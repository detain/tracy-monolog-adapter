<?php

/**
 * This file is part of the detain/tracy-monolog-adapter package.
 *
 * @license    New BSD License
 * @link       https://github.com/nextras/tracy-monolog-adapter
 */

declare(strict_types=1);

namespace Nextras\TracyMonologAdapter;

use Monolog\Level;
use Monolog\Logger as MonologLogger;
use Throwable;
use Tracy\Helpers;
use Tracy\ILogger;

/**
 * Legacy Tracy-to-Monolog adapter retained for backwards compatibility
 * with downstream code still importing the `Nextras\TracyMonologAdapter`
 * namespace.
 *
 * New code should prefer {@see \Detain\TracyHasMono\TracyMonoLogger} —
 * the two classes are functionally equivalent.
 *
 * @internal Kept for BC; functionally equivalent to TracyMonoLogger.
 */
class Logger implements ILogger
{
    /**
     * Maps Tracy {@see ILogger} priority strings to Monolog {@see Level}
     * cases. Unknown priorities fall back to {@see Level::Error} in
     * {@see self::log()}.
     *
     * @var array<string, Level>
     */
    protected const PRIORITY_MAP = [
        self::DEBUG => Level::Debug,
        self::INFO => Level::Info,
        self::WARNING => Level::Warning,
        self::ERROR => Level::Error,
        self::EXCEPTION => Level::Critical,
        self::CRITICAL => Level::Critical,
    ];

    /**
     * The underlying Monolog logger that receives the translated records.
     */
    protected MonologLogger $monolog;

    /**
     * @param MonologLogger $monolog Monolog logger that will receive every
     *                               Tracy log call.
     */
    public function __construct(MonologLogger $monolog)
    {
        $this->monolog = $monolog;
    }

    /**
     * Forward a Tracy log call to the wrapped Monolog logger.
     *
     * @param mixed  $message  The Tracy payload — typically a string,
     *                         a {@see \Stringable}, or a {@see Throwable}.
     * @param string $priority One of the Tracy {@see ILogger} priority
     *                         constants.
     */
    public function log($message, $priority = self::INFO): void
    {
        $context = [
            'at' => Helpers::getSource(),
        ];

        if ($message instanceof Throwable) {
            $context['exception'] = $message;
            $message = '';
        } elseif (!is_string($message) && !$message instanceof \Stringable) {
            $message = (string) json_encode(
                $message,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
        }

        $this->monolog->log(
            self::PRIORITY_MAP[$priority] ?? Level::Error,
            (string) $message,
            $context
        );
    }
}
