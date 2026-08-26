<?php

namespace Dktaylor\DevToolkit\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;

/**
 * Shared plumbing for the local dev-environment commands (dev:up, dev:down, dev:status, dev:logs):
 * the project directory and helpers for running external processes and querying the Symfony server.
 */
abstract class AbstractDevCommand extends Command
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        protected readonly string $projectDir,
    ) {
        parent::__construct();
    }

    /**
     * Runs a command from the project root, streaming its output. Returns the exit code
     * (1 if the process could not be started, e.g. the binary is missing).
     *
     * @param list<string> $command
     */
    protected function runProcess(array $command, OutputInterface $output): int
    {
        $process = new Process($command, $this->projectDir, timeout: null);

        try {
            return $process->run(static function (string $type, string $buffer) use ($output): void {
                $output->write($buffer);
            });
        } catch (\Throwable $e) {
            $output->writeln('<error>'.$e->getMessage().'</error>');

            return 1;
        }
    }

    /**
     * Runs a labelled step and reports failure through the given style.
     *
     * @param list<string> $command
     */
    protected function runStep(SymfonyStyle $io, OutputInterface $output, string $label, array $command): bool
    {
        $io->section($label);
        if (0 !== $this->runProcess($command, $output)) {
            $io->error(sprintf('Step failed: %s', implode(' ', $command)));

            return false;
        }

        return true;
    }

    /**
     * Returns the output of `symfony server:status --no-ansi`, or null if it cannot be obtained.
     */
    protected function captureServerStatus(): ?string
    {
        $process = new Process(['symfony', 'server:status', '--no-ansi'], $this->projectDir, timeout: 15);

        try {
            $process->run();
        } catch (\Throwable) {
            return null;
        }

        return $process->isSuccessful() ? $process->getOutput() : null;
    }
}
