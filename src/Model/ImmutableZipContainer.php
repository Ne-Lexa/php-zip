<?php

namespace PhpZip\Model;

/**
 * Class ImmutableZipContainer.
 */
class ImmutableZipContainer implements \Countable
{
    /** @var ZipEntry[] */
    protected $entries;

    /** @var string|null Archive comment */
    protected $archiveComment;

    /**
     * ZipContainer constructor.
     *
     * @param ZipEntry[]  $entries
     * @param string|null $archiveComment
     */
    public function __construct(array $entries, $archiveComment)
    {
        $this->entries = $entries;
        $this->archiveComment = $archiveComment;
    }

    /**
     * @return ZipEntry[]
     */
    public function &getEntries()
    {
        return $this->entries;
    }

    /**
     * @return string|null
     */
    public function getArchiveComment()
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
    public function count()
    {
        return \count($this->entries);
    }
}
