<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Web;

/**
 * ORequest - Container class with information about a user made request (method, headers, parameters and filters)
 */
class ORequest {
	private string | null $method = null;
	private array $headers = [];
	private array $params = [];
	private array $filters = [];

	function __construct(array $url_result, array $filter_results) {
		$this->setMethod($url_result['method']);
		$this->setHeaders($url_result['headers']);
		$this->setParams($url_result['params']);
		$this->setFilters($filter_results);
	}

	/**
	 * Set HTTP method used in the call (GET/POST...)
	 *
	 * @param string $method HTTP method used in the call
	 *
	 * @return void
	 */
	public function setMethod(string $method): void {
		$this->method = $method;
	}

	/**
	 * Get HTTP method used in the call
	 *
	 * @return string | null HTTP method used in the call
	 */
	public function getMethod(): string | null {
		return $this->method;
	}

	/**
	 * Set HTTP headers used in the call
	 *
	 * @param array $headers List of HTTP headers used in the call
	 *
	 * @return void
	 */
	public function setHeaders(array $headers): void {
		$this->headers = $headers;
	}

	/**
	 * Get list of HTTP headers used in the call
	 *
	 * @return array List of HTTP headers used in the call
	 */
	public function getHeaders(): array {
		return $this->headers;
	}

	/**
	 * Get a specific HTTP header, null if not found
	 *
	 * @param string $key Key code of the HTTP header
	 *
	 * @return string | null Value of the requested HTTP header or null if not found
	 */
	public function getHeader(string $key): string | null {
		return array_key_exists($key, $this->headers) ? $this->headers[$key] : null;
	}

	/**
	 * Set list of parameters passed in the call
	 *
	 * @param array $params List of parameters passed in the call
	 *
	 * @return void
	 */
	public function setParams(array $params): void {
		$this->params = $params;
	}

	/**
	 * Get list of parameters passed in the call
	 *
	 * @return array List of parameters passed in the call
	 */
	public function getParams(): array {
		return $this->params;
	}

	/**
	 * Get a specific parameter or a default value if not found
	 *
	 * @param string $key Key code of the value to be retrieved
	 *
	 * @param mixed $default Default value if key not found
	 */
	public function getParam(string $key, mixed $default = null) {
		return array_key_exists($key, $this->params) ? $this->params[$key] : $default;
	}

	/**
	 * Get a specific parameter as a string
	 *
	 * @param string $key Key code of the value to be retrieved
	 *
	 * @param mixed $default Default value if key not found
	 *
	 * @return string | null String value of the required parameter
	 */
	public function getParamString(string $key, mixed $default = null): string | null {
		$param = $this->getParam($key, $default);
		return !is_null($param) ? strval($param) : null;
	}

	/**
	 * Get a specific parameter as an int
	 *
	 * @param string $key Key code of the value to be retrieved
	 *
	 * @param mixed $default Default value if key not found
	 *
	 * @return int | null Int value of the required parameter
	 */
	public function getParamInt(string $key, mixed $default = null): int | null {
		$param = $this->getParam($key, $default);
		return (!is_null($param) && $param !== 'null' && is_numeric($param)) ? intval($param) : null;
	}

	/**
	 * Get a specific parameter as a float
	 *
	 * @param string $key Key code of the value to be retrieved
	 *
	 * @param mixed $default Default value if key not found
	 *
	 * @return float | null Float value of the required parameter
	 */
	public function getParamFloat(string $key, mixed $default = null): float | null {
		$param = $this->getParam($key, $default);
		return (!is_null($param) && $param !== 'null' && is_numeric($param)) ? floatval($param) : null;
	}

	/**
	 * Get a specific parameter as a boolean
	 *
	 * @param string $key Key code of the value to be retrieved
	 *
	 * @param mixed $default Default value if key not found
	 *
	 * @return bool | null Boolean value of the required parameter
	 */
	public function getParamBool(string $key, mixed $default = null): bool | null {
		$param = $this->getParam($key, $default);
		return !is_null($param) ? filter_var($param, FILTER_VALIDATE_BOOLEAN) : null;
	}

	/**
	 * Set filters returned values
	 *
	 * @param array $filters List of values returned by filters
	 *
	 * @return void
	 */
	public function setFilters(array $filters): void {
		$this->filters = $filters;
	}

	/**
	 * Get list of filters returned values
	 *
	 * @return array List of filters returned values
	 */
	public function getFilters(): array {
		return $this->filters;
	}

	/**
	 * Set the values returned of a specific filter
	 *
	 * @param string $key Name of the filter
	 *
	 * @param array $values Values returned by the filter
	 *
	 * @return false
	 */
	public function setFilter(string $key, array $values): void {
		$this->filters[$key] = $values;
	}

	/**
	 * Get the values returned by a specific filter or null if not found
	 *
	 * @param string $key Name of the filter
	 *
	 * @return array | null Values returned by the filter or null if not found
	 */
	public function getFilter(string $key): array | null {
		return array_key_exists($key, $this->filters) ? $this->filters[$key] : null;
	}
}
