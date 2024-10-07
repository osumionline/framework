<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\CLI;

use Osumi\OsumiFramework\Core\OCore;
use Osumi\OsumiFramework\Tools\OTools;
use Osumi\OsumiFramework\Tools\OColors;

/**
 * Class to handle CLI tools and tasks (internal and user defined)
 */
class CLI {
  private array $ofw_task_list = [];
  private array $app_task_list = [];
  private ?OColors $colors = null;

  public function __construct() {
    global $core;
    $core = new OCore();
    $core->load(true);
    $this->colors = new OColors();
    $this->loadOFWTasks();
    $this->loadAppTasks();
  }

  /**
   * Parse CLI arguments and return them in an array
   *
   * @param array $argv List of parameters passed to the CLI
   *
   * @return array List of parsed parameters
   */
  private function parseArguments(array $argv): array {
    // Remove script name and command (first two elements)
    array_shift($argv);
    array_shift($argv);

    $params = [];
    $i = 0;

    while ($i < count($argv)) {
      $arg = $argv[$i];

      // Check if the argument is a parameter (starts with --)
      if (strpos($arg, '--') === 0) {
        $paramName = substr($arg, 2); // Remove -- prefix

        // Check if there's a next argument and it's not another parameter
        if (isset($argv[$i + 1]) && strpos($argv[$i + 1], '--') !== 0) {
          $params[$paramName] = $argv[$i + 1];
          $i += 2; // Skip both parameter name and value
        } else {
          // Parameter without value, set it to true
          $params[$paramName] = false;
          $i++;
        }
      } else {
        // Handle non-parameter arguments if needed
        $params['param_'.$i] = $arg;
        $i++;
      }
    }

    return $params;
  }

  /**
   * Load list of available framework tasks
   *
   * @return void
   */
  private function loadOFWTasks(): void {
    global $core;
    if ($model = opendir($core->config->getDir('ofw_task'))) {
    	while (false !== ($entry = readdir($model))) {
    		if ($entry !== "." && $entry !== "..") {
    			$this->ofw_task_list[] = str_ireplace("Task.php", "", $entry);
    		}
    	}
    	closedir($model);
    }
  }

  /**
   * Load list of available user tasks
   *
   * @return void
   */
  private function loadAppTasks(): void {
    global $core;
    $tasks_path = $core->config->getDir('app_task');
    if (file_exists($tasks_path)) {
    	if ($model = opendir($tasks_path)) {
    		while (false !== ($entry = readdir($model))) {
    			if ($entry !== "." && $entry !== "..") {
    				require_once $tasks_path.$entry;
    				$this->app_task_list[] = str_ireplace('Task.php', '', $entry);
    			}
    		}
    		closedir($model);
    	}
    }
  }

  /**
   * Show list of available tasks, both framework and users
   *
   * @return string List of tasks
   */
  private function taskOptions(): string {
  	$ret = "";
  	$ret .= OTools::getMessage('OFW_OPTIONS');
  	asort($this->ofw_task_list);
  	foreach ($this->ofw_task_list as $task) {
  		$task_name = "\\Osumi\\OsumiFramework\\Task\\".$task."Task";
  		$task = new $task_name;
  		$task->loadTask();
  		$ret .= "  ·  ".$task."\n";
  	}
  	asort($this->app_task_list);
  	foreach ($this->app_task_list as $task) {
  		$task_name = "\\Osumi\\OsumiFramework\\App\\Task\\".$task."Task";
  		$task = new $task_name;
  		$task->loadTask();
  		$ret .= "  ·  ".$task."\n";
  	}
  	$ret .= "\n".OTools::getMessage('OFW_EXAMPLE').": php of ".lcfirst($this->ofw_task_list[0])."\n\n";
  	return $ret;
  }

  /**
   * Run selected task
   *
   * @param string $task_name Full name of the task to be run
   *
   * @param array $options List of parameters passed to the task
   *
   * @return void
   */
  private function runTask(string $task_name, array $options): void {
    $task = new $task_name;
    $task->loadTask();
    $task->run($options);
  }

  /**
   * Runs CLI
   *
   * @param array $argv List of options passed in the command line
   *
   * @return void
   */
  public function run(array $argv = []): void {
    // Check if option exists
    if (!array_key_exists(1, $argv)) {
    	echo "\n  ".$this->colors->getColoredString("Osumi Framework", "white", "blue")."\n\n";
    	echo OTools::getMessage('OFW_INDICATE_OPTION')."\n";
    	echo $this->taskOptions();
    	exit;
    }

    // Check if option is valid
    $option = $argv[1];
    if (!in_array(ucfirst($option), $this->ofw_task_list) && !in_array(ucfirst($option), $this->app_task_list)) {
    	echo OTools::getMessage('OFW_WRONG_OPTION', [$option])."\n\n";
    	echo $this->taskOptions();
    	exit;
    }

    // Get parameters
    $options = $this->parseArguments($argv);

    // Check if it is an OFW task or user task
    if (in_array(ucfirst($option), $this->ofw_task_list)) {
    	$task_name = "\\Osumi\\OsumiFramework\\Task\\".ucfirst($option).'Task';
    }
    if (in_array(ucfirst($option), $this->app_task_list)) {
    	$task_name = "\\Osumi\\OsumiFramework\\App\\Task\\".ucfirst($option).'Task';
    }

    $this->runTask($task_name, $options);
  }
}
