<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class MoneyExtension extends AbstractExtension
{
    public function getFilters(): array { return [new TwigFilter('micro_usd', self::format(...))]; }

    public static function format(int|string|null $value): string
    {
        if ($value === null) { return 'Unknown'; }
        $digits = (string) $value;
        if (!preg_match('/\A[0-9]+\z/D', $digits)) { throw new \InvalidArgumentException('Expected nonnegative integer micro-USD.'); }
        $digits = str_pad($digits, 7, '0', STR_PAD_LEFT);
        return '$'.substr($digits, 0, -6).'.'.substr($digits, -6);
    }
}
