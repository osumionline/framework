<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Routing;

/**
 * Register application routes and their accumulated routing context.
 */
class ORoute {
  /**
   * Supported middleware phases.
   *
   * @var list<string>
   */
  private const array MIDDLEWARE_PHASES = [
    'before',
    'afterRender',
    'afterResponse'
  ];

  /**
   * Registered application routes.
   *
   * @var list<array{
   *     method: string,
   *     url: string,
   *     component: string,
   *     middlewares: array{
   *         before: list<class-string>,
   *         afterRender: list<class-string>,
   *         afterResponse: list<class-string>
   *     },
   *     layout: string|null,
   *     is_view: bool
   * }>
   */
  public static array $routes = [];

  private static string $current_prefix = '';
  private static ?string $current_layout = null;

  /**
   * Middlewares accumulated by the currently active route groups.
   *
   * @var array{
   *     before: list<class-string>,
   *     afterRender: list<class-string>,
   *     afterResponse: list<class-string>
   * }
   */
  private static array $current_middlewares = [
    'before' => [],
    'afterRender' => [],
    'afterResponse' => []
  ];

  /**
   * Register a new GET route with the router.
   *
   * @param string $url URL to respond.
   * @param string $component Component to be executed.
   * @param array{
   *     before?: array<array-key, class-string>,
   *     afterRender?: array<array-key, class-string>,
   *     afterResponse?: array<array-key, class-string>
   * } $middlewares Middlewares applied specifically to the route.
   * @param string|null $layout Layout component, optional.
   *
   * @return void
   *
   * @throws \InvalidArgumentException If a middleware definition is invalid.
   * @throws \LogicException If the same method and URL are already registered.
   */
  public static function get(
    string $url,
    string $component,
    array $middlewares = [],
    ?string $layout = null
  ): void {
    $full_url = self::normalizeUrl(
      self::$current_prefix . '/' . $url
    );

    $layout = self::$current_layout
      ?? $layout;

    self::addRoute(
      'GET',
      $full_url,
      $component,
      self::mergeMiddlewares(
        self::$current_middlewares,
        $middlewares
      ),
      $layout
    );
  }

  /**
   * Register a new POST route with the router.
   *
   * @param string $url URL to respond.
   * @param string $component Component to be executed.
   * @param array{
   *     before?: array<array-key, class-string>,
   *     afterRender?: array<array-key, class-string>,
   *     afterResponse?: array<array-key, class-string>
   * } $middlewares Middlewares applied specifically to the route.
   * @param string|null $layout Layout component, optional.
   *
   * @return void
   *
   * @throws \InvalidArgumentException If a middleware definition is invalid.
   * @throws \LogicException If the same method and URL are already registered.
   */
  public static function post(
    string $url,
    string $component,
    array $middlewares = [],
    ?string $layout = null
  ): void {
    $full_url = self::normalizeUrl(
      self::$current_prefix . '/' . $url
    );

    $layout = self::$current_layout
      ?? $layout;

    self::addRoute(
      'POST',
      $full_url,
      $component,
      self::mergeMiddlewares(
        self::$current_middlewares,
        $middlewares
      ),
      $layout
    );
  }

  /**
   * Register a new PUT route with the router.
   *
   * @param string $url URL to respond.
   * @param string $component Component to be executed.
   * @param array{
   *     before?: array<array-key, class-string>,
   *     afterRender?: array<array-key, class-string>,
   *     afterResponse?: array<array-key, class-string>
   * } $middlewares Middlewares applied specifically to the route.
   * @param string|null $layout Layout component, optional.
   *
   * @return void
   *
   * @throws \InvalidArgumentException If a middleware definition is invalid.
   * @throws \LogicException If the same method and URL are already registered.
   */
  public static function put(
    string $url,
    string $component,
    array $middlewares = [],
    ?string $layout = null
  ): void {
    $full_url = self::normalizeUrl(
      self::$current_prefix . '/' . $url
    );

    $layout = self::$current_layout
      ?? $layout;

    self::addRoute(
      'PUT',
      $full_url,
      $component,
      self::mergeMiddlewares(
        self::$current_middlewares,
        $middlewares
      ),
      $layout
    );
  }

  /**
   * Register a new DELETE route with the router.
   *
   * @param string $url URL to respond.
   * @param string $component Component to be executed.
   * @param array{
   *     before?: array<array-key, class-string>,
   *     afterRender?: array<array-key, class-string>,
   *     afterResponse?: array<array-key, class-string>
   * } $middlewares Middlewares applied specifically to the route.
   * @param string|null $layout Layout component, optional.
   *
   * @return void
   *
   * @throws \InvalidArgumentException If a middleware definition is invalid.
   * @throws \LogicException If the same method and URL are already registered.
   */
  public static function delete(
    string $url,
    string $component,
    array $middlewares = [],
    ?string $layout = null
  ): void {
    $full_url = self::normalizeUrl(
      self::$current_prefix . '/' . $url
    );

    $layout = self::$current_layout
      ?? $layout;

    self::addRoute(
      'DELETE',
      $full_url,
      $component,
      self::mergeMiddlewares(
        self::$current_middlewares,
        $middlewares
      ),
      $layout
    );
  }

