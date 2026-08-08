<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use FOSSBilling\PsrLogAdapter;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

function recordingBoxLog(): Box_Log
{
    return new class extends Box_Log {
        /** @var list<array{message: string, priority: int}> */
        public array $written = [];

        #[Override]
        public function log($message, $priority, array|string|null $extras = null): void
        {
            $this->written[] = ['message' => (string) $message, 'priority' => $priority];
        }
    };
}

test('is a PSR-3 logger', function (): void {
    expect(new PsrLogAdapter(recordingBoxLog()))->toBeInstanceOf(LoggerInterface::class);
});

test('maps each PSR-3 level onto the matching Box_Log priority', function (string $level, int $priority): void {
    $boxLog = recordingBoxLog();

    (new PsrLogAdapter($boxLog))->log($level, 'a message');

    expect($boxLog->written)->toBe([['message' => 'a message', 'priority' => $priority]]);
})->with([
    [LogLevel::EMERGENCY, Box_Log::EMERG],
    [LogLevel::ALERT, Box_Log::ALERT],
    [LogLevel::CRITICAL, Box_Log::CRIT],
    [LogLevel::ERROR, Box_Log::ERR],
    [LogLevel::WARNING, Box_Log::WARN],
    [LogLevel::NOTICE, Box_Log::NOTICE],
    [LogLevel::INFO, Box_Log::INFO],
    [LogLevel::DEBUG, Box_Log::DEBUG],
]);

test('the level shortcut methods extensions use reach the underlying log', function (): void {
    $boxLog = recordingBoxLog();
    $logger = new PsrLogAdapter($boxLog);

    // The four levels the bundled extensions actually call.
    $logger->debug('d');
    $logger->info('i');
    $logger->error('e');
    $logger->critical('c');

    expect(array_column($boxLog->written, 'priority'))
        ->toBe([Box_Log::DEBUG, Box_Log::INFO, Box_Log::ERR, Box_Log::CRIT]);
});

test('an unrecognised level does not lose the message', function (): void {
    $boxLog = recordingBoxLog();

    (new PsrLogAdapter($boxLog))->log('not-a-level', 'still written');

    expect($boxLog->written)->toBe([['message' => 'still written', 'priority' => Box_Log::INFO]]);
});
