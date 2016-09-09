<?php

class TestZipFile extends PHPUnit_Framework_TestCase
{

    public function testCreate()
    {
        $output = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test-create.zip';
        $extractOutputDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test-create';

        $listFilename = 'files.txt';
        $listFileContent = implode(PHP_EOL, glob('*'));
        $dirName = 'src/';
        $archiveComment = 'Archive comment - 😀';
        $commentIndex0 = basename(__FILE__);

        $zip = new \Nelexa\Zip\ZipFile();
        $zip->create();
        $zip->addFile(__FILE__);
        $zip->addFromString($listFilename, $listFileContent);
        $zip->addEmptyDir($dirName);
        $zip->setArchiveComment($archiveComment);
        $zip->setCommentIndex(0, $commentIndex0);
        $zip->saveAs($output);
        $zip->close();

        $this->assertTrue(file_exists($output));
        $this->assertCorrectZipArchive($output);

        $zip = new \Nelexa\Zip\ZipFile();
        $zip->open($output);
        $listFiles = $zip->getListFiles();

        $this->assertEquals(sizeof($listFiles), 3);
        $filenameIndex0 = basename(__FILE__);
        $this->assertEquals($listFiles[0], $filenameIndex0);
        $this->assertEquals($listFiles[1], $listFilename);
        $this->assertEquals($listFiles[2], $dirName);

        $this->assertEquals($zip->getFromIndex(0), $zip->getFromName(basename(__FILE__)));
        $this->assertEquals($zip->getFromIndex(0), file_get_contents(__FILE__));
        $this->assertEquals($zip->getFromIndex(1), $zip->getFromName($listFilename));
        $this->assertEquals($zip->getFromIndex(1), $listFileContent);

        $this->assertEquals($zip->getArchiveComment(), $archiveComment);
        $this->assertEquals($zip->getCommentIndex(0), $commentIndex0);

        if (!file_exists($extractOutputDir)) {
            $this->assertTrue(mkdir($extractOutputDir, 0755, true));
        }

        $zip->extractTo($extractOutputDir);

        $this->assertTrue(file_exists($extractOutputDir . DIRECTORY_SEPARATOR . $filenameIndex0));
        $this->assertEquals(md5_file($extractOutputDir . DIRECTORY_SEPARATOR . $filenameIndex0), md5_file(__FILE__));

        $this->assertTrue(file_exists($extractOutputDir . DIRECTORY_SEPARATOR . $listFilename));
        $this->assertEquals(file_get_contents($extractOutputDir . DIRECTORY_SEPARATOR . $listFilename), $listFileContent);

        $zip->close();

        unlink($output);

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($extractOutputDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileInfo) {
            $todo = ($fileInfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileInfo->getRealPath());
        }

        rmdir($extractOutputDir);
    }

    /**
     *
     */
    public function testUpdate()
    {
        $file = __DIR__ . '/res/file.apk';
        $privateKey = __DIR__ . '/res/private.pem';
        $publicKey = __DIR__ . '/res/public.pem';
        $outputFile = sys_get_temp_dir() . '/test-update.apk';

        $zip = new \Nelexa\Zip\ZipFile($file);
        $zip->open($file);

        // signed apk file
        $certList = array();
        $manifestMf = new Manifest();
        $manifestMf->appendLine("Manifest-Version: 1.0");
        $manifestMf->appendLine("Created-By: 1.0 (Android)");
        $manifestMf->appendLine('');
        for ($i = 0, $length = $zip->getCountFiles(); $i < $length; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name[strlen($name) - 1] === '/') continue; // is path
            $content = $zip->getFromIndex($i);

            $certManifest = $this->createSha1EncodeEntryManifest($name, $content);
            $manifestMf->appendManifest($certManifest);
            $certList[$name] = $certManifest;
        }
        $manifestMf = $manifestMf->getContent();

