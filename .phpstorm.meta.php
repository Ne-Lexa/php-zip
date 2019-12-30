<?php

namespace PHPSTORM_META {

    registerArgumentsSet(
        "bool",
        true,
        false
    );

    registerArgumentsSet(
        "compression_methods",
        \PhpZip\Constants\ZipCompressionMethod::STORED,
        \PhpZip\Constants\ZipCompressionMethod::DEFLATED,
        \PhpZip\Constants\ZipCompressionMethod::BZIP2
    );
    expectedArguments(\PhpZip\ZipFile::addFile(), 2, argumentsSet("compression_methods"));
    expectedArguments(\PhpZip\ZipFile::addFromStream(), 2, argumentsSet("compression_methods"));
    expectedArguments(\PhpZip\ZipFile::addFromString(), 2, argumentsSet("compression_methods"));
    expectedArguments(\PhpZip\ZipFile::addDir(), 2, argumentsSet("compression_methods"));
    expectedArguments(\PhpZip\ZipFile::addDirRecursive(), 2, argumentsSet("compression_methods"));
    expectedArguments(\PhpZip\ZipFile::addFilesFromIterator(), 2, argumentsSet("compression_methods"));
    expectedArguments(\PhpZip\ZipFile::addFilesFromIterator(), 2, argumentsSet("compression_methods"));
    expectedArguments(\PhpZip\ZipFile::addFilesFromGlob(), 3, argumentsSet("compression_methods"));
    expectedArguments(\PhpZip\ZipFile::addFilesFromGlobRecursive(), 3, argumentsSet("compression_methods"));
    expectedArguments(\PhpZip\ZipFile::addFilesFromRegex(), 3, argumentsSet("compression_methods"));
    expectedArguments(\PhpZip\ZipFile::addFilesFromRegexRecursive(), 3, argumentsSet("compression_methods"));
    expectedArguments(\PhpZip\ZipFile::setCompressionMethodEntry(), 1, argumentsSet("compression_methods"));

    registerArgumentsSet(
        'compression_levels',
        \PhpZip\Constants\ZipCompressionLevel::MAXIMUM,
        \PhpZip\Constants\ZipCompressionLevel::NORMAL,
        \PhpZip\Constants\ZipCompressionLevel::FAST,
        \PhpZip\Constants\ZipCompressionLevel::SUPER_FAST
    );
    expectedArguments(\PhpZip\ZipFile::setCompressionLevel(), 0, argumentsSet("compression_levels"));
    expectedArguments(\PhpZip\ZipFile::setCompressionLevelEntry(), 1, argumentsSet("compression_levels"));

    registerArgumentsSet(
        'encryption_methods',
        \PhpZip\Constants\ZipEncryptionMethod::WINZIP_AES_256,
        \PhpZip\Constants\ZipEncryptionMethod::WINZIP_AES_192,
        \PhpZip\Constants\ZipEncryptionMethod::WINZIP_AES_128,
        \PhpZip\Constants\ZipEncryptionMethod::PKWARE
    );
    expectedArguments(\PhpZip\ZipFile::setPassword(), 1, argumentsSet("encryption_methods"));
    expectedArguments(\PhpZip\ZipFile::setPasswordEntry(), 2, argumentsSet("encryption_methods"));

    registerArgumentsSet(
        'zip_mime_types',
        null,
        'application/zip',
        'application/vnd.android.package-archive',
        'application/java-archive'
    );
    expectedArguments(\PhpZip\ZipFile::outputAsAttachment(), 1, argumentsSet("zip_mime_types"));
    expectedArguments(\PhpZip\ZipFile::outputAsAttachment(), 2, argumentsSet("bool"));

    expectedArguments(\PhpZip\ZipFileI::outputAsResponse(), 2, argumentsSet("zip_mime_types"));
    expectedArguments(\PhpZip\ZipFileI::outputAsResponse(), 3, argumentsSet("bool"));
}
