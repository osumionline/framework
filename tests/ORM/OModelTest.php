<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\ORM;

use Osumi\OsumiFramework\Tests\Fixtures\ORM\ExampleModel;
use Osumi\OsumiFramework\Tests\Fixtures\ORM\InvalidAutoIncrementModel;
use Osumi\OsumiFramework\Tests\Fixtures\ORM\TextKeyModel;
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

        $this->expectExceptionMessage(
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

        $this->expectExceptionMessage(
            "Auto-increment primary key 'code' must use OField::NUMBER."
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
}
