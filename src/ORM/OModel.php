<?php
declare(strict_types=1);

namespace Osumi\OsumiFramework\ORM;

use PDO;
use ReflectionClass;
use ReflectionProperty;
use Exception;

abstract class OModel {
  // Class properties
  protected bool $initialized = false;
  protected bool $is_new_record = true;
  protected array $original_values = [];
  protected static array $schema_cache = [];
  protected static array $model_validated = [];
  protected static array $results_cache = [];

  // Constructor
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
      foreach ($attributes as $attribute) {
        $attr_instance = $attribute->newInstance();

        if ($attr_instance instanceof OPK) {
          $has_primary_key = true;
        } elseif ($attr_instance instanceof OCreatedAt) {
          $has_created_at++;
        } elseif ($attr_instance instanceof OUpdatedAt) {
          $has_updated_at++;
        } elseif ($attr_instance instanceof ODeletedAt) {
          $has_deleted_at++;
        }

        // Validate field types
        if ($attr_instance instanceof OField) {
          $this->validateFieldType($property, $attr_instance);
        }
      }
    }

    // Validate primary key
    if (!$has_primary_key) {
      throw new Exception("Model '{$className}' doesn't have a primary key field (OPK) defined.");
    }

    // Validate mandatory created_at and updated_at fields
    if ($has_created_at === 0) {
      throw new Exception("Model '{$className}' doesn't have a created at field (OCreatedAt) defined.");
    }
    if ($has_created_at > 1) {
      throw new Exception("Model '{$className}' can't have more than one created at field (OCreatedAt) defined.");
    }
    if ($has_updated_at === 0) {
      throw new Exception("Model '{$className}' doesn't have an updated at field (OUpdatedAt) defined.");
    }
    if ($has_updated_at > 1) {
      throw new Exception("Model '{$className}' can't have more than one updated at field (OUpdatedAt) defined.");
    }
    if ($has_deleted_at > 1) {
      throw new Exception("Model '{$className}' can't have more than one deleted at field (ODeletedAt) defined.");
    }

    // Mark model as validated
    self::$model_validated[$class_name] = true;
  }

  /**
   * Method to validate field types matching definitions
   *
   * @param ReflectionProperty $property Property from the class
   *
   * @param OField $field Field definition
   *
   * @return void
   */
  protected function validateFieldType(ReflectionProperty $property, OField $field): void {
    $field_name = $property->getName();

    // If the type has not been defined in the OField decorator, automatically assign according to the property type
    if ($field->type === null) {
      $property_type = $property->getType()->getName();
      switch ($property_type) {
        case 'string':
          $field->type = OField::TEXT;
          break;
        case 'int':
          $field->type = OField::NUMBER;
          break;
        case 'float':
          $field->type = OField::FLOAT;
          break;
        case 'bool':
          $field->type = OField::BOOL;
          break;
        default:
          throw new Exception("Unsupported type for property '{$field_name}': {$property_type}.");
        }
    }

    // Validate that the property type matches the expected type
    $type = $field->type;

    switch ($type) {
      case OField::NUMBER:
      case OField::FLOAT:
        if (!in_array($property->getType()->getName(), ['int', 'float'])) {
          throw new Exception("The type of the property '{$field_name}' does not match the expected type '{$type}'.");
        }
        break;
      case OField::TEXT:
      case OField::LONGTEXT:
        if ($property->getType()->getName() !== 'string') {
          throw new Exception("The type of the property '{$field_name}' does not match the expected type '{$type}'.");
        }
        break;
      case OField::BOOL:
        if ($property->getType()->getName() !== 'bool') {
          throw new Exception("The type of the property '{$field_name}' does not match the expected type 'bool'.");
        }
        break;
      case OField::DATE:
        if ($property->getType()->getName() !== 'string') {
          throw new Exception("The type of the property '{$field_name}' does not match the expected type 'string' for dates.");
        }
        break;
      default:
        throw new Exception("Unknown property type for '{$field_name}'.");
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
            // Primary key
            $field_schema['type'] = $attr_instance->type ?? null;  // Type might not be defined
            $field_schema['primary'] = true;
            $field_schema['auto_increment'] = $attr_instance->incr;
            $field_schema['ref'] = $attr_instance->ref;
            $schema['primary_key'][] = $field_name;
          } elseif ($attr_instance instanceof OField) {
            // Regular field
            $field_schema['type'] = $attr_instance->type ?? null;  // Type might not be defined
            $field_schema['nullable'] = $attr_instance->nullable;
            $field_schema['default'] = $attr_instance->default;
            $field_schema['max'] = $attr_instance->max;
            $field_schema['visible'] = $attr_instance->visible;
            $field_schema['ref'] = $attr_instance->ref;
          } elseif ($attr_instance instanceof OCreatedAt) {
            $field_schema['type'] = OField::DATE;
            $schema['created_at'] = $field_name;
          } elseif ($attr_instance instanceof OUpdatedAt) {
            $field_schema['type'] = OField::DATE;
            $schema['updated_at'] = $field_name;
          } elseif ($attr_instance instanceof ODeletedAt) {
            $field_schema['type'] = OField::DATE;
            $schema['deleted_at'] = $field_name;
          }
          $field_schema['comment'] = $attr_instance->comment;
        }

        // Get the type of the field if it is not defined in the attribute
        if ($field_schema['type'] === null) {
          $property_type = $property->getType();
          if ($property_type !== null) {
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
   * Assign values on instantiation
   *
   * @param array Data to be assigned to the object
   *
   * @return void
   */
  protected function assignValues(array $data): void {
    $reflection = new ReflectionClass($this);
    $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

    // Check every property
    foreach ($properties as $property) {
      $property_name = $property->getName();
      $property_type = $property->getType()->getName();

      // If the field is in the data, assign the provided value
      if (array_key_exists($property_name, $data)) {
        if ($property_type === 'bool') {
          $this->$property_name = (bool) $data[$property_name];
          $this->original_values[$property_name] = (bool) $data[$property_name];
        } else {
          $this->$property_name = $data[$property_name];
          $this->original_values[$property_name] = $data[$property_name];
        }
      }
      // If the field is not in the data, initialize it to null
      else {
        $this->$property_name = null;
        $this->original_values[$property_name] = null;
      }
    }

    // Check if all primary keys are set to determine if it is an existing record
    $primary_keys = self::$schema_cache[static::class]['primary_key'];
    $is_existing_record = true;
    foreach ($primary_keys as $primary_key) {
      if (!isset($this->$primary_key) || $this->$primary_key === null) {
        $is_existing_record = false;
        break;
      }
    }
    $this->is_new_record = !$is_existing_record;
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
   * Get list of primary keys
   *
   * @return array List of primary key fields
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
        if (!$field['nullable']) {
          throw new Exception("Field '{$field_name}' cannot be null.");
        }
        continue; // If it is null and it is allowed, continue to next field
      }

      // Validate the data type
      switch ($field['type']) {
        case OField::NUMBER:
          if (!is_int($value)) {
            throw new Exception("The '{$field_name}' field must be an integer.");
          }
          break;
        case OField::FLOAT:
          if (!is_float($value)) {
            throw new Exception("The '{$field_name}' field must be a decimal number.");
          }
          break;
        case OField::TEXT:
        case OField::LONGTEXT:
          if (!is_string($value)) {
            throw new Exception("The '{$field_name}' field must be a text string.");
          }
          if (isset($field['max']) && strlen($value) > $field['max']) {
            throw new Exception("The '{$field_name}' field cannot be longer than {$field['max']} characters.");
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
   * Method used to generate a cache key (for internal use)
   *
   * @param string $table Table name
   *
   * @param string $method Method that originated the cache item
   *
   * @param array $conditions Conditions used on the query
   *
   * @param array $options Options of the query
   *
   * @return string Generated cache key
   */
  protected static function generateCacheKey(string $table, string $method, array $conditions, array $options = []): string {
    // Combine method ('where', 'findOne'...), conditions and options on a single key
    return $table . ':' . $method . ':' . json_encode($conditions) . ':' . json_encode($options);
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
   * Return a new instance of the model class based on given data
   *
   * @param array $data Initial data for the instance
   *
   * @return New instance of the model class
   */
  public static function create(array $data = []): static {
    return new static($data);
  }

  /**
   * Return a new instance of the model class based on previously loaded data
   *
   * @param array $data Data to be loaded into the new instance
   *
   * @return New instance of the model class
   */
  public static function from(array $data): static {
    $instance = new static($data);
    $instance->is_new_record = false;
    return $instance;
  }

  /**
   * Performs a query and returns only one result
   *
   * @param array $conditions conditions to be applied on the query
   *
   * @return New instance of the model class, if successful
   */
  public static function findOne(array $conditions): ?static {
    // Generate cache key
    $table_name = self::getTableName();
    $cache_key = self::generateCacheKey($table_name, 'findOne', $conditions);

    // If results are cached, return them
    if (isset(self::$results_cache[$cache_key])) {
        return self::$results_cache[$cache_key];
    }

    // Execute query with a limit of one
    $results = self::where($conditions, ['limit' => 1]);

    // Store results on cache
    self::$results_cache[$cacheKey] = $results[0] ?? null;

    return $results[0] ?? null;
  }

  /**
   * Performs a query based on conditions (['field' => 'value']) and optional options ('orderBy', 'limit' or 'offset')
   *
   * @param array $conditions List of conditions to be applied on the query
   *
   * @param array $options List of options (order, limit or offset) to be applied on the query
   *
   * @return array Returns an array of model class objects with found values
   */
  public static function where(array $conditions, array $options = []): array {
    // Generate cache key
    $table_name = self::getTableName();
    $cache_key = self::generateCacheKey($table_name, 'where', $conditions, $options);

    // If results are cached, return them
    if (isset(self::$results_cache[$cache_key])) {
      return self::$results_cache[$cache_key];
    }

    $sql = "SELECT * FROM `{$table_name}` WHERE ";
    $params = [];
    $wheres = [];

    foreach ($conditions as $field => $value) {
      $wheres[] = "`{$field}` = :{$field}";
      $params[":{$field}"] = $value;
    }

    $sql .= implode(' AND ', $wheres);

    // Aditional options (order_by, limit, offset)
    if (isset($options['order_by'])) {
      // Splits the value of "order_by" into field and direction if it contains '#'
      list($field, $direction) = array_pad(explode('#', $options['order_by']), 2, 'ASC');

      // Checks if the direction is valid, otherwise sets 'ASC' by default
      $direction = strtoupper($direction);
      if ($direction !== 'ASC' && $direction !== 'DESC') {
        $direction = 'ASC';
      }

      $sql .= " ORDER BY `{$field}` {$direction}";
    }

    if (isset($options['limit'])) {
      if (is_numeric($options['limit'])) {
        $count = null;
        $start = $options['limit'];
      }
      else {
        // Splits the value of "limit" into start and amount if it contains '#'
        list($start, $count) = array_pad(explode('#', $options['limit']), 2, null);
      }

      // Constructs the LIMIT clause according to the format provided
      if ($count !== null) {
        // If both start and amount are specified
        $sql .= " LIMIT {$start}, {$count}";
      } else {
        // If only one limit value is specified
        $sql .= " LIMIT {$start}";
      }
    }

    if (isset($options['offset'])) {
      $offset = $options['offset'];
      $sql .= " OFFSET {$offset}";
    }

    $db = ODB::getInstance();
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $results = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $instance = new static($row);
      $instance->is_new_record = false;
      $results[] = $instance;
    }

    // Store results on cache
    self::$results_cache[$cache_key] = $results;

    return $results;
  }

  /**
   * Return all records of a table with optional options ('orderBy', 'limit' or 'offset')
   *
   * @param array $options List of options (order, limit or offset) to be applied on the query
   *
   * @return array Returns an array of model class objects with found values
   */
  public static function all(array $options = []): array {
    // Generate cache key
    $table_name = self::getTableName();
    $cache_key = self::generateCacheKey($table_name, 'all', [], $options);

    // If results are cached, return them
    if (isset(self::$results_cache[$cache_key])) {
      return self::$results_cache[$cache_key];
    }

    $sql = "SELECT * FROM `{$table_name}`";

    // Aditional options (order_by, limit, offset)
    if (isset($options['order_by'])) {
      // Splits the value of "order_by" into field and direction if it contains '#'
      list($field, $direction) = array_pad(explode('#', $options['order_by']), 2, 'ASC');

      // Checks if the direction is valid, otherwise sets 'ASC' by default
      $direction = strtoupper($direction);
      if ($direction !== 'ASC' && $direction !== 'DESC') {
        $direction = 'ASC';
      }

      $sql .= " ORDER BY `{$field}` {$direction}";
    }

    if (isset($options['limit'])) {
      if (is_numeric($options['limit'])) {
        $count = null;
        $start = $options['limit'];
      }
      else {
        // Splits the value of "limit" into start and amount if it contains '#'
        list($start, $count) = array_pad(explode('#', $options['limit']), 2, null);
      }

      // Constructs the LIMIT clause according to the format provided
      if ($count !== null) {
        // If both start and amount are specified
        $sql .= " LIMIT {$start}, {$count}";
      } else {
        // If only one limit value is specified
        $sql .= " LIMIT {$start}";
      }
    }

    if (isset($options['offset'])) {
      $offset = $options['offset'];
      $sql .= " OFFSET {$offset}";
    }

    $db = ODB::getInstance();
    $stmt = $db->prepare($sql);
    $stmt->execute();

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $instances = [];
    foreach ($results as $row) {
      $instances[] = new static($row);
    }

    // Store results on cache
    self::$results_cache[$cache_key] = $instances;

    return $instances;
  }

  /**
   * Returns a count of all records of a table with given conditions
   *
   * @param array $conditions List of conditions to be applied on the query
   *
   * @return int Result count
   */
  public static function count(array $conditions = []): int {
    $table_name = self::getTableName();
    $db = ODB::getInstance();

    // Build the base COUNT query
    $sql = "SELECT COUNT(*) as `num` FROM `{$table_name}`";

    // Add WHERE conditions if defined
    $params = [];
    if (!empty($conditions)) {
      $where_clauses = [];
      foreach ($conditions as $field => $value) {
        $where_clauses[] = "`{$field}` = :{$field}";
        $params[":{$field}"] = $value;
      }
      $sql .= " WHERE " . implode(' AND ', $where_clauses);
    }

    // Prepare and execute the query
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    // Gets the result and returns the value of "num"
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['num'] ?? 0;
  }

  /**
   * Save a model class object into the database
   *
   * @return bool Result of the operation
   */
  public function save(): bool {
    $this->validate();

    // Empty cache
    self::clearResultsCache();

    $schema = self::$schema_cache[static::class];
    $table_name = $schema['table_name'];
    $fields = $schema['fields'];

    $db = ODB::getInstance();

    if ($this->is_new_record) {
      // INSERT
      $columns = [];
      $placeholders = [];
      $params = [];

      foreach ($fields as $field_name => $field) {
        if (in_array($field_name, $schema['primary_key']) && $field['auto_increment']) {
          continue; // Ommit autoincremental fields
        }

        $columns[] = "`{$field_name}`";
        $placeholders[] = ":{$field_name}";
        $params[":{$field_name}"] = $this->$field_name;
      }

      // Handle created_at and updated_at
      if (!is_null($schema['created_at'])) {
        $created_at_field = $schema['created_at'];
        $params[":{$created_at_field}"] = date('Y-m-d H:i:s');
        $this->$created_at_field = $params[":{$created_at_field}"];
      }
      if (!is_null($schema['updated_at'])) {
        $updated_at_field = $schema['updated_at'];
        $params[":{$updated_at_field}"] = date('Y-m-d H:i:s');
        $this->$updated_at_field = $params[":{$updated_at_field}"];
      }

      $sql = "INSERT INTO `{$table_name}` (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";
      $stmt = $db->prepare($sql);
      $result = $stmt->execute($params);

      // If there is a autoincremental key, update it's value
      foreach ($schema['primary_key'] as $primary_key_field) {
        $field = $fields[$primary_key_field];
        if ($field['auto_increment']) {
          $this->$primary_key_field = (int) $db->lastInsertId();
          break; // There can only be one autoincremental field
        }
      }

      $this->is_new_record = false;
    } else {
      // UPDATE
      $updates = [];
      $params = [];

      foreach ($fields as $field_name => $field) {
        if (in_array($field_name, $schema['primary_key'])) {
          continue; // Ommit primary keys
        }

        if ($this->$field_name !== $this->original_values[$field_name]) {
          $updates[] = "`{$field_name}` = :{$field_name}";
          $params[":{$field_name}"] = $this->$field_name;
        }
      }

      //  If there are no changes, it's not necessary to perform the UPDATE
      if (empty($updates)) {
        return true;
      }

      // Handle updated_at
      if (!is_null($schema['updated_at'])) {
        $updated_at_field = $schema['updated_at'];
        $updates[] = "`{$updated_at_field}` = :{$updated_at_field}";
        $params[":{$updated_at_field}"] = date('Y-m-d H:i:s');
        $this->$updated_at_field = $params[":{$updated_at_field}"];
      }

      // Build WHERE clause with all the primary keys
      $where_clause = [];
      foreach ($schema['primary_key'] as $primary_key_field) {
        $where_clause[] = "`{$primary_key_field}` = :{$primary_key_field}";
        $params[":{$primary_key_field}"] = $this->$primary_key_field;
      }

      $sql = "UPDATE `{$table_name}` SET " . implode(',', $updates) . " WHERE " . implode(' AND ', $where_clause);
      $stmt = $db->prepare($sql);
      $result = $stmt->execute($params);
    }

    // Update original values
    foreach ($this->original_values as $key => $value) {
      $this->original_values[$key] = $this->$key;
    }

    return $result;
  }

  /**
   * Delete a record from the database. If there is a "deleted_at" field it updated it's value but doesn't delete it.
   *
   * @return bool Result of the operation
   */
    public function delete(): bool {
    // Empty cache
    self::clearResultsCache();

    $schema = self::$schema_cache[static::class];
    $table_name = $schema['table_name'];
    $primary_keys = $schema['primary_key'];
    $db = ODB::getInstance();

    if (!is_null($schema['deleted_at'])) {
      // Soft Delete: Update deleted_at field
      $deleted_at_field = $schema['deleted_at'];
      $this->$deleted_at_field = date('Y-m-d H:i:s');

      // Generate WHERE clause for composite keys
      $where_clause = [];
      $params = [];
      foreach ($primary_keys as $primary_key) {
        $where_clause[] = "`{$primary_key}` = :{$primary_key}";
        $params[":{$primary_key}"] = $this->$primary_key;
      }

      $sql = "UPDATE `{$table_name}` SET `{$deleted_at_field}` = :{$deleted_at_field} WHERE " . implode(' AND ', $where_clause);
      $params[":{$deleted_at_field}"] = $this->$deleted_at_field;

      $stmt = $db->prepare($sql);
      return $stmt->execute($params);
    } else {
      // Hard Delete: Delete record from database
      // Generate WHERE clause for composite keys
      $where_clause = [];
      $params = [];
      foreach ($primary_keys as $primary_key) {
        $where_clause[] = "`{$primary_key}` = :{$primary_key}";
        $params[":{$primary_key}"] = $this->$primary_key;
      }

      $sql = "DELETE FROM `{$table_name}` WHERE " . implode(' AND ', $where_clause);
      $stmt = $db->prepare($sql);
      return $stmt->execute($params);
    }
  }

  /**
   * Function to apply transformations to date or float field types
   *
   * @param string $field Name of the field
   *
   * @param $params Date mask for date type fields or number format fields for float type fields
   */
  public function get(string $field, ...$params) {
    $schema = self::$schema_cache[static::class];

    // Check if the field exists in the schema
    if (!array_key_exists($field, $schema['fields'])) {
      throw new Exception("The field '{$field}' does not exist in the model.");
    }

    $field_schema = $schema['fields'][$field];
    $value = $this->$field;

    // Case for date type fields
    if ($field_schema['type'] === OField::DATE) {
      if (empty($params) || !isset($params[0])) {
        throw new Exception("A format must be provided for the date field.");
      }
      $format = $params[0];
      $timestamp = strtotime($value);
      return date($format, $timestamp);
    }

    // Case for float type fields
    if ($field_schema['type'] === OField::FLOAT) {
      if (count($params) < 3) {
        throw new Exception("For a float field, 3 parameters must be provided: decimals, decimal separator, and thousands separator.");
      }
      [$decimals, $dec_point, $thousands_sep] = $params;
      return number_format($value, $decimals, $dec_point, $thousands_sep);
    }

    // If it is not date or float, return the original value
    return $value;
  }

  /**
   * Get model schema definition
   *
   * @return array Model schema definition
   */
  public function getModel(): array {
    $class_name = static::class;

    if (!isset(self::$schema_cache[$class_name])) {
      throw new Exception("The model schema '{$class_name}' has not been initialized.");
    }

    return self::$schema_cache[$class_name];
  }

  /**
   * Return an array representation of the model class data
   *
   * @return array Array representation of the model class data
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
   * Return a JSON representation of the model class data
   *
   * @return string JSON representation of the model class data
   */
  public function toJSON(): string {
    return json_encode($this->toArray());
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
                 $sql_field .= " TEXT COLLATE utf8mb4_unicode_ci";
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
             $default_value = $field['default'];
             if (is_string($default_value)) {
                 $default_value = "'{$default_value}'";
             }
             $sql_field .= " DEFAULT {$default_value}";
         }

         // Field comment
         if (!empty($field['comment'])) {
             $sql_field .= " COMMENT '{$field['comment']}'";
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
             [$ref_table, $ref_column] = explode('.', $field['ref']);
             $foreign_keys[] = "ADD CONSTRAINT `fk_{$table_name}_{$ref_table}` FOREIGN KEY (`{$field_name}`) REFERENCES `{$ref_table}` (`{$ref_column}`) ON DELETE NO ACTION ON UPDATE NO ACTION";
             $keys[] = "ADD KEY `fk_{$table_name}_{$ref_table}_idx` (`{$field_name}`)";
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
