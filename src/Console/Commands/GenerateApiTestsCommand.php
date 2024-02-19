<?php


namespace EMedia\Api\Console\Commands;

use EMedia\Api\Domain\FileGenerators\Postman\PostmanCollectionReader;
use EMedia\Api\Domain\Traits\NamesAndPathLocations;
use EMedia\Api\Domain\Vendors\CsFixer;
use Illuminate\Foundation\Console\TestMakeCommand;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

class GenerateApiTestsCommand extends TestMakeCommand
{
	use NamesAndPathLocations;

	protected $signature = 'generate:api-tests
								{--force : Force overwrite}
								{--debug : Dump debug information}';

	protected $description = 'Generate API Tests';

	protected $pathVersion = 'v1';

	/**
	 *
	 * Execute the console command.
	 *
	 * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
	 */
	public function handle()
	{
		// read the YML file and get parse info
		$reader = new PostmanCollectionReader();
		$pathsData = $reader->getPathData();
		$this->pathVersion = $reader->getPathVersion();

		// build base test case
		$this->generateApiBaseTestCase();

		foreach ($pathsData as $pathData) {
			// if you need to debug a path, you may catch and dump here
			// if ($pathData['uri'] === '/api/v1/notifications') {
				// dd($pathData);
			// }

			$this->generateApiTest($pathData);
		}

		// be a good boy and clean up
		$this->cleanup();

		// fix style
		$this->fixCodeStyle();
	}


	/**
	 *
	 * Generate BaseTest Class
	 *
	 * @return bool
	 * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
	 */
	protected function generateApiBaseTestCase()
	{
		$name = 'APIBaseTestCase';
		$name = $this->qualifyClass($name);
		$path = $this->getPath($name);

		if ((! $this->hasOption('force') || ! $this->option('force')) &&
			$this->alreadyExists($name)) {
			$this->error($this->type.' already exists!');

			return false;
		}

		$this->makeDirectory($path);

		$stubPath = $this->resolveStubPath('/stubs/tests/APIBaseTestCase.stub');
		$stub = $this->files->get($stubPath);

		$contents = $this->replaceNamespace($stub, $name)->replaceClass($stub, $name);

		$this->files->put($path, $this->sortImports($contents));

		$this->info($name.' created successfully.');
	}

	/**
	 *
	 * Generate a test from path data
	 *
	 * @param $pathData
	 * @return bool
	 * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
	 */
	protected function generateApiTest($pathData)
	{
		// build a name to start with
		$name = $this->getClassNameFromPathData($pathData);

		if ($this->isReservedName($name)) {
			$this->error('The name {$name} is reserved by PHP.');
			return false;
		}

		// pass it through Laravel's default cleansing
		$name = $this->qualifyClass($name);

		// get the path
		$path = $this->getPath($name);

		// Next, We will check to see if the class already exists. If it does, we don't want
		// to create the class and overwrite the user's code. So, we will bail out so the
		// code is untouched. Otherwise, we will continue generating this class' files.
		if ((! $this->hasOption('force') || ! $this->option('force')) &&
			$this->alreadyExists($name)) {
			$this->error($this->type.' already exists!');

			return false;
		}

		// Next, we check if there's a Manually created class by user.
		// If it exists, we don't want to auto-generate another class. Because we can assume humans know
		// what they're doing.
		if (str_contains($path, 'AutoGen')) {
			$manualPath = str_replace('AutoGen', 'Manual', $path);
			if (file_exists($manualPath)) {
				$this->info(pathinfo($manualPath, PATHINFO_FILENAME).' exists. Skipping...');
				return false;
			}
		}

		// Next, we will generate the path to the location where this class' file should get
		// written. Then, we will build the class and make the proper replacements on the
		// stub files so that it gets the correctly formatted namespace and class name.
		$this->makeDirectory($path);

		$this->files->put($path, $this->sortImports($this->buildTestClass($name, $pathData)));

		$this->info($name.' created successfully.');
	}