  /**
   * Register a static file route with the router.
   *
   * @param string $url URL to respond.
   * @param string $file File to be displayed.
   * @param array{
   *     before?: array<array-key, class-string>,
   *     afterRender?: array<array-key, class-string>,
   *     afterResponse?: array<array-key, class-string>
   * } $middlewares Middlewares applied specifically to the route.
   * @param string|null $layout Layout component, optional.
   *
   * @return void
   *
   * @throws \InvalidArgumentException If a middleware definition is invalid.
   * @throws \LogicException If the same method and URL are already registered.
   */
  public static function view(
    string $url,
    string $file,
    array $middlewares = [],
    ?string $layout = null
  ): void {
    $full_url = self::normalizeUrl(
      self::$current_prefix . '/' . $url
    );

    $layout = self::$current_layout
      ?? $layout;

    self::addRoute(
      'GET',
      $full_url,
      $file,
      self::mergeMiddlewares(
        self::$current_middlewares,
        $middlewares
      ),
      $layout,
      true
    );
  }

  /**
   * Register a new route with the router.
   *
   * @param string $method HTTP request method.
   * @param string $url Route URL.
   * @param string $component Component or view file to execute.
   * @param array{
   *     before?: array<array-key, class-string>,
   *     afterRender?: array<array-key, class-string>,
   *     afterResponse?: array<array-key, class-string>
   * } $middlewares Middlewares applied to the route.
   * @param string|null $layout Optional layout component.
   * @param bool $is_view Whether the route represents a static view.
   *
   * @return void
   *
   * @throws \InvalidArgumentException If the HTTP method or middleware definition is invalid.
   * @throws \LogicException If the same method and URL are already registered.
   */
  public static function addRoute(
    string $method,
    string $url,
    string $component,
    array $middlewares = [],
    ?string $layout = null,
    bool $is_view = false
  ): void {
    $method = strtoupper(
      trim(
        $method
      )
    );

    if ($method === '') {
      throw new \InvalidArgumentException(
        'Route HTTP method cannot be empty.'
      );
    }

    $url = self::normalizeUrl(
      $url
    );

    $middlewares = self::normalizeMiddlewares(
      $middlewares
    );

    foreach (self::$routes as $route) {
      if (
        $route['method'] === $method &&
        $route['url'] === $url
      ) {
        throw new \LogicException(
          "Route '{$method} {$url}' is already registered."
        );
      }
    }

    self::$routes[] = [
      'method' => $method,
      'url' => $url,
      'component' => $component,
      'middlewares' => $middlewares,
      'layout' => $layout,
      'is_view' => $is_view
    ];
  }

  /**
   * Register a group of routes with a cumulative URL prefix.
   *
   * Prefixes and middlewares are accumulated through nested groups and restored
   * when the callback finishes, including when it throws an exception.
   *
   * @param string $prefix Prefix to be added to the currently active prefix.
   * @param callable $callback Routes or nested groups using the resulting prefix.
   * @param array{
   *     before?: array<array-key, class-string>,
   *     afterRender?: array<array-key, class-string>,
   *     afterResponse?: array<array-key, class-string>
   * } $middlewares Middlewares accumulated inside the group.
   *
   * @return void
   *
   * @throws \InvalidArgumentException If a middleware definition is invalid.
   */
  public static function prefix(
    string $prefix,
    callable $callback,
    array $middlewares = []
  ): void {
    $next_prefix = self::normalizeUrl(
      self::$current_prefix . '/' . $prefix
    );

    $next_middlewares = self::mergeMiddlewares(
      self::$current_middlewares,
      $middlewares
    );

    $previous_prefix = self::$current_prefix;
    $previous_middlewares = self::$current_middlewares;

    self::$current_prefix = $next_prefix;
    self::$current_middlewares = $next_middlewares;

    try {
      $callback();
    } finally {
      self::$current_prefix = $previous_prefix;
      self::$current_middlewares = $previous_middlewares;
    }
  }

