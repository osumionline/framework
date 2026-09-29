<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Core;

use Osumi\OsumiFramework\Core\OConfig;
use Osumi\OsumiFramework\ORM\ODBContainer;
use Osumi\OsumiFramework\Cache\OCacheContainer;
use Osumi\OsumiFramework\Web\OSession;
use Osumi\OsumiFramework\Web\ORequest;
use Osumi\OsumiFramework\Routing\OUrl;
use Osumi\OsumiFramework\Tools\OTools;
use Osumi\OsumiFramework\Log\OLog;
use Osumi\OsumiFramework\DTO\ODTO;
use ReflectionNamedType;
use PDO;
use ReflectionClass;
use Exception;
use Throwable;

/**
 * OCore - Base class for the framework with methods to load required files and start the application
 */
class OCore {
	public ?ODBContainer    $db_container = null;
	public ?OCacheContainer $cache_container = null;
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
	private int $http_status = 200;

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

		return $dir . '/';
	}

	/**
	 * Load and initialize the framework.
	 *
	 * @param bool $from_cli Whether the framework is being loaded from the CLI.
	 *
	 * @return void
	 *
	 * @throws \RuntimeException If the framework cannot be initialized.
	 */
	public function load(bool $from_cli = false): void {
		$this->config = new OConfig($this->getBaseDir());

		if (!date_default_timezone_set($this->config->getTimezone())) {
			throw new \RuntimeException(
				"Could not configure application timezone '{$this->config->getTimezone()}'."
			);
		}

		// Check locale file
		$locale_file = $this->config->getDir('ofw_locale')
			. $this->config->getLang()
			. '.po';

		if (!is_file($locale_file)) {
			throw new \RuntimeException(
				"Locale file '{$locale_file}' was not found."
			);
		}

		// Due to a circular dependancy, check name of the log file after core loading
		if (is_null($this->config->getLog('name'))) {
			$this->config->setLog('name', OTools::slugify($this->config->getName()));
		}

		// Load framework translations
		$this->translate = new OTranslate();
		$this->translate->load($this->config->getDir('ofw_locale') . $this->config->getLang() . '.po');

		// If there is a DB connection configured, check drivers and load required classes
		if ($this->config->getDB('user') !== '' || $this->config->getDB('pass') !== '' || $this->config->getDB('host') !== '' || $this->config->getDB('name') !== '') {
			$pdo_drivers = PDO::getAvailableDrivers();
			$db_driver = $this->config->getDB('driver');

			if (
				!in_array(
					$db_driver,
					$pdo_drivers,
					true
				)
			) {
				throw new \RuntimeException(
					"PDO driver '{$db_driver}' is not available."
				);
			}

			$this->db_container = new ODBContainer();
		}

		if (!$from_cli && $this->config->getUseSession()) {
			$this->startSession();
		}

		// Set up an empty cache container
		$this->cache_container = new OCacheContainer();

		// Load routes
		$routes_path = $this->config->getDir('app_routes');
		$files = scandir($routes_path);
		foreach ($files as $file) {
			if ($file === '.' || $file === '..') {
				continue;
			}
			require_once $routes_path . $file;
		}

		// Load global functions
		require_once $this->config->getDir('ofw_tools') . 'functions.php';
	}

	/**
	 * Start up the application checking the accessed URL, load matched URL or give the appropiate error
	 *
	 * @return void
	 */
	public function run(): void {
		if ($this->config->getAllowCrossOrigin()) {
			header('Access-Control-Allow-Origin: *');
			header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
			header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
		}

		// Load current URL
		$u = new OUrl($_SERVER['REQUEST_METHOD']);
		$u->setCheckUrl($_SERVER['REQUEST_URI'], $_GET, $_POST, $_FILES);
		$url_result = $u->process();

		if ($url_result['res']) {
			// If the call method is OPTIONS, just return OK right away
			if ($url_result['method'] === 'OPTIONS') {
				header($_SERVER['SERVER_PROTOCOL'] . ' 200 OK');
				exit;
			}

			// Check method
			if ($url_result['method'] !== $url_result['component_method']) {
				$url_result['message'] = OTools::getMessage('ERROR_405_MESSAGE', [$url_result['component_method'], $url_result['method']]);
				$this->setHttpStatus(405);
				header($_SERVER['SERVER_PROTOCOL'] . ' ' . $this->getHttpStatus());
				OTools::showErrorPage($url_result, '405');
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
					} else {
						$this->setHttpStatus(403);
						OTools::showErrorPage($url_result, '403');
					}
				}
			}

			// If route has a component
			if (!$url_result['is_view']) {
				$component_instance = new $url_result['component']();
				$reflection = new ReflectionClass($component_instance);

				// Component without run()
				if (!$reflection->hasMethod('run')) {
					$body = $component_instance->render();
				} else {
					$run_method = $reflection->getMethod('run');
					$run_parameters = $run_method->getParameters();
					$run_parameter_count = count($run_parameters);

					// run() without parameters
					if ($run_parameter_count === 0) {
						$body = $component_instance->render();
					}
					// run() with one parameter
					elseif ($run_parameter_count === 1) {
						$reflection_param_type = $run_parameters[0]->getType();

						// Parameter must have a non-nullable named type
						if (
							!$reflection_param_type instanceof ReflectionNamedType ||
							$reflection_param_type->allowsNull()
						) {
							throw new Exception(
								"The run method of component '{$url_result['component']}' must receive an ORequest or a class extending ODTO."
							);
						}

						$param_class = $reflection_param_type->getName();
						$req = new ORequest($url_result, $filter_results);

						// ORequest parameter
						if ($param_class === ORequest::class) {
							$body = $component_instance->render($req);
						}
						// ODTO parameter
						elseif (
							class_exists($param_class) &&
							is_subclass_of($param_class, ODTO::class)
						) {
							/** @var ODTO $dto */
							$dto = new $param_class($req);
							$body = $component_instance->render($dto);
						}
						// Any other parameter type is invalid
						else {
							throw new Exception(
								"The run method of component '{$url_result['component']}' must receive an ORequest or a class extending ODTO. Received: '{$param_class}'."
							);
						}
					}
					// More than one parameter is not allowed
					else {
						throw new Exception(
							"The run method of component '{$url_result['component']}' can receive at most one parameter."
						);
					}
				}

				$return_type = $component_instance->component_info['template_type'];
			} else {
				// Route is a view
				$view_file = $this->config->getDir('app') . $url_result['component'];
				if (file_exists($view_file)) {
					$body = file_get_contents($view_file);
					$return_type = pathinfo($view_file, PATHINFO_EXTENSION);
				} else {
					$url_result['message'] = OTools::getMessage('ERROR_VIEW_MESSAGE', [$url_result['component']]);
					OTools::showErrorPage($url_result, 'view');
				}
			}

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
					$layout_body = str_ireplace('</head>', $this->renderInline() . '</head>', $layout_body);
					$layout_body = str_ireplace('</head>', $this->renderExternal() . '</head>', $layout_body);
				}
				$body = $layout_body;
				if (isset($component_instance)) {
					$return_type = $component_instance->component_info['template_type'];
				}
			}

			// If type is not html is most likely it's and API call so tell the browsers not to cache it
			if ($return_type !== 'html') {
				header('Cache-Control: no-cache, must-revalidate');
				header('Expires: Thu, 02 Jul 1981 03:00:00 GMT');
			}

			header('Content-type: ' . $this->return_types[$return_type]);
			header('X-Powered-By: Osumi Framework ' . OTools::getVersion());

			// Show resulting HTML
			echo $body;
		} else {
			$this->setHttpStatus(404);
			OTools::showErrorPage($url_result, '404');
		}

		if (!is_null($this->db_container)) {
			$this->db_container->closeAllConnections();
		}
		header($_SERVER['SERVER_PROTOCOL'] . ' ' . $this->getHttpStatus());
	}

	/**
	 * Configure and start a secure PHP session.
	 *
	 * @return void
	 *
	 * @throws \RuntimeException If a session configuration option cannot be changed
	 *                           or the session cannot be started.
	 */
	private function startSession(): void {
		$session_settings = [
			'session.use_cookies'      => '1',
			'session.use_only_cookies' => '1',
			'session.use_strict_mode'  => '1',
			'session.use_trans_sid'    => '0'
		];

		foreach ($session_settings as $key => $value) {
			if (ini_set($key, $value) === false) {
				throw new \RuntimeException(
					"Could not configure PHP session option '{$key}'."
				);
			}
		}

		if (!session_set_cookie_params([
			'lifetime' => 0,
			'path'     => $this->config->getCookiePath(),
			'domain'   => $this->config->getCookieUrl(),
			'secure'   => $this->config->getCookieSecure(),
			'httponly' => $this->config->getCookieHttpOnly(),
			'samesite' => $this->config->getCookieSameSite()
		])) {
			throw new \RuntimeException(
				'Could not configure PHP session cookie parameters.'
			);
		}

		if (!session_start()) {
			throw new \RuntimeException(
				'Could not start PHP session.'
			);
		}

		$this->session = new OSession();
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
				$this->includes['inline_css'][] = $this->config->getDir('public') . 'css/' . $css . '.css';
			}
		}
		// Add global JS files
		if (count($this->config->getJsList())) {
			foreach ($this->config->getJsList() as $js) {
				$this->includes['inline_js'][] = $this->config->getDir('public') . 'js/' . $js . '.js';
			}
		}

		// Process inline CSS files
		if (count($this->includes['inline_css']) > 0) {
			foreach ($this->includes['inline_css'] as $css) {
				if (file_exists($css)) {
					$ret .= "<style>\n";
					$ret .= file_get_contents($css);
					$ret .= "</style>\n";
				} else {
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
				} else {
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
		// Add head elements defined in config
		if (count($this->config->getHeadElements())) {
			$ret .= $this->buildHeadElements($this->config->getHeadElements());
		}
		// Process CSS files
		if (count($this->includes['css']) > 0) {
			foreach ($this->includes['css'] as $css) {
				$ret .= "<link rel=\"stylesheet\" type=\"text/css\" href=\"" . $css . "\">\n";
			}
		}
		// Process JS files
		if (count($this->includes['js']) > 0) {
			foreach ($this->includes['js'] as $js) {
				$ret .= "<script src=\"" . $js . "\"></script>\n";
			}
		}

		return $ret;
	}

	/**
	 * Build HTML elements for the <head> from an array of definitions.
	 *
	 * Each element of the array must be another array with the keys:
	 * - 'item' => tag name (e.g. 'meta', 'link', 'script')
	 * - 'attributes' => associative array of attributes (e.g. ['rel'=>'icon','href'=>'...'])
	 *
	 * For 'script' tags a full opening and closing tag will be generated
	 * (<script ...></script>), while other tags will be self-closed (<meta ... />).
	 *
	 * @param array $items Array of element definitions
	 *
	 * @return string Concatenated elements separated by "\n"
	 */
	public function buildHeadElements(array $items): string {
		$ret = [];
		foreach ($items as $item) {
			if (!is_array($item)) {
				continue;
			}
			$tag = isset($item['item']) ? strtolower((string)$item['item']) : '';
			if ($tag === '') {
				continue;
			}
			$attrs = isset($item['attributes']) && is_array($item['attributes']) ? $item['attributes'] : [];
			$parts = [];
			foreach ($attrs as $k => $v) {
				if ($v === true) {
					$parts[] = $k;
				} elseif ($v === false || is_null($v)) {
					continue;
				} else {
					$parts[] = $k . '="' . htmlspecialchars((string)$v, ENT_QUOTES) . '"';
				}
			}
			$attr_str = count($parts) ? ' ' . implode(' ', $parts) : '';
			if ($tag === 'script') {
				$ret[] = "<script" . $attr_str . "></script>";
			} else {
				$ret[] = "<" . $tag . $attr_str . " />";
			}
		}
		return implode("\n", $ret);
	}

	/**
	 * Sets the HTTP status
	 *
	 * @param int $http_status HTTP status number
	 *
	 * @return void
	 */
	public function setHttpStatus(int $http_status): void {
		$this->http_status = $http_status;
	}

	/**
	 * Gets full HTTP status
	 *
	 * @return string Fullt HTTP status string
	 */
	public function getHttpStatus(): string {
		switch ($this->http_status) {
			case 200:
				return '200 OK';
				break;
			case 201:
				return '201 Created';
				break;
			case 400:
				return '400 Bad Request';
				break;
			case 401:
				return '401 Unauthorized';
				break;
			case 403:
				return '403 Forbidden';
				break;
			case 404:
				return '404 Not Found';
				break;
			case 405:
				return '405 Method Not Allowed';
				break;
			case 409:
				return '409 Conflict';
				break;
			case 500:
				return '500 Internal Server Error';
				break;
		}
		return '200 OK';
	}

	/**
	 * Handle an uncaught exception.
	 *
	 * Full exception details are logged whenever the framework logging system is
	 * already available. If the framework is only partially initialized, PHP's
	 * native error log is used as a fallback.
	 *
	 * Exception details are never exposed in an HTTP response.
	 *
	 * @param Throwable $ex Exception to handle.
	 *
	 * @return void
	 */
	public function errorHandler(Throwable $ex): void {
		$exception_details = (string) $ex;
		$logged = false;

		if (
			$this->config !== null &&
			$this->config->getLog('name') !== null
		) {
			try {
				$log = new OLog(
					get_class($this)
				);

				$logged = $log->error(
					$exception_details
				);
			} catch (Throwable) {
				// The framework may still be partially initialized.
			}
		}

		if (!$logged) {
			error_log(
				$exception_details
			);
		}

		$this->setHttpStatus(500);

		if (
			$this->config !== null &&
			$this->translate !== null
		) {
			try {
				OTools::showErrorPage(
					[],
					'500'
				);
			} catch (Throwable $handler_error) {
				error_log(
					(string) $handler_error
				);
			}
		}

		if (!headers_sent()) {
			http_response_code(500);

			header(
				'Content-Type: text/plain; charset=UTF-8'
			);
		}

		echo 'Internal Server Error';
	}
}
