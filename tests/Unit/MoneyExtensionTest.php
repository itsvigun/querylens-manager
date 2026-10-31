<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Twig\MoneyExtension;
use PHPUnit\Framework\TestCase;

final class MoneyExtensionTest extends TestCase
{
    public function testExactBigintAndNullFormatting(): void
    {
        self::assertSame('$9223372036854.775807', MoneyExtension::format('9223372036854775807'));
        self::assertSame('$0.000001', MoneyExtension::format(1));
        self::assertSame('Unknown', MoneyExtension::format(null));
    }
}
