<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Model;

use PhpZip\Constants\DosAttrs;
use PhpZip\Constants\DosCodePage;
use PhpZip\Constants\GeneralPurposeBitFlag;
use PhpZip\Constants\UnixStat;
use PhpZip\Constants\ZipCompressionLevel;
use PhpZip\Constants\ZipCompressionMethod;
use PhpZip\Constants\ZipConstants;
use PhpZip\Constants\ZipEncryptionMethod;
use PhpZip\Constants\ZipPlatform;
use PhpZip\Constants\ZipVersion;
use PhpZip\Exception\InvalidArgumentException;
use PhpZip\Exception\RuntimeException;
use PhpZip\Exception\ZipUnsupportMethodException;
use PhpZip\Model\Extra\ExtraFieldsCollection;
use PhpZip\Model\Extra\Fields\AsiExtraField;
use PhpZip\Model\Extra\Fields\ExtendedTimestampExtraField;
use PhpZip\Model\Extra\Fields\NtfsExtraField;
use PhpZip\Model\Extra\Fields\OldUnixExtraField;
use PhpZip\Model\Extra\Fields\UnicodePathExtraField;
use PhpZip\Model\Extra\Fields\WinZipAesExtraField;
use PhpZip\Model\Extra\ZipExtraField;
use PhpZip\Util\DateTimeConverter;
use PhpZip\Util\StringUtil;

/**
 * ZIP file entry.
 *
 * @see     https://pkware.cachefly.net/webdocs/casestudies/APPNOTE.TXT .ZIP File Format Specification
 */
class ZipEntry
{
    /** @var int the unknown value for numeric properties */
    public const UNKNOWN = -1;

    /** @var string Entry name (filename in archive) */
    private string $name;

    /** @var bool Is directory */
    private bool $isDirectory;

    /** @var ZipData|null Zip entry contents */
    private ?ZipData $data = null;

    /** @var int Made by platform */
    private int $createdOS = self::UNKNOWN;

    /** @var int Extracted by platform */
    private int $extractedOS = self::UNKNOWN;

    /** @var int Software version */
    private int $softwareVersion = self::UNKNOWN;

    /** @var int Version needed to extract */
    private int $extractVersion = self::UNKNOWN;

    /** @var int Compression method */
    private int $compressionMethod = self::UNKNOWN;

    /** @var int General purpose bit flags */
    private int $generalPurposeBitFlags = 0;

    /** @var int Dos time */
    private int $dosTime = self::UNKNOWN;

    /** @var int Crc32 */
    private int $crc = self::UNKNOWN;

    /** @var int Compressed size */
    private int $compressedSize = self::UNKNOWN;

    /** @var int Uncompressed size */
    private int $uncompressedSize = self::UNKNOWN;

    /** @var int Internal attributes */
    private int $internalAttributes = 0;

    /** @var int External attributes */
    private int $externalAttributes = 0;

    /** @var int relative Offset Of Local File Header */
    private int $localHeaderOffset = 0;

    /**
     * Collections of Extra Fields in Central Directory.
     * Keys from Header ID [int] and value Extra Field [ExtraField].
     */
    protected ExtraFieldsCollection $cdExtraFields;

    /**
     * Collections of Extra Fields int local header.
     * Keys from Header ID [int] and value Extra Field [ExtraField].
     */
    protected ExtraFieldsCollection $localExtraFields;

    /** @var string|null comment field */
    private ?string $comment = null;

    /** @var string|null entry password for read or write encryption data */
    private ?string $password = null;

    /** @var int encryption method */
    private int $encryptionMethod = ZipEncryptionMethod::NONE;

    /** @var int compression level */
    private int $compressionLevel = ZipCompressionLevel::NORMAL;

    /** @var string|null entry name charset */
    private ?string $charset = null;

