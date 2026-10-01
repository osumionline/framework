<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\Migrations\Steps;

use Osumi\OsumiFramework\Migrations\Runner;
use Osumi\OsumiFramework\Migrations\State\StateStore;
use Osumi\OsumiFramework\Tests\Support\TemporaryProject;
use PHPUnit\Framework\TestCase;

final class V9_9_0Test extends TestCase {
    private TemporaryProject $project;

    /**
     * Prepare an isolated legacy OFW project.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();

        $this->project = new TemporaryProject();
    }

    /**
     * Remove the temporary project.
     *
     * @return void
     */
    protected function tearDown(): void {
        $this->project->remove();

        parent::tearDown();
    }

    /**
     * Test a complete 9.8.5 to 9.9.0 Filter migration.
     *
     * @return void
     */
    public function testCompleteFilterMigration(): void {
        $this->createLegacyProject();

        $original_filter = file_get_contents(
            $this->project->getPath(
                'src/Filter/LoginFilter.php'
            )
        );

        Runner::run(
            $this->project->getBasePath(),
            '9.8.5',
            '9.9.0'
        );

        $middleware_path = $this->project->getPath(
            'src/Middleware/LoginMiddleware.php'
        );

        self::assertFileExists(
            $middleware_path
        );

        $middleware = file_get_contents(
            $middleware_path
        );

        self::assertIsString(
            $middleware
        );

        self::assertStringContainsString(
            'class LoginMiddleware',
            $middleware
        );

        self::assertStringContainsString(
            'use Osumi\\OsumiFramework\\App\\Filter\\LoginFilter;',
            $middleware
        );

        self::assertFileExists(
            $this->project->getPath(
                'src/Middleware/Middlewares.php'
            )
        );

        $route = $this->readProjectFile(
            'src/Routes/Api.php'
        );

        self::assertStringContainsString(
            "'before'",
            $route
        );

        self::assertStringContainsString(
            'Osumi\\OsumiFramework\\App\\Middleware\\LoginMiddleware',
            $route
        );

        $dto = $this->readProjectFile(
            'src/DTO/UserDTO.php'
        );

        self::assertStringContainsString(
            "middleware: 'Login'",
            $dto
        );

        self::assertStringContainsString(
            "middlewareProperty: 'id'",
            $dto
        );

        self::assertStringNotContainsString(
            'filterProperty:',
            $dto
        );

        $component = $this->readProjectFile(
            'src/Component/ProfileComponent.php'
        );

        self::assertStringContainsString(
            "getMiddleware('Login')",
            $component
        );

        self::assertSame(
            $original_filter,
            file_get_contents(
                $this->project->getPath(
                    'src/Filter/LoginFilter.php'
                )
            )
        );

        $state = new StateStore(
            $this->project->getPath(
                'ofw/tmp'
            )
        );

        self::assertSame(
            '9.9.0',
            $state->readLastMigrated()
        );
    }

    /**
     * Test that the complete migration is idempotent when state is lost.
     *
     * @return void
     */
    public function testMigrationIsIdempotentWhenStateFileIsMissing(): void {
        $this->createLegacyProject();

        Runner::run(
            $this->project->getBasePath(),
            '9.8.5',
            '9.9.0'
        );

        $route_before = $this->readProjectFile(
            'src/Routes/Api.php'
        );

        $middleware_before = $this->readProjectFile(
            'src/Middleware/LoginMiddleware.php'
        );

        $state = new StateStore(
            $this->project->getPath(
                'ofw/tmp'
            )
        );

        if (!unlink(
            $state->getStateFile()
        )) {
            self::fail(
                'Could not remove migration state fixture.'
            );
        }

        Runner::run(
            $this->project->getBasePath(),
            '0.0.0',
            '9.9.0'
        );

        self::assertSame(
            $route_before,
            $this->readProjectFile(
                'src/Routes/Api.php'
            )
        );

        self::assertSame(
            $middleware_before,
            $this->readProjectFile(
                'src/Middleware/LoginMiddleware.php'
            )
        );

        self::assertSame(
            '9.9.0',
            $state->readLastMigrated()
        );
    }

    /**
     * Test that an existing different Middleware prevents any project modification.
     *
     * @return void
     */
    public function testMiddlewareCollisionAbortsBeforeWriting(): void {
        $this->createLegacyProject();

        $this->writeProjectFile(
            'src/Middleware/LoginMiddleware.php',
            "<?php\n\n// Existing custom middleware.\n"
        );

        $route_before = $this->readProjectFile(
            'src/Routes/Api.php'
        );

        $custom_middleware = $this->readProjectFile(
            'src/Middleware/LoginMiddleware.php'
        );

        try {
            Runner::run(
                $this->project->getBasePath(),
                '9.8.5',
                '9.9.0'
            );

            self::fail(
                'Middleware collision did not abort the migration.'
            );
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString(
                'already exists with different contents',
                $exception->getMessage()
            );
        }

        self::assertSame(
            $route_before,
            $this->readProjectFile(
                'src/Routes/Api.php'
            )
        );

        self::assertSame(
            $custom_middleware,
            $this->readProjectFile(
                'src/Middleware/LoginMiddleware.php'
            )
        );

        self::assertFileDoesNotExist(
            $this->project->getPath(
                'src/Middleware/Middlewares.php'
            )
        );

        $state = new StateStore(
            $this->project->getPath(
                'ofw/tmp'
            )
        );

        self::assertNull(
            $state->readLastMigrated()
        );
    }

