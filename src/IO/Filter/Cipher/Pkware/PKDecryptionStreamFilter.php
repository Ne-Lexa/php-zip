<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\IO\Filter\Cipher\Pkware;

use PhpZip\Exception\ZipAuthenticationException;
use PhpZip\Model\ZipEntry;

/**
 * Decryption PKWARE Traditional Encryption.
 */
class PKDecryptionStreamFilter extends \php_user_filter
{
    public const FILTER_NAME = 'phpzip.decryption.pkware';

    private int $checkByte = 0;

    private int $readLength = 0;

    private int $size = 0;

    private bool $readHeader = false;

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
        if (!isset($this->params['entry'])) {
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

        $this->size = $entry->getCompressedSize();

        // init context
        $this->context = new PKCryptContext($password);

        // init check byte
        if ($entry->isDataDescriptorEnabled()) {
            $this->checkByte = ($entry->getDosTime() >> 8) & 0xFF;
        } else {
            $this->checkByte = ($entry->getCrc() >> 24) & 0xFF;
        }

        $this->readLength = 0;
        $this->readHeader = false;

        return true;
    }

    /**
     * Decryption filter.
     *
     * @todo USE FFI in php 7.4
     * @noinspection PhpDocSignatureInspection
     *
     * @param mixed $in
     * @param mixed $out
     * @param mixed $consumed
     * @param mixed $closing
     *
     * @throws ZipAuthenticationException
     */
    public function filter($in, $out, &$consumed, $closing): int
    {
        while ($bucket = stream_bucket_make_writeable($in)) {
            $buffer = $bucket->data;
            $this->readLength += $bucket->datalen;

            if ($this->readLength > $this->size) {
                $buffer = substr($buffer, 0, $this->size - $this->readLength);
            }

            if (!$this->readHeader) {
                $header = substr($buffer, 0, PKCryptContext::STD_DEC_HDR_SIZE);
                $this->context->checkHeader($header, $this->checkByte);

                $buffer = substr($buffer, PKCryptContext::STD_DEC_HDR_SIZE);
                $this->readHeader = true;
            }

            $bucket->data = $this->context->decryptString($buffer);

            $consumed += $bucket->datalen;
            stream_bucket_append($out, $bucket);
        }

        return \PSFS_PASS_ON;
    }
}
