<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\DTO;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ODTOField {
    public function __construct(
        public bool $required = false,
        public string | null $requiredIf = null,
        public string | null $filter = null,
        public string | null $filterProperty = null,
        public string | null $header = null
    ) {
    }
}
