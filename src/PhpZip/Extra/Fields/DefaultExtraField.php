<?php

namespace PhpZip\Extra\Fields;

use PhpZip\Exception\ZipException;
use PhpZip\Extra\ExtraField;

/**
 * Default implementation for an Extra Field in a Local or Central Header of a
 * ZIP file.
 *
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class DefaultExtraField implements ExtraField
{
    /**
     * @var int
     */
    private static $headerId;

    /**
     * @var string
     */
    protected $data;

    /**
     * Constructs a new Extra Field.
     *
     * @param int $headerId an unsigned short integer (two bytes) indicating the
     *         type of the Extra Field.
     * @throws ZipException
     */
    public function __construct($headerId)
    {
        if (0x0000 > $headerId || $headerId > 0xffff) {
            throw new ZipException('headerId out of range');
        }
        self::$headerId = $headerId;
    }

    /**
     * Returns the Header ID (type) of this Extra Field.
     * The Header ID is an unsigned short integer (two bytes)
     * which must be constant during the life cycle of this object.
     *
     * @return int
     */
    public static function getHeaderId()
    {
        return self::$headerId & 0xffff;
    }

    /**
     * Serializes a Data Block.
     * @return string
     */
    public function serialize()
    {
        return $this->data;
    }

    /**
     * Initializes this Extra Field by deserializing a Data Block.
     * @param string $data
     */
    public function deserialize($data)
    {
        $this->data = $data;
    }
}
