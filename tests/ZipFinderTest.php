<?php

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
    public function testFinder()
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
