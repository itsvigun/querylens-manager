<?php

declare(strict_types=1);

namespace App\Command;

use App\Security\OwnerConfiguration;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:check-configuration', description: 'Validate owner configuration without connecting to any database or provider.')]
final class CheckConfigurationCommand extends Command
{
    public function __construct(private readonly OwnerConfiguration $configuration) { parent::__construct(); }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Owner configuration is valid. No external connection was attempted.');
        return Command::SUCCESS;
    }
}
