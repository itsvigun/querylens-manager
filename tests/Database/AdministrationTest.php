<?php

declare(strict_types=1);

namespace App\Tests\Database;

use App\Infrastructure\ManagerDatabase;
use App\Invitation\InvitationAdministrator;
use App\Invitation\InvitationOptions;
use App\Invitation\InvitationReader;
use App\Invitation\IssuedInvitation;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[Group('database')]
final class AdministrationTest extends WebTestCase
{
    public function testHttpCreateShowsSecretOnlyOnceAndReplaysSafely(): void
    {
        $client = static::createClient();
        $client->loginUser(new InMemoryUser('owner', $_ENV['ADMIN_PASSWORD_HASH'], ['ROLE_ADMIN']));
        $crawler = $client->request('GET', '/invitations/new');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Create invitation')->form(['invitation[name]' => 'Offline HTTP recipient']);
        $id = $form['invitation[operationId]']->getValue();
        $client->submit($form);
        self::assertTrue($client->getResponse()->getStatusCode() === 201, 'Issuance must succeed without dumping the secret response.');
        $html = $client->getResponse()->getContent();
        self::assertTrue(str_contains($html, 'https://querylens.test/#at=qlt_'.$id.'_'));
        self::assertTrue(str_contains($client->getResponse()->headers->get('Cache-Control'), 'no-store'));
        self::assertTrue($client->getResponse()->headers->get('Referrer-Policy') === 'no-referrer');
        self::assertTrue((bool) preg_match('/qlt_[a-f0-9]{32}_[A-Za-z0-9_-]{43}/', $html, $matches));
        $secret = $matches[0];
        $session = $client->getSession();
        self::assertFalse(str_contains(serialize($session->all()), $secret));
        $client->submit($form);
        self::assertResponseStatusCodeSame(409);
        self::assertFalse(str_contains($client->getResponse()->getContent(), $secret));
        $client->request('GET', '/invitations/'.$id);
        self::assertResponseIsSuccessful();
        self::assertFalse(str_contains($client->getResponse()->getContent(), $secret));
        $connection = static::getContainer()->get(ManagerDatabase::class)->connection();
        self::assertSame(1, (int) $connection->fetchOne('SELECT count(*) FROM manager.invitations WHERE id=:id', ['id' => $id]));
    }

    public function testMutationsPreserveAccountingRejectStaleEditsAndReplayOnce(): void
    {
        self::bootKernel();
        $service = static::getContainer()->get(InvitationAdministrator::class);
        $reader = static::getContainer()->get(InvitationReader::class);
        $options = new InvitationOptions(); $options->name = 'Offline mutation recipient';
        $issued = IssuedInvitation::generate(bin2hex(random_bytes(16)));
        self::assertTrue($service->create($issued, $options, 'owner'));
        self::assertFalse($service->create($issued, $options, 'owner'));
        $owner = $this->owner();
        $owner->executeStatement('UPDATE operations.access_usage SET admitted_requests=3,charged_micro_usd=999999,unknown_requests=2,reserved_micro_usd=7,rate_count=2,first_used_at=clock_timestamp(),last_used_at=clock_timestamp() WHERE token_id=:id', ['id' => $issued->id]);
        $before = $owner->fetchAssociative('SELECT * FROM operations.access_usage WHERE token_id=:id', ['id' => $issued->id]);
        $token = $reader->find($issued->id);
        $options->operationId = bin2hex(random_bytes(16)); $options->version = $token['metadata_version'];
        $options->maxRequests = '1'; $options->budgetUsd = '0.000001'; $options->expiresInDays = '';
        self::assertSame('updated', $service->update($issued->id, $options, 'owner'));
        self::assertSame('replayed', $service->update($issued->id, $options, 'owner'));
        self::assertSame($before, $owner->fetchAssociative('SELECT * FROM operations.access_usage WHERE token_id=:id', ['id' => $issued->id]));
        $after = $reader->find($issued->id);
        self::assertSame(0, (int) $after['remaining_requests']);
        self::assertSame($token['expires_at'], $after['expires_at']);
        $options->operationId = bin2hex(random_bytes(16));
        self::assertSame('stale', $service->update($issued->id, $options, 'owner'));
        $operation = bin2hex(random_bytes(16));
        self::assertSame('revoked', $service->revoke($operation, $issued->id, 'owner'));
        $revoked = $reader->find($issued->id)['revoked_at'];
        self::assertSame('replayed', $service->revoke($operation, $issued->id, 'owner'));
        self::assertSame('revoked', $service->revoke(bin2hex(random_bytes(16)), $issued->id, 'owner'));
        self::assertSame($revoked, $reader->find($issued->id)['revoked_at']);
        self::assertSame('revoked', $service->update($issued->id, $options, 'owner'));
        self::assertSame(4, (int) $owner->fetchOne('SELECT count(*) FROM manager.audit WHERE token_id=:id', ['id' => $issued->id]));
        $audit = json_encode($owner->fetchAllAssociative('SELECT * FROM manager.audit WHERE token_id=:id', ['id' => $issued->id]));
        self::assertFalse(str_contains($audit, $issued->credential));
        self::assertFalse(str_contains($audit, $issued->digest));
        $owner->close();
    }

