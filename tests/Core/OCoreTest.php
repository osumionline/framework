<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\Core;

use Osumi\OsumiFramework\Core\OCore;
use Osumi\OsumiFramework\Tests\Support\TemporaryProject;
use PHPUnit\Framework\TestCase;

final class OCoreTest extends TestCase {
    private TemporaryProject $project;
    private string|false $previous_error_log;

    /**
     * Configure an isolated PHP error log.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();

        $this->project = new TemporaryProject();

        $this->previous_error_log = ini_get(
            'error_log'
        );

        ini_set(
            'error_log',
            $this->project->getPath(
                'php-error.log'
            )
        );
    }

    /**
     * Restore PHP configuration and remove temporary data.
     *
     * @return void
     */
    protected function tearDown(): void {
        if ($this->previous_error_log !== false) {
            ini_set(
                'error_log',
                $this->previous_error_log
            );
        }

        http_response_code(
            200
        );

        $this->project->remove();

        parent::tearDown();
    }

    /**
     * Test the exception handler before the core has been fully initialized.
     *
     * Exception details must be logged but never exposed to the response.
     *
     * @return void
     *
     * @throws \RuntimeException If the temporary PHP error log cannot be read.
     */
    public function testErrorHandlerWorksWithUninitializedCore(): void {
        $core = new OCore();

        $buffer_level = ob_get_level();

        if (!ob_start()) {
            throw new \RuntimeException(
                'Could not start test output buffer.'
            );
        }

        try {
            $core->errorHandler(
                new \RuntimeException(
                    'private exception detail'
                )
            );

            $output = ob_get_contents();

            if ($output === false) {
                throw new \RuntimeException(
                    'Could not read test output buffer.'
                );
            }
        } finally {
            while (ob_get_level() > $buffer_level) {
                ob_end_clean();
            }
        }

        self::assertSame(
            'Internal Server Error',
            $output
        );

        self::assertStringNotContainsString(
            'private exception detail',
            $output
        );

        self::assertSame(
            '500 Internal Server Error',
            $core->getHttpStatus()
        );

        $error_log = file_get_contents(
            $this->project->getPath(
                'php-error.log'
            )
        );

        if ($error_log === false) {
            throw new \RuntimeException(
                'Could not read temporary PHP error log.'
            );
        }

        self::assertStringContainsString(
            'private exception detail',
            $error_log
        );
    }

    /**
     * Test supported HTTP status mappings.
     *
     * @return void
     */
    public function testHttpStatusMappings(): void {
        $core = new OCore();

        $core->setHttpStatus(
            405
        );

        self::assertSame(
            '405 Method Not Allowed',
            $core->getHttpStatus()
        );

        $core->setHttpStatus(
            500
        );

        self::assertSame(
            '500 Internal Server Error',
            $core->getHttpStatus()
        );
    }
}
