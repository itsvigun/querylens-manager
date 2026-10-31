<?php

declare(strict_types=1);

namespace App\Tests\Database;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[Group('database')]
final class ReadModelTest extends WebTestCase
{
    public function testAnonymousCannotReadInvitationMetadata(): void
    {
        $client = static::createClient();
        $client->request('GET', '/invitations/'.str_pad('1', 32, '0', STR_PAD_LEFT));
        self::assertResponseRedirects('http://localhost/login');
        self::assertStringNotContainsString('Offline recipient', $client->getResponse()->getContent());
    }

    public function testEscapedPaginatedListAndSanitizedUsage(): void
    {
        $client = static::createClient();
        $client->loginUser(new InMemoryUser('owner', $_ENV['ADMIN_PASSWORD_HASH'], ['ROLE_ADMIN']));
        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(25, 'tbody tr');
        self::assertSelectorExists('a:contains("Next")');
        $client->request('GET', '/?page=2');
        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $client->getCrawler()->filter('tbody tr')->count());
        $client->request('GET', '/invitations/'.str_pad('1', 32, '0', STR_PAD_LEFT));
        self::assertResponseIsSuccessful();
        $html = $client->getResponse()->getContent();
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        foreach (['PRIVATE_CONTENT_CANARY', 'DIGEST_CANARY', 'PRIVATE_ERROR_CANARY', 'secret_digest', 'reserved_micro_usd'] as $forbidden) { self::assertStringNotContainsString($forbidden, $html); }
        foreach (['gpt-5.4-mini', '100 input / 10 output tokens', '$0.012345', 'recorded', 'pending', 'unknown', 'legacy', 'not admitted'] as $expected) { self::assertStringContainsString($expected, $html); }
    }

    public function testRuntimeHasOnlyProjectedReadAccess(): void
    {
        $url = getenv('TEST_MANAGER_DATABASE_URL');
        self::assertIsString($url);
        $connection = DriverManager::getConnection((new DsnParser(['postgresql' => 'pdo_pgsql']))->parse($url));
        self::assertSame('querylens_manager', $connection->fetchOne('SELECT current_user'));
        self::assertGreaterThanOrEqual(27, (int) $connection->fetchOne('SELECT count(*) FROM manager.invitations'));
        foreach (["SELECT secret_digest FROM operations.access_tokens", 'SELECT * FROM operations.access_sessions', 'SELECT * FROM operations.access_requests', 'SELECT * FROM operations.demo_leases', 'SELECT * FROM operations.demo_policy', 'SELECT * FROM operations.demo_months', 'UPDATE operations.access_usage SET admitted_requests=0', 'DELETE FROM operations.access_tokens', 'SELECT * FROM analytics.users', 'SELECT * FROM knowledge.chunks', 'CREATE TABLE manager.forbidden(id int)', 'CREATE TEMP TABLE forbidden(id int)', 'SET ROLE querylens_manager_owner'] as $query) {
            try { $connection->executeQuery($query); self::fail('Forbidden permission succeeded.'); }
            catch (\Doctrine\DBAL\Exception) { self::assertTrue(true); }
        }
        $connection->close();
    }
}
