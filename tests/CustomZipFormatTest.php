<?php

namespace PhpZip\Tests;

use PhpZip\Exception\ZipEntryNotFoundException;
use PhpZip\Exception\ZipException;
use PhpZip\Model\Extra\Fields\NewUnixExtraField;
use PhpZip\Model\Extra\Fields\NtfsExtraField;
use PhpZip\Tests\Internal\CustomZip\ZipFileCustomWriter;
use PhpZip\Tests\Internal\CustomZip\ZipFileWithBeforeSave;
use PhpZip\Tests\Internal\Epub\EpubFile;
use PhpZip\ZipFile;

/**
 * Checks the ability to create own file-type class, reader, writer and container.
 **.
 *
 * @internal
 *
 * @small
 */
final class CustomZipFormatTest extends ZipTestCase
{
    /**
     * @throws ZipException
     *
     * @see http://www.epubtest.org/test-books source epub files
     */
    public function testEpub()
    {
        $epubFile = new EpubFile();
        $epubFile->openFile(__DIR__ . '/resources/Advanced-v1.0.0.epub');
        self::assertSame($epubFile->getRootFile(), 'EPUB/package.opf');
        self::assertSame($epubFile->getMimeType(), 'application/epub+zip');
        $epubInfo = $epubFile->getEpubInfo();
        self::assertSame($epubInfo->toArray(), [
            'title' => 'Advanced Accessibility Tests: Extended Descriptions',
            'creator' => 'DAISY Consortium Transition to EPUB 3 and DIAGRAM Standards WG',
            'language' => 'en-US',
            'publisher' => 'DAISY Consortium and DIAGRAM Center',
            'description' => 'Tests for accessible extended descriptions of images in EPUBs',
            'rights' => 'This work is licensed under a Creative Commons Attribution-Noncommercial-Share Alike (CC BY-NC-SA) license.',
            'date' => '2019-01-03',
            'subject' => 'extended-descriptions',
        ]);
        $epubFile->deleteFromName('mimetype');
        self::assertFalse($epubFile->hasEntry('mimetype'));

        try {
            $epubFile->getMimeType();
            self::fail('deleted mimetype');
        } catch (ZipEntryNotFoundException $e) {
            self::assertSame('Zip Entry "mimetype" was not found in the archive.', $e->getMessage());
        }
        $epubFile->saveAsFile($this->outputFilename);
        self::assertFalse($epubFile->hasEntry('mimetype'));
        $epubFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $epubFile->openFile($this->outputFilename);
        // file appended in EpubWriter before write
        self::assertTrue($epubFile->hasEntry('mimetype'));
        $epubFile->close();
    }

    /**
     * @throws \Exception
     */
    public function testBeforeSaveInZipWriter()
    {
        $zipFile = new ZipFileCustomWriter();
        for ($i = 0; $i < 10; $i++) {
            $zipFile['file ' . $i] = 'contents file ' . $i;
        }
        $this->existsExtraFields($zipFile, false);
        $zipFile->saveAsFile($this->outputFilename);
        $this->existsExtraFields($zipFile, false);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        $this->existsExtraFields($zipFile, true);
        $zipFile->close();
    }

    /**
     * @throws \Exception
     */
    public function testBeforeSaveInZipFile()
    {
        $zipFile = new ZipFileWithBeforeSave();
        for ($i = 0; $i < 10; $i++) {
            $zipFile['file ' . $i] = 'contents file ' . $i;
        }
        $this->existsExtraFields($zipFile, false);
        $zipFile->saveAsFile($this->outputFilename);
        $this->existsExtraFields($zipFile, true);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        $this->existsExtraFields($zipFile, true);
        $zipFile->close();
    }

    /**
     * @param ZipFile $zipFile
     * @param bool    $exists
     */
    private function existsExtraFields(ZipFile $zipFile, $exists)
    {
        foreach ($zipFile->getEntries() as $entry) {
            $localExtras = $entry->getLocalExtraFields();
            $cdExtras = $entry->getCdExtraFields();

            self::assertSame(isset($localExtras[NtfsExtraField::HEADER_ID]), $exists);
            self::assertSame(isset($cdExtras[NtfsExtraField::HEADER_ID]), $exists);

            self::assertSame(isset($localExtras[NewUnixExtraField::HEADER_ID]), $exists);
            self::assertSame(isset($cdExtras[NewUnixExtraField::HEADER_ID]), $exists);
        }
    }
}
