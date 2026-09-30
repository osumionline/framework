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
     * Test middleware accumulation order across nested route groups.
     *
     * @return void
     */
    public function testNestedMiddlewaresAreAccumulatedInDeclarationOrder(): void {
        ORoute::prefix(
            '/api',
            function (): void {
                ORoute::layout(
                    'App\\MainLayout',
                    function (): void {
                        ORoute::group(
                            '/v1',
                            'App\\ApiLayout',
                            function (): void {
                                ORoute::get(
                                    '/users',
                                    'App\\UsersComponent',
                                    [
                                        'before' => ['App\\Middleware\\RouteBeforeMiddleware'],
                                        'afterRender' => ['App\\Middleware\\RouteRenderMiddleware'],
                                        'afterResponse' => ['App\\Middleware\\RouteResponseMiddleware']
                                    ]
                                );
                            },
                            [
                                'before' => ['App\\Middleware\\GroupBeforeMiddleware'],
                                'afterRender' => ['App\\Middleware\\GroupRenderMiddleware']
                            ]
                        );
                    },
                    [
                        'before' => ['App\\Middleware\\LayoutBeforeMiddleware'],
                        'afterResponse' => ['App\\Middleware\\LayoutResponseMiddleware']
                    ]
                );
            },
            [
                'before' => ['App\\Middleware\\PrefixBeforeMiddleware'],
                'afterRender' => ['App\\Middleware\\PrefixRenderMiddleware'],
                'afterResponse' => ['App\\Middleware\\PrefixResponseMiddleware']
            ]
        );

        self::assertSame(
            [
                'App\\Middleware\\PrefixBeforeMiddleware',
                'App\\Middleware\\LayoutBeforeMiddleware',
                'App\\Middleware\\GroupBeforeMiddleware',
                'App\\Middleware\\RouteBeforeMiddleware'
            ],
            ORoute::$routes[0]['middlewares']['before']
        );

        self::assertSame(
            [
                'App\\Middleware\\PrefixRenderMiddleware',
                'App\\Middleware\\GroupRenderMiddleware',
                'App\\Middleware\\RouteRenderMiddleware'
            ],
            ORoute::$routes[0]['middlewares']['afterRender']
        );

        self::assertSame(
            [
                'App\\Middleware\\PrefixResponseMiddleware',
                'App\\Middleware\\LayoutResponseMiddleware',
                'App\\Middleware\\RouteResponseMiddleware'
            ],
            ORoute::$routes[0]['middlewares']['afterResponse']
        );

        self::assertSame(
            '/api/v1/users',
            ORoute::$routes[0]['url']
        );

        self::assertSame(
            'App\\ApiLayout',
            ORoute::$routes[0]['layout']
        );
    }

    /**
     * Test that routing scope is restored when a grouped callback throws.
     *
     * @return void
     */
    public function testRoutingScopeIsRestoredAfterException(): void {
        try {
            ORoute::prefix(
                '/api',
                function (): void {
                    ORoute::layout(
                        'App\\BrokenLayout',
                        function (): void {
                            throw new \RuntimeException(
                                'Expected test exception.'
                            );
                        },
                        [
                            'before' => ['App\\Middleware\\LayoutMiddleware']
                        ]
                    );
                },
                [
                    'before' => ['App\\Middleware\\PrefixMiddleware']
                ]
            );

            self::fail(
                'The grouped callback should have thrown an exception.'
            );
        } catch (\RuntimeException $exception) {
            self::assertSame(
                'Expected test exception.',
                $exception->getMessage()
            );
        }

        ORoute::get(
            '/health',
            'App\\HealthComponent'
        );

        self::assertSame(
            '/health',
            ORoute::$routes[0]['url']
        );

        self::assertNull(
            ORoute::$routes[0]['layout']
        );

        self::assertSame(
            [
                'before' => [],
                'afterRender' => [],
                'afterResponse' => []
            ],
            ORoute::$routes[0]['middlewares']
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
     * Test that addRoute normalizes the HTTP method, URL and middleware phases.
     *
     * @return void
     */
    public function testAddRouteNormalizesMethodUrlAndMiddlewares(): void {
        ORoute::addRoute(
            ' post ',
            '//api///user/',
            'App\\UserComponent',
            [
                'before' => ['App\\Middleware\\AuthMiddleware']
            ]
        );

        self::assertSame(
            'POST',
            ORoute::$routes[0]['method']
        );

        self::assertSame(
            '/api/user',
            ORoute::$routes[0]['url']
        );

        self::assertSame(
            [
                'before' => ['App\\Middleware\\AuthMiddleware'],
                'afterRender' => [],
                'afterResponse' => []
            ],
            ORoute::$routes[0]['middlewares']
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

        ORoute::get(
            '/user',
            'App\\UserComponent',
            [
                'invalid' => []
            ]
        );
    }

    /**
     * Test that middleware phases must contain arrays.
     *
     * @return void
     */
    public function testMiddlewarePhaseMustContainAnArray(): void {
        $this->expectException(
            \InvalidArgumentException::class
        );

        ORoute::get(
            '/user',
            'App\\UserComponent',
            [
                'before' => 'App\\Middleware\\AuthMiddleware'
            ]
        );
    }

    /**
     * Test that middleware class names cannot be empty.
     *
     * @return void
     */
    public function testMiddlewareClassNameCannotBeEmpty(): void {
        $this->expectException(
            \InvalidArgumentException::class
        );

        ORoute::get(
            '/user',
            'App\\UserComponent',
            [
                'before' => ['']
            ]
        );
    }
}
