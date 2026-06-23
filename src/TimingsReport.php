<?php

declare(strict_types=1);

namespace PhpTimings;

/**
 * Builds a readable report from the collected records.
 */
final class TimingsReport
{
    public static function generate(): string
    {
        $records = TimingsRecord::getAll();
        $start = TimingsHandler::getStartTime();
        $sampleTime = $start > 0 ? hrtime(true) - $start : 0;

        /** @var array<int, list<TimingsRecord>> $childrenByParent */
        $childrenByParent = [];
        foreach ($records as $record) {
            $childrenByParent[$record->getParentId() ?? 0][] = $record;
        }

        $lines = [];
        $lines[] = 'Timings report';
        $lines[] = 'Sample time: ' . self::formatMs($sampleTime)
            . ' (' . count($records) . ' records)';
        $lines[] = str_repeat('-', 110);

        $roots = $childrenByParent[0] ?? [];
        self::sortByTotal($roots);

        /** @var array<string, list<TimingsRecord>> $byGroup */
        $byGroup = [];
        foreach ($roots as $root) {
            $byGroup[$root->getGroup()][] = $root;
        }

        foreach ($byGroup as $group => $groupRoots) {
            $lines[] = '';
            $lines[] = '# ' . $group;
            foreach ($groupRoots as $root) {
                self::appendRecord($lines, $root, $childrenByParent, $sampleTime, 1);
            }
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param list<string>                     $lines
     * @param array<int, list<TimingsRecord>>  $childrenByParent
     */
    private static function appendRecord(array &$lines, TimingsRecord $record, array $childrenByParent, int $sampleTime, int $depth): void
    {
        $indent = str_repeat('  ', $depth);
        $count = $record->getCount();
        $total = $record->getTotalTime();
        $avg = $count > 0 ? (int) ($total / $count) : 0;
        $pct = $sampleTime > 0 ? ($total / $sampleTime) * 100 : 0.0;

        $label = $indent . $record->getName();

        $lines[] = sprintf(
            '%-48s Total: %11s  Count: %7d  Avg: %11s  Peak: %11s  %%: %5.1f  Viol: %d',
            $label,
            self::formatMs($total),
            $count,
            self::formatMs($avg),
            self::formatMs($record->getPeakTime()),
            $pct,
            $record->getViolations(),
        );

        $children = $childrenByParent[$record->getId()] ?? [];
        self::sortByTotal($children);
        foreach ($children as $child) {
            self::appendRecord($lines, $child, $childrenByParent, $sampleTime, $depth + 1);
        }
    }

    /** @param list<TimingsRecord> $records */
    private static function sortByTotal(array &$records): void
    {
        usort($records, static fn(TimingsRecord $a, TimingsRecord $b): int => $b->getTotalTime() <=> $a->getTotalTime());
    }

    private static function formatMs(int $ns): string
    {
        return number_format($ns / 1_000_000, 3, '.', '') . 'ms';
    }
}
