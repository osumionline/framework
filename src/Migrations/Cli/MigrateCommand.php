<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Migrations\Cli;

use Closure;
use Osumi\OsumiFramework\Migrations\Runner;
use Osumi\OsumiFramework\Migrations\Util\MigrationVersion;
use Throwable;

final class MigrateCommand {
    private Closure $stdout;
    private Closure $stderr;

    /**
     * Create the migration CLI command.
     *
     * @param (Closure(string): void)|null $stdout Standard output writer.
     * @param (Closure(string): void)|null $stderr Standard error writer.
     */
    public function __construct(
        ?Closure $stdout = null,
        ?Closure $stderr = null
    ) {
        $this->stdout = $stdout
            ?? static function (string $message): void {
                fwrite(
                    STDOUT,
                    $message . PHP_EOL
                );
            };

        $this->stderr = $stderr
            ?? static function (string $message): void {
                fwrite(
                    STDERR,
                    $message . PHP_EOL
                );
            };
    }

    /**
     * Execute the migration command.
     *
     * @param list<string> $arguments Command line arguments excluding the script name.
     * @param string $project_root Project root path.
     *
     * @return int Process exit code.
     */
    public function run(
        array $arguments,
        string $project_root
    ): int {
        try {
            $options = $this->parseArguments(
                $arguments
            );

            if ($options['help']) {
                $this->writeOutput(
                    $this->getHelp()
                );

                return 0;
            }

            $migration_options = [
                'dryRun' => $options['dryRun'],
                'force' => $options['force'],
                'verbose' => $options['verbose'],
                'interactive' => $options['interactive'],
                'logger' => function (string $message): void {
                    $this->writeOutput(
                        $message
                    );
                }
            ];

            if ($options['from'] !== null) {
                $target = $options['to']
                    ?? Runner::detectInstalledVersion(
                        $project_root
                    );

                Runner::run(
                    $project_root,
                    $options['from'],
                    $target,
                    $migration_options
                );
            } else {
                Runner::runPending(
                    $project_root,
                    $options['to'],
                    $migration_options
                );
            }

            return 0;
        } catch (Throwable $exception) {
            $this->writeError(
                '[OFW] ERROR: '
                    . $exception->getMessage()
            );

            return 1;
        }
    }

    /**
     * Parse migration command line arguments.
     *
     * @param list<string> $arguments Command line arguments.
     *
     * @return array{
     *     from: string|null,
     *     to: string|null,
     *     dryRun: bool,
     *     force: bool,
     *     verbose: bool,
     *     interactive: bool,
     *     help: bool
     * } Parsed migration command options.
     *
     * @throws \InvalidArgumentException If an argument is unknown, duplicated
     *                                   or contains an invalid value.
     */
    private function parseArguments(array $arguments): array {
        $options = [
            'from' => null,
            'to' => null,
            'dryRun' => false,
            'force' => false,
            'verbose' => false,
            'interactive' => true,
            'help' => false
        ];

        $seen = [];

        foreach ($arguments as $argument) {
            if ($argument === '--help') {
                $this->assertNotDuplicate(
                    'help',
                    $seen
                );

                $options['help'] = true;
                continue;
            }

            if ($argument === '--dry-run') {
                $this->assertNotDuplicate(
                    'dryRun',
                    $seen
                );

                $options['dryRun'] = true;
                continue;
            }

            if ($argument === '--force') {
                $this->assertNotDuplicate(
                    'force',
                    $seen
                );

                $options['force'] = true;
                continue;
            }

            if ($argument === '--verbose') {
                $this->assertNotDuplicate(
                    'verbose',
                    $seen
                );

                $options['verbose'] = true;
                continue;
            }

            if ($argument === '--no-interaction') {
                $this->assertNotDuplicate(
                    'interactive',
                    $seen
                );

                $options['interactive'] = false;
                continue;
            }

            if (
                str_starts_with(
                    $argument,
                    '--from='
                )
            ) {
                $this->assertNotDuplicate(
                    'from',
                    $seen
                );

                $options['from'] = $this->parseVersion(
                    'from',
                    substr(
                        $argument,
                        strlen('--from=')
                    )
                );

                continue;
            }

            if (
                str_starts_with(
                    $argument,
                    '--to='
                )
            ) {
                $this->assertNotDuplicate(
                    'to',
                    $seen
                );

                $options['to'] = $this->parseVersion(
                    'to',
                    substr(
                        $argument,
                        strlen('--to=')
                    )
                );

                continue;
            }

            throw new \InvalidArgumentException(
                "Unknown migration argument '{$argument}'."
            );
        }

        return $options;
    }

    /**
     * Parse and validate a command line framework version.
     *
     * @param string $option Option name.
     * @param string $version Raw framework version.
     *
     * @return string Normalized framework version.
     *
     * @throws \InvalidArgumentException If the version is invalid.
     */
    private function parseVersion(
        string $option,
        string $version
    ): string {
        $version = MigrationVersion::normalize(
            $version
        );

        if (!MigrationVersion::isValid($version)) {
            throw new \InvalidArgumentException(
                "Migration option '--{$option}' contains an invalid version."
            );
        }

        return $version;
    }

    /**
     * Ensure a command line option has not already been provided.
     *
     * @param string $option Normalized option name.
     * @param array<string, bool> $seen Previously parsed options.
     *
     * @return void
     *
     * @throws \InvalidArgumentException If the option is duplicated.
     */
    private function assertNotDuplicate(
        string $option,
        array &$seen
    ): void {
        if (isset($seen[$option])) {
            throw new \InvalidArgumentException(
                "Migration option '{$option}' was provided more than once."
            );
        }

        $seen[$option] = true;
    }

    /**
     * Get migration CLI help text.
     *
     * @return string Command help text.
     */
    private function getHelp(): string {
        return implode(
            PHP_EOL,
            [
                'Osumi Framework migration tool',
                '',
                'Usage:',
                '  ofw-migrate [options]',
                '',
                'Options:',
                '  --from=VERSION       Override the source framework version.',
                '  --to=VERSION         Override the target framework version.',
                '  --dry-run            Show changes without modifying project files.',
                '  --force              Bypass migration safety checks.',
                '  --verbose            Show detailed migration information.',
                '  --no-interaction     Disable interactive migration behavior.',
                '  --help               Show this help.'
            ]
        );
    }

    /**
     * Write a standard command message.
     *
     * @param string $message Message to write.
     *
     * @return void
     */
    private function writeOutput(string $message): void {
        ($this->stdout)(
            $message
        );
    }

    /**
     * Write a command error message.
     *
     * @param string $message Message to write.
     *
     * @return void
     */
    private function writeError(string $message): void {
        ($this->stderr)(
            $message
        );
    }
}
