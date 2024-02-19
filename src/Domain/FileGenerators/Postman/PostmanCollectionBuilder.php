<?php


namespace EMedia\Api\Domain\FileGenerators\Postman;

use EMedia\Api\Docs\Param;
use EMedia\Api\Domain\FileGenerators\BaseFileGenerator;

class PostmanCollectionBuilder extends BaseFileGenerator
{
	protected $collection;


	public function setSchema($schema)
	{
		$this->schema = $schema;

		return $this;
	}


	protected function getServerUrl($path = '')
	{
		$protocol = 'http';

		if (isset($this->schema['schemes'])) {
			if (isset($this->schema['schemes'][0])) {
				$protocol = $this->schema['schemes'][0];
			}
		}

		return
			$protocol . '://' .
			($this->schema['host'] ?? '') .
			($this->schema['basePath'] ?? '') .
			$path;
	}



	public function getOutput()
	{
		// set Postman variables
		$this->schema['host'] = '{{host}}';
		$this->schema['schemes'] = ['{{scheme}}'];

		$this->schema['securityDefinitions']['apiKey']['schema'] = [
			'type' => Param::TYPE_STRING,
			'example' => '{{x-api-key}}',
		];
		$this->schema['securityDefinitions']['accessToken']['schema'] = [
			'type' => Param::TYPE_STRING,
			'example' => '{{x-access-token}}',
		];


		foreach ($this->schema['paths'] as $path => &$pathData) {
			foreach ($pathData as $method => &$methodData) {
				// starting the index at 1, because of Postman's bug of importing only 1 security variable
				// https://github.com/postmanlabs/postman-app-support/issues/8663
				// this workaround can be removed after the above bug is fixed

				// get the security rule names applicable for this method
				if (isset($methodData['security'])) {
					for ($i = 1, $iMax = count($methodData['security']); $i < $iMax; $i++) {
						$securityRule = $methodData['security'][$i];
						foreach ($securityRule as $secName => $secValue) {
							if (isset($this->schema['securityDefinitions'][$secName])) {
								// add the root definition as a parameter
								$secDefinition = $this->schema['securityDefinitions'][$secName];
								$secDefinition['type'] = Param::TYPE_STRING;
								$methodData['parameters'][] = $secDefinition;

								unset($methodData['security'][$i]);
							}
						}
					}
				}
			}
		}

		return $this->schema;
	}
}
