<?php

namespace PhpZip;

use PhpZip\Exception\ZipAuthenticationException;
use PhpZip\Exception\ZipEntryNotFoundException;
use PhpZip\Exception\ZipException;
use PhpZip\Model\ZipInfo;
use PhpZip\Util\CryptoUtil;

/**
 * Tests with zip password.
 */
class ZipPasswordTest extends ZipFileAddDirTest
{
    /**
     * Test archive password.
     * @throws ZipException
     */
    public function testSetPassword()
    {
        if (PHP_INT_SIZE === 4) {
            $this->markTestSkipped('Skip test for 32-bit system. Not support Traditional PKWARE Encryption.');
        }

        $password = base64_encode(CryptoUtil::randomBytes(100));
        $badPassword = "bad password";

        // create encryption password with ZipCrypto
        $zipFile = new ZipFile();
        $zipFile->addDir(__DIR__);
        $zipFile->setPassword($password, ZipFileInterface::ENCRYPTION_METHOD_TRADITIONAL);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        $this->assertCorrectZipArchive($this->outputFilename, $password);

        // check bad password for ZipCrypto
        $zipFile->openFile($this->outputFilename);
        $zipFile->setReadPassword($badPassword);
        foreach ($zipFile->getListFiles() as $entryName) {
            try {
                $zipFile[$entryName];
                $this->fail("Expected Exception has not been raised.");
            } catch (ZipAuthenticationException $ae) {
                $this->assertContains('Invalid password for zip entry', $ae->getMessage());
            }
        }

        // check correct password for ZipCrypto
        $zipFile->setReadPassword($password);
        foreach ($zipFile->getAllInfo() as $info) {
            $this->assertTrue($info->isEncrypted());
            $this->assertContains('ZipCrypto', $info->getMethodName());
            $decryptContent = $zipFile[$info->getName()];
            $this->assertNotEmpty($decryptContent);
            $this->assertContains('<?php', $decryptContent);
        }

        // change encryption method to WinZip Aes and update file
        $zipFile->setPassword($password, ZipFileInterface::ENCRYPTION_METHOD_WINZIP_AES);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        $this->assertCorrectZipArchive($this->outputFilename, $password);

        // check from WinZip AES encryption
        $zipFile->openFile($this->outputFilename);
        // set bad password WinZip AES
        $zipFile->setReadPassword($badPassword);
        foreach ($zipFile->getListFiles() as $entryName) {
            try {
                $zipFile[$entryName];
                $this->fail("Expected Exception has not been raised.");
            } catch (ZipAuthenticationException $ae) {
                $this->assertNotNull($ae);
            }
        }

        // set correct password WinZip AES
        $zipFile->setReadPassword($password);
        foreach ($zipFile->getAllInfo() as $info) {
            $this->assertTrue($info->isEncrypted());
            $this->assertContains('WinZip', $info->getMethodName());
            $decryptContent = $zipFile[$info->getName()];
            $this->assertNotEmpty($decryptContent);
            $this->assertContains('<?php', $decryptContent);
        }

        // clear password
        $zipFile->addFromString('file1', '');
        $zipFile->disableEncryption();
        $zipFile->addFromString('file2', '');
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        $this->assertCorrectZipArchive($this->outputFilename);

        // check remove password
        $zipFile->openFile($this->outputFilename);
        foreach ($zipFile->getAllInfo() as $info) {
            $this->assertFalse($info->isEncrypted());
        }
        $zipFile->close();
    }

