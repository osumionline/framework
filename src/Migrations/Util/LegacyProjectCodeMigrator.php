<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Migrations\Util;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Osumi\OsumiFramework\Migrations\ValueObject\LegacyFilterDefinition;

final class LegacyProjectCodeMigrator {
    private LegacyPhpSourceTransformer $source_transformer;
    private LegacyRouteTransformer $route_transformer;

    /**
     * Create a project code migrator for discovered legacy Filters.
     *
     * @param list<LegacyFilterDefinition> $definitions Legacy Filter definitions.
     */
    public function __construct(array $definitions) {
        $this->source_transformer = new LegacyPhpSourceTransformer();
        $this->route_transformer = new LegacyRouteTransformer(
            $definitions
        );
    }

    /**
     * Build all PHP source changes required by the 9.9 Filter migration.
     *
     * Legacy src/Filter code and generated/native src/Middleware code are
     * intentionally excluded. The complete project is analyzed before any
     * migration step writes changes through FilePatcher.
     *
     * @param string $project_root Project root path.
     *
     * @return array<string, string> Changed project-relative paths and contents.
     *
     * @throws \RuntimeException If the project cannot be resolved, contains an
     *                           unsafe symbolic link or a PHP file cannot be read.
     */
    public function plan(string $project_root): array {
        $resolved_root = realpath(
            $project_root
        );

        if ($resolved_root === false) {
            throw new \RuntimeException(
                "Unable to resolve migration project root '{$project_root}'."
            );
        }

        $project_root = $this->normalizePath(
            $resolved_root
        );

        $src_root = $project_root
            . '/src';

        if (!is_dir($src_root)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $src_root,
                FilesystemIterator::SKIP_DOTS
            )
        );

        $changes = [];

        foreach ($iterator as $file) {
            if ($file->isLink()) {
                throw new \RuntimeException(
                    "Migration source path '{$file->getPathname()}' cannot be a symbolic link."
                );
            }

            if (
                !$file->isFile() ||
                strtolower(
                    $file->getExtension()
                ) !== 'php'
            ) {
                continue;
            }

            $absolute_path = $this->normalizePath(
                $file->getPathname()
            );

            $relative_path = substr(
                $absolute_path,
                strlen($project_root) + 1
            );

            if (
                str_starts_with(
                    $relative_path,
                    'src/Filter/'
                ) ||
                str_starts_with(
                    $relative_path,
                    'src/Middleware/'
                )
            ) {
                continue;
            }

            $source = file_get_contents(
                $absolute_path
            );

            if ($source === false) {
                throw new \RuntimeException(
                    "Unable to read migration source '{$relative_path}'."
                );
            }

            $migrated = $this->source_transformer->transform(
                $relative_path,
                $source
            );

            if (
                str_starts_with(
                    $relative_path,
                    'src/Routes/'
                )
            ) {
                $migrated = $this->route_transformer->transform(
                    $relative_path,
                    $migrated
                );
            }

            if ($migrated !== $source) {
                $changes[$relative_path] = $migrated;
            }
        }

        ksort(
            $changes
        );

        return $changes;
    }

    /**
     * Normalize a filesystem path for cross-platform comparisons.
     *
     * @param string $path Filesystem path.
     *
     * @return string Normalized path.
     */
    private function normalizePath(string $path): string {
        return rtrim(
            str_replace(
                '\\',
                '/',
                $path
            ),
            '/'
        );
    }
}
