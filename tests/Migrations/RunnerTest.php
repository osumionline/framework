<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\Migrations;

use Osumi\OsumiFramework\Migrations\Context\MigrationContext;
use Osumi\OsumiFramework\Migrations\Contract\MigrationStepInterface;
use Osumi\OsumiFramework\Migrations\MigrationManifest;
use Osumi\OsumiFramework\Migrations\Runner;
use Osumi\OsumiFramework\Migrations\State\StateStore;
use Osumi\OsumiFramework\Migrations\ValueObject\MigrationOptions;
use Osumi\OsumiFramework\Tests\Support\TemporaryProject;
use PHPUnit\Framework\TestCase;

final class RunnerSuccessfulStep implements MigrationStepInterface {
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
        return 'Create a migrated test file.';
    }

    /**
     * Apply the successful test migration.
     *
     * @param MigrationContext $context Migration context.
     *
     * @return void
     */
    public function apply(MigrationContext $context): void {
        $context->patcher->write(
            'src/Migrated.txt',
            'migrated'
        );
    }
}

final class RunnerFailingStep implements MigrationStepInterface {
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
        return 'Fail after creating a test file.';
    }

    /**
     * Apply the failing test migration.
     *
     * @param MigrationContext $context Migration context.
     *
     * @return void
     *
     * @throws \RuntimeException Always.
     */
    public function apply(MigrationContext $context): void {
        $context->patcher->write(
            'src/Failing.txt',
            'must-roll-back'
        );

        throw new \RuntimeException(
            'Intentional migration failure.'
        );
    }
}

final class RunnerTest extends TestCase {
    private TemporaryProject $project;

    /**
     * Prepare an isolated project for migration runner tests.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();

        $this->project = new TemporaryProject();
    }

    /**
     * Remove the temporary project after every test.
     *
     * @return void
     */
    protected function tearDown(): void {
        $this->project->remove();

        parent::tearDown();
    }

    /**
     * Test that successful migrations commit files and persist the executed step version.
     *
     * @return void
     */
    public function testSuccessfulMigrationCommitsAndStoresStepVersion(): void {
        $runner = new Runner(
            new MigrationManifest([
                [
                    'since' => '9.9.0',
                    'step' => RunnerSuccessfulStep::class
                ]
            ])
        );

        $runner->migrate(
            $this->project->getBasePath(),
            '9.8.5',
            '9.9.5',
            new MigrationOptions()
        );

        self::assertSame(
            'migrated',
            file_get_contents(
                $this->project->getPath(
                    'src/Migrated.txt'
                )
            )
        );

        $state_store = new StateStore(
            $this->project->getPath(
                'ofw/tmp'
            )
        );

        self::assertSame(
            '9.9.0',
            $state_store->readLastMigrated()
        );
    }

    /**
     * Test that dry-run executes steps without changing files or migration state.
     *
     * @return void
     */
    public function testDryRunDoesNotChangeProjectOrState(): void {
        $runner = new Runner(
            new MigrationManifest([
                [
                    'since' => '9.9.0',
                    'step' => RunnerSuccessfulStep::class
                ]
            ])
        );

        $runner->migrate(
            $this->project->getBasePath(),
            '9.8.5',
            '9.9.0',
            new MigrationOptions(
                dry_run: true
            )
        );

        self::assertFileDoesNotExist(
            $this->project->getPath(
                'src/Migrated.txt'
            )
        );

        $state_store = new StateStore(
            $this->project->getPath(
                'ofw/tmp'
            )
        );

        self::assertNull(
            $state_store->readLastMigrated()
        );
    }

    /**
     * Test that a failed later step rolls back all file changes and leaves state untouched.
     *
     * @return void
     */
    public function testFailureRollsBackCompleteMigrationTransaction(): void {
        $runner = new Runner(
            new MigrationManifest([
                [
                    'since' => '9.9.0',
                    'step' => RunnerSuccessfulStep::class
                ],
                [
                    'since' => '10.0.0',
                    'step' => RunnerFailingStep::class
                ]
            ])
        );

        try {
            $runner->migrate(
                $this->project->getBasePath(),
                '9.8.5',
                '10.0.0',
                new MigrationOptions()
            );

            self::fail(
                'The failing migration step did not throw an exception.'
            );
        } catch (\RuntimeException $exception) {
            self::assertSame(
                'Intentional migration failure.',
                $exception->getMessage()
            );
        }

        self::assertFileDoesNotExist(
            $this->project->getPath(
                'src/Migrated.txt'
            )
        );

        self::assertFileDoesNotExist(
            $this->project->getPath(
                'src/Failing.txt'
            )
        );

        $state_store = new StateStore(
            $this->project->getPath(
                'ofw/tmp'
            )
        );

        self::assertNull(
            $state_store->readLastMigrated()
        );
    }

    /**
     * Test framework version detection from the installed package Composer file.
     *
     * @return void
     *
     * @throws \JsonException If the fixture Composer file cannot be encoded.
     */
    public function testInstalledVersionIsDetectedFromVendorPackage(): void {
        $composer_file = $this->project->getPath(
            'vendor/osumionline/framework/composer.json'
        );

        $directory = dirname(
            $composer_file
        );

        if (
            !mkdir(
                $directory,
                0755,
                true
            ) &&
            !is_dir($directory)
        ) {
            self::fail(
                'Could not create temporary framework package directory.'
            );
        }

        $content = json_encode(
            [
                'name' => 'osumionline/framework',
                'version' => 'v9.9.0'
            ],
            JSON_PRETTY_PRINT |
                JSON_UNESCAPED_SLASHES |
                JSON_THROW_ON_ERROR
        );

        if (
            file_put_contents(
                $composer_file,
                $content,
                LOCK_EX
            ) === false
        ) {
            self::fail(
                'Could not create temporary framework Composer file.'
            );
        }

        self::assertSame(
            '9.9.0',
            Runner::detectInstalledVersion(
                $this->project->getBasePath()
            )
        );
    }
}
