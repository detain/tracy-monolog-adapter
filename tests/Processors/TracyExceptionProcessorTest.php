<?php

declare(strict_types=1);

namespace Detain\TracyHasMono\Tests\Processors;

use Detain\TracyHasMono\Processors\TracyExceptionProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tracy\BlueScreen;

/**
 * @covers \Detain\TracyHasMono\Processors\TracyExceptionProcessor
 */
class TracyExceptionProcessorTest extends TestCase
{
    private string $logDir;

    protected function setUp(): void
    {
        $this->logDir = sys_get_temp_dir() . '/tracy-monolog-test-' . uniqid('', true);
        mkdir($this->logDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->logDir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->logDir);
    }

    public function testRecordWithoutThrowableIsUnchanged(): void
    {
        $processor = new TracyExceptionProcessor($this->logDir, new BlueScreen());
        $record = $this->makeRecord('plain message', []);
        $out = $processor($record);

        self::assertSame('plain message', $out->message);
        self::assertArrayNotHasKey('tracy_filename', $out->context);
        self::assertArrayNotHasKey('tracy_created', $out->context);
    }

    public function testRecordWithThrowableInExceptionKeyGetsRendered(): void
    {
        $processor = new TracyExceptionProcessor($this->logDir, new BlueScreen());
        $record = $this->makeRecord('', ['exception' => new RuntimeException('boom-1')]);

        $out = $processor($record);

        self::assertArrayHasKey('tracy_filename', $out->context);
        self::assertArrayHasKey('tracy_created', $out->context);
        self::assertTrue($out->context['tracy_created']);
        self::assertStringStartsWith('exception--', $out->context['tracy_filename']);
        self::assertStringEndsWith('.html', $out->context['tracy_filename']);
        self::assertFileExists($this->logDir . '/' . $out->context['tracy_filename']);
        self::assertNotSame('', $out->message, 'message should be replaced with throwable summary');
        self::assertStringContainsString('boom-1', $out->message);
    }

    public function testRecordWithThrowableInErrorKeyAlsoMatches(): void
    {
        $processor = new TracyExceptionProcessor($this->logDir, new BlueScreen());
        $record = $this->makeRecord('', ['error' => new RuntimeException('boom-error')]);

        $out = $processor($record);

        self::assertArrayHasKey('tracy_filename', $out->context);
        self::assertTrue($out->context['tracy_created']);
    }

    public function testSecondInvocationForSameThrowableReusesFile(): void
    {
        $processor = new TracyExceptionProcessor($this->logDir, new BlueScreen());
        // same instance both times -> identical string repr -> identical hash
        $exception = new RuntimeException('stable-message');

        $first = $processor(
            $this->makeRecord('', ['exception' => $exception])
        );
        self::assertTrue($first->context['tracy_created']);

        $second = $processor(
            $this->makeRecord('', ['exception' => $exception])
        );
        self::assertFalse($second->context['tracy_created'], 'duplicate hash should reuse file');
        self::assertSame($first->context['tracy_filename'], $second->context['tracy_filename']);
    }

    public function testNonEmptyMessageIsPreserved(): void
    {
        $processor = new TracyExceptionProcessor($this->logDir, new BlueScreen());
        $record = $this->makeRecord('keep me', ['exception' => new RuntimeException('boom-keep')]);

        $out = $processor($record);

        self::assertSame('keep me', $out->message);
    }

    public function testMissingLogDirectoryDoesNotCrash(): void
    {
        $missing = $this->logDir . '/does-not-exist';
        $processor = new TracyExceptionProcessor($missing, new BlueScreen());
        $record = $this->makeRecord('', ['exception' => new RuntimeException('boom-missing')]);

        $out = $processor($record);

        // we still get a target filename back, even though the file write fails
        self::assertArrayHasKey('tracy_filename', $out->context);
        self::assertFalse($out->context['tracy_created']);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function makeRecord(string $message, array $context): LogRecord
    {
        return new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Error,
            message: $message,
            context: $context,
            extra: [],
        );
    }

}
