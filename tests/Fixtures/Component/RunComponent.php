<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\Fixtures\Component;

use Osumi\OsumiFramework\Core\OComponent;

final class RunComponent extends OComponent {
    public string $value = 'before';

    /**
     * Prepare the component before rendering.
     *
     * @return void
     */
    public function run(): void {
        $this->value = 'after';
    }
}
