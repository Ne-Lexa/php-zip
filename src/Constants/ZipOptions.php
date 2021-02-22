<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Constants;

use PhpZip\IO\ZipReader;
use PhpZip\ZipFile;

interface ZipOptions
{
    /**
     * Boolean option for store just file names (skip directory names).
     *
     * @see ZipFile::addFromFinder()
     */
    public const STORE_ONLY_FILES = 'only_files';

    /**
     * Uses the specified compression method.
     *
     * @see ZipFile::addFromFinder()
     * @see ZipFile::addSplFile()
     */
    public const COMPRESSION_METHOD = 'compression_method';

    /**
     * Set the specified record modification time.
     * The value can be {@see \DateTimeInterface}, integer timestamp
     * or a string of any format.
     *
     * @see ZipFile::addFromFinder()
     * @see ZipFile::addSplFile()
     */
    public const MODIFIED_TIME = 'mtime';

    /**
     * Specifies the encoding of the record name for cases when the UTF-8
     * usage flag is not set.
     *
     * The most commonly used encodings are compiled into the constants
     * of the {@see DosCodePage} class.
     *
     * @see ZipFile::openFile()
     * @see ZipFile::openFromString()
     * @see ZipFile::openFromStream()
     * @see ZipReader::getDefaultOptions()
     * @see DosCodePage::getCodePages()
     */
    public const CHARSET = 'charset';

    /**
     * Allows ({@see true}) or denies ({@see false}) unpacking unix symlinks.
     *
     * This is a potentially dangerous operation for uncontrolled zip files.
     * By default is ({@see false}).
     *
     * @see https://josipfranjkovic.blogspot.com/2014/12/reading-local-files-from-facebooks.html
     */
    public const EXTRACT_SYMLINKS = 'extract_symlinks';
}
