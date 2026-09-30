<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Core;

/**
 * OConfig - Class with all the configuration info for the framework
 */
class OConfig {
	private string $name = 'Osumi';
	private string $environment = '';
	private array $log = [
		'name'          => null,
		'level'         => 'DEBUG',
		'max_file_size' => 50,
		'max_num_files' => 3
	];
	private bool $use_session = false;
	private bool $allow_cross_origin = true;

	private array $dirs = [];
	private array $db = [
		'driver'  => 'mysql',
		'user'    => '',
		'pass'    => '',
		'host'    => '',
		'name'    => '',
		'charset' => 'utf8mb4',
		'collate' => 'utf8mb4_unicode_ci'
	];
	private array $urls = [
		'base'   => ''
	];

	private array $plugin_config = [];

	private string $cookie_prefix    = '';
	private string $cookie_url       = '';
	private string $cookie_path      = '/';
	private bool   $cookie_secure    = true;
	private bool   $cookie_http_only = true;
	private string $cookie_same_site = 'Lax';

	private array | null $url_list = null;

	private array $error_pages  = [
		'403' => null,
		'404' => null,
		'500' => null
	];

	private array  $css_list      = [];
	private array  $js_list       = [];
	private array  $head_elements = [];
	private string $default_title = '';
	private string $mailing_from  = '';
	private string $lang          = 'es';
	private string $timezone      = 'Europe/Madrid';

	private array $libs   = [];
	private array $extras = [];

	/**
	 * Load the application configuration files.
	 *
	 * @param string $bd Base directory of the application.
	 *
	 * @throws \InvalidArgumentException If the application configuration contains invalid values.
	 * @throws \JsonException If a configuration file contains invalid JSON.
	 * @throws \RuntimeException If a configuration file cannot be read.
	 */
	public function __construct(string $bd) {
		$this->setBaseDir($bd);
		$json_file = $this->getDir('app_config') . 'Config.json';
		$config = [];
		if (file_exists($json_file)) {
			$config_content = file_get_contents($json_file);

			if ($config_content === false) {
				throw new \RuntimeException(
					"Unable to read configuration file '{$json_file}'."
				);
			}

			$config_data = json_decode(
				$config_content,
				true,
				512,
				JSON_THROW_ON_ERROR
			);

			if (!is_array($config_data)) {
				throw new \InvalidArgumentException(
					'Application configuration root must be an object.'
				);
			}

			$config = $config_data;
		}
		$this->loadConfig($config);
		if (array_key_exists('environment', $config)) {
			$environment = $this->getConfigString($config, 'environment');

			$this->setEnvironment($environment);

			$json_env_file = $this->getDir('app_config')
				. 'Config_'
				. $environment
				. '.json';
			if (file_exists($json_env_file)) {
				$config_env_content = file_get_contents($json_env_file);

				if ($config_env_content === false) {
					throw new \RuntimeException(
						"Unable to read environment configuration file '{$json_env_file}'."
					);
				}

				$config_env_data = json_decode(
					$config_env_content,
					true,
					512,
					JSON_THROW_ON_ERROR
				);

				if (!is_array($config_env_data)) {
					throw new \InvalidArgumentException(
						'Environment configuration root must be an object.'
					);
				}

				$this->loadConfig($config_env_data);
			}
		}

		$this->validateCookieConfig();
	}

	/**
	 * Get and validate a string configuration value.
	 *
	 * @param array $config Configuration source.
	 * @param string $key Key to retrieve.
	 * @param string|null $field Full field name used in error messages.
	 *
	 * @return string Configuration value.
	 *
	 * @throws \InvalidArgumentException If the value is not a string.
	 */
	private function getConfigString(array $config, string $key, ?string $field = null): string {
		$value = $config[$key];
		$field ??= $key;

		if (!is_string($value)) {
			throw new \InvalidArgumentException(
				"Configuration field '{$field}' must be a string."
			);
		}

		return $value;
	}

	/**
	 * Get and validate a boolean configuration value.
	 *
	 * @param array $config Configuration source.
	 * @param string $key Key to retrieve.
	 * @param string|null $field Full field name used in error messages.
	 *
	 * @return bool Configuration value.
	 *
	 * @throws \InvalidArgumentException If the value is not a boolean.
	 */
	private function getConfigBool(array $config, string $key, ?string $field = null): bool {
		$value = $config[$key];
		$field ??= $key;

		if (!is_bool($value)) {
			throw new \InvalidArgumentException(
				"Configuration field '{$field}' must be a boolean."
			);
		}

		return $value;
	}

