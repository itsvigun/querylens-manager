<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HealthTest extends WebTestCase
{
    public function testPlatformLivenessHasNoMetadataAndNeedsNoSessionOrDatabase(): void
    {
        $client = self::createClient();
        $client->request('GET', '/health/live', server: ['HTTP_HOST' => 'healthcheck.railway.app']);
        self::assertResponseIsSuccessful();
        self::assertSame(['status' => 'ok'], json_decode($client->getResponse()->getContent(), true));
        self::assertFalse($client->getResponse()->headers->has('Set-Cookie'));
    }

    public function testHealthExceptionDoesNotExposeAdministrativeRoutes(): void
    {
        $client = self::createClient();
        $client->request('GET', '/', server: ['HTTP_HOST' => 'healthcheck.railway.app']);
        self::assertResponseStatusCodeSame(400);
    }
}
