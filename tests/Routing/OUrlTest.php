<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\Routing;

use Osumi\OsumiFramework\Routing\ORoute;
use Osumi\OsumiFramework\Routing\OUrl;
use PHPUnit\Framework\TestCase;

final class OUrlTestConfig {
    /**
     * Create a test URL configuration.
     *
     * @param string $base_url Base URL.
     */
    public function __construct(
        private readonly string $base_url
    ) {
    }

    /**
     * Get a configured URL.
     *
     * @param string $key URL configuration key.
     *
     * @return string Configured URL.
     *
     * @throws \OutOfBoundsException If the requested key is invalid.
     */
    public function getUrl(
        string $key
    ): string {
        if ($key !== 'base') {
            throw new \OutOfBoundsException(
                "Unknown test URL key '{$key}'."
            );
        }

        return $this->base_url;
    }
}

final class OUrlTestCore {
    public OUrlTestConfig $config;

    /**
     * Create a test core object.
     *
     * @param string $base_url Base URL.
     */
    public function __construct(
        string $base_url
    ) {
        $this->config = new OUrlTestConfig(
            $base_url
        );
    }
}

final class OUrlTest extends TestCase {
    private bool $core_existed = false;
    private mixed $previous_core = null;

    /**
     * Reset route state and preserve the global core.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();

        ORoute::$routes = [];

        $this->core_existed = array_key_exists(
            'core',
            $GLOBALS
        );

        $this->previous_core = $GLOBALS['core']
            ?? null;
    }

    /**
     * Restore global state after every test.
     *
     * @return void
     */
    protected function tearDown(): void {
        ORoute::$routes = [];

        if ($this->core_existed) {
            $GLOBALS['core'] = $this->previous_core;
        } else {
            unset(
                $GLOBALS['core']
            );
        }

        parent::tearDown();
    }

    /**
     * Test that an exact HTTP method match has priority for the same URL.
     *
     * @return void
     */
    public function testProcessPrefersMatchingHttpMethod(): void {
        ORoute::get(
            '/user',
            'App\\GetUserComponent'
        );

        ORoute::post(
            '/user',
            'App\\SaveUserComponent'
        );

        $url = new OUrl(
            'POST'
        );

        $result = $url->process(
            '/user'
        );

        self::assertTrue(
            $result['res']
        );

        self::assertSame(
            'App\\SaveUserComponent',
            $result['component']
        );

        self::assertSame(
            'POST',
            $result['component_method']
        );
    }

    /**
     * Test that route middlewares are transported in the processed URL result.
     *
     * @return void
     */
    public function testProcessReturnsRouteMiddlewares(): void {
        ORoute::get(
            '/secure',
            'App\\SecureComponent',
            [
                'before' => ['App\\Middleware\\AuthMiddleware'],
                'afterResponse' => ['App\\Middleware\\AuditMiddleware']
            ]
        );

        $url = new OUrl(
            'GET'
        );

        $result = $url->process(
            '/secure'
        );

        self::assertSame(
            [
                'before' => ['App\\Middleware\\AuthMiddleware'],
                'afterRender' => [],
                'afterResponse' => ['App\\Middleware\\AuditMiddleware']
            ],
            $result['middlewares']
        );
    }

    /**
     * Test that an unsupported method still returns the matching URL.
     *
     * This allows OCore to generate a 405 response instead of a 404.
     *
     * @return void
     */
    public function testProcessKeepsUrlMatchForUnsupportedMethod(): void {
        ORoute::get(
            '/user',
            'App\\GetUserComponent'
        );

        ORoute::post(
            '/user',
            'App\\SaveUserComponent'
        );

        $url = new OUrl(
            'PATCH'
        );

        $result = $url->process(
            '/user'
        );

        self::assertTrue(
            $result['res']
        );

        self::assertSame(
            'PATCH',
            $result['method']
        );

        self::assertSame(
            'GET',
            $result['component_method']
        );
    }

    /**
     * Test that OPTIONS can resolve an existing route without an OPTIONS route.
     *
     * @return void
     */
    public function testOptionsCanResolveExistingUrl(): void {
        ORoute::get(
            '/health',
            'App\\HealthComponent'
        );

        $url = new OUrl(
            'OPTIONS'
        );

        $result = $url->process(
            '/health'
        );

        self::assertTrue(
            $result['res']
        );

        self::assertSame(
            'OPTIONS',
            $result['method']
        );

        self::assertSame(
            'GET',
            $result['component_method']
        );
    }

    /**
     * Test that an unknown URL is not considered a route match.
     *
     * @return void
     */
    public function testUnknownUrlReturnsNoMatch(): void {
        ORoute::get(
            '/health',
            'App\\HealthComponent'
        );

        $url = new OUrl(
            'GET'
        );

        $result = $url->process(
            '/missing'
        );

        self::assertFalse(
            $result['res']
        );

        self::assertSame(
            [
                'before' => [],
                'afterRender' => [],
                'afterResponse' => []
            ],
            $result['middlewares']
        );
    }

    /**
     * Test fully-qualified component lookup and URL parameter encoding.
     *
     * @return void
     */
    public function testGenerateUrlUsesExactFullyQualifiedComponent(): void {
        ORoute::get(
            '/admin/user',
            'App\\Admin\\UserComponent'
        );

        ORoute::get(
            '/api/user/:id/:slug',
            'App\\Api\\UserComponent'
        );

        $url = OUrl::generateUrl(
            'App\\Api\\UserComponent',
            [
                'id' => 25,
                'slug' => 'hello world'
            ]
        );

        self::assertSame(
            '/api/user/25/hello%20world',
            $url
        );
    }

    /**
     * Test that short component names remain supported.
     *
     * @return void
     */
    public function testGenerateUrlSupportsShortComponentName(): void {
        ORoute::get(
            '/user',
            'App\\Api\\UserComponent'
        );

        self::assertSame(
            '/user',
            OUrl::generateUrl(
                'UserComponent'
            )
        );
    }

    /**
     * Test absolute URL generation when the base URL has no trailing slash.
     *
     * @return void
     */
    public function testGenerateAbsoluteUrlDoesNotTrimBaseUrl(): void {
        ORoute::get(
            '/health',
            'App\\HealthComponent'
        );

        $GLOBALS['core'] = new OUrlTestCore(
            'https://example.com'
        );

        self::assertSame(
            'https://example.com/health',
            OUrl::generateUrl(
                'App\\HealthComponent',
                [],
                true
            )
        );
    }

    /**
     * Test that complex route parameter values are rejected.
     *
     * @return void
     */
    public function testGenerateUrlRejectsComplexParameterValues(): void {
        ORoute::get(
            '/user/:id',
            'App\\UserComponent'
        );

        $this->expectException(
            \InvalidArgumentException::class
        );

        OUrl::generateUrl(
            'App\\UserComponent',
            [
                'id' => [
                    'invalid'
                ]
            ]
        );
    }
}
