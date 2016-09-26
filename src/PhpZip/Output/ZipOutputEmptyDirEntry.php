<?php
namespace PhpZip\Output;

/**
 * Zip output entry for empty dir.
 *
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class ZipOutputEmptyDirEntry extends ZipOutputEntry
{

    /**
     * Returns entry data.
     *
     * @return string
     */
    public function getEntryContent()
    {
        return '';
    }
}