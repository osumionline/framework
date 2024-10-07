<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\Routing;

class ORoute {
  public static $routes = [];
  private static $currentPrefix = '';
  private static $currentType = '';

  /**
   * Register a new GET route with the router.
   *
   * @param string $url URL to respond.
   *
   * @param string $action Action to be executed.
   *
   * @param array $filters List of filters to be applied.
   *
   * @param string $type Return type (html, json, xml).
   *
   * @param array $options Extra options (layout, inline_css, css, inline_js, js)
   *
   * @return void
   */
  public static function get(string $url, string $action, array $filters = [], string $type = 'html', array $options = []): void {
    $fullUrl = self::$currentPrefix . $url;
    $type = (self::$currentType !== '') ? self::$currentType : $type;

    self::addRoute('GET', $fullUrl, $action, $filters, $type, $options);
  }

  /**
   * Register a new POST route with the router.
   *
   * @param string $url URL to respond.
   *
   * @param string $action Action to be executed.
   *
   * @param array $filters List of filters to be applied.
   *
   * @param string $type Return type (html, json, xml).
   *
   * @param array $options Extra options (layout, inline_css, css, inline_js, js)
   *
   * @return void
   */
  public static function post(string $url, string $action, array $filters = [], string $type = 'html', array $options = []): void {
    $fullUrl = self::$currentPrefix . $url;
    $type = (self::$currentType !== '') ? self::$currentType : $type;

    self::addRoute('POST', $fullUrl, $action, $filters, $type, $options);
  }

  /**
   * Register a new PUT route with the router.
   *
   * @param string $url URL to respond.
   *
   * @param string $action Action to be executed.
   *
   * @param array $filters List of filters to be applied.
   *
   * @param string $type Return type (html, json, xml).
   *
   * @param array $options Extra options (layout, inline_css, css, inline_js, js)
   *
   * @return void
   */
  public static function put(string $url, string $action, array $filters = [], string $type = 'html', array $options = []): void {
    $fullUrl = self::$currentPrefix . $url;
    $type = (self::$currentType !== '') ? self::$currentType : $type;

    self::addRoute('PUT', $fullUrl, $action, $filters, $type, $options);
  }

  /**
   * Register a new DELETE route with the router.
   *
   * @param string $url URL to respond.
   *
   * @param string $action Action to be executed.
   *
   * @param array $filters List of filters to be applied.
   *
   * @param string $type Return type (html, json, xml).
   *
   * @param array $options Extra options (layout, inline_css, css, inline_js, js)
   *
   * @return void
   */
  public static function delete(string $url, string $action, array $filters = [], string $type = 'html', array $options = []): void {
    $fullUrl = self::$currentPrefix . $url;
    $type = (self::$currentType !== '') ? self::$currentType : $type;

    self::addRoute('DELETE', $fullUrl, $action, $filters, $type, $options);
  }

  /**
   * Register a new route with the router.
   *
   * @param string $method Method of the request (GET, POST, PUT, DELETE)
   *
   * @param string $url URL to respond.
   *
   * @param string $action Action to be executed.
   *
   * @param array $filters List of filters to be applied.
   *
   * @param string $type Return type (html, json, xml).
   *
   * @param array $options Extra options (layout, inline_css, css, inline_js, js)
   *
   * @return void
   */
  public static function addRoute(string $method, string $url, string $action, array $filters, string $type, array $options): void {
    $route = [
      'method' => $method,
      'url' => $url,
      'action' => $action,
      'filters' => $filters,
      'type' => $type
    ];

    if (count($options) > 0) {
      if (array_key_exists('layout', $options)) {
        $route['layout'] = $options['layout'];
      }
      if (array_key_exists('inline_css', $options)) {
        $route['inline_css'] = $options['inline_css'];
      }
      if (array_key_exists('css', $options)) {
        $route['css'] = $options['css'];
      }
      if (array_key_exists('inline_js', $options)) {
        $route['inline_js'] = $options['inline_js'];
      }
      if (array_key_exists('js', $options)) {
        $route['js'] = $options['js'];
      }
    }
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
   * Register a group of routes with a given type ("json").
   *
   * @param string $type Type to be applied.
   *
   * @param callable $callback Anonymous function of routes to be added.
   *
   * @return void
   */
  public static function type(string $type, callable $callback): void {
    $previousType = self::$currentType;
    self::$currentType = $type;

    $callback();

    self::$currentType = $previousType;
  }

  /**
   * Register a group of routes with a given prefix ("/api") and a give type ("json").
   *
   * @param string $prefix Prefix to be applied.
   *
   * @param string $type Type to be applied.
   *
   * @param callable $callback Anonymous function of routes to be added.
   *
   * @return void
   */
  public static function group(string $prefix, string $type, callable $callback): void {
    $previousPrefix = self::$currentPrefix;
    self::$currentPrefix = $prefix;
    $previousType = self::$currentType;
    self::$currentType = $type;

    $callback();

    self::$currentPrefix = $previousPrefix;
    self::$currentType = $previousType;
  }
}
