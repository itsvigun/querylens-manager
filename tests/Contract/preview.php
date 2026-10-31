<?php

declare(strict_types=1);

// Disposable local UI verification only; never generates a production identity.
require dirname(__DIR__).'/bootstrap.php';
umask(0077);
$path = dirname(__DIR__, 2).'/artifacts/preview.env';
$file = fopen($path, 'x');
if ($file === false) { throw new RuntimeException('Preview environment already exists.'); }
fwrite($file, 'APP_ENV=preview'."\nAPP_DEBUG=0\nAPP_SECRET=".bin2hex(random_bytes(32))."\nADMIN_USERNAME=owner\nADMIN_PASSWORD_HASH=".$_ENV['ADMIN_PASSWORD_HASH']."\nAPP_ORIGIN=http://localhost:8086\nQUERYLENS_BASE_URL=https://querylens.test\nMANAGER_DATABASE_URL=".$_ENV['MANAGER_DATABASE_URL']."\n");
fclose($file);
echo "Wrote private, disposable preview environment.\n";
