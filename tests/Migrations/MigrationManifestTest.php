<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\Migrations;

use Osumi\OsumiFramework\Migrations\Context\MigrationContext;
use Osumi\OsumiFramework\Migrations\Contract\MigrationStepInterface;
use Osumi\OsumiFramework\Migrations\MigrationManifest;
use PHPUnit\Framework\TestCase;

final class ManifestStepNineNine implements MigrationStepInterface {
    /**
     * Get the migration version.
     *
     * @return string Migration version.
     */
    public function getVersion(): string {
        return '9.9.0';
    }

    /**
     * Get the migration description.
     *
     * @return string Migration description.
     */
    public function getDescription(): string {
        return 'Test 9.9.0 migration.';
    }

    /**
     * Apply the test migration.
     *
     * @param MigrationContext $context Migration context.
     *
     * @return void
     */
    public function apply(MigrationContext $context): void {
    }
}

final class ManifestStepTen implements MigrationStepInterface {
    /**
     * Get the migration version.
     *
     * @return string Migration version.
     */
    public function getVersion(): string {
        return '10.0.0';
    }

    /**
     * Get the migration description.
     *
     * @return string Migration description.
     */
    public function getDescription(): string {
        return 'Test 10.0.0 migration.';
    }

    /**
     * Apply the test migration.
     *
     * @param MigrationContext $context Migration context.
     *
     * @return void
     */
    public function apply(MigrationContext $context): void {
    }
}

final class ManifestMismatchedStep implements MigrationStepInterface {
    /**
     * Get the migration version.
     *
     * @return string Migration version.
     */
    public function getVersion(): string {
        return '9.8.0';
    }

    /**
     * Get the migration description.
     *
     * @return string Migration description.
     */
    public function getDescription(): string {
        return 'Mismatched test migration.';
    }

    /**
     * Apply the test migration.
     *
     * @param MigrationContext $context Migration context.
     *
     * @return void
     */
    public function apply(MigrationContext $context): void {
    }
}

final class MigrationManifestTest extends TestCase {
    /**
     * Test that manifest steps are ordered and selected by version range.
     *
     * @return void
     */
    public function testStepsAreOrderedAndSelectedByVersionRange(): void {
        $manifest = new MigrationManifest([
            [
                'since' => '10.0.0',
                'step' => ManifestStepTen::class
            ],
            [
                'since' => '9.9.0',
                'step' => ManifestStepNineNine::class
            ]
        ]);

        $steps = $manifest->selectSteps(
            '9.8.5',
            '10.0.0'
        );

        self::assertCount(
            2,
            $steps
        );
        self::assertInstanceOf(
            ManifestStepNineNine::class,
            $steps[0]
        );
        self::assertInstanceOf(
            ManifestStepTen::class,
            $steps[1]
        );
    }

    /**
     * Test that the source version boundary is exclusive.
     *
     * @return void
     */
    public function testSourceVersionBoundaryIsExclusive(): void {
        $manifest = new MigrationManifest([
            [
                'since' => '9.9.0',
                'step' => ManifestStepNineNine::class
            ],
            [
                'since' => '10.0.0',
                'step' => ManifestStepTen::class
            ]
        ]);

        $steps = $manifest->selectSteps(
            '9.9.0',
            '10.0.0'
        );

        self::assertCount(
            1,
            $steps
        );
        self::assertInstanceOf(
            ManifestStepTen::class,
            $steps[0]
        );
    }

    /**
     * Test that duplicate migration versions are rejected.
     *
     * @return void
     */
    public function testDuplicateVersionsAreRejected(): void {
        $this->expectException(
            \InvalidArgumentException::class
        );

        new MigrationManifest([
            [
                'since' => '9.9.0',
                'step' => ManifestStepNineNine::class
            ],
            [
                'since' => '9.9.0',
                'step' => ManifestStepNineNine::class
            ]
        ]);
    }

    /**
     * Test that manifest and step versions must match.
     *
     * @return void
     */
    public function testStepVersionMustMatchManifestVersion(): void {
        $this->expectException(
            \InvalidArgumentException::class
        );

        new MigrationManifest([
            [
                'since' => '9.9.0',
                'step' => ManifestMismatchedStep::class
            ]
        ]);
    }

    /**
     * Test that migration downgrades are rejected.
     *
     * @return void
     */
    public function testDowngradeIsRejected(): void {
        $manifest = new MigrationManifest([]);

        $this->expectException(
            \InvalidArgumentException::class
        );

        $manifest->selectSteps(
            '10.0.0',
            '9.9.0'
        );
    }
}
