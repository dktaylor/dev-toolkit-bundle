<?php

namespace Dktaylor\DevToolkit\Composer;

/**
 * Composer script handlers for the consuming project's tooling.
 *
 * Reference from the project's composer.json, e.g.:
 *
 *     "scripts": {
 *         "install-tools": ["Dktaylor\\DevToolkit\\Composer\\ScriptHandler::installTools"],
 *         "post-install-cmd": ["@auto-scripts", "@install-tools"],
 *         "post-update-cmd":  ["@auto-scripts", "@install-tools"]
 *     }
 */
final class ScriptHandler
{
    /**
     * Installs the isolated dev tools (e.g. PHPStan, PHP-CS-Fixer) that live under tools/ in the
     * consuming project. Skipped for --no-dev installs (COMPOSER_DEV_MODE=0), so production deploys
     * never pull dev-only tooling. The tool directories are read from the project's
     * composer.json "extra.dev-tools" list.
     */
    public static function installTools(): void
    {
        if ('0' === getenv('COMPOSER_DEV_MODE')) {
            fwrite(\STDOUT, 'Skipping dev-only tools install (--no-dev).'.\PHP_EOL);

            return;
        }

        $composer = (string) getenv('COMPOSER_BINARY');
        $prefix = '' !== $composer
            ? escapeshellarg(\PHP_BINARY).' '.escapeshellarg($composer)
            : 'composer';

        foreach (self::toolDirectories() as $dir) {
            fwrite(\STDOUT, sprintf('> Installing tools in %s'.\PHP_EOL, $dir));
            passthru($prefix.' install --working-dir='.escapeshellarg($dir), $exitCode);

            if (0 !== $exitCode) {
                throw new \RuntimeException(sprintf('Tool install failed in %s (exit code %d).', $dir, $exitCode));
            }
        }
    }

    /**
     * @return list<string>
     */
    private static function toolDirectories(): array
    {
        // Composer runs script handlers from the project root; COMPOSER points at its composer.json.
        $manifestPath = getenv('COMPOSER') ?: getcwd().'/composer.json';
        $manifest = json_decode((string) @file_get_contents((string) $manifestPath), true);

        return self::parseToolDirectories(\is_array($manifest) ? $manifest : []);
    }

    /**
     * Extracts the "extra.dev-tools" string list from a decoded composer.json manifest.
     *
     * @param array<mixed> $manifest
     *
     * @return list<string>
     */
    public static function parseToolDirectories(array $manifest): array
    {
        $extra = \is_array($manifest['extra'] ?? null) ? $manifest['extra'] : [];
        $tools = \is_array($extra['dev-tools'] ?? null) ? $extra['dev-tools'] : [];

        $directories = [];
        foreach ($tools as $tool) {
            if (\is_string($tool)) {
                $directories[] = $tool;
            }
        }

        return $directories;
    }
}