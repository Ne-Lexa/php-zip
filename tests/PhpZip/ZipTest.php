<?php
namespace PhpZip;

use PhpZip\Exception\ZipAuthenticationException;
use PhpZip\Model\ZipEntry;
use PhpZip\Util\CryptoUtil;
use PhpZip\Util\FilesUtil;

/**
 * ZipFile and ZipOutputFile test
 */
class ZipTest extends ZipTestCase
{
    /**
     * @var string
     */
    private $outputFilename;

    /**
     * Before test
     */
    protected function setUp()
    {
        parent::setUp();

        $this->outputFilename = sys_get_temp_dir() . '/' . uniqid() . '.zip';
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
    }

    /**
     * Create empty archive
     *
     * @see ZipOutputFile::create()
     */
    public function testCreateEmptyArchive()
    {
        $zipFile = ZipOutputFile::create();
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        $zipFile = ZipFile::openFromFile($this->outputFilename);
        self::assertEquals(count($zipFile), 0);
        $zipFile->close();

        self::assertCorrectEmptyZip($this->outputFilename);
    }

    /**
     * Create archive and add files.
     *
     * @see ZipOutputFile::addFromString()
     * @see ZipOutputFile::addFromFile()
     * @see ZipOutputFile::addFromStream()
     * @see ZipFile::getEntryContent()
     */
    public function testCreateArchiveAndAddFiles()
    {
        $outputFromString = file_get_contents(__FILE__);
        $outputFromFile = file_get_contents(dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'bootstrap.xml');
        $outputFromStream = file_get_contents(dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'composer.json');

        $filenameFromString = basename(__FILE__);
        $filenameFromFile = 'data/test file.txt';
        $filenameFromStream = 'data/ডিরেক্টরি/αρχείο.json';
        $emptyDirName = 'empty dir/пустой каталог/空目錄/ไดเรกทอรีที่ว่างเปล่า/';

        $tempFile = tempnam(sys_get_temp_dir(), 'txt');
        file_put_contents($tempFile, $outputFromFile);

        $tempStream = tmpfile();
        fwrite($tempStream, $outputFromStream);

        $outputZipFile = ZipOutputFile::create();
        $outputZipFile->addFromString($filenameFromString, $outputFromString);
        $outputZipFile->addFromFile($tempFile, $filenameFromFile);
        $outputZipFile->addFromStream($tempStream, $filenameFromStream);
        $outputZipFile->addEmptyDir($emptyDirName);
        $outputZipFile->saveAsFile($this->outputFilename);
        $outputZipFile->close();
        unlink($tempFile);

        self::assertCorrectZipArchive($this->outputFilename);

        $zipFile = ZipFile::openFromFile($this->outputFilename);
        self::assertEquals(count($zipFile), 4);
        self::assertEquals($zipFile->getEntryContent($filenameFromString), $outputFromString);
        self::assertEquals($zipFile->getEntryContent($filenameFromFile), $outputFromFile);
        self::assertEquals($zipFile->getEntryContent($filenameFromStream), $outputFromStream);
        self::assertTrue($zipFile->hasEntry($emptyDirName));
        self::assertTrue($zipFile->isDirectory($emptyDirName));

        $listFiles = $zipFile->getListFiles();
        self::assertEquals($listFiles[0], $filenameFromString);
        self::assertEquals($listFiles[1], $filenameFromFile);
        self::assertEquals($listFiles[2], $filenameFromStream);
        self::assertEquals($listFiles[3], $emptyDirName);

        $zipFile->close();
    }

