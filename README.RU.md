<h1 align="center"><img src="logo.svg" alt="PhpZip" width="250" height="51"></h1>

`PhpZip` - php библиотека для продвинутой работы с ZIP-архивами.

[![Packagist Version](https://img.shields.io/packagist/v/nelexa/zip.svg)](https://packagist.org/packages/nelexa/zip)
[![Packagist Downloads](https://img.shields.io/packagist/dt/nelexa/zip.svg?color=%23ff007f)](https://packagist.org/packages/nelexa/zip)
[![Code Coverage](https://scrutinizer-ci.com/g/Ne-Lexa/php-zip/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/Ne-Lexa/php-zip/?branch=master)
[![Build Status](https://github.com/Ne-Lexa/php-zip/workflows/build/badge.svg)](https://github.com/Ne-Lexa/php-zip/actions)
[![License](https://img.shields.io/packagist/l/nelexa/zip.svg)](https://github.com/Ne-Lexa/php-zip/blob/master/LICENSE)


[English Documentation](README.md)

Содержание
----------
- [Функционал](#функционал)
- [Требования](#требования)
- [Установка](#установка)
- [Примеры](#примеры)
- [Глоссарий](#глоссарий)
- [Документация](#документация)
  + [Обзор методов класса `\PhpZip\ZipFile`](#обзор-методов-класса-phpzipzipfile)
  + [Создание/Открытие ZIP-архива](#созданиеоткрытие-zip-архива)
  + [Чтение записей из архива](#чтение-записей-из-архива)
  + [Перебор записей/Итератор](#перебор-записейитератор)
  + [Получение информации о записях](#получение-информации-о-записях)
  + [Добавление записей в архив](#добавление-записей-в-архив)
  + [Удаление записей из архива](#удаление-записей-из-архива)
  + [Работа с записями и с архивом](#работа-с-записями-и-с-архивом)
  + [Работа с паролями](#работа-с-паролями)
  + [Отмена изменений](#отмена-изменений)
  + [Сохранение файла или вывод в браузер](#сохранение-файла-или-вывод-в-браузер)
  + [Закрытие архива](#закрытие-архива)
- [Запуск тестов](#запуск-тестов)
- [История изменений](#история-изменений)
- [Обновление версий](#обновление-версий)
  + [Обновление с версии 3 до версии 4](#обновление-с-версии-3-до-версии-4)
  + [Обновление с версии 2 до версии 3](#обновление-с-версии-2-до-версии-3)

### Функционал
- Открытие и разархивирование ZIP-архивов.
- Создание ZIP-архивов.
- Модификация ZIP-архивов.
- Чистый php (не требуется расширение `php-zip` и класс `\ZipArchive`).
- Поддерживается сохранение архива в файл, вывод архива в браузер или вывод в виде строки, без сохранения в файл.
- Поддерживаются комментарии архива и комментарии отдельных записей.
- Получение подробной информации о каждой записи в архиве.
- Поддерживаются только следующие методы сжатия:
  + Без сжатия (Stored).
  + Deflate сжатие.
  + BZIP2 сжатие при наличии расширения `php-bz2`.
- Поддержка `ZIP64` (размер файла более 4 GB или количество записей в архиве более 65535).
- Работа с паролями
  > **Внимание!**
  >
  > Для 32-bit систем, в данный момент не поддерживается метод шифрование `Traditional PKWARE Encryption (ZipCrypto)`. 
  > Используйте метод шифрования `WinZIP AES Encryption`, когда это возможно.
  + Установка пароля для чтения архива глобально или для некоторых записей.
  + Изменение пароля архива, в том числе и для отдельных записей.
  + Удаление пароля архива глобально или для отдельных записей.
  + Установка пароля и/или метода шифрования, как для всех, так и для отдельных записей в архиве.
  + Установка разных паролей и методов шифрования для разных записей.
  + Удаление пароля для всех или для некоторых записей.
  + Поддержка методов шифрования `Traditional PKWARE Encryption (ZipCrypto)` и `WinZIP AES Encryption (128, 192 или 256 bit)`.
  + Установка метода шифрования для всех или для отдельных записей в архиве.

### Требования
- `PHP` 7.4 или ^8.0 (предпочтительно 64-bit).
- Опционально php-расширение `bzip2` для поддержки BZIP2 компрессии.
- Опционально php-расширение `openssl` для `WinZip Aes Encryption` шифрования.

### Установка
`composer require nelexa/zip`

Последняя стабильная версия: [![Latest Stable Version](https://poser.pugx.org/nelexa/zip/v/stable)](https://packagist.org/packages/nelexa/zip)

### Примеры
```php
// создание нового архива
$zipFile = new \PhpZip\ZipFile();
try{
    $zipFile
        ->addFromString('zip/entry/filename', "Is file content") // добавить запись из строки
        ->addFile('/path/to/file', 'data/tofile') // добавить запись из файла
        ->addDir(__DIR__, 'to/path/') // добавить файлы из директории
        ->saveAsFile($outputFilename) // сохранить архив в файл
        ->close(); // закрыть архив
            
    // открытие архива, извлечение файлов, удаление файлов, добавление файлов, установка пароля и вывод архива в браузер.
    $zipFile
        ->openFile($outputFilename) // открыть архив из файла
        ->extractTo($outputDirExtract) // извлечь файлы в заданную директорию
        ->deleteFromRegex('~^\.~') // удалить все скрытые (Unix) файлы
        ->addFromString('dir/file.txt', 'Test file') // добавить новую запись из строки
        ->setPassword('password') // установить пароль на все записи
        ->outputAsAttachment('library.jar'); // вывести в браузер без сохранения в файл
}
catch(\PhpZip\Exception\ZipException $e){
    // обработка исключения
}
finally{
    $zipFile->close();
}
```
Другие примеры можно посмотреть в папке `tests/`.

### Глоссарий
**Запись в ZIP-архиве (Zip Entry)** - файл или папка в ZIP-архиве. У каждой записи в архиве есть определённые свойства, например: имя файла, метод сжатия, метод шифрования, размер файла до сжатия, размер файла после сжатия, CRC32 и другие.

### Документация
#### Обзор методов класса `\PhpZip\ZipFile`
- [ZipFile::__construct](#zipfile__construct) - инициализирует ZIP-архив.
- [ZipFile::addAll](#zipfileaddall) - добавляет все записи из массива.
- [ZipFile::addDir](#zipfileadddir) - добавляет файлы из директории по указанному пути без вложенных директорий.
- [ZipFile::addDirRecursive](#zipfileadddirrecursive) - добавляет файлы из директории по указанному пути с вложенными директориями.
- [ZipFile::addEmptyDir](#zipfileaddemptydir) - добавляет в ZIP-архив новую директорию.
- [ZipFile::addFile](#zipfileaddfile) - добавляет в ZIP-архив файл по указанному пути.
- [ZipFile::addSplFile](#zipfileaddsplfile) - добавляет объект `\SplFileInfo` в zip-архив.
- [ZipFile::addFromFinder](#zipfileaddfromfinder) - добавляет файлы из `Symfony\Component\Finder\Finder` в zip архив.
- [ZipFile::addFilesFromIterator](#zipfileaddfilesfromiterator) - добавляет файлы из итератора директорий.
- [ZipFile::addFilesFromGlob](#zipfileaddfilesfromglob) - добавляет файлы из директории в соответствии с glob шаблоном без вложенных директорий.
- [ZipFile::addFilesFromGlobRecursive](#zipfileaddfilesfromglobrecursive) - добавляет файлы из директории в соответствии с glob шаблоном c вложенными директориями.
- [ZipFile::addFilesFromRegex](#zipfileaddfilesfromregex) - добавляет файлы из директории в соответствии с регулярным выражением без вложенных директорий.
- [ZipFile::addFilesFromRegexRecursive](#zipfileaddfilesfromregexrecursive) - добавляет файлы из директории в соответствии с регулярным выражением с вложенными директориями.
- [ZipFile::addFromStream](#zipfileaddfromstream) - добавляет в ZIP-архив запись из потока.
- [ZipFile::addFromString](#zipfileaddfromstring) - добавляет файл в ZIP-архив, используя его содержимое в виде строки.
- [ZipFile::close](#zipfileclose) - закрывает ZIP-архив.
- [ZipFile::count](#zipfilecount) - возвращает количество записей в архиве.
- [ZipFile::deleteFromName](#zipfiledeletefromname) - удаляет запись по имени.
- [ZipFile::deleteFromGlob](#zipfiledeletefromglob) - удаляет записи в соответствии с glob шаблоном.
- [ZipFile::deleteFromRegex](#zipfiledeletefromregex) - удаляет записи в соответствии с регулярным выражением.
- [ZipFile::deleteAll](#zipfiledeleteall) - удаляет все записи в ZIP-архиве.
- [ZipFile::disableEncryption](#zipfiledisableencryption) - отключает шифрования всех записей, находящихся в архиве.
- [ZipFile::disableEncryptionEntry](#zipfiledisableencryptionentry) - отключает шифрование записи по её имени.
- [ZipFile::extractTo](#zipfileextractto) - извлекает содержимое архива в заданную директорию.
- [ZipFile::getArchiveComment](#zipfilegetarchivecomment) - возвращает комментарий ZIP-архива.
- [ZipFile::getEntryComment](#zipfilegetentrycomment) - возвращает комментарий к записи, используя её имя.
- [ZipFile::getEntryContent](#zipfilegetentrycontent) - возвращает содержимое записи.
- [ZipFile::getListFiles](#zipfilegetlistfiles) - возвращает список файлов архива.
- [ZipFile::hasEntry](#zipfilehasentry) - проверяет, присутствует ли запись в архиве.
- [ZipFile::isDirectory](#zipfileisdirectory) - проверяет, является ли запись в архиве директорией.
- [ZipFile::matcher](#zipfilematcher) - выборка записей в архиве для проведения операций над выбранными записями.
- [ZipFile::openFile](#zipfileopenfile) - открывает ZIP-архив из файла.
- [ZipFile::openFromString](#zipfileopenfromstring) - открывает ZIP-архив из строки.
- [ZipFile::openFromStream](#zipfileopenfromstream) - открывает ZIP-архив из потока.
- [ZipFile::outputAsAttachment](#zipfileoutputasattachment) - выводит ZIP-архив в браузер.
- [ZipFile::outputAsPsr7Response](#zipfileoutputaspsr7response) - выводит ZIP-архив, как PSR-7 Response.
- [ZipFile::outputAsSymfonyResponse](#zipfileoutputassymfonyresponse) - выводит ZIP-архив, как Symfony Response.
- [ZipFile::outputAsString](#zipfileoutputasstring) - выводит ZIP-архив в виде строки.
- [ZipFile::rename](#zipfilerename) - переименовывает запись по имени.
- [ZipFile::rewrite](#zipfilerewrite) - сохраняет изменения и заново открывает изменившийся архив.
- [ZipFile::saveAsFile](#zipfilesaveasfile) - сохраняет архив в файл.
- [ZipFile::saveAsStream](#zipfilesaveasstream) - записывает архив в поток.
- [ZipFile::setArchiveComment](#zipfilesetarchivecomment) - устанавливает комментарий к ZIP-архиву.
- [ZipFile::setCompressionLevel](#zipfilesetcompressionlevel) - устанавливает уровень сжатия для всех файлов, находящихся в архиве.
- [ZipFile::setCompressionLevelEntry](#zipfilesetcompressionlevelentry) - устанавливает уровень сжатия для определённой записи в архиве.
- [ZipFile::setCompressionMethodEntry](#zipfilesetcompressionmethodentry) - устанавливает метод сжатия для определённой записи в архиве.
- [ZipFile::setEntryComment](#zipfilesetentrycomment) - устанавливает комментарий к записи, используя её имя.
- [ZipFile::setReadPassword](#zipfilesetreadpassword) - устанавливает пароль на чтение открытого запароленного архива для всех зашифрованных записей.
- [ZipFile::setReadPasswordEntry](#zipfilesetreadpasswordentry) - устанавливает пароль на чтение конкретной зашифрованной записи открытого запароленного архива.
- [ZipFile::setPassword](#zipfilesetpassword) - устанавливает новый пароль для всех файлов, находящихся в архиве.
- [ZipFile::setPasswordEntry](#zipfilesetpasswordentry) - устанавливает новый пароль для конкретного файла.
- [ZipFile::unchangeAll](#zipfileunchangeall) - отменяет все изменения, сделанные в архиве.
- [ZipFile::unchangeArchiveComment](#zipfileunchangearchivecomment) - отменяет изменения в комментарии к архиву.
- [ZipFile::unchangeEntry](#zipfileunchangeentry) - отменяет изменения для конкретной записи архива.

#### Создание/Открытие ZIP-архива
##### ZipFile::__construct
Инициализирует ZIP-архив.
```php
$zipFile = new \PhpZip\ZipFile();
```
##### ZipFile::openFile
Открывает ZIP-архив из файла.
```php
$zipFile = new \PhpZip\ZipFile();
$zipFile->openFile('file.zip');
```
##### ZipFile::openFromString
Открывает ZIP-архив из строки.
```php
$zipFile = new \PhpZip\ZipFile();
$zipFile->openFromString($stringContents);
```
##### ZipFile::openFromStream
Открывает ZIP-архив из потока.
```php
$stream = fopen('file.zip', 'rb');

$zipFile = new \PhpZip\ZipFile();
$zipFile->openFromStream($stream);
```
#### Чтение записей из архива
##### ZipFile::count
Возвращает количество записей в архиве.
```php
$zipFile = new \PhpZip\ZipFile();

$count = count($zipFile);
// или
$count = $zipFile->count();
```
##### ZipFile::getListFiles
Возвращает список файлов архива.
```php
$zipFile = new \PhpZip\ZipFile();
$listFiles = $zipFile->getListFiles();

// Пример содержимого массива:
// array (
//   0 => 'info.txt',
//   1 => 'path/to/file.jpg',
//   2 => 'another path/',
// )
```
##### ZipFile::getEntryContent
Возвращает содержимое записи.
```php
// $entryName = 'path/to/example-entry-name.txt';
$zipFile = new \PhpZip\ZipFile();

$contents = $zipFile[$entryName];
// или
$contents = $zipFile->getEntryContents($entryName);
```
##### ZipFile::hasEntry
Проверяет, присутствует ли запись в архиве.
```php
// $entryName = 'path/to/example-entry-name.txt';
$zipFile = new \PhpZip\ZipFile();

$hasEntry = isset($zipFile[$entryName]);
// или
$hasEntry = $zipFile->hasEntry($entryName);
```
##### ZipFile::isDirectory
Проверяет, является ли запись в архиве директорией.
```php
// $entryName = 'path/to/';
$zipFile = new \PhpZip\ZipFile();

$isDirectory = $zipFile->isDirectory($entryName);
```
##### ZipFile::extractTo
Извлекает содержимое архива в заданную директорию.
Директория должна существовать.
```php
$zipFile = new \PhpZip\ZipFile();
$zipFile->extractTo($directory);
```
Можно извлечь только некоторые записи в заданную директорию.
Директория должна существовать.
```php
$extractOnlyFiles = [
    'filename1', 
    'filename2', 
    'dir/dir/dir/'
];
$zipFile = new \PhpZip\ZipFile();
$zipFile->extractTo($toDirectory, $extractOnlyFiles);
```
#### Перебор записей/Итератор
`ZipFile` является итератором.
Можно перебрать все записи, через цикл `foreach`.
```php
foreach($zipFile as $entryName => $contents){
    echo "Файл: $entryName" . PHP_EOL;
    echo "Содержимое: $contents" . PHP_EOL;
    echo '-----------------------------' . PHP_EOL;
}
```
Можно использовать паттерн `Iterator`.
```php
$iterator = new \ArrayIterator($zipFile);
while ($iterator->valid())
{
    $entryName = $iterator->key();
    $contents = $iterator->current();

    echo "Файл: $entryName" . PHP_EOL;
    echo "Содержимое: $contents" . PHP_EOL;
    echo '-----------------------------' . PHP_EOL;

    $iterator->next();
}
```
#### Получение информации о записях
##### ZipFile::getArchiveComment
Возвращает комментарий ZIP-архива.
```php
$commentArchive = $zipFile->getArchiveComment();
```
##### ZipFile::getEntryComment
Возвращает комментарий к записи, используя её имя.
```php
$commentEntry = $zipFile->getEntryComment($entryName);
```
#### Добавление записей в архив

Все методы добавления записей в ZIP-архив позволяют указать метод сжатия содержимого.

Доступны следующие методы сжатия:
- `\PhpZip\Constants\ZipCompressionMethod::STORED` - без сжатия
- `\PhpZip\Constants\ZipCompressionMethod::DEFLATED` - Deflate сжатие
- `\PhpZip\Constants\ZipCompressionMethod::BZIP2` - Bzip2 сжатие при наличии расширения `ext-bz2`

##### ZipFile::addFile
Добавляет в ZIP-архив файл по указанному пути из файловой системы.
```php
$zipFile = new \PhpZip\ZipFile();
// $file = '...../file.ext'; 
$zipFile->addFile($file);

// можно указать имя записи в архиве (если null, то используется последний компонент из имени файла)
$zipFile->addFile($file, $entryName);

// можно указать метод сжатия
$zipFile->addFile($file, $entryName, \PhpZip\Constants\ZipCompressionMethod::STORED); // Без сжатия
$zipFile->addFile($file, $entryName, \PhpZip\Constants\ZipCompressionMethod::DEFLATED); // Deflate сжатие
$zipFile->addFile($file, $entryName, \PhpZip\Constants\ZipCompressionMethod::BZIP2); // BZIP2 сжатие
```
##### ZipFile::addSplFile"
Добавляет объект `\SplFileInfo` в zip-архив.
```php
// $file = '...../file.ext'; 
// $entryName = 'file2.ext'
$zipFile = new \PhpZip\ZipFile();

$splFile = new \SplFileInfo('README.md');

$zipFile->addSplFile($splFile);
$zipFile->addSplFile($splFile, $entryName);
// or
$zipFile[$entryName] = new \SplFileInfo($file);

// установить метод сжатия
$zipFile->addSplFile($splFile, $entryName, $options = [
    \PhpZip\Constants\ZipOptions::COMPRESSION_METHOD => \PhpZip\Constants\ZipCompressionMethod::DEFLATED,
]);
```
##### ZipFile::addFromFinder"
Добавляет файлы из [`Symfony\Component\Finder\Finder`](https://symfony.com/doc/current/components/finder.html) в zip архив.
```php
$finder = new \Symfony\Component\Finder\Finder();
$finder
    ->files()
    ->name('*.{jpg,jpeg,gif,png}')
    ->name('/^[0-9a-f]\./')
    ->contains('/lorem\s+ipsum$/i')
    ->in('path');

$zipFile = new \PhpZip\ZipFile();
$zipFile->addFromFinder($finder, $options = [
    \PhpZip\Constants\ZipOptions::COMPRESSION_METHOD => \PhpZip\Constants\ZipCompressionMethod::DEFLATED,
    \PhpZip\Constants\ZipOptions::MODIFIED_TIME => new \DateTimeImmutable('-1 day 5 min')
]);
```
##### ZipFile::addFromString
Добавляет файл в ZIP-архив, используя его содержимое в виде строки.
```php
$zipFile = new \PhpZip\ZipFile();

$zipFile[$entryName] = $contents;
// или
$zipFile->addFromString($entryName, $contents);

// можно указать метод сжатия
$zipFile->addFromString($entryName, $contents, \PhpZip\Constants\ZipCompressionMethod::STORED); // Без сжатия
$zipFile->addFromString($entryName, $contents, \PhpZip\Constants\ZipCompressionMethod::DEFLATED); // Deflate сжатие
$zipFile->addFromString($entryName, $contents, \PhpZip\Constants\ZipCompressionMethod::BZIP2); // BZIP2 сжатие
```
##### ZipFile::addFromStream
Добавляет в ZIP-архив запись из потока.
```php
// $stream = fopen(..., 'rb');

$zipFile->addFromStream($stream, $entryName);

// можно указать метод сжатия
$zipFile->addFromStream($stream, $entryName, \PhpZip\Constants\ZipCompressionMethod::STORED); // Без сжатия
$zipFile->addFromStream($stream, $entryName, \PhpZip\Constants\ZipCompressionMethod::DEFLATED); // Deflate сжатие
$zipFile->addFromStream($stream, $entryName, \PhpZip\Constants\ZipCompressionMethod::BZIP2); // BZIP2 сжатие
```
##### ZipFile::addEmptyDir
Добавляет в ZIP-архив новую (пустую) директорию.
```php
// $path = "path/to/";

$zipFile->addEmptyDir($path);
// или
$zipFile[$path] = null;
```
##### ZipFile::addAll
Добавляет все записи из массива.
```php
$entries = [
    'file.txt' => 'file contents', // запись из строки данных
    'empty dir/' => null, // пустой каталог
    'path/to/file.jpg' => fopen('..../filename', 'rb'), // запись из потока
    'path/to/file.dat' => new \SplFileInfo('..../filename'), // запись из файла
];

$zipFile->addAll($entries);
```
##### ZipFile::addDir
Добавляет файлы из директории по указанному пути без вложенных директорий.
```php
$zipFile->addDir($dirName);

// можно указать путь в архиве в который необходимо поместить записи
$localPath = "to/path/";
$zipFile->addDir($dirName, $localPath);

// можно указать метод сжатия
$zipFile->addDir($dirName, $localPath, \PhpZip\Constants\ZipCompressionMethod::STORED); // Без сжатия
$zipFile->addDir($dirName, $localPath, \PhpZip\Constants\ZipCompressionMethod::DEFLATED); // Deflate сжатие
$zipFile->addDir($dirName, $localPath, \PhpZip\Constants\ZipCompressionMethod::BZIP2); // BZIP2 сжатие
```
##### ZipFile::addDirRecursive
Добавляет файлы из директории по указанному пути c вложенными директориями.
```php
$zipFile->addDirRecursive($dirName);

// можно указать путь в архиве в который необходимо поместить записи
$localPath = "to/path/";
$zipFile->addDirRecursive($dirName, $localPath);

// можно указать метод сжатия
$zipFile->addDirRecursive($dirName, $localPath, \PhpZip\Constants\ZipCompressionMethod::STORED); // Без сжатия
$zipFile->addDirRecursive($dirName, $localPath, \PhpZip\Constants\ZipCompressionMethod::DEFLATED); // Deflate сжатие
$zipFile->addDirRecursive($dirName, $localPath, \PhpZip\Constants\ZipCompressionMethod::BZIP2); // BZIP2 сжатие
```
##### ZipFile::addFilesFromIterator
Добавляет файлы из итератора директорий.
```php
// $directoryIterator = new \DirectoryIterator($dir); // без вложенных директорий
// $directoryIterator = new \RecursiveDirectoryIterator($dir); // с вложенными директориями

$zipFile->addFilesFromIterator($directoryIterator);

// можно указать путь в архиве в который необходимо поместить записи
$localPath = "to/path/";
$zipFile->addFilesFromIterator($directoryIterator, $localPath);
// или
$zipFile[$localPath] = $directoryIterator;

// можно указать метод сжатия
$zipFile->addFilesFromIterator($directoryIterator, $localPath, \PhpZip\Constants\ZipCompressionMethod::STORED); // Без сжатия
$zipFile->addFilesFromIterator($directoryIterator, $localPath, \PhpZip\Constants\ZipCompressionMethod::DEFLATED); // Deflate сжатие
$zipFile->addFilesFromIterator($directoryIterator, $localPath, \PhpZip\Constants\ZipCompressionMethod::BZIP2); // BZIP2 сжатие
```
Пример добавления файлов из директории в архив с игнорированием некоторых файлов при помощи итератора директорий.
```php
$ignoreFiles = [
    "file_ignore.txt", 
    "dir_ignore/sub dir ignore/"
];

// $directoryIterator = new \DirectoryIterator($dir); // без вложенных директорий
// $directoryIterator = new \RecursiveDirectoryIterator($dir); // с вложенными директориями
 
// используйте \PhpZip\Util\Iterator\IgnoreFilesFilterIterator для не рекурсивного поиска
$ignoreIterator = new \PhpZip\Util\Iterator\IgnoreFilesRecursiveFilterIterator(
    $directoryIterator, 
    $ignoreFiles
);

$zipFile->addFilesFromIterator($ignoreIterator);
```
##### ZipFile::addFilesFromGlob
Добавляет файлы из директории в соответствии с [glob шаблоном](https://en.wikipedia.org/wiki/Glob_(programming)) без вложенных директорий.
```php
$globPattern = '**.{jpg,jpeg,png,gif}'; // пример glob шаблона -> добавить все .jpg, .jpeg, .png и .gif файлы

$zipFile->addFilesFromGlob($dir, $globPattern);

// можно указать путь в архиве в который необходимо поместить записи
$localPath = "to/path/";
$zipFile->addFilesFromGlob($dir, $globPattern, $localPath);

// можно указать метод сжатия
$zipFile->addFilesFromGlob($dir, $globPattern, $localPath, \PhpZip\Constants\ZipCompressionMethod::STORED); // Без сжатия
$zipFile->addFilesFromGlob($dir, $globPattern, $localPath, \PhpZip\Constants\ZipCompressionMethod::DEFLATED); // Deflate сжатие
$zipFile->addFilesFromGlob($dir, $globPattern, $localPath, \PhpZip\Constants\ZipCompressionMethod::BZIP2); // BZIP2 сжатие
```
##### ZipFile::addFilesFromGlobRecursive
Добавляет файлы из директории в соответствии с [glob шаблоном](https://en.wikipedia.org/wiki/Glob_(programming)) c вложенными директориями.
```php
$globPattern = '**.{jpg,jpeg,png,gif}'; // пример glob шаблона -> добавить все .jpg, .jpeg, .png и .gif файлы

$zipFile->addFilesFromGlobRecursive($dir, $globPattern);

// можно указать путь в архиве в который необходимо поместить записи
$localPath = "to/path/";
$zipFile->addFilesFromGlobRecursive($dir, $globPattern, $localPath);

// можно указать метод сжатия
$zipFile->addFilesFromGlobRecursive($dir, $globPattern, $localPath, \PhpZip\Constants\ZipCompressionMethod::STORED); // Без сжатия
$zipFile->addFilesFromGlobRecursive($dir, $globPattern, $localPath, \PhpZip\Constants\ZipCompressionMethod::DEFLATED); // Deflate сжатие
$zipFile->addFilesFromGlobRecursive($dir, $globPattern, $localPath, \PhpZip\Constants\ZipCompressionMethod::BZIP2); // BZIP2 сжатие
```
##### ZipFile::addFilesFromRegex
Добавляет файлы из директории в соответствии с [регулярным выражением](https://en.wikipedia.org/wiki/Regular_expression) без вложенных директорий.
```php
$regexPattern = '/\.(jpe?g|png|gif)$/si'; // пример регулярного выражения -> добавить все .jpg, .jpeg, .png и .gif файлы

$zipFile->addFilesFromRegex($dir, $regexPattern);

// можно указать путь в архиве в который необходимо поместить записи
$localPath = "to/path/";
$zipFile->addFilesFromRegex($dir, $regexPattern, $localPath);

// можно указать метод сжатия
$zipFile->addFilesFromRegex($dir, $regexPattern, $localPath, \PhpZip\Constants\ZipCompressionMethod::STORED); // Без сжатия
$zipFile->addFilesFromRegex($dir, $regexPattern, $localPath, \PhpZip\Constants\ZipCompressionMethod::DEFLATED); // Deflate сжатие
$zipFile->addFilesFromRegex($dir, $regexPattern, $localPath, \PhpZip\Constants\ZipCompressionMethod::BZIP2); // BZIP2 сжатие
```
##### ZipFile::addFilesFromRegexRecursive
Добавляет файлы из директории в соответствии с [регулярным выражением](https://en.wikipedia.org/wiki/Regular_expression) с вложенными директориями.
```php
$regexPattern = '/\.(jpe?g|png|gif)$/si'; // пример регулярного выражения -> добавить все .jpg, .jpeg, .png и .gif файлы

$zipFile->addFilesFromRegexRecursive($dir, $regexPattern);

// можно указать путь в архиве в который необходимо поместить записи
$localPath = "to/path/";
$zipFile->addFilesFromRegexRecursive($dir, $regexPattern, $localPath);

// можно указать метод сжатия
$zipFile->addFilesFromRegexRecursive($dir, $regexPattern, $localPath, \PhpZip\Constants\ZipCompressionMethod::STORED); // Без сжатия
$zipFile->addFilesFromRegexRecursive($dir, $regexPattern, $localPath, \PhpZip\Constants\ZipCompressionMethod::DEFLATED); // Deflate сжатие
$zipFile->addFilesFromRegexRecursive($dir, $regexPattern, $localPath, \PhpZip\Constants\ZipCompressionMethod::BZIP2); // BZIP2 сжатие
```
#### Удаление записей из архива
##### ZipFile::deleteFromName
Удаляет запись по имени.
```php
$zipFile->deleteFromName($entryName);
```
##### ZipFile::deleteFromGlob
Удаляет записи в соответствии с [glob шаблоном](https://en.wikipedia.org/wiki/Glob_(programming)).
```php
$globPattern = '**.{jpg,jpeg,png,gif}'; // пример glob шаблона -> удалить все .jpg, .jpeg, .png и .gif файлы

$zipFile->deleteFromGlob($globPattern);
```
##### ZipFile::deleteFromRegex
Удаляет записи в соответствии с [регулярным выражением](https://en.wikipedia.org/wiki/Regular_expression).
```php
$regexPattern = '/\.(jpe?g|png|gif)$/si'; // пример регулярному выражения -> удалить все .jpg, .jpeg, .png и .gif файлы

$zipFile->deleteFromRegex($regexPattern);
```
##### ZipFile::deleteAll
Удаляет все записи в ZIP-архиве.
```php
$zipFile->deleteAll();
```
#### Работа с записями и с архивом
##### ZipFile::rename
Переименовывает запись по имени.
```php
$zipFile->rename($oldName, $newName);
```
##### ZipFile::setCompressionLevel
Устанавливает уровень сжатия для всех файлов, находящихся в архиве.

> _Обратите внимание, что действие данного метода не распространяется на записи, добавленные после выполнения этого метода._

По умолчанию используется уровень сжатия 5 (`\PhpZip\Constants\ZipCompressionLevel::NORMAL`) или уровень сжатия, определённый в архиве для Deflate сжатия.

Поддерживаются диапазон значений от 1 (`\PhpZip\Constants\ZipCompressionLevel::SUPER_FAST`) до 9 (`\PhpZip\Constants\ZipCompressionLevel::MAXIMUM`). Чем выше число, тем лучше и дольше сжатие.
```php
$zipFile->setCompressionLevel(\PhpZip\Constants\ZipCompressionLevel::MAXIMUM);
```
##### ZipFile::setCompressionLevelEntry
Устанавливает уровень сжатия для определённой записи в архиве.

Поддерживаются диапазон значений от 1 (`\PhpZip\Constants\ZipCompressionLevel::SUPER_FAST`) до 9 (`\PhpZip\Constants\ZipCompressionLevel::MAXIMUM`). Чем выше число, тем лучше и дольше сжатие.
```php
$zipFile->setCompressionLevelEntry($entryName, \PhpZip\Constants\ZipCompressionLevel::MAXIMUM);
```
##### ZipFile::setCompressionMethodEntry
Устанавливает метод сжатия для определённой записи в архиве.

Доступны следующие методы сжатия:
- `\PhpZip\Constants\ZipCompressionMethod::STORED` - без сжатия
- `\PhpZip\Constants\ZipCompressionMethod::DEFLATED` - Deflate сжатие
- `\PhpZip\Constants\ZipCompressionMethod::BZIP2` - Bzip2 сжатие при наличии расширения `ext-bz2`
```php
$zipFile->setCompressionMethodEntry($entryName, \PhpZip\Constants\ZipCompressionMethod::DEFLATED);
```
##### ZipFile::setArchiveComment
Устанавливает комментарий к ZIP-архиву.
```php
$zipFile->setArchiveComment($commentArchive);
```
##### ZipFile::setEntryComment
Устанавливает комментарий к записи, используя её имя.
```php
$zipFile->setEntryComment($entryName, $comment);
```
##### ZipFile::matcher
Выборка записей в архиве для проведения операций над выбранными записями.
```php
$matcher = $zipFile->matcher();
```
Выбор файлов из архива по одному:
```php
$matcher
    ->add('entry name')
    ->add('another entry');
```
Выбор нескольких файлов в архиве:
```php
$matcher->add([
    'entry name',
    'another entry name',
    'path/'
]);
```
Выбор файлов по регулярному выражению:
```php
$matcher->match('~\.jpe?g$~i');
```
Выбор всех файлов в архиве:
```php
$matcher->all();
```
count() - получает количество выбранных записей:
```php
$count = count($matcher);
// или
$count = $matcher->count();
```
getMatches() - получает список выбранных записей:
```php
$entries = $matcher->getMatches();
// пример содержимого: ['entry name', 'another entry name'];
```
invoke() - выполняет пользовательскую функцию над выбранными записями:
```php
// пример
$matcher->invoke(function($entryName) use($zipFile) {
    $newName = preg_replace('~\.(jpe?g)$~i', '.no_optimize.$1', $entryName);
    $zipFile->rename($entryName, $newName);
});
```
Функции для работы над выбранными записями:
```php
$matcher->delete(); // удалет выбранные записи из ZIP-архива
$matcher->setPassword($password); // устанавливает новый пароль на выбранные записи
$matcher->setPassword($password, $encryptionMethod); // устанавливает новый пароль и метод шифрования на выбранные записи
$matcher->setEncryptionMethod($encryptionMethod); // устанавливает метод шифрования на выбранные записи
$matcher->disableEncryption(); // отключает шифрование для выбранных записей
```
#### Работа с паролями

Реализована поддержка методов шифрования:
- `\PhpZip\Constants\ZipEncryptionMethod::PKWARE` - Traditional PKWARE encryption
- `\PhpZip\Constants\ZipEncryptionMethod::WINZIP_AES_256` - WinZip AES encryption 256 bit (рекомендуемое)
- `\PhpZip\Constants\ZipEncryptionMethod::WINZIP_AES_192` - WinZip AES encryption 192 bit
- `\PhpZip\Constants\ZipEncryptionMethod::WINZIP_AES_128` - WinZip AES encryption 128 bit

##### ZipFile::setReadPassword
Устанавливает пароль на чтение открытого запароленного архива для всех зашифрованных записей.

> _Установка пароля не является обязательной для добавления новых записей или удаления существующих, но если вы захотите извлечь контент или изменить метод/уровень сжатия, метод шифрования или изменить пароль, то в этом случае пароль необходимо указать._
```php
$zipFile->setReadPassword($password);
```
##### ZipFile::setReadPasswordEntry
Устанавливает пароль на чтение конкретной зашифрованной записи открытого запароленного архива.
```php
$zipFile->setReadPasswordEntry($entryName, $password);
```
##### ZipFile::setPassword
Устанавливает новый пароль для всех файлов, находящихся в архиве.

> _Обратите внимание, что действие данного метода не распространяется на записи, добавленные после выполнения этого метода._
```php
$zipFile->setPassword($password);
```
Можно установить метод шифрования:
```php
$encryptionMethod = \PhpZip\Constants\ZipEncryptionMethod::WINZIP_AES_256;
$zipFile->setPassword($password, $encryptionMethod);
```
##### ZipFile::setPasswordEntry
Устанавливает новый пароль для конкретного файла.
```php
$zipFile->setPasswordEntry($entryName, $password);
```
Можно установить метод шифрования:
```php
$encryptionMethod = \PhpZip\Constants\ZipEncryptionMethod::WINZIP_AES_256;
$zipFile->setPasswordEntry($entryName, $password, $encryptionMethod);
```
##### ZipFile::disableEncryption
Отключает шифрования всех записей, находящихся в архиве.

> _Обратите внимание, что действие данного метода не распространяется на записи, добавленные после выполнения этого метода._
```php
$zipFile->disableEncryption();
```
##### ZipFile::disableEncryptionEntry
Отключает шифрование записи по её имени.
```php
$zipFile->disableEncryptionEntry($entryName);
```
#### Отмена изменений
##### ZipFile::unchangeAll
Отменяет все изменения, сделанные в архиве.
```php
$zipFile->unchangeAll();
```
##### ZipFile::unchangeArchiveComment
Отменяет изменения в комментарии к архиву.
```php
$zipFile->unchangeArchiveComment();
```
##### ZipFile::unchangeEntry
Отменяет изменения для конкретной записи архива.
```php
$zipFile->unchangeEntry($entryName);
```
#### Сохранение файла или вывод в браузер
##### ZipFile::saveAsFile
Сохраняет архив в файл.
```php
$zipFile->saveAsFile($filename);
```
##### ZipFile::saveAsStream
Записывает архив в поток.
```php
// $fp = fopen($filename, 'w+b');

$zipFile->saveAsStream($fp);
```
##### ZipFile::outputAsString
Выводит ZIP-архив в виде строки.
```php
$rawZipArchiveBytes = $zipFile->outputAsString();
```
##### ZipFile::outputAsAttachment
Выводит ZIP-архив в браузер.

При выводе устанавливаются необходимые заголовки, а после вывода завершается работа скрипта.
```php
$zipFile->outputAsAttachment($outputFilename);
```
Можно установить MIME-тип:
```php
$mimeType = 'application/zip';
$zipFile->outputAsAttachment($outputFilename, $mimeType);
```
##### ZipFile::outputAsPsr7Response
Выводит ZIP-архив, как [PSR-7 Response](http://www.php-fig.org/psr/psr-7/).

Метод вывода может использоваться в любом PSR-7 совместимом фреймворке. 
```php
// $response = ....; // instance Psr\Http\Message\ResponseInterface
$zipFile->outputAsPsr7Response($response, $outputFilename);
```
Можно установить MIME-тип:
```php
$mimeType = 'application/zip';
$zipFile->outputAsPsr7Response($response, $outputFilename, $mimeType);
```
##### ZipFile::outputAsSymfonyResponse
Выводит ZIP-архив, как [Symfony Response](https://symfony.com/doc/current/components/http_foundation.html#response).

Метод вывода можно использовать в фреймворке Symfony.
```php
$response = $zipFile->outputAsSymfonyResponse($outputFilename);
```
Вы можете установить Mime-Type:
```php
$mimeType = 'application/zip';
$response = $zipFile->outputAsSymfonyResponse($outputFilename, $mimeType);
```
Пример использования в Symfony Controller:
```php
<?php

namespace App\Controller;

use PhpZip\ZipFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DownloadZipController
{
    /**
     * @Route("/downloads/{id}")
     *
     * @throws \PhpZip\Exception\ZipException
     */
    public function __invoke(string $id): Response
    {
        $zipFile = new ZipFile();
        $zipFile['file'] = 'contents';

        $outputFilename = $id . '.zip';
        return $zipFile->outputAsSymfonyResponse($outputFilename);
    }
}
```
##### ZipFile::rewrite
Сохраняет изменения и заново открывает изменившийся архив.
```php
$zipFile->rewrite();
```
#### Закрытие архива
##### ZipFile::close
Закрывает ZIP-архив.
```php
$zipFile->close();
```
### Запуск тестов
Установите зависимости для разработки.
```bash
composer install --dev
```
Запустите тесты:
```bash
vendor/bin/phpunit -v -c phpunit.xml
```
### История изменений
История изменений на [странице релизов](https://github.com/Ne-Lexa/php-zip/releases).

### Обновление версий
#### Обновление с версии 3 до версии 4
Обновите мажорную версию в файле `composer.json` до `^4.0`.
```json
{
    "require": {
        "nelexa/zip": "^4.0"
    }
}
```
Затем установите обновления с помощью `Composer`:
```bash
composer update nelexa/zip
```
Обновите ваш код для работы с новой версией:
- удалены устаревшие метроды
- удалён zipalign функционал (он будет помещен в отдельный пакет nelexa/apkfile)
#### Обновление с версии 2 до версии 3
Обновите мажорную версию в файле `composer.json` до `^3.0`.
```json
{
    "require": {
        "nelexa/zip": "^3.0"
    }
}
```
Затем установите обновления с помощью `Composer`:
```bash
composer update nelexa/zip
```
Обновите ваш код для работы с новой версией:
- Класс `ZipOutputFile` объединён с `ZipFile` и удалён.
  + Замените `new \PhpZip\ZipOutputFile()` на `new \PhpZip\ZipFile()`
- Статичиская инициализация методов стала не статической.
  + Замените `\PhpZip\ZipFile::openFromFile($filename);` на `(new \PhpZip\ZipFile())->openFile($filename);`
  + Замените `\PhpZip\ZipOutputFile::openFromFile($filename);` на `(new \PhpZip\ZipFile())->openFile($filename);`
  + Замените `\PhpZip\ZipFile::openFromString($contents);` на `(new \PhpZip\ZipFile())->openFromString($contents);`
  + Замените `\PhpZip\ZipFile::openFromStream($stream);` на `(new \PhpZip\ZipFile())->openFromStream($stream);`
  + Замените `\PhpZip\ZipOutputFile::create()` на `new \PhpZip\ZipFile()`
  + Замените `\PhpZip\ZipOutputFile::openFromZipFile($zipFile)` на `(new \PhpZip\ZipFile())->openFile($filename);`
- Переименуйте методы:
  + `addFromFile` в `addFile`
  + `setLevel` в `setCompressionLevel`
  + `ZipFile::setPassword` в `ZipFile::withReadPassword`
  + `ZipOutputFile::setPassword` в `ZipFile::withNewPassword`
  + `ZipOutputFile::disableEncryptionAllEntries` в `ZipFile::withoutPassword`
  + `ZipOutputFile::setComment` в `ZipFile::setArchiveComment`
  + `ZipFile::getComment` в `ZipFile::getArchiveComment`
- Изменились сигнатуры для методов `addDir`, `addFilesFromGlob`, `addFilesFromRegex`.
- Удалены методы:
  + `getLevel`
  + `setCompressionMethod`
  + `setEntryPassword`
