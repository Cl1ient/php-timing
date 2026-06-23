<?php

declare(strict_types=1);

namespace PhpTimings;

use LogicException;

/**
 * Accumulated data for a (handler, parent) pair.
 * A handler may own several records when measured under different parents.
 */
final class TimingsRecord
{
    /** @var array<int, TimingsRecord> keyed by record id (spl_object_id) */
    private static array $records = [];

    private static ?TimingsRecord $currentRecord = null;

    private readonly int $id;

    /** Total measured calls. */
    private int $count = 0;
    /** Calls measured during the current tick. */
    private int $curCount = 0;
    /** Timestamp (ns) of the last startTiming(), 0 when idle. */
    private int $start = 0;
    /** Cumulative time (ns). */
    private int $totalTime = 0;
    /** Time accumulated during the current tick */
    private int $curTickTotal = 0;
    /** Number of tick-duration-limit overruns. */
    private int $violations = 0;
    /** Ticks during which this record was active. */
    private int $ticksActive = 0;
    /** Longest single call (ns). */
    private int $peakTime = 0;

    public function __construct(
        private readonly TimingsHandler $handler,
        private readonly ?TimingsRecord $parentRecord,
    ) {
        $this->id = spl_object_id($this);
        self::$records[$this->id] = $this;
    }

    /** @return array<int, TimingsRecord> */
    public static function getAll(): array
    {
        return self::$records;
    }

    public static function getCurrentRecord(): ?TimingsRecord
    {
        return self::$currentRecord;
    }

    public static function clearRecords(): void
    {
        self::$records = [];
        self::$currentRecord = null;
    }

    /**
     * Commits the current-tick counters, call once per tick.
     *
     * @param bool $measure false discards the current tick's measurement.
     */
    public static function tick(bool $measure): void
    {
        if ($measure) {
            foreach (self::$records as $record) {
                if ($record->curCount > 0) {
                    if ($record->curTickTotal > Timings::$tickDurationLimit) {
                        $record->violations += (int) round($record->curTickTotal / Timings::$tickDurationLimit);
                    }
                    $record->curTickTotal = 0;
                    $record->curCount = 0;
                    $record->ticksActive++;
                }
            }
        } else {
            foreach (self::$records as $record) {
                $record->totalTime -= $record->curTickTotal;
                $record->count -= $record->curCount;
                $record->curTickTotal = 0;
                $record->curCount = 0;
            }
        }
    }

    public function startTiming(int $now): void
    {
        $this->start = $now;
        self::$currentRecord = $this;
    }

    public function stopTiming(int $now): void
    {
        if ($this->start === 0) {
            return;
        }
        if (self::$currentRecord !== $this) {
            if (self::$currentRecord === null) {
                // uhhhhh timings were likely reset mid-measurement.
                return;
            }
            throw new LogicException(
                "Timing order mismatch: stopping \"{$this->getName()}\" "
                . "while the current record is \"" . self::$currentRecord->getName() . "\""
            );
        }

        self::$currentRecord = $this->parentRecord;

        $diff = $now - $this->start;
        $this->totalTime += $diff;
        $this->curTickTotal += $diff;
        $this->curCount++;
        $this->count++;
        $this->start = 0;
        if ($diff > $this->peakTime) {
            $this->peakTime = $diff;
        }
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getParentId(): ?int
    {
        return $this->parentRecord?->getId();
    }

    public function getHandler(): TimingsHandler
    {
        return $this->handler;
    }

    public function getName(): string
    {
        return $this->handler->getName();
    }

    public function getGroup(): string
    {
        return $this->handler->getGroup();
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function getTotalTime(): int
    {
        return $this->totalTime;
    }

    public function getViolations(): int
    {
        return $this->violations;
    }

    public function getTicksActive(): int
    {
        return $this->ticksActive;
    }

    public function getPeakTime(): int
    {
        return $this->peakTime;
    }
}
