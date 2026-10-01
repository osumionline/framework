<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Migrations\Util;

use Closure;

final class GitStatus {
    /**
     * Ensure a Git-backed project has no unexpected uncommitted changes before migration.
     *
     * Projects without a .git directory or file are accepted because FilePatcher
     * provides rollback backups independently of Git. Explicitly ignored paths
     * remain excluded from the cleanliness check, which allows Composer-managed
     * files to change while protecting application source code.
     *
     * @param string $project_root Absolute project root path.
     * @param bool $force Whether the clean working tree check is bypassed.
     * @param (Closure(string): void)|null $logger Optional message writer.
     * @param bool $verbose Whether verbose messages are enabled.
     * @param list<string> $ignored_paths Project-relative paths ignored by Git status.
     *
     * @return void
     *
     * @throws \InvalidArgumentException If an ignored path is unsafe.
     * @throws \RuntimeException If Git cannot inspect the repository or the
     *                           working tree contains unexpected changes.
     */
    public static function ensureCleanWorkingTree(
        string $project_root,
        bool $force = false,
        ?Closure $logger = null,
        bool $verbose = false,
        array $ignored_paths = []
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
        )
            . '/.git';

        if (!file_exists($git_marker)) {
            self::writeVerbose(
                $logger,
                $verbose,
                '[OFW] Project is not Git-backed; using migration backups only.'
            );

            return;
        }

        $arguments = [
            'status',
            '--porcelain=v1',
            '--untracked-files=normal'
        ];

        if ($ignored_paths !== []) {
            $arguments[] = '--';
            $arguments[] = '.';

            foreach ($ignored_paths as $ignored_path) {
                $arguments[] = ':(top,literal,exclude)'
                    . self::normalizeIgnoredPath(
                        $ignored_path
                    );
            }
        }

        [
            $exit_code,
            $stdout,
            $stderr
        ] = self::runGit(
            $project_root,
            $arguments
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
            $ignored_paths === []
                ? '[OFW] Git working tree is clean.'
                : '[OFW] Git working tree is clean except for explicitly ignored paths.'
        );
    }

    /**
     * Normalize and validate a Git path excluded from the working tree check.
     *
     * Paths must be relative to the project root. Git receives them through a
     * top-level literal pathspec, so glob characters have no special meaning.
     *
     * @param string $path Project-relative path.
     *
     * @return string Normalized project-relative path.
     *
     * @throws \InvalidArgumentException If the path is empty, absolute or escapes
     *                                   the project root.
     */
    private static function normalizeIgnoredPath(string $path): string {
        if (
            str_contains(
                $path,
                "\0"
            )
        ) {
            throw new \InvalidArgumentException(
                'Ignored Git path cannot contain null bytes.'
            );
        }

        $path = str_replace(
            '\\',
            '/',
            trim(
                $path
            )
        );

        if (
            $path === '' ||
            str_starts_with(
                $path,
                '/'
            ) ||
            preg_match(
                '/^[A-Za-z]:\//D',
                $path
            ) === 1
        ) {
            throw new \InvalidArgumentException(
                "Ignored Git path '{$path}' must be project-relative."
            );
        }

        $parts = explode(
            '/',
            $path
        );

        $normalized = [];

        foreach ($parts as $part) {
            if (
                $part === '' ||
                $part === '.'
            ) {
                continue;
            }

            if ($part === '..') {
                throw new \InvalidArgumentException(
                    "Ignored Git path '{$path}' cannot contain parent segments."
                );
            }

            $normalized[] = $part;
        }

        if ($normalized === []) {
            throw new \InvalidArgumentException(
                'Ignored Git path cannot be empty.'
            );
        }

        return implode(
            '/',
            $normalized
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
