<?php


namespace EMedia\Api\Docs;

class ParamNames
{
	public const X_API_KEY 		= 'x-api-key';
	public const X_ACCESS_TOKEN = 'x-access-token';

	public const SECURITY_PARAMS_NAMES = [
		self::X_API_KEY,
		self::X_ACCESS_TOKEN,
	];

	public const ROOT_LEVEL_SECURITY_PARAMS = [
		self::X_API_KEY,
	];
}