    public function testCsrfProtectsMutationEndpointsAndGetCannotRevoke(): void
    {
        $client = static::createClient();
        $client->loginUser(new InMemoryUser('owner', $_ENV['ADMIN_PASSWORD_HASH'], ['ROLE_ADMIN']));
        $client->request('POST', '/invitations/new', ['invitation' => ['name' => 'Offline missing CSRF']]);
        self::assertResponseStatusCodeSame(422);
        $id = str_pad('1', 32, '0', STR_PAD_LEFT);
        $client->request('POST', '/invitations/'.$id.'/revoke');
        self::assertResponseStatusCodeSame(403);
        $client->request('GET', '/invitations/'.$id.'/revoke');
        self::assertResponseStatusCodeSame(405);
    }

    public function testRuntimeCannotBypassRoutinesOrAudit(): void
    {
        self::bootKernel();
        $connection = static::getContainer()->get(ManagerDatabase::class)->connection();
        foreach (['UPDATE operations.access_tokens SET revoked_at=NULL', 'INSERT INTO manager.audit SELECT * FROM manager.audit', 'UPDATE manager.audit SET action=\'revoke\'', 'DELETE FROM manager.audit', 'SELECT * FROM manager.audit', "SELECT manager.validate_options('x',1,1,1,1)"] as $sql) {
            try { $connection->executeQuery($sql); self::fail('Forbidden operation succeeded.'); }
            catch (\Doctrine\DBAL\Exception) { self::assertTrue(true); }
        }
    }

    public function testEditAndRevokeFormsWorkWithCsrfAndPreserveExpiry(): void
    {
        $client = static::createClient();
        $client->loginUser(new InMemoryUser('owner', $_ENV['ADMIN_PASSWORD_HASH'], ['ROLE_ADMIN']));
        $administrator = static::getContainer()->get(InvitationAdministrator::class);
        $reader = static::getContainer()->get(InvitationReader::class);
        $options = new InvitationOptions(); $options->name = 'Offline edit form';
        $issued = IssuedInvitation::generate(bin2hex(random_bytes(16)));
        $administrator->create($issued, $options, 'owner');
        $before = $reader->find($issued->id);
        $client->request('GET', '/invitations/'.$issued->id.'/edit');
        $client->submitForm('Save changes', ['invitation[maxRequests]' => '7', 'invitation[budgetUsd]' => '1.234567']);
        self::assertResponseRedirects('/invitations/'.$issued->id, 303);
        $crawler = $client->followRedirect();
        self::assertSelectorTextContains('main', '7');
        $after = static::getContainer()->get(InvitationReader::class)->find($issued->id);
        self::assertSame($before['expires_at'], $after['expires_at']);
        self::assertSame(1234567, (int) $after['budget_micro_usd']);
        $client->submit($crawler->selectButton('Revoke invitation permanently')->form());
        self::assertResponseRedirects('/invitations/'.$issued->id, 303);
        $client->followRedirect();
        self::assertSelectorNotExists('button.danger');
        $client->request('GET', '/invitations/'.$issued->id.'/edit');
        self::assertResponseStatusCodeSame(409);
    }

    private function owner(): \Doctrine\DBAL\Connection
    {
        $url = getenv('TEST_OWNER_DATABASE_URL');
        if (!is_string($url) || !str_ends_with($url, '@database/manager_test')) { throw new \LogicException('Disposable owner connection required.'); }
        return DriverManager::getConnection((new DsnParser(['postgresql' => 'pdo_pgsql']))->parse($url));
    }

    public function testUnsupportedUpstreamMigrationHeadFailsClosed(): void
    {
        $owner = $this->owner();
        $owner->executeStatement("UPDATE public.alembic_version SET version_num='unsupported_test_revision'");
        try {
            try {
                (new ManagerDatabase(getenv('TEST_MANAGER_DATABASE_URL')))->connection();
                self::fail('Unsupported schema must be refused.');
            } catch (\RuntimeException $error) {
                self::assertSame('Manager database is unavailable or its role is unsafe.', $error->getMessage());
            }
        } finally {
            $owner->executeStatement("UPDATE public.alembic_version SET version_num='0006_post_request_usage'");
            $owner->close();
        }
    }
}
