<?php

namespace Dktaylor\DevToolkit\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'dev:down',
    description: 'Stop the local dev environment: the Symfony web server and Docker services.',
    aliases: ['dev:stop'],
)]
final class DevDownCommand extends AbstractDevCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Stopping local development environment');

        // Both steps are best-effort: a service that is already stopped is not an error.
        $steps = [
            ['Stopping Symfony web server', ['symfony', 'server:stop']],
            ['Stopping Docker services', ['docker', 'compose', 'stop']],
        ];

        foreach ($steps as [$label, $command]) {
            $io->section($label);
            $this->runProcess($command, $output);
        }

        $io->success('Development environment stopped.');
        $io->writeln(' Docker containers are stopped but preserved (data volume kept). Start again with <info>bin/console dev:up</info>.');
        $io->newLine();

        return Command::SUCCESS;
    }
}
