<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\Task;

use Osumi\OsumiFramework\Core\OTask;
use Osumi\OsumiFramework\Tools\OTools;

/**
 * Performs a database backup using "mysqldump" CLI tool. Generates a file on ofw/export folder with the name of the database.
 */
class BackupDBTask extends OTask {
	public function __toString() {
		return $this->getColors()->getColoredString("backupDB", "light_green").": ".OTools::getMessage('TASK_BACKUP_DB');
	}

	/**
	 * Run the task
	 *
	 * @param array $params If $params has one item and is true, generates the backup silently, else it echoes information messages
	 *
	 * @return void Echoes messages generated while performing the backup
	 */
	public function run(array $params = []): void {
		$silent = false;
		if (array_key_exists('silent', $params) && $params['silent'] === 'true') {
			$silent = true;
		}

		$path   = $this->getConfig()->getDir('ofw_template').'backupDB/backupDB.php';
		$values = [
			'colors'      => $this->getColors(),
			'from_all'    => (array_key_exists('from_all', $params) && $params['from_all'] === 'true'),
			'hasDB'       => true,
			'db_name'     => '',
			'dump_file'   => '',
			'dump_exists' => false,
			'success'     => false,
		];

		if ($this->getConfig()->getDB('host') === '' ||
				$this->getConfig()->getDB('user') === '' ||
				$this->getConfig()->getDB('pass') === '' ||
				$this->getConfig()->getDB('name') === ''
			) {
			$values['hasDB'] = false;
		}

		if ($values['hasDB']) {
			OTools::checkOfw('export');
			$values['db_name']     = $this->getColors()->getColoredString($this->getConfig()->getDb('name'));
			$values['dump_file']   = $this->getConfig()->getDir('ofw_export').$this->getConfig()->getDb('name').'.sql';
			$values['dump_exists'] = file_exists($values['dump_file']);


			if ($values['dump_exists']) {
				unlink($values['dump_file']);
			}
			$command = "mysqldump --user={$this->getConfig()->getDB('user')} --password={$this->getConfig()->getDB('pass')} --host={$this->getConfig()->getDB('host')} {$this->getConfig()->getDB('name')} --result-file={$values['dump_file']} 2>&1";

			exec($command, $output);
			if (is_array($output) && count($output) === 0) {
				$values['success'] = true;
			}
		}

		if (!$silent) {
			echo OTools::getPartial($path, $values);
		}
	}
}
