<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Task;

use Osumi\OsumiFramework\Core\OTask;

class {{uc_name}}Task extends OTask {
	public function __toString(): string {
		return {{task_description}};
	}

	public function run(array $options = []): void {}
}
