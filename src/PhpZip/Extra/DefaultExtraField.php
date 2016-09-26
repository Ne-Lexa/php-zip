<?php
namespace PhpZip\Extra;

use PhpZip\Exception\ZipException;

/**
 * Default implementation for an Extra Field in a Local or Central Header of a
 * ZIP file.
 *
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class DefaultExtraField extends ExtraField
{
    /**
     * @var int
     */
    private static $headerId;

    /**
     * @var string
     */
    private $data;

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
     * Returns the Data Size of this Extra Field.
     * The Data Size is an unsigned short integer (two bytes)
     * which indicates the length of the Data Block in bytes and does not
     * include its own size in this Extra Field.
     * This property may be initialized by calling ExtraField::readFrom.
     *
     * @return int The size of the Data Block in bytes
     *         or 0 if unknown.
     */
    public function getDataSize()
    {
        return null !== $this->data ? strlen($this->data) : 0;
    }

    /**
     * Initializes this Extra Field by deserializing a Data Block of
     * size bytes $size from the resource $handle at the zero based offset $off.
     *
     * @param resource $handle
     * @param int $off Offset bytes
     * @param int $size Size
     * @throws ZipException
     */
    public function readFrom($handle, $off, $size)
    {
        if (0x0000 > $size || $size > 0xffff) {
            throw new ZipException('size out of range');
        }
        if ($size > 0) {
            fseek($handle, $off, SEEK_SET);
            $this->data = fread($handle, $size);
        }
    }

    /**
     * @param resource $handle
     * @param int $off
     */
    public function writeTo($handle, $off)
    {
        if (null !== $this->data) {
            fseek($handle, $off, SEEK_SET);
            fwrite($handle, $this->data);
        }
    }
}