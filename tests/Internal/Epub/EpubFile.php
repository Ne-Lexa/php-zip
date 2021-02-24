<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
    protected function createZipWriter(): ZipWriter
    {
        return new EpubWriter($this->zipContainer);
    }

    /**
     * @param resource $inputStream
     */
    protected function createZipReader($inputStream, array $options = []): ZipReader
    {
        return new EpubReader($inputStream, $options);
    }

    protected function createZipContainer(?ImmutableZipContainer $sourceContainer = null): ZipContainer
    {
        return new EpubZipContainer($sourceContainer);
    }

    protected function addZipEntry(ZipEntry $zipEntry): void
    {
        $zipEntry->setCreatedOS(ZipPlatform::OS_DOS);
        $zipEntry->setExtractedOS(ZipPlatform::OS_UNIX);
        parent::addZipEntry($zipEntry);
    }

    /**
     * @throws ZipEntryNotFoundException
     */
    public function getMimeType(): string
    {
        return $this->zipContainer->getMimeType();
    }

    /**
     * @throws ZipException
     * @throws ZipEntryNotFoundException
     */
    public function getEpubInfo(): EpubInfo
    {
        return new EpubInfo($this->getEntryContents($this->getRootFile()));
    }

    /**
     * @throws ZipException
     */
    public function getRootFile(): string
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
