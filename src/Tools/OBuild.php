<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\Tools;

use \ReflectionClass;
use Osumi\OsumiFramework\Tools\OTools;

/**
 * OBuild - Utility class with tools to build framework components
 */
class OBuild {
  /**
	 * Returns an array of model objects (one object per model)
	 *
	 * @return array Array of model objects
	 */
	public static function getModelList(): array {
		global $core;
		$ret = [];

		if ($model = opendir($core->config->getDir('app_model'))) {
			while (false !== ($entry = readdir($model))) {
				if ($entry != '.' && $entry != '..') {
					$table = "\\Osumi\\OsumiFramework\\App\\Model\\".str_ireplace('.php','',$entry);
					$ret[] = new $table();
				}
			}
			closedir($model);
		}

		sort($ret);
		return $ret;
	}

  /**
	 * Generates a SQL file to build the database based on models defined by the user
	 *
	 * @return string SQL string to build all the tables in the database (also written to ofw/export/model.sql)
	 */
	public static function generateModel(): string  {
		global $core;
		$sql = "/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;\n\n";
		$models = self::getModelList();

		foreach ($models as $model) {
			if (method_exists($model, 'generate')) {
				$sql .= $model->generate() . "\n\n";
			}
		}
		foreach ($models as $model) {
			if (method_exists($model, 'generateRefs')) {
				$refs = $model->generateRefs();
				if ($refs!=''){
					$sql .= $refs . "\n\n";
				}
			}
		}

		$sql .= "/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;\n";

		OTools::checkOfw('export');
		$sql_file = $core->config->getDir('ofw_export').'model.sql';
		if (file_exists($sql_file)) {
			unlink($sql_file);
		}

		file_put_contents($sql_file, $sql);

		return $sql;
	}

	/**
	 * Updates or creates a route file.
	 *
	 * @param string $file_path Path of the route file.
	 *
	 * @param string $new_route New route to be added.
	 *
	 * @param string $new_use_statement New "use" statement for the action.
	 *
	 * @return void
	 */
	public static function updateRoutesFile(string $file_path, string $new_route, string $new_use_statement): void {
		global $core;

    // Read the current file contents
		if (file_exists($file_path)) {
    	$contents = file_get_contents($file_path);
		}
		else {
			$template_path = $core->config->getDir('ofw_template').'add/urlsTemplate.php';
			$contents = file_get_contents($template_path);
    }

    // Split the file into lines
    $lines = explode("\n", $contents);

    // Find the last 'use' statement
    $last_use_index = -1;
    $use_statement_exists = false;
    foreach ($lines as $index => $line) {
        if (strpos($line, 'use ') === 0) {
            $last_use_index = $index;
            // Check if the use statement already exists
            if (trim($line) === trim($new_use_statement)) {
                $use_statement_exists = true;
            }
        }
    }

    // Add the new 'use' statement after the last existing one
    if (!$use_statement_exists && $last_use_index !== -1) {
        array_splice($lines, $last_use_index + 1, 0, $new_use_statement);
    }

    // Find the last line with content (ignoring empty lines at the end)
    $last_content_line = count($lines) - 1;
    while ($last_content_line >= 0 && trim($lines[$last_content_line]) === '') {
        $last_content_line--;
    }

    // Add the new route
    if ($last_content_line >= 0) {
        // Add a blank line if the last line isn't already blank
        if (trim($lines[$last_content_line]) !== '') {
            $last_content_line++;
            $lines[$last_content_line] = '';
        }
        $last_content_line++;
        $lines[$last_content_line] = $new_route;
    }

    // Combine lines back into a single string
    $new_contents = implode("\n", $lines)."\n";

    // Write the updated contents back to the file
    file_put_contents($file_path, $new_contents);
	}

  /**
	 * Creates a new empty action with the given name, URL and type
	 *
	 * @param array $values New actions configuration options
	 *
	 * @return string Status of the operation
	 */
	public static function addAction(array $values): string {
		global $core;

		if (file_exists($values['action_file'])) {
			return 'action-exists';
		}
		if (file_exists($values['action_folder'])) {
			return 'action-exists';
		}
		if (file_exists($values['action_template'])) {
			return 'template-exists';
		}

		// Create action's folder
		mkdir($values['action_folder'], 0777, true);

		// New action's content
		$str_template = OTools::getMessage('TASK_ADD_ACTION_TEMPLATE', [$values['action_name']]);
		$template_path = $core->config->getDir('ofw_template').'add/actionTemplate.php';
		$folders = str_ireplace('/', '\\', $values['folders']);
		$str_action = OTools::getTemplate($template_path, '', [
			'folders' => $folders,
			'action' => $values['action_name'],
			'str_template' => $str_template
		]);

		file_put_contents($values['action_file'],     $str_action);
		file_put_contents($values['action_template'], $str_template);

		// Update URLs file
		$urls_path = $core->config->getDir('app_routes').'Web.php';

		$new_url = "ORoute::get('".$values['action_url']."', ".$values['action_name']."Action::class, [], '".$values['action_type']."');";
		$use_url = "use Osumi\OsumiFramework\App\\".$folders."\\".$values['action_name']."Action;";

		self::updateRoutesFile($urls_path, $new_url, $use_url);

		return 'ok';
	}

