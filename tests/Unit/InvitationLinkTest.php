<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Invitation\InvitationLink;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InvitationLinkTest extends TestCase
{
    #[DataProvider('invalidBases')]
    public function testUntrustedConfiguredDestinationsAreRejected(string $base): void
    {
        $this->expectException(\LogicException::class);
        (new InvitationLink($base))->for('offline-placeholder');
    }
    public static function invalidBases(): iterable
    {
        foreach (['http://querylens.test', 'https://querylens.example', 'https://user@querylens.test', 'https://querylens.test/?x=1', 'https://querylens.test/#fragment', 'https://querylens.test/path'] as $url) { yield [$url]; }
    }

    #[DataProvider('localOrigins')]
    public function testDevelopmentAcceptsOnlyExplicitLoopbackHttp(string $origin): void
    {
        self::assertSame($origin.'/#at=offline-placeholder', (new InvitationLink($origin, 'dev'))->for('offline-placeholder'));
    }

    public static function localOrigins(): iterable
    {
        yield ['http://127.0.0.1:8000'];
        yield ['http://localhost:8000'];
    }

    #[DataProvider('forbiddenHttpOrigins')]
    public function testHttpExceptionCannotWidenProductionOrRemoteAccess(string $origin, string $environment): void
    {
        $this->expectException(\LogicException::class);
        (new InvitationLink($origin, $environment))->for('offline-placeholder');
    }

    public static function forbiddenHttpOrigins(): iterable
    {
        yield ['http://127.0.0.1:8000', 'prod'];
        yield ['http://localhost:8000', 'prod'];
        yield ['http://127.0.0.1.evil.test:8000', 'dev'];
        yield ['http://192.168.1.2:8000', 'dev'];
        yield ['http://user@127.0.0.1:8000', 'dev'];
        yield ['http://127.0.0.1:8000/other', 'dev'];
        yield ['http://127.0.0.1:8000/?at=value', 'dev'];
        yield ['http://127.0.0.1:8000/#at=value', 'dev'];
    }
}
