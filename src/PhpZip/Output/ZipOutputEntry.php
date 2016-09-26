<?php
namespace PhpZip\Output;

use PhpZip\Model\ZipEntry;

/**
 * Zip output Entry
 *
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
abstract class ZipOutputEntry
{
    /**
     * @var ZipEntry
     */
    private $entry;

    /**
     * @param ZipEntry $entry
     */
    public function __construct(ZipEntry $entry)
    {
        if ($entry === null) {
            throw new \RuntimeException('entry is null');
        }
        $this->entry = $entry;
    }

    /**
     * Returns zip entry
     *
     * @return ZipEntry
     */
    public function getEntry()
    {
        return $this->entry;
    }

    /**
     * Returns entry data.
     *
     * @return string
     */
    abstract public function getEntryContent();
}