<?php

declare(strict_types=1);

namespace PhpZip\Tests\Polyfill;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @small
 */
class LegacyTestCase extends TestCase
{
    use PhpUnit8CompatTrait;
    use PhpUnit9CompatTrait;
}
