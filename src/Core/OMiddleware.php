<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Core;

/**
 * Manage middleware registration and execution state for the current request.
 */
final class OMiddleware {
    public const string PHASE_BEFORE = 'before';
    public const string PHASE_AFTER_RENDER = 'afterRender';
    public const string PHASE_AFTER_RESPONSE = 'afterResponse';

    /**
     * Supported middleware execution phases.
     *
     * @var list<string>
     */
    private const array PHASES = [
        self::PHASE_BEFORE,
        self::PHASE_AFTER_RENDER,
        self::PHASE_AFTER_RESPONSE
    ];

    /**
     * Project-wide middlewares.
     *
     * @var array{
     *     before: list<class-string>,
     *     afterRender: list<class-string>,
     *     afterResponse: list<class-string>
     * }
     */
    private static array $global_middlewares = [
        self::PHASE_BEFORE => [],
        self::PHASE_AFTER_RENDER => [],
        self::PHASE_AFTER_RESPONSE => []
    ];

    /**
     * Middlewares accumulated for the current route.
     *
     * @var array{
     *     before: list<class-string>,
     *     afterRender: list<class-string>,
     *     afterResponse: list<class-string>
     * }
     */
    private static array $route_middlewares = [
        self::PHASE_BEFORE => [],
        self::PHASE_AFTER_RENDER => [],
        self::PHASE_AFTER_RESPONSE => []
    ];

    /**
     * Context published by executed middlewares, indexed by middleware name.
     *
     * @var array<string, array<string, mixed>>
     */
    private static array $context = [];

    private static bool $is_error = false;
    private static ?string $error_phase = null;
    private static int $error_status_code = 200;
    private static bool $is_streaming_response = false;
    private static string $error_message = '';
    private static string $component_body = '';
    private static string $final_body = '';

    /**
     * Response headers accumulated by the middleware pipeline.
     *
     * @var array<string, string>
     */
    private static array $headers = [];

    private static int $status_code = 200;

    /**
     * Reset request-scoped middleware state while preserving global middlewares.
     *
     * @return void
     */
    public static function reset(): void {
        self::$route_middlewares = [
            self::PHASE_BEFORE => [],
            self::PHASE_AFTER_RENDER => [],
            self::PHASE_AFTER_RESPONSE => []
        ];

        self::$context = [];
        self::$is_error = false;
        self::$error_phase = null;
        self::$error_status_code = 200;
        self::$error_message = '';
        self::$component_body = '';
        self::$final_body = '';
        self::$headers = [];
        self::$status_code = 200;
        self::$is_streaming_response = false;
    }

    /**
     * Set project-wide middlewares.
     *
     * @param array{
     *     before?: array<array-key, class-string>,
     *     afterRender?: array<array-key, class-string>,
     *     afterResponse?: array<array-key, class-string>
     * } $middlewares Middleware definitions by phase.
     *
     * @return void
     *
     * @throws \InvalidArgumentException If a middleware definition is invalid.
     */
    public static function setGlobal(array $middlewares): void {
        self::$global_middlewares = self::normalizeMiddlewares(
            $middlewares
        );
    }

    /**
     * Set middlewares accumulated for the current route.
     *
     * @param array{
     *     before?: array<array-key, class-string>,
     *     afterRender?: array<array-key, class-string>,
     *     afterResponse?: array<array-key, class-string>
     * } $middlewares Middleware definitions by phase.
     *
     * @return void
     *
     * @throws \InvalidArgumentException If a middleware definition is invalid.
     */
    public static function setRoute(array $middlewares): void {
        self::$route_middlewares = self::normalizeMiddlewares(
            $middlewares
        );
    }

    /**
     * Get global and route middlewares in their execution order.
     *
     * @return array{
     *     before: list<class-string>,
     *     afterRender: list<class-string>,
     *     afterResponse: list<class-string>
     * } Middlewares by phase.
     */
    public static function getAll(): array {
        return [
            self::PHASE_BEFORE => array_merge(
                self::$global_middlewares[self::PHASE_BEFORE],
                self::$route_middlewares[self::PHASE_BEFORE]
            ),
            self::PHASE_AFTER_RENDER => array_merge(
                self::$global_middlewares[self::PHASE_AFTER_RENDER],
                self::$route_middlewares[self::PHASE_AFTER_RENDER]
            ),
            self::PHASE_AFTER_RESPONSE => array_merge(
                self::$global_middlewares[self::PHASE_AFTER_RESPONSE],
                self::$route_middlewares[self::PHASE_AFTER_RESPONSE]
            )
        ];
    }

    /**
     * Set the rendered component body available to afterRender middlewares.
     *
     * @param string $body Rendered component body.
     *
     * @return void
     */
    public static function setComponentBody(string $body): void {
        self::$component_body = $body;
    }

