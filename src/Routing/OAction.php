<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\Routing;

use \ReflectionClass;
use Osumi\OsumiFramework\Core\OConfig;
use Osumi\OsumiFramework\Core\OTemplate;
use Osumi\OsumiFramework\DB\ODB;
use Osumi\OsumiFramework\Log\OLog;
use Osumi\OsumiFramework\Web\OSession;
use Osumi\OsumiFramework\Web\OCookie;
use Osumi\OsumiFramework\Cache\OCacheContainer;

/**
 * OAction - Base class for the actions providing access to the framework configuration, database, template, logs, session or cookies
 */
class OAction {
	protected ?OConfig         $config     = null;
	protected ?ODB             $db         = null;
	protected ?OTemplate       $template   = null;
	protected ?OLog            $log        = null;
	protected ?OSession        $session    = null;
	protected ?OCookie         $cookie     = null;
	protected ?OCacheContainer $cacheContainer = null;
	protected array            $service    = [];

	/**
	 * Load matched URL configuration value into the action
	 *
	 * @param array $url_result Configuration array as in urls.json
	 *
	 * @return void
	 */
	public final function loadAction(array $url_result): void {
		global $core;

		if (!is_null($core->dbContainer)) {
			$this->db = new ODB();
		}
		$this->template = new OTemplate();
		$this->log      = new OLog(get_class($this));
		$this->cookie   = new OCookie();

		// Current and previous action
		if ($this->getSession()->getParam('current') != '') {
			$this->getSession()->addParam('previous', $this->session->getParam('current'));
		}
		$this->getSession()->addParam('current', get_class($this));

		// Load type and layout into the template
		$this->template->setType($url_result['type']);
		$this->template->loadLayout($url_result['layout']);

		// Load actions template file
		$reflection = new ReflectionClass($this);
    $template_path = str_ireplace('.php', '.'.$url_result['type'], $reflection->getFileName());
		$this->template->setTemplatePath($template_path);

		// Load action's required services
		foreach ($url_result['services'] as $item) {
			$service = new $item();
			$service->loadService();
			$reflection = new ReflectionClass($service);
			$class_name = str_ireplace('Service', '', $reflection->getShortName());
			$this->service[$class_name] = $service;
		}

		$action_base_dir = '';
		if (count($url_result['inline_css']) > 0 || count($url_result['inline_js']) > 0) {
			$reflection = new ReflectionClass($this);
	    $action_base_dir = dirname($reflection->getFileName());
		}

		// Load action's CSS and JS files
		foreach ($url_result['inline_css'] as $item) {
			$css_file = $action_base_dir.'/'.$item.'.css';
			$this->template->addCss($css_file, true);
		}

		foreach ($url_result['css'] as $item) {
			$this->template->addCss($item);
		}

		foreach ($url_result['inline_js'] as $item) {
			$js_file = $action_base_dir.'/'.$item.'.js';
			$this->template->addJs($js_file, true);
		}

		foreach ($url_result['js'] as $item) {
			$this->template->addJs($item);
		}
	}

	/**
	 * Get the application configuration (shortcut to $core->config)
	 *
	 * @return OConfig Configuration class object
	 */
	public final function getConfig(): OConfig {
		global $core;
		return $core->config;
	}

	/**
	 * Get a preloaded object to access the database
	 *
	 * @return ODB Database access object
	 */
	public final function getDB(): ODB {
		return $this->db;
	}

	/**
	 * Get access to the action's template via a template configuration class object
	 *
	 * @return OTemplate Template configuration class object
	 */
	public final function getTemplate(): OTemplate {
		return $this->template;
	}

	/**
	 * Get object to log information into the debug log
	 *
	 * @return OLog Information logger object
	 */
	public final function getLog(): OLog {
		return $this->log;
	}

	/**
	 * Get access to the users session information
	 *
	 * @return OSession Session configuration class object
	 */
	public final function getSession(): OSession {
		global $core;
		return $core->session;
	}

	/**
	 * Get access to the users cookies
	 *
	 * @return OCookie Cookie configuration class object
	 */
	public final function getCookie(): OCookie {
		return $this->cookie;
	}

	/**
	 * Get access to the cache container
	 *
	 * @return OCacheContainer Cache container class object
	 */
	public final function getCacheContainer(): OCacheContainer {
		global $core;
		return $core->cacheContainer;
	}
}
