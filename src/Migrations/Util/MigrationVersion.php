<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Migrations\Util;

final class MigrationVersion {
    private const string VERSION_PATTERN = '/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/D';

    /**
     * Prevent instantiation of the migration version utility.
     */
    private function __construct() {
    }

    /**
     * Normalize a framework version for migration comparisons.
     *
     * Leading "v" or "V" prefixes are removed when followed by a numeric
     * version.
     *
     * @param string $version Framework version.
     *
     * @return string Normalized framework version.
     */
    public static function normalize(string $version): string {
        $version = trim(
            $version
        );

        if (
            strlen($version) > 1 &&
            (
                $version[0] === 'v' ||
                $version[0] === 'V'
            ) &&
            ctype_digit(
                $version[1]
            )
        ) {
            return substr(
                $version,
                1
            );
        }

        return $version;
    }

    /**
     * Validate a framework migration version.
     *
     * @param string $version Framework version.
     *
     * @return bool Whether the version has a valid migration format.
     */
    public static function isValid(string $version): bool {
        return preg_match(
            self::VERSION_PATTERN,
            $version
        ) === 1;
    }
}
