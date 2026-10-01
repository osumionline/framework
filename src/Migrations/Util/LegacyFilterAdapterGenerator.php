<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Migrations\Util;

use Osumi\OsumiFramework\Migrations\ValueObject\LegacyFilterDefinition;

final class LegacyFilterAdapterGenerator {
    /**
     * Generate a Middleware adapter for a legacy Filter.
     *
     * The generated class preserves the legacy Filter implementation and adapts
     * its result to the 9.9 middleware contract.
     *
     * @param LegacyFilterDefinition $definition Legacy Filter definition.
     *
     * @return string Generated Middleware PHP source.
     */
    public function generate(
        LegacyFilterDefinition $definition
    ): string {
        $namespace = $definition->middleware_namespace;
        $middleware_class = $definition->middleware_class;
        $filter_class = $definition->filter_class;
        $filter_fqcn = $definition->filter_fqcn;

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use {$filter_fqcn};
use Osumi\\OsumiFramework\\Core\\OMiddleware;

/**
 * Compatibility middleware generated automatically from {$filter_class}.
 */
final class {$middleware_class} {
	/**
	 * Adapt the legacy Filter result to the middleware pipeline.
	 *
	 * @param string \$phase Current middleware phase.
	 * @param array<string, mixed> \$data Current middleware pipeline data.
	 *
	 * @return array<string, mixed> Middleware result.
	 *
	 * @throws \\UnexpectedValueException If the legacy Filter returns invalid data.
	 */
	public static function handle(
		string \$phase,
		array \$data
	): array {
		if (\$phase !== OMiddleware::PHASE_BEFORE) {
			return [];
		}

		\$params = \$data['params'] ?? [];
		\$headers = \$data['headers'] ?? [];

		if (
			!is_array(\$params) ||
			!is_array(\$headers)
		) {
			throw new \\UnexpectedValueException(
				'Legacy Filter middleware received invalid request data.'
			);
		}

		\$filter = new {$filter_class}();

		\$result = \$filter->handle(
			\$params,
			\$headers
		);

		if (!is_array(\$result)) {
			throw new \\UnexpectedValueException(
				'Legacy Filter {$filter_class} must return an array.'
			);
		}

		if (
			(\$result['status'] ?? null) === 'ok'
		) {
			return [
				'context' => \$result
			];
		}

		\$redirect = \$result['return']
			?? null;

		if (\$redirect !== null) {
			if (
				!is_string(\$redirect) ||
				\$redirect === ''
			) {
				throw new \\UnexpectedValueException(
					"Legacy Filter {$filter_class} returned an invalid redirect URL."
				);
			}

			return [
				'stop' => true,
				'status_code' => 302,
				'headers' => [
					'Location' => \$redirect
				],
				'message' => ''
			];
		}

		return [
			'stop' => true,
			'status_code' => 403,
			'message' => ''
		];
	}
}

PHP;
    }
}
