<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Task;

use Osumi\OsumiFramework\Core\OTask;
use Osumi\OsumiFramework\Tools\OTools;
use Osumi\OsumiFramework\Tools\OBuild;
use Osumi\OsumiFramework\ORM\ODB;

/**
 * Generates all model files from a database connection
 */
class GenerateModelFromDBTask extends OTask {
	public function __toString(): string {
		return $this->getColors()->getColoredString('generateModelFromDB', 'light_green') . ': ' . OTools::getMessage('TASK_GENERATE_MODEL_FROM_DB');
	}

	private string $db_name = '';

	/**
	 * Get the list of tables from the configured database.
	 *
	 * @return list<array{table_name: string}> Database tables.
	 */
	private function getTables(): array {
		$sql = "SELECT
		t.`TABLE_NAME` AS `table_name`
		FROM INFORMATION_SCHEMA.`TABLES` t
		WHERE t.`TABLE_SCHEMA` = :db_name
		ORDER BY t.`TABLE_NAME`";

		$db = new ODB();
		$db->query($sql, [
			'db_name' => $this->db_name
		]);

		$ret = [];

		while ($res = $db->next()) {
			$ret[] = $res;
		}

		return $ret;
	}

	/**
	 * Parse a MariaDB column default into an ORM-compatible PHP value.
	 *
	 * String and date literals must be quoted by MariaDB metadata. Numeric and
	 * boolean literals may be represented without quotes. SQL expressions cannot
	 * be represented by the ORM because model defaults must be actual PHP values.
	 *
	 * @param string $field_name Field name.
	 * @param string $attribute_type PHP property type.
	 * @param mixed $column_default Raw COLUMN_DEFAULT metadata value.
	 *
	 * @return string|int|float|bool|null Parsed default value.
	 *
	 * @throws \RuntimeException If the metadata is invalid or contains an SQL
	 *                           expression that cannot be represented by the ORM.
	 */
	private function parseColumnDefault(
		string $field_name,
		string $attribute_type,
		mixed $column_default
	): string|int|float|bool|null {
		if ($column_default === null) {
			return null;
		}

		if (!is_string($column_default)) {
			throw new \RuntimeException(
				"Unexpected COLUMN_DEFAULT metadata type for field '{$field_name}'."
			);
		}

		/*
	 * MariaDB may return the text NULL for an implicit or explicit SQL
	 * DEFAULT NULL. A literal string containing "NULL" is returned quoted and
	 * therefore does not match this condition.
	 */
		if (strcasecmp(
			$column_default,
			'NULL'
		) === 0) {
			return null;
		}

		$length = strlen(
			$column_default
		);

		$is_quoted = (
			$length >= 2 &&
			$column_default[0] === "'" &&
			$column_default[$length - 1] === "'"
		);

		$value = $column_default;

		if ($is_quoted) {
			$value = substr(
				$column_default,
				1,
				-1
			);

			$value = str_replace(
				"''",
				"'",
				$value
			);
		}

		switch ($attribute_type) {
			case 'string':
				if (!$is_quoted) {
					throw new \RuntimeException(
						"SQL default expression '{$column_default}' for field '{$field_name}' cannot be represented as an ORM default value."
					);
				}

				return $value;

			case 'int':
				if (
					preg_match(
						'/^[+-]?\d+$/D',
						$value
					) !== 1
				) {
					throw new \RuntimeException(
						"SQL default expression '{$column_default}' for field '{$field_name}' cannot be represented as an integer ORM default."
					);
				}

				$normalized = filter_var(
					$value,
					FILTER_VALIDATE_INT
				);

				if ($normalized === false) {
					throw new \RuntimeException(
						"Integer default for field '{$field_name}' is outside the supported PHP integer range."
					);
				}

				return $normalized;

			case 'float':
				$normalized = filter_var(
					$value,
					FILTER_VALIDATE_FLOAT
				);

				if (
					$normalized === false ||
					!is_finite(
						(float) $normalized
					)
				) {
					throw new \RuntimeException(
						"SQL default expression '{$column_default}' for field '{$field_name}' cannot be represented as a float ORM default."
					);
				}

				return (float) $normalized;

			case 'bool':
				if (
					$value !== '0' &&
					$value !== '1'
				) {
					throw new \RuntimeException(
						"SQL default expression '{$column_default}' for field '{$field_name}' cannot be represented as a boolean ORM default."
					);
				}

				return $value === '1';
		}

		throw new \RuntimeException(
			"Unsupported PHP attribute type '{$attribute_type}' for field '{$field_name}'."
		);
	}

