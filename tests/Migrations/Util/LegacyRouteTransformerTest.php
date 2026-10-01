<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\Migrations\Util;

use Osumi\OsumiFramework\Migrations\Util\LegacyRouteTransformer;
use Osumi\OsumiFramework\Migrations\ValueObject\LegacyFilterDefinition;
use PHPUnit\Framework\TestCase;

final class LegacyRouteTransformerTest extends TestCase {
    /**
     * Test that a positional Filter list is migrated to before middlewares.
     *
     * @return void
     */
    public function testPositionalFilterListIsMigrated(): void {
        $source = <<<'PHP'
<?php

use Osumi\OsumiFramework\Routing\ORoute;
use Osumi\OsumiFramework\App\Filter\LoginFilter;

ORoute::get('/profile', ProfileComponent::class, [LoginFilter::class]);
PHP;

        $transformer = $this->createTransformer();

        $migrated = $transformer->transform(
            'src/Routes/Api.php',
            $source
        );

        self::assertStringContainsString(
            "['before' => [\\Osumi\\OsumiFramework\\App\\Middleware\\LoginMiddleware::class]]",
            $migrated
        );
    }

    /**
     * Test that aliases and named Filter arguments are migrated.
     *
     * @return void
     */
    public function testAliasAndNamedFilterArgumentAreMigrated(): void {
        $source = <<<'PHP'
<?php

use Osumi\OsumiFramework\Routing\ORoute as Route;
use Osumi\OsumiFramework\App\Filter\LoginFilter as Auth;

Route::post(
    url: '/profile',
    component: ProfileComponent::class,
    filters: [Auth::class]
);
PHP;

        $transformer = $this->createTransformer();

        $migrated = $transformer->transform(
            'src/Routes/Api.php',
            $source
        );

        self::assertStringContainsString(
            "middlewares: ['before' => [\\Osumi\\OsumiFramework\\App\\Middleware\\LoginMiddleware::class]]",
            $migrated
        );
    }

    /**
     * Test that addRoute migrates its fourth Filter argument.
     *
     * @return void
     */
    public function testAddRouteFilterListIsMigrated(): void {
        $source = <<<'PHP'
<?php

use Osumi\OsumiFramework\Routing\ORoute;
use Osumi\OsumiFramework\App\Filter\LoginFilter;

ORoute::addRoute(
    'GET',
    '/profile',
    ProfileComponent::class,
    [LoginFilter::class],
    null,
    false
);
PHP;

        $transformer = $this->createTransformer();

        $migrated = $transformer->transform(
            'src/Routes/Api.php',
            $source
        );

        self::assertStringContainsString(
            "['before' => [\\Osumi\\OsumiFramework\\App\\Middleware\\LoginMiddleware::class]]",
            $migrated
        );
    }

    /**
     * Test that already migrated route definitions remain unchanged.
     *
     * @return void
     */
    public function testMigratedRouteIsIdempotent(): void {
        $source = <<<'PHP'
<?php

use Osumi\OsumiFramework\Routing\ORoute;

ORoute::get(
    '/profile',
    ProfileComponent::class,
    ['before' => [LoginMiddleware::class]]
);
PHP;

        $transformer = $this->createTransformer();

        self::assertSame(
            $source,
            $transformer->transform(
                'src/Routes/Api.php',
                $source
            )
        );
    }

    /**
     * Test that an empty legacy Filter list remains a valid empty middleware definition.
     *
     * @return void
     */
    public function testEmptyFilterListIsLeftUnchanged(): void {
        $source = <<<'PHP'
<?php

use Osumi\OsumiFramework\Routing\ORoute;

ORoute::get('/profile', ProfileComponent::class, []);
PHP;

        $transformer = $this->createTransformer();

        self::assertSame(
            $source,
            $transformer->transform(
                'src/Routes/Api.php',
                $source
            )
        );
    }

    /**
     * Test that dynamic legacy Filter definitions abort automatic migration.
     *
     * @return void
     */
    public function testDynamicFilterExpressionIsRejected(): void {
        $source = <<<'PHP'
<?php

use Osumi\OsumiFramework\Routing\ORoute;

ORoute::get('/profile', ProfileComponent::class, $filters);
PHP;

        $transformer = $this->createTransformer();

        $this->expectException(
            \RuntimeException::class
        );
        $this->expectExceptionMessage(
            'Only literal Filter class arrays can be migrated automatically.'
        );

        $transformer->transform(
            'src/Routes/Api.php',
            $source
        );
    }

    /**
     * Test that unknown Filter classes abort automatic migration.
     *
     * @return void
     */
    public function testUnknownFilterClassIsRejected(): void {
        $source = <<<'PHP'
<?php

use Osumi\OsumiFramework\Routing\ORoute;
use Example\UnknownFilter;

ORoute::get('/profile', ProfileComponent::class, [UnknownFilter::class]);
PHP;

        $transformer = $this->createTransformer();

        $this->expectException(
            \RuntimeException::class
        );
        $this->expectExceptionMessage(
            'no migratable Filter definition was found'
        );

        $transformer->transform(
            'src/Routes/Api.php',
            $source
        );
    }

    /**
     * Create a transformer with one legacy LoginFilter definition.
     *
     * @return LegacyRouteTransformer Configured route transformer.
     */
    private function createTransformer(): LegacyRouteTransformer {
        return new LegacyRouteTransformer([
            new LegacyFilterDefinition(
                filter_relative_path: 'src/Filter/LoginFilter.php',
                filter_namespace: 'Osumi\\OsumiFramework\\App\\Filter',
                filter_class: 'LoginFilter',
                filter_fqcn: 'Osumi\\OsumiFramework\\App\\Filter\\LoginFilter',
                middleware_relative_path: 'src/Middleware/LoginMiddleware.php',
                middleware_namespace: 'Osumi\\OsumiFramework\\App\\Middleware',
                middleware_class: 'LoginMiddleware',
                middleware_fqcn: 'Osumi\\OsumiFramework\\App\\Middleware\\LoginMiddleware'
            )
        ]);
    }
}
