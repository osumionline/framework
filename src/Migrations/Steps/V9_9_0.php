<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Migrations\Steps;

use Osumi\OsumiFramework\Migrations\Context\MigrationContext;
use Osumi\OsumiFramework\Migrations\Contract\MigrationStepInterface;
use Osumi\OsumiFramework\Migrations\Util\LegacyFilterAdapterGenerator;
use Osumi\OsumiFramework\Migrations\Util\LegacyFilterScanner;
use Osumi\OsumiFramework\Migrations\Util\LegacyProjectCodeMigrator;
use Osumi\OsumiFramework\Migrations\ValueObject\LegacyFilterDefinition;

final class V9_9_0 implements MigrationStepInterface {
    private const string VERSION = '9.9.0';
    private const string MIDDLEWARE_CONFIG = 'src/Middleware/Middlewares.php';

    /**
     * Get the framework version introduced by this migration.
     *
     * @return string Framework version.
     */
    public function getVersion(): string {
        return self::VERSION;
    }

    /**
     * Get a human-readable description of this migration.
     *
     * @return string Migration description.
     */
    public function getDescription(): string {
        return 'Migrate legacy Filters to Middlewares.';
    }

    /**
     * Migrate a legacy OFW application from Filters to Middlewares.
     *
     * The complete project is analyzed before any file is modified. Legacy
     * Filter implementations are preserved and compatibility Middleware
     * adapters are generated for them.
     *
     * @param MigrationContext $context Migration execution context.
     *
     * @return void
     *
     * @throws \RuntimeException If the project contains an unsupported legacy
     *                           construct or a generated Middleware would
     *                           overwrite different existing code.
     */
    public function apply(MigrationContext $context): void {
        $scanner = new LegacyFilterScanner();

        $definitions = $scanner->discover(
            $context->project_root
        );

        $migrator = new LegacyProjectCodeMigrator(
            $definitions
        );

        /*
		 * Preflight every application PHP file before planning any write.
		 */
        $source_changes = $migrator->plan(
            $context->project_root
        );

        $adapter_generator = new LegacyFilterAdapterGenerator();

        $adapter_changes = $this->planAdapters(
            $context,
            $definitions,
            $adapter_generator
        );

        $middleware_config = $this->planMiddlewareConfiguration(
            $context
        );

        $context->writeVerbose(
            sprintf(
                '[OFW] 9.9.0 preflight complete: %d legacy Filter(s), %d adapter(s), %d source file(s) to migrate.',
                count($definitions),
                count($adapter_changes),
                count($source_changes)
            )
        );

        /*
		 * No writes occur before this point. Any unsupported or ambiguous
		 * project code has already caused the migration to abort.
		 */
        foreach ($adapter_changes as $path => $content) {
            $context->patcher->write(
                $path,
                $content
            );
        }

        if ($middleware_config !== null) {
            $context->patcher->write(
                self::MIDDLEWARE_CONFIG,
                $middleware_config
            );
        }

        foreach ($source_changes as $path => $content) {
            $context->patcher->write(
                $path,
                $content
            );
        }
    }

    /**
     * Plan compatibility Middleware adapters for legacy Filters.
     *
     * Existing generated adapters are accepted to make the migration
     * idempotent. Existing files with different contents are treated as
     * collisions and are never overwritten.
     *
     * @param MigrationContext $context Migration execution context.
     * @param list<LegacyFilterDefinition> $definitions Legacy Filter definitions.
     * @param LegacyFilterAdapterGenerator $generator Adapter source generator.
     *
     * @return array<string, string> Adapter files that must be created.
     *
     * @throws \RuntimeException If an adapter destination conflicts with an
     *                           existing file or filesystem entry.
     */
    private function planAdapters(
        MigrationContext $context,
        array $definitions,
        LegacyFilterAdapterGenerator $generator
    ): array {
        $changes = [];

        foreach ($definitions as $definition) {
            $generated = $generator->generate(
                $definition
            );

            $existing = $this->readDestination(
                $context,
                $definition->middleware_relative_path
            );

            if ($existing === null) {
                $changes[$definition->middleware_relative_path] = $generated;
                continue;
            }

            if ($existing !== $generated) {
                throw new \RuntimeException(
                    "Cannot migrate legacy Filter '{$definition->filter_relative_path}': "
                        . "Middleware destination '{$definition->middleware_relative_path}' already exists with different contents."
                );
            }

            $context->writeVerbose(
                "[OFW] Existing generated adapter '{$definition->middleware_relative_path}' is already up to date."
            );
        }

        ksort(
            $changes
        );

        return $changes;
    }

    /**
     * Plan creation of the global Middleware configuration file.
     *
     * Existing configuration is preserved exactly because it may contain
     * project-specific global Middleware registrations.
     *
     * @param MigrationContext $context Migration execution context.
     *
     * @return string|null Default file contents when creation is required, or
     *                     null when the file already exists.
     *
     * @throws \RuntimeException If the destination is not a regular file.
     */
    private function planMiddlewareConfiguration(
        MigrationContext $context
    ): ?string {
        $existing = $this->readDestination(
            $context,
            self::MIDDLEWARE_CONFIG
        );

        if ($existing !== null) {
            return null;
        }

        return $this->buildDefaultMiddlewareConfiguration();
    }

    /**
     * Read a planned migration destination safely.
     *
     * FilePatcher performs path and symbolic-link validation. A filesystem
     * entry that exists but is not a regular file is rejected explicitly.
     *
     * @param MigrationContext $context Migration execution context.
     * @param string $relative_path Project-relative destination path.
     *
     * @return string|null Existing file contents or null when absent.
     *
     * @throws \RuntimeException If the destination exists but is not a regular file.
     */
    private function readDestination(
        MigrationContext $context,
        string $relative_path
    ): ?string {
        $content = $context->patcher->read(
            $relative_path
        );

        if ($content !== null) {
            return $content;
        }

        $absolute_path = rtrim(
            $context->project_root,
            '/\\'
        )
            . '/'
            . $relative_path;

        if (file_exists($absolute_path)) {
            throw new \RuntimeException(
                "Migration destination '{$relative_path}' exists but is not a regular file."
            );
        }

        return null;
    }

    /**
     * Build the default project-level Middleware configuration.
     *
     * @return string PHP source for src/Middleware/Middlewares.php.
     */
    private function buildDefaultMiddlewareConfiguration(): string {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Middleware;

use Osumi\OsumiFramework\Core\OMiddleware;

OMiddleware::setGlobal([
	OMiddleware::PHASE_BEFORE => [],
	OMiddleware::PHASE_AFTER_RENDER => [],
	OMiddleware::PHASE_AFTER_RESPONSE => []
]);

PHP;
    }
}
