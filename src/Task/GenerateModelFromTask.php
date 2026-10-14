<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Task;

use Osumi\OsumiFramework\Core\OTask;
use Osumi\OsumiFramework\Tools\OTools;
use Osumi\OsumiFramework\Tools\OBuild;
use Osumi\OsumiFramework\ORM\OField;

/**
 * Generates all model files from a JSON file.
 */
class GenerateModelFromTask extends OTask {
  public function __toString(): string {
    return $this->getColors()->getColoredString(
      'generateModelFrom',
      'light_green'
    ) . ': ' . OTools::getMessage('TASK_GENERATE_MODEL_FROM');
  }

  /**
   * Check whether a value can safely be used as a PHP identifier.
   *
   * @param string $value Value to validate.
   *
   * @return bool Whether the value is a valid PHP identifier.
   */
  private function isValidIdentifier(string $value): bool {
    return preg_match(
      '/^[A-Za-z_][A-Za-z0-9_]*$/D',
      $value
    ) === 1;
  }

  /**
   * Validate a DECIMAL value without using floating-point arithmetic.
   *
   * Both "." and "," are accepted as decimal separators. The value must fit
   * within the configured precision and scale without requiring rounding.
   *
   * @param string $field_name Field name.
   * @param string $value Decimal value.
   * @param int $precision Maximum total number of digits.
   * @param int $scale Maximum number of fractional digits.
   *
   * @return void
   *
   * @throws \InvalidArgumentException If the value has an invalid format or
   *                                   exceeds the configured precision or scale.
   */
  private function validateDecimalValue(
    string $field_name,
    string $value,
    int $precision,
    int $scale
  ): void {
    $matches = [];

    if (
      preg_match(
        '/^([+-]?)(\d+)(?:[.,](\d+))?$/D',
        $value,
        $matches
      ) !== 1
    ) {
      throw new \InvalidArgumentException(
        "DECIMAL default for field '{$field_name}' has an invalid format."
      );
    }

    $integer_part = ltrim(
      $matches[2],
      '0'
    );

    if ($integer_part === '') {
      $integer_part = '0';
    }

    $fractional_part = $matches[3]
      ?? '';

    if (strlen($fractional_part) > $scale) {
      throw new \InvalidArgumentException(
        "DECIMAL default for field '{$field_name}' exceeds scale {$scale}."
      );
    }

    $maximum_integer_digits = $precision - $scale;

    $integer_digits = $integer_part === '0'
      ? 0
      : strlen($integer_part);

    if ($integer_digits > $maximum_integer_digits) {
      throw new \InvalidArgumentException(
        "DECIMAL default for field '{$field_name}' exceeds precision {$precision} with scale {$scale}."
      );
    }
  }

