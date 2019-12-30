<?php

namespace PhpZip\Tests\Internal;

use PhpZip\ZipFile;

/**
 * Class ZipFileExtended.
 */
class ZipFileExtended extends ZipFile
{
    protected function onBeforeSave()
    {
        parent::onBeforeSave();
        $this->deleteFromRegex('~^META\-INF/~i');
    }
}
