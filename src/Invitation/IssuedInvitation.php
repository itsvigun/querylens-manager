<?php

declare(strict_types=1);

namespace App\Invitation;

final readonly class IssuedInvitation
{
    public function __construct(public string $id, #[\SensitiveParameter] public string $credential, public string $digest) {}
    public static function generate(string $id): self
    {
        if (!preg_match('/\A[a-f0-9]{32}\z/D', $id)) { throw new \InvalidArgumentException('Invalid issuance ID.'); }
        $credential = 'qlt_'.$id.'_'.rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        return new self($id, $credential, hash('sha256', $credential));
    }
    public function __debugInfo(): array { return ['id' => $this->id]; }
}
