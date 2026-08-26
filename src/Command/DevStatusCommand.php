<?php

namespace Dktaylor\DevToolkit\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'dev:status',
    description: 'Show the status of the local dev environment (Symfony web server and Docker services).',
)]
final class DevStatusCommand extends AbstractDevCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Local development environment status');

        $io->section('Symfony web server');
        $this->runProcess(['symfony', 'server:status'], $output);

        $io->section('Docker services');
        $this->runProcess(['docker', 'compose', 'ps'], $output);

        return Command::SUCCESS;
    }
}