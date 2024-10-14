<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\Routing;

class ORoute {
  public static array $routes = [];
  private static string $currentPrefix = '';
  private static ?string $currentLayout = null;

  /**
   * Register a new GET route with the router.
   *
   * @param string $url URL to respond.
   *
   * @param string $component Component to be executed.
   *
   * @param array $filters List of filters to be applied.
   *
   * @param ?string $layout Layout component, optional.
   *
   * @return void
   */
  public static function get(string $url, string $component, array $filters = [], ?string $layout = null): void {
    $fullUrl = self::$currentPrefix . $url;
    $layout = (!is_null(self::$currentLayout)) ? self::$currentLayout : $layout;

    self::addRoute('GET', $fullUrl, $component, $filters, $layout);
  }

  /**
   * Register a new POST route with the router.
   *
   * @param string $url URL to respond.
   *
   * @param string $component Component to be executed.
   *
   * @param array $filters List of filters to be applied.
   *
   * @param ?string $layout Layout component, optional.
   *
   * @return void
   */
  public static function post(string $url, string $component, array $filters = [], ?string $layout = null): void {
    $fullUrl = self::$currentPrefix . $url;
    $layout = (!is_null(self::$currentLayout)) ? self::$currentLayout : $layout;

    self::addRoute('POST', $fullUrl, $component, $filters, $layout);
  }

  /**
   * Register a new PUT route with the router.
   *
   * @param string $url URL to respond.
   *
   * @param string $component Component to be executed.
   *
   * @param array $filters List of filters to be applied.
   *
   * @param ?string $layout Layout component, optional.
   *
   * @return void
   */
  public static function put(string $url, string $component, array $filters = [], ?string $layout = null): void {
    $fullUrl = self::$currentPrefix . $url;
    $layout = (!is_null(self::$currentLayout)) ? self::$currentLayout : $layout;

    self::addRoute('PUT', $fullUrl, $component, $filters, $layout);
  }

  /**
   * Register a new DELETE route with the router.
   *
   * @param string $url URL to respond.
   *
   * @param string $component Component to be executed.
   *
   * @param array $filters List of filters to be applied.
   *
   * @param ?string $layout Layout component, optional.
   *
   * @return void
   */
  public static function delete(string $url, string $component, array $filters = [], ?string $layout = null): void {
    $fullUrl = self::$currentPrefix . $url;
    $layout = (!is_null(self::$currentLayout)) ? self::$currentLayout : $layout;

    self::addRoute('DELETE', $fullUrl, $component, $filters, $layout);
  }

  /**
   * Register a new route with the router.
   *
   * @param string $method Method of the request (GET, POST, PUT, DELETE)
   *
   * @param string $url URL to respond.
   *
   * @param string $component Component to be executed.
   *
   * @param array $filters List of filters to be applied.
   *
   * @param ?string $layout Layout component, optional.
   *
   * @return void
   */
  public static function addRoute(string $method, string $url, string $component, array $filters, ?string $layout = null): void {
    $route = [
      'method' => $method,
      'url' => $url,
      'component' => $component,
      'filters' => $filters,
      'layout' => $layout
    ];

    self::$routes[] = $route;
  }

  /**
   * Register a group of routes with a given prefix ("/api").
   *
   * @param string $prefix Prefix to be applied.
   *
   * @param callable $callback Anonymous function of routes to be added.
   *
   * @return void
   */
  public static function prefix(string $prefix, callable $callback): void {
    $previousPrefix = self::$currentPrefix;
    self::$currentPrefix = $prefix;

    $callback();

    self::$currentPrefix = $previousPrefix;
  }

  /**
   * Register a group of routes with a given layout.
   *
   * @param string $layout Layout to be applied.
   *
   * @param callable $callback Anonymous function of routes to be added.
   *
   * @return void
   */
  public static function layout(string $layout, callable $callback): void {
    $previousLayout = self::$currentLayout;
    self::$currentLayout = $layout;

    $callback();

    self::$currentLayout = $previousLayout;
  }

  /**
   * Register a group of routes with a given prefix ("/api") and a given layout component.
   *
   * @param string $prefix Prefix to be applied.
   *
   * @param string $layout Layout to be applied.
   *
   * @param callable $callback Anonymous function of routes to be added.
   *
   * @return void
   */
  public static function group(string $prefix, string $layout, callable $callback): void {
    $previousPrefix = self::$currentPrefix;
    self::$currentPrefix = $prefix;
    $previousLayout = self::$currentLayout;
    self::$currentLayout = $layout;

    $callback();

    self::$currentPrefix = $previousPrefix;
    self::$currentLayout = $previousLayout;
  }
}
