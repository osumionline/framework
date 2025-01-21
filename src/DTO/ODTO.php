<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\DTO;

use \ReflectionClass;
use \ReflectionProperty;
use Osumi\OsumiFramework\Web\ORequest;

class ODTO {
  private array $validation_errors = [];

  /**
   * Constructor that loads data from the request into the DTO class
   *
   * @param ORequest $req Request object with data to be loaded
   */
  public function __construct(ORequest $req) {
    $reflection = new ReflectionClass($this);
    $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
    $field_values = [];

    foreach ($properties as $property) {
      $attributes = $property->getAttributes(ODTOField::class);

      foreach ($attributes as $attribute) {
        $field_definition = $attribute->newInstance();
        $property_name = $property->getName();

        // Get value from the filter if defined
        if (!is_null($field_definition->filter)) {
          $filter_values = $req->getFilter($field_definition->filter);
          if (is_array($filter_values) && array_key_exists($property_name, $filter_values)) {
            $this->$property_name = $filter_values[$property_name];
            $field_values[$property_name] = $filter_values[$property_name];
            continue;
          }
        }

        // If there is not a filter, get value from request
        $type = $property->getType()?->getName();
        $value = match ($type) {
          'int'    => $req->getParamInt($property_name),
          'float'  => $req->getParamFloat($property_name),
          'bool'   => $req->getParamBool($property_name),
          'string' => $req->getParamString($property_name),
          'array'  => $req->getParam($property_name),
          default  => null
        };

        $this->$property_name = $value;
        $field_values[$property_name] = $value;
      }
    }

    // "required" and "requiredIf" field validations
    foreach ($properties as $property) {
      $attributes = $property->getAttributes(ODTOField::class);
      foreach ($attributes as $attribute) {
        $field_definition = $attribute->newInstance();
        $property_name = $property->getName();

        if ($field_definition->required && is_null($field_values[$property_name] ?? null)) {
          $this->validation_errors[] = "The property '{$property_name}' is required.";
        }

        if (!is_null($field_definition->requiredIf)) {
          $dependency = $field_definition->requiredIf;
          if (!is_null($field_values[$dependency] ?? null) && is_null($field_values[$property_name] ?? null)) {
            $this->validation_errors[] = "The property '{$property_name}' is required because '{$dependency}' is set.";
          }
        }
      }
    }
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
   * Returns the validation error list
   *
   * @return array Validation error list
   */
  public function getValidationErrors(): array {
    return $this->validation_errors;
  }
}
