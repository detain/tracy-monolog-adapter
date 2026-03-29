---
name: nette-di-extension
description: Extends or modifies Nette DI wiring in `src/Bridges/NetteDI/MonologExtension.php`. Use when user says 'add DI service', 'configure extension', 'register in container', 'add handler', or needs to add `addDefinition`/`addSetup` calls. Covers `loadConfiguration()` and `afterCompile()` patterns. Do NOT use for non-DI changes like Tracy priority mapping or processor logic.
---
# Nette DI Extension

## Critical

- All service definitions go in `loadConfiguration()` — never in `afterCompile()`
- `afterCompile()` is **only** for PHP body injection into the generated container's `initialize()` method
- Always use `$this->prefix('serviceName')` for definition names — never hardcode `monolog.serviceName`
- Check `isset($config['monolog'])` before building the default handler stack — user-supplied loggers bypass it
- Set `->setAutowired(false)` on handlers and processors; only `tracyLogger` should be autowired
- Never register `tracy.logger` as a new definition — only disable its autowiring via `setAutowired(false)`

## Instructions

1. **Open the extension file.**  
   File: `src/Bridges/NetteDI/MonologExtension.php`  
   Namespace: `Detain\TracyHasMono\Bridges\NetteDI`  
   Class extends: `Nette\DI\CompilerExtension`

2. **Add the import** for any new handler or processor class at the top, alongside existing `use` statements:
   ```php
   use Monolog\Handler\RotatingFileHandler;   // existing
   use Monolog\Logger as MonologLogger;        // existing
   use Nette\DI\CompilerExtension;            // existing
   use Nette\DI\Helpers;                      // existing
   use Nette\PhpGenerator\ClassType;          // existing
   use Detain\TracyHasMono\TracyMonoLogger;   // existing
   use Detain\TracyHasMono\Processors\TracyExceptionProcessor; // existing
   use Tracy\Debugger;                        // existing
   use Your\New\ClassName;                    // add here
   ```
   Verify the class exists in `src/` or a Composer dependency before proceeding.

3. **Register a new service definition** inside `loadConfiguration()`, inside the `else` block (before `$monologLogger` is built), using `$this->prefix()`:
   ```php
   $builder->addDefinition($this->prefix('myHandler'))
       ->setType(YourHandlerClass::class)
       ->setArguments([$logDir . '/custom.log'])
       ->setAutowired(false);
   ```

4. **Wire the new service into the Monolog logger** with `addSetup()` on the `$monologLogger` definition:
   ```php
   $monologLogger = $builder->addDefinition($this->prefix('monologLogger'))
       ->setType(MonologLogger::class)
       ->setArguments(['nette'])
       ->addSetup('pushHandler', ['@' . $this->prefix('handler')])
       ->addSetup('pushHandler', ['@' . $this->prefix('myHandler')])  // new
       ->addSetup('pushProcessor', ['@' . $this->prefix('tracyExceptionProcessor')])
       ->setAutowired(false);
   ```
   Verify `$this->prefix('myHandler')` matches the name used in step 3.

5. **For `afterCompile()` changes** (e.g. additional initialization calls), append body lines to `initialize()`:
   ```php
   public function afterCompile(ClassType $class): void
   {
       $initialize = $class->getMethod('initialize');
       $initialize->addBody('\Tracy\Debugger::setLogger($this->getByType(\Tracy\ILogger::class));');
       $initialize->addBody('// additional initialization here');
   }
   ```
   Only use `addBody()` here — never call `$builder` in `afterCompile()`.

6. **Verify** by running `composer dump-autoload` and checking that the container compiles without error in the target Nette app.

## Examples

**User says:** "Add a StreamHandler to also write to stderr in the DI extension."

Actions taken:
1. Add `use Monolog\Handler\StreamHandler;` import
2. In `loadConfiguration()` `else` block, add:
   ```php
   $builder->addDefinition($this->prefix('stderrHandler'))
       ->setType(StreamHandler::class)
       ->setArguments(['php://stderr', MonologLogger::WARNING])
       ->setAutowired(false);
   ```
3. Chain onto `$monologLogger`:
   ```php
   ->addSetup('pushHandler', ['@' . $this->prefix('stderrHandler')])
   ```

Result: container wires a `StreamHandler` for stderr at WARNING+ alongside the existing `RotatingFileHandler`.

## Common Issues

- **"Service 'monolog.X' not found"**: You used a hardcoded prefix instead of `$this->prefix('X')`. Replace with `$this->prefix('X')`.
- **"Call to undefined method ... addDefinition() ... afterCompile"**: You put `$builder->addDefinition()` inside `afterCompile()`. Move it to `loadConfiguration()`.
- **New handler ignored when `config['monolog']` is set**: The default handler block is skipped when a user supplies their own logger. If the new handler must always register, move its `addDefinition()` call outside the `else` block and reference it via `$builder->getDefinition($this->prefix('monologLogger'))->addSetup(...)`.
- **"Class ... not found" during container compile**: The `use` import is missing or the Composer package isn't installed. Run `composer require vendor/package` then re-add the `use` statement.
- **Duplicate service error**: `addDefinition()` called twice with the same `$this->prefix('name')`. Use `$builder->hasDefinition($this->prefix('name'))` to guard.