<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\DTO;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ODTOField {
    public function __construct(
        public bool $required = false,
        public ?string $requiredIf = null,
        public ?string $filter = null,
        public ?string $filterProperty = null
    ) {}
}
