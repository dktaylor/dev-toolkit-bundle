<?php

namespace Dktaylor\DevToolkit\Tests\Unit;

use Dktaylor\DevToolkit\Dev\ServerStatus;
use PHPUnit\Framework\TestCase;

final class ServerStatusTest extends TestCase
{
    public function testReturnsNoUrlWhenStatusIsNull(): void
    {
        $server = ServerStatus::fromStatusOutput(null);

        self::assertNull($server->url);
        self::assertFalse($server->proxyDomain);
    }

    public function testResolvesLoopbackUrlFromListeningLine(): void
    {
        $status = implode("\n", [
            'Local Web Server',
            '    Listening on https://127.0.0.1:8001',
            '',
            'Local Domains',
            '',
            'Workers',
            '    PID 202819: /usr/bin/php8.5 -S 127.0.0.1:57114',
        ]);

        $server = ServerStatus::fromStatusOutput($status);

        self::assertSame('https://127.0.0.1:8001', $server->url);
        self::assertFalse($server->proxyDomain);
    }

    public function testPrefersLocalProxyDomainOverLoopback(): void
    {
        $status = implode("\n", [
            'Local Web Server',
            '    Listening on https://127.0.0.1:8001',
            '',
            'Local Domains',
            '    https://app.wip',
            '',
            'Workers',
            '    PID 202819: /usr/bin/php8.5 -S 127.0.0.1:57114',
        ]);

        $server = ServerStatus::fromStatusOutput($status);

        self::assertSame('https://app.wip', $server->url);
        self::assertTrue($server->proxyDomain);
    }

    public function testReturnsNoUrlWhenServerNotRunning(): void
    {
        $server = ServerStatus::fromStatusOutput("Local Web Server\n    Not Running\n");

        self::assertNull($server->url);
        self::assertFalse($server->proxyDomain);
    }
}