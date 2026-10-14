<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Migrations\State;

use Osumi\OsumiFramework\Migrations\Util\MigrationVersion;

final class StateStore {
    private const string STATE_FILE = '.ofw-migrations.json';
    private const string LEGACY_STATE_FILE = 'state.json';

    private string $project_root;
    private ?string $legacy_state_directory;

    /**
     * Create a migration state store.
     *
     * The primary migration state is stored in the project root so it can be
     * versioned together with the application. An optional legacy state
     * directory may be supplied to read pre-9.12 state.
     *
     * @param string $project_root Project root directory.
     * @param string|null $legacy_state_directory Legacy state directory.
     */
    public function __construct(
        string $project_root,
        ?string $legacy_state_directory = null
    ) {
        $this->project_root = rtrim(
            str_replace(
                '\\',
                '/',
                $project_root
            ),
            '/'
        );

        $this->legacy_state_directory = $legacy_state_directory === null
            ? null
            : rtrim(
                str_replace(
                    '\\',
                    '/',
                    $legacy_state_directory
                ),
                '/'
            );
    }

    /**
     * Get the complete migration state file path.
     *
     * @return string Migration state file path.
     */
    public function getStateFile(): string {
        return $this->project_root
            . '/'
            . self::STATE_FILE;
    }

    /**
     * Get the legacy migration state file path.
     *
     * @return string|null Legacy state path, or null when unavailable.
     */
    public function getLegacyStateFile(): ?string {
        if ($this->legacy_state_directory === null) {
            return null;
        }

        return $this->legacy_state_directory
            . '/'
            . self::LEGACY_STATE_FILE;
    }

    /**
     * Read the last successfully migrated framework version.
     *
     * The versioned project state takes precedence over the legacy temporary
     * state used before OFW 9.12.
     *
     * @return string|null Last migrated version or null when no state exists.
     *
     * @throws \JsonException If the state file contains invalid JSON.
     * @throws \RuntimeException If the state file cannot be read or has an invalid structure.
     */
    public function readLastMigrated(): ?string {
        $state_file = $this->getStateFile();

        if (!is_file($state_file)) {
            $legacy_state_file = $this->getLegacyStateFile();

            if (
                $legacy_state_file === null ||
                !is_file($legacy_state_file)
            ) {
                return null;
            }

            $state_file = $legacy_state_file;
        }

        return $this->readStateFile(
            $state_file
        );
    }

    /**
     * Read and validate a migration state file.
     *
     * @param string $state_file State file path.
     *
     * @return string Last migrated framework version.
     *
     * @throws \JsonException If the file contains invalid JSON.
     * @throws \RuntimeException If the file cannot be read or has an invalid structure.
     */
    private function readStateFile(
        string $state_file
    ): string {
        $content = file_get_contents(
            $state_file
        );

        if ($content === false) {
            throw new \RuntimeException(
                "Unable to read migration state file '{$state_file}'."
            );
        }

        $data = json_decode(
            $content,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (
            !is_array($data) ||
            !array_key_exists(
                'last_migrated',
                $data
            ) ||
            !is_string($data['last_migrated']) ||
            !MigrationVersion::isValid(
                $data['last_migrated']
            )
        ) {
            throw new \RuntimeException(
                "Migration state file '{$state_file}' has an invalid structure."
            );
        }

        return $data['last_migrated'];
    }

    /**
     * Build the serialized migration state for a successfully migrated version.
     *
     * This method does not write anything to disk. It allows the migration runner
     * to include the migration state in the same FilePatcher transaction as
     * application file changes.
     * file changes.
     *
     * @param string $version Framework version.
     *
     * @return string Serialized migration state including the final newline.
     *
     * @throws \InvalidArgumentException If the version format is invalid.
     * @throws \JsonException If the state data cannot be encoded.
     */
    public function buildLastMigratedContent(string $version): string {
        if (!MigrationVersion::isValid($version)) {
            throw new \InvalidArgumentException(
                "Invalid migration version '{$version}'."
            );
        }

        $content = json_encode(
            [
                'last_migrated' => $version
            ],
            JSON_PRETTY_PRINT |
                JSON_UNESCAPED_SLASHES |
                JSON_THROW_ON_ERROR
        );

        return $content
            . "\n";
    }

    /**
     * Persist the last successfully migrated framework version.
     *
     * Direct writes remain available for callers that do not require a migration
     * file transaction. Runner uses buildLastMigratedContent() together with
     * FilePatcher so project changes and migration state are committed atomically.
     *
     * @param string $version Framework version.
     *
     * @return void
     *
     * @throws \InvalidArgumentException If the version format is invalid.
     * @throws \JsonException If the state data cannot be encoded.
     * @throws \RuntimeException If the state directory or file cannot be written.
     */
    public function writeLastMigrated(string $version): void {
        $content = $this->buildLastMigratedContent(
            $version
        );

        $state_file = $this->getStateFile();

        try {
            $result = file_put_contents(
                $state_file,
                $content,
                LOCK_EX
            );
        } catch (\Throwable $exception) {
            throw new \RuntimeException(
                "Unable to write migration state file '{$state_file}'.",
                0,
                $exception
            );
        }

        if ($result === false) {
            throw new \RuntimeException(
                "Unable to write migration state file '{$state_file}'."
            );
        }
    }
}
