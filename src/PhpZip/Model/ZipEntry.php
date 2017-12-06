<?php

namespace PhpZip\Model;

use PhpZip\Exception\ZipException;
use PhpZip\Extra\ExtraFieldsCollection;
use PhpZip\ZipFileInterface;

/**
 * ZIP file entry.
 *
 * @see https://pkware.cachefly.net/webdocs/casestudies/APPNOTE.TXT .ZIP File Format Specification
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
interface ZipEntry
{
    // Bit masks for initialized fields.
    const BIT_PLATFORM = 1,
        BIT_METHOD = 2 /* 1 << 1 */,
        BIT_CRC = 4 /* 1 << 2 */,
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
     * Pseudo compression method for WinZip AES encrypted entries.
     * Require php extension openssl or mcrypt.
     */
    const METHOD_WINZIP_AES = 99;

    /** General Purpose Bit Flag mask for encrypted data. */
    const GPBF_ENCRYPTED = 1; // 1 << 0
//    (For Methods 8 and 9 - Deflating)
//    Bit 2  Bit 1
//    0      0    Normal compression
//    0      1    Maximum compression
//    1      0    Fast compression
//    1      1    Super Fast compression
    const GPBF_COMPRESSION_FLAG1 = 2; // 1 << 1
    const GPBF_COMPRESSION_FLAG2 = 4; // 1 << 2
    /** General Purpose Bit Flag mask for data descriptor. */
    const GPBF_DATA_DESCRIPTOR = 8; // 1 << 3
    /** General Purpose Bit Flag mask for strong encryption. */
    const GPBF_STRONG_ENCRYPTION = 64; // 1 << 6
    /** General Purpose Bit Flag mask for UTF-8. */
    const GPBF_UTF8 = 2048; // 1 << 11

    /** Local File Header signature. */
    const LOCAL_FILE_HEADER_SIG = 0x04034B50;
    /** Data Descriptor signature. */
    const DATA_DESCRIPTOR_SIG = 0x08074B50;
    /**
     * The minimum length of the Local File Header record.
     *
     * local file header signature      4
     * version needed to extract        2
     * general purpose bit flag         2
     * compression method               2
     * last mod file time               2
     * last mod file date               2
     * crc-32                           4
     * compressed size                  4
     * uncompressed size                4
     * file name length                 2
     * extra field length               2
     */
    const LOCAL_FILE_HEADER_MIN_LEN = 30;
    /**
     * Local File Header signature      4
     * Version Needed To Extract        2
     * General Purpose Bit Flags        2
     * Compression Method               2
     * Last Mod File Time               2
     * Last Mod File Date               2
     * CRC-32                           4
     * Compressed Size                  4
     * Uncompressed Size                4
     */
    const LOCAL_FILE_HEADER_FILE_NAME_LENGTH_POS = 26;
    /**
     * Default compression level for bzip2
     */
    const LEVEL_DEFAULT_BZIP2_COMPRESSION = 4;

    /**
     * Returns the ZIP entry name.
     *
     * @return string
     */
    public function getName();

    /**
     * Set entry name.
     *
     * @param string $name New entry name
     * @return ZipEntry
     * @throws ZipException
     */
    public function setName($name);

    /**
     * @return int Get platform
     */
    public function getPlatform();

    /**
     * Set platform
     *
     * @param int $platform
     * @return ZipEntry
     * @throws ZipException
     */
    public function setPlatform($platform);

    /**
     * Version needed to extract.
     *
     * @return int
     */
    public function getVersionNeededToExtract();

    /**
     * Set version needed to extract.
     *
     * @param int $version
     * @return ZipEntry
     */
    public function setVersionNeededToExtract($version);

    /**
     * @return bool
     */
    public function isZip64ExtensionsRequired();

    /**
     * Returns the compressed size of this entry.
     *
     * @see int
     */
    public function getCompressedSize();

    /**
     * Sets the compressed size of this entry.
     *
     * @param int $compressedSize The Compressed Size.
     * @return ZipEntry
     * @throws ZipException
     */
    public function setCompressedSize($compressedSize);

    /**
     * Returns the uncompressed size of this entry.
     *
     * @see ZipEntry::setCompressedSize
     */
    public function getSize();

    /**
     * Sets the uncompressed size of this entry.
     *
     * @param int $size The (Uncompressed) Size.
     * @return ZipEntry
     * @throws ZipException
     */
    public function setSize($size);

    /**
     * Return relative Offset Of Local File Header.
     *
     * @return int
     */
    public function getOffset();

    /**
     * @param int $offset
     * @return ZipEntry
     * @throws ZipException
     */
    public function setOffset($offset);

    /**
     * Returns true if and only if this ZIP entry represents a directory entry
     * (i.e. end with '/').
     *
     * @return bool
     */
    public function isDirectory();

    /**
     * Returns the General Purpose Bit Flags.
     *
     * @return int
     */
    public function getGeneralPurposeBitFlags();

    /**
     * Sets the General Purpose Bit Flags.
     *
     * @var int general
     * @return ZipEntry
     * @throws ZipException
     */
    public function setGeneralPurposeBitFlags($general);

    /**
     * Returns the indexed General Purpose Bit Flag.
     *
     * @param int $mask
     * @return bool
     */
    public function getGeneralPurposeBitFlag($mask);

    /**
     * Sets the indexed General Purpose Bit Flag.
     *
     * @param int $mask
     * @param bool $bit
     * @return ZipEntry
     */
    public function setGeneralPurposeBitFlag($mask, $bit);

    /**
     * Returns true if and only if this ZIP entry is encrypted.
     *
     * @return bool
     */
    public function isEncrypted();

    /**
     * Sets the encryption flag for this ZIP entry.
     *
     * @param bool $encrypted
     * @return ZipEntry
     */
    public function setEncrypted($encrypted);

    /**
     * Sets the encryption property to false and removes any other
     * encryption artifacts.
     *
     * @return ZipEntry
     */
    public function disableEncryption();

    /**
     * Returns the compression method for this entry.
     *
     * @return int
     */
    public function getMethod();

    /**
     * Sets the compression method for this entry.
     *
     * @param int $method
     * @return ZipEntry
     * @throws ZipException If method is not STORED, DEFLATED, BZIP2 or UNKNOWN.
     */
    public function setMethod($method);

    /**
     * Get Unix Timestamp
     *
     * @return int
     */
    public function getTime();

    /**
     * Set time from unix timestamp.
     *
     * @param int $unixTimestamp
     * @return ZipEntry
     */
    public function setTime($unixTimestamp);

    /**
     * Get Dos Time
     *
     * @return int
     */
    public function getDosTime();

    /**
     * Set Dos Time
     * @param int $dosTime
     * @throws ZipException
     */
    public function setDosTime($dosTime);

    /**
     * Returns the external file attributes.
     *
     * @return int The external file attributes.
     */
    public function getExternalAttributes();

    /**
     * Sets the external file attributes.
     *
     * @param int $externalAttributes the external file attributes.
     * @return ZipEntry
     * @throws ZipException
     */
    public function setExternalAttributes($externalAttributes);

    /**
     * @return ExtraFieldsCollection
     */
    public function getExtraFieldsCollection();

    /**
     * Returns a protective copy of the serialized Extra Fields.
     *
     * @return string A new byte array holding the serialized Extra Fields.
     *                null is never returned.
     */
    public function getExtra();

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
    public function setExtra($data);

    /**
     * Returns comment entry
     *
     * @return string
     */
    public function getComment();

    /**
     * Set entry comment.
     *
     * @param $comment
     * @return ZipEntry
     */
    public function setComment($comment);

    /**
     * @return bool
     */
    public function isDataDescriptorRequired();

    /**
     * Return crc32 content or 0 for WinZip AES v2
     *
     * @return int
     */
    public function getCrc();

    /**
     * Set crc32 content.
     *
     * @param int $crc
     * @return ZipEntry
     * @throws ZipException
     */
    public function setCrc($crc);

    /**
     * @return string
     */
    public function getPassword();

    /**
     * Set password and encryption method from entry
     *
     * @param string $password
     * @param null|int $encryptionMethod
     * @return ZipEntry
     */
    public function setPassword($password, $encryptionMethod = null);

    /**
     * @return int
     */
    public function getEncryptionMethod();

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
    public function setEncryptionMethod($encryptionMethod);

    /**
     * Returns an string content of the given entry.
     *
     * @return null|string
     * @throws ZipException
     */
    public function getEntryContent();

    /**
     * @param int $compressionLevel
     * @return ZipEntry
     */
    public function setCompressionLevel($compressionLevel = ZipFileInterface::LEVEL_DEFAULT_COMPRESSION);

    /**
     * @return int
     */
    public function getCompressionLevel();
}
