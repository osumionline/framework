<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Filter;

class {{name}}Filter {
	/**
	 * {{description}}
	 *
	 * @param array $params Parameter array received on the call
	 *
	 * @param array $headers HTTP header array received on the call
	 *
	 * @return array Return filter status (ok / error) and information
	 */
	public static function handle(array $params, array $headers): array {
		$ret = ['status'=>'ok'];

		return $ret;
	}
}
