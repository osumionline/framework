<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Cache;

use Osumi\OsumiFramework\Tools\OTools;

/**
 * OCacheContainer - Container for all json cache files.
 */
class OCacheContainer {
	private ?string $cache_folder = null;
	/**
	 * @var array<string, OCache>
	 */
	private array $list = [];

	/**
	 * On startup, the cache container reads the cache folder and loads the cache keys, not the objects itself.
	 */
	public function __construct() {
		global $core;
		OTools::checkOfw('cache');
		$this->cache_folder = $core->config->getDir('ofw_cache');
		$this->loadItems();
	}

	/**
	 * Read the cache folder and load the cache items found there into the list.
	 *
	 * @return void
	 */
	public function loadItems(): void {
		$this->list = [];

		$model = opendir($this->cache_folder);

		if ($model === false) {
			return;
		}

		while (($entry = readdir($model)) !== false) {
			if (
				$entry === '.' ||
				$entry === '..' ||
				!str_ends_with($entry, '.cache.json')
			) {
				continue;
			}

			$name = substr($entry, 0, -strlen('.cache.json'));

			if ($name === '') {
				continue;
			}

			$this->list[$name] = new OCache($name);
		}

		closedir($model);
	}

	/**
	 * Get the OCache item with the given key. If the cache item doesn't exist, return an empty new OCache.
	 *
	 * @param string $key Key of the cache item to be retrieved
	 *
	 * @return OCache Cache instance.
	 */
	public function getItem(string $key): OCache {
		if ($this->hasItem($key)) {
			return $this->list[$key];
		}
		return new OCache($key);
	}

	/**
	 * Get the cache item list.
	 *
	 * @return array<string, OCache> Cache items indexed by cache key.
	 */
	public function getItems(): array {
		return $this->list;
	}

	/**
	 * Get if an item exists on the cache item list.
	 */
	public function hasItem(string $key): bool {
		return array_key_exists($key, $this->list);
	}

	/**
	 * Deletes all cached files.
	 *
	 * @return bool True if all cache files where successfully deleted, false otherwise.
	 */
	public function clear(): bool {
		$ret = true;
		foreach ($this->list as $key => $item) {
			if (!$this->deleteItem($key)) {
				$ret = false;
			}
		}
		return $ret;
	}

	/**
	 * Delete a cache item from both the filesystem and the container.
	 *
	 * @param string $key Cache item key.
	 *
	 * @return bool True if the cache item was successfully deleted, false otherwise.
	 */
	public function deleteItem(string $key): bool {
		if (!$this->hasItem($key)) {
			return false;
		}

		$route = $this->cache_folder . $key . '.cache.json';

		if (!file_exists($route)) {
			return false;
		}

		if (!unlink($route)) {
			return false;
		}

		unset($this->list[$key]);

		return true;
	}

	/**
	 * Delete a list of cache items.
	 *
	 * @param string[] $keys Cache item keys.
	 *
	 * @return bool True if all requested cache items were successfully deleted.
	 *
	 * @throws \InvalidArgumentException If the supplied value is not a list of string keys.
	 */
	public function deleteItems(array $keys): bool {
		if (!array_is_list($keys)) {
			throw new \InvalidArgumentException(
				'Cache item keys must be provided as a list.'
			);
		}

		foreach ($keys as $key) {
			if (!is_string($key)) {
				throw new \InvalidArgumentException(
					'Cache item keys must be strings.'
				);
			}
		}

		$ret = true;

		foreach ($keys as $key) {
			if (!$this->deleteItem($key)) {
				$ret = false;
			}
		}

		return $ret;
	}

	/**
	 * Save the cache item and store it on the cache container.
	 *
	 * @param OCache $item Cache item to be saved.
	 *
	 * @return bool True if the item was successfully saved, false otherwise.
	 */
	public function save(OCache $item): bool {
		if ($item->save()) {
			if (!$this->hasItem($item->getKey())) {
				$this->list[$item->getKey()] = $item;
			}
			return true;
		}
		return false;
	}
}