	/**
	 * Get and validate an integer configuration value.
	 *
	 * @param array $config Configuration source.
	 * @param string $key Key to retrieve.
	 * @param string|null $field Full field name used in error messages.
	 *
	 * @return int Configuration value.
	 *
	 * @throws \InvalidArgumentException If the value is not an integer.
	 */
	private function getConfigInt(array $config, string $key, ?string $field = null): int {
		$value = $config[$key];
		$field ??= $key;

		if (!is_int($value)) {
			throw new \InvalidArgumentException(
				"Configuration field '{$field}' must be an integer."
			);
		}

		return $value;
	}

	/**
	 * Get and validate an array configuration value.
	 *
	 * @param array $config Configuration source.
	 * @param string $key Key to retrieve.
	 * @param string|null $field Full field name used in error messages.
	 *
	 * @return array Configuration value.
	 *
	 * @throws \InvalidArgumentException If the value is not an array.
	 */
	private function getConfigArray(
		array $config,
		string $key,
		?string $field = null
	): array {
		$value = $config[$key];
		$field ??= $key;

		if (!is_array($value)) {
			throw new \InvalidArgumentException(
				"Configuration field '{$field}' must be an object or array."
			);
		}

		return $value;
	}

	/**
	 * Validate that an array is a list of strings.
	 *
	 * @param array $values Values to validate.
	 * @param string $field Field name used in error messages.
	 *
	 * @return void
	 *
	 * @throws \InvalidArgumentException If the value is not a list of strings.
	 */
	private function validateStringList(array $values, string $field): void {
		if (!array_is_list($values)) {
			throw new \InvalidArgumentException(
				"Configuration field '{$field}' must be a list."
			);
		}

		foreach ($values as $value) {
			if (!is_string($value)) {
				throw new \InvalidArgumentException(
					"Configuration field '{$field}' must contain only strings."
				);
			}
		}
	}

	/**
	 * Validate a head element definition.
	 *
	 * @param array $item Head element definition.
	 * @param string $field Field name used in error messages.
	 *
	 * @return void
	 *
	 * @throws \InvalidArgumentException If the head element definition is invalid.
	 */
	private function validateHeadElement(array $item, string $field): void {
		if (
			!array_key_exists('item', $item) ||
			!is_string($item['item']) ||
			$item['item'] === ''
		) {
			throw new \InvalidArgumentException(
				"Configuration field '{$field}.item' must be a non-empty string."
			);
		}

		if (
			!array_key_exists('attributes', $item) ||
			!is_array($item['attributes'])
		) {
			throw new \InvalidArgumentException(
				"Configuration field '{$field}.attributes' must be an object."
			);
		}

		foreach ($item['attributes'] as $key => $value) {
			if (!is_string($key)) {
				throw new \InvalidArgumentException(
					"Configuration field '{$field}.attributes' must use string keys."
				);
			}

			if (
				!is_string($value) &&
				!is_int($value) &&
				!is_float($value) &&
				!is_bool($value) &&
				$value !== null
			) {
				throw new \InvalidArgumentException(
					"Configuration attribute '{$field}.attributes.{$key}' has an invalid value type."
				);
			}
		}
	}

