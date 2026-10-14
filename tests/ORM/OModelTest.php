<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\ORM;

use PDO;
use PDOStatement;
use Osumi\OsumiFramework\ORM\OField;
use Osumi\OsumiFramework\Tests\Fixtures\ORM\ExampleModel;
use Osumi\OsumiFramework\Tests\Fixtures\ORM\InvalidAutoIncrementModel;
use Osumi\OsumiFramework\Tests\Fixtures\ORM\TextKeyModel;
use Osumi\OsumiFramework\Tests\Fixtures\ORM\DecimalModel;
use Osumi\OsumiFramework\Tests\Fixtures\ORM\TemporalBigIntModel;
use PHPUnit\Framework\TestCase;

final class OModelTest extends TestCase {
    /**
     * Test normalization of values supplied to the constructor.
     *
     * @return void
     */
    public function testConstructorNormalizesValues(): void {
        $model = new ExampleModel(
            [
                'id' => '25',
                'name' => 'Test',
                'active' => 'true',
                'price' => '12.75',
                'category_id' => '4'
            ]
        );

        self::assertSame(
            25,
            $model->id
        );

        self::assertSame(
            'Test',
            $model->name
        );

        self::assertTrue(
            $model->active
        );

        self::assertSame(
            12.75,
            $model->price
        );

        self::assertSame(
            4,
            $model->category_id
        );
    }

    /**
     * Test that unknown input fields are rejected.
     *
     * @return void
     */
    public function testUnknownInputFieldIsRejected(): void {
        $this->expectException(
            \InvalidArgumentException::class
        );

        new ExampleModel(
            [
                'unknown_field' => 'value'
            ]
        );
    }

    /**
     * Test that create always produces a new record.
     *
     * @return void
     */
    public function testCreateAlwaysProducesNewRecord(): void {
        $model = ExampleModel::create(
            [
                'id' => 25,
                'name' => 'Test'
            ]
        );

        self::assertTrue(
            $model->isNewRecordForTest()
        );

        $this->expectException(
            \LogicException::class
        );

        $model->assertPersistedForTest();
    }

    /**
     * Test that from creates a persisted model.
     *
     * @return void
     */
    public function testFromCreatesPersistedRecord(): void {
        $model = ExampleModel::from(
            [
                'id' => '25',
                'name' => 'Test'
            ]
        );

        self::assertFalse(
            $model->isNewRecordForTest()
        );

        $model->assertPersistedForTest();

        self::assertSame(
            25,
            $model->id
        );
    }

    /**
     * Test that persisted models require a complete primary key.
     *
     * @return void
     */
    public function testFromRequiresPrimaryKey(): void {
        $this->expectException(
            \InvalidArgumentException::class
        );

        ExampleModel::from(
            [
                'name' => 'Test'
            ]
        );
    }

    /**
     * Test application of model defaults.
     *
     * @return void
     */
    public function testDefaultsAreAppliedToNewModel(): void {
        $model = ExampleModel::create();

        $model->applyDefaultsForTest();

        self::assertNull(
            $model->id
        );

        self::assertSame(
            "O'Reilly",
            $model->name
        );

        self::assertTrue(
            $model->active
        );

        self::assertSame(
            12.5,
            $model->price
        );

        /*
		 * A null auto-increment primary key is valid before INSERT.
		 */
        $model->validateForTest();
    }

    /**
     * Test that explicit values are not overwritten by defaults.
     *
     * @return void
     */
    public function testDefaultsDoNotOverwriteExplicitValues(): void {
        $model = ExampleModel::create(
            [
                'name' => 'Custom',
                'active' => false,
                'price' => 99.5
            ]
        );

        $model->applyDefaultsForTest();

        self::assertSame(
            'Custom',
            $model->name
        );

        self::assertFalse(
            $model->active
        );

        self::assertSame(
            99.5,
            $model->price
        );
    }

    /**
     * Test that manually assigned primary keys cannot be null.
     *
     * @return void
     */
    public function testManualPrimaryKeyCannotBeNull(): void {
        $model = TextKeyModel::create();

        $this->expectException(
            \Exception::class
        );

        $this->expectExceptionMessageIs(
            "Primary key field 'code' cannot be null."
        );

        $model->validateForTest();
    }

