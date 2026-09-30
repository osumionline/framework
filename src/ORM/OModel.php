<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\ORM;

use PDO;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use Exception;

abstract class OModel {
  // Class properties
  protected bool $initialized = false;
  protected bool $is_new_record = true;
  /**
   * @var array<string, mixed>
   */
  protected array $original_values = [];
  /**
   * @var array<class-string, array{
   *     table_name: string,
   *     fields: array<string, array{
   *         name: string,
   *         type: string,
   *         nullable: bool,
   *         default: mixed,
   *         max: int|null,
   *         comment: string,
   *         visible: bool,
   *         ref: string|null,
   *         primary?: bool,
   *         auto_increment?: bool
   *     }>,
   *     primary_key: list<string>,
   *     created_at: string|null,
   *     updated_at: string|null,
   *     deleted_at: string|null
   * }>
   */
  protected static array $schema_cache = [];
  /**
   * @var array<class-string, bool>
   */
  protected static array $model_validated = [];
  /**
   * Cached database result rows indexed by query cache key.
   *
   * @var array<string, list<array<string, mixed>>>
   */
  protected static array $results_cache = [];

  /**
   * Create a model instance.
   *
   * @param array<array-key, mixed> $data Initial field values.
   *
   * @throws \InvalidArgumentException If an input key does not match a model
   *                                   field.
   * @throws \UnexpectedValueException If a field value cannot be normalized.
   */
  public function __construct(array $data = []) {
    $this->validateModel();
    $this->initializeModel();
    $this->assignValues($data);
  }

  /**
   * Check model validation, only done on first instantiation of a model class
   *
   * @return void
   */
  protected function validateModel(): void {
    $class_name = static::class;

    // Si el modelo ya ha sido validado, salimos
    if (isset(self::$model_validated[$class_name]) && self::$model_validated[$class_name]) {
      return;
    }

    $reflection = new ReflectionClass($this);
    $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

    $has_primary_key = false;
    $has_created_at = 0;
    $has_updated_at = 0;
    $has_deleted_at = 0;

    foreach ($properties as $property) {
      $attributes = $property->getAttributes();
      $orm_attribute_count = 0;

      foreach ($attributes as $attribute) {
        $attr_instance = $attribute->newInstance();

        if ($attr_instance instanceof OPK) {
          $orm_attribute_count++;
          $has_primary_key = true;

          $this->validateFieldType(
            $property,
            $attr_instance
          );
        } elseif ($attr_instance instanceof OField) {
          $orm_attribute_count++;

          $this->validateFieldType(
            $property,
            $attr_instance
          );
        } elseif ($attr_instance instanceof OCreatedAt) {
          $orm_attribute_count++;
          $has_created_at++;

          $this->validateTimestampFieldType(
            $property
          );
        } elseif ($attr_instance instanceof OUpdatedAt) {
          $orm_attribute_count++;
          $has_updated_at++;

          $this->validateTimestampFieldType(
            $property
          );
        } elseif ($attr_instance instanceof ODeletedAt) {
          $orm_attribute_count++;
          $has_deleted_at++;

          $this->validateTimestampFieldType(
            $property
          );
        }
      }

      if ($orm_attribute_count > 1) {
        throw new Exception(
          "Model property '{$property->getName()}' cannot define more than one ORM field attribute."
        );
      }
    }

    // Validate primary key
    if (!$has_primary_key) {
      throw new Exception("Model '{$class_name}' doesn't have a primary key field (OPK) defined.");
    }

    // Validate mandatory created_at and updated_at fields
    if ($has_created_at === 0) {
      throw new Exception("Model '{$class_name}' doesn't have a created at field (OCreatedAt) defined.");
    }
    if ($has_created_at > 1) {
      throw new Exception("Model '{$class_name}' can't have more than one created at field (OCreatedAt) defined.");
    }
    if ($has_updated_at === 0) {
      throw new Exception("Model '{$class_name}' doesn't have an updated at field (OUpdatedAt) defined.");
    }
    if ($has_updated_at > 1) {
      throw new Exception("Model '{$class_name}' can't have more than one updated at field (OUpdatedAt) defined.");
    }
    if ($has_deleted_at > 1) {
      throw new Exception("Model '{$class_name}' can't have more than one deleted at field (ODeletedAt) defined.");
    }

    // Mark model as validated
    self::$model_validated[$class_name] = true;
  }

  /**
   * Normalize a value before assigning it to a model property.
   *
   * Database drivers may return numeric and boolean values using different
   * scalar representations. This method converts supported representations to
   * the PHP type defined by the ORM schema.
   *
   * @param string $field_name Field name.
   * @param string $field_type ORM field type.
   * @param mixed $value Raw field value.
   *
   * @return string|int|float|bool|null Normalized field value.
   *
   * @throws \UnexpectedValueException If the value cannot be converted to the
   *                                   expected ORM field type.
   */
  protected function normalizeModelValue(
    string $field_name,
    string $field_type,
    mixed $value
  ): string|int|float|bool|null {
    if ($value === null) {
      return null;
    }

    switch ($field_type) {
      case OField::NUMBER:
        if (is_int($value)) {
          return $value;
        }

        if (
          is_string($value) &&
          preg_match(
            '/^[+-]?\d+$/D',
            $value
          ) === 1
        ) {
          $normalized = filter_var(
            $value,
            FILTER_VALIDATE_INT
          );

          if ($normalized !== false) {
            return $normalized;
          }
        }

        break;

      case OField::FLOAT:
        if (is_int($value)) {
          return (float) $value;
        }

        if (
          is_float($value) &&
          is_finite($value)
        ) {
          return $value;
        }

        if (is_string($value)) {
          $normalized = filter_var(
            $value,
            FILTER_VALIDATE_FLOAT
          );

          if (
            $normalized !== false &&
            is_finite((float) $normalized)
          ) {
            return (float) $normalized;
          }
        }

        break;

      case OField::BOOL:
        if (is_bool($value)) {
          return $value;
        }

        if (
          is_int($value) ||
          is_string($value)
        ) {
          $normalized = filter_var(
            $value,
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
          );

          if ($normalized !== null) {
            return $normalized;
          }
        }

        break;

      case OField::TEXT:
      case OField::LONGTEXT:
      case OField::DATE:
        if (is_string($value)) {
          return $value;
        }

        break;

      default:
        throw new \UnexpectedValueException(
          "Unknown ORM field type '{$field_type}' for field '{$field_name}'."
        );
    }

    throw new \UnexpectedValueException(
      "Value for field '{$field_name}' cannot be converted to ORM type '{$field_type}'."
    );
  }

