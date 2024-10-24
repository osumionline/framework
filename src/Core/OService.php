<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\Core;

use Osumi\OsumiFramework\Log\OLog;
use Osumi\OsumiFramework\Cache\OCacheContainer;

/**
 * OService - Base class for the service classes
 */
class OService {
	protected ?OLog    $log    = null;

	/**
	 * Load global configuration and logger to use in the service
	 *
	 * @return void
	 */
	public final function loadService(): void {
		$this->log = new OLog(get_class($this));
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
	 * Get object to log information into the debug log
	 *
	 * @return OLog Information logger object
	 */
	public final function getLog(): OLog {
		return $this->log;
	}

	/**
	 * Get access to the cache container
	 *
	 * @return OCacheContainer Cache container class object
	 */
	public final function getCacheContainer(): OCacheContainer {
		global $core;
		return $core->cache_container;
	}
}
