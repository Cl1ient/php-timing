# php-timings

Système de timings pour PHP inspiré de [PocketMine-MP](https://github.com/pmmp/PocketMine-MP)

> État actuel : la **base** est posée (collecte + rapport texte). Le format JSON
> pour le visualiseur web viendra ensuite.

## Concepts

- **`TimingsHandler`** — représente un segment de code nommé que l'on mesure.
  La hiérarchie parent/enfant est gérée automatiquement : une mesure qui démarre
  pendant qu'une autre est active devient son enfant.
- **`TimingsRecord`** — données accumulées pour un couple *(handler, parent)* :
  nombre d'appels, temps total, pic, violations, etc.
- **`Timings`** — registre central : handlers racines communs + fabrique
  `getHandler()` qui mutualise les handlers par nom.
- **`TimingsReport`** — génère le rapport (texte pour l'instant).

## Utilisation

```php
require __DIR__ . '/autoload.php'; // ou vendor/autoload.php avec Composer

use PhpTimings\Timings;
use PhpTimings\TimingsHandler;
use PhpTimings\TimingsReport;

Timings::init();
TimingsHandler::setEnabled(true);

$db = Timings::getHandler('Database Query', Timings::$fullTick, 'Database');

for ($i = 0; $i < 100; $i++) {
    Timings::$fullTick->startTiming();

    // closure (recommandé : ferme la mesure même en cas d'exception)
    $db->time(fn() => maRequete());

    // ou manuel
    // $db->startTiming(); ...; $db->stopTiming();

    Timings::$fullTick->stopTiming();
    TimingsHandler::tick(); // fin du cycle
}

echo TimingsReport::generate();
```

## Lancer l'exemple

```bash
php examples/basic.php
```

## Tests

```bash
composer install
vendor/bin/phpunit
```

## Prérequis

- PHP >= 8.1 (utilise `hrtime()` pour des mesures en nanosecondes)
- Composer (optionnel — un `autoload.php` sans dépendance est fourni)

