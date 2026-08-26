<?php

namespace Dktaylor\DevToolkit\Tests\Unit;

use Dktaylor\DevToolkit\Composer\ScriptHandler;
use PHPUnit\Framework\TestCase;

final class ScriptHandlerTest extends TestCase
{
    public function testParsesConfiguredDevToolsList(): void
    {
        $manifest = ['extra' => ['dev-tools' => ['tools/phpstan', 'tools/php-cs-fixer']]];

        self::assertSame(['tools/phpstan', 'tools/php-cs-fixer'], ScriptHandler::parseToolDirectories($manifest));
    }

    public function testReturnsEmptyListWhenNotConfigured(): void
    {
        self::assertSame([], ScriptHandler::parseToolDirectories([]));
        self::assertSame([], ScriptHandler::parseToolDirectories(['extra' => []]));
        self::assertSame([], ScriptHandler::parseToolDirectories(['extra' => ['dev-tools' => 'not-a-list']]));
    }

    public function testIgnoresNonStringEntries(): void
    {
        $manifest = ['extra' => ['dev-tools' => ['tools/phpstan', 123, null, 'tools/php-cs-fixer']]];

        self::assertSame(['tools/phpstan', 'tools/php-cs-fixer'], ScriptHandler::parseToolDirectories($manifest));
    }
}
