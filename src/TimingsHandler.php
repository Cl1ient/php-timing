<?php

declare(strict_types=1);

namespace PhpTimings;

use Closure;

/**
 * Entry point for measuring a named code segment
 */
final class TimingsHandler
{
    /** @var array<int, TimingsHandler> keyed by spl_object_id */
    private static array $handlers = [];

    private static bool $enabled = false;

    /** Timestamp (ns) when collection started */
    private static int $timingStart = 0;

    private ?TimingsRecord $rootRecord = null;

    private int $timingDepth = 0;

    /** @var array<int, TimingsRecord> child records keyed by parent record id */
    private array $recordsByParent = [];

    public function __construct(
        private readonly string $name,
        private readonly ?TimingsHandler $parent = null,
        private readonly string $group = 'Application',
    ) {
        self::$handlers[spl_object_id($this)] = $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getGroup(): string
    {
        return $this->group;
    }

    public static function isEnabled(): bool
    {
        return self::$enabled;
    }

    public static function setEnabled(bool $enable = true): void
    {
        self::$enabled = $enable;
        self::internalReload();
    }

    /** Collection start timestamp (ns, hrtime base) */
    public static function getStartTime(): int
    {
        return self::$timingStart;
    }

    /** Clears all collected data without changing the enabled state */
    public static function reload(): void
    {
        if (self::$enabled) {
            self::internalReload();
        }
    }

    private static function internalReload(): void
    {
        TimingsRecord::clearRecords();
        foreach (self::$handlers as $handler) {
            $handler->reset();
        }
        if (self::$enabled) {
            self::$timingStart = hrtime(true);
        }
    }

    /**
     * Commits the current tick's counters, call once per tick/cycle
     *
     * @param bool $measure false discards the current tick's measurement
     */
    public static function tick(bool $measure = true): void
    {
        if (self::$enabled) {
            TimingsRecord::tick($measure);
        }
    }

    private function reset(): void
    {
        $this->rootRecord = null;
        $this->recordsByParent = [];
        $this->timingDepth = 0;
    }

    public function startTiming(): void
    {
        if (self::$enabled) {
            $this->internalStartTiming(hrtime(true));
        }
    }

    private function internalStartTiming(int $now): void
    {
        if (++$this->timingDepth === 1) {
            if ($this->parent !== null) {
                $this->parent->internalStartTiming($now);
            }

            $current = TimingsRecord::getCurrentRecord();
            if ($current !== null) {
                $parentId = $current->getId();
                $record = $this->recordsByParent[$parentId]
                    ??= new TimingsRecord($this, $current);
            } else {
                $record = $this->rootRecord
                    ??= new TimingsRecord($this, null);
            }
            $record->startTiming($now);
        }
    }

    public function stopTiming(): void
    {
        if (self::$enabled) {
            $this->internalStopTiming(hrtime(true));
        }
    }

    private function internalStopTiming(int $now): void
    {
        if ($this->timingDepth === 0) {
            return;
        }
        if (--$this->timingDepth !== 0) {
            return;
        }

        $current = TimingsRecord::getCurrentRecord();
        if ($current !== null) {
            $current->stopTiming($now);
        }

        if ($this->parent !== null) {
            $this->parent->internalStopTiming($now);
        }
    }

    /**
     * Measures a closure and returns its result
     *
     * @template T
     * @param Closure():T $closure
     * @return T
     */
    public function time(Closure $closure)
    {
        $this->startTiming();
        try {
            return $closure();
        } finally {
            $this->stopTiming();
        }
    }
}
