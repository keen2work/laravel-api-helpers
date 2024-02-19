# API Helpers for Laravel

This package adds the following.

- Custom request responses.
- Auto-generated API tests
- HTML API Specs
- Swagger configurations 
- Postman collections
- Postman environments

[Watch the Demo Video Here](https://www.dropbox.com/s/f2gi684aidiycbj/20200729-API%20Generation%20Demo.mp4?dl=0)

### Version Compatibility

| Laravel Version | Api Helpers Version |    Branch    |
|-----------------|:-------------------:|:------------:|
| v10             |         6.x         |    master    |
| v9              |         5.x         |     5.x      |  
| v8              |         4.x         |    4.x			    |  
| v7              |        3.1.x        | version/v3.x |

See [CHANGELOG](CHANGELOG.md) for past versions.

## Install
Add the repository to `composer.json`
```
"repositories": [
	{
	    "type":"vcs",
	    "url":"git@bitbucket.org:elegantmedia/laravel-api-helpers.git"
	}
]
```

```
composer require emedia/api
```

On your `.env` and `.env.example` files, add these values
```
APP_ENV=testing
APP_SANDBOX_URL=https://sandbox-project-url.preview.cx
APP_SANDBOX_API_KEY="123-123-123-123"
API_SAVE_TEST_RESPONSES=true
```

The package will be auto-discovered. For version compatibility with past Laravel versions, see `CHANGELOG.md`.

## System Requirements

These must be available in your system before continuing.

- [ApiDocs.js](http://apidocjs.com/)
- [PHP CS Fixer](https://github.com/FriendsOfPHP/PHP-CS-Fixer)

## Usage

### Success
```
return response()->apiSuccess($transaction, $optionalMessage);
```

This will return a JSON response as,
```
{
	payload: {transactionObject or Array},
	message: '',
	result: true
}
```

### Returning a paginated response

Do this because you may need to attach a message and the result type to the message.
```
$users = User::paginate(20);
return response()->apiSuccessPaginated($users, $optionalMessage);
```

Returns
```
{
	message: 'Optional message',
	payload: [ array of objects],
	paginator: { paginationObject },
	result: true
}
```

### Unauthorized - 401, General Error - 403 (Forbidden)

```
return response()->apiUnauthorized($optionalMessage);
return response()->apiAccessDenied($optionalMessage);
```

Returns (Status code: 401 or 403)
```
{
	message: 'Optional message',
	result: false
}
```

### Generic error
```
return response()->apiError($optionalMessage, $optionalData, $optionalStatusCode);
```

Returns (Unprocessable Entity - 422 by default)
```
{
	message: 'Optional message',
	payload: {object or array},
	result: false
}
```

## Documentation Builder

To allow auto-generation of docs, you need to call the `document()` function immediately after the functions.

For example, look at the `register()` method in `AuthController` below.

```
use EMedia\Api\Docs\APICall;
use EMedia\Api\Docs\Param;
use EMedia\Api\Domain\Postman\PostmanVar;

/**
 *
 * Sign up a user
 *
 * @param Request $request
 *
 * @return \Illuminate\Http\JsonResponse
 * @throws \Illuminate\Validation\ValidationException
 */
public function register(Request $request)
{
    document(function () {
        return (new APICall)
            ->setName('Register')
            ->setParams([
                (new Param('device_id', 'string', 'Unique ID of the device')),

                // define a parameter as a Param object
                // (new Param('device_type', 'string', 'Type of the device `APPLE` or `ANDROID`')),

                // or you can define it as a string
                // the line below is the same as the one above
                'device_type|string|Type of the device `APPLE` or `ANDROID`',

                (new Param('device_push_token', 'String', 'Unique push token for the device'))->optional(),

                // you can set additional data within the string
                // if a data type is not set, it will default to `string`
                // the first item must be the field name. The order of other items doesn't matter
                'first_name|{{$randomExampleEmail}}|example:Joe|optional',

                (new Param('last_name'))->setDefaultValue('Johnson')->optional(),

                // You can set Postman environment variables for parameters
                // so they can be created dynamically when testing
                // all fields below for the `phone` field does the same thing
                'phone|optional|{{$randomPhoneNumber}}',
                // 'phone|optional|variable:{{$randomPhoneNumber}}',
                // 'phone|optional|variable:$randomPhoneNumber',
                // (new Param('phone'))->optional()->setVariable('$randomPhoneNumber'),
                // (new Param('phone'))->optional()->setVariable('{{$randomPhoneNumber}}'),
                // (new Param('phone'))->optional()->setVariable(PostmanVar::PHONE),

                (new Param('email'))->setVariable('{{$randomExampleEmail}}'),

                (new Param('password', Param::TYPE_STRING,
                    'Password. Must be at least 6 characters.'))->setDefaultValue('123456'),
                (new Param('password_confirmation'))->setDefaultValue('123456'),
                
                // set array parameters
				(new Param('staff_id', 'array', 'An array of IDs of staff'))
					->setArrayType(ParamType::STRING),
            ])
            ->setApiKeyHeader()
            ->setSuccessObject(\App\User::class)
            ->setErrorExample('{
                "message": "The email must be a valid email address.",
                "payload": {
                    "errors": {
                        "email": [
                            "The email must be a valid email address."
                        ]
                    }
                },
                "result": false
            }', 422);
    });

    $this->validate($request, [
        // add validation rules
    ]);

    // add function logic

    // return a single object
    $responseData = []
    return response()->apiSuccess($responseData);
}
```

The above example defines and returns a single object. If you want to return a paginated list of objects, use the pagination methods instead.

Example
```
	// definition
	...
		->setGroup('Properties')
		->setSuccessPaginatedObject(Property::class)
		->setSuccessExample('')
	...

	// response
	$paginator = Property::paginate();
	return response()->apiSuccessPaginated($paginator);
```

### Define Additional Response Fields

Swagger depends on the response fields that you send back. This generator script will read the Models and build the fields automatically. If you'd like to define any custom fields, add them to the Model with the `getExtraApiFields()` method. Note how you can add arrays and objects. This will be helpful when adding related entities as nested objects.

Example: Add 4 new fields to the response object as `is_active`, `access_token`, `author` and `comments` with different data types.
```
class User extends Authenticatable
{

	public function getExtraApiFields()
	{
		return [
			'is_active' => 'boolean',
			'access_token',	// if the data type is not given, it will default to `string`

            // specify data type and formats (Swagger Data Types)
            // https://swagger.io/docs/specification/data-models/data-types/
            'latitude' => [ 
                'type' => 'number',
                'format' => 'double'
            ],

			'author' => ['type' => 'object', 'items' => 'User'],	// User object should be exposed to API generator in a separate endpoint eg: /users
			'comments' => ['type' => 'array', 'items' => 'Comment'], // User object should be exposed to API generator in a separate endpoint eg: /post/:id/comments
		];
	}

}
```

### Define Custom Type Maps (Optional)

While generating documentation, The generator using `Doctrine\DBAL\Platforms` to get `Type Maps`. In this situation you may get 
**following error message**, If you have some **unsupported column types** in the model.

```
[Doctrine\DBAL\DBALException]
Unknown database type point requested, Doctrine\DBAL\Platforms\MySQL57Platform may not support it.
```

To fix above issue, You need to add `Custom Type Maps` in your `Config/database.php` file.

```php
// Add following `doctrine_type_maps` key to the main array.

'doctrine_type_maps' => [
    'point' => 'string' // 'column_type' => 'mapping type'
]
```


After all API definitions are included, call this command. It will run the tests and create documents.
```
php artisan generate:docs-tests
```

If you don't want to run the tests, you can only create the docs with:
```
php artisan generate:docs
```

## Overriding auto-generated files

You can manually override responses and Test files. Just crate a `manual` folder and add the file with a same name there.

For example:
If you don't want the test `tests/Feature/AutoGen/API/V1/MyTestAPI.php` to be created, manually create a file called `tests/Feature/Manual/API/V1/MyTestAPI.php`

## Notes About Documentation

- The `APICall` and `APIParam` classes will use many defaults, and guess the HTTP method types, group names etc. But they can be customised. See the auto-completion methods with an IDE such as PHPStorm.
- The `APICall` will auto-insert the authenticated user headers with `x-access-token`. If you don't want this to be included, call `noDefaultHeaders()` method and then specify your own headers with `setHeaders()`.
- The `swagger.json` file will also contain model definitions if the models are included in the `app\Entities` folder. You can disable this feature from `config/oxygen.php` file. You can also hide unwanted model definitions from there.

## Postman Collections and Environments

- The generator will output a Postman Collection file and a separate Environment file. These files can be directly imported to Postman to fast and easy testing of the API.
- See the videos below for more information on this feature.
- [Importing API as a Postman Collection](https://youtu.be/WQwYNu4PCpg?t=73)
- [Postman Environments](https://youtu.be/M3QAjLTqC9c)

## Common Issues

- Most of the time, when there's an error, it will tell you exactly why it happened on screen, and most of the time what you should do. So please read it first and look for a solution there.
- When there's an error, it will show the line of file where the error happened. Also look for the line on screen just before an error was shown.

- Apidoc generation fails. 
    - Try running it manually with `apidoc -i resources/docs/apidoc -o public_html/docs/api`

- PHPUnit error when calling `php artisan generate:docs-tests`. 
    - If it's an error when running the test, you should fix your code that generated the error. 
    - The actual HTTP response will be saved in `resources/docs/api_responses/auto_generated`
    - Run phpunit with `./vendor/bin/phpunit` and resolve the error first

- Binary data on POST/PUT Requests

- CSRF validation error. If your API routes have CSRF protection, switch your `ÀPP_ENV` to `APP_ENV=testing` to disable CSRF validation for API doc generation.

- Apidoc will attempt to log in as a user before hitting the API endpoints to document. If you need these routes to be called as a non-logged in user use `php artisan generate:docs --no-authenticate-web-apis`.

Due to [Symfony and Laravel limitations](https://github.com/laravel/framework/issues/13457#issuecomment-239451567), you cannot send binary data (such as images) to a PUT endpoint. So either use a POST request with `_method=put` or don't use PUT requests with binary data.

## Contributing

- Found a bug? Report as an issue and submit a pull request.
- Please see [CONTRIBUTING](CONTRIBUTING.md) and for details.

Copyright (c) Elegant Media.
