<?php

declare(strict_types=1);

use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Question\Question;

require dirname(__DIR__).'/vendor/autoload.php';
$path = dirname(__DIR__).'/.env.local';
if (file_exists($path)) {
    fwrite(STDERR, ".env.local already exists; edit it privately or move it before configuring again.\n");
    exit(1);
}
$question = new Question('Choose the owner password (16–72 bytes): ');
$question->setHidden(true)->setHiddenFallback(false);
$password = (new QuestionHelper())->ask(new ArgvInput(), new ConsoleOutput(), $question);
if (!is_string($password) || strlen($password) < 16 || strlen($password) > 72) {
    fwrite(STDERR, "Use a password of 16–72 bytes.\n"); exit(1);
}
umask(0077);
$file = fopen($path, 'x');
if ($file === false) { exit(1); }
fwrite($file, "APP_ENV=dev\nAPP_DEBUG=0\nAPP_SECRET=".bin2hex(random_bytes(32))."\nADMIN_USERNAME=owner\nADMIN_PASSWORD_HASH='".password_hash($password, PASSWORD_DEFAULT)."'\nAPP_ORIGIN=http://localhost:8086\nQUERYLENS_BASE_URL=https://querylens.example\nMANAGER_DATABASE_URL=\n");
fclose($file);
fwrite(STDOUT, "Created private .env.local. Configure the trusted QueryLens URL before issuing invitations.\n");