  /**
   * Validate a model field definition.
   *
   * @param array<array-key, mixed> $field Field definition to validate.
   *
   * @return void
   *
   * @throws \InvalidArgumentException If the field definition is invalid.
   */
  private function validateField(array $field): void {
    if (
      !array_key_exists('name', $field) ||
      !is_string($field['name']) ||
      !$this->isValidIdentifier($field['name'])
    ) {
      throw new \InvalidArgumentException(
        'Every model field must contain a valid "name".'
      );
    }

    if (
      !array_key_exists('decorator', $field) ||
      !is_string($field['decorator']) ||
      !in_array(
        $field['decorator'],
        [
          'OPK',
          'OField',
          'OCreatedAt',
          'OUpdatedAt',
          'ODeletedAt'
        ],
        true
      )
    ) {
      throw new \InvalidArgumentException(
        "Field '{$field['name']}' contains an invalid decorator."
      );
    }

    $allowed_keys = match ($field['decorator']) {
      'OPK' => [
        'name',
        'decorator',
        'attribute_type',
        'type',
        'incr',
        'comment',
        'ref',
        'nullable',
        'default',
        'max'
      ],
      'OField' => [
        'name',
        'decorator',
        'attribute_type',
        'type',
        'nullable',
        'default',
        'max',
        'comment',
        'visible',
        'ref',
        'precision',
        'scale'
      ],
      'OCreatedAt',
      'OUpdatedAt',
      'ODeletedAt' => [
        'name',
        'decorator',
        'comment'
      ]
    };

    foreach (array_keys($field) as $key) {
      if (
        !is_string($key) ||
        !in_array($key, $allowed_keys, true)
      ) {
        throw new \InvalidArgumentException(
          "Field '{$field['name']}' contains unsupported property '{$key}'."
        );
      }
    }

    if ($field['decorator'] === 'OField') {
      if (
        !array_key_exists('attribute_type', $field) ||
        !is_string($field['attribute_type'])
      ) {
        throw new \InvalidArgumentException(
          "Field '{$field['name']}' must define an attribute type."
        );
      }
    }

    if (array_key_exists('attribute_type', $field)) {
      if (
        !is_string($field['attribute_type']) ||
        !in_array(
          $field['attribute_type'],
          [
            'string',
            'int',
            'float',
            'bool'
          ],
          true
        )
      ) {
        throw new \InvalidArgumentException(
          "Field '{$field['name']}' contains an invalid attribute type."
        );
      }
    }

    if (array_key_exists('type', $field)) {
      if (
        !is_string($field['type']) ||
        !in_array(
          $field['type'],
          [
            'OField::NUMBER',
            'OField::TEXT',
            'OField::LONGTEXT',
            'OField::FLOAT',
            'OField::BOOL',
            'OField::DATE',
            'OField::DECIMAL'
          ],
          true
        )
      ) {
        throw new \InvalidArgumentException(
          "Field '{$field['name']}' contains an invalid field type."
        );
      }
    }

    if (
      array_key_exists('type', $field) &&
      array_key_exists('attribute_type', $field)
    ) {
      $expected_attribute_type = match ($field['type']) {
        'OField::NUMBER' => 'int',
        'OField::FLOAT' => 'float',
        'OField::BOOL' => 'bool',
        'OField::TEXT',
        'OField::LONGTEXT',
        'OField::DATE',
        'OField::DECIMAL' => 'string'
      };

      if (
        $field['attribute_type'] !==
        $expected_attribute_type
      ) {
        throw new \InvalidArgumentException(
          "Field '{$field['name']}' type '{$field['type']}' is incompatible with PHP attribute type '{$field['attribute_type']}'."
        );
      }
    }

    $field_type = $field['type']
      ?? null;

    $has_precision = array_key_exists(
      'precision',
      $field
    );

    $has_scale = array_key_exists(
      'scale',
      $field
    );

    if ($field_type === 'OField::DECIMAL') {
      if ($field['decorator'] === 'OPK') {
        throw new \InvalidArgumentException(
          "Primary key field '{$field['name']}' cannot use OField::DECIMAL."
        );
      }

      if ($field['decorator'] !== 'OField') {
        throw new \InvalidArgumentException(
          "DECIMAL field '{$field['name']}' must use the OField decorator."
        );
      }

      $precision = $field['precision']
        ?? OField::DECIMAL_DEFAULT_PRECISION;

      $scale = $field['scale']
        ?? OField::DECIMAL_DEFAULT_SCALE;

      if (
        !is_int($precision) ||
        $precision < 1 ||
        $precision > OField::DECIMAL_MAX_PRECISION
      ) {
        throw new \InvalidArgumentException(
          "DECIMAL field '{$field['name']}' precision must be between 1 and "
            . OField::DECIMAL_MAX_PRECISION
            . '.'
        );
      }

      if (
        !is_int($scale) ||
        $scale < 0 ||
        $scale > OField::DECIMAL_MAX_SCALE
      ) {
        throw new \InvalidArgumentException(
          "DECIMAL field '{$field['name']}' scale must be between 0 and "
            . OField::DECIMAL_MAX_SCALE
            . '.'
        );
      }

      if ($scale > $precision) {
        throw new \InvalidArgumentException(
          "DECIMAL field '{$field['name']}' scale cannot be greater than its precision."
        );
      }

      if (
        array_key_exists(
          'default',
          $field
        ) &&
        $field['default'] !== null
      ) {
        if (!is_string($field['default'])) {
          throw new \InvalidArgumentException(
            "DECIMAL field '{$field['name']}' default value must be a string."
          );
        }

        $this->validateDecimalValue(
          $field['name'],
          $field['default'],
          $precision,
          $scale
        );
      }
    } elseif (
      $has_precision ||
      $has_scale
    ) {
      throw new \InvalidArgumentException(
        "Precision and scale can only be defined for OField::DECIMAL field '{$field['name']}'."
      );
    }

    if ($field['decorator'] === 'OPK') {
      $field_type = $field['type']
        ?? 'OField::NUMBER';

      $is_incremental = $field['incr']
        ?? true;

      if (
        $is_incremental &&
        $field_type !== 'OField::NUMBER'
      ) {
        throw new \InvalidArgumentException(
          "Auto-increment primary key '{$field['name']}' must use OField::NUMBER."
        );
      }

      if (
        $is_incremental &&
        array_key_exists(
          'default',
          $field
        ) &&
        $field['default'] !== null
      ) {
        throw new \InvalidArgumentException(
          "Auto-increment primary key '{$field['name']}' cannot define a default value."
        );
      }
    }

    foreach (['nullable', 'incr', 'visible'] as $key) {
      if (
        array_key_exists($key, $field) &&
        !is_bool($field[$key])
      ) {
        throw new \InvalidArgumentException(
          "Field '{$field['name']}' property '{$key}' must be boolean."
        );
      }
    }

    if (
      array_key_exists('max', $field) &&
      (
        !is_int($field['max']) ||
        $field['max'] <= 0
      )
    ) {
      throw new \InvalidArgumentException(
        "Field '{$field['name']}' property 'max' must be a positive integer."
      );
    }

    if (
      array_key_exists('comment', $field) &&
      !is_string($field['comment'])
    ) {
      throw new \InvalidArgumentException(
        "Field '{$field['name']}' property 'comment' must be a string."
      );
    }

    if (array_key_exists('ref', $field)) {
      if (!is_string($field['ref'])) {
        throw new \InvalidArgumentException(
          "Field '{$field['name']}' property 'ref' must be a string."
        );
      }

      if (
        $field['ref'] !== '' &&
        preg_match(
          '/^[A-Za-z_][A-Za-z0-9_]*\.[A-Za-z_][A-Za-z0-9_]*$/D',
          $field['ref']
        ) !== 1
      ) {
        throw new \InvalidArgumentException(
          "Field '{$field['name']}' contains an invalid reference."
        );
      }
    }

    if (
      array_key_exists('default', $field) &&
      $field['default'] !== null &&
      !is_string($field['default']) &&
      !is_int($field['default']) &&
      !is_float($field['default']) &&
      !is_bool($field['default'])
    ) {
      throw new \InvalidArgumentException(
        "Field '{$field['name']}' contains an unsupported default value."
      );
    }
  }