        $certSf = new Manifest();
        $certSf->appendLine('Signature-Version: 1.0');
        $certSf->appendLine('Created-By: 1.0 (Android)');
        $certSf->appendLine('SHA1-Digest-Manifest: ' . base64_encode(sha1($manifestMf, 1)));
        $certSf->appendLine('');
        foreach ($certList AS $filename => $content) {
            $certManifest = $this->createSha1EncodeEntryManifest($filename, $content->getContent());
            $certSf->appendManifest($certManifest);
        }
        $certSf = $certSf->getContent();
        unset($certList);

        $zip->addFromString('META-INF/MANIFEST.MF', $manifestMf);
        $zip->addFromString('META-INF/CERT.SF', $certSf);

        if (`which openssl`) {
            $openssl_cmd = 'printf ' . escapeshellarg($certSf) . ' | openssl smime -md sha1 -sign -inkey ' . escapeshellarg($privateKey) . ' -signer ' . $publicKey . ' -binary -outform DER -noattr';

            ob_start();
            passthru($openssl_cmd, $error);
            $rsaContent = ob_get_clean();
            $this->assertEquals($error, 0);

            $zip->addFromString('META-INF/CERT.RSA', $rsaContent);
        }

        $zip->saveAs($outputFile);
        $zip->close();

        $this->assertCorrectZipArchive($outputFile);

        if (`which jarsigner`) {
            ob_start();
            passthru('jarsigner -verify -verbose -certs ' . escapeshellarg($outputFile), $error);
            $verifedResult = ob_get_clean();

            $this->assertEquals($error, 0);
            $this->assertContains('jar verified', $verifedResult);
        }

        unlink($outputFile);
    }

    /**
     * @param $filename
     */
    private function assertCorrectZipArchive($filename)
    {
        exec("zip -T " . escapeshellarg($filename), $output, $returnCode);
        $this->assertEquals($returnCode, 0);
    }

    /**
     * @param string $filename
     * @param string $content
     * @return Manifest
     */
    private function createSha1EncodeEntryManifest($filename, $content)
    {
        $manifest = new Manifest();
        $manifest->appendLine('Name: ' . $filename);
        $manifest->appendLine('SHA1-Digest: ' . base64_encode(sha1($content, 1)));
        return $manifest;
    }
}

class Manifest
{
    private $content;

    /**
     * @return mixed
     */
    public function getContent()
    {
        return trim($this->content) . "\r\n\r\n";
    }

    /**
     * Process a long manifest line and add continuation if required
     * @param $line string
     * @return Manifest
     */
    public function appendLine($line)
    {
        $begin = 0;
        $sb = '';
        $lineLength = mb_strlen($line, "UTF-8");
        for ($end = 70; $lineLength - $begin > 70; $end += 69) {
            $sb .= mb_substr($line, $begin, $end - $begin, "UTF-8") . "\r\n ";
            $begin = $end;
        }
        $this->content .= $sb . mb_substr($line, $begin, $lineLength, "UTF-8") . "\r\n";
        return $this;
    }

    public function appendManifest(Manifest $manifest)
    {
        $this->content .= $manifest->getContent();
        return $this;
    }

    public function clear()
    {
        $this->content = '';
    }

    /**
     * @param string $manifestContent
     * @return Manifest
     */
    public static function createFromManifest($manifestContent)
    {
        $manifestContent = trim($manifestContent);
        $lines = explode("\n", $manifestContent);

        // normalize manifest
        $content = '';
        $trim = array("\r", "\n");
        foreach ($lines AS $line) {

            $line = str_replace($trim, '', $line);
            if ($line[0] === ' ') {
                $content = rtrim($content, "\n\r");
                $line = ltrim($line);
            }
            $content .= $line . "\r\n";
        }

        $manifset = new self;
        $lines = explode("\n", $content);
        foreach ($lines AS $line) {
            $line = trim($line, "\n\r");
            $manifset->appendLine($line);
        }
        return $manifset;
    }
}