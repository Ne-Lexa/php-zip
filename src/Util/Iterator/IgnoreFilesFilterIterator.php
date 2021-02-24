<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Util\Iterator;

use PhpZip\Util\StringUtil;

/**
 * Iterator for ignore files.
 */
class IgnoreFilesFilterIterator extends \FilterIterator
{
    /** Ignore list files. */
    private array $ignoreFiles = ['..'];

    public function __construct(\Iterator $iterator, array $ignoreFiles)
    {
        parent::__construct($iterator);
        $this->ignoreFiles = array_merge($this->ignoreFiles, $ignoreFiles);
    }

    /**
     * Check whether the current element of the iterator is acceptable.
     *
     * @see http://php.net/manual/en/filteriterator.accept.php
     *
     * @return bool true if the current element is acceptable, otherwise false
     */
    public function accept(): bool
    {
        /**
         * @var \SplFileInfo $fileInfo
         */
        $fileInfo = $this->current();
        $pathname = str_replace('\\', '/', $fileInfo->getPathname());

        foreach ($this->ignoreFiles as $ignoreFile) {
            // handler dir and sub dir
            if ($fileInfo->isDir()
                && StringUtil::endsWith($ignoreFile, '/')
                && StringUtil::endsWith($pathname, substr($ignoreFile, 0, -1))
            ) {
                return false;
            }

            // handler filename
            if (StringUtil::endsWith($pathname, $ignoreFile)) {
                return false;
            }
        }

        return true;
    }
}
