<?php
namespace Nelexa\Zip;

use Nelexa\Buffer\Buffer;
use Nelexa\Buffer\StringBuffer;

class ZipEntry
{
    // made by constants
    const MADE_BY_MS_DOS = 0;
    const MADE_BY_AMIGA = 1;
    const MADE_BY_OPEN_VMS = 2;
    const MADE_BY_UNIX = 3;
    const MADE_BY_VM_CMS = 4;
    const MADE_BY_ATARI = 5;
    const MADE_BY_OS_2 = 6;
    const MADE_BY_MACINTOSH = 7;
    const MADE_BY_Z_SYSTEM = 8;
    const MADE_BY_CP_M = 9;
    const MADE_BY_WINDOWS_NTFS = 10;
    const MADE_BY_MVS = 11;
    const MADE_BY_VSE = 12;
    const MADE_BY_ACORN_RISC = 13;
    const MADE_BY_VFAT = 14;
    const MADE_BY_ALTERNATE_MVS = 15;
    const MADE_BY_BEOS = 16;
    const MADE_BY_TANDEM = 17;
    const MADE_BY_OS_400 = 18;
    const MADE_BY_OS_X = 19;
    const MADE_BY_UNKNOWN = 20;

    private static $valuesMadeBy = array(
        self::MADE_BY_MS_DOS => 'MS-DOS and OS/2 (FAT / VFAT / FAT32 file systems)',
        self::MADE_BY_AMIGA => 'Amiga',
        self::MADE_BY_OPEN_VMS => 'OpenVMS',
        self::MADE_BY_UNIX => 'UNIX',
        self::MADE_BY_VM_CMS => 'VM/CMS',
        self::MADE_BY_ATARI => 'Atari ST',
        self::MADE_BY_OS_2 => 'OS/2 H.P.F.S.',
        self::MADE_BY_MACINTOSH => 'Macintosh',
        self::MADE_BY_Z_SYSTEM => 'Z-System',
        self::MADE_BY_CP_M => 'CP/M',
        self::MADE_BY_WINDOWS_NTFS => 'Windows NTFS',
        self::MADE_BY_MVS => 'MVS (OS/390 - Z/OS)',
        self::MADE_BY_VSE => 'VSE',
        self::MADE_BY_ACORN_RISC => 'Acorn Risc',
        self::MADE_BY_VFAT => 'VFAT',
        self::MADE_BY_ALTERNATE_MVS => 'alternate MVS',
        self::MADE_BY_BEOS => 'BeOS',
        self::MADE_BY_TANDEM => 'Tandem',
        self::MADE_BY_OS_400 => 'OS/400',
        self::MADE_BY_OS_X => 'OS X (Darwin)',
    );

    // constants version by extract
    const EXTRACT_VERSION_10 = 10;
    const EXTRACT_VERSION_11 = 11;
    const EXTRACT_VERSION_20 = 20;
//1.0 - Default value
//1.1 - File is a volume label
//2.0 - File is a folder (directory)
//2.0 - File is compressed using Deflate compression
//2.0 - File is encrypted using traditional PKWARE encryption
//2.1 - File is compressed using Deflate64(tm)
//2.5 - File is compressed using PKWARE DCL Implode
//2.7 - File is a patch data set
//4.5 - File uses ZIP64 format extensions
//4.6 - File is compressed using BZIP2 compression*
//5.0 - File is encrypted using DES
//5.0 - File is encrypted using 3DES
//5.0 - File is encrypted using original RC2 encryption
//5.0 - File is encrypted using RC4 encryption
//5.1 - File is encrypted using AES encryption
//5.1 - File is encrypted using corrected RC2 encryption**
//5.2 - File is encrypted using corrected RC2-64 encryption**
//6.1 - File is encrypted using non-OAEP key wrapping***
//6.2 - Central directory encryption
//6.3 - File is compressed using LZMA
//6.3 - File is compressed using PPMd+
//6.3 - File is encrypted using Blowfish
//6.3 - File is encrypted using Twofish

