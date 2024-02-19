<?php
namespace EMedia\Api\Domain\FileGenerators;

use ElegantMedia\PHPToolkit\Dir;
use ElegantMedia\PHPToolkit\Exceptions\FIleSystem\DirectoryNotCreatedException;
use EMedia\Api\Exceptions\FileGenerationFailedException;
use Illuminate\Contracts\Filesystem\FileExistsException;
use Symfony\Component\Yaml\Yaml;

abstract class BaseFileGenerator
{
	protected $schema = [];

	abstract public function getOutput();

	/**
	 *
	 * Add MetaData for Environment
	 *
	 * @param $key
	 * @param $value
	 * @return $this
	 */
	public function addToSchema($key, $value)
	{
		$this->schema[$key] = $value;

		return $this;
	}

	/**
	 *
	 * Append to a schema array
	 *
	 * @param $key
	 * @param $value
	 * @return $this
	 */
	public function appendToSchemaArray($key, $value)
	{
		$this->schema[$key][] = $value;

		return $this;
	}

	/**
	 *
	 * Write generated output to a JSON file
	 *
	 * @param $outputFilePath
	 * @param bool $overwrite
	 * @return bool
	 * @throws FileExistsException
	 * @throws FileGenerationFailedException
	 */
	public function writeOutputFileJson($outputFilePath, $overwrite = true): bool
	{
		if (!$overwrite && file_exists($outputFilePath)) {
			throw new FileExistsException("File {$outputFilePath} already exists.");
		}

		try {
			$outputString = json_encode($this->getOutput(), JSON_PRETTY_PRINT);
		} catch (\Exception $ex) {
			throw new FileGenerationFailedException("Failed to generate a valid output. " . $ex->getMessage());
		}

		$outputDir = pathinfo($outputFilePath, PATHINFO_DIRNAME);
		Dir::makeDirectoryIfNotExists($outputDir);

		file_put_contents($outputFilePath, $outputString);

		return true;
	}


	/**
	 *
	 * Write generated output to a YAML file
	 *
	 * @param $outputFilePath
	 * @param bool $overwrite
	 * @return bool
	 * @throws FileExistsException
	 * @throws FileGenerationFailedException
	 * @throws DirectoryNotCreatedException
	 */
	public function writeOutputFileYaml($outputFilePath, $overwrite = true): bool
	{
		if (!$overwrite && file_exists($outputFilePath)) {
			throw new FileExistsException("File {$outputFilePath} already exists.");
		}

		$outputDir = pathinfo($outputFilePath, PATHINFO_DIRNAME);
		Dir::makeDirectoryIfNotExists($outputDir);

		try {
			$yamlString = Yaml::dump($this->getOutput(), 10, 4, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE|Yaml::DUMP_OBJECT_AS_MAP);
			file_put_contents($outputFilePath, $yamlString);
		} catch (\Exception $ex) {
			throw new FileGenerationFailedException("Failed to generate a valid output. " . $ex->getMessage());
		}

		return true;
	}
}
