<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Routing;

use Osumi\OsumiFramework\Routing\ORoute;

/**
 * OUrl - Class with methods to check required URL, get its data, generate new URLs or redirect the user to a new one
 */
class OUrl {
	/**
	 * @var array<int, array<string, mixed>>
	 */
	private array $urls = [];

	private string $check_url = '';

	/**
	 * @var array<string, mixed>
	 */
	private array $url_params = [];

	private string $method = '';

	/**
	 * Create a URL processor for the given HTTP method.
	 *
	 * @param string $method HTTP method.
	 */
	public function __construct(string $method) {
		$this->method = strtoupper($method);
		$this->urls = ORoute::$routes;
	}

	/**
	 * Add request parameters from a specific source.
	 *
	 * @param array<array-key, mixed> $params Parameters to add.
	 * @param string $source Parameter source used in error messages.
	 *
	 * @return void
	 *
	 * @throws \InvalidArgumentException If a parameter key is not a string.
	 */
	private function addParams(
		array $params,
		string $source
	): void {
		foreach ($params as $key => $value) {
			if (!is_string($key)) {
				throw new \InvalidArgumentException(
					"{$source} parameter keys must be strings."
				);
			}

			$this->url_params[$key] = $value;
		}
	}

	/**
	 * Get the HTTP request headers.
	 *
	 * @return array<string, string> HTTP request headers.
	 *
	 * @throws \UnexpectedValueException If a header name or value has an invalid
	 *                                   type.
	 */
	private function getRequestHeaders(): array {
		if (!function_exists('getallheaders')) {
			return [];
		}

		$headers = getallheaders();

		if ($headers === false) {
			return [];
		}

		foreach ($headers as $key => $value) {
			if (
				!is_string($key) ||
				!is_string($value)
			) {
				throw new \UnexpectedValueException(
					'HTTP headers must contain string names and string values.'
				);
			}
		}

		return $headers;
	}

	/**
	 * Set the URL to process and load request parameters.
	 *
	 * Parameters are merged in this order:
	 * GET, POST, uploaded files and JSON body. Later sources overwrite earlier
	 * values using the same key.
	 *
	 * @param string $check_url URL to process.
	 * @param array<string, mixed>|null $get GET parameters.
	 * @param array<string, mixed>|null $post POST parameters.
	 * @param array<string, mixed>|null $files Uploaded files.
	 *
	 * @return void
	 *
	 * @throws \InvalidArgumentException If a parameter key is invalid or a JSON
	 *                                   request body does not contain an object at
	 *                                   its root.
	 * @throws \RuntimeException If the request body cannot be read.
	 */
	public function setCheckUrl(
		string $check_url,
		?array $get = null,
		?array $post = null,
		?array $files = null
	): void {
		$this->check_url = $check_url;
		$this->url_params = [];

		$check_params = stripos(
			$check_url,
			'?'
		);

		if ($check_params !== false) {
			$this->check_url = substr(
				$check_url,
				0,
				$check_params
			);
		}

		if ($get !== null) {
			$this->addParams(
				$get,
				'GET'
			);
		}

		if ($post !== null) {
			$this->addParams(
				$post,
				'POST'
			);
		}

		if ($files !== null) {
			$this->addParams(
				$files,
				'FILES'
			);
		}

		$raw_input = file_get_contents(
			'php://input'
		);

		if ($raw_input === false) {
			throw new \RuntimeException(
				'Unable to read the request body.'
			);
		}

		if ($raw_input === '') {
			return;
		}

		try {
			$input = json_decode(
				$raw_input,
				true,
				512,
				JSON_THROW_ON_ERROR
			);
		} catch (\JsonException) {
			/*
		 * The request body is not JSON. It may belong to another supported
		 * content type, so it is ignored here.
		 */
			return;
		}

		$trimmed_input = ltrim($raw_input);

		if (
			$trimmed_input === '' ||
			$trimmed_input[0] !== '{' ||
			!is_array($input)
		) {
			throw new \InvalidArgumentException(
				'JSON request body must contain an object at the root level.'
			);
		}

		$this->addParams(
			$input,
			'JSON request body'
		);
	}

