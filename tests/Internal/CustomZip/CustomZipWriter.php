<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Tests\Internal\CustomZip;

use PhpZip\IO\ZipWriter;
use PhpZip\Model\Extra\Fields\NewUnixExtraField;
use PhpZip\Model\Extra\Fields\NtfsExtraField;

class CustomZipWriter extends ZipWriter
{
    protected function beforeWrite(): void
    {
        parent::beforeWrite();
        $now = new \DateTimeImmutable();
        $ntfsTimeExtra = NtfsExtraField::create($now, $now->modify('-1 day'), $now->modify('-10 day'));
        $unixExtra = new NewUnixExtraField();

        foreach ($this->zipContainer->getEntries() as $entry) {
            $entry->addExtraField($ntfsTimeExtra);
            $entry->addExtraField($unixExtra);
        }
    }
}
