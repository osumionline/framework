<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\Task;

use Osumi\OsumiFramework\Core\OConfig;
use Osumi\OsumiFramework\Core\OTranslate;
use Osumi\OsumiFramework\Task\GenerateModelFromTask;
use Osumi\OsumiFramework\Tests\Support\TemporaryProject;
use PHPUnit\Framework\TestCase;

final class GenerateModelFromTaskTest extends TestCase {
    private TemporaryProject $project;
    private bool $core_existed = false;
    private mixed $previous_core = null;

    /**
     * Prepare an isolated model generation environment.
     *
     * @return void
     *
     * @throws \RuntimeException If the model directory cannot be created.
     */
    protected function setUp(): void {
        parent::setUp();

        $this->project = new TemporaryProject();

        $this->core_existed = array_key_exists(
            'core',
            $GLOBALS
        );

        $this->previous_core = $GLOBALS['core']
            ?? null;

        $config = new OConfig(
            $this->project->getBasePath()
        );

        $model_path = $config->getDir(
            'app_model'
        );

        if (
            !mkdir(
                $model_path,
                0755,
                true
            ) &&
            !is_dir($model_path)
        ) {
            throw new \RuntimeException(
                "Could not create test model directory '{$model_path}'."
            );
        }

        $config->setDir(
            'ofw_template',
            dirname(
                __DIR__,
                2
            )
                . '/src/Assets/template/'
        );

        $translate = new OTranslate();

        $translate->setTranslation(
            'TASK_GENERATE_MODEL_FROM_OK',
            'Generated %s in %s'
        );

        $core = new \stdClass();
        $core->config = $config;
        $core->translate = $translate;

        $GLOBALS['core'] = $core;
    }

    /**
     * Restore global state and remove temporary files.
     *
     * @return void
     */
    protected function tearDown(): void {
        if ($this->core_existed) {
            $GLOBALS['core'] = $this->previous_core;
        } else {
            unset(
                $GLOBALS['core']
            );
        }

        $this->project->remove();

        parent::tearDown();
    }

    /**
     * Write a model definition file.
     *
     * @param array<string, mixed> $definition Model definition.
     *
     * @return string Relative file path.
     *
     * @throws \JsonException If the definition cannot be encoded.
     * @throws \RuntimeException If the file cannot be written.
     */
    private function writeDefinition(
        array $definition
    ): string {
        $relative_path = 'model.json';

        $content = json_encode(
            $definition,
            JSON_PRETTY_PRINT |
                JSON_UNESCAPED_SLASHES |
                JSON_THROW_ON_ERROR
        );

        $path = $this->project->getPath(
            $relative_path
        );

        if (
            file_put_contents(
                $path,
                $content,
                LOCK_EX
            ) === false
        ) {
            throw new \RuntimeException(
                "Could not write model definition '{$path}'."
            );
        }

        return $relative_path;
    }

    /**
     * Run the generator while capturing CLI output.
     *
     * @param string $relative_path Model definition file.
     *
     * @return string Captured task output.
     *
     * @throws \RuntimeException If output buffering cannot be used.
     */
    private function runGenerator(
        string $relative_path
    ): string {
        $buffer_level = ob_get_level();

        if (!ob_start()) {
            throw new \RuntimeException(
                'Could not start generator test output buffer.'
            );
        }

        try {
            $task = new GenerateModelFromTask();

            $task->run(
                [
                    'file' => $relative_path
                ]
            );

            $output = ob_get_contents();

            if ($output === false) {
                throw new \RuntimeException(
                    'Could not read generator test output.'
                );
            }

            return $output;
        } finally {
            while (ob_get_level() > $buffer_level) {
                ob_end_clean();
            }
        }
    }