    /**
     * @throws ZipException
     */
    public function testTraditionalEncryption()
    {
        if (PHP_INT_SIZE === 4) {
            $this->markTestSkipped('Skip test for 32-bit system. Not support Traditional PKWARE Encryption.');
        }

        $password = base64_encode(CryptoUtil::randomBytes(50));

        $zip = new ZipFile();
        $zip->addDirRecursive($this->outputDirname);
        $zip->setPassword($password, ZipFileInterface::ENCRYPTION_METHOD_TRADITIONAL);
        $zip->saveAsFile($this->outputFilename);
        $zip->close();

        $this->assertCorrectZipArchive($this->outputFilename, $password);

        $zip->openFile($this->outputFilename);
        $zip->setReadPassword($password);
        $this->assertFilesResult($zip, array_keys(self::$files));
        foreach ($zip->getAllInfo() as $info) {
            if (!$info->isFolder()) {
                $this->assertTrue($info->isEncrypted());
                $this->assertContains('ZipCrypto', $info->getMethodName());
            }
        }
        $zip->close();
    }

    /**
     * @dataProvider winZipKeyStrengthProvider
     * @param int $encryptionMethod
     * @param int $bitSize
     * @throws ZipException
     */
    public function testWinZipAesEncryption($encryptionMethod, $bitSize)
    {
        $password = base64_encode(CryptoUtil::randomBytes(50));

        $zip = new ZipFile();
        $zip->addDirRecursive($this->outputDirname);
        $zip->setPassword($password, $encryptionMethod);
        $zip->saveAsFile($this->outputFilename);
        $zip->close();

        $this->assertCorrectZipArchive($this->outputFilename, $password);

        $zip->openFile($this->outputFilename);
        $zip->setReadPassword($password);
        $this->assertFilesResult($zip, array_keys(self::$files));
        foreach ($zip->getAllInfo() as $info) {
            if (!$info->isFolder()) {
                $this->assertTrue($info->isEncrypted());
                $this->assertEquals($info->getEncryptionMethod(), $encryptionMethod);
                $this->assertContains('WinZip AES-' . $bitSize, $info->getMethodName());
            }
        }
        $zip->close();
    }

    /**
     * @return array
     */
    public function winZipKeyStrengthProvider()
    {
        return [
            [ZipFileInterface::ENCRYPTION_METHOD_WINZIP_AES_128, 128],
            [ZipFileInterface::ENCRYPTION_METHOD_WINZIP_AES_192, 192],
            [ZipFileInterface::ENCRYPTION_METHOD_WINZIP_AES, 256],
            [ZipFileInterface::ENCRYPTION_METHOD_WINZIP_AES_256, 256],
        ];
    }

    /**
     * @throws Exception\ZipEntryNotFoundException
     * @throws ZipException
     */
    public function testEncryptionEntries()
    {
        if (PHP_INT_SIZE === 4) {
            $this->markTestSkipped('Skip test for 32-bit system. Not support Traditional PKWARE Encryption.');
        }

        $password1 = '353442434235424234';
        $password2 = 'adgerhvrwjhqqehtqhkbqrgewg';

        $zip = new ZipFile();
        $zip->addDir($this->outputDirname);
        $zip->setPasswordEntry('.hidden', $password1, ZipFileInterface::ENCRYPTION_METHOD_TRADITIONAL);
        $zip->setPasswordEntry('text file.txt', $password2, ZipFileInterface::ENCRYPTION_METHOD_WINZIP_AES);
        $zip->saveAsFile($this->outputFilename);
        $zip->close();

        $zip->openFile($this->outputFilename);
        $zip->setReadPasswordEntry('.hidden', $password1);
        $zip->setReadPasswordEntry('text file.txt', $password2);
        $this->assertFilesResult($zip, [
            '.hidden',
            'text file.txt',
            'Текстовый документ.txt',
            'empty dir/',
        ]);

        $info = $zip->getEntryInfo('.hidden');
        $this->assertTrue($info->isEncrypted());
        $this->assertContains('ZipCrypto', $info->getMethodName());

        $info = $zip->getEntryInfo('text file.txt');
        $this->assertTrue($info->isEncrypted());
        $this->assertContains('WinZip AES', $info->getMethodName());

        $this->assertFalse($zip->getEntryInfo('Текстовый документ.txt')->isEncrypted());
        $this->assertFalse($zip->getEntryInfo('empty dir/')->isEncrypted());

        $zip->close();
    }

