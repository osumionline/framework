<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Migrations\Util;

use Closure;

final class GitStatus {
    /**
     * Ensure a Git-backed project has no uncommitted changes before migration.
     *
     * Projects without a .git directory or file are accepted because FilePatcher
     * provides rollback backups independently of Git.
     *
     * @param string $project_root Absolute project root path.
     * @param bool $force Whether the clean working tree check is bypassed.
     * @param (Closure(string): void)|null $logger Optional message writer.
     * @param bool $verbose Whether verbose messages are enabled.
     *
     * @return void
     *
     * @throws \RuntimeException If Git cannot inspect the repository or the
     *                           working tree contains changes.
     */
    public static function ensureCleanWorkingTree(
        string $project_root,
        bool $force = false,
        ?Closure $logger = null,
        bool $verbose = false
    ): void {
        if ($force) {
            self::writeVerbose(
                $logger,
                $verbose,
                '[OFW] Git working tree check skipped by force option.'
            );

            return;
        }

        $git_marker = rtrim(
            $project_root,
            '/\\'
        ) . '/.git';

        if (!file_exists($git_marker)) {
            self::writeVerbose(
                $logger,
                $verbose,
                '[OFW] Project is not Git-backed; using migration backups only.'
            );

            return;
        }

        [
            $exit_code,
            $stdout,
            $stderr
        ] = self::runGit(
            $project_root,
            [
                'status',
                '--porcelain=v1',
                '--untracked-files=normal'
            ]
        );

        if ($exit_code !== 0) {
            $details = trim(
                $stderr
            );

            throw new \RuntimeException(
                'Unable to inspect Git working tree.'
                    . ($details === ''
                        ? ''
                        : ' ' . $details)
            );
        }

        if (trim($stdout) !== '') {
            throw new \RuntimeException(
                'Framework migrations require a clean Git working tree. Commit, stash or discard local changes, or run with force enabled.'
            );
        }

        self::writeVerbose(
            $logger,
            $verbose,
            '[OFW] Git working tree is clean.'
        );
    }

    /**
     * Execute a Git command without invoking a command shell.
     *
     * @param string $project_root Absolute project root path.
     * @param list<string> $arguments Git arguments excluding the executable and project path.
     *
     * @return array{0: int, 1: string, 2: string} Exit code, stdout and stderr.
     *
     * @throws \RuntimeException If the Git process cannot be started or its pipes
     *                           cannot be read.
     */
    private static function runGit(
        string $project_root,
        array $arguments
    ): array {
        $command = [
            'git',
            '-C',
            $project_root,
            ...$arguments
        ];

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
                'Unable to start Git process.'
            );
        }

        fclose(
            $pipes[0]
        );

        $stdout = stream_get_contents(
            $pipes[1]
        );
        $stderr = stream_get_contents(
            $pipes[2]
        );

        fclose(
            $pipes[1]
        );
        fclose(
            $pipes[2]
        );

        $exit_code = proc_close(
            $process
        );

        if (
            $stdout === false ||
            $stderr === false
        ) {
            throw new \RuntimeException(
                'Unable to read Git process output.'
            );
        }

        return [
            $exit_code,
            $stdout,
            $stderr
        ];
    }

    /**
     * Write a Git status message only when verbose mode is enabled.
     *
     * @param (Closure(string): void)|null $logger Optional message writer.
     * @param bool $verbose Whether verbose output is enabled.
     * @param string $message Message to write.
     *
     * @return void
     */
    private static function writeVerbose(
        ?Closure $logger,
        bool $verbose,
        string $message
    ): void {
        if (
            $verbose &&
            $logger !== null
        ) {
            $logger(
                $message
            );
        }
    }
}