    /**
     * Test generation of textual primary keys and deleted-at fields.
     *
     * @return void
     *
     * @throws \RuntimeException If the generated model cannot be read.
     */
    public function testGeneratesStrictModelDefinition(): void {
        $file = $this->writeDefinition(
            [
                'model' => [
                    [
                        'name' => 'users',
                        'fields' => [
                            [
                                'name' => 'code',
                                'decorator' => 'OPK',
                                'attribute_type' => 'string',
                                'type' => 'OField::TEXT',
                                'incr' => false,
                                'nullable' => false,
                                'max' => 36
                            ],
                            [
                                'name' => 'name',
                                'decorator' => 'OField',
                                'attribute_type' => 'string',
                                'type' => 'OField::TEXT',
                                'nullable' => false,
                                'default' => 'Anonymous',
                                'max' => 100
                            ],
                            [
                                'name' => 'created_at',
                                'decorator' => 'OCreatedAt'
                            ],
                            [
                                'name' => 'updated_at',
                                'decorator' => 'OUpdatedAt'
                            ],
                            [
                                'name' => 'deleted_at',
                                'decorator' => 'ODeletedAt'
                            ]
                        ],
                        'refs' => []
                    ]
                ]
            ]
        );

        $this->runGenerator(
            $file
        );

        $generated_path = $this->project->getPath(
            'src/Model/Users.php'
        );

        self::assertFileExists(
            $generated_path
        );

        $content = file_get_contents(
            $generated_path
        );

        if ($content === false) {
            throw new \RuntimeException(
                'Could not read generated model.'
            );
        }

        self::assertStringContainsString(
            '#[OPK(',
            $content
        );

        self::assertStringContainsString(
            'type: OField::TEXT',
            $content
        );

        self::assertStringContainsString(
            'incr: false',
            $content
        );

        self::assertStringContainsString(
            'max: 36',
            $content
        );

        self::assertStringContainsString(
            'public ?string $code;',
            $content
        );

        self::assertStringContainsString(
            '#[ODeletedAt(',
            $content
        );
    }

    /**
     * Test incompatible ORM and PHP field types.
     *
     * @return void
     */
    public function testRejectsIncompatibleFieldTypes(): void {
        $file = $this->writeDefinition(
            [
                'model' => [
                    [
                        'name' => 'users',
                        'fields' => [
                            [
                                'name' => 'id',
                                'decorator' => 'OPK'
                            ],
                            [
                                'name' => 'name',
                                'decorator' => 'OField',
                                'attribute_type' => 'string',
                                'type' => 'OField::NUMBER'
                            ],
                            [
                                'name' => 'created_at',
                                'decorator' => 'OCreatedAt'
                            ],
                            [
                                'name' => 'updated_at',
                                'decorator' => 'OUpdatedAt'
                            ]
                        ],
                        'refs' => []
                    ]
                ]
            ]
        );

        $this->expectException(
            \InvalidArgumentException::class
        );

        $this->runGenerator(
            $file
        );
    }

    /**
     * Test that every model is validated before any output file is generated.
     *
     * @return void
     */
    public function testCompleteDocumentIsValidatedBeforeGeneration(): void {
        $file = $this->writeDefinition(
            [
                'model' => [
                    [
                        'name' => 'first_table',
                        'fields' => [
                            [
                                'name' => 'id',
                                'decorator' => 'OPK'
                            ],
                            [
                                'name' => 'created_at',
                                'decorator' => 'OCreatedAt'
                            ],
                            [
                                'name' => 'updated_at',
                                'decorator' => 'OUpdatedAt'
                            ]
                        ],
                        'refs' => []
                    ],
                    [
                        'name' => 'second_table',
                        'fields' => [
                            [
                                'name' => 'id',
                                'decorator' => 'OPK'
                            ],
                            [
                                'name' => 'invalid',
                                'decorator' => 'OField',
                                'attribute_type' => 'array'
                            ],
                            [
                                'name' => 'created_at',
                                'decorator' => 'OCreatedAt'
                            ],
                            [
                                'name' => 'updated_at',
                                'decorator' => 'OUpdatedAt'
                            ]
                        ],
                        'refs' => []
                    ]
                ]
            ]
        );

        try {
            $this->runGenerator(
                $file
            );

            self::fail(
                'The invalid model document was expected to fail validation.'
            );
        } catch (\InvalidArgumentException) {
            self::assertFileDoesNotExist(
                $this->project->getPath(
                    'src/Model/FirstTable.php'
                )
            );
        }
    }

    /**
     * Test generation and SQL round-trip of a DECIMAL model field.
     *
     * @return void
     *
     * @throws \RuntimeException If the generated model cannot be read.
     */
    public function testGeneratesDecimalModelDefinition(): void {
        $file = $this->writeDefinition(
            [
                'model' => [
                    [
                        'name' => 'generated_decimal_model_for_test',
                        'fields' => [
                            [
                                'name' => 'id',
                                'decorator' => 'OPK'
                            ],
                            [
                                'name' => 'amount',
                                'decorator' => 'OField',
                                'attribute_type' => 'string',
                                'type' => 'OField::DECIMAL',
                                'nullable' => false,
                                'default' => '12,5',
                                'precision' => 12,
                                'scale' => 2
                            ],
                            [
                                'name' => 'created_at',
                                'decorator' => 'OCreatedAt'
                            ],
                            [
                                'name' => 'updated_at',
                                'decorator' => 'OUpdatedAt'
                            ]
                        ],
                        'refs' => []
                    ]
                ]
            ]
        );

        $this->runGenerator(
            $file
        );

        $generated_path = $this->project->getPath(
            'src/Model/GeneratedDecimalModelForTest.php'
        );

        self::assertFileExists(
            $generated_path
        );

        $content = file_get_contents(
            $generated_path
        );

        if ($content === false) {
            throw new \RuntimeException(
                'Could not read generated DECIMAL model.'
            );
        }

        self::assertStringContainsString(
            'type: OField::DECIMAL',
            $content
        );

        self::assertStringContainsString(
            'precision: 12',
            $content
        );

        self::assertStringContainsString(
            'scale: 2',
            $content
        );

        self::assertStringContainsString(
            "default: '12,5'",
            $content
        );

        self::assertStringContainsString(
            'public ?string $amount;',
            $content
        );

        require_once $generated_path;

        $class_name =
            'Osumi\\OsumiFramework\\App\\Model\\GeneratedDecimalModelForTest';

        $model = new $class_name();

        $sql = $model->toSQL();

        self::assertStringContainsString(
            '`amount` DECIMAL(12,2) NOT NULL DEFAULT 12.50',
            $sql
        );
    }

