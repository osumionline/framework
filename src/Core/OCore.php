<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\Core;

use \PDO;
use \ReflectionParameter;
use \ReflectionClass;
use Osumi\OsumiFramework\DB\ODBContainer;
use Osumi\OsumiFramework\Cache\OCacheContainer;
use Osumi\OsumiFramework\Web\OSession;
use Osumi\OsumiFramework\Web\ORequest;
use Osumi\OsumiFramework\Routing\OUrl;
use Osumi\OsumiFramework\Tools\OTools;
use Osumi\OsumiFramework\Tools\OBuild;
use Osumi\OsumiFramework\Log\OLog;

/**
 * OCore - Base class for the framework with methods to load required files and start the application
 */
class OCore {
	public ?ODBContainer    $dbContainer = null;
	public ?OCacheContainer $cacheContainer = null;
	public ?OConfig         $config = null;
	public ?OSession        $session = null;
	public ?OTranslate      $translate = null;
	public ?float           $start_time = null;

	/**
	 * Get the start time in milliseconds to use in benchmarks
	 */
	public function __construct() {
		$this->start_time = microtime(true);
	}

	/**
	 * Get whole projects base dir
	 *
	 * @return string Absolute path of the project
	 */
	private function getBaseDir(): string {
		// Start from the directory of the executed script
		$dir = dirname(__DIR__, 3);

		// Look for a marker file or directory that indicates the project root
		while (!is_dir($dir . '/vendor') && $dir !== '/') {
			$dir = dirname($dir);
		}

		// If we've reached the filesystem root without finding our marker, throw an exception
		if ($dir === '/') {
			throw new \RuntimeException("Could not locate project root directory");
		}

		return $dir.'/';
  }

	/**
	 * Include required files for the framework and start up some components like configuration, cache container or database connection container
	 *
	 * @param bool $from_cli Marks if the core is being loaded for use in web application or CLI application
	 *
	 * @return void
	 */
	public function load(bool $from_cli=false): void {
		date_default_timezone_set('Europe/Madrid');

		$this->config = new OConfig($this->getBaseDir());

		// Check locale file
		$locale_file = $this->config->getDir('ofw_locale').$this->config->getLang().'.po';
		if (!file_exists($locale_file)){
			echo "ERROR: locale file ".$this->config->getLang()." not found.";
			exit;
		}

		// Due to a circular dependancy, check name of the log file after core loading
		if (is_null($this->config->getLog('name'))) {
			$this->config->setLog('name', OTools::slugify($this->config->getName()));
		}

		// Load framework translations
		$this->translate = new OTranslate();
		$this->translate->load($this->config->getDir('ofw_locale').$this->config->getLang().'.po');

		// If there is a DB connection configured, check drivers and load required classes
		if ($this->config->getDB('user')!=='' || $this->config->getDB('pass')!=='' || $this->config->getDB('host')!=='' || $this->config->getDB('name')!=='') {
			$pdo_drivers = PDO::getAvailableDrivers();
			if (!in_array($this->config->getDB('driver'), $pdo_drivers)) {
				echo "ERROR: El sistema no dispone del driver ".$this->config->getDB('driver')." solicitado para realizar la conexión a la base de datos.\n";
				exit;
			}
			define('OMODEL_PK', 1);
			define('OMODEL_PK_STR', 10);
			define('OMODEL_CREATED', 2);
			define('OMODEL_UPDATED', 3);
			define('OMODEL_NUM', 4);
			define('OMODEL_TEXT', 5);
			define('OMODEL_DATE', 6);
			define('OMODEL_BOOL', 7);
			define('OMODEL_LONGTEXT', 8);
			define('OMODEL_FLOAT', 9);

			$this->dbContainer = new ODBContainer();
		}

		if (!$from_cli) {
			$this->session  = new OSession();
		}

		// Set up an empty cache container
		$this->cacheContainer = new OCacheContainer();
	}