  /**
   * Register a group of routes with a layout.
   *
   * The layout temporarily replaces the currently active layout while
   * middlewares are accumulated with the enclosing groups.
   *
   * @param string $layout Layout to be applied.
   * @param callable $callback Routes or nested groups using the layout.
   * @param array{
   *     before?: array<array-key, class-string>,
   *     afterRender?: array<array-key, class-string>,
   *     afterResponse?: array<array-key, class-string>
   * } $middlewares Middlewares accumulated inside the group.
   *
   * @return void
   *
   * @throws \InvalidArgumentException If a middleware definition is invalid.
   */
  public static function layout(
    string $layout,
    callable $callback,
    array $middlewares = []
  ): void {
    $next_middlewares = self::mergeMiddlewares(
      self::$current_middlewares,
      $middlewares
    );

    $previous_layout = self::$current_layout;
    $previous_middlewares = self::$current_middlewares;

    self::$current_layout = $layout;
    self::$current_middlewares = $next_middlewares;

    try {
      $callback();
    } finally {
      self::$current_layout = $previous_layout;
      self::$current_middlewares = $previous_middlewares;
    }
  }

  /**
   * Register a group of routes with a cumulative prefix and a layout.
   *
   * Prefix, layout and middlewares are restored when the callback finishes,
   * including when it throws an exception.
   *
   * @param string $prefix Prefix to be added to the currently active prefix.
   * @param string $layout Layout to be applied inside the group.
   * @param callable $callback Routes or nested groups using the group context.
   * @param array{
   *     before?: array<array-key, class-string>,
   *     afterRender?: array<array-key, class-string>,
   *     afterResponse?: array<array-key, class-string>
   * } $middlewares Middlewares accumulated inside the group.
   *
   * @return void
   *
   * @throws \InvalidArgumentException If a middleware definition is invalid.
   */
  public static function group(
    string $prefix,
    string $layout,
    callable $callback,
    array $middlewares = []
  ): void {
    $next_prefix = self::normalizeUrl(
      self::$current_prefix . '/' . $prefix
    );

    $next_middlewares = self::mergeMiddlewares(
      self::$current_middlewares,
      $middlewares
    );

    $previous_prefix = self::$current_prefix;
    $previous_layout = self::$current_layout;
    $previous_middlewares = self::$current_middlewares;

    self::$current_prefix = $next_prefix;
    self::$current_layout = $layout;
    self::$current_middlewares = $next_middlewares;

    try {
      $callback();
    } finally {
      self::$current_prefix = $previous_prefix;
      self::$current_layout = $previous_layout;
      self::$current_middlewares = $previous_middlewares;
    }
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
   * @throws \InvalidArgumentException If a phase or middleware class name is invalid.
   */
  private static function normalizeMiddlewares(
    array $middlewares
  ): array {
    foreach (array_keys($middlewares) as $phase) {
      if (
        !is_string($phase) ||
        !in_array(
          $phase,
          self::MIDDLEWARE_PHASES,
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
      'before' => [],
      'afterRender' => [],
      'afterResponse' => []
    ];

    foreach (self::MIDDLEWARE_PHASES as $phase) {
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
          trim($middleware_class) === ''
        ) {
          throw new \InvalidArgumentException(
            "Middleware phase '{$phase}' contains an invalid middleware class."
          );
        }

        $normalized[$phase][] = $middleware_class;
      }
    }

    return $normalized;
  }

  /**
   * Merge accumulated group middlewares with an additional middleware definition.
   *
   * Middlewares keep declaration order. Enclosing group middlewares are executed
   * before nested group middlewares, and route-specific middlewares are last.
   *
   * @param array{
   *     before: list<class-string>,
   *     afterRender: list<class-string>,
   *     afterResponse: list<class-string>
   * } $current Accumulated middlewares.
   * @param array{
   *     before?: array<array-key, class-string>,
   *     afterRender?: array<array-key, class-string>,
   *     afterResponse?: array<array-key, class-string>
   * } $additional Additional middlewares.
   *
   * @return array{
   *     before: list<class-string>,
   *     afterRender: list<class-string>,
   *     afterResponse: list<class-string>
   * } Merged middleware definitions.
   *
   * @throws \InvalidArgumentException If a middleware definition is invalid.
   */
  private static function mergeMiddlewares(
    array $current,
    array $additional
  ): array {
    $additional = self::normalizeMiddlewares(
      $additional
    );

    return [
      'before' => array_merge(
        $current['before'],
        $additional['before']
      ),
      'afterRender' => array_merge(
        $current['afterRender'],
        $additional['afterRender']
      ),
      'afterResponse' => array_merge(
        $current['afterResponse'],
        $additional['afterResponse']
      )
    ];
  }

  /**
   * Normalize a route URL ensuring that path separators are consistent.
   *
   * The normalized URL always starts with a single slash, removes duplicate
   * slashes between path segments and removes the trailing slash except for
   * the root URL.
   *
   * @param string $url URL to be normalized.
   *
   * @return string Normalized URL.
   */
  private static function normalizeUrl(
    string $url
  ): string {
    $url = '/' . ltrim(
      $url,
      '/'
    );

    $url = preg_replace(
      '#/+#',
      '/',
      $url
    ) ?? $url;

    if ($url !== '/') {
      $url = rtrim(
        $url,
        '/'
      );
    }

    return $url;
  }
}
