<?php


namespace EMedia\Api\Console\Commands;

use App\Entities\Auth\UsersRepository;
use ElegantMedia\PHPToolkit\Dir;
use ElegantMedia\PHPToolkit\Exceptions\FIleSystem\DirectoryNotCreatedException;
use EMedia\Api\Docs\APICall;
use EMedia\Api\Docs\Param;
use EMedia\Api\Docs\ParamType;
use EMedia\Api\Domain\FileGenerators\OpenApi\V3\OpenApiSchema;
use EMedia\Api\Domain\FileGenerators\Postman\PostmanCollectionBuilder;
use EMedia\Api\Domain\FileGenerators\Postman\PostmanEnvironment;
use EMedia\Api\Domain\FileGenerators\Swagger\SwaggerV2;
use EMedia\Api\Domain\FileGenerators\Transformers\APICallTransformer;
use EMedia\Api\Domain\ModelDefinition;
use EMedia\Api\Domain\Traits\NamesAndPathLocations;
use EMedia\Api\Domain\Vendors\ApiDoc;
use EMedia\Api\Exceptions\APICallsNotDefinedException;
use EMedia\Api\Exceptions\DocumentationModeEnabledException;
use EMedia\Api\Exceptions\UndocumentedAPIException;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\Process\Exception\ProcessFailedException;

class GenerateDocsCommand extends Command
{
	use NamesAndPathLocations;

	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'generate:docs
								{--login-user-id=3 : User ID to access login of API}
								{--login-user-pass=12345678 : Password for the Login User}
								{--no-authenticate-web-apis : Do not log in with "login-user-id" (web route) before generating the API docs. }
								{--test-user-id=4 : User ID of the test user}
								{--no-apidoc : Do not run api docs}
								{--no-files-output : Do not show generated files}
								{--reset : Reset and start a new instance}
								';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Generate API Documentation';

	/**
	 * The router instance.
	 *
	 * @var \Illuminate\Routing\Router
	 */
	protected $router;

	/**
	 * An array of all the registered routes.
	 *
	 * @var \Illuminate\Routing\RouteCollection
	 */
	protected $routes;

	protected $docBuilder;

	protected $loginUser;
	protected $testUser;

	protected $modelDefinitionNames = [];

	protected $docsFolder;

	protected $createdFiles = [];

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct(Router $router)
	{
		parent::__construct();

		$this->router = $router;
		$this->routes = $router->getRoutes();
	}

	/**
	 *
	 * Execute the console command.
	 *
	 */
	public function handle()
	{
		if (!$this->checkIsAllowedToRun()) {
			$this->error('Aborting...');
			return false;
		}

		$this->docsFolder = public_path('docs');
		Dir::makeDirectoryIfNotExists($this->docsFolder);

		putenv('DOCUMENTATION_MODE=true');
		$this->docBuilder = app('emedia.api.builder');

		// reset and start again
		if ($this->option('reset')) {
			$this->docBuilder->reset();
			$this->createdFiles = [];
		}

		$this->defineDefaultHeaders();
		try {
			$this->hitRoutesAndLoadDocs();
			$this->createDocSourceFiles();
			$this->createSwaggerJson('api');
			$this->createSwaggerJson('postman');

			if (!$this->option('no-files-output')) {
				$this->table(['Generated File', 'Path'], $this->createdFiles);
				$this->info('');
			}

			if (!$this->option('no-apidoc')) {
				if (ApiDoc::isInstalled()) {
					try {
						$process = ApiDoc::compile();
						$this->info($process->getOutput());
						$this->info('ApiDoc compiled. Done.');
					} catch (ProcessFailedException $e) {
						$this->error($e->getMessage());
						$this->error("ApiDoc generation failed. Try running it manually.");
					}
				}
			}
		} catch (UndocumentedAPIException $ex) {
			$this->error($ex->getMessage());
		} catch (APICallsNotDefinedException $ex) {
			$this->error('No APICalls found on controllers. Add APICalls with document() functions before trying again. Aborting...');
		} catch (\BadMethodCallException $ex) {
			// Exception caught earlier, nothing to do here
		} catch (MethodNotAllowedHttpException $ex) {
			// Exception caught earlier, nothing to do here
		} catch (ValidationException $ex) {
			$this->error('ValidationException detected. Have you documented this API?');
			$this->error($ex->getMessage());
		}
	}

