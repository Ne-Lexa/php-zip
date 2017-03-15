<?php
namespace PhpZip\Model\Entry;

use PhpZip\Exception\ZipException;

/**
 * New zip entry from empty dir.
 *
 * @see https://pkware.cachefly.net/webdocs/casestudies/APPNOTE.TXT .ZIP File Format Specification
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class ZipNewEmptyDirEntry extends ZipNewEntry
{

    /**
     * Returns an string content of the given entry.
     *
     * @return null|string
     * @throws ZipException
     */
    public function getEntryContent()
    {
        return null;
    }
}