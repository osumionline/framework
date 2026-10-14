<?php

declare(strict_types=1);

use Osumi\OsumiFramework\Migrations\Steps\V9_9_0;
use Osumi\OsumiFramework\Migrations\Steps\V9_12_0;

return [
    [
        'since' => '9.9.0',
        'step' => V9_9_0::class
    ],
    [
        'since' => '9.12.0',
        'step' => V9_12_0::class
    ]
];
