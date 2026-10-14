<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\Fixtures\ORM;

use Osumi\OsumiFramework\ORM\OCreatedAt;
use Osumi\OsumiFramework\ORM\OField;
use Osumi\OsumiFramework\ORM\OModel;
use Osumi\OsumiFramework\ORM\OPK;
use Osumi\OsumiFramework\ORM\OUpdatedAt;

final class InvalidCurrentTimestampDefaultModel extends OModel {
    #[OPK]
    public ?int $id = null;

    #[OField(
        type: OField::TEXT,
        default: OField::CURRENT_TIMESTAMP
    )]
    public ?string $name = null;

    #[OCreatedAt]
    public ?string $created_at = null;

    #[OUpdatedAt]
    public ?string $updated_at = null;
}
