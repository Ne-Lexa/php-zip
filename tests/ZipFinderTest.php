<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Tests;

use PhpZip\Constants\ZipCompressionMethod;
use PhpZip\Constants\ZipOptions;
use PhpZip\Exception\ZipException;
use PhpZip\ZipFile;
use Symfony\Component\Finder\Finder;

/**
 * @internal
 *
 * @small
 */
class ZipFinderTest extends ZipTestCase
{
    /**
     * @throws ZipException
     */
    public function testFinder(): void
    {
        $finder = (new Finder())
            ->files()
            ->name('*.php')
            ->in(__DIR__)
        ;
        $zipFile = new ZipFile();
        $zipFile->addFromFinder(
            $finder,
            [
                ZipOptions::COMPRESSION_METHOD => ZipCompressionMethod::DEFLATED,
            ]
        );
        $zipFile->saveAsFile($this->outputFilename);

        static::assertCorrectZipArchive($this->outputFilename);

        static::assertSame($finder->count(), $zipFile->count());
        $zipFile->close();
    }
}
