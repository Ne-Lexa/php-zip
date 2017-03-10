<?php
namespace PhpZip;

use PhpZip\Exception\ZipAuthenticationException;
use PhpZip\Model\ZipEntry;
use PhpZip\Util\CryptoUtil;
use PhpZip\Util\FilesUtil;

/**
 * ZipFile test
 */
class ZipFileTest extends ZipTestCase
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
     * Test create, open and extract empty archive.
     */
    public function testEmptyArchive()
    {
        $zipFile = new ZipFile();
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectEmptyZip($this->outputFilename);

        $extractPath = sys_get_temp_dir() . '/extract-empty-dir';
        if (is_dir($extractPath)) {
            FilesUtil::removeDir($extractPath);
        }
        self::assertTrue(mkdir($extractPath, 0755, true));

        $zipFile->openFile($this->outputFilename);
        self::assertEquals(count($zipFile), 0);
        self::assertEquals($zipFile->count(), 0);
        $zipFile->extractTo($extractPath);
        $zipFile->close();

        self::assertTrue(FilesUtil::isEmptyDir($extractPath));
        FilesUtil::removeDir($extractPath);
    }

    /**
     * @expectedException \PhpZip\Exception\InvalidArgumentException
     * @expectedExceptionMessage can't exists
     */
    public function testCantExistsOpenFile()
    {
        $zipFile = new ZipFile();
        $zipFile->openFile(uniqid());
    }

    /**
     * @expectedException \PhpZip\Exception\ZipException
     * @expectedExceptionMessage can't open
     */
    public function testCantOpenFile()
    {
        self::assertNotFalse(file_put_contents($this->outputFilename, 'content'));
        self::assertTrue(chmod($this->outputFilename, 0222));

        $zipFile = new ZipFile();
        $zipFile->openFile($this->outputFilename);
    }

    /**
     * @expectedException \PhpZip\Exception\InvalidArgumentException
     * @expectedExceptionMessage Data not available
     */
    public function testOpenFromStringNull()
    {
        $zipFile = new ZipFile();
        $zipFile->openFromString(null);
    }

    /**
     * @expectedException \PhpZip\Exception\InvalidArgumentException
     * @expectedExceptionMessage Data not available
     */
    public function testOpenFromStringEmpty()
    {
        $zipFile = new ZipFile();
        $zipFile->openFromString("");
    }

    public function testOpenFromString()
    {
        $zipFile = new ZipFile();
        $zipFile->addFromString('file', 'content', ZipFile::METHOD_DEFLATED);
        $zipContents = $zipFile->outputAsString();
        $zipFile->close();

        $zipFile->openFromString($zipContents);
        self::assertTrue(isset($zipFile['file']));
        self::assertEquals($zipFile['file'], 'content');
        $zipFile->close();
    }

    public function testOpenFromStream()
    {
        $zipFile = new ZipFile();
        $zipFile->addFromString('file', 'content', ZipFile::METHOD_DEFLATED)
            ->saveAsFile($this->outputFilename);
        $zipFile->close();

        $handle = fopen($this->outputFilename, 'rb');
        $zipFile->openFromStream($handle);
        self::assertTrue(isset($zipFile['file']));
        self::assertEquals($zipFile['file'], 'content');
        $zipFile->close();
    }

    /**
     * @expectedException \PhpZip\Exception\InvalidArgumentException
     * @expectedExceptionMessage Invalid stream resource
     */
    public function testOpenFromInvalidStream()
    {
        $zipFile = new ZipFile();
        $zipFile->openFromStream("stream resource");
    }

    /**
     * @expectedException \PhpZip\Exception\InvalidArgumentException
     * @expectedExceptionMessage Resource cannot seekable stream.
     */
    public function testOpenFromStreamNoSeekable()
    {
        if (!$fp = @fopen("http://localhost", 'r')) {
            if (!$fp = @fopen("http://example.org", 'r')) {
                $this->markTestSkipped('not connected to localhost or remote host');
                return;
            }
        }

        $zipFile = new ZipFile();
        $zipFile->openFromStream($fp);
    }

    /**
     * No modified archive
     *
     * @see ZipOutputFile::create()
     */
    public function testNoModifiedArchive()
    {
        $zipFile = new ZipFile();
        $zipFile->addDir(__DIR__);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);
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
        $outputFromString2 = file_get_contents(dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'README.md');
        $outputFromFile = file_get_contents(dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'bootstrap.xml');
        $outputFromStream = file_get_contents(dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'composer.json');

        $filenameFromString = basename(__FILE__);
        $filenameFromString2 = 'test_file.txt';
        $filenameFromFile = 'data/test file.txt';
        $filenameFromStream = 'data/ডিরেক্টরি/αρχείο.json';
        $emptyDirName = 'empty dir/пустой каталог/空目錄/ไดเรกทอรีที่ว่างเปล่า/';
        $emptyDirName2 = 'empty dir/пустой каталог/';
        $emptyDirName3 = 'empty dir/пустой каталог/ещё один пустой каталог/';

        $tempFile = tempnam(sys_get_temp_dir(), 'txt');
        file_put_contents($tempFile, $outputFromFile);

        $tempStream = tmpfile();
        fwrite($tempStream, $outputFromStream);

        $zipFile = new ZipFile;
        $zipFile->addFromString($filenameFromString, $outputFromString);
        $zipFile->addFile($tempFile, $filenameFromFile);
        $zipFile->addFromStream($tempStream, $filenameFromStream);
        $zipFile->addEmptyDir($emptyDirName);
        $zipFile[$filenameFromString2] = $outputFromString2;
        $zipFile[$emptyDirName2] = null;
        $zipFile[$emptyDirName3] = 'this content ignoring';
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();
        unlink($tempFile);

        self::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        self::assertEquals(count($zipFile), 7);
        self::assertEquals($zipFile[$filenameFromString], $outputFromString);
        self::assertEquals($zipFile[$filenameFromFile], $outputFromFile);
        self::assertEquals($zipFile[$filenameFromStream], $outputFromStream);
        self::assertEquals($zipFile[$filenameFromString2], $outputFromString2);
        self::assertTrue(isset($zipFile[$emptyDirName]));
        self::assertTrue(isset($zipFile[$emptyDirName2]));
        self::assertTrue(isset($zipFile[$emptyDirName3]));
        self::assertTrue($zipFile->isDirectory($emptyDirName));
        self::assertTrue($zipFile->isDirectory($emptyDirName2));
        self::assertTrue($zipFile->isDirectory($emptyDirName3));

        $listFiles = $zipFile->getListFiles();
        self::assertEquals($listFiles[0], $filenameFromString);
        self::assertEquals($listFiles[1], $filenameFromFile);
        self::assertEquals($listFiles[2], $filenameFromStream);
        self::assertEquals($listFiles[3], $emptyDirName);
        self::assertEquals($listFiles[4], $filenameFromString2);
        self::assertEquals($listFiles[5], $emptyDirName2);
        self::assertEquals($listFiles[6], $emptyDirName3);

        $zipFile->close();
    }

    /**
     * Test compression method from image file.
     */
    public function testCompressionMethodFromImageMimeType()
    {
        if (!function_exists('mime_content_type')) {
            $this->markTestSkipped('Function mime_content_type not exists');
        }
        $outputFilename = $this->outputFilename;
        $this->outputFilename .= '.gif';
        self::assertNotFalse(
            file_put_contents(
                $this->outputFilename,
                base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICBAEAOw==')
            )
        );
        $basename = basename($this->outputFilename);

        $zipFile = new ZipFile();
        $zipFile->addFile($this->outputFilename, $basename);
        $zipFile->saveAsFile($outputFilename);
        unlink($this->outputFilename);
        $this->outputFilename = $outputFilename;

        $zipFile->openFile($this->outputFilename);
        $info = $zipFile->getEntryInfo($basename);
        self::assertEquals($info->getMethod(), 'No compression');
        $zipFile->close();
    }

    /**
     * Create archive and add directory recursively.
     */
    public function testAddDirRecursively()
    {
        $inputDir = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . "src";

        $zipFile = new ZipFile();
        $zipFile->addDir($inputDir);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);
    }

    /**
     * Create archive and add directory not recursively.
     */
    public function testAddDirNotRecursively()
    {
        $inputDir = dirname(dirname(__DIR__));
        $recursive = false;

        $zipFile = new ZipFile();
        $zipFile->addDir($inputDir, $recursive);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);
    }

    /**
     * Create archive and add directory and put files to path.
     */
    public function testAddDirAndMoveToPath()
    {
        $inputDir = __DIR__;
        $files = array_slice(scandir($inputDir), 2);

        $recursive = true;

        $zipFile = new ZipFile();
        $moveToPath = 'Library/tests';
        $zipFile->addDir($inputDir, $recursive, $moveToPath);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        self::assertEquals(count($zipFile), count($files));
        foreach ($files as $file) {
            self::assertTrue(isset($zipFile[$moveToPath . '/' . $file]));
            self::assertEquals($zipFile[$moveToPath . '/' . $file], file_get_contents($inputDir . '/' . $file));
        }
        $zipFile->close();
    }

    /**
     * Create archive and add directory with ignore files list.
     */
    public function testAddDirAndIgnoreFiles()
    {
        $inputDir = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR;

        $recursive = false;

        $zipFile = new ZipFile();
        $ignoreFiles = ['tests/', '.git/', 'composer.lock', 'vendor/', ".idea/"];
        $moveToPath = 'PhpZip Library';
        $zipFile->addDir($inputDir, $recursive, $moveToPath, $ignoreFiles);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);
    }

    /**
     * Create archive and add directory recursively with ignore files list.
     */
    public function testAddDirAndIgnoreFilesRecursively()
    {
        $inputDir = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR;

        $recursive = true;

        $zipFile = new ZipFile();
        $ignoreFiles = ['tests/', '.git/', 'composer.lock', 'vendor/', ".idea/copyright/"];
        $moveToPath = 'PhpZip Library';
        $zipFile->addDir($inputDir, $recursive, $moveToPath, $ignoreFiles);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

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

        $zipFile = new ZipFile();
        $zipFile->addFilesFromGlob($inputDir, '**.{php,xml}', $moveToPath, $recursive);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

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

        $zipFile = new ZipFile();
        $zipFile->addFilesFromGlob($inputDir, '**.{php,xml}', $moveToPath, $recursive);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

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

        $zipFile = new ZipFile();
        $zipFile->addFilesFromRegex($inputDir, '~\.(xml|php)$~i', $moveToPath, $recursive);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

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

        $zipFile = new ZipFile();
        $zipFile->addFilesFromRegex($inputDir, '~\.(xml|php)$~i', $recursive, $moveToPath);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);
    }

    /**
     * Rename zip entry name.
     */
    public function testRename()
    {
        $oldName = basename(__FILE__);
        $newName = 'tests/' . $oldName;

        $zipFile = new ZipFile();
        $zipFile->addDir(__DIR__);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        $zipFile->rename($oldName, $newName);
        $zipFile->addFromString('file1.txt', 'content');
        $zipFile->rename('file1.txt', 'file2.txt');
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        self::assertFalse(isset($zipFile[$oldName]));
        self::assertTrue(isset($zipFile[$newName]));
        self::assertFalse(isset($zipFile['file1.txt']));
        self::assertTrue(isset($zipFile['file2.txt']));
        $zipFile->close();
    }

    /**
     * Delete entry from name.
     */
    public function testDeleteFromName()
    {
        $inputDir = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR;
        $deleteEntryName = 'composer.json';

        $zipFile = new ZipFile();
        $zipFile->addDir($inputDir, false);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        $zipFile->deleteFromName($deleteEntryName);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        self::assertFalse(isset($zipFile[$deleteEntryName]));
        $zipFile->close();
    }

    /**
     * Delete zip entries from glob pattern
     */
    public function testDeleteFromGlob()
    {
        $inputDir = dirname(dirname(__DIR__));

        $zipFile = new ZipFile();
        $zipFile->addFilesFromGlob($inputDir, '**.{php,xml,json}', '/', true);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        $zipFile->deleteFromGlob('**.{xml,json}');
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        self::assertFalse(isset($zipFile['composer.json']));
        self::assertFalse(isset($zipFile['bootstrap.xml']));
        $zipFile->close();
    }

    /**
     * @expectedException \PhpZip\Exception\InvalidArgumentException
     * @expectedExceptionMessage Glob pattern is empty
     */
    public function testDeleteFromGlobFailNull(){
        $zipFile = new ZipFile();
        $zipFile->deleteFromGlob(null);
    }

    /**
     * @expectedException \PhpZip\Exception\InvalidArgumentException
     * @expectedExceptionMessage Glob pattern is empty
     */
    public function testDeleteFromGlobFailEmpty(){
        $zipFile = new ZipFile();
        $zipFile->deleteFromGlob('');
    }

    /**
     * Delete entries from regex pattern
     */
    public function testDeleteFromRegex()
    {
        $inputDir = dirname(dirname(__DIR__));

        $zipFile = new ZipFile();
        $zipFile->addFilesFromRegex($inputDir, '~\.(xml|php|json)$~i', 'Path', true);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        $zipFile->deleteFromRegex('~\.(json)$~i');
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        self::assertFalse(isset($zipFile['Path/composer.json']));
        self::assertTrue(isset($zipFile['Path/bootstrap.xml']));
        $zipFile->close();
    }

    /**
     * @expectedException \PhpZip\Exception\InvalidArgumentException
     * @expectedExceptionMessage Regex pattern is empty.
     */
    public function testDeleteFromRegexFailNull(){
        $zipFile = new ZipFile();
        $zipFile->deleteFromRegex(null);
    }

    /**
     * @expectedException \PhpZip\Exception\InvalidArgumentException
     * @expectedExceptionMessage Regex pattern is empty.
     */
    public function testDeleteFromRegexFailEmpty(){
        $zipFile = new ZipFile();
        $zipFile->deleteFromRegex('');
    }

    /**
     * Delete all entries
     */
    public function testDeleteAll()
    {
        $zipFile = new ZipFile();
        $zipFile->addDir(__DIR__);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        self::assertTrue($zipFile->count() > 0);
        $zipFile->deleteAll();
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectEmptyZip($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
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

        $zipFile = new ZipFile();
        $zipFile->setArchiveComment($comment);
        $zipFile->addFile(__FILE__);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        self::assertEquals($zipFile->getArchiveComment(), $comment);
        $zipFile->setArchiveComment(null); // remove archive comment
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        // check empty comment
        $zipFile->openFile($this->outputFilename);
        self::assertEquals($zipFile->getArchiveComment(), "");
        $zipFile->close();
    }

    /**
     * Test very long archive comment.
     *
     * @expectedException \PhpZip\Exception\InvalidArgumentException
     */
    public function testVeryLongArchiveComment()
    {
        $comment = "Very long comment" . PHP_EOL .
            "Очень длинный комментарий" . PHP_EOL;
        $comment = str_repeat($comment, ceil(0xffff / strlen($comment)) + strlen($comment) + 1);

        $zipFile = new ZipFile();
        $zipFile->setArchiveComment($comment);
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

        // create archive with entry comments
        $zipFile = new ZipFile();
        foreach ($entries as $entryName => $item) {
            $zipFile->addFromString($entryName, $item['data']);
            $zipFile->setEntryComment($entryName, $item['comment']);
        }
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        // check and modify comments
        $zipFile->openFile($this->outputFilename);
        foreach ($zipFile->getListFiles() as $entryName) {
            $entriesItem = $entries[$entryName];
            self::assertNotEmpty($entriesItem);
            self::assertEquals($zipFile[$entryName], $entriesItem['data']);
            self::assertEquals($zipFile->getEntryComment($entryName), (string)$entriesItem['comment']);
        }
        // modify comment
        $entries['file5.txt']['comment'] = mt_rand(1, 100000000);
        $zipFile->setEntryComment('file5.txt', $entries['file5.txt']['comment']);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        // check modify comments
        $zipFile->openFile($this->outputFilename);
        foreach ($entries as $entryName => $entriesItem) {
            self::assertTrue(isset($zipFile[$entryName]));
            self::assertEquals($zipFile->getEntryComment($entryName), (string)$entriesItem['comment']);
            self::assertEquals($zipFile[$entryName], $entriesItem['data']);
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

        $zipFile = new ZipFile();
        $zipFile->addFile(__FILE__, 'test');
        $zipFile->setEntryComment('test', $comment);
    }

    /**
     * Test all available support compression methods.
     */
    public function testCompressionMethod()
    {
        $entries = [
            '1' => [
                'data' => CryptoUtil::randomBytes(255),
                'method' => ZipFile::METHOD_STORED,
                'expected' => 'No compression',
            ],
            '2' => [
                'data' => CryptoUtil::randomBytes(255),
                'method' => ZipFile::METHOD_DEFLATED,
                'expected' => 'Deflate',
            ],
        ];
        if (extension_loaded("bz2")) {
            $entries['3'] = [
                'data' => CryptoUtil::randomBytes(255),
                'method' => ZipFile::METHOD_BZIP2,
                'expected' => 'Bzip2',
            ];
        }

        $zipFile = new ZipFile();
        foreach ($entries as $entryName => $item) {
            $zipFile->addFromString($entryName, $item['data'], $item['method']);
        }
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        $zipFile->setCompressionLevel(ZipFile::LEVEL_BEST_COMPRESSION);
        $zipAllInfo = $zipFile->getAllInfo();

        foreach ($zipAllInfo as $entryName => $info) {
            self::assertEquals($zipFile[$entryName], $entries[$entryName]['data']);
            self::assertEquals($info->getMethod(), $entries[$entryName]['expected']);
            $entryInfo = $zipFile->getEntryInfo($entryName);
            self::assertEquals($entryInfo, $info);
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

        $extractPath = sys_get_temp_dir() . '/zipExtract' . uniqid();
        if (!is_dir($extractPath)) {
            mkdir($extractPath, 0755, true);
        }

        $zipFile = new ZipFile();
        foreach ($entries as $entryName => $value) {
            if ($value === null) {
                $zipFile->addEmptyDir($entryName);
            } else {
                $zipFile->addFromString($entryName, $value);
            }
        }
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        $zipFile->openFile($this->outputFilename);
        $zipFile->extractTo($extractPath);
        foreach ($entries as $entryName => $value) {
            $fullExtractedFilename = $extractPath . DIRECTORY_SEPARATOR . $entryName;
            if ($value === null) {
                self::assertTrue(is_dir($fullExtractedFilename));
                self::assertTrue(FilesUtil::isEmptyDir($fullExtractedFilename));
            } else {
                self::assertTrue(is_file($fullExtractedFilename));
                $contents = file_get_contents($fullExtractedFilename);
                self::assertEquals($contents, $value);
            }
        }
        $zipFile->close();

        FilesUtil::removeDir($extractPath);
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

        $extractEntries = [
            'test1.txt',
            'test3.txt',
            'test5.txt',
            'test/test/test 2.txt',
            'test empty/dir2/'
        ];

        $extractPath = sys_get_temp_dir() . '/zipExtractTest';
        if (is_dir($extractPath)) {
            FilesUtil::removeDir($extractPath);
        }
        self::assertTrue(mkdir($extractPath, 0755, true));

        $zipFile = new ZipFile();
        $zipFile->addAll($entries);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        $zipFile->openFile($this->outputFilename);
        $zipFile->extractTo($extractPath, $extractEntries);

        foreach ($entries as $entryName => $value) {
            $fullExtractFilename = $extractPath . DIRECTORY_SEPARATOR . $entryName;
            if (in_array($entryName, $extractEntries)) {
                if ($value === null) {
                    self::assertTrue(is_dir($fullExtractFilename));
                    self::assertTrue(FilesUtil::isEmptyDir($fullExtractFilename));
                } else {
                    self::assertTrue(is_file($fullExtractFilename));
                    $contents = file_get_contents($fullExtractFilename);
                    self::assertEquals($contents, $value);
                }
            } else {
                if ($value === null) {
                    self::assertFalse(is_dir($fullExtractFilename));
                } else {
                    self::assertFalse(is_file($fullExtractFilename));
                }
            }
        }
        self::assertFalse(is_file($extractPath . DIRECTORY_SEPARATOR . 'test/test/test.txt'));
        $zipFile->extractTo($extractPath, 'test/test/test.txt');
        self::assertTrue(is_file($extractPath . DIRECTORY_SEPARATOR . 'test/test/test.txt'));

        $zipFile->close();
        FilesUtil::removeDir($extractPath);
    }

    /**
     * @expectedException \PhpZip\Exception\ZipException
     * @expectedExceptionMessage not found
     */
    public function testExtractFail()
    {
        $zipFile = new ZipFile();
        $zipFile['file'] = 'content';
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        $zipFile->openFile($this->outputFilename);
        $zipFile->extractTo('path/to/path');
    }

    /**
     * @expectedException \PhpZip\Exception\ZipException
     * @expectedExceptionMessage Destination is not directory
     */
    public function testExtractFail2()
    {
        $zipFile = new ZipFile();
        $zipFile['file'] = 'content';
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        $zipFile->openFile($this->outputFilename);
        $zipFile->extractTo($this->outputFilename);
    }

    /**
     * @expectedException \PhpZip\Exception\ZipException
     * @expectedExceptionMessage Destination is not writable directory
     */
    public function testExtractFail3()
    {
        $zipFile = new ZipFile();
        $zipFile['file'] = 'content';
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        $extractPath = sys_get_temp_dir() . '/zipExtractTest';
        if (is_dir($extractPath)) {
            FilesUtil::removeDir($extractPath);
        }
        self::assertTrue(mkdir($extractPath, 0444, true));
        self::assertTrue(chmod($extractPath, 0444));

        $zipFile->openFile($this->outputFilename);
        $zipFile->extractTo($extractPath);
    }

    /**
     * Test archive password.
     */
    public function testSetPassword()
    {
        $password = base64_encode(CryptoUtil::randomBytes(100));
        $badPassword = "sdgt43r23wefe";

        // create encryption password with ZipCrypto
        $zipFile = new ZipFile();
        $zipFile->addDir(__DIR__);
        $zipFile->withNewPassword($password, ZipFile::ENCRYPTION_METHOD_TRADITIONAL);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename, $password);

        // check bad password for ZipCrypto
        $zipFile->openFile($this->outputFilename);
        $zipFile->withReadPassword($badPassword);
        foreach ($zipFile->getListFiles() as $entryName) {
            try {
                $zipFile[$entryName];
                self::fail("Expected Exception has not been raised.");
            } catch (ZipAuthenticationException $ae) {
                self::assertNotNull($ae);
            }
        }

        // check correct password for ZipCrypto
        $zipFile->withReadPassword($password);
        foreach ($zipFile->getAllInfo() as $info) {
            self::assertTrue($info->isEncrypted());
            self::assertContains('ZipCrypto', $info->getMethod());
            $decryptContent = $zipFile[$info->getPath()];
            self::assertNotEmpty($decryptContent);
            self::assertContains('<?php', $decryptContent);
        }

        // change encryption method to WinZip Aes and update file
        $zipFile->withNewPassword($password, ZipFile::ENCRYPTION_METHOD_WINZIP_AES);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename, $password);

        // check from WinZip AES encryption
        $zipFile->openFile($this->outputFilename);
        // set bad password WinZip AES
        $zipFile->withReadPassword($badPassword);
        foreach ($zipFile->getListFiles() as $entryName) {
            try {
                $zipFile[$entryName];
                self::fail("Expected Exception has not been raised.");
            } catch (ZipAuthenticationException $ae) {
                self::assertNotNull($ae);
            }
        }

        // set correct password WinZip AES
        $zipFile->withReadPassword($password);
        foreach ($zipFile->getAllInfo() as $info) {
            self::assertTrue($info->isEncrypted());
            self::assertContains('WinZip', $info->getMethod());
            $decryptContent = $zipFile[$info->getPath()];
            self::assertNotEmpty($decryptContent);
            self::assertContains('<?php', $decryptContent);
        }

        // clear password
        $zipFile->addFromString('file1', '');
        $zipFile->withoutPassword();
        $zipFile->addFromString('file2', '');
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        // check remove password
        $zipFile->openFile($this->outputFilename);
        foreach ($zipFile->getAllInfo() as $info) {
            self::assertFalse($info->isEncrypted());
        }
        $zipFile->close();
    }

    /**
     * @expectedException \PhpZip\Exception\InvalidArgumentException
     * @expectedExceptionMessage entryName is null
     */
    public function testAddFromArrayAccessNullName()
    {
        $zipFile = new ZipFile();
        $zipFile[null] = 'content';
    }

    /**
     * @expectedException \PhpZip\Exception\InvalidArgumentException
     * @expectedExceptionMessage entryName is empty
     */
    public function testAddFromArrayAccessEmptyName()
    {
        $zipFile = new ZipFile();
        $zipFile[''] = 'content';
    }

    /**
     * @expectedException \PhpZip\Exception\InvalidArgumentException
     * @expectedExceptionMessage Contents is null
     */
    public function testAddFromStringNullContents()
    {
        $zipFile = new ZipFile();
        $zipFile->addFromString('file', null);
    }

    /**
     * @expectedException \PhpZip\Exception\InvalidArgumentException
     * @expectedExceptionMessage Incorrect entry name
     */
    public function testAddFromStringNullEntryName()
    {
        $zipFile = new ZipFile();
        $zipFile->addFromString(null, 'contents');
    }

    /**
     * @expectedException \PhpZip\Exception\ZipUnsupportMethod
     * @expectedExceptionMessage Unsupported method
     */
    public function testAddFromStringUnsupportedMethod()
    {
        $zipFile = new ZipFile();
        $zipFile->addFromString('file', 'contents', ZipEntry::METHOD_WINZIP_AES);
    }

    /**
     * @expectedException \PhpZip\Exception\InvalidArgumentException
     * @expectedExceptionMessage Incorrect entry name
     */
    public function testAddFromStringEmptyEntryName()
    {
        $zipFile = new ZipFile();
        $zipFile->addFromString('', 'contents');
    }

    /**
     * Test compression method from add string.
     */
    public function testAddFromStringCompressionMethod()
    {
        $fileStored = sys_get_temp_dir() . '/zip-stored.txt';
        $fileDeflated = sys_get_temp_dir() . '/zip-deflated.txt';

        self::assertNotFalse(file_put_contents($fileStored, 'content'));
        self::assertNotFalse(file_put_contents($fileDeflated, str_repeat('content', 200)));

        $zipFile = new ZipFile();
        $zipFile->addFromString(basename($fileStored), file_get_contents($fileStored));
        $zipFile->addFromString(basename($fileDeflated), file_get_contents($fileDeflated));
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        unlink($fileStored);
        unlink($fileDeflated);

        $zipFile->openFile($this->outputFilename);
        $infoStored = $zipFile->getEntryInfo(basename($fileStored));
        $infoDeflated = $zipFile->getEntryInfo(basename($fileDeflated));
        self::assertEquals($infoStored->getMethod(), 'No compression');
        self::assertEquals($infoDeflated->getMethod(), 'Deflate');
        $zipFile->close();
    }

    /**
     * @expectedException \PhpZip\Exception\InvalidArgumentException
     * @expectedExceptionMessage stream is not resource
     */
    public function testAddFromStreamInvalidResource()
    {
        $zipFile = new ZipFile();
        $zipFile->addFromStream("invalid resource", "name");
    }

    /**
     * @expectedException \PhpZip\Exception\InvalidArgumentException
     * @expectedExceptionMessage Incorrect entry name
     */
    public function testAddFromStreamEmptyEntryName()
    {
        $handle = fopen(__FILE__, 'rb');

        $zipFile = new ZipFile();
        $zipFile->addFromStream($handle, "");
    }

    /**
     * @expectedException \PhpZip\Exception\ZipUnsupportMethod
     * @expectedExceptionMessage Unsupported method
     */
    public function testAddFromStreamUnsupportedMethod()
    {
        $handle = fopen(__FILE__, 'rb');

        $zipFile = new ZipFile();
        $zipFile->addFromStream($handle, basename(__FILE__), ZipEntry::METHOD_WINZIP_AES);
    }

    /**
     * Test compression method from add stream.
     */
    public function testAddFromStreamCompressionMethod()
    {
        $fileStored = sys_get_temp_dir() . '/zip-stored.txt';
        $fileDeflated = sys_get_temp_dir() . '/zip-deflated.txt';

        self::assertNotFalse(file_put_contents($fileStored, 'content'));
        self::assertNotFalse(file_put_contents($fileDeflated, str_repeat('content', 200)));

        $fpStored = fopen($fileStored, 'rb');
        $fpDeflated = fopen($fileDeflated, 'rb');

        $zipFile = new ZipFile();
        $zipFile->addFromStream($fpStored, basename($fileStored));
        $zipFile->addFromStream($fpDeflated, basename($fileDeflated));
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        unlink($fileStored);
        unlink($fileDeflated);

        $zipFile->openFile($this->outputFilename);
        $infoStored = $zipFile->getEntryInfo(basename($fileStored));
        $infoDeflated = $zipFile->getEntryInfo(basename($fileDeflated));
        self::assertEquals($infoStored->getMethod(), 'No compression');
        self::assertEquals($infoDeflated->getMethod(), 'Deflate');
        $zipFile->close();
    }

    /**
     * @expectedException \PhpZip\Exception\InvalidArgumentException
     * @expectedExceptionMessage Filename is null
     */
    public function testAddFileNullFileName()
    {
        $zipFile = new ZipFile();
        $zipFile->addFile(null);
    }

    /**
     * @expectedException \PhpZip\Exception\InvalidArgumentException
     * @expectedExceptionMessage File is not exists
     */
    public function testAddFileCantExists()
    {
        $zipFile = new ZipFile();
        $zipFile->addFile('path/to/file');
    }

    /**
     * @expectedException \PhpZip\Exception\ZipUnsupportMethod
     * @expectedExceptionMessage Unsupported method
     */
    public function testAddFileUnsupportedMethod()
    {
        $zipFile = new ZipFile();
        $zipFile->addFile(__FILE__, null, ZipEntry::METHOD_WINZIP_AES);
    }

    /**
     * @expectedException \PhpZip\Exception\InvalidArgumentException
     * @expectedExceptionMessage can not open
     */
    public function testAddFileCantOpen()
    {
        self::assertNotFalse(file_put_contents($this->outputFilename, ''));
        self::assertTrue(chmod($this->outputFilename, 0244));

        $zipFile = new ZipFile();
        $zipFile->addFile($this->outputFilename);
    }

    /**
     * @expectedException \PhpZip\Exception\InvalidArgumentException
     * @expectedExceptionMessage handle is not resource
     */
    public function testSaveAsStreamBadStream()
    {
        $zipFile = new ZipFile();
        $zipFile->saveAsStream("bad stream");
    }

    /**
     * @expectedException \PhpZip\Exception\InvalidArgumentException
     * @expectedExceptionMessage can not open from write
     */
    public function testSaveAsFileNotWritable()
    {
        $this->outputFilename = sys_get_temp_dir() . '/zipExtractTest';
        if (is_dir($this->outputFilename)) {
            FilesUtil::removeDir($this->outputFilename);
        }
        self::assertTrue(mkdir($this->outputFilename, 0444, true));
        self::assertTrue(chmod($this->outputFilename, 0444));

        $this->outputFilename .= '/' . uniqid() . '.zip';

        $zipFile = new ZipFile();
        $zipFile->saveAsFile($this->outputFilename);
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

        $methods = [ZipFile::METHOD_STORED, ZipFile::METHOD_DEFLATED];
        if (extension_loaded("bz2")) {
            $methods[] = ZipFile::METHOD_BZIP2;
        }

        $zipFile = new ZipFile();
        $zipFile->setCompressionLevel(ZipFile::LEVEL_BEST_SPEED);
        foreach ($files as $entryName => $content) {
            $zipFile->addFromString($entryName, $content, $methods[array_rand($methods)]);
        }
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);

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

        $zipFile = new ZipFile();
        $zipFile['file1.txt'] = 'content 1';
        $zipFile['dir/file2.txt'] = 'content 1';
        $zipFile['dir/empty dir/'] = null;
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        self::assertTrue(isset($zipFile['file1.txt']));
        self::assertTrue(isset($zipFile['dir/file2.txt']));
        self::assertTrue(isset($zipFile['dir/empty dir/']));
        self::assertFalse(isset($zipFile['dir/empty dir/2/']));
        $zipFile['dir/empty dir/2/'] = null;
        unset($zipFile['dir/file2.txt'], $zipFile['dir/empty dir/']);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        self::assertTrue(isset($zipFile['file1.txt']));
        self::assertFalse(isset($zipFile['dir/file2.txt']));
        self::assertFalse(isset($zipFile['dir/empty dir/']));
        self::assertTrue(isset($zipFile['dir/empty dir/2/']));
        $zipFile->close();
    }

    /**
     * @expectedException \PhpZip\Exception\InvalidArgumentException
     * @expectedExceptionMessage DirName empty
     */
    public function testAddEmptyDirNullName()
    {
        $zipFile = new ZipFile();
        $zipFile->addEmptyDir(null);
    }

    /**
     * @expectedException \PhpZip\Exception\InvalidArgumentException
     * @expectedExceptionMessage DirName empty
     */
    public function testAddEmptyDirEmptyName()
    {
        $zipFile = new ZipFile();
        $zipFile->addEmptyDir("");
    }

    /**
     * Test zip alignment.
     */
    public function testZipAlign()
    {
        $zipFile = new ZipFile();
        for ($i = 0; $i < 100; $i++) {
            $zipFile->addFromString(
                'entry' . $i . '.txt',
                CryptoUtil::randomBytes(mt_rand(100, 4096)),
                ZipFile::METHOD_STORED
            );
        }
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $result = self::doZipAlignVerify($this->outputFilename);
        if ($result === null) return; // zip align not installed

        // check not zip align
        self::assertFalse($result);

        $zipFile->openFile($this->outputFilename);
        $zipFile->setZipAlign(4);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $result = self::doZipAlignVerify($this->outputFilename, true);
        self::assertNotNull($result);

        // check zip align
        self::assertTrue($result);

        $zipFile = new ZipFile();
        for ($i = 0; $i < 100; $i++) {
            $zipFile->addFromString(
                'entry' . $i . '.txt',
                CryptoUtil::randomBytes(mt_rand(100, 4096)),
                ZipFile::METHOD_STORED
            );
        }
        $zipFile->setZipAlign(4);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $result = self::doZipAlignVerify($this->outputFilename);
        // check not zip align
        self::assertTrue($result);
    }

    /**
     * Test support ZIP64 ext (slow test - normal).
     * Create > 65535 files in archive and open and extract to /dev/null.
     */
    public function testCreateAndOpenZip64Ext()
    {
        $countFiles = 0xffff + 1;

        $zipFile = new ZipFile();
        for ($i = 0; $i < $countFiles; $i++) {
            $zipFile[$i . '.txt'] = $i;
        }
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        self::assertEquals($zipFile->count(), $countFiles);
        foreach ($zipFile as $entry => $content) {

        }
        $zipFile->close();
    }

}