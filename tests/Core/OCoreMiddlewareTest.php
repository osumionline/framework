<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\Core;

use Osumi\OsumiFramework\Cache\OCacheContainer;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Core\OConfig;
use Osumi\OsumiFramework\Core\OCore;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\Routing\ORoute;
use Osumi\OsumiFramework\Web\OStreamResponse;
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

final class StopAfterResponseMiddleware {
    /**
     * Stop the request during the afterResponse phase.
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

        return [
            'stop' => true,
            'status_code' => 503,
            'message' => 'Unavailable'
        ];
    }
}

final class NeverRunAfterResponseMiddleware {
    public static bool $executed = false;

    /**
     * Reset middleware execution state.
     *
     * @return void
     */
    public static function reset(): void {
        self::$executed = false;
    }

    /**
     * Record execution of the middleware.
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
        if ($phase === OMiddleware::PHASE_AFTER_RESPONSE) {
            self::$executed = true;
        }

        return [];
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

final class CoreStreamComponent extends OComponent {
    public static ?OStreamResponse $last_response = null;

    /**
     * Reset the last streamed response.
     *
     * @return void
     */
    public static function reset(): void {
        self::$last_response = null;
    }

    /**
     * Create a streamed test response.
     *
     * @return OStreamResponse Streamed response.
     */
    public function run(): OStreamResponse {
        $stream = fopen(
            'php://temp',
            'w+b'
        );

        if ($stream === false) {
            throw new \RuntimeException(
                'Could not create test stream.'
            );
        }

        fwrite(
            $stream,
            'streamed-content'
        );

        rewind(
            $stream
        );

        $response = new OStreamResponse(
            $stream,
            [
                'Content-Type' => 'application/octet-stream',
                'Content-Length' => '16',
                'Content-Disposition' => 'attachment; filename="test.bin"'
            ],
            206,
            4
        );

        self::$last_response = $response;

        return $response;
    }
}

final class ObserveStreamingPipelineMiddleware {
    /**
     * @var list<array{phase: string, streaming: bool}>
     */
    public static array $observations = [];

    /**
     * Reset observed streaming phases.
     *
     * @return void
     */
    public static function reset(): void {
        self::$observations = [];
    }

    /**
     * Observe streamed response state.
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
        self::$observations[] = [
            'phase' => $phase,
            'streaming' => ($data['is_streaming_response'] ?? false) === true
        ];

        return [];
    }
}

final class StopStreamAfterRenderMiddleware {
    /**
     * Stop a streamed response during afterRender.
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
            'stop' => true,
            'status_code' => 409,
            'message' => 'Stream rejected'
        ];
    }
}

final class ThrowStreamAfterRenderMiddleware {
    /**
     * Throw during streamed afterRender processing.
     *
     * @param string $phase Middleware phase.
     * @param array<string, mixed> $data Middleware data.
     *
     * @return array<string, mixed> Middleware result.
     *
     * @throws \RuntimeException During afterRender.
     */
    public static function handle(
        string $phase,
        array $data
    ): array {
        if ($phase === OMiddleware::PHASE_AFTER_RENDER) {
            throw new \RuntimeException(
                'Stream middleware exploded.'
            );
        }

        return [];
    }
}

