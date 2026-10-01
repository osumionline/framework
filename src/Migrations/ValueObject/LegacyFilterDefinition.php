<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Migrations\ValueObject;

final readonly class LegacyFilterDefinition {
    /**
     * Describe a legacy Filter and the Middleware adapter generated for it.
     *
     * @param string $filter_relative_path Project-relative Filter file path.
     * @param string $filter_namespace Legacy Filter namespace.
     * @param string $filter_class Legacy Filter short class name.
     * @param string $filter_fqcn Legacy Filter fully-qualified class name.
     * @param string $middleware_relative_path Project-relative Middleware file path.
     * @param string $middleware_namespace Generated Middleware namespace.
     * @param string $middleware_class Generated Middleware short class name.
     * @param string $middleware_fqcn Generated Middleware fully-qualified class name.
     */
    public function __construct(
        public string $filter_relative_path,
        public string $filter_namespace,
        public string $filter_class,
        public string $filter_fqcn,
        public string $middleware_relative_path,
        public string $middleware_namespace,
        public string $middleware_class,
        public string $middleware_fqcn
    ) {
    }
}
