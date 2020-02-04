<?php

namespace PhpZip\Tests\Internal\Epub;

use PhpZip\Exception\ZipEntryNotFoundException;
use PhpZip\Model\ZipContainer;

/**
 * Class EpubZipContainer.
 */
class EpubZipContainer extends ZipContainer
{
    /**
     * @throws ZipEntryNotFoundException
     *
     * @return string
     */
    public function getMimeType()
    {
        return $this->getEntry('mimetype')->getData()->getDataAsString();
    }
}
