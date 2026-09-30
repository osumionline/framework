<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\Routing;

use Osumi\OsumiFramework\Routing\ORoute;
use PHPUnit\Framework\TestCase;

final class ORouteTest extends TestCase {
    /**
     * Reset registered routes before every test.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();

        ORoute::$routes = [];
    }

    /**
     * Reset registered routes after every test.
     *
     * @return void
     */
    protected function tearDown(): void {
        ORoute::$routes = [];

        parent::tearDown();
    }

    /**
     * Test that nested prefixes are accumulated and normalized.
     *
     * @return void
     */
    public function testNestedPrefixesAreAccumulated(): void {
        ORoute::prefix('/api/', function (): void {
            ORoute::prefix('/v1/', function (): void {
                ORoute::get(
                    '/users/',
                    'App\\UsersComponent'
                );
            });
        });

        self::assertCount(
            1,
            ORoute::$routes
        );
        self::assertSame(
            '/api/v1/users',
            ORoute::$routes[0]['url']
        );
    }

    /**
     * Test that nested layouts are restored when their callback finishes.
     *
     * @return void
     */
    public function testNestedLayoutsAreRestored(): void {
        ORoute::layout('App\\MainLayout', function (): void {
            ORoute::get(
                '/first',
                'App\\FirstComponent'
            );

            ORoute::layout('App\\InnerLayout', function (): void {
                ORoute::get(
                    '/second',
                    'App\\SecondComponent'
                );
            });

            ORoute::get(
                '/third',
                'App\\ThirdComponent'
            );
        });

        self::assertSame(
            'App\\MainLayout',
            ORoute::$routes[0]['layout']
        );

        self::assertSame(
            'App\\InnerLayout',
            ORoute::$routes[1]['layout']
        );

        self::assertSame(
            'App\\MainLayout',
            ORoute::$routes[2]['layout']
        );
    }

    /**
     * Test that the same URL may be registered for different HTTP methods.
     *
     * @return void
     */
    public function testSameUrlCanUseDifferentMethods(): void {
        ORoute::get(
            '/user',
            'App\\GetUserComponent'
        );

        ORoute::post(
            '/user',
            'App\\SaveUserComponent'
        );

        self::assertCount(
            2,
            ORoute::$routes
        );

        self::assertSame(
            'GET',
            ORoute::$routes[0]['method']
        );
        self::assertSame(
            'POST',
            ORoute::$routes[1]['method']
        );
    }

    /**
     * Test that duplicate method and URL combinations are rejected.
     *
     * @return void
     */
    public function testDuplicateMethodAndUrlAreRejected(): void {
        ORoute::get(
            '/user',
            'App\\FirstComponent'
        );

        $this->expectException(
            \LogicException::class
        );

        ORoute::get(
            '/user',
            'App\\SecondComponent'
        );
    }

    /**
     * Test that addRoute normalizes the HTTP method and URL.
     *
     * @return void
     */
    public function testAddRouteNormalizesMethodAndUrl(): void {
        ORoute::addRoute(
            ' post ',
            '//api///user/',
            'App\\UserComponent',
            []
        );

        self::assertSame(
            'POST',
            ORoute::$routes[0]['method']
        );

        self::assertSame(
            '/api/user',
            ORoute::$routes[0]['url']
        );
    }
}