    /**
     * Test that DECIMAL fields require string PHP properties.
     *
     * @return void
     */
    public function testRejectsDecimalWithFloatAttributeType(): void {
        $file = $this->writeDefinition(
            [
                'model' => [
                    [
                        'name' => 'invalid_decimal',
                        'fields' => [
                            [
                                'name' => 'id',
                                'decorator' => 'OPK'
                            ],
                            [
                                'name' => 'amount',
                                'decorator' => 'OField',
                                'attribute_type' => 'float',
                                'type' => 'OField::DECIMAL'
                            ],
                            [
                                'name' => 'created_at',
                                'decorator' => 'OCreatedAt'
                            ],
                            [
                                'name' => 'updated_at',
                                'decorator' => 'OUpdatedAt'
                            ]
                        ],
                        'refs' => []
                    ]
                ]
            ]
        );

        $this->expectException(
            \InvalidArgumentException::class
        );

        $this->runGenerator(
            $file
        );
    }

    /**
     * Test that precision cannot be used by non-DECIMAL fields.
     *
     * @return void
     */
    public function testRejectsPrecisionOnNonDecimalField(): void {
        $file = $this->writeDefinition(
            [
                'model' => [
                    [
                        'name' => 'invalid_precision',
                        'fields' => [
                            [
                                'name' => 'id',
                                'decorator' => 'OPK'
                            ],
                            [
                                'name' => 'name',
                                'decorator' => 'OField',
                                'attribute_type' => 'string',
                                'type' => 'OField::TEXT',
                                'precision' => 12
                            ],
                            [
                                'name' => 'created_at',
                                'decorator' => 'OCreatedAt'
                            ],
                            [
                                'name' => 'updated_at',
                                'decorator' => 'OUpdatedAt'
                            ]
                        ],
                        'refs' => []
                    ]
                ]
            ]
        );

        $this->expectException(
            \InvalidArgumentException::class
        );

        $this->runGenerator(
            $file
        );
    }

    /**
     * Test that DECIMAL cannot be used as a primary key.
     *
     * @return void
     */
    public function testRejectsDecimalPrimaryKeyDefinition(): void {
        $file = $this->writeDefinition(
            [
                'model' => [
                    [
                        'name' => 'invalid_decimal_pk',
                        'fields' => [
                            [
                                'name' => 'id',
                                'decorator' => 'OPK',
                                'attribute_type' => 'string',
                                'type' => 'OField::DECIMAL',
                                'incr' => false
                            ],
                            [
                                'name' => 'created_at',
                                'decorator' => 'OCreatedAt'
                            ],
                            [
                                'name' => 'updated_at',
                                'decorator' => 'OUpdatedAt'
                            ]
                        ],
                        'refs' => []
                    ]
                ]
            ]
        );

        $this->expectException(
            \InvalidArgumentException::class
        );

        $this->expectExceptionMessageIs(
            "Primary key field 'id' cannot use OField::DECIMAL."
        );

        $this->runGenerator(
            $file
        );
    }

    /**
     * Test that DECIMAL defaults must remain exact strings.
     *
     * @return void
     */
    public function testRejectsDecimalFloatDefault(): void {
        $file = $this->writeDefinition(
            [
                'model' => [
                    [
                        'name' => 'invalid_decimal_default',
                        'fields' => [
                            [
                                'name' => 'id',
                                'decorator' => 'OPK'
                            ],
                            [
                                'name' => 'amount',
                                'decorator' => 'OField',
                                'attribute_type' => 'string',
                                'type' => 'OField::DECIMAL',
                                'default' => 12.5
                            ],
                            [
                                'name' => 'created_at',
                                'decorator' => 'OCreatedAt'
                            ],
                            [
                                'name' => 'updated_at',
                                'decorator' => 'OUpdatedAt'
                            ]
                        ],
                        'refs' => []
                    ]
                ]
            ]
        );

        $this->expectException(
            \InvalidArgumentException::class
        );

        $this->expectExceptionMessageIs(
            "DECIMAL field 'amount' default value must be a string."
        );

        $this->runGenerator(
            $file
        );
    }

