<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\Task;

use Osumi\OsumiFramework\Core\OTask;
use Osumi\OsumiFramework\Tools\OTools;

/**
 * Get Frameworks current version information
 */
class versionTask extends OTask {
	public function __toString() {
		return $this->getColors()->getColoredString('version', 'light_green').': '.OTools::getMessage('TASK_VERSION');
	}

	private string $repo_url = 'https://github.com/osumionline/framework';
	private string $x_url    = 'https://x.com/osumionline';

	/**
	 * Run the task
	 *
	 * @return void Echoes framework information
	 */
	public function run(): void {
		$path   = $this->getConfig()->getDir('ofw_template').'version/version.php';
		$values = [
			'colors'   => $this->getColors(),
			'repo_url' => $this->repo_url,
			'x_url'    => $this->x_url
		];

		echo OTools::getPartial($path, $values);
	}
}