	/**
	 * Build the class with the given name.
	 *
	 * @param  string  $name
	 * @return string
	 *
	 * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
	 */
	protected function buildTestClass($name, $data)
	{
		$compiled = $this->buildClass($name);
		$data['testName'] = $this->getTestNameFromPathData($data);

		// remove starting <?php if exists
		// otherwise it will cause compile errors
		$regex = '~^<\?php~';
		$openTagRemoved = false;
		if (preg_match($regex, $compiled)) {
			$compiled = preg_replace($regex, '--php--', $compiled);
			$openTagRemoved = true;
		}

		// create a temp file to store as a blade template
		$tempFile = tempnam(sys_get_temp_dir(), '_em_api_'. date('Ymdhis'));
		$tempBladeFile = $tempFile . '.blade.php';

		try {
			file_put_contents($tempBladeFile, $compiled);
			$viewBuilder = View::file($tempBladeFile, $data);
			$view = $viewBuilder->render();
		} catch (\Exception $ex) {
			// remove temp file if something goes wrong
			if (file_exists($tempFile)) {
				unlink($tempBladeFile);
			}
			if (file_exists($tempBladeFile)) {
				unlink($tempBladeFile);
			}

			// allow debugging, because temp files will be deleted at this point
			if (!$this->hasOption('debug')) {
				$this->error("Run command again with `--debug` to dump debug data.");
			} else {
				var_dump($data);
				$this->error('`$data` variable shown above.');
			}

			$this->error("Error when generating {$name}");

			// throw the exception to caller
			throw $ex;
		}

		// remote temp file
		if (file_exists($tempFile)) {
			unlink($tempBladeFile);
		}
		if (file_exists($tempBladeFile)) {
			unlink($tempBladeFile);
		}

		// add back the opening tag
		if ($openTagRemoved) {
			$view = preg_replace('~^--php--~', '<?php', $view);
		}

		return $view;
	}

	/**
	 * Resolve the fully-qualified path to the stub.
	 *
	 * @param  string  $stub
	 * @return string
	 */
	protected function resolveStubPath($stub)
	{
		return file_exists($customPath = $this->laravel->basePath(trim($stub, '/')))
			? $customPath
			: __DIR__.$stub;
	}

	/**
	 * Get the destination class path.
	 *
	 * @param  string  $name
	 * @return string
	 */
	protected function getPath($name)
	{
		$name = Str::replaceFirst($this->rootNamespace(), '', $name);

		$path = base_path('tests').str_replace('\\', '/', $name).'.php';

		return $path;
	}

	/**
	 * Get the default namespace for the class.
	 *
	 * @param  string  $rootNamespace
	 * @return string
	 */
	protected function getDefaultNamespace($rootNamespace)
	{
		$ns = str_replace('/', '\\', self::getTestsAutoGenDir($this->pathVersion));

		return $rootNamespace."\\".$ns;
	}

	/**
	 * Get the root namespace for the class.
	 *
	 * @return string
	 */
	protected function rootNamespace()
	{
		return 'Tests';
	}

	protected function getStub()
	{
		return $this->resolveStubPath('/stubs/tests/test.stub');
	}


	/**
	 *
	 * Return a Class Name for the test
	 *
	 * @param $pathData
	 * @return string|string[]
	 */
	protected function getClassNameFromPathData($pathData)
	{
		$name = '';

		if (isset($pathData['tags'][0])) {
			$name .= $pathData['tags'][0];
		}

		if (isset($pathData['summary'])) {
			$name .= $pathData['summary'];
		}

		$name .= 'APITest';

		return str_replace(' ', '', $name);
	}

	/**
	 *
	 * Return a test name (the function name) for a test
	 *
	 * @param $pathData
	 * @return string
	 */
	protected function getTestNameFromPathData($pathData)
	{
		$name = ['test_api'];

		if (isset($pathData['operationId'])) {
			$name[] = $pathData['operationId'];
		} else {
			$name[] = Str::random(5);
		}

		return strtolower(implode('_', array_map('trim', $name)));
	}


	/**
	 *
	 * Clean up after you're done
	 *
	 */
	protected function cleanup()
	{
		// clean any remaining temp files
		$files = glob(sys_get_temp_dir() . DIRECTORY_SEPARATOR . '_em_api_*'); // get all file names
		foreach ($files as $file) {
			if (is_file($file)) {
				// delete file
				unlink($file);
			}
		}

		// clear compiled views, because there will be unwanted blade cache files
		$this->call('view:clear');
	}

	/**
	 *
	 * Fix code styles of generated files
	 *
	 */
	protected function fixCodeStyle()
	{
		if (!CsFixer::isInstalled()) {
			$this->error("Can't find PHP-CS-Fixer in this machine. Install from https://github.com/FriendsOfPHP/PHP-CS-Fixer");
			return false;
		}

		$path = self::getTestsAutoGenDir($this->pathVersion);
		$process = CsFixer::fix(base_path('tests' . DIRECTORY_SEPARATOR . $path));

		if (!$process->isSuccessful()) {
			$this->error('Code style cleanup failed.');
			return false;
		}

		return true;
	}
}
