<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tools;

use DateTimeImmutable;

class OPipeFunctions {
  /**
   * Formats a DATE or DATETIME value.
   *
   * DATE values use "d/m/Y" by default.
   * DATETIME values use "d/m/Y H:i:s" by default.
   *
   * @param string|null $value DATE or DATETIME value, or null.
   * @param string|null $mask Optional output format mask.
   *
   * @return string Formatted date in quotes or "null" if the value is null
   *                or invalid.
   */
  public static function getDateValue(
    ?string $value,
    ?string $mask = null
  ): string {
    if ($value === null) {
      return 'null';
    }

    $formats = [
      'Y-m-d H:i:s' => 'd/m/Y H:i:s',
      'Y-m-d' => 'd/m/Y'
    ];

    foreach ($formats as $input_format => $default_mask) {
      $date = DateTimeImmutable::createFromFormat(
        '!' . $input_format,
        $value
      );

      $errors = DateTimeImmutable::getLastErrors();

      if (
        $date === false ||
        (
          $errors !== false &&
          (
            $errors['warning_count'] > 0 ||
            $errors['error_count'] > 0
          )
        ) ||
        $date->format($input_format) !== $value
      ) {
        continue;
      }

      return '"'
        . $date->format(
          $mask ?? $default_mask
        )
        . '"';
    }

    return 'null';
  }

  /**
   * Formats a number value based on the given parameters for number_format.
   *
   * @param int|float|null $value Number value, or null.
   *
   * @param int $decimals Number of decimals, default is 2.
   *
   * @param string $decimal_separator Decimal point, default is ".".
   *
   * @param string $thousands_separator Thousands separator, default is "".
   *
   * @return string Formatted number, or "null" if value is null.
   */
  public static function getNumberValue($value, int $decimals = 2, string $decimal_separator = ".", string $thousands_separator = ""): string {
    if ($value === null) {
      return "null";
    }

    // Convert to number if it is a float and apply `number_format`
    if (is_float($value)) {
      return number_format($value, (int)$decimals, $decimal_separator, $thousands_separator);
    }

    return strval($value);
  }

  /**
   * Encodes a string value with urlencode, or returns "null" if value is null.
   *
   * @param string|null $value String value, or null.
   *
   * @return string URL-encoded string in quotes, or "null" if value is null.
   */
  public static function getStringValue(?string $value): string {
    return is_null($value) ? 'null' : '"' . urlencode($value) . '"';
  }

  /**
   * Encodes a string value as a JSON-safe string without URL encoding,
   * or returns "null" if value is null.
   *
   * @param string|null $value String value, or null.
   *
   * @return string JSON-safe string in quotes, or "null" if value is null.
   *
   * @throws \JsonException If the value cannot be encoded as JSON.
   */
  public static function getStringPlainValue(?string $value): string {
    if (is_null($value)) {
      return 'null';
    }

    return json_encode(
      $value,
      JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
  }

  /**
   * Converts a boolean value to "true" or "false", or returns "null" if value is null.
   *
   * @param bool|null $value Boolean value, or null.
   *
   * @return string "true" or "false" (without quotes), or "null" if value is null.
   */
  public static function getBoolValue(?bool $value): string {
    if (is_null($value)) {
      return 'null';
    }

    return $value ? 'true' : 'false';
  }
}