  /**
   * Validate a model reference definition.
   *
   * @param array<array-key, mixed> $ref Reference definition to validate.
   *
   * @return void
   *
   * @throws \InvalidArgumentException If the reference definition is invalid.
   */
  private function validateRef(array $ref): void {
    $required_keys = [
      'to',
      'field_from',
      'field_to'
    ];

    if (
      count($ref) !== count($required_keys) ||
      array_diff(
        array_keys($ref),
        $required_keys
      ) !== []
    ) {
      throw new \InvalidArgumentException(
        'Every model reference must contain only "to", "field_from" and "field_to".'
      );
    }

    foreach ($required_keys as $key) {
      if (
        !array_key_exists($key, $ref) ||
        !is_string($ref[$key]) ||
        !$this->isValidIdentifier($ref[$key])
      ) {
        throw new \InvalidArgumentException(
          "Model reference property '{$key}' must be a valid identifier."
        );
      }
    }
  }

  /**
   * Validate a model table definition.
   *
   * @param array<array-key, mixed> $table Table definition to validate.
   *
   * @return void
   *
   * @throws \InvalidArgumentException If the table definition is invalid.
   */
  private function validateTable(array $table): void {
    foreach (array_keys($table) as $key) {
      if (
        !is_string($key) ||
        !in_array(
          $key,
          [
            'name',
            'fields',
            'refs'
          ],
          true
        )
      ) {
        throw new \InvalidArgumentException(
          "Unsupported model property '{$key}'."
        );
      }
    }

    if (
      !array_key_exists('name', $table) ||
      !is_string($table['name']) ||
      !$this->isValidIdentifier($table['name'])
    ) {
      throw new \InvalidArgumentException(
        'Every model must contain a valid "name".'
      );
    }

    if (
      !array_key_exists('fields', $table) ||
      !is_array($table['fields']) ||
      !array_is_list($table['fields']) ||
      $table['fields'] === []
    ) {
      throw new \InvalidArgumentException(
        "Model '{$table['name']}' must contain a non-empty field list."
      );
    }

    foreach ($table['fields'] as $field) {
      if (!is_array($field)) {
        throw new \InvalidArgumentException(
          "Every field in model '{$table['name']}' must be an array."
        );
      }

      $this->validateField($field);
    }

    if (array_key_exists('refs', $table)) {
      if (
        !is_array($table['refs']) ||
        !array_is_list($table['refs'])
      ) {
        throw new \InvalidArgumentException(
          "Model '{$table['name']}' property 'refs' must be a list."
        );
      }

      foreach ($table['refs'] as $ref) {
        if (!is_array($ref)) {
          throw new \InvalidArgumentException(
            "Every reference in model '{$table['name']}' must be an array."
          );
        }

        $this->validateRef($ref);
      }
    }
  }

