<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\Tools;

use \ReflectionClass;
use Osumi\OsumiFramework\Tools\OTools;
use Osumi\OsumiFramework\Routing\OModule;
use Osumi\OsumiFramework\Routing\OModuleAction;

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
					array_push($ret, new $table());
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
	 * Creates or updates cache file of flattened URLs based on user configured module routes. Also calls to generate new modules/actions/templates that are new.
	 *
	 * @param bool $silent If set to true echoes messages about the update process
	 *
	 * @return ?string Information about the update if silent is false
	 */
	public static function updateUrls(bool $silent=false): ?string {
		global $core;
		$urls = self::getModuleUrls();
		$urls_cache_file = $core->cacheContainer->getItem('urls');
		$urls_cache_file->set(json_encode($urls, JSON_UNESCAPED_UNICODE));
		$urls_cache_file->save();

		return self::updateControllers($silent);
	}

  /**
	 * Get the attribute class from a module or an action.
	 *
	 * @param $class Class from which information will be taken
	 *
	 * @return OModule | OModuleAction Attribute class obtained from the class
	 */
	public static function getClassAttributes($class): OModule | OModuleAction {
		$reflector = new ReflectionClass($class::class);
		foreach ($reflector->getAttributes() as $attr) {
			$attributes = $attr->newInstance();
		}
		return $attributes;
	}

  /**
	 * Get module method's phpDoc information
	 *
	 * @param string $inspectclass Module name
	 *
	 * @return array List of items with module name, method name and associated phpDoc information
	 */
	public static function getDocumentation(string $inspectclass): array {
		global $core;
		$module_path = $core->config->getDir('app_module').$inspectclass.'/'.$inspectclass.'.php';
		$module_name = "Osumi\\OsumiFramework\\App\\Module\\".$inspectclass."\\".$inspectclass;
		$module = new $module_name;
		$module_attributes = self::getClassAttributes($module);

		$class_params = [
			'module'  => $inspectclass,
			'type'    => !is_null($module_attributes->getType()) ? $module_attributes->getType() : 'html',
			'prefix'  => !is_null($module_attributes->getPrefix()) ? $module_attributes->getPrefix() : null
		];
		$actions = $module_attributes->getActions();

		$arr = [];
		foreach($actions as $action_name) {
			$action_path = $core->config->getDir('app_module').$inspectclass.'/Actions/'.$action_name.'/'.$action_name.'Action.php';
			$action_class_name = "Osumi\\OsumiFramework\\App\\Module\\".$inspectclass."\\Actions\\".$action_name."\\".$action_name."Action";
			$action = new $action_class_name;
			$action_attributes = self::getClassAttributes($action);

			$action_params = [
				'module'  => $class_params['module'],
				'action'  => $action_name,
				'type'    => (!is_null($action_attributes->getType())) ? $action_attributes->getType() : $class_params['type'],
				'prefix'  => $class_params['prefix'],
				'filters' => $action_attributes->getFilters(),
				'url'     => $action_attributes->getUrl(),
				'layout'  => $action_attributes->getLayout(),
				'utils'   => $action_attributes->getUtils()
			];
			array_push($arr, $action_params);
		}
		return $arr;
	}

  /**
	 * Get information from all the modules and actions to build the url cache file
	 *
	 * @return array List of every action with it's information: module, action, type, url, prefix and filter
	 */
	public static function getModuleUrls(): array {
		global $core;
		$modules = [];
		if (is_dir($core->config->getDir('app_module'))) {
			if ($model = opendir($core->config->getDir('app_module'))) {
				while (false !== ($entry = readdir($model))) {
					if ($entry != '.' && $entry != '..') {
						array_push($modules, $entry);
					}
				}
				closedir($model);
			}
		}

		$list = [];
		foreach ($modules as $module) {
			$actions = self::getDocumentation($module);
			foreach ($actions as $action) {
				if (!is_null($action['prefix'])) {
					$action['url'] = $action['prefix'].$action['url'];
				}
				unset($action['prefix']);
				array_push($list, $action);
			}
		}
		return $list;
	}

  /**
	 * Creates a new empty module with the given name
	 *
	 * @param string $name Name of the new module
	 *
	 * @return array Status of the operation (status, module name and module path)
	 */
	public static function addModule(string $name): array {
		global $core;

		$module_path    = $core->config->getDir('app_module').ucfirst($name)."Module";
		$module_actions = $module_path.'/Actions';
		$module_file    = $module_path.'/'.ucfirst($name).'Module.php';

		if (file_exists($module_path) || file_exists($module_file)) {
			return ['status' => 'exists', 'name' => $name];
		}
		mkdir($module_path);
		mkdir($module_actions);

		$template_path = $core->config->getDir('ofw_template').'add/moduleTemplate.php';
		$str_module = OTools::getTemplate($template_path, '', [
			'uc_name' => ucfirst($name),
			'name' => $name
		]);

		file_put_contents($module_file, $str_module);

		return ['status' => 'ok', 'name' => $name, 'path' => $module_file];
	}

  /**
	 * Creates a new empty action with the given name, URL and type into the given module
	 *
	 * @param string $module Name of the module where the action should go
	 *
	 * @param string $action Name of the new action
	 *
	 * @param string $url URL of the new action
	 *
	 * @param string $type Type of the return the new action will make
	 *
	 * @param string $layout Layout of the new action
	 *
	 * @param string $utils "utils" folder's classes to be loaded into the method (comma separated values)
	 *
	 * @return array Status of the operation (status, module name, action name, action url and action type)
	 */
	public static function addAction(string $module, string $action, string $url, string $type=null, string $layout=null, string $utils=null): array {
		global $core;

		$module_path    = $core->config->getDir('app_module').ucfirst($module).'Module';
		$module_actions = $module_path.'/Actions';
		$module_file    = $module_path.'/'.ucfirst($module).'Module.php';
		$status         = [
			'status'   => 'ok',
			'module'   => $module,
			'action'   => $action,
			'url'      => $url,
			'type'     => $type,
			'layout'   => $layout,
			'utils'    => $utils,
			'template' => ''
		];

		if (!file_exists($module_path) || !file_exists($module_file)) {
			$status['status'] = 'no-module';
			return $status;
		}
		$str_module = file_get_contents($module_file);
		if (preg_match("/^\s+actions: \[(.*?)".$action."(.*?)\],?$/", $str_module) == 1) {
			$status['status'] = 'action-exists';
			return $status;
		}

		$module_type = false;
		require_once $module_file;

		$module_name = "\\Osumi\\OsumiFramework\\App\\Module\\".ucfirst($module)."Module\\".ucfirst($module)."Module";
		$module_class = new $module_name;
		$module_attributes = self::getClassAttributes($module_class);

		$class_params = [
			'module' => $module,
			'action' => null,
			'type'   => $type,
			'prefix' => null,
			'filter' => null,
			'layout' => null,
			'utils'  => null
		];
		if (!is_null($module_attributes->getPrefix())) {
			if (stripos($url, $module_attributes->getPrefix())!==false) {
				$url = str_ireplace($module_attributes->getPrefix(), '', $url);
			}
		}
		if (is_null($type) && !is_null($module_attributes->getType())) {
			$type = $class_params['type'];
			$module_type = true;
		}
		if (is_null($type)) {
			$type = 'html';
		}
		$status['type'] = $type;
		if (is_null($layout)) {
			$layout = 'default';
		}
		$status['layout'] = $layout;
		$status['utils']  = $utils;

		$action_folder = $module_actions.'/'.ucfirst($action);
		if (file_exists($action_folder)) {
			$status['status'] = 'action-exists';
			return $status;
		}
		$action_file   = $action_folder.'/'.ucfirst($action).'Action.php';
		if (file_exists($action_file)) {
			$status['status'] = 'action-exists';
			return $status;
		}
		$action_template  = $action_folder.'/'.ucfirst($action).'Action.'.$type;
		$status['template'] = $action_template;
		if (file_exists($action_template)) {
			$status['status'] = 'template-exists';
			return $status;
		}

		// Add action to module
		if (stripos($str_module, "actions: []") !== false) {
			$str_module = preg_replace("/actions: \[\]/i", "actions: ['".ucfirst($action)."']", $str_module);
		}
		else {
			preg_match("/actions: \[(.*?)\]/m", $str_module, $match);
			$actions = explode(',', $match[1]);
			for ($i = 0; $i < count($actions); $i++) {
				$actions[$i] = trim($actions[$i]);
			}
			array_push($actions, "'".ucfirst($action)."'");
			$str_module = preg_replace("/actions: \[(.*?)\]/i", "actions: [".implode(', ', $actions)."]", $str_module);
		}

		// Create action's folder
		mkdir($action_folder);

		// New action's content
		$str_template = OTools::getMessage('TASK_ADD_ACTION_TEMPLATE', [$action]);
		$template_path = $core->config->getDir('ofw_template').'add/actionTemplate.php';
		$str_action = OTools::getTemplate($template_path, '', [
			'uc_module' => ucfirst($module),
			'uc_action' => ucfirst($action),
			'url' => $url,
			'type' => (!$module_type) ? ",\n	type: '".$type."'" : '',
			'layout' => (!is_null($layout) && $layout != 'default') ? ",\n	layout: '".$layout."'" : '',
			'utils' => (!is_null($utils)) ? ",\n	utils: ['".$utils."']" : '',
			'str_template' => $str_template
		]);

		file_put_contents($module_file,     $str_module);
		file_put_contents($action_file,     $str_action);
		file_put_contents($action_template, $str_template);

		return $status;
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
		if (!is_dir($core->config->getDir('app_task'))) {
			mkdir($core->config->getDir('app_task'));
		}

		$task_file = $core->config->getDir('app_task').ucfirst($name).'Task.php';
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
			'uc_name' => ucfirst($name),
			'name' => $name,
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
			if (in_array($field->getType(), $text_fields) || in_array($field->getType(), $date_fields)) {
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
				$str_fields .= "<"."?php echo is_null($"."values['".$values['model_name']."']->get('".$field->getName()."')) ? 'null' : urlencode($"."values['".$values['model_name']."']->get('".$field->getName()."')) ?>";
			}
			elseif (!$field->getNullable() && in_array($field->getType(), $urlencode_fields)) {
				$str_fields .= "<"."?php echo urlencode($"."values['".$values['model_name']."']->get('".$field->getName()."')) ?>";
			}

			if (in_array($field->getType(), $text_fields) || in_array($field->getType(), $date_fields)) {
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

  /**
	 * Update the controllers based on cached-flattened urls.json file. Creates the modules/controllers/templates that are configured but are not found.
	 *
	 * @param bool $silent If true doesn't give an output and performs the actions silently
	 *
	 * @return ?string Result of performed actions or null if $silent parameter is true
	 */
	public static function updateControllers(bool $silent=false): ?string {
		global $core;
		$ret = null;
		$urls_cache_file = $core->cacheContainer->getItem('urls');
		$urls   = json_decode($urls_cache_file->get(), true);
		$errors = false;
		$all_updated = true;

		if (!$silent) {
			$colors = new OColors();
			$ret = "";
		}

		$reserved_modules = ['private', 'protected', 'public'];
		foreach ($urls as $url) {
			if (in_array($url['module'], $reserved_modules)) {
				if (!$silent) {
					$ret .= $colors->getColoredString('ERROR', 'white', 'red').": ".OTools::getMessage('TASK_UPDATE_URLS_RESERVED')."\n";
					foreach ($reserved_modules as $module) {
						$ret .= "  · ".$module."\n";
					}
					$errors = true;
				}
				continue;
			}

			if ($url['action']==$url['module']) {
				if (!$silent) {
					$ret .= $colors->getColoredString('ERROR', 'white', 'red').": ".OTools::getMessage('TASK_UPDATE_URLS_ACTION_MODULE')."\n";
					$ret .= "  ".self::getMessage('TASK_UPDATE_URLS_MODULE').": ".$url['module']."\n";
					$ret .= "  ".self::getMessage('TASK_UPDATE_URLS_ACTION').": ".$url['action']."\n";
					$errors = true;
				}
				continue;
			}

			$module_name = lcfirst(preg_replace('/Module$/', '', $url['module']));
			$module_result = self::addModule($module_name);

			if ($module_result['status'] == 'ok') {
				$all_updated = false;
				if (!$silent) {
					$ret .= "    ".OTools::getMessage('TASK_UPDATE_URLS_NEW_MODULE', [
						$colors->getColoredString($url['module'], 'light_green'),
						$colors->getColoredString($module_result['path'], 'light_green')
					])."\n";
				}

			}

			$action_result = self::addAction($url['module'], $url['action'], $url['url'], $url['type'], $url['layout']);
			if ($action_result['status'] == 'ok') {
				$all_updated = false;
				if (!$silent) {
					$ret .= "    ".OTools::getMessage('TASK_UPDATE_URLS_NEW_ACTION', [
						$colors->getColoredString($url['action'], 'light_green'),
						$colors->getColoredString($url['module'], 'light_green')
					])."\n";
					$ret .= "    ".OTools::getMessage('TASK_UPDATE_URLS_NEW_TEMPLATE', [
							$colors->getColoredString($action_result['template'], 'light_green')
						])."\n";
				}
			}
		}

		if ($errors && !$silent) {
			$ret .= "\n";
			$ret .= $colors->getColoredString('----------------------------------------------------------------------------------------------------------------------', 'white', 'red')."\n";
			$ret .= $colors->getColoredString(OTools::getMessage('TASK_UPDATE_URLS_ERROR'), 'white', 'red')."\n";
			$ret .= $colors->getColoredString('----------------------------------------------------------------------------------------------------------------------', 'white', 'red')."\n";
		}
		if (!$silent && $all_updated) {
			$ret .= "\n  ".OTools::getMessage('TASK_UPDATE_URLS_ALL_UPDATED');
		}

		return $ret;
	}
}
