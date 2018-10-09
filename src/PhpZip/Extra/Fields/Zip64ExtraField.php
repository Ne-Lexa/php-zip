<?php

namespace PhpZip\Extra\Fields;

use PhpZip\Exception\RuntimeException;
use PhpZip\Extra\ExtraField;
use PhpZip\Model\ZipEntry;
use PhpZip\Util\PackUtil;

/**
 * ZIP64 Extra Field
 *
 * @see https://pkware.cachefly.net/webdocs/casestudies/APPNOTE.TXT .ZIP File Format Specification
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class Zip64ExtraField implements ExtraField
{
    /** The Header ID for a ZIP64 Extended Information Extra Field. */
    const ZIP64_HEADER_ID = 0x0001;
    /**
     * @var ZipEntry
     */
    protected $entry;

    /**
     * Zip64ExtraField constructor.
     * @param ZipEntry $entry
     */
    public function __construct(ZipEntry $entry = null)
    {
        if ($entry !== null) {
            $this->setEntry($entry);
        }
    }

    /**
     * @param ZipEntry $entry
     */
    public function setEntry(ZipEntry $entry)
    {
        $this->entry = $entry;
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
        return 0x0001;
    }

    /**
     * Serializes a Data Block.
     * @return string
     */
    public function serialize()
    {
        if ($this->entry === null) {
            throw new RuntimeException("entry is null");
        }
        $data = '';
        // Write out Uncompressed Size.
        $size = $this->entry->getSize();
        if (0xffffffff <= $size) {
            $data .= PackUtil::packLongLE($size);
        }
        // Write out Compressed Size.
        $compressedSize = $this->entry->getCompressedSize();
        if (0xffffffff <= $compressedSize) {
            $data .= PackUtil::packLongLE($compressedSize);
        }
        // Write out Relative Header Offset.
        $offset = $this->entry->getOffset();
        if (0xffffffff <= $offset) {
            $data .= PackUtil::packLongLE($offset);
        }
        return $data;
    }

    /**
     * Initializes this Extra Field by deserializing a Data Block.
     * @param string $data
     * @throws \PhpZip\Exception\ZipException
     */
    public function deserialize($data)
    {
        if ($this->entry === null) {
            throw new RuntimeException("entry is null");
        }
        $off = 0;
        // Read in Uncompressed Size.
        $size = $this->entry->getSize();
        if (0xffffffff <= $size) {
            assert(0xffffffff === $size);
            $this->entry->setSize(PackUtil::unpackLongLE(substr($data, $off, 8)));
            $off += 8;
        }
        // Read in Compressed Size.
        $compressedSize = $this->entry->getCompressedSize();
        if (0xffffffff <= $compressedSize) {
            assert(0xffffffff === $compressedSize);
            $this->entry->setCompressedSize(PackUtil::unpackLongLE(substr($data, $off, 8)));
            $off += 8;
        }
        // Read in Relative Header Offset.
        $offset = $this->entry->getOffset();
        if (0xffffffff <= $offset) {
            assert(0xffffffff, $offset);
            $this->entry->setOffset(PackUtil::unpackLongLE(substr($data, $off, 8)));
        }
    }
}
