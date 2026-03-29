---
name: tracy-ilogger-impl
description: Creates or extends a Tracy\ILogger implementation that bridges to Monolog. Use when user says 'new logger', 'implement ILogger', 'bridge Tracy', 'fork TracyMonoLogger', or wants custom Tracy-to-Monolog priority routing. Covers PRIORITY_MAP, log() signature, and Helpers::getSource() context injection. Do NOT use for Monolog processor tasks (TracyExceptionProcessor) or Nette DI wiring (MonologExtension).
---
# Tracy ILogger Implementation

## Critical

- **Always** implement `Tracy\ILogger` — never extend `Tracy\Logger` (no such base class in this project).
- **Never** omit `PRIORITY_MAP` — missing keys cause an undefined-offset error on unknown Tracy priorities; fall back with `?? Monolog\Logger::ERROR`.
- `$message` may be a `Throwable` — check before calling `addRecord()` or you will pass an object as the message string.
- Use `Helpers::getSource()` for the `'at'` context key — this is the project standard; do not substitute `debug_backtrace()` directly.
- Active namespace for new implementations: `Detain\TracyHasMono\`. `Nextras\TracyMonologAdapter\` is legacy/BC-only.

## Instructions

1. **Create the file** in `src/` (or a subdirectory under `src/`), modelling on `src/TracyMonoLogger.php`.
   ```php
   <?php
   /**
    * @license    New BSD License
    * @link       https://github.com/detain/tracy-monolog-adapter
    */
   namespace Detain\TracyHasMono;
   
   use Monolog;
   use Throwable;
   use Tracy\Helpers;
   use Tracy\ILogger;
   ```
   Verify: namespace matches `src/` PSR-4 root `Detain\TracyHasMono\`.

2. **Declare the class** implementing `ILogger` with a `public const PRIORITY_MAP` covering all six Tracy levels:
   ```php
   class YourLogger implements ILogger
   {
       public const PRIORITY_MAP = [
           self::DEBUG     => Monolog\Logger::DEBUG,
           self::INFO      => Monolog\Logger::INFO,
           self::WARNING   => Monolog\Logger::WARNING,
           self::ERROR     => Monolog\Logger::ERROR,
           self::EXCEPTION => Monolog\Logger::CRITICAL,
           self::CRITICAL  => Monolog\Logger::CRITICAL,
       ];
   
       /** @var Monolog\Logger */
       protected $monolog;
   
       public function __construct(Monolog\Logger $monolog)
       {
           $this->monolog = $monolog;
       }
   }
   ```
   Verify: all six Tracy priority constants are present as keys.

3. **Implement `log()`** following the exact pattern from `src/TracyMonoLogger.php`:
   ```php
   public function log($message, $priority = self::INFO): void
   {
       $context = [
           'at' => Helpers::getSource(),
       ];
   
       if ($message instanceof Throwable) {
           $context['exception'] = $message;
           $message = '';
       }
   
       $this->monolog->addRecord(
           self::PRIORITY_MAP[$priority] ?? Monolog\Logger::ERROR,
           $message,
           $context
       );
   }
   ```
   Verify: `?? Monolog\Logger::ERROR` fallback is present; `$message` is reset to `''` for Throwable.

4. **Register with Tracy** (outside the class, in bootstrap or DI setup):
   ```php
   \Tracy\Debugger::setLogger(new YourLogger($monologInstance));
   ```
   Verify: `Tracy\Debugger::setLogger()` is called after `Debugger::enable()`.

## Examples

**User says:** "Fork TracyMonoLogger but map EXCEPTION to WARNING instead of CRITICAL."

**Actions taken:**
1. Copy `src/TracyMonoLogger.php` → `src/LenientLogger.php`, rename class to `LenientLogger`.
2. Change `self::EXCEPTION => Monolog\Logger::CRITICAL` to `self::EXCEPTION => Monolog\Logger::WARNING`.
3. Keep all other lines identical (imports, constructor, `log()` body, `?? Monolog\Logger::ERROR` fallback).

**Result:**
```php
namespace Detain\TracyHasMono;
use Monolog; use Throwable; use Tracy\Helpers; use Tracy\ILogger;
class LenientLogger implements ILogger {
    public const PRIORITY_MAP = [
        self::DEBUG => Monolog\Logger::DEBUG,
        self::INFO  => Monolog\Logger::INFO,
        self::WARNING   => Monolog\Logger::WARNING,
        self::ERROR     => Monolog\Logger::ERROR,
        self::EXCEPTION => Monolog\Logger::WARNING,   // changed
        self::CRITICAL  => Monolog\Logger::CRITICAL,
    ];
    protected $monolog;
    public function __construct(Monolog\Logger $m) { $this->monolog = $m; }
    public function log($message, $priority = self::INFO): void {
        $context = ['at' => Helpers::getSource()];
        if ($message instanceof Throwable) { $context['exception'] = $message; $message = ''; }
        $this->monolog->addRecord(self::PRIORITY_MAP[$priority] ?? Monolog\Logger::ERROR, $message, $context);
    }
}
```

## Common Issues

- **`Undefined array key "critical"` (or any priority string):** A Tracy priority string was passed that is not in `PRIORITY_MAP`. Add the key, or ensure the `?? Monolog\Logger::ERROR` null-coalescing fallback is present on `addRecord()`.
- **`Call to undefined method Tracy\Helpers::getSource()`:** Tracy version is below 2.4. Upgrade Tracy or substitute `Tracy\Debugger::$source` as a fallback.
- **Monolog `addRecord()` deprecation warning (Monolog 3.x):** `addRecord()` is removed in Monolog 3. Use `$this->monolog->log(Level::fromValue(...), $message, $context)` and update `PRIORITY_MAP` values to `Monolog\Level` enum cases.
- **Logger not used by Tracy after instantiation:** `\Tracy\Debugger::setLogger()` was not called, or was called before `Debugger::enable()`. Always call `setLogger()` after `enable()`.
- **`$message` passed as Throwable object to Monolog:** The `instanceof Throwable` guard was removed or skipped. Restore it; Monolog expects a string message.
