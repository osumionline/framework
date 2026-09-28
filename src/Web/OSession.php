<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Web;

use Osumi\OsumiFramework\Log\OLog;

/**
 * OSession - Class with methods to get/set information into the users session
 */
class OSession {
	private bool        $debug  = false;
	private OLog | null $l      = null;
	/**
	 * @var array<string, string|int|float|bool>
	 */
	private array $params = [];

	/**
	 * Load session information on startup.
	 */
	public function __construct() {
		global $core;

		$this->debug = ($core->config->getLog('level') == 'ALL');

		if ($this->debug) {
			$this->l = new OLog('OSession');
		}

		$params = $_SESSION['params'] ?? [];

		if (!is_array($params)) {
			unset($_SESSION['params']);
			return;
		}

		foreach ($params as $key => $value) {
			if (
				!is_string($key) ||
				(
					!is_string($value) &&
					!is_int($value) &&
					!is_float($value) &&
					!is_bool($value)
				)
			) {
				unset($_SESSION['params']);

				return;
			}
		}

		$this->params = $params;
	}

	/**
	 * Logs internal information of the class.
	 *
	 * @param string $str String to be logged.
	 *
	 * @return void
	 */
	private function log(string $str): void {
		if ($this->debug) {
			$this->l->debug($str);
		}
	}

	/**
	 * Save the given parameter list into memory and the user session.
	 *
	 * @param array<string, string|int|float|bool> $p Parameter list.
	 *
	 * @return void
	 *
	 * @throws \InvalidArgumentException If a session parameter key or value is
	 *                                   unsupported.
	 */
	public function setParams(array $p): void {
		foreach ($p as $key => $value) {
			if (!is_string($key)) {
				throw new \InvalidArgumentException(
					'Session parameter keys must be strings.'
				);
			}

			if (
				!is_string($value) &&
				!is_int($value) &&
				!is_float($value) &&
				!is_bool($value)
			) {
				throw new \InvalidArgumentException(
					'Session parameters only support string, int, float and bool values.'
				);
			}
		}

		$this->log('setParams - Params:');
		$this->log(var_export($p, true));

		$this->params = $p;
		$_SESSION['params'] = $p;
	}

	/**
	 * Get the parameter list.
	 *
	 * @return array<string, string|int|float|bool> Parameter list.
	 */
	public function getParams(): array {
		return $this->params;
	}

	/**
	 * Add a new key/value parameter into memory and the user session.
	 *
	 * @param string $key Key code of the parameter.
	 * @param string|int|float|bool $value Value of the parameter.
	 *
	 * @return void
	 */
	public function addParam(
		string $key,
		string | int | float | bool $value
	): void {
		$this->params[$key] = $value;
		$this->setParams($this->params);
	}

	/**
	 * Get a parameter from the previously loaded list.
	 *
	 * @param string $key Key code of the parameter.
	 *
	 * @return string|int|float|bool|null Value of the parameter or null if not found.
	 */
	public function getParam(string $key): string | int | float | bool | null {
		return array_key_exists($key, $this->params)
			? $this->params[$key]
			: null;
	}

	/**
	 * Remove a parameter from the list and the user session.
	 *
	 * @param string $key Key code of the parameter.
	 *
	 * @return void
	 */
	public function removeParam(string $key): void {
		unset($this->params[$key]);
		$this->setParams($this->params);
	}

	/**
	 * Remove all parameters from the user session and reset the internal list.
	 *
	 * @return void
	 */
	public function cleanSession(): void {
		$this->params = [];
		unset($_SESSION['params']);
	}

	/**
	 * Regenerate the current PHP session identifier.
	 *
	 * The current session data is preserved and the previous session data is not
	 * immediately deleted to avoid race conditions with concurrent requests.
	 *
	 * @return void
	 *
	 * @throws \RuntimeException If there is no active session or the session
	 *                           identifier cannot be regenerated.
	 */
	public function regenerateId(): void {
		if (session_status() !== PHP_SESSION_ACTIVE) {
			throw new \RuntimeException(
				'Cannot regenerate the session identifier because there is no active session.'
			);
		}

		if (!session_regenerate_id(false)) {
			throw new \RuntimeException(
				'Could not regenerate the session identifier.'
			);
		}
	}
}
