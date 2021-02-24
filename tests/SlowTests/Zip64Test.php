<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Tests\SlowTests;

use PhpZip\Constants\ZipCompressionMethod;
use PhpZip\Exception\ZipException;
use PhpZip\Tests\ZipTestCase;
use PhpZip\Util\FilesUtil;
use PhpZip\ZipFile;

/**
 * @internal
 *
 * @large
 */
class Zip64Test extends ZipTestCase
{
    /**
     * @throws ZipException
     */
    public function testCreateLargeZip64File(): void
    {
        if (\PHP_INT_SIZE === 4) { // php 32 bit
            static::markTestSkipped('Only php-64 bit.');
        }

        if (!self::existsProgram('fallocate')) {
            static::markTestSkipped('Cannot find the program "fallocate" for the test');
        }

        $basedir = \dirname($this->outputFilename);
        $tmpLargeFile = $basedir . '/large_bin_file.bin';

        $sizeLargeBinFile = (int) (4.2 * 1024 * 1024 * 1024);
        $needFreeSpace = $sizeLargeBinFile * 4;
        $diskFreeSpace = disk_free_space($basedir);

        if ($needFreeSpace > $diskFreeSpace) {
            static::markTestIncomplete(
                sprintf(
                    'Not enough disk space for the test. Need to free %s',
                    FilesUtil::humanSize($needFreeSpace - $diskFreeSpace)
                )
            );
        }

        try {
            $commandCreateLargeBinFile = 'fallocate -l ' . $sizeLargeBinFile . ' ' . escapeshellarg($tmpLargeFile);

            exec($commandCreateLargeBinFile, $output, $returnCode);

            if ($returnCode !== 0) {
                static::markTestIncomplete('Cannot create large file. Error code: ' . $returnCode);
            }

            $zipFile = new ZipFile();
            $zipFile
                ->addFile($tmpLargeFile, 'large_file1.bin', ZipCompressionMethod::STORED)
                ->addFile($tmpLargeFile, 'large_file2.bin', ZipCompressionMethod::DEFLATED)
                ->saveAsFile($this->outputFilename)
                ->close()
            ;

            if (is_file($tmpLargeFile)) {
                unlink($tmpLargeFile);
            }

            self::assertCorrectZipArchive($this->outputFilename);

            if (!is_dir($this->outputDirname)) {
                static::assertTrue(mkdir($this->outputDirname, 0755, true));
            }

            $zipFile->openFile($this->outputFilename);
            $zipFile->extractTo($this->outputDirname);

            static::assertTrue(is_file($this->outputDirname . '/large_file1.bin'));
            static::assertTrue(is_file($this->outputDirname . '/large_file2.bin'));

            $zipFile->deleteFromName('large_file1.bin');
            $zipFile->saveAsFile($this->outputFilename);

            self::assertCorrectZipArchive($this->outputFilename);
        } finally {
            if (is_file($tmpLargeFile)) {
                unlink($tmpLargeFile);
            }
        }
    }
}