    /**
     * Get the current rendered component body.
     *
     * @return string Rendered component body.
     */
    public static function getComponentBody(): string {
        return self::$component_body;
    }

    /**
     * Set the final response body available to afterResponse middlewares.
     *
     * @param string $body Final response body.
     *
     * @return void
     */
    public static function setFinalBody(string $body): void {
        self::$final_body = $body;
    }

    /**
     * Get the current final response body.
     *
     * @return string Final response body.
     */
    public static function getFinalBody(): string {
        return self::$final_body;
    }

    /**
     * Set or replace a response header using a case-insensitive header name.
     *
     * @param string $name Header name.
     * @param string $value Header value.
     *
     * @return void
     *
     * @throws \InvalidArgumentException If the header name or value is invalid.
     */
    public static function setHeader(
        string $name,
        string $value
    ): void {
        if (
            $name === '' ||
            preg_match(
                "/^[!#$%&'*+.^_`|~0-9A-Za-z-]+$/D",
                $name
            ) !== 1
        ) {
            throw new \InvalidArgumentException(
                "Invalid HTTP header name '{$name}'."
            );
        }

        if (
            str_contains(
                $value,
                "\r"
            ) ||
            str_contains(
                $value,
                "\n"
            )
        ) {
            throw new \InvalidArgumentException(
                "Invalid HTTP header value for '{$name}'."
            );
        }

        foreach (array_keys(self::$headers) as $header_name) {
            if (
                strcasecmp(
                    $header_name,
                    $name
                ) === 0
            ) {
                unset(
                    self::$headers[$header_name]
                );

                break;
            }
        }

        self::$headers[$name] = $value;
    }

    /**
     * Get response headers accumulated by the middleware pipeline.
     *
     * @return array<string, string> Response headers.
     */
    public static function getHeaders(): array {
        return self::$headers;
    }

    /**
     * Set the response HTTP status code.
     *
     * @param int $status_code HTTP status code.
     *
     * @return void
     *
     * @throws \InvalidArgumentException If the status code is outside the HTTP range.
     */
    public static function setStatusCode(int $status_code): void {
        if (
            $status_code < 100 ||
            $status_code > 599
        ) {
            throw new \InvalidArgumentException(
                "Invalid HTTP status code '{$status_code}'."
            );
        }

        self::$status_code = $status_code;
    }

    /**
     * Get the response HTTP status code.
     *
     * @return int HTTP status code.
     */
    public static function getStatusCode(): int {
        return self::$status_code;
    }
    
    /**
 	 * Set whether the current response is streamed.
 	 *
 	 * Streamed responses still pass through middleware phases, but their body
 	 * cannot be replaced by middleware because it is emitted progressively.
 	 *
 	 * @param bool $is_streaming_response Whether the response is streamed.
 	 *
 	 * @return void
 	 */
	public static function setStreamingResponse(
    	bool $is_streaming_response
	): void {
    	self::$is_streaming_response = $is_streaming_response;
	}

	/**
 	 * Check whether the current response is streamed.
 	 *
 	 * @return bool True when the current response is streamed.
 	 */
	public static function isStreamingResponse(): bool {
    	return self::$is_streaming_response;
	}

    /**
     * Check whether a middleware stopped the normal pipeline.
     *
     * @return bool True when the current request is in middleware error state.
     */
    public static function isError(): bool {
        return self::$is_error;
    }

    /**
     * Get the phase that stopped the middleware pipeline.
     *
     * @return string|null Middleware phase or null when no stop occurred.
     */
    public static function getErrorPhase(): ?string {
        return self::$error_phase;
    }

    /**
     * Get the status code associated with a middleware stop.
     *
     * @return int Middleware error status code.
     */
    public static function getErrorStatusCode(): int {
        return self::$error_status_code;
    }

    /**
     * Get the message associated with a middleware stop.
     *
     * @return string Middleware error message.
     */
    public static function getErrorMessage(): string {
        return self::$error_message;
    }

