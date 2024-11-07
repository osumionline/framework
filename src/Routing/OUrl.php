<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\Routing;

use Osumi\OsumiFramework\Core\OConfig;
use Osumi\OsumiFramework\Tools\OTools;
use Osumi\OsumiFramework\Tools\OBuild;
use Osumi\OsumiFramework\Routing\ORoute;

/**
 * OUrl - Class with methods to check required URL, get its data, generate new URLs or redirect the user to a new one
 */
class OUrl {
	private ?OConfig $config      = null;
	private ?array   $urls        = null;
	private string   $check_url   = '';
	private array    $url_params  = [];
	private string   $method      = '';

	/**
	 * Loads user defined urls, used method to access and URL and path to the routing library
	 *
	 * @param string $method Method used to access the URL (get / post / delete)
	 */
	function __construct(string $method) {
		global $core;
		$this->config = $core->config;
		$this->method = strtoupper($method);
		$this->urls   = ORoute::$routes;
	}

	/**
	 * Sets URL to be checked and loads all passed parameters (get / post / files / document body)
	 *
	 * @param string $check_url URL to be checked
	 *
	 * @param array $get Array of parameters passed by GET method
	 *
	 * @param array $post Array of parameters passed by POST method
	 *
	 * @param array $files Array of files submitted by a form (multipart/form-data)
	 *
	 * @return void
	 */
	public function setCheckUrl(string $check_url, ?array $get = null, ?array $post = null, ?array $files = null): void {
		$this->check_url = $check_url;
		$check_params = stripos($check_url, '?');
		if ($check_params !== false) {
			$this->check_url = substr($check_url, 0, $check_params);
		}
		if (!is_null($get)) {
			foreach ($get as $key => $value) {
				$this->url_params[$key] = $value;
			}
		}
		if (!is_null($post)) {
			foreach ($post as $key => $value) {
				$this->url_params[$key] = $value;
			}
		}
		if (!is_null($files)) {
			foreach ($files as $key => $value) {
				$this->url_params[$key] = $value;
			}
		}
		$input = json_decode(file_get_contents('php://input'), true);
		if (!is_null($input)) {
			foreach ($input as $key => $value) {
				$this->url_params[$key] = $value;
			}
		}
	}

	/**
	 * Process the given URL checking it against user defined URLs and get its configuration information if found
	 *
	 * @param string $url URL to be checked
	 *
	 * @return array Array of configuration information
	 */
	public function process(string $url = null): array {
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
			'headers'          => getallheaders(),
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

				$ret['params'] = $chk;

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
				$url = str_replace(':'.$key, $value, $url);
			}
		}

		if ($absolute === true) {
			$base = $core->config->getUrl('base');
			$base = substr($base, 0, strlen($base)-1);

			$url = $base.$url;
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
		header('Location:'.$url);
		exit;
	}
}