    /**
     * Test that unsupported dynamic route Filters abort during preflight.
     *
     * @return void
     */
    public function testDynamicRouteAbortsBeforeAdaptersAreCreated(): void {
        $this->createLegacyFilter();

        $this->writeProjectFile(
            'src/Routes/Api.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Routes;

use Osumi\OsumiFramework\App\Filter\LoginFilter;
use Osumi\OsumiFramework\Routing\ORoute;

$filters = [
	LoginFilter::class
];

ORoute::get(
	'/profile',
	ProfileComponent::class,
	$filters
);
PHP
        );

        $this->expectException(
            \RuntimeException::class
        );

        try {
            Runner::run(
                $this->project->getBasePath(),
                '9.8.5',
                '9.9.0'
            );
        } finally {
            self::assertFileDoesNotExist(
                $this->project->getPath(
                    'src/Middleware/LoginMiddleware.php'
                )
            );

            self::assertFileDoesNotExist(
                $this->project->getPath(
                    'src/Middleware/Middlewares.php'
                )
            );
        }
    }

    /**
     * Test that dry-run analyzes the full migration without changing the project.
     *
     * @return void
     */
    public function testDryRunDoesNotModifyLegacyProject(): void {
        $this->createLegacyProject();

        $route_before = $this->readProjectFile(
            'src/Routes/Api.php'
        );

        Runner::run(
            $this->project->getBasePath(),
            '9.8.5',
            '9.9.0',
            [
                'dryRun' => true
            ]
        );

        self::assertSame(
            $route_before,
            $this->readProjectFile(
                'src/Routes/Api.php'
            )
        );

        self::assertFileDoesNotExist(
            $this->project->getPath(
                'src/Middleware/LoginMiddleware.php'
            )
        );

        self::assertFileDoesNotExist(
            $this->project->getPath(
                'src/Middleware/Middlewares.php'
            )
        );

        $state = new StateStore(
            $this->project->getPath(
                'ofw/tmp'
            )
        );

        self::assertNull(
            $state->readLastMigrated()
        );
    }

    /**
     * Create a representative OFW 9.8.5 project.
     *
     * @return void
     */
    private function createLegacyProject(): void {
        $this->createLegacyFilter();

        $this->writeProjectFile(
            'src/Routes/Api.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Routes;

use Osumi\OsumiFramework\App\Filter\LoginFilter;
use Osumi\OsumiFramework\App\Module\ProfileComponent;
use Osumi\OsumiFramework\Routing\ORoute;

ORoute::get(
	'/profile',
	ProfileComponent::class,
	[
		LoginFilter::class
	]
);
PHP
        );

        $this->writeProjectFile(
            'src/DTO/UserDTO.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\DTO;

use Osumi\OsumiFramework\DTO\ODTO;
use Osumi\OsumiFramework\DTO\ODTOField;

class UserDTO extends ODTO {
	#[ODTOField(
		required: true,
		filter: 'Login',
		filterProperty: 'id'
	)]
	public ?int $id = null;
}
PHP
        );

        $this->writeProjectFile(
            'src/Component/ProfileComponent.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Component;

use Osumi\OsumiFramework\Web\ORequest;

class ProfileComponent {
	public function run(ORequest $req): void {
		$login = $req->getFilter('Login');
	}
}
PHP
        );
    }

    /**
     * Create the representative legacy LoginFilter.
     *
     * @return void
     */
    private function createLegacyFilter(): void {
        $this->writeProjectFile(
            'src/Filter/LoginFilter.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Filter;

class LoginFilter {
	/**
	 * Handle authentication.
	 *
	 * @param array<string, mixed> $params Request parameters.
	 * @param array<string, string> $headers Request headers.
	 *
	 * @return array<string, mixed> Filter result.
	 */
	public static function handle(
		array $params,
		array $headers
	): array {
		return [
			'status' => 'ok',
			'id' => 25
		];
	}
}
PHP
        );
    }

    /**
     * Read a temporary project file.
     *
     * @param string $relative_path Project-relative file path.
     *
     * @return string File contents.
     */
    private function readProjectFile(string $relative_path): string {
        $content = file_get_contents(
            $this->project->getPath(
                $relative_path
            )
        );

        if ($content === false) {
            self::fail(
                "Could not read temporary project file '{$relative_path}'."
            );
        }

        return $content;
    }

    /**
     * Write a temporary project file.
     *
     * @param string $relative_path Project-relative file path.
     * @param string $content File contents.
     *
     * @return void
     */
    private function writeProjectFile(
        string $relative_path,
        string $content
    ): void {
        $path = $this->project->getPath(
            $relative_path
        );

        $directory = dirname(
            $path
        );

        if (
            !is_dir($directory) &&
            !mkdir(
                $directory,
                0755,
                true
            ) &&
            !is_dir($directory)
        ) {
            self::fail(
                "Could not create temporary directory '{$directory}'."
            );
        }

        if (
            file_put_contents(
                $path,
                $content,
                LOCK_EX
            ) === false
        ) {
            self::fail(
                "Could not write temporary project file '{$relative_path}'."
            );
        }
    }
}
