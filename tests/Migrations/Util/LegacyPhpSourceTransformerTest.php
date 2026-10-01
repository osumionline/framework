<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\Migrations\Util;

use Osumi\OsumiFramework\Migrations\Util\LegacyPhpSourceTransformer;
use PHPUnit\Framework\TestCase;

final class LegacyPhpSourceTransformerTest extends TestCase {
    /**
     * Test that DTO named arguments and ORequest access are migrated.
     *
     * @return void
     */
    public function testDtoAndRequestUsagesAreMigrated(): void {
        $source = <<<'PHP'
<?php

use Osumi\OsumiFramework\DTO\ODTOField as Field;

class ExampleDTO {
    #[Field(required: true, filter: 'Login', filterProperty: 'id')]
    public ?int $id = null;

    public function read(object $request): array {
        return $request->getFilter('Login');
    }
}
PHP;

        $transformer = new LegacyPhpSourceTransformer();

        $migrated = $transformer->transform(
            'src/DTO/ExampleDTO.php',
            $source
        );

        self::assertStringContainsString(
            "middleware: 'Login'",
            $migrated
        );
        self::assertStringContainsString(
            "middlewareProperty: 'id'",
            $migrated
        );
        self::assertStringContainsString(
            "getMiddleware('Login')",
            $migrated
        );
    }

    /**
     * Test that unrelated named arguments are not modified.
     *
     * @return void
     */
    public function testUnrelatedNamedArgumentsAreNotModified(): void {
        $source = <<<'PHP'
<?php

example(filter: 'value', filterProperty: 'other');
PHP;

        $transformer = new LegacyPhpSourceTransformer();

        self::assertSame(
            $source,
            $transformer->transform(
                'src/Service/Example.php',
                $source
            )
        );
    }

    /**
     * Test that the transformed source is idempotent.
     *
     * @return void
     */
    public function testTransformationIsIdempotent(): void {
        $source = <<<'PHP'
<?php

#[ODTOField(filter: 'Login', filterProperty: 'id')]
class Example {
    public function run(object $request): void {
        $request->getFilter('Login');
    }
}
PHP;

        $transformer = new LegacyPhpSourceTransformer();

        $first = $transformer->transform(
            'src/Example.php',
            $source
        );

        $second = $transformer->transform(
            'src/Example.php',
            $first
        );

        self::assertSame(
            $first,
            $second
        );
    }

    /**
     * Test that unsupported ORequest collection APIs abort migration.
     *
     * @return void
     */
    public function testUnsupportedRequestMethodIsRejected(): void {
        $source = <<<'PHP'
<?php

$request->getFilters();
PHP;

        $transformer = new LegacyPhpSourceTransformer();

        $this->expectException(
            \RuntimeException::class
        );
        $this->expectExceptionMessage(
            "src/Component/Example.php:3"
        );

        $transformer->transform(
            'src/Component/Example.php',
            $source
        );
    }
}
