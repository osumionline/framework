<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\Core;

use Osumi\OsumiFramework\Cache\OCacheContainer;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Core\OConfig;
use Osumi\OsumiFramework\Core\OCore;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\Routing\ORoute;
use Osumi\OsumiFramework\Tests\Fixtures\Component\BasicComponent;
use Osumi\OsumiFramework\Tests\Support\TemporaryProject;
use PHPUnit\Framework\TestCase;

final class ReplaceBodyMiddleware {
    /**
     * Replace the rendered component body during afterRender.
     *
     * @param string $phase Middleware phase.
     * @param array<string, mixed> $data Middleware data.
     *
     * @return array<string, mixed> Middleware result.
     */
    public static function handle(
        string $phase,
        array $data
    ): array {
        if ($phase !== OMiddleware::PHASE_AFTER_RENDER) {
            return [];
        }

        return [
            'body' => 'middleware-body'
        ];
    }
}

final class StopBeforeMiddleware {
    /**
     * Stop the request during the before phase.
     *
     * @param string $phase Middleware phase.
     * @param array<string, mixed> $data Middleware data.
     *
     * @return array<string, mixed> Middleware result.
     */
    public static function handle(
        string $phase,
        array $data
    ): array {
        if ($phase !== OMiddleware::PHASE_BEFORE) {
            return [];
        }

        return [
            'stop' => true,
            'status_code' => 401,
            'message' => 'Denied'
        ];
    }
}

final class ObserveErrorMiddleware {
    public static bool $saw_error = false;
    public static ?string $error_phase = null;
    public static int $error_status_code = 0;
    public static string $error_message = '';

    /**
     * Reset captured middleware state.
     *
     * @return void
     */
    public static function reset(): void {
        self::$saw_error = false;
        self::$error_phase = null;
        self::$error_status_code = 0;
        self::$error_message = '';
    }

    /**
     * Capture the error state received by afterResponse.
     *
     * @param string $phase Middleware phase.
     * @param array<string, mixed> $data Middleware data.
     *
     * @return array<string, mixed> Middleware result.
     */
    public static function handle(
        string $phase,
        array $data
    ): array {
        if ($phase !== OMiddleware::PHASE_AFTER_RESPONSE) {
            return [];
        }

        self::$saw_error = $data['is_error'] === true;
        self::$error_phase = is_string($data['error_phase'])
            ? $data['error_phase']
            : null;
        self::$error_status_code = is_int($data['error_status_code'])
            ? $data['error_status_code']
            : 0;
        self::$error_message = is_string($data['error_message'])
            ? $data['error_message']
            : '';

        return [
            'body' => 'after-response-error'
        ];
    }
}

final class NeverInstantiateComponent extends OComponent {
    /**
     * Fail if the component is instantiated.
     *
     * @param array<array-key, mixed> $vars Component variables.
     *
     * @throws \RuntimeException Always.
     */
    public function __construct(array $vars = []) {
        throw new \RuntimeException(
            'This component must not be instantiated after a before stop.'
        );
    }
}

final class OCoreMiddlewareTest extends TestCase {
    private TemporaryProject $project;
    private bool $core_existed = false;
    private mixed $previous_core = null;

    /**
     * @var array<string, mixed>
     */
    private array $previous_server = [];

    /**
     * @var array<string, mixed>
     */
    private array $previous_get = [];

    /**
     * @var array<string, mixed>
     */
    private array $previous_post = [];

    /**
     * @var array<string, mixed>
     */
    private array $previous_files = [];

