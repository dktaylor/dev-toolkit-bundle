<?php

namespace Dktaylor\DevToolkit\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'dev-toolkit:install',
    description: 'Scaffold the dev-toolkit config (quality tooling, Makefile, hooks, CI) into this project.',
)]
final class ScaffoldCommand extends Command
{
    /**
     * Stub file (relative to resources/stubs) => destination (relative to the project root).
     */
    private const FILES = [
        'php-cs-fixer.dist.php' => '.php-cs-fixer.dist.php',
        'phpstan.dist.neon' => 'phpstan.dist.neon',
        'Makefile' => 'Makefile',
        'ci.yaml' => '.github/workflows/ci.yaml',
        'pre-commit' => '.githooks/pre-commit',
        'tools/phpstan/composer.json' => 'tools/phpstan/composer.json',
        'tools/php-cs-fixer/composer.json' => 'tools/php-cs-fixer/composer.json',
    ];

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite files that already exist.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Installing dev-toolkit');

        $force = (bool) $input->getOption('force');
        $stubsDir = \dirname(__DIR__, 2).'/resources/stubs';

        $io->section('Config files');
        foreach (self::FILES as $stub => $dest) {
            $this->copyStub($io, $stubsDir.'/'.$stub, $this->projectDir.'/'.$dest, $dest, $force);
        }

        $hook = $this->projectDir.'/.githooks/pre-commit';
        if (is_file($hook)) {
            @chmod($hook, 0o755);
        }

        $io->section('composer.json');
        $this->patchComposerJson($io);

        $io->success('dev-toolkit installed.');
        $io->writeln(' Next steps:');
        $io->writeln('  1. <info>composer install</info>   (installs the isolated tools declared in extra.dev-tools)');
        $io->writeln('  2. <info>make hooks</info>          (optional: enable the pre-commit hook)');
        $io->writeln('  3. <info>bin/console dev:up</info>');
        $io->newLine();

        return Command::SUCCESS;
    }

    private function copyStub(SymfonyStyle $io, string $source, string $dest, string $label, bool $force): void
    {
        if (!is_file($source)) {
            $io->writeln(sprintf('  <error>missing stub: %s</error>', $source));

            return;
        }

        if (is_file($dest) && !$force) {
            $io->writeln(sprintf('  <comment>skip </comment> %s (exists; use --force to overwrite)', $label));

            return;
        }

        $dir = \dirname($dest);
        if (!is_dir($dir) && !@mkdir($dir, 0o755, true) && !is_dir($dir)) {
            $io->writeln(sprintf('  <error>could not create %s</error>', $dir));

            return;
        }

        if (false === @copy($source, $dest)) {
            $io->writeln(sprintf('  <error>could not write %s</error>', $label));

            return;
        }

        $io->writeln(sprintf('  <info>write</info> %s', $label));
    }

    private function patchComposerJson(SymfonyStyle $io): void
    {
        $path = $this->projectDir.'/composer.json';
        if (!is_file($path)) {
            $io->writeln('  <error>no composer.json found</error>');

            return;
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (!\is_array($data)) {
            $io->writeln('  <error>composer.json is not valid JSON</error>');

            return;
        }

        $changed = false;
        $scripts = \is_array($data['scripts'] ?? null) ? $data['scripts'] : [];

        $defaults = [
            'phpstan' => ['tools/phpstan/vendor/bin/phpstan analyse src tests --no-progress'],
            'cs-fix' => ['tools/php-cs-fixer/vendor/bin/php-cs-fixer fix'],
            'cs-check' => ['tools/php-cs-fixer/vendor/bin/php-cs-fixer fix --dry-run --diff'],
            'security-check' => ['composer audit'],
            'test' => ['phpunit'],
            'quality' => ['@phpstan', '@cs-check', '@security-check', '@test'],
            'install-tools' => ['Dktaylor\\DevToolkit\\Composer\\ScriptHandler::installTools'],
        ];
        foreach ($defaults as $name => $value) {
            if (!\array_key_exists($name, $scripts)) {
                $scripts[$name] = $value;
                $changed = true;
                $io->writeln(sprintf('  <info>script</info> %s', $name));
            }
        }

        // Ensure the install/update lifecycle runs @install-tools (append, never overwrite).
        foreach (['post-install-cmd', 'post-update-cmd'] as $lifecycle) {
            $existing = $this->asList($scripts[$lifecycle] ?? null);
            if (!\in_array('@install-tools', $existing, true)) {
                $existing[] = '@install-tools';
                $scripts[$lifecycle] = $existing;
                $changed = true;
                $io->writeln(sprintf('  <info>hook</info>   %s -> @install-tools', $lifecycle));
            }
        }
        $data['scripts'] = $scripts;

        $extra = \is_array($data['extra'] ?? null) ? $data['extra'] : [];
        if (!\array_key_exists('dev-tools', $extra)) {
            $extra['dev-tools'] = ['tools/phpstan', 'tools/php-cs-fixer'];
            $data['extra'] = $extra;
            $changed = true;
            $io->writeln('  <info>extra</info>  dev-tools');
        }

        if (!$changed) {
            $io->writeln('  <comment>already configured</comment>');

            return;
        }

        $json = json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        if (false === $json) {
            $io->writeln('  <error>failed to encode composer.json</error>');

            return;
        }

        file_put_contents($path, $json.\PHP_EOL);
    }

    /**
     * Normalizes a composer script value (string or list) into a list of strings.
     *
     * @return list<string>
     */
    private function asList(mixed $value): array
    {
        if (\is_string($value)) {
            return [$value];
        }

        if (!\is_array($value)) {
            return [];
        }

        $list = [];
        foreach ($value as $item) {
            if (\is_string($item)) {
                $list[] = $item;
            }
        }

        return $list;
    }
}