	/**
	 * Load a specific configuration array.
	 *
	 * @param array $config Application configuration values.
	 *
	 * @return void
	 *
	 * @throws \InvalidArgumentException If a configuration value has an invalid type.
	 */
	private function loadConfig(array $config): void {
		if (array_key_exists('name', $config)) {
			$this->setName($this->getConfigString($config, 'name'));
		}

		if (array_key_exists('use-session', $config)) {
			$this->setUseSession($this->getConfigBool($config, 'use-session'));
		}

		if (array_key_exists('allow-cross-origin', $config)) {
			$this->setAllowCrossOrigin($this->getConfigBool($config, 'allow-cross-origin'));
		}

		if (array_key_exists('db', $config)) {
			$db = $this->getConfigArray($config, 'db');

			$db_fields = [
				'driver',
				'host',
				'user',
				'pass',
				'name',
				'charset',
				'collate'
			];

			foreach ($db_fields as $db_field) {
				if (array_key_exists($db_field, $db)) {
					$this->setDB($db_field, $this->getConfigString($db, $db_field, 'db.' . $db_field));
				}
			}
		}

		if (array_key_exists('cookies', $config)) {
			$cookies = $this->getConfigArray($config, 'cookies');

			if (array_key_exists('prefix', $cookies)) {
				$this->setCookiePrefix($this->getConfigString($cookies, 'prefix', 'cookies.prefix'));
			}

			if (array_key_exists('url', $cookies)) {
				$this->setCookieUrl($this->getConfigString($cookies, 'url', 'cookies.url'));
			}

			if (array_key_exists('path', $cookies)) {
				$this->setCookiePath($this->getConfigString($cookies, 'path', 'cookies.path'));
			}

			if (array_key_exists('secure', $cookies)) {
				$this->setCookieSecure($this->getConfigBool($cookies, 'secure', 'cookies.secure'));
			}

			if (array_key_exists('http_only', $cookies)) {
				$this->setCookieHttpOnly($this->getConfigBool($cookies, 'http_only', 'cookies.http_only'));
			}

			if (array_key_exists('same_site', $cookies)) {
				$this->setCookieSameSite($this->getConfigString($cookies, 'same_site', 'cookies.same_site'));
			}
		}

		if (array_key_exists('log_level', $config)) {
			$this->setLog('level', $this->getConfigString($config, 'log_level'));
		}

		if (array_key_exists('log', $config)) {
			$log = $this->getConfigArray($config, 'log');

			if (array_key_exists('name', $log)) {
				$this->setLog('name', $this->getConfigString($log, 'name', 'log.name'));
			}

			if (array_key_exists('max_file_size', $log)) {
				$this->setLog('max_file_size', $this->getConfigInt($log, 'max_file_size', 'log.max_file_size'));
			}

			if (array_key_exists('max_num_files', $log)) {
				$this->setLog('max_num_files', $this->getConfigInt($log, 'max_num_files', 'log.max_num_files'));
			}
		}

		if (array_key_exists('base_url', $config)) {
			$this->setUrl('base', $this->getConfigString($config, 'base_url'));
		}

		if (array_key_exists('default_title', $config)) {
			$this->setDefaultTitle($this->getConfigString($config, 'default_title'));
		}

		if (array_key_exists('lang', $config)) {
			$this->setLang($this->getConfigString($config, 'lang'));
		}

		if (array_key_exists('timezone', $config)) {
			$this->setTimezone($this->getConfigString($config, 'timezone'));
		}

		if (array_key_exists('mailing_from', $config)) {
			$this->setMailingFrom($this->getConfigString($config, 'mailing_from'));
		}

		if (array_key_exists('plugins', $config)) {
			$plugins = $this->getConfigArray($config, 'plugins');

			foreach ($plugins as $key => $plugin_conf) {
				if (
					!is_string($key) ||
					!is_array($plugin_conf)
				) {
					throw new \InvalidArgumentException(
						"Configuration field 'plugins' must contain plugin-name to object mappings."
					);
				}

				$this->setPluginConfig($key, $plugin_conf);
			}
		}

		if (array_key_exists('error_pages', $config)) {
			$error_pages = $this->getConfigArray($config, 'error_pages');

			foreach ($error_pages as $status => $url) {
				$status = (string) $status;

				if (
					!is_string($url) &&
					$url !== null
				) {
					throw new \InvalidArgumentException(
						"Configuration field 'error_pages.{$status}' must be a string or null."
					);
				}

				$this->setErrorPage(
					$status,
					$url
				);
			}
		}

		if (array_key_exists('css', $config)) {
			$this->setCssList($this->getConfigArray($config, 'css'));
		}

		if (array_key_exists('js', $config)) {
			$this->setJsList($this->getConfigArray($config, 'js'));
		}

		if (array_key_exists('libs', $config)) {
			$this->setLibs($this->getConfigArray($config, 'libs'));
		}

		if (array_key_exists('head_elements', $config)) {
			$this->setHeadElements($this->getConfigArray($config, 'head_elements'));
		}

		if (array_key_exists('extra', $config)) {
			if (!is_array($config['extra'])) {
				throw new \InvalidArgumentException(
					"Configuration field 'extra' must be an object."
				);
			}

			foreach ($config['extra'] as $key => $value) {
				if (
					!is_string($key) ||
					(
						!is_string($value) &&
						!is_int($value) &&
						!is_float($value) &&
						!is_bool($value)
					)
				) {
					throw new \InvalidArgumentException(
						"Configuration extra '{$key}' must be a string, integer, float or boolean."
					);
				}

				$this->setExtra($key, $value);
			}
		}

		if (array_key_exists('dir', $config)) {
			$dirs = $this->getConfigArray($config, 'dir');

			$dir_list = $this->getDir();
			$dir_from = [];
			$dir_to = [];

			foreach ($dir_list as $key => $value) {
				$dir_from[] = '{{' . $key . '}}';
				$dir_to[] = $value;
			}

			foreach ($dirs as $key => $value) {
				if (
					!is_string($key) ||
					!is_string($value)
				) {
					throw new \InvalidArgumentException(
						"Configuration field 'dir' must contain directory-name to string-path mappings."
					);
				}

				$this->setDir($key, str_ireplace($dir_from, $dir_to, $value));
			}
		}

		if (array_key_exists('libs', $config)) {
			$this->setLibs($config['libs']);
		}
	}

