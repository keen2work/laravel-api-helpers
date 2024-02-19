<?php


namespace EMedia\Api\Domain;

use ElegantMedia\PHPToolkit\Loader;
use EMedia\Api\Docs\Param;
use Illuminate\Database\Eloquent\Model;

class ModelDefinition
{
	/**
	 *
	 * Get all model definitions
	 *
	 * @return array
	 * @throws \ReflectionException
	 */
	public function getAllModelDefinitions()
	{
		if (!config('oxygen.api.includeModelDefinitions', false)) {
			return [];
		}

		$models = $this->getAllModels();

		$hiddenModelDefinitions = config('oxygen.api.hiddenModelDefinitionClasses', []);

		$definitions = [];
		foreach ($models as $model) {
			$definition = $this->getModelDefinition($model);

			// if hidden, don't include them
			if (!in_array($definition['name'], $hiddenModelDefinitions)) {
				// if already included, don't include them
				// this will prioritise the models in local project first
				// and ignore any inherited ones if found.
				if (!isset($definitions[$definition['name']])) {
					$definitions[$definition['name']] = $definition['definition'];
				}
			}
		}

		$definitions['SuccessResponse'] = [
			'type' => 'object',
			'properties' => [
				'message' => [ 'type' => 'string' ],
				'result'  => [ 'type' => 'boolean', 'default' => true ],
				'payload' => [ 'type' => 'object' ],
			],
		];
		$definitions['Paginator'] = [
			'type' => 'object',
			'properties' => [
				'current_page' => [ 'type' => 'number' ],
				'per_page'  => [ 'type' => 'number', 'default' => 50 ],
				'from' => [ 'type' => 'number' ],
				'to' => [ 'type' => 'number' ],
				'total' => [ 'type' => 'number' ],
				'last_page' => [ 'type' => 'number' ],
			],
		];

		return $definitions;
	}

	/**
	 *
	 * Return the default error definitions
	 *
	 * @return array
	 */
	public function getAllErrorDefinitions()
	{
		return [
			'ApiErrorUnauthorized' => [
				'type' => 'object',
				'properties' => [
					'message' => [ 'type' => 'string' ],
					'result'  => [ 'type' => 'boolean', 'default' => true ],
					'payload' => [ 'type' => 'object' ],
				],
			],
			'ApiErrorAccessDenied' => [
				'type' => 'object',
				'properties' => [
					'message' => [ 'type' => 'string' ],
					'result'  => [ 'type' => 'boolean', 'default' => true ],
					'payload' => [ 'type' => 'object' ],
				],
			],
			'ApiError' => [
				'type' => 'object',
				'properties' => [
					'message' => [ 'type' => 'string' ],
					'result'  => [ 'type' => 'boolean', 'default' => true ],
					'payload' => [ 'type' => 'object' ],
				],
			],
		];
	}

	public function getSuccessResponseDefinition($responseName, $successObject)
	{
		$shortName = self::getModelShortName($successObject);

		return [
			$responseName => [
				'type' => 'object',
				'properties' => [
					'message' => [ 'type' => 'string' ],
					'result'  => [ 'type' => 'boolean', 'default' => true ],
					'payload' => [ '$ref' => '#/definitions/' . $shortName ],
				],
			]
		];
	}

	public function getSuccessResponsePaginatedDefinition($responseName, $successObject)
	{
		$shortName = self::getModelShortName($successObject);

		return [
			$responseName => [
				'type' => 'object',
				'properties' => [
					'message' => [ 'type' => 'string' ],
					'result'  => [ 'type' => 'boolean', 'default' => true ],
					'payload' => [
						'type' => 'array',
						'items' => [
							'$ref' => '#/definitions/' . $shortName
						],
					],
					'paginator' => ['$ref' => '#/definitions/Paginator'],
				],
			]
		];
	}

	/**
	 *
	 * Return all declared definitions
	 *
	 * @return array
	 * @throws \ReflectionException
	 */
	public function getAllDefinitions()
	{
		return array_merge($this->getAllModelDefinitions(), $this->getAllErrorDefinitions());
	}

	/**
	 *
	 * Get all models for this project
	 *
	 * @return array
	 */
	protected function getAllModels($directoryList = [])
	{
		if (empty($directoryList)) {
			$directories = [
				app_path('Entities'),
			];
		}

		$appendModelDirectories = array_filter(config('oxygen.api.modelDirectories', []));
		if (!empty($appendModelDirectories)) {
			$directories = array_merge($directories, $appendModelDirectories);
		}

		foreach ($directories as $dirPath) {
			Loader::includeAllFilesFromDirRecursive($dirPath);
		}

		$response = [];
		foreach (get_declared_classes() as $class) {
			if (is_subclass_of($class, Model::class)) {
				$response[] = $class;
			}
		}

		return $response;
	}

