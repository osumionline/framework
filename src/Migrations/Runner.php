<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Migrations;

use Osumi\OsumiFramework\Core\OConfig;
use Osumi\OsumiFramework\Migrations\Context\MigrationContext;
use Osumi\OsumiFramework\Migrations\State\StateStore;
use Osumi\OsumiFramework\Migrations\Util\FilePatcher;
use Osumi\OsumiFramework\Migrations\Util\GitStatus;
use Osumi\OsumiFramework\Migrations\ValueObject\MigrationOptions;
use Throwable;

final class Runner {
    private const string INITIAL_VERSION = '0.0.0';

    /**
     * Create a migration runner with a validated manifest.
     *
     * @param MigrationManifest $manifest Migration manifest.
     */
    public function __construct(
        private readonly MigrationManifest $manifest
    ) {
    }

    /**
     * Run migrations for an explicit framework version range using the default manifest.
     *
     * @param string $project_root Project root path.
     * @param string $from Source framework version.
     * @param string $to Target framework version.
     * @param array{
     *     dryRun?: bool,
     *     force?: bool,
     *     verbose?: bool,
     *     interactive?: bool,
     *     extra?: array<string, mixed>,
     *     logger?: \Closure(string): void
     * } $options Migration options.
     *
     * @return void
     */
    public static function run(
        string $project_root,
        string $from,
        string $to,
        array $options = []
    ): void {
        self::createDefault()->migrate(
            $project_root,
            $from,
            $to,
            MigrationOptions::fromArray(
                $options
            )
        );
    }

    /**
     * Run all pending migrations using state.json as the source version.
     *
     * When no migration state exists, version 0.0.0 is used so legacy projects
     * can execute every applicable idempotent migration step.
     *
     * @param string $project_root Project root path.
     * @param string|null $to Target framework version or null to detect the installed version.
     * @param array{
     *     dryRun?: bool,
     *     force?: bool,
     *     verbose?: bool,
     *     interactive?: bool,
     *     extra?: array<string, mixed>,
     *     logger?: \Closure(string): void
     * } $options Migration options.
     *
     * @return void
     *
     * @throws \JsonException If migration state is malformed.
     * @throws \RuntimeException If the installed version cannot be detected.
     */
    public static function runPending(
        string $project_root,
        ?string $to = null,
        array $options = []
    ): void {
        $project_root = self::resolveProjectRoot(
            $project_root
        );

        $state_store = self::createStateStore(
            $project_root
        );

        $from = $state_store->readLastMigrated()
            ?? self::INITIAL_VERSION;

        $target = $to
            ?? self::detectInstalledVersion(
                $project_root
            );

        self::createDefault()->migrate(
            $project_root,
            $from,
            $target,
            MigrationOptions::fromArray(
                $options
            )
        );
    }

