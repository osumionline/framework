<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\Fixtures\Component;

use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Web\OStreamResponse;

final class StreamComponent extends OComponent {
    /**
     * Create a streamed component response.
     *
     * @return OStreamResponse Streamed response.
     */
    public function run(): OStreamResponse {
        $stream = fopen(
            'php://temp',
            'w+b'
        );

        if ($stream === false) {
            throw new \RuntimeException(
                'Could not create test stream.'
            );
        }

        fwrite(
            $stream,
            'streamed-content'
        );

        rewind(
            $stream
        );

        return new OStreamResponse(
            $stream,
            [
                'Content-Type' => 'application/octet-stream'
            ]
        );
    }
}
