<?php


namespace EMedia\Api\Console\Commands;

use EMedia\Api\Domain\Traits\HandlesProcess;
use EMedia\Api\Domain\Traits\NamesAndPathLocations;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class GenerateDocsTestsCommand extends Command
{
	use NamesAndPathLocations;
	use HandlesProcess;

	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'generate:docs-tests
								{--login-user-id=3 : User ID to access login of API}
								{--login-user-pass=12345678 : Password for the Login User}
								{--no-authenticate-web-apis : Do not log in with "login-user-id" (web route) before generating the API docs. }
								{--test-user-id=4 : User ID of the test user}
								{--no-apidoc : Do not run api docs}
								{--force-tests=false : Overwrite test files}
								';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Generate API Documentation, API Tests, Run Tests';

	public function handle()
	{
		// generate initial docs, so the variables can be written to Postman config
		$this->call('generate:docs', [
			'--login-user-id' => $this->option('login-user-id'),
			'--login-user-pass' => $this->option('login-user-pass'),
			'--no-authenticate-web-apis' => $this->option('no-authenticate-web-apis'),
			'--test-user-id' => $this->option('test-user-id'),

			// don't need apidocs on first pass
			'--no-apidoc' => true,
			'--no-files-output' => true,
		]);

		// generate tests from the created files
		$this->call('generate:api-tests', [
			'--force' => $this->option('force-tests'),
		]);

		// clear api responses
		$dirPath = self::getApiResponsesAutoGenDir();
		self::deleteFilesInDirectory($dirPath, 'json');

		// run the tests, so the responses can be saved
		putenv('DOCUMENTATION_MODE=false');
		$process = Process::fromShellCommandline(base_path('/vendor/bin/phpunit') . ' --filter AutoGen');
		$process->run();
		$process = Process::fromShellCommandline(base_path('/vendor/bin/phpunit') . ' --filter Manual');
		$process->run();

		if (!$process->isSuccessful()) {
			$this->warn($process->getOutput());
		} else {
			$this->line($process->getOutput());

			// generate docs again, because now you should have the test responses saved.
			$this->call('generate:docs', [
				'--reset' => true,
				'--login-user-id' => $this->option('login-user-id'),
				'--login-user-pass' => $this->option('login-user-pass'),
				'--no-authenticate-web-apis' => $this->option('no-authenticate-web-apis'),
				'--test-user-id' => $this->option('test-user-id'),
			]);
		}
	}
}
