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
		$translate = new OTranslate();
		$translate->load(
			$locale_file
		);

		$this->translate = $translate;

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
		$routes_path = rtrim(
			$this->config->getDir('app_routes'),
			'/\\'
		);

		if (!is_dir($routes_path)) {
			throw new \RuntimeException(
				"Routes directory '{$routes_path}' does not exist."
			);
		}

		if (!is_readable($routes_path)) {
			throw new \RuntimeException(
				"Routes directory '{$routes_path}' is not readable."
			);
		}

		$files = scandir($routes_path);

		if ($files === false) {
			throw new \RuntimeException(
				"Could not scan routes directory '{$routes_path}'."
			);
		}

		foreach ($files as $file) {
			$route_file = $routes_path
				. DIRECTORY_SEPARATOR
				. $file;

			if (
				!is_file($route_file) ||
				strtolower(
					pathinfo(
						$route_file,
						PATHINFO_EXTENSION
					)
				) !== 'php'
			) {
				continue;
			}

			if (!is_readable($route_file)) {
				throw new \RuntimeException(
					"Route file '{$route_file}' is not readable."
				);
			}

			require_once $route_file;
		}

		// Load global middlewares (project-level)
		OMiddleware::setGlobal([]);

		$middlewares_file = rtrim(
			$this->config->getDir('app_middleware'),
			'/\\'
		)
			. DIRECTORY_SEPARATOR
			. 'Middlewares.php';

		if (file_exists($middlewares_file)) {
			if (
				!is_file($middlewares_file) ||
				!is_readable($middlewares_file)
			) {
				throw new \RuntimeException(
					"Middleware configuration file '{$middlewares_file}' is not readable."
				);
			}

			require $middlewares_file;
		}

		// Load global functions
		require_once $this->config->getDir('ofw_tools') . 'functions.php';
	}

	/**
	 * Process the current HTTP request and execute the matched route.
	 *
	 * The request is resolved against the registered routes, route middlewares
	 * are configured, and the middleware pipeline is executed in the following
	 * order: before, component or view rendering, afterRender, layout rendering
	 * and afterResponse. Middleware stops are converted into an error response
	 * and still pass through afterResponse before being emitted.
	 *
	 * @return void
	 *
	 * @throws \LogicException If the core has not been loaded before execution.
	 * @throws \RuntimeException If a matched component, view or layout cannot be
	 *                           resolved, read or executed safely.
	 * @throws \InvalidArgumentException If middleware configuration or response
	 *                                    data contains an invalid value.
	 * @throws \UnexpectedValueException If a middleware returns an invalid result.
	 * @throws \JsonException If a JSON middleware error response cannot be encoded.
	 * @throws \Exception If a component run() method has an invalid signature.
	 */
	public function run(): void {
		if ($this->config === null) {
			throw new \LogicException(
				'OCore must be loaded before run() is called.'
			);
		}

		if ($this->config->getAllowCrossOrigin()) {
			header('Access-Control-Allow-Origin: *');
			header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
			header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
		}

		$url = new OUrl(
			$_SERVER['REQUEST_METHOD']
		);
		$url->setCheckUrl(
			$_SERVER['REQUEST_URI'],
			$_GET,
			$_POST,
			$_FILES
		);

		$url_result = $url->process();

		if (!$url_result['res']) {
			$this->setHttpStatus(
				404
			);
			$this->closeDbConnections();

			OTools::showErrorPage(
				$url_result,
				'404'
			);

			return;
		}

		if ($url_result['method'] === 'OPTIONS') {
			if (!headers_sent()) {
				http_response_code(
					200
				);
			}

			$this->closeDbConnections();

			return;
		}

		if ($url_result['method'] !== $url_result['component_method']) {
			$url_result['message'] = OTools::getMessage(
				'ERROR_405_MESSAGE',
				[
					$url_result['component_method'],
					$url_result['method']
				]
			);

			$this->setHttpStatus(
				405
			);
			$this->closeDbConnections();

			OTools::showErrorPage(
				$url_result,
				'405'
			);

			return;
		}

		OMiddleware::reset();
		OMiddleware::setRoute(
			$url_result['middlewares']
		);

		$expected_type = $this->getExpectedResponseType(
			$url_result
		);

		$this->prepareMiddlewareResponseHeaders(
			$expected_type
		);

		/** @var array<string, mixed> $middleware_data */
		$middleware_data = [
			'route' => $url_result,
			'params' => $url_result['params'],
			'headers' => $url_result['headers'],
			'expected_type' => $expected_type,
			'response_type' => $expected_type
		];

		$before_result = OMiddleware::runPhase(
			OMiddleware::PHASE_BEFORE,
			$middleware_data
		);

		if ($before_result['stop']) {
			OMiddleware::setFinalBody(
				$this->buildMiddlewareErrorBody(
					$expected_type,
					$before_result['status_code'],
					$before_result['message']
				)
			);

			OMiddleware::runPhase(
				OMiddleware::PHASE_AFTER_RESPONSE,
				$middleware_data
			);

			$this->emitMiddlewareResponse();
			$this->closeDbConnections();

			return;
		}

		$body = '';
		$return_type = $expected_type;

		if (!$url_result['is_view']) {
			$component = $url_result['component'];

			if (
				!is_string($component) ||
				$component === '' ||
				!class_exists($component)
			) {
				throw new \RuntimeException(
					'Matched route does not contain a valid component class.'
				);
			}

			$component_instance = new $component();
			$reflection = new ReflectionClass(
				$component_instance
			);

			if (!$reflection->hasMethod('run')) {
				$body = $component_instance->render();
			} else {
				$run_method = $reflection->getMethod(
					'run'
				);
				$run_parameters = $run_method->getParameters();
				$run_parameter_count = count(
					$run_parameters
				);

				if ($run_parameter_count === 0) {
					$body = $component_instance->render();
				} elseif ($run_parameter_count === 1) {
					$reflection_param_type = $run_parameters[0]->getType();

					if (
						!$reflection_param_type instanceof ReflectionNamedType ||
						$reflection_param_type->allowsNull()
					) {
						throw new Exception(
							"The run method of component '{$component}' must receive an ORequest or a class extending ODTO."
						);
					}

					$param_class = $reflection_param_type->getName();
					$request = new ORequest(
						$url_result,
						[]
					);

					if ($param_class === ORequest::class) {
						$body = $component_instance->render(
							$request
						);
					} elseif (
						class_exists($param_class) &&
						is_subclass_of(
							$param_class,
							ODTO::class
						)
					) {
						/** @var ODTO $dto */
						$dto = new $param_class(
							$request
						);

						$body = $component_instance->render(
							$dto
						);
					} else {
						throw new Exception(
							"The run method of component '{$component}' must receive an ORequest or a class extending ODTO. Received: '{$param_class}'."
						);
					}
				} else {
					throw new Exception(
						"The run method of component '{$component}' can receive at most one parameter."
					);
				}
			}

			$return_type = $this->normalizeResponseType(
				(string) $component_instance->component_info['template_type']
			);
		} else {
			$view = $url_result['component'];

			if (
				!is_string($view) ||
				$view === ''
			) {
				throw new \RuntimeException(
					'Matched view route does not contain a valid view path.'
				);
			}

			$view_file = $this->config->getDir('app')
				. $view;

			if (
				!is_file($view_file) ||
				!is_readable($view_file)
			) {
				$url_result['message'] = OTools::getMessage(
					'ERROR_VIEW_MESSAGE',
					[
						$view
					]
				);

				$this->setHttpStatus(
					500
				);
				$this->closeDbConnections();

				OTools::showErrorPage(
					$url_result,
					'view'
				);

				return;
			}

			$view_content = file_get_contents(
				$view_file
			);

			if ($view_content === false) {
				throw new \RuntimeException(
					"Unable to read view file '{$view_file}'."
				);
			}

			$body = $view_content;
			$return_type = $this->normalizeResponseType(
				pathinfo(
					$view_file,
					PATHINFO_EXTENSION
				)
			);
		}

		OMiddleware::setComponentBody(
			$body
		);

		$middleware_data['response_type'] = $return_type;

		$after_render_result = OMiddleware::runPhase(
			OMiddleware::PHASE_AFTER_RENDER,
			$middleware_data
		);

		if ($after_render_result['stop']) {
			OMiddleware::setFinalBody(
				$this->buildMiddlewareErrorBody(
					$return_type,
					$after_render_result['status_code'],
					$after_render_result['message']
				)
			);

			OMiddleware::runPhase(
				OMiddleware::PHASE_AFTER_RESPONSE,
				$middleware_data
			);

			$this->emitMiddlewareResponse();
			$this->closeDbConnections();

			return;
		}

		$body = OMiddleware::getComponentBody();

		if ($url_result['layout'] !== null) {
			$layout = $url_result['layout'];

			if (
				!is_string($layout) ||
				$layout === '' ||
				!class_exists($layout)
			) {
				throw new \RuntimeException(
					'Matched route does not contain a valid layout class.'
				);
			}

			$layout_instance = new $layout();
			$layout_instance->title = $this->config->getDefaultTitle();
			$layout_instance->body = $body;

			$layout_body = $layout_instance->render();

			if (
				stripos(
					$layout_body,
					'</head>'
				) !== false
			) {
				$layout_body = str_ireplace(
					'</head>',
					$this->renderInline() . '</head>',
					$layout_body
				);
				$layout_body = str_ireplace(
					'</head>',
					$this->renderExternal() . '</head>',
					$layout_body
				);
			}

			$body = $layout_body;
		}

		OMiddleware::setFinalBody(
			$body
		);

		$middleware_data['final_body'] = $body;

		OMiddleware::runPhase(
			OMiddleware::PHASE_AFTER_RESPONSE,
			$middleware_data
		);

		$this->emitMiddlewareResponse();
		$this->closeDbConnections();
	}

	/**
	 * Determine the expected response type for a matched route without
	 * instantiating its component.
	 *
	 * Static views use their file extension. Component routes are inspected by
	 * reflection and their template file is resolved using the same supported
	 * extensions as OComponent. PHP templates are normalized to HTML responses.
	 *
	 * @param array<string, mixed> $url_result Processed route information.
	 *
	 * @return string Normalized response type: html, json or xml.
	 *
	 * @throws \RuntimeException If the component class does not exist or its
	 *                           source file cannot be resolved.
	 */
	private function getExpectedResponseType(array $url_result): string {
		$component = $url_result['component'];

		if (!is_string($component) || $component === '') {
			return 'html';
		}

		if ($url_result['is_view']) {
			return $this->normalizeResponseType(
				pathinfo(
					$component,
					PATHINFO_EXTENSION
				)
			);
		}

		if (!class_exists($component)) {
			throw new \RuntimeException(
				"Route component '{$component}' does not exist."
			);
		}

		$reflection = new ReflectionClass(
			$component
		);
		$component_file = $reflection->getFileName();

		if ($component_file === false) {
			throw new \RuntimeException(
				"Could not resolve component file for '{$component}'."
			);
		}

		$base_name = str_ireplace(
			'Component',
			'',
			pathinfo(
				$component_file,
				PATHINFO_FILENAME
			)
		);

		foreach (
			[
				'html',
				'json',
				'xml',
				'php'
			] as $extension
		) {
			$template_file = dirname(
				$component_file
			)
				. DIRECTORY_SEPARATOR
				. $base_name
				. 'Template.'
				. $extension;

			if (is_file($template_file)) {
				return $this->normalizeResponseType(
					$extension
				);
			}
		}

		return 'html';
	}

	/**
	 * Normalize a template or response type to a supported HTTP response type.
	 *
	 * PHP templates are treated as HTML. Unknown values also fall back to HTML.
	 *
	 * @param string $type Template or response type.
	 *
	 * @return string Normalized response type: html, json or xml.
	 */
	private function normalizeResponseType(string $type): string {
		$type = strtolower(
			trim(
				$type
			)
		);

		if ($type === 'php') {
			return 'html';
		}

		return array_key_exists(
			$type,
			$this->return_types
		)
			? $type
			: 'html';
	}

	/**
	 * Build a middleware stop response body for the requested response type.
	 *
	 * The temporary F3 implementation generates minimal valid HTML, JSON or XML
	 * responses. Dedicated framework error templates will replace this fallback
	 * in the later middleware error-response block.
	 *
	 * @param string $type Requested response type.
	 * @param int $status_code HTTP status code returned by the middleware.
	 * @param string $message Public middleware error message.
	 *
	 * @return string Encoded middleware error response body.
	 *
	 * @throws \JsonException If the JSON response cannot be encoded.
	 */
	private function buildMiddlewareErrorBody(
		string $type,
		int $status_code,
		string $message
	): string {
		$type = $this->normalizeResponseType(
			$type
		);

		if ($type === 'json') {
			return json_encode(
				[
					'status' => 'error',
					'status_code' => $status_code,
					'message' => $message
				],
				JSON_UNESCAPED_UNICODE |
					JSON_UNESCAPED_SLASHES |
					JSON_THROW_ON_ERROR
			);
		}

		if ($type === 'xml') {
			return '<error><status>error</status><status_code>'
				. $status_code
				. '</status_code><message>'
				. htmlspecialchars(
					$message,
					ENT_QUOTES |
						ENT_XML1 |
						ENT_SUBSTITUTE,
					'UTF-8'
				)
				. '</message></error>';
		}

		return '<h1>Error '
			. $status_code
			. '</h1><p>'
			. htmlspecialchars(
				$message,
				ENT_QUOTES |
					ENT_SUBSTITUTE |
					ENT_HTML5,
				'UTF-8'
			)
			. '</p>';
	}

	/**
	 * Initialize the response headers exposed to the middleware pipeline.
	 *
	 * Non-HTML responses receive the framework no-cache headers. Middleware
	 * phases may subsequently replace any of these headers through OMiddleware.
	 *
	 * @param string $type Expected response type.
	 *
	 * @return void
	 *
	 * @throws \InvalidArgumentException If a generated response header is invalid.
	 */
	private function prepareMiddlewareResponseHeaders(
		string $type
	): void {
		$type = $this->normalizeResponseType(
			$type
		);

		if ($type !== 'html') {
			OMiddleware::setHeader(
				'Cache-Control',
				'no-cache, must-revalidate'
			);
			OMiddleware::setHeader(
				'Expires',
				'Thu, 02 Jul 1981 03:00:00 GMT'
			);
		}

		OMiddleware::setHeader(
			'Content-Type',
			$this->return_types[$type]
		);
		OMiddleware::setHeader(
			'X-Powered-By',
			'Osumi Framework '
				. OTools::getVersion()
		);
	}

	/**
	 * Emit the final response accumulated by the middleware pipeline.
	 *
	 * The final middleware status code and response headers are applied before
	 * writing the final response body.
	 *
	 * @return void
	 */
	private function emitMiddlewareResponse(): void {
		$status_code = OMiddleware::isError()
			? OMiddleware::getErrorStatusCode()
			: OMiddleware::getStatusCode();

		$this->setHttpStatus(
			$status_code
		);

		if (!headers_sent()) {
			http_response_code(
				$status_code
			);

			foreach (OMiddleware::getHeaders() as $name => $value) {
				header(
					$name . ': ' . $value,
					true
				);
			}
		}

		echo OMiddleware::getFinalBody();
	}

	/**
	 * Close all active framework database connections when a database container
	 * is available.
	 *
	 * @return void
	 */
	private function closeDbConnections(): void {
		if ($this->db_container !== null) {
			$this->db_container->closeAllConnections();
		}
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