    /**
     * Test that an auto-increment primary key must be numeric.
     *
     * @return void
     */
    public function testAutoIncrementPrimaryKeyMustBeNumeric(): void {
        $this->expectException(
            \Exception::class
        );

        $this->expectExceptionMessageIs(
            "Auto-increment primary key 'code' must use OField::NUMBER or OField::BIGINT."
        );

        new InvalidAutoIncrementModel();
    }

    /**
     * Test the schema generated from model attributes.
     *
     * @return void
     */
    public function testModelSchemaContainsExpectedContracts(): void {
        $model = new ExampleModel();

        $schema = $model->getModel();

        self::assertSame(
            'example_model',
            $schema['table_name']
        );

        self::assertSame(
            [
                'id'
            ],
            $schema['primary_key']
        );

        self::assertFalse(
            $schema['fields']['id']['nullable']
        );

        self::assertTrue(
            $schema['fields']['id']['auto_increment']
        );

        self::assertSame(
            "O'Reilly",
            $schema['fields']['name']['default']
        );

        self::assertFalse(
            $schema['fields']['secret']['visible']
        );

        self::assertSame(
            'created_at',
            $schema['created_at']
        );

        self::assertSame(
            'updated_at',
            $schema['updated_at']
        );

        self::assertSame(
            'deleted_at',
            $schema['deleted_at']
        );
    }

    /**
     * Test serialization hides fields marked as invisible.
     *
     * @return void
     */
    public function testToArrayHidesInvisibleFields(): void {
        $model = new ExampleModel(
            [
                'id' => 1,
                'name' => 'Visible',
                'secret' => 'hidden'
            ]
        );

        $data = $model->toArray();

        self::assertSame(
            'Visible',
            $data['name']
        );

        self::assertArrayNotHasKey(
            'secret',
            $data
        );
    }

    /**
     * Test SQL generation including defaults, long text and references.
     *
     * @return void
     */
    public function testToSQLPreservesOrmSchema(): void {
        $model = new ExampleModel();

        $sql = $model->toSQL();

        self::assertStringContainsString(
            '`id` INT(11) NOT NULL AUTO_INCREMENT',
            $sql
        );

        self::assertStringContainsString(
            "`name` VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'O''Reilly' COMMENT 'Display name'",
            $sql
        );

        self::assertStringContainsString(
            '`active` TINYINT(1) NOT NULL DEFAULT 1',
            $sql
        );

        self::assertStringContainsString(
            '`notes` LONGTEXT COLLATE utf8mb4_unicode_ci',
            $sql
        );

        self::assertStringContainsString(
            'PRIMARY KEY (`id`)',
            $sql
        );

        self::assertStringContainsString(
            'ADD KEY `fk_example_model_category_id_category_idx` (`category_id`)',
            $sql
        );

        self::assertStringContainsString(
            'ADD CONSTRAINT `fk_example_model_category_id_category` FOREIGN KEY (`category_id`) REFERENCES `category` (`id`)',
            $sql
        );
    }

    /**
     * Test that textual primary keys preserve their maximum length.
     *
     * @return void
     */
    public function testTextPrimaryKeyPreservesMaximumLength(): void {
        $model = new TextKeyModel();

        $sql = $model->toSQL();

        self::assertStringContainsString(
            '`code` VARCHAR(36) COLLATE utf8mb4_unicode_ci NOT NULL',
            $sql
        );

        self::assertStringNotContainsString(
            'AUTO_INCREMENT',
            $sql
        );
    }

