<?php

namespace PhpZip\Model\Entry;

use PhpZip\Exception\ZipException;

/**
 * Source Entry Changes
 *
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class ZipChangesEntry extends ZipAbstractEntry
{
    /**
     * @var ZipSourceEntry
     */
    protected $entry;

    /**
     * ZipChangesEntry constructor.
     * @param ZipSourceEntry $entry
     * @throws ZipException
     * @throws \PhpZip\Exception\InvalidArgumentException
     */
    public function __construct(ZipSourceEntry $entry)
    {
        parent::__construct();
        $this->entry = $entry;
        $this->setEntry($entry);
    }

    /**
     * @return bool
     */
    public function isChangedContent()
    {
        return !(
            $this->getCompressionLevel() === $this->entry->getCompressionLevel() &&
            $this->getMethod() === $this->entry->getMethod() &&
            $this->isEncrypted() === $this->entry->isEncrypted() &&
            $this->getEncryptionMethod() === $this->entry->getEncryptionMethod() &&
            $this->getPassword() === $this->entry->getPassword()
        );
    }

    /**
     * Returns an string content of the given entry.
     *
     * @return null|string
     * @throws ZipException
     */
    public function getEntryContent()
    {
        return $this->entry->getEntryContent();
    }

    /**
     * @return ZipSourceEntry
     */
    public function getSourceEntry()
    {
        return $this->entry;
    }
}
