<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\Routing;

use Osumi\OsumiFramework\Routing\ORouteCheck;
use PHPUnit\Framework\TestCase;

final class ORouteCheckTest extends TestCase {
    /**
     * Test variable matching and URL decoding.
     *
     * @return void
     */
    public function testMatchesAndDecodesVariable(): void {
        $route = new ORouteCheck(
            '/user/:slug'
        );

        $result = $route->matchesUrl(
            '/user/hello%20world'
        );

        self::assertNotNull(
            $result
        );

        self::assertSame(
            'hello world',
            $result['slug']
        );
    }

    /**
     * Test that requirement anchors are normalized.
     *
     * @return void
     */
    public function testRequirementAnchorsAreNormalized(): void {
        $route = new ORouteCheck(
            '/user/:id',
            [],
            [
                'id' => '^\d+$'
            ]
        );

        self::assertNotNull(
            $route->matchesUrl(
                '/user/25'
            )
        );

        self::assertNull(
            $route->matchesUrl(
                '/user/test'
            )
        );
    }

    /**
     * Test that empty route requirements are rejected.
     *
     * @return void
     */
    public function testEmptyRequirementIsRejected(): void {
        $route = new ORouteCheck(
            '/user/:id',
            [],
            [
                'id' => ''
            ]
        );

        $this->expectException(
            \InvalidArgumentException::class
        );

        $route->compile();
    }

    /**
     * Test that numeric default entries are converted to boolean flags.
     *
     * @return void
     */
    public function testNumericDefaultsBecomeBooleanFlags(): void {
        $route = new ORouteCheck(
            '/archive',
            [
                'preview'
            ]
        );

        $result = $route->matchesUrl(
            '/archive'
        );

        self::assertNotNull(
            $result
        );

        self::assertTrue(
            $result['preview']
        );
    }

    /**
     * Test that string default values are URL-decoded.
     *
     * @return void
     */
    public function testStringDefaultsAreDecoded(): void {
        $route = new ORouteCheck(
            '/archive',
            [
                'lang' => 'es%20ES'
            ]
        );

        $result = $route->matchesUrl(
            '/archive'
        );

        self::assertNotNull(
            $result
        );

        self::assertSame(
            'es ES',
            $result['lang']
        );
    }

    /**
     * Test that a failed compilation does not mark the route as compiled.
     *
     * A second compilation attempt must fail in the same controlled way instead
     * of using incomplete compiled state.
     *
     * @return void
     */
    public function testFailedCompilationCanBeRetriedSafely(): void {
        $route = new ORouteCheck('/user/:id', [], ['id' => '[']);
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $route->compile();

                self::fail(
                    'Invalid route requirement was expected to fail compilation.'
                );
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString(
                    'invalid regular expression',
                    $e->getMessage()
                );
            }
        }
    }
}
