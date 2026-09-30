<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\Support;

final class TemporaryProject {
    private string $base_path;

    /**
     * Create an isolated temporary OFW project directory.
     *
     * @throws \RuntimeException If the temporary directory cannot be created.
     */
    public function __construct() {
        $this->base_path = rtrim(
            sys_get_temp_dir(),
            '/\\'
        )
            . '/ofw-tests-'
            . bin2hex(
                random_bytes(8)
            );

        $config_path = $this->getPath(
            'src/Config'
        );

        if (
            !mkdir(
                $config_path,
                0755,
                true
            ) &&
            !is_dir($config_path)
        ) {
            throw new \RuntimeException(
                "Could not create temporary project directory '{$config_path}'."
            );
        }
    }

    /**
     * Get the project base path with a trailing slash.
     *
     * @return string Project base path.
     */
    public function getBasePath(): string {
        return $this->base_path . '/';
    }

    /**
     * Get a path inside the temporary project.
     *
     * @param string $relative_path Relative path.
     *
     * @return string Absolute path.
     */
    public function getPath(
        string $relative_path = ''
    ): string {
        if ($relative_path === '') {
            return $this->base_path;
        }

        return $this->base_path
            . '/'
            . ltrim(
                str_replace(
                    '\\',
                    '/',
                    $relative_path
                ),
                '/'
            );
    }

    /**
     * Write an application configuration file.
     *
     * @param array<string, mixed> $config Configuration values.
     * @param string|null $environment Environment name or null for Config.json.
     *
     * @return void
     *
     * @throws \JsonException If the configuration cannot be encoded.
     * @throws \RuntimeException If the configuration file cannot be written.
     */
    public function writeConfig(
        array $config,
        ?string $environment = null
    ): void {
        $file_name = $environment === null
            ? 'Config.json'
            : "Config_{$environment}.json";

        $path = $this->getPath(
            'src/Config/' . $file_name
        );

        $content = json_encode(
            $config,
            JSON_PRETTY_PRINT |
                JSON_UNESCAPED_SLASHES |
                JSON_THROW_ON_ERROR
        );

        if (
            file_put_contents(
                $path,
                $content,
                LOCK_EX
            ) === false
        ) {
            throw new \RuntimeException(
                "Could not write temporary configuration '{$path}'."
            );
        }
    }

    /**
     * Remove the complete temporary project.
     *
     * @return void
     *
     * @throws \RuntimeException If a temporary file or directory cannot be
     *                           removed.
     */
    public function remove(): void {
        self::removeDirectory(
            $this->base_path
        );
    }

    /**
     * Recursively remove a directory.
     *
     * @param string $path Directory path.
     *
     * @return void
     *
     * @throws \RuntimeException If the directory cannot be read or removed.
     */
    private static function removeDirectory(
        string $path
    ): void {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir(
            $path
        );

        if ($items === false) {
            throw new \RuntimeException(
                "Could not scan temporary directory '{$path}'."
            );
        }

        foreach ($items as $item) {
            if (
                $item === '.' ||
                $item === '..'
            ) {
                continue;
            }

            $item_path = $path
                . '/'
                . $item;

            if (
                is_file($item_path) ||
                is_link($item_path)
            ) {
                if (!unlink($item_path)) {
                    throw new \RuntimeException(
                        "Could not remove temporary file '{$item_path}'."
                    );
                }

                continue;
            }

            self::removeDirectory(
                $item_path
            );
        }

        if (!rmdir($path)) {
            throw new \RuntimeException(
                "Could not remove temporary directory '{$path}'."
            );
        }
    }
}
