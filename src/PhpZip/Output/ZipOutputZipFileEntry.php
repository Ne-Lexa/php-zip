<?php
namespace PhpZip\Output;

use PhpZip\Exception\ZipException;
use PhpZip\Model\ZipEntry;
use PhpZip\ZipFile;

/**
 * Zip output entry for input zip file.
 *
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class ZipOutputZipFileEntry extends ZipOutputEntry
{
    /**
     * Input zip file.
     *
     * @var ZipFile
     */
    private $inputZipFile;

    /**
     * Input entry name.
     *
     * @var string
     */
    private $inputEntryName;

    /**
     * ZipOutputZipFileEntry constructor.
     * @param ZipFile $zipFile
     * @param ZipEntry $zipEntry
     * @throws ZipException If input zip file is null.
     */
    public function __construct(ZipFile $zipFile, ZipEntry $zipEntry)
    {
        if ($zipFile === null) {
            throw new ZipException('ZipFile is null');
        }
        parent::__construct(clone $zipEntry);

        $this->inputZipFile = $zipFile;
        $this->inputEntryName = $zipEntry->getName();
    }

    /**
     * Returns entry data.
     *
     * @return string
     */
    public function getEntryContent()
    {
        return $this->inputZipFile->getEntryContent($this->inputEntryName);
    }
}