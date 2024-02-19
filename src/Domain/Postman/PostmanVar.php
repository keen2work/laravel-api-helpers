<?php


namespace EMedia\Api\Domain\Postman;

// Refer to
// https://learning.postman.com/docs/writing-scripts/script-references/variables-list/
use EMedia\Api\Docs\Param;

class PostmanVar
{

	// Pre-defined

	/** Registered/login user to test the logins.
	 *  This should not be the same as the `test_user`
	 *  Because logging-in will can reset the `access_token`
	 */
	public const REGISTERED_USER_EMAIL 	= 'login_user_email';
	public const REGISTERED_USER_PASS 	= 'login_user_pass';

	/**
	 *  Use the test_user to test API calls with an access token
	 */
	public const TEST_USER_EMAIL = 'test_user_email';

	// Common
	public const UUID = '$guid';

	// Names
	public const LAST_NAME 	= '$randomLastName';
	public const FIRST_NAME = '$randomFirstName';

	// Domains, Emails, Usernames
	public const EXAMPLE_EMAIL = '$randomExampleEmail';
	public const EMAIL = '$randomEmail';

	// Phone, Address and Location
	public const PHONE = '$randomPhoneNumber';

	// Grammar
	public const PHRASE = '$randomPhrase';

	// Images
	/** Add a random image. This will only work on server-side. */
	public const RANDOM_IMAGE_FILE = 'random_image_file';

	// TODO: add other variables

	/**
	 *
	 * Map Postman Dynamic variable names to faker variable names
	 * See list at
	 * https://learning.postman.com/docs/writing-scripts/script-references/variables-list/
	 *
	 * @param array $param
	 * @return string
	 */
	public static function postmanToFaker(array $param): string
	{
		$varName = $param['param_value'];

		// handle data types
		switch ($param['type']) {
			case Param::TYPE_BOOLEAN:
				return ($param['param_value']) ? 'true': 'false';
				break;
		}

		switch ($varName) {
			case self::UUID:
				return '$faker->uuid';
				break;
			case self::FIRST_NAME:
				return '$faker->firstName';
				break;
			case self::LAST_NAME:
				return '$faker->lastName';
				break;
			case self::EXAMPLE_EMAIL:
			case self::EMAIL:
				return '$faker->safeEmail';
				break;
			case self::PHONE:
				return '$faker->phoneNumber';
				break;
			case self::PHRASE:
				return '$faker->sentence';
				break;
			case self::RANDOM_IMAGE_FILE:
				return '\Illuminate\Http\UploadedFile::fake()->image(\'image.jpg\')';
				break;
			default:
				// remove $ sign to avoid conflicts
				return "'" . ltrim($varName, " \t\n\r\0\x0B$") . "'";
		}
	}
}
