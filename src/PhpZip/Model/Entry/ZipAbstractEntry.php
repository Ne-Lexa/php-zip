<?php

namespace PhpZip\Model\Entry;

use PhpZip\Exception\InvalidArgumentException;
use PhpZip\Exception\ZipException;
use PhpZip\Extra\ExtraFieldsCollection;
use PhpZip\Extra\ExtraFieldsFactory;
use PhpZip\Extra\Fields\WinZipAesEntryExtraField;
use PhpZip\Model\ZipEntry;
use PhpZip\Util\DateTimeConverter;
use PhpZip\Util\StringUtil;
use PhpZip\ZipFileInterface;

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
     * @var int Compression method
     */
    private $method;
    /**
     * @var int
     */
    private $general;
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
     * Collections of Extra Fields.
     * Keys from Header ID [int] and value Extra Field [ExtraField].
     * Should be null or may be empty if no Extra Fields are used.
     *
     * @var ExtraFieldsCollection
     */
    private $extraFieldsCollection;
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
     * @see ZipFileInterface::ENCRYPTION_METHOD_TRADITIONAL
     * @see ZipFileInterface::ENCRYPTION_METHOD_WINZIP_AES_128
     * @see ZipFileInterface::ENCRYPTION_METHOD_WINZIP_AES_192
     * @see ZipFileInterface::ENCRYPTION_METHOD_WINZIP_AES_256
     * @var int
     */
    private $encryptionMethod = ZipFileInterface::ENCRYPTION_METHOD_TRADITIONAL;
    /**
     * @var int
     */
    private $compressionLevel = ZipFileInterface::LEVEL_DEFAULT_COMPRESSION;

    /**
     * ZipAbstractEntry constructor.
     */
    public function __construct()
    {
        $this->extraFieldsCollection = new ExtraFieldsCollection();
    }

    /**
     * @param ZipEntry $entry
     * @throws ZipException
     */
    public function setEntry(ZipEntry $entry)
    {
        $this->setName($entry->getName());
        $this->setPlatform($entry->getPlatform());
        $this->setVersionNeededToExtract($entry->getVersionNeededToExtract());
        $this->setMethod($entry->getMethod());
        $this->setGeneralPurposeBitFlags($entry->getGeneralPurposeBitFlags());
        $this->setDosTime($entry->getDosTime());
        $this->setCrc($entry->getCrc());
        $this->setCompressedSize($entry->getCompressedSize());
        $this->setSize($entry->getSize());
        $this->setExternalAttributes($entry->getExternalAttributes());
        $this->setOffset($entry->getOffset());
        $this->setExtra($entry->getExtra());
        $this->setComment($entry->getComment());
        $this->setPassword($entry->getPassword());
        $this->setEncryptionMethod($entry->getEncryptionMethod());
        $this->setCompressionLevel($entry->getCompressionLevel());
        $this->setEncrypted($entry->isEncrypted());
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
     * Sets the indexed General Purpose Bit Flag.
     *
     * @param int $mask
     * @param bool $bit
     * @return ZipEntry
     */
    public function setGeneralPurposeBitFlag($mask, $bit)
    {
        if ($bit) {
            $this->general |= $mask;
        } else {
            $this->general &= ~$mask;
        }
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
     * @param int $mask
     * @return bool
     */
    protected function isInit($mask)
    {
        return ($this->init & $mask) !== 0;
    }

    /**
     * @param int $mask
     * @param bool $init
     */
    protected function setInit($mask, $init)
    {
        if ($init) {
            $this->init |= $mask;
        } else {
            $this->init &= ~$mask;
        }
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
        return 0xffffffff <= $this->getCompressedSize()
            || 0xffffffff <= $this->getSize();
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
     */
    public function setCompressedSize($compressedSize)
    {
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
     */
    public function setSize($size)
    {
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
     */
    public function setOffset($offset)
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Returns the General Purpose Bit Flags.
     * @return int
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
        if ($this->method === ZipFileInterface::METHOD_DEFLATED) {
            $bit1 = $this->getGeneralPurposeBitFlag(self::GPBF_COMPRESSION_FLAG1);
            $bit2 = $this->getGeneralPurposeBitFlag(self::GPBF_COMPRESSION_FLAG2);
            if ($bit1 && !$bit2) {
                $this->compressionLevel = ZipFileInterface::LEVEL_BEST_COMPRESSION;
            } elseif (!$bit1 && $bit2) {
                $this->compressionLevel = ZipFileInterface::LEVEL_FAST;
            } elseif ($bit1 && $bit2) {
                $this->compressionLevel = ZipFileInterface::LEVEL_SUPER_FAST;
            } else {
                $this->compressionLevel = ZipFileInterface::LEVEL_DEFAULT_COMPRESSION;
            }
        }
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
     * Returns the indexed General Purpose Bit Flag.
     *
     * @param int $mask
     * @return bool
     */
    public function getGeneralPurposeBitFlag($mask)
    {
        return ($this->general & $mask) !== 0;
    }

    /**
     * Sets the encryption property to false and removes any other
     * encryption artifacts.
     *
     * @return ZipEntry
     * @throws ZipException
     */
    public function disableEncryption()
    {
        $this->setEncrypted(false);
        $headerId = WinZipAesEntryExtraField::getHeaderId();
        if (isset($this->extraFieldsCollection[$headerId])) {
            /**
             * @var WinZipAesEntryExtraField $field
             */
            $field = $this->extraFieldsCollection[$headerId];
            if (self::METHOD_WINZIP_AES === $this->getMethod()) {
                $this->setMethod($field === null ? self::UNKNOWN : $field->getMethod());
            }
            unset($this->extraFieldsCollection[$headerId]);
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
        $encrypted = (bool)$encrypted;
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
        $isInit = $this->isInit(self::BIT_METHOD);
        return $isInit ?
            $this->method & 0xffff :
            self::UNKNOWN;
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
        if ($method === self::UNKNOWN) {
            $this->method = $method;
            $this->setInit(self::BIT_METHOD, false);
            return $this;
        }
        if (0x0000 > $method || $method > 0xffff) {
            throw new ZipException('method out of range: ' . $method);
        }
        switch ($method) {
            case self::METHOD_WINZIP_AES:
            case ZipFileInterface::METHOD_STORED:
            case ZipFileInterface::METHOD_DEFLATED:
            case ZipFileInterface::METHOD_BZIP2:
                $this->method = $method;
                $this->setInit(self::BIT_METHOD, true);
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
     * Get Dos Time
     *
     * @return int
     */
    public function getDosTime()
    {
        return $this->dosTime;
    }

    /**
     * Set Dos Time
     * @param int $dosTime
     * @throws ZipException
     */
    public function setDosTime($dosTime)
    {
        $dosTime = sprintf('%u', $dosTime);
        if (0x00000000 > $dosTime || $dosTime > 0xffffffff) {
            throw new ZipException('DosTime out of range');
        }
        $this->dosTime = $dosTime;
        $this->setInit(self::BIT_DATE_TIME, true);
    }

    /**
     * Set time from unix timestamp.
     *
     * @param int $unixTimestamp
     * @return ZipEntry
     * @throws ZipException
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
     * Returns the external file attributes.
     *
     * @return int The external file attributes.
     */
    public function getExternalAttributes()
    {
        if (!$this->isInit(self::BIT_EXTERNAL_ATTR)) {
            return $this->isDirectory() ? 0x10 : 0;
        }
        return $this->externalAttributes;
    }

    /**
     * Sets the external file attributes.
     *
     * @param int $externalAttributes the external file attributes.
     * @return ZipEntry
     */
    public function setExternalAttributes($externalAttributes)
    {
        $known = self::UNKNOWN != $externalAttributes;
        if ($known) {
            $this->externalAttributes = $externalAttributes;
        } else {
            $this->externalAttributes = 0;
        }
        $this->setInit(self::BIT_EXTERNAL_ATTR, $known);
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
        return StringUtil::endsWith($this->name, '/');
    }

    /**
     * @return ExtraFieldsCollection
     */
    public function &getExtraFieldsCollection()
    {
        return $this->extraFieldsCollection;
    }

    /**
     * Returns a protective copy of the serialized Extra Fields.
     * @return string
     * @throws ZipException
     */
    public function getExtra()
    {
        return ExtraFieldsFactory::createSerializedData($this->extraFieldsCollection);
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
     */
    public function setExtra($data)
    {
        $this->extraFieldsCollection = ExtraFieldsFactory::createExtraFieldCollections($data, $this);
    }

    /**
     * Returns comment entry
     *
     * @return string
     */
    public function getComment()
    {
        return null !== $this->comment ? $this->comment : "";
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
        if ($comment !== null) {
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
        return ($this->getCrc() | $this->getCompressedSize() | $this->getSize()) == self::UNKNOWN;
    }

    /**
     * Return crc32 content or 0 for WinZip AES v2
     *
     * @return int
     */
    public function getCrc()
    {
        return $this->crc;
    }

    /**
     * Set crc32 content.
     *
     * @param int $crc
     * @return ZipEntry
     */
    public function setCrc($crc)
    {
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
     * @throws ZipException
     */
    public function setPassword($password, $encryptionMethod = null)
    {
        $this->password = $password;
        if ($encryptionMethod !== null) {
            $this->setEncryptionMethod($encryptionMethod);
        }
        if (!empty($this->password)) {
            $this->setEncrypted(true);
        } else {
            $this->disableEncryption();
        }
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
     * Set encryption method
     *
     * @see ZipFileInterface::ENCRYPTION_METHOD_TRADITIONAL
     * @see ZipFileInterface::ENCRYPTION_METHOD_WINZIP_AES_128
     * @see ZipFileInterface::ENCRYPTION_METHOD_WINZIP_AES_192
     * @see ZipFileInterface::ENCRYPTION_METHOD_WINZIP_AES_256
     *
     * @param int $encryptionMethod
     * @return ZipEntry
     * @throws ZipException
     */
    public function setEncryptionMethod($encryptionMethod)
    {
        if (null !== $encryptionMethod) {
            if (
                $encryptionMethod !== ZipFileInterface::ENCRYPTION_METHOD_TRADITIONAL
                && $encryptionMethod !== ZipFileInterface::ENCRYPTION_METHOD_WINZIP_AES_128
                && $encryptionMethod !== ZipFileInterface::ENCRYPTION_METHOD_WINZIP_AES_192
                && $encryptionMethod !== ZipFileInterface::ENCRYPTION_METHOD_WINZIP_AES_256
            ) {
                throw new ZipException('Invalid encryption method');
            }
            $this->encryptionMethod = $encryptionMethod;
        }
        return $this;
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
     */
    public function setCompressionLevel($compressionLevel = ZipFileInterface::LEVEL_DEFAULT_COMPRESSION)
    {
        if ($compressionLevel < ZipFileInterface::LEVEL_DEFAULT_COMPRESSION ||
            $compressionLevel > ZipFileInterface::LEVEL_BEST_COMPRESSION
        ) {
            throw new InvalidArgumentException('Invalid compression level. Minimum level ' .
                ZipFileInterface::LEVEL_DEFAULT_COMPRESSION . '. Maximum level ' . ZipFileInterface::LEVEL_BEST_COMPRESSION);
        }
        $this->compressionLevel = $compressionLevel;
        return $this;
    }

    /**
     * Clone extra fields
     */
    public function __clone()
    {
        $this->extraFieldsCollection = clone $this->extraFieldsCollection;
    }
}
