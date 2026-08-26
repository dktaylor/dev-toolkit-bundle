<?php

namespace Dktaylor\DevToolkit\Dev;

/**
 * Parses the output of `symfony server:status` to determine how the app is reachable.
 *
 * A configured local-proxy hostname (e.g. https://app.wip) is preferred over the loopback
 * address, whose port can differ from the default 8000 when it is already in use.
 */
final class ServerStatus
{
    public function __construct(
        public readonly ?string $url,
        public readonly bool $proxyDomain,
    ) {
    }

    public static function fromStatusOutput(?string $status): self
    {
        if (null === $status) {
            return new self(null, false);
        }

        $domainUrl = self::extractLocalDomainUrl($status);
        if (null !== $domainUrl) {
            return new self($domainUrl, true);
        }

        if (1 === preg_match('#Listening on\s+(https?://\S+)#', $status, $matches)) {
            return new self(rtrim($matches[1], '/'), false);
        }

        if (1 === preg_match('#https?://127\.0\.0\.1:\d+#', $status, $matches)) {
            return new self($matches[0], false);
        }

        return new self(null, false);
    }

    /**
     * Extracts the first URL listed under the "Local Domains" section — a hostname served through
     * the Symfony local proxy. Returns null when no domain is attached.
     */
    private static function extractLocalDomainUrl(string $status): ?string
    {
        $inDomains = false;
        foreach (preg_split('/\R/', $status) ?: [] as $line) {
            if (1 === preg_match('/^\S/', $line)) {
                // Section headers start at column 0; their entries are indented below them.
                $inDomains = str_starts_with($line, 'Local Domains');

                continue;
            }

            if ($inDomains && 1 === preg_match('#https?://\S+#', $line, $matches)) {
                return rtrim($matches[0], '/');
            }
        }

        return null;
    }
}