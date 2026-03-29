---
name: monolog-handler
description: Adds a new Monolog handler or processor to the tracy-monolog-adapter pipeline. Use when user says 'add handler', 'new processor', 'log to X', 'extend TracyExceptionProcessor', or wants to wire a new handler/processor into the DI container. Covers __invoke(array $record): array processor contract and wiring into MonologExtension::loadConfiguration(). Do NOT use for changing Tracy priority mappings (PRIORITY_MAP in TracyMonoLogger.php or Logger.php).
---
# monolog-handler

## Critical

- Processors MUST be callable via `__invoke(array $record): array` — return the (modified) `$record` array unchanged in the non-matching case.
- Namespace for new processors: `Detain\TracyHasMono\Processors\` → file in `src/Processors/`.
- New handlers/processors are wired in `MonologExtension::loadConfiguration()` via `$builder->addDefinition()` + `addSetup('pushHandler'|'pushProcessor', ...)` — never instantiated inline.
- `setAutowired(false)` is required on all handler and processor definitions added by this extension.

## Instructions

1. **Create the processor class** in `src/Processors/`, following `src/Processors/TracyExceptionProcessor.php` as a model.
   - Declare `namespace Detain\TracyHasMono\Processors;`
   - Add license block matching existing files (New BSD License / link to repo)
   - Implement `__invoke(array $record): array`; inject dependencies via constructor.
   - Verify: class is in `src/Processors/` and namespace matches before proceeding.

   ```php
   <?php
   /**
    * @license    New BSD License
    * @link       https://github.com/detain/tracy-monolog-adapter
    */
   namespace Detain\TracyHasMono\Processors;

   class YourProcessor
   {
       public function __construct(/* dependencies */) { /* store */ }

       public function __invoke(array $record): array
       {
           // inspect/mutate $record['context'], $record['message'], etc.
           return $record;
       }
   }
   ```

2. **Wire it in `MonologExtension::loadConfiguration()`** (`src/Bridges/NetteDI/MonologExtension.php`).
   - Add `use` import for the new class alongside existing imports.
   - Inside the `else` branch (default stack), add a `$builder->addDefinition()` call, then chain it onto `$monologLogger` via `addSetup`.
   - Use `$this->prefix('yourProcessor')` as the definition name.
   - Always pass `->setAutowired(false)`.

   ```php
   // After the tracyExceptionProcessor definition:
   $builder->addDefinition($this->prefix('yourProcessor'))
       ->setType(YourProcessor::class)
       ->setArguments([/* constructor args, use '@ServiceName' for DI refs */])
       ->setAutowired(false);

   $monologLogger = $builder->addDefinition($this->prefix('monologLogger'))
       ->setType(MonologLogger::class)
       ->setArguments(['nette'])
       ->addSetup('pushHandler', ['@' . $this->prefix('handler')])
       ->addSetup('pushProcessor', ['@' . $this->prefix('tracyExceptionProcessor')])
       ->addSetup('pushProcessor', ['@' . $this->prefix('yourProcessor')])
       ->setAutowired(false);
   ```

3. **For a new handler** (not a processor), use `addSetup('pushHandler', ...)` instead and pick the appropriate Monolog handler class from `monolog/monolog`.
   - Add the `use Monolog\Handler\YourHandler;` import.
   - Define it with `->setType(YourHandler::class)` and required constructor arguments.
   - Chain `->addSetup('pushHandler', ['@' . $this->prefix('yourHandler')])` on `$monologLogger`.

4. **Verify autoloading** — the `psr-4` map in `composer.json` is under `extra` (known issue). If autoloading fails, move `psr-4` to the `autoload` key and run `composer dump-autoload`.

## Examples

**User says:** "Add a processor that tags every log record with the current app environment."

**Actions taken:**
1. Create `src/Processors/EnvironmentProcessor.php`:
   ```php
   <?php
   /** @license New BSD License @link https://github.com/detain/tracy-monolog-adapter */
   namespace Detain\TracyHasMono\Processors;
   class EnvironmentProcessor {
       private string $env;
       public function __construct(string $env) { $this->env = $env; }
       public function __invoke(array $record): array {
           $record['context']['env'] = $this->env;
           return $record;
       }
   }
   ```
2. In `MonologExtension::loadConfiguration()` (`src/Bridges/NetteDI/MonologExtension.php`), add:
   ```php
   use Detain\TracyHasMono\Processors\EnvironmentProcessor;
   // ...
   $builder->addDefinition($this->prefix('envProcessor'))
       ->setType(EnvironmentProcessor::class)
       ->setArguments(['production'])
       ->setAutowired(false);
   // chain onto $monologLogger:
   ->addSetup('pushProcessor', ['@' . $this->prefix('envProcessor')])
   ```

**Result:** Every record forwarded through `TracyMonoLogger` gains `context['env'] = 'production'`.

## Common Issues

- **`Class not found` for new processor** — `composer.json` has `psr-4` under `extra` instead of `autoload`. Move it and run `composer dump-autoload`.
- **Processor not called** — Verify `addSetup('pushProcessor', ...)` was added to `$monologLogger` definition, not to a separate logger instance. Check you are inside the `else` branch.
- **DI service reference not resolved** — DI refs in `setArguments` must be prefixed with `@` (e.g. `'@Tracy\BlueScreen'`). Plain strings are treated as literals.
- **`pushProcessor` order unexpected** — Monolog processes in LIFO order; last `pushProcessor` call runs first. Add processors in reverse-priority order if ordering matters.
- **`setAutowired(false)` missing** — Nette DI may throw ambiguous service type errors. All handler/processor definitions added by this extension must have `->setAutowired(false)`.