    /**
     * Execute all middlewares registered for a phase.
     *
     * Each middleware must expose a public static handle(string $phase, array $data)
     * method returning an array. Context, body, headers and status changes are
     * propagated to downstream middlewares in the same phase and to later phases.
     *
     * @param string $phase Middleware phase.
     * @param array<string, mixed> $data Initial phase data.
     *
     * @return array{stop: bool, status_code?: int, message?: string} Phase result.
     *
     * @throws \InvalidArgumentException If the phase or a middleware result value is invalid.
     * @throws \UnexpectedValueException If a middleware does not return an array.
     */
    public static function runPhase(
        string $phase,
        array $data
    ): array {
        self::validatePhase(
            $phase
        );

        $middlewares = self::getAll()[$phase];
        $data = self::buildPhaseData(
            $data
        );

        foreach ($middlewares as $middleware_class) {
            $result = $middleware_class::handle(
                $phase,
                $data
            );

            if (!is_array($result)) {
                throw new \UnexpectedValueException(
                    "Middleware '{$middleware_class}' must return an array."
                );
            }

            self::applyMiddlewareResult(
                $middleware_class,
                $phase,
                $result
            );

            $data = self::buildPhaseData(
                $data
            );

            if (
                array_key_exists(
                    'stop',
                    $result
                )
            ) {
                if (!is_bool($result['stop'])) {
                    throw new \InvalidArgumentException(
                        "Middleware '{$middleware_class}' returned a non-boolean 'stop' value."
                    );
                }

                if ($result['stop']) {
                    $status_code = array_key_exists(
                        'status_code',
                        $result
                    )
                        ? self::$status_code
                        : 500;

                    $message = array_key_exists(
                        'message',
                        $result
                    )
                        ? $result['message']
                        : 'Middleware stopped execution.';

                    if (!is_string($message)) {
                        throw new \InvalidArgumentException(
                            "Middleware '{$middleware_class}' returned a non-string 'message' value."
                        );
                    }

                    self::$is_error = true;
                    self::$error_phase = $phase;
                    self::$error_status_code = $status_code;
                    self::$error_message = $message;
                    self::$status_code = $status_code;

                    return [
                        'stop' => true,
                        'status_code' => $status_code,
                        'message' => $message
                    ];
                }
            }
        }

        return [
            'stop' => false
        ];
    }

    /**
     * Get one value published by a middleware.
     *
     * @param string $middleware Middleware name without the Middleware suffix.
     * @param string $key Context key.
     *
     * @return mixed Context value or null when it does not exist.
     */
    public static function getContext(
        string $middleware,
        string $key
    ): mixed {
        return self::$context[$middleware][$key]
            ?? null;
    }

    /**
     * Get the full context published by a middleware.
     *
     * @param string $middleware Middleware name without the Middleware suffix.
     *
     * @return array<string, mixed> Middleware context.
     */
    public static function getMiddlewareContext(
        string $middleware
    ): array {
        return self::$context[$middleware]
            ?? [];
    }

    /**
     * Normalize and validate middleware definitions.
     *
     * @param array{
     *     before?: array<array-key, class-string>,
     *     afterRender?: array<array-key, class-string>,
     *     afterResponse?: array<array-key, class-string>
     * } $middlewares Middleware definitions by phase.
     *
     * @return array{
     *     before: list<class-string>,
     *     afterRender: list<class-string>,
     *     afterResponse: list<class-string>
     * } Normalized middleware definitions.
     *
     * @throws \InvalidArgumentException If a phase or middleware class is invalid.
     */
    private static function normalizeMiddlewares(
        array $middlewares
    ): array {
        foreach (array_keys($middlewares) as $phase) {
            if (
                !is_string($phase) ||
                !in_array(
                    $phase,
                    self::PHASES,
                    true
                )
            ) {
                $phase_name = is_scalar($phase)
                    ? (string) $phase
                    : gettype($phase);

                throw new \InvalidArgumentException(
                    "Invalid middleware phase '{$phase_name}'."
                );
            }
        }

        $normalized = [
            self::PHASE_BEFORE => [],
            self::PHASE_AFTER_RENDER => [],
            self::PHASE_AFTER_RESPONSE => []
        ];

        foreach (self::PHASES as $phase) {
            $phase_middlewares = $middlewares[$phase]
                ?? [];

            if (!is_array($phase_middlewares)) {
                throw new \InvalidArgumentException(
                    "Middleware phase '{$phase}' must contain an array of middleware classes."
                );
            }

            foreach ($phase_middlewares as $middleware_class) {
                if (
                    !is_string($middleware_class) ||
                    $middleware_class === ''
                ) {
                    throw new \InvalidArgumentException(
                        "Middleware phase '{$phase}' contains an invalid middleware class."
                    );
                }

                self::validateMiddlewareClass(
                    $middleware_class
                );

                $normalized[$phase][] = $middleware_class;
            }
        }

        return $normalized;
    }

    /**
     * Validate that a middleware class can be executed by the pipeline.
     *
     * @param string $middleware_class Middleware class name.
     *
     * @return void
     *
     * @throws \InvalidArgumentException If the middleware class cannot be executed.
     */
    private static function validateMiddlewareClass(
        string $middleware_class
    ): void {
        if (!class_exists($middleware_class)) {
            throw new \InvalidArgumentException(
                "Middleware class '{$middleware_class}' does not exist."
            );
        }

        if (!method_exists(
            $middleware_class,
            'handle'
        )) {
            throw new \InvalidArgumentException(
                "Middleware class '{$middleware_class}' must expose a handle() method."
            );
        }

        $handle_method = new \ReflectionMethod(
            $middleware_class,
            'handle'
        );

        if (
            !$handle_method->isPublic() ||
            !$handle_method->isStatic()
        ) {
            throw new \InvalidArgumentException(
                "Middleware class '{$middleware_class}' must expose a public static handle() method."
            );
        }
    }

