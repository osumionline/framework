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
     * @param string|null $middleware Middleware public name used as the field source.
     * @param string|null $middlewareProperty Context property read from the middleware.
     * @param string|null $header HTTP header used as the field source.
     */
    public function __construct(
        public bool $required = false,
        public ?string $requiredIf = null,
        public ?string $middleware = null,
        public ?string $middlewareProperty = null,
        public ?string $header = null
    ) {
    }
}
