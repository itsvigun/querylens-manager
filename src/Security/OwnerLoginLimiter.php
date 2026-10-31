<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RateLimiter\PeekableRequestRateLimiterInterface;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

final readonly class OwnerLoginLimiter implements PeekableRequestRateLimiterInterface
{
    public function __construct(private RateLimiterFactoryInterface $ownerLoginLimiter) {}
    private function limiter(): LimiterInterface { return $this->ownerLoginLimiter->create('owner'); }
    public function consume(Request $request): RateLimit { return $this->limiter()->consume(); }
    public function reset(Request $request): void { $this->limiter()->reset(); }
    public function peek(Request $request): RateLimit { return $this->limiter()->consume(0); }
}