    /**
     * Test that ORM statement parameters are bound using their PDO types.
     *
     * @return void
     */
    public function testStatementParametersAreBoundUsingPDOType(): void {
        $statement = $this->createMock(
            PDOStatement::class
        );

        $bound_values = [];

        $statement
            ->expects(
                self::exactly(6)
            )
            ->method('bindValue')
            ->willReturnCallback(
                static function (
                    string|int $param,
                    mixed $value,
                    int $type = PDO::PARAM_STR
                ) use (&$bound_values): bool {
                    $bound_values[] = [
                        $param,
                        $value,
                        $type
                    ];

                    return true;
                }
            );

        $statement
            ->expects(
                self::once()
            )
            ->method('execute')
            ->willReturn(true);

        $result = ExampleModel::executeStatementForTest(
            $statement,
            [
                ':bool_false' => false,
                ':bool_true' => true,
                ':integer' => 25,
                ':null' => null,
                ':float' => 12.5,
                ':string' => 'test'
            ]
        );

        self::assertTrue(
            $result
        );

        self::assertSame(
            [
                [
                    ':bool_false',
                    false,
                    PDO::PARAM_BOOL
                ],
                [
                    ':bool_true',
                    true,
                    PDO::PARAM_BOOL
                ],
                [
                    ':integer',
                    25,
                    PDO::PARAM_INT
                ],
                [
                    ':null',
                    null,
                    PDO::PARAM_NULL
                ],
                [
                    ':float',
                    12.5,
                    PDO::PARAM_STR
                ],
                [
                    ':string',
                    'test',
                    PDO::PARAM_STR
                ]
            ],
            $bound_values
        );
    }

    /**
     * Test exact DECIMAL normalization.
     *
     * @return void
     */
    public function testDecimalValuesAreNormalizedExactly(): void {
        $model = DecimalModel::create(
            [
                'amount' => '+0012,5',
                'ratio' => '0,9',
                'units' => '00012'
            ]
        );

        self::assertSame(
            '12.50',
            $model->amount
        );

        self::assertSame(
            '0.90',
            $model->ratio
        );

        self::assertSame(
            '12',
            $model->units
        );

        $model->applyDefaultsForTest();

        self::assertSame(
            '1.50',
            $model->rate
        );
    }

    /**
     * Test that negative zero is normalized to unsigned zero.
     *
     * @return void
     */
    public function testDecimalNegativeZeroIsNormalized(): void {
        $model = DecimalModel::create(
            [
                'amount' => '-0,00'
            ]
        );

        self::assertSame(
            '0.00',
            $model->amount
        );
    }

    /**
     * Test that DECIMAL values never accept floating-point input.
     *
     * @return void
     */
    public function testDecimalRejectsFloatInput(): void {
        $this->expectException(
            \UnexpectedValueException::class
        );

        DecimalModel::create(
            [
                'amount' => 12.5
            ]
        );
    }

    /**
     * Test that DECIMAL values never accept integer input.
     *
     * @return void
     */
    public function testDecimalRejectsIntegerInput(): void {
        $this->expectException(
            \UnexpectedValueException::class
        );

        DecimalModel::create(
            [
                'amount' => 12
            ]
        );
    }

    /**
     * Test that DECIMAL values cannot exceed their scale.
     *
     * @return void
     */
    public function testDecimalRejectsExcessiveScale(): void {
        $this->expectException(
            \UnexpectedValueException::class
        );

        DecimalModel::create(
            [
                'amount' => '12.345'
            ]
        );
    }

    /**
     * Test that DECIMAL values cannot exceed their integer precision.
     *
     * @return void
     */
    public function testDecimalRejectsExcessivePrecision(): void {
        $this->expectException(
            \UnexpectedValueException::class
        );

        DecimalModel::create(
            [
                'amount' => '123456789.00'
            ]
        );
    }

    /**
     * Test DECIMAL definitions whose scale consumes all available precision.
     *
     * @return void
     */
    public function testDecimalSupportsZeroIntegerDigits(): void {
        $model = DecimalModel::create(
            [
                'amount' => '1.00',
                'ratio' => '0.99'
            ]
        );

        self::assertSame(
            '0.99',
            $model->ratio
        );
    }

    /**
     * Test that DECIMAL fields with scale zero reject fractional values.
     *
     * @return void
     */
    public function testDecimalScaleZeroRejectsFractionalValues(): void {
        $this->expectException(
            \UnexpectedValueException::class
        );

        DecimalModel::create(
            [
                'amount' => '1.00',
                'units' => '12.0'
            ]
        );
    }

    /**
     * Test SQL generation for DECIMAL fields.
     *
     * @return void
     */
    public function testDecimalSQLDefinitionIsGenerated(): void {
        $model = new DecimalModel();

        $sql = $model->toSQL();

        self::assertStringContainsString(
            '`amount` DECIMAL(10,2) NOT NULL',
            $sql
        );

        self::assertStringContainsString(
            '`rate` DECIMAL(5,2) DEFAULT 1.50',
            $sql
        );

        self::assertStringContainsString(
            '`ratio` DECIMAL(2,2)',
            $sql
        );

        self::assertStringContainsString(
            '`units` DECIMAL(5,0)',
            $sql
        );
    }

