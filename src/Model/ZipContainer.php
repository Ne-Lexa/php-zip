<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Model;

use PhpZip\Constants\ZipEncryptionMethod;
use PhpZip\Exception\InvalidArgumentException;
use PhpZip\Exception\ZipEntryNotFoundException;
use PhpZip\Exception\ZipException;

/**
 * Zip Container.
 */
class ZipContainer extends ImmutableZipContainer
{
    /**
     * @var ImmutableZipContainer|null The source container contains zip entries from
     *                                 an open zip archive. The source container makes
     *                                 it possible to undo changes in the archive.
     *                                 When cloning, this container is not cloned.
     */
    private ?ImmutableZipContainer $sourceContainer;

    public function __construct(?ImmutableZipContainer $sourceContainer = null)
    {
        $entries = [];
        $archiveComment = null;

        if ($sourceContainer !== null) {
            foreach ($sourceContainer->getEntries() as $entryName => $entry) {
                $entries[$entryName] = clone $entry;
            }
            $archiveComment = $sourceContainer->getArchiveComment();
        }
        parent::__construct($entries, $archiveComment);
        $this->sourceContainer = $sourceContainer;
    }

    public function getSourceContainer(): ?ImmutableZipContainer
    {
        return $this->sourceContainer;
    }

    public function addEntry(ZipEntry $entry): void
    {
        $this->entries[$entry->getName()] = $entry;
    }

    /**
     * @param string|ZipEntry $entry
     */
    public function deleteEntry($entry): bool
    {
        $entry = $entry instanceof ZipEntry ? $entry->getName() : (string) $entry;

        if (isset($this->entries[$entry])) {
            unset($this->entries[$entry]);

            return true;
        }

        return false;
    }

    /**
     * @param string|ZipEntry $old
     * @param string|ZipEntry $new
     *
     * @throws ZipException
     *
     * @return ZipEntry New zip entry
     */
    public function renameEntry($old, $new): ZipEntry
    {
        $old = $old instanceof ZipEntry ? $old->getName() : (string) $old;
        $new = $new instanceof ZipEntry ? $new->getName() : (string) $new;

        if (isset($this->entries[$new])) {
            throw new InvalidArgumentException('New entry name ' . $new . ' is exists.');
        }

        $entry = $this->getEntry($old);
        $newEntry = $entry->rename($new);

        $this->deleteEntry($entry);
        $this->addEntry($newEntry);

        return $newEntry;
    }

    /**
     * @param string|ZipEntry $entryName
     *
     * @throws ZipEntryNotFoundException
     */
    public function getEntry($entryName): ZipEntry
    {
        $entry = $this->getEntryOrNull($entryName);

        if ($entry !== null) {
            return $entry;
        }

        throw new ZipEntryNotFoundException($entryName);
    }

    /**
     * @param string|ZipEntry $entryName
     */
    public function getEntryOrNull($entryName): ?ZipEntry
    {
        $entryName = $entryName instanceof ZipEntry ? $entryName->getName() : (string) $entryName;

        return $this->entries[$entryName] ?? null;
    }

    /**
     * @param string|ZipEntry $entryName
     */
    public function hasEntry($entryName): bool
    {
        $entryName = $entryName instanceof ZipEntry ? $entryName->getName() : (string) $entryName;

        return isset($this->entries[$entryName]);
    }

    /**
     * Delete all entries.
     */
    public function deleteAll(): void
    {
        $this->entries = [];
    }

    /**
     * Delete entries by regex pattern.
     *
     * @param string $regexPattern Regex pattern
     *
     * @return ZipEntry[] Deleted entries
     */
    public function deleteByRegex(string $regexPattern): array
    {
        if (empty($regexPattern)) {
            throw new InvalidArgumentException('The regex pattern is not specified');
        }

        /** @var ZipEntry[] $found */
        $found = [];

        foreach ($this->entries as $entryName => $entry) {
            if (preg_match($regexPattern, $entryName)) {
                $found[] = $entry;
            }
        }

        foreach ($found as $entry) {
            $this->deleteEntry($entry);
        }

        return $found;
    }

