<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Security\OwnerLoginLimiter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class SecurityTest extends WebTestCase
{
    public function testAnonymousCannotReadConsoleAndCanLoginWithCsrf(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        self::assertResponseRedirects('http://localhost/login');
        $client->followRedirect();
        self::assertResponseHeaderSame('Referrer-Policy', 'no-referrer');
        self::assertStringContainsString('no-store', $client->getResponse()->headers->get('Cache-Control'));
        $client->submitForm('Sign in', ['_username' => 'owner', '_password' => 'offline-owner-password']);
        self::assertResponseRedirects('http://localhost/');
        $client->followRedirect();
        self::assertResponseStatusCodeSame(503);
        self::assertSelectorTextContains('h1', 'Console unavailable');
        $client->submitForm('Sign out');
        self::assertResponseRedirects('/login');
        $client->request('GET', '/');
        self::assertResponseRedirects('http://localhost/login');
    }

    public function testMissingLoginCsrfIsRejected(): void
    {
        $client = static::createClient();
        $client->request('POST', '/login', ['_username' => 'owner', '_password' => 'offline-owner-password']);
        self::assertResponseRedirects('/login');
        $client->request('GET', '/');
        self::assertResponseRedirects('http://localhost/login');
    }

    public function testHostHeaderCannotChangeOrigin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login', server: ['HTTP_HOST' => 'evil.example']);
        self::assertResponseStatusCodeSame(400);
    }

    public function testExpiredOwnerSessionCannotReachConsole(): void
    {
        $client = static::createClient();
        $client->loginUser(new InMemoryUser('owner', $_ENV['ADMIN_PASSWORD_HASH'], ['ROLE_ADMIN']));
        $session = $client->getSession();
        self::assertNotNull($session);
        $session->set('owner_activity', time() - 1801);
        $session->save();
        $client->request('GET', '/');
        self::assertResponseRedirects('/login');
    }

    public function testOwnerWideThrottleCannotBeBypassedByChangingUsername(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');
        static::getContainer()->get(OwnerLoginLimiter::class)->reset(Request::create('/login'));
        for ($attempt = 0; $attempt < 5; ++$attempt) {
            $client->submitForm('Sign in', ['_username' => 'unknown-'.$attempt, '_password' => 'wrong-offline-password']);
            $client->followRedirect();
        }
        self::assertSame(0, static::getContainer()->get(OwnerLoginLimiter::class)->peek(Request::create('/login'))->getRemainingTokens());
        $client->submitForm('Sign in', ['_username' => 'owner', '_password' => 'offline-owner-password']);
        $client->request('GET', '/');
        self::assertResponseRedirects('http://localhost/login');
        static::getContainer()->get(OwnerLoginLimiter::class)->reset(Request::create('/login'));
    }

    public function testPasswordRotationInvalidatesExistingSession(): void
    {
        $client = static::createClient();
        $original = $_ENV['ADMIN_PASSWORD_HASH'];
        $client->loginUser(new InMemoryUser('owner', $original, ['ROLE_ADMIN']));
        $client->request('GET', '/');
        self::assertResponseStatusCodeSame(503);
        try {
            $_SERVER['ADMIN_PASSWORD_HASH'] = $_ENV['ADMIN_PASSWORD_HASH'] = password_hash('rotated-offline-password', PASSWORD_BCRYPT, ['cost' => 4]);
            $client->request('GET', '/');
            self::assertResponseRedirects('http://localhost/login');
        } finally {
            $_SERVER['ADMIN_PASSWORD_HASH'] = $_ENV['ADMIN_PASSWORD_HASH'] = $original;
        }
    }

    public function testLogoutRequiresCsrf(): void
    {
        $client = static::createClient();
        $client->loginUser(new InMemoryUser('owner', $_ENV['ADMIN_PASSWORD_HASH'], ['ROLE_ADMIN']));
        $client->request('POST', '/logout');
        self::assertResponseStatusCodeSame(403);
    }
}
