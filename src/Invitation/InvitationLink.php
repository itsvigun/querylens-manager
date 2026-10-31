<?php

declare(strict_types=1);

namespace App\Invitation;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class InvitationLink
{
    public function __construct(
        #[Autowire('%env(QUERYLENS_BASE_URL)%')] private string $baseUrl,
        #[Autowire('%kernel.environment%')] private string $environment = 'prod',
    ) {}
    public function for(#[\SensitiveParameter] string $credential): string
    {
        $parts = parse_url($this->baseUrl);
        $localHttp = $parts && ($parts['scheme'] ?? '') === 'http'
            && in_array($parts['host'] ?? '', ['localhost', '127.0.0.1'], true)
            && in_array($this->environment, ['dev', 'test', 'local'], true);
        if (!$parts || !filter_var($this->baseUrl, FILTER_VALIDATE_URL)
            || (($parts['scheme'] ?? '') !== 'https' && !$localHttp) || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || !in_array($parts['path'] ?? '', ['', '/'], true) || str_ends_with($parts['host'], '.example')) {
            throw new \LogicException('Configure QUERYLENS_BASE_URL with a trusted HTTPS origin; loopback HTTP is allowed only in development.');
        }
        return rtrim($this->baseUrl, '/').'/#at='.$credential;
    }
}
