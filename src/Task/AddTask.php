<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\Task;

use Osumi\OsumiFramework\Core\OTask;
use Osumi\OsumiFramework\Tools\OTools;
use Osumi\OsumiFramework\Tools\OBuild;
use Osumi\OsumiFramework\DB\OModel;

/**
 * Add new actions, services, tasks, model components, components or filters
 */
class AddTask extends OTask {
	public function __toString() {
		return $this->getColors()->getColoredString('add', 'light_green').': '.OTools::getMessage('TASK_ADD');
	}

	/**
	 * Creates a new action with the given parameters
	 *
	 * @param array Array with the action "action", name of the new action, URL of the action and optionally action type
	 *
	 * @return void
	 */
	private function createAction(array $params): void {
		$path = $this->getConfig()->getDir('ofw_template').'add/createAction.php';
		$values = [
			'colors'          => $this->getColors(),
			'folders'         => '',
			'action_folder'   => '',
			'action_name'     => '',
			'action_url'      => '',
			'action_type'     => '',
			'action_file'     => '',
			'action_template' => '',
			'error'           => 0
		];

		if (count($params) < 3) {
			$values['error'] = 1;
			echo OTools::getPartial($path, $values);
			exit;
		}

		$action_name_parts = explode('/', $params['name']);
		for ($i = 0; $i < count($action_name_parts); $i++) {
			$action_name_parts[$i] = ucfirst($action_name_parts[$i]);
		}
		$values['folders'] = implode('/', $action_name_parts);
		$values['action_folder']   = $this->getConfig()->getDir('app').$values['folders'].'/';
		$values['action_name']     = $action_name_parts[count($action_name_parts) -1];
		$values['action_url']      = $params['url'];
		$values['action_type']     = isset($params['type']) ? $params['type'] : 'html';
		$values['action_file']     = $values['action_folder'].$values['action_name'].'Component.php';
		$values['action_template'] = $values['action_folder'].$values['action_name'].'Template.'.$values['action_type'];

		$add = OBuild::addAction($values);

		if ($add === 'action-exists') {
			$values['error'] = 2;
			echo OTools::getPartial($path, $values);
			exit;
		}
		if ($add === 'template-exists') {
			$values['error'] = 3;
			echo OTools::getPartial($path, $values);
			exit;
		}

		echo OTools::getPartial($path, $values);
		exit;
	}

	/**
	 * Creates a new service with the given parameters
	 *
	 * @param array Array with the action "service" and the name of the new service
	 *
	 * @return void
	 */
	private function createService(array $params): void {
		$path = $this->getConfig()->getDir('ofw_template').'add/createService.php';
		$values = [
			'colors'       => $this->getColors(),
			'service_name' => '',
			'service_file' => '',
			'error'        => 0
		];

		if (count($params) < 2) {
			$values['error'] = 1;
			echo OTools::getPartial($path, $values);
			exit;
		}

		$values['service_name'] = $params['name'];
		$values['service_file'] = $this->getConfig()->getDir('app_service').ucfirst($values['service_name']).'Service.php';

		$add = OBuild::addService($values['service_name']);

		if ($add['status'] === 'exists') {
			$values['error'] = 2;
			echo OTools::getPartial($path, $values);
			exit;
		}

		echo OTools::getPartial($path, $values);
		exit;
	}

