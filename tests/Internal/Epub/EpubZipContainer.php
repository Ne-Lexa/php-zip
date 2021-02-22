<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Tests\Internal\Epub;

use PhpZip\Exception\ZipEntryNotFoundException;
use PhpZip\Model\ZipContainer;

class EpubZipContainer extends ZipContainer
{
    /**
     * @throws ZipEntryNotFoundException
     */
    public function getMimeType(): string
    {
        return $this->getEntry('mimetype')->getData()->getDataAsString();
    }
}
