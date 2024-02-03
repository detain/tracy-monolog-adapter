<?php

/**
 * @license    New BSD License
 * @link       https://github.com/detain/tracy-monolog-adapter
 */

namespace Detain\TracyHasMono\Bridges\NetteDI;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger as MonologLogger;
use Nette\DI\CompilerExtension;
use Nette\DI\Helpers;
use Nette\PhpGenerator\ClassType;
use Detain\TracyHasMono\TracyMonoLogger;
use Detain\TracyHasMono\Processors\TracyExceptionProcessor;
use Tracy\Debugger;

class MonologExtension extends CompilerExtension
{
    public function loadConfiguration(): void
    {
        $builder = $this->getContainerBuilder();
        $logDir = isset($builder->parameters['logDir']) ? Helpers::expand('%logDir%', $builder->parameters) : Debugger::$logDirectory;

        $config = $this->getConfig();

        $config = $this->getConfig();

        if (isset($config['monolog'])) {
            $monologLogger = $config['monolog'];
        } else {
            $builder->addDefinition($this->prefix('handler'))
                ->setClass(RotatingFileHandler::class)
                ->setArguments([$logDir . '/nette.log'])
                ->setAutowired(false);

            $builder->addDefinition($this->prefix('tracyExceptionProcessor'))
                ->setClass(TracyExceptionProcessor::class)
                ->setArguments([$logDir, '@Tracy\BlueScreen'])
                ->setAutowired(false);

            $monologLogger = $builder->addDefinition($this->prefix('monologLogger'))
                ->setClass(MonologLogger::class)
                ->setArguments(['nette'])
                ->addSetup('pushHandler', ['@' . $this->prefix('handler')])
                ->addSetup('pushProcessor', ['@' . $this->prefix('tracyExceptionProcessor')])
                ->setAutowired(false);
        }

        $builder->addDefinition($this->prefix('tracyLogger'))
            ->setClass(TracyMonoLogger::class)
            ->setArguments([$monologLogger]);

        if ($builder->hasDefinition('tracy.logger')) {
            $builder->getDefinition('tracy.logger')->setAutowired(false);
        }
    }


    public function afterCompile(ClassType $class): void
    {
        $initialize = $class->getMethod('initialize');
        $initialize->addBody('\Tracy\Debugger::setLogger($this->getByType(\Tracy\ILogger::class));');
    }
}
