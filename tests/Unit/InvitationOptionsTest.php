<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Invitation\InvitationOptions;
use App\Invitation\IssuedInvitation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class InvitationOptionsTest extends TestCase
{
    public function testCredentialFormatEntropyAndDigest(): void
    {
        $id = bin2hex(random_bytes(16));
        $one = IssuedInvitation::generate($id);
        $two = IssuedInvitation::generate($id);
        self::assertTrue(strlen($one->credential) === 80);
        self::assertTrue((bool) preg_match('/\Aqlt_[a-f0-9]{32}_[A-Za-z0-9_-]{43}\z/D', $one->credential));
        self::assertTrue(hash_equals(hash('sha256', $one->credential), $one->digest));
        self::assertTrue($one->credential !== $two->credential);
    }

    #[DataProvider('money')]
    public function testExactMoney(string $usd, int $micro): void { self::assertSame($micro, InvitationOptions::microUsd($usd)); }
    public static function money(): iterable { yield ['0', 0]; yield ['0.000001', 1]; yield ['1.234567', 1234567]; yield ['100.000000', 100000000]; }

    #[DataProvider('invalidOptions')]
    public function testInvalidOptions(string $property, string $value): void
    {
        $options = new InvitationOptions(); $options->name = 'Offline recipient'; $options->$property = $value;
        self::assertGreaterThan(0, count(Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($options)));
    }
    public static function invalidOptions(): iterable
    {
        foreach (['', ' ', "Line\nname", "Nonbreaking\u{00a0}space", "Zero\u{200b}width", str_repeat('界', 129)] as $name) { yield ['name', $name]; }
        foreach (['maxRequests' => ['0', '10001', '1.5', '1e2'], 'budgetUsd' => ['100.000001', '-1', 'NaN', '0.0000001'], 'requestsPerMinute' => ['0', '61'], 'expiresInDays' => ['0', '366']] as $field => $values) { foreach ($values as $value) { yield [$field, $value]; } }
    }
}
