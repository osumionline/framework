<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\Web;

use Osumi\OsumiFramework\Web\ORequest;
use PHPUnit\Framework\TestCase;

final class ORequestTest extends TestCase {
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
            ],
            []
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
}