final class SetCoreStatusMiddleware {
    /**
     * Change the HTTP status through the public OCore API.
     *
     * @param string $phase Middleware phase.
     * @param array<string, mixed> $data Middleware data.
     *
     * @return array<string, mixed> Middleware result.
     *
     * @throws \RuntimeException If the test core is unavailable.
     */
    public static function handle(
        string $phase,
        array $data
    ): array {
        if ($phase !== OMiddleware::PHASE_AFTER_RENDER) {
            return [];
        }

        $core = $GLOBALS['core']
            ?? null;

        if (!$core instanceof OCore) {
            throw new \RuntimeException(
                'Test core is not available.'
            );
        }

        $core->setHttpStatus(
            404
        );

        return [];
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

        $template_path = $framework_path
            . '/src/Assets/template';

        if (
            !mkdir(
                $template_path,
                0755,
                true
            ) &&
            !is_dir($template_path)
        ) {
            throw new \RuntimeException(
                "Could not create temporary template directory '{$template_path}'."
            );
        }

        $middleware_error_templates = [
            'html' => "<h1>Error {{status_code}}</h1>\n<p>{{message}}</p>",
            'json' => '{"status":"error","status_code":{{status_code}},"message":{{message}}}',
            'xml' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
                . "<error>\n"
                . "\t<status>error</status>\n"
                . "\t<status_code>{{status_code}}</status_code>\n"
                . "\t<message>{{message}}</message>\n"
                . "</error>"
        ];

        foreach ($middleware_error_templates as $type => $content) {
            $file = $template_path
                . '/error.'
                . $type;

            if (
                file_put_contents(
                    $file,
                    $content,
                    LOCK_EX
                ) === false
            ) {
                throw new \RuntimeException(
                    "Could not create temporary middleware error template '{$file}'."
                );
            }
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
        NeverRunAfterResponseMiddleware::reset();
        CoreStreamComponent::reset();
        ObserveStreamingPipelineMiddleware::reset();

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
        NeverRunAfterResponseMiddleware::reset();
        CoreStreamComponent::reset();
        ObserveStreamingPipelineMiddleware::reset();

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
     * Test that an afterResponse stop replaces the final response and prevents
     * later middlewares in the phase from running.
     *
     * @return void
     *
     * @throws \RuntimeException If output buffering cannot be used.
     */
    public function testAfterResponseStopProducesFinalErrorResponse(): void {
        ORoute::get(
            '/after-response-stop',
            BasicComponent::class,
            [
                'afterResponse' => [
                    StopAfterResponseMiddleware::class,
                    NeverRunAfterResponseMiddleware::class
                ]
            ]
        );

        $_SERVER['REQUEST_URI'] = '/after-response-stop';

        $output = $this->runCoreAndCaptureOutput();

        self::assertSame(
            "<h1>Error 503</h1>\n<p>Unavailable</p>",
            $output
        );

        self::assertSame(
            503,
            http_response_code()
        );

        self::assertTrue(
            OMiddleware::isError()
        );

        self::assertSame(
            OMiddleware::PHASE_AFTER_RESPONSE,
            OMiddleware::getErrorPhase()
        );

        self::assertFalse(
            NeverRunAfterResponseMiddleware::$executed
        );
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
     * Test that middleware stops use the HTML error template.
     *
     * @return void
     *
     * @throws \RuntimeException If output buffering cannot be used.
     */
    public function testBeforeStopUsesHtmlErrorTemplate(): void {
        ORoute::view(
            '/blocked-html',
            'blocked.html',
            [
                'before' => [
                    StopBeforeMiddleware::class
                ]
            ]
        );

        $_SERVER['REQUEST_URI'] = '/blocked-html';

        $output = $this->runCoreAndCaptureOutput();

        self::assertSame(
            "<h1>Error 401</h1>\n<p>Denied</p>",
            $output
        );
    }

    /**
     * Test that middleware stops use the JSON error template.
     *
     * @return void
     *
     * @throws \JsonException If the response cannot be decoded.
     * @throws \RuntimeException If output buffering cannot be used.
     */
    public function testBeforeStopUsesJsonErrorTemplate(): void {
        ORoute::view(
            '/blocked-json',
            'blocked.json',
            [
                'before' => [
                    StopBeforeMiddleware::class
                ]
            ]
        );

        $_SERVER['REQUEST_URI'] = '/blocked-json';

        $output = $this->runCoreAndCaptureOutput();

        $response = json_decode(
            $output,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertSame(
            [
                'status' => 'error',
                'status_code' => 401,
                'message' => 'Denied'
            ],
            $response
        );
    }

    /**
     * Test that middleware stops use the XML error template.
     *
     * @return void
     *
     * @throws \RuntimeException If output buffering cannot be used.
     */
    public function testBeforeStopUsesXmlErrorTemplate(): void {
        ORoute::view(
            '/blocked-xml',
            'blocked.xml',
            [
                'before' => [
                    StopBeforeMiddleware::class
                ]
            ]
        );

        $_SERVER['REQUEST_URI'] = '/blocked-xml';

        $output = $this->runCoreAndCaptureOutput();

        self::assertStringStartsWith(
            '<?xml version="1.0" encoding="UTF-8"?>',
            $output
        );

        self::assertStringContainsString(
            '<status_code>401</status_code>',
            $output
        );

        self::assertStringContainsString(
            '<message>Denied</message>',
            $output
        );

        self::assertStringNotContainsString(
            '<h1>',
            $output
        );
    }

    /**
     * Test that streamed responses pass through middleware phases before emission.
     *
     * @return void
     *
     * @throws \RuntimeException If output buffering cannot be used.
     */
    public function testStreamResponseUsesMiddlewarePipelineAndIsClosed(): void {
        ORoute::get(
            '/stream',
            CoreStreamComponent::class,
            [
                'afterRender' => [
                    ObserveStreamingPipelineMiddleware::class
                ],
                'afterResponse' => [
                    ObserveStreamingPipelineMiddleware::class
                ]
            ]
        );

        $_SERVER['REQUEST_URI'] = '/stream';

        $output = $this->runCoreAndCaptureOutput();

        self::assertSame(
            'streamed-content',
            $output
        );

        self::assertSame(
            [
                [
                    'phase' => OMiddleware::PHASE_AFTER_RENDER,
                    'streaming' => true
                ],
                [
                    'phase' => OMiddleware::PHASE_AFTER_RESPONSE,
                    'streaming' => true
                ]
            ],
            ObserveStreamingPipelineMiddleware::$observations
        );

        self::assertSame(
            206,
            http_response_code()
        );

        self::assertFalse(
            OMiddleware::isStreamingResponse()
        );

        self::assertNotNull(
            CoreStreamComponent::$last_response
        );

        self::assertFalse(
            CoreStreamComponent::$last_response->isOpen()
        );
    }

    /**
     * Test that an afterRender stop discards the stream before emission.
     *
     * @return void
     *
     * @throws \RuntimeException If output buffering cannot be used.
     */
    public function testStreamResponseCanBeStoppedBeforeEmission(): void {
        ORoute::get(
            '/stream-stopped',
            CoreStreamComponent::class,
            [
                'afterRender' => [
                    StopStreamAfterRenderMiddleware::class
                ],
                'afterResponse' => [
                    ObserveErrorMiddleware::class
                ]
            ]
        );

        $_SERVER['REQUEST_URI'] = '/stream-stopped';

        $output = $this->runCoreAndCaptureOutput();

        self::assertSame(
            'after-response-error',
            $output
        );

        self::assertStringNotContainsString(
            'streamed-content',
            $output
        );

        self::assertTrue(
            ObserveErrorMiddleware::$saw_error
        );

        self::assertSame(
            OMiddleware::PHASE_AFTER_RENDER,
            ObserveErrorMiddleware::$error_phase
        );

        self::assertSame(
            409,
            http_response_code()
        );

        self::assertFalse(
            OMiddleware::isStreamingResponse()
        );

        self::assertArrayNotHasKey(
            'Content-Disposition',
            OMiddleware::getHeaders()
        );

        self::assertArrayNotHasKey(
            'Content-Length',
            OMiddleware::getHeaders()
        );

        self::assertFalse(
            CoreStreamComponent::$last_response?->isOpen()
                ?? true
        );
    }

    /**
     * Test that an afterResponse stop still occurs before stream bytes are emitted.
     *
     * @return void
     *
     * @throws \RuntimeException If output buffering cannot be used.
     */
    public function testStreamResponseCanBeStoppedDuringAfterResponse(): void {
        ORoute::get(
            '/stream-after-response-stop',
            CoreStreamComponent::class,
            [
                'afterResponse' => [
                    StopAfterResponseMiddleware::class,
                    NeverRunAfterResponseMiddleware::class
                ]
            ]
        );

        $_SERVER['REQUEST_URI'] = '/stream-after-response-stop';

        $output = $this->runCoreAndCaptureOutput();

        self::assertSame(
            "<h1>Error 503</h1>\n<p>Unavailable</p>",
            $output
        );

        self::assertStringNotContainsString(
            'streamed-content',
            $output
        );

        self::assertFalse(
            NeverRunAfterResponseMiddleware::$executed
        );

        self::assertSame(
            503,
            http_response_code()
        );

        self::assertFalse(
            CoreStreamComponent::$last_response?->isOpen()
                ?? true
        );
    }

    /**
     * Test that a pre-emission stream exception restores normal response state.
     *
     * @return void
     */
    public function testStreamExceptionBeforeEmissionRestoresResponseState(): void {
        ORoute::get(
            '/stream-exception',
            CoreStreamComponent::class,
            [
                'afterRender' => [
                    ThrowStreamAfterRenderMiddleware::class
                ]
            ]
        );

        $_SERVER['REQUEST_URI'] = '/stream-exception';

        try {
            $this->runCoreAndCaptureOutput();

            self::fail(
                'The streamed middleware was expected to throw.'
            );
        } catch (\RuntimeException $exception) {
            self::assertSame(
                'Stream middleware exploded.',
                $exception->getMessage()
            );
        }

        self::assertFalse(
            OMiddleware::isStreamingResponse()
        );

        self::assertSame(
            200,
            OMiddleware::getStatusCode()
        );

        self::assertArrayNotHasKey(
            'Content-Disposition',
            OMiddleware::getHeaders()
        );

        self::assertArrayNotHasKey(
            'Content-Length',
            OMiddleware::getHeaders()
        );

        self::assertNotNull(
            CoreStreamComponent::$last_response
        );

        self::assertFalse(
            CoreStreamComponent::$last_response->isOpen()
        );
    }

    /**
     * Test that OCore::setHttpStatus remains effective in the middleware pipeline.
     *
     * @return void
     *
     * @throws \RuntimeException If output buffering cannot be used.
     */
    public function testCoreHttpStatusIsPropagatedToFinalResponse(): void {
        ORoute::get(
            '/core-status',
            BasicComponent::class,
            [
                'afterRender' => [
                    SetCoreStatusMiddleware::class
                ]
            ]
        );

        $_SERVER['REQUEST_URI'] = '/core-status';

        $this->runCoreAndCaptureOutput();

        self::assertSame(
            404,
            http_response_code()
        );

        self::assertSame(
            404,
            OMiddleware::getStatusCode()
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
