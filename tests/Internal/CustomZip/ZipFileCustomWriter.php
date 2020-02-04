<?php

namespace PhpZip\Tests\Internal\CustomZip;

use PhpZip\IO\ZipWriter;
use PhpZip\ZipFile;

/**
 * Class ZipFileCustomWriter.
 */
class ZipFileCustomWriter extends ZipFile
{
    /**
     * @return ZipWriter
     */
    protected function createZipWriter()
    {
        return new CustomZipWriter($this->zipContainer);
    }
}