	/**
	 * Get the column definitions for a database table.
	 *
	 * @param string $table_name Table name.
	 *
	 * @return list<array{
	 *     name: string,
	 *     decorator: string,
	 *     comment: string,
	 *     attribute_type?: string,
	 *     type?: string,
	 *     nullable?: bool,
	 *     default?: string|int|float|bool,
	 *     max?: int,
	 *     ref?: string
	 * }> Column definitions.
	 *
	 * @throws \RuntimeException If a database field type or default value cannot
	 *                           be represented by the ORM.
	 */
	private function getColumns(
		string $table_name
	): array {
		$sql = "SELECT
		c.`COLUMN_NAME`,
		c.`ORDINAL_POSITION`,
		c.`COLUMN_DEFAULT`,
		c.`IS_NULLABLE`,
		c.`DATA_TYPE`,
		c.`CHARACTER_MAXIMUM_LENGTH`,
		c.`NUMERIC_PRECISION`,
		c.`NUMERIC_SCALE`,
		c.`COLUMN_TYPE`,
		c.`COLUMN_KEY`,
		c.`EXTRA`,
		c.`GENERATION_EXPRESSION`,
		c.`COLLATION_NAME`,
		c.`COLUMN_COMMENT`
	FROM INFORMATION_SCHEMA.`COLUMNS` c
	WHERE c.`TABLE_SCHEMA` = :db_name
		AND c.`TABLE_NAME` = :table_name
	ORDER BY c.`ORDINAL_POSITION`";

		$db = new ODB();

		$db->query(
			$sql,
			[
				'db_name' => $this->db_name,
				'table_name' => $table_name
			]
		);

		$ret = [];

		while ($res = $db->next()) {
			$field_name = (string) $res['COLUMN_NAME'];

			$field = [
				'name' => $field_name,
				'comment' => (string) $res['COLUMN_COMMENT']
			];

			if ($field_name === 'created_at') {
				$field['decorator'] = 'OCreatedAt';
				$ret[] = $field;
				continue;
			}

			if ($field_name === 'updated_at') {
				$field['decorator'] = 'OUpdatedAt';
				$ret[] = $field;
				continue;
			}

			if ($field_name === 'deleted_at') {
				$field['decorator'] = 'ODeletedAt';
				$ret[] = $field;
				continue;
			}

			$field['nullable'] = $res['IS_NULLABLE'] === 'YES';

			$data_type = strtolower(
				(string) $res['DATA_TYPE']
			);

			$column_type = strtolower(
				(string) $res['COLUMN_TYPE']
			);

			switch ($data_type) {
				case 'text':
				case 'longtext':
					$field['decorator'] = 'OField';
					$field['type'] = 'OField::LONGTEXT';
					$field['attribute_type'] = 'string';
					break;

				case 'varchar':
				case 'char':
					$field['decorator'] = 'OField';
					$field['type'] = 'OField::TEXT';
					$field['attribute_type'] = 'string';
					$field['max'] = (int) $res['CHARACTER_MAXIMUM_LENGTH'];
					break;

				case 'float':
				case 'decimal':
					$field['decorator'] = 'OField';
					$field['type'] = 'OField::FLOAT';
					$field['attribute_type'] = 'float';
					break;

				case 'datetime':
				case 'timestamp':
					$field['decorator'] = 'OField';
					$field['type'] = 'OField::DATE';
					$field['attribute_type'] = 'string';
					break;

				case 'tinyint':
					if (
						preg_match(
							'/^tinyint\(1\)/D',
							$column_type
						) === 1
					) {
						$field['decorator'] = 'OField';
						$field['type'] = 'OField::BOOL';
						$field['attribute_type'] = 'bool';
						break;
					}

					/*
				 * Non-boolean TINYINT columns are regular integer fields.
				 */
					$field['decorator'] = 'OField';
					$field['type'] = 'OField::NUMBER';
					$field['attribute_type'] = 'int';
					break;

				case 'smallint':
				case 'mediumint':
				case 'int':
				case 'integer':
				case 'bigint':
					$field['decorator'] = 'OField';
					$field['type'] = 'OField::NUMBER';
					$field['attribute_type'] = 'int';
					break;

				default:
					throw new \RuntimeException(
						"Database type '{$data_type}' for field '{$field_name}' cannot be represented by the ORM."
					);
			}

			$default = $this->parseColumnDefault(
				$field_name,
				$field['attribute_type'],
				$res['COLUMN_DEFAULT']
			);

			if ($default !== null) {
				$field['default'] = $default;
			}

			$ret[] = $field;
		}

		return $ret;
	}

