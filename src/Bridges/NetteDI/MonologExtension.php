<?php

/**
 * This file is part of the detain/tracy-monolog-adapter package.
 *
 * @license    New BSD License
 * @link       https://github.com/detain/tracy-monolog-adapter
 */

declare(strict_types=1);

namespace Detain\TracyHasMono\Bridges\NetteDI;

use Detain\TracyHasMono\Processors\TracyExceptionProcessor;
use Detain\TracyHasMono\TracyMonoLogger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger as MonologLogger;
use Nette\DI\CompilerExtension;
use Nette\DI\Helpers;
use Nette\PhpGenerator\ClassType;
use Tracy\Debugger;

/**
 * Nette DI compiler extension that wires the Tracy → Monolog adapter
 * into a container.
 *
 * Out of the box the extension registers:
 *
 *  - `<prefix>.handler` — a {@see RotatingFileHandler} writing to
 *    `%logDir%/nette.log`.
 *  - `<prefix>.tracyExceptionProcessor` — a {@see TracyExceptionProcessor}
 *    pointed at `%logDir%`.
 *  - `<prefix>.monologLogger` — a {@see MonologLogger} channel named
 *    `nette` with the handler and processor pre-pushed.
 *  - `<prefix>.tracyLogger` — a {@see TracyMonoLogger} that wraps the
 *    Monolog logger above and replaces the autowired
 *    `tracy.logger` service.
 *
 * Pass `monolog: @yourLoggerService` in the extension config to skip the
 * default handler/processor/logger registration and reuse an existing
 * Monolog logger service.
 *
 * After container compilation the extension hooks into the generated
 * container's `initialize()` method to call
 * {@see Debugger::setLogger()} with whatever service implements
 * {@see \Tracy\ILogger}, ensuring Tracy itself routes through Monolog
 * for the rest of the request lifecycle.
 *
 * Example NEON registration:
 *
 * ```neon
 * extensions:
 *     monolog: Detain\TracyHasMono\Bridges\NetteDI\MonologExtension
 * ```
 */
class MonologExtension extends CompilerExtension
{
    /**
     * Register all services with the DI container builder.
     */
    public function loadConfiguration(): void
    {
        $builder = $this->getContainerBuilder();
        $logDir = isset($builder->parameters['logDir'])
            ? Helpers::expand('%logDir%', $builder->parameters)
            : Debugger::$logDirectory;

        $config = $this->getConfig();

        if (is_array($config) && isset($config['monolog'])) {
            $monologLogger = $config['monolog'];
        } elseif (is_object($config) && isset($config->monolog)) {
            $monologLogger = $config->monolog;
        } else {
            $builder->addDefinition($this->prefix('handler'))
                ->setType(RotatingFileHandler::class)
                ->setArguments([$logDir . '/nette.log'])
                ->setAutowired(false);

            $builder->addDefinition($this->prefix('tracyExceptionProcessor'))
                ->setType(TracyExceptionProcessor::class)
                ->setArguments([$logDir, '@Tracy\BlueScreen'])
                ->setAutowired(false);

            $monologLogger = $builder->addDefinition($this->prefix('monologLogger'))
                ->setType(MonologLogger::class)
                ->setArguments(['nette'])
                ->addSetup('pushHandler', ['@' . $this->prefix('handler')])
                ->addSetup('pushProcessor', ['@' . $this->prefix('tracyExceptionProcessor')])
                ->setAutowired(false);
        }

        $builder->addDefinition($this->prefix('tracyLogger'))
            ->setType(TracyMonoLogger::class)
            ->setArguments([$monologLogger]);

        if ($builder->hasDefinition('tracy.logger')) {
            $builder->getDefinition('tracy.logger')->setAutowired(false);
        }
    }

    /**
     * After container compilation, ensure Tracy uses our logger by
     * wiring `\Tracy\Debugger::setLogger()` into the container's
     * `initialize()` method.
     *
     * @param ClassType $class The generated container class.
     */
    public function afterCompile(ClassType $class): void
    {
        $initialize = $class->getMethod('initialize');
        $initialize->addBody('\Tracy\Debugger::setLogger($this->getByType(\Tracy\ILogger::class));');
    }
}
