<?php

declare(strict_types=1);

namespace Detain\TracyHasMono\Tests;

use Detain\TracyHasMono\TracyMonoLogger;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger as MonologLogger;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tracy\ILogger;

/**
 * @covers \Detain\TracyHasMono\TracyMonoLogger
 */
class TracyMonoLoggerTest extends TestCase
{
    private TestHandler $handler;
    private MonologLogger $monolog;
    private TracyMonoLogger $logger;

    protected function setUp(): void
    {
        $this->handler = new TestHandler();
        $this->monolog = new MonologLogger('test');
        $this->monolog->pushHandler($this->handler);
        $this->logger = new TracyMonoLogger($this->monolog);
    }

    public function testImplementsTracyILogger(): void
    {
        self::assertInstanceOf(ILogger::class, $this->logger);
    }

    /**
     * @dataProvider priorityProvider
     */
    public function testPriorityMapping(string $priority, Level $expected): void
    {
        $this->logger->log('msg', $priority);
        $records = $this->handler->getRecords();
        self::assertCount(1, $records);
        self::assertSame($expected, $records[0]->level);
    }

    /**
     * @return array<string, array{0: string, 1: Level}>
     */
    public static function priorityProvider(): array
    {
        return [
            'debug'     => [ILogger::DEBUG, Level::Debug],
            'info'      => [ILogger::INFO, Level::Info],
            'warning'   => [ILogger::WARNING, Level::Warning],
            'error'     => [ILogger::ERROR, Level::Error],
            'exception' => [ILogger::EXCEPTION, Level::Critical],
            'critical'  => [ILogger::CRITICAL, Level::Critical],
        ];
    }

    public function testUnknownPriorityFallsBackToError(): void
    {
        $this->logger->log('msg', 'totally-made-up-level');
        $records = $this->handler->getRecords();
        self::assertSame(Level::Error, $records[0]->level);
    }

    public function testStringMessageIsForwarded(): void
    {
        $this->logger->log('hello world', ILogger::INFO);
        self::assertTrue($this->handler->hasInfoThatContains('hello world'));
    }

    public function testRecordIncludesSourceLocation(): void
    {
        $this->logger->log('hello', ILogger::INFO);
        $context = $this->handler->getRecords()[0]->context;
        self::assertArrayHasKey('at', $context);
    }

    public function testThrowableIsMovedIntoContext(): void
    {
        $exception = new RuntimeException('boom');
        $this->logger->log($exception, ILogger::EXCEPTION);

        $record = $this->handler->getRecords()[0];
        self::assertSame(Level::Critical, $record->level);
        self::assertSame('', $record->message);
        self::assertArrayHasKey('exception', $record->context);
        self::assertSame($exception, $record->context['exception']);
    }

    public function testNonStringScalarIsCoercedSafely(): void
    {
        $this->logger->log(['a' => 1], ILogger::WARNING);
        $records = $this->handler->getRecords();
        self::assertCount(1, $records);
        self::assertSame('{"a":1}', $records[0]->message);
    }

    public function testStringableObjectIsForwarded(): void
    {
        $stringable = new class () implements \Stringable {
            public function __toString(): string
            {
                return 'stringable-message';
            }
        };
        $this->logger->log($stringable, ILogger::INFO);
        self::assertTrue($this->handler->hasInfoThatContains('stringable-message'));
    }

    public function testPriorityMapCoversAllTracyConstants(): void
    {
        $expectedKeys = [
            ILogger::DEBUG,
            ILogger::INFO,
            ILogger::WARNING,
            ILogger::ERROR,
            ILogger::EXCEPTION,
            ILogger::CRITICAL,
        ];
        foreach ($expectedKeys as $key) {
            self::assertArrayHasKey($key, TracyMonoLogger::PRIORITY_MAP);
            self::assertInstanceOf(Level::class, TracyMonoLogger::PRIORITY_MAP[$key]);
        }
    }
}
