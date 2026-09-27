<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Cache;

use Osumi\OsumiFramework\Tools\OTools;

/**
 * OCache - Cache item instance
 */
class OCache {
	private string | null $key = null;
	private bool $loaded = false;
	private string | null $route = null;
	private bool $is_hit = false;
	private mixed $value;
	private int $expires_after = 0;

	/**
	 * Create a new cache instance with a given key.
	 */
	public function __construct(string $key) {
		global $core;
		$this->key = $key;
		OTools::checkOfw('cache');
		$this->route = $core->config->getDir('ofw_cache') . $key . '.cache.json';
		$this->expires_after = 60 * 60 * 24 * 7; // Default expiration time: one week
	}

	/**
	 * Load and validate the cache item from disk.
	 *
	 * @return void
	 */
	private function load(): void {
		if ($this->loaded) {
			return;
		}

		$this->loaded = true;
		$this->is_hit = false;
		$this->value = null;

		if (!file_exists($this->route)) {
			return;
		}

		$content = file_get_contents($this->route);

		if ($content === false) {
			return;
		}

		try {
			$content_parsed = json_decode(
				$content,
				true,
				512,
				JSON_THROW_ON_ERROR
			);
		} catch (\JsonException) {
			return;
		}

		if (
			!is_array($content_parsed) ||
			!array_key_exists('expiresAt', $content_parsed) ||
			!is_int($content_parsed['expiresAt']) ||
			!array_key_exists('value', $content_parsed) ||
			!is_string($content_parsed['value'])
		) {
			return;
		}

		if (time() > $content_parsed['expiresAt']) {
			return;
		}

		$this->value = OTools::base64urlDecode($content_parsed['value']);
		$this->is_hit = true;
	}

	/**
	 * Get the cache item key name.
	 *
	 * @return string | null Item key name
	 */
	public function getKey(): string | null {
		return $this->key;
	}

	/**
	 * Check if the cache item contains a valid, non-expired value.
	 *
	 * @return bool True if the cache item is valid and has not expired.
	 */
	public function isHit(): bool {
		$this->load();

		return $this->is_hit;
	}

	/**
	 * Get the cached value.
	 *
	 * @return mixed Cached value, or null if the cache item is not valid.
	 */
	public function get(): mixed {
		$this->load();

		return $this->value;
	}

	/**
	 * Override manually the default expiration time
	 *
	 * @param int $expires_after Expiration time in seconds
	 *
	 * @return void
	 */
	public function setExpiresAfter(int $expires_after): void {
		$this->expires_after = $expires_after;
	}

	/**
	 * Set in memory the value that will be cached
	 *
	 * @return void
	 */
	public function set(mixed $value): void {
		$this->value = $value;
	}

	/**
	 * Save the stored value into a cache file.
	 *
	 * @return bool True if the cache file was saved, false otherwise.
	 *
	 * @throws \JsonException If the cache data cannot be encoded as JSON.
	 */
	public function save(): bool {
		$content = [
			'expiresAt' => (time() + $this->expires_after),
			'value' => OTools::base64urlEncode($this->value)
		];
		$content_json = json_encode(
			$content,
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
		);

		$status = file_put_contents($this->route, $content_json) !== false;
		if ($status) {
			$this->is_hit = true;
			$this->loaded = true;
		}
		return $status;
	}

	/**
	 * Reset values so that the next time is used it will load the cache again fron scratch
	 *
	 * @return void
	 */
	public function reload(): void {
		$this->loaded = false;
		$this->is_hit = false;
		$this->value  = null;
	}
}
