<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\Core;

use Osumi\OsumiFramework\Web\ORequest;

interface ODTO {
	public function isValid(): bool;
	public function load(ORequest $req): void;
}