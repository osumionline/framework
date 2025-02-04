<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\Routing;

class ORoute {
  public  static array   $routes         = [];
  private static string  $current_prefix = '';
  private static ?string $current_layout = null;

  /**
   * Register a new GET route with the router.
   *
   * @param string $url URL to respond.
   *
   * @param string $component Component to be executed.
   *
   * @param array $filters List of filters to be applied.
   *
   * @param string | null $layout Layout component, optional.
   *
   * @return void
   */
  public static function get(string $url, string $component, array $filters = [], string | null $layout = null): void {
    $full_url = self::$current_prefix . $url;
    $layout = (!is_null(self::$current_layout)) ? self::$current_layout : $layout;

    self::addRoute('GET', $full_url, $component, $filters, $layout);
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
   * @param string | null $layout Layout component, optional.
   *
   * @return void
   */
  public static function post(string $url, string $component, array $filters = [], string | null $layout = null): void {
    $full_url = self::$current_prefix . $url;
    $layout = (!is_null(self::$current_layout)) ? self::$current_layout : $layout;

    self::addRoute('POST', $full_url, $component, $filters, $layout);
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
   * @param string | null $layout Layout component, optional.
   *
   * @return void
   */
  public static function put(string $url, string $component, array $filters = [], string | null $layout = null): void {
    $full_url = self::$current_prefix . $url;
    $layout = (!is_null(self::$current_layout)) ? self::$current_layout : $layout;

    self::addRoute('PUT', $full_url, $component, $filters, $layout);
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
   * @param string | null $layout Layout component, optional.
   *
   * @return void
   */
  public static function delete(string $url, string $component, array $filters = [], string | null $layout = null): void {
    $full_url = self::$current_prefix . $url;
    $layout = (!is_null(self::$current_layout)) ? self::$current_layout : $layout;

    self::addRoute('DELETE', $full_url, $component, $filters, $layout);
  }

  /**
   * Register a static file to a route with the router.
   *
   * @param string $url URL to respond.
   *
   * @param string $file File to be displayed.
   *
   * @param array $filters List of filters to be applied.
   *
   * @param string | null $layout Layout component, optional.
   *
   * @return void
   */
  public static function view(string $url, string $file, array $filters = [], string | null $layout = null): void {
    $full_url = self::$current_prefix . $url;
    $layout = (!is_null(self::$current_layout)) ? self::$current_layout : $layout;

    self::addRoute('GET', $full_url, $file, $filters, $layout, true);
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
   * @param string | null $layout Layout component, optional.
   *
   * @param bool $is_view View mark for static file routes, optional.
   *
   * @return void
   */
  public static function addRoute(string $method, string $url, string $component, array $filters, string | null $layout = null, bool $is_view = false): void {
    $route = [
      'method'    => $method,
      'url'       => $url,
      'component' => $component,
      'filters'   => $filters,
      'layout'    => $layout,
      'is_view'   => $is_view
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
    $previous_prefix = self::$current_prefix;
    self::$current_prefix = $prefix;

    $callback();

    self::$current_prefix = $previous_prefix;
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
    $previous_layout = self::$current_layout;
    self::$current_layout = $layout;

    $callback();

    self::$current_layout = $previous_layout;
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
    $previous_prefix = self::$current_prefix;
    self::$current_prefix = $prefix;
    $previous_layout = self::$current_layout;
    self::$current_layout = $layout;

    $callback();

    self::$current_prefix = $previous_prefix;
    self::$current_layout = $previous_layout;
  }
}
