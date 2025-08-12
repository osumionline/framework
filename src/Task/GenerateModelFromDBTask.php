<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\Task;

use Osumi\OsumiFramework\Core\OTask;
use Osumi\OsumiFramework\Tools\OTools;
use Osumi\OsumiFramework\Tools\OBuild;
use Osumi\OsumiFramework\ORM\ODB;

/**
 * Generates all model files from a database connection
 */
class GenerateModelFromDBTask extends OTask {
	public function __toString() {
		return $this->getColors()->getColoredString('generateModelFromDB', 'light_green').': '.OTools::getMessage('TASK_GENERATE_MODEL_FROM_DB');
	}

	private string $db_name = '';

	private function getTables(): array {
		$sql = "SELECT
			t.`TABLE_NAME` AS `table_name`
		FROM INFORMATION_SCHEMA.`TABLES` t
		WHERE t.`TABLE_SCHEMA` = '{$this->db_name}'
		ORDER BY t.`TABLE_NAME`";

		$db = new ODB();
		$db->query($sql);
		$ret = [];

		while ($res = $db->next()) {
			$ret[] = $res;
		}

		return $ret;
	}

	private function getColumns(string $table_name): array {
		$sql = "SELECT
		  c.`COLUMN_NAME`,
		  c.`ORDINAL_POSITION`,
		  c.`COLUMN_DEFAULT`,
		  c.`IS_NULLABLE`,                     -- 'YES'/'NO'
		  c.`DATA_TYPE`,                       -- varchar, int, float, datetime, text, tinyint, etc.
		  c.`CHARACTER_MAXIMUM_LENGTH`,
		  c.`NUMERIC_PRECISION`,
		  c.`NUMERIC_SCALE`,
		  c.`COLUMN_TYPE`,                     -- incluye ENUM/SET o longitudes exactas (ej: int(11), tinyint(1))
		  c.`COLUMN_KEY`,                      -- 'PRI', 'UNI', 'MUL'…
		  c.`EXTRA`,                           -- auto_increment, on update CURRENT_TIMESTAMP, VIRTUAL/ STORED, etc.
		  c.`GENERATION_EXPRESSION`,           -- columnas generadas
		  c.`COLLATION_NAME`,
		  c.`COLUMN_COMMENT`
		FROM INFORMATION_SCHEMA.`COLUMNS` c
		WHERE c.`TABLE_SCHEMA` = '{$this->db_name}'
		AND c.`TABLE_NAME` = '{$table_name}'
		ORDER BY c.`TABLE_NAME`, c.`ORDINAL_POSITION`";

		$db = new ODB();
		$db->query($sql);
		$ret = [];

		while ($res = $db->next()) {
			$field = [
				'name' => $res['COLUMN_NAME'],
				'comment' => $res['COLUMN_COMMENT']
			];

			// Created At
			if ($field['name'] === 'created_at') {
				$field['decorator'] = 'OCreatedAt';
			}
			// Updated At
			else if ($field['name'] === 'updated_at') {
				$field['decorator'] = 'OUpdatedAt';
			}
			else {
				$field['nullable'] = $res['IS_NULLABLE'] === 'YES';

				// Text
				if ($res['DATA_TYPE'] === 'text' || $res['DATA_TYPE'] === 'longtext') {
					$field['decorator'] = 'OField';
					$field['type'] = 'OField::LONGTEXT';
          $field['attribute_type'] = 'string';
					$field['default'] = $res['COLUMN_DEFAULT'] === 'NULL'
																? ($field['nullable'] ? null : '')
																: ($res['COLUMN_DEFAULT'] === "''" ? '' : $res['COLUMN_DEFAULT']);
				}
				// Float
				if ($res['DATA_TYPE'] === 'float' || $res['DATA_TYPE'] === 'decimal') {
					$field['decorator'] = 'OField';
          $field['attribute_type'] = 'float';
					$field['default'] = $res['COLUMN_DEFAULT'] === 'NULL'
																? ($field['nullable'] ? null : 0.0)
																: floatval($res['COLUMN_DEFAULT']);
				}
				// Datetime
				if ($res['DATA_TYPE'] === 'datetime') {
					$field['decorator'] = 'OField';
					$field['type'] = 'OField::DATE';
          $field['attribute_type'] = 'string';
					$field['default'] = $res['COLUMN_DEFAULT'] === 'NULL' ? null : $res['COLUMN_DEFAULT'];
				}
				// Bool
				if ($res['DATA_TYPE'] === 'tinyint' && ($res['COLUMN_DEFAULT'] === '0' || $res['COLUMN_DEFAULT'] === '1')) {
					$field['decorator'] = 'OField';
          $field['attribute_type'] = 'bool';
					$field['default'] = $res['COLUMN_DEFAULT'] === '1';
				}
				// String
				if ($res['DATA_TYPE'] === 'varchar' || $res['DATA_TYPE'] === 'char') {
					$field['decorator'] = 'OField';
					$field['max'] = $res['CHARACTER_MAXIMUM_LENGTH'];
          $field['attribute_type'] = 'string';
					$field['default'] = $res['COLUMN_DEFAULT'] === 'NULL'
																? ($field['nullable'] ? null : '')
																: ($res['COLUMN_DEFAULT'] === "''" ? '' : $res['COLUMN_DEFAULT']);
				}
				// Int
				if ($res['DATA_TYPE'] === 'int' || $res['DATA_TYPE'] === 'bigint') {
					$field['decorator'] = 'OField';
          $field['attribute_type'] = 'int';
					$field['default'] = $res['COLUMN_DEFAULT'] === 'NULL'
																? ($field['nullable'] ? null : 0)
																: intval($res['COLUMN_DEFAULT']);
				}
			}
			$ret[] = $field;
		}

		return $ret;
	}

