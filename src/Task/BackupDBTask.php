<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Task;

use Osumi\OsumiFramework\Core\OTask;
use Osumi\OsumiFramework\Tools\OTools;

/**
 * Performs a database backup using "mysqldump" CLI tool. Generates a file on ofw/export folder with the name of the database.
 */
class BackupDBTask extends OTask {
	public function __toString(): string {
		return $this->getColors()->getColoredString("backupDB", "light_green") . ": " . OTools::getMessage('TASK_BACKUP_DB');
	}

	/**
	 * Escapes a value to be safely used in a MySQL option file.
	 *
	 * @param string $value Value to be escaped.
	 *
	 * @return string Escaped value enclosed in double quotes.
	 */
	private function escapeOptionFileValue(string $value): string {
		$value = str_replace(
			['\\', '"', "\n", "\r", "\t"],
			['\\\\', '\\"', '\\n', '\\r', '\\t'],
			$value
		);

		return '"' . $value . '"';
	}

	/**
	 * Run the task.
	 *
	 * Supported options:
	 * - silent: "true" suppresses informational output.
	 * - from_all: "true" indicates execution from BackupAllTask.
	 *
	 * @param array<string, string|false> $params Task options.
	 *
	 * @return void
	 *
	 * @throws \RuntimeException If a database backup or temporary credentials file
	 *                           operation fails.
	 * @throws \Random\RandomException If a secure temporary filename cannot be
	 *                                 generated.
	 */
	public function run(array $params = []): void {
		$silent = false;
		if (array_key_exists('silent', $params) && $params['silent'] === 'true') {
			$silent = true;
		}

		$path   = $this->getConfig()->getDir('ofw_template') . 'backupDB/backupDB.php';
		$values = [
			'colors'      => $this->getColors(),
			'from_all'    => (array_key_exists('from_all', $params) && $params['from_all'] === 'true'),
			'hasDB'       => true,
			'db_name'     => '',
			'dump_file'   => '',
			'dump_exists' => false,
			'success'     => false,
		];

		if (
			$this->getConfig()->getDB('host') === '' ||
			$this->getConfig()->getDB('user') === '' ||
			$this->getConfig()->getDB('pass') === '' ||
			$this->getConfig()->getDB('name') === ''
		) {
			$values['hasDB'] = false;
		}

		if ($values['hasDB']) {
			OTools::checkOfw('export');
			$values['db_name']     = $this->getColors()->getColoredString($this->getConfig()->getDb('name'));
			$values['dump_file']   = $this->getConfig()->getDir('ofw_export') . $this->getConfig()->getDb('name') . '.sql';
			$values['dump_exists'] = file_exists($values['dump_file']);


			OTools::checkOfw('tmp');

			$credentials_file = $this->getConfig()->getDir('ofw_tmp')
				. 'mysqldump_'
				. bin2hex(random_bytes(16))
				. '.cnf';

			$credentials = implode("\n", [
				'[client]',
				'host=' . $this->escapeOptionFileValue($this->getConfig()->getDB('host')),
				'user=' . $this->escapeOptionFileValue($this->getConfig()->getDB('user')),
				'password=' . $this->escapeOptionFileValue($this->getConfig()->getDB('pass')),
				''
			]);

			if (file_put_contents($credentials_file, $credentials, LOCK_EX) === false) {
				throw new \RuntimeException(
					'Could not create temporary database credentials file.'
				);
			}

			try {
				if (DIRECTORY_SEPARATOR === '/' && !chmod($credentials_file, 0600)) {
					throw new \RuntimeException(
						'Could not set permissions on temporary database credentials file.'
					);
				}

				$command = sprintf(
					'mysqldump --defaults-extra-file=%s %s --result-file=%s 2>&1',
					escapeshellarg($credentials_file),
					escapeshellarg($this->getConfig()->getDB('name')),
					escapeshellarg($values['dump_file'])
				);

				$output = [];
				$return_code = 0;

				exec($command, $output, $return_code);

				if ($return_code === 0 && file_exists($values['dump_file'])) {
					$values['success'] = true;
				}
			} finally {
				if (file_exists($credentials_file) && !unlink($credentials_file)) {
					throw new \RuntimeException(
						'Could not remove temporary database credentials file.'
					);
				}
			}
		}

		if (!$silent) {
			echo OTools::getPartial($path, $values);
		}
	}
}
