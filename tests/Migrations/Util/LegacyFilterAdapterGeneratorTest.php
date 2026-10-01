<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\Migrations\Util;

use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\Migrations\Util\LegacyFilterAdapterGenerator;
use Osumi\OsumiFramework\Migrations\Util\LegacyFilterScanner;
use Osumi\OsumiFramework\Tests\Support\TemporaryProject;
use PHPUnit\Framework\TestCase;

final class LegacyFilterAdapterGeneratorTest extends TestCase {
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
     * Test that generated middleware preserves successful context and legacy failures.
     *
     * @return void
     */
    public function testGeneratedAdapterPreservesLegacyFilterSemantics(): void {
        $filter_path = $this->project->getPath(
            'src/Filter/MigrationAdapterTestFilter.php'
        );

        $filter_directory = dirname(
            $filter_path
        );

        if (
            !mkdir(
                $filter_directory,
                0755,
                true
            ) &&
            !is_dir($filter_directory)
        ) {
            self::fail(
                'Could not create temporary Filter directory.'
            );
        }

        $filter_source = <<<'PHP'
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Filter;

class MigrationAdapterTestFilter {
	public function handle(array $params, array $headers): array {
		if (($params['redirect'] ?? false) === true) {
			return [
				'status' => 'error',
				'return' => '/login'
			];
		}

		if (($params['fail'] ?? false) === true) {
			return [
				'status' => 'error'
			];
		}

		return [
			'status' => 'ok',
			'id' => 25
		];
	}
}
PHP;

        if (
            file_put_contents(
                $filter_path,
                $filter_source,
                LOCK_EX
            ) === false
        ) {
            self::fail(
                'Could not create temporary legacy Filter.'
            );
        }

        $scanner = new LegacyFilterScanner();

        $definitions = $scanner->discover(
            $this->project->getBasePath()
        );

        self::assertCount(
            1,
            $definitions
        );

        $definition = $definitions[0];

        $generator = new LegacyFilterAdapterGenerator();

        $middleware_source = $generator->generate(
            $definition
        );

        $middleware_path = $this->project->getPath(
            $definition->middleware_relative_path
        );

        $middleware_directory = dirname(
            $middleware_path
        );

        if (
            !mkdir(
                $middleware_directory,
                0755,
                true
            ) &&
            !is_dir($middleware_directory)
        ) {
            self::fail(
                'Could not create temporary Middleware directory.'
            );
        }

        if (
            file_put_contents(
                $middleware_path,
                $middleware_source,
                LOCK_EX
            ) === false
        ) {
            self::fail(
                'Could not create generated Middleware.'
            );
        }

        require_once $filter_path;
        require_once $middleware_path;

        $middleware_class = $definition->middleware_fqcn;

        $success = $middleware_class::handle(
            OMiddleware::PHASE_BEFORE,
            [
                'params' => [],
                'headers' => []
            ]
        );

        self::assertSame(
            [
                'context' => [
                    'status' => 'ok',
                    'id' => 25
                ]
            ],
            $success
        );

        $failure = $middleware_class::handle(
            OMiddleware::PHASE_BEFORE,
            [
                'params' => [
                    'fail' => true
                ],
                'headers' => []
            ]
        );

        self::assertSame(
            [
                'stop' => true,
                'status_code' => 403,
                'message' => ''
            ],
            $failure
        );

        $redirect = $middleware_class::handle(
            OMiddleware::PHASE_BEFORE,
            [
                'params' => [
                    'redirect' => true
                ],
                'headers' => []
            ]
        );

        self::assertSame(
            302,
            $redirect['status_code']
        );

        self::assertSame(
            '/login',
            $redirect['headers']['Location']
        );

        self::assertSame(
            [],
            $middleware_class::handle(
                OMiddleware::PHASE_AFTER_RENDER,
                []
            )
        );
    }
}
