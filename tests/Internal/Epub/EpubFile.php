<?php

/** @noinspection PhpComposerExtensionStubsInspection */

namespace PhpZip\Tests\Internal\Epub;

use PhpZip\Constants\ZipPlatform;
use PhpZip\Exception\ZipEntryNotFoundException;
use PhpZip\Exception\ZipException;
use PhpZip\IO\ZipReader;
use PhpZip\IO\ZipWriter;
use PhpZip\Model\ImmutableZipContainer;
use PhpZip\Model\ZipContainer;
use PhpZip\Model\ZipEntry;
use PhpZip\ZipFile;

/**
 * Class EpubFile.
 *
 * @property EpubZipContainer $zipContainer
 */
class EpubFile extends ZipFile
{
    /**
     * @return ZipWriter
     */
    protected function createZipWriter()
    {
        return new EpubWriter($this->zipContainer);
    }

    /**
     * @param resource $inputStream
     * @param array    $options
     *
     * @return ZipReader
     */
    protected function createZipReader($inputStream, array $options = [])
    {
        return new EpubReader($inputStream, $options);
    }

    /**
     * @param ImmutableZipContainer|null $sourceContainer
     *
     * @return ZipContainer
     */
    protected function createZipContainer(ImmutableZipContainer $sourceContainer = null)
    {
        return new EpubZipContainer($sourceContainer);
    }

    /**
     * @param ZipEntry $zipEntry
     */
    protected function addZipEntry(ZipEntry $zipEntry)
    {
        $zipEntry->setCreatedOS(ZipPlatform::OS_DOS);
        $zipEntry->setExtractedOS(ZipPlatform::OS_UNIX);
        parent::addZipEntry($zipEntry);
    }

    /**
     * @throws ZipEntryNotFoundException
     *
     * @return string
     */
    public function getMimeType()
    {
        return $this->zipContainer->getMimeType();
    }

    public function getEpubInfo()
    {
        return new EpubInfo($this->getEntryContents($this->getRootFile()));
    }

    /**
     * @throws ZipException
     *
     * @return string
     */
    public function getRootFile()
    {
        $entryName = 'META-INF/container.xml';
        $contents = $this->getEntryContents($entryName);
        $doc = new \DOMDocument();
        $doc->loadXML($contents);
        $xpath = new \DOMXPath($doc);
        $rootFile = $xpath->evaluate('string(//@full-path)');

        if ($rootFile === '') {
            throw new ZipException('Incorrect ' . $entryName . ' file format');
        }

        return $rootFile;
    }
}
