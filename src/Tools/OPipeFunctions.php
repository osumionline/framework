<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\Tools;
use \DateTime;

class OPipeFunctions {
  /**
   * Formats a date value based on the given mask.
   *
   * @param string|null $value Date string in 'Y-m-d H:i:s' format, or null.
   *
   * @param string $mask Format mask, default is "d/m/Y H:i:s".
   *
   * @return string Formatted date in quotes or "null" if value is null.
   */
  public static function getDateValue(?string $value, string $mask = "d/m/Y H:i:s"): string {
    if (is_null($value)) {
      return 'null';
    }

    $date = DateTime::createFromFormat('Y-m-d H:i:s', $value);
    return $date ? '"' . $date->format($mask) . '"' : 'null';
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

    // Convertir a número si es int o float y aplicar `number_format`
    if (is_float($value) || is_int($value)) {
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