  /**
   * Validate that an ORM field definition matches its PHP property type.
   *
   * Model properties must use nullable built-in scalar types because model
   * instances may temporarily contain null before being persisted.
   *
   * @param ReflectionProperty $property Model property.
   * @param OField|OPK $field Field definition.
   *
   * @return void
   *
   * @throws Exception If the property type or ORM field type is invalid.
   */
  protected function validateFieldType(
    ReflectionProperty $property,
    OField|OPK $field
  ): void {
    $field_name = $property->getName();
    $property_type = $property->getType();

    if (
      !$property_type instanceof ReflectionNamedType ||
      !$property_type->isBuiltin()
    ) {
      throw new Exception(
        "The property '{$field_name}' must have a supported built-in type."
      );
    }

    if (!$property_type->allowsNull()) {
      throw new Exception(
        "The property '{$field_name}' must be nullable."
      );
    }

    $property_type_name = $property_type->getName();

    if (
      $field instanceof OField &&
      $field->type === null
    ) {
      $field->type = match ($property_type_name) {
        'string' => OField::TEXT,
        'int' => OField::NUMBER,
        'float' => OField::FLOAT,
        'bool' => OField::BOOL,
        default => throw new Exception(
          "Unsupported type for property '{$field_name}': {$property_type_name}."
        )
      };
    }

    $type = $field->type;

    if ($field instanceof OPK) {
      if (
        $field->incr &&
        $type !== OField::NUMBER
      ) {
        throw new Exception(
          "Auto-increment primary key '{$field_name}' must use OField::NUMBER."
        );
      }

      if (
        $field->incr &&
        $field->default !== null
      ) {
        throw new Exception(
          "Auto-increment primary key '{$field_name}' cannot define a default value."
        );
      }
    }

    if (
      $type === OField::TEXT &&
      $field->max <= 0
    ) {
      throw new Exception(
        "Text field '{$field_name}' must define a maximum length greater than zero."
      );
    }

    switch ($type) {
      case OField::NUMBER:
        if ($property_type_name !== 'int') {
          throw new Exception(
            "The type of the property '{$field_name}' does not match the expected type '{$type}'."
          );
        }
        break;

      case OField::FLOAT:
        if ($property_type_name !== 'float') {
          throw new Exception(
            "The type of the property '{$field_name}' does not match the expected type '{$type}'."
          );
        }
        break;

      case OField::TEXT:
      case OField::LONGTEXT:
        if ($property_type_name !== 'string') {
          throw new Exception(
            "The type of the property '{$field_name}' does not match the expected type '{$type}'."
          );
        }
        break;

      case OField::BOOL:
        if ($property_type_name !== 'bool') {
          throw new Exception(
            "The type of the property '{$field_name}' does not match the expected type 'bool'."
          );
        }
        break;

      case OField::DATE:
        if ($property_type_name !== 'string') {
          throw new Exception(
            "The type of the property '{$field_name}' does not match the expected type 'string' for dates."
          );
        }
        break;

      default:
        throw new Exception(
          "Unknown ORM field type '{$type}' for property '{$field_name}'."
        );
    }
  }

  /**
   * Validate an automatic timestamp property.
   *
   * Timestamp fields must be nullable strings because their values are managed
   * automatically by the ORM.
   *
   * @param ReflectionProperty $property Timestamp property.
   *
   * @return void
   *
   * @throws Exception If the property is not declared as a nullable string.
   */
  protected function validateTimestampFieldType(
    ReflectionProperty $property
  ): void {
    $field_name = $property->getName();
    $property_type = $property->getType();

    if (
      !$property_type instanceof ReflectionNamedType ||
      !$property_type->isBuiltin() ||
      $property_type->getName() !== 'string' ||
      !$property_type->allowsNull()
    ) {
      throw new Exception(
        "The timestamp property '{$field_name}' must be declared as ?string."
      );
    }
  }

  /**
   * Apply configured default values to a new model instance.
   *
   * Defaults are applied only when the current field value is null. Automatic
   * increment primary keys are excluded because their value is generated by the
   * database.
   *
   * @return void
   *
   * @throws \UnexpectedValueException If a default value cannot be converted to
   *                                   the field ORM type.
   */
  protected function applyDefaults(): void {
    $schema = self::$schema_cache[static::class];

    foreach ($schema['fields'] as $field_name => $field) {
      if (
        !empty($field['primary']) &&
        !empty($field['auto_increment'])
      ) {
        continue;
      }

      if (
        $this->$field_name !== null ||
        $field['default'] === null
      ) {
        continue;
      }

      $this->$field_name = $this->normalizeModelValue(
        $field_name,
        $field['type'],
        $field['default']
      );
    }
  }

