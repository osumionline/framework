<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Migrations;

use Osumi\OsumiFramework\Migrations\Contract\MigrationStepInterface;
use Osumi\OsumiFramework\Migrations\Util\MigrationVersion;
use ReflectionClass;

final class MigrationManifest {
    /**
     * @var list<array{since: string, step: class-string<MigrationStepInterface>}>
     */
    private array $entries;

    /**
     * Create a validated migration manifest.
     *
     * @param array<array-key, mixed> $entries Raw manifest entries.
     *
     * @throws \InvalidArgumentException If a manifest entry is invalid.
     */
    public function __construct(array $entries) {
        $this->entries = $this->normalizeEntries(
            $entries
        );
    }

    /**
     * Load a migration manifest from a PHP file.
     *
     * @param string $manifest_file Manifest file path.
     *
     * @return self Loaded and validated manifest.
     *
     * @throws \RuntimeException If the manifest file does not exist, is not
     *                           readable or does not return an array.
     * @throws \InvalidArgumentException If a manifest entry is invalid.
     */
    public static function fromFile(string $manifest_file): self {
        if (!is_file($manifest_file)) {
            throw new \RuntimeException(
                "Migration manifest '{$manifest_file}' does not exist."
            );
        }

        if (!is_readable($manifest_file)) {
            throw new \RuntimeException(
                "Migration manifest '{$manifest_file}' is not readable."
            );
        }

        $entries = require $manifest_file;

        if (!is_array($entries)) {
            throw new \RuntimeException(
                "Migration manifest '{$manifest_file}' must return an array."
            );
        }

        return new self(
            $entries
        );
    }

    /**
     * Select migration steps in the interval from < step <= to.
     *
     * @param string $from Source framework version.
     * @param string $to Target framework version.
     *
     * @return list<MigrationStepInterface> Ordered migration steps.
     *
     * @throws \InvalidArgumentException If a version is invalid or represents a downgrade.
     */
    public function selectSteps(
        string $from,
        string $to
    ): array {
        $this->validateRange(
            $from,
            $to
        );

        $steps = [];

        foreach ($this->entries as $entry) {
            if (
                version_compare(
                    $from,
                    $entry['since'],
                    '<'
                ) &&
                version_compare(
                    $to,
                    $entry['since'],
                    '>='
                )
            ) {
                $class_name = $entry['step'];
                $steps[] = new $class_name();
            }
        }

        return $steps;
    }

    /**
     * Normalize and validate manifest entries.
     *
     * @param array<array-key, mixed> $entries Raw manifest entries.
     *
     * @return list<array{since: string, step: class-string<MigrationStepInterface>}> Normalized entries.
     *
     * @throws \InvalidArgumentException If a manifest entry is invalid.
     */
    private function normalizeEntries(array $entries): array {
        $normalized = [];
        $versions = [];

        foreach ($entries as $index => $entry) {
            if (!is_array($entry)) {
                throw new \InvalidArgumentException(
                    "Migration manifest entry '{$index}' must be an array."
                );
            }

            $unknown_keys = array_diff(
                array_keys($entry),
                [
                    'since',
                    'step'
                ]
            );

            if ($unknown_keys !== []) {
                throw new \InvalidArgumentException(
                    "Migration manifest entry '{$index}' contains unknown keys."
                );
            }

            $since = $entry['since'] ?? null;
            $step = $entry['step'] ?? null;

            if (
                !is_string($since) ||
                !MigrationVersion::isValid($since)
            ) {
                throw new \InvalidArgumentException(
                    "Migration manifest entry '{$index}' has an invalid 'since' version."
                );
            }

            if (isset($versions[$since])) {
                throw new \InvalidArgumentException(
                    "Migration manifest contains duplicate version '{$since}'."
                );
            }

            if (
                !is_string($step) ||
                $step === '' ||
                !class_exists($step) ||
                !is_subclass_of(
                    $step,
                    MigrationStepInterface::class
                )
            ) {
                throw new \InvalidArgumentException(
                    "Migration manifest entry '{$since}' has an invalid step class."
                );
            }

            $reflection = new ReflectionClass(
                $step
            );

            if (!$reflection->isInstantiable()) {
                throw new \InvalidArgumentException(
                    "Migration step '{$step}' is not instantiable."
                );
            }

            $constructor = $reflection->getConstructor();

            if (
                $constructor !== null &&
                $constructor->getNumberOfRequiredParameters() > 0
            ) {
                throw new \InvalidArgumentException(
                    "Migration step '{$step}' must have a parameterless constructor."
                );
            }

            /** @var MigrationStepInterface $step_instance */
            $step_instance = $reflection->newInstance();

            if ($step_instance->getVersion() !== $since) {
                throw new \InvalidArgumentException(
                    "Migration step '{$step}' version '{$step_instance->getVersion()}' does not match manifest version '{$since}'."
                );
            }

            $versions[$since] = true;

            /** @var class-string<MigrationStepInterface> $step */
            $normalized[] = [
                'since' => $since,
                'step' => $step
            ];
        }

        usort(
            $normalized,
            static fn(array $left, array $right): int => version_compare(
                $left['since'],
                $right['since']
            )
        );

        return $normalized;
    }

    /**
     * Validate a migration version range.
     *
     * @param string $from Source framework version.
     * @param string $to Target framework version.
     *
     * @return void
     *
     * @throws \InvalidArgumentException If a version is invalid or represents a downgrade.
     */
    private function validateRange(
        string $from,
        string $to
    ): void {
        if (!MigrationVersion::isValid($from)) {
            throw new \InvalidArgumentException(
                "Invalid source migration version '{$from}'."
            );
        }

        if (!MigrationVersion::isValid($to)) {
            throw new \InvalidArgumentException(
                "Invalid target migration version '{$to}'."
            );
        }

        if (
            version_compare(
                $from,
                $to,
                '>'
            )
        ) {
            throw new \InvalidArgumentException(
                "Framework migrations do not support downgrades ('{$from}' -> '{$to}')."
            );
        }
    }
}
