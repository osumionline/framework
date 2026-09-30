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
    public int $max;

    /**
     * Create a primary key field definition.
     *
     * @param string $type Field type.
     * @param bool $incr Whether the field is auto-incremental.
     * @param string $comment Database column comment.
     * @param string $ref Foreign key reference.
     * @param bool $nullable Whether the field accepts null values.
     * @param mixed $default Default value applied to new records when the field
     *                       is null and used when generating the SQL column.
     * @param int $max Maximum field size for textual primary keys.
     */
    public function __construct(
        string $type = OField::NUMBER,
        bool $incr = true,
        string $comment = '',
        string $ref = '',
        bool $nullable = true,
        mixed $default = null,
        int $max = 50
    ) {
        $this->type = $type;
        $this->incr = $incr;
        $this->comment = $comment;
        $this->ref = $ref;
        $this->nullable = $nullable;
        $this->default = $default;
        $this->max = $max;
    }
}
