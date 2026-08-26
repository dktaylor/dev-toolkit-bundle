<?php

namespace Dktaylor\DevToolkit\Command;

use Dktaylor\DevToolkit\Dev\ServerStatus;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: 'dev:up',
    description: 'Start the local dev environment: Docker services, the Symfony web server, and importmap assets.',
)]
final class DevUpCommand extends AbstractDevCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Starting local development environment');

        $this->reportManualSetup($io);

        if (is_file($this->projectDir.'/compose.yaml') || is_file($this->projectDir.'/compose.yml') || is_file($this->projectDir.'/docker-compose.yaml') || is_file($this->projectDir.'/docker-compose.yml')) {
            if (!$this->runStep($io, $output, 'Starting Docker services', ['docker', 'compose', 'up', '-d'])) {
                return Command::FAILURE;
            }
        }

        $io->section('Starting Symfony web server');
        if (!$this->startWebServer($output)) {
            $io->error('The Symfony web server failed to start.');

            return Command::FAILURE;
        }

        $this->startProxyIfConfigured($io, $output);

        // Only relevant when the project uses AssetMapper.
        if (is_file($this->projectDir.'/importmap.php')
            && !$this->runStep($io, $output, 'Installing JavaScript importmap assets', [\PHP_BINARY, 'bin/console', 'importmap:install'])) {
            return Command::FAILURE;
        }

        $server = ServerStatus::fromStatusOutput($this->captureServerStatus());

        $io->success('Development environment is up.');
        if (null !== $server->url) {
            $io->writeln(' • App:    <info>'.$server->url.'</info>');
        } else {
            $io->writeln(' • App:    run <info>symfony server:status</info> for the URL');
        }
        $io->writeln(' • Stop:   <info>bin/console dev:down</info>');
        if (!$server->proxyDomain) {
            $io->writeln(' • Tip:    reach the app by hostname instead of IP by adding a proxy domain to '
                .'<info>.symfony.local.yaml</info>'
                .' (https://symfony.com/doc/current/setup/symfony_server.html)');
        }
        $io->newLine();

        return Command::SUCCESS;
    }

    /**
     * Detects setup that this command intentionally does not perform automatically
     * (key generation, driver downloads, CA trust) and prints the exact commands to run.
     * Each check is gated on evidence the project actually uses that feature.
     */
    private function reportManualSetup(SymfonyStyle $io): void
    {
        $notes = [];

        // Symfony encrypted secret vault. Only nudge if the project uses it (the vault dir exists).
        // We never read the vault — only check the key files exist.
        $secretsDir = $this->projectDir.'/config/secrets';
        if (is_dir($secretsDir)
            && (!is_file($secretsDir.'/dev/dev.encrypt.public.php') || !is_file($secretsDir.'/dev/dev.decrypt.private.php'))) {
            $notes[] = 'Secret keys are missing — generate them with:'
                .\PHP_EOL.'      <info>php bin/console secrets:generate-keys</info>';
        }

        // Browser drivers for Panther tests. Only nudge if the project has the bdi tool installed.
        if (is_file($this->projectDir.'/vendor/bin/bdi') && !$this->hasBrowserDrivers()) {
            $notes[] = 'Browser drivers are missing (needed for Panther browser tests) — install them with:'
                .\PHP_EOL.'      <info>vendor/bin/bdi detect drivers</info>';
        }

        // Local Certificate Authority for trusted HTTPS on the Symfony server.
        if (!$this->hasLocalCertificateAuthority()) {
            $notes[] = 'The local HTTPS certificate is not installed yet — without it the browser will warn about the'
                .' self-signed certificate. Install and trust the local CA (one-time) with:'
                .\PHP_EOL.'      <info>symfony server:ca:install</info>';
        }

        if ([] !== $notes) {
            $io->warning('Some one-time setup is still required:');
            foreach ($notes as $note) {
                $io->writeln('  • '.$note);
            }
            $io->newLine();
        }
    }

    private function hasBrowserDrivers(): bool
    {
        $driversDir = $this->projectDir.'/drivers';
        if (!is_dir($driversDir)) {
            return false;
        }

        foreach (new \FilesystemIterator($driversDir) as $entry) {
            // Any real binary counts; ignore placeholder dotfiles such as .gitkeep.
            if ($entry->isFile()) {
                return true;
            }
        }

        return false;
    }

    private function hasLocalCertificateAuthority(): bool
    {
        $home = getenv('HOME') ?: '';
        if ('' === $home) {
            // Can't determine — assume installed so we don't nag with a possibly-wrong hint.
            return true;
        }

        // The Symfony CLI stores its local CA under ~/.symfony5/certs (older versions: ~/.symfony/certs).
        foreach (['/.symfony5/certs/rootCA.pem', '/.symfony/certs/rootCA.pem'] as $relative) {
            if (is_file($home.$relative)) {
                return true;
            }
        }

        return false;
    }

    private function startWebServer(OutputInterface $output): bool
    {
        // Redirect the launcher's stdio to /dev/null so the long-lived server daemon does not inherit
        // this command's output stream — otherwise it keeps the pipe open and hangs callers that wait
        // for EOF (e.g. `bin/console dev:up | tail`, or a captured subprocess). Readiness and the URL
        // are obtained separately via `server:status`. (POSIX-only redirect; targets Unix/WSL.)
        $process = Process::fromShellCommandline(
            'symfony server:start -d < /dev/null > /dev/null 2>&1',
            $this->projectDir,
            timeout: 60,
        );

        try {
            $process->run();
        } catch (\Throwable $e) {
            $output->writeln('<error>'.$e->getMessage().'</error>');

            return false;
        }

        for ($attempt = 0; $attempt < 30; ++$attempt) {
            $status = $this->captureServerStatus();
            if (null !== $status && str_contains($status, 'Listening on')) {
                $output->writeln('<info>Web server listening.</info>');

                return true;
            }

            usleep(500_000);
        }

        $output->writeln('<error>Timed out waiting for the server to start (check <info>symfony server:log</info>).</error>');

        return false;
    }

    /**
     * Starts the Symfony local proxy (which resolves this project's *.wip domains), but only when the
     * project opts in by declaring a "proxy" key in .symfony.local.yaml. The proxy is a shared,
     * machine-wide daemon, so we do not start it for projects that don't use hostname access.
     */
    private function startProxyIfConfigured(SymfonyStyle $io, OutputInterface $output): void
    {
        if (!$this->projectConfiguresProxy()) {
            return;
        }

        $io->section('Starting Symfony local proxy (.wip domains)');

        $process = Process::fromShellCommandline(
            'symfony proxy:start < /dev/null > /dev/null 2>&1',
            $this->projectDir,
            timeout: 30,
        );

        try {
            $process->run();
            $output->writeln('<info>Local proxy running.</info>');
        } catch (\Throwable $e) {
            $output->writeln('<comment>Could not start the local proxy: '.$e->getMessage().'</comment>');
        }
    }

    private function projectConfiguresProxy(): bool
    {
        foreach (['.symfony.local.yaml', '.symfony.local.yml'] as $file) {
            $path = $this->projectDir.'/'.$file;
            if (!is_file($path)) {
                continue;
            }

            try {
                $config = Yaml::parseFile($path);
            } catch (\Throwable) {
                continue;
            }

            if (\is_array($config) && \array_key_exists('proxy', $config)) {
                return true;
            }
        }

        return false;
    }
}