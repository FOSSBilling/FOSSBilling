<?php

declare(strict_types=1);

#[Group('Core')]
final class Box_LogTest extends PHPUnit\Framework\TestCase
{
    public function testPsr3MethodNamesMapToCorrectPriorities(): void
    {
        $writer = new Box_LogTest_CapturingWriter();

        $log = new Box_Log();
        $log->addWriter($writer);
        $log->setChannel('test');

        $log->emergency('emerg msg');
        $log->critical('crit msg');
        $log->error('err msg');
        $log->warning('warn msg');
        $log->notice('notice msg');
        $log->info('info msg');

        $this->assertCount(6, $writer->events);

        $this->assertSame(Box_Log::EMERG, $writer->events[0]['priority']);
        $this->assertSame('EMERGENCY', $writer->events[0]['priorityName']);
        $this->assertSame('emerg msg', $writer->events[0]['message']);

        $this->assertSame(Box_Log::CRIT, $writer->events[1]['priority']);
        $this->assertSame('CRITICAL', $writer->events[1]['priorityName']);
        $this->assertSame('crit msg', $writer->events[1]['message']);

        $this->assertSame(Box_Log::ERR, $writer->events[2]['priority']);
        $this->assertSame('ERROR', $writer->events[2]['priorityName']);
        $this->assertSame('err msg', $writer->events[2]['message']);

        $this->assertSame(Box_Log::WARN, $writer->events[3]['priority']);
        $this->assertSame('WARNING', $writer->events[3]['priorityName']);
        $this->assertSame('warn msg', $writer->events[3]['message']);

        $this->assertSame(Box_Log::NOTICE, $writer->events[4]['priority']);
        $this->assertSame('NOTICE', $writer->events[4]['priorityName']);

        $this->assertSame(Box_Log::INFO, $writer->events[5]['priority']);
        $this->assertSame('INFO', $writer->events[5]['priorityName']);
    }

    public function testLegacyAliasesMapToSamePrioritiesAsPsr3Names(): void
    {
        $writer = new Box_LogTest_CapturingWriter();

        $log = new Box_Log();
        $log->addWriter($writer);
        $log->setChannel('test');

        $log->emerg('a');
        $log->crit('b');
        $log->err('c');
        $log->warn('d');

        $this->assertCount(4, $writer->events);
        $this->assertSame(Box_Log::EMERG, $writer->events[0]['priority']);
        $this->assertSame('EMERGENCY', $writer->events[0]['priorityName']);
        $this->assertSame(Box_Log::CRIT, $writer->events[1]['priority']);
        $this->assertSame('CRITICAL', $writer->events[1]['priorityName']);
        $this->assertSame(Box_Log::ERR, $writer->events[2]['priority']);
        $this->assertSame('ERROR', $writer->events[2]['priorityName']);
        $this->assertSame(Box_Log::WARN, $writer->events[3]['priority']);
        $this->assertSame('WARNING', $writer->events[3]['priorityName']);
    }

    public function testLegacyAndPsr3ProduceIdenticalEvents(): void
    {
        $writerA = new Box_LogTest_CapturingWriter();
        $writerB = new Box_LogTest_CapturingWriter();

        $logA = new Box_Log();
        $logA->addWriter($writerA);
        $logA->setChannel('test');

        $logB = new Box_Log();
        $logB->addWriter($writerB);
        $logB->setChannel('test');

        $logA->emergency('payload');
        $logB->emerg('payload');

        $this->assertCount(1, $writerA->events);
        $this->assertCount(1, $writerB->events);
        $this->assertSame($writerA->events[0]['priority'], $writerB->events[0]['priority']);
        $this->assertSame($writerA->events[0]['priorityName'], $writerB->events[0]['priorityName']);
        $this->assertSame($writerA->events[0]['message'], $writerB->events[0]['message']);
    }

    public function testUnknownLogMethodThrows(): void
    {
        $log = new Box_Log();

        $this->expectException(FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Bad log priority');

        $log->notARealMethod('msg');
    }

    public function testEmptyLogMessageThrows(): void
    {
        $log = new Box_Log();

        $this->expectException(FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Missing log message');

        $log->info();
    }

    public function testPlaceholderFormatSubstitutesArguments(): void
    {
        $writer = new Box_LogTest_CapturingWriter();

        $log = new Box_Log();
        $log->addWriter($writer);
        $log->setChannel('test');

        $log->info('Hello %s, you are %d', 'Alice', 30);

        $this->assertCount(1, $writer->events);
        $this->assertSame('Hello Alice, you are 30', $writer->events[0]['message']);
    }

    public function testMaskedKeysAreReplacedInExtras(): void
    {
        $writer = new Box_LogTest_CapturingWriter();

        $log = new Box_Log();
        $log->addWriter($writer);
        $log->setChannel('test');

        $log->info('Hello %s', 'alice', ['password' => 'secret123', 'token' => 'abc']);

        $this->assertCount(1, $writer->events);
        $event = $writer->events[0];
        $this->assertArrayHasKey('info', $event);
        $this->assertSame('alice', $event['info'][0]);
        $this->assertSame('********', $event['info'][1]['password']);
        $this->assertSame('********', $event['info'][1]['token']);
    }

    public function testMaskedKeyWithArrayValueIsMasked(): void
    {
        $writer = new Box_LogTest_CapturingWriter();

        $log = new Box_Log();
        $log->addWriter($writer);
        $log->setChannel('test');

        $log->info('Hello %s', 'alice', ['password' => ['old' => 'a', 'new' => 'b']]);

        $this->assertCount(1, $writer->events);
        $this->assertSame('********', $writer->events[0]['info'][1]['password']);
    }

    public function testFailingWriterDoesNotPropagateException(): void
    {
        $log = new Box_Log();
        $log->addWriter(new Box_LogTest_FailingWriter());
        $log->setChannel('test');

        $log->info('should not throw');

        $this->expectNotToPerformAssertions();
    }

    public function testLogWithUnknownPriorityThrows(): void
    {
        $log = new Box_Log();

        $this->expectException(FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Bad log priority');

        $log->log('msg', 999);
    }

    public function testLogWithNoWritersIsNoOp(): void
    {
        $log = new Box_Log();

        $log->log('msg', Box_Log::INFO);

        $this->expectNotToPerformAssertions();
    }

    public function testPriorityConstantsMatchDocblockNames(): void
    {
        $this->assertSame(0, Box_Log::EMERG);
        $this->assertSame(1, Box_Log::ALERT);
        $this->assertSame(2, Box_Log::CRIT);
        $this->assertSame(3, Box_Log::ERR);
        $this->assertSame(4, Box_Log::WARN);
        $this->assertSame(5, Box_Log::NOTICE);
        $this->assertSame(6, Box_Log::INFO);
        $this->assertSame(7, Box_Log::DEBUG);
    }
}

class Box_LogTest_CapturingWriter
{
    /** @var array<int, array<string, mixed>> */
    public array $events = [];

    public function write(array $event, string $channel = 'application'): void
    {
        $this->events[] = $event;
    }
}

class Box_LogTest_FailingWriter
{
    public function write(array $event, string $channel = 'application'): void
    {
        throw new RuntimeException('writer is down');
    }
}
