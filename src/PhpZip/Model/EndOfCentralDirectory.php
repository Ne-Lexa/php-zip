<?php

namespace PhpZip\Model;

/**
 * End of Central Directory.
 *
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class EndOfCentralDirectory
{
    /** Zip64 End Of Central Directory Record. */
    const ZIP64_END_OF_CD_RECORD_SIG = 0x06064B50;

    /** Zip64 End Of Central Directory Locator. */
    const ZIP64_END_OF_CD_LOCATOR_SIG = 0x07064B50;

    /** End Of Central Directory Record signature. */
    const END_OF_CD_SIG = 0x06054B50;

    /**
     * The minimum length of the End Of Central Directory Record.
     *
     * end of central dir signature    4
     * number of this disk             2
     * number of the disk with the
     * start of the central directory  2
     * total number of entries in the
     * central directory on this disk  2
     * total number of entries in
     * the central directory           2
     * size of the central directory   4
     * offset of start of central      *
     * directory with respect to       *
     * the starting disk number        4
     * zipfile comment length          2
     */
    const END_OF_CENTRAL_DIRECTORY_RECORD_MIN_LEN = 22;

    /**
     * The length of the Zip64 End Of Central Directory Locator.
     * zip64 end of central dir locator
     * signature                       4
     * number of the disk with the
     * start of the zip64 end of
     * central directory               4
     * relative offset of the zip64
     * end of central directory record 8
     * total number of disks           4.
     */
    const ZIP64_END_OF_CD_LOCATOR_LEN = 20;

    /**
     * The minimum length of the Zip64 End Of Central Directory Record.
     *
     * zip64 end of central dir
     * signature                        4
     * size of zip64 end of central
     * directory record                 8
     * version made by                  2
     * version needed to extract        2
     * number of this disk              4
     * number of the disk with the
     * start of the central directory   4
     * total number of entries in the
     * central directory on this disk   8
     * total number of entries in
     * the central directory            8
     * size of the central directory    8
     * offset of start of central
     * directory with respect to
     * the starting disk number         8
     */
    const ZIP64_END_OF_CENTRAL_DIRECTORY_RECORD_MIN_LEN = 56;

    /** @var int Count files. */
    private $entryCount;

    /** @var int Central Directory Offset. */
    private $cdOffset;

    /** @var int */
    private $cdSize;

    /** @var string|null The archive comment. */
    private $comment;

    /** @var bool Zip64 extension */
    private $zip64;

    /**
     * EndOfCentralDirectory constructor.
     *
     * @param int        $entryCount
     * @param int        $cdOffset
     * @param int        $cdSize
     * @param bool       $zip64
     * @param mixed|null $comment
     */
    public function __construct($entryCount, $cdOffset, $cdSize, $zip64, $comment = null)
    {
        $this->entryCount = $entryCount;
        $this->cdOffset = $cdOffset;
        $this->cdSize = $cdSize;
        $this->zip64 = $zip64;
        $this->comment = $comment;
    }

    /**
     * @param string|null $comment
     */
    public function setComment($comment)
    {
        $this->comment = $comment;
    }

    /**
     * @return int
     */
    public function getEntryCount()
    {
        return $this->entryCount;
    }

    /**
     * @return int
     */
    public function getCdOffset()
    {
        return $this->cdOffset;
    }

    /**
     * @return int
     */
    public function getCdSize()
    {
        return $this->cdSize;
    }

    /**
     * @return string|null
     */
    public function getComment()
    {
        return $this->comment;
    }

    /**
     * @return bool
     */
    public function isZip64()
    {
        return $this->zip64;
    }
}