	/**
	 * Creates a new task with the given parameters
	 *
	 * @param array Array with the action "task" and the name of the new task
	 *
	 * @return void
	 */
	private function createTask(array $params): void {
		$path = $this->getConfig()->getDir('ofw_template').'add/createTask.php';
		$values = [
			'colors'    => $this->getColors(),
			'task_name' => '',
			'task_file' => '',
			'error'     => 0
		];

		if (count($params) < 2) {
			$values['error'] = 1;
			echo OTools::getPartial($path, $values);
			exit;
		}

		$values['task_name'] = $params['name'];
		$values['task_file'] = $this->getConfig()->getDir('app_task').ucfirst($values['task_name']).'Task.php';

		$add = OBuild::addTask($values['task_name']);

		if ($add['status'] === 'exists') {
			$values['error'] = 2;
			echo OTools::getPartial($path, $values);
			exit;
		}
		if ($add['status'] === 'ofw-exists') {
			$values['error'] = 2;
			$values['task_file'] = $this->getConfig()->getDir('ofw_task').ucfirst($values['task_name']).'Task.php';
			echo OTools::getPartial($path, $values);
			exit;
		}

		echo OTools::getPartial($path, $values);
		exit;
	}

	/**
	 * Creates a new model component with the given parameters
	 *
	 * @param array Array with the action "modelComponent" and the name of the model whose component should be created
	 *
	 * @return void
	 */
	private function createModelComponent(array $params): void {
		$path = $this->getConfig()->getDir('ofw_template').'add/createModelComponent.php';
		$values = [
			'colors'           => $this->getColors(),
			'model_name'       => '',
			'model_file'       => '',
			'model'            => null,
			'list_folder'      => '',
			'list_file'        => '',
			'component_folder' => '',
			'component_file'   => '',
			'error'            => 0
		];

		if (count($params) < 2) {
			$values['error'] = 1;
			echo OTools::getPartial($path, $values);
			exit;
		}

		$values['model_name'] = $params['name'];
		$values['model_name_lower'] = strtolower($params['name']);
		$values['model_file'] = '';
		$model_path           = $this->config->getDir('app_model');

		if (file_exists($model_path)) {
			if ($model = opendir($model_path)) {
				while (false !== ($entry = readdir($model))) {
					if ($entry !== '.' && $entry !== '..') {
						$model_content = file_get_contents($model_path.$entry);
						if (stripos($model_content, 'class '.$values['model_name'].' extends') !== false) {
							$values['model_file'] = $this->getConfig()->getDir('app_model').$entry;
							break;
						}
					}
				}
				closedir($model);
			}
		}

		if ($values['model_file'] === '' || !file_exists($values['model_file'])) {
			$values['error'] = 2;
			echo OTools::getPartial($path, $values);
			exit;
		}

		$component_path = $this->getConfig()->getDir('app_component');
		$model_name = "\\Osumi\\OsumiFramework\\App\\Model\\".$values['model_name'];
		$model = new $model_name;
		$values['model'] = $model->getModel();
		$values['list_name']               = $values['model_name'].'ListComponent';
		$values['list_folder']             = $component_path.'Model/'.$values['model_name'].'List/';
		$values['list_file']               = $values['model_name'].'ListComponent.php';
		$values['list_template_file']      = $values['model_name'].'ListTemplate.php';
		$values['component_name']          = $values['model_name'];
		$values['component_folder']        = $component_path.'Model/'.$values['model_name'].'/';
		$values['component_file']          = $values['model_name'].'Component.php';
		$values['component_template_file'] = $values['model_name'].'Template.php';

		$add = OBuild::addModelComponent($values);

		if ($add === 'list-folder-exists') {
			$values['error'] = 3;
			echo OTools::getPartial($path, $values);
			exit;
		}
		if ($add === 'list-file-exists') {
			$values['error'] = 4;
			echo OTools::getPartial($path, $values);
			exit;
		}
		if ($add === 'component-folder-exists') {
			$values['error'] = 5;
			echo OTools::getPartial($path, $values);
			exit;
		}
		if ($add === 'component-file-exists') {
			$values['error'] = 6;
			echo OTools::getPartial($path, $values);
			exit;
		}
		if ($add === 'list-folder-cant-create') {
			$values['error'] = 7;
			echo OTools::getPartial($path, $values);
			exit;
		}
		if ($add === 'component-folder-cant-create') {
			$values['error'] = 8;
			echo OTools::getPartial($path, $values);
			exit;
		}
		if ($add === 'list-file-cant-create') {
			$values['error'] = 9;
			echo OTools::getPartial($path, $values);
			exit;
		}
		if ($add === 'component-file-cant-create') {
			$values['error'] = 10;
			echo OTools::getPartial($path, $values);
			exit;
		}

		echo OTools::getPartial($path, $values);
		exit;
	}

