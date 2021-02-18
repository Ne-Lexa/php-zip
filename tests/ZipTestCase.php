<?php

namespace PhpZip\Tests;

use PHPUnit\Framework\TestCase;
use PhpZip\Constants\ZipConstants;
use PhpZip\Util\FilesUtil;

/**
 * PHPUnit test case and helper methods.
 */
abstract class ZipTestCase extends TestCase
{
    /** @var string */
    protected $outputFilename;

    /** @var string */
    protected $outputDirname;

    /**
     * Before test.
     *
     * @noinspection PhpMissingParentCallCommonInspection
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

        if ($this->outputFilename !== null && file_exists($this->outputFilename)) {
            unlink($this->outputFilename);
        }

        if ($this->outputDirname !== null && is_dir($this->outputDirname)) {
            FilesUtil::removeDir($this->outputDirname);
        }
    }

    /**
     * Assert correct zip archive.
     *
     * @param string      $filename
     * @param string|null $password
     */
    public static function assertCorrectZipArchive($filename, $password = null)
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

    /**
     * @param string      $filename
     * @param string|null $password
     */
    private static function assertCorrectZipArchiveFrom7z($filename, $password = null)
    {
        $command = '7z t';

        if ($password !== null) {
            $command .= ' -p' . escapeshellarg($password);
        }
        $command .= ' ' . escapeshellarg($filename) . ' 2>&1';

        exec($command, $output, $returnCode);
        $output = implode(\PHP_EOL, $output);

        static::assertSame($returnCode, 0);
        static::assertNotContains(' Errors', $output);
        static::assertContains(' Ok', $output);
    }

    /**
     * @param string      $filename
     * @param string|null $password
     */
    private static function assertCorrectZipArchiveFromUnzip($filename, $password = null)
    {
        $command = 'unzip';

        if ($password !== null) {
            $command .= ' -P ' . escapeshellarg($password);
        }
        $command .= ' -t ' . escapeshellarg($filename) . ' 2>&1';

        exec($command, $output, $returnCode);
        $output = implode(\PHP_EOL, $output);

        if ($password !== null && $returnCode === 81) {
            fwrite(\STDERR, 'Program unzip cannot support this function.' . \PHP_EOL);
            fwrite(\STDERR, 'You have to install 7-zip to complete this test.' . \PHP_EOL);
            fwrite(\STDERR, 'Install 7-Zip on Ubuntu: sudo apt-get install p7zip-full' . \PHP_EOL);
            fwrite(\STDERR, 'Install 7-Zip on Windows: https://www.7-zip.org/download.html' . \PHP_EOL);

            return;
        }

        static::assertSame($returnCode, 0, $output);
        static::assertNotContains('incorrect password', $output);
        static::assertContains(' OK', $output);
        static::assertContains('No errors', $output);
    }

    /**
     * @param string $program
     * @param array  $successCodes
     *
     * @return bool
     */
    protected static function existsProgram($program, array $successCodes = [0])
    {
        $command = \DIRECTORY_SEPARATOR === '\\' ?
            escapeshellarg($program) :
            'which ' . escapeshellarg($program);
        $command .= ' 2>&1';

        exec($command, $output, $returnCode);

        return \in_array($returnCode, $successCodes, true);
    }

    /**
     * Assert correct empty zip archive.
     *
     * @param $filename
     */
    public static function assertCorrectEmptyZip($filename)
    {
        if (self::existsProgram('zipinfo')) {
            exec('zipinfo ' . escapeshellarg($filename), $output, $returnCode);

            $output = implode(\PHP_EOL, $output);

            static::assertContains('Empty zipfile', $output);
        }
        $actualEmptyZipData = pack('VVVVVv', ZipConstants::END_CD, 0, 0, 0, 0, 0);
        static::assertStringEqualsFile($filename, $actualEmptyZipData);
    }

    /**
     * @param string $filename
     * @param bool   $showErrors
     *
     * @return bool|null If null returned, then the zipalign program is not installed
     */
    public static function assertVerifyZipAlign($filename, $showErrors = false)
    {
        if (self::existsProgram('zipalign', [0, 2])) {
            exec('zipalign -c -v 4 ' . escapeshellarg($filename), $output, $returnCode);

            if ($showErrors && $returnCode !== 0) {
                fwrite(\STDERR, implode(\PHP_EOL, $output));
            }

            return $returnCode === 0;
        }

        fwrite(\STDERR, "Cannot find the program 'zipalign' for the test" . \PHP_EOL);
        fwrite(\STDERR, 'To fix this, install zipalign.' . \PHP_EOL);
        fwrite(\STDERR, \PHP_EOL);
        fwrite(\STDERR, 'Install on Ubuntu: sudo apt-get install zipalign' . \PHP_EOL);
        fwrite(\STDERR, \PHP_EOL);
        fwrite(\STDERR, 'Install on Windows:' . \PHP_EOL);
        fwrite(\STDERR, ' 1. Install Android Studio' . \PHP_EOL);
        fwrite(\STDERR, ' 2. Install Android Sdk' . \PHP_EOL);
        fwrite(\STDERR, ' 3. Add zipalign path to \$Path' . \PHP_EOL);

        return null;
    }

    /**
     * @return bool
     */
    public static function skipTestForRootUser()
    {
        /** @noinspection PhpComposerExtensionStubsInspection */
        if (\extension_loaded('posix') && posix_getuid() === 0) {
            static::markTestSkipped('Skip the test for a user with root privileges');

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public static function skipTestForWindows()
    {
        if (\DIRECTORY_SEPARATOR === '\\') {
            static::markTestSkipped('Skip on Windows');

            return true;
        }

        return false;
    }
}
