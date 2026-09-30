<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\Task;

use Osumi\OsumiFramework\Task\GenerateModelFromDBTask;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class GenerateModelFromDBTaskTest extends TestCase {
    /**
     * Invoke the pure COLUMN_DEFAULT parser.
     *
     * The complete task requires a MariaDB connection, but parsing defaults is
     * deterministic and can be tested independently.
     *
     * @param string $field_name Field name.
     * @param string $attribute_type PHP field type.
     * @param mixed $column_default Raw MariaDB COLUMN_DEFAULT value.
     *
     * @return string|int|float|bool|null Parsed default.
     */
    private function parseColumnDefault(
        string $field_name,
        string $attribute_type,
        mixed $column_default
    ): string|int|float|bool|null {
        $task = new GenerateModelFromDBTask();

        $method = new ReflectionMethod(
            $task,
            'parseColumnDefault'
        );

        /** @var string|int|float|bool|null $result */
        $result = $method->invoke(
            $task,
            $field_name,
            $attribute_type,
            $column_default
        );

        return $result;
    }

    /**
     * Test supported MariaDB default representations.
     *
     * @param string $attribute_type PHP property type.
     * @param mixed $column_default Raw MariaDB default.
     * @param string|int|float|bool|null $expected Expected PHP value.
     *
     * @return void
     */
    #[DataProvider('columnDefaultProvider')]
    public function testColumnDefaultsAreConverted(
        string $attribute_type,
        mixed $column_default,
        string|int|float|bool|null $expected
    ): void {
        self::assertSame(
            $expected,
            $this->parseColumnDefault(
                'field',
                $attribute_type,
                $column_default
            )
        );
    }

    /**
     * Provide valid COLUMN_DEFAULT values.
     *
     * @return array<string, array{
     *     string,
     *     mixed,
     *     string|int|float|bool|null
     * }>
     */
    public static function columnDefaultProvider(): array {
        return [
            'no default' => [
                'string',
                null,
                null
            ],
            'sql null' => [
                'string',
                'NULL',
                null
            ],
            'literal null string' => [
                'string',
                "'NULL'",
                'NULL'
            ],
            'string literal' => [
                'string',
                "'Hello'",
                'Hello'
            ],
            'escaped apostrophe' => [
                'string',
                "'O''Reilly'",
                "O'Reilly"
            ],
            'integer' => [
                'int',
                '42',
                42
            ],
            'negative integer' => [
                'int',
                '-42',
                -42
            ],
            'float' => [
                'float',
                '12.50',
                12.5
            ],
            'boolean true' => [
                'bool',
                '1',
                true
            ],
            'boolean false' => [
                'bool',
                '0',
                false
            ]
        ];
    }

    /**
     * Test that SQL expressions are not converted into string defaults.
     *
     * @return void
     */
    public function testSqlExpressionIsRejected(): void {
        $this->expectException(
            \RuntimeException::class
        );

        $this->expectExceptionMessage(
            "SQL default expression 'current_timestamp()'"
        );

        $this->parseColumnDefault(
            'created',
            'string',
            'current_timestamp()'
        );
    }

    /**
     * Test that invalid metadata types are rejected.
     *
     * @return void
     */
    public function testInvalidMetadataTypeIsRejected(): void {
        $this->expectException(
            \RuntimeException::class
        );

        $this->parseColumnDefault(
            'field',
            'int',
            42
        );
    }
}