	/**
	 * Apply primary key information to a model definition.
	 *
	 * @param array{
	 *     name: string,
	 *     fields: list<array{
	 *         name: string,
	 *         decorator: string,
	 *         comment: string,
	 *         attribute_type?: string,
	 *         type?: string,
	 *         nullable?: bool,
	 *         default?: string|int|float|bool|null,
	 *         max?: int,
	 *         ref?: string
	 *     }>,
	 *     refs?: list<array{
	 *         to: string,
	 *         field_from: string,
	 *         field_to: string
	 *     }>
	 * } $model Model definition.
	 *
	 * @return array Updated model definition.
	 *
	 * @throws \RuntimeException If a primary key field type cannot be determined.
	 */
	private function getPK(
		array $model
	): array {
		$sql = "SELECT
		kcu.`TABLE_SCHEMA`,
		kcu.`TABLE_NAME`,
		kcu.`COLUMN_NAME`,
		kcu.`ORDINAL_POSITION`,
		c.`EXTRA`
		FROM INFORMATION_SCHEMA.`KEY_COLUMN_USAGE` kcu
		JOIN INFORMATION_SCHEMA.`TABLE_CONSTRAINTS` tc
			ON tc.`CONSTRAINT_SCHEMA` = kcu.`CONSTRAINT_SCHEMA`
			AND tc.`TABLE_NAME` = kcu.`TABLE_NAME`
			AND tc.`CONSTRAINT_NAME` = kcu.`CONSTRAINT_NAME`
		JOIN INFORMATION_SCHEMA.`COLUMNS` c
			ON c.`TABLE_SCHEMA` = kcu.`TABLE_SCHEMA`
			AND c.`TABLE_NAME` = kcu.`TABLE_NAME`
			AND c.`COLUMN_NAME` = kcu.`COLUMN_NAME`
		WHERE tc.`CONSTRAINT_TYPE` = 'PRIMARY KEY'
			AND tc.`TABLE_SCHEMA` = :db_name
			AND kcu.`TABLE_NAME` = :table_name
		ORDER BY kcu.`ORDINAL_POSITION`";

		$db = new ODB();

		$db->query(
			$sql,
			[
				'db_name' => $this->db_name,
				'table_name' => $model['name']
			]
		);

		while ($res = $db->next()) {
			foreach ($model['fields'] as &$field) {
				if ($field['name'] !== $res['COLUMN_NAME']) {
					continue;
				}

				$attribute_type = $field['attribute_type'] ?? null;

				if (!is_string($attribute_type)) {
					throw new \RuntimeException(
						"Could not determine PHP type for primary key '{$field['name']}'."
					);
				}

				$field['decorator'] = 'OPK';

				$field['type'] = match ($attribute_type) {
					'int' => 'OField::NUMBER',
					'float' => 'OField::FLOAT',
					'bool' => 'OField::BOOL',
					'string' => $field['type'] ?? 'OField::TEXT',
					default => throw new \RuntimeException(
						"Unsupported PHP type '{$attribute_type}' for primary key '{$field['name']}'."
					)
				};

				$field['incr'] = str_contains(
					strtolower(
						(string) $res['EXTRA']
					),
					'auto_increment'
				);

				$field['nullable'] = false;

				if ($field['incr']) {
					unset(
						$field['default']
					);
				}

				unset($field['visible']);

				break;
			}

			unset($field);
		}

		return $model;
	}

