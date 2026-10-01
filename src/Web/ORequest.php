<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Web;

use Osumi\OsumiFramework\Core\OMiddleware;

/**
 * Container with information about the current HTTP request.
 *
 * Besides the request method, headers and parameters, the request exposes
 * context published by middlewares executed before the component.
 */
class ORequest {
	private string $method;

	/**
	 * @var array<string, string>
	 */
	private array $headers = [];

	/**
	 * @var array<string, mixed>
	 */
	private array $params = [];

	/**
	 * Create a request instance from processed route information.
	 *
	 * @param array{
	 *     method: string,
	 *     headers: array<string, string>,
	 *     params: array<string, mixed>,
	 *     ...
	 * } $url_result Processed route information.
	 *
	 * @throws \InvalidArgumentException If headers or parameters have invalid keys
	 *                                   or values.
	 */
	public function __construct(
		array $url_result
	) {
		$this->setMethod($url_result['method']);
		$this->setHeaders($url_result['headers']);
		$this->setParams($url_result['params']);
	}

	/**
	 * Set HTTP method used in the call (GET/POST...).
	 *
	 * @param string $method HTTP method used in the call.
	 *
	 * @return void
	 */
	public function setMethod(string $method): void {
		$this->method = $method;
	}

	/**
	 * Get the HTTP method used in the request.
	 *
	 * @return string HTTP method.
	 */
	public function getMethod(): string {
		return $this->method;
	}

	/**
	 * Set HTTP request headers.
	 *
	 * @param array<string, string> $headers HTTP headers.
	 *
	 * @return void
	 *
	 * @throws \InvalidArgumentException If a header name or value is not a string.
	 */
	public function setHeaders(array $headers): void {
		foreach ($headers as $key => $value) {
			if (
				!is_string($key) ||
				!is_string($value)
			) {
				throw new \InvalidArgumentException(
					'HTTP headers must contain string names and string values.'
				);
			}
		}

		$this->headers = $headers;
	}

	/**
	 * Get all HTTP request headers.
	 *
	 * @return array<string, string> HTTP headers.
	 */
	public function getHeaders(): array {
		return $this->headers;
	}

	/**
	 * Get an HTTP header using a case-insensitive name comparison.
	 *
	 * @param string $key Header name.
	 *
	 * @return string|null Header value or null if not found.
	 */
	public function getHeader(
		string $key
	): ?string {
		foreach ($this->headers as $header_name => $value) {
			if (strcasecmp(
				$header_name,
				$key
			) === 0) {
				return $value;
			}
		}

		return null;
	}

	/**
	 * Set request parameters.
	 *
	 * @param array<string, mixed> $params Request parameters.
	 *
	 * @return void
	 *
	 * @throws \InvalidArgumentException If a parameter key is not a string.
	 */
	public function setParams(array $params): void {
		foreach ($params as $key => $value) {
			if (!is_string($key)) {
				throw new \InvalidArgumentException(
					'Request parameter keys must be strings.'
				);
			}
		}

		$this->params = $params;
	}

	/**
	 * Get all request parameters.
	 *
	 * @return array<string, mixed> Request parameters.
	 */
	public function getParams(): array {
		return $this->params;
	}

	/**
	 * Get a request parameter or a default value if it does not exist.
	 *
	 * @param string $key Parameter key.
	 * @param mixed $default Default value.
	 *
	 * @return mixed Parameter value or default value.
	 */
	public function getParam(
		string $key,
		mixed $default = null
	): mixed {
		return array_key_exists(
			$key,
			$this->params
		)
			? $this->params[$key]
			: $default;
	}

	/**
	 * Get a request parameter as a string.
	 *
	 * Scalar values are converted to their string representation.
	 *
	 * @param string $key Parameter key.
	 * @param string|null $default Default value if the parameter does not exist.
	 *
	 * @return string|null Converted value, default value if missing, or null if
	 *                     the value cannot be converted.
	 */
	public function getParamString(
		string $key,
		?string $default = null
	): ?string {
		$param = $this->getParam(
			$key,
			$default
		);

		if ($param === null) {
			return null;
		}

		if (!is_scalar($param)) {
			return null;
		}

		return (string) $param;
	}

	/**
	 * Get a request parameter as an integer.
	 *
	 * Integer values and canonical integer strings are accepted.
	 *
	 * @param string $key Parameter key.
	 * @param int|null $default Default value if the parameter does not exist.
	 *
	 * @return int|null Converted value, default value if missing, or null if the
	 *                  value is not a valid integer.
	 */
	public function getParamInt(
		string $key,
		?int $default = null
	): ?int {
		$param = $this->getParam(
			$key,
			$default
		);

		if ($param === null) {
			return null;
		}

		if (is_int($param)) {
			return $param;
		}

		if (
			!is_string($param) ||
			preg_match(
				'/^[+-]?\d+$/D',
				$param
			) !== 1
		) {
			return null;
		}

		$value = filter_var(
			$param,
			FILTER_VALIDATE_INT
		);

		return $value === false
			? null
			: $value;
	}

	/**
	 * Get a request parameter as a float.
	 *
	 * @param string $key Parameter key.
	 * @param float|null $default Default value if the parameter does not exist.
	 *
	 * @return float|null Converted value, default value if missing, or null if the
	 *                    value is not a valid number.
	 */
	public function getParamFloat(
		string $key,
		?float $default = null
	): ?float {
		$param = $this->getParam(
			$key,
			$default
		);

		if ($param === null) {
			return null;
		}

		if (is_float($param)) {
			return $param;
		}

		if (is_int($param)) {
			return (float) $param;
		}

		if (!is_string($param)) {
			return null;
		}

		$value = filter_var(
			$param,
			FILTER_VALIDATE_FLOAT
		);

		return $value === false
			? null
			: (float) $value;
	}

	/**
	 * Get a request parameter as a boolean.
	 *
	 * @param string $key Parameter key.
	 * @param bool|null $default Default value if the parameter does not exist.
	 *
	 * @return bool|null Converted value, default value if missing, or null if the
	 *                   value cannot be interpreted as a boolean.
	 */
	public function getParamBool(
		string $key,
		?bool $default = null
	): ?bool {
		$param = $this->getParam(
			$key,
			$default
		);

		if ($param === null) {
			return null;
		}

		return filter_var(
			$param,
			FILTER_VALIDATE_BOOLEAN,
			FILTER_NULL_ON_FAILURE
		);
	}

	/**
	 * Get the complete context published by a middleware.
	 *
	 * Middleware names use their public short name without the Middleware suffix,
	 * for example LoginMiddleware is exposed as Login.
	 *
	 * @param string $middleware Middleware public name.
	 *
	 * @return array<string, mixed> Middleware context, or an empty array if the
	 *                              middleware has not published context.
	 */
	public function getMiddleware(
		string $middleware
	): array {
		return OMiddleware::getMiddlewareContext(
			$middleware
		);
	}

	/**
	 * Get one value from context published by a middleware.
	 *
	 * @param string $middleware Middleware public name.
	 * @param string $key Context key.
	 *
	 * @return mixed Context value or null if it does not exist.
	 */
	public function getMiddlewareValue(
		string $middleware,
		string $key
	): mixed {
		return OMiddleware::getContext(
			$middleware,
			$key
		);
	}
}
