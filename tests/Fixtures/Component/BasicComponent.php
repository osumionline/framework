<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\Fixtures\Component;

use Osumi\OsumiFramework\Core\OComponent;

final class BasicComponent extends OComponent {
    public string $name = 'Default';
    public string $text = '';
    public string $json_text = '';
    public bool $enabled = false;
    public ?object $item = null;
}
