<?php


namespace EMedia\Api\Domain\Vendors;

use EMedia\Api\Domain\Traits\HandlesProcess;
use Symfony\Component\Process\Process;

class CsFixer
{
	use HandlesProcess;

	public static function isInstalled()
	{
		// check required software exists
		$requiredCommands = [
			'php-cs-fixer --version' => 'PHP CS Fixer',
		];
		return self::verifyRequiredCommandsExist($requiredCommands);
	}

	public static function fix($path)
	{
		$process = Process::fromShellCommandline(
			'php-cs-fixer fix ' . $path
		);
		$process->run();

		return $process;
	}
}