    /**
     * Detect the installed Osumi Framework version for a project.
     *
     * Installed applications read vendor/osumionline/framework/composer.json.
     * The framework repository itself falls back to its root composer.json.
     *
     * @param string $project_root Project root path.
     *
     * @return string Installed framework version.
     *
     * @throws \JsonException If a Composer file contains invalid JSON.
     * @throws \RuntimeException If no valid framework version can be detected.
     */
    public static function detectInstalledVersion(string $project_root): string {
        $project_root = self::resolveProjectRoot(
            $project_root
        );

        $candidates = [
            $project_root . '/vendor/osumionline/framework/composer.json',
            $project_root . '/composer.json'
        ];

        foreach ($candidates as $composer_file) {
            if (!is_file($composer_file)) {
                continue;
            }

            $content = file_get_contents(
                $composer_file
            );

            if ($content === false) {
                throw new \RuntimeException(
                    "Unable to read Composer file '{$composer_file}'."
                );
            }

            $data = json_decode(
                $content,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            if (!is_array($data)) {
                continue;
            }

            $package_name = $data['name'] ?? null;
            $version = $data['version'] ?? null;

            if (
                $package_name !== 'osumionline/framework' ||
                !is_string($version)
            ) {
                continue;
            }

            $version = self::normalizeVersion(
                $version
            );

            if (self::isValidVersion($version)) {
                return $version;
            }
        }

        throw new \RuntimeException(
            "Unable to detect installed Osumi Framework version in '{$project_root}'."
        );
    }

    /**
     * Apply migration steps for a framework version range.
     *
     * File changes are rolled back if a step or patch commit fails. Migration
     * state is persisted only after the file transaction has committed.
     *
     * @param string $project_root Project root path.
     * @param string $from Source framework version.
     * @param string $to Target framework version.
     * @param MigrationOptions $options Migration execution options.
     *
     * @return void
     *
     * @throws Throwable If a migration step, rollback or state update fails.
     */
    public function migrate(
        string $project_root,
        string $from,
        string $to,
        MigrationOptions $options
    ): void {
        $project_root = self::resolveProjectRoot(
            $project_root
        );

        $steps = $this->manifest->selectSteps(
            self::normalizeVersion($from),
            self::normalizeVersion($to)
        );

        if ($steps === []) {
            if (
                $options->verbose &&
                $options->logger !== null
            ) {
                ($options->logger)(
                    '[OFW] No framework migrations are pending.'
                );
            }

            return;
        }

        $state_store = self::createStateStore(
            $project_root
        );

        $config = new OConfig(
            $project_root . '/'
        );

        $tmp_directory = $config->getDir(
            'ofw_tmp'
        );

        if (!is_string($tmp_directory)) {
            throw new \LogicException(
                'Configured OFW temporary directory is invalid.'
            );
        }

        if (!$options->dry_run) {
            GitStatus::ensureCleanWorkingTree(
                $project_root,
                $options->force,
                $options->logger,
                $options->verbose
            );
        }

        $patcher = new FilePatcher(
            $project_root,
            $tmp_directory,
            $options->dry_run,
            $options->verbose,
            $options->logger
        );

        $context = new MigrationContext(
            project_root: $project_root,
            dry_run: $options->dry_run,
            force: $options->force,
            verbose: $options->verbose,
            interactive: $options->interactive,
            patcher: $patcher,
            state_store: $state_store,
            extra: $options->extra,
            logger: $options->logger
        );

        $this->write(
            $options,
            sprintf(
                '[OFW] Running framework migrations (%s -> %s)%s',
                $from,
                $to,
                $options->dry_run
                    ? ' [dry-run]'
                    : ''
            )
        );

        try {
            foreach ($steps as $step) {
                $this->write(
                    $options,
                    sprintf(
                        '[OFW] - %s: %s',
                        $step->getVersion(),
                        $step->getDescription()
                    )
                );

                $step->apply(
                    $context
                );
            }

            $patcher->commit();
        } catch (Throwable $exception) {
            try {
                $patcher->rollback();
            } catch (Throwable $rollback_exception) {
                throw new \RuntimeException(
                    'Framework migration failed and rollback also failed: '
                        . $rollback_exception->getMessage(),
                    0,
                    $exception
                );
            }

            throw $exception;
        }

        if (!$options->dry_run) {
            $last_step = $steps[array_key_last(
                $steps
            )];

            $state_store->writeLastMigrated(
                $last_step->getVersion()
            );
        }

        $this->write(
            $options,
            $options->dry_run
                ? '[OFW] Dry run completed; no project files were changed.'
                : '[OFW] Framework migrations completed.'
        );
    }

    /**
     * Create a runner using the framework migration manifest.
     *
     * @return self Default migration runner.
     */
    private static function createDefault(): self {
        return new self(
            MigrationManifest::fromFile(
                __DIR__ . '/manifest.php'
            )
        );
    }

    /**
     * Create the migration state store configured for a project.
     *
     * @param string $project_root Resolved project root path.
     *
     * @return StateStore Project migration state store.
     */
    private static function createStateStore(string $project_root): StateStore {
        $config = new OConfig(
            $project_root . '/'
        );

        $tmp_directory = $config->getDir(
            'ofw_tmp'
        );

        if (!is_string($tmp_directory)) {
            throw new \LogicException(
                'Configured OFW temporary directory is invalid.'
            );
        }

        return new StateStore(
            $tmp_directory
        );
    }

    /**
     * Resolve and normalize a project root path.
     *
     * @param string $project_root Project root path.
     *
     * @return string Canonical project root using forward slashes.
     *
     * @throws \RuntimeException If the project root cannot be resolved.
     */
    private static function resolveProjectRoot(string $project_root): string {
        $resolved = realpath(
            $project_root
        );

        if ($resolved === false) {
            throw new \RuntimeException(
                "Unable to resolve migration project root '{$project_root}'."
            );
        }

        return rtrim(
            str_replace(
                '\\',
                '/',
                $resolved
            ),
            '/'
        );
    }

    /**
     * Normalize a framework version for migration comparisons.
     *
     * @param string $version Framework version.
     *
     * @return string Normalized framework version.
     */
    private static function normalizeVersion(string $version): string {
        $version = trim(
            $version
        );

        if (
            strlen($version) > 1 &&
            (
                $version[0] === 'v' ||
                $version[0] === 'V'
            ) &&
            ctype_digit(
                $version[1]
            )
        ) {
            return substr(
                $version,
                1
            );
        }

        return $version;
    }

    /**
     * Validate a framework migration version.
     *
     * @param string $version Framework version.
     *
     * @return bool Whether the version is valid.
     */
    private static function isValidVersion(string $version): bool {
        return preg_match(
            '/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/D',
            $version
        ) === 1;
    }

    /**
     * Write a runner message when a logger is available.
     *
     * @param MigrationOptions $options Migration execution options.
     * @param string $message Message to write.
     *
     * @return void
     */
    private function write(
        MigrationOptions $options,
        string $message
    ): void {
        if ($options->logger !== null) {
            ($options->logger)(
                $message
            );
        }
    }
}