    /**
     * @throws Exception\ZipEntryNotFoundException
     * @throws ZipException
     */
    public function testEncryptionEntriesWithDefaultPassword()
    {
        if (PHP_INT_SIZE === 4) {
            $this->markTestSkipped('Skip test for 32-bit system. Not support Traditional PKWARE Encryption.');
        }

        $password1 = '353442434235424234';
        $password2 = 'adgerhvrwjhqqehtqhkbqrgewg';
        $defaultPassword = '  f  f  f  f f  ffff   f5   ';

        $zip = new ZipFile();
        $zip->addDir($this->outputDirname);
        $zip->setPassword($defaultPassword);
        $zip->setPasswordEntry('.hidden', $password1, ZipFileInterface::ENCRYPTION_METHOD_TRADITIONAL);
        $zip->setPasswordEntry('text file.txt', $password2, ZipFileInterface::ENCRYPTION_METHOD_WINZIP_AES);
        $zip->saveAsFile($this->outputFilename);
        $zip->close();

        $zip->openFile($this->outputFilename);
        $zip->setReadPassword($defaultPassword);
        $zip->setReadPasswordEntry('.hidden', $password1);
        $zip->setReadPasswordEntry('text file.txt', $password2);
        $this->assertFilesResult($zip, [
            '.hidden',
            'text file.txt',
            'Текстовый документ.txt',
            'empty dir/',
        ]);

        $info = $zip->getEntryInfo('.hidden');
        $this->assertTrue($info->isEncrypted());
        $this->assertContains('ZipCrypto', $info->getMethodName());

        $info = $zip->getEntryInfo('text file.txt');
        $this->assertTrue($info->isEncrypted());
        $this->assertContains('WinZip AES', $info->getMethodName());

        $info = $zip->getEntryInfo('Текстовый документ.txt');
        $this->assertTrue($info->isEncrypted());
        $this->assertContains('WinZip AES', $info->getMethodName());

        $this->assertFalse($zip->getEntryInfo('empty dir/')->isEncrypted());

        $zip->close();
    }

    /**
     * @expectedException \PhpZip\Exception\ZipException
     * @expectedExceptionMessage Invalid encryption method
     */
    public function testSetEncryptionMethodInvalid()
    {
        $zipFile = new ZipFile();
        $encryptionMethod = 9999;
        $zipFile->setPassword('pass', $encryptionMethod);
        $zipFile['entry'] = 'content';
        $zipFile->outputAsString();
    }

    /**
     * @throws Exception\ZipEntryNotFoundException
     * @throws ZipException
     */
    public function testEntryPassword()
    {
        $zipFile = new ZipFile();
        $zipFile->setPassword('pass');
        $zipFile['file'] = 'content';
        $this->assertFalse($zipFile->getEntryInfo('file')->isEncrypted());
        for ($i = 1; $i <= 10; $i++) {
            $zipFile['file' . $i] = 'content';
            if ($i < 6) {
                $zipFile->setPasswordEntry('file' . $i, 'pass');
                $this->assertTrue($zipFile->getEntryInfo('file' . $i)->isEncrypted());
            } else {
                $this->assertFalse($zipFile->getEntryInfo('file' . $i)->isEncrypted());
            }
        }
        $zipFile->disableEncryptionEntry('file3');
        $this->assertFalse($zipFile->getEntryInfo('file3')->isEncrypted());
        $this->asserttrue($zipFile->getEntryInfo('file2')->isEncrypted());
        $zipFile->disableEncryption();
        $infoList = $zipFile->getAllInfo();
        array_walk($infoList, function (ZipInfo $zipInfo) {
            $this->assertFalse($zipInfo->isEncrypted());
        });
        $zipFile->close();
    }

