<?php

namespace PhpZip\Tests;

use PhpZip\Util\StringUtil;
use PhpZip\ZipFile;

/**
 * Class ZipFileSetTestCase.
 */
abstract class ZipFileSetTestCase extends ZipTestCase
{
    protected static $files = [
        '.hidden' => 'Hidden file',
        'text file.txt' => 'Text file',
        'Текстовый документ.txt' => 'Текстовый документ',
        'LoremIpsum.txt' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.',
        'empty dir/' => '',
        'empty dir2/ещё пустой каталог/' => '',
        'catalog/New File' => 'New Catalog File',
        'catalog/New File 2' => 'New Catalog File 2',
        'catalog/Empty Dir/' => '',
        'category/list.txt' => 'Category list',
        'category/Pictures/128x160/Car/01.jpg' => 'File 01.jpg',
        'category/Pictures/128x160/Car/02.jpg' => 'File 02.jpg',
        'category/Pictures/240x320/Car/01.jpg' => 'File 01.jpg',
        'category/Pictures/240x320/Car/02.jpg' => 'File 02.jpg',
    ];

    /**
     * Before test.
     */
    protected function setUp()
    {
        parent::setUp();
        $this->fillDirectory();
    }

    protected function fillDirectory()
    {
        foreach (self::$files as $name => $content) {
            $fullName = $this->outputDirname . '/' . $name;

            if (StringUtil::endsWith($name, '/')) {
                if (!is_dir($fullName) && !mkdir($fullName, 0755, true) && !is_dir($fullName)) {
                    throw new \RuntimeException(sprintf('Directory "%s" was not created', $fullName));
                }
            } else {
                $dirname = \dirname($fullName);

                if (!is_dir($dirname) && !mkdir($dirname, 0755, true) && !is_dir($dirname)) {
                    throw new \RuntimeException(sprintf('Directory "%s" was not created', $dirname));
                }
                file_put_contents($fullName, $content);
            }
        }
    }

    /**
     * @param ZipFile $zipFile
     * @param array   $actualResultFiles
     * @param string  $localPath
     */
    protected static function assertFilesResult(
        ZipFile $zipFile,
        array $actualResultFiles = [],
        $localPath = '/'
    ) {
        $localPath = rtrim($localPath, '/');
        $localPath = empty($localPath) ? '' : $localPath . '/';
        static::assertCount(\count($zipFile), $actualResultFiles);
        $actualResultFiles = array_flip($actualResultFiles);

        foreach (self::$files as $file => $content) {
            $zipEntryName = $localPath . $file;

            if (isset($actualResultFiles[$file])) {
                static::assertTrue(isset($zipFile[$zipEntryName]), 'Not found entry name ' . $zipEntryName);
                static::assertSame(
                    $zipFile[$zipEntryName],
                    $content,
                    sprintf('The content of the entry "%s" is not as expected.', $zipEntryName)
                );
                unset($actualResultFiles[$file]);
            } else {
                static::assertFalse(isset($zipFile[$zipEntryName]));
            }
        }
        static::assertEmpty($actualResultFiles);
    }
}
