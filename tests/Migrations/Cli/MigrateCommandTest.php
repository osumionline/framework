<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\Migrations\Cli;

use Osumi\OsumiFramework\Migrations\Cli\MigrateCommand;
use Osumi\OsumiFramework\Tests\Support\TemporaryProject;
use PHPUnit\Framework\TestCase;

final class MigrateCommandTest extends TestCase {
    private TemporaryProject $project;

    /**
     * Prepare an isolated project for migration CLI tests.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();

        $this->project = new TemporaryProject();
    }

    /**
     * Remove the temporary project after every test.
     *
     * @return void
     */
    protected function tearDown(): void {
        $this->project->remove();

        parent::tearDown();
    }

    /**
     * Test that migration help can be displayed successfully.
     *
     * @return void
     */
    public function testHelpReturnsSuccess(): void {
        $output = [];
        $error = [];

        $command = $this->createCommand(
            $output,
            $error
        );

        $exit_code = $command->run(
            [
                '--help'
            ],
            $this->project->getBasePath()
        );

        self::assertSame(
            0,
            $exit_code
        );

        self::assertNotEmpty(
            $output
        );

        self::assertSame(
            [],
            $error
        );

        self::assertStringContainsString(
            '--dry-run',
            $output[0]
        );
    }

    /**
     * Test that unknown migration arguments fail explicitly.
     *
     * @return void
     */
    public function testUnknownArgumentReturnsError(): void {
        $output = [];
        $error = [];

        $command = $this->createCommand(
            $output,
            $error
        );

        $exit_code = $command->run(
            [
                '--unknown'
            ],
            $this->project->getBasePath()
        );

        self::assertSame(
            1,
            $exit_code
        );

        self::assertSame(
            [],
            $output
        );

        self::assertCount(
            1,
            $error
        );
    }

    /**
     * Test that prefixed versions are normalized by the CLI.
     *
     * @return void
     */
    public function testPrefixedVersionsAreAccepted(): void {
        $output = [];
        $error = [];

        $command = $this->createCommand(
            $output,
            $error
        );

        $exit_code = $command->run(
            [
                '--from=v9.8.5',
                '--to=V9.8.5',
                '--verbose'
            ],
            $this->project->getBasePath()
        );

        self::assertSame(
            0,
            $exit_code
        );

        self::assertSame(
            [],
            $error
        );

        self::assertContains(
            '[OFW] No framework migrations are pending.',
            $output
        );
    }

    /**
     * Test that duplicated options are rejected.
     *
     * @return void
     */
    public function testDuplicatedOptionReturnsError(): void {
        $output = [];
        $error = [];

        $command = $this->createCommand(
            $output,
            $error
        );

        $exit_code = $command->run(
            [
                '--from=9.8.5',
                '--from=9.9.0'
            ],
            $this->project->getBasePath()
        );

        self::assertSame(
            1,
            $exit_code
        );

        self::assertCount(
            1,
            $error
        );
    }

    /**
     * Test that downgrade ranges are rejected through the CLI.
     *
     * @return void
     */
    public function testDowngradeReturnsError(): void {
        $output = [];
        $error = [];

        $command = $this->createCommand(
            $output,
            $error
        );

        $exit_code = $command->run(
            [
                '--from=10.0.0',
                '--to=9.9.0'
            ],
            $this->project->getBasePath()
        );

        self::assertSame(
            1,
            $exit_code
        );

        self::assertCount(
            1,
            $error
        );

        self::assertStringContainsString(
            'do not support downgrades',
            $error[0]
        );
    }

    /**
     * Create a migration command whose output can be inspected.
     *
     * @param list<string> $output Captured standard output messages.
     * @param list<string> $error Captured standard error messages.
     *
     * @return MigrateCommand Configured migration command.
     */
    private function createCommand(
        array &$output,
        array &$error
    ): MigrateCommand {
        return new MigrateCommand(
            static function (string $message) use (&$output): void {
                $output[] = $message;
            },
            static function (string $message) use (&$error): void {
                $error[] = $message;
            }
        );
    }
}