    const FLAG_ENCRYPTION = 0;
    const FLAG_DATA_DESCRIPTION = 3;
    const FLAG_UTF8 = 11;
    private static $valuesFlag = array(
        self::FLAG_ENCRYPTION => 'encrypted file', // 1 << 0
        1 => 'compression option', // 1 << 1
        2 => 'compression option', // 1 << 2
        self::FLAG_DATA_DESCRIPTION => 'data descriptor', // 1 << 3
        4 => 'enhanced deflation', // 1 << 4
        5 => 'compressed patched data', // 1 << 5
        6 => 'strong encryption', // 1 << 6
        7 => 'unused', // 1 << 7
        8 => 'unused', // 1 << 8
        9 => 'unused', // 1 << 9
        10 => 'unused', // 1 << 10
        self::FLAG_UTF8 => 'language encoding', // 1 << 11
        12 => 'reserved', // 1 << 12
        13 => 'mask header values', // 1 << 13
        14 => 'reserved', // 1 << 14
        15 => 'reserved', // 1 << 15
    );

    // compression method constants
    const COMPRESS_METHOD_STORED = 0;
    const COMPRESS_METHOD_DEFLATED = 8;
    const COMPRESS_METHOD_AES = 99;

    private static $valuesCompressionMethod = array(
        self::COMPRESS_METHOD_STORED => 'no compression',
        1 => 'shrink',
        2 => 'reduce level 1',
        3 => 'reduce level 2',
        4 => 'reduce level 3',
        5 => 'reduce level 4',
        6 => 'implode',
        7 => 'reserved for Tokenizing compression algorithm',
        self::COMPRESS_METHOD_DEFLATED => 'deflate',
        9 => 'deflate64',
        10 => 'PKWARE Data Compression Library Imploding (old IBM TERSE)',
        11 => 'reserved by PKWARE',
        12 => 'bzip2',
        13 => 'reserved by PKWARE',
        14 => 'LZMA (EFS)',
        15 => 'reserved by PKWARE',
        16 => 'reserved by PKWARE',
        17 => 'reserved by PKWARE',
        18 => 'IBM TERSE',
        19 => 'IBM LZ77 z Architecture (PFS)',
        97 => 'WavPack',
        98 => 'PPMd version I, Rev 1',
        self::COMPRESS_METHOD_AES => 'AES Encryption',
    );

    const INTERNAL_ATTR_DEFAULT = 0;
    const EXTERNAL_ATTR_DEFAULT = 0;

    /*
     * Extra field header ID
     */
    const EXTID_ZIP64 = 0x0001; // Zip64
    const EXTID_NTFS = 0x000a; // NTFS (for storing full file times information)
    const EXTID_UNIX = 0x000d; // UNIX
    const EXTID_EXTT = 0x5455; // Info-ZIP Extended Timestamp
    const EXTID_UNICODE_FILENAME = 0x7075; // for Unicode filenames
    const EXTID_UNICODE_ = 0x6375; // for Unicode file comments
    const EXTID_STORING_STRINGS = 0x5A4C; // for storing strings code pages and Unicode filenames using custom Unicode implementation (see Unicode Support: Using Non-English Characters in Filenames, Comments and Passwords).
    const EXTID_OFFSETS_COMPRESS_DATA = 0x5A4D; // for saving offsets array from seekable compressed data
    const EXTID_AES_ENCRYPTION = 0x9901; // WinZip AES encryption (http://www.winzip.com/aes_info.htm)

