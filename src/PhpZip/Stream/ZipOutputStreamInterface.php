<?php

namespace PhpZip\Stream;

use PhpZip\Model\ZipEntry;

/**
 * Write zip file
 *
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
interface ZipOutputStreamInterface
{
    /** Central File Header signature. */
    const CENTRAL_FILE_HEADER_SIG = 0x02014B50;

    public function writeZip();

    /**
     * @param ZipEntry $entry
     */
    public function writeEntry(ZipEntry $entry);

    /**
     * @return resource
     */
    public function getStream();
}
