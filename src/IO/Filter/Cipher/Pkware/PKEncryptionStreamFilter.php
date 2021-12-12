<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\IO\Filter\Cipher\Pkware;

use PhpZip\Exception\RuntimeException;
use PhpZip\Model\ZipEntry;

/**
 * Encryption PKWARE Traditional Encryption.
 */
class PKEncryptionStreamFilter extends \php_user_filter
{
    public const FILTER_NAME = 'phpzip.encryption.pkware';

    private int $size;

    private string $headerBytes;

    private int $writeLength;

    private bool $writeHeader;

    private PKCryptContext $context;

    public static function register(): bool
    {
        return stream_filter_register(self::FILTER_NAME, __CLASS__);
    }

    /**
     * @see https://php.net/manual/en/php-user-filter.oncreate.php
     */
    public function onCreate(): bool
    {
        if (\PHP_INT_SIZE === 4) {
            throw new RuntimeException('Traditional PKWARE Encryption is not supported in 32-bit PHP.');
        }

        if (!isset($this->params['entry'], $this->params['size'])) {
            return false;
        }

        if (!($this->params['entry'] instanceof ZipEntry)) {
            throw new \RuntimeException('ZipEntry expected');
        }
        /** @var ZipEntry $entry */
        $entry = $this->params['entry'];
        $password = $entry->getPassword();

        if ($password === null) {
            return false;
        }

        $this->size = (int) $this->params['size'];

        // init keys
        $this->context = new PKCryptContext($password);

        $crc = $entry->isDataDescriptorRequired() || $entry->getCrc() === ZipEntry::UNKNOWN
            ? ($entry->getDosTime() & 0x0000FFFF) << 16
            : $entry->getCrc();

        try {
            $headerBytes = random_bytes(PKCryptContext::STD_DEC_HDR_SIZE);
        } catch (\Exception $e) {
            throw new \RuntimeException('Oops, our server is bust and cannot generate any random data.', 1, $e);
        }

        $headerBytes[PKCryptContext::STD_DEC_HDR_SIZE - 1] = pack('c', ($crc >> 24) & 0xFF);
        $headerBytes[PKCryptContext::STD_DEC_HDR_SIZE - 2] = pack('c', ($crc >> 16) & 0xFF);

        $this->headerBytes = $headerBytes;
        $this->writeLength = 0;
        $this->writeHeader = false;

        return true;
    }

    /**
     * Encryption filter.
     *
     * @todo USE FFI in php 7.4
     *
     * @noinspection PhpDocSignatureInspection
     *
     * @param mixed $in
     * @param mixed $out
     * @param mixed $consumed
     * @param mixed $closing
     */
    public function filter($in, $out, &$consumed, $closing): int
    {
        while ($bucket = stream_bucket_make_writeable($in)) {
            $buffer = $bucket->data;
            $this->writeLength += $bucket->datalen;

            if ($this->writeLength > $this->size) {
                $buffer = substr($buffer, 0, $this->size - $this->writeLength);
            }

            $data = '';

            if (!$this->writeHeader) {
                $data .= $this->context->encryptString($this->headerBytes);
                $this->writeHeader = true;
            }

            $data .= $this->context->encryptString($buffer);

            $bucket->data = $data;

            $consumed += $bucket->datalen;
            stream_bucket_append($out, $bucket);
        }

        return \PSFS_PASS_ON;
    }
}