    /**
     * entry name
     * @var string
     */
    private $name;
    /**
     * version made by
     * @var int
     */
    private $versionMadeBy = self::MADE_BY_WINDOWS_NTFS;
    /**
     * version needed to extract
     * @var int
     */
    private $versionExtract = self::EXTRACT_VERSION_20;
    /**
     * general purpose bit flag
     * @var int
     */
    private $flag = 0;
    /**
     * compression method
     * @var int
     */
    private $compressionMethod = self::COMPRESS_METHOD_DEFLATED;
    /**
     * last mod file datetime
     * @var int Unix timestamp
     */
    private $lastModDateTime;
    /**
     * crc-32
     * @var int
     */
    private $crc32;
    /**
     * compressed size
     * @var int
     */
    private $compressedSize;
    /**
     * uncompressed size
     * @var int
     */
    private $unCompressedSize;
    /**
     * disk number start
     * @var int
     */
    private $diskNumber = 0;
    /**
     * internal file attributes
     * @var int
     */
    private $internalAttributes = self::INTERNAL_ATTR_DEFAULT;
    /**
     * external file attributes
     * @var int
     */
    private $externalAttributes = self::EXTERNAL_ATTR_DEFAULT;
    /**
     * relative offset of local header
     * @var int
     */
    private $offsetOfLocal;
    /**
     * @var int
     */
    private $offsetOfCentral;

    /**
     * optional extra field data for entry
     *
     * @var string
     */
    private $extraCentral = "";
    /**
     * @var string
     */
    private $extraLocal = "";
    /**
     * optional comment string for entry
     *
     * @var string
     */
    private $comment = "";

    function __construct()
    {

    }

    public function getLengthOfLocal()
    {
        return $this->getLengthLocalHeader() + $this->compressedSize + ($this->hasDataDescriptor() ? 12 : 0);
    }

    public function getLengthLocalHeader()
    {
        return 30 + strlen($this->name) + strlen($this->extraLocal);
    }

    public function getLengthOfCentral()
    {
        return 46 + strlen($this->name) + strlen($this->extraCentral) + strlen($this->comment);
    }

    /**
     * @param Buffer $buffer
     * @throws ZipException
     */
    public function readCentralHeader(Buffer $buffer)
    {
        $signature = $buffer->getUnsignedInt(); // after offset 4
        if ($signature !== ZipFile::SIGNATURE_CENTRAL_DIR) {
            throw new ZipException("Can not read central directory. Bad signature: " . $signature);
        }
        $this->versionMadeBy = $buffer->getUnsignedShort(); // after offset 6
        $this->versionExtract = $buffer->getUnsignedShort(); // after offset 8
        $this->flag = $buffer->getUnsignedShort(); // after offset 10
        $this->compressionMethod = $buffer->getUnsignedShort(); // after offset 12
        $lastModTime = $buffer->getUnsignedShort(); // after offset 14
        $lastModDate = $buffer->getUnsignedShort(); // after offset 16
        $this->setLastModifyDosDatetime($lastModTime, $lastModDate);
        $this->crc32 = $buffer->getUnsignedInt(); // after offset 20
        $this->compressedSize = $buffer->getUnsignedInt(); // after offset 24
        $this->unCompressedSize = $buffer->getUnsignedInt(); // after offset 28
        $fileNameLength = $buffer->getUnsignedShort(); // after offset 30
        $extraCentralLength = $buffer->getUnsignedShort(); // after offset 32
        $fileCommentLength = $buffer->getUnsignedShort(); // after offset 34
        $this->diskNumber = $buffer->getUnsignedShort(); // after offset 36
        $this->internalAttributes = $buffer->getUnsignedShort(); // after offset 38
        $this->externalAttributes = $buffer->getUnsignedInt(); // after offset 42
        $this->offsetOfLocal = $buffer->getUnsignedInt(); // after offset 46
        $this->name = $buffer->getString($fileNameLength);
        $this->setExtra($buffer->getString($extraCentralLength));
        $this->comment = $buffer->getString($fileCommentLength);

        $currentPos = $buffer->position();
        $buffer->setPosition($this->offsetOfLocal + 28);
        $extraLocalLength = $buffer->getUnsignedShort();
        $buffer->skip($fileNameLength);
        $this->extraLocal = $buffer->getString($extraLocalLength);
        $buffer->setPosition($currentPos);
    }

