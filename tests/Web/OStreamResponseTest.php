<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Tests\Web;

use Osumi\OsumiFramework\Web\OStreamResponse;
use PHPUnit\Framework\TestCase;

final class OStreamResponseTest extends TestCase {
    /**
     * Test creation with valid stream response options.
     *
     * @return void
     */
    public function testValidStreamResponse(): void {
        $stream = fopen(
            'php://temp',
            'w+b'
        );

        self::assertIsResource(
            $stream
        );

        fwrite(
            $stream,
            'stream-content'
        );

        rewind(
            $stream
        );

        $response = new OStreamResponse(
            $stream,
            [
                'Content-Type' => 'application/octet-stream',
                'Content-Length' => '14'
            ],
            206,
            4096
        );

        self::assertSame(
            $stream,
            $response->getStream()
        );

        self::assertSame(
            [
                'Content-Type' => 'application/octet-stream',
                'Content-Length' => '14'
            ],
            $response->getHeaders()
        );

        self::assertSame(
            206,
            $response->getStatusCode()
        );

        self::assertSame(
            4096,
            $response->getChunkSize()
        );

        self::assertTrue(
            $response->shouldCloseOnFinish()
        );

        self::assertTrue(
            $response->isOpen()
        );

        $response->close();

        self::assertFalse(
            $response->isOpen()
        );
    }

    /**
     * Test that closing a response stream more than once is safe.
     *
     * @return void
     */
    public function testCloseIsIdempotent(): void {
        $stream = fopen(
            'php://temp',
            'w+b'
        );

        self::assertIsResource(
            $stream
        );

        $response = new OStreamResponse(
            $stream
        );

        $response->close();
        $response->close();

        self::assertFalse(
            $response->isOpen()
        );
    }

    /**
     * Test rejection of non-resource values.
     *
     * @return void
     */
    public function testInvalidStreamIsRejected(): void {
        $this->expectException(
            \InvalidArgumentException::class
        );

        new OStreamResponse(
            'not-a-stream'
        );
    }

    /**
     * Test rejection of invalid HTTP status codes.
     *
     * @return void
     */
    public function testInvalidStatusCodeIsRejected(): void {
        $stream = fopen(
            'php://temp',
            'w+b'
        );

        self::assertIsResource(
            $stream
        );

        try {
            $this->expectException(
                \InvalidArgumentException::class
            );

            new OStreamResponse(
                $stream,
                [],
                99
            );
        } finally {
            fclose(
                $stream
            );
        }
    }

    /**
     * Test rejection of invalid chunk sizes.
     *
     * @return void
     */
    public function testInvalidChunkSizeIsRejected(): void {
        $stream = fopen(
            'php://temp',
            'w+b'
        );

        self::assertIsResource(
            $stream
        );

        try {
            $this->expectException(
                \InvalidArgumentException::class
            );

            new OStreamResponse(
                $stream,
                [],
                200,
                0
            );
        } finally {
            fclose(
                $stream
            );
        }
    }

    /**
     * Test rejection of response header injection.
     *
     * @return void
     */
    public function testHeaderInjectionIsRejected(): void {
        $stream = fopen(
            'php://temp',
            'w+b'
        );

        self::assertIsResource(
            $stream
        );

        try {
            $this->expectException(
                \InvalidArgumentException::class
            );

            new OStreamResponse(
                $stream,
                [
                    'X-Test' => "value\r\nInjected: true"
                ]
            );
        } finally {
            fclose(
                $stream
            );
        }
    }

    /**
     * Test access to an already closed stream.
     *
     * @return void
     */
    public function testClosedStreamCannotBeRetrieved(): void {
        $stream = fopen(
            'php://temp',
            'w+b'
        );

        self::assertIsResource(
            $stream
        );

        $response = new OStreamResponse(
            $stream
        );

        $response->close();

        $this->expectException(
            \RuntimeException::class
        );

        $response->getStream();
    }

    /**
     * Test rejection of unreadable streams.
     *
     * @return void
     */
    public function testUnreadableStreamIsRejected(): void {
        $file_path = tempnam(
            sys_get_temp_dir(),
            'ofw-stream-'
        );

        self::assertIsString(
            $file_path
        );

        $stream = fopen(
            $file_path,
            'wb'
        );

        self::assertIsResource(
            $stream
        );

        try {
            $this->expectException(
                \InvalidArgumentException::class
            );

            $this->expectExceptionMessage(
                'requires a readable stream'
            );

            new OStreamResponse(
                $stream
            );
        } finally {
            fclose(
                $stream
            );

            unlink(
                $file_path
            );
        }
    }

    /**
     * Test rejection of invalid HTTP header names.
     *
     * @return void
     */
    public function testInvalidHeaderNameIsRejected(): void {
        $stream = fopen(
            'php://temp',
            'w+b'
        );

        self::assertIsResource(
            $stream
        );

        try {
            $this->expectException(
                \InvalidArgumentException::class
            );

            new OStreamResponse(
                $stream,
                [
                    "Invalid Header" => 'value'
                ]
            );
        } finally {
            fclose(
                $stream
            );
        }
    }
}