	/**
	 *
	 * Check if you're allowed to run the command
	 *
	 * @return bool
	 */
	protected function checkIsAllowedToRun(): bool
	{
		// startup checks
		if (app()->environment('production')) {
			$this->error('Application in production environment. This command cannot be run in this environment.');
			return false;
		}

		// get the test user
		$testUser = (app(UsersRepository::class))->find($this->option('test-user-id'));
		if (!$testUser) {
			$this->error('A user with an ID of ' . $this->option('test-user-id') . ' is not found. Ensure this user exists on database or provide a new user user with the `--test-user-id` option.');
			return false;
		}
		$this->testUser = $testUser;

		// you need a separate user to test logins
		// because after you login, the `x-access-token` will reset
		$loginUser = (app(UsersRepository::class))->find($this->option('login-user-id'));
		if (!$loginUser) {
			$this->error('A user with an ID of ' . $this->option('login-user-id') . ' is not found. Ensure this user exists on database or provide a new user user with the `--login-user-id` option.');
			return false;
		}
		$this->loginUser = $loginUser;

		if (is_countable($this->routes) && count($this->routes) === 0) {
			$this->error("Your application doesn't have any routes.");
			return false;
		}
		// end start-up checks

		return true;
	}

	/**
	 *
	 * Define the default headers for all routes
	 *
	 */
	protected function defineDefaultHeaders()
	{
		try {
			document(function () {
				return (new APICall)->setDefine('default_headers')
					->setHeaders([
						(new Param('Accept', ParamType::STRING, 'Set to `application/json`'))->setDefaultValue('application/json'),
						(new Param('x-api-key', ParamType::STRING, 'API Key')),
						(new Param('x-access-token', ParamType::STRING, 'Unique user authentication token')),
					]);
			});
		} catch (DocumentationModeEnabledException $ex) {
			// Do Nothing
			// This exception will always be thrown while in documentation mode
		}
	}

	/**
	 *
	 * Hit the API routes and load the defined route documentation to memory
	 *
	 * @throws UndocumentedAPIException
	 */
	protected function hitRoutesAndLoadDocs()
	{
		// filter the API routes from other HTTP routes
		$apiRoutes = new Collection();
		foreach ($this->routes as $route) {
			if (strpos($route->uri(), 'api') === 0) {
				$apiRoutes->push($route);
			}
		}

		// unless disabled, login as a user before hitting any routes
		if (!$this->option('no-authenticate-web-apis')) {
			Auth::loginUsingId($this->option('login-user-id'));
		}

		foreach ($apiRoutes as $route) {
			// split route info from a Route
			$routeInfo = $this->getRouteInformation($route);

			// set interceptor
			$this->docBuilder->setInterceptor($routeInfo['method'], $routeInfo['uri'], $routeInfo['action']);

			try {
				// output
				$this->info('Sending ' . $routeInfo['method'] . ' request to ' . $routeInfo['url'] . '...');

				// hit the route, and get the exception
				$this->callRoute($routeInfo['method'], $routeInfo['url']);

				// if the documentation is defined, we should not reach here
				// so throw a new exception
				throw new UndocumentedAPIException("Route {$routeInfo['url']} does not have an API documented. You need to add the `document()` method on this route for {$routeInfo['method']} call.");
			} catch (DocumentationModeEnabledException $ex) {
				// do nothing.
				// The exception will always be thrown while in documentation mode
			} catch (\BadMethodCallException $ex) {
				$this->error('Route error on ' . $routeInfo['url']);
				$this->error($ex->getMessage());

				throw $ex;
			} catch (MethodNotAllowedHttpException $ex) {
				$this->error('Route error accessing ' . $routeInfo['url']);
				$this->error('Have you checked your middleware?');
				$this->error(MethodNotAllowedHttpException::class);

				throw $ex;
			}
		}

		// check if we found any defined calls
		$items = $this->docBuilder->getApiCalls();
		$itemCount = is_countable($items) ? count($items) : 0;

		$this->info("");
		$this->info("API Doc Builder found {$itemCount} defined APICalls.");

		if (!$itemCount) {
			throw new APICallsNotDefinedException();
		}
	}

