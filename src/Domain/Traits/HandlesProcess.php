<?php
namespace EMedia\Api\Domain\Traits;

use Symfony\Component\Process\Process;

trait HandlesProcess
{

	/**
	 *
	 * Verify pre-requisite software exists
	 *
	 * @param $list
	 * @return bool
	 */
	protected static function verifyRequiredCommandsExist($list): bool
	{
		foreach ($list as $command => $name) {
			$process = Process::fromShellCommandline($command);
			$process->run();

			if (!$process->isSuccessful()) {
				throw new \RuntimeException("{$name} not found. This is required to proceed. Check by typing `{$command}` and press enter.");
			}
		}

		return true;
	}
}