    /**
     * Sets the optional extra field data for the entry.
     *
     * @param string $extra the extra field data bytes
     * @throws ZipException
     */
    private function setExtra($extra)
    {
        if (!empty($extra)) {
            $len = strlen($extra);
            if ($len > 0xFFFF) {
                throw new ZipException("invalid extra field length");
            }
            $buffer = new StringBuffer($extra);
            $buffer->setOrder(Buffer::LITTLE_ENDIAN);
            // extra fields are in "HeaderID(2)DataSize(2)Data... format
            while ($buffer->position() + 4 < $len) {
                $tag = $buffer->getUnsignedShort();
                $sz = $buffer->getUnsignedShort();
                if ($buffer->position() + $sz > $len) // invalid data
                    break;
                switch ($tag) {
                    case self::EXTID_ZIP64:
                        // not support zip64
                        break;
                    case self::EXTID_NTFS:
                        $buffer->skip(4); // reserved 4 bytes
                        if ($buffer->getUnsignedShort() != 0x0001 || $buffer->getUnsignedShort() != 24)
                            break;
//                    $mtime = winTimeToFileTime($buffer->getLong());
//                    $atime = winTimeToFileTime($buffer->getLong());
//                    $ctime = winTimeToFileTime($buffer->getLong());
                        break;
                    case self::EXTID_EXTT:
                        $flag = $buffer->getUnsignedByte();
                        $sz0 = 1;
                        // The CEN-header extra field contains the modification
                        // time only, or no timestamp at all. 'sz' is used to
                        // flag its presence or absence. But if mtime is present
                        // in LOC it must be present in CEN as well.
                        if (($flag & 0x1) != 0 && ($sz0 + 4) <= $sz) {
                            $mtime = $buffer->getUnsignedInt();
                            $sz0 += 4;
                        }
                        if (($flag & 0x2) != 0 && ($sz0 + 4) <= $sz) {
                            $atime = $buffer->getUnsignedInt();
                            $sz0 += 4;
                        }
                        if (($flag & 0x4) != 0 && ($sz0 + 4) <= $sz) {
                            $ctime = $buffer->getUnsignedInt();
                            $sz0 += 4;
                        }
                        break;
                    default:
                }
            }
        }
        $this->extraCentral = $extra;
    }

    /**
     * @return Buffer
     */
    public function writeLocalHeader()
    {
        $buffer = new StringBuffer();
        $buffer->setOrder(Buffer::LITTLE_ENDIAN);
        $buffer->insertInt(ZipFile::SIGNATURE_LOCAL_HEADER);
        $buffer->insertShort($this->versionExtract);
        $buffer->insertShort($this->flag);
        $buffer->insertShort($this->compressionMethod);
        $buffer->insertShort($this->getLastModifyDosTime());
        $buffer->insertShort($this->getLastModifyDosDate());
        if ($this->hasDataDescriptor()) {
            $buffer->insertInt(0);
            $buffer->insertInt(0);
            $buffer->insertInt(0);
        } else {
            $buffer->insertInt($this->crc32);
            $buffer->insertInt($this->compressedSize);
            $buffer->insertInt($this->unCompressedSize);
        }
        $buffer->insertShort(strlen($this->name));
        $buffer->insertShort(strlen($this->extraLocal)); // offset 30
        $buffer->insertString($this->name);
        $buffer->insertString($this->extraLocal);
        return $buffer;
    }

    /**
     * @param int $bit
     * @return bool
     */
    public function setFlagBit($bit)
    {
        if ($bit < 0 || $bit > 15) {
            return false;
        }
        $this->flag |= 1 << $bit;
        return true;
    }

    /**
     * @param int $bit
     * @return bool
     */
    public function testFlagBit($bit)
    {
        return (($this->flag & (1 << $bit)) !== 0);
    }

