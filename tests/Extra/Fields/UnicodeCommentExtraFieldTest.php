<?php

namespace PhpZip\Tests\Extra\Fields;

use PhpZip\Model\Extra\Fields\UnicodeCommentExtraField;

/**
 * @internal
 *
 * @small
 */
final class UnicodeCommentExtraFieldTest extends AbstractUnicodeExtraFieldTest
{
    /**
     * {@inheritdoc}
     */
    protected function getUnicodeExtraFieldClassName()
    {
        return UnicodeCommentExtraField::class;
    }

    /**
     * @return array
     */
    public function provideExtraField()
    {
        return [
            [
                4293813303,
                'комментарий',
                "\xAA\xAE\xAC\xAC\xA5\xAD\xE2\xA0\xE0\xA8\xA9",
                "\x017d\xEE\xFF\xD0\xBA\xD0\xBE\xD0\xBC\xD0\xBC\xD0\xB5\xD0\xBD\xD1\x82\xD0\xB0\xD1\x80\xD0\xB8\xD0\xB9",
            ],
            [
                897024324,
                'תגובה',
                "\x9A\x82\x85\x81\x84",
                "\x01D\x81w5\xD7\xAA\xD7\x92\xD7\x95\xD7\x91\xD7\x94",
            ],
        ];
    }
}
