<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\Fixtures\ORM;

use Osumi\OsumiFramework\ORM\OCreatedAt;
use Osumi\OsumiFramework\ORM\OField;
use Osumi\OsumiFramework\ORM\OModel;
use Osumi\OsumiFramework\ORM\OPK;
use Osumi\OsumiFramework\ORM\OUpdatedAt;

final class DecimalModel extends OModel {
    #[OPK]
    public ?int $id = null;

    #[OField(
        type: OField::DECIMAL,
        nullable: false
    )]
    public ?string $amount = null;

    #[OField(
        type: OField::DECIMAL,
        default: '1,5',
        precision: 5,
        scale: 2
    )]
    public ?string $rate = null;

    #[OField(
        type: OField::DECIMAL,
        precision: 2,
        scale: 2
    )]
    public ?string $ratio = null;

    #[OField(
        type: OField::DECIMAL,
        precision: 5,
        scale: 0
    )]
    public ?string $units = null;

    #[OCreatedAt]
    public ?string $created_at = null;

    #[OUpdatedAt]
    public ?string $updated_at = null;

    /**
     * Apply ORM defaults for testing.
     *
     * @return void
     */
    public function applyDefaultsForTest(): void {
        $this->applyDefaults();
    }

    /**
     * Validate and normalize the current model values for testing.
     *
     * @return void
     */
    public function validateForTest(): void {
        $this->validate();
    }

    /**
     * Normalize ORM query conditions for testing.
     *
     * @param array<string, mixed> $conditions Query conditions.
     *
     * @return array<string, string|int|float|bool|null> Normalized conditions.
     */
    public static function normalizeConditionsForTest(
        array $conditions
    ): array {
        return static::normalizeConditions(
            $conditions
        );
    }
}
