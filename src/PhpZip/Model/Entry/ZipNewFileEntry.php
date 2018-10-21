<?php

namespace PhpZip\Model\Entry;

use PhpZip\Exception\InvalidArgumentException;
use PhpZip\Exception\RuntimeException;
use PhpZip\Exception\ZipException;

/**
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class ZipNewFileEntry extends ZipAbstractEntry
{
    /**
     * @var string Filename
     */
    protected $file;

    /**
     * ZipNewEntry constructor.
     * @param string $file
     * @throws ZipException
     */
    public function __construct($file)
    {
        parent::__construct();
        if ($file === null) {
            throw new InvalidArgumentException("file is null");
        }
        $file = (string)$file;
        if (!is_file($file)) {
            throw new ZipException("File $file does not exist.");
        }
        if (!is_readable($file)) {
            throw new ZipException("The '$file' file could not be read. Check permissions.");
        }
        $this->file = $file;
    }

    /**
     * Returns an string content of the given entry.
     *
     * @return null|string
     */
    public function getEntryContent()
    {
        if (!is_file($this->file)) {
            throw new RuntimeException("File {$this->file} does not exist.");
        }
        return file_get_contents($this->file);
    }
}
