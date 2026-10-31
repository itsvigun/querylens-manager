<?php

declare(strict_types=1);

namespace App\Security;

final readonly class OwnerConfiguration
{
    public function __construct(
        public string $username,
        #[\SensitiveParameter] public string $passwordHash,
        #[\SensitiveParameter] string $appSecret,
        public string $origin,
    ) {
        if ($username === '' || strlen($username) > 128 || strlen($appSecret) < 32
            || (password_get_info($passwordHash)['algoName'] ?? 'unknown') === 'unknown') {
            throw new \LogicException('Configure ADMIN_USERNAME, ADMIN_PASSWORD_HASH and APP_SECRET (at least 32 characters).');
        }
        $parts = parse_url($origin);
        if (!$parts || !isset($parts['scheme'], $parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment']) || !in_array($parts['path'] ?? '', ['', '/'], true)
            || ($parts['scheme'] !== 'https' && !($parts['scheme'] === 'http' && in_array($parts['host'], ['localhost', '127.0.0.1'], true)))) {
            throw new \LogicException('APP_ORIGIN must be a trusted HTTPS origin (loopback HTTP is allowed for development).');
        }
    }
}
