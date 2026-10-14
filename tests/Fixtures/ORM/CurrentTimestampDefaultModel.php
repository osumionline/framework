<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\Fixtures\ORM;

use Osumi\OsumiFramework\ORM\OCreatedAt;
use Osumi\OsumiFramework\ORM\OField;
use Osumi\OsumiFramework\ORM\OModel;
use Osumi\OsumiFramework\ORM\OPK;
use Osumi\OsumiFramework\ORM\OUpdatedAt;

final class CurrentTimestampDefaultModel extends OModel {
    #[OPK]
    public ?int $id = null;

    #[OField(
        type: OField::DATETIME,
        nullable: false,
        default: OField::CURRENT_TIMESTAMP
    )]
    public ?string $joined_at = null;

    #[OCreatedAt]
    public ?string $created_at = null;

    #[OUpdatedAt]
    public ?string $updated_at = null;

    /**
     * Apply model defaults for testing.
     *
     * @return void
     */
    public function applyDefaultsForTest(): void {
        $this->applyDefaults();
    }

    /**
     * Validate model values for testing.
     *
     * @return void
     */
    public function validateForTest(): void {
        $this->validate();
    }
}
