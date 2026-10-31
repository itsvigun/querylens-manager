<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Logging\SanitizedLogger;
use PHPUnit\Framework\TestCase;

final class SanitizedLoggerTest extends TestCase
{
    public function testDiagnosticsCannotSerializeMessagesExceptionArgumentsOrRequestContent(): void
    {
        $stream = fopen('php://memory', 'w+');
        $logger = new SanitizedLogger($stream);
        $logger->error('PRIVATE_MESSAGE_CANARY', ['exception' => new \RuntimeException('PRIVATE_EXCEPTION_CANARY'), 'request' => ['password' => 'PRIVATE_PASSWORD_CANARY']]);
        rewind($stream);
        $record = stream_get_contents($stream);
        self::assertStringNotContainsString('PRIVATE_', $record);
        self::assertSame(['event', 'level', 'timestamp'], array_keys(json_decode($record, true, flags: JSON_THROW_ON_ERROR)));
        fclose($stream);
    }
}
