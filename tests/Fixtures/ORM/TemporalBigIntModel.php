<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\Fixtures\ORM;

use Osumi\OsumiFramework\ORM\OCreatedAt;
use Osumi\OsumiFramework\ORM\OField;
use Osumi\OsumiFramework\ORM\OModel;
use Osumi\OsumiFramework\ORM\OPK;
use Osumi\OsumiFramework\ORM\OUpdatedAt;

final class TemporalBigIntModel extends OModel {
    #[OPK(
        type: OField::BIGINT
    )]
    public ?int $id = null;

    #[OField(
        type: OField::DATE,
        nullable: false
    )]
    public ?string $event_date = null;

    #[OField(
        type: OField::DATETIME,
        nullable: false
    )]
    public ?string $event_datetime = null;

    #[OCreatedAt]
    public ?string $created_at = null;

    #[OUpdatedAt]
    public ?string $updated_at = null;

    /**
     * Validate the current model values for testing.
     *
     * @return void
     */
    public function validateForTest(): void {
        $this->validate();
    }
}
