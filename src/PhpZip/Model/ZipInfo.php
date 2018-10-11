<?php /** @noinspection PhpMissingBreakStatementInspection */

namespace PhpZip\Model;

use PhpZip\Extra\Fields\NtfsExtraField;
use PhpZip\Extra\Fields\WinZipAesEntryExtraField;
use PhpZip\Util\FilesUtil;
use PhpZip\ZipFileInterface;

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

    const UNX_IFMT = 0170000;    /* Unix file type mask */
    const UNX_IFREG = 0100000;    /* Unix regular file */
    const UNX_IFSOCK = 0140000;     /* Unix socket (BSD, not SysV or Amiga) */
    const UNX_IFLNK = 0120000;    /* Unix symbolic link (not SysV, Amiga) */
    const UNX_IFBLK = 0060000;    /* Unix block special       (not Amiga) */
    const UNX_IFDIR = 0040000;    /* Unix directory */
    const UNX_IFCHR = 0020000;    /* Unix character special   (not Amiga) */
    const UNX_IFIFO = 0010000;    /* Unix fifo    (BCC, not MSC or Amiga) */
    const UNX_ISUID = 04000;      /* Unix set user id on execution */
    const UNX_ISGID = 02000;      /* Unix set group id on execution */
    const UNX_ISVTX = 01000;      /* Unix directory permissions control */
    const UNX_ENFMT = self::UNX_ISGID;  /* Unix record locking enforcement flag */
    const UNX_IRWXU = 00700;      /* Unix read, write, execute: owner */
    const UNX_IRUSR = 00400;      /* Unix read permission: owner */
    const UNX_IWUSR = 00200;      /* Unix write permission: owner */
    const UNX_IXUSR = 00100;      /* Unix execute permission: owner */
    const UNX_IRWXG = 00070;      /* Unix read, write, execute: group */
    const UNX_IRGRP = 00040;      /* Unix read permission: group */
    const UNX_IWGRP = 00020;      /* Unix write permission: group */
    const UNX_IXGRP = 00010;      /* Unix execute permission: group */
    const UNX_IRWXO = 00007;      /* Unix read, write, execute: other */
    const UNX_IROTH = 00004;      /* Unix read permission: other */
    const UNX_IWOTH = 00002;      /* Unix write permission: other */
    const UNX_IXOTH = 00001;      /* Unix execute permission: other */

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
        ZipEntry::UNKNOWN => 'unknown',
        ZipFileInterface::METHOD_STORED => 'no compression',
        1 => 'shrink',
        2 => 'reduce level 1',
        3 => 'reduce level 2',
        4 => 'reduce level 3',
        5 => 'reduce level 4',
        6 => 'implode',
        7 => 'reserved for Tokenizing compression algorithm',
        ZipFileInterface::METHOD_DEFLATED => 'deflate',
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
        ZipEntry::METHOD_WINZIP_AES => 'WinZip AES',
    ];

    /**
     * @var string
     */
    private $name;
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
    private $methodName;
    /**
     * @var int
     */
    private $compressionMethod;
    /**
     * @var string
     */
    private $platform;
    /**
     * @var int
     */
    private $version;
    /**
     * @var string
     */
    private $attributes;
    /**
     * @var int|null
     */
    private $encryptionMethod;
    /**
     * @var int|null
     */
    private $compressionLevel;

    /**
     * ZipInfo constructor.
     *
     * @param ZipEntry $entry
     * @throws \PhpZip\Exception\ZipException
     */
    public function __construct(ZipEntry $entry)
    {
        $mtime = $entry->getTime();
        $atime = null;
        $ctime = null;

        $field = $entry->getExtraFieldsCollection()->get(NtfsExtraField::getHeaderId());
        if (null !== $field && $field instanceof NtfsExtraField) {
            /**
             * @var NtfsExtraField $field
             */
            $atime = $field->getAtime();
            $ctime = $field->getCtime();
            $mtime = $field->getMtime();
        }

        $this->name = $entry->getName();
        $this->folder = $entry->isDirectory();
        $this->size = PHP_INT_SIZE === 4 ?
            sprintf('%u', $entry->getSize()) :
            $entry->getSize();
        $this->compressedSize = PHP_INT_SIZE === 4 ?
            sprintf('%u', $entry->getCompressedSize()) :
            $entry->getCompressedSize();
        $this->mtime = $mtime;
        $this->ctime = $ctime;
        $this->atime = $atime;
        $this->encrypted = $entry->isEncrypted();
        $this->encryptionMethod = $entry->getEncryptionMethod();
        $this->comment = $entry->getComment();
        $this->crc = $entry->getCrc();
        $this->compressionMethod = self::getMethodId($entry);
        $this->methodName = self::getEntryMethodName($entry);
        $this->platform = self::getPlatformName($entry);
        $this->version = $entry->getVersionNeededToExtract();
        $this->compressionLevel = $entry->getCompressionLevel();

        $attributes = str_repeat(" ", 12);
        $externalAttributes = $entry->getExternalAttributes();
        $externalAttributes = PHP_INT_SIZE === 4 ?
            sprintf('%u', $externalAttributes) :
            $externalAttributes;
        $xattr = (($externalAttributes >> 16) & 0xFFFF);
        switch ($entry->getPlatform()) {
            case self::MADE_BY_MS_DOS:
            case self::MADE_BY_WINDOWS_NTFS:
                if ($entry->getPlatform() != self::MADE_BY_MS_DOS ||
                    ($xattr & 0700) !=
                    (0400 |
                        (!($externalAttributes & 1) << 7) |
                        (($externalAttributes & 0x10) << 2))
                ) {
                    $xattr = $externalAttributes & 0xFF;
                    $attributes = ".r.-...     ";
                    $attributes[2] = ($xattr & 0x01) ? '-' : 'w';
                    $attributes[5] = ($xattr & 0x02) ? 'h' : '-';
                    $attributes[6] = ($xattr & 0x04) ? 's' : '-';
                    $attributes[4] = ($xattr & 0x20) ? 'a' : '-';
                    if ($xattr & 0x10) {
                        $attributes[0] = 'd';
                        $attributes[3] = 'x';
                    } else {
                        $attributes[0] = '-';
                    }
                    if ($xattr & 0x08) {
                        $attributes[0] = 'V';
                    } else {
                        $ext = strtolower(pathinfo($entry->getName(), PATHINFO_EXTENSION));
                        if (in_array($ext, ["com", "exe", "btm", "cmd", "bat"])) {
                            $attributes[3] = 'x';
                        }
                    }
                    break;
                } /* else: fall through! */

            // no break
            default: /* assume Unix-like */
                switch ($xattr & self::UNX_IFMT) {
                    case self::UNX_IFDIR:
                        $attributes[0] = 'd';
                        break;
                    case self::UNX_IFREG:
                        $attributes[0] = '-';
                        break;
                    case self::UNX_IFLNK:
                        $attributes[0] = 'l';
                        break;
                    case self::UNX_IFBLK:
                        $attributes[0] = 'b';
                        break;
                    case self::UNX_IFCHR:
                        $attributes[0] = 'c';
                        break;
                    case self::UNX_IFIFO:
                        $attributes[0] = 'p';
                        break;
                    case self::UNX_IFSOCK:
                        $attributes[0] = 's';
                        break;
                    default:
                        $attributes[0] = '?';
                        break;
                }
                $attributes[1] = ($xattr & self::UNX_IRUSR) ? 'r' : '-';
                $attributes[4] = ($xattr & self::UNX_IRGRP) ? 'r' : '-';
                $attributes[7] = ($xattr & self::UNX_IROTH) ? 'r' : '-';
                $attributes[2] = ($xattr & self::UNX_IWUSR) ? 'w' : '-';
                $attributes[5] = ($xattr & self::UNX_IWGRP) ? 'w' : '-';
                $attributes[8] = ($xattr & self::UNX_IWOTH) ? 'w' : '-';

                if ($xattr & self::UNX_IXUSR) {
                    $attributes[3] = ($xattr & self::UNX_ISUID) ? 's' : 'x';
                } else {
                    $attributes[3] = ($xattr & self::UNX_ISUID) ? 'S' : '-';
                }  /* S==undefined */
                if ($xattr & self::UNX_IXGRP) {
                    $attributes[6] = ($xattr & self::UNX_ISGID) ? 's' : 'x';
                }  /* == UNX_ENFMT */
                else {
                    $attributes[6] = ($xattr & self::UNX_ISGID) ? 'S' : '-';
                }  /* SunOS 4.1.x */
                if ($xattr & self::UNX_IXOTH) {
                    $attributes[9] = ($xattr & self::UNX_ISVTX) ? 't' : 'x';
                }  /* "sticky bit" */
                else {
                    $attributes[9] = ($xattr & self::UNX_ISVTX) ? 'T' : '-';
                }  /* T==undefined */
        }
        $this->attributes = trim($attributes);
    }

    /**
     * @param ZipEntry $entry
     * @return int
     * @throws \PhpZip\Exception\ZipException
     */
    private static function getMethodId(ZipEntry $entry)
    {
        $method = $entry->getMethod();
        if ($entry->isEncrypted()) {
            if ($entry->getMethod() === ZipEntry::METHOD_WINZIP_AES) {
                $field = $entry->getExtraFieldsCollection()->get(WinZipAesEntryExtraField::getHeaderId());
                if (null !== $field) {
                    /**
                     * @var WinZipAesEntryExtraField $field
                     */
                    $method = $field->getMethod();
                }
            }
        }
        return $method;
    }

    /**
     * @param ZipEntry $entry
     * @return string
     * @throws \PhpZip\Exception\ZipException
     */
    private static function getEntryMethodName(ZipEntry $entry)
    {
        $return = '';
        if ($entry->isEncrypted()) {
            if ($entry->getMethod() === ZipEntry::METHOD_WINZIP_AES) {
                $return = ucfirst(self::$valuesCompressionMethod[$entry->getMethod()]);
                $field = $entry->getExtraFieldsCollection()->get(WinZipAesEntryExtraField::getHeaderId());
                if (null !== $field) {
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
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     * @deprecated use \PhpZip\Model\ZipInfo::getName()
     */
    public function getPath()
    {
        return $this->getName();
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
     * @return string
     */
    public function getAttributes()
    {
        return $this->attributes;
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
     * @deprecated use \PhpZip\Model\ZipInfo::getMethodName()
     */
    public function getMethod()
    {
        return $this->getMethodName();
    }

    /**
     * @return string
     */
    public function getMethodName()
    {
        return $this->methodName;
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
     * @return int|null
     */
    public function getEncryptionMethod()
    {
        return $this->encryptionMethod;
    }

    /**
     * @return int|null
     */
    public function getCompressionLevel()
    {
        return $this->compressionLevel;
    }

    /**
     * @return int
     */
    public function getCompressionMethod()
    {
        return $this->compressionMethod;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'name' => $this->getName(),
            'path' => $this->getName(), // deprecated
            'folder' => $this->isFolder(),
            'size' => $this->getSize(),
            'compressed_size' => $this->getCompressedSize(),
            'modified' => $this->getMtime(),
            'created' => $this->getCtime(),
            'accessed' => $this->getAtime(),
            'attributes' => $this->getAttributes(),
            'encrypted' => $this->isEncrypted(),
            'encryption_method' => $this->getEncryptionMethod(),
            'comment' => $this->getComment(),
            'crc' => $this->getCrc(),
            'method' => $this->getMethodName(), // deprecated
            'method_name' => $this->getMethodName(),
            'compression_method' => $this->getCompressionMethod(),
            'platform' => $this->getPlatform(),
            'version' => $this->getVersion()
        ];
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return __CLASS__ . ' {'
            . 'Name="' . $this->getName() . '", '
            . ($this->isFolder() ? 'Folder, ' : '')
            . 'Size="' . FilesUtil::humanSize($this->getSize()) . '"'
            . ', Compressed size="' . FilesUtil::humanSize($this->getCompressedSize()) . '"'
            . ', Modified time="' . date(DATE_W3C, $this->getMtime()) . '", '
            . ($this->getCtime() !== null ? 'Created time="' . date(DATE_W3C, $this->getCtime()) . '", ' : '')
            . ($this->getAtime() !== null ? 'Accessed time="' . date(DATE_W3C, $this->getAtime()) . '", ' : '')
            . ($this->isEncrypted() ? 'Encrypted, ' : '')
            . (!empty($this->comment) ? 'Comment="' . $this->getComment() . '", ' : '')
            . (!empty($this->crc) ? 'Crc=0x' . dechex($this->getCrc()) . ', ' : '')
            . 'Method name="' . $this->getMethodName() . '", '
            . 'Attributes="' . $this->getAttributes() . '", '
            . 'Platform="' . $this->getPlatform() . '", '
            . 'Version=' . $this->getVersion()
            . '}';
    }
}
