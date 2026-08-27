<?php

namespace Dktaylor\DevToolkit\Dev;

/**
 * Builds the list of tailable log sources for the local dev environment and parses the
 * `docker compose config --services` / `docker compose ps` output they derive from.
 *
 * Pure logic (unit-tested); the command layer runs the processes.
 */
final class LogSources
{
    /**
     * The Symfony web server log already aggregates the web server, PHP, and application logs,
     * so it is the single "app" source rather than three separate ones.
     */
    public const APP = 'app';

    /**
     * @param list<string> $composeServices service names from `docker compose config --services`
     *
     * @return list<LogSource>
     */
    public static function build(bool $hasComposeFile, array $composeServices): array
    {
        $sources = [
            new LogSource(
                self::APP,
                'Symfony web server — web server, PHP, and application logs',
                ['symfony', 'server:log'],
                ['symfony', 'server:log'],
            ),
        ];

        if ($hasComposeFile) {
            foreach ($composeServices as $service) {
                $sources[] = new LogSource(
                    $service,
                    sprintf('Docker Compose service: %s', $service),
                    ['docker', 'compose', 'logs', '--follow', $service],
                    ['docker', 'compose', 'logs', '--follow', '--no-log-prefix', $service],
                );
            }
        }

        return $sources;
    }

    /**
     * @param list<LogSource> $sources
     */
    public static function find(array $sources, string $name): ?LogSource
    {
        foreach ($sources as $source) {
            if ($source->name === $name) {
                return $source;
            }
        }

        return null;
    }

    /**
     * Parses newline-separated command output (e.g. `docker compose config --services` or
     * `docker compose ps --services`) into a clean, ordered list of names.
     *
     * @return list<string>
     */
    public static function parseServiceList(?string $output): array
    {
        if (null === $output || '' === trim($output)) {
            return [];
        }

        $names = [];
        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            $line = trim($line);
            if ('' !== $line) {
                $names[] = $line;
            }
        }

        return $names;
    }
}
