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
    protected function setUp()
    {
        $id = uniqid('phpzip', true);
        $tempDir = sys_get_temp_dir() . '/phpunit-phpzip';

        if (!is_dir($tempDir) && !mkdir($tempDir, 0755, true) && !is_dir($tempDir)) {
            throw new \RuntimeException('Dir ' . $tempDir . " can't created");
        }
        $this->outputFilename = $tempDir . '/' . $id . '.zip';
        $this->outputDirname = $tempDir . '/' . $id;
    }

    /**
     * After test.
     */
    protected function tearDown()
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
        if (self::existsProgram('unzip')) {
            $command = 'unzip';

            if ($password !== null) {
                $command .= ' -P ' . escapeshellarg($password);
            }
            $command .= ' -t ' . escapeshellarg($filename);
            $command .= ' 2>&1';
            exec($command, $output, $returnCode);

            $output = implode(\PHP_EOL, $output);

            if ($password !== null && $returnCode === 81) {
                if (self::existsProgram('7z')) {
                    /**
                     * WinZip 99-character limit.
                     *
                     * @see https://sourceforge.net/p/p7zip/discussion/383044/thread/c859a2f0/
                     */
                    $password = substr($password, 0, 99);

                    $command = '7z t -p' . escapeshellarg($password) . ' ' . escapeshellarg($filename);
                    exec($command, $output, $returnCode);
                    /**
                     * @var array $output
                     */
                    $output = implode(\PHP_EOL, $output);

                    static::assertSame($returnCode, 0);
                    static::assertNotContains(' Errors', $output);
                    static::assertContains(' Ok', $output);
                } else {
                    fwrite(\STDERR, 'Program unzip cannot support this function.' . \PHP_EOL);
                    fwrite(\STDERR, 'Please install 7z. For Ubuntu-like: sudo apt-get install p7zip-full' . \PHP_EOL);
                }
            } else {
                static::assertSame($returnCode, 0, $output);
                static::assertNotContains('incorrect password', $output);
                static::assertContains(' OK', $output);
                static::assertContains('No errors', $output);
            }
        }
    }

    /**
     * @param string $program
     *
     * @return bool
     */
    protected static function existsProgram($program)
    {
        if (\DIRECTORY_SEPARATOR !== '\\') {
            exec('which ' . escapeshellarg($program), $output, $returnCode);

            return $returnCode === 0;
        }
        // false for Windows
        return false;
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
        if (self::existsProgram('zipalign')) {
            exec('zipalign -c -v 4 ' . escapeshellarg($filename), $output, $returnCode);

            if ($showErrors && $returnCode !== 0) {
                fwrite(\STDERR, implode(\PHP_EOL, $output));
            }

            return $returnCode === 0;
        }

        fwrite(\STDERR, "Cannot find the program 'zipalign' for the test" . \PHP_EOL);

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
}