    /**
     * @param string      $name    Entry name
     * @param string|null $charset Entry name charset
     */
    public function __construct(string $name, ?string $charset = null)
    {
        $this->setName($name, $charset);

        $this->cdExtraFields = new ExtraFieldsCollection();
        $this->localExtraFields = new ExtraFieldsCollection();
    }

    /**
     * This method only internal use.
     *
     * @internal
     *
     * @noinspection PhpTooManyParametersInspection
     *
     * @param ?string $comment
     * @param ?string $charset
     *
     * @return ZipEntry
     */
    public static function create(
        string $name,
        int $createdOS,
        int $extractedOS,
        int $softwareVersion,
        int $extractVersion,
        int $compressionMethod,
        int $gpbf,
        int $dosTime,
        int $crc,
        int $compressedSize,
        int $uncompressedSize,
        int $internalAttributes,
        int $externalAttributes,
        int $offsetLocalHeader,
        ?string $comment = null,
        ?string $charset = null
    ): self {
        $entry = new self($name);
        $entry->createdOS = $createdOS;
        $entry->extractedOS = $extractedOS;
        $entry->softwareVersion = $softwareVersion;
        $entry->extractVersion = $extractVersion;
        $entry->compressionMethod = $compressionMethod;
        $entry->generalPurposeBitFlags = $gpbf;
        $entry->dosTime = $dosTime;
        $entry->crc = $crc;
        $entry->compressedSize = $compressedSize;
        $entry->uncompressedSize = $uncompressedSize;
        $entry->internalAttributes = $internalAttributes;
        $entry->externalAttributes = $externalAttributes;
        $entry->localHeaderOffset = $offsetLocalHeader;
        $entry->setComment($comment);
        $entry->setCharset($charset);
        $entry->updateCompressionLevel();

        return $entry;
    }

    /**
     * Set entry name.
     *
     * @param string      $name    New entry name
     * @param string|null $charset Entry name charset
     *
     * @return ZipEntry
     */
    private function setName(string $name, ?string $charset = null): self
    {
        $name = ltrim($name, '\\/');

        if ($name === '') {
            throw new InvalidArgumentException('Empty zip entry name');
        }

        $length = \strlen($name);

        if ($length > 0xFFFF) {
            throw new InvalidArgumentException('Illegal zip entry name parameter');
        }

        $this->setCharset($charset);

        if ($this->charset === null && !StringUtil::isASCII($name)) {
            $this->enableUtf8Name(true);
        }
        $this->name = $name;
        $this->isDirectory = ($length = \strlen($name)) >= 1 && $name[$length - 1] === '/';
        $this->externalAttributes = $this->isDirectory ? DosAttrs::DOS_DIRECTORY : DosAttrs::DOS_ARCHIVE;

        if ($this->extractVersion !== self::UNKNOWN) {
            $this->extractVersion = max(
                $this->extractVersion,
                $this->isDirectory
                    ? ZipVersion::v20_DEFLATED_FOLDER_ZIPCRYPTO
                    : ZipVersion::v10_DEFAULT_MIN
            );
        }

        return $this;
    }

    /**
     * @see DosCodePage::getCodePages()
     *
     * @param ?string $charset
     *
     * @return ZipEntry
     */
    public function setCharset(?string $charset = null): self
    {
        if ($charset !== null && $charset === '') {
            throw new InvalidArgumentException('Empty charset');
        }
        $this->charset = $charset;

        return $this;
    }

    public function getCharset(): ?string
    {
        return $this->charset;
    }

    /**
     * @param string $newName New entry name
     *
     * @return ZipEntry new {@see ZipEntry} object with new name
     *
     * @internal
     */
    public function rename(string $newName): self
    {
        $newEntry = clone $this;
        $newEntry->setName($newName);

        $newEntry->removeExtraField(UnicodePathExtraField::HEADER_ID);

        return $newEntry;
    }

