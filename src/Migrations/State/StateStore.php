<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Migrations\State;

final class StateStore {
    private const string STATE_FILE = 'state.json';

    private string $state_directory;

    /**
     * Create a migration state store.
     *
     * The supplied directory is expected to be OConfig::getDir('ofw_tmp').
     *
     * @param string $state_directory Directory containing the migration state.
     */
    public function __construct(string $state_directory) {
        $this->state_directory = rtrim(
            str_replace('\\', '/', $state_directory),
            '/'
        );
    }

    /**
     * Get the complete migration state file path.
     *
     * @return string Migration state file path.
     */
    public function getStateFile(): string {
        return $this->state_directory
            . '/'
            . self::STATE_FILE;
    }

    /**
     * Read the last successfully migrated framework version.
     *
     * @return string|null Last migrated version or null when no state exists.
     *
     * @throws \JsonException If the state file contains invalid JSON.
     * @throws \RuntimeException If the state file cannot be read or has an invalid structure.
     */
    public function readLastMigrated(): ?string {
        $state_file = $this->getStateFile();

        if (!is_file($state_file)) {
            return null;
        }

        $content = file_get_contents($state_file);

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
            !$this->isValidVersion(
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
     * Persist the last successfully migrated framework version.
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
        if (!$this->isValidVersion($version)) {
            throw new \InvalidArgumentException(
                "Invalid migration version '{$version}'."
            );
        }

        if (
            !is_dir($this->state_directory) &&
            !mkdir(
                $this->state_directory,
                0755,
                true
            ) &&
            !is_dir($this->state_directory)
        ) {
            throw new \RuntimeException(
                "Unable to create migration state directory '{$this->state_directory}'."
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

        $state_file = $this->getStateFile();

        if (
            file_put_contents(
                $state_file,
                $content . "\n",
                LOCK_EX
            ) === false
        ) {
            throw new \RuntimeException(
                "Unable to write migration state file '{$state_file}'."
            );
        }
    }

    /**
     * Validate the version format stored by the migration engine.
     *
     * @param string $version Framework version.
     *
     * @return bool Whether the version is valid.
     */
    private function isValidVersion(string $version): bool {
        return preg_match(
            '/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/D',
            $version
        ) === 1;
    }
}
