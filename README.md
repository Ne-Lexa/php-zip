`PhpZip` Version 2
================
`PhpZip` - is to create, update, opening and unpacking ZIP archives in pure PHP.

The library supports `ZIP64`, `zipalign`, `Traditional PKWARE Encryption` and `WinZIP AES Encryption`.

The library does not require extension `php-xml` and class `ZipArchive`.

Requirements
------------
- `PHP` >= 5.4 (64 bit)
- Php-extension `mbstring`
- Optional php-extension `bzip2` for BZIP2 compression.
- Optional php-extension `openssl` or `mcrypt` for `WinZip Aes Encryption` support.

Installation
------------
`composer require nelexa/zip`

Documentation
-------------
#### Class `\PhpZip\ZipFile` (open, extract, info)
Open zip archive from file.
```php
$zipFile = \PhpZip\ZipFile::openFromFile($filename);
```
Open zip archive from data string.
```php
$data = file_get_contents($filename);
$zipFile = \PhpZip\ZipFile::openFromString($data);
```
Open zip archive from stream resource.
```php
$stream = fopen($filename, 'rb');
$zipFile = \PhpZip\ZipFile::openFromStream($stream);
```
Get num entries.
```php
$count = $zipFile->count();
// or
$count = count($zipFile);
```
Get list files.
```php
$listFiles = $zipFile->getListFiles();
```
Foreach zip entries.
```php
foreach($zipFile as $entryName => $dataContent){
    echo "Entry: $entryName" . PHP_EOL;
    echo "Data: $dataContent" . PHP_EOL;
    echo "-----------------------------" . PHP_EOL;
}
```
Iterator zip entries.
```php
$iterator = new \ArrayIterator($zipFile);
while ($iterator->valid())
{
    $entryName = $iterator->key();
    $dataContent = $iterator->current();

    echo "Entry: $entryName" . PHP_EOL;
    echo "Data: $dataContent" . PHP_EOL;
    echo "-----------------------------" . PHP_EOL;

    $iterator->next();
}
```
Checks whether a entry exists.
```php
$boolValue = $zipFile->hasEntry($entryName);
```
Check whether the directory entry.
```php
$boolValue = $zipFile->isDirectory($entryName);
```
Set password to all encrypted entries.
```php
$zipFile->setPassword($password);
```
Set password to concrete zip entry.
```php
$zipFile->setEntryPassword($entryName, $password);
```
Get comment archive.
```php
$commentArchive = $zipFile->getComment();
```
Get comment zip entry.
```php
$commentEntry = $zipFile->getEntryComment($entryName);
```
Get entry info.
```php
$zipInfo = $zipFile->getEntryInfo('file.txt');
echo $zipInfo . PHP_EOL;
// ZipInfo {Path="file.txt", Size=9.77KB, Compressed size=2.04KB, Modified time=2016-09-24T19:25:10+03:00, Crc=0x4b5ab5c7, Method="Deflate", Platform="UNIX", Version=20}
print_r($zipInfo);
//PhpZip\Model\ZipInfo Object
//(
//    [path:PhpZip\Model\ZipInfo:private] => file.txt
//    [folder:PhpZip\Model\ZipInfo:private] => 
//    [size:PhpZip\Model\ZipInfo:private] => 10000
//    [compressedSize:PhpZip\Model\ZipInfo:private] => 2086
//    [mtime:PhpZip\Model\ZipInfo:private] => 1474734310
//    [ctime:PhpZip\Model\ZipInfo:private] => 
//    [atime:PhpZip\Model\ZipInfo:private] => 
//    [encrypted:PhpZip\Model\ZipInfo:private] => 
//    [comment:PhpZip\Model\ZipInfo:private] => 
//    [crc:PhpZip\Model\ZipInfo:private] => 1264235975
//    [method:PhpZip\Model\ZipInfo:private] => Deflate
//    [platform:PhpZip\Model\ZipInfo:private] => UNIX
//    [version:PhpZip\Model\ZipInfo:private] => 20
//)
```
Get info for all entries.
```php
$zipAllInfo = $zipFile->getAllInfo();
print_r($zipAllInfo);
//Array
//(
//    [file.txt] => PhpZip\Model\ZipInfo Object
//    (
//            ...
//    )
//
//    [file2.txt] => PhpZip\Model\ZipInfo Object
//    (
//            ...
//    )
//    
//    ...
//)
```
Extract all files to directory.
```php
$zipFile->extractTo($directory);
```
Extract some files to directory.
```php
$extractOnlyFiles = ["filename1", "filename2", "dir/dir/dir/"];
$zipFile->extractTo($directory, $extractOnlyFiles);
```
Get entry content.
```php
$data = $zipFile->getEntryContent($entryName);
```
Close zip archive.
```php
$zipFile->close();
```
#### Class `\PhpZip\ZipOutputFile` (create, update, extract)
Create zip archive.
```php
$zipOutputFile = new \PhpZip\ZipOutputFile();
// or
$zipOutputFile = \PhpZip\ZipOutputFile::create();
```
Open zip file from update.
```php
// initial ZipFile
$zipFile = \PhpZip\ZipFile::openFromFile($filename);

// Create output stream from update zip file
$zipOutputFile = new \PhpZip\ZipOutputFile($zipFile);
// or
$zipOutputFile = \PhpZip\ZipOutputFile::openFromZipFile($zipFile);
```
Add entry from file.
```php
$zipOutputFile->addFromFile($filename); // $entryName == basename($filename);
$zipOutputFile->addFromFile($filename, $entryName);
$zipOutputFile->addFromFile($filename, $entryName, ZipEntry::METHOD_DEFLATED);
$zipOutputFile->addFromFile($filename, null, ZipEntry::METHOD_BZIP2); // $entryName == basename($filename);
```
Add entry from string data.
```php
$zipOutputFile->addFromString($entryName, $data)
$zipOutputFile->addFromString($entryName, $data, ZipEntry::METHOD_DEFLATED)
```
Add entry from stream.
```php
$zipOutputFile->addFromStream($stream, $entryName)
$zipOutputFile->addFromStream($stream, $entryName, ZipEntry::METHOD_DEFLATED)
```
Add empty dir
```php
$zipOutputFile->addEmptyDir($dirName);
```
Add a directory **recursively** to the archive.
```php
$zipOutputFile->addDir($dirName);
// or
$zipOutputFile->addDir($dirName, true);
```
Add a directory **not recursively** to the archive.
```php
$zipOutputFile->addDir($dirName, false);
```
Add a directory to the archive by path `$moveToPath`
```php
$moveToPath = 'dir/subdir/';
$zipOutputFile->addDir($dirName, $boolResursive, $moveToPath);
```
Add a directory to the archive with ignoring files.
```php
$ignoreFiles = ["file_ignore.txt", "dir_ignore/sub dir ignore/"];
$zipOutputFile->addDir($dirName, $boolResursive, $moveToPath, $ignoreFiles);
```
Add a directory and set compression method.
```php
$compressionMethod = ZipEntry::METHOD_DEFLATED;
$zipOutputFile->addDir($dirName, $boolRecursive, $moveToPath, $ignoreFiles, $compressionMethod);
```
Add a files **recursively** from [glob pattern](https://en.wikipedia.org/wiki/Glob_(programming)) to the archive.
```php
$globPattern = '**.{jpg,jpeg,png,gif}'; // example glob pattern -> add all .jpg, .jpeg, .png and .gif files
$zipOutputFile->addFilesFromGlob($inputDir, $globPattern);
```
Add a files **not recursively** from [glob pattern](https://en.wikipedia.org/wiki/Glob_(programming)) to the archive.
```php
$recursive = false;
$zipOutputFile->addFilesFromGlob($inputDir, $globPattern, $recursive);
```
Add a files from [glob pattern](https://en.wikipedia.org/wiki/Glob_(programming)) to the archive by path `$moveToPath`.
```php
$moveToPath = 'dir/dir2/dir3';
$zipOutputFile->addFilesFromGlob($inputDir, $globPattern, $recursive = true, $moveToPath);
```
Add a files from [glob pattern](https://en.wikipedia.org/wiki/Glob_(programming)) to the archive and set compression method.
```php
$compressionMethod = ZipEntry::METHOD_DEFLATED;
$zipOutputFile->addFilesFromGlob($inputDir, $globPattern, $recursive, $moveToPath, $compressionMethod);
```
Add a files **recursively** from [RegEx (Regular Expression) pattern](https://en.wikipedia.org/wiki/Regular_expression) to the archive.
```php
$regexPattern = '/\.(jpe?g|png|gif)$/si'; // example regex pattern -> add all .jpg, .jpeg, .png and .gif files
$zipOutputFile->addFilesFromRegex($inputDir, $regexPattern);
```
Add a files **not recursively** from [RegEx (Regular Expression) pattern](https://en.wikipedia.org/wiki/Regular_expression) to the archive.
```php
$recursive = false;
$zipOutputFile->addFilesFromRegex($inputDir, $regexPattern, $recursive);
```
Add a files from [RegEx (Regular Expression) pattern](https://en.wikipedia.org/wiki/Regular_expression) to the archive by path `$moveToPath`.
```php
$moveToPath = 'dir/dir2/dir3';
$zipOutputFile->addFilesFromRegex($inputDir, $regexPattern, $recursive = true, $moveToPath);
```
Add a files from [RegEx (Regular Expression) pattern](https://en.wikipedia.org/wiki/Regular_expression) to the archive and set compression method.
```php
$compressionMethod = ZipEntry::METHOD_DEFLATED;
$zipOutputFile->addFilesFromRegex($inputDir, $regexPattern, $recursive, $moveToPath, $compressionMethod);
```
Rename entry name.
```php
$zipOutputFile->rename($oldName, $newName);
```
Delete entry by name.
```php
$zipOutputFile->deleteFromName($entryName);
```
Delete entries from [glob pattern](https://en.wikipedia.org/wiki/Glob_(programming)).
```php
$globPattern = '**.{jpg,jpeg,png,gif}'; // example glob pattern -> delete all .jpg, .jpeg, .png and .gif files
$zipOutputFile->deleteFromGlob($globPattern);
```
Delete entries from [RegEx (Regular Expression) pattern](https://en.wikipedia.org/wiki/Regular_expression).
```php
$regexPattern = '/\.(jpe?g|png|gif)$/si'; // example regex pattern -> delete all .jpg, .jpeg, .png and .gif files
$zipOutputFile->deleteFromRegex($regexPattern);
```
Delete all entries.
```php
$zipOutputFile->deleteAll();
```
Get num entries.
```php
$count = $zipOutputFile->count();
// or
$count = count($zipOutputFile);
```
Get list files.
```php
$listFiles = $zipOutputFile->getListFiles();
```
Get the compression level for entries.
```php
$compressionLevel = $zipOutputFile->getLevel();
```
Sets the compression level for entries.
```php
// This property is only used if the effective compression method is DEFLATED or BZIP2.
// Legal values are ZipOutputFile::LEVEL_DEFAULT_COMPRESSION or range from
// ZipOutputFile::LEVEL_BEST_SPEED to ZipOutputFile::LEVEL_BEST_COMPRESSION.
$compressionMethod = ZipOutputFile::LEVEL_BEST_COMPRESSION;
$zipOutputFile->setLevel($compressionLevel);
```
Get comment archive.
```php
$commentArchive = $zipOutputFile->getComment();
```
Set comment archive.
```php
$zipOutputFile->setComment($commentArchive);
```
Get comment zip entry.
```php
$commentEntry = $zipOutputFile->getEntryComment($entryName);
```
Set comment zip entry.
```php
$zipOutputFile->setEntryComment($entryName, $entryComment);
```
Set compression method for zip entry.
```php
$compressionMethod = ZipEntry::METHOD_DEFLATED;
$zipOutputMethod->setCompressionMethod($entryName, $compressionMethod);

// Support compression methods:
// ZipEntry::METHOD_STORED - no compression
// ZipEntry::METHOD_DEFLATED - deflate compression
// ZipEntry::METHOD_BZIP2 - bzip2 compression (need bz2 extension)
```
Set a password for all previously added entries.
```php
$zipOutputFile->setPassword($password);
```
Set a password and encryption method for all previously added entries.
```php
$encryptionMethod = ZipEntry::ENCRYPTION_METHOD_WINZIP_AES; // default value
$zipOutputFile->setPassword($password, $encryptionMethod);

// Support encryption methods:
// ZipEntry::ENCRYPTION_METHOD_TRADITIONAL - Traditional PKWARE Encryption
// ZipEntry::ENCRYPTION_METHOD_WINZIP_AES - WinZip AES Encryption
```
Set a password for a concrete entry.
```php
$zipOutputFile->setEntryPassword($entryName, $password);
```
Set a password and encryption method for a concrete entry.
```php
$zipOutputFile->setEntryPassword($entryName, $password, $encryptionMethod);

// Support encryption methods:
// ZipEntry::ENCRYPTION_METHOD_TRADITIONAL - Traditional PKWARE Encryption
// ZipEntry::ENCRYPTION_METHOD_WINZIP_AES - WinZip AES Encryption (default value)
```
Remove password from all entries.
```php
$zipOutputFile->removePasswordAllEntries();
```
Remove password for concrete zip entry.
```php
$zipOutputFile->removePasswordFromEntry($entryName);
```
Save archive to a file.
```php
$zipOutputFile->saveAsFile($filename);
```
Save archive to a stream.
```php
$handle = fopen($filename, 'w+b');
$autoCloseResource = true;
$zipOutputFile->saveAsStream($handle, $autoCloseResource);
if(!$autoCloseResource){
    fclose($handle);
}
```
Returns the zip archive as a string.
```php
$rawZipArchiveBytes = $zipOutputFile->outputAsString();
```
Output .ZIP archive as attachment and terminate.
```php
$zipOutputFile->outputAsAttachment($outputFilename);
// or set mime type
$zipOutputFile->outputAsAttachment($outputFilename = 'output.zip', $mimeType = 'application/zip');
```
Extract all files to directory.
```php
$zipOutputFile->extractTo($directory);
```
Extract some files to directory.
```php
$extractOnlyFiles = ["filename1", "filename2", "dir/dir/dir/"];
$zipOutputFile->extractTo($directory, $extractOnlyFiles);
```
Get entry contents.
```php
$data = $zipOutputFile->getEntryContent($entryName);
```
Foreach zip entries.
```php
foreach($zipOutputFile as $entryName => $dataContent){
    echo "Entry: $entryName" . PHP_EOL;
    echo "Data: $dataContent" . PHP_EOL;
    echo "-----------------------------" . PHP_EOL;
}
```
Iterator zip entries.
```php
$iterator = new \ArrayIterator($zipOutputFile);
while ($iterator->valid())
{
    $entryName = $iterator->key();
    $dataContent = $iterator->current();

    echo "Entry: $entryName" . PHP_EOL;
    echo "Data: $dataContent" . PHP_EOL;
    echo "-----------------------------" . PHP_EOL;

    $iterator->next();
}
```
Set zip alignment (alternate program `zipalign`).
```php
// before save or output
$zipOutputFile->setAlign(4); // alternative cmd: zipalign -f -v 4 filename.zip
```
Close zip archive.
```php
$zipOutputFile->close();
```
Examples
--------
Create, open, extract and update archive.
```php
$outputFilename = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'output.zip';
$outputDirExtract = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'extract';

if(!is_dir($outputDirExtract)){
    mkdir($outputDirExtract, 0755, true);
}

$zipOutputFile = \PhpZip\ZipOutputFile::create(); // create archive
$zipOutputFile->addDir(__DIR__, true); // add this dir to archive
$zipOutputFile->saveAsFile($outputFilename); // save as file
$zipOutputFile->close(); // close output file, release all streams

$zipFile = \PhpZip\ZipFile::openFromFile($outputFilename); // open zip archive from file
$zipFile->extractTo($outputDirExtract); // extract files to dir

$zipOutputFile = \PhpZip\ZipOutputFile::openFromZipFile($zipFile); // create zip output archive for update
$zipOutputFile->deleteFromRegex('~^\.~'); // delete all hidden (Unix) files
$zipOutputFile->addFromString('dir/file.txt', 'Test file'); // add files from string contents
$zipOutputFile->saveAsFile($outputFilename); // update zip file
$zipOutputFile->close(); // close output file, release all streams

$zipFile->close(); // close input file, release all streams
```
Other examples can be found in the `tests/` folder

Running Tests
-------------
```bash
vendor/bin/phpunit -v --tap -c bootstrap.xml
```