    /**
     * Validate a middleware execution phase.
     *
     * @param string $phase Middleware phase.
     *
     * @return void
     *
     * @throws \InvalidArgumentException If the phase is unsupported.
     */
    private static function validatePhase(
        string $phase
    ): void {
        if (!in_array(
            $phase,
            self::PHASES,
            true
        )) {
            throw new \InvalidArgumentException(
                "Invalid middleware phase '{$phase}'."
            );
        }
    }

    /**
     * Apply one middleware result to the accumulated pipeline state.
     *
     * @param class-string $middleware_class Executed middleware class.
     * @param string $phase Current middleware phase.
     * @param array<string, mixed> $result Middleware result.
     *
     * @return void
     *
     * @throws \InvalidArgumentException If a returned value has an invalid type.
     */
    private static function applyMiddlewareResult(
        string $middleware_class,
        string $phase,
        array $result
    ): void {
        if (
            array_key_exists(
                'context',
                $result
            )
        ) {
            if (!is_array($result['context'])) {
                throw new \InvalidArgumentException(
                    "Middleware '{$middleware_class}' returned a non-array 'context' value."
                );
            }

            $middleware_name = self::getMiddlewareName(
                $middleware_class
            );

            self::$context[$middleware_name] ??= [];

            foreach ($result['context'] as $key => $value) {
                if (!is_string($key)) {
                    throw new \InvalidArgumentException(
                        "Middleware '{$middleware_class}' context keys must be strings."
                    );
                }

                self::$context[$middleware_name][$key] = $value;
            }
        }

        if (
    		array_key_exists(
        		'body',
        		$result
    		)
		) {
    		if (self::$is_streaming_response) {
        		throw new \InvalidArgumentException(
            		"Middleware '{$middleware_class}' cannot replace the body of a streamed response."
        		);
    		}

    		if (!is_string($result['body'])) {
        		throw new \InvalidArgumentException(
            		"Middleware '{$middleware_class}' returned a non-string 'body' value."
        		);
    		}

    		if ($phase === self::PHASE_AFTER_RENDER) {
        		self::$component_body = $result['body'];
    		}

    		if ($phase === self::PHASE_AFTER_RESPONSE) {
        		self::$final_body = $result['body'];
    		}
		}

        if (
            array_key_exists(
                'headers',
                $result
            )
        ) {
            if (!is_array($result['headers'])) {
                throw new \InvalidArgumentException(
                    "Middleware '{$middleware_class}' returned a non-array 'headers' value."
                );
            }

            foreach ($result['headers'] as $name => $value) {
                if (
                    !is_string($name) ||
                    !is_string($value)
                ) {
                    throw new \InvalidArgumentException(
                        "Middleware '{$middleware_class}' headers must contain string names and string values."
                    );
                }

                self::setHeader(
                    $name,
                    $value
                );
            }
        }

        if (
            array_key_exists(
                'status_code',
                $result
            )
        ) {
            if (!is_int($result['status_code'])) {
                throw new \InvalidArgumentException(
                    "Middleware '{$middleware_class}' returned a non-integer 'status_code' value."
                );
            }

            self::setStatusCode(
                $result['status_code']
            );
        }
    }

    /**
     * Add current accumulated middleware state to phase input data.
     *
     * @param array<string, mixed> $data Phase data.
     *
     * @return array<string, mixed> Phase data including accumulated state.
     */
    private static function buildPhaseData(
        array $data
    ): array {
        $data['context'] = self::$context;
        $data['component_body'] = self::$component_body;
        $data['final_body'] = self::$final_body;
        $data['response_headers'] = self::$headers;
        $data['status_code'] = self::$status_code;
        $data['is_streaming_response'] = self::$is_streaming_response;
        $data['is_error'] = self::$is_error;
        $data['error_phase'] = self::$error_phase;
        $data['error_status_code'] = self::$error_status_code;
        $data['error_message'] = self::$error_message;

        return $data;
    }

    /**
     * Get the public middleware context name from its class name.
     *
     * @param class-string $middleware_class Middleware class.
     *
     * @return string Middleware context name.
     */
    private static function getMiddlewareName(
        string $middleware_class
    ): string {
        $separator_position = strrpos(
            $middleware_class,
            '\\'
        );

        $name = $separator_position === false
            ? $middleware_class
            : substr(
                $middleware_class,
                $separator_position + 1
            );

        if (
            str_ends_with(
                $name,
                'Middleware'
            )
        ) {
            $name = substr(
                $name,
                0,
                -strlen('Middleware')
            );
        }

        return $name;
    }
}
