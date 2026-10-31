<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final readonly class SessionLifetime
{
    public function __construct(private Security $security, private TokenStorageInterface $tokens) {}

    #[AsEventListener(priority: -32)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || $this->security->getUser() === null) { return; }
        $session = $event->getRequest()->getSession();
        $now = time();
        if ($now - $session->get('owner_activity', $now) >= 1800) {
            $this->tokens->setToken(null);
            $session->invalidate();
            $event->setResponse(new RedirectResponse('/login'));
            return;
        }
        $session->set('owner_activity', $now);
    }
}
