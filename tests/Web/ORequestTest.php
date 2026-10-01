<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\Web;

use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\Web\ORequest;
use PHPUnit\Framework\TestCase;

final class RequestContextMiddleware {
    /**
     * Publish request test context during the before phase.
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
            'context' => [
                'id' => 25,
                'role' => 'admin'
            ]
        ];
    }
}

final class ORequestTest extends TestCase {
    /**
     * Reset middleware state before every test.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();

        OMiddleware::setGlobal([]);
        OMiddleware::reset();
    }

    /**
     * Reset middleware state after every test.
     *
     * @return void
     */
    protected function tearDown(): void {
        OMiddleware::setGlobal([]);
        OMiddleware::reset();

        parent::tearDown();
    }

    /**
     * Create a request for testing.
     *
     * @param array<string, mixed> $params Request parameters.
     * @param array<string, string> $headers Request headers.
     *
     * @return ORequest Request instance.
     */
    private function createRequest(
        array $params = [],
        array $headers = []
    ): ORequest {
        return new ORequest(
            [
                'method' => 'POST',
                'headers' => $headers,
                'params' => $params
            ]
        );
    }

    /**
     * Test typed request parameter conversion.
     *
     * @return void
     */
    public function testTypedParametersAreNormalized(): void {
        $request = $this->createRequest(
            [
                'id' => '25',
                'price' => '12.50',
                'enabled' => 'true',
                'name' => 123
            ]
        );

        self::assertSame(
            25,
            $request->getParamInt(
                'id'
            )
        );

        self::assertSame(
            12.5,
            $request->getParamFloat(
                'price'
            )
        );

        self::assertTrue(
            $request->getParamBool(
                'enabled'
            )
        );

        self::assertSame(
            '123',
            $request->getParamString(
                'name'
            )
        );
    }

    /**
     * Test that invalid typed parameter values become null.
     *
     * @return void
     */
    public function testInvalidTypedParametersReturnNull(): void {
        $request = $this->createRequest(
            [
                'id' => '25.5',
                'enabled' => 'not-a-boolean',
                'name' => [
                    'invalid'
                ]
            ]
        );

        self::assertNull(
            $request->getParamInt(
                'id'
            )
        );

        self::assertNull(
            $request->getParamBool(
                'enabled'
            )
        );

        self::assertNull(
            $request->getParamString(
                'name'
            )
        );
    }

    /**
     * Test that HTTP header lookup is case-insensitive.
     *
     * @return void
     */
    public function testHeadersAreCaseInsensitive(): void {
        $request = $this->createRequest(
            [],
            [
                'authorization' => 'Bearer token'
            ]
        );

        self::assertSame(
            'Bearer token',
            $request->getHeader(
                'Authorization'
            )
        );

        self::assertSame(
            'Bearer token',
            $request->getHeader(
                'AUTHORIZATION'
            )
        );
    }

    /**
     * Test default values for missing typed parameters.
     *
     * @return void
     */
    public function testTypedGettersUseDefaultsForMissingParameters(): void {
        $request = $this->createRequest();

        self::assertSame(
            10,
            $request->getParamInt(
                'id',
                10
            )
        );

        self::assertFalse(
            $request->getParamBool(
                'enabled',
                false
            )
        );

        self::assertSame(
            'default',
            $request->getParamString(
                'name',
                'default'
            )
        );
    }

    /**
     * Test access to context published by executed middlewares.
     *
     * @return void
     */
    public function testMiddlewareContextIsAvailable(): void {
        OMiddleware::setRoute(
            [
                'before' => [
                    RequestContextMiddleware::class
                ]
            ]
        );

        OMiddleware::runPhase(
            OMiddleware::PHASE_BEFORE,
            []
        );

        $request = $this->createRequest();

        self::assertSame(
            [
                'id' => 25,
                'role' => 'admin'
            ],
            $request->getMiddleware(
                'RequestContext'
            )
        );

        self::assertSame(
            25,
            $request->getMiddlewareValue(
                'RequestContext',
                'id'
            )
        );

        self::assertNull(
            $request->getMiddlewareValue(
                'RequestContext',
                'missing'
            )
        );
    }
}