	/**
	 * Start up the application checking the accessed URL, load matched URL or give the appropiate error
	 *
	 * @return void
	 */
	public function run(): void {
		// Check if session is to be used
		if ($this->config->getUseSession()) {
			session_start();
		}

		if ($this->config->getAllowCrossOrigin()) {
			header('Access-Control-Allow-Origin: *');
			header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
			header('Access-Control-Allow-Methods: GET, POST');
		}

		// Load current URL
		$u = new OUrl($_SERVER['REQUEST_METHOD']);
		$u->setCheckUrl($_SERVER['REQUEST_URI'], $_GET, $_POST, $_FILES);
		$url_result = $u->process();

		if ($url_result['res']) {
			// If the call method is OPTIONS, just return OK right away
			if ($url_result['method']==='options'){
				header($_SERVER['SERVER_PROTOCOL'].' 200 OK');
				exit;
			}

			// If there is a filter defined, apply it before the controller
			if (array_key_exists('filters', $url_result) && count($url_result['filters']) > 0) {
				$filter_check =  true;
				$filter_return = null;
				foreach ($url_result['filters'] as $filter_name => $value) {
					// Check if the filter's file exist as it is loaded per request
					$filter_route = $this->config->getDir('app_filter').$filter_name.'Filter.php';
					if (file_exists($filter_route)) {
						$value = call_user_func(
							["\\Osumi\\OsumiFramework\\App\\Filter\\".$filter_name."Filter", 'handle'],
							$url_result['params'],
							$url_result['headers']
						);

						// If status is not 'ok', filter checks have failed
						if ($value['status'] !== 'ok') {
							$filter_check = false;
							if (is_null($filter_return) && array_key_exists('return', $value)) {
								$filter_return = $value['return'];
							}
							break;
						}

						// Store the result value
						$url_result['filters'][$filter_name] = $value;
					}
					else {
						OTools::showErrorPage($url_result, '403');
					}
				}

				// If filter checks didn't pass
				if (!$filter_check) {
					// If return value has been set in any of the filters, go there, otherwise go to error page
					if (!is_null($filter_return)) {
						OUrl::goToUrl($filter_return);
					}
					else {
						OTools::showErrorPage($url_result, '403');
					}
				}
			}

			$module_path = $this->config->getDir('app_module').$url_result['module'].'/'.$url_result['module'].'.php';

			if (file_exists($module_path)) {
				$module_name = "\\Osumi\\OsumiFramework\\App\\Module\\".$url_result['module']."\\".$url_result['module'];
				$module = new $module_name;
				$module_attributes = OBuild::getClassAttributes($module);

				if (in_array($url_result['action'], $module_attributes->getActions())) {
					$action_path = $this->config->getDir('app_module').$url_result['module'].'/Actions/'.$url_result['action'].'/'.$url_result['action'].'Action.php';
					if (file_exists($action_path)) {
						$action_name = "Osumi\\OsumiFramework\\App\\Module\\".$url_result['module']."\\Actions\\".$url_result['action']."\\".$url_result['action']."Action";

						$action = new $action_name;
						$action_attributes = OBuild::getClassAttributes($action);
						$reflection_param = new ReflectionParameter([$action_name, 'run'], 0);
						$reflection_param_type = $reflection_param->getType()->getName();

						$req = new ORequest($url_result);
						if (str_starts_with($reflection_param_type, 'Osumi\OsumiFramework\App\DTO')) {
							$param = new $reflection_param_type;
							$param->load($req);
						}
						else {
							$param = $req;
						}

						$action->loadAction($url_result, $action_attributes);
						$action->run($param);
						echo $action->getTemplate()->process();
					}
					else {
						OTools::showErrorPage($url_result, 'action');
					}
				}
				else {
					OTools::showErrorPage($url_result, 'action');
				}
			}
			else {
				OTools::showErrorPage($url_result, 'module');
			}
		}
		else {
			OTools::showErrorPage($url_result, '404');
		}

		if (!is_null($this->dbContainer)) {
			$this->dbContainer->closeAllConnections();
		}
	}

	/**
	 * Custom error handler, shows an error page and the error's stack trace
	 *
	 * @param Throwable $ex Given error
	 *
	 * @return void
	 */
	public function errorHandler(\Throwable $ex): void {
		$log = new OLog(get_class($this));
		$params = ['message' => OTools::getMessage('ERROR_500_LABEL')];
		$params['message'] = "<strong>Error:</strong> \"".$ex->getMessage()."\"\n<strong>File:</strong> \"".$ex->getFile()."\" (Line: ".$ex->getLine().")\n\n<strong>Trace:</strong> \n";
		foreach ($ex->getTrace() as $trace) {
			if (array_key_exists('file', $trace)) {
				$params['message'] .= "  <strong>File:</strong> \"".$trace['file']." (Line: ".$trace['line'].")\"\n";
			}
			if (array_key_exists('class', $trace)) {
				$params['message'] .= "  <strong>Class:</strong> \"".$trace['class']."\"\n";
			}
			if (array_key_exists('function', $trace)) {
				$params['message'] .= "  <strong>Function:</strong> \"".$trace['function']."\"\n\n";
			}
		}
		$log->error( str_ireplace('</strong>', '', str_ireplace('<strong>', '', $params['message'])) );
		OTools::showErrorPage($params, '500');
	}
}