	/**
	 * Creates a new component with the given parameters
	 *
	 * @param array Array with the action "component" and the name of the new component
	 *
	 * @return void
	 */
	private function createComponent(array $params): void {
		$path = $this->getConfig()->getDir('ofw_template').'add/createComponent.php';
		$values = [
			'colors'         => $this->getColors(),
			'folders'        => '',
			'path'           => '',
			'component_name' => '',
			'component_file' => '',
			'template_file'  => '',
			'error'          => 0
		];

		if (count($params) < 2) {
			$values['error'] = 1;
			echo OTools::getPartial($path, $values);
			exit;
		}

		$component_name_parts = explode('/', $params['name']);
		for ($i = 0; $i < count($component_name_parts); $i++) {
			$component_name_parts[$i] = ucfirst($component_name_parts[$i]);
		}
		$values['folders']        = implode('/', $component_name_parts);
		$values['path']           = $this->getConfig()->getDir('app_component').$values['folders'].'/';
		$values['component_name'] = $component_name_parts[count($component_name_parts) -1];
		$values['component_file'] = $values['path'].$values['component_name'].'Component.php';
		$values['template_file']  = $values['path'].$values['component_name'].'Template.php';

		$add = OBuild::addComponent($values);

		if ($add === 'exists') {
			$values['error'] = 2;
			echo OTools::getPartial($path, $values);
			exit;
		}

		echo OTools::getPartial($path, $values);
		exit;
	}

	/**
	 * Creates a new filter with the given parameters
	 *
	 * @param array Array with the action "filter" and the name of the new component
	 *
	 * @return void
	 */
	private function createFilter(array $params): void {
		$path = $this->getConfig()->getDir('ofw_template').'add/createFilter.php';
		$values = [
			'colors'      => $this->getColors(),
			'filter_name' => '',
			'filter_file' => '',
			'error'       => 0
		];

		if (count($params) < 2) {
			$values['error'] = 1;
			echo OTools::getPartial($path, $values);
			exit;
		}

		$values['filter_name'] = ucfirst($params['name']);
		$values['filter_file'] = $this->getConfig()->getDir('app_filter').$values['filter_name'].'Filter.php';

		$add = OBuild::addFilter($values);

		if ($add === 'exists') {
			$values['error'] = 2;
			echo OTools::getPartial($path, $values);
			exit;
		}

		echo OTools::getPartial($path, $values);
		exit;
	}

	/**
	 * Run the task
	 *
	 * @param array Command line parameters: option and name
	 *
	 * @return void Echoes framework information
	 */
	public function run(array $params): void {
		$available_options = ['action', 'service', 'task', 'modelComponent','component', 'filter'];
		$option = (array_key_exists('option', $params)) ? $params['option'] : 'none';
		$option = in_array($option, $available_options) ? $option : 'none';

		switch ($option) {
			case 'action': {
				$this->createAction($params);
			}
			break;
			case 'service': {
				$this->createService($params);
			}
			break;
			case 'task': {
				$this->createTask($params);
			}
			break;
			case 'modelComponent': {
				$this->createModelComponent($params);
			}
			break;
			case 'component': {
				$this->createComponent($params);
			}
			break;
			case 'filter': {
				$this->createFilter($params);
			}
			break;
			case 'none': {
				$path   = $this->getConfig()->getDir('ofw_template').'add/add.php';
				$values = [
					'colors' => $this->getColors()
				];

				echo OTools::getPartial($path, $values);
			}
		}
	}
}
