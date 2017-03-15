<?php
namespace PhpZip\Extra;

use PhpZip\Exception\ZipException;

/**
 * Abstract base class for an Extra Field in a Local or Central Header of a
 * ZIP archive.
 * It defines the common properties of all Extra Fields and how to
 * serialize/deserialize them to/from byte arrays.
 *
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
abstract class ExtraField implements ExtraFieldHeader
{
    /** The Header ID for a ZIP64 Extended Information Extra Field. */
    const ZIP64_HEADER_ID = 0x0001;

    /**
     * @var array|null
     */
    private static $registry;

    /**
     * A static factory method which creates a new Extra Field based on the
     * given Header ID.
     * The returned Extra Field still requires proper initialization, for
     * example by calling ExtraField::readFrom.
     *
     * @param int $headerId An unsigned short integer (two bytes) which indicates
     *         the type of the returned Extra Field.
     * @return ExtraField A new Extra Field or null if not support header id.
     * @throws ZipException If headerId is out of range.
     */
    public static function create($headerId)
    {
        if (0x0000 > $headerId || $headerId > 0xffff) {
            throw new ZipException('headerId out of range');
        }

        /**
         * @var ExtraField $extraField
         */
        if (isset(self::getRegistry()[$headerId])) {
            $extraClassName = self::getRegistry()[$headerId];
            $extraField = new $extraClassName;
            if ($extraField::getHeaderId() !== $headerId) {
                throw new ZipException('Runtime error support headerId ' . $headerId);
            }
        } else {
            $extraField = new DefaultExtraField($headerId);
        }
        return $extraField;
    }

    /**
     * Registered extra field classes.
     *
     * @return array|null
     */
    private static function getRegistry()
    {
        if (null === self::$registry) {
            self::$registry[WinZipAesEntryExtraField::getHeaderId()] = WinZipAesEntryExtraField::class;
            self::$registry[NtfsExtraField::getHeaderId()] = NtfsExtraField::class;
        }
        return self::$registry;
    }

    /**
     * Returns a protective copy of the Data Block.
     *
     * @return resource
     * @throws ZipException If size data block out of range.
     */
    public function getDataBlock()
    {
        $size = $this->getDataSize();
        if (0x0000 > $size || $size > 0xffff) {
            throw new ZipException('size data block out of range.');
        }
        $fp = fopen('php://memory', 'r+b');
        if (0 === $size) return $fp;
        $this->writeTo($fp, 0);
        rewind($fp);
        return $fp;
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
    abstract public function getDataSize();

    /**
     * Serializes a Data Block of ExtraField::getDataSize bytes to the
     * resource $handle at the zero based offset $off.
     *
     * @param resource $handle
     * @param int $off Offset bytes
     */
    abstract public function writeTo($handle, $off);

    /**
     * Initializes this Extra Field by deserializing a Data Block of
     * size bytes $size from the resource $handle at the zero based offset $off.
     *
     * @param resource $handle
     * @param int $off Offset bytes
     * @param int $size Size
     */
    abstract public function readFrom($handle, $off, $size);
}