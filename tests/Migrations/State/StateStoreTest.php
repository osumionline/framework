<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\Migrations\State;

use Osumi\OsumiFramework\Migrations\State\StateStore;
use Osumi\OsumiFramework\Tests\Support\TemporaryProject;
use PHPUnit\Framework\TestCase;

final class StateStoreTest extends TestCase {
    private TemporaryProject $project;

    /**
     * Prepare an isolated migration state directory.
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
     * Test that a missing state file returns no migrated version.
     *
     * @return void
     */
    public function testMissingStateReturnsNull(): void {
        $store = new StateStore(
            $this->project->getBasePath(),
            $this->project->getPath(
                'ofw/tmp'
            )
        );

        self::assertNull(
            $store->readLastMigrated()
        );
    }

    /**
     * Test that the last migrated version can be persisted and loaded.
     *
     * @return void
     */
    public function testStateCanBeWrittenAndRead(): void {
        $store = new StateStore(
            $this->project->getBasePath(),
            $this->project->getPath(
                'ofw/tmp'
            )
        );

        $store->writeLastMigrated(
            '9.9.0'
        );

        self::assertSame(
            '9.9.0',
            $store->readLastMigrated()
        );

        self::assertFileExists(
            $store->getStateFile()
        );
    }

    /**
     * Test that an invalid version cannot be stored.
     *
     * @return void
     */
    public function testInvalidVersionIsRejected(): void {
        $store = new StateStore(
            $this->project->getBasePath(),
            $this->project->getPath(
                'ofw/tmp'
            )
        );

        $this->expectException(
            \InvalidArgumentException::class
        );

        $store->writeLastMigrated(
            'main'
        );
    }

    /**
     * Test that malformed JSON state is reported instead of silently ignored.
     *
     * @return void
     */
    public function testMalformedStateIsRejected(): void {
        $state_file = $this->project->getPath(
            '.ofw-migrations.json'
        );

        if (
            file_put_contents(
                $state_file,
                '{invalid',
                LOCK_EX
            ) === false
        ) {
            self::fail(
                'Could not create malformed migration state file.'
            );
        }

        $store = new StateStore(
            $this->project->getBasePath(),
            $this->project->getPath(
                'ofw/tmp'
            )
        );

        $this->expectException(
            \JsonException::class
        );

        $store->readLastMigrated();
    }

    /**
     * Test that migration state contents can be built without writing a file.
     *
     * @return void
     */
    public function testStateContentCanBeBuiltWithoutWriting(): void {
        $store = new StateStore(
            $this->project->getBasePath(),
            $this->project->getPath(
                'ofw/tmp'
            )
        );

        $content = $store->buildLastMigratedContent(
            '9.9.0'
        );

        self::assertSame(
            "{\n"
                . "    \"last_migrated\": \"9.9.0\"\n"
                . "}\n",
            $content
        );

        self::assertFileDoesNotExist(
            $store->getStateFile()
        );
    }

    /**
     * Test that legacy pre-9.12 state can still be read.
     *
     * @return void
     */
    public function testLegacyStateIsUsedWhenProjectStateDoesNotExist(): void {
        $legacy_directory = $this->project->getPath(
            'ofw/tmp'
        );

        if (
            !is_dir($legacy_directory) &&
            !mkdir(
                $legacy_directory,
                0755,
                true
            ) &&
            !is_dir($legacy_directory)
        ) {
            self::fail(
                'Could not create legacy migration state directory.'
            );
        }

        if (
            file_put_contents(
                $legacy_directory . '/state.json',
                "{\n    \"last_migrated\": \"9.9.0\"\n}\n",
                LOCK_EX
            ) === false
        ) {
            self::fail(
                'Could not create legacy migration state.'
            );
        }

        $store = new StateStore(
            $this->project->getBasePath(),
            $legacy_directory
        );

        self::assertSame(
            '9.9.0',
            $store->readLastMigrated()
        );

        self::assertFileDoesNotExist(
            $store->getStateFile()
        );
    }

    /**
     * Test that project state takes precedence over legacy state.
     *
     * @return void
     */
    public function testProjectStateTakesPrecedenceOverLegacyState(): void {
        $legacy_directory = $this->project->getPath(
            'ofw/tmp'
        );

        if (
            !mkdir(
                $legacy_directory,
                0755,
                true
            ) &&
            !is_dir($legacy_directory)
        ) {
            self::fail(
                'Could not create legacy migration state directory.'
            );
        }

        if (
            file_put_contents(
                $legacy_directory . '/state.json',
                "{\n    \"last_migrated\": \"9.9.0\"\n}\n",
                LOCK_EX
            ) === false
        ) {
            self::fail(
                'Could not create legacy migration state.'
            );
        }

        $store = new StateStore(
            $this->project->getBasePath(),
            $legacy_directory
        );

        $store->writeLastMigrated(
            '9.12.0'
        );

        self::assertSame(
            '9.12.0',
            $store->readLastMigrated()
        );
    }
}
