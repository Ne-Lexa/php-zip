<?php

namespace PhpZip\Model\Entry;

use PhpZip\Model\ZipEntry;

/**
 * Entry to write to the central directory.
 *
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class OutputOffsetEntry
{
    /**
     * @var int
     */
    private $offset;
    /**
     * @var ZipEntry
     */
    private $entry;

    /**
     * @param int $pos
     * @param ZipEntry $entry
     */
    public function __construct($pos, ZipEntry $entry)
    {
        $this->offset = $pos;
        $this->entry = $entry;
    }

    /**
     * @return int
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * @return ZipEntry
     */
    public function getEntry()
    {
        return $this->entry;
    }
}
