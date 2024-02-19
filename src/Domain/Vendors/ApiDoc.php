<?php


namespace EMedia\Api\Domain\Vendors;

use EMedia\Api\Domain\Traits\HandlesProcess;
use EMedia\Api\Domain\Traits\NamesAndPathLocations;
use Symfony\Component\Process\Process;

class ApiDoc
{
	use HandlesProcess;
	use NamesAndPathLocations;

	public static function isInstalled()
	{
		// check required software exists
		$requiredCommands = [
			'apidoc --help' => 'ApiDoc.js',
		];
		return self::verifyRequiredCommandsExist($requiredCommands);
	}

	public static function compile()
	{
		$command = implode(' ', [
			'apidoc',
			'--input', self::getApiDocsDir(true),
			'--output', self::getApiDocsOutputDir(true),
		]);
		$process = Process::fromShellCommandline($command);
		$process->mustRun();

		return $process;
	}
}
