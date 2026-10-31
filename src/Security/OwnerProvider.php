<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/** @implements UserProviderInterface<InMemoryUser> */
final readonly class OwnerProvider implements UserProviderInterface
{
    public function __construct(private OwnerConfiguration $configuration) {}

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        if (!hash_equals($this->configuration->username, $identifier)) {
            throw new UserNotFoundException('Invalid credentials.');
        }

        return new InMemoryUser($identifier, $this->configuration->passwordHash, ['ROLE_ADMIN']);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof InMemoryUser) {
            throw new UnsupportedUserException();
        }
        // A changed password hash invalidates existing sessions through Symfony's user comparison.
        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return $class === InMemoryUser::class;
    }
}
