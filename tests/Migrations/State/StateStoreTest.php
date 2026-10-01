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
        $state_directory = $this->project->getPath(
            'ofw/tmp'
        );

        if (
            !mkdir(
                $state_directory,
                0755,
                true
            ) &&
            !is_dir($state_directory)
        ) {
            self::fail(
                'Could not create temporary migration state directory.'
            );
        }

        if (
            file_put_contents(
                $state_directory . '/state.json',
                '{invalid',
                LOCK_EX
            ) === false
        ) {
            self::fail(
                'Could not create malformed migration state file.'
            );
        }

        $store = new StateStore(
            $state_directory
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
}
