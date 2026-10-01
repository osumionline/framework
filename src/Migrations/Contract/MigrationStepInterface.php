<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Migrations\Contract;

use Osumi\OsumiFramework\Migrations\Context\MigrationContext;

interface MigrationStepInterface {
    /**
     * Get the framework version introduced by this migration step.
     *
     * @return string Framework version.
     */
    public function getVersion(): string;

    /**
     * Get a human-readable description of this migration step.
     *
     * @return string Migration description.
     */
    public function getDescription(): string;

    /**
     * Apply the migration step.
     *
     * Implementations must be idempotent so they can safely run again when the
     * migration state file is unavailable or has been removed.
     *
     * @param MigrationContext $context Migration execution context.
     *
     * @return void
     *
     * @throws \RuntimeException If the migration cannot be applied safely.
     */
    public function apply(MigrationContext $context): void;
}
