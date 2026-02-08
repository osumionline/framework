<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Task;

use Osumi\OsumiFramework\Core\OTask;
use Osumi\OsumiFramework\Tools\OTools;
use Osumi\OsumiFramework\Tools\OBuild;

/**
 * Generates all model files from a JSON file
 */
class GenerateModelFromTask extends OTask {
  public function __toString() {
    return $this->getColors()->getColoredString('generateModelFrom', 'light_green') . ': ' . OTools::getMessage('TASK_GENERATE_MODEL_FROM');
  }

  /**
   * Load specified file
   *
   * @param string $content Content of the specified file
   *
   * @return void
   */
  private function loadFile(string $content): void {
    $data = json_decode($content, true);
    foreach ($data['model'] as $table) {
      $this->generateTable($table);
    }
  }

  /**
   * Generate a table
   *
   * @param array $table Data of a table
   *
   * @return void
   */
  private function generateTable(array $table): void {
    $table_name = OTools::underscoresToCamelCase($table['name'], true);
    $values = [
      'table_name' => $table_name,
      'class_file' => $this->getConfig()->getDir('app_model') . $table_name . '.php',
      'fields'     => $table['fields'],
      'refs'       => array_key_exists('refs', $table) ? $table['refs'] : []
    ];
    $status = OBuild::addModelClass($values);

    switch ($status) {
      case 'ok': {
          echo OTools::getMessage('TASK_GENERATE_MODEL_FROM_OK', [$values['table_name'], $values['class_file']]) . "\n";
        }
        break;
      case 'error-exists': {
          echo OTools::getMessage('TASK_GENERATE_MODEL_FROM_ERROR_EXISTS', [$values['class_file']]) . "\n";
        }
        break;
      case 'error-pk': {
          echo OTools::getMessage('TASK_GENERATE_MODEL_FROM_ERROR_PK', [$table['name']]) . "\n";
        }
        break;
      case 'error-created-at': {
          echo OTools::getMessage('TASK_GENERATE_MODEL_FROM_ERROR_CREATED_AT', [$table['name']]) . "\n";
        }
        break;
      case 'error-updated-at': {
          echo OTools::getMessage('TASK_GENERATE_MODEL_FROM_ERROR_UPDATED_AT', [$table['name']]) . "\n";
        }
        break;
    }
  }

  /**
   * Run the task
   *
   * @return void Echoes generated model files and creates them on the Model folder
   */
  public function run(array $options = []): void {
    if (count($options) === 0) {
      echo "\n  " . $this->getColors()->getColoredString(OTools::getMessage('TASK_GENERATE_MODEL_FROM_WARNING'), 'red') . "\n\n";
      echo "  " . OTools::getMessage('TASK_GENERATE_MODEL_FROM_CONTINUE') . "\n\n";
      exit;
    }

    $file = $this->getConfig()->getDir('base') . $options['file'];
    if (!file_exists($file)) {
      echo "\n  " . $this->getColors()->getColoredString(OTools::getMessage('TASK_GENERATE_MODEL_FROM_WARNING'), 'red') . "\n\n";
      echo "  " . OTools::getMessage('TASK_GENERATE_MODEL_FROM_FILE_NOT_FOUND') . "\n\n";
      exit;
    }

    $content = file_get_contents($file);
    $this->loadFile($content);
  }
}
