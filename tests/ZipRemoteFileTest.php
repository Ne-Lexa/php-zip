<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Tests;

use PhpZip\Exception\ZipException;
use PhpZip\ZipFile;

/**
 * Test add remote files to zip archive.
 *
 * @internal
 *
 * @small
 */
class ZipRemoteFileTest extends ZipTestCase
{
    /**
     * @throws ZipException
     */
    public function testAddRemoteFileFromStream(): void
    {
        $zipFile = new ZipFile();
        $outputZip = $this->outputFilename;
        $fileUrl = 'https://raw.githubusercontent.com/Ne-Lexa/php-zip/master/README.md';
        /** @noinspection PhpUsageOfSilenceOperatorInspection */
        $fp = @fopen(
            $fileUrl,
            'rb',
            false,
            stream_context_create(
                [
                    'http' => [
                        'timeout' => 3,
                    ],
                ]
            )
        );

        if ($fp === false) {
            static::markTestSkipped(
                sprintf(
                    'Could not fetch remote file: %s',
                    $fileUrl
                )
            );
        }

        $fileName = 'remote-file-from-http-stream.md';
        $zipFile->addFromStream($fp, $fileName);
        $zipFile->saveAsFile($outputZip);
        $zipFile->close();

        $zipFile = new ZipFile();
        $zipFile->openFile($outputZip);
        $files = $zipFile->getListFiles();
        static::assertCount(1, $files);
        static::assertSame($fileName, $files[0]);
        $zipFile->close();
    }
}
