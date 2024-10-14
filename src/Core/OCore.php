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
use Osumi\OsumiFramework\Routing\ORoute;
use Osumi\OsumiFramework\Tools\OTools;
use Osumi\OsumiFramework\Tools\OBuild;
use Osumi\OsumiFramework\Log\OLog;
use Osumi\OsumiFramework\Core\OComponent;

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
	public array            $services = [];
	public array            $includes = [
    'css' => [],
    'inline_css' => [],
    'js' => [],
    'inline_js' => []
  ];
	private array $return_types  = [
		'html' => 'text/html',
		'json' => 'application/json',
		'xml'  => 'text/xml'
	];

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

		// Load routes
		$routes_path = $this->config->getDir('app_routes');
		$files = scandir($routes_path);
    foreach ($files as $file) {
			if ($file === '.' || $file === '..') {
				continue;
			}
			require_once $routes_path.$file;
		}

		// Load global functions
		require_once $this->config->getDir('ofw_tools').'functions.php';
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
			if ($url_result['method'] === 'OPTIONS'){
				header($_SERVER['SERVER_PROTOCOL'].' 200 OK');
				exit;
			}

			// Check method
			if ($url_result['method'] !== $url_result['component_method']) {
				$url_result['message'] = 'Method not allowed, expected "' . $url_result['component_method'].'" but received "' . $url_result['method'].'".';
				OTools::showErrorPage($url_result, 'method');
				exit;
			}

			// If there is a filter defined, apply it before the controller
			$filter_results = [];
			if (array_key_exists('filters', $url_result) && count($url_result['filters']) > 0) {
				$filter_check =  true;
				$filter_return = null;
				foreach ($url_result['filters'] as $filter) {
					$filter_instance = new $filter();
					$value = $filter_instance->handle(
						$url_result['params'],
						$url_result['headers']
					);
					$reflection = new ReflectionClass($filter_instance);
  				$class_name = str_ireplace('Filter', '', $reflection->getShortName());

					// If status is not 'ok', filter checks have failed
					if ($value['status'] !== 'ok') {
						$filter_check = false;
						if (is_null($filter_return) && array_key_exists('return', $value)) {
							$filter_return = $value['return'];
						}
						break;
					}

					// Store the result value
					$filter_results[$class_name] = $value;
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

			$component_instance = new $url_result['component']();
			$reflection_param = new ReflectionParameter([$component_instance, 'run'], 0);
			$reflection_param_type = $reflection_param->getType()->getName();

			$req = new ORequest($url_result, $filter_results);
			if (str_starts_with($reflection_param_type, 'Osumi\OsumiFramework\App\DTO')) {
				$param = new $reflection_param_type();
				$param->load($req);
			}
			else {
				$param = $req;
			}

			$component_instance->run($param);
			$body = $component_instance->render();
			$return_type = $component_instance->component_info['template_type'];

			// If there is a layout defined
			if (!is_null($url_result['layout'])) {
				$layout_instance = new $url_result['layout']();
				// Add title and executed component's body
				$layout_instance->title = $this->config->getDefaultTitle();
				$layout_instance->body = $body;
				// Get resulting body
				$layout_body = $layout_instance->render();

				// Add any CSS, inline CSS, JS or inline JS
				if (stripos($layout_body, '</head>') !== false) {
					$layout_body = str_ireplace('</head>', $this->renderInline().'</head>', $layout_body);
					$layout_body = str_ireplace('</head>', $this->renderExternal().'</head>', $layout_body);
				}
				$body = $layout_body;
				$return_type = $component_instance->component_info['template_type'];
			}

			// If type is not html is most likely it's and API call so tell the browsers not to cache it
			if ($return_type !== 'html') {
				header('Cache-Control: no-cache, must-revalidate');
				header('Expires: Thu, 02 Jul 1981 03:00:00 GMT');
			}

			header('Content-type: '.$this->return_types[$return_type]);
			header('X-Powered-By: Osumi Framework '.OTools::getVersion());

			// Show resulting HTML
			echo $body;
		}
		else {
			OTools::showErrorPage($url_result, '404');
		}

		if (!is_null($this->dbContainer)) {
			$this->dbContainer->closeAllConnections();
		}
	}

	/**
   * Returns inline content (CSS and JS)
   *
   * @return string Inline content, if any
   */
  private function renderInline(): string {
    $ret = '';
		// Add global CSS files
		if (count($this->config->getCssList())) {
			foreach ($this->config->getCssList() as $css) {
				$this->includes['inline_css'][] = $this->config->getDir('public').'css/'.$css.'.css';
			}
		}
		// Add global JS files
		if (count($this->config->getJsList())) {
			foreach ($this->config->getJsList() as $js) {
				$this->includes['inline_js'][] = $this->config->getDir('public').'js/'.$js.'.js';
			}
		}

		// Process inline CSS files
    if (count($this->includes['inline_css']) > 0) {
      foreach ($this->includes['inline_css'] as $css) {
        if (file_exists($css)) {
          $ret .= "<style>\n";
          $ret .= file_get_contents($css);
          $ret .= "</style>\n";
        }
        else {
          throw new Exception("No valid inline CSS file found: " . $css);
        }
      }
    }
		// Process inline JS files
    if (count($this->includes['inline_js']) > 0) {
      foreach ($this->includes['inline_js'] as $js) {
        if (file_exists($js)) {
          $ret .= "<script>\n";
          $ret .= file_get_contents($js);
          $ret .= "</script>\n";
        }
        else {
          throw new Exception("No valid inline JS file found for the component: " . $js);
        }
      }
    }

    return $ret;
  }

	/**
   * Returns external content (CSS and JS)
   *
   * @return string External content, if any
   */
  private function renderExternal(): string {
    $ret = '';
		// Add global external CSS files
		if (count($this->config->getExtCssList())) {
			foreach ($this->config->getExtCssList() as $css) {
				$this->includes['css'][] = $css;
			}
		}
		// Add global external JS files
		if (count($this->config->getExtJsList())) {
			foreach ($this->config->getExtJsList() as $js) {
				$this->includes['js'][] = $js;
			}
		}

		// Process CSS files
    if (count($this->includes['css']) > 0) {
      foreach ($this->includes['css'] as $css) {
        $ret .= "<link rel=\"stylesheet\" type=\"text/css\" href=\"".$css."\">\n";
      }
    }
		// Process JS files
    if (count($this->includes['js']) > 0) {
      foreach ($this->includes['js'] as $js) {
        $ret .= "<script src=\"".$js."\"></script>\n";
      }
    }

    return $ret;
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
