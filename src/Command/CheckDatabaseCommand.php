<?php

declare(strict_types=1);

namespace App\Command;

use App\Infrastructure\ManagerDatabase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:check-database', description: 'Read-only validation of the restricted Manager role and shared-schema compatibility.')]
final class CheckDatabaseCommand extends Command
{
    public function __construct(private readonly ManagerDatabase $database)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->database->connection()->fetchOne('SELECT 1 FROM manager.invitations LIMIT 1');
        } catch (\Throwable) {
            $output->writeln('Manager database configuration, role or schema check failed.');
            return Command::FAILURE;
        }
        $output->writeln('Restricted Manager database connection and schema are valid.');
        return Command::SUCCESS;
    }
}
