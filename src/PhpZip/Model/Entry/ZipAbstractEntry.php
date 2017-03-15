<?php
namespace PhpZip\Model\Entry;

use PhpZip\Exception\InvalidArgumentException;
use PhpZip\Exception\ZipException;
use PhpZip\Extra\DefaultExtraField;
use PhpZip\Extra\ExtraField;
use PhpZip\Extra\ExtraFields;
use PhpZip\Extra\WinZipAesEntryExtraField;
use PhpZip\Model\CentralDirectory;
use PhpZip\Model\ZipEntry;
use PhpZip\Util\DateTimeConverter;
use PhpZip\Util\PackUtil;
use PhpZip\ZipFile;

/**
 * Abstract ZIP entry.
 *
 * @see https://pkware.cachefly.net/webdocs/casestudies/APPNOTE.TXT .ZIP File Format Specification
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
abstract class ZipAbstractEntry implements ZipEntry
{
    /**
     * @var CentralDirectory
     */
    private $centralDirectory;

    /**
     * @var int Bit flags for init state.
     */
    private $init;

    /**
     * @var string Entry name (filename in archive)
     */
    private $name;
    /**
     * @var int Made by platform
     */
    private $platform;
    /**
     * @var int
     */
    private $versionNeededToExtract = 20;
    /**
     * @var int
     */
    private $general;
    /**
     * @var int Compression method
     */
    private $method;
    /**
     * @var int Dos time
     */
    private $dosTime;
    /**
     * @var int Crc32
     */
    private $crc;
    /**
     * @var int Compressed size
     */
    private $compressedSize = self::UNKNOWN;
    /**
     * @var int Uncompressed size
     */
    private $size = self::UNKNOWN;
    /**
     * @var int External attributes
     */
    private $externalAttributes;
    /**
     * @var int Relative Offset Of Local File Header.
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
     * @var string Comment field.
     */
    private $comment;
    /**
     * @var string Entry password for read or write encryption data.
     */
    private $password;
    /**
     * Encryption method.
     * @see ZipFile::ENCRYPTION_METHOD_TRADITIONAL
     * @see ZipFile::ENCRYPTION_METHOD_WINZIP_AES
     * @var int
     */
    private $encryptionMethod = ZipFile::ENCRYPTION_METHOD_TRADITIONAL;

    /**
     * @var int
     */
    private $compressionLevel = ZipFile::LEVEL_DEFAULT_COMPRESSION;

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
     * @return CentralDirectory
     */
    public function getCentralDirectory()
    {
        return $this->centralDirectory;
    }

    /**
     * @param CentralDirectory $centralDirectory
     * @return ZipEntry
     */
    public function setCentralDirectory(CentralDirectory $centralDirectory)
    {
        $this->centralDirectory = $centralDirectory;
        return $this;
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
     * @param string $name New entry name
     * @return ZipEntry
     * @throws ZipException
     */
    public function setName($name)
    {
        $length = strlen($name);
        if (0x0000 > $length || $length > 0xffff) {
            throw new ZipException('Illegal zip entry name parameter');
        }
        $this->setGeneralPurposeBitFlag(self::GPBF_UTF8, true);
        $this->name = $name;
        return $this;
    }

    /**
     * @return int Get platform
     */
    public function getPlatform()
    {
        return $this->isInit(self::BIT_PLATFORM) ? $this->platform & 0xffff : self::UNKNOWN;
    }

    /**
     * Set platform
     *
     * @param int $platform
     * @return ZipEntry
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
        return $this;
    }

    /**
     * Version needed to extract.
     *
     * @return int
     */
    public function getVersionNeededToExtract()
    {
        return $this->versionNeededToExtract;
    }

    /**
     * Set version needed to extract.
     *
     * @param int $version
     * @return ZipEntry
     */
    public function setVersionNeededToExtract($version)
    {
        $this->versionNeededToExtract = $version;
        return $this;
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
     * @return ZipEntry
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
        return $this;
    }

    /**
     * Returns the uncompressed size of this entry.
     *
     * @see ZipEntry::setCompressedSize
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * Sets the uncompressed size of this entry.
     *
     * @param int $size The (Uncompressed) Size.
     * @return ZipEntry
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
        return $this;
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
     * @param int $offset
     * @return ZipEntry
     * @throws ZipException
     */
    public function setOffset($offset)
    {
        if (0 > $offset || $offset > 0x7fffffffffffffff) {
            throw new ZipException("Offset out of range - " . $this->name);
        }
        $this->offset = $offset;
        return $this;
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
     * @return ZipEntry
     * @throws ZipException
     */
    public function setGeneralPurposeBitFlags($general)
    {
        if (0x0000 > $general || $general > 0xffff) {
            throw new ZipException('general out of range');
        }
        $this->general = $general;
        return $this;
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
     * Sets the indexed General Purpose Bit Flag.
     *
     * @param int $mask
     * @param bool $bit
     * @return ZipEntry
     */
    public function setGeneralPurposeBitFlag($mask, $bit)
    {
        if ($bit)
            $this->general |= $mask;
        else
            $this->general &= ~$mask;
        return $this;
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
     * Sets the encryption property to false and removes any other
     * encryption artifacts.
     *
     * @return ZipEntry
     */
    public function clearEncryption()
    {
        $this->setEncrypted(false);
        if (null !== $this->fields) {
            $field = $this->fields->get(WinZipAesEntryExtraField::getHeaderId());
            if (null !== $field) {
                /**
                 * @var WinZipAesEntryExtraField $field
                 */
                $this->removeExtraField(WinZipAesEntryExtraField::getHeaderId());
            }
            if (self::METHOD_WINZIP_AES === $this->getMethod()) {
                $this->setMethod(null === $field ? self::UNKNOWN : $field->getMethod());
            }
        }
        $this->password = null;
        return $this;
    }

    /**
     * Sets the encryption flag for this ZIP entry.
     *
     * @param bool $encrypted
     * @return ZipEntry
     */
    public function setEncrypted($encrypted)
    {
        $this->setGeneralPurposeBitFlag(self::GPBF_ENCRYPTED, $encrypted);
        return $this;
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
     * @return ZipEntry
     * @throws ZipException If method is not STORED, DEFLATED, BZIP2 or UNKNOWN.
     */
    public function setMethod($method)
    {
        if (0x0000 > $method || $method > 0xffff) {
            throw new ZipException('method out of range');
        }
        switch ($method) {
            case self::METHOD_WINZIP_AES:
                $this->method = $method;
                $this->setInit(self::BIT_METHOD, true);
                $this->setEncryptionMethod(ZipFile::ENCRYPTION_METHOD_WINZIP_AES);
                break;

            case ZipFile::METHOD_STORED:
            case ZipFile::METHOD_DEFLATED:
            case ZipFile::METHOD_BZIP2:
                $this->method = $method;
                $this->setInit(self::BIT_METHOD, true);
                break;

            case self::UNKNOWN:
                $this->method = ZipFile::METHOD_STORED;
                $this->setInit(self::BIT_METHOD, false);
                break;

            default:
                throw new ZipException($this->name . " (unsupported compression method $method)");
        }
        return $this;
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
        return DateTimeConverter::toUnixTimestamp($this->getDosTime());
    }

    /**
     * Set time from unix timestamp.
     *
     * @param int $unixTimestamp
     * @return ZipEntry
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
        return $this;
    }

    /**
     * Get Dos Time
     *
     * @return int
     */
    public function getDosTime()
    {
        return $this->dosTime & 0xffffffff;
    }

    /**
     * Set Dos Time
     * @param int $dosTime
     * @throws ZipException
     */
    public function setDosTime($dosTime)
    {
        if (0x00000000 > $dosTime || $dosTime > 0xffffffff) {
            throw new ZipException('DosTime out of range');
        }
        $this->dosTime = $dosTime;
        $this->setInit(self::BIT_DATE_TIME, true);
    }

    /**
     * Returns the external file attributes.
     *
     * @return int The external file attributes.
     */
    public function getExternalAttributes()
    {
        if (!$this->isInit(self::BIT_EXTERNAL_ATTR)) {
            return $this->isDirectory() ? 0x10 : 0;
        }
        return $this->externalAttributes & 0xffffffff;
    }

    /**
     * Sets the external file attributes.
     *
     * @param int $externalAttributes the external file attributes.
     * @return ZipEntry
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
        return $this;
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
     * @return string
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
        $handle = fopen('php://memory', 'r+b');
        // Write out Uncompressed Size.
        $size = $this->getSize();
        if (0xffffffff <= $size) {
            fwrite($handle, PackUtil::packLongLE($size));
        }
        // Write out Compressed Size.
        $compressedSize = $this->getCompressedSize();
        if (0xffffffff <= $compressedSize) {
            fwrite($handle, PackUtil::packLongLE($compressedSize));
        }
        // Write out Relative Header Offset.
        $offset = $this->getOffset();
        if (0xffffffff <= $offset) {
            fwrite($handle, PackUtil::packLongLE($offset));
        }
        // Create ZIP64 Extended Information Extra Field from serialized data.
        $field = null;
        if (ftell($handle) > 0) {
            $field = new DefaultExtraField(ExtraField::ZIP64_HEADER_ID);
            $field->readFrom($handle, 0, ftell($handle));
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
     * @return ZipEntry
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
        return $this;
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
        $handle = fopen('php://memory', 'r+b');
        fwrite($handle, $data);
        rewind($handle);

        $this->fields->readFrom($handle, 0, strlen($data));
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
        fclose($handle);
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
        $dataBlockHandle = $ef->getDataBlock();
        $off = 0;
        // Read in Uncompressed Size.
        $size = $this->getSize();
        if (0xffffffff <= $size) {
            assert(0xffffffff === $size);
            fseek($dataBlockHandle, $off);
            $this->setSize(PackUtil::unpackLongLE(fread($dataBlockHandle, 8)));
            $off += 8;
        }
        // Read in Compressed Size.
        $compressedSize = $this->getCompressedSize();
        if (0xffffffff <= $compressedSize) {
            assert(0xffffffff === $compressedSize);
            fseek($dataBlockHandle, $off);
            $this->setCompressedSize(PackUtil::unpackLongLE(fread($dataBlockHandle, 8)));
            $off += 8;
        }
        // Read in Relative Header Offset.
        $offset = $this->getOffset();
        if (0xffffffff <= $offset) {
            assert(0xffffffff, $offset);
            fseek($dataBlockHandle, $off);
            $this->setOffset(PackUtil::unpackLongLE(fread($dataBlockHandle, 8)));
            //$off += 8;
        }
        fclose($dataBlockHandle);
        return true;
    }

    /**
     * Returns comment entry
     *
     * @return string
     */
    public function getComment()
    {
        return null != $this->comment ? $this->comment : "";
    }

    /**
     * Set entry comment.
     *
     * @param $comment
     * @return ZipEntry
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
        $this->setGeneralPurposeBitFlag(self::GPBF_UTF8, true);
        $this->comment = $comment;
        return $this;
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
        return $this->crc & 0xffffffff;
    }

    /**
     * Set crc32 content.
     *
     * @param int $crc
     * @return ZipEntry
     * @throws ZipException
     */
    public function setCrc($crc)
    {
        if (0x00000000 > $crc || $crc > 0xffffffff) {
            throw new ZipException("CRC-32 out of range - " . $this->name);
        }
        $this->crc = $crc;
        $this->setInit(self::BIT_CRC, true);
        return $this;
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
     * @return ZipEntry
     */
    public function setPassword($password, $encryptionMethod = null)
    {
        $this->password = $password;
        if (null !== $encryptionMethod) {
            $this->setEncryptionMethod($encryptionMethod);
        }
        $this->setEncrypted(!empty($this->password));
        return $this;
    }

    /**
     * @return int
     */
    public function getEncryptionMethod()
    {
        return $this->encryptionMethod;
    }

    /**
     * @return int
     */
    public function getCompressionLevel()
    {
        return $this->compressionLevel;
    }

    /**
     * @param int $compressionLevel
     * @return ZipEntry
     * @throws InvalidArgumentException
     */
    public function setCompressionLevel($compressionLevel = ZipFile::LEVEL_DEFAULT_COMPRESSION)
    {
        if ($compressionLevel < ZipFile::LEVEL_DEFAULT_COMPRESSION ||
            $compressionLevel > ZipFile::LEVEL_BEST_COMPRESSION
        ) {
            throw new InvalidArgumentException('Invalid compression level. Minimum level ' .
                ZipFile::LEVEL_DEFAULT_COMPRESSION . '. Maximum level ' . ZipFile::LEVEL_BEST_COMPRESSION);
        }
        $this->compressionLevel = $compressionLevel;
        return $this;
    }

    /**
     * Set encryption method
     *
     * @see ZipFile::ENCRYPTION_METHOD_TRADITIONAL
     * @see ZipFile::ENCRYPTION_METHOD_WINZIP_AES
     *
     * @param int $encryptionMethod
     * @return ZipEntry
     * @throws ZipException
     */
    public function setEncryptionMethod($encryptionMethod)
    {
        if (
            ZipFile::ENCRYPTION_METHOD_TRADITIONAL !== $encryptionMethod &&
            ZipFile::ENCRYPTION_METHOD_WINZIP_AES !== $encryptionMethod
        ) {
            throw new ZipException('Invalid encryption method');
        }
        $this->encryptionMethod = $encryptionMethod;
        $this->setEncrypted(true);
        return $this;
    }

    /**
     * Clone extra fields
     */
    function __clone()
    {
        $this->fields = $this->fields !== null ? clone $this->fields : null;
    }
}