  /**
	 * Creates a new empty service with the given name
	 *
	 * @param string $name Name of the new service
	 *
	 * @return array Status of the operation (status and service name)
	 */
	public static function addService(string $name): array {
		global $core;

		// If services folder does not exist I create it before doing anything else
		if (!is_dir($core->config->getDir('app_service'))) {
			mkdir($core->config->getDir('app_service'));
		}

		$service_file = $core->config->getDir('app_service').ucfirst($name).'Service.php';

		if (file_exists($service_file)) {
			return ['status' => 'exists', 'name' => $name];
		}

		$template_path = $core->config->getDir('ofw_template').'add/serviceTemplate.php';
		$str_service = OTools::getTemplate($template_path, '', [
			'uc_name' => ucfirst($name)
		]);

		file_put_contents($service_file, $str_service);

		return ['status' => 'ok', 'name' => $name];
	}

  /**
	 * Creates a new empty task with the given name
	 *
	 * @param string $name Name of the new task
	 *
	 * @return array Status of the operation (status and task name)
	 */
	public static function addTask(string $name): array {
		global $core;

		// If tasks folder does not exist I create it before doing anything else
		$tasks_path = $core->config->getDir('app_task');
		if (!is_dir($tasks_path)) {
			mkdir($tasks_path);
		}

		$task_file = $tasks_path.ucfirst($name).'Task.php';
		$ofw_task_file = $core->config->getDir('ofw_task').ucfirst($name).'Task.php';

		if (file_exists($task_file)) {
			return ['status' => 'exists', 'name' => $name];
		}
		if (file_exists($ofw_task_file)) {
			return ['status' => 'ofw-exists', 'name' => $name];
		}

		$str_message = str_ireplace('"', '\"', OTools::getMessage('TASK_ADD_TASK_MESSAGE', [$name]));

		$template_path = $core->config->getDir('ofw_template').'add/taskTemplate.php';
		$str_task = OTools::getTemplate($template_path, '', [
			'uc_name'     => ucfirst($name),
			'name'        => $name,
			'str_message' => $str_message
		]);
		file_put_contents($task_file, $str_task);

		return ['status' => 'ok', 'name' => $name];
	}

