<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\{{uc_module}}Module\Actions\{{uc_action}};

use Osumi\OsumiFramework\Routing\OModuleAction;
use Osumi\OsumiFramework\Routing\OAction;
use Osumi\OsumiFramework\Web\ORequest;

#[OModuleAction(
	url: '{{url}}'{{type}}{{layout}}{{utils}}
)]
class {{uc_action}}Action extends OAction {
	/**
   * {{str_template}}
	 *
	 * @param ORequest $req Request object with method, headers, parameters and filters used
	 * @return void
	 */
	public function run(ORequest $req): void {}
}
