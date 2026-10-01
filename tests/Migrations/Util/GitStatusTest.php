<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\Migrations\Util;

use Osumi\OsumiFramework\Migrations\Util\GitStatus;
use Osumi\OsumiFramework\Tests\Support\TemporaryProject;
use PHPUnit\Framework\TestCase;

final class GitStatusTest extends TestCase {
    private TemporaryProject $project;

    /**
     * Prepare an isolated project for Git migration checks.
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
     * Test that projects without Git metadata are accepted.
     *
     * @return void
     */
    public function testProjectWithoutGitIsAccepted(): void {
        GitStatus::ensureCleanWorkingTree(
            $this->project->getBasePath()
        );

        self::assertTrue(
            true
        );
    }

    /**
     * Test that force bypasses Git inspection.
     *
     * @return void
     */
    public function testForceBypassesGitInspection(): void {
        $git_marker = $this->project->getPath(
            '.git'
        );

        if (
            file_put_contents(
                $git_marker,
                'invalid',
                LOCK_EX
            ) === false
        ) {
            self::fail(
                'Could not create fake Git marker.'
            );
        }

        GitStatus::ensureCleanWorkingTree(
            $this->project->getBasePath(),
            true
        );

        self::assertTrue(
            true
        );
    }

    /**
     * Test that an untracked file makes a Git project unsafe to migrate.
     *
     * @return void
     */
    public function testDirtyGitWorkingTreeIsRejected(): void {
        if (!$this->isGitAvailable()) {
            self::markTestSkipped(
                'Git is not available in the test environment.'
            );
        }

        $this->runGit(
            [
                'init',
                '--quiet'
            ]
        );

        if (
            file_put_contents(
                $this->project->getPath(
                    'dirty.txt'
                ),
                'dirty',
                LOCK_EX
            ) === false
        ) {
            self::fail(
                'Could not create dirty Git test file.'
            );
        }

        $this->expectException(
            \RuntimeException::class
        );

        GitStatus::ensureCleanWorkingTree(
            $this->project->getBasePath()
        );
    }

    /**
     * Test that explicitly ignored Composer files may be dirty.
     *
     * @return void
     */
    public function testIgnoredComposerFilesAreAccepted(): void {
        if (!$this->isGitAvailable()) {
            self::markTestSkipped(
                'Git is not available in the test environment.'
            );
        }

        $this->initializeCleanGitRepository();

        if (
            file_put_contents(
                $this->project->getPath(
                    'composer.json'
                ),
                "{}\n",
                LOCK_EX
            ) === false ||
            file_put_contents(
                $this->project->getPath(
                    'composer.lock'
                ),
                "{}\n",
                LOCK_EX
            ) === false
        ) {
            self::fail(
                'Could not create dirty Composer files.'
            );
        }

        GitStatus::ensureCleanWorkingTree(
            project_root: $this->project->getBasePath(),
            ignored_paths: [
                'composer.json',
                'composer.lock'
            ]
        );

        self::assertTrue(
            true
        );
    }

    /**
     * Test that ignored Composer files do not hide application source changes.
     *
     * @return void
     */
    public function testIgnoredComposerFilesDoNotHideSourceChanges(): void {
        if (!$this->isGitAvailable()) {
            self::markTestSkipped(
                'Git is not available in the test environment.'
            );
        }

        $this->initializeCleanGitRepository();

        if (
            file_put_contents(
                $this->project->getPath(
                    'composer.json'
                ),
                "{}\n",
                LOCK_EX
            ) === false
        ) {
            self::fail(
                'Could not create dirty Composer file.'
            );
        }

        $source_path = $this->project->getPath(
            'src/Dirty.php'
        );

        if (
            file_put_contents(
                $source_path,
                "<?php\n",
                LOCK_EX
            ) === false
        ) {
            self::fail(
                'Could not create dirty source file.'
            );
        }

        $this->expectException(
            \RuntimeException::class
        );

        GitStatus::ensureCleanWorkingTree(
            project_root: $this->project->getBasePath(),
            ignored_paths: [
                'composer.json',
                'composer.lock'
            ]
        );
    }

    /**
     * Initialize and commit the temporary project as a clean Git repository.
     *
     * Composer files are committed as part of the baseline so tests can simulate
     * the tracked modifications produced by a real Composer update.
     *
     * @return void
     */
    private function initializeCleanGitRepository(): void {
        $this->runGit([
            'init',
            '--quiet'
        ]);

        $this->runGit([
            'config',
            'user.email',
            'ofw-tests@example.test'
        ]);

        $this->runGit([
            'config',
            'user.name',
            'OFW Tests'
        ]);

        if (
            file_put_contents(
                $this->project->getPath(
                    'composer.json'
                ),
                "{\"baseline\":true}\n",
                LOCK_EX
            ) === false
        ) {
            self::fail(
                'Could not create baseline composer.json.'
            );
        }

        if (
            file_put_contents(
                $this->project->getPath(
                    'composer.lock'
                ),
                "{\"baseline\":true}\n",
                LOCK_EX
            ) === false
        ) {
            self::fail(
                'Could not create baseline composer.lock.'
            );
        }

        $this->runGit([
            'add',
            'composer.json',
            'composer.lock'
        ]);

        $this->runGit([
            'commit',
            '--quiet',
            '-m',
            'Test baseline'
        ]);
    }

    /**
     * Check whether the Git executable is available.
     *
     * @return bool Whether Git can be executed.
     */
    private function isGitAvailable(): bool {
        try {
            return $this->runProcess(
                [
                    'git',
                    '--version'
                ]
            ) === 0;
        } catch (\RuntimeException) {
            return false;
        }
    }

    /**
     * Execute Git inside the temporary project.
     *
     * @param list<string> $arguments Git arguments.
     *
     * @return void
     */
    private function runGit(array $arguments): void {
        $exit_code = $this->runProcess(
            [
                'git',
                '-C',
                $this->project->getBasePath(),
                ...$arguments
            ]
        );

        self::assertSame(
            0,
            $exit_code
        );
    }

    /**
     * Execute a process without using a command shell.
     *
     * @param list<string> $command Process command and arguments.
     *
     * @return int Process exit code.
     *
     * @throws \RuntimeException If the process cannot be started.
     */
    private function runProcess(array $command): int {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];

        $pipes = [];

        $process = proc_open(
            $command,
            $descriptors,
            $pipes
        );

        if (!is_resource($process)) {
            throw new \RuntimeException(
                'Could not start test process.'
            );
        }

        fclose(
            $pipes[0]
        );

        stream_get_contents(
            $pipes[1]
        );

        stream_get_contents(
            $pipes[2]
        );

        fclose(
            $pipes[1]
        );

        fclose(
            $pipes[2]
        );

        return proc_close(
            $process
        );
    }
}
