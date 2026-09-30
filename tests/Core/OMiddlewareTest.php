<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\Core;

use Osumi\OsumiFramework\Core\OMiddleware;
use PHPUnit\Framework\TestCase;

final class OMiddlewareTest extends TestCase {
    /**
     * Reset middleware state before every test.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();

        OMiddleware::setGlobal([]);
        OMiddleware::reset();
        ContextConsumerMiddleware::resetObservedContext();
        ErrorObserverMiddleware::resetObservedError();
        RequestHeaderObserverMiddleware::resetObservedHeader();
    }

    /**
     * Test that global middlewares run before route middlewares.
     *
     * @return void
     */
    public function testGlobalMiddlewaresRunBeforeRouteMiddlewares(): void {
        OMiddleware::setGlobal([
            OMiddleware::PHASE_BEFORE => [
                GlobalOrderMiddleware::class
            ]
        ]);

        OMiddleware::setRoute([
            OMiddleware::PHASE_BEFORE => [
                RouteOrderMiddleware::class
            ]
        ]);

        OrderRecorder::reset();

        $result = OMiddleware::runPhase(
            OMiddleware::PHASE_BEFORE,
            []
        );

        self::assertFalse(
            $result['stop']
        );

        self::assertSame(
            [
                'global',
                'route'
            ],
            OrderRecorder::$calls
        );
    }

    /**
     * Test that middleware context is available to downstream middlewares.
     *
     * @return void
     */
    public function testContextIsAvailableToDownstreamMiddlewares(): void {
        OMiddleware::setRoute([
            OMiddleware::PHASE_BEFORE => [
                ContextProducerMiddleware::class,
                ContextConsumerMiddleware::class
            ]
        ]);

        OMiddleware::runPhase(
            OMiddleware::PHASE_BEFORE,
            []
        );

        self::assertSame(
            42,
            ContextConsumerMiddleware::$observed_user_id
        );

        self::assertSame(
            [
                'user_id' => 42
            ],
            OMiddleware::getMiddlewareContext(
                'ContextProducer'
            )
        );

        self::assertSame(
            42,
            OMiddleware::getContext(
                'ContextProducer',
                'user_id'
            )
        );
    }


    /**
     * Test that request headers passed by OCore are preserved in middleware data.
     *
     * @return void
     */
    public function testRequestHeadersArePreservedInPhaseData(): void {
        OMiddleware::setRoute([
            OMiddleware::PHASE_BEFORE => [
                RequestHeaderObserverMiddleware::class
            ]
        ]);

        OMiddleware::runPhase(
            OMiddleware::PHASE_BEFORE,
            [
                'headers' => [
                    'Authorization' => 'Bearer test'
                ]
            ]
        );

        self::assertSame(
            'Bearer test',
            RequestHeaderObserverMiddleware::$observed_header
        );
    }

    /**
     * Test that the first afterResponse middleware can inspect a previous stop.
     *
     * @return void
     */
    public function testAfterResponseReceivesErrorStateFromPreviousStop(): void {
        OMiddleware::setRoute([
            OMiddleware::PHASE_BEFORE => [
                StopMiddleware::class
            ],
            OMiddleware::PHASE_AFTER_RESPONSE => [
                ErrorObserverMiddleware::class
            ]
        ]);

        $result = OMiddleware::runPhase(
            OMiddleware::PHASE_BEFORE,
            []
        );

        self::assertTrue(
            $result['stop']
        );
        self::assertSame(
            401,
            $result['status_code']
        );
        self::assertSame(
            'Unauthorized',
            $result['message']
        );

        OMiddleware::runPhase(
            OMiddleware::PHASE_AFTER_RESPONSE,
            []
        );

        self::assertSame(
            [
                'is_error' => true,
                'error_phase' => OMiddleware::PHASE_BEFORE,
                'error_status_code' => 401,
                'error_message' => 'Unauthorized'
            ],
            ErrorObserverMiddleware::$observed_error
        );
    }

    /**
     * Test body, headers and status propagation between middleware phases.
     *
     * @return void
     */
    public function testResponseStateCanBeUpdatedByMiddlewares(): void {
        OMiddleware::setRoute([
            OMiddleware::PHASE_AFTER_RENDER => [
                AfterRenderBodyMiddleware::class
            ],
            OMiddleware::PHASE_AFTER_RESPONSE => [
                AfterResponseMiddleware::class
            ]
        ]);

        OMiddleware::setComponentBody(
            'component'
        );

        OMiddleware::runPhase(
            OMiddleware::PHASE_AFTER_RENDER,
            []
        );

        self::assertSame(
            'component-modified',
            OMiddleware::getComponentBody()
        );

        OMiddleware::setFinalBody(
            OMiddleware::getComponentBody()
        );

        OMiddleware::runPhase(
            OMiddleware::PHASE_AFTER_RESPONSE,
            []
        );

        self::assertSame(
            'final-response',
            OMiddleware::getFinalBody()
        );
        self::assertSame(
            [
                'X-Test' => 'middleware'
            ],
            OMiddleware::getHeaders()
        );
        self::assertSame(
            201,
            OMiddleware::getStatusCode()
        );
    }

