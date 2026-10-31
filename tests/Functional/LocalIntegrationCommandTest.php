<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class LocalIntegrationCommandTest extends KernelTestCase
{
    public function testProductionRejectsLocalIssuanceCheckBeforeAnyConnection(): void
    {
        $kernel = self::bootKernel(['environment' => 'prod']);
        $command = (new Application($kernel))->find('app:verify-local-integration');
        $tester = new CommandTester($command);
        self::assertSame(1, $tester->execute([]));
        self::assertStringContainsString('restricted to the explicitly connected local development stack', $tester->getDisplay());
    }
}
