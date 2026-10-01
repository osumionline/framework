<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\Migrations\Util;

use Osumi\OsumiFramework\Migrations\Util\LegacyFilterScanner;
use Osumi\OsumiFramework\Tests\Support\TemporaryProject;
use PHPUnit\Framework\TestCase;

final class LegacyFilterScannerTest extends TestCase {
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
     * Test that a conventional Filter is mapped to its Middleware adapter.
     *
     * @return void
     */
    public function testLegacyFilterIsDiscovered(): void {
        $this->writeFilter(
            'src/Filter/LoginFilter.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Filter;

class LoginFilter {
	public static function handle(array $params, array $headers): array {
		return ['status' => 'ok'];
	}
}
PHP
        );

        $scanner = new LegacyFilterScanner();

        $definitions = $scanner->discover(
            $this->project->getBasePath()
        );

        self::assertCount(
            1,
            $definitions
        );

        $definition = $definitions[0];

        self::assertSame(
            'Osumi\\OsumiFramework\\App\\Filter\\LoginFilter',
            $definition->filter_fqcn
        );

        self::assertSame(
            'src/Middleware/LoginMiddleware.php',
            $definition->middleware_relative_path
        );

        self::assertSame(
            'Osumi\\OsumiFramework\\App\\Middleware\\LoginMiddleware',
            $definition->middleware_fqcn
        );
    }

    /**
     * Test that nested Filter directories preserve their namespace structure.
     *
     * @return void
     */
    public function testNestedLegacyFilterIsDiscovered(): void {
        $this->writeFilter(
            'src/Filter/Admin/LoginFilter.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Filter\Admin;

class LoginFilter {
	public static function handle(array $params, array $headers): array {
		return ['status' => 'ok'];
	}
}
PHP
        );

        $scanner = new LegacyFilterScanner();

        $definitions = $scanner->discover(
            $this->project->getBasePath()
        );

        self::assertSame(
            'src/Middleware/Admin/LoginMiddleware.php',
            $definitions[0]->middleware_relative_path
        );

        self::assertSame(
            'Osumi\\OsumiFramework\\App\\Middleware\\Admin\\LoginMiddleware',
            $definitions[0]->middleware_fqcn
        );
    }

    /**
     * Test that a namespace inconsistent with the Filter path is rejected.
     *
     * @return void
     */
    public function testInvalidFilterNamespaceIsRejected(): void {
        $this->writeFilter(
            'src/Filter/LoginFilter.php',
            <<<'PHP'
<?php

namespace Example;

class LoginFilter {
}
PHP
        );

        $scanner = new LegacyFilterScanner();

        $this->expectException(
            \RuntimeException::class
        );

        $scanner->discover(
            $this->project->getBasePath()
        );
    }

    /**
     * Test that projects without legacy Filters return an empty definition list.
     *
     * @return void
     */
    public function testProjectWithoutFiltersReturnsEmptyList(): void {
        $scanner = new LegacyFilterScanner();

        self::assertSame(
            [],
            $scanner->discover(
                $this->project->getBasePath()
            )
        );
    }

    /**
     * Write a temporary legacy Filter source file.
     *
     * @param string $relative_path Project-relative path.
     * @param string $content PHP source.
     *
     * @return void
     */
    private function writeFilter(
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
                "Could not create temporary Filter directory '{$directory}'."
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
                "Could not create temporary Filter '{$path}'."
            );
        }
    }
}
