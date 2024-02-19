<?php


namespace EMedia\Api\Domain\FileGenerators\Postman;

use EMedia\Api\Domain\FileGenerators\BaseFileGenerator;
use Illuminate\Support\Collection;

class PostmanEnvironment extends BaseFileGenerator
{

	/**
	 * @var Collection
	 */
	protected $variables;

	public function __construct()
	{
		$this->addToSchema('_postman_variable_scope', 'environment');
		$this->variables = new Collection();
	}

	/**
	 *
	 * Set name of the Environment
	 *
	 * @param $name
	 * @return $this
	 */
	public function setName($name)
	{
		$this->addToSchema('name', $name);

		return $this;
	}

	/**
	 *
	 * Set the Server URL as per Spec
	 *
	 * @example https://api.example.com
	 * @example https://api.example.com/v1
	 *
	 * @param $serverUrl
	 * @return $this
	 */
	public function setServerUrl($serverUrl)
	{
		$this->addVariable('scheme', parse_url($serverUrl, PHP_URL_SCHEME));
		$this->addVariable('host', parse_url($serverUrl, PHP_URL_HOST));

		return $this;
	}

	/**
	 *
	 * Add a new Variable
	 *
	 * @param $variableName
	 * @param $initialValue
	 * @return $this
	 */
	public function addVariable($variableName, $initialValue)
	{
		$isUpdated = false;

		// update existing value if found
		$this->variables->transform(function ($item) use ($variableName, $initialValue, &$isUpdated) {
			if ($item['key'] === $variableName) {
				$item['value'] = $initialValue;
				$isUpdated = true;
			}
			return $item;
		});

		// add a new value
		if (!$isUpdated) {
			$this->variables->push([
				'key' => $variableName,
				'value' => $initialValue,
			]);
		}

		return $this;
	}

	/**
	 *
	 * Remove a variable by name
	 *
	 * @param $variableName
	 * @return $this
	 */
	public function removeVariable($variableName)
	{
		$this->variables = $this->variables->reject(function ($item) use ($variableName) {
			return $item['key'] === $variableName;
		});

		return $this;
	}

	/**
	 *
	 * Get generated output array
	 *
	 * @return array
	 */
	public function getOutput()
	{
		$output = $this->schema;

		$output['values'] = $this->variables;

		return $output;
	}
}
