<?php

namespace PhpZip\Tests\Internal\Epub;

use PhpZip\IO\ZipReader;

/**
 * Class EpubReader.
 */
class EpubReader extends ZipReader
{
    /**
     * @return bool
     *
     * @see https://github.com/w3c/epubcheck/issues/334
     */
    protected function isZip64Support()
    {
        return false;
    }
}
