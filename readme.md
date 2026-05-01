Detain Tracy-Monolog Adapter
============================

[![Downloads this Month](https://img.shields.io/packagist/dm/detain/tracy-monolog-adapter.svg?style=flat)](https://packagist.org/packages/detain/tracy-monolog-adapter)
[![Stable version](http://img.shields.io/packagist/v/detain/tracy-monolog-adapter.svg?style=flat)](https://packagist.org/packages/detain/tracy-monolog-adapter)
[![License](https://img.shields.io/packagist/l/detain/tracy-monolog-adapter.svg?style=flat)](license.md)

A small bridge that lets the [Tracy](https://tracy.nette.org/) debugger
log through a [Monolog](https://github.com/Seldaek/monolog) logger so you
can route Tracy's log calls (warnings, errors, exceptions) through any
Monolog handler, processor or formatter you already use.

Includes:

* `Detain\TracyHasMono\TracyMonoLogger` — a `Tracy\ILogger` implementation
  backed by a `Monolog\Logger`.
* `Detain\TracyHasMono\Processors\TracyExceptionProcessor` — a Monolog
  processor that renders any `Throwable` found in a record's context as a
  Tracy BlueScreen HTML file.
* `Detain\TracyHasMono\Bridges\NetteDI\MonologExtension` — an optional
  Nette DI compiler extension that wires everything into a container.
* `Nextras\TracyMonologAdapter\Logger` — a legacy alias kept for
  backwards compatibility with code that still imports the old namespace.

## Requirements

| Dependency       | Version    |
|------------------|------------|
| PHP              | `>=8.1`    |
| `tracy/tracy`    | `^2.10`    |
| `monolog/monolog`| `^3.0`     |
| `nette/di`       | `^3.0` (only for the Nette DI bridge) |

## Installation

```bash
composer require detain/tracy-monolog-adapter
```

## Usage

### Plain PHP

Wire a Monolog logger and pass it to `TracyMonoLogger`, then register the
result with Tracy's `Debugger`.

```php
use Detain\TracyHasMono\Processors\TracyExceptionProcessor;
use Detain\TracyHasMono\TracyMonoLogger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Tracy\BlueScreen;
use Tracy\Debugger;

require __DIR__ . '/vendor/autoload.php';

$logDir = __DIR__ . '/log';

Debugger::enable(Debugger::PRODUCTION, $logDir);

$monolog = new Logger('app');
$monolog->pushHandler(new RotatingFileHandler($logDir . '/app.log'));
$monolog->pushProcessor(new TracyExceptionProcessor($logDir, new BlueScreen()));

Debugger::setLogger(new TracyMonoLogger($monolog));
```

After this, every call Tracy makes to its `ILogger` (warnings, errors,
uncaught exceptions, manual `Debugger::log()` calls) is routed through
your Monolog stack.

### Priority mapping

`TracyMonoLogger` maps Tracy priority strings to Monolog `Level` cases:

| Tracy constant         | Monolog `Level` |
|------------------------|-----------------|
| `ILogger::DEBUG`       | `Level::Debug`    |
| `ILogger::INFO`        | `Level::Info`     |
| `ILogger::WARNING`     | `Level::Warning`  |
| `ILogger::ERROR`       | `Level::Error`    |
| `ILogger::EXCEPTION`   | `Level::Critical` |
| `ILogger::CRITICAL`    | `Level::Critical` |

Unknown priorities fall back to `Level::Error`.

### Throwable handling

When `Debugger::log()` is called with a `Throwable`, the throwable is
moved into the Monolog record context under the `exception` key. If you
have pushed `TracyExceptionProcessor` onto your logger, that processor
will:

1. Render the throwable to a Tracy BlueScreen HTML file in the configured
   log directory (file name `exception--Y-m-d--H-i--{hash}.html`),
   reusing an existing file if one for the same hash is already present.
2. Add `tracy_filename` (just the basename) and `tracy_created`
   (`true` if this call wrote the file, `false` if it pre-existed) to
   the record context.
3. Replace the record message with a human-readable summary of the
   throwable chain when the original message is empty.

### Nette DI

Register the bundled compiler extension in your NEON config:

```neon
extensions:
    monolog: Detain\TracyHasMono\Bridges\NetteDI\MonologExtension
```

By default this registers:

* `monolog.handler` — `Monolog\Handler\RotatingFileHandler`
  pointing at `%logDir%/nette.log`
* `monolog.tracyExceptionProcessor` — `TracyExceptionProcessor`
  pointing at `%logDir%`
* `monolog.monologLogger` — `Monolog\Logger` channel `nette`
  with the handler and processor pre-pushed
* `monolog.tracyLogger` — `TracyMonoLogger` wrapping the above and
  replacing the autowired `tracy.logger` service

After compilation the extension also injects
`\Tracy\Debugger::setLogger($this->getByType(\Tracy\ILogger::class))`
into the container's `initialize()` method, so Tracy itself begins
routing through Monolog as soon as the container boots.

To reuse an existing Monolog logger service instead of letting the
extension create one, pass it via the extension config:

```neon
monolog:
    monolog: @myMonologLogger
```

## Architecture

```
src/
├── Bridges/NetteDI/MonologExtension.php   # Nette DI compiler extension
├── Logger.php                             # Legacy Nextras\TracyMonologAdapter alias
├── Processors/TracyExceptionProcessor.php # Monolog processor → BlueScreen HTML
└── TracyMonoLogger.php                    # Tracy ILogger → Monolog adapter
```

Two namespaces are exposed:

* `Detain\TracyHasMono\` — the active namespace; use this in new code.
* `Nextras\TracyMonologAdapter\` — covers `Logger.php` only, kept as a
  drop-in replacement for the original Nextras package.

## License

[New BSD License](license.md). See the license file for details.

## Credits

Originally based on the
[Nextras Tracy-Monolog Adapter](https://github.com/nextras/tracy-monolog-adapter)
by the Nextras contributors, with portions (BlueScreen rendering,
exception file naming, exception summary formatting) adapted from
[Nette Tracy](https://github.com/nette/tracy) by David Grudl. Maintained
for the Detain project by the Detain Project contributors.
