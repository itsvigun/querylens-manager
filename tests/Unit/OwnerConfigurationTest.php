<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Security\OwnerConfiguration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OwnerConfigurationTest extends TestCase
{
    public function testMissingPrivateConfigurationHasSafeExplicitError(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Configure ADMIN_USERNAME');
        new OwnerConfiguration('owner', 'invalid-private-hash', '', 'https://manager.example');
    }

    #[DataProvider('untrustedOrigins')]
    public function testRejectsUntrustedOrigin(string $origin): void
    {
        $this->expectException(\LogicException::class);
        new OwnerConfiguration('owner', password_hash('fixture', PASSWORD_BCRYPT), str_repeat('x', 32), $origin);
    }

    public static function untrustedOrigins(): iterable
    {
        foreach (['http://manager.example', 'https://user@manager.example', 'https://manager.example/path', 'https://manager.example?x=1', 'https://manager.example#x', ''] as $url) { yield [$url]; }
    }
}