	/**
	 * Process the requested URL against the configured routes.
	 *
	 * @param string|null $url URL to process or null to use the currently loaded
	 *                         URL.
	 *
	 * @return array{
	 *     component: string|null,
	 *     filters: array,
	 *     layout: string|null,
	 *     type: string,
	 *     params: array<string, mixed>,
	 *     headers: array<string, string>,
	 *     method: string,
	 *     component_method: string,
	 *     is_view: bool,
	 *     res: bool
	 * } Processed route information.
	 *
	 * @throws \InvalidArgumentException If a route parameter key is invalid.
	 * @throws \UnexpectedValueException If HTTP headers have an invalid structure.
	 */
	public function process(?string $url = null): array {
		if (!is_null($url)) {
			$this->check_url = $url;
		}

		$found = false;
		$i     = 0;
		$ret   = [
			'component'        => null,
			'filters'          => [],
			'layout'           => null,
			'type'             => 'html',
			'params'           => [],
			'headers'          => $this->getRequestHeaders(),
			'method'           => $this->method,
			'component_method' => '',
			'is_view'          => false,
			'res'              => false
		];

		while (!$found && $i < count($this->urls)) {
			$route = new ORouteCheck($this->urls[$i]['url']);
			$chk = $route->matchesUrl($this->check_url);

			// If there is a match, return Urls.php values plus the parameters in the route and the headers
			if (!is_null($chk)) {
				$found      = true;
				$ret['res'] = true;
				$ret['component']        = $this->urls[$i]['component'];
				$ret['component_method'] = $this->urls[$i]['method'];
				$ret['is_view']          = $this->urls[$i]['is_view'];

				if (array_key_exists('filters', $this->urls[$i])) {
					$ret['filters'] = $this->urls[$i]['filters'];
				}
				if (array_key_exists('layout', $this->urls[$i])) {
					$ret['layout'] = $this->urls[$i]['layout'];
				}

				foreach ($chk as $key => $value) {
					if (!is_string($key)) {
						throw new \InvalidArgumentException(
							'Route parameter keys must be strings.'
						);
					}

					$ret['params'][$key] = $value;
				}

				foreach ($this->url_params as $key => $value) {
					$ret['params'][$key] = $value;
				}
			}

			$i++;
		}
		return $ret;
	}

	/**
	 * Static method to generate a URL for a user configured URL
	 *
	 * @param string $component Component whose url has to be generated
	 *
	 * @param array $params Array of parameters to build the URL in case of a dynamic URL (eg /user/:id/:slug -> /user/1/igorosabel)
	 *
	 * @param bool $absolute If true returns an absolute URL and if false returns a partial URL
	 *
	 * @return string Generated URL with given parameters
	 */
	public static function generateUrl(string $component, array $params = [], bool $absolute = false): string {
		// Load URLs, as it's a static method it won't go through the constructor
		global $core;

		$found  = false;
		$i      = 0;
		$url    = '';
		$routes = ORoute::$routes;

		while (!$found && $i < count($routes)) {
			$check_component = $routes[$i]['component'];
			$check_component_parts = explode('\\', $check_component);
			$check_last_part = array_pop($check_component_parts);

			if ($check_last_part == $component) {
				$url = $routes[$i]['url'];
				$found = true;
			}
			$i++;
		}

		if ($found) {
			foreach ($params as $key => $value) {
				$url = str_replace(':' . $key, $value, $url);
			}
		}

		if ($absolute === true) {
			$base = $core->config->getUrl('base');
			$base = substr($base, 0, strlen($base) - 1);

			$url = $base . $url;
		}

		return $url;
	}

	/**
	 * Static method to redirect the user to a new URL using a 301 redirect
	 *
	 * @param string $url URL where the user will be redirected
	 *
	 * @return void
	 */
	public static function goToUrl(string $url): void {
		header('Location:' . $url);
		exit;
	}
}