    /**
     * Returns the ZIP entry name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    public function getData(): ?ZipData
    {
        return $this->data;
    }

    public function setData(?ZipData $data): void
    {
        $this->data = $data;
    }

    /**
     * @return int platform
     */
    public function getCreatedOS(): int
    {
        return $this->createdOS;
    }

    /**
     * Set platform.
     *
     * @return ZipEntry
     */
    public function setCreatedOS(int $platform): self
    {
        if ($platform < 0x00 || $platform > 0xFF) {
            throw new InvalidArgumentException('Platform out of range');
        }
        $this->createdOS = $platform;

        return $this;
    }

    public function getExtractedOS(): int
    {
        return $this->extractedOS;
    }

    /**
     * Set extracted OS.
     *
     * @return ZipEntry
     */
    public function setExtractedOS(int $platform): self
    {
        if ($platform < 0x00 || $platform > 0xFF) {
            throw new InvalidArgumentException('Platform out of range');
        }
        $this->extractedOS = $platform;

        return $this;
    }

    public function getSoftwareVersion(): int
    {
        if ($this->softwareVersion === self::UNKNOWN) {
            return $this->getExtractVersion();
        }

        return $this->softwareVersion;
    }

    /**
     * @return ZipEntry
     */
    public function setSoftwareVersion(int $softwareVersion): self
    {
        $this->softwareVersion = $softwareVersion;

        return $this;
    }

    /**
     * Version needed to extract.
     */
    public function getExtractVersion(): int
    {
        if ($this->extractVersion === self::UNKNOWN) {
            if (ZipEncryptionMethod::isWinZipAesMethod($this->encryptionMethod)) {
                return ZipVersion::v51_ENCR_AES_RC2_CORRECT;
            }

            if ($this->compressionMethod === ZipCompressionMethod::BZIP2) {
                return ZipVersion::v46_BZIP2;
            }

            if ($this->isZip64ExtensionsRequired()) {
                return ZipVersion::v45_ZIP64_EXT;
            }

            if (
                $this->compressionMethod === ZipCompressionMethod::DEFLATED
                || $this->isDirectory
                || $this->encryptionMethod === ZipEncryptionMethod::PKWARE
            ) {
                return ZipVersion::v20_DEFLATED_FOLDER_ZIPCRYPTO;
            }

            return ZipVersion::v10_DEFAULT_MIN;
        }

        return $this->extractVersion;
    }

    /**
     * Set version needed to extract.
     *
     * @return ZipEntry
     */
    public function setExtractVersion(int $version): self
    {
        $this->extractVersion = max(ZipVersion::v10_DEFAULT_MIN, $version);

        return $this;
    }

    /**
     * Returns the compressed size of this entry.
     */
    public function getCompressedSize(): int
    {
        return $this->compressedSize;
    }

    /**
     * Sets the compressed size of this entry.
     *
     * @param int $compressedSize the Compressed Size
     *
     * @return ZipEntry
     *
     * @internal
     */
    public function setCompressedSize(int $compressedSize): self
    {
        if ($compressedSize < self::UNKNOWN) {
            throw new InvalidArgumentException('Compressed size < ' . self::UNKNOWN);
        }
        $this->compressedSize = $compressedSize;

        return $this;
    }

    /**
     * Returns the uncompressed size of this entry.
     */
    public function getUncompressedSize(): int
    {
        return $this->uncompressedSize;
    }

    /**
     * Sets the uncompressed size of this entry.
     *
     * @param int $uncompressedSize the (Uncompressed) Size
     *
     * @return ZipEntry
     *
     * @internal
     */
    public function setUncompressedSize(int $uncompressedSize): self
    {
        if ($uncompressedSize < self::UNKNOWN) {
            throw new InvalidArgumentException('Uncompressed size < ' . self::UNKNOWN);
        }
        $this->uncompressedSize = $uncompressedSize;

        return $this;
    }

    /**
     * Return relative Offset Of Local File Header.
     */
    public function getLocalHeaderOffset(): int
    {
        return $this->localHeaderOffset;
    }

