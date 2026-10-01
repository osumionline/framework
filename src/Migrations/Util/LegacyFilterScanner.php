<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Migrations\Util;

use FilesystemIterator;
use ParseError;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Osumi\OsumiFramework\Migrations\ValueObject\LegacyFilterDefinition;

final class LegacyFilterScanner {
    private const string FILTER_NAMESPACE = 'Osumi\\OsumiFramework\\App\\Filter';
    private const string MIDDLEWARE_NAMESPACE = 'Osumi\\OsumiFramework\\App\\Middleware';

    /**
     * Discover legacy Filter classes in an OFW project.
     *
     * Filters are expected below src/Filter and must follow the application's
     * PSR-4 namespace and file naming conventions.
     *
     * @param string $project_root Project root path.
     *
     * @return list<LegacyFilterDefinition> Discovered legacy Filters.
     *
     * @throws \RuntimeException If the project cannot be resolved, a Filter
     *                           cannot be read or its structure is unsupported.
     */
    public function discover(string $project_root): array {
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

        $filter_root = $project_root
            . '/src/Filter';

        if (!is_dir($filter_root)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $filter_root,
                FilesystemIterator::SKIP_DOTS
            )
        );

        $definitions = [];

        foreach ($iterator as $file) {
            if ($file->isLink()) {
                throw new \RuntimeException(
                    "Legacy Filter path '{$file->getPathname()}' cannot be a symbolic link."
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

            $content = file_get_contents(
                $absolute_path
            );

            if ($content === false) {
                throw new \RuntimeException(
                    "Unable to read legacy Filter '{$relative_path}'."
                );
            }

            $definitions[] = $this->parseFilter(
                $relative_path,
                $content
            );
        }

        usort(
            $definitions,
            static fn(
                LegacyFilterDefinition $left,
                LegacyFilterDefinition $right
            ): int => strcmp(
                $left->filter_relative_path,
                $right->filter_relative_path
            )
        );

        return $definitions;
    }

    /**
     * Parse one legacy Filter source file.
     *
     * @param string $relative_path Project-relative Filter path.
     * @param string $source Filter PHP source.
     *
     * @return LegacyFilterDefinition Parsed Filter definition.
     *
     * @throws \RuntimeException If the Filter does not follow supported OFW conventions.
     */
    private function parseFilter(
        string $relative_path,
        string $source
    ): LegacyFilterDefinition {
        [
            $namespace,
            $class
        ] = $this->extractNamespaceAndClass(
            $source,
            $relative_path
        );

        $path_inside_filter = substr(
            $relative_path,
            strlen('src/Filter/')
        );

        $subdirectory = dirname(
            $path_inside_filter
        );

        $namespace_suffix = $subdirectory === '.'
            ? ''
            : '\\' . str_replace(
                '/',
                '\\',
                $subdirectory
            );

        $expected_namespace = self::FILTER_NAMESPACE
            . $namespace_suffix;

        if ($namespace !== $expected_namespace) {
            throw new \RuntimeException(
                "Legacy Filter '{$relative_path}' uses namespace '{$namespace}', expected '{$expected_namespace}'."
            );
        }

        $file_class = pathinfo(
            $relative_path,
            PATHINFO_FILENAME
        );

        if ($class !== $file_class) {
            throw new \RuntimeException(
                "Legacy Filter '{$relative_path}' declares class '{$class}', expected '{$file_class}'."
            );
        }

        if (
            !str_ends_with(
                $class,
                'Filter'
            )
        ) {
            throw new \RuntimeException(
                "Legacy Filter class '{$class}' must end with 'Filter'."
            );
        }

        $base_name = substr(
            $class,
            0,
            -strlen('Filter')
        );

        if ($base_name === '') {
            throw new \RuntimeException(
                "Legacy Filter '{$relative_path}' has an invalid class name."
            );
        }

        $middleware_class = $base_name
            . 'Middleware';

        $middleware_namespace = self::MIDDLEWARE_NAMESPACE
            . $namespace_suffix;

        $middleware_relative_path = 'src/Middleware/'
            . ($subdirectory === '.'
                ? ''
                : $subdirectory . '/')
            . $middleware_class
            . '.php';

        return new LegacyFilterDefinition(
            filter_relative_path: $relative_path,
            filter_namespace: $namespace,
            filter_class: $class,
            filter_fqcn: $namespace . '\\' . $class,
            middleware_relative_path: $middleware_relative_path,
            middleware_namespace: $middleware_namespace,
            middleware_class: $middleware_class,
            middleware_fqcn: $middleware_namespace . '\\' . $middleware_class
        );
    }

    /**
     * Extract the namespace and single declared class from PHP source.
     *
     * @param string $source PHP source.
     * @param string $relative_path Source path used in validation errors.
     *
     * @return array{0: string, 1: string} Namespace and class name.
     *
     * @throws \RuntimeException If the source cannot be parsed or does not
     *                           contain exactly one named class.
     */
    private function extractNamespaceAndClass(
        string $source,
        string $relative_path
    ): array {
        try {
            $tokens = token_get_all(
                $source,
                TOKEN_PARSE
            );
        } catch (ParseError $exception) {
            throw new \RuntimeException(
                "Legacy Filter '{$relative_path}' contains invalid PHP syntax.",
                0,
                $exception
            );
        }

        $namespace = null;
        $classes = [];

        $token_count = count(
            $tokens
        );

        for (
            $index = 0;
            $index < $token_count;
            $index++
        ) {
            $token = $tokens[$index];

            if (
                is_array($token) &&
                $token[0] === T_NAMESPACE
            ) {
                $namespace = '';

                for (
                    $namespace_index = $index + 1;
                    $namespace_index < $token_count;
                    $namespace_index++
                ) {
                    $namespace_token = $tokens[$namespace_index];

                    if (
                        $namespace_token === ';' ||
                        $namespace_token === '{'
                    ) {
                        break;
                    }

                    if (
                        is_array($namespace_token) &&
                        in_array(
                            $namespace_token[0],
                            [
                                T_STRING,
                                T_NAME_QUALIFIED,
                                T_NS_SEPARATOR
                            ],
                            true
                        )
                    ) {
                        $namespace .= $namespace_token[1];
                    }
                }

                continue;
            }

            if (
                !is_array($token) ||
                $token[0] !== T_CLASS
            ) {
                continue;
            }

            $previous = $this->getPreviousSignificantToken(
                $tokens,
                $index
            );

            if (
                $previous === T_DOUBLE_COLON ||
                $previous === T_NEW
            ) {
                continue;
            }

            $class_name = $this->getNextClassName(
                $tokens,
                $index
            );

            if ($class_name !== null) {
                $classes[] = $class_name;
            }
        }

        if (
            $namespace === null ||
            $namespace === ''
        ) {
            throw new \RuntimeException(
                "Legacy Filter '{$relative_path}' does not declare a namespace."
            );
        }

        if (count($classes) !== 1) {
            throw new \RuntimeException(
                "Legacy Filter '{$relative_path}' must declare exactly one named class."
            );
        }

        return [
            $namespace,
            $classes[0]
        ];
    }

    /**
     * Find the previous significant token identifier.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens PHP tokens.
     * @param int $index Current token index.
     *
     * @return int|string|null Token identifier or literal token.
     */
    private function getPreviousSignificantToken(
        array $tokens,
        int $index
    ): int|string|null {
        for (
            $position = $index - 1;
            $position >= 0;
            $position--
        ) {
            $token = $tokens[$position];

            if (
                is_array($token) &&
                in_array(
                    $token[0],
                    [
                        T_WHITESPACE,
                        T_COMMENT,
                        T_DOC_COMMENT
                    ],
                    true
                )
            ) {
                continue;
            }

            return is_array($token)
                ? $token[0]
                : $token;
        }

        return null;
    }

    /**
     * Find the class name following a class declaration token.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens PHP tokens.
     * @param int $index Class token index.
     *
     * @return string|null Declared class name or null.
     */
    private function getNextClassName(
        array $tokens,
        int $index
    ): ?string {
        $token_count = count(
            $tokens
        );

        for (
            $position = $index + 1;
            $position < $token_count;
            $position++
        ) {
            $token = $tokens[$position];

            if (
                is_array($token) &&
                $token[0] === T_STRING
            ) {
                return $token[1];
            }

            if (
                is_array($token) &&
                in_array(
                    $token[0],
                    [
                        T_WHITESPACE,
                        T_COMMENT,
                        T_DOC_COMMENT,
                        T_FINAL,
                        T_ABSTRACT,
                        T_READONLY
                    ],
                    true
                )
            ) {
                continue;
            }

            if (!is_array($token)) {
                return null;
            }
        }

        return null;
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
