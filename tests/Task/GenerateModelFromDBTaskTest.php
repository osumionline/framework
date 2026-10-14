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
     * @param string|null $field_type ORM field type when relevant.
     *
     * @return string|int|float|bool|null Parsed default.
     */
    private function parseColumnDefault(
        string $field_name,
        string $attribute_type,
        mixed $column_default,
        ?string $field_type = null
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
            $column_default,
            $field_type
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

        $this->expectExceptionMessageIsOrContains(
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

    /**
     * Test that DECIMAL defaults are preserved as exact strings.
     *
     * @return void
     */
    public function testDecimalDefaultIsPreservedAsString(): void {
        self::assertSame(
            '12.50',
            $this->parseColumnDefault(
                'amount',
                'string',
                '12.50',
                'OField::DECIMAL'
            )
        );

        self::assertSame(
            '-0.10',
            $this->parseColumnDefault(
                'amount',
                'string',
                '-0.10',
                'OField::DECIMAL'
            )
        );
    }

    /**
     * Test that DECIMAL defaults cannot use scientific notation.
     *
     * @return void
     */
    public function testDecimalDefaultRejectsScientificNotation(): void {
        $this->expectException(
            \RuntimeException::class
        );

        $this->parseColumnDefault(
            'amount',
            'string',
            '1e3',
            'OField::DECIMAL'
        );
    }

    /**
     * Test SQL types introduced or redefined in OFW 9.12.
     *
     * @return void
     */
    public function testDateDateTimeAndBigIntAreMapped(): void {
        self::assertSame(
            [
                'decorator' => 'OField',
                'type' => 'OField::DATE',
                'attribute_type' => 'string'
            ],
            $this->getColumnTypeDefinition(
                'date',
                'date'
            )
        );

        self::assertSame(
            [
                'decorator' => 'OField',
                'type' => 'OField::DATETIME',
                'attribute_type' => 'string'
            ],
            $this->getColumnTypeDefinition(
                'datetime',
                'datetime'
            )
        );

        self::assertSame(
            [
                'decorator' => 'OField',
                'type' => 'OField::BIGINT',
                'attribute_type' => 'int'
            ],
            $this->getColumnTypeDefinition(
                'bigint',
                'bigint(20)'
            )
        );
    }

    /**
     * Test that unsigned BIGINT columns remain unsupported.
     *
     * @return void
     */
    public function testUnsignedBigIntIsRejected(): void {
        try {
            $this->getColumnTypeDefinition(
                'bigint',
                'bigint(20) unsigned'
            );

            self::fail(
                'Unsigned BIGINT was expected to be rejected.'
            );
        } catch (\RuntimeException $e) {
            self::assertSame(
                "Database column 'field' uses unsupported numeric modifiers 'bigint(20) unsigned'.",
                $e->getMessage()
            );
        }
    }

    /**
     * Invoke the pure SQL type mapper.
     *
     * @param string $data_type SQL DATA_TYPE.
     * @param string $column_type SQL COLUMN_TYPE.
     * @param int|null $character_maximum_length Character maximum length.
     * @param int|null $numeric_precision Numeric precision.
     * @param int|null $numeric_scale Numeric scale.
     *
     * @return array<string, string|int>
     */
    private function getColumnTypeDefinition(
        string $data_type,
        string $column_type,
        ?int $character_maximum_length = null,
        ?int $numeric_precision = null,
        ?int $numeric_scale = null
    ): array {
        $task = new GenerateModelFromDBTask();

        $method = new ReflectionMethod(
            $task,
            'getColumnTypeDefinition'
        );

        $result = $method->invoke(
            $task,
            'field',
            $data_type,
            $column_type,
            $character_maximum_length,
            $numeric_precision,
            $numeric_scale
        );

        if (!is_array($result)) {
            self::fail(
                'Column type mapper did not return an array.'
            );
        }

        /** @var array<string, string|int> $result */
        return $result;
    }
}