    /**
     * Test that reset clears request state but preserves global middlewares.
     *
     * @return void
     */
    public function testResetPreservesOnlyGlobalMiddlewares(): void {
        OMiddleware::setGlobal([
            OMiddleware::PHASE_BEFORE => [
                GlobalOrderMiddleware::class
            ]
        ]);

        OMiddleware::setRoute([
            OMiddleware::PHASE_BEFORE => [
                RouteOrderMiddleware::class
            ]
        ]);

        OMiddleware::setComponentBody(
            'component'
        );
        OMiddleware::setFinalBody(
            'final'
        );
        OMiddleware::setHeader(
            'X-Test',
            'value'
        );
        OMiddleware::setStatusCode(
            202
        );

        OMiddleware::reset();

        self::assertSame(
            [
                OMiddleware::PHASE_BEFORE => [
                    GlobalOrderMiddleware::class
                ],
                OMiddleware::PHASE_AFTER_RENDER => [],
                OMiddleware::PHASE_AFTER_RESPONSE => []
            ],
            OMiddleware::getAll()
        );
        self::assertSame(
            '',
            OMiddleware::getComponentBody()
        );
        self::assertSame(
            '',
            OMiddleware::getFinalBody()
        );
        self::assertSame(
            [],
            OMiddleware::getHeaders()
        );
        self::assertSame(
            200,
            OMiddleware::getStatusCode()
        );
        self::assertFalse(
            OMiddleware::isError()
        );
    }

    /**
     * Test that unknown middleware phases are rejected.
     *
     * @return void
     */
    public function testUnknownMiddlewarePhaseIsRejected(): void {
        $this->expectException(
            \InvalidArgumentException::class
        );

        OMiddleware::setRoute([
            'befor' => [
                RouteOrderMiddleware::class
            ]
        ]);
    }

    /**
     * Test that missing middleware classes are rejected.
     *
     * @return void
     */
    public function testMissingMiddlewareClassIsRejected(): void {
        $this->expectException(
            \InvalidArgumentException::class
        );

        OMiddleware::setRoute([
            OMiddleware::PHASE_BEFORE => [
                'Osumi\\OsumiFramework\\Tests\\Core\\MissingMiddleware'
            ]
        ]);
    }

    /**
     * Test that invalid HTTP status codes are rejected.
     *
     * @return void
     */
    public function testInvalidStatusCodeIsRejected(): void {
        $this->expectException(
            \InvalidArgumentException::class
        );

        OMiddleware::setStatusCode(
            99
        );
    }

    /**
     * Test that response headers cannot contain line breaks.
     *
     * @return void
     */
    public function testInvalidHeaderValueIsRejected(): void {
        $this->expectException(
            \InvalidArgumentException::class
        );

        OMiddleware::setHeader(
            'X-Test',
            "value\r\nInjected: true"
        );
    }
}

final class OrderRecorder {
    /** @var list<string> */
    public static array $calls = [];

    /**
     * Reset recorded calls.
     *
     * @return void
     */
    public static function reset(): void {
        self::$calls = [];
    }
}

final class GlobalOrderMiddleware {
    /**
     * Record global middleware execution.
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
        OrderRecorder::$calls[] = 'global';

        return [];
    }
}

final class RouteOrderMiddleware {
    /**
     * Record route middleware execution.
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
        OrderRecorder::$calls[] = 'route';

        return [];
    }
}

final class ContextProducerMiddleware {
    /**
     * Publish middleware context.
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
        return [
            'context' => [
                'user_id' => 42
            ]
        ];
    }
}

final class ContextConsumerMiddleware {
    public static ?int $observed_user_id = null;

    /**
     * Reset observed context.
     *
     * @return void
     */
    public static function resetObservedContext(): void {
        self::$observed_user_id = null;
    }

    /**
     * Read context published by a previous middleware.
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
        $value = $data['context']['ContextProducer']['user_id']
            ?? null;

        self::$observed_user_id = is_int($value)
            ? $value
            : null;

        return [];
    }
}

final class StopMiddleware {
    /**
     * Stop middleware execution with an authorization error.
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
        return [
            'stop' => true,
            'status_code' => 401,
            'message' => 'Unauthorized'
        ];
    }
}

final class ErrorObserverMiddleware {
    /**
     * @var array{
     *     is_error: bool,
     *     error_phase: string|null,
     *     error_status_code: int,
     *     error_message: string
     * }|null
     */
    public static ?array $observed_error = null;

    /**
     * Reset observed error data.
     *
     * @return void
     */
    public static function resetObservedError(): void {
        self::$observed_error = null;
    }

    /**
     * Observe middleware error state.
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
        self::$observed_error = [
            'is_error' => $data['is_error'],
            'error_phase' => $data['error_phase'],
            'error_status_code' => $data['error_status_code'],
            'error_message' => $data['error_message']
        ];

        return [];
    }
}

final class AfterRenderBodyMiddleware {
    /**
     * Replace the component body after rendering.
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
        return [
            'body' => 'component-modified'
        ];
    }
}

final class AfterResponseMiddleware {
    /**
     * Replace final response state.
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
        return [
            'body' => 'final-response',
            'headers' => [
                'X-Test' => 'middleware'
            ],
            'status_code' => 201
        ];
    }
}

final class RequestHeaderObserverMiddleware {
    public static ?string $observed_header = null;

    /**
     * Reset observed request header.
     *
     * @return void
     */
    public static function resetObservedHeader(): void {
        self::$observed_header = null;
    }

    /**
     * Observe a request header passed to the middleware pipeline.
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
        $headers = $data['headers'] ?? null;

        if (is_array($headers)) {
            $value = $headers['Authorization'] ?? null;
            self::$observed_header = is_string($value)
                ? $value
                : null;
        }

        return [];
    }
}