	/**
	 *
	 * Get column definitions for a model
	 * Logic from https://github.com/beyondcode/laravel-er-diagram-generator
	 *
	 * @param string $model
	 *
	 * @return mixed
	 */
	public function getTableColumnsFromModel(string $model)
	{
		$model = app($model);

		$tableName = $model->getTable();

		if ($tableName) {
			$table = $model->getConnection()->getTablePrefix() . $tableName;
			$schema = $model->getConnection()->getDoctrineSchemaManager($table);
			$databasePlatform = $schema->getDatabasePlatform();
			$databasePlatform->registerDoctrineTypeMapping('enum', 'string');

			if (!empty(config('database.doctrine_type_maps'))) {
				foreach (config('database.doctrine_type_maps') as $type => $map) {
					$databasePlatform->registerDoctrineTypeMapping($type, $map);
				}
			}

			$database = null;
			if (strpos($table, '.')) {
				list($database, $table) = explode('.', $table);
			}
			return $schema->listTableColumns($table, $database);
		} else {
			return null;
		}
	}

	/**
	 *
	 * Build the model definition for a given model by reading the database columns
	 *
	 * @param $class
	 *
	 * @return array
	 * @throws \ReflectionException
	 */
	protected function getModelDefinition($class): array
	{
		$columns = $this->getTableColumnsFromModel($class);
		/** @var \Doctrine\DBAL\Schema\Column $column */
		$model = new $class;

		$fields = [];
		if (!empty($columns)) {
			foreach ($columns as $column) {
				$dataType = $column->getType()->getName();

				// change the data type to swagger's format
				$dataType = Param::getSwaggerDataType($dataType);

				$fields[$column->getName()] = $dataType;
			}
		}

		// append visible fields
		$visibleFields = $model->getVisible();
		$filteredFields = [];
		if (empty($visibleFields)) {
			$filteredFields = $fields;
		} else {
			foreach ($visibleFields as $visibleKey) {
				if (isset($fields[$visibleKey])) {
					$filteredFields[$visibleKey] = $fields[$visibleKey];
				} else {
					$filteredFields[$visibleKey] = 'string';
				}
			}
		}

		// if there are external fields, add them
		if (method_exists($model, 'getExtraApiFields')) {
			$extraFields = $model->getExtraApiFields();
			foreach ($extraFields as $key => $value) {
				if (is_int($key)) {
					$filteredFields[$value] = 'string';
				} else {
					$filteredFields[$key] = $value;
				}
			}
		}

		// remove hidden fields
		foreach ($model->getHidden() as $hiddenKey) {
			unset($filteredFields[$hiddenKey]);
		}

		$properties = [];
		foreach ($filteredFields as $key => $value) {
			if (is_array($value) && isset($value['type'])) {
				if ($value['type'] == 'array' && isset($value['items'])) {
					if (is_array($value['items'])) {
						$properties[$key] = [
							'type' => $value['type'],
							'items' => [
								"type" => $value['items']['type'],
							],
						];

						if (isset($value['items']['format'])) {
							$properties[$key]['items']['format'] = $value['items']['format'];
						}
					} else {
						$properties[$key] = [
							'type' => $value['type'],
							'items' => [
								"\$ref" => "#/definitions/" . $value['items'],
							],
						];
					}
				} elseif ($value['type'] == 'object' && isset($value['items'])) {
					$properties[$key] = [
						"\$ref" => "#/definitions/" . $value['items'],
					];
				} else {
					if (isset($value['format'])) {
						$properties[$key] = [
							'type' => $value['type'],
							'format' => $value['format']
						];
					} else {
						$properties[$key] = [
							'type' => $value['type'],
						];
					}
				}
			} else {
				$properties[$key] = ['type' => $value];
			}
		}

		$reflect = new \ReflectionClass($class);

		$response = [
			'name' => $reflect->getShortName(),
			'definition' => [
				'type' => 'object',
			],
		];

		// empty properties are not allowed
		if (count($properties) > 0) {
			$response['definition']['properties'] = $properties;
		}

		return $response;
	}


	public static function getModelShortName($class)
	{
		$reflect = new \ReflectionClass($class);

		return $reflect->getShortName();
	}
}
