<?php

namespace Dktaylor\DevToolkit\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'dev:logs',
    description: 'Tail the local Symfony web server logs (Ctrl-C to stop).',
)]
final class DevLogsCommand extends AbstractDevCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Tailing Symfony web server logs');

        // `symfony server:log` follows the logs until interrupted; stream until the user stops it.
        return $this->runProcess(['symfony', 'server:log'], $output);
    }
}
