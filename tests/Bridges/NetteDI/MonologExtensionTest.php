<?php

declare(strict_types=1);

namespace Detain\TracyHasMono\Tests\Bridges\NetteDI;

use Detain\TracyHasMono\Bridges\NetteDI\MonologExtension;
use Detain\TracyHasMono\Processors\TracyExceptionProcessor;
use Detain\TracyHasMono\TracyMonoLogger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger as MonologLogger;
use Nette\DI\ContainerLoader;
use PHPUnit\Framework\TestCase;
use Tracy\BlueScreen;
use Tracy\ILogger;

/**
 * @covers \Detain\TracyHasMono\Bridges\NetteDI\MonologExtension
 */
class MonologExtensionTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/tracy-monolog-di-' . uniqid('', true);
        mkdir($this->tempDir . '/cache', 0o777, true);
        mkdir($this->tempDir . '/log', 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->rmdirRecursive($this->tempDir);
    }

    public function testRegistersDefaultServices(): void
    {
        $loader = new ContainerLoader($this->tempDir . '/cache', autoRebuild: true);
        $class = $loader->load(function ($compiler): void {
            $compiler->addExtension('monolog', new MonologExtension());
            $compiler->addConfig([
                'parameters' => ['logDir' => $this->tempDir . '/log'],
                'services' => [
                    'tracy.blueScreen' => BlueScreen::class,
                ],
            ]);
        }, __METHOD__ . sha1(serialize($this->tempDir)));

        /** @var \Nette\DI\Container $container */
        $container = new $class();

        $tracyLogger = $container->getByType(ILogger::class);
        self::assertInstanceOf(TracyMonoLogger::class, $tracyLogger);

        $monolog = $container->getService('monolog.monologLogger');
        self::assertInstanceOf(MonologLogger::class, $monolog);

        $handler = $container->getService('monolog.handler');
        self::assertInstanceOf(RotatingFileHandler::class, $handler);

        $processor = $container->getService('monolog.tracyExceptionProcessor');
        self::assertInstanceOf(TracyExceptionProcessor::class, $processor);
    }

    private function rmdirRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            if (is_dir($path)) {
                $this->rmdirRecursive($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
