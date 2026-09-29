<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\DTO;

use ReflectionClass;
use ReflectionProperty;
use ReflectionNamedType;
use Osumi\OsumiFramework\Web\ORequest;

class ODTO {
  /**
   * @var list<string>
   */
  private array $validation_errors = [];

  /**
   * Create a DTO and load its values from the request.
   *
   * DTO field definitions are validated before request values are assigned.
   *
   * @param ORequest $req Request containing the values to load.
   *
   * @throws \InvalidArgumentException If a DTO field definition is invalid.
   */
  public function __construct(ORequest $req) {
    $reflection = new ReflectionClass($this);
    $properties = $reflection->getProperties(
      ReflectionProperty::IS_PUBLIC
    );

    /**
     * Supported type of each DTO field.
     *
     * @var array<string, string>
     */
    $field_types = [];

    /**
     * Names of all properties declared as DTO fields.
     *
     * @var array<string, true>
     */
    $field_names = [];

    /**
     * Values loaded into DTO fields.
     *
     * @var array<string, mixed>
     */
    $field_values = [];

    /*
	 * First validate all DTO field definitions and store their types.
	 */
    foreach ($properties as $property) {
      $attributes = $property->getAttributes(
        ODTOField::class
      );

      if ($attributes === []) {
        continue;
      }

      $property_name = $property->getName();

      $field_types[$property_name] = $this->getFieldType(
        $property
      );

      $field_names[$property_name] = true;
    }

    /*
	 * Validate requiredIf references before loading any values.
	 */
    foreach ($properties as $property) {
      $attributes = $property->getAttributes(
        ODTOField::class
      );

      foreach ($attributes as $attribute) {
        $field_definition = $attribute->newInstance();

        if (
          $field_definition->requiredIf !== null &&
          !array_key_exists(
            $field_definition->requiredIf,
            $field_names
          )
        ) {
          throw new \InvalidArgumentException(
            "DTO property '{$property->getName()}' references unknown requiredIf field '{$field_definition->requiredIf}'."
          );
        }
      }
    }

    /*
	 * Load DTO field values.
	 */
    foreach ($properties as $property) {
      $attributes = $property->getAttributes(
        ODTOField::class
      );

      foreach ($attributes as $attribute) {
        $field_definition = $attribute->newInstance();
        $property_name = $property->getName();

        /*
			 * Get value from a filter if configured.
			 */
        if ($field_definition->filter !== null) {
          $filter_values = $req->getFilter(
            $field_definition->filter
          );

          if (
            is_array($filter_values) &&
            $field_definition->filterProperty !== null &&
            array_key_exists(
              $field_definition->filterProperty,
              $filter_values
            )
          ) {
            $value = $filter_values[$field_definition->filterProperty];

            $this->$property_name = $value;
            $field_values[$property_name] = $value;

            continue;
          }
        }

        /*
			 * Get value from an HTTP header if configured.
			 */
        if ($field_definition->header !== null) {
          $value = $req->getHeader(
            $field_definition->header
          );

          $this->$property_name = $value;
          $field_values[$property_name] = $value;

          continue;
        }

        /*
			 * Otherwise load the value from the request parameters using
			 * the declared DTO property type.
			 */
        $type = $field_types[$property_name];

        $value = match ($type) {
          'int' => $req->getParamInt(
            $property_name
          ),
          'float' => $req->getParamFloat(
            $property_name
          ),
          'bool' => $req->getParamBool(
            $property_name
          ),
          'string' => $req->getParamString(
            $property_name
          ),
          'array' => $this->getArrayParam(
            $req,
            $property_name
          )
        };

        $this->$property_name = $value;
        $field_values[$property_name] = $value;
      }
    }

    /*
	 * Apply required and requiredIf validations after every DTO field has
	 * been loaded.
	 */
    foreach ($properties as $property) {
      $attributes = $property->getAttributes(
        ODTOField::class
      );

      foreach ($attributes as $attribute) {
        $field_definition = $attribute->newInstance();
        $property_name = $property->getName();

        if (
          $field_definition->required &&
          ($field_values[$property_name] ?? null) === null
        ) {
          $this->validation_errors[] =
            "The property '{$property_name}' is required.";
        }

        if ($field_definition->requiredIf !== null) {
          $dependency = $field_definition->requiredIf;

          if (
            ($field_values[$dependency] ?? null) !== null &&
            ($field_values[$property_name] ?? null) === null
          ) {
            $this->validation_errors[] =
              "The property '{$property_name}' is required because '{$dependency}' is set.";
          }
        }
      }
    }
  }

  /**
   * Get and validate the supported type of a DTO field.
   *
   * DTO fields must use one of the supported built-in nullable types because
   * null represents a missing or invalid input value during DTO validation.
   *
   * @param ReflectionProperty $property DTO property.
   *
   * @return string Supported property type.
   *
   * @throws \InvalidArgumentException If the property type is unsupported or
   *                                   non-nullable.
   */
  private function getFieldType(ReflectionProperty $property): string {
    $type = $property->getType();
    $property_name = $property->getName();

    if (
      !$type instanceof ReflectionNamedType ||
      !$type->isBuiltin()
    ) {
      throw new \InvalidArgumentException(
        "DTO property '{$property_name}' must have a supported built-in type."
      );
    }

    $type_name = $type->getName();

    if (
      !in_array(
        $type_name,
        [
          'int',
          'float',
          'bool',
          'string',
          'array'
        ],
        true
      )
    ) {
      throw new \InvalidArgumentException(
        "DTO property '{$property_name}' has unsupported type '{$type_name}'."
      );
    }

    if (!$type->allowsNull()) {
      throw new \InvalidArgumentException(
        "DTO property '{$property_name}' must be nullable."
      );
    }

    return $type_name;
  }

  /**
   * Get an array request parameter.
   *
   * @param ORequest $req Request containing the parameter.
   * @param string $key Parameter key.
   *
   * @return array|null Parameter value or null if it is not an array.
   */
  private function getArrayParam(
    ORequest $req,
    string $key
  ): ?array {
    $value = $req->getParam($key);

    return is_array($value)
      ? $value
      : null;
  }

  /**
   * Checks if the DTO is valid checking if there are validation errors
   *
   * @return bool True if it is a valid DTO or false otherwise
   */
  public function isValid(): bool {
    return empty($this->validation_errors);
  }

  /**
   * Get DTO validation errors.
   *
   * @return list<string> Validation error messages.
   */
  public function getValidationErrors(): array {
    return $this->validation_errors;
  }
}