  /**
	 * Creates a model component file and a component for lists of such model
	 *
	 * @param array $values Information about the files that have to be created
	 *
	 * @return string Status of the operation
	 */
	public static function addModelComponent(array $values): string {
		global $core;

		if (file_exists($values['list_folder'])) {
			return 'list-folder-exists';
		}
		if (file_exists($values['list_folder'].$values['list_file'])) {
			return 'list-file-exists';
		}
		if (file_exists($values['list_folder'].$values['list_template_file'])) {
			return 'list-file-exists';
		}
		if (file_exists($values['component_folder'])) {
			return 'component-folder-exists';
		}
		if (file_exists($values['component_folder'].$values['component_file'])) {
			return 'component-file-exists';
		}
		if (file_exists($values['component_folder'].$values['component_template_file'])) {
			return 'component-file-exists';
		}
		if (!mkdir($values['list_folder'], 0755, true)) {
			return 'list-folder-cant-create';
		}
		if (!mkdir($values['component_folder'], 0755, true)) {
			return 'component-folder-cant-create';
		}

		$text_fields      = [OMODEL_PK_STR, OMODEL_TEXT, OMODEL_LONGTEXT];
		$urlencode_fields = [OMODEL_TEXT, OMODEL_LONGTEXT];
		$date_fields      = [OMODEL_CREATED, OMODEL_UPDATED, OMODEL_DATE];
		$cont             = 0;

		$component_name = $values['model_name'].'Component';

		$template_path = $core->config->getDir('ofw_template').'add/modelListComponentTemplate.php';
		$list_component_content = OTools::getTemplate($template_path, '', [
			'model_name' => $values['model_name'],
			'list_name' => $values['list_name']
		]);

		$template_path = $core->config->getDir('ofw_template').'add/modelListTemplate.php';
		$list_template_content = OTools::getTemplate($template_path, '', [
			'model_name' => $values['model_name'],
			'component_name' => $component_name
		]);

		if (file_put_contents($values['list_folder'].$values['list_file'], $list_component_content)===false) {
			return 'list-file-cant-create';
		}
		if (file_put_contents($values['list_folder'].$values['list_template_file'], $list_template_content)===false) {
			return 'list-file-cant-create';
		}

		$template_path = $core->config->getDir('ofw_template').'add/modelComponentTemplate.php';
		$component_content = OTools::getTemplate($template_path, '', [
			'component_name' => $component_name,
			'model_name' => $values['model_name']
		]);

		$str_fields = '';
		foreach ($values['model'] as $field) {
			$cont++;
			$str_fields .= "	\"".OTools::underscoresToCamelCase($field->getName())."\": ";
			if (
				(in_array($field->getType(), $text_fields) || in_array($field->getType(), $date_fields)) &&
				in_array($field->getType(), $urlencode_fields) &&
				!$field->getNullable()
			) {
				$str_fields .= "\"";
			}

			if ($field->getType()===OMODEL_BOOL) {
				$str_fields .= "<"."?php echo $"."values['".$values['model_name']."']->get('".$field->getName()."') ? 'true' : 'false' ?>";
			}
			elseif ($field->getNullable() && in_array($field->getType(), $date_fields)) {
				$str_fields .= "<"."?php echo is_null($"."values['".$values['model_name']."']->get('".$field->getName()."')) ? 'null' : $"."values['".$values['model_name']."']->get('".$field->getName()."', 'd/m/Y H:i:s') ?>";
			}
			elseif (!$field->getNullable() && in_array($field->getType(), $date_fields)) {
				$str_fields .= "<"."?php echo $"."values['".$values['model_name']."']->get('".$field->getName()."', 'd/m/Y H:i:s') ?>";
			}
			elseif ($field->getNullable() && !in_array($field->getType(), $urlencode_fields)) {
				$str_fields .= "<"."?php echo is_null($"."values['".$values['model_name']."']->get('".$field->getName()."')) ? 'null' : $"."values['".$values['model_name']."']->get('".$field->getName()."') ?>";
			}
			elseif (!$field->getNullable() && !in_array($field->getType(), $urlencode_fields)) {
				$str_fields .= "<"."?php echo $"."values['".$values['model_name']."']->get('".$field->getName()."') ?>";
			}
			elseif ($field->getNullable() && in_array($field->getType(), $urlencode_fields)) {
				$str_fields .= "<"."?php echo is_null($"."values['".$values['model_name']."']->get('".$field->getName()."')) ? 'null' : '\"'.urlencode($"."values['".$values['model_name']."']->get('".$field->getName()."')).'\"' ?>";
			}
			elseif (!$field->getNullable() && in_array($field->getType(), $urlencode_fields)) {
				$str_fields .= "<"."?php echo urlencode($"."values['".$values['model_name']."']->get('".$field->getName()."')) ?>";
			}

			if (
				(in_array($field->getType(), $text_fields) || in_array($field->getType(), $date_fields)) &&
				in_array($field->getType(), $urlencode_fields) &&
				!$field->getNullable()
			) {
				$str_fields .= "\"";
			}

			if ($cont<count($values['model'])) {
				$str_fields .= ",";
			}

			$str_fields .= "\n";
		}

		$template_path = $core->config->getDir('ofw_template').'add/modelTemplate.php';
		$template_content = OTools::getTemplate($template_path, '', [
			'model_name' => $values['model_name'],
			'str_fields' => $str_fields
		]);

		if (file_put_contents($values['component_folder'].$values['component_file'], $component_content)===false) {
			return 'component-file-cant-create';
		}
		if (file_put_contents($values['component_folder'].$values['component_template_file'], $template_content)===false) {
			return 'component-file-cant-create';
		}

		return 'ok';
	}

  /**
	 * Creates a empty component file
	 *
	 * @param array $values Information about the files that have to be created
	 *
	 * @return string Status of the operation
	 */
	public static function addComponent(array $values): string {
		global $core;

		// If components folder does not exist I create it before doing anything else
		if (!is_dir($core->config->getDir('app_component'))) {
			mkdir($core->config->getDir('app_component'));
		}

		// Check if component already exists
		if (file_exists($values['component_file'])) {
			return 'exists';
		}

		// Create component's folder recursively
		mkdir($values['path'], 0777, true);

		$template_path = $core->config->getDir('ofw_template').'add/componentTemplate.php';
		$str_component = OTools::getTemplate($template_path, '', [
			'name' => $values['component_name'],
			'path' => str_ireplace("/", "\\", $values['folders'])
		]);
		file_put_contents($values['component_file'], $str_component);

		file_put_contents($values['template_file'], OTools::getMessage('TASK_ADD_COMPONENT_TEMPLATE', [$values['component_name']]));

		return 'ok';
	}

  /**
	 * Creates a empty filter file
	 *
	 * @param array $values Information about the files that have to be created
	 *
	 * @return string Status of the operation
	 */
	public static function addFilter(array $values): string {
		global $core;

		// If filters folder does not exist I create it before doing anything else
		if (!is_dir($core->config->getDir('app_filter'))) {
			mkdir($core->config->getDir('app_filter'));
		}

		// Check if component already exists
		if (file_exists($values['filter_file'])) {
			return 'exists';
		}

		$template_path = $core->config->getDir('ofw_template').'add/filterTemplate.php';
		$str_component = OTools::getTemplate($template_path, '', [
			'name' => $values['filter_name'],
			'description' => OTools::getMessage('TASK_ADD_FILTER_TEMPLATE', [$values['filter_name']])
		]);
		file_put_contents($values['filter_file'], $str_component);

		return 'ok';
	}
}
