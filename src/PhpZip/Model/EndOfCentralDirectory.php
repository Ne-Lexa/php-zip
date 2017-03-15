<?php
namespace PhpZip\Model;

use PhpZip\Exception\InvalidArgumentException;
use PhpZip\Exception\ZipException;
use PhpZip\Mapper\OffsetPositionMapper;
use PhpZip\Mapper\PositionMapper;
use PhpZip\Util\PackUtil;

/**
 * Read End of Central Directory
 *
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class EndOfCentralDirectory
{
    /** Zip64 End Of Central Directory Record. */
    const ZIP64_END_OF_CENTRAL_DIRECTORY_RECORD_SIG = 0x06064B50;
    /** Zip64 End Of Central Directory Locator. */
    const ZIP64_END_OF_CENTRAL_DIRECTORY_LOCATOR_SIG = 0x07064B50;
    /** End Of Central Directory Record signature. */
    const END_OF_CENTRAL_DIRECTORY_RECORD_SIG = 0x06054B50;
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
     * total number of disks           4
     */
    const ZIP64_END_OF_CENTRAL_DIRECTORY_LOCATOR_LEN = 20;
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
    /**
     * @var string|null The archive comment.
     */
    private $comment;
    /**
     * @var int The number of bytes in the preamble of this ZIP file.
     */
    private $preamble;
    /**
     * @var int The number of bytes in the postamble of this ZIP file.
     */
    private $postamble;
    /**
     * @var PositionMapper Maps offsets specified in the ZIP file to real offsets in the file.
     */
    private $mapper;
    /**
     * @var int
     */
    private $centralDirectoryEntriesSize;
    /**
     * @var bool
     */
    private $zip64 = false;
    /**
     * @var string|null
     */
    private $newComment;
    /**
     * @var bool
     */
    private $modified;

    /**
     * EndOfCentralDirectory constructor.
     */
    public function __construct()
    {
        $this->mapper = new PositionMapper();
    }

    /**
     * Positions the file pointer at the first Central File Header.
     * Performs some means to check that this is really a ZIP file.
     *
     * @param resource $inputStream
     * @throws ZipException If the file is not compatible to the ZIP File
     *         Format Specification.
     */
    public function findCentralDirectory($inputStream)
    {
        // Search for End of central directory record.
        $stats = fstat($inputStream);
        $size = $stats['size'];
        $max = $size - self::END_OF_CENTRAL_DIRECTORY_RECORD_MIN_LEN;
        $min = $max >= 0xffff ? $max - 0xffff : 0;
        for ($endOfCentralDirRecordPos = $max; $endOfCentralDirRecordPos >= $min; $endOfCentralDirRecordPos--) {
            fseek($inputStream, $endOfCentralDirRecordPos, SEEK_SET);
            // end of central dir signature    4 bytes  (0x06054b50)
            if (self::END_OF_CENTRAL_DIRECTORY_RECORD_SIG !== unpack('V', fread($inputStream, 4))[1])
                continue;

            // number of this disk                        - 2 bytes
            // number of the disk with the start of the
            //        central directory                   - 2 bytes
            // total number of entries in the central
            //        directory on this disk              - 2 bytes
            // total number of entries in the central
            //        directory                           - 2 bytes
            // size of the central directory              - 4 bytes
            // offset of start of central directory with
            //        respect to the starting disk number - 4 bytes
            // ZIP file comment length                    - 2 bytes
            $data = unpack(
                'vdiskNo/vcdDiskNo/vcdEntriesDisk/vcdEntries/VcdSize/VcdPos/vcommentLength',
                fread($inputStream, 18)
            );

            if (0 !== $data['diskNo'] || 0 !== $data['cdDiskNo'] || $data['cdEntriesDisk'] !== $data['cdEntries']) {
                throw new ZipException(
                    "ZIP file spanning/splitting is not supported!"
                );
            }
            // .ZIP file comment       (variable size)
            if (0 < $data['commentLength']) {
                $this->comment = fread($inputStream, $data['commentLength']);
            }
            $this->preamble = $endOfCentralDirRecordPos;
            $this->postamble = $size - ftell($inputStream);

            // Check for ZIP64 End Of Central Directory Locator.
            $endOfCentralDirLocatorPos = $endOfCentralDirRecordPos - self::ZIP64_END_OF_CENTRAL_DIRECTORY_LOCATOR_LEN;

            fseek($inputStream, $endOfCentralDirLocatorPos, SEEK_SET);
            // zip64 end of central dir locator
            // signature                       4 bytes  (0x07064b50)
            if (
                0 > $endOfCentralDirLocatorPos ||
                ftell($inputStream) === $size ||
                self::ZIP64_END_OF_CENTRAL_DIRECTORY_LOCATOR_SIG !== unpack('V', fread($inputStream, 4))[1]
            ) {
                // Seek and check first CFH, probably requiring an offset mapper.
                $offset = $endOfCentralDirRecordPos - $data['cdSize'];
                fseek($inputStream, $offset, SEEK_SET);
                $offset -= $data['cdPos'];
                if (0 !== $offset) {
                    $this->mapper = new OffsetPositionMapper($offset);
                }
                $this->centralDirectoryEntriesSize = $data['cdEntries'];
                return;
            }

            // number of the disk with the
            // start of the zip64 end of
            // central directory               4 bytes
            $zip64EndOfCentralDirectoryRecordDisk = unpack('V', fread($inputStream, 4))[1];
            // relative offset of the zip64
            // end of central directory record 8 bytes
            $zip64EndOfCentralDirectoryRecordPos = PackUtil::unpackLongLE(fread($inputStream, 8));
            // total number of disks           4 bytes
            $totalDisks = unpack('V', fread($inputStream, 4))[1];
            if (0 !== $zip64EndOfCentralDirectoryRecordDisk || 1 !== $totalDisks) {
                throw new ZipException("ZIP file spanning/splitting is not supported!");
            }
            fseek($inputStream, $zip64EndOfCentralDirectoryRecordPos, SEEK_SET);
            // zip64 end of central dir
            // signature                       4 bytes  (0x06064b50)
            $zip64EndOfCentralDirSig = unpack('V', fread($inputStream, 4))[1];
            if (self::ZIP64_END_OF_CENTRAL_DIRECTORY_RECORD_SIG !== $zip64EndOfCentralDirSig) {
                throw new ZipException("Expected ZIP64 End Of Central Directory Record!");
            }
            // size of zip64 end of central
            // directory record                8 bytes
            // version made by                 2 bytes
            // version needed to extract       2 bytes
            fseek($inputStream, 12, SEEK_CUR);
            // number of this disk             4 bytes
            $diskNo = unpack('V', fread($inputStream, 4))[1];
            // number of the disk with the
            // start of the central directory  4 bytes
            $cdDiskNo = unpack('V', fread($inputStream, 4))[1];
            // total number of entries in the
            // central directory on this disk  8 bytes
            $cdEntriesDisk = PackUtil::unpackLongLE(fread($inputStream, 8));
            // total number of entries in the
            // central directory               8 bytes
            $cdEntries = PackUtil::unpackLongLE(fread($inputStream, 8));
            if (0 !== $diskNo || 0 !== $cdDiskNo || $cdEntriesDisk !== $cdEntries) {
                throw new ZipException("ZIP file spanning/splitting is not supported!");
            }
            if ($cdEntries < 0 || 0x7fffffff < $cdEntries) {
                throw new ZipException("Total Number Of Entries In The Central Directory out of range!");
            }
            // size of the central directory   8 bytes
            fseek($inputStream, 8, SEEK_CUR);
            // offset of start of central
            // directory with respect to
            // the starting disk number        8 bytes
            $cdPos = PackUtil::unpackLongLE(fread($inputStream, 8));
            // zip64 extensible data sector    (variable size)
            fseek($inputStream, $cdPos, SEEK_SET);
            $this->preamble = $zip64EndOfCentralDirectoryRecordPos;
            $this->centralDirectoryEntriesSize = $cdEntries;
            $this->zip64 = true;
            return;
        }
        // Start recovering file entries from min.
        $this->preamble = $min;
        $this->postamble = $size - $min;
        $this->centralDirectoryEntriesSize = 0;
    }

    /**
     * @return null|string
     */
    public function getComment()
    {
        return $this->comment;
    }

    /**
     * @return int
     */
    public function getCentralDirectoryEntriesSize()
    {
        return $this->centralDirectoryEntriesSize;
    }

    /**
     * @return bool
     */
    public function isZip64()
    {
        return $this->zip64;
    }

    /**
     * @return int
     */
    public function getPreamble()
    {
        return $this->preamble;
    }

    /**
     * @return int
     */
    public function getPostamble()
    {
        return $this->postamble;
    }

    /**
     * @return PositionMapper
     */
    public function getMapper()
    {
        return $this->mapper;
    }

    /**
     * @param int $preamble
     */
    public function setPreamble($preamble)
    {
        $this->preamble = $preamble;
    }

    /**
     * Set archive comment
     * @param string|null $comment
     * @throws InvalidArgumentException
     */
    public function setComment($comment = null)
    {
        if (null !== $comment && strlen($comment) !== 0) {
            $comment = (string)$comment;
            $length = strlen($comment);
            if (0x0000 > $length || $length > 0xffff) {
                throw new InvalidArgumentException('Length comment out of range');
            }
        }
        $this->modified = $comment !== $this->comment;
        $this->newComment = $comment;
    }

    /**
     * Write end of central directory.
     *
     * @param resource $outputStream Output stream
     * @param int $centralDirectoryEntries Size entries
     * @param int $centralDirectoryOffset Offset central directory
     */
    public function writeEndOfCentralDirectory($outputStream, $centralDirectoryEntries, $centralDirectoryOffset)
    {
        $position = ftell($outputStream);
        $centralDirectorySize = $position - $centralDirectoryOffset;
        $centralDirectoryEntriesZip64 = $centralDirectoryEntries > 0xffff;
        $centralDirectorySizeZip64 = $centralDirectorySize > 0xffffffff;
        $centralDirectoryOffsetZip64 = $centralDirectoryOffset > 0xffffffff;
        $centralDirectoryEntries16 = $centralDirectoryEntriesZip64 ? 0xffff : (int)$centralDirectoryEntries;
        $centralDirectorySize32 = $centralDirectorySizeZip64 ? 0xffffffff : $centralDirectorySize;
        $centralDirectoryOffset32 = $centralDirectoryOffsetZip64 ? 0xffffffff : $centralDirectoryOffset;
        $zip64 // ZIP64 extensions?
            = $centralDirectoryEntriesZip64
            || $centralDirectorySizeZip64
            || $centralDirectoryOffsetZip64;
        if ($zip64) {
            // relative offset of the zip64 end of central directory record
            $zip64EndOfCentralDirectoryOffset = $position;
            // zip64 end of central dir
            // signature                       4 bytes  (0x06064b50)
            fwrite($outputStream, pack('V', self::ZIP64_END_OF_CENTRAL_DIRECTORY_RECORD_SIG));
            // size of zip64 end of central
            // directory record                8 bytes
            fwrite($outputStream, PackUtil::packLongLE(self::ZIP64_END_OF_CENTRAL_DIRECTORY_RECORD_MIN_LEN - 12));
            // version made by                 2 bytes
            // version needed to extract       2 bytes
            //                                 due to potential use of BZIP2 compression
            // number of this disk             4 bytes
            // number of the disk with the
            // start of the central directory  4 bytes
            fwrite($outputStream, pack('vvVV', 63, 46, 0, 0));
            // total number of entries in the
            // central directory on this disk  8 bytes
            fwrite($outputStream, PackUtil::packLongLE($centralDirectoryEntries));
            // total number of entries in the
            // central directory               8 bytes
            fwrite($outputStream, PackUtil::packLongLE($centralDirectoryEntries));
            // size of the central directory   8 bytes
            fwrite($outputStream, PackUtil::packLongLE($centralDirectorySize));
            // offset of start of central
            // directory with respect to
            // the starting disk number        8 bytes
            fwrite($outputStream, PackUtil::packLongLE($centralDirectoryOffset));
            // zip64 extensible data sector    (variable size)
            //
            // zip64 end of central dir locator
            // signature                       4 bytes  (0x07064b50)
            // number of the disk with the
            // start of the zip64 end of
            // central directory               4 bytes
            fwrite($outputStream, pack('VV', self::ZIP64_END_OF_CENTRAL_DIRECTORY_LOCATOR_SIG, 0));
            // relative offset of the zip64
            // end of central directory record 8 bytes
            fwrite($outputStream, PackUtil::packLongLE($zip64EndOfCentralDirectoryOffset));
            // total number of disks           4 bytes
            fwrite($outputStream, pack('V', 1));
        }
        $comment = $this->modified ? $this->newComment : $this->comment;
        $commentLength = strlen($comment);
        fwrite(
            $outputStream,
            pack('VvvvvVVv',
                // end of central dir signature    4 bytes  (0x06054b50)
                self::END_OF_CENTRAL_DIRECTORY_RECORD_SIG,
                // number of this disk             2 bytes
                0,
                // number of the disk with the
                // start of the central directory  2 bytes
                0,
                // total number of entries in the
                // central directory on this disk  2 bytes
                $centralDirectoryEntries16,
                // total number of entries in
                // the central directory           2 bytes
                $centralDirectoryEntries16,
                // size of the central directory   4 bytes
                $centralDirectorySize32,
                // offset of start of central
                // directory with respect to
                // the starting disk number        4 bytes
                $centralDirectoryOffset32,
                // .ZIP file comment length        2 bytes
                $commentLength
            )
        );
        if ($commentLength > 0) {
            // .ZIP file comment       (variable size)
            fwrite($outputStream, $comment);
        }
    }

}