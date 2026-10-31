<?php

declare(strict_types=1);

namespace App\Invitation;

/** Python str.isprintable() at the pinned upstream runtime, independent of ICU/PCRE Unicode versions. */
final class PrintableName
{
    private static ?array $ranges = null;

    public static function valid(string $name): bool
    {
        if (!mb_check_encoding($name, 'UTF-8') || mb_strlen($name, 'UTF-8') > 128 || trim($name, ' ') === '') { return false; }
        self::$ranges ??= json_decode(file_get_contents(dirname(__DIR__, 2).'/resources/python-printable-ranges.json'), true, flags: JSON_THROW_ON_ERROR)['ranges'];
        foreach (mb_str_split($name, 1, 'UTF-8') as $character) {
            if (!self::contains(mb_ord($character, 'UTF-8'))) { return false; }
        }
        return true;
    }

    public static function contains(int $point): bool
    {
        self::$ranges ??= json_decode(file_get_contents(dirname(__DIR__, 2).'/resources/python-printable-ranges.json'), true, flags: JSON_THROW_ON_ERROR)['ranges'];
        $low = 0; $high = count(self::$ranges) - 1;
        while ($low <= $high) {
            $middle = intdiv($low + $high, 2);
            [$start, $end] = self::$ranges[$middle];
            if ($point < $start) { $high = $middle - 1; }
            elseif ($point >= $end) { $low = $middle + 1; }
            else { return true; }
        }
        return false;
    }
}
