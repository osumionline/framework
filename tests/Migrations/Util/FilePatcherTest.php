<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\Migrations\Util;

use Osumi\OsumiFramework\Migrations\Util\FilePatcher;
use Osumi\OsumiFramework\Tests\Support\TemporaryProject;
use PHPUnit\Framework\TestCase;

final class FilePatcherTest extends TestCase {
    private TemporaryProject $project;

    /**
     * Prepare an isolated project for filesystem migration tests.
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
     * Test that rollback restores modified and deleted files and removes new files.
     *
     * @return void
     */
    public function testRollbackRestoresOriginalProjectState(): void {
        $original_file = $this->project->getPath(
            'src/Original.php'
        );

        $deleted_file = $this->project->getPath(
            'src/Delete.php'
        );

        $this->writeFile(
            $original_file,
            'original'
        );

        $this->writeFile(
            $deleted_file,
            'delete-me'
        );

        $patcher = new FilePatcher(
            $this->project->getBasePath(),
            $this->project->getPath(
                'ofw/tmp'
            )
        );

        $patcher->write(
            'src/Original.php',
            'changed'
        );

        $patcher->write(
            'src/Middleware/NewMiddleware.php',
            'new'
        );

        $patcher->delete(
            'src/Delete.php'
        );

        $patcher->rollback();

        self::assertSame(
            'original',
            file_get_contents(
                $original_file
            )
        );

        self::assertSame(
            'delete-me',
            file_get_contents(
                $deleted_file
            )
        );

        self::assertFileDoesNotExist(
            $this->project->getPath(
                'src/Middleware/NewMiddleware.php'
            )
        );

        self::assertDirectoryDoesNotExist(
            $this->project->getPath(
                'src/Middleware'
            )
        );
    }

    /**
     * Test that commit keeps migrated files and stores their backups.
     *
     * @return void
     */
    public function testCommitKeepsChangesAndBackupManifest(): void {
        $original_file = $this->project->getPath(
            'src/Original.php'
        );

        $this->writeFile(
            $original_file,
            'original'
        );

        $patcher = new FilePatcher(
            $this->project->getBasePath(),
            $this->project->getPath(
                'ofw/tmp'
            )
        );

        $patcher->write(
            'src/Original.php',
            'changed'
        );

        $patcher->commit();

        self::assertSame(
            'changed',
            file_get_contents(
                $original_file
            )
        );

        self::assertSame(
            'original',
            file_get_contents(
                $patcher->getBackupRoot()
                    . '/files/src/Original.php'
            )
        );

        self::assertFileExists(
            $patcher->getBackupRoot()
                . '/manifest.json'
        );
    }

    /**
     * Test that dry-run reports changes without touching the filesystem.
     *
     * @return void
     */
    public function testDryRunDoesNotModifyProject(): void {
        $messages = [];

        $original_file = $this->project->getPath(
            'src/Original.php'
        );

        $this->writeFile(
            $original_file,
            'original'
        );

        $patcher = new FilePatcher(
            $this->project->getBasePath(),
            $this->project->getPath(
                'ofw/tmp'
            ),
            true,
            false,
            static function (string $message) use (&$messages): void {
                $messages[] = $message;
            }
        );

        $patcher->write(
            'src/Original.php',
            'changed'
        );

        $patcher->write(
            'src/New.php',
            'new'
        );

        $patcher->commit();

        self::assertSame(
            'original',
            file_get_contents(
                $original_file
            )
        );

        self::assertFileDoesNotExist(
            $this->project->getPath(
                'src/New.php'
            )
        );

        self::assertDirectoryDoesNotExist(
            $patcher->getBackupRoot()
        );

        self::assertCount(
            2,
            $messages
        );
    }

    /**
     * Test that project traversal paths are rejected.
     *
     * @return void
     */
    public function testUnsafeParentPathIsRejected(): void {
        $patcher = new FilePatcher(
            $this->project->getBasePath(),
            $this->project->getPath(
                'ofw/tmp'
            )
        );

        $this->expectException(
            \InvalidArgumentException::class
        );

        $patcher->write(
            '../outside.php',
            'invalid'
        );
    }