  /**
   * Initialize model class schema and properties
   *
   * @return void
   */
  protected function initializeModel(): void {
    if ($this->initialized) {
      return;
    }

    $class_name = static::class;

    // If schema is already on cache, don't process it again
    if (!isset(self::$schema_cache[$class_name])) {
      $reflection = new ReflectionClass($this);
      $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

      $schema = [
        'table_name' => $this->getTableName(),
        'fields' => [],
        'primary_key' => [],
        'created_at' => null,
        'updated_at' => null,
        'deleted_at' => null
      ];

      foreach ($properties as $property) {
        $field_name = $property->getName();
        $field_schema = [
          'name' => $field_name,
          'type' => null,
          'nullable' => true,
          'default' => null,
          'max' => null,
          'comment' => '',
          'visible' => true,
          'ref' => null
        ];

        // Get property attributes
        $attributes = $property->getAttributes();
        foreach ($attributes as $attribute) {
          $attr_instance = $attribute->newInstance();

          if ($attr_instance instanceof OPK) {
            $field_schema['type'] = $attr_instance->type;
            $field_schema['nullable'] = $attr_instance->nullable;
            $field_schema['default'] = $attr_instance->default;
            $field_schema['max'] = $attr_instance->max;
            $field_schema['primary'] = true;
            $field_schema['auto_increment'] = $attr_instance->incr;
            $field_schema['ref'] = $attr_instance->ref;
            $field_schema['comment'] = $attr_instance->comment;

            $schema['primary_key'][] = $field_name;
          } elseif ($attr_instance instanceof OField) {
            $field_schema['type'] = $attr_instance->type;
            $field_schema['nullable'] = $attr_instance->nullable;
            $field_schema['default'] = $attr_instance->default;
            $field_schema['max'] = $attr_instance->max;
            $field_schema['visible'] = $attr_instance->visible;
            $field_schema['ref'] = $attr_instance->ref;
            $field_schema['comment'] = $attr_instance->comment;
          } elseif ($attr_instance instanceof OCreatedAt) {
            $field_schema['type'] = OField::DATE;
            $field_schema['comment'] = $attr_instance->comment;
            $schema['created_at'] = $field_name;
          } elseif ($attr_instance instanceof OUpdatedAt) {
            $field_schema['type'] = OField::DATE;
            $field_schema['comment'] = $attr_instance->comment;
            $schema['updated_at'] = $field_name;
          } elseif ($attr_instance instanceof ODeletedAt) {
            $field_schema['type'] = OField::DATE;
            $field_schema['comment'] = $attr_instance->comment;
            $schema['deleted_at'] = $field_name;
          }
        }

        // Get the type of the field if it is not defined in the attribute
        if ($field_schema['type'] === null) {
          $property_type = $property->getType();
          if ($property_type instanceof ReflectionNamedType) {
            $type_name = $property_type->getName();
            switch ($type_name) {
              case 'string':
                $field_schema['type'] = OField::TEXT;
                break;
              case 'int':
                $field_schema['type'] = OField::NUMBER;
                break;
              case 'float':
                $field_schema['type'] = OField::FLOAT;
                break;
              case 'bool':
                $field_schema['type'] = OField::BOOL;
                break;
              default:
                throw new Exception("Unsupported type for field '{$field_name}': {$type_name}.");
            }
          } else {
            throw new Exception("The type of field '{$field_name}' could not be determined and was not specified.");
          }
        }

        // Get default value of property if not defined in attribute
        $default_value = $property->getDefaultValue();
        if ($field_schema['default'] === null && $default_value !== null) {
          $field_schema['default'] = $default_value;
        }

        if ($field_schema['default'] !== null) {
          $field_schema['default'] = $this->normalizeModelValue(
            $field_name,
            $field_schema['type'],
            $field_schema['default']
          );
        }

        $schema['fields'][$field_name] = $field_schema;
      }

      // If there is more than one field of type OPK, set autoIncrement to false
      if (count($schema['primary_key']) > 1) {
        foreach ($schema['primary_key'] as $pk_field) {
          $schema['fields'][$pk_field]['auto_increment'] = false;
        }
      }

      self::$schema_cache[$class_name] = $schema;
    }

    $this->initialized = true;
  }

  /**
   * Synchronize the persisted value snapshot with the current model values.
   *
   * @return void
   */
  protected function syncOriginalValues(): void {
    $schema = self::$schema_cache[static::class];

    foreach ($schema['fields'] as $field_name => $field) {
      $this->original_values[$field_name] = $this->$field_name;
    }
  }

  /**
   * Assign field values to the model.
   *
   * Input values are normalized according to the ORM schema before being
   * assigned to their typed PHP properties.
   *
   * @param array<array-key, mixed> $data Field values.
   *
   * @return void
   *
   * @throws \InvalidArgumentException If an input key does not match a model
   *                                   field.
   * @throws \UnexpectedValueException If a field value cannot be converted to
   *                                   its ORM type.
   */
  protected function assignValues(array $data): void {
    $schema = self::$schema_cache[static::class];

    foreach ($data as $field_name => $value) {
      if (
        !is_string($field_name) ||
        !array_key_exists(
          $field_name,
          $schema['fields']
        )
      ) {
        throw new \InvalidArgumentException(
          "Unknown model field '{$field_name}'."
        );
      }
    }

    foreach ($schema['fields'] as $field_name => $field) {
      $value = array_key_exists(
        $field_name,
        $data
      )
        ? $this->normalizeModelValue(
          $field_name,
          $field['type'],
          $data[$field_name]
        )
        : null;

      $this->$field_name = $value;
      $this->original_values[$field_name] = $value;
    }

    $is_existing_record = true;
    foreach ($schema['primary_key'] as $primary_key) {
      if ($this->$primary_key === null) {
        $is_existing_record = false;
        break;
      }
    }

    $this->is_new_record = ! $is_existing_record;
  }

  /**
   * Get name of the table
   *
   * @return string Name of the table
   */
  protected static function getTableName(): string {
    $class_name = static::class;
    $parts = explode('\\', $class_name);
    $short_class_name = end($parts);

    // Convert CamelCase to snake_case
    return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', lcfirst($short_class_name)));
  }

