<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use PhpTimings\Timings;
use PhpTimings\TimingsExporter;
use PhpTimings\TimingsHandler;
use PhpTimings\TimingsReport;

Timings::init();
TimingsHandler::setEnabled(true);

$db = Timings::getHandler('Database Query', Timings::$fullTick, 'Database');
$render = Timings::getHandler('Render', Timings::$fullTick);

for ($tick = 0; $tick < 100; $tick++) {
    Timings::$fullTick->startTiming();

    $db->time(static function (): void {
        usleep(random_int(100, 500));
    });

    $render->startTiming();
    usleep(random_int(50, 200));
    $render->stopTiming();

    Timings::$fullTick->stopTiming();

    TimingsHandler::tick();
}

echo TimingsReport::generate();

// Also export a JSON report for the web viewer (web/index.html).
$jsonPath = __DIR__ . '/timings.json';
file_put_contents($jsonPath, TimingsExporter::toJson(JSON_PRETTY_PRINT));
echo "\nJSON report written to {$jsonPath}\n";
echo "Open web/index.html and load it.\n";
