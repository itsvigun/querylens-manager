<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (is_file(dirname(__DIR__).'/.env.local')) {
    (new Dotenv())->loadEnv(dirname(__DIR__).'/.env.local');
}
$_SERVER['APP_ENV'] ??= $_ENV['APP_ENV'] ?? 'prod';
$_SERVER['APP_DEBUG'] ??= $_ENV['APP_DEBUG'] ?? '0';
