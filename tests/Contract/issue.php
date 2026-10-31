<?php

declare(strict_types=1);

// Outputs disposable credentials only to the parent verifier's in-memory pipe.
use App\Invitation\InvitationAdministrator;
use App\Invitation\InvitationOptions;
use App\Invitation\IssuedInvitation;
use App\Invitation\PrintableName;

require dirname(__DIR__).'/bootstrap.php';
$kernel = new App\Kernel('test', false);
$kernel->boot();
$administrator = $kernel->getContainer()->get('test.service_container')->get(InvitationAdministrator::class);
$fixtures = [];
foreach (['login', 'expiry', 'revoke_race', 'limit_race', 'admit_first', 'rollback', 'unknown', 'budget'] as $case) {
    $options = new InvitationOptions();
    $options->name = 'Offline contract '.$case;
    $options->requestsPerMinute = '60';
    $issued = IssuedInvitation::generate(bin2hex(random_bytes(16)));
    if (!$administrator->create($issued, $options, 'owner')) { throw new RuntimeException('Unexpected issuance replay.'); }
    $fixtures[$case] = ['id' => $issued->id, 'credential' => $issued->credential];
}
$bits = '';
for ($point = 0; $point <= 0x10ffff; ++$point) { $bits .= PrintableName::contains($point) ? '1' : '0'; }
echo json_encode(['fixtures' => $fixtures, 'printable_sha256' => hash('sha256', $bits)], JSON_THROW_ON_ERROR);
$kernel->shutdown();