    /**
     * Test that DECIMAL query conditions use their canonical representation.
     *
     * @return void
     */
    public function testDecimalQueryConditionsAreNormalized(): void {
        $conditions = DecimalModel::normalizeConditionsForTest(
            [
                'amount' => '+0012,5'
            ]
        );

        self::assertSame(
            [
                'amount' => '12.50'
            ],
            $conditions
        );
    }

    /**
     * Test that accepted decimal separators produce identical query conditions.
     *
     * @return void
     */
    public function testDecimalQuerySeparatorsNormalizeIdentically(): void {
        $comma_conditions = DecimalModel::normalizeConditionsForTest(
            [
                'amount' => '12,5'
            ]
        );

        $dot_conditions = DecimalModel::normalizeConditionsForTest(
            [
                'amount' => '12.50'
            ]
        );

        self::assertSame(
            $dot_conditions,
            $comma_conditions
        );
    }

    /**
     * Test that DECIMAL query conditions reject floating-point values.
     *
     * @return void
     */
    public function testDecimalQueryConditionsRejectFloat(): void {
        $this->expectException(
            \UnexpectedValueException::class
        );

        DecimalModel::normalizeConditionsForTest(
            [
                'amount' => 12.5
            ]
        );
    }

    /**
     * Test that DECIMAL query conditions reject integer values.
     *
     * @return void
     */
    public function testDecimalQueryConditionsRejectInteger(): void {
        $this->expectException(
            \UnexpectedValueException::class
        );

        DecimalModel::normalizeConditionsForTest(
            [
                'amount' => 12
            ]
        );
    }

    /**
     * Test that query conditions use the normalization rules of every ORM field.
     *
     * @return void
     */
    public function testQueryConditionsUseModelFieldNormalization(): void {
        $conditions = DecimalModel::normalizeConditionsForTest(
            [
                'id' => '25',
                'amount' => '12,5'
            ]
        );

        self::assertSame(
            [
                'id' => 25,
                'amount' => '12.50'
            ],
            $conditions
        );
    }

    /**
     * Test that null query conditions remain null.
     *
     * @return void
     */
    public function testQueryConditionsPreserveNull(): void {
        $conditions = DecimalModel::normalizeConditionsForTest(
            [
                'rate' => null
            ]
        );

        self::assertSame(
            [
                'rate' => null
            ],
            $conditions
        );
    }

    /**
     * Test that query conditions cannot reference unknown model fields.
     *
     * @return void
     */
    public function testQueryConditionsRejectUnknownFields(): void {
        $this->expectException(
            \InvalidArgumentException::class
        );

        $this->expectExceptionMessageIs(
            "Unknown model field 'unknown_field'."
        );

        DecimalModel::normalizeConditionsForTest(
            [
                'unknown_field' => '12.50'
            ]
        );
    }

    /**
     * Test BIGINT values are normalized to PHP integers.
     *
     * @return void
     */
    public function testBigIntValuesAreNormalizedToInteger(): void {
        $model = TemporalBigIntModel::from(
            [
                'id' => '9000000000',
                'event_date' => '2026-10-14',
                'event_datetime' => '2026-10-14 12:30:00'
            ]
        );

        self::assertSame(
            9000000000,
            $model->id
        );
    }

    /**
     * Test DATE and DATETIME values use their respective formats.
     *
     * @return void
     */
    public function testDateAndDateTimeValuesAreValid(): void {
        $model = TemporalBigIntModel::create(
            [
                'event_date' => '2026-10-14',
                'event_datetime' => '2026-10-14 12:30:45'
            ]
        );

        $model->validateForTest();

        self::assertSame(
            '2026-10-14',
            $model->event_date
        );

        self::assertSame(
            '2026-10-14 12:30:45',
            $model->event_datetime
        );
    }

