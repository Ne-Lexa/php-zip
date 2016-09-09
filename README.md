## Documentation

Create and manipulate zip archives. No use ZipArchive class and php-zip extension.

### class \Nelexa\Zip\ZipFile
Initialization
```php
$zip = new \Nelexa\Zip\ZipFile();
```
Create archive
```php
$zip->create();
```
Open archive file
```php
$zip->open($filename);
```
Open archive from string
```php
$zip->openFromString($string)
```
Set password
```php
$zip->setPassword($password);
```
List files
```php
$listFiles = $zip->getListFiles();
```
Get count files
```php
$countFiles = $zip->getCountFiles();
```
Add empty dir
```php
$zip->addEmptyDir($dirName);
```
Add dir
```php
$directory = "/tmp";
$ignoreFiles = array("xxx.file", "xxx2.file");
$zip->addDir($directory); // add path /tmp to /
$zip->addDir($directory, "var/temp"); // add path /tmp to var/temp
$zip->addDir($directory, "var/temp", $ignoreFiles); // add path /tmp to var/temp and ignore files xxx.file and xxx2.file
```
Add files from glob pattern
```php
$zip->addGlob("music/*.mp3"); // add all mp3 files
```
Add files from regex pattern
```php
$zip->addPattern("~file[0-9]+\.jpg$~", "picture/");
```
Add file
```php
$zip->addFile($filename);
$zip->addFile($filename, $localName);
$zip->addFile($filename, $localName, \Nelexa\Zip\ZipEntry::COMPRESS_METHOD_STORED); // no compression
$zip->addFile($filename, $localName, \Nelexa\Zip\ZipEntry::COMPRESS_METHOD_DEFLATED);
```
Add file from string
```php
$zip->addFromString($localName, $contents);
$zip->addFromString($localName, $contents,  \Nelexa\Zip\ZipEntry::COMPRESS_METHOD_STORED); // no compression
$zip->addFromString($localName, $contents,  \Nelexa\Zip\ZipEntry::COMPRESS_METHOD_DEFLATED);
```
Update timestamp for all files
```php
$timestamp = time(); // now time
$zip->updateTimestamp($timestamp);
```
Delete files from glob pattern
```php
$zip->deleteGlob("*.jpg"); // remove all jpg files
```
Delete files from regex pattern
```php
$zip->deletePattern("~\.jpg$~i"); // remove all jpg files
```
Delete file from index
```php
$zip->deleteIndex(0);
```
Delete all files
```php
$zip->deleteAll();
```
Delete from file name
```php
$zip->deleteName($filename);
```
Extract zip archive
```php
$zip->extractTo($toPath)
$zip->extractTo($toPath, array("file1", "file2")); // extract only files file1 and file2
```
Get archive comment
```php
$archiveComment = $zip->getArchiveComment();
```
Set archive comment
```php
$zip->setArchiveComment($comment)
```
Get comment file from index
```php
$commentFile = $zip->getCommentIndex($index);
```
Set comment file from index
```php
$zip->setCommentIndex($index, $comment);
```
Get comment file from filename
```php
$commentFile = $zip->getCommentName($filename);
```
Set comment file from filename
```php
$zip->setCommentName($name, $comment);
```
Get file content from index
```php
$content = $zip->getFromIndex($index);
```
Get file content from filename
```php
$content = $zip->getFromName($name);
```
Get filename from index
```php
$filename = $zip->getNameIndex($index);
```
Rename file from index
```php
$zip->renameIndex($index, $newFilename);
```
Rename file from filename
```php
$zip->renameName($oldName, $newName);
```
Get zip entries
```php
/**
 * @var \Nelexa\Zip\ZipEntry[] $zipEntries
 */
$zipEntries = $zip->getZipEntries();
```
Get zip entry from index
```php
/**
 * @var \Nelexa\Zip\ZipEntry $zipEntry
 */
$zipEntry = $zip->getZipEntryIndex($index);
```
Get zip entry from filename
```php
/**
 * @var \Nelexa\Zip\ZipEntry $zipEntry
 */
$zipEntry = $zip->getZipEntryName($name);
```
Get info from index
```php
$info = $zip->statIndex($index);
// [
//     'name' - filename
//     'index' - index number
//     'crc' - crc32
//     'size' - uncompressed size
//     'mtime' - last modify date time
//     'comp_size' - compressed size
//     'comp_method' - compressed method
// ]
```
Get info from name
```php
$info = $zip->statName($name);
// [
//     'name' - filename
//     'index' - index number
//     'crc' - crc32
//     'size' - uncompressed size
//     'mtime' - last modify date time
//     'comp_size' - compressed size
//     'comp_method' - compressed method
// ]
```
Get info from all files
```php
$info = $zip->getExtendedListFiles();
```
Get output contents
```php
$content = $zip->output();
```
Save opened file
```php
$isSuccessSave = $zip->save();
```
Save file as
```php
$zip->saveAs($outputFile);
```
Close archive
```php
$zip->close();
```

### Example create zip archive
```php
$zip = new \Nelexa\Zip\ZipFile();
$zip->create();
$zip->addFile("README.md");
$zip->addFile("README.md", "folder/README");
$zip->addFromString("folder/file.txt", "File content");
$zip->addEmptyDir("f/o/l/d/e/r");
$zip->setArchiveComment("Archive comment");
$zip->setCommentIndex(0, "Comment file with index 0");
$zip->saveAs("output.zip");
$zip->close();

// $ zipinfo output.zip
// Archive:  output.zip
// Zip file size: 912 bytes, number of entries: 4
// -rw----     1.0 fat      387 b- defN README.md
// -rw----     1.0 fat      387 b- defN folder/README
// -rw----     1.0 fat       12 b- defN folder/file.txt
// -rw----     1.0 fat        0 b- stor f/o/l/d/e/r/
// 4 files, 786 bytes uncompressed, 448 bytes compressed:  43.0%
```

### Example modification zip archive
```php
$zip = new \Nelexa\Zip\ZipFile();
$zip->open("output.zip");
$zip->addFromString("new-file", file_get_contents(__FILE__));
$zip->saveAs("output2.zip");
$zip->close();

// $ zipinfo output2.zip 
// Archive:  output2.zip
// Zip file size: 1331 bytes, number of entries: 5
// -rw----     1.0 fat      387 b- defN README.md
// -rw----     1.0 fat      387 b- defN folder/README
// -rw----     1.0 fat       12 b- defN folder/file.txt
// -rw----     1.0 fat        0 b- stor f/o/l/d/e/r/
// -rw----     1.0 fat      593 b- defN new-file
// 5 files, 1379 bytes uncompressed, 775 bytes compressed:  43.8%
```