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
            'gitIgnoredPaths' => [
                'composer.json',
                'composer.lock'
            ],
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
            [
                'composer.json',
                'composer.lock'
            ],
            $options->git_ignored_paths
        );

        self::assertSame(
            $logger,
            $options->logger
        );
    }

    /**
     * Test that Git ignored paths must be a string list.
     *
     * @return void
     */
    public function testInvalidGitIgnoredPathsAreRejected(): void {
        $this->expectException(
            \InvalidArgumentException::class
        );

        MigrationOptions::fromArray([
            'gitIgnoredPaths' => [
                'composer.json',
                7
            ]
        ]);
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
