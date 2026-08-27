<?php

namespace Dktaylor\DevToolkit\Tests\Unit;

use Dktaylor\DevToolkit\Dev\LogSource;
use Dktaylor\DevToolkit\Dev\LogSources;
use PHPUnit\Framework\TestCase;

final class LogSourcesTest extends TestCase
{
    public function testBuildAlwaysIncludesTheAppSourceFirst(): void
    {
        $sources = LogSources::build(false, []);

        self::assertCount(1, $sources);
        self::assertSame(LogSources::APP, $sources[0]->name);
        self::assertSame(['symfony', 'server:log'], $sources[0]->command);
    }

    public function testBuildOmitsComposeServicesWhenNoComposeFile(): void
    {
        $sources = LogSources::build(false, ['database', 'redis']);

        self::assertCount(1, $sources);
        self::assertSame(LogSources::APP, $sources[0]->name);
    }

    public function testBuildAddsAServiceSourcePerComposeService(): void
    {
        $sources = LogSources::build(true, ['database', 'redis']);

        self::assertSame(['app', 'database', 'redis'], array_map(static fn (LogSource $s): string => $s->name, $sources));

        $database = $sources[1];
        self::assertSame(['docker', 'compose', 'logs', '--follow', 'database'], $database->command);
        // Under --all we drop compose's own prefix so only our "[name]" tag remains.
        self::assertContains('--no-log-prefix', $database->multiplexCommand);
        self::assertContains('database', $database->multiplexCommand);
    }

    public function testFindReturnsMatchingSourceOrNull(): void
    {
        $sources = LogSources::build(true, ['database']);

        self::assertSame('database', LogSources::find($sources, 'database')?->name);
        self::assertNull(LogSources::find($sources, 'nope'));
    }

    public function testParseServiceListSplitsTrimsAndDropsBlankLines(): void
    {
        self::assertSame(['database', 'redis'], LogSources::parseServiceList("database\nredis\n"));
        self::assertSame(['database', 'redis'], LogSources::parseServiceList("  database  \r\n\r\n  redis  "));
    }

    public function testParseServiceListReturnsEmptyForNullOrBlank(): void
    {
        self::assertSame([], LogSources::parseServiceList(null));
        self::assertSame([], LogSources::parseServiceList("   \n  \n"));
    }
}
