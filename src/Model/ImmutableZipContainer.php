<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Model;

class ImmutableZipContainer implements \Countable
{
    /** @var ZipEntry[] */
    protected array $entries;

    /** @var string|null Archive comment */
    protected ?string $archiveComment;

    /**
     * @param ZipEntry[] $entries
     * @param ?string    $archiveComment
     */
    public function __construct(array $entries, ?string $archiveComment = null)
    {
        $this->entries = $entries;
        $this->archiveComment = $archiveComment;
    }

    /**
     * @return ZipEntry[]
     */
    public function &getEntries(): array
    {
        return $this->entries;
    }

    public function getArchiveComment(): ?string
    {
        return $this->archiveComment;
    }

    /**
     * Count elements of an object.
     *
     * @see https://php.net/manual/en/countable.count.php
     *
     * @return int The custom count as an integer.
     *             The return value is cast to an integer.
     */
    public function count(): int
    {
        return \count($this->entries);
    }

    /**
     * When an object is cloned, PHP 5 will perform a shallow copy of all of the object's properties.
     * Any properties that are references to other variables, will remain references.
     * Once the cloning is complete, if a __clone() method is defined,
     * then the newly created object's __clone() method will be called, to allow any necessary properties that need to
     * be changed. NOT CALLABLE DIRECTLY.
     *
     * @see https://php.net/manual/en/language.oop5.cloning.php
     */
    public function __clone()
    {
        foreach ($this->entries as $key => $value) {
            $this->entries[$key] = clone $value;
        }
    }
}