  /**
   * Get the model primary key fields.
   *
   * @return list<string> Primary key field names.
   */
  protected static function getPrimaryKey(): array {
    $schema = self::$schema_cache[static::class];
    return $schema['primary_key'];
  }

  /**
   * Validate current record object before saving data
   *
   * @return void
   */
  protected function validate(): void {
    $schema = self::$schema_cache[static::class];
    $fields = $schema['fields'];

    foreach ($fields as $field_name => $field) {
      $value = $this->$field_name;

      // Allow null value if field is nullable
      if ($value === null) {
        /*
         * An auto-increment primary key is legitimately null while creating a new
         * record because its value will be assigned by the database.
         */
        if (
          !empty($field['primary']) &&
          $this->is_new_record &&
          !empty($field['auto_increment'])
        ) {
          continue;
        }

        if (!empty($field['primary'])) {
          throw new Exception(
            "Primary key field '{$field_name}' cannot be null."
          );
        }

        if (!$field['nullable']) {
          throw new Exception(
            "Field '{$field_name}' cannot be null."
          );
        }

        continue;
      }

      // Validate the data type
      switch ($field['type']) {
        case OField::NUMBER:
          if (!is_int($value)) {
            throw new Exception("The '{$field_name}' field must be an integer.");
          }
          break;
        case OField::FLOAT:
          if (!is_float($value) && !is_int($value)) {
            throw new Exception("The '{$field_name}' field must be a decimal number.");
          }
          break;
        case OField::TEXT:
          if (!is_string($value)) {
            throw new Exception("The '{$field_name}' field must be a text string.");
          }
          if (isset($field['max']) && strlen($value) > $field['max']) {
            throw new Exception("The '{$field_name}' field cannot be longer than {$field['max']} characters.");
          }
          break;
        case OField::LONGTEXT:
          if (!is_string($value)) {
            throw new Exception("The '{$field_name}' field must be a text string.");
          }
          break;
        case OField::BOOL:
          if (!is_bool($value)) {
            throw new Exception("The '{$field_name}' field must be a boolean value.");
          }
          break;
        case OField::DATE:
          if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
            throw new Exception("The '{$field_name}' field must be in a valid date and time format (Y-m-d H:i:s).");
          }
          break;
        default:
          throw new Exception("Unknown field type for '{$field_name}'.");
      }
    }
  }

  /**
   * Generate a cache key for an ORM query.
   *
   * @param string $table Table name.
   * @param string $method Query method.
   * @param array<string, mixed> $conditions Query conditions.
   * @param array{
   *     order_by?: string,
   *     limit?: int|string,
   *     offset?: int|string
   * } $options Query options.
   *
   * @return string Generated cache key.
   *
   * @throws \JsonException If conditions or options cannot be encoded as JSON.
   */
  protected static function generateCacheKey(
    string $table,
    string $method,
    array $conditions,
    array $options = []
  ): string {
    $encoded_conditions = json_encode(
      $conditions,
      JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );

    $encoded_options = json_encode(
      $options,
      JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );

    return $table . ':' . $method . ':' . $encoded_conditions . ':' . $encoded_options;
  }

  /**
   * Hydrate database rows into model instances.
   *
   * @param list<array<string, mixed>> $rows Database rows.
   *
   * @return list<static> Hydrated model instances.
   */
  protected static function hydrateRows(
    array $rows
  ): array {
    $instances = [];

    foreach ($rows as $row) {
      $instances[] = static::from(
        $row
      );
    }

    return $instances;
  }

  /**
   * Clear results cache
   *
   * @return void
   */
  protected static function clearResultsCache(): void {
    // Clear cache to mantain consistency
    self::$results_cache = [];
  }

  /**
   * Validate a SQL identifier before interpolating it in a query.
   *
   * @param string $identifier Identifier to validate
   *
   * @param string $context Context used in the exception message
   *
   * @return string Validated identifier
   */
  protected static function validateSqlIdentifier(string $identifier, string $context = 'identifier'): string {
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
      throw new Exception("Invalid SQL {$context} '{$identifier}'.");
    }

    return $identifier;
  }

  /**
   * Parse a non-negative integer used on LIMIT or OFFSET clauses.
   *
   * @param mixed $value Value to parse
   *
   * @param string $context Context used in the exception message
   *
   * @return int Parsed value
   */
  protected static function parseNonNegativeInteger(mixed $value, string $context): int {
    if (is_int($value)) {
      $parsed_value = $value;
    } elseif (is_string($value) && preg_match('/^\d+$/', $value)) {
      $parsed_value = (int) $value;
    } else {
      throw new Exception("Invalid {$context} value.");
    }

    if ($parsed_value < 0) {
      throw new Exception("Invalid {$context} value.");
    }

    return $parsed_value;
  }

  /**
   * Build a WHERE clause and its bound parameters.
   *
   * @param array<string, mixed> $conditions Query conditions indexed by field.
   *
   * @return array{
   *     clause: string,
   *     params: array<string, mixed>
   * } Built WHERE clause and parameters.
   *
   * @throws \Exception If a field name is invalid.
   */
  protected static function buildWhereClause(array $conditions): array {
    $params = [];
    $where_clauses = [];

    foreach ($conditions as $field => $value) {
      if (!is_string($field)) {
        throw new Exception("Invalid SQL field '{$field}'.");
      }

      $field = self::validateSqlIdentifier($field, 'field');

      if ($value === null) {
        $where_clauses[] = "`{$field}` IS NULL";
      } else {
        $where_clauses[] = "`{$field}` = :{$field}";
        $params[":{$field}"] = $value;
      }
    }

    return [
      'clause' => implode(' AND ', $where_clauses),
      'params' => $params
    ];
  }

  /**
   * Apply supported SQL query options.
   *
   * @param string $sql Base SQL query.
   * @param array{
   *     order_by?: string,
   *     limit?: int|string,
   *     offset?: int|string
   * } $options Query options.
   *
   * @return string SQL query with applied options.
   *
   * @throws \Exception If an option contains an invalid SQL identifier or
   *                    numeric value.
   * @throws \InvalidArgumentException If an unsupported option or value type is
   *                                   supplied.
   */
  protected static function applyQueryOptions(string $sql, array $options): string {
    self::validateQueryOptions($options);

    if (isset($options['order_by'])) {
      [
        $field,
        $direction
      ] = array_pad(
        explode(
          '#',
          $options['order_by']
        ),
        2,
        'ASC'
      );

      $field = self::validateSqlIdentifier(trim($field), 'order field');

      $direction = strtoupper(trim($direction));

      if ($direction !== 'ASC' && $direction !== 'DESC') {
        $direction = 'ASC';
      }

      $sql .= " ORDER BY `{$field}` {$direction}";
    }

    $has_limit = isset($options['limit']);

    if ($has_limit) {
      if (
        is_int($options['limit']) ||
        (
          is_string($options['limit']) &&
          preg_match(
            '/^\d+$/',
            $options['limit']
          )
        )
      ) {
        $count = null;

        $start = self::parseNonNegativeInteger($options['limit'], 'limit');
      } else {
        [
          $start,
          $count
        ] = array_pad(
          explode(
            '#',
            $options['limit']
          ),
          2,
          null
        );

        $start = self::parseNonNegativeInteger($start, 'limit start');

        $count = self::parseNonNegativeInteger($count, 'limit count');
      }

      if ($count !== null) {
        $sql .= " LIMIT {$start}, {$count}";
      } else {
        $sql .= " LIMIT {$start}";
      }
    }

    if (isset($options['offset'])) {
      $offset = self::parseNonNegativeInteger(
        $options['offset'],
        'offset'
      );

      if (!$has_limit) {
        $sql .= ' LIMIT 18446744073709551615';
      }

      $sql .= " OFFSET {$offset}";
    }

    return $sql;
  }

  /**
   * Quote a value for generated SQL.
   *
   * @param mixed $value Value to quote
   *
   * @return string SQL representation of the value
   */
  protected static function quoteSqlValue(mixed $value): string {
    if ($value === null) {
      return 'NULL';
    }

    if (is_bool($value)) {
      return $value ? '1' : '0';
    }

    if (is_int($value) || is_float($value)) {
      return (string) $value;
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
  }

  /**
   * Validate ORM query options.
   *
   * Supported options:
   * - order_by: Field and optional direction using "field#ASC" or "field#DESC".
   * - limit: Maximum number of rows or "start#count".
   * - offset: Number of rows to skip.
   *
   * @param array<array-key, mixed> $options Query options to validate.
   *
   * @return void
   *
   * @throws \InvalidArgumentException If an option name or value type is invalid.
   */
  protected static function validateQueryOptions(
    array $options
  ): void {
    $allowed_options = [
      'order_by',
      'limit',
      'offset'
    ];

    foreach ($options as $key => $value) {
      if (
        !is_string($key) ||
        !in_array(
          $key,
          $allowed_options,
          true
        )
      ) {
        throw new \InvalidArgumentException(
          "Unsupported ORM query option '{$key}'."
        );
      }

      if (
        $key === 'order_by' &&
        !is_string($value)
      ) {
        throw new \InvalidArgumentException(
          "ORM query option 'order_by' must be a string."
        );
      }

      if (
        (
          $key === 'limit' ||
          $key === 'offset'
        ) &&
        !is_int($value) &&
        !is_string($value)
      ) {
        throw new \InvalidArgumentException(
          "ORM query option '{$key}' must be an integer or string."
        );
      }
    }
  }

  /**
   * Create a new model instance.
   *
   * The model is always marked as a new record regardless of whether primary
   * key values are provided in the initial data.
   *
   * @param array<array-key, mixed> $data Initial field values.
   *
   * @return static New model instance.
   *
   * @throws \InvalidArgumentException If an input key does not match a model
   *                                   field.
   * @throws \UnexpectedValueException If a field value cannot be normalized.
   */
  public static function create(array $data = []): static {
    $instance = new static($data);
    $instance->is_new_record = true;

    return $instance;
  }

  /**
   * Create a model instance representing an existing persisted record.
   *
   * Every primary key field must have a value.
   *
   * @param array<array-key, mixed> $data Persisted record values.
   *
   * @return static Model instance.
   *
   * @throws \InvalidArgumentException If an input field is invalid or any
   *                                   primary key value is missing.
   * @throws \UnexpectedValueException If a field value cannot be normalized.
   */
  public static function from(array $data): static {
    $instance = new static($data);
    $schema = self::$schema_cache[static::class];

    foreach ($schema['primary_key'] as $primary_key) {
      if ($instance->$primary_key === null) {
        throw new \InvalidArgumentException(
          "Cannot create an existing model instance without primary key field '{$primary_key}'."
        );
      }
    }

    $instance->is_new_record = false;

    return $instance;
  }

  /**
   * Find one record matching the given conditions.
   *
   * @param array<string, mixed> $conditions Query conditions.
   *
   * @return static|null Matching model or null.
   */
  public static function findOne(
    array $conditions
  ): ?static {
    $results = static::where(
      $conditions,
      [
        'limit' => 1
      ]
    );

    return $results[0] ?? null;
  }

  /**
   * Find records matching the given conditions.
   *
   * @param array<string, mixed> $conditions Query conditions.
   * @param array{
   *     order_by?: string,
   *     limit?: int|string,
   *     offset?: int|string
   * } $options Query options.
   *
   * @return list<static> Matching model instances.
   *
   * @throws \InvalidArgumentException If query options are invalid.
   */
  public static function where(
    array $conditions,
    array $options = []
  ): array {
    self::validateQueryOptions(
      $options
    );

    if ($conditions === []) {
      return static::all(
        $options
      );
    }

    $table_name = self::getTableName();

    $cache_key = self::generateCacheKey(
      $table_name,
      'where',
      $conditions,
      $options
    );

    if (array_key_exists(
      $cache_key,
      self::$results_cache
    )) {
      return static::hydrateRows(
        self::$results_cache[$cache_key]
      );
    }

    $where = self::buildWhereClause(
      $conditions
    );

    $sql = "SELECT * FROM `{$table_name}` WHERE "
      . $where['clause'];

    $sql = self::applyQueryOptions(
      $sql,
      $options
    );

    $db = ODB::getInstance();

    $stmt = $db->prepare(
      $sql
    );

    $stmt->execute(
      $where['params']
    );

    /** @var list<array<string, mixed>> $rows */
    $rows = $stmt->fetchAll(
      PDO::FETCH_ASSOC
    );

    self::$results_cache[$cache_key] = $rows;

    return static::hydrateRows(
      $rows
    );
  }

  /**
   * Get all model records.
   *
   * @param array{
   *     order_by?: string,
   *     limit?: int|string,
   *     offset?: int|string
   * } $options Query options.
   *
   * @return list<static> Model instances.
   *
   * @throws \InvalidArgumentException If query options are invalid.
   */
  public static function all(
    array $options = []
  ): array {
    self::validateQueryOptions(
      $options
    );

    $table_name = self::getTableName();

    $cache_key = self::generateCacheKey(
      $table_name,
      'all',
      [],
      $options
    );

    if (array_key_exists(
      $cache_key,
      self::$results_cache
    )) {
      return static::hydrateRows(
        self::$results_cache[$cache_key]
      );
    }

    $sql = "SELECT * FROM `{$table_name}`";

    $sql = self::applyQueryOptions(
      $sql,
      $options
    );

    $db = ODB::getInstance();

    $stmt = $db->prepare(
      $sql
    );

    $stmt->execute();

    /** @var list<array<string, mixed>> $rows */
    $rows = $stmt->fetchAll(
      PDO::FETCH_ASSOC
    );

    self::$results_cache[$cache_key] = $rows;

    return static::hydrateRows(
      $rows
    );
  }

  /**
   * Count records matching the given conditions.
   *
   * @param array<string, mixed> $conditions Query conditions.
   *
   * @return int Number of matching records.
   */
  public static function count(array $conditions = []): int {
    $table_name = self::getTableName();
    $db = ODB::getInstance();

    // Build the base COUNT query
    $sql = "SELECT COUNT(*) as `num` FROM `{$table_name}`";

    // Add WHERE conditions if defined
    $params = [];
    if (!empty($conditions)) {
      $where = self::buildWhereClause($conditions);
      $sql .= " WHERE " . $where['clause'];
      $params = $where['params'];
    }

    // Prepare and execute the query
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    // Gets the result and returns the value of "num"
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int) ($result['num'] ?? 0);
  }

  /**
   * Save the model to the database.
   *
   * New records are inserted and persisted records are updated only when their
   * values have changed.
   *
   * @return bool Whether the database operation succeeded.
   *
   * @throws \LogicException If an update is attempted on a non-persisted model.
   * @throws \Exception If model validation fails.
   */
  public function save(): bool {
    if ($this->is_new_record) {
      $this->applyDefaults();
    }

    $this->validate();

    $schema = self::$schema_cache[static::class];
    $table_name = $schema['table_name'];
    $fields = $schema['fields'];

    $db = ODB::getInstance();
    if ($this->is_new_record) {
      $columns = [];
      $placeholders = [];
      $params = [];
      foreach ($fields as $field_name => $field) {
        if (
          in_array(
            $field_name,
            $schema['primary_key'],
            true
          ) && !empty($field['auto_increment'])
        ) {
          continue;
        }

        $columns[] = "`{$field_name}`";
        $placeholders[] = ":{$field_name}";
        $params[":{$field_name}"] = $this->$field_name;
      }

      $created_at_value = null;
      $updated_at_value = null;
      if ($schema['created_at'] !== null) {
        $created_at_field = $schema['created_at'];
        $created_at_value = date(
          'Y-m-d H:i:s'
        );

        $params[":{$created_at_field}"] = $created_at_value;
      }

      if ($schema['updated_at'] !== null) {
        $updated_at_field = $schema['updated_at'];
        $updated_at_value = date(
          'Y-m-d H:i:s'
        );

        $params[":{$updated_at_field}"] = $updated_at_value;
      }

      $sql = "INSERT INTO `{$table_name}` ("
        . implode(',', $columns)
        . ') VALUES ('
        . implode(',', $placeholders)
        . ')';

      $stmt = $db->prepare(
        $sql
      );

      $result = $stmt->execute(
        $params
      );
      if (!$result) {
        return false;
      }

      if (
        $schema['created_at'] !== null &&
        $created_at_value !== null
      ) {
        $this->{$schema['created_at']} = $created_at_value;
      }

      if (
        $schema['updated_at'] !== null &&
        $updated_at_value !== null
      ) {
        $this->{$schema['updated_at']} = $updated_at_value;
      }

      foreach ($schema['primary_key'] as $primary_key_field) {
        $field = $fields[$primary_key_field];

        if (!empty($field['auto_increment'])) {
          $this->$primary_key_field = (int) $db->lastInsertId();
          break;
        }
      }

      $this->is_new_record = false;

      self::clearResultsCache();
      $this->syncOriginalValues();

      return true;
    }

    $this->assertPersisted();

    $updates = [];
    $params = [];
    $updated_at_field = $schema['updated_at'];
    foreach ($fields as $field_name => $field) {
      if (
        in_array(
          $field_name,
          $schema['primary_key'],
          true
        )
      ) continue;
      if ($field_name === $updated_at_field) {
        continue;
      }

      if (
        $this->$field_name !==
        $this->original_values[$field_name]
      ) {
        $updates[] = "`{$field_name}` = :{$field_name}";
        $params[":{$field_name}"] = $this->$field_name;
      }
    }

    if ($updates === []) {
      return true;
    }

    $updated_at_value = null;
    if ($updated_at_field !== null) {
      $updated_at_value = date(
        'Y-m-d H:i:s'
      );

      $updates[] = "`{$updated_at_field}` = :{$updated_at_field}";
      $params[":{$updated_at_field}"] = $updated_at_value;
    }

    $where_clause = [];
    foreach ($schema['primary_key'] as $primary_key_field) {
      $where_clause[] = "`{$primary_key_field}` = :{$primary_key_field}";
      $params[":{$primary_key_field}"] = $this->$primary_key_field;
    }

    $sql = "UPDATE `{$table_name}` SET "
      . implode(',', $updates)
      . ' WHERE '
      . implode(
        ' AND ',
        $where_clause
      );

    $stmt = $db->prepare(
      $sql
    );

    $result = $stmt->execute(
      $params
    );
    if (!$result) {
      return false;
    }

    if (
      $updated_at_field !== null &&
      $updated_at_value !== null
    ) {
      $this->$updated_at_field = $updated_at_value;
    }

    self::clearResultsCache();
    $this->syncOriginalValues();

    return true;
  }

  /**
   * Delete the persisted model.
   *
   * Models with an ODeletedAt field are soft-deleted. Models without one are
   * removed from the database and become new/transient instances again.
   *
   * @return bool Whether the database operation succeeded.
   *
   * @throws \LogicException If the model does not represent a persisted record.
   */
  public function delete(): bool {
    $this->assertPersisted();

    $schema = self::$schema_cache[static::class];
    $table_name = $schema['table_name'];
    $primary_keys = $schema['primary_key'];
    $db = ODB::getInstance();

    $where_clause = [];
    $params = [];
    foreach ($primary_keys as $primary_key) {
      $where_clause[] = "`{$primary_key}` = :{$primary_key}";
      $params[":{$primary_key}"] = $this->$primary_key;
    }

    if ($schema['deleted_at'] !== null) {
      $deleted_at_field = $schema['deleted_at'];
      $deleted_at_value = date(
        'Y-m-d H:i:s'
      );

      $sql = "UPDATE `{$table_name}` SET "
        . "`{$deleted_at_field}` = :{$deleted_at_field}"
        . ' WHERE '
        . implode(
          ' AND ',
          $where_clause
        );

      $params[":{$deleted_at_field}"] = $deleted_at_value;

      $stmt = $db->prepare(
        $sql
      );

      $result = $stmt->execute(
        $params
      );
      if (!$result) {
        return false;
      }

      $this->$deleted_at_field = $deleted_at_value;

      self::clearResultsCache();
      $this->syncOriginalValues();

      return true;
    }

    $sql = "DELETE FROM `{$table_name}` WHERE "
      . implode(
        ' AND ',
        $where_clause
      );

    $stmt = $db->prepare(
      $sql
    );

    $result = $stmt->execute(
      $params
    );
    if (!$result) {
      return false;
    }

    /*
	 * A hard-deleted model no longer represents a persisted row. Mark it as a
	 * new record so a subsequent save performs an INSERT rather than attempting
	 * to UPDATE a row that no longer exists.
	 */
    $this->is_new_record = true;
    $this->original_values = [];

    self::clearResultsCache();

    return true;
  }

  /**
   * Get a field value, optionally applying type-specific formatting.
   *
   * Date fields require a date format as the first additional parameter.
   * Float fields require the number of decimals, decimal separator and
   * thousands separator as the first three additional parameters.
   *
   * Additional parameters are ignored for field types that do not require
   * formatting.
   *
   * @param string $field Field name.
   * @param mixed ...$params Type-specific formatting parameters.
   *
   * @return string|int|float|bool|null Field value or formatted value.
   *
   * @throws \Exception If the requested field does not exist.
   * @throws \InvalidArgumentException If formatting parameters are missing or
   *                                   have invalid types.
   * @throws \UnexpectedValueException If a date field contains an invalid value.
   */
  public function get(
    string $field,
    mixed ...$params
  ): string | int | float | bool | null {
    $schema = self::$schema_cache[static::class];

    if (!array_key_exists($field, $schema['fields'])) {
      throw new Exception(
        "The field '{$field}' does not exist in the model."
      );
    }

    $field_schema = $schema['fields'][$field];
    $value = $this->$field;

    if ($value === null) {
      return null;
    }

    if ($field_schema['type'] === OField::DATE) {
      if (
        !array_key_exists(0, $params) ||
        !is_string($params[0])
      ) {
        throw new \InvalidArgumentException(
          "A string format must be provided for date field '{$field}'."
        );
      }

      $timestamp = strtotime($value);

      if ($timestamp === false) {
        throw new \UnexpectedValueException(
          "Field '{$field}' contains an invalid date value."
        );
      }

      return date(
        $params[0],
        $timestamp
      );
    }

    if ($field_schema['type'] === OField::FLOAT) {
      if (count($params) < 3) {
        throw new \InvalidArgumentException(
          "Float field '{$field}' requires decimals, decimal separator and thousands separator."
        );
      }

      if (!is_int($params[0])) {
        throw new \InvalidArgumentException(
          "The decimals parameter for float field '{$field}' must be an integer."
        );
      }

      if (!is_string($params[1])) {
        throw new \InvalidArgumentException(
          "The decimal separator for float field '{$field}' must be a string."
        );
      }

      if (!is_string($params[2])) {
        throw new \InvalidArgumentException(
          "The thousands separator for float field '{$field}' must be a string."
        );
      }

      return number_format(
        $value,
        $params[0],
        $params[1],
        $params[2]
      );
    }

    return $value;
  }

  /**
   * Ensure that the model represents a persisted record with a complete primary
   * key.
   *
   * @return void
   *
   * @throws \LogicException If the model is new or its primary key is incomplete.
   */
  protected function assertPersisted(): void {
    if ($this->is_new_record) {
      throw new \LogicException(
        'The operation requires an existing persisted model.'
      );
    }

    $schema = self::$schema_cache[static::class];

    foreach ($schema['primary_key'] as $primary_key) {
      if ($this->$primary_key === null) {
        throw new \LogicException(
          "Persisted model primary key field '{$primary_key}' cannot be null."
        );
      }
    }
  }

  /**
   * Get the model schema definition.
   *
   * @return array{
   *     table_name: string,
   *     fields: array<string, array{
   *         name: string,
   *         type: string,
   *         nullable: bool,
   *         default: mixed,
   *         max: int|null,
   *         comment: string,
   *         visible: bool,
   *         ref: string|null,
   *         primary?: bool,
   *         auto_increment?: bool
   *     }>,
   *     primary_key: list<string>,
   *     created_at: string|null,
   *     updated_at: string|null,
   *     deleted_at: string|null
   * } Model schema.
   *
   * @throws \Exception If the model schema has not been initialized.
   */
  public function getModel(): array {
    $class_name = static::class;
    if (!isset(self::$schema_cache[$class_name])) {
      throw new Exception(
        "Model schema '{$class_name}' has not been initialized."
      );
    }

    return self::$schema_cache[$class_name];
  }

  /**
   * Return the model data as an array.
   *
   * Only fields marked as visible are included.
   *
   * @return array<string, mixed> Model field values.
   */
  public function toArray(): array {
    $schema = self::$schema_cache[static::class];
    $data = [];

    foreach ($schema['fields'] as $field_name => $field) {
      if ($field['visible']) {
        $data[$field_name] = $this->$field_name;
      }
    }

    return $data;
  }

  /**
   * Return a JSON representation of the model data.
   *
   * @return string JSON representation of the model data.
   *
   * @throws \JsonException If the model data cannot be encoded as JSON.
   */
  public function toJSON(): string {
    return json_encode(
      $this->toArray(),
      JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
  }

  /**
   * Return a SQL representation of the model class
   *
   * @return string SQL representation of the model class
   */
  public function toSQL(): string {
    $class_name = static::class;
    $table_name = $this->getTableName();

    // Get schema model from cache
    $schema = self::$schema_cache[$class_name];
    $fields = $schema['fields'];

    $sql_fields = [];
    $primary_key = [];
    $foreign_keys = [];
    $keys = [];

    foreach ($fields as $field_name => $field) {
      $sql_field = "`{$field_name}`";

      // Field type
      switch ($field['type']) {
        case OField::NUMBER:
          $sql_field .= " INT(11)";
          break;
        case OField::FLOAT:
          $sql_field .= " FLOAT";
          break;
        case OField::TEXT:
          $sql_field .= " VARCHAR({$field['max']})";
          $sql_field .= " COLLATE utf8mb4_unicode_ci";
          break;
        case OField::LONGTEXT:
          $sql_field .= " LONGTEXT COLLATE utf8mb4_unicode_ci";
          break;
        case OField::BOOL:
          $sql_field .= " TINYINT(1)";
          break;
        case OField::DATE:
          $sql_field .= " DATETIME";
          break;
      }

      if (!$field['nullable']) {
        $sql_field .= " NOT NULL";
      }

      // Default value
      if (isset($field['default'])) {
        $sql_field .= " DEFAULT " . self::quoteSqlValue($field['default']);
      }

      // Field comment
      if (!empty($field['comment'])) {
        $sql_field .= " COMMENT " . self::quoteSqlValue($field['comment']);
      }

      // Primary key
      if (!empty($field['primary'])) {
        $primary_key[] = "`{$field_name}`";
        if (!empty($field['auto_increment'])) {
          $sql_field .= " AUTO_INCREMENT";
        }
      }

      // Foreign keys
      if (!empty($field['ref'])) {
        $ref_parts = explode('.', $field['ref']);
        if (count($ref_parts) !== 2) {
          throw new Exception("Invalid reference '{$field['ref']}' for field '{$field_name}'.");
        }

        [$ref_table, $ref_column] = $ref_parts;
        $ref_table = self::validateSqlIdentifier($ref_table, 'reference table');
        $ref_column = self::validateSqlIdentifier($ref_column, 'reference column');
        $constraint_name = "fk_{$table_name}_{$field_name}_{$ref_table}";

        $foreign_keys[] = "ADD CONSTRAINT `{$constraint_name}` FOREIGN KEY (`{$field_name}`) REFERENCES `{$ref_table}` (`{$ref_column}`) ON DELETE NO ACTION ON UPDATE NO ACTION";
        $keys[] = "ADD KEY `{$constraint_name}_idx` (`{$field_name}`)";
      }

      // Add field to SQL array
      $sql_fields[] = $sql_field;
    }

    // Build CREATE TABLE sentence
    $sql = "CREATE TABLE `{$table_name}` (\n  ";
    $sql .= implode(",\n  ", $sql_fields);

    // Add primary key
    if (!empty($primary_key)) {
      $sql .= ",\n  PRIMARY KEY (" . implode(', ', $primary_key) . ")";
    }

    $sql .= "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    // Add foreign keys
    if (!empty($foreign_keys) || !empty($keys)) {
      $sql .= "\n\nALTER TABLE `{$table_name}`\n  ";
      $sql .= implode(",\n  ", array_merge($keys, $foreign_keys)) . ";";
    }

    return $sql;
  }
}
