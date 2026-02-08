<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Core;

use Osumi\OsumiFramework\Tools\OColors;
use Osumi\OsumiFramework\Log\OLog;
use Osumi\OsumiFramework\Core\OConfig;
use Osumi\OsumiFramework\Cache\OCacheContainer;

/**
 * OTask - Base class for the task classes
 */
class OTask {
	protected OColors | null $colors = null;
	protected OLog | null    $log    = null;

	/**
	 * Load global configuration and logger to use in the service
	 *
	 * @return void
	 */
	public final function loadTask(): void {
		$this->colors = new OColors();
		$this->log    = new OLog(get_class($this));
	}

	/**
	 * Get the colors object used to colorize messages in the CLI tasks
	 *
	 * @return OColors | null Message colorizer object
	 */
	public final function getColors(): OColors | null {
		return $this->colors;
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
	public final function getLog(): OLog | null {
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
