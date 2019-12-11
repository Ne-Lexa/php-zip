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
use PhpZip\ZipFile;

/**
 * Abstract ZIP entry.
 *
 * @see https://pkware.cachefly.net/webdocs/casestudies/APPNOTE.TXT .ZIP File Format Specification
 *
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
abstract class ZipAbstractEntry implements ZipEntry
{
    /** @var string Entry name (filename in archive) */
    private $name;

    /** @var int Made by platform */
    private $createdOS = self::UNKNOWN;

    /** @var int Extracted by platform */
    private $extractedOS = self::UNKNOWN;

    /** @var int */
    private $softwareVersion = self::UNKNOWN;

    /** @var int */
    private $versionNeededToExtract = self::UNKNOWN;

    /** @var int Compression method */
    private $method = self::UNKNOWN;

    /** @var int */
    private $generalPurposeBitFlags = 0;

    /** @var int Dos time */
    private $dosTime = self::UNKNOWN;

    /** @var int Crc32 */
    private $crc = self::UNKNOWN;

    /** @var int Compressed size */
    private $compressedSize = self::UNKNOWN;

    /** @var int Uncompressed size */
    private $size = self::UNKNOWN;

    /** @var int Internal attributes */
    private $internalAttributes = 0;

    /** @var int External attributes */
    private $externalAttributes = 0;

    /** @var int relative Offset Of Local File Header */
    private $offset = 0;

    /**
     * Collections of Extra Fields.
     * Keys from Header ID [int] and value Extra Field [ExtraField].
     * Should be null or may be empty if no Extra Fields are used.
     *
     * @var ExtraFieldsCollection
     */
    private $extraFieldsCollection;

    /** @var string|null comment field */
    private $comment;

    /** @var string entry password for read or write encryption data */
    private $password;

    /**
     * Encryption method.
     *
     * @see ZipFile::ENCRYPTION_METHOD_TRADITIONAL
     * @see ZipFile::ENCRYPTION_METHOD_WINZIP_AES_128
     * @see ZipFile::ENCRYPTION_METHOD_WINZIP_AES_192
     * @see ZipFile::ENCRYPTION_METHOD_WINZIP_AES_256
     *
     * @var int
     */
    private $encryptionMethod = ZipFile::ENCRYPTION_METHOD_TRADITIONAL;

    /** @var int */
    private $compressionLevel = ZipFile::LEVEL_DEFAULT_COMPRESSION;

    /**
     * ZipAbstractEntry constructor.
     */
    public function __construct()
    {
        $this->extraFieldsCollection = new ExtraFieldsCollection();
    }

    /**
     * @param ZipEntry $entry
     *
     * @throws ZipException
     */
    public function setEntry(ZipEntry $entry)
    {
        $this->setName($entry->getName());
        $this->setSoftwareVersion($entry->getSoftwareVersion());
        $this->setCreatedOS($entry->getCreatedOS());
        $this->setExtractedOS($entry->getExtractedOS());
        $this->setVersionNeededToExtract($entry->getVersionNeededToExtract());
        $this->setMethod($entry->getMethod());
        $this->setGeneralPurposeBitFlags($entry->getGeneralPurposeBitFlags());
        $this->setDosTime($entry->getDosTime());
        $this->setCrc($entry->getCrc());
        $this->setCompressedSize($entry->getCompressedSize());
        $this->setSize($entry->getSize());
        $this->setInternalAttributes($entry->getInternalAttributes());
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
     *
     * @throws ZipException
     *
     * @return ZipEntry
     */
    public function setName($name)
    {
        $length = \strlen($name);

        if ($length < 0x0000 || $length > 0xffff) {
            throw new ZipException('Illegal zip entry name parameter');
        }
        $this->setGeneralPurposeBitFlag(self::GPBF_UTF8, true);
        $this->name = $name;
        $this->externalAttributes = $this->isDirectory() ? 0x10 : 0;

        return $this;
    }

    /**
     * Sets the indexed General Purpose Bit Flag.
     *
     * @param int  $mask
     * @param bool $bit
     *
     * @return ZipEntry
     */
    public function setGeneralPurposeBitFlag($mask, $bit)
    {
        if ($bit) {
            $this->generalPurposeBitFlags |= $mask;
        } else {
            $this->generalPurposeBitFlags &= ~$mask;
        }

        return $this;
    }

    /**
     * @return int Get platform
     *
     * @deprecated Use {@see ZipEntry::getCreatedOS()}
     * @noinspection PhpUsageOfSilenceOperatorInspection
     */
    public function getPlatform()
    {
        @trigger_error('ZipEntry::getPlatform() is deprecated. Use ZipEntry::getCreatedOS()', \E_USER_DEPRECATED);

        return $this->getCreatedOS();
    }

    /**
     * @param int $platform
     *
     * @throws ZipException
     *
     * @return ZipEntry
     *
     * @deprecated Use {@see ZipEntry::setCreatedOS()}
     * @noinspection PhpUsageOfSilenceOperatorInspection
     */
    public function setPlatform($platform)
    {
        @trigger_error('ZipEntry::setPlatform() is deprecated. Use ZipEntry::setCreatedOS()', \E_USER_DEPRECATED);

        return $this->setCreatedOS($platform);
    }

    /**
     * @return int platform
     */
    public function getCreatedOS()
    {
        return $this->createdOS;
    }

    /**
     * Set platform.
     *
     * @param int $platform
     *
     * @throws ZipException
     *
     * @return ZipEntry
     */
    public function setCreatedOS($platform)
    {
        $platform = (int) $platform;

        if ($platform < 0x00 || $platform > 0xff) {
            throw new ZipException('Platform out of range');
        }
        $this->createdOS = $platform;

        return $this;
    }

    /**
     * @return int
     */
    public function getExtractedOS()
    {
        return $this->extractedOS;
    }

    /**
     * Set extracted OS.
     *
     * @param int $platform
     *
     * @throws ZipException
     *
     * @return ZipEntry
     */
    public function setExtractedOS($platform)
    {
        $platform = (int) $platform;

        if ($platform < 0x00 || $platform > 0xff) {
            throw new ZipException('Platform out of range');
        }
        $this->extractedOS = $platform;

        return $this;
    }

    /**
     * @return int
     */
    public function getSoftwareVersion()
    {
        return $this->softwareVersion;
    }

    /**
     * @param int $softwareVersion
     *
     * @return ZipEntry
     */
    public function setSoftwareVersion($softwareVersion)
    {
        $this->softwareVersion = (int) $softwareVersion;

        return $this;
    }

    /**
     * Version needed to extract.
     *
     * @return int
     */
    public function getVersionNeededToExtract()
    {
        if ($this->versionNeededToExtract === self::UNKNOWN) {
            $method = $this->getMethod();

            if ($method === self::METHOD_WINZIP_AES) {
                return 51;
            }

            if ($method === ZipFile::METHOD_BZIP2) {
                return 46;
            }

            if ($this->isZip64ExtensionsRequired()) {
                return 45;
            }

            return $method === ZipFile::METHOD_DEFLATED || $this->isDirectory() ? 20 : 10;
        }

        return $this->versionNeededToExtract;
    }

    /**
     * Set version needed to extract.
     *
     * @param int $version
     *
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
        return $this->getCompressedSize() >= 0xffffffff
            || $this->getSize() >= 0xffffffff;
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
     * @param int $compressedSize the Compressed Size
     *
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
     * @param int $size the (Uncompressed) Size
     *
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
     *
     * @return ZipEntry
     */
    public function setOffset($offset)
    {
        $this->offset = (int) $offset;

        return $this;
    }

    /**
     * Returns the General Purpose Bit Flags.
     *
     * @return int
     */
    public function getGeneralPurposeBitFlags()
    {
        return $this->generalPurposeBitFlags & 0xffff;
    }

    /**
     * Sets the General Purpose Bit Flags.
     *
     * @param mixed $general
     *
     * @throws ZipException
     *
     * @return ZipEntry
     *
     * @var int general
     */
    public function setGeneralPurposeBitFlags($general)
    {
        if ($general < 0x0000 || $general > 0xffff) {
            throw new ZipException('general out of range');
        }
        $this->generalPurposeBitFlags = $general;

        if ($this->method === ZipFile::METHOD_DEFLATED) {
            $bit1 = $this->getGeneralPurposeBitFlag(self::GPBF_COMPRESSION_FLAG1);
            $bit2 = $this->getGeneralPurposeBitFlag(self::GPBF_COMPRESSION_FLAG2);

            if ($bit1 && !$bit2) {
                $this->compressionLevel = ZipFile::LEVEL_BEST_COMPRESSION;
            } elseif (!$bit1 && $bit2) {
                $this->compressionLevel = ZipFile::LEVEL_FAST;
            } elseif ($bit1 && $bit2) {
                $this->compressionLevel = ZipFile::LEVEL_SUPER_FAST;
            } else {
                $this->compressionLevel = ZipFile::LEVEL_DEFAULT_COMPRESSION;
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
     *
     * @return bool
     */
    public function getGeneralPurposeBitFlag($mask)
    {
        return ($this->generalPurposeBitFlags & $mask) !== 0;
    }

    /**
     * Sets the encryption property to false and removes any other
     * encryption artifacts.
     *
     * @throws ZipException
     *
     * @return ZipEntry
     */
    public function disableEncryption()
    {
        $this->setEncrypted(false);
        $headerId = WinZipAesEntryExtraField::getHeaderId();

        if (isset($this->extraFieldsCollection[$headerId])) {
            /** @var WinZipAesEntryExtraField $field */
            $field = $this->extraFieldsCollection[$headerId];

            if ($this->getMethod() === self::METHOD_WINZIP_AES) {
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
     *
     * @return ZipEntry
     */
    public function setEncrypted($encrypted)
    {
        $encrypted = (bool) $encrypted;
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
        return $this->method;
    }

    /**
     * Sets the compression method for this entry.
     *
     * @param int $method
     *
     * @throws ZipException if method is not STORED, DEFLATED, BZIP2 or UNKNOWN
     *
     * @return ZipEntry
     */
    public function setMethod($method)
    {
        if ($method === self::UNKNOWN) {
            $this->method = $method;

            return $this;
        }

        if ($method < 0x0000 || $method > 0xffff) {
            throw new ZipException('method out of range: ' . $method);
        }
        switch ($method) {
            case self::METHOD_WINZIP_AES:
            case ZipFile::METHOD_STORED:
            case ZipFile::METHOD_DEFLATED:
            case ZipFile::METHOD_BZIP2:
                $this->method = $method;
                break;

            default:
                throw new ZipException($this->name . " (unsupported compression method {$method})");
        }

        return $this;
    }

    /**
     * Get Unix Timestamp.
     *
     * @return int
     */
    public function getTime()
    {
        if ($this->getDosTime() === self::UNKNOWN) {
            return self::UNKNOWN;
        }

        return DateTimeConverter::toUnixTimestamp($this->getDosTime());
    }

    /**
     * Get Dos Time.
     *
     * @return int
     */
    public function getDosTime()
    {
        return $this->dosTime;
    }

    /**
     * Set Dos Time.
     *
     * @param int $dosTime
     *
     * @throws ZipException
     *
     * @return ZipEntry
     */
    public function setDosTime($dosTime)
    {
        $dosTime = (int) $dosTime;

        if ($dosTime < 0x00000000 || $dosTime > 0xffffffff) {
            throw new ZipException('DosTime out of range');
        }
        $this->dosTime = $dosTime;

        return $this;
    }

    /**
     * Set time from unix timestamp.
     *
     * @param int $unixTimestamp
     *
     * @throws ZipException
     *
     * @return ZipEntry
     */
    public function setTime($unixTimestamp)
    {
        $known = $unixTimestamp !== self::UNKNOWN;

        if ($known) {
            $this->dosTime = DateTimeConverter::toDosTime($unixTimestamp);
        } else {
            $this->dosTime = 0;
        }

        return $this;
    }

    /**
     * Returns the external file attributes.
     *
     * @return int the external file attributes
     */
    public function getExternalAttributes()
    {
        return $this->externalAttributes;
    }

    /**
     * Sets the external file attributes.
     *
     * @param int $externalAttributes the external file attributes
     *
     * @return ZipEntry
     */
    public function setExternalAttributes($externalAttributes)
    {
        $this->externalAttributes = $externalAttributes;

        return $this;
    }

    /**
     * Sets the internal file attributes.
     *
     * @param int $attributes the internal file attributes
     *
     * @return ZipEntry
     */
    public function setInternalAttributes($attributes)
    {
        $this->internalAttributes = (int) $attributes;

        return $this;
    }

    /**
     * Returns the internal file attributes.
     *
     * @return int the internal file attributes
     */
    public function getInternalAttributes()
    {
        return $this->internalAttributes;
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
     *
     * @throws ZipException
     *
     * @return string
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
     * @param string $data the byte array holding the serialized Extra Fields
     *
     * @throws ZipException if the serialized Extra Fields exceed 64 KB
     *
     * @return ZipEntry
     */
    public function setExtra($data)
    {
        $this->extraFieldsCollection = ExtraFieldsFactory::createExtraFieldCollections($data, $this);

        return $this;
    }

    /**
     * Returns comment entry.
     *
     * @return string
     */
    public function getComment()
    {
        return $this->comment !== null ? $this->comment : '';
    }

    /**
     * Set entry comment.
     *
     * @param string|null $comment
     *
     * @throws ZipException
     *
     * @return ZipEntry
     */
    public function setComment($comment)
    {
        if ($comment !== null) {
            $commentLength = \strlen($comment);

            if ($commentLength < 0x0000 || $commentLength > 0xffff) {
                throw new ZipException('Comment too long');
            }
            $this->setGeneralPurposeBitFlag(self::GPBF_UTF8, true);
        }
        $this->comment = $comment;

        return $this;
    }

    /**
     * @return bool
     */
    public function isDataDescriptorRequired()
    {
        return ($this->getCrc() | $this->getCompressedSize() | $this->getSize()) === self::UNKNOWN;
    }

    /**
     * Return crc32 content or 0 for WinZip AES v2.
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
     *
     * @return ZipEntry
     */
    public function setCrc($crc)
    {
        $this->crc = (int) $crc;

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
     * Set password and encryption method from entry.
     *
     * @param string   $password
     * @param int|null $encryptionMethod
     *
     * @throws ZipException
     *
     * @return ZipEntry
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
     * Set encryption method.
     *
     * @param int $encryptionMethod
     *
     * @throws ZipException
     *
     * @return ZipEntry
     *
     * @see ZipFile::ENCRYPTION_METHOD_WINZIP_AES_256
     * @see ZipFile::ENCRYPTION_METHOD_TRADITIONAL
     * @see ZipFile::ENCRYPTION_METHOD_WINZIP_AES_128
     * @see ZipFile::ENCRYPTION_METHOD_WINZIP_AES_192
     */
    public function setEncryptionMethod($encryptionMethod)
    {
        if ($encryptionMethod !== null) {
            if (
                $encryptionMethod !== ZipFile::ENCRYPTION_METHOD_TRADITIONAL
                && $encryptionMethod !== ZipFile::ENCRYPTION_METHOD_WINZIP_AES_128
                && $encryptionMethod !== ZipFile::ENCRYPTION_METHOD_WINZIP_AES_192
                && $encryptionMethod !== ZipFile::ENCRYPTION_METHOD_WINZIP_AES_256
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
     *
     * @return ZipEntry
     */
    public function setCompressionLevel($compressionLevel = ZipFile::LEVEL_DEFAULT_COMPRESSION)
    {
        if ($compressionLevel < ZipFile::LEVEL_DEFAULT_COMPRESSION ||
            $compressionLevel > ZipFile::LEVEL_BEST_COMPRESSION
        ) {
            throw new InvalidArgumentException(
                'Invalid compression level. Minimum level ' .
                ZipFile::LEVEL_DEFAULT_COMPRESSION . '. Maximum level ' . ZipFile::LEVEL_BEST_COMPRESSION
            );
        }
        $this->compressionLevel = $compressionLevel;

        return $this;
    }

    /**
     * Clone extra fields.
     */
    public function __clone()
    {
        $this->extraFieldsCollection = clone $this->extraFieldsCollection;
    }
}
