<?php

declare(strict_types=1);

namespace App\Command;

use App\Infrastructure\ManagerDatabase;
use App\Invitation\InvitationAdministrator;
use App\Invitation\InvitationLink;
use App\Invitation\InvitationOptions;
use App\Invitation\IssuedInvitation;
use App\Security\OwnerConfiguration;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:verify-local-integration', description: 'Issue and revoke a local verification invitation; check Python login/session without an AI request.')]
final class VerifyLocalIntegrationCommand extends Command
{
    private const PUBLIC_ORIGIN = 'http://127.0.0.1:8000';
    private const INTERNAL_ORIGIN = 'http://api:8000';

    public function __construct(
        private readonly InvitationAdministrator $administrator,
        private readonly InvitationLink $links,
        private readonly ManagerDatabase $database,
        private readonly OwnerConfiguration $owner,
        #[Autowire('%kernel.environment%')] private readonly string $environment,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->environment !== 'dev') {
            $output->writeln('This check is restricted to the explicitly connected local development stack.');
            return Command::FAILURE;
        }

        $id = bin2hex(random_bytes(16));
        $issued = IssuedInvitation::generate($id);
        $created = false;
        $cookie = null;
        $csrf = null;
        $passed = false;
        try {
            if ($this->links->for($issued->credential) !== self::PUBLIC_ORIGIN.'/#at='.$issued->credential
                || $this->database->connection()->fetchOne('SELECT current_database()') !== 'querylens') {
                throw new \RuntimeException('Unexpected local configuration.');
            }
            $options = new InvitationOptions();
            $options->name = 'Local integration verification';
            $created = $this->administrator->create($issued, $options, $this->owner->username);
            if (!$created) {
                throw new \RuntimeException('Unexpected verification replay.');
            }

            [$login, $headers] = $this->request('POST', '/api/access/login', ['token' => $issued->credential]);
            foreach ($headers as $header) {
                if (preg_match('/\ASet-Cookie: (querylens_dev_session=[^;]+)/i', $header, $match)) {
                    $cookie = $match[1];
                }
            }
            $csrf = $login['csrf_token'] ?? null;
            if (($login['authenticated'] ?? false) !== true || $cookie === null || !is_string($csrf)) {
                throw new \RuntimeException('Python login did not establish a local visitor session.');
            }
            [$status] = $this->request('GET', '/api/access/status', cookie: $cookie);
            if (($status['authenticated'] ?? false) !== true || ($status['remaining_requests'] ?? null) !== 10) {
                throw new \RuntimeException('Python session was not authenticated.');
            }
            $row = $this->database->connection()->fetchAssociative('SELECT admitted_requests,charged_micro_usd,unknown_requests FROM manager.invitations WHERE id=:id', ['id' => $id]);
            if (!$row || (int) $row['admitted_requests'] !== 0 || (int) $row['charged_micro_usd'] !== 0 || (int) $row['unknown_requests'] !== 0) {
                throw new \RuntimeException('A login must not consume usage.');
            }
            $passed = true;
        } catch (\Throwable) {
            $output->writeln('Local integration check failed. No credential or HTTP response was displayed.');
        } finally {
            if ($cookie !== null && $csrf !== null) {
                try {
                    $this->request('POST', '/api/access/logout', cookie: $cookie, csrf: $csrf);
                } catch (\Throwable) {
                    $output->writeln('Verification logout could not be confirmed; its invitation will be revoked.');
                }
            }
            if ($created) {
                try {
                    $this->administrator->revoke(bin2hex(random_bytes(16)), $id, $this->owner->username);
                } catch (\Throwable) {
                    $output->writeln('Revoke the invitation labeled Local integration verification from the owner console.');
                    $passed = false;
                }
            }
        }
        if ($passed) {
            $output->writeln('Local PHP issuance -> Python HTTP login -> session status -> logout passed. Zero questions/cost. Verification invitation revoked; audit retained.');
        }
        return $passed ? Command::SUCCESS : Command::FAILURE;
    }

    /** @return array{array, array} */
    private function request(string $method, string $path, #[\SensitiveParameter] array $body = [], #[\SensitiveParameter] ?string $cookie = null, #[\SensitiveParameter] ?string $csrf = null): array
    {
        $headers = [
            'Content-Type: application/json',
            'Origin: '.self::PUBLIC_ORIGIN,
            'X-QueryLens-Client: browser',
            'Sec-Fetch-Site: same-origin',
        ];
        if ($cookie !== null) {
            $headers[] = 'Cookie: '.$cookie;
        }
        if ($csrf !== null) {
            $headers[] = 'X-CSRF-Token: '.$csrf;
        }
        $options = ['method' => $method, 'header' => implode("\r\n", $headers), 'timeout' => 10, 'ignore_errors' => true, 'follow_location' => 0];
        if ($method === 'POST') {
            $options['content'] = json_encode($body, JSON_THROW_ON_ERROR);
        }
        $response = @file_get_contents(self::INTERNAL_ORIGIN.$path, false, stream_context_create(['http' => $options]));
        $responseHeaders = http_get_last_response_headers() ?? [];
        if ($response === false || !preg_match('/\AHTTP\/\S+ 200\b/', $responseHeaders[0] ?? '')) {
            throw new \RuntimeException('Local Python HTTP check failed.');
        }
        return [json_decode($response, true, flags: JSON_THROW_ON_ERROR), $responseHeaders];
    }
}
