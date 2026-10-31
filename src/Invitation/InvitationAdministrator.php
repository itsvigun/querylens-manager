<?php

declare(strict_types=1);

namespace App\Invitation;

use App\Infrastructure\ManagerDatabase;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class InvitationAdministrator
{
    public function __construct(private ManagerDatabase $database, private ValidatorInterface $validator) {}

    public function create(#[\SensitiveParameter] IssuedInvitation $invitation, InvitationOptions $options, string $owner): bool
    {
        $parameters = $this->parameters($options) + ['id' => $invitation->id, 'digest' => $invitation->digest, 'owner' => $owner];
        if ($parameters['days'] === null) { throw new \InvalidArgumentException('Expiry is required for issuance.'); }
        try {
            return (bool) $this->database->connection()->transactional(fn ($connection) => $connection->fetchOne('SELECT manager.create_invitation(:id,:digest,:name,:max,:budget,:rate,:days,:owner)', $parameters, $this->types($parameters)));
        } catch (\Throwable) { throw new \RuntimeException('Issuance could not be confirmed. Retry this form; if committed, its secret cannot be recovered.'); }
    }

    public function update(string $id, InvitationOptions $options, string $owner): string
    {
        $parameters = $this->parameters($options) + ['operation' => $options->operationId, 'id' => $id, 'version' => $options->version, 'owner' => $owner];
        return $this->mutate('SELECT manager.update_invitation(:operation,:id,:version,:name,:max,:budget,:rate,:days,:owner)', $parameters);
    }

    public function revoke(string $operation, string $id, string $owner): string
    {
        return $this->mutate('SELECT manager.revoke_invitation(:operation,:id,:owner)', ['operation' => $operation, 'id' => $id, 'owner' => $owner]);
    }

    private function parameters(InvitationOptions $options): array
    {
        if (count($this->validator->validate($options)) !== 0) { throw new \InvalidArgumentException('Invalid invitation options.'); }
        return ['name' => trim($options->name, ' '), 'max' => (int) $options->maxRequests, 'budget' => InvitationOptions::microUsd($options->budgetUsd), 'rate' => (int) $options->requestsPerMinute, 'days' => $options->expiresInDays === '' ? null : (int) $options->expiresInDays];
    }

    private function types(array $parameters): array
    {
        $types = [];
        foreach ($parameters as $key => $value) { $types[$key] = $value === null ? ParameterType::NULL : (is_int($value) ? ParameterType::INTEGER : ParameterType::STRING); }
        return $types;
    }

    private function mutate(string $sql, array $parameters): string
    {
        try { return $this->database->connection()->transactional(fn ($connection) => $connection->fetchOne($sql, $parameters, $this->types($parameters))); }
        catch (\Throwable) { throw new \RuntimeException('The change could not be confirmed. Retry the same form or inspect current invitation metadata.'); }
    }
}
