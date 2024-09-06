<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\Task;

use Osumi\OsumiFramework\Core\OTask;
use Osumi\OsumiFramework\Tools\OTools;

/**
 * Cleans all non framework data, to be used on new installations
 */
class resetTask extends OTask {
	public function __toString() {
		return $this->getColors()->getColoredString('reset', 'light_green').': '.OTools::getMessage('TASK_RESET');
	}

	private function rrmdir(string $dir): bool {
		if (is_dir($dir)) {
			$files = array_diff(scandir($dir), array('.','..'));
			foreach ($files as $file) {
				if (is_dir($dir.'/'.$file)) {
					$this->rrmdir($dir.'/'.$file);
				}
				else {
					unlink($dir.'/'.$file);
				}
			}
			return rmdir($dir);
		}
		else {
			return unlink($dir);
		}
	}

	private function countDown(): void {
		for ($i=10; $i>=0; $i--) {
			echo "  ";
			if ($i<4) {
				echo $this->getColors()->getColoredString(strval($i), 'red');
			}
			else {
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
			if (is_dir($this->config->getDir($value))) {
				if ($model = opendir($this->config->getDir($value))) {
					while (false !== ($entry = readdir($model))) {
						if ($entry != '.' && $entry != '..') {
							$this->rrmdir($this->config->getDir($value).$entry);
						}
					}
					closedir($model);
				}
				if ($delete) {
					rmdir($this->config->getDir($value));
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
			'app_module',
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
			mkdir($this->config->getDir($value));
		}

		// Generate default config.json
		$default_config_json = "{\n";
		$default_config_json .= "	\"name\": \"Osumi Framework\"\n";
		$default_config_json .= "}";
		$config_file = $this->config->getDir('app_config').'Config.json';
		file_put_contents($config_file, $default_config_json);

		// Generate default layout
		$default_layout = "<!DOCTYPE html>\n";
		$default_layout .= "<html>\n";
		$default_layout .= "	<head>\n";
		$default_layout .= "		<meta charset=\"utf-8\">\n";
		$default_layout .= "		<meta name=\"viewport\" content=\"width=device-width\">\n";
		$default_layout .= "		<meta name=\"description\" content=\"\">\n";
		$default_layout .= "		<title>{{title}}</title>\n";
		$default_layout .= "		<link type=\"image/x-icon\" href=\"/favicon.png\" rel=\"icon\">\n";
		$default_layout .= "		<link type=\"image/x-icon\" href=\"/favicon.png\" rel=\"shortcut icon\">\n";
		$default_layout .= "		{{css}}\n";
		$default_layout .= "		{{js}}\n";
		$default_layout .= "	</head>\n";
		$default_layout .= "	<body>\n";
		$default_layout .= "		{{body}}\n";
		$default_layout .= "	</body>\n";
		$default_layout .= "</html>";
		$layout_file = $this->config->getDir('app_layout').'DefaultLayout.php';
		file_put_contents($layout_file, $default_layout);

		// Generate default .htaccess
		$default_htaccess = "Options +FollowSymLinks +ExecCGI\n\n";
		$default_htaccess .= "<IfModule mod_rewrite.c>\n";
		$default_htaccess .= "	RewriteEngine On\n";
		$default_htaccess .= "	RewriteBase /\n\n";
		$default_htaccess .= "	RewriteCond %{HTTP:Authorization} ^(.*)\n";
		$default_htaccess .= "	RewriteRule .* - [e=HTTP_AUTHORIZATION:%1]\n\n";
		$default_htaccess .= "	RewriteCond %{HTTP_HOST} ^www\.(.*)$ [NC]\n";
		$default_htaccess .= "	RewriteRule ^(.*)$ http://%1/$1 [R=301,L]\n\n";
		$default_htaccess .= "	RewriteCond %{REQUEST_FILENAME} !-f\n";
		$default_htaccess .= "	RewriteRule ^(.*)$ index.php [QSA,L]\n";
		$default_htaccess .= "</IfModule>\n";
		$htaccess_file = $this->config->getDir('public').'.htaccess';
		file_put_contents($htaccess_file, $default_htaccess);

		// Generate default index file
		$default_index = "<"."?php\n\n";
		$default_index .= "require_once __DIR__ . '/../vendor/autoload.php';\n\n";
		$default_index .= "use Osumi\OsumiFramework\Core\OCore;\n\n";
		$default_index .= "$"."core = new OCore();\n";
		$default_index .= "$"."core->load();\n\n";
		$default_index .= "set_exception_handler([$"."core, 'errorHandler']);\n\n";
		$default_index .= "$"."core->run();\n";
		$index_file = $this->config->getDir('public').'index.php';
		file_put_contents($index_file, $default_index);
	}

	/**
	 * Run the task
	 *
	 * @return void
	 */
	public function run(array $options = []): void {
		$cache_file = $this->config->getDir('ofw_cache').'reset.json';
		$reset_key = '';
		$reset_date = 0;

		if (file_exists($cache_file)) {
			$reset_data = json_decode(file_get_contents($cache_file), true);
			if (!is_null($reset_data)) {
				$reset_key  = $reset_data['key'];
				$reset_date = $reset_data['date'];
			}
			unlink($cache_file);
		}

		if (count($options) == 0) {
			echo "\n  ".$this->getColors()->getColoredString(OTools::getMessage('TASK_RESET_WARNING'), 'red')."\n\n";
			echo "  ".OTools::getMessage('TASK_RESET_CONTINUE')."\n\n";
			echo "  ".OTools::getMessage('TASK_RESET_TIME_TO_CANCEL')."\n\n";

			$this->countDown();

			$data = [
				'key' => substr(hash('sha512', strval(time())), 0, 12),
				'date' => time() + (60 * 15)
			];
			OTools::checkOfw('cache');
			file_put_contents($cache_file, json_encode($data));

			echo "\n  ".OTools::getMessage('TASK_RESET_RESET_KEY_CREATED')."\n\n";
			echo "    php of reset ".$data['key']."\n\n";
		}
		else {
			if ($options[0] === 'silent') {
				$this->cleanData();
			}
			else {
				if (
					$options[0] === $reset_key &&
					$reset_date > time()
				) {
					$this->cleanData();
					echo "\n  ".OTools::getMessage('TASK_RESET_DATA_ERASED')."\n\n";
				}
				else {
					echo "\n  ".$this->getColors()->getColoredString(OTools::getMessage('TASK_RESET_ERROR'), 'red')."\n\n";
					echo "  ".OTools::getMessage('TASK_RESET_GET_NEW_KEY')."\n\n";
					echo "    php of reset\n\n";
				}
			}
		}
	}
}
