<?php

declare(strict_types=1);

namespace App\Invitation;

use App\Infrastructure\ManagerDatabase;
use Doctrine\DBAL\ParameterType;

final readonly class InvitationReader
{
    private const FIELDS = 'id,name,created_at,expires_at,revoked_at,max_requests,budget_micro_usd,requests_per_minute,admitted_requests,charged_micro_usd,unknown_requests,first_used_at,last_used_at,remaining_requests,lifecycle,metadata_version';
    public const PAGE_SIZE = 25;

    public function __construct(private ManagerDatabase $database) {}

    public function list(int $page): array
    {
        try {
            return $this->database->connection()->fetchAllAssociative('SELECT '.self::FIELDS.' FROM manager.invitations ORDER BY created_at DESC,id DESC LIMIT :limit OFFSET :offset',
                ['limit' => self::PAGE_SIZE + 1, 'offset' => ($page - 1) * self::PAGE_SIZE], ['limit' => ParameterType::INTEGER, 'offset' => ParameterType::INTEGER]);
        } catch (\Throwable) { throw new \RuntimeException('Invitation data is unavailable. Check dedicated database provisioning.'); }
    }

    public function find(string $id): ?array
    {
        try {
            return $this->database->connection()->fetchAssociative('SELECT '.self::FIELDS.' FROM manager.invitations WHERE id=:id', ['id' => $id]) ?: null;
        } catch (\Throwable) { throw new \RuntimeException('Invitation data is unavailable. Check dedicated database provisioning.'); }
    }

    public function requests(string $id, int $page): array
    {
        try {
            return $this->database->connection()->fetchAllAssociative('SELECT request_id,created_at,completed_at,charged_micro_usd,usage_state,outcome,error_category,model,input_tokens,output_tokens,embedding_model,embedding_tokens FROM manager.requests WHERE token_id=:id ORDER BY created_at DESC,request_id DESC LIMIT :limit OFFSET :offset',
                ['id' => $id, 'limit' => self::PAGE_SIZE + 1, 'offset' => ($page - 1) * self::PAGE_SIZE], ['limit' => ParameterType::INTEGER, 'offset' => ParameterType::INTEGER]);
        } catch (\Throwable) { throw new \RuntimeException('Usage data is unavailable. Check dedicated database provisioning.'); }
    }
}
