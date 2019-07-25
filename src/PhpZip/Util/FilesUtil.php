<?php

namespace PhpZip\Util;

use PhpZip\Util\Iterator\IgnoreFilesFilterIterator;
use PhpZip\Util\Iterator\IgnoreFilesRecursiveFilterIterator;

/**
 * Files util.
 *
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class FilesUtil
{

    /**
     * Is empty directory
     *
     * @param string $dir Directory
     * @return bool
     */
    public static function isEmptyDir($dir)
    {
        if (!is_readable($dir)) {
            return false;
        }
        return count(scandir($dir)) === 2;
    }

    /**
     * Remove recursive directory.
     *
     * @param string $dir Directory path.
     */
    public static function removeDir($dir)
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileInfo) {
            $function = ($fileInfo->isDir() ? 'rmdir' : 'unlink');
            $function($fileInfo->getRealPath());
        }
        rmdir($dir);
    }


    /**
     * Convert glob pattern to regex pattern.
     *
     * @param string $globPattern
     * @return string
     */
    public static function convertGlobToRegEx($globPattern)
    {
        // Remove beginning and ending * globs because they're useless
        $globPattern = trim($globPattern, '*');
        $escaping = false;
        $inCurrent = 0;
        $chars = str_split($globPattern);
        $regexPattern = '';
        foreach ($chars as $currentChar) {
            switch ($currentChar) {
                case '*':
                    $regexPattern .= ($escaping ? "\\*" : '.*');
                    $escaping = false;
                    break;
                case '?':
                    $regexPattern .= ($escaping ? "\\?" : '.');
                    $escaping = false;
                    break;
                case '.':
                case '(':
                case ')':
                case '+':
                case '|':
                case '^':
                case '$':
                case '@':
                case '%':
                    $regexPattern .= '\\' . $currentChar;
                    $escaping = false;
                    break;
                case '\\':
                    if ($escaping) {
                        $regexPattern .= "\\\\";
                        $escaping = false;
                    } else {
                        $escaping = true;
                    }
                    break;
                case '{':
                    if ($escaping) {
                        $regexPattern .= "\\{";
                    } else {
                        $regexPattern = '(';
                        $inCurrent++;
                    }
                    $escaping = false;
                    break;
                case '}':
                    if ($inCurrent > 0 && !$escaping) {
                        $regexPattern .= ')';
                        $inCurrent--;
                    } elseif ($escaping) {
                        $regexPattern = "\\}";
                    } else {
                        $regexPattern = "}";
                    }
                    $escaping = false;
                    break;
                case ',':
                    if ($inCurrent > 0 && !$escaping) {
                        $regexPattern .= '|';
                    } elseif ($escaping) {
                        $regexPattern .= "\\,";
                    } else {
                        $regexPattern = ",";
                    }
                    break;
                default:
                    $escaping = false;
                    $regexPattern .= $currentChar;
            }
        }
        return $regexPattern;
    }

    /**
     * Search files.
     *
     * @param string $inputDir
     * @param bool $recursive
     * @param array $ignoreFiles
     * @return array Searched file list
     */
    public static function fileSearchWithIgnore($inputDir, $recursive = true, array $ignoreFiles = [])
    {
        $directoryIterator = $recursive ?
            new \RecursiveDirectoryIterator($inputDir) :
            new \DirectoryIterator($inputDir);

        if (!empty($ignoreFiles)) {
            $directoryIterator = $recursive ?
                new IgnoreFilesRecursiveFilterIterator($directoryIterator, $ignoreFiles) :
                new IgnoreFilesFilterIterator($directoryIterator, $ignoreFiles);
        }

        $iterator = $recursive ?
            new \RecursiveIteratorIterator($directoryIterator) :
            new \IteratorIterator($directoryIterator);

        $fileList = [];
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo) {
                $fileList[] = $file->getPathname();
            }
        }
        return $fileList;
    }

    /**
     * Search files from glob pattern.
     *
     * @param string $globPattern
     * @param int $flags
     * @param bool $recursive
     * @return array Searched file list
     */
    public static function globFileSearch($globPattern, $flags = 0, $recursive = true)
    {
        $flags = (int)$flags;
        $recursive = (bool)$recursive;
        $files = glob($globPattern, $flags);
        if (!$recursive) {
            return $files;
        }
        foreach (glob(dirname($globPattern) . '/*', GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {
            $files = array_merge($files, self::globFileSearch($dir . '/' . basename($globPattern), $flags, $recursive));
        }
        return $files;
    }

    /**
     * Search files from regex pattern.
     *
     * @param string $folder
     * @param string $pattern
     * @param bool $recursive
     * @return array Searched file list
     */
    public static function regexFileSearch($folder, $pattern, $recursive = true)
    {
        $directoryIterator = $recursive ? new \RecursiveDirectoryIterator($folder) : new \DirectoryIterator($folder);
        $iterator = $recursive ? new \RecursiveIteratorIterator($directoryIterator) : new \IteratorIterator($directoryIterator);
        $regexIterator = new \RegexIterator($iterator, $pattern, \RegexIterator::MATCH);
        $fileList = [];
        foreach ($regexIterator as $file) {
            if ($file instanceof \SplFileInfo) {
                $fileList[] = $file->getPathname();
            }
        }
        return $fileList;
    }

    /**
     * Convert bytes to human size.
     *
     * @param int $size Size bytes
     * @param string|null $unit Unit support 'GB', 'MB', 'KB'
     * @return string
     */
    public static function humanSize($size, $unit = null)
    {
        if (($unit === null && $size >= 1 << 30) || $unit === "GB") {
            return number_format($size / (1 << 30), 2) . "GB";
        }
        if (($unit === null && $size >= 1 << 20) || $unit === "MB") {
            return number_format($size / (1 << 20), 2) . "MB";
        }
        if (($unit === null && $size >= 1 << 10) || $unit === "KB") {
            return number_format($size / (1 << 10), 2) . "KB";
        }
        return number_format($size) . " bytes";
    }

    /**
     * Normalizes zip path.
     *
     * @param string $path Zip path
     * @return string
     */
    public static function normalizeZipPath($path)
    {
        return implode(
            '/',
            array_filter(
                explode('/', (string)$path),
                static function ($part) {
                    return $part !== '.' && $part !== '..';
                }
            )
        );
    }
}
