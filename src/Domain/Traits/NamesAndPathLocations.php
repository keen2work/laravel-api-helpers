<?php
namespace EMedia\Api\Domain\Traits;

use ElegantMedia\PHPToolkit\Dir;
use ElegantMedia\PHPToolkit\Exceptions\FIleSystem\DirectoryNotCreatedException;

trait NamesAndPathLocations
{
	/**
	 * @param false $createIfNotExists
	 * @return string
	 * @throws DirectoryNotCreatedException
	 */
	protected static function getDocsDir($createIfNotExists = false): string
	{
		$dirPath = resource_path('docs');

		if ($createIfNotExists) {
			Dir::makeDirectoryIfNotExists($dirPath);
		}

		return $dirPath;
	}

	/**
	 *
	 * Get storage path for API responses
	 *
	 * @param bool $createIfNotExists Create directory if it doesn't exist
	 * @return string        Directory Path
	 *
	 * @throws DirectoryNotCreatedException
	 */
	protected static function getApiResponsesStorageDir($createIfNotExists = false): string
	{
		$dirPath = self::getDocsDir() . DIRECTORY_SEPARATOR . 'api_responses';

		if ($createIfNotExists) {
			Dir::makeDirectoryIfNotExists($dirPath);
		}

		return $dirPath;
	}

	/**
	 * @param false $createIfNotExists
	 * @return string
	 * @throws DirectoryNotCreatedException
	 */
	protected static function getApiResponsesAutoGenDir($createIfNotExists = false): string
	{
		$dirPath = self::getApiResponsesStorageDir() . DIRECTORY_SEPARATOR . 'auto_generated';

		if ($createIfNotExists) {
			Dir::makeDirectoryIfNotExists($dirPath);
		}

		return $dirPath;
	}

	/**
	 * @param false $createIfNotExists
	 * @return string
	 * @throws DirectoryNotCreatedException
	 */
	protected static function getApiResponsesManualDir($createIfNotExists = false): string
	{
		$dirPath = self::getApiResponsesStorageDir() . DIRECTORY_SEPARATOR . 'manual';

		if ($createIfNotExists) {
			Dir::makeDirectoryIfNotExists($dirPath);
		}

		return $dirPath;
	}

	/**
	 * @param false $createIfNotExists
	 * @return string
	 * @throws DirectoryNotCreatedException
	 */
	public static function getDocsOutputDir($createIfNotExists = false): string
	{
		$dirPath = public_path(DIRECTORY_SEPARATOR . 'docs');

		if ($createIfNotExists) {
			Dir::makeDirectoryIfNotExists($dirPath);
		}

		return $dirPath;
	}

	/**
	 * @param false $createIfNotExists
	 * @return string
	 * @throws DirectoryNotCreatedException
	 */
	protected static function getApiDocsOutputDir($createIfNotExists = false): string
	{
		$dirPath = self::getDocsOutputDir() . DIRECTORY_SEPARATOR . 'api';

		if ($createIfNotExists) {
			Dir::makeDirectoryIfNotExists($dirPath);
		}

		return $dirPath;
	}

	/**
	 * @param false $createIfNotExists
	 * @return string
	 * @throws DirectoryNotCreatedException
	 */
	protected static function getApiDocsDir($createIfNotExists = false): string
	{
		$dirPath = self::getDocsDir() . DIRECTORY_SEPARATOR . 'apidoc';

		if ($createIfNotExists) {
			Dir::makeDirectoryIfNotExists($dirPath);
		}

		return $dirPath;
	}

	/**
	 * @param false $createIfNotExists
	 * @return string
	 * @throws DirectoryNotCreatedException
	 */
	protected static function getApiDocsAutoGenDir($createIfNotExists = false): string
	{
		$dirPath = self::getApiDocsDir() . DIRECTORY_SEPARATOR . 'auto_generated';

		if ($createIfNotExists) {
			Dir::makeDirectoryIfNotExists($dirPath);
		}

		return $dirPath;
	}

	/**
	 * @param false $createIfNotExists
	 * @return string
	 * @throws DirectoryNotCreatedException
	 */
	protected static function getApiDocsManualDir($createIfNotExists = false): string
	{
		$dirPath = self::getApiDocsDir() . DIRECTORY_SEPARATOR . 'manual';

		if ($createIfNotExists) {
			Dir::makeDirectoryIfNotExists($dirPath);
		}

		return $dirPath;
	}

	/**
	 * @param null $apiVersion
	 * @return string
	 */
	public static function getTestsAutoGenDir($apiVersion = null): string
	{
		$path = 'Feature' . DIRECTORY_SEPARATOR . 'AutoGen' . DIRECTORY_SEPARATOR . 'API';

		if ($apiVersion) {
			$path .= DIRECTORY_SEPARATOR . strtoupper($apiVersion);
		}

		return $path;
	}

	/**
	 * @param $apiVersion
	 * @param $relativePath
	 * @return string
	 */
	public static function getTestFilePath($apiVersion, $relativePath): string
	{
		return base_path('tests/'.self::getTestsAutoGenDir($apiVersion).DIRECTORY_SEPARATOR.$relativePath);
	}

	/**
	 *
	 * Delete old files
	 *
	 * @param $dirPath
	 * @param $fileExtension
	 */
	public static function deleteFilesInDirectory($dirPath, $fileExtension)
	{
		array_map('unlink', glob("$dirPath/*.$fileExtension"));
	}
}