    /**
     * Undo all changes done in the archive.
     */
    public function unchangeAll(): void
    {
        $this->entries = [];

        if ($this->sourceContainer !== null) {
            foreach ($this->sourceContainer->getEntries() as $entry) {
                $this->entries[$entry->getName()] = clone $entry;
            }
        }
        $this->unchangeArchiveComment();
    }

    /**
     * Undo change archive comment.
     */
    public function unchangeArchiveComment(): void
    {
        $this->archiveComment = null;

        if ($this->sourceContainer !== null) {
            $this->archiveComment = $this->sourceContainer->archiveComment;
        }
    }

    /**
     * Revert all changes done to an entry with the given name.
     *
     * @param string|ZipEntry $entry Entry name or ZipEntry
     */
    public function unchangeEntry($entry): bool
    {
        $entry = $entry instanceof ZipEntry ? $entry->getName() : (string) $entry;

        if (
            $this->sourceContainer !== null
            && isset($this->entries[$entry], $this->sourceContainer->entries[$entry])
        ) {
            $this->entries[$entry] = clone $this->sourceContainer->entries[$entry];

            return true;
        }

        return false;
    }

    /**
     * Entries sort by name.
     *
     * Example:
     * ```php
     * $zipContainer->sortByName(static function (string $nameA, string $nameB): int {
     *     return strcmp($nameA, $nameB);
     * });
     * ```
     */
    public function sortByName(callable $cmp): void
    {
        uksort($this->entries, $cmp);
    }

    /**
     * Entries sort by entry.
     *
     * Example:
     * ```php
     * $zipContainer->sortByEntry(static function (ZipEntry $a, ZipEntry $b): int {
     *     return strcmp($a->getName(), $b->getName());
     * });
     * ```
     */
    public function sortByEntry(callable $cmp): void
    {
        uasort($this->entries, $cmp);
    }

    public function setArchiveComment(?string $archiveComment): void
    {
        if ($archiveComment !== null && $archiveComment !== '') {
            $length = \strlen($archiveComment);

            if ($length > 0xFFFF) {
                throw new InvalidArgumentException('Length comment out of range');
            }
        }
        $this->archiveComment = $archiveComment;
    }

    public function matcher(): ZipEntryMatcher
    {
        return new ZipEntryMatcher($this);
    }

    /**
     * Specify a password for extracting files.
     *
     * @param ?string $password
     */
    public function setReadPassword(?string $password): void
    {
        if ($this->sourceContainer !== null) {
            foreach ($this->sourceContainer->entries as $entry) {
                if ($entry->isEncrypted()) {
                    $entry->setPassword($password);
                }
            }
        }
    }

    /**
     * @throws ZipEntryNotFoundException
     * @throws ZipException
     */
    public function setReadPasswordEntry(string $entryName, string $password): void
    {
        if (!isset($this->sourceContainer->entries[$entryName])) {
            throw new ZipEntryNotFoundException($entryName);
        }

        if ($this->sourceContainer->entries[$entryName]->isEncrypted()) {
            $this->sourceContainer->entries[$entryName]->setPassword($password);
        }
    }

    /**
     * @param ?string $writePassword
     *
     * @throws ZipEntryNotFoundException
     */
    public function setWritePassword(?string $writePassword): void
    {
        $this->matcher()->all()->setPassword($writePassword);
    }

    /**
     * Remove password.
     *
     * @throws ZipEntryNotFoundException
     */
    public function removePassword(): void
    {
        $this->matcher()->all()->setPassword(null);
    }

    /**
     * @param string|ZipEntry $entryName
     *
     * @throws ZipEntryNotFoundException
     */
    public function removePasswordEntry($entryName): void
    {
        $this->matcher()->add($entryName)->setPassword(null);
    }

    /**
     * @throws ZipEntryNotFoundException
     */
    public function setEncryptionMethod(int $encryptionMethod = ZipEncryptionMethod::WINZIP_AES_256): void
    {
        $this->matcher()->all()->setEncryptionMethod($encryptionMethod);
    }
}
