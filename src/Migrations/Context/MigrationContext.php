<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Migrations\Context;

use Closure;
use Osumi\OsumiFramework\Migrations\State\StateStore;
use Osumi\OsumiFramework\Migrations\Util\FilePatcher;

final class MigrationContext {
    /**
     * Create a migration execution context.
     *
     * @param string $project_root Absolute project root path.
     * @param bool $dry_run Whether filesystem changes must be simulated only.
     * @param bool $force Whether migration safety checks may be bypassed.
     * @param bool $verbose Whether verbose migration messages are enabled.
     * @param FilePatcher $patcher Transactional project file patcher.
     * @param StateStore $state_store Migration state storage.
     * @param array<string, mixed> $extra Additional migration options.
     * @param (Closure(string): void)|null $logger Optional migration message writer.
     */
    public function __construct(
        public readonly string $project_root,
        public readonly bool $dry_run,
        public readonly bool $force,
        public readonly bool $verbose,
        public readonly FilePatcher $patcher,
        public readonly StateStore $state_store,
        public readonly array $extra = [],
        private readonly ?Closure $logger = null
    ) {
    }

    /**
     * Write a migration message when a logger is available.
     *
     * @param string $message Message to write.
     *
     * @return void
     */
    public function write(string $message): void {
        if ($this->logger !== null) {
            ($this->logger)($message);
        }
    }

    /**
     * Write a migration message only when verbose mode is enabled.
     *
     * @param string $message Message to write.
     *
     * @return void
     */
    public function writeVerbose(string $message): void {
        if (!$this->verbose) {
            return;
        }

        $this->write($message);
    }
}
