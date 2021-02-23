<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Tests;

use PHPUnit\Framework\TestCase;
use PhpZip\Constants\ZipConstants;
use PhpZip\Util\FilesUtil;

/**
 * PHPUnit test case and helper methods.
 */
abstract class ZipTestCase extends TestCase
{
    protected string $outputFilename;

    protected string $outputDirname;

    /**
     * Before test.
     */
    protected function setUp(): void
    {
        $id = uniqid('phpzip', false);
        $tempDir = sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'phpunit-phpzip';

        if (!is_dir($tempDir) && !mkdir($tempDir, 0755, true) && !is_dir($tempDir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $tempDir));
        }
        $this->outputFilename = $tempDir . \DIRECTORY_SEPARATOR . $id . '.zip';
        $this->outputDirname = $tempDir . \DIRECTORY_SEPARATOR . $id;
    }

    /**
     * After test.
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        if (file_exists($this->outputFilename)) {
            unlink($this->outputFilename);
        }

        if (is_dir($this->outputDirname)) {
            FilesUtil::removeDir($this->outputDirname);
        }
    }

    /**
     * Assert correct zip archive.
     *
     * @param ?string $password
     */
    public static function assertCorrectZipArchive(string $filename, ?string $password = null): void
    {
        if (self::existsProgram('7z')) {
            self::assertCorrectZipArchiveFrom7z($filename, $password);
        } elseif (self::existsProgram('unzip')) {
            self::assertCorrectZipArchiveFromUnzip($filename, $password);
        } else {
            fwrite(\STDERR, 'Skipped testing the zip archive for errors using third-party utilities.' . \PHP_EOL);
            fwrite(\STDERR, 'To fix this, install 7-zip or unzip.' . \PHP_EOL);
            fwrite(\STDERR, \PHP_EOL);
            fwrite(\STDERR, 'Install on Ubuntu: sudo apt-get install p7zip-full unzip' . \PHP_EOL);
            fwrite(\STDERR, \PHP_EOL);
            fwrite(\STDERR, 'Install on Windows:' . \PHP_EOL);
            fwrite(\STDERR, ' * 7-zip - https://www.7-zip.org/download.html' . \PHP_EOL);
            fwrite(\STDERR, ' * unzip - http://gnuwin32.sourceforge.net/packages/unzip.htm' . \PHP_EOL);
            fwrite(\STDERR, \PHP_EOL);
        }
    }

    private static function assertCorrectZipArchiveFrom7z(string $filename, ?string $password = null): void
    {
        $command = '7z t';

        if ($password !== null) {
            $command .= ' -p' . escapeshellarg($password);
        }
        $command .= ' ' . escapeshellarg($filename) . ' 2>&1';

        exec($command, $outputLines, $returnCode);
        $output = implode(\PHP_EOL, $outputLines);

        static::assertSame($returnCode, 0);
        static::assertStringNotContainsString(' Errors', $output);
        static::assertStringContainsString(' Ok', $output);
    }

    private static function assertCorrectZipArchiveFromUnzip(string $filename, ?string $password = null): void
    {
        $command = 'unzip';

        if ($password !== null) {
            $command .= ' -P ' . escapeshellarg($password);
        }
        $command .= ' -t ' . escapeshellarg($filename) . ' 2>&1';

        exec($command, $outputLines, $returnCode);
        $output = implode(\PHP_EOL, $outputLines);

        if ($password !== null && $returnCode === 81) {
            fwrite(\STDERR, 'Program unzip cannot support this function.' . \PHP_EOL);
            fwrite(\STDERR, 'You have to install 7-zip to complete this test.' . \PHP_EOL);
            fwrite(\STDERR, 'Install 7-Zip on Ubuntu: sudo apt-get install p7zip-full' . \PHP_EOL);
            fwrite(\STDERR, 'Install 7-Zip on Windows: https://www.7-zip.org/download.html' . \PHP_EOL);

            return;
        }

        static::assertSame($returnCode, 0, $output);
        static::assertStringNotContainsString('incorrect password', $output);
        static::assertStringContainsString(' OK', $output);
        static::assertStringContainsString('No errors', $output);
    }

    protected static function existsProgram(string $program, array $successCodes = [0]): bool
    {
        $command = \DIRECTORY_SEPARATOR === '\\'
            ? escapeshellarg($program)
            : 'command -v ' . escapeshellarg($program);
        $command .= ' 2>&1';

        exec($command, $output, $returnCode);

        return \in_array($returnCode, $successCodes, true);
    }

    /**
     * Assert correct empty zip archive.
     *
     * @param $filename
     */
    public static function assertCorrectEmptyZip($filename): void
    {
        if (self::existsProgram('zipinfo')) {
            exec('zipinfo ' . escapeshellarg($filename), $outputLines, $returnCode);

            $output = implode(\PHP_EOL, $outputLines);

            static::assertStringContainsString('Empty zipfile', $output);
        }
        $actualEmptyZipData = pack('VVVVVv', ZipConstants::END_CD, 0, 0, 0, 0, 0);
        static::assertStringEqualsFile($filename, $actualEmptyZipData);
    }

    public static function skipTestForRootUser(): void
    {
        /** @noinspection PhpComposerExtensionStubsInspection */
        if (\extension_loaded('posix') && posix_getuid() === 0) {
            static::markTestSkipped('Skip the test for a user with root privileges');
        }
    }

    public static function skipTestForWindows(): void
    {
        if (\DIRECTORY_SEPARATOR === '\\') {
            static::markTestSkipped('Skip on Windows');
        }
    }
}
