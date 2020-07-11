<?php

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
     * @param bool $allowSymlink
     *
     * @throws \Exception
     */
    public function testSymlink($allowSymlink)
    {
        if (self::skipTestForWindows()) {
            return;
        }

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
        self::assertFalse(is_dir($this->outputDirname));
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

    /**
     * @return \Generator
     */
    public function provideAllowSymlink()
    {
        yield 'allow' => [true];
        yield 'deny' => [false];
    }
}
