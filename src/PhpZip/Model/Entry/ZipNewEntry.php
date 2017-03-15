<?php
namespace PhpZip\Model\Entry;

use PhpZip\Crypto\TraditionalPkwareEncryptionEngine;
use PhpZip\Crypto\WinZipAesEngine;
use PhpZip\Exception\ZipException;
use PhpZip\Extra\WinZipAesEntryExtraField;
use PhpZip\Model\ZipEntry;
use PhpZip\Util\PackUtil;
use PhpZip\ZipFile;

/**
 * Abstract class for new zip entry.
 *
 * @see https://pkware.cachefly.net/webdocs/casestudies/APPNOTE.TXT .ZIP File Format Specification
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
abstract class ZipNewEntry extends ZipAbstractEntry
{
    /**
     * Default compression level for bzip2
     */
    const LEVEL_DEFAULT_BZIP2_COMPRESSION = 4;

    /**
     * Version needed to extract.
     *
     * @return int
     */
    public function getVersionNeededToExtract()
    {
        $method = $this->getMethod();
        return self::METHOD_WINZIP_AES === $method ? 51 :
            (ZipFile::METHOD_BZIP2 === $method ? 46 :
                ($this->isZip64ExtensionsRequired() ? 45 :
                    (ZipFile::METHOD_DEFLATED === $method || $this->isDirectory() ? 20 : 10)
                )
            );
    }

    /**
     * Write local file header, encryption header, file data and data descriptor to output stream.
     *
     * @param resource $outputStream
     * @throws ZipException
     */
    public function writeEntry($outputStream)
    {
        $nameLength = strlen($this->getName());
        $size = $nameLength + strlen($this->getExtra()) + strlen($this->getComment());
        if (0xffff < $size) {
            throw new ZipException($this->getName()
                . " (the total size of "
                . $size
                . " bytes for the name, extra fields and comment exceeds the maximum size of "
                . 0xffff . " bytes)");
        }

        if (self::UNKNOWN === $this->getPlatform()) {
            $this->setPlatform(self::PLATFORM_UNIX);
        }
        if (self::UNKNOWN === $this->getTime()) {
            $this->setTime(time());
        }
        $method = $this->getMethod();
        if (self::UNKNOWN === $method) {
            $this->setMethod($method = ZipFile::METHOD_DEFLATED);
        }
        $skipCrc = false;

        $encrypted = $this->isEncrypted();
        $dd = $this->isDataDescriptorRequired();
        // Compose General Purpose Bit Flag.
        // See appendix D of PKWARE's ZIP File Format Specification.
        $utf8 = true;
        $general = ($encrypted ? self::GPBF_ENCRYPTED : 0)
            | ($dd ? self::GPBF_DATA_DESCRIPTOR : 0)
            | ($utf8 ? self::GPBF_UTF8 : 0);

        $entryContent = $this->getEntryContent();

        $this->setSize(strlen($entryContent));
        $this->setCrc(crc32($entryContent));

        if ($encrypted && null === $this->getPassword()) {
            throw new ZipException("Can not password from entry " . $this->getName());
        }

        if (
            $encrypted &&
            (
                self::METHOD_WINZIP_AES === $method ||
                $this->getEncryptionMethod() === ZipFile::ENCRYPTION_METHOD_WINZIP_AES
            )
        ) {
            $field = null;
            $method = $this->getMethod();
            $keyStrength = 256; // bits

            $compressedSize = $this->getCompressedSize();

            if (self::METHOD_WINZIP_AES === $method) {
                /**
                 * @var WinZipAesEntryExtraField $field
                 */
                $field = $this->getExtraField(WinZipAesEntryExtraField::getHeaderId());
                if (null !== $field) {
                    $method = $field->getMethod();
                    if (self::UNKNOWN !== $compressedSize) {
                        $compressedSize -= $field->getKeyStrength() / 2 // salt value
                            + 2   // password verification value
                            + 10; // authentication code
                    }
                    $this->setMethod($method);
                }
            }
            if (null === $field) {
                $field = new WinZipAesEntryExtraField();
            }
            $field->setKeyStrength($keyStrength);
            $field->setMethod($method);
            $size = $this->getSize();
            if (20 <= $size && ZipFile::METHOD_BZIP2 !== $method) {
                $field->setVendorVersion(WinZipAesEntryExtraField::VV_AE_1);
            } else {
                $field->setVendorVersion(WinZipAesEntryExtraField::VV_AE_2);
                $skipCrc = true;
            }
            $this->addExtraField($field);
            if (self::UNKNOWN !== $compressedSize) {
                $compressedSize += $field->getKeyStrength() / 2 // salt value
                    + 2   // password verification value
                    + 10; // authentication code
                $this->setCompressedSize($compressedSize);
            }
            if ($skipCrc) {
                $this->setCrc(0);
            }
        }

        switch ($method) {
            case ZipFile::METHOD_STORED:
                break;
            case ZipFile::METHOD_DEFLATED:
                $entryContent = gzdeflate($entryContent, $this->getCompressionLevel());
                break;
            case ZipFile::METHOD_BZIP2:
                $compressionLevel = $this->getCompressionLevel() === ZipFile::LEVEL_DEFAULT_COMPRESSION ?
                    self::LEVEL_DEFAULT_BZIP2_COMPRESSION :
                    $this->getCompressionLevel();
                $entryContent = bzcompress($entryContent, $compressionLevel);
                if (is_int($entryContent)) {
                    throw new ZipException('Error bzip2 compress. Error code: ' . $entryContent);
                }
                break;
            default:
                throw new ZipException($this->getName() . " (unsupported compression method " . $method . ")");
        }

        if ($encrypted) {
            if ($this->getEncryptionMethod() === ZipFile::ENCRYPTION_METHOD_WINZIP_AES) {
                if ($skipCrc) {
                    $this->setCrc(0);
                }
                $this->setMethod(self::METHOD_WINZIP_AES);

                /**
                 * @var WinZipAesEntryExtraField $field
                 */
                $field = $this->getExtraField(WinZipAesEntryExtraField::getHeaderId());
                $winZipAesEngine = new WinZipAesEngine($this, $field);
                $entryContent = $winZipAesEngine->encrypt($entryContent);
            } elseif ($this->getEncryptionMethod() === ZipFile::ENCRYPTION_METHOD_TRADITIONAL) {
                $zipCryptoEngine = new TraditionalPkwareEncryptionEngine($this);
                $entryContent = $zipCryptoEngine->encrypt($entryContent);
            }
        }

        $compressedSize = strlen($entryContent);
        $this->setCompressedSize($compressedSize);

        $offset = ftell($outputStream);

        // Commit changes.
        $this->setGeneralPurposeBitFlags($general);
        $this->setOffset($offset);

        $extra = $this->getExtra();

        // zip align
        $padding = 0;
        $zipAlign = $this->getCentralDirectory()->getZipAlign();
        $extraLength = strlen($extra);
        if ($zipAlign !== null && !$this->isEncrypted() && $this->getMethod() === ZipFile::METHOD_STORED) {
            $padding =
                (
                    $zipAlign -
                    (
                        $offset +
                        ZipEntry::LOCAL_FILE_HEADER_MIN_LEN +
                        $nameLength + $extraLength
                    ) % $zipAlign
                ) % $zipAlign;
        }

        fwrite(
            $outputStream,
            pack(
                'VvvvVVVVvv',
                // local file header signature     4 bytes  (0x04034b50)
                self::LOCAL_FILE_HEADER_SIG,
                // version needed to extract       2 bytes
                $this->getVersionNeededToExtract(),
                // general purpose bit flag        2 bytes
                $general,
                // compression method              2 bytes
                $this->getMethod(),
                // last mod file time              2 bytes
                // last mod file date              2 bytes
                $this->getDosTime(),
                // crc-32                          4 bytes
                $dd ? 0 : $this->getCrc(),
                // compressed size                 4 bytes
                $dd ? 0 : $this->getCompressedSize(),
                // uncompressed size               4 bytes
                $dd ? 0 : $this->getSize(),
                // file name length                2 bytes
                $nameLength,
                // extra field length              2 bytes
                $extraLength + $padding
            )
        );
        fwrite($outputStream, $this->getName());
        if ($extraLength > 0) {
            fwrite($outputStream, $extra);
        }

        if ($padding > 0) {
            fwrite($outputStream, str_repeat(chr(0), $padding));
        }

        if (null !== $entryContent) {
            fwrite($outputStream, $entryContent);
        }

        assert(self::UNKNOWN !== $this->getCrc());
        assert(self::UNKNOWN !== $this->getSize());
        if ($this->getGeneralPurposeBitFlag(self::GPBF_DATA_DESCRIPTOR)) {
            // data descriptor signature       4 bytes  (0x08074b50)
            // crc-32                          4 bytes
            fwrite($outputStream, pack('VV', self::DATA_DESCRIPTOR_SIG, $this->getCrc()));
            // compressed size                 4 or 8 bytes
            // uncompressed size               4 or 8 bytes
            if ($this->isZip64ExtensionsRequired()) {
                fwrite($outputStream, PackUtil::packLongLE($compressedSize));
                fwrite($outputStream, PackUtil::packLongLE($this->getSize()));
            } else {
                fwrite($outputStream, pack('VV', $this->getCompressedSize(), $this->getSize()));
            }
        } elseif ($this->getCompressedSize() !== $compressedSize) {
            throw new ZipException($this->getName()
                . " (expected compressed entry size of "
                . $this->getCompressedSize() . " bytes, but is actually " . $compressedSize . " bytes)");
        }
    }

}