  /**
   * Load models from a JSON definition.
   *
   * @param string $content JSON content containing the model definitions.
   *
   * @return void
   *
   * @throws \JsonException If the content contains invalid JSON.
   * @throws \InvalidArgumentException If the model definition has an invalid structure.
   */
  private function loadFile(string $content): void {
    $data = json_decode(
      $content,
      true,
      512,
      JSON_THROW_ON_ERROR
    );

    if (
      !is_array($data) ||
      !array_key_exists('model', $data) ||
      !is_array($data['model']) ||
      !array_is_list($data['model'])
    ) {
      throw new \InvalidArgumentException(
        'The model definition must contain a "model" list.'
      );
    }

    foreach ($data['model'] as $table) {
      if (!is_array($table)) {
        throw new \InvalidArgumentException(
          'Each model definition must be an array.'
        );
      }

      $this->validateTable($table);
    }

    /*
		 * Validate the complete document before generating any file.
		 *
		 * This avoids leaving a partially generated model set if one of the
		 * later definitions is invalid.
		 */
    foreach ($data['model'] as $table) {
      $this->generateTable($table);
    }
  }

  /**
   * Generate a model class from a validated table definition.
   *
   * @param array{
   *     name: string,
   *     fields: list<array{
   *         name: string,
   *         decorator: string,
   *         attribute_type?: string,
   *         type?: string,
   *         nullable?: bool,
   *         default?: string|int|float|bool|null,
   *         max?: int,
   *         comment?: string,
   *         precision?: int,
   *         scale?: int,
   *         visible?: bool,
   *         ref?: string,
   *         incr?: bool
   *     }>,
   *     refs?: list<array{
   *         to: string,
   *         field_from: string,
   *         field_to: string
   *     }>
   * } $table Validated table definition.
   *
   * @return void
   */
  private function generateTable(array $table): void {
    $table_name = OTools::underscoresToCamelCase(
      $table['name'],
      true
    );

    $values = [
      'table_name' => $table_name,
      'class_file' => $this->getConfig()->getDir('app_model')
        . $table_name
        . '.php',
      'fields' => $table['fields'],
      'refs' => $table['refs'] ?? []
    ];

    $status = OBuild::addModelClass($values);

    switch ($status) {
      case 'ok': {
          echo OTools::getMessage(
            'TASK_GENERATE_MODEL_FROM_OK',
            [
              $values['table_name'],
              $values['class_file']
            ]
          ) . "\n";
        }
        break;

      case 'error-exists': {
          echo OTools::getMessage(
            'TASK_GENERATE_MODEL_FROM_ERROR_EXISTS',
            [
              $values['class_file']
            ]
          ) . "\n";
        }
        break;

      case 'error-pk': {
          echo OTools::getMessage(
            'TASK_GENERATE_MODEL_FROM_ERROR_PK',
            [
              $table['name']
            ]
          ) . "\n";
        }
        break;

      case 'error-created-at': {
          echo OTools::getMessage(
            'TASK_GENERATE_MODEL_FROM_ERROR_CREATED_AT',
            [
              $table['name']
            ]
          ) . "\n";
        }
        break;

      case 'error-updated-at': {
          echo OTools::getMessage(
            'TASK_GENERATE_MODEL_FROM_ERROR_UPDATED_AT',
            [
              $table['name']
            ]
          ) . "\n";
        }
        break;
    }
  }

