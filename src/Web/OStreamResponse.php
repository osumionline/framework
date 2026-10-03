<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Web;

use RuntimeException;

final class OStreamResponse {
  private mixed $stream;

  /**
   * Create a streamed HTTP response.
   *
   * The supplied resource remains owned by this response and can optionally be
   * closed automatically after emission or when the response is discarded.
   *
   * @param resource              $stream          Readable stream resource.
   * @param array<string, string> $headers         Response headers.
   * @param int                   $status_code     HTTP status code.
   * @param int                   $chunk_size      Number of bytes read per iteration.
   * @param bool                  $close_on_finish Whether the stream must be closed automatically.
   *
   * @throws \InvalidArgumentException If any response option is invalid.
   */
  public function __construct(
    mixed $stream,
    private readonly array $headers = [],
    private readonly int $status_code = 200,
    private readonly int $chunk_size = 1048576,
    private readonly bool $close_on_finish = true
  ) {
    if (
      !is_resource($stream) ||
      get_resource_type($stream) !== 'stream'
    ) {
      throw new \InvalidArgumentException(
        'OStreamResponse requires a valid stream resource.'
      );
    }

    if (
      $this->status_code < 100 ||
      $this->status_code > 599
    ) {
      throw new \InvalidArgumentException(
        'OStreamResponse status code must be between 100 and 599.'
      );
    }

    if ($this->chunk_size <= 0) {
      throw new \InvalidArgumentException(
        'OStreamResponse chunk size must be greater than zero.'
      );
    }

    foreach ($this->headers as $name => $value) {
      if (
        !is_string($name) ||
        $name === '' ||
        !is_string($value)
      ) {
        throw new \InvalidArgumentException(
          'OStreamResponse headers must contain string names and values.'
        );
      }

      if (
        str_contains(
          $value,
          "\r"
        ) ||
        str_contains(
          $value,
          "\n"
        )
      ) {
        throw new \InvalidArgumentException(
          "Invalid HTTP header value for '{$name}'."
        );
      }
    }

    $this->stream = $stream;
  }

  /**
   * Get the readable stream.
   *
   * @return resource Stream resource.
   *
   * @throws RuntimeException When the stream has already been closed.
   */
  public function getStream(): mixed {
    if (
      !is_resource($this->stream) ||
      get_resource_type($this->stream) !== 'stream'
    ) {
      throw new RuntimeException(
        'OStreamResponse stream is already closed.'
      );
    }

    return $this->stream;
  }

  /**
   * Get response headers.
   *
   * @return array<string, string> Response headers.
   */
  public function getHeaders(): array {
    return $this->headers;
  }

  /**
   * Get the response HTTP status code.
   *
   * @return int HTTP status code.
   */
  public function getStatusCode(): int {
    return $this->status_code;
  }

  /**
   * Get the stream read chunk size.
   *
   * @return int Chunk size in bytes.
   */
  public function getChunkSize(): int {
    return $this->chunk_size;
  }

  /**
   * Check whether the stream must be closed after use.
   *
   * @return bool True when the framework owns stream closing.
   */
  public function shouldCloseOnFinish(): bool {
    return $this->close_on_finish;
  }

  /**
   * Check whether the stream is still open.
   *
   * @return bool True when the underlying stream is available.
   */
  public function isOpen(): bool {
    return is_resource($this->stream)
      && get_resource_type($this->stream) === 'stream';
  }

  /**
   * Close the underlying stream when it is still open.
   *
   * This operation is idempotent.
   *
   * @return void
   */
  public function close(): void {
    if (!$this->isOpen()) {
      return;
    }

    fclose(
      $this->stream
    );
  }
}