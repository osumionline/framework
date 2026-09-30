<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\Fixtures\ORM;

use Osumi\OsumiFramework\ORM\OCreatedAt;
use Osumi\OsumiFramework\ORM\ODeletedAt;
use Osumi\OsumiFramework\ORM\OField;
use Osumi\OsumiFramework\ORM\OModel;
use Osumi\OsumiFramework\ORM\OPK;
use Osumi\OsumiFramework\ORM\OUpdatedAt;

final class ExampleModel extends OModel {
    #[OPK]
    public ?int $id = null;

    #[OField(
        type: OField::TEXT,
        nullable: false,
        default: "O'Reilly",
        max: 100,
        comment: 'Display name'
    )]
    public ?string $name = null;

    #[OField(
        type: OField::BOOL,
        nullable: false,
        default: true
    )]
    public ?bool $active = null;

    #[OField(
        type: OField::FLOAT,
        default: 12.5
    )]
    public ?float $price = null;

    #[OField(
        type: OField::LONGTEXT
    )]
    public ?string $notes = null;

    #[OField(
        type: OField::TEXT,
        visible: false
    )]
    public ?string $secret = null;

    #[OField(
        type: OField::NUMBER,
        ref: 'category.id'
    )]
    public ?int $category_id = null;

    #[OCreatedAt]
    public ?string $created_at = null;

    #[OUpdatedAt]
    public ?string $updated_at = null;

    #[ODeletedAt]
    public ?string $deleted_at = null;

    /**
     * Apply ORM defaults for testing.
     *
     * @return void
     */
    public function applyDefaultsForTest(): void {
        $this->applyDefaults();
    }

    /**
     * Validate the current model values for testing.
     *
     * @return void
     */
    public function validateForTest(): void {
        $this->validate();
    }

    /**
     * Check whether the model is considered new.
     *
     * @return bool Whether the model is new.
     */
    public function isNewRecordForTest(): bool {
        return $this->is_new_record;
    }

    /**
     * Assert that the model represents a persisted record.
     *
     * @return void
     */
    public function assertPersistedForTest(): void {
        $this->assertPersisted();
    }
}
