<?php

namespace PhpZip\Internal;

use PhpZip\ZipFile;

/**
 * Class ZipFileExtended.
 */
class ZipFileExtended extends ZipFile
{
    protected function onBeforeSave()
    {
        parent::onBeforeSave();
        $this->setZipAlign(4);
        $this->deleteFromRegex('~^META\-INF/~i');
    }
}