	/**
	 * Validate cookie configuration.
	 *
	 * @return void
	 *
	 * @throws \InvalidArgumentException If the cookie configuration is invalid.
	 */
	private function validateCookieConfig(): void {
		if (
			$this->cookie_same_site === 'None' &&
			!$this->cookie_secure
		) {
			throw new \InvalidArgumentException(
				'Cookies configured with SameSite=None must also use the Secure attribute.'
			);
		}
	}

	/**
	 * Set application's name
	 *
	 * @param string $name Name of the application
	 *
	 * @return void
	 */
	public function setName(string $name): void {
		$this->name = $name;
	}

	/**
	 * Get application's name
	 *
	 * @return string Application's name
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * Set the environment name, if any
	 *
	 * @return void
	 */
	public function setEnvironment(string $environment): void {
		$this->environment = $environment;
	}

	/**
	 * Get environment name
	 *
	 * @return string Environment name
	 */
	public function getEnvironment(): string {
		return $this->environment;
	}

	/**
	 * Set a logging configuration value.
	 *
	 * Log levels must be one of ALL, DEBUG, INFO or ERROR. File size and file
	 * count limits must be positive integers. The log name must be a safe,
	 * non-empty filename component.
	 *
	 * @param string $key Logging configuration key.
	 * @param string|int $value Configuration value.
	 *
	 * @return void
	 *
	 * @throws \InvalidArgumentException If the supplied value is invalid for the
	 *                                   requested logging configuration key.
	 * @throws \OutOfBoundsException If the logging configuration key is invalid.
	 */
	public function setLog(
		string $key,
		string|int $value
	): void {
		if (!array_key_exists($key, $this->log)) {
			throw new \OutOfBoundsException(
				"Logging configuration key '{$key}' does not exist."
			);
		}

		$valid = match ($key) {
			'name' => (
				is_string($value) &&
				$value !== '' &&
				$value !== '.' &&
				$value !== '..' && !str_contains($value, '/') &&
				!str_contains($value, '\\') &&
				!str_contains($value, "\0")
			),


			'level' => (
				is_string($value) &&
				in_array(
					$value,
					['ALL', 'DEBUG', 'INFO', 'ERROR'],
					true
				)),

			'max_file_size', 'max_num_files' => (is_int($value) && $value > 0),

			default => false
		};

		if (!$valid) {
			throw new \InvalidArgumentException(
				"Invalid value for logging configuration key '{$key}'."
			);
		}

		$this->log[$key] = $value;
	}

	/**
	 * Get a logging configuration value.
	 *
	 * @param string $key Logging configuration key.
	 *
	 * @return string|int|null Configuration value.
	 *
	 * @throws \OutOfBoundsException If the logging configuration key is invalid.
	 */
	public function getLog(string $key): string | int | null {
		if (!array_key_exists($key, $this->log)) {
			throw new \OutOfBoundsException(
				"Logging configuration key '{$key}' does not exist."
			);
		}

		return $this->log[$key];
	}

	/**
	 * Set if session is to be used
	 *
	 * @param bool $value Value of the Use Session configuration
	 *
	 * @return void
	 */
	public function setUseSession(bool $value): void {
		$this->use_session = $value;
	}

	/**
	 * Get if session is to be used
	 *
	 * @return bool Value of the Use Session configuration
	 */
	public function getUseSession(): bool {
		return $this->use_session;
	}

	/**
	 * Set if Cross-Origin calls are allowed
	 *
	 * @param bool $value Value of the Cross-Origin configuration
	 *
	 * @return void
	 */
	public function setAllowCrossOrigin(bool $value): void {
		$this->allow_cross_origin = $value;
	}

