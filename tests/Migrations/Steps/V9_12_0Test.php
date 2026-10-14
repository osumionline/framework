<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\Migrations\Steps;

use Osumi\OsumiFramework\Migrations\Runner;
use Osumi\OsumiFramework\Migrations\State\StateStore;
use Osumi\OsumiFramework\Tests\Support\TemporaryProject;
use PHPUnit\Framework\TestCase;

final class V9_12_0Test extends TestCase {
    private TemporaryProject $project;

    /**
     * Prepare an isolated OFW project.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();

        $this->project = new TemporaryProject();
    }

    /**
     * Remove temporary project files.
     *
     * @return void
     */
    protected function tearDown(): void {
        $this->project->remove();

        parent::tearDown();
    }

    /**
     * Test migration of legacy DATE model fields.
     *
     * @return void
     */
    public function testLegacyDateFieldsBecomeDateTimeFields(): void {
        $this->writeProjectFile(
            'src/Model/Event.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Model;

use Osumi\OsumiFramework\ORM\OField;

class Event {
    #[OField(
        type: OField::DATE
    )]
    public ?string $starts_at = null;

    #[OField(
        type: OField::DATE
    )]
    public ?string $ends_at = null;
}

PHP
        );

        Runner::run(
            $this->project->getBasePath(),
            '9.11.0',
            '9.12.0'
        );

        $content = $this->readProjectFile(
            'src/Model/Event.php'
        );

        self::assertSame(
            0,
            preg_match(
                '/type:\s*OField::DATE\b/',
                $content
            )
        );

        self::assertSame(
            2,
            substr_count(
                $content,
                'type: OField::DATETIME'
            )
        );

        $state = new StateStore(
            $this->project->getBasePath(),
            $this->project->getPath(
                'ofw/tmp'
            )
        );

        self::assertSame(
            '9.12.0',
            $state->readLastMigrated()
        );
    }

    /**
     * Test that comments and strings containing OField::DATE are preserved.
     *
     * @return void
     */
    public function testCommentsAndStringsAreNotModified(): void {
        $this->writeProjectFile(
            'src/Model/Event.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Model;

use Osumi\OsumiFramework\ORM\OField;

class Event {
    // Legacy documentation mentions OField::DATE.

    public const string DESCRIPTION = 'Use OField::DATE here';

    #[OField(
        type: OField::DATE
    )]
    public ?string $starts_at = null;
}

PHP
        );

        Runner::run(
            $this->project->getBasePath(),
            '9.11.0',
            '9.12.0'
        );

        $content = $this->readProjectFile(
            'src/Model/Event.php'
        );

        self::assertStringContainsString(
            '// Legacy documentation mentions OField::DATE.',
            $content
        );

        self::assertStringContainsString(
            "'Use OField::DATE here'",
            $content
        );

        self::assertStringContainsString(
            'type: OField::DATETIME',
            $content
        );
    }

    /**
     * Test that files outside the model directory are not modified.
     *
     * @return void
     */
    public function testNonModelFilesAreNotModified(): void {
        $original = <<<'PHP'
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Service;

class ExampleService {
    public const string EXAMPLE = 'OField::DATE';
}

PHP;

        $this->writeProjectFile(
            'src/Service/ExampleService.php',
            $original
        );

        Runner::run(
            $this->project->getBasePath(),
            '9.11.0',
            '9.12.0'
        );

        self::assertSame(
            $original,
            $this->readProjectFile(
                'src/Service/ExampleService.php'
            )
        );
    }

    /**
     * Test that running the migration again does not alter migrated models.
     *
     * @return void
     */
    public function testMigrationIsIdempotent(): void {
        $this->writeProjectFile(
            'src/Model/Event.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Model;

use Osumi\OsumiFramework\ORM\OField;

class Event {
    #[OField(
        type: OField::DATE
    )]
    public ?string $starts_at = null;
}

PHP
        );

        Runner::run(
            $this->project->getBasePath(),
            '9.11.0',
            '9.12.0'
        );

        $first_result = $this->readProjectFile(
            'src/Model/Event.php'
        );

        Runner::run(
            $this->project->getBasePath(),
            '9.11.0',
            '9.12.0'
        );

        self::assertSame(
            $first_result,
            $this->readProjectFile(
                'src/Model/Event.php'
            )
        );
    }

    /**
     * Test that genuine DATE fields created after migration remain DATE fields.
     *
     * @return void
     */
    public function testNewDateFieldsAreNotRemigrated(): void {
        $this->writeProjectFile(
            'src/Model/LegacyEvent.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Model;

use Osumi\OsumiFramework\ORM\OField;

class LegacyEvent {
    #[OField(
        type: OField::DATE
    )]
    public ?string $processed_at = null;
}

PHP
        );

        Runner::run(
            $this->project->getBasePath(),
            '9.11.0',
            '9.12.0'
        );

        /*
     * This model represents code written after the project has already
     * migrated to 9.12. Its DATE field now has real SQL DATE semantics.
     */
        $this->writeProjectFile(
            'src/Model/NewEvent.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Model;

use Osumi\OsumiFramework\ORM\OField;

class NewEvent {
    #[OField(
        type: OField::DATE
    )]
    public ?string $event_date = null;
}

PHP
        );

        Runner::runPending(
            $this->project->getBasePath(),
            '9.12.0'
        );

        $content = $this->readProjectFile(
            'src/Model/NewEvent.php'
        );

        self::assertStringContainsString(
            'type: OField::DATE',
            $content
        );

        self::assertStringNotContainsString(
            'type: OField::DATETIME',
            $content
        );
    }

    /**
     * Read a temporary project file.
     *
     * @param string $relative_path Project-relative file path.
     *
     * @return string File contents.
     */
    private function readProjectFile(
        string $relative_path
    ): string {
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