	/**
	 * Apply foreign key relationship information to model definitions.
	 *
	 * @param list<array{
	 *     name: string,
	 *     fields: list<array{
	 *         name: string,
	 *         decorator: string,
	 *         comment: string,
	 *         attribute_type?: string,
	 *         type?: string,
	 *         nullable?: bool,
	 *         default?: string|int|float|bool|null,
	 *         max?: int,
	 *         ref?: string
	 *     }>,
	 *     refs?: list<array{
	 *         to: string,
	 *         field_from: string,
	 *         field_to: string
	 *     }>
	 * }> $models Model definitions.
	 *
	 * @return list<array{
	 *     name: string,
	 *     fields: list<array{
	 *         name: string,
	 *         decorator: string,
	 *         comment: string,
	 *         attribute_type?: string,
	 *         type?: string,
	 *         nullable?: bool,
	 *         default?: string|int|float|bool|null,
	 *         max?: int,
	 *         ref?: string
	 *     }>,
	 *     refs?: list<array{
	 *         to: string,
	 *         field_from: string,
	 *         field_to: string
	 *     }>
	 * }> Updated model definitions.
	 */
	private function getRefs(array $models): array {
		$sql = "SELECT
			kcu.TABLE_NAME,
			kcu.COLUMN_NAME,
			kcu.REFERENCED_TABLE_NAME,
			kcu.REFERENCED_COLUMN_NAME
		FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
		JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc
		ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
		AND rc.CONSTRAINT_NAME   = kcu.CONSTRAINT_NAME
		WHERE kcu.TABLE_SCHEMA = :db_name
		AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
		ORDER BY kcu.TABLE_NAME, kcu.CONSTRAINT_NAME, kcu.ORDINAL_POSITION";

		$db = new ODB();
		$db->query($sql, [
			'db_name' => $this->db_name
		]);

		$refs = [];

		while ($res = $db->next()) {
			$refs[] = $res;
		}

		foreach ($refs as $ref) {
			for ($i = 0; $i < count($models); $i++) {
				if ($models[$i]['name'] === $ref['TABLE_NAME']) {
					for ($j = 0; $j < count($models[$i]['fields']); $j++) {
						if ($models[$i]['fields'][$j]['name'] === $ref['COLUMN_NAME']) {
							$models[$i]['fields'][$j]['ref'] =
								$ref['REFERENCED_TABLE_NAME']
								. '.'
								. $ref['REFERENCED_COLUMN_NAME'];
						}
					}
				}

				if ($models[$i]['name'] === $ref['REFERENCED_TABLE_NAME']) {
					if (!isset($models[$i]['refs'])) {
						$models[$i]['refs'] = [];
					}

					$models[$i]['refs'][] = [
						'to'         => $ref['TABLE_NAME'],
						'field_from' => $ref['REFERENCED_COLUMN_NAME'],
						'field_to'   => $ref['COLUMN_NAME']
					];
				}
			}
		}

		return $models;
	}

	/**
	 * Generate a model class from a table definition.
	 *
	 * @param array{
	 *     name: string,
	 *     fields: list<array{
	 *         name: string,
	 *         decorator: string,
	 *         comment: string,
	 *         attribute_type?: string,
	 *         type?: string,
	 *         nullable?: bool,
	 *         default?: string|int|float|bool|null,
	 *         max?: int,
	 *         ref?: string
	 *     }>,
	 *     refs?: list<array{
	 *         to: string,
	 *         field_from: string,
	 *         field_to: string
	 *     }>
	 * } $table Table definition.
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
	 * Run the task.
	 *
	 * @param array<string, string|false> $options Task options.
	 *
	 * @return void
	 *
	 * @throws \RuntimeException If the model directory cannot be created.
	 */
	public function run(array $options = []): void {
		$db_name = $this->getConfig()->getDB('name');
		if (empty($db_name)) {
			echo "\n  " . $this->getColors()->getColoredString(OTools::getMessage('TASK_GENERATE_MODEL_FROM_WARNING'), 'red') . "\n\n";
			echo "  " . OTools::getMessage('TASK_GENERATE_MODEL_FROM_CONTINUE') . "\n\n";
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
		if (
			!is_dir($model_path) &&
			!mkdir($model_path, 0755, true)
		) {
			throw new \RuntimeException(
				"Could not create model directory '{$model_path}'."
			);
		}
		foreach ($models as $model) {
			$this->generateTable($model);
		}
	}
}