	/**
	 * Get if Cross-Origin calls are allowed
	 *
	 * @return bool Value of the Cross-Origin configuration
	 */
	public function getAllowCrossOrigin(): bool {
		return $this->allow_cross_origin;
	}

	/**
	 * Set configuration fields of a plugin
	 *
	 * @param string $plugin Name of the plugin
	 *
	 * @param array $plugin_conf Array of configuration fields for the plugin
	 *
	 * @return void
	 */
	public function setPluginConfig(string $plugin, array $plugin_conf): void {
		$this->plugin_config[$plugin] = $plugin_conf;
	}

	/**
	 * Get configuration fields of a plugin
	 *
	 * @param string $plugin Name of the plugin
	 *
	 * @return array | null Array of configuration fields for the plugin or null if not found
	 */
	public function getPluginConfig(string $plugin): array | null {
		return array_key_exists($plugin, $this->plugin_config) ? $this->plugin_config[$plugin] : null;
	}

	/**
	 * Set list of framework and user defined directories
	 *
	 * @param string $dir Name or code of the directory
	 *
	 * @param string $value Full path of the directory
	 *
	 * @return void
	 */
	public function setDir(string $dir, string $value): void {
		$this->dirs[$dir] = $value;
	}

	/**
	 * Get the path of a configured directory or the complete directory list.
	 *
	 * @param string|null $dir Directory name or null to return all directories.
	 *
	 * @return string|array Requested directory path or complete directory list.
	 *
	 * @throws \OutOfBoundsException If the requested directory does not exist.
	 */
	public function getDir(
		?string $dir = null
	): string | array {
		if ($dir === null) {
			return $this->dirs;
		}

		if (!array_key_exists($dir, $this->dirs)) {
			throw new \OutOfBoundsException(
				"Directory '{$dir}' is not configured."
			);
		}

		return $this->dirs[$dir];
	}

	/**
	 * Sets up framework internal directories based on applications base directory
	 *
	 * @param string $bd Base directory of the application
	 *
	 * @return void
	 */
	private function setBaseDir(string $bd): void {
		$this->setDir('base',           $bd);
		$this->setDir('app',            $bd . 'src/');
		$this->setDir('app_component',  $bd . 'src/Component/');
		$this->setDir('app_config',     $bd . 'src/Config/');
		$this->setDir('app_dto',        $bd . 'src/DTO/');
		$this->setDir('app_filter',     $bd . 'src/Filter/');
		$this->setDir('app_layout',     $bd . 'src/Layout/');
		$this->setDir('app_model',      $bd . 'src/Model/');
		$this->setDir('app_routes',     $bd . 'src/Routes/');
		$this->setDir('app_service',    $bd . 'src/Service/');
		$this->setDir('app_task',       $bd . 'src/Task/');
		$this->setDir('app_utils',      $bd . 'src/Utils/');
		$this->setDir('ofw',            $bd . 'ofw/');
		$this->setDir('ofw_cache',      $bd . 'ofw/cache/');
		$this->setDir('ofw_export',     $bd . 'ofw/export/');
		$this->setDir('ofw_tmp',        $bd . 'ofw/tmp/');
		$this->setDir('ofw_logs',       $bd . 'ofw/logs/');
		$this->setDir('ofw_base',       $bd . 'vendor/osumionline/framework/');
		$this->setDir('ofw_vendor',     $bd . 'vendor/osumionline/framework/src/');
		$this->setDir('ofw_assets',     $bd . 'vendor/osumionline/framework/src/Assets/');
		$this->setDir('ofw_locale',     $bd . 'vendor/osumionline/framework/src/Assets/locale/');
		$this->setDir('ofw_template',   $bd . 'vendor/osumionline/framework/src/Assets/template/');
		$this->setDir('ofw_task',       $bd . 'vendor/osumionline/framework/src/Task/');
		$this->setDir('ofw_tools',      $bd . 'vendor/osumionline/framework/src/Tools/');
		$this->setDir('public',         $bd . 'public/');
	}

	/**
	 * Set a database configuration value.
	 *
	 * @param string $key Database configuration key.
	 * @param string $value Configuration value.
	 *
	 * @return void
	 *
	 * @throws \OutOfBoundsException If the database configuration key is invalid.
	 */
	public function setDB(string $key, string $value): void {
		if (!array_key_exists($key, $this->db)) {
			throw new \OutOfBoundsException(
				"Database configuration key '{$key}' does not exist."
			);
		}

		$this->db[$key] = $value;
	}

