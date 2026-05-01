<?php

/**
 * This file is part of the detain/tracy-monolog-adapter package.
 *
 * @license    New BSD License
 * @link       https://github.com/detain/tracy-monolog-adapter
 */

declare(strict_types=1);

namespace Detain\TracyHasMono\Processors;

use Monolog\LogRecord;
use Throwable;
use Tracy\BlueScreen;
use Tracy\Helpers;

/**
 * Monolog processor that detects {@see Throwable} instances in a record's
 * context and renders them as Tracy BlueScreen HTML files in the
 * configured log directory.
 *
 * For each record, the context keys `exception` and `error` are inspected.
 * The first one that holds a {@see Throwable} triggers BlueScreen
 * rendering. If a file for that exception (identified by its hash) already
 * exists, no new file is written. The record is then enriched with two
 * extra context keys — `tracy_filename` (the BlueScreen file name) and
 * `tracy_created` (`true` if the file was created by this call,
 * `false` if it already existed) — and the record's message is replaced
 * with a human-readable summary when the original message is empty.
 *
 * Processors are callables that take a {@see LogRecord} and return a
 * {@see LogRecord}; this class implements that contract via
 * {@see self::__invoke()}.
 *
 * @link https://github.com/Seldaek/monolog/blob/main/doc/02-handlers-formatters-processors.md
 */
class TracyExceptionProcessor
{
    /**
     * Directory where BlueScreen HTML files are written. Should already
     * exist; the processor does not create it.
     */
    private string $directory;

    /**
     * Tracy BlueScreen renderer used to build the HTML output.
     */
    private BlueScreen $blueScreen;

    /**
     * @param string     $logDirectory Directory where BlueScreen HTML
     *                                 files will be written. Must exist
     *                                 and be writable by the PHP process.
     * @param BlueScreen $blueScreen   Tracy BlueScreen renderer used to
     *                                 produce the HTML output.
     */
    public function __construct(string $logDirectory, BlueScreen $blueScreen)
    {
        $this->directory = $logDirectory;
        $this->blueScreen = $blueScreen;
    }

    /**
     * Render any Throwable found in the record context as a BlueScreen
     * HTML file and enrich the record with metadata about the file.
     *
     * @param LogRecord $record Incoming Monolog record.
     *
     * @return LogRecord Same record, possibly with `tracy_filename` and
     *                   `tracy_created` added to the context and the
     *                   message replaced by a Throwable summary.
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        foreach (['exception', 'error'] as $key) {
            if (isset($record->context[$key]) && $record->context[$key] instanceof Throwable) {
                /** @var Throwable $throwable */
                $throwable = $record->context[$key];
                [$justCreated, $exceptionFileName] = $this->logException($throwable);
                $context = $record->context;
                $context['tracy_filename'] = basename($exceptionFileName);
                $context['tracy_created'] = $justCreated;
                $message = $record->message !== '' ? $record->message : self::formatMessage($throwable);
                $record = $record->with(message: $message, context: $context);
                break;
            }
        }
        return $record;
    }

    /**
     * Render a Throwable to its BlueScreen HTML file, creating the file
     * if it does not already exist.
     *
     * @param Throwable $exception The throwable to render.
     *
     * @return array{0: bool, 1: string} `[justCreated, absoluteFilePath]`
     *                                   — `justCreated` is `true` when
     *                                   this call wrote the file and
     *                                   `false` when it already existed.
     *
     * @author David Grudl
     * @see    https://github.com/nette/tracy
     */
    protected function logException(Throwable $exception): array
    {
        $file = $this->getExceptionFile($exception);
        $handle = @fopen($file, 'x'); // @ — file may already exist
        if ($handle !== false) {
            ob_start(); // double buffer prevents sending HTTP headers in some PHP
            ob_start(static function ($buffer) use ($handle): void {
                fwrite($handle, $buffer);
            }, 4096);
            $this->blueScreen->render($exception);
            ob_end_flush();
            ob_end_clean();
            fclose($handle);
            return [true, $file];
        }
        return [false, $file];
    }

    /**
     * Resolve the absolute path to the BlueScreen HTML file for the
     * given Throwable. Reuses an existing file if one for the same hash
     * is found in the log directory; otherwise builds a new file name of
     * the form `exception--Y-m-d--H-i--{hash}.html`.
     *
     * @param Throwable $exception The throwable to render.
     *
     * @return string Absolute file path inside the configured log directory.
     *
     * @author David Grudl
     * @see    https://github.com/nette/tracy
     */
    private function getExceptionFile(Throwable $exception): string
    {
        $dir = strtr($this->directory . '/', '\\/', DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR);
        $hash = substr(
            md5((string) preg_replace('~(Resource id #)\d+~', '$1', (string) $exception)),
            0,
            10
        );
        if (is_dir($this->directory)) {
            foreach (new \DirectoryIterator($this->directory) as $file) {
                if ($file->isDot()) {
                    continue;
                }
                if (strpos($file->getFilename(), $hash) !== false) {
                    return $dir . $file->getFilename();
                }
            }
        }
        return $dir . 'exception--' . @date('Y-m-d--H-i') . "--$hash.html"; // @ timezone may not be set
    }

    /**
     * Format a Throwable (and any chained `previous` causes) into a
     * single human-readable string suitable for use as a log message.
     *
     * @param Throwable $message The (possibly chained) throwable.
     *
     * @return string `class: message in file:line` for each link in the
     *                chain, joined by `caused by`.
     *
     * @author David Grudl
     * @see    https://github.com/nette/tracy
     */
    protected static function formatMessage(Throwable $message): string
    {
        $tmp = [];
        $current = $message;
        while ($current !== null) {
            $tmp[] = ($current instanceof \ErrorException
                ? Helpers::errorTypeToString($current->getSeverity()) . ': ' . $current->getMessage()
                : get_class($current) . ': ' . $current->getMessage()
            ) . ' in ' . $current->getFile() . ':' . $current->getLine();
            $current = $current->getPrevious();
        }
        return trim(implode("\ncaused by ", $tmp));
    }
}