  /**
   * Run the task.
   *
   * @param array<string, string|false> $options Task options. The "file" option
   *                                             contains the model definition
   *                                             file path.
   *
   * @return void
   *
   * @throws \InvalidArgumentException If the model file or its definitions are
   *                                   unsafe or invalid.
   * @throws \JsonException If the model file contains invalid JSON.
   * @throws \RuntimeException If the model definition file cannot be read.
   */
  public function run(array $options = []): void {
    if (
      !array_key_exists('file', $options) ||
      !is_string($options['file']) ||
      $options['file'] === ''
    ) {
      echo "\n"
        . "  "
        . $this->getColors()->getColoredString(
          OTools::getMessage(
            'TASK_GENERATE_MODEL_FROM_WARNING'
          ),
          'red'
        )
        . "\n\n";

      echo "  "
        . OTools::getMessage(
          'TASK_GENERATE_MODEL_FROM_CONTINUE'
        )
        . "\n\n";

      exit;
    }

    $base_path = realpath(
      $this->getConfig()->getDir('base')
    );

    $file_path = realpath(
      $this->getConfig()->getDir('base')
        . $options['file']
    );

    if ($base_path === false) {
      throw new \RuntimeException(
        'Could not resolve the project base directory.'
      );
    }

    if (
      $file_path === false ||
      !is_file($file_path)
    ) {
      echo "\n"
        . "  "
        . $this->getColors()->getColoredString(
          OTools::getMessage(
            'TASK_GENERATE_MODEL_FROM_WARNING'
          ),
          'red'
        )
        . "\n\n";

      echo "  "
        . OTools::getMessage(
          'TASK_GENERATE_MODEL_FROM_FILE_NOT_FOUND'
        )
        . "\n\n";

      exit;
    }

    $normalized_base_path = rtrim(
      str_replace(
        '\\',
        '/',
        $base_path
      ),
      '/'
    );

    $normalized_file_path = str_replace(
      '\\',
      '/',
      $file_path
    );

    if (PHP_OS_FAMILY === 'Windows') {
      $normalized_base_path = strtolower(
        $normalized_base_path
      );

      $normalized_file_path = strtolower(
        $normalized_file_path
      );
    }

    if (
      !str_starts_with(
        $normalized_file_path,
        $normalized_base_path . '/'
      )
    ) {
      throw new \InvalidArgumentException(
        'The model definition file must be located inside the project directory.'
      );
    }

    $content = file_get_contents($file_path);

    if ($content === false) {
      throw new \RuntimeException(
        "Unable to read model definition file '{$file_path}'."
      );
    }

    $this->loadFile($content);
  }
}
