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

class TracyMonoLogger implements ILogger
{
    /** @const Tracy priority to Monolog priority mapping */
    public const PRIORITY_MAP = [
        self::DEBUG => Monolog\Logger::DEBUG,
        self::INFO => Monolog\Logger::INFO,
        self::WARNING => Monolog\Logger::WARNING,
        self::ERROR => Monolog\Logger::ERROR,
        self::EXCEPTION => Monolog\Logger::CRITICAL,
        self::CRITICAL => Monolog\Logger::CRITICAL,
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
        $ret = self::PRIORITY_MAP[$priority];
        $this->monolog->addRecord(!is_null($ret) ? $ret : Monolog\Logger::ERROR, $message, $context);
    }
}