    /**
     * @return bool
     */
    public function hasDataDescriptor()
    {
        return $this->testFlagBit(self::FLAG_DATA_DESCRIPTION);
    }

    /**
     * @return bool
     */
    public function isEncrypted()
    {
        return $this->testFlagBit(self::FLAG_ENCRYPTION);
    }

    public function writeDataDescriptor()
    {
        $buffer = new StringBuffer();
        $buffer->setOrder(Buffer::LITTLE_ENDIAN);
        $buffer->insertInt($this->crc32);
        $buffer->insertInt($this->compressedSize);
        $buffer->insertInt($this->unCompressedSize);
        return $buffer;
    }

    /**
     * @return Buffer
     * @throws ZipException
     */
    public function writeCentralHeader()
    {
        $buffer = new StringBuffer();
        $buffer->setOrder(Buffer::LITTLE_ENDIAN);

        $buffer->insertInt(ZipFile::SIGNATURE_CENTRAL_DIR);
        $buffer->insertShort($this->versionMadeBy);
        $buffer->insertShort($this->versionExtract);
        $buffer->insertShort($this->flag);
        $buffer->insertShort($this->compressionMethod);
        $buffer->insertShort($this->getLastModifyDosTime());
        $buffer->insertShort($this->getLastModifyDosDate());
        $buffer->insertInt($this->crc32);
        $buffer->insertInt($this->compressedSize);
        $buffer->insertInt($this->unCompressedSize);
        $buffer->insertShort(strlen($this->name));
        $buffer->insertShort(strlen($this->extraCentral));
        $buffer->insertShort(strlen($this->comment));
        $buffer->insertShort($this->diskNumber);
        $buffer->insertShort($this->internalAttributes);
        $buffer->insertInt($this->externalAttributes);
        $buffer->insertInt($this->offsetOfLocal);
        $buffer->insertString($this->name);
        $buffer->insertString($this->extraCentral);
        $buffer->insertString($this->comment);
        return $buffer;
    }

    /**
     * @return bool
     */
    public function isDirectory()
    {
        return $this->name[strlen($this->name) - 1] === "/";
    }

    /**
     * @return array
     */
    public static function getValuesMadeBy()
    {
        return self::$valuesMadeBy;
    }

    /**
     * @param array $valuesMadeBy
     */
    public static function setValuesMadeBy($valuesMadeBy)
    {
        self::$valuesMadeBy = $valuesMadeBy;
    }

    /**
     * @return array
     */
    public static function getValuesFlag()
    {
        return self::$valuesFlag;
    }

    /**
     * @param array $valuesFlag
     */
    public static function setValuesFlag($valuesFlag)
    {
        self::$valuesFlag = $valuesFlag;
    }

    /**
     * @return array
     */
    public static function getValuesCompressionMethod()
    {
        return self::$valuesCompressionMethod;
    }

