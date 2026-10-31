<?php

declare(strict_types=1);

namespace App\Logging;

use Psr\Log\AbstractLogger;

/** Framework diagnostics deliberately omit messages, exception data, URLs and context. */
final class SanitizedLogger extends AbstractLogger
{
    public function __construct(private mixed $stream = null) {}

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if (!in_array($level, ['warning', 'error', 'critical', 'alert', 'emergency'], true)) { return; }
        $stream = $this->stream ?? fopen('php://stderr', 'w');
        fwrite($stream, json_encode(['event' => 'console_error', 'level' => $level, 'timestamp' => gmdate('Y-m-d\TH:i:s\Z')], JSON_THROW_ON_ERROR)."\n");
    }
}
