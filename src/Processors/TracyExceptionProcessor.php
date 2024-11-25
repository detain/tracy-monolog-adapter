<?php

/**
 * @license    New BSD License
 * @link       https://github.com/detain/tracy-monolog-adapter
 */

namespace Detain\TracyHasMono\Processors;

use Throwable;
use Tracy\BlueScreen;
use Tracy\Helpers;

class TracyExceptionProcessor
{
    /** @var string */
    private $directory;

    /** @var BlueScreen */
    private $blueScreen;


    public function __construct(string $logDirectory, BlueScreen $blueScreen)
    {
        $this->directory = $logDirectory;
        $this->blueScreen = $blueScreen;
    }


    public function __invoke(array $record): array
    {
        foreach (['exception', 'error'] as $key) {
            if (isset($record['context'][$key]) && $record['context'][$key] instanceof Throwable) {
                [$justCreated, $exceptionFileName] = $this->logException($record['context'][$key]);
                $record['context']['tracy_filename'] = basename($exceptionFileName);
                $record['context']['tracy_created'] = $justCreated;
                $record['message'] = $record['message'] ?: self::formatMessage($record['context'][$key]);
                break;
            }
        }
        return $record;
    }


    /**
     * @author David Grudl
     * @see    https://github.com/nette/tracy
     */
    protected function logException(Throwable $exception): array
    {
        $file = $this->getExceptionFile($exception);
        if ($handle = @fopen($file, 'x')) { // @ file may already exist
            ob_start(); // double buffer prevents sending HTTP headers in some PHP
            ob_start(function ($buffer) use ($handle) {
                fwrite($handle, $buffer);
            }, 4096);
            $this->blueScreen->render($exception);
            ob_end_flush();
            ob_end_clean();
            fclose($handle);
            return [true, $file];
        } else {
            return [false, $file];
        }
    }


    /**
     * @author David Grudl
     * @see    https://github.com/nette/tracy
     */
    private function getExceptionFile(Throwable $exception): string
    {
        $dir = strtr($this->directory . '/', '\\/', DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR);
        $hash = substr(md5(preg_replace('~(Resource id #)\d+~', '$1', $exception)), 0, 10);
        foreach (new \DirectoryIterator($this->directory) as $file) {
            if (strpos($file, $hash)) {
                return $dir . $file;
            }
        }
        return $dir . 'exception--' . @date('Y-m-d--H-i') . "--$hash.html"; // @ timezone may not be set
    }


    /**
     * @author David Grudl
     * @see    https://github.com/nette/tracy
     */
    protected static function formatMessage(Throwable $message): string
    {
        $tmp = [];
        while ($message) {
            $tmp[] = ($message instanceof \ErrorException
                ? Helpers::errorTypeToString($message->getSeverity()) . ': ' . $message->getMessage()
                : get_class($message) . ': ' . $message->getMessage()
            ) . ' in ' . $message->getFile() . ':' . $message->getLine();
            $message = $message->getPrevious();
        }
        $message = implode("\ncaused by ", $tmp);
        return trim($message);
    }
}
