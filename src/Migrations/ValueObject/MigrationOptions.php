<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Migrations\ValueObject;

use Closure;

final readonly class MigrationOptions {
    /**
     * Create immutable migration execution options.
     *
     * @param bool $dry_run Whether filesystem changes must be simulated only.
     * @param bool $force Whether migration safety checks may be bypassed.
     * @param bool $verbose Whether verbose migration messages are enabled.
     * @param bool $interactive Whether interactive migration behavior is allowed.
     * @param array<string, mixed> $extra Additional migration-specific options.
     * @param (Closure(string): void)|null $logger Optional migration message writer.
     */
    public function __construct(
        public bool $dry_run = false,
        public bool $force = false,
        public bool $verbose = false,
        public bool $interactive = true,
        public array $extra = [],
        public ?Closure $logger = null
    ) {
    }

    /**
     * Build migration options from the public array API.
     *
     * @param array{
     *     dryRun?: bool,
     *     force?: bool,
     *     verbose?: bool,
     *     interactive?: bool,
     *     extra?: array<string, mixed>,
     *     logger?: Closure(string): void
     * } $options Migration options.
     *
     * @return self Normalized migration options.
     *
     * @throws \InvalidArgumentException If an option name or value is invalid.
     */
    public static function fromArray(array $options): self {
        $allowed_options = [
            'dryRun',
            'force',
            'verbose',
            'interactive',
            'extra',
            'logger'
        ];

        foreach (array_keys($options) as $key) {
            if (
                !is_string($key) ||
                !in_array(
                    $key,
                    $allowed_options,
                    true
                )
            ) {
                $option_name = is_scalar($key)
                    ? (string) $key
                    : gettype($key);

                throw new \InvalidArgumentException(
                    "Unknown migration option '{$option_name}'."
                );
            }
        }

        foreach (
            [
                'dryRun',
                'force',
                'verbose',
                'interactive'
            ] as $boolean_option
        ) {
            if (
                array_key_exists(
                    $boolean_option,
                    $options
                ) &&
                !is_bool($options[$boolean_option])
            ) {
                throw new \InvalidArgumentException(
                    "Migration option '{$boolean_option}' must be boolean."
                );
            }
        }

        $extra = $options['extra'] ?? [];

        if (!is_array($extra)) {
            throw new \InvalidArgumentException(
                "Migration option 'extra' must be an array."
            );
        }

        foreach (array_keys($extra) as $key) {
            if (!is_string($key)) {
                throw new \InvalidArgumentException(
                    "Migration option 'extra' must contain string keys."
                );
            }
        }

        $logger = $options['logger'] ?? null;

        if (
            $logger !== null &&
            !$logger instanceof Closure
        ) {
            throw new \InvalidArgumentException(
                "Migration option 'logger' must be a Closure or null."
            );
        }

        return new self(
            dry_run: $options['dryRun'] ?? false,
            force: $options['force'] ?? false,
            verbose: $options['verbose'] ?? false,
            interactive: $options['interactive'] ?? true,
            extra: $extra,
            logger: $logger
        );
    }
}