    /**
     * @return ZipEntry
     *
     * @internal
     */
    public function setLocalHeaderOffset(int $localHeaderOffset): self
    {
        if ($localHeaderOffset < 0) {
            throw new InvalidArgumentException('Negative $localHeaderOffset');
        }
        $this->localHeaderOffset = $localHeaderOffset;

        return $this;
    }

    /**
     * Returns the General Purpose Bit Flags.
     */
    public function getGeneralPurposeBitFlags(): int
    {
        return $this->generalPurposeBitFlags;
    }

    /**
     * Sets the General Purpose Bit Flags.
     *
     * @param int $gpbf general purpose bit flags
     *
     * @return ZipEntry
     *
     * @internal
     */
    public function setGeneralPurposeBitFlags(int $gpbf): self
    {
        if ($gpbf < 0x0000 || $gpbf > 0xFFFF) {
            throw new InvalidArgumentException('general purpose bit flags out of range');
        }
        $this->generalPurposeBitFlags = $gpbf;
        $this->updateCompressionLevel();

        return $this;
    }

    private function updateCompressionLevel(): void
    {
        if ($this->compressionMethod === ZipCompressionMethod::DEFLATED) {
            $bit1 = $this->isSetGeneralBitFlag(GeneralPurposeBitFlag::COMPRESSION_FLAG1);
            $bit2 = $this->isSetGeneralBitFlag(GeneralPurposeBitFlag::COMPRESSION_FLAG2);

            if ($bit1 && !$bit2) {
                $this->compressionLevel = ZipCompressionLevel::MAXIMUM;
            } elseif (!$bit1 && $bit2) {
                $this->compressionLevel = ZipCompressionLevel::FAST;
            } elseif ($bit1 && $bit2) {
                $this->compressionLevel = ZipCompressionLevel::SUPER_FAST;
            } else {
                $this->compressionLevel = ZipCompressionLevel::NORMAL;
            }
        }
    }

    /**
     * @return ZipEntry
     */
    private function setGeneralBitFlag(int $mask, bool $enable): self
    {
        if ($enable) {
            $this->generalPurposeBitFlags |= $mask;
        } else {
            $this->generalPurposeBitFlags &= ~$mask;
        }

        return $this;
    }

    private function isSetGeneralBitFlag(int $mask): bool
    {
        return ($this->generalPurposeBitFlags & $mask) === $mask;
    }

    public function isDataDescriptorEnabled(): bool
    {
        return $this->isSetGeneralBitFlag(GeneralPurposeBitFlag::DATA_DESCRIPTOR);
    }

    /**
     * Enabling or disabling the use of the Data Descriptor block.
     */
    public function enableDataDescriptor(bool $enabled = true): void
    {
        $this->setGeneralBitFlag(GeneralPurposeBitFlag::DATA_DESCRIPTOR, $enabled);
    }

    public function enableUtf8Name(bool $enabled): void
    {
        $this->setGeneralBitFlag(GeneralPurposeBitFlag::UTF8, $enabled);
    }

    public function isUtf8Flag(): bool
    {
        return $this->isSetGeneralBitFlag(GeneralPurposeBitFlag::UTF8);
    }

    /**
     * Returns true if and only if this ZIP entry is encrypted.
     */
    public function isEncrypted(): bool
    {
        return $this->isSetGeneralBitFlag(GeneralPurposeBitFlag::ENCRYPTION);
    }

    public function isStrongEncryption(): bool
    {
        return $this->isSetGeneralBitFlag(GeneralPurposeBitFlag::STRONG_ENCRYPTION);
    }

    /**
     * Sets the encryption property to false and removes any other
     * encryption artifacts.
     *
     * @return ZipEntry
     */
    public function disableEncryption(): self
    {
        $this->setEncrypted(false);
        $this->removeExtraField(WinZipAesExtraField::HEADER_ID);
        $this->encryptionMethod = ZipEncryptionMethod::NONE;
        $this->password = null;
        $this->extractVersion = self::UNKNOWN;

        return $this;
    }

