<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Task;

use Osumi\OsumiFramework\Core\OTask;
use Osumi\OsumiFramework\Tools\OTools;

/**
 * Cleans all non framework data, to be used on new installations
 */
class ResetTask extends OTask {
	public function __toString(): string {
		return $this->getColors()->getColoredString('reset', 'light_green') . ': ' . OTools::getMessage('TASK_RESET');
	}

	/**
	 * Recursively remove a directory or file without following symbolic links.
	 *
	 * @param string $path Path to remove.
	 *
	 * @return bool Whether the path was successfully removed.
	 *
	 * @throws \RuntimeException If a directory cannot be scanned or one of its
	 *                           contents cannot be removed.
	 */
	private function rrmdir(string $path): bool {
		if (is_link($path) || !is_dir($path)) {
			return unlink($path);
		}

		$files = scandir($path);

		if ($files === false) {
			throw new \RuntimeException(
				"Could not scan directory '{$path}'."
			);
		}

		foreach ($files as $file) {
			if ($file === '.' || $file === '..') {
				continue;
			}

			$item = $path . DIRECTORY_SEPARATOR . $file;

			if (!$this->rrmdir($item)) {
				throw new \RuntimeException(
					"Could not remove '{$item}'."
				);
			}
		}

		return rmdir($path);
	}

	private function countDown(): void {
		for ($i = 10; $i >= 0; $i--) {
			echo "  ";
			if ($i < 4) {
				echo $this->getColors()->getColoredString(strval($i), 'red');
			} else {
				echo $i;
			}
			echo "\n";
			sleep(1);
		}
	}

