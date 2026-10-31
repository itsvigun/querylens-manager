<?php

declare(strict_types=1);

namespace App\Invitation;

use Symfony\Component\HttpFoundation\RequestStack;

final readonly class OperationIntents
{
    public function __construct(private RequestStack $requests) {}

    public function issue(string $action, ?string $target = null): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $id = bin2hex($bytes);
        $session = $this->requests->getSession();
        $intents = array_filter($session->get('admin_intents', []), static fn (array $intent) => $intent['expires'] > time());
        $intents = array_slice($intents, -19, null, true);
        $intents[$id] = ['action' => $action, 'target' => $target ?? $id, 'expires' => time() + 3600];
        $session->set('admin_intents', $intents);
        return $id;
    }

    public function allows(string $id, string $action, string $target): bool
    {
        $intent = $this->requests->getSession()->get('admin_intents', [])[$id] ?? null;
        return $intent !== null && $intent['expires'] > time() && $intent['action'] === $action && $intent['target'] === $target;
    }
}
