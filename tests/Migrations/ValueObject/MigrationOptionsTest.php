<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\Migrations\ValueObject;

use Osumi\OsumiFramework\Migrations\ValueObject\MigrationOptions;
use PHPUnit\Framework\TestCase;

final class MigrationOptionsTest extends TestCase {
    /**
     * Test that public migration options are normalized.
     *
     * @return void
     */
    public function testOptionsAreNormalized(): void {
        $logger = static function (string $message): void {
        };

        $options = MigrationOptions::fromArray([
            'dryRun' => true,
            'force' => true,
            'verbose' => true,
            'interactive' => false,
            'extra' => [
                'example' => 'value'
            ],
            'logger' => $logger
        ]);

        self::assertTrue($options->dry_run);
        self::assertTrue($options->force);
        self::assertTrue($options->verbose);
        self::assertFalse($options->interactive);

        self::assertSame(
            [
                'example' => 'value'
            ],
            $options->extra
        );

        self::assertSame(
            $logger,
            $options->logger
        );
    }

    /**
     * Test that unknown migration options are rejected.
     *
     * @return void
     */
    public function testUnknownOptionIsRejected(): void {
        $this->expectException(
            \InvalidArgumentException::class
        );

        MigrationOptions::fromArray([
            'unknown' => true
        ]);
    }

    /**
     * Test that option types are validated strictly.
     *
     * @return void
     */
    public function testInvalidBooleanOptionIsRejected(): void {
        $this->expectException(
            \InvalidArgumentException::class
        );

        MigrationOptions::fromArray([
            'dryRun' => 1
        ]);
    }
}
