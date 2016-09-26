<?php
namespace PhpZip\Output;

use PhpZip\Exception\ZipException;
use PhpZip\Model\ZipEntry;

/**
 * Zip output entry for string data.
 *
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class ZipOutputStringEntry extends ZipOutputEntry
{
    /**
     * Data content.
     *
     * @var string
     */
    private $data;

    /**
     * @param string $data
     * @param ZipEntry $entry
     * @throws ZipException If data empty.
     */
    public function __construct($data, ZipEntry $entry)
    {
        parent::__construct($entry);
        $data = (string)$data;
        if ($data === null) {
            throw new ZipException("data is null");
        }
        $this->data = $data;
    }

    /**
     * Returns entry data.
     *
     * @return string
     */
    public function getEntryContent()
    {
        return $this->data;
    }
}