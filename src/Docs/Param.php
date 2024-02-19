<?php


namespace EMedia\Api\Docs;

use ElegantMedia\PHPToolkit\Text;
use Illuminate\Contracts\Support\Arrayable;

class Param implements Arrayable, \JsonSerializable
{

	// Parameter Locations
	// https://swagger.io/docs/specification/2-0/describing-parameters/
	public const LOCATION_PATH 		= 'path';
	public const LOCATION_QUERY 	= 'query';
	public const LOCATION_HEADER 	= 'header';
	public const LOCATION_COOKIE 	= 'cookie';
	public const LOCATION_BODY		= 'body';
	public const LOCATION_FORM 		= 'formData';

	// Data Types
	public const TYPE_STRING = 'string';
	public const TYPE_INT	 = 'integer';
	public const TYPE_NUMBER = 'number';
	public const TYPE_FLOAT  = 'number';
	public const TYPE_DOUBLE = 'number';
	public const TYPE_BOOLEAN = 'boolean';
	public const TYPE_ARRAY = 'array';

	protected $fieldName;
	protected $required = true;
	protected $dataType;
	protected $defaultValue;
	protected $description = '';
	protected $location;
	protected $model;
	protected $collectionFormat;
	protected $items;

	protected $variable;
	protected $example;

	public function __construct($fieldName = null, $dataType = self::TYPE_STRING, $description = null, $location = null)
	{
		$this->fieldName = $fieldName;
		$this->setDataType($dataType);
		$this->location = $location;
		if (!$description && $fieldName) {
			$this->description = ucfirst(Text::reverseSnake($fieldName));
		} else {
			$this->description = $description;
		}
	}

	public static function getParamLocations()
	{
		return [
			self::LOCATION_HEADER,
			self::LOCATION_PATH,
			self::LOCATION_QUERY,
			self::LOCATION_FORM,
		];
	}

	public static function getDataTypes()
	{
		return [
			self::TYPE_STRING,
			self::TYPE_INT,
			self::TYPE_NUMBER,
			self::TYPE_FLOAT,
			self::TYPE_DOUBLE,
			self::TYPE_BOOLEAN,
			self::TYPE_ARRAY,
		];
	}

	/**
	 * @return bool
	 */
	public function getRequired(): bool
	{
		return $this->required;
	}

	/**
	 * @param bool $required
	 */
	public function required()
	{
		$this->required = true;

		return $this;
	}

	public function optional()
	{
		$this->required = false;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getDataType(): string
	{
		return ucfirst($this->dataType);
	}

	/**
	 * @param string $dataType
	 */
	public function dataType(string $dataType)
	{
		$this->dataType = $dataType;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getDefaultValue()
	{
		return $this->defaultValue;
	}

	/**
	 * @param mixed $defaultValue
	 */
	public function defaultValue($defaultValue)
	{
		$this->defaultValue = $defaultValue;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getDescription(): string
	{
		return $this->description;
	}

	/**
	 * @param string $description
	 */
	public function description(string $description)
	{
		$this->description = $description;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getName()
	{
		return $this->fieldName;
	}

	/**
	 * @param mixed $fieldName
	 */
	public function field($fieldName)
	{
		$this->fieldName = $fieldName;

		return $this;
	}

	/**
	 * @return null|string
	 */
	public function getLocation()
	{
		return $this->location;
	}

	/**
	 * @param string $location
	 */
	public function setLocation(string $location)
	{
		$this->location = $location;

		return $this;
	}

	/**
	 * @param object $model
	 */
	public function setModel($model)
	{
		$this->model = $model;

		return $this;
	}

	/**
	 * @return object
	 */
	public function getModel()
	{
		return $this->model;
	}

	/**
	 * @param mixed $defaultValue
	 *
	 * @return Param
	 */
	public function setDefaultValue($defaultValue)
	{
		$this->defaultValue = $defaultValue;

		return $this;
	}

	/**
	 * @param $dataType
	 *
	 * @return string
	 */
	public static function getSwaggerDataType($dataType)
	{
		$dataType = strtolower($dataType);

		switch ($dataType) {
			case 'integer':
				return 'integer';
				break;
			case 'float':
			case 'double':
				return 'number';
				break;
			case 'boolean':
				return 'boolean';
				break;
			case 'array':
				return 'array';
			case 'object':
			case 'model':
				return 'object';
				break;
			case 'string':
			case 'datetime':
			case 'file':
			case 'date':
			case 'text':
			default:
				return 'string';
		}
	}

	/**
	 *
	 * toArray response
	 *
	 * @return array
	 */
	public function toArray(): array
	{
		return [
			'fieldName' => $this->fieldName,
			'required' => $this->required,
			'dataType' => $this->dataType,
			'defaultValue' => $this->defaultValue,
			'location' => $this->location,
			'model' => $this->model,
			'variable' => $this->variable,
			'example' => $this->example,
			'collectionFormat' => $this->collectionFormat,
			'items' => $this->items,
		];
	}

	public function jsonSerialize()
	{
		return $this->toArray();
	}

	/**
	 * @return mixed
	 */
	public function getExample()
	{
		return $this->example;
	}

	/**
	 * @param mixed $example
	 */
	public function setExample($example)
	{
		$this->example = $example;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getVariable()
	{
		return $this->variable;
	}

	/**
	 *
	 * Give a custom or a dynamic variable
	 * See full list at https://learning.postman.com/docs/writing-scripts/script-references/variables-list/
	 *
	 * @example {{user_name}}
	 * @example {{$randomExampleEmail}}
	 *
	 * @param mixed $variable
	 */
	public function setVariable($variable)
	{
		// clean up and add the braces if they're not there
		$variable = '{{' . trim($variable, " \t\n\r\0\x0B{}") . '}}';

		$this->variable = trim($variable);

		return $this;
	}

	/**
	 * @param string $dataType
	 */
	public function setDataType(string $dataType): Param
	{
		$this->dataType = $dataType;

		// set the default array type
		if ($dataType === self::TYPE_ARRAY) {
			$this->setCollectionFormat('multi');
			$this->setArrayType(self::TYPE_STRING);
		}

		return $this;
	}

	/**
	 * @param string|null $description
	 * @return Param
	 */
	public function setDescription(?string $description): Param
	{
		$this->description = $description;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getCollectionFormat(): string
	{
		return $this->collectionFormat;
	}

	/**
	 * @return mixed
	 */
	public function getItems()
	{
		return $this->items;
	}

	/**
	 * @param string $collectionFormat
	 *
	 * @return Param
	 */
	public function setCollectionFormat(string $collectionFormat): Param
	{
		$this->collectionFormat = $collectionFormat;
		return $this;
	}

	/**
	 * @param mixed $items
	 *
	 * @return Param
	 */
	public function setItems($items)
	{
		$this->items = $items;
		return $this;
	}

	public function setArrayType(string $dataType)
	{
		$this->items = [
			'type' => $dataType,
		];

		return $this;
	}
}