    /**
     * Prepare an isolated core and HTTP request environment.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();

        $this->project = new TemporaryProject();

        $framework_path = $this->project->getPath(
            'vendor/osumionline/framework'
        );

        if (
            !mkdir(
                $framework_path,
                0755,
                true
            ) &&
            !is_dir($framework_path)
        ) {
            throw new \RuntimeException(
                "Could not create temporary framework directory '{$framework_path}'."
            );
        }

        $composer_content = json_encode(
            [
                'version' => '9.9.0'
            ],
            JSON_PRETTY_PRINT |
                JSON_UNESCAPED_SLASHES |
                JSON_THROW_ON_ERROR
        );

        if (
            file_put_contents(
                $framework_path . '/composer.json',
                $composer_content,
                LOCK_EX
            ) === false
        ) {
            throw new \RuntimeException(
                'Could not create temporary framework composer.json.'
            );
        }

        $this->core_existed = array_key_exists(
            'core',
            $GLOBALS
        );
        $this->previous_core = $GLOBALS['core']
            ?? null;

        $this->previous_server = $_SERVER;
        $this->previous_get = $_GET;
        $this->previous_post = $_POST;
        $this->previous_files = $_FILES;

        $config = new OConfig(
            $this->project->getBasePath()
        );
        $config->setLog(
            'name',
            'core-middleware-test'
        );

        $core = new OCore();
        $core->config = $config;

        $GLOBALS['core'] = $core;

        $core->cache_container = new OCacheContainer();

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $_GET = [];
        $_POST = [];
        $_FILES = [];

        ORoute::$routes = [];
        OMiddleware::setGlobal([]);
        OMiddleware::reset();
        ObserveErrorMiddleware::reset();

        http_response_code(
            200
        );
    }

    /**
     * Restore global state after every test.
     *
     * @return void
     */
    protected function tearDown(): void {
        ORoute::$routes = [];
        OMiddleware::setGlobal([]);
        OMiddleware::reset();
        ObserveErrorMiddleware::reset();

        $_SERVER = $this->previous_server;
        $_GET = $this->previous_get;
        $_POST = $this->previous_post;
        $_FILES = $this->previous_files;

        if ($this->core_existed) {
            $GLOBALS['core'] = $this->previous_core;
        } else {
            unset(
                $GLOBALS['core']
            );
        }

        http_response_code(
            200
        );

        $this->project->remove();

        parent::tearDown();
    }

    /**
     * Test that afterRender can replace the body produced by a component.
     *
     * @return void
     *
     * @throws \RuntimeException If output buffering cannot be used.
     */
    public function testAfterRenderCanReplaceComponentBody(): void {
        ORoute::get(
            '/middleware',
            BasicComponent::class,
            [
                'afterRender' => [
                    ReplaceBodyMiddleware::class
                ]
            ]
        );

        $_SERVER['REQUEST_URI'] = '/middleware';

        $output = $this->runCoreAndCaptureOutput();

        self::assertSame(
            'middleware-body',
            $output
        );
    }

    /**
     * Test that a before stop skips component creation and still runs afterResponse.
     *
     * @return void
     *
     * @throws \RuntimeException If output buffering cannot be used.
     */
    public function testBeforeStopSkipsComponentAndRunsAfterResponse(): void {
        ORoute::get(
            '/blocked',
            NeverInstantiateComponent::class,
            [
                'before' => [
                    StopBeforeMiddleware::class
                ],
                'afterResponse' => [
                    ObserveErrorMiddleware::class
                ]
            ]
        );

        $_SERVER['REQUEST_URI'] = '/blocked';

        $output = $this->runCoreAndCaptureOutput();

        self::assertSame(
            'after-response-error',
            $output
        );
        self::assertTrue(
            ObserveErrorMiddleware::$saw_error
        );
        self::assertSame(
            OMiddleware::PHASE_BEFORE,
            ObserveErrorMiddleware::$error_phase
        );
        self::assertSame(
            401,
            ObserveErrorMiddleware::$error_status_code
        );
        self::assertSame(
            'Denied',
            ObserveErrorMiddleware::$error_message
        );
        self::assertSame(
            401,
            http_response_code()
        );
    }

    /**
     * Execute the configured core and capture its response body.
     *
     * @return string Captured response body.
     *
     * @throws \RuntimeException If output buffering cannot be used.
     */
    private function runCoreAndCaptureOutput(): string {
        $core = $GLOBALS['core'];

        if (!$core instanceof OCore) {
            throw new \RuntimeException(
                'Test core is not available.'
            );
        }

        $buffer_level = ob_get_level();

        if (!ob_start()) {
            throw new \RuntimeException(
                'Could not start test output buffer.'
            );
        }

        try {
            $core->run();

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

        return $output;
    }
}
