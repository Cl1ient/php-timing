<?php

declare(strict_types=1);

namespace PhpTimings\Tests;

use PhpTimings\Timings;
use PhpTimings\TimingsHandler;
use PhpTimings\TimingsRecord;
use PHPUnit\Framework\TestCase;

final class TimingsHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        Timings::init();
        TimingsHandler::setEnabled(true);
    }

    protected function tearDown(): void
    {
        TimingsHandler::setEnabled(false);
    }

    public function testRecordsAccumulate(): void
    {
        $handler = Timings::getHandler('Test Op');

        for ($i = 0; $i < 5; $i++) {
            $handler->time(static fn() => usleep(100));
        }

        $records = array_values(array_filter(
            TimingsRecord::getAll(),
            static fn(TimingsRecord $r): bool => $r->getName() === 'Test Op',
        ));

        self::assertCount(1, $records);
        self::assertSame(5, $records[0]->getCount());
        self::assertGreaterThan(0, $records[0]->getTotalTime());
    }

    public function testNestedTimingsCreateChildRecord(): void
    {
        $parent = Timings::getHandler('Parent');
        $child = Timings::getHandler('Child', $parent);

        $parent->time(static function () use ($child): void {
            $child->time(static fn() => usleep(100));
        });

        $childRecords = array_values(array_filter(
            TimingsRecord::getAll(),
            static fn(TimingsRecord $r): bool => $r->getName() === 'Child',
        ));

        self::assertCount(1, $childRecords);
        self::assertNotNull($childRecords[0]->getParentId());
    }

    public function testDisabledHandlerRecordsNothing(): void
    {
        TimingsHandler::setEnabled(false);

        $handler = Timings::getHandler('Disabled Op');
        $handler->time(static fn() => usleep(100));

        $records = array_filter(
            TimingsRecord::getAll(),
            static fn(TimingsRecord $r): bool => $r->getName() === 'Disabled Op',
        );

        self::assertCount(0, $records);
    }
}