    /**
     * @param array $valuesCompressionMethod
     */
    public static function setValuesCompressionMethod($valuesCompressionMethod)
    {
        self::$valuesCompressionMethod = $valuesCompressionMethod;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @throws ZipException
     */
    public function setName($name)
    {
        if (strlen($name) > 0xFFFF) {
            throw new ZipException("entry name too long");
        }
        $this->name = $name;
        $encoding = mb_detect_encoding($this->name, "ASCII, UTF-8", true);
        if ($encoding === 'UTF-8') {
            $this->setFlagBit(self::FLAG_UTF8);
        }
    }

    /**
     * @return int
     */
    public function getVersionMadeBy()
    {
        return $this->versionMadeBy;
    }

    /**
     * @param int $versionMadeBy
     */
    public function setVersionMadeBy($versionMadeBy)
    {
        $this->versionMadeBy = $versionMadeBy;
    }

    /**
     * @return int
     */
    public function getVersionExtract()
    {
        return $this->versionExtract;
    }

    /**
     * @param int $versionExtract
     */
    public function setVersionExtract($versionExtract)
    {
        $this->versionExtract = $versionExtract;
    }

    /**
     * @return int
     */
    public function getFlag()
    {
        return $this->flag;
    }

    /**
     * @param int $flag
     */
    public function setFlag($flag)
    {
        $this->flag = $flag;
    }

    /**
     * @return int
     */
    public function getCompressionMethod()
    {
        return $this->compressionMethod;
    }

    /**
     * @param int $compressionMethod
     * @throws ZipException
     */
    public function setCompressionMethod($compressionMethod)
    {
        if (!isset(self::$valuesCompressionMethod[$compressionMethod])) {
            throw new ZipException("invalid compression method " . $compressionMethod);
        }
        $this->compressionMethod = $compressionMethod;
    }

    /**
     * @return int
     */
    public function getLastModDateTime()
    {
        return $this->lastModDateTime;
    }

    /**
     * @param int $lastModDateTime
     */
    public function setLastModDateTime($lastModDateTime)
    {
        $this->lastModDateTime = $lastModDateTime;
    }

    /**
     * @return int
     */
    public function getCrc32()
    {
        return $this->crc32;
    }

    /**
     * @param int $crc32
     * @throws ZipException
     */
    public function setCrc32($crc32)
    {
        if ($crc32 < 0 || $crc32 > 0xFFFFFFFF) {
            throw new ZipException("invalid entry crc-32");
        }
        $this->crc32 = $crc32;
    }

    /**
     * @return int
     */
    public function getCompressedSize()
    {
        return $this->compressedSize;
    }

    /**
     * @param int $compressedSize
     */
    public function setCompressedSize($compressedSize)
    {
        $this->compressedSize = $compressedSize;
    }

    /**
     * @return int
     */
    public function getUnCompressedSize()
    {
        return $this->unCompressedSize;
    }

    /**
     * @param int $unCompressedSize
     * @throws ZipException
     */
    public function setUnCompressedSize($unCompressedSize)
    {
        if ($unCompressedSize < 0 || $unCompressedSize > 0xFFFFFFFF) {
            throw new ZipException("invalid entry size");
        }
        $this->unCompressedSize = $unCompressedSize;
    }

    /**
     * @return int
     */
    public function getDiskNumber()
    {
        return $this->diskNumber;
    }

    /**
     * @param int $diskNumber
     */
    public function setDiskNumber($diskNumber)
    {
        $this->diskNumber = $diskNumber;
    }

    /**
     * @return int
     */
    public function getInternalAttributes()
    {
        return $this->internalAttributes;
    }

    /**
     * @param int $internalAttributes
     */
    public function setInternalAttributes($internalAttributes)
    {
        $this->internalAttributes = $internalAttributes;
    }

    /**
     * @return int
     */
    public function getExternalAttributes()
    {
        return $this->externalAttributes;
    }

    /**
     * @param int $externalAttributes
     */
    public function setExternalAttributes($externalAttributes)
    {
        $this->externalAttributes = $externalAttributes;
    }

    /**
     * @return int
     */
    public function getOffsetOfLocal()
    {
        return $this->offsetOfLocal;
    }

    /**
     * @param int $offsetOfLocal
     */
    public function setOffsetOfLocal($offsetOfLocal)
    {
        $this->offsetOfLocal = $offsetOfLocal;
    }

    /**
     * @return int
     */
    public function getOffsetOfCentral()
    {
        return $this->offsetOfCentral;
    }

    /**
     * @param int $offsetOfCentral
     */
    public function setOffsetOfCentral($offsetOfCentral)
    {
        $this->offsetOfCentral = $offsetOfCentral;
    }

    /**
     * @return string
     */
    public function getExtraCentral()
    {
        return $this->extraCentral;
    }

    /**
     * @param string $extra
     * @throws ZipException
     */
    public function setExtraCentral($extra)
    {
        if ($extra !== null && strlen($extra) > 0xFFFF) {
            throw new ZipException("invalid extra field length");
        }
        $this->extraCentral = $extra;
    }

    /**
     * @param string $extra
     * @throws ZipException
     */
    public function setExtraLocal($extra)
    {
        if ($extra !== null && strlen($extra) > 0xFFFF) {
            throw new ZipException("invalid extra field length");
        }
        $this->extraLocal = $extra;
    }

    /**
     * @return string
     */
    public function getExtraLocal()
    {
        return $this->extraLocal;
    }

    /**
     * @return string
     */
    public function getComment()
    {
        return $this->comment;
    }

    /**
     * @param string $comment
     */
    public function setComment($comment)
    {
        $this->comment = $comment;
    }

    /**
     * @param int $lastModTime
     * @param int $lastModDate
     */
    private function setLastModifyDosDatetime($lastModTime, $lastModDate)
    {
        $hour = ($lastModTime & 0xF800) >> 11;
        $minute = ($lastModTime & 0x07E0) >> 5;
        $seconds = ($lastModTime & 0x001F) * 2;

        $year = (($lastModDate & 0xFE00) >> 9) + 1980;
        $month = ($lastModDate & 0x01E0) >> 5;
        $day = $lastModDate & 0x001F;

        // ----- Get UNIX date format
        $this->lastModDateTime = mktime($hour, $minute, $seconds, $month, $day, $year);
    }

    public function getLastModifyDosTime()
    {
        $date = getdate($this->lastModDateTime);
        return ($date['hours'] << 11) + ($date['minutes'] << 5) + $date['seconds'] / 2;
    }

    public function getLastModifyDosDate()
    {
        $date = getdate($this->lastModDateTime);
        return (($date['year'] - 1980) << 9) + ($date['mon'] << 5) + $date['mday'];
    }

    public function versionMadeToString()
    {
        if (isset(self::$valuesMadeBy[$this->versionMadeBy])) {
            return self::$valuesMadeBy[$this->versionMadeBy];
        } else return "unknown";
    }

    public function compressionMethodToString()
    {
        if (isset(self::$valuesCompressionMethod[$this->compressionMethod])) {
            return self::$valuesCompressionMethod[$this->compressionMethod];
        } else return "unknown";
    }

    public function flagToString()
    {
        $return = array();
        foreach (self::$valuesFlag AS $bit => $value) {
            if ($this->testFlagBit($bit)) {
                $return[] = $value;
            }
        }
        if (!empty($return)) {
            return implode(', ', $return);
        } else if ($this->flag === 0) {
            return "default";
        }
        return "unknown";
    }

    function __toString()
    {
        return __CLASS__ . '{' .
        'name="' . $this->name . '"' .
        ', versionMadeBy={' . $this->versionMadeBy . ' => "' . $this->versionMadeToString() . '"}' .
        ', versionExtract="' . $this->versionExtract . '"' .
        ', flag={' . $this->flag . ' => ' . $this->flagToString() . '}' .
        ', compressionMethod={' . $this->compressionMethod . ' => ' . $this->compressionMethodToString() . '}' .
        ', lastModify=' . date("Y-m-d H:i:s", $this->lastModDateTime) .
        ', crc32=0x' . dechex($this->crc32) .
        ', compressedSize=' . ZipUtils::humanSize($this->compressedSize) .
        ', unCompressedSize=' . ZipUtils::humanSize($this->unCompressedSize) .
        ', diskNumber=' . $this->diskNumber .
        ', internalAttributes=' . $this->internalAttributes .
        ', externalAttributes=' . $this->externalAttributes .
        ', offsetOfLocal=' . $this->offsetOfLocal .
        ', offsetOfCentral=' . $this->offsetOfCentral .
        ', extraCentral="' . $this->extraCentral . '"' .
        ', extraLocal="' . $this->extraLocal . '"' .
        ', comment="' . $this->comment . '"' .
        '}';
    }
}