	/**
	 * Get a database configuration value.
	 *
	 * @param string $key Database configuration key.
	 *
	 * @return string Configuration value.
	 *
	 * @throws \OutOfBoundsException If the database configuration key is invalid.
	 */
	public function getDB(string $key): string {
		if (!array_key_exists($key, $this->db)) {
			throw new \OutOfBoundsException(
				"Database configuration key '{$key}' does not exist."
			);
		}

		return $this->db[$key];
	}

	/**
	 * Set a configured URL.
	 *
	 * @param string $key URL configuration key.
	 * @param string $url URL value.
	 *
	 * @return void
	 *
	 * @throws \OutOfBoundsException If the URL configuration key is invalid.
	 */
	public function setUrl(string $key, string $url): void {
		if (!array_key_exists($key, $this->urls)) {
			throw new \OutOfBoundsException(
				"URL configuration key '{$key}' does not exist."
			);
		}

		$this->urls[$key] = $url;
	}

	/**
	 * Get a configured URL.
	 *
	 * @param string $key URL configuration key.
	 *
	 * @return string URL value.
	 *
	 * @throws \OutOfBoundsException If the URL configuration key is invalid.
	 */
	public function getUrl(string $key): string {
		if (!array_key_exists($key, $this->urls)) {
			throw new \OutOfBoundsException(
				"URL configuration key '{$key}' does not exist."
			);
		}

		return $this->urls[$key];
	}

	/**
	 * Set up a prefix for the cookies used in the application
	 *
	 * @param string $cp Cookie prefix (eg osumi-)
	 *
	 * @return void
	 */
	public function setCookiePrefix(string $cp): void {
		$this->cookie_prefix = $cp;
	}

	/**
	 * Get the previously configured cookie prefix
	 *
	 * @return string Cookie prefix (eg osumi-)
	 */
	public function getCookiePrefix(): string {
		return $this->cookie_prefix;
	}

	/**
	 * Set up the URL to be used in the cookies
	 *
	 * @param string $cu URL of the cookies
	 *
	 * @return void
	 */
	public function setCookieUrl(string $cu): void {
		$this->cookie_url = $cu;
	}

	/**
	 * Get the previously configured cookie URL
	 *
	 * @return string URL of the cookies
	 */
	public function getCookieUrl(): string {
		return $this->cookie_url;
	}

	/**
	 * Set the SameSite policy used for cookies.
	 *
	 * @param string $same_site SameSite policy (Strict, Lax or None).
	 *
	 * @return void
	 *
	 * @throws \InvalidArgumentException If the SameSite policy is invalid.
	 */
	public function setCookieSameSite(string $same_site): void {
		$allowed_values = ['Strict', 'Lax', 'None'];

		if (!in_array($same_site, $allowed_values, true)) {
			throw new \InvalidArgumentException(
				'Invalid cookie SameSite policy. Allowed values are Strict, Lax and None.'
			);
		}

		$this->cookie_same_site = $same_site;
	}

	/**
	 * Get the configured SameSite cookie policy.
	 *
	 * @return string SameSite policy.
	 */
	public function getCookieSameSite(): string {
		return $this->cookie_same_site;
	}

	/**
	 * Set the path to be used for cookies.
	 *
	 * @param string $path Cookie path.
	 *
	 * @return void
	 */
	public function setCookiePath(string $path): void {
		$this->cookie_path = $path;
	}

	/**
	 * Get the configured cookie path.
	 *
	 * @return string Cookie path.
	 */
	public function getCookiePath(): string {
		return $this->cookie_path;
	}

	/**
	 * Set whether cookies must only be sent over secure HTTPS connections.
	 *
	 * @param bool $secure Whether cookies must use the Secure attribute.
	 *
	 * @return void
	 */
	public function setCookieSecure(bool $secure): void {
		$this->cookie_secure = $secure;
	}

	/**
	 * Get whether cookies are configured to use the Secure attribute.
	 *
	 * @return bool Whether cookies must only be sent over secure HTTPS connections.
	 */
	public function getCookieSecure(): bool {
		return $this->cookie_secure;
	}

	/**
	 * Set whether cookies must be inaccessible to client-side scripts.
	 *
	 * @param bool $http_only Whether cookies must use the HttpOnly attribute.
	 *
	 * @return void
	 */
	public function setCookieHttpOnly(bool $http_only): void {
		$this->cookie_http_only = $http_only;
	}