    /**
     * Test that DECIMAL scale cannot exceed precision.
     *
     * @return void
     */
    public function testRejectsDecimalScaleGreaterThanPrecision(): void {
        $file = $this->writeDefinition(
            [
                'model' => [
                    [
                        'name' => 'invalid_decimal_scale',
                        'fields' => [
                            [
                                'name' => 'id',
                                'decorator' => 'OPK'
                            ],
                            [
                                'name' => 'amount',
                                'decorator' => 'OField',
                                'attribute_type' => 'string',
                                'type' => 'OField::DECIMAL',
                                'precision' => 5,
                                'scale' => 6
                            ],
                            [
                                'name' => 'created_at',
                                'decorator' => 'OCreatedAt'
                            ],
                            [
                                'name' => 'updated_at',
                                'decorator' => 'OUpdatedAt'
                            ]
                        ],
                        'refs' => []
                    ]
                ]
            ]
        );

        $this->expectException(
            \InvalidArgumentException::class
        );

        $this->runGenerator(
            $file
        );
    }

    /**
     * Test generation and SQL round-trip of DATE, DATETIME and BIGINT fields.
     *
     * @return void
     *
     * @throws \RuntimeException If the generated model cannot be read.
     */
    public function testGeneratesTemporalAndBigIntModelDefinition(): void {
        $file = $this->writeDefinition(
            [
                'model' => [
                    [
                        'name' => 'generated_temporal_bigint_model_for_test',
                        'fields' => [
                            [
                                'name' => 'id',
                                'decorator' => 'OPK',
                                'type' => 'OField::BIGINT',
                                'incr' => true,
                                'nullable' => false
                            ],
                            [
                                'name' => 'business_date',
                                'decorator' => 'OField',
                                'attribute_type' => 'string',
                                'type' => 'OField::DATE',
                                'nullable' => false
                            ],
                            [
                                'name' => 'processed_at',
                                'decorator' => 'OField',
                                'attribute_type' => 'string',
                                'type' => 'OField::DATETIME',
                                'nullable' => true
                            ],
                            [
                                'name' => 'created_at',
                                'decorator' => 'OCreatedAt'
                            ],
                            [
                                'name' => 'updated_at',
                                'decorator' => 'OUpdatedAt'
                            ],
                            [
                                'name' => 'joined_at',
                                'decorator' => 'OField',
                                'attribute_type' => 'string',
                                'type' => 'OField::DATETIME',
                                'nullable' => false,
                                'default' => 'CURRENT_TIMESTAMP'
                            ]
                        ],
                        'refs' => []
                    ]
                ]
            ]
        );

        $this->runGenerator(
            $file
        );

        $generated_path = $this->project->getPath(
            'src/Model/GeneratedTemporalBigintModelForTest.php'
        );

        self::assertFileExists(
            $generated_path
        );

        $content = file_get_contents(
            $generated_path
        );

        if ($content === false) {
            throw new \RuntimeException(
                'Could not read generated temporal/BIGINT model.'
            );
        }

        self::assertStringContainsString(
            'type: OField::BIGINT',
            $content
        );

        self::assertStringContainsString(
            'public ?int $id;',
            $content
        );

        self::assertStringContainsString(
            'type: OField::DATE',
            $content
        );

        self::assertStringContainsString(
            'public ?string $business_date;',
            $content
        );

        self::assertStringContainsString(
            'type: OField::DATETIME',
            $content
        );

        self::assertStringContainsString(
            'public ?string $processed_at;',
            $content
        );

        self::assertStringContainsString(
            'default: OField::CURRENT_TIMESTAMP',
            $content
        );

        require_once $generated_path;

        $class_name =
            'Osumi\\OsumiFramework\\App\\Model\\GeneratedTemporalBigintModelForTest';

        $model = new $class_name();

        $sql = $model->toSQL();

        self::assertStringContainsString(
            '`id` BIGINT NOT NULL AUTO_INCREMENT',
            $sql
        );

        self::assertStringContainsString(
            '`business_date` DATE NOT NULL',
            $sql
        );

        self::assertStringContainsString(
            '`processed_at` DATETIME',
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

        self::assertStringContainsString(
            '`joined_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
            $sql
        );
    }
}
