<?php

/**
 * This file is part of the detain/tracy-monolog-adapter package.
 *
 * @license    New BSD License
 * @link       https://github.com/detain/tracy-monolog-adapter
 */

declare(strict_types=1);

namespace Detain\TracyHasMono;

use Monolog\Level;
use Monolog\Logger as MonologLogger;
use Throwable;
use Tracy\Helpers;
use Tracy\ILogger;

/**
 * Tracy {@see ILogger} implementation that forwards every Tracy log call to
 * a {@see MonologLogger} instance.
 *
 * Tracy priority strings (`debug`, `info`, `warning`, `error`,
 * `exception`, `critical`) are translated to Monolog {@see Level} enum
 * values via {@see self::PRIORITY_MAP}. Throwables are moved into the
 * Monolog record context under the `exception` key so that downstream
 * processors (e.g. {@see Processors\TracyExceptionProcessor}) can
 * render them as Tracy BlueScreen HTML. The Tracy source location is
 * always recorded in the context under the `at` key.
 */
class TracyMonoLogger implements ILogger
{
    /**
     * Maps Tracy {@see ILogger} priority strings to Monolog {@see Level}
     * cases. Unknown priorities fall back to {@see Level::Error} in
     * {@see self::log()}.
     *
     * @var array<string, Level>
     */
    public const PRIORITY_MAP = [
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
     *                               Tracy log call. Configure its handlers
     *                               and processors before passing it in.
     */
    public function __construct(MonologLogger $monolog)
    {
        $this->monolog = $monolog;
    }

    /**
     * Forward a Tracy log call to the wrapped Monolog logger.
     *
     * Throwables are moved into the record context under the `exception`
     * key (the message text is then cleared so downstream processors can
     * format it). Non-string/Stringable messages are JSON-encoded so
     * Monolog accepts them.
     *
     * @param mixed  $message  The Tracy payload — typically a string,
     *                         a {@see \Stringable}, or a {@see Throwable}.
     * @param string $priority One of the Tracy {@see ILogger} priority
     *                         constants (`debug`, `info`, `warning`,
     *                         `error`, `exception`, `critical`).
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

        $level = self::PRIORITY_MAP[$priority] ?? Level::Error;
        $this->monolog->log($level, (string) $message, $context);
    }
}
