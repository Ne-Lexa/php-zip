<?php

namespace PhpZip;

use PhpZip\Exception\ZipAuthenticationException;
use PhpZip\Model\ZipInfo;
use PhpZip\Util\CryptoUtil;

/**
 * Tests with zip password.
 */
class ZipPasswordTest extends ZipFileAddDirTest
{
    /**
     * Test archive password.
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

        self::assertCorrectZipArchive($this->outputFilename, $password);

        // check bad password for ZipCrypto
        $zipFile->openFile($this->outputFilename);
        $zipFile->setReadPassword($badPassword);
        foreach ($zipFile->getListFiles() as $entryName) {
            try {
                $zipFile[$entryName];
                self::fail("Expected Exception has not been raised.");
            } catch (ZipAuthenticationException $ae) {
                self::assertNotNull($ae);
            }
        }

        // check correct password for ZipCrypto
        $zipFile->setReadPassword($password);
        foreach ($zipFile->getAllInfo() as $info) {
            self::assertTrue($info->isEncrypted());
            self::assertContains('ZipCrypto', $info->getMethodName());
            $decryptContent = $zipFile[$info->getName()];
            self::assertNotEmpty($decryptContent);
            self::assertContains('<?php', $decryptContent);
        }

        // change encryption method to WinZip Aes and update file
        $zipFile->setPassword($password, ZipFileInterface::ENCRYPTION_METHOD_WINZIP_AES);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename, $password);

        // check from WinZip AES encryption
        $zipFile->openFile($this->outputFilename);
        // set bad password WinZip AES
        $zipFile->setReadPassword($badPassword);
        foreach ($zipFile->getListFiles() as $entryName) {
            try {
                $zipFile[$entryName];
                self::fail("Expected Exception has not been raised.");
            } catch (ZipAuthenticationException $ae) {
                self::assertNotNull($ae);
            }
        }

        // set correct password WinZip AES
        $zipFile->setReadPassword($password);
        foreach ($zipFile->getAllInfo() as $info) {
            self::assertTrue($info->isEncrypted());
            self::assertContains('WinZip', $info->getMethodName());
            $decryptContent = $zipFile[$info->getName()];
            self::assertNotEmpty($decryptContent);
            self::assertContains('<?php', $decryptContent);
        }

        // clear password
        $zipFile->addFromString('file1', '');
        $zipFile->disableEncryption();
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

        self::assertCorrectZipArchive($this->outputFilename, $password);

        $zip->openFile($this->outputFilename);
        $zip->setReadPassword($password);
        self::assertFilesResult($zip, array_keys(self::$files));
        foreach ($zip->getAllInfo() as $info) {
            if (!$info->isFolder()) {
                self::assertTrue($info->isEncrypted());
                self::assertContains('ZipCrypto', $info->getMethodName());
            }
        }
        $zip->close();
    }

    /**
     * @dataProvider winZipKeyStrengthProvider
     * @param int $encryptionMethod
     * @param int $bitSize
     */
    public function testWinZipAesEncryption($encryptionMethod, $bitSize)
    {
        $password = base64_encode(CryptoUtil::randomBytes(50));

        $zip = new ZipFile();
        $zip->addDirRecursive($this->outputDirname);
        $zip->setPassword($password, $encryptionMethod);
        $zip->saveAsFile($this->outputFilename);
        $zip->close();

        self::assertCorrectZipArchive($this->outputFilename, $password);

        $zip->openFile($this->outputFilename);
        $zip->setReadPassword($password);
        self::assertFilesResult($zip, array_keys(self::$files));
        foreach ($zip->getAllInfo() as $info) {
            if (!$info->isFolder()) {
                self::assertTrue($info->isEncrypted());
                self::assertEquals($info->getEncryptionMethod(), $encryptionMethod);
                self::assertContains('WinZip AES-' . $bitSize, $info->getMethodName());
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
        self::assertFilesResult($zip, [
            '.hidden',
            'text file.txt',
            'Текстовый документ.txt',
            'empty dir/',
        ]);

        $info = $zip->getEntryInfo('.hidden');
        self::assertTrue($info->isEncrypted());
        self::assertContains('ZipCrypto', $info->getMethodName());

        $info = $zip->getEntryInfo('text file.txt');
        self::assertTrue($info->isEncrypted());
        self::assertContains('WinZip AES', $info->getMethodName());

        self::assertFalse($zip->getEntryInfo('Текстовый документ.txt')->isEncrypted());
        self::assertFalse($zip->getEntryInfo('empty dir/')->isEncrypted());

        $zip->close();
    }

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
        self::assertFilesResult($zip, [
            '.hidden',
            'text file.txt',
            'Текстовый документ.txt',
            'empty dir/',
        ]);

        $info = $zip->getEntryInfo('.hidden');
        self::assertTrue($info->isEncrypted());
        self::assertContains('ZipCrypto', $info->getMethodName());

        $info = $zip->getEntryInfo('text file.txt');
        self::assertTrue($info->isEncrypted());
        self::assertContains('WinZip AES', $info->getMethodName());

        $info = $zip->getEntryInfo('Текстовый документ.txt');
        self::assertTrue($info->isEncrypted());
        self::assertContains('WinZip AES', $info->getMethodName());

        self::assertFalse($zip->getEntryInfo('empty dir/')->isEncrypted());

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

    public function testEntryPassword()
    {
        $zipFile = new ZipFile();
        $zipFile->setPassword('pass');
        $zipFile['file'] = 'content';
        self::assertFalse($zipFile->getEntryInfo('file')->isEncrypted());
        for ($i = 1; $i <= 10; $i++) {
            $zipFile['file' . $i] = 'content';
            if ($i < 6) {
                $zipFile->setPasswordEntry('file' . $i, 'pass');
                self::assertTrue($zipFile->getEntryInfo('file' . $i)->isEncrypted());
            } else {
                self::assertFalse($zipFile->getEntryInfo('file' . $i)->isEncrypted());
            }
        }
        $zipFile->disableEncryptionEntry('file3');
        self::assertFalse($zipFile->getEntryInfo('file3')->isEncrypted());
        self::asserttrue($zipFile->getEntryInfo('file2')->isEncrypted());
        $zipFile->disableEncryption();
        $infoList = $zipFile->getAllInfo();
        array_walk($infoList, function (ZipInfo $zipInfo) {
            self::assertFalse($zipInfo->isEncrypted());
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

    public function testArchivePasswordUpdateWithoutSetReadPassword()
    {
        $zipFile = new ZipFile();
        $zipFile['file1'] = 'content';
        $zipFile['file2'] = 'content';
        $zipFile['file3'] = 'content';
        $zipFile->setPassword('password');
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename, 'password');

        $zipFile->openFile($this->outputFilename);
        self::assertCount(3, $zipFile);
        foreach ($zipFile->getAllInfo() as $info) {
            self::assertTrue($info->isEncrypted());
        }
        unset($zipFile['file3']);
        $zipFile['file4'] = 'content';
        $zipFile->rewrite();

        self::assertCorrectZipArchive($this->outputFilename, 'password');

        self::assertCount(3, $zipFile);
        self::assertFalse(isset($zipFile['file3']));
        self::assertTrue(isset($zipFile['file4']));
        self::assertTrue($zipFile->getEntryInfo('file1')->isEncrypted());
        self::assertTrue($zipFile->getEntryInfo('file2')->isEncrypted());
        self::assertFalse($zipFile->getEntryInfo('file4')->isEncrypted());
        self::assertEquals($zipFile['file4'], 'content');

        $zipFile->extractTo($this->outputDirname, ['file4']);

        self::assertTrue(file_exists($this->outputDirname . DIRECTORY_SEPARATOR . 'file4'));
        self::assertEquals(file_get_contents($this->outputDirname . DIRECTORY_SEPARATOR . 'file4'), $zipFile['file4']);

        $zipFile->close();
    }

    /**
     * @see https://github.com/Ne-Lexa/php-zip/issues/9
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
