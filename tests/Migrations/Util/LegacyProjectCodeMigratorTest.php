<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\Migrations\Util;

use Osumi\OsumiFramework\Migrations\Util\LegacyProjectCodeMigrator;
use Osumi\OsumiFramework\Migrations\ValueObject\LegacyFilterDefinition;
use Osumi\OsumiFramework\Tests\Support\TemporaryProject;
use PHPUnit\Framework\TestCase;

final class LegacyProjectCodeMigratorTest extends TestCase {
    private TemporaryProject $project;

    /**
     * Prepare an isolated legacy project.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();

        $this->project = new TemporaryProject();
    }

    /**
     * Remove the temporary project after every test.
     *
     * @return void
     */
    protected function tearDown(): void {
        $this->project->remove();

        parent::tearDown();
    }

    /**
     * Test that project changes are planned without modifying source files.
     *
     * @return void
     */
    public function testProjectChangesArePlannedWithoutWritingFiles(): void {
        $route_source = <<<'PHP'
<?php

use Osumi\OsumiFramework\Routing\ORoute;
use Osumi\OsumiFramework\App\Filter\LoginFilter;

ORoute::get('/profile', ProfileComponent::class, [LoginFilter::class]);
PHP;

        $dto_source = <<<'PHP'
<?php

#[ODTOField(filter: 'Login', filterProperty: 'id')]
class UserDTO {
}
PHP;

        $this->writeFile(
            'src/Routes/Api.php',
            $route_source
        );
        $this->writeFile(
            'src/DTO/UserDTO.php',
            $dto_source
        );
        $this->writeFile(
            'src/Filter/LoginFilter.php',
            '<?php class untouched {}'
        );
        $this->writeFile(
            'src/Middleware/ExistingMiddleware.php',
            '<?php class untouchedMiddleware {}'
        );

        $migrator = new LegacyProjectCodeMigrator([
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

        $changes = $migrator->plan(
            $this->project->getBasePath()
        );

        self::assertArrayHasKey(
            'src/Routes/Api.php',
            $changes
        );
        self::assertArrayHasKey(
            'src/DTO/UserDTO.php',
            $changes
        );
        self::assertArrayNotHasKey(
            'src/Filter/LoginFilter.php',
            $changes
        );
        self::assertArrayNotHasKey(
            'src/Middleware/ExistingMiddleware.php',
            $changes
        );

        self::assertSame(
            $route_source,
            file_get_contents(
                $this->project->getPath(
                    'src/Routes/Api.php'
                )
            )
        );
    }

    /**
     * Write a source file into the temporary project.
     *
     * @param string $relative_path Project-relative path.
     * @param string $content File contents.
     *
     * @return void
     */
    private function writeFile(
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
                "Could not create temporary source directory '{$directory}'."
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
                "Could not create temporary source file '{$path}'."
            );
        }
    }
}
