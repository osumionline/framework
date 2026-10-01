<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\DTO;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
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
   * Values may come from middleware context, an HTTP header or request
   * parameters. Explicit middleware and header sources never fall back to
   * request parameters when their value is missing.
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
     * Validate field references and explicit data sources before loading values.
     */
    foreach ($properties as $property) {
      $attributes = $property->getAttributes(
        ODTOField::class
      );

      foreach ($attributes as $attribute) {
        $field_definition = $attribute->newInstance();
        $property_name = $property->getName();

        if ($field_definition->requiredIf !== null) {
          $dependency = $field_definition->requiredIf;

          if ($dependency === $property_name) {
            throw new \InvalidArgumentException(
              "DTO property '{$property_name}' cannot use itself as requiredIf dependency."
            );
          }

          if (!array_key_exists(
            $dependency,
            $field_names
          )) {
            throw new \InvalidArgumentException(
              "DTO property '{$property_name}' references unknown requiredIf field '{$dependency}'."
            );
          }
        }

        $has_middleware = $field_definition->middleware !== null;
        $has_middleware_property = $field_definition->middlewareProperty !== null;

        if ($has_middleware !== $has_middleware_property) {
          throw new \InvalidArgumentException(
            "DTO property '{$property_name}' must define middleware and middlewareProperty together."
          );
        }

        if (
          $field_definition->middleware !== null &&
          trim($field_definition->middleware) === ''
        ) {
          throw new \InvalidArgumentException(
            "DTO property '{$property_name}' contains an empty middleware name."
          );
        }

        if (
          $field_definition->middlewareProperty !== null &&
          trim($field_definition->middlewareProperty) === ''
        ) {
          throw new \InvalidArgumentException(
            "DTO property '{$property_name}' contains an empty middlewareProperty."
          );
        }

        if (
          $field_definition->header !== null &&
          trim($field_definition->header) === ''
        ) {
          throw new \InvalidArgumentException(
            "DTO property '{$property_name}' contains an empty header name."
          );
        }

        if (
          $field_definition->middleware !== null &&
          $field_definition->header !== null
        ) {
          throw new \InvalidArgumentException(
            "DTO property '{$property_name}' cannot define both middleware and header sources."
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
         * Middleware is an explicit source. If its context value is absent,
         * the DTO value remains null and does not fall back to client input.
         */
        if ($field_definition->middleware !== null) {
          $value = $this->normalizeFieldValue(
            $req->getMiddlewareValue(
              $field_definition->middleware,
              $field_definition->middlewareProperty
            ),
            $field_types[$property_name]
          );

          $this->$property_name = $value;
          $field_values[$property_name] = $value;

          continue;
        }

        /*
         * Header is also an explicit source and never falls back to request
         * parameters when the header is absent or invalid.
         */
        if ($field_definition->header !== null) {
          $value = $this->normalizeFieldValue(
            $req->getHeader(
              $field_definition->header
            ),
            $field_types[$property_name]
          );

          $this->$property_name = $value;
          $field_values[$property_name] = $value;

          continue;
        }

        /*
         * Otherwise load the value from request parameters using the declared
         * DTO property type.
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

        $value = $field_values[$property_name]
          ?? null;

        if (
          $field_definition->required &&
          $value === null
        ) {
          $this->validation_errors[] =
            "The property '{$property_name}' is required.";

          continue;
        }

        if ($field_definition->requiredIf === null) {
          continue;
        }

        $dependency = $field_definition->requiredIf;

        $dependency_value = $field_values[$dependency]
          ?? null;

        if (
          $dependency_value !== null &&
          $value === null
        ) {
          $this->validation_errors[] =
            "The property '{$property_name}' is required when '{$dependency}' has a value.";
        }
      }
    }
  }

  /**
   * Normalize a raw value according to a supported DTO field type.
   *
   * Invalid values are converted to null so DTO validation can handle them
   * consistently with request parameter getters.
   *
   * @param mixed $value Raw value.
   * @param string $type DTO field type.
   *
   * @return string|int|float|bool|array|null Normalized value.
   */
  private function normalizeFieldValue(
    mixed $value,
    string $type
  ): string|int|float|bool|array|null {
    if ($value === null) {
      return null;
    }

    switch ($type) {
      case 'int':
        if (is_int($value)) {
          return $value;
        }

        if (
          !is_string($value) ||
          preg_match(
            '/^[+-]?\d+$/D',
            $value
          ) !== 1
        ) {
          return null;
        }

        $normalized = filter_var(
          $value,
          FILTER_VALIDATE_INT
        );

        return $normalized === false
          ? null
          : $normalized;

      case 'float':
        if (is_float($value)) {
          return $value;
        }

        if (is_int($value)) {
          return (float) $value;
        }

        if (!is_string($value)) {
          return null;
        }

        $normalized = filter_var(
          $value,
          FILTER_VALIDATE_FLOAT
        );

        return $normalized === false
          ? null
          : (float) $normalized;

      case 'bool':
        if (!is_scalar($value)) {
          return null;
        }

        return filter_var(
          $value,
          FILTER_VALIDATE_BOOLEAN,
          FILTER_NULL_ON_FAILURE
        );

      case 'string':
        return is_scalar($value)
          ? (string) $value
          : null;

      case 'array':
        return is_array($value)
          ? $value
          : null;
    }

    return null;
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
   * Check whether the DTO is valid.
   *
   * @return bool True when the DTO has no validation errors.
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
