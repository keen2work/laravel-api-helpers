<?php


namespace EMedia\Api\Domain\FileGenerators\Postman;

use EMedia\Api\Domain\Traits\HasAttributes;
use EMedia\Api\Domain\Traits\NamesAndPathLocations;
use Illuminate\Support\Collection;
use Symfony\Component\Yaml\Yaml;

/**
 * @property $schema
 * @property $env
 */
class PostmanCollectionReader
{
	use NamesAndPathLocations;
	use HasAttributes;

	public function __construct()
	{
		$this->readSchemaFile();
		$this->readEnvironmentFile();

		$this->getPathData();
	}

	/**
	 *
	 * Read the schema. We read Postman collection, because it has dynamic variables
	 *
	 */
	public function readSchemaFile()
	{
		$path = self::getDocsOutputDir();

		$this->schema = Yaml::parseFile($path.DIRECTORY_SEPARATOR.'postman_collection.yml');
	}

	/**
	 *
	 * This will have variable values for known settings
	 *
	 */
	public function readEnvironmentFile()
	{
		$path = self::getDocsOutputDir();

		$envFilePath = $path.DIRECTORY_SEPARATOR.'postman_environment_local.json';

		$content = file_get_contents($envFilePath);

		$content = json_decode($content, true);

		if (isset($content['values'])) {
			$this->env = $content['values'];
		} else {
			// no values on the env file
			throw new \InvalidArgumentException("No values found on the environment file {$envFilePath}");
		}
	}

	/**
	 *
	 * Get API version from path
	 *
	 * @return mixed|string
	 */
	public function getPathVersion()
	{
		$basePath = $this->schema['basePath'];

		// look for a path version on 'basePath'
		// something like v1, v2, v3 etc.
		preg_match('/(v\d+)/', $basePath, $matches);

		if (count($matches) > 0) {
			return $matches[0];
		}

		return '';
	}

	public function getPathData()
	{
		$output = [];

		foreach ($this->schema['paths'] as $pathKey => $pathOperations) {
			$fullPath = $this->getBaseUri($pathKey);

			foreach ($pathOperations as $method => $methodValues) {
				$parameters = $methodValues['parameters'];

				if (isset($methodValues['security'])) {
					foreach ($methodValues['security'] as $secRule) {
						foreach ($secRule as $key => $secParamName) {
							$param = $this->getSecurityParamByName($key);
							if ($param) {
								$parameters[] = $param;
							}
						}
					}
				}

				$paramsCollection = collect($parameters);
				$paramsCollection = $this->parseParamEnvVariables($paramsCollection);

				$paramData = [
					'uri' => $fullPath,
					'method' => $method,
					'tags' => $methodValues['tags'] ?? null,
					'summary' => $methodValues['summary'] ?? null,
					'description' => $methodValues['description'] ?? '',
					'operationId' => $methodValues['operationId'] ?? '',
					'parameters' => $parameters,
					'param_locations' => $paramsCollection->groupBy('in')->toArray(),
				];

				$output[] = $paramData;
			}
		}

		return $output;
	}

	/**
	 *
	 * Convert Postman variables to actual values
	 *
	 * @param Collection $params
	 * @return Collection
	 */
	public function parseParamEnvVariables(Collection $params)
	{
		return $params->map(function ($param) {
			$paramValue = '';

			if (isset($param['example'])) {
				$paramValue = $this->parseEnvVariable($param['example']);
			} elseif (isset($param['schema']['example'])) {
				$paramValue = $this->parseEnvVariable($param['schema']['example']);
			}

			$param['param_value'] = $paramValue;

			return $param;
		});
	}

	/**
	 *
	 * Parse a variable and return the value
	 *
	 * @param $string
	 * @return string
	 */
	protected function parseEnvVariable($string)
	{
		// if no variable, return the string
		if (strpos($string, '{{') === false) {
			return $string;
		}

		// strip the braces
		$string = trim($string, " \t\n\r\0\x0B{}");

		foreach ($this->env as $env) {
			if ($env['key'] === $string) {
				$string = $env['value'];
				break;
			}
		}

		return $string;
	}

	/**
	 *
	 * Find security definition by a nem
	 *
	 * @param $keyName
	 * @return |null
	 */
	public function getSecurityParamByName($keyName)
	{
		if (empty($this->schema['securityDefinitions'])) {
			return null;
		}

		foreach ($this->schema['securityDefinitions'] as $key => $definition) {
			if ($key === $keyName) {
				return $definition;
			}
		}

		return null;
	}

	public function getBaseUri($pathStr = null)
	{
		$output = '';

		//		if (is_array($this->schema['schemes'])) {
		//			$output .= $this->parseEnvVariable($this->schema['schemes'][0]);
		//		} else {
		//			$output .= $this->parseEnvVariable($this->schema['schemes']);
		//		}
		//		$output .= '://';
		//		$output .= $this->parseEnvVariable($this->schema['host']);
		$output .= $this->parseEnvVariable($this->schema['basePath']);

		if ($pathStr) {
			$output .= $this->parseEnvVariable($pathStr);
		}

		return $output;
	}
}