    /**
     * Sets the encryption flag for this ZIP entry.
     *
     * @return ZipEntry
     */
    private function setEncrypted(bool $encrypted): self
    {
        $this->setGeneralBitFlag(GeneralPurposeBitFlag::ENCRYPTION, $encrypted);

        return $this;
    }

    /**
     * Returns the compression method for this entry.
     */
    public function getCompressionMethod(): int
    {
        return $this->compressionMethod;
    }

    /**
     * Sets the compression method for this entry.
     *
     * @throws ZipUnsupportMethodException
     *
     * @return ZipEntry
     *
     * @see ZipCompressionMethod::STORED
     * @see ZipCompressionMethod::DEFLATED
     * @see ZipCompressionMethod::BZIP2
     */
    public function setCompressionMethod(int $compressionMethod): self
    {
        if ($compressionMethod < 0x0000 || $compressionMethod > 0xFFFF) {
            throw new InvalidArgumentException('method out of range: ' . $compressionMethod);
        }

        ZipCompressionMethod::checkSupport($compressionMethod);

        $this->compressionMethod = $compressionMethod;
        $this->updateCompressionLevel();
        $this->extractVersion = self::UNKNOWN;

        return $this;
    }

    /**
     * Get Unix Timestamp.
     */
    public function getTime(): int
    {
        if ($this->getDosTime() === self::UNKNOWN) {
            return self::UNKNOWN;
        }

        return DateTimeConverter::msDosToUnix($this->getDosTime());
    }

    /**
     * Get Dos Time.
     */
    public function getDosTime(): int
    {
        return $this->dosTime;
    }

    /**
     * Set Dos Time.
     *
     * @return ZipEntry
     */
    public function setDosTime(int $dosTime): self
    {
        if (\PHP_INT_SIZE === 8) {
            if ($dosTime < 0x00000000 || $dosTime > 0xFFFFFFFF) {
                throw new InvalidArgumentException('DosTime out of range');
            }
        }

        $this->dosTime = $dosTime;

        return $this;
    }