	/**
	 * Get whether cookies are configured to use the HttpOnly attribute.
	 *
	 * @return bool Whether cookies are inaccessible to client-side scripts.
	 */
	public function getCookieHttpOnly(): bool {
		return $this->cookie_http_only;
	}

	/**
	 * Store in memory flattened/cached URL list of the application
	 *
	 * @param array Array of the application URLs and their configuration
	 *
	 * @return void
	 */
	public function setUrlList(array $u): void {
		$this->url_list = $u;
	}

	/**
	 * Retrieve stored flattened/cache URL list
	 *
	 * @return array Array of the application URLs and their configuration
	 */
	public function getUrlList(): array | null {
		return $this->url_list;
	}

	/**
	 * Configure a custom error page URL.
	 *
	 * A null value removes the custom error page for the status.
	 *
	 * @param string $status HTTP status code.
	 * @param string|null $url Custom error page URL or null.
	 *
	 * @return void
	 *
	 * @throws \OutOfBoundsException If the status code is unsupported.
	 */
	public function setErrorPage(string $status, ?string $url): void {
		if (!array_key_exists($status, $this->error_pages)) {
			throw new \OutOfBoundsException(
				"Error page status '{$status}' is not supported."
			);
		}

		$this->error_pages[$status] = $url;
	}

	/**
	 * Get the URL where the user has to be redirected on a given HTTP status code or null if it hasn't been customized
	 *
	 * @param string $status Status code to be checked
	 *
	 * @return string URL where the user has to be redirected or null if it hasn't been customized
	 */
	public function getErrorPage(string $status): string | null {
		if (array_key_exists($status, $this->error_pages)) {
			return $this->error_pages[$status];
		}
		return null;
	}

	/**
	 * Set CSS files to include in the application.
	 *
	 * @param string[] $cl CSS file names.
	 *
	 * @return void
	 *
	 * @throws \InvalidArgumentException If the value is not a list of strings.
	 */
	public function setCssList(array $cl): void {
		$this->validateStringList($cl, 'css');

		$this->css_list = $cl;
	}

	/**
	 * Get array of CSS file names to be included in the application
	 *
	 * @return string[] Array of CSS file names to be included
	 */
	public function getCssList(): array {
		return $this->css_list;
	}

	/**
	 * Adds a single item to the array of CSS files to be included in the application
	 *
	 * @param string $item Name of a CSS file to be included
	 *
	 * @return void
	 */
	public function addCssList(string $item): void {
		$this->css_list[] = $item;
	}

	/**
	 * Set elements to include in the document head.
	 *
	 * @param array $he Head element definitions.
	 *
	 * @return void
	 *
	 * @throws \InvalidArgumentException If a head element definition is invalid.
	 */
	public function setHeadElements(array $he): void {
		if (!array_is_list($he)) {
			throw new \InvalidArgumentException(
				"Configuration field 'head_elements' must be a list."
			);
		}

		foreach ($he as $index => $item) {
			if (!is_array($item)) {
				throw new \InvalidArgumentException(
					"Configuration field 'head_elements.{$index}' must be an object."
				);
			}

			$this->validateHeadElement($item, 'head_elements.' . $index);
		}

		$this->head_elements = $he;
	}

	/**
	 * Get array of elements to be included in the <head> tag of the application
	 *
	 * @return string[] Array of elements to be included in the <head> tag of the application
	 */
	public function getHeadElements(): array {
		return $this->head_elements;
	}

	/**
	 * Add an element to the document head.
	 *
	 * @param array $item Head element definition.
	 *
	 * @return void
	 *
	 * @throws \InvalidArgumentException If the head element definition is invalid.
	 */
	public function addHeadElement(array $item): void {
		$this->validateHeadElement($item, 'head_element');

		$this->head_elements[] = $item;
	}

	/**
	 * Set JavaScript files to include in the application.
	 *
	 * @param string[] $jl JavaScript file names.
	 *
	 * @return void
	 *
	 * @throws \InvalidArgumentException If the value is not a list of strings.
	 */
	public function setJsList(array $jl): void {
		$this->validateStringList($jl, 'js');

		$this->js_list = $jl;
	}

	/**
	 * Get array of JS file names to be included in the application
	 *
	 * @return string[] Array of JS file names to be included
	 */
	public function getJsList(): array {
		return $this->js_list;
	}

