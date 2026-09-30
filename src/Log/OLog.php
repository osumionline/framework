<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Log;

use Osumi\OsumiFramework\Tools\OTools;

/**
 * OLog - Class to log information to a debug log file
 */
class OLog {
	private string | null $class_name = null;
	private string | null $log_dir = null;
	private string $log_file_name = 'osumi';
	private string $log_file_ext  = 'log';
	private string $log_path      = '';
	private int $max_file_size    = 50;
	private int $max_num_files    = 3;
	private string $log_level     = 'ALL';
	private array $levels         = ['ALL', 'DEBUG', 'INFO', 'ERROR'];

	/**
	 * Start up the object by getting the logging configuration from the global config
	 *
	 * @param string | null $class_name Name of the class where the logger is used
	 */
	function __construct(string | null $class_name = null) {
		global $core;
		OTools::checkOfw('logs');
		$this->log_dir = $core->config->getDir('ofw_logs');
		$this->log_file_name = $core->config->getLog('name');
		$this->log_path = $this->log_dir . $this->log_file_name . '.' . $this->log_file_ext;
		$this->max_file_size = $core->config->getLog('max_file_size');
		$this->max_num_files = $core->config->getLog('max_num_files');
		$log_level = $core->config->getLog('level');

		$this->log_level = (is_string($log_level) &&
			in_array(
				$log_level,
				$this->levels,
				true
			)
		)
			? $log_level
			: 'ALL';

		if (!is_null($class_name)) {
			$this->class_name = $class_name;
		}
	}

	/**
	 * Log a given debug string if the log level is in ('ALL', 'DEBUG')
	 *
	 * @param string $str String to be logged
	 *
	 * @return bool Returns if the message was written to the log file or not
	 */
	public function debug(string $str): bool {
		$bt = debug_backtrace();
		$caller = array_shift($bt);
		if (in_array($this->log_level, ['ALL', 'DEBUG'])) {
			return $this->putLog('DEBUG', $str, $caller);
		}
		return false;
	}

	/**
	 * Log a given info string if the log level is in ('ALL', 'DEBUG', 'INFO')
	 *
	 * @param string $str String to be logged
	 *
	 * @return bool Returns if the message was written to the log file or not
	 */
	public function info(string $str): bool {
		$bt = debug_backtrace();
		$caller = array_shift($bt);
		if (in_array($this->log_level, ['ALL', 'DEBUG', 'INFO'])) {
			return $this->putLog('INFO', $str, $caller);
		}
		return false;
	}

	/**
	 * Log a given error string
	 *
	 * @param string $str String to be logged
	 *
	 * @return bool Returns if the message was written to the log file or not
	 */
	public function error(string $str): bool {
		$bt = debug_backtrace();
		$caller = array_shift($bt);
		return $this->putLog('ERROR', $str, $caller);
	}

	/**
	 * Write a log entry to the configured log file.
	 *
	 * The log file is rotated or truncated when the configured maximum size is
	 * exceeded.
	 *
	 * @param string $level Log importance level (DEBUG, INFO or ERROR).
	 * @param string $str Message to log.
	 * @param array<string, mixed> $caller Information about the caller.
	 *
	 * @return bool Whether the log entry was written successfully.
	 */
	private function putLog(
		string $level,
		string $str,
		array $caller
	): bool {
		$data = '['
			. date('Y-m-d H:i:s')
			. '] - ['
			. $level . '] -';
		if ($this->class_name !== null) {
			$data .= '['
				. $this->class_name
				. '] - ';
		}

		$data .= '[' . basename((string)($caller['file'] ?? 'unknown'))
			. ' - '
			. (string) ($caller['line'] ?? 0)
			. '] - ';

		$data .= $str . "\n";

		$data_file_size = strlen($data);
		$max_size = $this->max_file_size * 1024 * 1024;

		$log_exists = is_file(
			$this->log_path
		);
		if (!$log_exists) {
			return file_put_contents(
				$this->log_path,
				$data,
				LOCK_EX
			) !== false;
		}

		$log_file_size = filesize(
			$this->log_path
		);
		if ($log_file_size === false) {
			return false;
		}

		if (($log_file_size + $data_file_size) <= $max_size) {
			return file_put_contents(
				$this->log_path,
				$data,
				FILE_APPEND | LOCK_EX
			) !== false;
		}

		if ($this->max_num_files <= 1) {
			/*
		 * A single log entry may itself be larger than the configured maximum
		 * size. Preserve the complete entry rather than truncating it.
		 */
			if ($data_file_size >= $max_size) {
				return file_put_contents(
					$this->log_path,
					$data,
					LOCK_EX
				) !== false;
			}

			$old_log = file_get_contents(
				$this->log_path
			);
			if ($old_log === false) {
				return false;
			}

			$available_size = $max_size - $data_file_size;
			if (strlen($old_log) > $available_size) {
				$old_log = substr(
					$old_log,
					-$available_size
				);
			}

			return file_put_contents(
				$this->log_path,
				$old_log . $data,
				LOCK_EX
			) !== false;
		}

		$last_log = $this->log_dir
			. $this->log_file_name
			. '_'
			. $this->max_num_files
			. '.'
			. $this->log_file_ext;
		if (
			is_file($last_log) &&
			!unlink($last_log)
		) {
			return false;
		}

		for (
			$i = $this->max_num_files - 1;
			$i > 0;
			$i--
		) {
			$from = $this->log_dir
				. $this->log_file_name
				. '_'
				. $i
				. '.'
				. $this->log_file_ext;

			$to = $this->log_dir
				. $this->log_file_name
				. '_'
				. ($i + 1)
				. '.'
				. $this->log_file_ext;
			if (
				is_file($from) &&
				!rename(
					$from,
					$to
				)
			) {
				return false;
			}
		}

		$first_log = $this->log_dir
			. $this->log_file_name
			. '_1.'
			. $this->log_file_ext;

		if (!rename(
			$this->log_path,
			$first_log
		)) {
			return false;
		}

		return file_put_contents(
			$this->log_path,
			$data,
			LOCK_EX
		) !== false;
	}
}
