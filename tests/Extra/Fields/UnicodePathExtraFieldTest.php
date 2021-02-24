<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Tests\Extra\Fields;

use PhpZip\Model\Extra\Fields\UnicodePathExtraField;

/**
 * Class UnicodePathExtraFieldTest.
 *
 * @internal
 *
 * @small
 */
final class UnicodePathExtraFieldTest extends AbstractUnicodeExtraFieldTest
{
    /**
     * {@inheritdoc}
     */
    protected function getUnicodeExtraFieldClassName()
    {
        return UnicodePathExtraField::class;
    }

    public function provideExtraField(): array
    {
        return [
            [
                2728523760,
                'txt\מבחן עברי.txt',
                "txt/\x8E\x81\x87\x8F \x92\x81\x98\x89.txt",
                "\x01\xF0\xF7\xA1\xA2txt\\\xD7\x9E\xD7\x91\xD7\x97\xD7\x9F \xD7\xA2\xD7\x91\xD7\xA8\xD7\x99.txt",
            ],
            [
                953311492,
                'ä\ü.txt',
                "\x84/\x81.txt",
                "\x01\x04a\xD28\xC3\xA4\\\xC3\xBC.txt",
            ],
            [
                2965532848,
                'Ölfässer.txt',
                "\x99lf\x84sser.txt",
                "\x01\xB0p\xC2\xB0\xC3\x96lf\xC3\xA4sser.txt",
            ],
            [
                3434671236,
                'Как заработать в интернете.mp4',
                "\x8A\xA0\xAA \xA7\xA0\xE0\xA0\xA1\xAE\xE2\xA0\xE2\xEC \xA2 \xA8\xAD\xE2\xA5\xE0\xAD\xA5\xE2\xA5.mp4",
                "\x01\x84\xEC\xB8\xCC\xD0\x9A\xD0\xB0\xD0\xBA \xD0\xB7\xD0\xB0\xD1\x80\xD0\xB0\xD0\xB1\xD0\xBE\xD1\x82\xD0\xB0\xD1\x82\xD1\x8C \xD0\xB2 \xD0\xB8\xD0\xBD\xD1\x82\xD0\xB5\xD1\x80\xD0\xBD\xD0\xB5\xD1\x82\xD0\xB5.mp4",
            ],
        ];
    }
}
