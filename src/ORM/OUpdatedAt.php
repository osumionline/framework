<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\ORM;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class OUpdatedAt {
  public string $comment;

  /**
   * OCreatedAt constructor
   *
   * @param string $comment Comment for the column.
   */
  public function __construct(string $comment = '') {
    $this->comment = $comment;
  }
}
