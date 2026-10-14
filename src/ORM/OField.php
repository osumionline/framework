<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\ORM;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class OField {
    // Field types
    public const NUMBER   = 'NUMBER';
    public const BIGINT   = 'BIGINT';
    public const TEXT     = 'TEXT';
    public const LONGTEXT = 'LONGTEXT';
    public const FLOAT    = 'FLOAT';
    public const BOOL     = 'BOOL';
    public const DATE     = 'DATE';
    public const DATETIME = 'DATETIME';
    public const DECIMAL  = 'DECIMAL';

    public const CURRENT_TIMESTAMP = 'CURRENT_TIMESTAMP';

    public const DECIMAL_DEFAULT_PRECISION = 10;
    public const DECIMAL_DEFAULT_SCALE = 2;
    public const DECIMAL_MAX_PRECISION = 65;
    public const DECIMAL_MAX_SCALE = 30;

    public ?string $type;
    public bool $nullable;
    public mixed $default;
    public int $max;
    public string $comment;
    public bool $visible;
    public string $ref;
    public ?int $precision;
    public ?int $scale;

    /**
     * OField constructor.
     *
     * @param string|null $type Field type.
     * @param bool $nullable Indicates whether the field can be null. Default is true.
     * @param mixed $default Default value applied to new records when the field is
     *                       null. Also used when generating the SQL column definition.
     *                       Defaults to null.
     * @param int $max Maximum field size. Default is 50.
     * @param string $comment Field comment. Default is empty string.
     * @param bool $visible Indicates whether the field is visible when serializing.
     *                      Defaults to true.
     * @param string $ref Reference to another table and field. Default is empty string.
     * @param int|null $precision Total number of digits for DECIMAL fields. When null,
     *                            DECIMAL fields use the framework default precision.
     * @param int|null $scale Number of fractional digits for DECIMAL fields. When null,
     *                        DECIMAL fields use the framework default scale.
     */
    public function __construct(
        ?string $type = null,
        bool $nullable = true,
        mixed $default = null,
        int $max = 50,
        string $comment = '',
        bool $visible = true,
        string $ref = '',
        ?int $precision = null,
        ?int $scale = null
    ) {
        $this->type = $type;
        $this->nullable = $nullable;
        $this->default = $default;
        $this->max = $max;
        $this->comment = $comment;
        $this->visible = $visible;
        $this->ref = $ref;
        $this->precision = $precision;
        $this->scale = $scale;
    }
}