	private function getPK(array $model): array {
		$sql = "SELECT
		  kcu.`TABLE_SCHEMA`,
		  kcu.`TABLE_NAME`,
		  kcu.`COLUMN_NAME`,
		  kcu.`ORDINAL_POSITION`        -- orden dentro de la PK
		FROM INFORMATION_SCHEMA.`KEY_COLUMN_USAGE` kcu
		JOIN INFORMATION_SCHEMA.`TABLE_CONSTRAINTS` tc
		  ON tc.`CONSTRAINT_SCHEMA` = kcu.`CONSTRAINT_SCHEMA`
		 AND tc.`TABLE_NAME`        = kcu.`TABLE_NAME`
		 AND tc.`CONSTRAINT_NAME`   = kcu.`CONSTRAINT_NAME`
		WHERE tc.`CONSTRAINT_TYPE` = 'PRIMARY KEY'
		  AND tc.`TABLE_SCHEMA`    = '{$this->db_name}'
			AND kcu.`TABLE_NAME`     = '{$model['name']}'
		ORDER BY kcu.`TABLE_NAME`, kcu.`ORDINAL_POSITION`";

		$db = new ODB();
		$db->query($sql);

		while ($res = $db->next()) {
			for ($i = 0; $i < count($model['fields']); $i++) {
				if ($model['fields'][$i]['name'] === $res['COLUMN_NAME']) {
					$model['fields'][$i]['decorator'] = 'OPK';
					unset($model['fields'][$i]['nullable']);
					unset($model['fields'][$i]['attribute_type']);
					unset($model['fields'][$i]['default']);
					break;
				}
			}
		}

		return $model;
	}

	private function getRefs(array $models): array {
		$sql = "SELECT
		  kcu.TABLE_NAME,
		  kcu.COLUMN_NAME,                -- columna local
		  kcu.REFERENCED_TABLE_NAME,
		  kcu.REFERENCED_COLUMN_NAME      -- columna referenciada (respeta el orden)
		FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
		JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc
		  ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
		 AND rc.CONSTRAINT_NAME   = kcu.CONSTRAINT_NAME
		WHERE kcu.TABLE_SCHEMA = '{$this->db_name}'
		  AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
		ORDER BY kcu.TABLE_NAME, kcu.CONSTRAINT_NAME, kcu.ORDINAL_POSITION";

		$db = new ODB();
		$db->query($sql);
		$refs = [];
		while ($res = $db->next()) {
			$refs[] = $res;
		}

		foreach ($refs as $ref) {
			for ($i = 0; $i < count($models); $i++) {
				if ($models[$i]['name'] === $ref['TABLE_NAME']) {
					for ($j = 0; $j < count($models[$i]['fields']); $j++) {
						if ($models[$i]['fields'][$j]['name'] === $ref['COLUMN_NAME']) {
							$models[$i]['fields'][$j]['ref'] = $ref['REFERENCED_TABLE_NAME'].'.'.$ref['REFERENCED_COLUMN_NAME'];
						}
					}
				}
				if ($models[$i]['name'] === $ref['REFERENCED_TABLE_NAME']) {
					if (!isset($models[$i]['refs'])) {
						$models[$i]['refs'] = [];
					}
					$models[$i]['refs'][] = [
						'to' => $ref['TABLE_NAME'],
						'field_from' => $ref['REFERENCED_COLUMN_NAME'],
						'field_to' => $ref['COLUMN_NAME']
					];
				}
			}
		}

		return $models;
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
        echo OTools::getMessage('TASK_GENERATE_MODEL_FROM_OK', [$values['table_name'], $values['class_file']])."\n";
      }
      break;
			case 'error-exists': {
				echo OTools::getMessage('TASK_GENERATE_MODEL_FROM_ERROR_EXISTS', [$values['class_file']])."\n";
			}
			break;
      case 'error-pk': {
        echo OTools::getMessage('TASK_GENERATE_MODEL_FROM_ERROR_PK', [$table['name']])."\n";
      }
      break;
      case 'error-created-at': {
        echo OTools::getMessage('TASK_GENERATE_MODEL_FROM_ERROR_CREATED_AT', [$table['name']])."\n";
      }
      break;
      case 'error-updated-at': {
        echo OTools::getMessage('TASK_GENERATE_MODEL_FROM_ERROR_UPDATED_AT', [$table['name']])."\n";
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
		$db_name = $this->getConfig()->getDB('name');
		if (empty($db_name)) {
			echo "\n  ".$this->getColors()->getColoredString(OTools::getMessage('TASK_GENERATE_MODEL_FROM_WARNING'), 'red')."\n\n";
			echo "  ".OTools::getMessage('TASK_GENERATE_MODEL_FROM_CONTINUE')."\n\n";
      exit;
		}
    $this->db_name = $db_name;

		$models = [];
		$tables = $this->getTables();
		foreach ($tables as $table) {
			$model = [
				'name'   => $table['table_name'],
				'fields' => $this->getColumns($table['table_name'])
			];
			$model = $this->getPK($model);
			$models[] = $model;
		}
		$models = $this->getRefs($models);

		$model_path = $this->getConfig()->getDir('app_model');
		if (!file_exists($model_path)) {
			mkdir($model_path, 0755);
		}
		foreach ($models as $model) {
			$this->generateTable($model);
		}
	}
}