	private function cleanData(): void {
		$clean_list = [
			'app' => true,
			'ofw' => true,
			'public' => false
		];

		// Empty and delete folders
		foreach ($clean_list as $value => $delete) {
			$directory = $this->getConfig()->getDir($value);

			if (is_dir($directory)) {
				$this->assertSafeDeletePath($directory);

				if ($model = opendir($directory)) {
					while (false !== ($entry = readdir($model))) {
						if ($entry !== '.' && $entry !== '..') {
							$this->rrmdir($directory . $entry);
						}
					}

					closedir($model);
				}

				if ($delete && !rmdir($directory)) {
					throw new \RuntimeException(
						"Could not remove directory '{$directory}'."
					);
				}
			}
		}

		$create_list = [
			'app',
			'app_component',
			'app_config',
			'app_dto',
			'app_filter',
			'app_layout',
			'app_model',
			'app_routes',
			'app_service',
			'app_task',
			'app_utils',
			'ofw',
			'ofw_cache',
			'ofw_export',
			'ofw_tmp'
		];

		// Create framework folders again
		foreach ($create_list as $value) {
			$directory = $this->getConfig()->getDir($value);

			if (
				!is_dir($directory) &&
				!mkdir($directory, 0755, true)
			) {
				throw new \RuntimeException(
					"Could not create directory '{$directory}'."
				);
			}
		}

		// Generate default Config.json
		$default_config_json = "{\n";
		$default_config_json .= "	\"name\": \"Osumi Framework\"\n";
		$default_config_json .= "}";
		$config_file = $this->getConfig()->getDir('app_config') . 'Config.json';
		file_put_contents($config_file, $default_config_json);

		// Generate default layout
		$default_layout = "<" . "?php declare(strict_types=1);\n\n";
		$default_layout .= "namespace Osumi\OsumiFramework\App\Layout;\n\n";
		$default_layout .= "use Osumi\OsumiFramework\Core\OComponent;\n\n";
		$default_layout .= "class DefaultLayoutComponent extends OComponent {\n";
		$default_layout .= "	public string $" . "title = '';\n";
		$default_layout .= "  public string $" . "body = '';\n";
		$default_layout .= "}\n";
		$layout_file = $this->getConfig()->getDir('app_layout') . 'DefaultLayoutComponent.php';
		file_put_contents($layout_file, $default_layout);

		$default_layout = "<!DOCTYPE html>\n";
		$default_layout .= "<html>\n";
		$default_layout .= "	<head>\n";
		$default_layout .= "		<meta charset=\"utf-8\">\n";
		$default_layout .= "		<meta name=\"viewport\" content=\"width=device-width\">\n";
		$default_layout .= "		<meta name=\"description\" content=\"\">\n";
		$default_layout .= "		<title>{{title}}</title>\n";
		$default_layout .= "		<link type=\"image/x-icon\" href=\"/favicon.png\" rel=\"icon\">\n";
		$default_layout .= "		<link type=\"image/x-icon\" href=\"/favicon.png\" rel=\"shortcut icon\">\n";
		$default_layout .= "	</head>\n";
		$default_layout .= "	<body>\n";
		$default_layout .= "		{{body}}\n";
		$default_layout .= "	</body>\n";
		$default_layout .= "</html>";
		$layout_file = $this->getConfig()->getDir('app_layout') . 'DefaultLayoutTemplate.php';
		file_put_contents($layout_file, $default_layout);

		// Generate default .htaccess
		$default_htaccess = "Options +FollowSymLinks\n\n";
		$default_htaccess .= "<IfModule mod_rewrite.c>\n";
		$default_htaccess .= "	RewriteEngine On\n";
		$default_htaccess .= "	RewriteBase /\n\n";
		$default_htaccess .= "	RewriteCond %{HTTP:Authorization} ^(.*)\n";
		$default_htaccess .= "	RewriteRule .* - [e=HTTP_AUTHORIZATION:%1]\n\n";
		$default_htaccess .= "	RewriteCond %{REQUEST_FILENAME} !-f\n";
		$default_htaccess .= "	RewriteRule ^(.*)$ index.php [QSA,L]\n";
		$default_htaccess .= "</IfModule>\n";

		$htaccess_file = $this->getConfig()->getDir('public') . '.htaccess';

		if (
			file_put_contents(
				$htaccess_file,
				$default_htaccess,
				LOCK_EX
			) === false
		) {
			throw new \RuntimeException(
				"Could not create default .htaccess file '{$htaccess_file}'."
			);
		}

		// Generate default index file
		$default_index = "<" . "?php\n\n";
		$default_index .= "require_once __DIR__ . '/../vendor/autoload.php';\n\n";
		$default_index .= "use Osumi\OsumiFramework\Core\OCore;\n\n";
		$default_index .= "$" . "core = new OCore();\n";
		$default_index .= "$" . "core->load();\n\n";
		$default_index .= "set_exception_handler([$" . "core, 'errorHandler']);\n\n";
		$default_index .= "$" . "core->run();\n";
		$index_file = $this->getConfig()->getDir('public') . 'index.php';
		file_put_contents($index_file, $default_index);
	}

	/**
	 * Ensure a directory selected for deletion belongs to the current project.
	 *
	 * The project root itself cannot be used as a deletion target.
	 *
	 * @param string $path Directory path to validate.
	 *
	 * @return void
	 *
	 * @throws \RuntimeException If the project root or target path cannot be
	 *                           resolved or the target is outside the project.
	 */
	private function assertSafeDeletePath(string $path): void {
		$base_path = realpath($this->getConfig()->getDir('base'));
		$target_path = realpath($path);

		if ($base_path === false) {
			throw new \RuntimeException(
				'Could not resolve the project base directory.'
			);
		}

		if ($target_path === false) {
			throw new \RuntimeException(
				"Could not resolve deletion target '{$path}'."
			);
		}

		$base_path = rtrim(
			str_replace('\\', '/', $base_path),
			'/'
		);

		$target_path = rtrim(
			str_replace('\\', '/', $target_path),
			'/'
		);

		if (PHP_OS_FAMILY === 'Windows') {
			$base_path = strtolower($base_path);
			$target_path = strtolower($target_path);
		}

		if (
			$target_path === $base_path ||
			!str_starts_with($target_path . '/', $base_path . '/')
		) {
			throw new \RuntimeException(
				"Refusing to delete unsafe path '{$path}'."
			);
		}
	}

