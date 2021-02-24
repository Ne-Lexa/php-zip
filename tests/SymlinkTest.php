<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Tests;

use PhpZip\Constants\ZipOptions;
use PhpZip\Util\FilesUtil;
use PhpZip\ZipFile;
use Symfony\Component\Finder\Finder;

/**
 * @internal
 *
 * @small
 */
final class SymlinkTest extends ZipTestCase
{
    /**
     * @dataProvider provideAllowSymlink
     *
     * @throws \Exception
     */
    public function testSymlink(bool $allowSymlink): void
    {
        self::skipTestForWindows();

        if (!is_dir($this->outputDirname)) {
            self::assertTrue(mkdir($this->outputDirname, 0755, true));
        }

        $contentsFile = random_bytes(100);
        $filePath = $this->outputDirname . '/file.bin';
        $symlinkPath = $this->outputDirname . '/symlink.bin';
        $symlinkTarget = basename($filePath);
        self::assertNotFalse(file_put_contents($filePath, $contentsFile));
        self::assertTrue(symlink($symlinkTarget, $symlinkPath));

        $finder = (new Finder())->in($this->outputDirname);
        $zipFile = new ZipFile();
        $zipFile->addFromFinder($finder);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        FilesUtil::removeDir($this->outputDirname);
        self::assertDirectoryDoesNotExist($this->outputDirname);
        self::assertTrue(mkdir($this->outputDirname, 0755, true));

        $zipFile->openFile($this->outputFilename);
        $zipFile->extractTo($this->outputDirname, null, [
            ZipOptions::EXTRACT_SYMLINKS => $allowSymlink,
        ]);
        $zipFile->close();

        $splFileInfo = new \SplFileInfo($symlinkPath);

        if ($allowSymlink) {
            self::assertTrue($splFileInfo->isLink());
            self::assertSame($splFileInfo->getLinkTarget(), $symlinkTarget);
        } else {
            self::assertFalse($splFileInfo->isLink());
            self::assertStringEqualsFile($symlinkPath, $symlinkTarget);
        }
    }

    public function provideAllowSymlink(): \Generator
    {
        yield 'allow' => [true];
        yield 'deny' => [false];
    }
}
