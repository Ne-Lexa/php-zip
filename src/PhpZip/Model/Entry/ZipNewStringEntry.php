<?php
namespace PhpZip\Model\Entry;

use PhpZip\Exception\ZipException;

/**
 * New zip entry from string.
 *
 * @see https://pkware.cachefly.net/webdocs/casestudies/APPNOTE.TXT .ZIP File Format Specification
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class ZipNewStringEntry extends ZipNewEntry
{
    /**
     * @var string
     */
    private $entryContent;

    /**
     * ZipNewStringEntry constructor.
     * @param string $entryContent
     */
    public function __construct($entryContent)
    {
        $this->entryContent = $entryContent;
    }

    /**
     * Returns an string content of the given entry.
     *
     * @return null|string
     * @throws ZipException
     */
    public function getEntryContent()
    {
        return $this->entryContent;
    }
}