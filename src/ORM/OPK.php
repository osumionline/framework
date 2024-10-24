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

    /**
     * OPK constructor
     *
     * @param string $type Field type. Default is OField::NUMBER.
     *
     * @param bool $incr Indicates whether it is auto-incremental. Default is true.
     *
     * @param string $comment Comment for the column.
     *
     * @param string $ref Reference to another table and field. Default is empty string.
     */
    public function __construct(
        string $type = OField::NUMBER,
        bool $incr = true,
        string $comment = '',
        string $ref = ''
    ) {
        $this->type    = $type;
        $this->incr    = $incr;
        $this->comment = $comment;
        $this->ref     = $ref;
    }
}
