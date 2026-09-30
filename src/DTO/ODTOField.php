<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\DTO;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ODTOField {
    /**
     * Define how a DTO property is populated and validated.
     *
     * @param bool $required Whether the property must contain a value.
     * @param string|null $requiredIf Property that makes this field required when
     *                                it contains a value.
     * @param string|null $filter Filter result source.
     * @param string|null $filterProperty Property read from the filter result.
     * @param string|null $header HTTP header used as the field source.
     */
    public function __construct(
        public bool $required = false,
        public ?string $requiredIf = null,
        public ?string $filter = null,
        public ?string $filterProperty = null,
        public ?string $header = null
    ) {
    }
}
