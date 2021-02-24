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
 * Recursive iterator for ignore files.
 */
class IgnoreFilesRecursiveFilterIterator extends \RecursiveFilterIterator
{
    /** Ignore list files. */
    private array $ignoreFiles = ['..'];

    public function __construct(\RecursiveIterator $iterator, array $ignoreFiles)
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
                && $ignoreFile[\strlen($ignoreFile) - 1] === '/'
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

    /**
     * @return IgnoreFilesRecursiveFilterIterator
     * @psalm-suppress UndefinedInterfaceMethod
     * @noinspection PhpPossiblePolymorphicInvocationInspection
     */
    public function getChildren(): self
    {
        return new self($this->getInnerIterator()->getChildren(), $this->ignoreFiles);
    }
}
