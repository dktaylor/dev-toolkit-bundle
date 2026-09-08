<?php

declare(strict_types=1);

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
     * Runs PHP-CS-Fixer in write mode. Reference as `"cs-fix": ["Dktaylor\\DevToolkit\\Composer\\ScriptHandler::csFix"]`.
     */
    public static function csFix(): void
    {
        self::runPhpCsFixer(['fix']);
    }

    /**
     * Runs PHP-CS-Fixer in dry-run mode. Reference as `"cs-check": ["Dktaylor\\DevToolkit\\Composer\\ScriptHandler::csCheck"]`.
     */
    public static function csCheck(): void
    {
        self::runPhpCsFixer(['fix', '--dry-run', '--diff']);
    }

    /**
     * Unlike PHPStan/Psalm (each has its own `phpVersion` config, decoupled from whatever interpreter
     * actually runs the tool), PHP-CS-Fixer has no such knob — its own fixers gate which syntax they'll
     * apply on the literal running `PHP_VERSION_ID`. Running it under a newer PHP than the project's
     * own declared floor (composer.json's `require.php`, e.g. ">=8.4") risks it silently introducing
     * syntax the floor doesn't support. So: run it under a `phpX.Y` binary matching that floor when one
     * exists on PATH; fall back to the default `php` otherwise (e.g. a CI leg that only has one PHP
     * version installed at all, where the floor and the runtime are necessarily the same thing anyway).
     *
     * @param list<string> $args
     */
    private static function runPhpCsFixer(array $args): void
    {
        $php = self::phpBinaryForProjectFloor();
        $command = array_map('escapeshellarg', array_merge(
            [$php, 'tools/php-cs-fixer/vendor/bin/php-cs-fixer'],
            $args,
        ));
        passthru(implode(' ', $command), $exitCode);

        if (0 !== $exitCode) {
            throw new \RuntimeException(sprintf('php-cs-fixer failed (exit code %d).', $exitCode));
        }
    }

    private static function phpBinaryForProjectFloor(): string
    {
        $floor = self::extractPhpFloor(self::projectManifest());
        if (null !== $floor && self::commandExists('php'.$floor)) {
            return 'php'.$floor;
        }

        return \PHP_BINARY;
    }

    /**
     * Extracts the "X.Y" floor from a composer.json manifest's `require.php` constraint (e.g. ">=8.4"
     * or "^8.4" -> "8.4"). Returns null if there's no parseable PHP requirement.
     *
     * @param array<mixed> $manifest
     */
    public static function extractPhpFloor(array $manifest): ?string
    {
        $constraint = $manifest['require']['php'] ?? null;
        if (!\is_string($constraint) || !preg_match('/(\d+)\.(\d+)/', $constraint, $matches)) {
            return null;
        }

        return $matches[1].'.'.$matches[2];
    }

    private static function commandExists(string $name): bool
    {
        foreach (explode(\PATH_SEPARATOR, (string) getenv('PATH')) as $dir) {
            if ('' !== $dir && is_executable($dir.\DIRECTORY_SEPARATOR.$name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function toolDirectories(): array
    {
        return self::parseToolDirectories(self::projectManifest());
    }

    /**
     * @return array<mixed>
     */
    private static function projectManifest(): array
    {
        // Composer runs script handlers from the project root; COMPOSER points at its composer.json.
        $manifestPath = getenv('COMPOSER') ?: getcwd().'/composer.json';
        $manifest = json_decode((string) @file_get_contents((string) $manifestPath), true);

        return \is_array($manifest) ? $manifest : [];
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