	protected function getOutputFilePath($fileName)
	{
		return $this->docsFolder . DIRECTORY_SEPARATOR . $fileName;
	}

	/**
	 *
	 * Create swagger 2.0 json file
	 * @param string $type
	 * @throws \EMedia\Api\Exceptions\FileGenerationFailedException
	 * @throws \Illuminate\Contracts\Filesystem\FileExistsException
	 * @throws \ReflectionException
	 */
	protected function createSwaggerJson($type = 'api'): void
	{
		if (!in_array($type, ['api', 'postman'])) {
			throw new \InvalidArgumentException("The given type $type is an invalid argument");
		}

		$items = $this->docBuilder->getApiCalls();

		// TODO: don't hardcode the version
		$basePath = '/api/v1';

		$modelDefinition = new ModelDefinition();
		$allDefinitions = $modelDefinition->getAllDefinitions();

		// Postman environment
		$postmanEnvironment = new PostmanEnvironment();

		// Swagger Config
		$swaggerConfig = new SwaggerV2();
		$swaggerConfig->setBasePath($basePath);
		$swaggerConfig->setServerUrl($this->getenv('APP_URL'));

		foreach ($items as $item) {
			/** @var APICall $item */

			$route = $item->getRoute();
			if (empty($route)) {
				continue;
			}

			// method can be get/post/put/delete/head
			$method = strtolower($item->getMethod());

			$parameters = [];

			// set parameters
			/** @var Param $param */
			$params = $item->getParams();
			$headers = $item->getHeaders();

			$allParams = (new Collection())->merge($headers)->merge($params);

			// check for API use calls and merge the headers
			foreach ($item->getUse() as $useName) {
				/** @var APICall $childApiCalls */
				$childApiCalls = $this->docBuilder->findByDefinition($useName);
				if ($childApiCalls) {
					$allParams = $allParams->merge($childApiCalls->getParams())->merge($childApiCalls->getHeaders());
				}
			}

			// split the security tokens
			$securityDefinitions = [];
			$filteredParams = new Collection();
			foreach ($allParams as $param) {
				if ($param->getLocation() === Param::LOCATION_HEADER) {
					$fieldName = strtolower($param->getName());
					if (in_array($fieldName, ['x-api-key', 'x-access-token'])) {
						if ($fieldName === 'x-api-key') {
							$securityDefinitions[] = ['apiKey' => []];
							// if ($type === 'api')
							continue;
						}
						if ($fieldName === 'x-access-token') {
							$securityDefinitions[] = ['accessToken' => []];
							// if ($type === 'api')
							continue;
						}
					}
				}

				$filteredParams->push($param);
			}

			foreach ($filteredParams as $param) {
				$dataType = $param->getDataType();

				// if this is an array, skip it
				// because it should be captured by the array's fields anyway
				// if ($dataType === 'Array') {
				// 	continue 1;
				// }

				// if the parameter contains a `.`, it's a data array
				$name = '';
				$nameParts = explode('.', $param->getName());
				$namePartsCount = is_countable($nameParts) ? count($nameParts) : 0;
				if ($namePartsCount <= 1) {
					$name = $nameParts[0];
				} else {
					// rewrite nested variable names
					// clinics.staff.dogs.name
					// clinics[0] [staff][0] [dogs][0] [name]
					for ($i = 0, $iMax = $namePartsCount; $i < $iMax; $i++) {
						if ($i === 0) {
							$name = $nameParts[$i] . '[0]';
						} elseif ($i === $iMax - 1) {
							$name .= '[' . $nameParts[$i] . ']';
						} else {
							$name .= '[' . $nameParts[$i] . '][0]';
						}
					}
				}

				// if `produces` is the same, we don't have to set the header again
				// if ($name === 'Accept' && $param->getDefaultValue() === 'application/json') {
				//	continue;
				// }

				$location = $param->getLocation();
				if ($location === null) {
					$location = $method === 'get' ? Param::LOCATION_QUERY : Param::LOCATION_FORM;
				}

				if ($dataType === 'Model' && $model = $param->getModel()) {
					$paramData = [
						'name'        => 'body',
						'in'          => 'body',
						'required'    => $param->getRequired(),
						'description' => $param->getDescription(),
						'schema'      => [
							'$ref' => '#/definitions/' . ModelDefinition::getModelShortName($model),
						],
					];
				} else {
					$paramData = [
						'name'        => $name,
						'in'          => $location,
						'required'    => $param->getRequired(),
						'description' => $param->getDescription(),
						'type'        => strtolower($dataType),
						// 'schema'      => [],
					];

					if ($paramData['type'] === Param::TYPE_ARRAY) {
						$paramData['collectionFormat'] = $param->getCollectionFormat();
						$paramData['items'] = $param->getItems();
					}

					// set the variable slots for postman
					if ($type === 'postman') {
						$variable = (string)$param->getVariable();
						if (empty($variable)) {
							$variable = $param->getDefaultValue();
						}
						if (empty($variable)) {
							$variable = $param->getExample();
						}

						if (!empty($variable)) {
							if ($location === Param::LOCATION_FORM) {
								$paramData['example'] = $variable;
							} else {
								$paramData['schema']['type'] = strtolower($dataType);
								$paramData['schema']['example'] = $variable;
							}
						}
					}
				}

				$parameters[] = $paramData;

				// add variable to Environment file
				$postmanEnvironment->addVariable($name, $param->getDefaultValue());
			}

			$pathSuffix = str_replace(ltrim($basePath, '/'), '', $route);

			$consumes = $item->getConsumes();
			if (empty($consumes)) {
				$consumes[] = APICall::CONSUME_JSON;
				$consumes[] = APICall::CONSUME_FORM_URLENCODED;
				// $consumes[] = APICall::CONSUME_MULTIPART_FORM;
			}

			// build success responses
			$successObject = $item->getSuccessObject();
			$successPaginatedObject = $item->getSuccessPaginatedObject();
			$responseObjectName = str_replace(
				['/', ' '],
				'',
				ucwords($item->getGroup()) . ucwords($item->getName())
			) . 'Response';
			if ($successObject || $successPaginatedObject) {
				if ($successObject) {
					$successResponse = $modelDefinition->getSuccessResponseDefinition($responseObjectName, $successObject);
				}

				if ($successPaginatedObject) {
					$successResponse = $modelDefinition->getSuccessResponsePaginatedDefinition($responseObjectName, $successPaginatedObject);
				}

				if (isset($allDefinitions[$responseObjectName])) {
					$error = "Definition $responseObjectName already exists. Change the method group or name to be unique.";
					$this->error($error);
					throw new \Exception($error);
				}

				$allDefinitions = array_merge($allDefinitions, $successResponse);
			} else {
				// generic success response
				$responseObjectName = 'SuccessResponse';
			}

			$pathData = [
				'tags' => [
					$item->getGroup(),
				],
				'summary' => $item->getName(),
				'consumes' => $consumes,
				'produces' => ['application/json'],
				'operationId' => $item->getOperationId(),
				'description' => $item->getDescription() ?? '',
				'parameters' => $parameters,
				'security' => $securityDefinitions,
				'responses' => [
					'200' => [
						'schema' => [
							'$ref' => "#/definitions/$responseObjectName",
						],
						'description' => $responseObjectName,
					],
					'401' => [
						'schema' => [
							'$ref' => '#/definitions/ApiErrorUnauthorized',
						],
						'description' => 'Authentication failed',
					],
					'403' => [
						'schema' => [
							'$ref' => '#/definitions/ApiErrorAccessDenied',
						],
						'description' => 'Access denied',
					],
					'422' => [
						'schema' => [
							'$ref' => '#/definitions/ApiError',
						],
						'description' => 'Generic API error. Check `message` for more information.',
					],
				],
			];

			$swaggerConfig->addPathData($pathSuffix, $method, $pathData);
		}

		$swaggerConfig->addToSchema('definitions', $allDefinitions);

		if ($type === 'postman') {
			$this->writePostmanEnvironments($postmanEnvironment);

			// create postman collection JSON file
			$outputPath = $this->getOutputFilePath('postman_collection.json');
			$swaggerConfig->writeOutputFileJson($outputPath);
			$this->createdFiles[] = ['Postman Collection (JSON)', $this->stripBasePath($outputPath)];

			$postmanCollection = new PostmanCollectionBuilder();
			$postmanCollection->setSchema($swaggerConfig->getSchema());
			$outputPath = $this->getOutputFilePath('postman_collection.yml');
			$postmanCollection->writeOutputFileYaml($outputPath);
			$this->createdFiles[] = ['Postman Collection (YAML)', $this->stripBasePath($outputPath)];
		} else {
			// if there is a sandbox URL, we should use that as the host
			if ($this->getenv('APP_SANDBOX_URL') !== false) {
				$swaggerConfig->setServerUrl($this->getenv('APP_SANDBOX_URL'));
				$swaggerConfig->setSchemes(['http', 'https']);
			}

			// create swagger YAML file
			$outputPath = $this->getOutputFilePath('swagger.yml');
			$swaggerConfig->writeOutputFileYaml($outputPath);
			$this->createdFiles[] = ['Swagger v2 (YAML)', $this->stripBasePath($outputPath)];

			// create swagger JSON file
			$outputPath = $this->getOutputFilePath('swagger.json');
			$swaggerConfig->writeOutputFileJson($outputPath);
			$this->createdFiles[] = ['Swagger v2 (JSON)', $this->stripBasePath($outputPath)];
		}
	}