    /**
     * Set time from unix timestamp.
     *
     * @return ZipEntry
     */
    public function setTime(int $unixTimestamp): self
    {
        if ($unixTimestamp !== self::UNKNOWN) {
            $this->setDosTime(DateTimeConverter::unixToMsDos($unixTimestamp));
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
    public function getExternalAttributes(): int
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
    public function setExternalAttributes(int $externalAttributes): self
    {
        $this->externalAttributes = $externalAttributes;

        if (\PHP_INT_SIZE === 8) {
            if ($externalAttributes < 0x00000000 || $externalAttributes > 0xFFFFFFFF) {
                throw new InvalidArgumentException('external attributes out of range: ' . $externalAttributes);
            }
        }

        $this->externalAttributes = $externalAttributes;

        return $this;
    }

    /**
     * Returns the internal file attributes.
     *
     * @return int the internal file attributes
     */
    public function getInternalAttributes(): int
    {
        return $this->internalAttributes;
    }

    /**
     * Sets the internal file attributes.
     *
     * @param int $internalAttributes the internal file attributes
     *
     * @return ZipEntry
     */
    public function setInternalAttributes(int $internalAttributes): self
    {
        if ($internalAttributes < 0x0000 || $internalAttributes > 0xFFFF) {
            throw new InvalidArgumentException('internal attributes out of range');
        }
        $this->internalAttributes = $internalAttributes;

        return $this;
    }

    /**
     * Returns true if and only if this ZIP entry represents a directory entry
     * (i.e. end with '/').
     */
    final public function isDirectory(): bool
    {
        return $this->isDirectory;
    }

    public function getCdExtraFields(): ExtraFieldsCollection
    {
        return $this->cdExtraFields;
    }

    public function getCdExtraField(int $headerId): ?ZipExtraField
    {
        return $this->cdExtraFields->get($headerId);
    }

    /**
     * @return ZipEntry
     */
    public function setCdExtraFields(ExtraFieldsCollection $cdExtraFields): self
    {
        $this->cdExtraFields = $cdExtraFields;

        return $this;
    }

    public function getLocalExtraFields(): ExtraFieldsCollection
    {
        return $this->localExtraFields;
    }

    public function getLocalExtraField(int $headerId): ?ZipExtraField
    {
        return $this->localExtraFields[$headerId];
    }

    /**
     * @return ZipEntry
     */
    public function setLocalExtraFields(ExtraFieldsCollection $localExtraFields): self
    {
        $this->localExtraFields = $localExtraFields;

        return $this;
    }

    public function getExtraField(int $headerId): ?ZipExtraField
    {
        $local = $this->getLocalExtraField($headerId);

        return $local ?? $this->getCdExtraField($headerId);
    }

    public function hasExtraField(int $headerId): bool
    {
        return isset($this->localExtraFields[$headerId])
            || isset($this->cdExtraFields[$headerId]);
    }

    public function removeExtraField(int $headerId): void
    {
        $this->cdExtraFields->remove($headerId);
        $this->localExtraFields->remove($headerId);
    }

    public function addExtraField(ZipExtraField $zipExtraField): void
    {
        $this->addLocalExtraField($zipExtraField);
        $this->addCdExtraField($zipExtraField);
    }

    public function addLocalExtraField(ZipExtraField $zipExtraField): void
    {
        $this->localExtraFields->add($zipExtraField);
    }

    public function addCdExtraField(ZipExtraField $zipExtraField): void
    {
        $this->cdExtraFields->add($zipExtraField);
    }

    /**
     * Returns comment entry.
     */
    public function getComment(): string
    {
        return $this->comment ?? '';
    }

    /**
     * Set entry comment.
     *
     * @param ?string $comment
     *
     * @return ZipEntry
     */
    public function setComment(?string $comment): self
    {
        if ($comment !== null) {
            $commentLength = \strlen($comment);

            if ($commentLength > 0xFFFF) {
                throw new InvalidArgumentException('Comment too long');
            }

            if ($this->charset === null && !StringUtil::isASCII($comment)) {
                $this->enableUtf8Name(true);
            }
        }
        $this->comment = $comment;

        return $this;
    }

    public function isDataDescriptorRequired(): bool
    {
        return ($this->getCrc() | $this->getCompressedSize() | $this->getUncompressedSize()) === self::UNKNOWN;
    }

    /**
     * Return crc32 content or 0 for WinZip AES v2.
     */
    public function getCrc(): int
    {
        return $this->crc;
    }

    /**
     * Set crc32 content.
     *
     * @return ZipEntry
     *
     * @internal
     */
    public function setCrc(int $crc): self
    {
        $this->crc = $crc;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * Set password and encryption method from entry.
     *
     * @param ?string $password
     * @param ?int    $encryptionMethod
     *
     * @return ZipEntry
     */
    public function setPassword(?string $password, ?int $encryptionMethod = null): self
    {
        if (!$this->isDirectory) {
            if ($password === null || $password === '') {
                $this->password = null;
                $this->disableEncryption();
            } else {
                $this->password = $password;

                if ($encryptionMethod === null && $this->encryptionMethod === ZipEncryptionMethod::NONE) {
                    $encryptionMethod = ZipEncryptionMethod::WINZIP_AES_256;
                }

                if ($encryptionMethod !== null) {
                    $this->setEncryptionMethod($encryptionMethod);
                }
                $this->setEncrypted(true);
            }
        }

        return $this;
    }

    public function getEncryptionMethod(): int
    {
        return $this->encryptionMethod;
    }

    /**
     * Set encryption method.
     *
     * @see ZipEncryptionMethod::NONE
     * @see ZipEncryptionMethod::PKWARE
     * @see ZipEncryptionMethod::WINZIP_AES_256
     * @see ZipEncryptionMethod::WINZIP_AES_192
     * @see ZipEncryptionMethod::WINZIP_AES_128
     *
     * @param ?int $encryptionMethod
     *
     * @return ZipEntry
     */
    public function setEncryptionMethod(?int $encryptionMethod): self
    {
        $method = $encryptionMethod ?? ZipEncryptionMethod::NONE;

        ZipEncryptionMethod::checkSupport($method);
        $this->encryptionMethod = $method;

        $this->setEncrypted($this->encryptionMethod !== ZipEncryptionMethod::NONE);
        $this->extractVersion = self::UNKNOWN;

        return $this;
    }

    public function getCompressionLevel(): int
    {
        return $this->compressionLevel;
    }

    /**
     * @return ZipEntry
     */
    public function setCompressionLevel(int $compressionLevel): self
    {
        if ($compressionLevel === self::UNKNOWN) {
            $compressionLevel = ZipCompressionLevel::NORMAL;
        }

        if (
            $compressionLevel < ZipCompressionLevel::LEVEL_MIN
            || $compressionLevel > ZipCompressionLevel::LEVEL_MAX
        ) {
            throw new InvalidArgumentException(
                'Invalid compression level. Minimum level '
                . ZipCompressionLevel::LEVEL_MIN . '. Maximum level ' . ZipCompressionLevel::LEVEL_MAX
            );
        }
        $this->compressionLevel = $compressionLevel;

        $this->updateGbpfCompLevel();

        return $this;
    }

    /**
     * Update general purpose bit flogs.
     */
    private function updateGbpfCompLevel(): void
    {
        if ($this->compressionMethod === ZipCompressionMethod::DEFLATED) {
            $bit1 = false;
            $bit2 = false;

            switch ($this->compressionLevel) {
                case ZipCompressionLevel::MAXIMUM:
                    $bit1 = true;
                    break;

                case ZipCompressionLevel::FAST:
                    $bit2 = true;
                    break;

                case ZipCompressionLevel::SUPER_FAST:
                    $bit1 = true;
                    $bit2 = true;
                    break;
                // default is ZipCompressionLevel::NORMAL
            }

            $this->generalPurposeBitFlags |= ($bit1 ? GeneralPurposeBitFlag::COMPRESSION_FLAG1 : 0);
            $this->generalPurposeBitFlags |= ($bit2 ? GeneralPurposeBitFlag::COMPRESSION_FLAG2 : 0);
        }
    }

    /**
     * Sets Unix permissions in a way that is understood by Info-Zip's
     * unzip command.
     *
     * @param int $mode mode an int value
     *
     * @return ZipEntry
     */
    public function setUnixMode(int $mode): self
    {
        $this->setExternalAttributes(
            ($mode << 16)
            // MS-DOS read-only attribute
            | (($mode & UnixStat::UNX_IWUSR) === 0 ? DosAttrs::DOS_HIDDEN : 0)
            // MS-DOS directory flag
            | ($this->isDirectory() ? DosAttrs::DOS_DIRECTORY : DosAttrs::DOS_ARCHIVE)
        );
        $this->createdOS = ZipPlatform::OS_UNIX;

        return $this;
    }

    /**
     * Unix permission.
     *
     * @return int the unix permissions
     */
    public function getUnixMode(): int
    {
        $mode = 0;

        if ($this->createdOS === ZipPlatform::OS_UNIX) {
            $mode = ($this->externalAttributes >> 16) & 0xFFFF;
        } elseif ($this->hasExtraField(AsiExtraField::HEADER_ID)) {
            /** @var AsiExtraField $asiExtraField */
            $asiExtraField = $this->getExtraField(AsiExtraField::HEADER_ID);
            $mode = $asiExtraField->getMode();
        }

        if ($mode > 0) {
            return $mode;
        }

        return $this->isDirectory ? 040755 : 0100644;
    }

    /**
     * Offset MUST be considered in decision about ZIP64 format - see
     * description of Data Descriptor in ZIP File Format Specification.
     */
    public function isZip64ExtensionsRequired(): bool
    {
        return $this->compressedSize > ZipConstants::ZIP64_MAGIC
            || $this->uncompressedSize > ZipConstants::ZIP64_MAGIC;
    }

    /**
     * Returns true if this entry represents a unix symlink,
     * in which case the entry's content contains the target path
     * for the symlink.
     *
     * @return bool true if the entry represents a unix symlink,
     *              false otherwise
     */
    public function isUnixSymlink(): bool
    {
        return ($this->getUnixMode() & UnixStat::UNX_IFMT) === UnixStat::UNX_IFLNK;
    }

    public function getMTime(): \DateTimeInterface
    {
        /** @var NtfsExtraField|null $ntfsExtra */
        $ntfsExtra = $this->getExtraField(NtfsExtraField::HEADER_ID);

        if ($ntfsExtra !== null) {
            return $ntfsExtra->getModifyDateTime();
        }

        /** @var ExtendedTimestampExtraField|null $extendedExtra */
        $extendedExtra = $this->getExtraField(ExtendedTimestampExtraField::HEADER_ID);

        if ($extendedExtra !== null && ($mtime = $extendedExtra->getModifyDateTime()) !== null) {
            return $mtime;
        }

        /** @var OldUnixExtraField|null $oldUnixExtra */
        $oldUnixExtra = $this->getExtraField(OldUnixExtraField::HEADER_ID);

        if ($oldUnixExtra !== null && ($mtime = $oldUnixExtra->getModifyDateTime()) !== null) {
            return $mtime;
        }

        $timestamp = $this->getTime();

        try {
            return new \DateTimeImmutable('@' . $timestamp);
        } catch (\Exception $e) {
            throw new RuntimeException('Error create DateTime object with timestamp ' . $timestamp, 1, $e);
        }
    }

    public function getATime(): ?\DateTimeInterface
    {
        /** @var NtfsExtraField|null $ntfsExtra */
        $ntfsExtra = $this->getExtraField(NtfsExtraField::HEADER_ID);

        if ($ntfsExtra !== null) {
            return $ntfsExtra->getAccessDateTime();
        }

        /** @var ExtendedTimestampExtraField|null $extendedExtra */
        $extendedExtra = $this->getExtraField(ExtendedTimestampExtraField::HEADER_ID);

        if ($extendedExtra !== null && ($atime = $extendedExtra->getAccessDateTime()) !== null) {
            return $atime;
        }

        /** @var OldUnixExtraField|null $oldUnixExtra */
        $oldUnixExtra = $this->getExtraField(OldUnixExtraField::HEADER_ID);

        if ($oldUnixExtra !== null) {
            return $oldUnixExtra->getAccessDateTime();
        }

        return null;
    }

    public function getCTime(): ?\DateTimeInterface
    {
        /** @var NtfsExtraField|null $ntfsExtra */
        $ntfsExtra = $this->getExtraField(NtfsExtraField::HEADER_ID);

        if ($ntfsExtra !== null) {
            return $ntfsExtra->getCreateDateTime();
        }

        /** @var ExtendedTimestampExtraField|null $extendedExtra */
        $extendedExtra = $this->getExtraField(ExtendedTimestampExtraField::HEADER_ID);

        if ($extendedExtra !== null) {
            return $extendedExtra->getCreateDateTime();
        }

        return null;
    }

    public function __clone()
    {
        $this->cdExtraFields = clone $this->cdExtraFields;
        $this->localExtraFields = clone $this->localExtraFields;

        if ($this->data !== null) {
            $this->data = clone $this->data;
        }
    }
}
