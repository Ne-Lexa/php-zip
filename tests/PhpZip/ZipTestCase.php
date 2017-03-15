<?php
namespace PhpZip;

use PhpZip\Model\EndOfCentralDirectory;
use PhpZip\Util\FilesUtil;

/**
 * PHPUnit test case and helper methods.
 */
class ZipTestCase extends \PHPUnit_Framework_TestCase
{

    /**
     * @var string
     */
    protected $outputFilename;
    /**
     * @var string
     */
    protected $outputDirname;

    /**
     * Before test
     */
    protected function setUp()
    {
        parent::setUp();

        $id = uniqid('phpzip');
        $this->outputFilename = sys_get_temp_dir() . '/' . $id . '.zip';
        $this->outputDirname = sys_get_temp_dir() . '/' . $id;
    }

    /**
     * After test
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
     * @param string $filename
     * @param string|null $password
     */
    public static function assertCorrectZipArchive($filename, $password = null)
    {
        if (DIRECTORY_SEPARATOR !== '\\' && `which unzip`) {
            $command = "unzip";
            if ($password !== null) {
                $command .= " -P " . escapeshellarg($password);
            }
            $command .= " -t " . escapeshellarg($filename);
            exec($command, $output, $returnCode);

            $output = implode(PHP_EOL, $output);

            if ($password !== null && $returnCode === 81) {
                if (`which 7z`) {
                    // WinZip 99-character limit
                    // @see https://sourceforge.net/p/p7zip/discussion/383044/thread/c859a2f0/
                    $password = substr($password, 0, 99);

                    $command = "7z t -p" . escapeshellarg($password) . " " . escapeshellarg($filename);
                    exec($command, $output, $returnCode);

                    $output = implode(PHP_EOL, $output);

                    self::assertEquals($returnCode, 0);
                    self::assertNotContains(' Errors', $output);
                    self::assertContains(' Ok', $output);
                } else {
                    fwrite(STDERR, 'Program unzip cannot support this function.' . PHP_EOL);
                    fwrite(STDERR, 'Please install 7z. For Ubuntu-like: sudo apt-get install p7zip-full' . PHP_EOL);
                }
            } else {
                self::assertEquals($returnCode, 0, $output);
                self::assertNotContains('incorrect password', $output);
                self::assertContains(' OK', $output);
                self::assertContains('No errors', $output);
            }
        }
    }

    /**
     * Assert correct empty zip archive.
     *
     * @param $filename
     */
    public static function assertCorrectEmptyZip($filename)
    {
        if (DIRECTORY_SEPARATOR !== '\\' && `which zipinfo`) {
            exec("zipinfo " . escapeshellarg($filename), $output, $returnCode);

            $output = implode(PHP_EOL, $output);

            self::assertContains('Empty zipfile', $output);
        }
        $actualEmptyZipData = pack('VVVVVv', EndOfCentralDirectory::END_OF_CENTRAL_DIRECTORY_RECORD_SIG, 0, 0, 0, 0, 0);
        self::assertEquals(file_get_contents($filename), $actualEmptyZipData);
    }

    /**
     * @param string $filename
     * @param bool $showErrors
     * @return bool|null If null - can not install zipalign
     */
    public static function doZipAlignVerify($filename, $showErrors = false)
    {
        if (DIRECTORY_SEPARATOR !== '\\' && `which zipalign`) {
            exec("zipalign -c -v 4 " . escapeshellarg($filename), $output, $returnCode);
            if ($showErrors && $returnCode !== 0) fwrite(STDERR, implode(PHP_EOL, $output));
            return $returnCode === 0;
        } else {
            fwrite(STDERR, 'Can not find program "zipalign" for test');
            return null;
        }
    }

}