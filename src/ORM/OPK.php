<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\ORM;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class OPK {
    public string $type;
    public bool $incr;
    public string $comment;
    public string $ref;
    public bool $nullable;
    public mixed $default;

    /**
     * Create a primary key field definition.
     *
     * @param string $type Field type. Defaults to OField::NUMBER.
     * @param bool $incr Whether the field is auto-incremental.
     * @param string $comment Database column comment.
     * @param string $ref Foreign key reference.
     * @param bool $nullable Whether the field accepts null values.
     * @param mixed $default Default value applied to new records when the field is
     *                       null. Also used when generating the SQL column
     *                       definition.
     */
    public function __construct(
        string $type = OField::NUMBER,
        bool $incr = true,
        string $comment = '',
        string $ref = '',
        bool $nullable = true,
        mixed $default = null
    ) {
        $this->type     = $type;
        $this->incr     = $incr;
        $this->comment  = $comment;
        $this->ref      = $ref;
        $this->nullable = $nullable;
        $this->default  = $default;
    }
}
