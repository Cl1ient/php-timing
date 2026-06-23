<?php

declare(strict_types=1);

namespace PhpTimings;

/**
 * Central timings registry: common root handlers plus a getHandler()
 * factory that shares handlers by name.
 */
final class Timings
{
    /**
     * Tick duration (ns) above which a violation is counted.
     * Defaults to 50 ms (20 ticks/second).
     */
    public static int $tickDurationLimit = 50_000_000;

    private static bool $initialized = false;

    public static TimingsHandler $fullTick;

    /** Time spent in garbage collection */
    public static TimingsHandler $garbageCollector;

    /** @var array<string, TimingsHandler> handler cache keyed by name */
    private static array $handlers = [];

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        self::$fullTick = new TimingsHandler('Full Tick');
        self::$garbageCollector = new TimingsHandler('Garbage Collector', self::$fullTick);
    }

    /**
     * Returns a shared handler, creating it if needed.
     *
     * @param string             $name   name shown in the report
     * @param TimingsHandler|null $parent explicit parent 
     * @param string             $group  display group
     */
    public static function getHandler(string $name, ?TimingsHandler $parent = null, string $group = 'Application'): TimingsHandler
    {
        $key = $group . '::' . $name . '::' . ($parent !== null ? spl_object_id($parent) : 'root');
        return self::$handlers[$key] ??= new TimingsHandler($name, $parent, $group);
    }
}