    /**
     * Create archive and add directory recursively.
     */
    public function testAddDirRecursively()
    {
        $inputDir = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . "src";

        $outputZipFile = new ZipOutputFile();
        $outputZipFile->addDir($inputDir);
        $outputZipFile->saveAsFile($this->outputFilename);
        $outputZipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);
    }

    /**
     * Create archive and add directory not recursively.
     */
    public function testAddDirNotRecursively()
    {
        $inputDir = dirname(dirname(__DIR__));
        $recursive = false;

        $outputZipFile = new ZipOutputFile();
        $outputZipFile->addDir($inputDir, $recursive);
        $outputZipFile->saveAsFile($this->outputFilename);
        $outputZipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);
    }

    /**
     * Create archive and add directory and put files to path.
     */
    public function testAddDirAndMoveToPath()
    {
        $inputDir = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . "src";

        $recursive = true;

        $outputZipFile = new ZipOutputFile();
        $moveToPath = 'Library/src';
        $outputZipFile->addDir($inputDir, $recursive, $moveToPath);
        $outputZipFile->saveAsFile($this->outputFilename);
        $outputZipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);
    }

    /**
     * Create archive and add directory with ignore files list.
     */
    public function testAddDirAndIgnoreFiles()
    {
        $inputDir = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR;

        $recursive = false;

        $outputZipFile = new ZipOutputFile();
        $ignoreFiles = ['tests/', '.git/', 'composer.lock', 'vendor/', ".idea/"];
        $moveToPath = 'PhpZip Library';
        $outputZipFile->addDir($inputDir, $recursive, $moveToPath, $ignoreFiles);
        $outputZipFile->saveAsFile($this->outputFilename);
        $outputZipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);
    }

    /**
     * Create archive and add directory recursively with ignore files list.
     */
    public function testAddDirAndIgnoreFilesRecursively()
    {
        $inputDir = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR;

        $recursive = true;

        $outputZipFile = new ZipOutputFile();
        $ignoreFiles = ['tests/', '.git/', 'composer.lock', 'vendor/', ".idea/copyright/"];
        $moveToPath = 'PhpZip Library';
        $outputZipFile->addDir($inputDir, $recursive, $moveToPath, $ignoreFiles);
        $outputZipFile->saveAsFile($this->outputFilename);
        $outputZipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);
    }

    /**
     * Create archive and add files from glob pattern
     */
    public function testAddFilesFromGlob()
    {
        $inputDir = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR;
        $moveToPath = null;
        $recursive = false;

        $outputZipFile = new ZipOutputFile();
        $outputZipFile->addFilesFromGlob($inputDir, '**.{php,xml}', $moveToPath, $recursive);
        $outputZipFile->saveAsFile($this->outputFilename);
        $outputZipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);
    }

    /**
     * Create archive and add recursively files from glob pattern
     */
    public function testAddFilesFromGlobRecursive()
    {
        $inputDir = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR;
        $moveToPath = "PhpZip Library";
        $recursive = true;

        $outputZipFile = new ZipOutputFile();
        $outputZipFile->addFilesFromGlob($inputDir, '**.{php,xml}', $recursive, $moveToPath);
        $outputZipFile->saveAsFile($this->outputFilename);
        $outputZipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);
    }

    /**
     * Create archive and add files from regex pattern
     */
    public function testAddFilesFromRegex()
    {
        $inputDir = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR;
        $moveToPath = "Test";
        $recursive = false;

        $outputZipFile = new ZipOutputFile();
        $outputZipFile->addFilesFromRegex($inputDir, '~\.(xml|php)$~i', $recursive, $moveToPath);
        $outputZipFile->saveAsFile($this->outputFilename);
        $outputZipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);
    }

    /**
     * Create archive and add files recursively from regex pattern
     */
    public function testAddFilesFromRegexRecursive()
    {
        $inputDir = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR;
        $moveToPath = "Test";
        $recursive = true;

        $outputZipFile = new ZipOutputFile();
        $outputZipFile->addFilesFromRegex($inputDir, '~\.(xml|php)$~i', $recursive, $moveToPath);
        $outputZipFile->saveAsFile($this->outputFilename);
        $outputZipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);
    }

    /**
     * Rename zip entry name.
     */
    public function testRename()
    {
        $oldName = basename(__FILE__);
        $newName = 'tests/' . $oldName;

        $outputZipFile = new ZipOutputFile();
        $outputZipFile->addDir(__DIR__);
        $outputZipFile->saveAsFile($this->outputFilename);
        $outputZipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $zipFile = ZipFile::openFromFile($this->outputFilename);
        $outputZipFile = new ZipOutputFile($zipFile);
        $outputZipFile->rename($oldName, $newName);
        $outputZipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $zipFile = ZipFile::openFromFile($this->outputFilename);
        self::assertFalse($zipFile->hasEntry($oldName));
        self::assertTrue($zipFile->hasEntry($newName));
        $zipFile->close();
    }

    /**
     * Delete entry from name.
     */
    public function testDeleteFromName()
    {
        $inputDir = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR;
        $deleteEntryName = 'composer.json';

        $outputZipFile = new ZipOutputFile();
        $outputZipFile->addDir($inputDir, false);
        $outputZipFile->saveAsFile($this->outputFilename);
        $outputZipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $zipFile = ZipFile::openFromFile($this->outputFilename);
        $outputZipFile = new ZipOutputFile($zipFile);
        $outputZipFile->deleteFromName($deleteEntryName);
        $outputZipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $zipFile = ZipFile::openFromFile($this->outputFilename);
        self::assertFalse($zipFile->hasEntry($deleteEntryName));
        $zipFile->close();
    }

    /**
     * Delete zip entries from glob pattern
     */
    public function testDeleteFromGlob()
    {
        $inputDir = dirname(dirname(__DIR__));

        $outputZipFile = new ZipOutputFile();
        $outputZipFile->addFilesFromGlob($inputDir, '**.{php,xml,json}', true);
        $outputZipFile->saveAsFile($this->outputFilename);
        $outputZipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $zipFile = ZipFile::openFromFile($this->outputFilename);
        $outputZipFile = new ZipOutputFile($zipFile);
        $outputZipFile->deleteFromGlob('**.{xml,json}');
        $outputZipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $zipFile = ZipFile::openFromFile($this->outputFilename);
        self::assertFalse($zipFile->hasEntry('composer.json'));
        self::assertFalse($zipFile->hasEntry('bootstrap.xml'));
        $zipFile->close();
    }

    /**
     * Delete entries from regex pattern
     */
    public function testDeleteFromRegex()
    {
        $inputDir = dirname(dirname(__DIR__));

        $outputZipFile = new ZipOutputFile();
        $outputZipFile->addFilesFromRegex($inputDir, '~\.(xml|php|json)$~i', true, 'Path');
        $outputZipFile->saveAsFile($this->outputFilename);
        $outputZipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $zipFile = ZipFile::openFromFile($this->outputFilename);
        $outputZipFile = new ZipOutputFile($zipFile);
        $outputZipFile->deleteFromRegex('~\.(json)$~i');
        $outputZipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $zipFile = ZipFile::openFromFile($this->outputFilename);
        self::assertFalse($zipFile->hasEntry('Path/composer.json'));
        self::assertTrue($zipFile->hasEntry('Path/bootstrap.xml'));
        $zipFile->close();
    }

    /**
     * Delete all entries
     */
    public function testDeleteAll()
    {
        $outputZipFile = new ZipOutputFile();
        $outputZipFile->addDir(__DIR__);
        $outputZipFile->saveAsFile($this->outputFilename);
        $outputZipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $zipFile = ZipFile::openFromFile($this->outputFilename);
        self::assertTrue($zipFile->count() > 0);

        $outputZipFile = new ZipOutputFile($zipFile);
        $outputZipFile->deleteAll();
        $outputZipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectEmptyZip($this->outputFilename);

        $zipFile = ZipFile::openFromFile($this->outputFilename);
        self::assertEquals($zipFile->count(), 0);
        $zipFile->close();
    }

    /**
     * Test zip archive comment.
     */
    public function testArchiveComment()
    {
        $comment = "This zip file comment" . PHP_EOL
            . "Αυτό το σχόλιο αρχείο zip" . PHP_EOL
            . "Это комментарий zip архива" . PHP_EOL
            . "這個ZIP文件註釋" . PHP_EOL
            . "ეს zip ფაილის კომენტარი" . PHP_EOL
            . "このzipファイルにコメント" . PHP_EOL
            . "ความคิดเห็นนี้ไฟล์ซิป";

        $outputZipFile = new ZipOutputFile();
        $outputZipFile->setComment($comment);
        $outputZipFile->addFromFile(__FILE__);
        $outputZipFile->saveAsFile($this->outputFilename);
        $outputZipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $zipFile = ZipFile::openFromFile($this->outputFilename);
        self::assertEquals($zipFile->getComment(), $comment);
        // remove comment
        $outputZipFile = ZipOutputFile::openFromZipFile($zipFile);
        $outputZipFile->setComment(null);
        $outputZipFile->saveAsFile($this->outputFilename);
        $outputZipFile->close();
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        // check empty comment
        $zipFile = ZipFile::openFromFile($this->outputFilename);
        self::assertEquals($zipFile->getComment(), "");
        $zipFile->close();
    }

    /**
     * Test very long archive comment.
     *
     * @expectedException \PhpZip\Exception\IllegalArgumentException
     */
    public function testVeryLongArchiveComment()
    {
        $comment = "Very long comment" . PHP_EOL .
            "Очень длинный комментарий" . PHP_EOL;
        $comment = str_repeat($comment, ceil(0xffff / strlen($comment)) + strlen($comment) + 1);

        $outputZipFile = new ZipOutputFile();
        $outputZipFile->setComment($comment);
    }

    /**
     * Test zip entry comment.
     */
    public function testEntryComment()
    {
        $entries = [
            '文件1.txt' => [
                'data' => CryptoUtil::randomBytes(255),
                'comment' => "這是註釋的條目。",
            ],
            'file2.txt' => [
                'data' => CryptoUtil::randomBytes(255),
                'comment' => null
            ],
            'file3.txt' => [
                'data' => CryptoUtil::randomBytes(255),
                'comment' => CryptoUtil::randomBytes(255),
            ],
            'file4.txt' => [
                'data' => CryptoUtil::randomBytes(255),
                'comment' => "Комментарий файла"
            ],
            'file5.txt' => [
                'data' => CryptoUtil::randomBytes(255),
                'comment' => "ไฟล์แสดงความคิดเห็น"
            ],
            'file6 emoji 🙍🏼.txt' => [
                'data' => CryptoUtil::randomBytes(255),
                'comment' => "Emoji comment file - 😀 ⛈ ❤️ 🤴🏽"
            ],
        ];

        $outputZipFile = new ZipOutputFile();
        foreach ($entries as $entryName => $item) {
            $outputZipFile->addFromString($entryName, $item['data']);
            $outputZipFile->setEntryComment($entryName, $item['comment']);
        }
        $outputZipFile->saveAsFile($this->outputFilename);
        $outputZipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $zipFile = ZipFile::openFromFile($this->outputFilename);
        foreach ($zipFile->getListFiles() as $entryName) {
            $entriesItem = $entries[$entryName];
            self::assertNotEmpty($entriesItem);
            self::assertEquals($zipFile->getEntryContent($entryName), $entriesItem['data']);
            self::assertEquals($zipFile->getEntryComment($entryName), (string)$entriesItem['comment']);
        }
        $zipFile->close();
    }

    /**
     * Test zip entry very long comment.
     *
     * @expectedException \PhpZip\Exception\ZipException
     */
    public function testVeryLongEntryComment()
    {
        $comment = "Very long comment" . PHP_EOL .
            "Очень длинный комментарий" . PHP_EOL;
        $comment = str_repeat($comment, ceil(0xffff / strlen($comment)) + strlen($comment) + 1);

        $outputZipFile = new ZipOutputFile();
        $outputZipFile->addFromFile(__FILE__, 'test');
        $outputZipFile->setEntryComment('test', $comment);
    }

    /**
     * Test set illegal compression method.
     *
     * @expectedException \PhpZip\Exception\IllegalArgumentException
     */
    public function testIllegalCompressionMethod()
    {
        $outputZipFile = new ZipOutputFile();
        $outputZipFile->addFromFile(__FILE__, null, ZipEntry::WINZIP_AES);
    }

    /**
     * Test all available support compression methods.
     */
    public function testCompressionMethod()
    {
        $entries = [
            '1' => [
                'data' => CryptoUtil::randomBytes(255),
                'method' => ZipEntry::METHOD_STORED,
            ],
            '2' => [
                'data' => CryptoUtil::randomBytes(255),
                'method' => ZipEntry::METHOD_DEFLATED,
            ],
        ];
        if (extension_loaded("bz2")) {
            $entries['3'] = [
                'data' => CryptoUtil::randomBytes(255),
                'method' => ZipEntry::METHOD_BZIP2,
            ];
        }

        $outputZipFile = new ZipOutputFile();
        foreach ($entries as $entryName => $item) {
            $outputZipFile->addFromString($entryName, $item['data'], $item['method']);
        }
        $outputZipFile->saveAsFile($this->outputFilename);
        $outputZipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $zipFile = ZipFile::openFromFile($this->outputFilename);
        $outputZipFile = ZipOutputFile::openFromZipFile($zipFile);
        $outputZipFile->setLevel(ZipOutputFile::LEVEL_BEST_COMPRESSION);
        foreach ($zipFile->getRawEntries() as $entry) {
            self::assertEquals($zipFile->getEntryContent($entry->getName()), $entries[$entry->getName()]['data']);
            self::assertEquals($entry->getMethod(), $entries[$entry->getName()]['method']);

            switch ($entry->getMethod()) {
                case ZipEntry::METHOD_STORED:
                    $entries[$entry->getName()]['method'] = ZipEntry::METHOD_DEFLATED;
                    $outputZipFile->setCompressionMethod($entry->getName(), ZipEntry::METHOD_DEFLATED);
                    break;

                case ZipEntry::METHOD_DEFLATED:
                    $entries[$entry->getName()]['method'] = ZipEntry::METHOD_STORED;
                    $outputZipFile->setCompressionMethod($entry->getName(), ZipEntry::METHOD_STORED);
                    break;
            }
        }
        $outputZipFile->saveAsFile($this->outputFilename);
        $outputZipFile->close();
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $zipFile = ZipFile::openFromFile($this->outputFilename);
        foreach ($zipFile->getRawEntries() as $entry) {
            $actualEntry = $entries[$entry->getName()];

            self::assertEquals($zipFile->getEntryContent($entry->getName()), $actualEntry['data']);
            self::assertEquals($entry->getMethod(), $actualEntry['method']);
        }
        $zipFile->close();
    }

    /**
     * Test extract all files.
     */
    public function testExtract()
    {
        $entries = [
            'test1.txt' => CryptoUtil::randomBytes(255),
            'test2.txt' => CryptoUtil::randomBytes(255),
            'test/test 2/test3.txt' => CryptoUtil::randomBytes(255),
            'test empty/dir' => null,
        ];

        $outputFolderInput = sys_get_temp_dir() . '/zipExtract' . uniqid();
        if (!is_dir($outputFolderInput)) {
            mkdir($outputFolderInput, 0755, true);
        }
        $outputFolderOutput = sys_get_temp_dir() . '/zipExtract' . uniqid();
        if (!is_dir($outputFolderOutput)) {
            mkdir($outputFolderOutput, 0755, true);
        }

        $outputZipFile = new ZipOutputFile();
        foreach ($entries as $entryName => $value) {
            if ($value === null) {
                $outputZipFile->addEmptyDir($entryName);
            } else {
                $outputZipFile->addFromString($entryName, $value);
            }
        }
        $outputZipFile->saveAsFile($this->outputFilename);
        $outputZipFile->close();

        $zipFile = ZipFile::openFromFile($this->outputFilename);
        $zipFile->extractTo($outputFolderInput);

        $outputZipFile = new ZipOutputFile($zipFile);
        $outputZipFile->extractTo($outputFolderOutput);
        foreach ($entries as $entryName => $value) {
            $fullInputFilename = $outputFolderInput . DIRECTORY_SEPARATOR . $entryName;
            $fullOutputFilename = $outputFolderOutput . DIRECTORY_SEPARATOR . $entryName;
            if ($value === null) {
                self::assertTrue(is_dir($fullInputFilename));
                self::assertTrue(is_dir($fullOutputFilename));

                self::assertTrue(FilesUtil::isEmptyDir($fullInputFilename));
                self::assertTrue(FilesUtil::isEmptyDir($fullOutputFilename));
            } else {
                self::assertTrue(is_file($fullInputFilename));
                self::assertTrue(is_file($fullOutputFilename));

                $contentInput = file_get_contents($fullInputFilename);
                $contentOutput = file_get_contents($fullOutputFilename);
                self::assertEquals($contentInput, $value);
                self::assertEquals($contentOutput, $value);
                self::assertEquals($contentInput, $contentOutput);
            }
        }
        $outputZipFile->close();
        $zipFile->close();

        FilesUtil::removeDir($outputFolderInput);
        FilesUtil::removeDir($outputFolderOutput);
    }

    /**
     * Test extract some files
     */
    public function testExtractSomeFiles()
    {
        $entries = [
            'test1.txt' => CryptoUtil::randomBytes(255),
            'test2.txt' => CryptoUtil::randomBytes(255),
            'test3.txt' => CryptoUtil::randomBytes(255),
            'test4.txt' => CryptoUtil::randomBytes(255),
            'test5.txt' => CryptoUtil::randomBytes(255),
            'test/test/test.txt' => CryptoUtil::randomBytes(255),
            'test/test/test 2.txt' => CryptoUtil::randomBytes(255),
            'test empty/dir/' => null,
            'test empty/dir2/' => null,
        ];

        $extractEntries = ['test1.txt', 'test3.txt', 'test5.txt', 'test/test/test 2.txt', 'test empty/dir2/'];

        $outputFolderInput = sys_get_temp_dir() . '/zipExtract' . uniqid();
        if (!is_dir($outputFolderInput)) {
            mkdir($outputFolderInput, 0755, true);
        }
        $outputFolderOutput = sys_get_temp_dir() . '/zipExtract' . uniqid();
        if (!is_dir($outputFolderOutput)) {
            mkdir($outputFolderOutput, 0755, true);
        }

        $outputZipFile = new ZipOutputFile();
        foreach ($entries as $entryName => $value) {
            if ($value === null) {
                $outputZipFile->addEmptyDir($entryName);
            } else {
                $outputZipFile->addFromString($entryName, $value);
            }
        }
        $outputZipFile->saveAsFile($this->outputFilename);
        $outputZipFile->close();

        $zipFile = ZipFile::openFromFile($this->outputFilename);
        $zipFile->extractTo($outputFolderInput, $extractEntries);

        $outputZipFile = new ZipOutputFile($zipFile);
        $outputZipFile->extractTo($outputFolderOutput, $extractEntries);
        foreach ($entries as $entryName => $value) {
            $fullInputFilename = $outputFolderInput . DIRECTORY_SEPARATOR . $entryName;
            $fullOutputFilename = $outputFolderOutput . DIRECTORY_SEPARATOR . $entryName;
            if (in_array($entryName, $extractEntries)) {
                if ($value === null) {
                    self::assertTrue(is_dir($fullInputFilename));
                    self::assertTrue(is_dir($fullOutputFilename));

                    self::assertTrue(FilesUtil::isEmptyDir($fullInputFilename));
                    self::assertTrue(FilesUtil::isEmptyDir($fullOutputFilename));
                } else {
                    self::assertTrue(is_file($fullInputFilename));
                    self::assertTrue(is_file($fullOutputFilename));

                    $contentInput = file_get_contents($fullInputFilename);
                    $contentOutput = file_get_contents($fullOutputFilename);
                    self::assertEquals($contentInput, $value);
                    self::assertEquals($contentOutput, $value);
                    self::assertEquals($contentInput, $contentOutput);
                }
            } else {
                if ($value === null) {
                    self::assertFalse(is_dir($fullInputFilename));
                    self::assertFalse(is_dir($fullOutputFilename));
                } else {
                    self::assertFalse(is_file($fullInputFilename));
                    self::assertFalse(is_file($fullOutputFilename));
                }
            }
        }
        $outputZipFile->close();
        $zipFile->close();

        FilesUtil::removeDir($outputFolderInput);
        FilesUtil::removeDir($outputFolderOutput);
    }

    /**
     * Test archive password.
     */
    public function testSetPassword()
    {
        $password = CryptoUtil::randomBytes(100);
        $badPassword = "sdgt43r23wefe";

        $outputZip = ZipOutputFile::create();
        $outputZip->addDir(__DIR__);
        $outputZip->setPassword($password, ZipEntry::ENCRYPTION_METHOD_TRADITIONAL);
        $outputZip->saveAsFile($this->outputFilename);
        $outputZip->close();

        $zipFile = ZipFile::openFromFile($this->outputFilename);

        // set bad password Traditional Encryption
        $zipFile->setPassword($badPassword);
        foreach ($zipFile->getListFiles() as $entryName) {
            try {
                $zipFile->getEntryContent($entryName);
                self::fail("Expected Exception has not been raised.");
            } catch (ZipAuthenticationException $ae) {
                self::assertNotNull($ae);
            }
        }

        // set correct password
        $zipFile->setPassword($password);
        foreach ($zipFile->getAllInfo() as $info) {
            self::assertTrue($info->isEncrypted());
            self::assertContains('ZipCrypto', $info->getMethod());
            $decryptContent = $zipFile->getEntryContent($info->getPath());
            self::assertNotEmpty($decryptContent);
            self::assertContains('<?php', $decryptContent);
        }

        // change encryption method
        $outputZip = ZipOutputFile::openFromZipFile($zipFile);
        $outputZip->setPassword($password, ZipEntry::ENCRYPTION_METHOD_WINZIP_AES);
        $outputZip->saveAsFile($this->outputFilename);
        $outputZip->close();
        $zipFile->close();

        // check from WinZip AES encryption
        $zipFile = ZipFile::openFromFile($this->outputFilename);

        // set bad password WinZip AES
        $zipFile->setPassword($badPassword);
        foreach ($zipFile->getListFiles() as $entryName) {
            try {
                $zipFile->getEntryContent($entryName);
                self::fail("Expected Exception has not been raised.");
            } catch (ZipAuthenticationException $ae) {
                self::assertNotNull($ae);
            }
        }

        // set correct password WinZip AES
        $zipFile->setPassword($password);
        foreach ($zipFile->getAllInfo() as $info) {
            self::assertTrue($info->isEncrypted());
            self::assertContains('WinZip', $info->getMethod());
            $decryptContent = $zipFile->getEntryContent($info->getPath());
            self::assertNotEmpty($decryptContent);
            self::assertContains('<?php', $decryptContent);
        }

        // clear password
        $outputZip = ZipOutputFile::openFromZipFile($zipFile);
        $outputZip->removePasswordAllEntries();
        $outputZip->saveAsFile($this->outputFilename);
        $outputZip->close();
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        // check remove password
        $zipFile = ZipFile::openFromFile($this->outputFilename);
        foreach ($zipFile->getAllInfo() as $info) {
            self::assertFalse($info->isEncrypted());
        }
        $zipFile->close();
    }

    /**
     * Test set password to some entries.
     */
    public function testSetPasswordToSomeEntries()
    {
        $entries = [
            'Traditional PKWARE Encryption Test.dat' => [
                'data' => CryptoUtil::randomBytes(255),
                'password' => CryptoUtil::randomBytes(255),
                'encryption_method' => ZipEntry::ENCRYPTION_METHOD_TRADITIONAL,
                'compression_method' => ZipEntry::METHOD_DEFLATED,
            ],
            'WinZip AES Encryption Test.dat' => [
                'data' => CryptoUtil::randomBytes(255),
                'password' => CryptoUtil::randomBytes(255),
                'encryption_method' => ZipEntry::ENCRYPTION_METHOD_WINZIP_AES,
                'compression_method' => ZipEntry::METHOD_BZIP2,
            ],
            'Not password.dat' => [
                'data' => CryptoUtil::randomBytes(255),
                'password' => null,
                'encryption_method' => ZipEntry::ENCRYPTION_METHOD_TRADITIONAL,
                'compression_method' => ZipEntry::METHOD_STORED,
            ],
        ];

        $outputZip = ZipOutputFile::create();
        foreach ($entries as $entryName => $item) {
            $outputZip->addFromString($entryName, $item['data'], $item['compression_method']);
            if ($item['password'] !== null) {
                $outputZip->setEntryPassword($entryName, $item['password'], $item['encryption_method']);
            }
        }
        $outputZip->saveAsFile($this->outputFilename);
        $outputZip->close();

        $outputDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'zipextract' . uniqid();
        if (!is_dir($outputDir)) {
            self::assertTrue(mkdir($outputDir, 0755, true));
        }

        $zipFile = ZipFile::openFromFile($this->outputFilename);
        foreach ($entries as $entryName => $item) {
            if ($item['password'] !== null) {
                $zipFile->setEntryPassword($entryName, $item['password']);
            }
        }
        $zipFile->extractTo($outputDir);
        $zipFile->close();

        self::assertFalse(FilesUtil::isEmptyDir($outputDir));

        foreach ($entries as $entryName => $item) {
            self::assertEquals(file_get_contents($outputDir . DIRECTORY_SEPARATOR . $entryName), $item['data']);
        }

        FilesUtil::removeDir($outputDir);
    }

    /**
     * Test `ZipFile` implemented \ArrayAccess, \Countable and |iterator.
     */
    public function testZipFileArrayAccessAndCountableAndIterator()
    {
        $files = [];
        $numFiles = mt_rand(20, 100);
        for ($i = 0; $i < $numFiles; $i++) {
            $files['file' . $i . '.txt'] = CryptoUtil::randomBytes(255);
        }

        $methods = [ZipEntry::METHOD_STORED, ZipEntry::METHOD_DEFLATED];
        if (extension_loaded("bz2")) {
            $methods[] = ZipEntry::METHOD_BZIP2;
        }

        $zipOutputFile = ZipOutputFile::create();
        $zipOutputFile->setLevel(ZipOutputFile::LEVEL_BEST_SPEED);
        foreach ($files as $entryName => $content) {
            $zipOutputFile->addFromString($entryName, $content, $methods[array_rand($methods)]);
        }
        $zipOutputFile->saveAsFile($this->outputFilename);
        $zipOutputFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $zipFile = ZipFile::openFromFile($this->outputFilename);

        // Test \Countable
        self::assertEquals($zipFile->count(), $numFiles);
        self::assertEquals(count($zipFile), $numFiles);

        // Test \ArrayAccess
        reset($files);
        foreach ($zipFile as $entryName => $content) {
            self::assertEquals($entryName, key($files));
            self::assertEquals($content, current($files));
            next($files);
        }

        // Test \Iterator
        reset($files);
        $iterator = new \ArrayIterator($zipFile);
        $iterator->rewind();
        while ($iterator->valid()) {
            $key = $iterator->key();
            $value = $iterator->current();

            self::assertEquals($key, key($files));
            self::assertEquals($value, current($files));

            next($files);
            $iterator->next();
        }
        $zipFile->close();
    }

    /**
     * Test `ZipOutputFile` implemented \ArrayAccess, \Countable and |iterator.
     */
    public function testZipOutputFileArrayAccessAndCountableAndIterator()
    {
        $files = [];
        $numFiles = mt_rand(20, 100);
        for ($i = 0; $i < $numFiles; $i++) {
            $files['file' . $i . '.txt'] = CryptoUtil::randomBytes(255);
        }

        $methods = [ZipEntry::METHOD_STORED, ZipEntry::METHOD_DEFLATED];
        if (extension_loaded("bz2")) {
            $methods[] = ZipEntry::METHOD_BZIP2;
        }

        $zipOutputFile = ZipOutputFile::create();
        $zipOutputFile->setLevel(ZipOutputFile::LEVEL_BEST_SPEED);
        foreach ($files as $entryName => $content) {
            $zipOutputFile->addFromString($entryName, $content, $methods[array_rand($methods)]);
        }
        $zipOutputFile->saveAsFile($this->outputFilename);
        $zipOutputFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $zipFile = ZipFile::openFromFile($this->outputFilename);
        $zipOutputFile = ZipOutputFile::openFromZipFile($zipFile);

        // Test \Countable
        self::assertEquals($zipOutputFile->count(), $numFiles);
        self::assertEquals(count($zipOutputFile), $numFiles);

        // Test \ArrayAccess
        reset($files);
        foreach ($zipOutputFile as $entryName => $content) {
            self::assertEquals($entryName, key($files));
            self::assertEquals($content, current($files));
            next($files);
        }

        // Test \Iterator
        reset($files);
        $iterator = new \ArrayIterator($zipOutputFile);
        $iterator->rewind();
        while ($iterator->valid()) {
            $key = $iterator->key();
            $value = $iterator->current();

            self::assertEquals($key, key($files));
            self::assertEquals($value, current($files));

            next($files);
            $iterator->next();
        }

        // Test set and unset
        $zipOutputFile['new entry name'] = 'content';
        unset($zipOutputFile['file0.txt'], $zipOutputFile['file1.txt'], $zipOutputFile['file2.txt']);
        $zipOutputFile->saveAsFile($this->outputFilename);
        $zipOutputFile->close();
        $zipFile->close();

        $zipFile = ZipFile::openFromFile($this->outputFilename);
        self::assertEquals($numFiles + 1 - 3, sizeof($zipFile));
        self::assertTrue(isset($zipFile['new entry name']));
        self::assertEquals($zipFile['new entry name'], 'content');
        self::assertFalse(isset($zipFile['file0.txt']));
        self::assertFalse(isset($zipFile['file1.txt']));
        self::assertFalse(isset($zipFile['file2.txt']));
        self::assertTrue(isset($zipFile['file3.txt']));
        $zipFile->close();
    }

    /**
     * Test support ZIP64 ext (slow test - normal).
     */
    public function testCreateAndOpenZip64Ext()
    {
        $countFiles = 0xffff + 1;

        $outputZipFile = ZipOutputFile::create();
        for ($i = 0; $i < $countFiles; $i++) {
            $outputZipFile->addFromString($i . '.txt', $i, ZipEntry::METHOD_STORED);
        }
        $outputZipFile->saveAsFile($this->outputFilename);
        $outputZipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $zipFile = ZipFile::openFromFile($this->outputFilename);
        self::assertEquals($zipFile->count(), $countFiles);
        foreach ($zipFile->getListFiles() as $entry) {
            $zipFile->getEntryContent($entry);
        }
        $zipFile->close();
    }

}