	/**
	 *
	 * Write Postman Environment Files
	 *
	 * @param PostmanEnvironment $postmanEnvironment
	 * @throws \EMedia\ApiBuilder\Exceptions\FileGenerationFailedException
	 * @throws \Illuminate\Contracts\Filesystem\FileExistsException
	 * @throws \EMedia\Api\Exceptions\FileGenerationFailedException
	 */
	protected function writePostmanEnvironments(PostmanEnvironment $postmanEnvironment): void
	{
		$postmanEnvironment->addVariable('login_user_email', $this->loginUser->email);
		$postmanEnvironment->addVariable('login_user_pass', $this->option('login-user-pass'));

		$postmanEnvironment->addVariable('test_user_email', $this->testUser->email);

		// Generate Local Environment Config
		if ($this->getenv('APP_ENV') === 'local' || $this->getenv('APP_ENV') === 'testing') {
			$filePath = $this->docsFolder . DIRECTORY_SEPARATOR . 'postman_environment_local.json';
			$postmanEnvironment->setName(sprintf("%s Environment (LOCAL)", config('app.name')));
			$postmanEnvironment->setServerUrl($this->getenv('APP_URL'));

			if ($this->getenv('API_KEY') !== false) {
				$postmanEnvironment->addVariable('x-api-key', getenv('API_KEY'));
				$postmanEnvironment->addVariable('x-access-token', $this->getDefaultUserAccessToken());
			}

			$postmanEnvironment->writeOutputFileJson($filePath);
			$this->createdFiles[] = ['Postman Environment (LOCAL)', $this->stripBasePath($filePath)];

			// remove the variables after done
			$postmanEnvironment->removeVariable('x-access-token');
		} else {
			$this->error("Failed to create Local Environment file. Run this on a `local` environment.");
		}

		// Generate Sandbox Environment Config
		if ($this->getenv('APP_SANDBOX_URL') === false) {
			$this->info("`APP_SANDBOX_URL` not found in your `.env`. Sandbox Environment file not generated.");
		} else {
			$postmanEnvironment->setName(sprintf("%s Environment (SANDBOX)", config('app.name')));
			$filePath = $this->docsFolder . DIRECTORY_SEPARATOR . 'postman_environment_sandbox.json';
			$postmanEnvironment->setServerUrl($this->getenv('APP_SANDBOX_URL'));

			// force https on sandbox URLs to prevent people doing stupid things
			$postmanEnvironment->addVariable('scheme', 'https');

			if ($this->getenv('APP_SANDBOX_API_KEY') !== false) {
				$postmanEnvironment->addVariable('x-api-key', getenv('APP_SANDBOX_API_KEY'));
			}

			$postmanEnvironment->writeOutputFileJson($filePath);
			$this->createdFiles[] = ['Postman Environment (SANDBOX)', $this->stripBasePath($filePath)];
		}

		$this->createdFiles[] = ['---', '---'];
	}

