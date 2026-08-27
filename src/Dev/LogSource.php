<?php

namespace Dktaylor\DevToolkit\Dev;

/**
 * A single tailable log source in the local dev environment: the Symfony web server's
 * aggregated log, or one Docker Compose service.
 */
final class LogSource
{
    /**
     * @param list<string> $command          process that follows this source's log on its own
     * @param list<string> $multiplexCommand process used under --all; drops any per-line prefix
     *                                       that would collide with our own "[name]" tag
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $command,
        public readonly array $multiplexCommand,
    ) {
    }
}
