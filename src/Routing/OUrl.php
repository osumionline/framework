<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Routing;

use Osumi\OsumiFramework\Routing\ORoute;

/**
 * OUrl - Class with methods to check required URL, get its data, generate new URLs or redirect the user to a new one
 */
class OUrl {
	/**
	 * @var list<array{
	 *     method: string,
	 *     url: string,
	 *     component: string,
	 *     filters: array,
	 *     layout: string|null,
	 *     is_view: bool
	 * }>
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
	 * A route matching both URL and HTTP method has priority. If the URL exists
	 * but no route accepts the current method, the first URL match is returned so
	 * the caller can generate a 405 response. OPTIONS requests also use the first
	 * URL match, allowing preflight requests to be handled without executing the
	 * route component.
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
	public function process(
		?string $url = null
	): array {
		if ($url !== null) {
			$this->check_url = $url;
		}

		$ret = [
			'component' => null,
			'filters' => [],
			'layout' => null,
			'type' => 'html',
			'params' => [],
			'headers' => $this->getRequestHeaders(),
			'method' => $this->method,
			'component_method' => '',
			'is_view' => false,
			'res' => false
		];

		$first_url_match = null;
		$method_match = null;

		foreach ($this->urls as $route) {
			$route_check = new ORouteCheck(
				$route['url']
			);

			$params = $route_check->matchesUrl(
				$this->check_url
			);

			if ($params === null) {
				continue;
			}

			$match = [
				'route' => $route,
				'params' => $params
			];

			if ($first_url_match === null) {
				$first_url_match = $match;
			}

			if ($route['method'] === $this->method) {
				$method_match = $match;
				break;
			}
		}

		/*
		 * OPTIONS intentionally falls back to the first matching URL so OCore can
		 * answer the preflight request without requiring an explicit OPTIONS route.
		 *
		 * For other unsupported methods, the same fallback lets OCore return 405
		 * instead of incorrectly reporting 404.
		 */
		$selected_match = $method_match
			?? $first_url_match;

		if ($selected_match === null) {
			return $ret;
		}

		$route = $selected_match['route'];
		$route_params = $selected_match['params'];

		$ret['res'] = true;
		$ret['component'] = $route['component'];
		$ret['component_method'] = $route['method'];
		$ret['is_view'] = $route['is_view'];
		$ret['filters'] = $route['filters'];
		$ret['layout'] = $route['layout'];

		foreach ($route_params as $key => $value) {
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

		return $ret;
	}

	/**
	 * Generate the URL registered for a component.
	 *
	 * Route parameters are substituted using scalar values. Both a fully-qualified
	 * component class name and its short class name are accepted.
	 *
	 * @param string $component Component class or short component name.
	 * @param array<array-key, mixed> $params Dynamic route parameters.
	 * @param bool $absolute Whether to prepend the configured base URL.
	 *
	 * @return string Generated URL, or an empty string if no route is registered
	 *                for the component.
	 *
	 * @throws \InvalidArgumentException If a parameter key or value cannot be used
	 *                                   in a URL.
	 */
	public static function generateUrl(
		string $component,
		array $params = [],
		bool $absolute = false
	): string {
		global $core;

		$normalized_component = ltrim(
			$component,
			'\\'
		);

		if ($normalized_component === '') {
			return '';
		}

		$is_fully_qualified = str_contains(
			$normalized_component,
			'\\'
		);

		$url = '';

		foreach (ORoute::$routes as $route) {
			$route_component = ltrim(
				$route['component'],
				'\\'
			);

			if ($is_fully_qualified) {
				if ($route_component !== $normalized_component) {
					continue;
				}
			} else {
				$route_parts = explode(
					'\\',
					$route_component
				);

				if (
					array_pop($route_parts) !==
					$normalized_component
				) {
					continue;
				}
			}

			$url = $route['url'];
			break;
		}

		if ($url === '') {
			return '';
		}

		foreach ($params as $key => $value) {
			if (!is_string($key)) {
				throw new \InvalidArgumentException(
					'URL parameter keys must be strings.'
				);
			}

			if (
				!is_string($value) &&
				!is_int($value) &&
				!is_float($value) &&
				!is_bool($value)
			) {
				throw new \InvalidArgumentException(
					"URL parameter '{$key}' must be a scalar value."
				);
			}

			$url = str_replace(
				':' . $key,
				rawurlencode(
					(string) $value
				),
				$url
			);
		}

		if ($absolute) {
			$base = rtrim(
				$core->config->getUrl('base'),
				'/'
			);

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
