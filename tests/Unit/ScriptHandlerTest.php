<?php

declare(strict_types=1);

namespace Dktaylor\DevToolkit\Tests\Unit;

use Dktaylor\DevToolkit\Composer\ScriptHandler;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * @param array<mixed> $manifest
     */
    #[DataProvider('phpFloorProvider')]
    public function testExtractsPhpFloor(array $manifest, ?string $expected): void
    {
        self::assertSame($expected, ScriptHandler::extractPhpFloor($manifest));
    }

    /**
     * @return iterable<string, array{0: array<mixed>, 1: ?string}>
     */
    public static function phpFloorProvider(): iterable
    {
        yield 'gte constraint' => [['require' => ['php' => '>=8.4']], '8.4'];
        yield 'caret constraint' => [['require' => ['php' => '^8.4']], '8.4'];
        yield 'patch-qualified constraint' => [['require' => ['php' => '>=8.4.1']], '8.4'];
        yield 'no php requirement' => [['require' => ['ext-ctype' => '*']], null];
        yield 'no require section' => [[], null];
        yield 'non-string constraint' => [['require' => ['php' => ['not', 'a', 'string']]], null];
    }
}
