<?php
namespace PhpZip\Model\Entry;

use PhpZip\Crypto\TraditionalPkwareEncryptionEngine;
use PhpZip\Crypto\WinZipAesEngine;
use PhpZip\Exception\Crc32Exception;
use PhpZip\Exception\InvalidArgumentException;
use PhpZip\Exception\ZipCryptoException;
use PhpZip\Exception\ZipException;
use PhpZip\Exception\ZipUnsupportMethod;
use PhpZip\Extra\WinZipAesEntryExtraField;
use PhpZip\Model\CentralDirectory;
use PhpZip\Model\ZipEntry;
use PhpZip\ZipFile;

/**
 * This class is used to represent a ZIP file entry.
 *
 * @see https://pkware.cachefly.net/webdocs/casestudies/APPNOTE.TXT .ZIP File Format Specification
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class ZipReadEntry extends ZipAbstractEntry
{
    /**
     * Max size cached content in memory.
     */
    const MAX_SIZE_CACHED_CONTENT_IN_MEMORY = 3145728; // 3 mb
    /**
     * @var resource
     */
    private $inputStream;
    /**
     * @var string
     */
    private $charset;
    /**
     * @var string|resource Cached entry content.
     */
    private $entryContent;

    /**
     * ZipFileEntry constructor.
     * @param $inputStream
     */
    public function __construct($inputStream)
    {
        $this->inputStream = $inputStream;
        $this->readZipEntry($inputStream);
    }

    /**
     * @param resource $inputStream
     * @throws InvalidArgumentException
     */
    private function readZipEntry($inputStream)
    {
        // central file header signature   4 bytes  (0x02014b50)
        $fileHeaderSig = unpack('V', fread($inputStream, 4))[1];
        if (CentralDirectory::CENTRAL_FILE_HEADER_SIG !== $fileHeaderSig) {
            throw new InvalidArgumentException("Corrupt zip file. Can not read zip entry.");
        }

        // version made by                 2 bytes
        // version needed to extract       2 bytes
        // general purpose bit flag        2 bytes
        // compression method              2 bytes
        // last mod file time              2 bytes
        // last mod file date              2 bytes
        // crc-32                          4 bytes
        // compressed size                 4 bytes
        // uncompressed size               4 bytes
        // file name length                2 bytes
        // extra field length              2 bytes
        // file comment length             2 bytes
        // disk number start               2 bytes
        // internal file attributes        2 bytes
        // external file attributes        4 bytes
        // relative offset of local header 4 bytes
        $data = unpack(
            'vversionMadeBy/vversionNeededToExtract/vgpbf/vrawMethod/VrawTime/VrawCrc/VrawCompressedSize/' .
            'VrawSize/vfileLength/vextraLength/vcommentLength/VrawInternalAttributes/VrawExternalAttributes/VlfhOff',
            fread($inputStream, 42)
        );

        $utf8 = 0 !== ($data['gpbf'] & self::GPBF_UTF8);
        if ($utf8) {
            $this->charset = "UTF-8";
        }

        // See appendix D of PKWARE's ZIP File Format Specification.
        $name = fread($inputStream, $data['fileLength']);

        $this->setName($name);
        $this->setVersionNeededToExtract($data['versionNeededToExtract']);
        $this->setPlatform($data['versionMadeBy'] >> 8);
        $this->setGeneralPurposeBitFlags($data['gpbf']);
        $this->setMethod($data['rawMethod']);
        $this->setDosTime($data['rawTime']);
        $this->setCrc($data['rawCrc']);
        $this->setCompressedSize($data['rawCompressedSize']);
        $this->setSize($data['rawSize']);
        $this->setExternalAttributes($data['rawExternalAttributes']);
        $this->setOffset($data['lfhOff']); // must be unmapped!
        if (0 < $data['extraLength']) {
            $this->setExtra(fread($inputStream, $data['extraLength']));
        }
        if (0 < $data['commentLength']) {
            $this->setComment(fread($inputStream, $data['commentLength']));
        }
    }

    /**
     * Returns an string content of the given entry.
     *
     * @return string
     * @throws ZipException
     */
    public function getEntryContent()
    {
        if (null === $this->entryContent) {
            if ($this->isDirectory()) {
                $this->entryContent = null;
                return $this->entryContent;
            }
            $isEncrypted = $this->isEncrypted();
            $password = $this->getPassword();
            if ($isEncrypted && empty($password)) {
                throw new ZipException("Not set password");
            }

            $pos = $this->getOffset();
            assert(self::UNKNOWN !== $pos);
            $startPos = $pos = $this->getCentralDirectory()->getEndOfCentralDirectory()->getMapper()->map($pos);
            fseek($this->inputStream, $startPos);

            // local file header signature     4 bytes  (0x04034b50)
            if (self::LOCAL_FILE_HEADER_SIG !== unpack('V', fread($this->inputStream, 4))[1]) {
                throw new ZipException($this->getName() . " (expected Local File Header)");
            }
            fseek($this->inputStream, $pos + ZipEntry::LOCAL_FILE_HEADER_FILE_NAME_LENGTH_POS);
            // file name length                2 bytes
            // extra field length              2 bytes
            $data = unpack('vfileLength/vextraLength', fread($this->inputStream, 4));
            $pos += ZipEntry::LOCAL_FILE_HEADER_MIN_LEN + $data['fileLength'] + $data['extraLength'];

            assert(self::UNKNOWN !== $this->getCrc());

            $method = $this->getMethod();

            fseek($this->inputStream, $pos);

            // Get raw entry content
            $content = fread($this->inputStream, $this->getCompressedSize());

            // Strong Encryption Specification - WinZip AES
            if ($this->isEncrypted()) {
                if (self::METHOD_WINZIP_AES === $method) {
                    $winZipAesEngine = new WinZipAesEngine($this);
                    $content = $winZipAesEngine->decrypt($content);
                    // Disable redundant CRC-32 check.
                    $isEncrypted = false;

                    /**
                     * @var WinZipAesEntryExtraField $field
                     */
                    $field = $this->getExtraField(WinZipAesEntryExtraField::getHeaderId());
                    $method = $field->getMethod();
                    $this->setEncryptionMethod(ZipFile::ENCRYPTION_METHOD_WINZIP_AES);
                } else {
                    // Traditional PKWARE Decryption
                    $zipCryptoEngine = new TraditionalPkwareEncryptionEngine($this);
                    $content = $zipCryptoEngine->decrypt($content);

                    $this->setEncryptionMethod(ZipFile::ENCRYPTION_METHOD_TRADITIONAL);
                }
            }
            if ($isEncrypted) {
                // Check CRC32 in the Local File Header or Data Descriptor.
                $localCrc = null;
                if ($this->getGeneralPurposeBitFlag(self::GPBF_DATA_DESCRIPTOR)) {
                    // The CRC32 is in the Data Descriptor after the compressed size.
                    // Note the Data Descriptor's Signature is optional:
                    // All newer apps should write it (and so does TrueVFS),
                    // but older apps might not.
                    fseek($this->inputStream, $pos + $this->getCompressedSize());
                    $localCrc = unpack('V', fread($this->inputStream, 4))[1];
                    if (self::DATA_DESCRIPTOR_SIG === $localCrc) {
                        $localCrc = unpack('V', fread($this->inputStream, 4))[1];
                    }
                } else {
                    fseek($this->inputStream, $startPos + 14);
                    // The CRC32 in the Local File Header.
                    $localCrc = unpack('V', fread($this->inputStream, 4))[1];
                }
                if ($this->getCrc() !== $localCrc) {
                    throw new Crc32Exception($this->getName(), $this->getCrc(), $localCrc);
                }
            }

            switch ($method) {
                case ZipFile::METHOD_STORED:
                    break;
                case ZipFile::METHOD_DEFLATED:
                    $content = gzinflate($content);
                    break;
                case ZipFile::METHOD_BZIP2:
                    if (!extension_loaded('bz2')) {
                        throw new ZipException('Extension bzip2 not install');
                    }
                    $content = bzdecompress($content);
                    break;
                default:
                    throw new ZipUnsupportMethod($this->getName()
                        . " (compression method "
                        . $method
                        . " is not supported)");
            }
            if ($isEncrypted) {
                $localCrc = crc32($content);
                if ($this->getCrc() !== $localCrc) {
                    if ($this->isEncrypted()) {
                        throw new ZipCryptoException("Wrong password");
                    }
                    throw new Crc32Exception($this->getName(), $this->getCrc(), $localCrc);
                }
            }
            if ($this->getSize() < self::MAX_SIZE_CACHED_CONTENT_IN_MEMORY) {
                $this->entryContent = $content;
            } else {
                $this->entryContent = fopen('php://temp', 'rb');
                fwrite($this->entryContent, $content);
            }
            return $content;
        }
        if (is_resource($this->entryContent)) {
            return stream_get_contents($this->entryContent, -1, 0);
        }
        return $this->entryContent;
    }

    /**
     * Write local file header, encryption header, file data and data descriptor to output stream.
     *
     * @param resource $outputStream
     */
    public function writeEntry($outputStream)
    {
        $pos = $this->getOffset();
        assert(ZipEntry::UNKNOWN !== $pos);
        $pos = $this->getCentralDirectory()->getEndOfCentralDirectory()->getMapper()->map($pos);
        $pos += ZipEntry::LOCAL_FILE_HEADER_FILE_NAME_LENGTH_POS;

        $this->setOffset(ftell($outputStream));
        // zip align
        $padding = 0;
        $zipAlign = $this->getCentralDirectory()->getZipAlign();
        $extra = $this->getExtra();
        $extraLength = strlen($extra);
        $nameLength = strlen($this->getName());
        if ($zipAlign !== null && !$this->isEncrypted() && $this->getMethod() === ZipFile::METHOD_STORED) {
            $padding =
                (
                    $zipAlign -
                    ($this->getOffset() + ZipEntry::LOCAL_FILE_HEADER_MIN_LEN + $nameLength + $extraLength)
                    % $zipAlign
                ) % $zipAlign;
        }
        $dd = $this->isDataDescriptorRequired();

        fwrite(
            $outputStream,
            pack(
                'VvvvVVVVvv',
                // local file header signature     4 bytes  (0x04034b50)
                self::LOCAL_FILE_HEADER_SIG,
                // version needed to extract       2 bytes
                $this->getVersionNeededToExtract(),
                // general purpose bit flag        2 bytes
                $this->getGeneralPurposeBitFlags(),
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

        fseek($this->inputStream, $pos);
        $data = unpack('vfileLength/vextraLength', fread($this->inputStream, 4));
        fseek($this->inputStream, $data['fileLength'] + $data['extraLength'], SEEK_CUR);

        $length = $this->getCompressedSize();
        if ($this->getGeneralPurposeBitFlag(ZipEntry::GPBF_DATA_DESCRIPTOR)) {
            $length += 12;
            if ($this->isZip64ExtensionsRequired()) {
                $length += 8;
            }
        }
        stream_copy_to_stream($this->inputStream, $outputStream, $length);
    }

    function __destruct()
    {
        if (null !== $this->entryContent && is_resource($this->entryContent)) {
            fclose($this->entryContent);
        }
    }

}