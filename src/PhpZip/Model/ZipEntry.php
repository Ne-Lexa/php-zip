<?php
namespace PhpZip\Model;

use PhpZip\Exception\ZipException;
use PhpZip\Extra\DefaultExtraField;
use PhpZip\Extra\ExtraField;
use PhpZip\Extra\ExtraFields;
use PhpZip\Extra\WinZipAesEntryExtraField;
use PhpZip\Util\DateTimeConverter;
use PhpZip\Util\PackUtil;

/**
 * This class is used to represent a ZIP file entry.
 *
 * @see https://pkware.cachefly.net/webdocs/casestudies/APPNOTE.TXT .ZIP File Format Specification
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class ZipEntry
{
    // Bit masks for initialized fields.
    const BIT_PLATFORM = 1,
        BIT_METHOD = 2 /* 1 << 1 */,
        BIT_CRC = 2 /* 1 << 2 */,
        BIT_DATE_TIME = 64 /* 1 << 6 */,
        BIT_EXTERNAL_ATTR = 128 /* 1 << 7*/
    ;

    /** The unknown value for numeric properties. */
    const UNKNOWN = -1;

    /** Windows platform. */
    const PLATFORM_FAT = 0;

    /** Unix platform. */
    const PLATFORM_UNIX = 3;

    /** MacOS platform */
    const PLATFORM_OS_X = 19;

    /**
     * Method for Stored (uncompressed) entries.
     *
     * @see ZipEntry::setMethod()
     */
    const METHOD_STORED = 0;

    /**
     * Method for Deflated compressed entries.
     *
     * @see ZipEntry::setMethod()
     */
    const METHOD_DEFLATED = 8;

    /**
     * Method for BZIP2 compressed entries.
     * Require php extension bz2.
     *
     * @see ZipEntry::setMethod()
     */
    const METHOD_BZIP2 = 12;

    /**
     * Pseudo compression method for WinZip AES encrypted entries.
     * Require php extension openssl or mcrypt.
     */
    const WINZIP_AES = 99;

    /** General Purpose Bit Flag mask for encrypted data. */
    const GPBF_ENCRYPTED = 1;

    /** General Purpose Bit Flag mask for data descriptor. */
    const GPBF_DATA_DESCRIPTOR = 8; // 1 << 3;

    /** General Purpose Bit Flag mask for UTF-8. */
    const GPBF_UTF8 = 2048; // 1 << 11;

    /**
     * No specified method for set encryption method to Traditional PKWARE encryption.
     */
    const ENCRYPTION_METHOD_TRADITIONAL = 0;

    /**
     * No specified method for set encryption method to WinZip AES encryption.
     */
    const ENCRYPTION_METHOD_WINZIP_AES = 1;

    /**
     * bit flags for init state
     *
     * @var int
     */
    private $init;

    /**
     * Entry name (filename in archive)
     *
     * @var string
     */
    private $name;

    /**
     * Made by platform
     *
     * @var int
     */
    private $platform;

    /**
     * @var 2 bytes unsigned int
     *
     * @var int
     */
    private $general;

    /**
     * Compression method
     *
     * @var int
     */
    private $method;

    /**
     * Dos time
     *
     * @var int 4 bytes unsigned int
     */
    private $dosTime;

    /**
     * Crc32
     *
     * @var int
     */
    private $crc;

    /**
     * Compressed size
     *
     * @var int
     */
    private $compressedSize = self::UNKNOWN;

    /**
     * Uncompressed size
     *
     * @var int
     */
    private $size = self::UNKNOWN;

    /**
     * External attributes
     *
     * @var int
     */
    private $externalAttributes;

    /**
     * Relative Offset Of Local File Header.
     *
     * @var int
     */
    private $offset = self::UNKNOWN;

    /**
     * The map of Extra Fields.
     * Maps from Header ID [Integer] to Extra Field [ExtraField].
     * Should be null or may be empty if no Extra Fields are used.
     *
     * @var ExtraFields
     */
    private $fields;

    /**
     * Comment field.
     *
     * @var string
     */
    private $comment;

    /**
     * Entry password for read or write encryption data.
     *
     * @var string
     */
    private $password;

    /**
     * Encryption method.
     *
     * @see ZipEntry::ENCRYPTION_METHOD_TRADITIONAL
     * @see ZipEntry::ENCRYPTION_METHOD_WINZIP_AES
     * @var int
     */
    private $encryptionMethod = self::ENCRYPTION_METHOD_TRADITIONAL;

    /**
     * ZipEntry constructor.
     *
     * @param string $name
     * @throws ZipException
     */
    public function __construct($name)
    {
        $this->setName($name);
    }

    /**
     * Detect current platform
     *
     * @return int
     */
    public static function getCurrentPlatform()
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return self::PLATFORM_FAT;
        } elseif (PHP_OS === 'Darwin') {
            return self::PLATFORM_OS_X;
        } else {
            return self::PLATFORM_UNIX;
        }
    }

    /**
     * Clone extra fields
     */
    function __clone()
    {
        $this->fields = $this->fields !== null ? clone $this->fields : null;
    }

    /**
     * Returns the ZIP entry name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set entry name.
     *
     * @see ZipEntry::__construct
     * @see ZipOutputFile::rename()
     *
     * @param string $name New entry name
     * @throws ZipException
     */
    public function setName($name)
    {
        $length = strlen($name);
        if (0x0000 > $length || $length > 0xffff) {
            throw new ZipException('Illegal zip entry name parameter');
        }
        $encoding = mb_detect_encoding($this->name, "ASCII, UTF-8", true);
        $this->setGeneralPurposeBitFlag(self::GPBF_UTF8, $encoding === 'UTF-8');
        $this->name = $name;
    }

    /**
     * Get platform
     *
     * @return int
     */
    public function getPlatform()
    {
        return $this->isInit(self::BIT_PLATFORM) ? $this->platform & 0xffff : self::UNKNOWN;
    }

    /**
     * Set platform
     *
     * @param int $platform
     * @throws ZipException
     */
    public function setPlatform($platform)
    {
        $known = self::UNKNOWN !== $platform;
        if ($known) {
            if (0x00 > $platform || $platform > 0xff) {
                throw new ZipException("Platform out of range");
            }
            $this->platform = $platform;
        } else {
            $this->platform = 0;
        }
        $this->setInit(self::BIT_PLATFORM, $known);
    }

    /**
     * @param int $mask
     * @return bool
     */
    private function isInit($mask)
    {
        return 0 !== ($this->init & $mask);
    }

    /**
     * @param int $mask
     * @param bool $init
     */
    private function setInit($mask, $init)
    {
        if ($init) {
            $this->init |= $mask;
        } else {
            $this->init &= ~$mask;
        }
    }

    /**
     * @return int
     */
    public function getRawPlatform()
    {
        return $this->platform & 0xff;
    }

    /**
     * @param int $platform
     * @throws ZipException
     */
    public function setRawPlatform($platform)
    {
        if (0x00 > $platform || $platform > 0xff) {
            throw new ZipException("Platform out of range");
        }
        $this->platform = $platform;
        $this->setInit(self::BIT_PLATFORM, true);
    }

    /**
     * Version needed to extract.
     *
     * @return int
     */
    public function getVersionNeededToExtract()
    {
        $method = $this->getRawMethod();
        return self::WINZIP_AES === $method ? 51 :
            (self::METHOD_BZIP2 === $method ? 46 :
                ($this->isZip64ExtensionsRequired() ? 45 :
                    (self::METHOD_DEFLATED === $method || $this->isDirectory() ? 20 : 10
                    )
                )
            );
    }

    /**
     * @return int
     */
    public function getRawMethod()
    {
        return $this->method & 0xff;
    }

    /**
     * @return bool
     */
    public function isZip64ExtensionsRequired()
    {
        // Offset MUST be considered in decision about ZIP64 format - see
        // description of Data Descriptor in ZIP File Format Specification!
        return 0xffffffff <= $this->getCompressedSize()
        || 0xffffffff <= $this->getSize()
        || 0xffffffff <= $this->getOffset();
    }

    /**
     * Returns the compressed size of this entry.
     *
     * @see int
     */
    public function getCompressedSize()
    {
        return $this->compressedSize;
    }

    /**
     * Sets the compressed size of this entry.
     *
     * @param int $compressedSize The Compressed Size.
     * @throws ZipException
     */
    public function setCompressedSize($compressedSize)
    {
        if (self::UNKNOWN != $compressedSize) {
            if (0 > $compressedSize || $compressedSize > 0x7fffffffffffffff) {
                throw new ZipException("Compressed size out of range - " . $this->name);
            }
        }
        $this->compressedSize = $compressedSize;
    }

    /**
     * Returns the uncompressed size of this entry.
     *
     * @see #setCompressedSize
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * Sets the uncompressed size of this entry.
     *
     * @param int $size The (Uncompressed) Size.
     * @throws ZipException
     */
    public function setSize($size)
    {
        if (self::UNKNOWN != $size) {
            if (0 > $size || $size > 0x7fffffffffffffff) {
                throw new ZipException("Uncompressed Size out of range - " . $this->name);
            }
        }
        $this->size = $size;
    }

    /**
     * Return relative Offset Of Local File Header.
     *
     * @return int
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * Returns true if and only if this ZIP entry represents a directory entry
     * (i.e. end with '/').
     *
     * @return bool
     */
    public function isDirectory()
    {
        return $this->name[strlen($this->name) - 1] === '/';
    }

    /**
     * Returns the General Purpose Bit Flags.
     *
     * @return bool
     */
    public function getGeneralPurposeBitFlags()
    {
        return $this->general & 0xffff;
    }

    /**
     * Sets the General Purpose Bit Flags.
     *
     * @var int general
     * @throws ZipException
     */
    public function setGeneralPurposeBitFlags($general)
    {
        if (0x0000 > $general || $general > 0xffff) {
            throw new ZipException('general out of range');
        }
        $this->general = $general;
    }

    /**
     * Returns true if and only if this ZIP entry is encrypted.
     *
     * @return bool
     */
    public function isEncrypted()
    {
        return $this->getGeneralPurposeBitFlag(self::GPBF_ENCRYPTED);
    }

    /**
     * Returns the indexed General Purpose Bit Flag.
     *
     * @param int $mask
     * @return bool
     */
    public function getGeneralPurposeBitFlag($mask)
    {
        return 0 !== ($this->general & $mask);
    }

    /**
     * Sets the encryption property to false and removes any other
     * encryption artifacts.
     */
    public function clearEncryption()
    {
        $this->setEncrypted(false);
        $field = $this->fields->get(WinZipAesEntryExtraField::getHeaderId());
        if ($field !== null) {
            /**
             * @var WinZipAesEntryExtraField $field
             */
            $this->removeExtraField(WinZipAesEntryExtraField::getHeaderId());
        }
        if (self::WINZIP_AES === $this->getRawMethod()) {
            $this->setRawMethod(null === $field ? self::UNKNOWN : $field->getMethod());
        }
        $this->password = null;
    }

    /**
     * Sets the encryption flag for this ZIP entry.
     *
     * @param bool $encrypted
     */
    public function setEncrypted($encrypted)
    {
        $this->setGeneralPurposeBitFlag(self::GPBF_ENCRYPTED, $encrypted);
    }

    /**
     * Sets the indexed General Purpose Bit Flag.
     *
     * @param int $mask
     * @param bool $bit
     */
    public function setGeneralPurposeBitFlag($mask, $bit)
    {
        if ($bit)
            $this->general |= $mask;
        else
            $this->general &= ~$mask;
    }

    /**
     * Remove extra field from header id.
     *
     * @param int $headerId
     * @return ExtraField|null
     */
    public function removeExtraField($headerId)
    {
        return null !== $this->fields ? $this->fields->remove($headerId) : null;
    }

    /**
     * @param int $method
     * @throws ZipException
     */
    public function setRawMethod($method)
    {
        if (0x0000 > $method || $method > 0xffff) {
            throw new ZipException('method out of range');
        }
        $this->setMethod($method);
    }

    /**
     * Returns the compression method for this entry.
     *
     * @return int
     */
    public function getMethod()
    {
        return $this->isInit(self::BIT_METHOD) ? $this->method & 0xffff : self::UNKNOWN;
    }

    /**
     * Sets the compression method for this entry.
     *
     * @param int $method
     * @throws ZipException If method is not STORED, DEFLATED, BZIP2 or UNKNOWN.
     */
    public function setMethod($method)
    {
        switch ($method) {
            case self::WINZIP_AES:
                $this->method = $method;
                $this->setInit(self::BIT_METHOD, true);
                $this->setEncryptionMethod(self::ENCRYPTION_METHOD_WINZIP_AES);
                break;

            case self::METHOD_STORED:
            case self::METHOD_DEFLATED:
            case self::METHOD_BZIP2:
                $this->method = $method;
                $this->setInit(self::BIT_METHOD, true);
                break;

            case self::UNKNOWN:
                $this->method = 0;
                $this->setInit(self::BIT_METHOD, false);
                break;

            default:
                throw new ZipException($this->name . " (unsupported compression method $method)");
        }
    }

    /**
     * Get Unix Timestamp
     *
     * @return int
     */
    public function getTime()
    {
        if (!$this->isInit(self::BIT_DATE_TIME)) {
            return self::UNKNOWN;
        }
        return DateTimeConverter::toUnixTimestamp($this->dosTime & 0xffffffff);
    }

    /**
     * Set time from unix timestamp.
     *
     * @param int $unixTimestamp
     */
    public function setTime($unixTimestamp)
    {
        $known = self::UNKNOWN != $unixTimestamp;
        if ($known) {
            $this->dosTime = DateTimeConverter::toDosTime($unixTimestamp);
        } else {
            $this->dosTime = 0;
        }
        $this->setInit(self::BIT_DATE_TIME, $known);
    }

    /**
     * @return int
     */
    public function getRawTime()
    {
        return $this->dosTime & 0xffffffff;
    }

    /**
     * @param int $dtime
     * @throws ZipException
     */
    public function setRawTime($dtime)
    {
        if (0x00000000 > $dtime || $dtime > 0xffffffff) {
            throw new ZipException('dtime out of range');
        }
        $this->dosTime = $dtime;
        $this->setInit(self::BIT_DATE_TIME, true);
    }

    /**
     * @return int
     */
    public function getRawCrc()
    {
        return $this->crc & 0xffffffff;
    }

    /**
     * @param int $crc
     * @throws ZipException
     */
    public function setRawCrc($crc)
    {
        if (0x00000000 > $crc || $crc > 0xffffffff) {
            throw new ZipException("CRC-32 out of range - " . $this->name);
        }
        $this->crc = $crc;
        $this->setInit(self::BIT_CRC, true);
    }

    /**
     * Returns the external file attributes.
     *
     * @return int The external file attributes.
     */
    public function getExternalAttributes()
    {
        return $this->isInit(self::BIT_EXTERNAL_ATTR) ? $this->externalAttributes & 0xffffffff : self::UNKNOWN;
    }

    /**
     * Sets the external file attributes.
     *
     * @param int $externalAttributes the external file attributes.
     * @throws ZipException
     */
    public function setExternalAttributes($externalAttributes)
    {
        $known = self::UNKNOWN != $externalAttributes;
        if ($known) {
            if (0x00000000 > $externalAttributes || $externalAttributes > 0xffffffff) {
                throw new ZipException("external file attributes out of range - " . $this->name);
            }
            $this->externalAttributes = $externalAttributes;
        } else {
            $this->externalAttributes = 0;
        }
        $this->setInit(self::BIT_EXTERNAL_ATTR, $known);
    }

    /**
     * @return int
     */
    public function getRawExternalAttributes()
    {
        if (!$this->isInit(self::BIT_EXTERNAL_ATTR)) {
            return $this->isDirectory() ? 0x10 : 0;
        }
        return $this->externalAttributes & 0xffffffff;
    }

    /**
     * @param int $externalAttributes
     * @throws ZipException
     */
    public function setRawExternalAttributes($externalAttributes)
    {
        if (0x00000000 > $externalAttributes || $externalAttributes > 0xffffffff) {
            throw new ZipException("external file attributes out of range - " . $this->name);
        }
        $this->externalAttributes = $externalAttributes;
        $this->setInit(self::BIT_EXTERNAL_ATTR, true);
    }

    /**
     * Return extra field from header id.
     *
     * @param int $headerId
     * @return ExtraField|null
     */
    public function getExtraField($headerId)
    {
        return $this->fields === null ? null : $this->fields->get($headerId);
    }

    /**
     * Return exists extra field from header id.
     *
     * @param int $headerId
     * @return bool
     */
    public function hasExtraField($headerId)
    {
        return $this->fields === null ? false : $this->fields->has($headerId);
    }

    /**
     * Add extra field.
     *
     * @param ExtraField $field
     * @return ExtraField
     * @throws ZipException
     */
    public function addExtraField($field)
    {
        if (null === $field) {
            throw new ZipException("extra field null");
        }
        if (null === $this->fields) {
            $this->fields = new ExtraFields();
        }
        return $this->fields->add($field);
    }

    /**
     * Returns a protective copy of the serialized Extra Fields.
     *
     * @return string A new byte array holding the serialized Extra Fields.
     *                null is never returned.
     */
    public function getExtra()
    {
        return $this->getExtraFields(false);
    }

    /**
     * @param bool $zip64
     * @return bool|string
     * @throws ZipException
     */
    private function getExtraFields($zip64)
    {
        if ($zip64) {
            $field = $this->composeZip64ExtraField();
            if (null !== $field) {
                if (null === $this->fields) {
                    $this->fields = new ExtraFields();
                }
                $this->fields->add($field);
            }
        } else {
            assert(null === $this->fields || null === $this->fields->get(ExtraField::ZIP64_HEADER_ID));
        }
        return null === $this->fields ? null : $this->fields->getExtra();
    }

    /**
     * Composes a ZIP64 Extended Information Extra Field from the properties
     * of this entry.
     * If no ZIP64 Extended Information Extra Field is required it is removed
     * from the collection of Extra Fields.
     *
     * @return ExtraField|null
     */
    private function composeZip64ExtraField()
    {
        $off = 0;
        $fp = fopen('php://temp', 'r+b');
        // Write out Uncompressed Size.
        $size = $this->getSize();
        if (0xffffffff <= $size) {
            fseek($fp, $off, SEEK_SET);
            fwrite($fp, PackUtil::packLongLE($size));
            $off += 8;
        }
        // Write out Compressed Size.
        $compressedSize = $this->getCompressedSize();
        if (0xffffffff <= $compressedSize) {
            fseek($fp, $off, SEEK_SET);
            fwrite($fp, PackUtil::packLongLE($compressedSize));
            $off += 8;
        }
        // Write out Relative Header Offset.
        $offset = $this->getOffset();
        if (0xffffffff <= $offset) {
            fseek($fp, $off, SEEK_SET);
            fwrite($fp, PackUtil::packLongLE($offset));
            $off += 8;
        }
        // Create ZIP64 Extended Information Extra Field from serialized data.
        $field = null;
        if ($off > 0) {
            $field = new DefaultExtraField(ExtraField::ZIP64_HEADER_ID);
            $field->readFrom($fp, 0, $off);
        } else {
            $field = null;
        }
        return $field;
    }

    /**
     * Sets the serialized Extra Fields by making a protective copy.
     * Note that this method parses the serialized Extra Fields according to
     * the ZIP File Format Specification and limits its size to 64 KB.
     * Therefore, this property cannot not be used to hold arbitrary
     * (application) data.
     * Consider storing such data in a separate entry instead.
     *
     * @param string $data The byte array holding the serialized Extra Fields.
     * @throws ZipException if the serialized Extra Fields exceed 64 KB
     *         or do not conform to the ZIP File Format Specification
     */
    public function setExtra($data)
    {
        if (null !== $data) {
            $length = strlen($data);
            if (0x0000 > $length || $length > 0xffff) {
                throw new ZipException("Extra Fields too large");
            }
        }
        if (null === $data || strlen($data) <= 0) {
            $this->fields = null;
        } else {
            $this->setExtraFields($data, false);
        }
    }

    /**
     * @param string $data
     * @param bool $zip64
     */
    private function setExtraFields($data, $zip64)
    {
        if (null === $this->fields) {
            $this->fields = new ExtraFields();
        }
        $fp = fopen('php://temp', 'r+b');
        fwrite($fp, $data);
        rewind($fp);
        $this->fields->readFrom($fp, 0, strlen($data));
        $result = false;
        if ($zip64) {
            $result = $this->parseZip64ExtraField();
        }
        if ($result) {
            $this->fields->remove(ExtraField::ZIP64_HEADER_ID);
            if ($this->fields->size() <= 0) {
                if (0 !== $this->fields->size()) {
                    $this->fields = null;
                }
            }
        }
        fclose($fp);
    }

    /**
     * Parses the properties of this entry from the ZIP64 Extended Information
     * Extra Field, if present.
     * The ZIP64 Extended Information Extra Field is not removed.
     *
     * @return bool
     * @throws ZipException
     */
    private function parseZip64ExtraField()
    {
        if (null === $this->fields) {
            return false;
        }
        $ef = $this->fields->get(ExtraField::ZIP64_HEADER_ID);
        if (null === $ef) {
            return false;
        }
        $handle = $ef->getDataBlock();
        $off = 0;
        // Read in Uncompressed Size.
        $size = $this->getRawSize();
        if (0xffffffff <= $size) {
            assert(0xffffffff === $size);
            fseek($handle, $off, SEEK_SET);
            $this->setRawSize(PackUtil::unpackLongLE(fread($handle, 8)));
            $off += 8;
        }
        // Read in Compressed Size.
        $compressedSize = $this->getRawCompressedSize();
        if (0xffffffff <= $compressedSize) {
            assert(0xffffffff === $compressedSize);
            fseek($handle, $off, SEEK_SET);
            $this->setRawCompressedSize(PackUtil::unpackLongLE(fread($handle, 8)));
            $off += 8;
        }
        // Read in Relative Header Offset.
        $offset = $this->getRawOffset();
        if (0xffffffff <= $offset) {
            assert(0xffffffff, $offset);
            fseek($handle, $off, SEEK_SET);
            $this->setRawOffset(PackUtil::unpackLongLE(fread($handle, 8)));
            //$off += 8;
        }
        fclose($handle);
        return true;
    }

    /**
     * @return int
     */
    public function getRawSize()
    {
        $size = $this->size;
        if (self::UNKNOWN == $size) return 0;
        return 0xffffffff <= $size ? 0xffffffff : $size;
    }

    /**
     * @param int $size
     * @throws ZipException
     */
    public function setRawSize($size)
    {
        if (0 > $size || $size > 0x7fffffffffffffff) {
            throw new ZipException("Uncompressed Size out of range - " . $this->name);
        }
        $this->size = $size;
    }

    /**
     * @return int
     */
    public function getRawCompressedSize()
    {
        $compressedSize = $this->compressedSize;
        if (self::UNKNOWN == $compressedSize) return 0;
        return 0xffffffff <= $compressedSize
            ? 0xffffffff
            : $compressedSize;
    }

    /**
     * @param int $compressedSize
     * @throws ZipException
     */
    public function setRawCompressedSize($compressedSize)
    {
        if (0 > $compressedSize || $compressedSize > 0x7fffffffffffffff) {
            throw new ZipException("Compressed size out of range - " . $this->name);
        }
        $this->compressedSize = $compressedSize;
    }

    /**
     * @return int
     */
    public function getRawOffset()
    {
        $offset = $this->offset;
        if (self::UNKNOWN == $offset) return 0;
        return 0xffffffff <= $offset ? 0xffffffff : $offset;
    }

    /**
     * Set relative Offset Of Local File Header.
     *
     * @param int $offset
     * @throws ZipException
     */
    public function setRawOffset($offset)
    {
        if (0 > $offset || $offset > 0x7fffffffffffffff) {
            throw new ZipException("Offset out of range - " . $this->name);
        }
        $this->offset = $offset;
    }

    /**
     * Returns a protective copy of the serialized Extra Fields.
     *
     * @return string A new byte array holding the serialized Extra Fields.
     *                null is never returned.
     * @see ZipEntry::getRawExtraFields()
     */
    public function getRawExtraFields()
    {
        return $this->getExtraFields(true);
    }

    /**
     * Sets extra fields and parses ZIP64 extra field.
     * This method must not get called before the uncompressed size,
     * compressed size and offset have been initialized!
     *
     * @param string $data
     * @throws ZipException
     */
    public function setRawExtraFields($data)
    {
        $length = strlen($data);
        if (0 < $length && (0x0000 > $length || $length > 0xffff)) {
            throw new ZipException("Extra Fields too large");
        }
        $this->setExtraFields($data, true);
    }

    /**
     * Returns comment entry
     *
     * @return string
     */
    public function getComment()
    {
        return $this->comment;
    }

    /**
     * Sets the entry comment.
     * Note that this method limits the comment size to 64 KB.
     * Therefore, this property should not be used to hold arbitrary
     * (application) data.
     * Consider storing such data in a separate entry instead.
     *
     * @param string $comment The entry comment.
     * @throws ZipException
     */
    public function setComment($comment)
    {
        if (null !== $comment) {
            $commentLength = strlen($comment);
            if (0x0000 > $commentLength || $commentLength > 0xffff) {
                throw new ZipException("Comment too long");
            }
        }
        $encoding = mb_detect_encoding($this->name, "ASCII, UTF-8", true);
        if ($encoding === 'UTF-8') {
            $this->setGeneralPurposeBitFlag(self::GPBF_UTF8, true);
        }
        $this->comment = $comment;
    }

    /**
     * @return string
     */
    public function getRawComment()
    {
        return null != $this->comment ? $this->comment : "";
    }

    /**
     * @param string $comment
     * @throws ZipException
     */
    public function setRawComment($comment)
    {
        $commentLength = strlen($comment);
        if (0x0000 > $commentLength || $commentLength > 0xffff) {
            throw new ZipException("Comment too long");
        }
        $this->comment = $comment;
    }

    /**
     * @return bool
     */
    public function isDataDescriptorRequired()
    {
        return self::UNKNOWN == ($this->getCrc() | $this->getCompressedSize() | $this->getSize());
    }

    /**
     * Return crc32 content or 0 for WinZip AES v2
     *
     * @return int
     */
    public function getCrc()
    {
        return $this->isInit(self::BIT_CRC) ? $this->crc & 0xffffffff : self::UNKNOWN;
    }

    /**
     * Set crc32 content.
     *
     * @param int $crc
     * @throws ZipException
     */
    public function setCrc($crc)
    {
        $known = self::UNKNOWN != $crc;
        if ($known) {
            if (0x00000000 > $crc || $crc > 0xffffffff) {
                throw new ZipException("CRC-32 out of range - " . $this->name);
            }
            $this->crc = $crc;
        } else {
            $this->crc = 0;
        }
        $this->setInit(self::BIT_CRC, $known);
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * Set password and encryption method from entry
     *
     * @param string $password
     * @param null|int $encryptionMethod
     */
    public function setPassword($password, $encryptionMethod = null)
    {
        $this->password = $password;
        if ($encryptionMethod !== null) {
            $this->setEncryptionMethod($encryptionMethod);
        }
        $this->setEncrypted(!empty($this->password));
    }

    /**
     * @return int
     */
    public function getEncryptionMethod()
    {
        return $this->encryptionMethod;
    }

    /**
     * Set encryption method
     *
     * @see ZipEntry::ENCRYPTION_METHOD_TRADITIONAL
     * @see ZipEntry::ENCRYPTION_METHOD_WINZIP_AES
     *
     * @param int $encryptionMethod
     * @throws ZipException
     */
    public function setEncryptionMethod($encryptionMethod)
    {
        if (
            self::ENCRYPTION_METHOD_TRADITIONAL !== $encryptionMethod &&
            self::ENCRYPTION_METHOD_WINZIP_AES !== $encryptionMethod
        ) {
            throw new ZipException('Invalid encryption method');
        }
        $this->encryptionMethod = $encryptionMethod;
        $this->setEncrypted(true);
    }

}