    /**
     * Test that DATE fields reject DATETIME values.
     *
     * @return void
     */
    public function testDateRejectsDateTimeValue(): void {
        $model = TemporalBigIntModel::create(
            [
                'event_date' => '2026-10-14 12:30:00',
                'event_datetime' => '2026-10-14 12:30:00'
            ]
        );

        $this->expectException(
            \Exception::class
        );

        $this->expectExceptionMessageIs(
            "The 'event_date' field must be in a valid date format (Y-m-d)."
        );

        $model->validateForTest();
    }

    /**
     * Test that DATETIME fields reject DATE-only values.
     *
     * @return void
     */
    public function testDateTimeRejectsDateValue(): void {
        $model = TemporalBigIntModel::create(
            [
                'event_date' => '2026-10-14',
                'event_datetime' => '2026-10-14'
            ]
        );

        $this->expectException(
            \Exception::class
        );

        $this->expectExceptionMessageIs(
            "The 'event_datetime' field must be in a valid date and time format (Y-m-d H:i:s)."
        );

        $model->validateForTest();
    }

    /**
     * Test automatic timestamp fields use DATETIME internally.
     *
     * @return void
     */
    public function testAutomaticTimestampFieldsUseDateTime(): void {
        $model = new TemporalBigIntModel();

        $schema = $model->getModel();

        self::assertSame(
            OField::DATETIME,
            $schema['fields']['created_at']['type']
        );

        self::assertSame(
            OField::DATETIME,
            $schema['fields']['updated_at']['type']
        );
    }

    /**
     * Test formatting of DATE and DATETIME fields.
     *
     * @return void
     */
    public function testDateAndDateTimeFieldsCanBeFormatted(): void {
        $model = TemporalBigIntModel::create(
            [
                'event_date' => '2026-10-14',
                'event_datetime' => '2026-10-14 12:30:45',
                'created_at' => '2026-10-14 09:15:00'
            ]
        );

        self::assertSame(
            '14/10/2026',
            $model->get(
                'event_date',
                'd/m/Y'
            )
        );

        self::assertSame(
            '14/10/2026 12:30',
            $model->get(
                'event_datetime',
                'd/m/Y H:i'
            )
        );

        self::assertSame(
            '14/10/2026 09:15',
            $model->get(
                'created_at',
                'd/m/Y H:i'
            )
        );
    }

    /**
     * Test SQL generation for BIGINT, DATE and DATETIME fields.
     *
     * @return void
     */
    public function testBigIntDateAndDateTimeSQLDefinitionsAreGenerated(): void {
        $model = new TemporalBigIntModel();

        $sql = $model->toSQL();

        self::assertStringContainsString(
            '`id` BIGINT NOT NULL AUTO_INCREMENT',
            $sql
        );

        self::assertStringContainsString(
            '`event_date` DATE NOT NULL',
            $sql
        );

        self::assertStringContainsString(
            '`event_datetime` DATETIME NOT NULL',
            $sql
        );

        self::assertStringContainsString(
            '`created_at` DATETIME',
            $sql
        );

        self::assertStringContainsString(
            '`updated_at` DATETIME',
            $sql
        );
    }

    /**
     * Test temporal and BIGINT schema contracts.
     *
     * @return void
     */
    public function testTemporalAndBigIntSchemaContracts(): void {
        $model = new TemporalBigIntModel();

        $schema = $model->getModel();

        self::assertSame(
            OField::BIGINT,
            $schema['fields']['id']['type']
        );

        self::assertSame(
            OField::DATE,
            $schema['fields']['event_date']['type']
        );

        self::assertSame(
            OField::DATETIME,
            $schema['fields']['event_datetime']['type']
        );
    }

    /**
     * Test that invalid calendar DATE values are rejected.
     *
     * @return void
     */
    public function testInvalidCalendarDateIsRejected(): void {
        $model = TemporalBigIntModel::create(
            [
                'event_date' => '2026-02-30',
                'event_datetime' => '2026-10-14 12:30:00'
            ]
        );

        try {
            $model->validateForTest();

            self::fail(
                'Invalid calendar DATE was expected to be rejected.'
            );
        } catch (\Exception $e) {
            self::assertSame(
                "The 'event_date' field must be in a valid date format (Y-m-d).",
                $e->getMessage()
            );
        }
    }
}
