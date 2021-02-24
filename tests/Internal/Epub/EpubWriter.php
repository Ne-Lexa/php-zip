<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Tests\Internal\Epub;

use PhpZip\Constants\ZipCompressionMethod;
use PhpZip\Constants\ZipPlatform;
use PhpZip\Exception\ZipUnsupportMethodException;
use PhpZip\IO\ZipWriter;
use PhpZip\Model\Data\ZipNewData;
use PhpZip\Model\ZipEntry;

/**
 * Class EpubWriter.
 *
 * @property EpubZipContainer $zipContainer
 */
class EpubWriter extends ZipWriter
{
    /**
     * @throws ZipUnsupportMethodException
     */
    protected function beforeWrite(): void
    {
        parent::beforeWrite();

        if (!$this->zipContainer->hasEntry('mimetype')) {
            $zipEntry = new ZipEntry('mimetype');
            $zipEntry->setCreatedOS(ZipPlatform::OS_DOS);
            $zipEntry->setExtractedOS(ZipPlatform::OS_DOS);
            $zipEntry->setCompressionMethod(ZipCompressionMethod::STORED);
            $zipEntry->setData(new ZipNewData($zipEntry, 'application/epub+zip'));
            $this->zipContainer->addEntry($zipEntry);
        }

        $this->sortEntries();
    }

    private function sortEntries(): void
    {
        $this->zipContainer->sortByEntry(
            static function (ZipEntry $a, ZipEntry $b) {
                if (strcasecmp($a->getName(), 'mimetype') === 0) {
                    return -1;
                }

                if (strcasecmp($b->getName(), 'mimetype') === 0) {
                    return 1;
                }

                if ($a->isDirectory() && $b->isDirectory()) {
                    return strcmp($a->getName(), $b->getName());
                }

                if ($a->isDirectory()) {
                    return -1;
                }

                if ($b->isDirectory()) {
                    return 1;
                }

                return strcmp($a->getName(), $b->getName());
            }
        );
    }
}
