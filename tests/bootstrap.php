<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';
(new Symfony\Component\Filesystem\Filesystem())->remove(dirname(__DIR__).'/var/cache/test');
$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
$_SERVER['APP_DEBUG'] = $_ENV['APP_DEBUG'] = '0';
$_SERVER['APP_SECRET'] = $_ENV['APP_SECRET'] = str_repeat('test-only-', 8);
$_SERVER['ADMIN_USERNAME'] = $_ENV['ADMIN_USERNAME'] = 'owner';
$_SERVER['ADMIN_PASSWORD_HASH'] = $_ENV['ADMIN_PASSWORD_HASH'] = password_hash('offline-owner-password', PASSWORD_BCRYPT, ['cost' => 4]);
$_SERVER['APP_ORIGIN'] = $_ENV['APP_ORIGIN'] = 'http://localhost';
$_SERVER['QUERYLENS_BASE_URL'] = $_ENV['QUERYLENS_BASE_URL'] = 'https://querylens.test';
$_SERVER['MANAGER_DATABASE_URL'] = $_ENV['MANAGER_DATABASE_URL'] = getenv('TEST_MANAGER_DATABASE_URL') ?: '';