    /**
     * Test that a native filesystem write failure is exposed as a stable
     * migration exception and previous changes can still be rolled back.
     *
     * @return void
     */
    public function testWriteFailureUsesMigrationExceptionAndAllowsRollback(): void {
        $state_path = $this->project->getPath(
            'ofw/tmp/state.json'
        );

        if (
            !mkdir(
                $state_path,
                0755,
                true
            ) &&
            !is_dir($state_path)
        ) {
            self::fail(
                'Could not create conflicting migration state directory.'
            );
        }

        $patcher = new FilePatcher(
            $this->project->getBasePath(),
            $this->project->getPath(
                'ofw/tmp'
            )
        );

        $patcher->write(
            'src/BeforeFailure.php',
            'created-before-failure'
        );

        try {
            $patcher->write(
                'ofw/tmp/state.json',
                '{}'
            );

            self::fail(
                'Writing over a directory did not fail.'
            );
        } catch (\RuntimeException $exception) {
            self::assertSame(
                "Unable to write migration file 'ofw/tmp/state.json'.",
                $exception->getMessage()
            );
        }

        $temporary_files = glob(
            $this->project->getPath(
                'ofw/tmp/ofw*'
            )
        );

        if ($temporary_files === false) {
            self::fail(
                'Could not inspect temporary migration files.'
            );
        }

        self::assertSame(
            [],
            $temporary_files
        );

        $patcher->rollback();

        self::assertFileDoesNotExist(
            $this->project->getPath(
                'src/BeforeFailure.php'
            )
        );

        self::assertDirectoryExists(
            $state_path
        );
    }

    /**
     * Test that atomic writes replace the destination without leaving temporary
     * files behind.
     *
     * @return void
     */
    public function testAtomicWriteReplacesFileWithoutTemporaryResidue(): void {
        $file = $this->project->getPath(
            'src/Atomic.php'
        );

        $this->writeFile(
            $file,
            'original'
        );

        $patcher = new FilePatcher(
            $this->project->getBasePath(),
            $this->project->getPath(
                'ofw/tmp'
            )
        );

        $patcher->write(
            'src/Atomic.php',
            'replaced'
        );

        if (PHP_OS_FAMILY !== 'Windows') {
            if (!chmod(
                $file,
                0640
            )) {
                self::fail(
                    'Could not set test file permissions.'
                );
            }
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            clearstatcache(
                true,
                $file
            );

            $permissions = fileperms(
                $file
            );

            self::assertNotFalse(
                $permissions
            );

            self::assertSame(
                0640,
                $permissions & 0777
            );
        }

        self::assertSame(
            'replaced',
            file_get_contents(
                $file
            )
        );

        $temporary_files = glob(
            $this->project->getPath(
                'src/ofw*'
            )
        );

        if ($temporary_files === false) {
            self::fail(
                'Could not inspect temporary migration files.'
            );
        }

        self::assertSame(
            [],
            $temporary_files
        );

        $patcher->rollback();

        self::assertSame(
            'original',
            file_get_contents(
                $file
            )
        );
    }

    /**
     * Write a test file, creating its parent directory when necessary.
     *
     * @param string $path Absolute file path.
     * @param string $content File contents.
     *
     * @return void
     */
    private function writeFile(
        string $path,
        string $content
    ): void {
        $directory = dirname(
            $path
        );

        if (
            !is_dir($directory) &&
            !mkdir(
                $directory,
                0755,
                true
            ) &&
            !is_dir($directory)
        ) {
            self::fail(
                "Could not create temporary directory '{$directory}'."
            );
        }

        if (
            file_put_contents(
                $path,
                $content,
                LOCK_EX
            ) === false
        ) {
            self::fail(
                "Could not create temporary file '{$path}'."
            );
        }
    }
}
