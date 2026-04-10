<?php

/**
 * @license    New BSD License
 * @link       https://github.com/detain/tracy-monolog-adapter
 */

namespace Detain\TracyHasMono;

use Monolog;
use Monolog\Level;
use Throwable;
use Tracy\Helpers;
use Tracy\ILogger;

class TracyMonoLogger implements ILogger
{
    /** @const Tracy priority to Monolog priority mapping */
    public const PRIORITY_MAP = [
        self::DEBUG => Level::Debug,
        self::INFO => Level::Info,
        self::WARNING => Level::Warning,
        self::ERROR => Level::Error,
        self::EXCEPTION => Level::Critical,
        self::CRITICAL => Level::Critical,
    ];

    /** @var Monolog\Logger */
    protected $monolog;


    public function __construct(Monolog\Logger $monolog)
    {
        $this->monolog = $monolog;
    }


    public function log($message, $priority = self::INFO)
    {
        $context = [
            'at' => Helpers::getSource(),
        ];

        if ($message instanceof Throwable) {
            $context['exception'] = $message;
            $message = '';
        }
        $level = self::PRIORITY_MAP[$priority] ?? Level::Error;
        $this->monolog->log($level, $message, $context);
    }
}
