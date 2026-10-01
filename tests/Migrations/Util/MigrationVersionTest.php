<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\Migrations\Util;

use Osumi\OsumiFramework\Migrations\Util\MigrationVersion;
use PHPUnit\Framework\TestCase;

final class MigrationVersionTest extends TestCase {
    /**
     * Test that framework version prefixes are normalized.
     *
     * @return void
     */
    public function testVersionPrefixIsNormalized(): void {
        self::assertSame(
            '9.9.0',
            MigrationVersion::normalize(
                'v9.9.0'
            )
        );

        self::assertSame(
            '10.0.0',
            MigrationVersion::normalize(
                'V10.0.0'
            )
        );
    }

    /**
     * Test that whitespace is removed during normalization.
     *
     * @return void
     */
    public function testVersionWhitespaceIsNormalized(): void {
        self::assertSame(
            '9.9.0',
            MigrationVersion::normalize(
                ' 9.9.0 '
            )
        );
    }

    /**
     * Test valid migration version formats.
     *
     * @return void
     */
    public function testValidVersionsAreAccepted(): void {
        self::assertTrue(
            MigrationVersion::isValid(
                '9.9.0'
            )
        );

        self::assertTrue(
            MigrationVersion::isValid(
                '10.0.0-beta.1'
            )
        );
    }

    /**
     * Test invalid migration version formats.
     *
     * @return void
     */
    public function testInvalidVersionsAreRejected(): void {
        self::assertFalse(
            MigrationVersion::isValid(
                'main'
            )
        );

        self::assertFalse(
            MigrationVersion::isValid(
                '9.9'
            )
        );

        self::assertFalse(
            MigrationVersion::isValid(
                'v9.9.0'
            )
        );
    }
}