	/**
	 *
	 * Try to get env variables. Try laravel's function first.
	 *
	 * @param $name
	 * @return array|false|mixed|string
	 */
	protected function getenv($name)
	{
		$var = env($name);

		if ($var) {
			return $var;
		}

		return getenv($name);
	}

	/**
	 *
	 * Create the documentation source files
	 *
	 * @throws DirectoryNotCreatedException
	 */
	protected function createDocSourceFiles(): void
	{
		$items = $this->docBuilder->getApiCalls();

		$docsFolder = self::getApiDocsAutoGenDir(true);

		self::deleteFilesInDirectory($docsFolder, 'coffee');

		foreach ($items as $item) {
			/** @var APICall $item */
			$outputFile = \Illuminate\Support\Str::snake($item->getGroup() . '.coffee');
			$outputPath = $docsFolder . DIRECTORY_SEPARATOR . $outputFile;

			$lines = [];
			$lines[] = "# ******************************************************** #";
			$lines[] = "#           AUTO-GENERATED. DO NOT EDIT THIS FILE.         #";
			$lines[] = "# ******************************************************** #";
			$lines[] = "#    Create your files in `resources/docs/apidoc/manual`   #";
			$lines[] = "# ******************************************************** #";
			$lines[] = $item->getApiDoc();
			$lines[] = '';
			file_put_contents($outputPath, implode("\r\n", $lines), FILE_APPEND);
		}

		$this->createdFiles[] = ['APIDoc Files', $this->stripBasePath($docsFolder)];
		$this->createdFiles[] = ['---', '---'];
	}


