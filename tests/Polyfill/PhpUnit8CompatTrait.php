<?php

namespace PhpZip\Tests\Polyfill;

use PHPUnit\Runner\Version;

trait PhpUnit8CompatTrait
{
    /**
     * @param string $regularExpression
     */
    public function expectExceptionMessageMatches(string $regularExpression): void
    {
        if (version_compare(Version::id(), '8.0.0', '<')) {
            $this->expectExceptionMessageRegExp($regularExpression);

            return;
        }

        parent::expectExceptionMessageMatches($regularExpression);
    }
}
