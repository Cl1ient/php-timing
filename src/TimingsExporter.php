<?php

declare(strict_types=1);

namespace PhpTimings;

/**
 * Serializes collected records into a structured payload for the web viewer.
 *
 * Times are exported in nanoseconds; derived metrics (averages, percentages)
 * are computed by the viewer.
 */
final class TimingsExporter
{
    /** Payload schema version, bumped on breaking changes. */
    public const VERSION = 1;

    /**
     * @return array{
     *     version: int,
     *     createdAt: int,
     *     sampleTimeNs: int,
     *     tickDurationLimitNs: int,
     *     records: list<array{
     *         id: int, parentId: int|null, name: string, group: string,
     *         count: int, totalTimeNs: int, peakTimeNs: int,
     *         violations: int, ticksActive: int
     *     }>
     * }
     */
    public static function toArray(): array
    {
        $start = TimingsHandler::getStartTime();
        $sampleTime = $start > 0 ? hrtime(true) - $start : 0;

        $records = [];
        foreach (TimingsRecord::getAll() as $record) {
            $records[] = [
                'id' => $record->getId(),
                'parentId' => $record->getParentId(),
                'name' => $record->getName(),
                'group' => $record->getGroup(),
                'count' => $record->getCount(),
                'totalTimeNs' => $record->getTotalTime(),
                'peakTimeNs' => $record->getPeakTime(),
                'violations' => $record->getViolations(),
                'ticksActive' => $record->getTicksActive(),
            ];
        }

        return [
            'version' => self::VERSION,
            'createdAt' => time(),
            'sampleTimeNs' => $sampleTime,
            'tickDurationLimitNs' => Timings::$tickDurationLimit,
            'records' => $records,
        ];
    }

    public static function toJson(int $flags = 0): string
    {
        return json_encode(self::toArray(), JSON_THROW_ON_ERROR | $flags);
    }
}
