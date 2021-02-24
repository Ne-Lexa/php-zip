<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Exception;

use PhpZip\Model\ZipEntry;

/**
 * Thrown if entry not found.
 */
class ZipEntryNotFoundException extends ZipException
{
    private string $entryName;

    /**
     * @param ZipEntry|string $entryName
     */
    public function __construct($entryName)
    {
        $entryName = $entryName instanceof ZipEntry ? $entryName->getName() : $entryName;
        parent::__construct(sprintf(
            'Zip Entry "%s" was not found in the archive.',
            $entryName
        ));
        $this->entryName = $entryName;
    }

    public function getEntryName(): string
    {
        return $this->entryName;
    }
}
