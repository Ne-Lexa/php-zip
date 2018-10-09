<?php

namespace PhpZip\Model;

use PhpZip\Exception\InvalidArgumentException;
use PhpZip\Exception\ZipEntryNotFoundException;
use PhpZip\Exception\ZipException;
use PhpZip\Model\Entry\ZipChangesEntry;
use PhpZip\Model\Entry\ZipSourceEntry;
use PhpZip\ZipFileInterface;

/**
 * Zip Model
 *
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class ZipModel implements \Countable
{
    /**
     * @var ZipSourceEntry[]
     */
    protected $inputEntries = [];
    /**
     * @var ZipEntry[]
     */
    protected $outEntries = [];
    /**
     * @var string|null
     */
    protected $archiveComment;
    /**
     * @var string|null
     */
    protected $archiveCommentChanges;
    /**
     * @var bool
     */
    protected $archiveCommentChanged = false;
    /**
     * @var int|null
     */
    protected $zipAlign;
    /**
     * @var bool
     */
    private $zip64;

    /**
     * @param ZipSourceEntry[] $entries
     * @param EndOfCentralDirectory $endOfCentralDirectory
     * @return ZipModel
     */
    public static function newSourceModel(array $entries, EndOfCentralDirectory $endOfCentralDirectory)
    {
        $model = new self;
        $model->inputEntries = $entries;
        $model->outEntries = $entries;
        $model->archiveComment = $endOfCentralDirectory->getComment();
        $model->zip64 = $endOfCentralDirectory->isZip64();
        return $model;
    }

    /**
     * @return null|string
     */
    public function getArchiveComment()
    {
        if ($this->archiveCommentChanged) {
            return $this->archiveCommentChanges;
        }
        return $this->archiveComment;
    }

    /**
     * @param string $comment
     */
    public function setArchiveComment($comment)
    {
        if ($comment !== null && strlen($comment) !== 0) {
            $comment = (string)$comment;
            $length = strlen($comment);
            if (0x0000 > $length || $length > 0xffff) {
                throw new InvalidArgumentException('Length comment out of range');
            }
        }
        if ($comment !== $this->archiveComment) {
            $this->archiveCommentChanges = $comment;
            $this->archiveCommentChanged = true;
        } else {
            $this->archiveCommentChanged = false;
        }
    }

    /**
     * Specify a password for extracting files.
     *
     * @param null|string $password
     * @throws ZipException
     */
    public function setReadPassword($password)
    {
        foreach ($this->inputEntries as $entry) {
            if ($entry->isEncrypted()) {
                $entry->setPassword($password);
            }
        }
    }

    /**
     * @param string $entryName
     * @param string $password
     * @throws ZipEntryNotFoundException
     * @throws ZipException
     */
    public function setReadPasswordEntry($entryName, $password)
    {
        if (!isset($this->inputEntries[$entryName])) {
            throw new ZipEntryNotFoundException($entryName);
        }
        if ($this->inputEntries[$entryName]->isEncrypted()) {
            $this->inputEntries[$entryName]->setPassword($password);
        }
    }

    /**
     * @return int|null
     */
    public function getZipAlign()
    {
        return $this->zipAlign;
    }

    /**
     * @param int|null $zipAlign
     */
    public function setZipAlign($zipAlign)
    {
        $this->zipAlign = $zipAlign === null ? null : (int)$zipAlign;
    }

    /**
     * @return bool
     */
    public function isZipAlign()
    {
        return $this->zipAlign != null;
    }

    /**
     * @param null|string $writePassword
     */
    public function setWritePassword($writePassword)
    {
        $this->matcher()->all()->setPassword($writePassword);
    }

    /**
     * Remove password
     */
    public function removePassword()
    {
        $this->matcher()->all()->setPassword(null);
    }

    /**
     * @param string|ZipEntry $entryName
     */
    public function removePasswordEntry($entryName)
    {
        $this->matcher()->add($entryName)->setPassword(null);
    }

    /**
     * @return bool
     */
    public function isArchiveCommentChanged()
    {
        return $this->archiveCommentChanged;
    }

    /**
     * @param string|ZipEntry $old
     * @param string|ZipEntry $new
     * @throws ZipException
     */
    public function renameEntry($old, $new)
    {
        $old = $old instanceof ZipEntry ? $old->getName() : (string)$old;
        $new = $new instanceof ZipEntry ? $new->getName() : (string)$new;

        if (isset($this->outEntries[$new])) {
            throw new InvalidArgumentException("New entry name " . $new . ' is exists.');
        }

        $entry = $this->getEntryForChanges($old);
        $entry->setName($new);
        $this->deleteEntry($old);
        $this->addEntry($entry);
    }

    /**
     * @param string|ZipEntry $entry
     * @return ZipChangesEntry|ZipEntry
     * @throws ZipException
     * @throws ZipEntryNotFoundException
     */
    public function getEntryForChanges($entry)
    {
        $entry = $this->getEntry($entry);
        if ($entry instanceof ZipSourceEntry) {
            $entry = new ZipChangesEntry($entry);
            $this->addEntry($entry);
        }
        return $entry;
    }

    /**
     * @param string|ZipEntry $entryName
     * @return ZipEntry
     * @throws ZipEntryNotFoundException
     */
    public function getEntry($entryName)
    {
        $entryName = $entryName instanceof ZipEntry ? $entryName->getName() : (string)$entryName;
        if (isset($this->outEntries[$entryName])) {
            return $this->outEntries[$entryName];
        }
        throw new ZipEntryNotFoundException($entryName);
    }

    /**
     * @param string|ZipEntry $entry
     * @return bool
     */
    public function deleteEntry($entry)
    {
        $entry = $entry instanceof ZipEntry ? $entry->getName() : (string)$entry;
        if (isset($this->outEntries[$entry])) {
            unset($this->outEntries[$entry]);
            return true;
        }
        return false;
    }

    /**
     * @param ZipEntry $entry
     */
    public function addEntry(ZipEntry $entry)
    {
        $this->outEntries[$entry->getName()] = $entry;
    }

    /**
     * Get all entries with changes.
     *
     * @return ZipEntry[]
     */
    public function &getEntries()
    {
        return $this->outEntries;
    }

    /**
     * @param string|ZipEntry $entryName
     * @return bool
     */
    public function hasEntry($entryName)
    {
        $entryName = $entryName instanceof ZipEntry ? $entryName->getName() : (string)$entryName;
        return isset($this->outEntries[$entryName]);
    }

    /**
     * Delete all entries.
     */
    public function deleteAll()
    {
        $this->outEntries = [];
    }

    /**
     * Count elements of an object
     * @link http://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     * </p>
     * <p>
     * The return value is cast to an integer.
     * @since 5.1.0
     */
    public function count()
    {
        return sizeof($this->outEntries);
    }

    /**
     * Undo all changes done in the archive
     */
    public function unchangeAll()
    {
        $this->outEntries = $this->inputEntries;
        $this->unchangeArchiveComment();
    }

    /**
     * Undo change archive comment
     */
    public function unchangeArchiveComment()
    {
        $this->archiveCommentChanges = null;
        $this->archiveCommentChanged = false;
    }

    /**
     * Revert all changes done to an entry with the given name.
     *
     * @param string|ZipEntry $entry Entry name or ZipEntry
     * @return bool
     */
    public function unchangeEntry($entry)
    {
        $entry = $entry instanceof ZipEntry ? $entry->getName() : (string)$entry;
        if (isset($this->outEntries[$entry]) && isset($this->inputEntries[$entry])) {
            $this->outEntries[$entry] = $this->inputEntries[$entry];
            return true;
        }
        return false;
    }

    /**
     * @param int $encryptionMethod
     */
    public function setEncryptionMethod($encryptionMethod = ZipFileInterface::ENCRYPTION_METHOD_WINZIP_AES_256)
    {
        $this->matcher()->all()->setEncryptionMethod($encryptionMethod);
    }

    /**
     * @return ZipEntryMatcher
     */
    public function matcher()
    {
        return new ZipEntryMatcher($this);
    }
}
