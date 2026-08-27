<?php

namespace Dktaylor\DevToolkit\Command;

use Dktaylor\DevToolkit\Dev\LogSource;
use Dktaylor\DevToolkit\Dev\LogSources;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'dev:logs',
    description: 'Follow local dev logs — the Symfony web server and each Docker service. Lists the sources when run without a target.',
)]
final class DevLogsCommand extends AbstractDevCommand
{
    protected function configure(): void
    {
        $this
            ->addArgument('source', InputArgument::OPTIONAL, 'Which log to follow ("app", or a Docker service such as "database"). Omit to list the available sources.')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Follow every source at once, tagging each line with its source name.')
            ->setHelp(<<<'HELP'
                  <info>%command.name%</info>            list the available log sources and whether each is running
                  <info>%command.name% app</info>        follow the Symfony web server log (web server + PHP + app)
                  <info>%command.name% database</info>   follow a single Docker Compose service
                  <info>%command.name% --all</info>      follow every source at once, each line tagged with its source
                HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $sources = LogSources::build($this->hasComposeFile(), $this->discoverComposeServices());

        if ((bool) $input->getOption('all')) {
            return $this->followAll($io, $output, $sources);
        }

        $target = $input->getArgument('source');
        if (null === $target) {
            $this->listSources($io, $sources);

            return Command::SUCCESS;
        }

        $source = LogSources::find($sources, (string) $target);
        if (!$source instanceof LogSource) {
            $io->error(sprintf('Unknown log source "%s".', (string) $target));
            $this->listSources($io, $sources);

            return Command::INVALID;
        }

        $io->title(sprintf('Following "%s" logs (Ctrl-C to stop)', $source->name));

        return $this->runProcess($source->command, $output);
    }

    /**
     * @param list<LogSource> $sources
     */
    private function listSources(SymfonyStyle $io, array $sources): void
    {
        $io->title('Available dev log sources');

        $serverRunning = $this->isServerRunning();
        $runningServices = $this->runningServiceNames();

        $rows = [];
        foreach ($sources as $source) {
            $live = LogSources::APP === $source->name
                ? $serverRunning
                : \in_array($source->name, $runningServices, true);

            $rows[] = [
                $source->name,
                $live ? '<info>● running</info>' : '<comment>○ stopped</comment>',
                $source->description,
            ];
        }

        $io->table(['Source', 'Status', 'Description'], $rows);
        $io->writeln([
            'Follow one:   <info>bin/console dev:logs app</info> (or a service name above)',
            'Follow all:   <info>bin/console dev:logs --all</info>',
        ]);
        $io->newLine();
    }

    /**
     * Follows every source concurrently, prefixing each line with its source name. Streams until
     * interrupted — Ctrl-C reaches the child processes through the shared process group.
     *
     * @param list<LogSource> $sources
     */
    private function followAll(SymfonyStyle $io, OutputInterface $output, array $sources): int
    {
        if ([] === $sources) {
            $io->warning('No log sources found.');

            return Command::SUCCESS;
        }

        $io->title('Following all dev logs (Ctrl-C to stop)');

        /** @var list<Process> $processes */
        $processes = [];
        foreach ($sources as $source) {
            $process = new Process($source->multiplexCommand, $this->projectDir, timeout: null);

            try {
                $process->start($this->prefixedWriter($output, $source->name));
            } catch (\Throwable $e) {
                $output->writeln(sprintf('<comment>[%s] could not start: %s</comment>', $source->name, $e->getMessage()));

                continue;
            }

            $processes[] = $process;
        }

        if ([] === $processes) {
            $io->error('Could not start any log stream.');

            return Command::FAILURE;
        }

        while ([] !== array_filter($processes, static fn (Process $p): bool => $p->isRunning())) {
            usleep(200_000);
        }

        return Command::SUCCESS;
    }

    /**
     * Builds a process callback that splits streamed output into lines and tags each with the source.
     */
    private function prefixedWriter(OutputInterface $output, string $name): callable
    {
        return static function (string $type, string $buffer) use ($output, $name): void {
            foreach (preg_split('/\R/', rtrim($buffer, "\r\n")) ?: [] as $line) {
                if ('' !== $line) {
                    $output->writeln(sprintf('<comment>[%s]</comment> %s', $name, $line));
                }
            }
        };
    }

    /**
     * @return list<string>
     */
    private function discoverComposeServices(): array
    {
        if (!$this->hasComposeFile()) {
            return [];
        }

        return LogSources::parseServiceList($this->capture(['docker', 'compose', 'config', '--services']));
    }

    /**
     * @return list<string>
     */
    private function runningServiceNames(): array
    {
        if (!$this->hasComposeFile()) {
            return [];
        }

        return LogSources::parseServiceList($this->capture(['docker', 'compose', 'ps', '--services', '--status', 'running']));
    }

    private function hasComposeFile(): bool
    {
        foreach (['compose.yaml', 'compose.yml', 'docker-compose.yaml', 'docker-compose.yml'] as $file) {
            if (is_file($this->projectDir.'/'.$file)) {
                return true;
            }
        }

        return false;
    }

    private function isServerRunning(): bool
    {
        $status = $this->captureServerStatus();

        return null !== $status && str_contains($status, 'Listening on');
    }

    /**
     * Runs a short-lived command and returns its stdout, or null if it fails or cannot be started.
     *
     * @param list<string> $command
     */
    private function capture(array $command): ?string
    {
        $process = new Process($command, $this->projectDir, timeout: 15);

        try {
            $process->run();
        } catch (\Throwable) {
            return null;
        }

        return $process->isSuccessful() ? $process->getOutput() : null;
    }
}