	/**
	 *
	 * Hit a route through application
	 *
	 * @param $method
	 * @param $url
	 */
	public function callRoute($method, $url)
	{
		$request = Request::create($url, $method);

		$apiKeys = env('API_KEY');
		$apiKey  = \Illuminate\Support\Arr::first(explode(',', $apiKeys));
		if (empty($apiKey)) {
			$this->error("AN API_KEY not found on `.env` file");
		}

		$request->headers->set('x-api-key', $apiKey);
		$request->headers->set('x-access-token', $this->getDefaultUserAccessToken());

		/** @var Response $response */
		/** @var \Illuminate\Contracts\Http\Kernel $kernel */
		$kernel = app()[\Illuminate\Contracts\Http\Kernel::class];
		$response = $kernel->handle($request);
		if ($response->exception) {
			throw $response->exception;
		}
	}

	/**
	 *
	 * Get an access-token for this user for API test
	 *
	 * @return mixed
	 */
	protected function getDefaultUserAccessToken()
	{
		if ($this->testUser) {
			$accessToken = app(\EMedia\Devices\Auth\DeviceAuthenticator::class)::getAnAccessTokenForUserId($this->testUser->id);
			if ($accessToken) {
				return $accessToken;
			}

			throw new \InvalidArgumentException("An access token not found for user with ID {$this->testUser->id}. Assign at least 1 device to this user. Look at your `DevicesTableSeeder` file.");
		}
	}


	/**
	 * Get the route information for a given route.
	 *
	 * @param  \Illuminate\Routing\Route  $route
	 * @return array
	 */
	protected function getRouteInformation(Route $route)
	{
		$methods = $route->methods();
		if (is_countable($methods) && count($methods) === 1) {
			$method = $methods[0];
		} else {
			if (in_array('GET', $methods)) {
				$method = 'GET';
			}
		}

		//		if ($route->uri() === 'api/v1/notifications/{uuid}/mark-read') {
		//			$p = $route->parameterNames();
		//			dd($p);
		//		}

		return [
			'host'   => $route->domain(),
			'method' => $method,
			'methods' => $route->methods(),
			'uri'    => $route->uri(),
			'url'	 => url($route->uri()),
			'name'   => $route->getName(),
			'action' => ltrim($route->getActionName(), '\\'),
			'middleware' => $this->getMiddleware($route),
			// 'controller' => $route->getController(),
			'parameter_names' => $route->parameterNames(),
		];
	}

	/**
	 * Get before filters.
	 *
	 * @param  \Illuminate\Routing\Route  $route
	 * @return string
	 */
	protected function getMiddleware($route)
	{
		return collect($route->gatherMiddleware())->map(function ($middleware) {
			return $middleware instanceof Closure ? 'Closure' : $middleware;
		})->implode(',');
	}

	protected function stripBasePath($path)
	{
		return str_replace(base_path(), '', $path);
	}
}