	/**
	 * Adds a single item to the array of JS files to be included in the application
	 *
	 * @param string $item Name of a JS file to be included
	 *
	 * @return void
	 */
	public function addJsList(string $item): void {
		$this->js_list[] = $item;
	}

	/**
	 * Set up the default title that will be shown in every page of the application (in <title> tag)
	 *
	 * @param string $dt Default title
	 *
	 * @return void
	 */
	public function setDefaultTitle(string $dt): void {
		$this->default_title = $dt;
	}

	/**
	 * Get the default title for the application
	 *
	 * @return string Default title
	 */
	public function getDefaultTitle(): string {
		return $this->default_title;
	}

	/**
	 * Set up the language code for the application (eg "es", "en", "eu"...)
	 *
	 * @param string $l Language code
	 *
	 * @return void
	 */
	public function setLang(string $l): void {
		$this->lang = $l;
	}

	/**
	 * Get the language code for the application
	 *
	 * @return string Language code
	 */
	public function getLang(): string {
		return $this->lang;
	}

	/**
	 * Set the application timezone.
	 *
	 * @param string $timezone Valid PHP timezone identifier.
	 *
	 * @return void
	 *
	 * @throws \InvalidArgumentException If the timezone identifier is invalid.
	 */
	public function setTimezone(string $timezone): void {
		try {
			new \DateTimeZone($timezone);
		} catch (\Exception $e) {
			throw new \InvalidArgumentException(
				"Invalid timezone '{$timezone}'.",
				0,
				$e
			);
		}

		$this->timezone = $timezone;
	}

	/**
	 * Get the application timezone.
	 *
	 * @return string Application timezone identifier.
	 */
	public function getTimezone(): string {
		return $this->timezone;
	}

	/**
	 * Set up the senders email address when sending emails
	 *
	 * @param string $mf Senders email address
	 *
	 * @return void
	 */
	public function setMailingFrom(string $mf): void {
		$this->mailing_from = $mf;
	}

	/**
	 * Get the senders email address when sending emails
	 *
	 * @return string Senders email address
	 */
	public function getMailingFrom(): string {
		return $this->mailing_from;
	}

	/**
	 * Set third-party libraries to load.
	 *
	 * @param string[] $l Library names.
	 *
	 * @return void
	 *
	 * @throws \InvalidArgumentException If the value is not a list of strings.
	 */
	public function setLibs(array $l): void {
		$this->validateStringList($l, 'libs');

		$this->libs = $l;
	}

	/**
	 * Get the list of third-party libraries loaded into the application
	 *
	 * @return string[] Array of third-party library names
	 */
	public function getLibs(): array {
		return $this->libs;
	}

	/**
	 * Add a single library to the list of third-party libraries to be loaded
	 *
	 * @param string $item Name of the library to be loaded
	 *
	 * @return void
	 */
	public function addLib(string $item): void {
		$this->libs[] = $item;
	}

	/**
	 * Set a customized configuration value.
	 *
	 * @param string $key Key of the item to store.
	 * @param string | int | float | bool $value Value to store.
	 *
	 * @return void
	 */
	public function setExtra(string $key, string | int | float | bool $value): void {
		$this->extras[$key] = $value;
	}

	/**
	 * Get a customized configuration value.
	 *
	 * @param string $key Key of the item to retrieve.
	 *
	 * @return string | int | float | bool | null Stored value or null if the key does not exist.
	 */
	public function getExtra(string $key): string | int | float | bool | null {
		return array_key_exists(
			$key,
			$this->extras
		)
			? $this->extras[$key]
			: null;
	}

	/**
	 * Return configuration information safe for debugging.
	 *
	 * Sensitive database credentials, plugin configuration values and extra values
	 * are hidden to prevent accidental disclosure.
	 *
	 * @return array Safe configuration information.
	 */
	public function __debugInfo(): array {
		$info = get_object_vars($this);

		if (
			isset($info['db']) &&
			is_array($info['db']) &&
			array_key_exists('pass', $info['db'])
		) {
			$info['db']['pass'] = '[HIDDEN]';
		}

		if (
			isset($info['plugin_config']) &&
			is_array($info['plugin_config'])
		) {
			$info['plugin_config'] = array_fill_keys(
				array_keys($info['plugin_config']),
				'[HIDDEN]'
			);
		}

		if (
			isset($info['extras']) &&
			is_array($info['extras'])
		) {
			$info['extras'] = array_fill_keys(
				array_keys($info['extras']),
				'[HIDDEN]'
			);
		}

		return $info;
	}
}
