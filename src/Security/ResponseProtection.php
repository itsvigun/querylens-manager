<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class ResponseProtection
{
    public function __construct(private OwnerConfiguration $configuration) {}

    #[AsEventListener(priority: 512)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) { return; }
        $request = $event->getRequest();
        if ($request->getSchemeAndHttpHost() !== rtrim($this->configuration->origin, '/')) {
            throw new BadRequestHttpException('Untrusted origin.');
        }
    }

    #[AsEventListener(priority: -512)]
    public function onKernelResponse(ResponseEvent $event): void
    {
        $headers = $event->getResponse()->headers;
        $headers->set('Cache-Control', 'no-store, private');
        $headers->set('Referrer-Policy', 'no-referrer');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Content-Security-Policy', "default-src 'none'; style-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
        if ($event->getRequest()->isSecure()) { $headers->set('Strict-Transport-Security', 'max-age=31536000'); }
    }
}
