<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\Routing;

use Osumi\OsumiFramework\Routing\OModuleAction;
use Osumi\OsumiFramework\Core\OConfig;
use Osumi\OsumiFramework\Core\OTemplate;
use Osumi\OsumiFramework\DB\ODB;
use Osumi\OsumiFramework\Log\OLog;
use Osumi\OsumiFramework\Web\OSession;
use Osumi\OsumiFramework\Web\OCookie;
use Osumi\OsumiFramework\Cache\OCacheContainer;

/**
 * OAction - Base class for the module actions providing access to the framework configuration, database, template, logs, session or cookies
 */
class OAction {
	protected OModuleAction | null   $attributes = null;
	protected OConfig | null         $config     = null;
	protected ODB | null             $db         = null;
	protected OTemplate | null       $template   = null;
	protected OLog | null            $log        = null;
	protected OSession | null        $session    = null;
	protected OCookie | null         $cookie     = null;
	protected OCacheContainer | null $cacheContainer = null;
	protected array                  $service    = [];

	/**
	 * Load matched URL configuration value into the module
	 *
	 * @param array $url_result Configuration array as in urls.json
	 *
	 * @param OModuleAction $attributes Action attributes
	 *
	 * @return void
	 */
	public final function loadAction(array $url_result, OModuleAction $attributes): void {
		global $core;

		$this->attributes = $attributes;
		$this->config     = $core->config;
		$this->session    = $core->session;
		$this->cacheContainer = $core->cacheContainer;
		if (!is_null($core->dbContainer)) {
			$this->db = new ODB();
		}
		$this->template = new OTemplate();
		$this->log      = new OLog(get_class($this));
		$this->cookie   = new OCookie();

		// Current and previous module
		if ($this->session->getParam('current') != '') {
			$this->session->addParam('previous', $this->session->getParam('current'));
		}
		$this->session->addParam('current', $url_result['module'].'/'.$url_result['action']);

		// Load module, action and layout into the template
		$this->template->setModule($url_result['module']);
		$this->template->setAction($url_result['action']);
		$this->template->setType($url_result['type']);
		$this->template->loadLayout($url_result['layout']);

		// Load action's required services
		foreach ($this->attributes->getServices() as $item) {
			$service_name = "Osumi\\OsumiFramework\\App\\Service\\".$item.'Service';
			$service = new $service_name;
			$service->loadService();
			$this->service[$item] = $service;
		}

		// Load action's CSS and JS files
		foreach ($this->attributes->getInlineCss() as $item) {
			$css_file = $this->config->getDir('app_module').$url_result['module'].'/Actions/'.$url_result['action'].'/'.$item.'.css';
			$this->template->addCss($css_file, true);
		}

		foreach ($this->attributes->getCss() as $item) {
			$this->template->addCss($item);
		}

		foreach ($this->attributes->getInlineJs() as $item) {
			$js_file = $this->config->getDir('app_module').$url_result['module'].'/Actions/'.$url_result['action'].'/'.$item.'.js';
			$this->template->addJs($js_file, true);
		}

		foreach ($this->attributes->getJs() as $item) {
			$this->template->addJs($item);
		}
	}

	/**
	 * Get the application configuration (shortcut to $core->config)
	 *
	 * @return OConfig Configuration class object
	 */
	public final function getConfig(): OConfig {
		return $this->config;
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
	 * Get access to the module's template via a template configuration class object
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
		return $this->session;
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
		return $this->cacheContainer;
	}
}