	/**
	 * Run the reset task.
	 *
	 * @param array<string, string|false> $options Reset task options. The "key"
	 *                                             option contains the reset token.
	 *
	 * @return void
	 *
	 * @throws \JsonException If reset data cannot be encoded.
	 * @throws \Random\RandomException If a secure reset key cannot be generated.
	 * @throws \RuntimeException If a required directory or file operation fails.
	 */
	public function run(array $options = []): void {
		$tmp_file = $this->getConfig()->getDir('ofw_tmp') . 'reset.json';

		$reset_key = '';
		$reset_date = 0;

		if (file_exists($tmp_file)) {
			$reset_content = file_get_contents($tmp_file);

			if ($reset_content !== false) {
				try {
					$reset_data = json_decode(
						$reset_content,
						true,
						512,
						JSON_THROW_ON_ERROR
					);

					if (
						is_array($reset_data) &&
						array_key_exists('key', $reset_data) &&
						is_string($reset_data['key']) &&
						array_key_exists('date', $reset_data) &&
						is_int($reset_data['date'])
					) {
						$reset_key = $reset_data['key'];
						$reset_date = $reset_data['date'];
					}
				} catch (\JsonException) {
					// Invalid reset data is ignored.
				}
			}

			if (!unlink($tmp_file)) {
				throw new \RuntimeException(
					"Unable to remove reset data file '{$tmp_file}'."
				);
			}
		}

		if (count($options) === 0) {
			echo "\n  "
				. $this->getColors()->getColoredString(
					OTools::getMessage('TASK_RESET_WARNING'),
					'red'
				)
				. "\n\n";

			echo "  "
				. OTools::getMessage('TASK_RESET_CONTINUE')
				. "\n\n";

			echo "  "
				. OTools::getMessage('TASK_RESET_TIME_TO_CANCEL')
				. "\n\n";

			$this->countDown();

			$data = [
				'key'  => bin2hex(random_bytes(16)),
				'date' => time() + (60 * 15)
			];

			OTools::checkOfw('tmp');

			$reset_content = json_encode(
				$data,
				JSON_UNESCAPED_UNICODE
					| JSON_UNESCAPED_SLASHES
					| JSON_THROW_ON_ERROR
			);

			if (
				file_put_contents(
					$tmp_file,
					$reset_content,
					LOCK_EX
				) === false
			) {
				throw new \RuntimeException(
					"Unable to write reset data file '{$tmp_file}'."
				);
			}

			if (
				PHP_OS_FAMILY !== 'Windows' &&
				!chmod($tmp_file, 0600)
			) {
				unlink($tmp_file);

				throw new \RuntimeException(
					"Unable to secure reset data file '{$tmp_file}'."
				);
			}

			echo "\n  "
				. OTools::getMessage(
					'TASK_RESET_RESET_KEY_CREATED'
				)
				. "\n\n";

			echo "    php of reset --key "
				. $data['key']
				. "\n\n";

			return;
		}

		if (
			array_key_exists('key', $options) &&
			is_string($options['key']) &&
			$reset_key !== '' &&
			$reset_date > time() &&
			hash_equals(
				$reset_key,
				$options['key']
			)
		) {
			$this->cleanData();

			echo "\n  "
				. OTools::getMessage(
					'TASK_RESET_DATA_ERASED'
				)
				. "\n\n";

			return;
		}

		echo "\n  "
			. $this->getColors()->getColoredString(
				OTools::getMessage('TASK_RESET_ERROR'),
				'red'
			)
			. "\n\n";

		echo "  "
			. OTools::getMessage(
				'TASK_RESET_GET_NEW_KEY'
			)
			. "\n\n";

		echo "    php of reset\n\n";
	}
}
