<?php

declare(strict_types=1);

namespace Detain\TracyHasMono\Tests;

use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger as MonologLogger;
use Nextras\TracyMonologAdapter\Logger as LegacyLogger;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tracy\ILogger;

/**
 * @covers \Nextras\TracyMonologAdapter\Logger
 */
class LegacyLoggerTest extends TestCase
{
    private TestHandler $handler;
    private LegacyLogger $logger;

    protected function setUp(): void
    {
        $this->handler = new TestHandler();
        $monolog = new MonologLogger('legacy');
        $monolog->pushHandler($this->handler);
        $this->logger = new LegacyLogger($monolog);
    }

    public function testImplementsTracyILogger(): void
    {
        self::assertInstanceOf(ILogger::class, $this->logger);
    }

    public function testStringMessageGetsLevelInfoByDefault(): void
    {
        $this->logger->log('plain');
        $records = $this->handler->getRecords();
        self::assertCount(1, $records);
        self::assertSame(Level::Info, $records[0]->level);
    }

    public function testThrowableIsMovedIntoContext(): void
    {
        $ex = new RuntimeException('boom');
        $this->logger->log($ex, ILogger::EXCEPTION);

        $record = $this->handler->getRecords()[0];
        self::assertSame(Level::Critical, $record->level);
        self::assertArrayHasKey('exception', $record->context);
        self::assertSame($ex, $record->context['exception']);
    }
}