    /**
     * @expectedException \PhpZip\Exception\ZipException
     * @expectedExceptionMessage Invalid encryption method
     */
    public function testInvalidEncryptionMethodEntry()
    {
        $zipFile = new ZipFile();
        $zipFile->addFromString('file', 'content', ZipFileInterface::METHOD_STORED);
        $zipFile->setPasswordEntry('file', 'pass', 99);
    }

    /**
     * @throws ZipEntryNotFoundException
     * @throws ZipException
     */
    public function testArchivePasswordUpdateWithoutSetReadPassword()
    {
        $zipFile = new ZipFile();
        $zipFile['file1'] = 'content';
        $zipFile['file2'] = 'content';
        $zipFile['file3'] = 'content';
        $zipFile->setPassword('password');
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        $this->assertCorrectZipArchive($this->outputFilename, 'password');

        $zipFile->openFile($this->outputFilename);
        $this->assertCount(3, $zipFile);
        foreach ($zipFile->getAllInfo() as $info) {
            $this->assertTrue($info->isEncrypted());
        }
        unset($zipFile['file3']);
        $zipFile['file4'] = 'content';
        $zipFile->rewrite();

        $this->assertCorrectZipArchive($this->outputFilename, 'password');

        $this->assertCount(3, $zipFile);
        $this->assertFalse(isset($zipFile['file3']));
        $this->assertTrue(isset($zipFile['file4']));
        $this->assertTrue($zipFile->getEntryInfo('file1')->isEncrypted());
        $this->assertTrue($zipFile->getEntryInfo('file2')->isEncrypted());
        $this->assertFalse($zipFile->getEntryInfo('file4')->isEncrypted());
        $this->assertEquals($zipFile['file4'], 'content');

        $zipFile->extractTo($this->outputDirname, ['file4']);

        $this->assertTrue(file_exists($this->outputDirname . DIRECTORY_SEPARATOR . 'file4'));
        $this->assertEquals(file_get_contents($this->outputDirname . DIRECTORY_SEPARATOR . 'file4'), $zipFile['file4']);

        $zipFile->close();
    }

    /**
     * @see https://github.com/Ne-Lexa/php-zip/issues/9
     * @throws ZipException
     */
    public function testIssues9()
    {
        $contents = str_pad('', 1000, 'test;test2;test3' . PHP_EOL, STR_PAD_RIGHT);
        $password = base64_encode(CryptoUtil::randomBytes(20));

        $encryptMethod = ZipFile::ENCRYPTION_METHOD_WINZIP_AES_256;
        $zipFile = new ZipFile();
        $zipFile
            ->addFromString('codes.csv', $contents)
            ->setPassword($password, $encryptMethod)
            ->saveAsFile($this->outputFilename)
            ->close();

        $this->assertCorrectZipArchive($this->outputFilename, $password);

        $zipFile->openFile($this->outputFilename);
        $zipFile->setReadPassword($password);
        $this->assertEquals($zipFile['codes.csv'], $contents);
        $zipFile->close();
    }

    /**
     * @throws ZipEntryNotFoundException
     * @throws ZipException
     */
    public function testReadAesEncryptedAndRewriteArchive()
    {
        $file = __DIR__ . '/resources/aes_password_archive.zip';
        $password = '1234567890';

        $zipFile = new ZipFile();
        $zipFile->openFile($file);
        $zipFile->setReadPassword($password);
        $zipFile->setEntryComment('contents.txt', 'comment'); // change entry, but not changed contents
        $zipFile->saveAsFile($this->outputFilename);

        $zipFile2 = new ZipFile();
        $zipFile2->openFile($this->outputFilename);
        $zipFile2->setReadPassword($password);
        $this->assertEquals($zipFile2->getListFiles(), $zipFile->getListFiles());
        foreach ($zipFile as $name => $contents) {
            $this->assertNotEmpty($name);
            $this->assertNotEmpty($contents);
            $this->assertContains('test contents', $contents);
            $this->assertEquals($zipFile2[$name], $contents);
        }
        $zipFile2->close();

        $zipFile->close();
    }
}
