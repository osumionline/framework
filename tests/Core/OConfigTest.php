<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\Core;

use Osumi\OsumiFramework\Core\OConfig;
use Osumi\OsumiFramework\Tests\Support\TemporaryProject;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OConfigTest extends TestCase {
    private TemporaryProject $project;

    /**
     * Create a temporary project before every test.
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
     * Test framework configuration defaults.
     *
     * @return void
     */
    public function testDefaultsAreLoaded(): void {
        $config = new OConfig(
            $this->project->getBasePath()
        );

        self::assertSame(
            'Europe/Madrid',
            $config->getTimezone()
        );

        self::assertSame(
            '',
            $config->getMailingFrom()
        );

        self::assertSame(
            'DEBUG',
            $config->getLog(
                'level'
            )
        );

        self::assertSame(
            50,
            $config->getLog(
                'max_file_size'
            )
        );

        self::assertSame(
            3,
            $config->getLog(
                'max_num_files'
            )
        );
    }

    /**
     * Test that environment configuration overrides base configuration.
     *
     * @return void
     */
    public function testEnvironmentOverridesBaseConfiguration(): void {
        $this->project->writeConfig(
            [
                'name' => 'Base',
                'environment' => 'test',
                'timezone' => 'Europe/Madrid',
                'mailing_from' => 'Base Sender <base@example.test>',
                'base_url' => 'https://base.example.test',
                'log_level' => 'DEBUG',
                'log' => [
                    'name' => 'base-log',
                    'max_file_size' => 5,
                    'max_num_files' => 3
                ],
                'error_pages' => [
                    '404' => '/base-404'
                ]
            ]
        );

        $this->project->writeConfig(
            [
                'timezone' => 'UTC',
                'mailing_from' => 'Environment Sender <environment@example.test>',
                'base_url' => 'https://environment.example.test',
                'log_level' => 'ERROR',
                'log' => [
                    'name' => 'environment-log',
                    'max_file_size' => 2,
                    'max_num_files' => 1
                ],
                'error_pages' => [
                    '500' => '/environment-500'
                ]
            ],
            'test'
        );

        $config = new OConfig(
            $this->project->getBasePath()
        );

        self::assertSame(
            'UTC',
            $config->getTimezone()
        );

        self::assertSame(
            'Environment Sender <environment@example.test>',
            $config->getMailingFrom()
        );

        self::assertSame(
            'https://environment.example.test',
            $config->getUrl(
                'base'
            )
        );

        self::assertSame(
            'ERROR',
            $config->getLog(
                'level'
            )
        );

        self::assertSame(
            'environment-log',
            $config->getLog(
                'name'
            )
        );

        self::assertSame(
            2,
            $config->getLog(
                'max_file_size'
            )
        );

        self::assertSame(
            1,
            $config->getLog(
                'max_num_files'
            )
        );

        self::assertSame(
            '/base-404',
            $config->getErrorPage(
                '404'
            )
        );

        self::assertSame(
            '/environment-500',
            $config->getErrorPage(
                '500'
            )
        );
    }

    /**
     * Test that invalid timezone identifiers are rejected.
     *
     * @return void
     */
    public function testInvalidTimezoneIsRejected(): void {
        $config = new OConfig(
            $this->project->getBasePath()
        );

        $this->expectException(
            \InvalidArgumentException::class
        );

        $config->setTimezone(
            'Invalid/Timezone'
        );
    }

    /**
     * Test invalid logging configuration values.
     *
     * @param string $key Logging configuration key.
     * @param string|int $value Invalid value.
     *
     * @return void
     */
    #[DataProvider('invalidLogConfigurationProvider')]
    public function testInvalidLogConfigurationIsRejected(
        string $key,
        string|int $value
    ): void {
        $config = new OConfig(
            $this->project->getBasePath()
        );

        $this->expectException(
            \InvalidArgumentException::class
        );

        $config->setLog(
            $key,
            $value
        );
    }

    /**
     * Provide invalid logging configuration values.
     *
     * @return array<string, array{string, string|int}> Invalid values.
     */
    public static function invalidLogConfigurationProvider(): array {
        return [
            'invalid level' => [
                'level',
                'WARN'
            ],
            'empty name' => [
                'name',
                ''
            ],
            'unsafe name' => [
                'name',
                '../application'
            ],
            'zero file size' => [
                'max_file_size',
                0
            ],
            'negative file count' => [
                'max_num_files',
                -1
            ]
        ];
    }
}
