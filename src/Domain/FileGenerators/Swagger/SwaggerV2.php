<?php


namespace EMedia\Api\Domain\FileGenerators\Swagger;

use EMedia\Api\Domain\FileGenerators\BaseFileGenerator;

class SwaggerV2 extends BaseFileGenerator
{
	public function __construct()
	{
		$this->schema = $this->getDefaultSchema();
	}

	/**
	 *
	 * Return the base template
	 * https://swagger.io/docs/specification/2-0/basic-structure/
	 *
	 * @return array
	 */
	protected function getDefaultSchema()
	{
		return [
			'swagger' => '2.0',
			'info' => [
				'title' => config('app.name') . ' API',
				// 'description' => 'The description',
				'version' => '1.0.0.' . date('Ymd'),
			],
			'host' => null,
			'schemes' => [],
			'basePath' => null,
			'paths' => [],
			'securityDefinitions' => [
				'apiKey' => [
					'type' => 'apiKey',
					'name' => 'x-api-key',
					'in' => 'header',
					'description' => 'API Key for application',
				],
				'accessToken' => [
					'type' => 'apiKey',
					'name' => 'x-access-token',
					'in' => 'header',
					'description' => 'Unique user authentication token',
				],
			],
		];
	}



	/**
	 *
	 * Set basePath of API
	 *
	 * @param $basePath
	 * @return $this
	 */
	public function setBasePath($basePath)
	{
		$this->addToSchema('basePath', $basePath);

		return $this;
	}

	public function setHost($host)
	{
		$this->addToSchema('host', $host);

		return $this;
	}

	/**
	 *
	 * Set the Server host and schemes from a URL for OpenApi 2 Spec
	 *
	 * @example https://api.example.com
	 * @example https://api.example.com/v1
	 *
	 * @param $serverUrl
	 * @return $this
	 */
	public function setServerUrl($serverUrl)
	{
		$this->setHost(parse_url($serverUrl, PHP_URL_HOST));
		$this->setSchemes([parse_url($serverUrl, PHP_URL_SCHEME)]);

		return $this;
	}


	/**
	 *
	 * Set schemes (protocols)
	 *
	 * @param $schemes
	 * @return $this
	 */
	public function setSchemes($schemes)
	{
		$this->addToSchema('schemes', $schemes);

		return $this;
	}


	/**
	 *
	 * Add paths to Schema
	 *
	 * @param $pathSuffix
	 * @param $method
	 * @param $data
	 * @return $this
	 */
	public function addPathData($pathSuffix, $method, $data)
	{
		$this->schema['paths'][$pathSuffix][$method] = $data;

		return $this;
	}


	public function getSchema()
	{
		return $this->schema;
	}

	/**
	 *
	 * Get generated output array
	 *
	 * @return array
	 */
	public function getOutput()
	{
		return $this->schema;
	}
}
