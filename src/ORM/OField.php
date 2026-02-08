<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\ORM;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class OField {
    // Tipos de campo
    public const NUMBER   = 'NUMBER';
    public const TEXT     = 'TEXT';
    public const LONGTEXT = 'LONGTEXT';
    public const FLOAT    = 'FLOAT';
    public const BOOL     = 'BOOL';
    public const DATE     = 'DATE';

    public ?string $type;
    public bool $nullable;
    public mixed $default;
    public int $max;
    public string $comment;
    public bool $visible;
    public string $ref;

    /**
     * OField constructor.
     *
     * @param string | null $type Field type (mandatory).
     *
     * @param bool $nullable Indicates whether the field can be null. Default is true.
     *
     * @param mixed $default Default value if null or undefined. Defaults to null.
     *
     * @param int $max Maximum field size. Default is 50.
     *
     * @param string $comment Field comment. Default is empty string.
     *
     * @param bool $visible Indicates whether the field is visible when serializing. Defaults to true.
     *
     * @param string $ref Reference to another table and field. Default is empty string.
     */
    public function __construct(
        string | null $type = null,
        bool $nullable = true,
        mixed $default = null,
        int $max = 50,
        string $comment = '',
        bool $visible = true,
        string $ref = ''
    ) {
        $this->type     = $type;
        $this->nullable = $nullable;
        $this->default  = $default;
        $this->max      = $max;
        $this->comment  = $comment;
        $this->visible  = $visible;
        $this->ref      = $ref;
    }
}
