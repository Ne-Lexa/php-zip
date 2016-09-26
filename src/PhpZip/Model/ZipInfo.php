<?php
namespace PhpZip\Model;

use PhpZip\Extra\NtfsExtraField;
use PhpZip\Extra\WinZipAesEntryExtraField;
use PhpZip\Util\FilesUtil;

/**
 * Zip info
 *
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class ZipInfo
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

    private static $valuesMadeBy = [
        self::MADE_BY_MS_DOS => 'FAT',
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
        self::MADE_BY_ALTERNATE_MVS => 'Alternate MVS',
        self::MADE_BY_BEOS => 'BeOS',
        self::MADE_BY_TANDEM => 'Tandem',
        self::MADE_BY_OS_400 => 'OS/400',
        self::MADE_BY_OS_X => 'Mac OS X',
    ];

    private static $valuesCompressionMethod = [
        ZipEntry::METHOD_STORED => 'no compression',
        1 => 'shrink',
        2 => 'reduce level 1',
        3 => 'reduce level 2',
        4 => 'reduce level 3',
        5 => 'reduce level 4',
        6 => 'implode',
        7 => 'reserved for Tokenizing compression algorithm',
        ZipEntry::METHOD_DEFLATED => 'deflate',
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
        ZipEntry::WINZIP_AES => 'WinZip AES',
    ];

    /**
     * @var string
     */
    private $path;

    /**
     * @var bool
     */
    private $folder;

    /**
     * @var int
     */
    private $size;

    /**
     * @var int
     */
    private $compressedSize;

    /**
     * @var int
     */
    private $mtime;

    /**
     * @var int|null
     */
    private $ctime;

    /**
     * @var int|null
     */
    private $atime;

    /**
     * @var bool
     */
    private $encrypted;

    /**
     * @var string|null
     */
    private $comment;

    /**
     * @var int
     */
    private $crc;

    /**
     * @var string
     */
    private $method;

    /**
     * @var string
     */
    private $platform;

    /**
     * @var int
     */
    private $version;

    /**
     * ZipInfo constructor.
     *
     * @param ZipEntry $entry
     */
    public function __construct(ZipEntry $entry)
    {
        $mtime = $entry->getTime();
        $atime = null;
        $ctime = null;

        $field = $entry->getExtraField(NtfsExtraField::getHeaderId());
        if ($field !== null && $field instanceof NtfsExtraField) {
            /**
             * @var NtfsExtraField $field
             */
            $atime = $field->getAtime();
            $ctime = $field->getCtime();
        }

        $this->path = $entry->getName();
        $this->folder = $entry->isDirectory();
        $this->size = $entry->getSize();
        $this->compressedSize = $entry->getCompressedSize();
        $this->mtime = $mtime;
        $this->ctime = $ctime;
        $this->atime = $atime;
        $this->encrypted = $entry->isEncrypted();
        $this->comment = $entry->getComment();
        $this->crc = $entry->getCrc();
        $this->method = self::getMethodName($entry);
        $this->platform = self::getPlatformName($entry);
        $this->version = $entry->getVersionNeededToExtract();
    }

    /**
     * @param ZipEntry $entry
     * @return string
     */
    public static function getMethodName(ZipEntry $entry)
    {
        $return = '';
        if ($entry->isEncrypted()) {
            if ($entry->getMethod() === ZipEntry::WINZIP_AES) {
                $field = $entry->getExtraField(WinZipAesEntryExtraField::getHeaderId());
                $return = ucfirst(self::$valuesCompressionMethod[$entry->getMethod()]);
                if ($field !== null) {
                    /**
                     * @var WinZipAesEntryExtraField $field
                     */
                    $return .= '-' . $field->getKeyStrength();
                    if (isset(self::$valuesCompressionMethod[$field->getMethod()])) {
                        $return .= ' ' . ucfirst(self::$valuesCompressionMethod[$field->getMethod()]);
                    }
                }
            } else {
                $return .= 'ZipCrypto';
                if (isset(self::$valuesCompressionMethod[$entry->getMethod()])) {
                    $return .= ' ' . ucfirst(self::$valuesCompressionMethod[$entry->getMethod()]);
                }
            }
        } elseif (isset(self::$valuesCompressionMethod[$entry->getMethod()])) {
            $return = ucfirst(self::$valuesCompressionMethod[$entry->getMethod()]);
        } else {
            $return = 'unknown';
        }
        return $return;
    }

    /**
     * @param ZipEntry $entry
     * @return string
     */
    public static function getPlatformName(ZipEntry $entry)
    {
        if (isset(self::$valuesMadeBy[$entry->getPlatform()])) {
            return self::$valuesMadeBy[$entry->getPlatform()];
        } else {
            return 'unknown';
        }
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'path' => $this->getPath(),
            'folder' => $this->isFolder(),
            'size' => $this->getSize(),
            'compressed_size' => $this->getCompressedSize(),
            'modified' => $this->getMtime(),
            'created' => $this->getCtime(),
            'accessed' => $this->getAtime(),
            'encrypted' => $this->isEncrypted(),
            'comment' => $this->getComment(),
            'crc' => $this->getCrc(),
            'method' => $this->getMethod(),
            'platform' => $this->getPlatform(),
            'version' => $this->getVersion()
        ];
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @return boolean
     */
    public function isFolder()
    {
        return $this->folder;
    }

    /**
     * @return int
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * @return int
     */
    public function getCompressedSize()
    {
        return $this->compressedSize;
    }

    /**
     * @return int
     */
    public function getMtime()
    {
        return $this->mtime;
    }

    /**
     * @return int|null
     */
    public function getCtime()
    {
        return $this->ctime;
    }

    /**
     * @return int|null
     */
    public function getAtime()
    {
        return $this->atime;
    }

    /**
     * @return boolean
     */
    public function isEncrypted()
    {
        return $this->encrypted;
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
    public function getCrc()
    {
        return $this->crc;
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @return string
     */
    public function getPlatform()
    {
        return $this->platform;
    }

    /**
     * @return int
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @return string
     */
    function __toString()
    {
        return 'ZipInfo {'
        . 'Path="' . $this->getPath() . '", '
        . ($this->isFolder() ? 'Folder, ' : '')
        . 'Size=' . FilesUtil::humanSize($this->getSize())
        . ', Compressed size=' . FilesUtil::humanSize($this->getCompressedSize())
        . ', Modified time=' . date(DATE_W3C, $this->getMtime()) . ', '
        . ($this->getCtime() !== null ? 'Created time=' . date(DATE_W3C, $this->getCtime()) . ', ' : '')
        . ($this->getAtime() !== null ? 'Accessed time=' . date(DATE_W3C, $this->getAtime()) . ', ' : '')
        . ($this->isEncrypted() ? 'Encrypted, ' : '')
        . (!empty($this->comment) ? 'Comment="' . $this->getComment() . '", ' : '')
        . (!empty($this->crc) ? 'Crc=0x' . dechex($this->getCrc()) . ', ' : '')
        . 'Method="' . $this->getMethod() . '", '
        . 'Platform="' . $this->getPlatform() . '", '
        . 'Version=' . $this->getVersion()
        . '}';
    }


}