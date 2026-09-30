<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\Log;

use Osumi\OsumiFramework\Core\OConfig;
use Osumi\OsumiFramework\Log\OLog;
use Osumi\OsumiFramework\Tests\Support\TemporaryProject;
use PHPUnit\Framework\TestCase;

final class OLogTest extends TestCase {
    private TemporaryProject $project;
    private bool $core_existed = false;
    private mixed $previous_core = null;

    /**
     * Prepare an isolated logger environment.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();

        $this->project = new TemporaryProject();

        $this->core_existed = array_key_exists(
            'core',
            $GLOBALS
        );

        $this->previous_core = $GLOBALS['core']
            ?? null;
    }

    /**
     * Restore global state and remove temporary files.
     *
     * @return void
     */
    protected function tearDown(): void {
        if ($this->core_existed) {
            $GLOBALS['core'] = $this->previous_core;
        } else {
            unset(
                $GLOBALS['core']
            );
        }

        $this->project->remove();

        parent::tearDown();
    }

    /**
     * Configure the global core used by OLog.
     *
     * @param string $name Log file name.
     * @param string $level Log level.
     * @param int $max_file_size Maximum file size in MB.
     * @param int $max_num_files Maximum rotated file count.
     *
     * @return OConfig Configured application configuration.
     */
    private function configureLogger(
        string $name,
        string $level = 'ALL',
        int $max_file_size = 1,
        int $max_num_files = 3
    ): OConfig {
        $config = new OConfig(
            $this->project->getBasePath()
        );

        $config->setLog(
            'name',
            $name
        );

        $config->setLog(
            'level',
            $level
        );

        $config->setLog(
            'max_file_size',
            $max_file_size
        );

        $config->setLog(
            'max_num_files',
            $max_num_files
        );

        $core = new \stdClass();
        $core->config = $config;

        $GLOBALS['core'] = $core;

        return $config;
    }

    /**
     * Read a log file.
     *
     * @param string $path Log file path.
     *
     * @return string Log contents.
     *
     * @throws \RuntimeException If the log cannot be read.
     */
    private function readLog(
        string $path
    ): string {
        $content = file_get_contents(
            $path
        );

        if ($content === false) {
            throw new \RuntimeException(
                "Could not read test log '{$path}'."
            );
        }

        return $content;
    }

    /**
     * Test logging levels and log entry formatting.
     *
     * @return void
     */
    public function testLevelFilteringAndEntryFormat(): void {
        $config = $this->configureLogger(
            'application',
            'INFO'
        );

        $log = new OLog(
            'ExampleClass'
        );

        self::assertFalse(
            $log->debug(
                'hidden-debug'
            )
        );

        self::assertTrue(
            $log->info(
                'visible-info'
            )
        );

        self::assertTrue(
            $log->error(
                'visible-error'
            )
        );

        $log_path = $config->getDir(
            'ofw_logs'
        )
            . 'application.log';

        $content = $this->readLog(
            $log_path
        );

        self::assertStringNotContainsString(
            'hidden-debug',
            $content
        );

        self::assertStringContainsString(
            '[INFO] - [ExampleClass] - [OLogTest.php - ',
            $content
        );

        self::assertStringContainsString(
            '] - visible-info',
            $content
        );

        self::assertStringContainsString(
            '[ERROR] - [ExampleClass] - [OLogTest.php - ',
            $content
        );

        self::assertStringContainsString(
            '] - visible-error',
            $content
        );
    }

    /**
     * Test multi-file log rotation.
     *
     * @return void
     */
    public function testLogRotation(): void {
        $config = $this->configureLogger(
            'rotation',
            'ALL',
            1,
            2
        );

        $log = new OLog(
            'RotationTest'
        );

        self::assertTrue(
            $log->info(
                str_repeat(
                    'A',
                    700000
                )
            )
        );

        self::assertTrue(
            $log->info(
                str_repeat(
                    'B',
                    700000
                )
            )
        );

        self::assertTrue(
            $log->info(
                str_repeat(
                    'C',
                    700000
                )
            )
        );

        $dir = $config->getDir(
            'ofw_logs'
        );

        $current = $dir . 'rotation.log';
        $first = $dir . 'rotation_1.log';
        $second = $dir . 'rotation_2.log';

        self::assertFileExists(
            $current
        );

        self::assertFileExists(
            $first
        );

        self::assertFileExists(
            $second
        );

        self::assertStringContainsString(
            str_repeat(
                'C',
                100
            ),
            $this->readLog(
                $current
            )
        );

        self::assertStringContainsString(
            str_repeat(
                'B',
                100
            ),
            $this->readLog(
                $first
            )
        );

        self::assertStringContainsString(
            str_repeat(
                'A',
                100
            ),
            $this->readLog(
                $second
            )
        );
    }

    /**
     * Test single-file truncation when rotation is disabled.
     *
     * @return void
     */
    public function testSingleFileLogIsTruncated(): void {
        $config = $this->configureLogger(
            'single',
            'ALL',
            1,
            1
        );

        $log = new OLog(
            'SingleFileTest'
        );

        self::assertTrue(
            $log->info(
                str_repeat(
                    'A',
                    700000
                )
            )
        );

        self::assertTrue(
            $log->info(
                str_repeat(
                    'B',
                    700000
                )
            )
        );

        $path = $config->getDir(
            'ofw_logs'
        )
            . 'single.log';

        $content = $this->readLog(
            $path
        );

        self::assertLessThanOrEqual(
            1024 * 1024,
            strlen(
                $content
            )
        );

        self::assertStringContainsString(
            str_repeat(
                'B',
                100
            ),
            $content
        );
    }

    /**
     * Test that an oversized single entry is preserved completely.
     *
     * @return void
     */
    public function testOversizedEntryIsPreserved(): void {
        $config = $this->configureLogger(
            'oversized',
            'ALL',
            1,
            1
        );

        $log = new OLog(
            'OversizedTest'
        );

        $message = str_repeat(
            'X',
            1100000
        );

        self::assertTrue(
            $log->error(
                $message
            )
        );

        $path = $config->getDir(
            'ofw_logs'
        )
            . 'oversized.log';

        $content = $this->readLog(
            $path
        );

        self::assertGreaterThan(
            1024 * 1024,
            strlen(
                $content
            )
        );

        self::assertStringContainsString(
            $message,
            $content
        );
    }
}
