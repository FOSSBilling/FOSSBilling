<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

test('channel changes are scoped to the returned logger', function (): void {
    $writer = new class {
        public array $writes = [];

        public function write(array $event, string $channel = 'application'): void
        {
            $this->writes[] = ['event' => $event, 'channel' => $channel];
        }
    };

    $logger = new FOSSBilling\Logger();
    $logger->addWriter($writer);

    $logger->withChannel('security')->info('Security event');
    $logger->info('Application event');

    expect(array_column($writer->writes, 'channel'))
        ->toBe(['security', 'application']);
});

test('logger implements PSR-3 and interpolates context', function (): void {
    $writer = new class {
        public array $writes = [];

        public function write(array $event, string $channel = 'application'): void
        {
            $this->writes[] = $event;
        }
    };

    $logger = new FOSSBilling\Logger();
    $logger->addWriter($writer);

    expect($logger)->toBeInstanceOf(Psr\Log\LoggerInterface::class);

    $logger->info('Invoice {id} was paid by {name}', ['id' => 12, 'name' => 'Ada']);

    expect($writer->writes[0]['message'])->toBe('Invoice 12 was paid by Ada')
        ->and($writer->writes[0]['info'])->toBe(['id' => 12, 'name' => 'Ada']);
});

test('logger masks sensitive context and rejects unknown levels', function (): void {
    $writer = new class {
        public array $writes = [];

        public function write(array $event, string $channel = 'application'): void
        {
            $this->writes[] = $event;
        }
    };

    $logger = new FOSSBilling\Logger();
    $logger->addWriter($writer);

    expect(fn () => $logger->log('verbose', 'Unknown level'))
        ->toThrow(Psr\Log\InvalidArgumentException::class);

    $logger->warning('Credentials were supplied', [
        'password' => 'secret',
        'nested' => ['token' => 'nested-secret'],
    ]);

    expect($writer->writes[0]['info'])
        ->toBe(['password' => '********', 'nested' => ['token' => '********']]);
});

test('context is scoped to the returned logger', function (): void {
    $writer = new class {
        public array $writes = [];

        public function write(array $event, string $channel = 'application'): void
        {
            $this->writes[] = $event;
        }
    };

    $logger = new FOSSBilling\Logger();
    $logger->addWriter($writer);

    $logger->withContext(['client_order_id' => 42, 'status' => 'active'])
        ->info('Order event');
    $logger->info('Application event');

    expect($writer->writes[0])
        ->toHaveKey('client_order_id', 42)
        ->toHaveKey('status', 'active');
    expect($writer->writes[1])
        ->not->toHaveKey('client_order_id')
        ->not->toHaveKey('status');
});

test('database writer keeps excluded channels out of activity history', function (): void {
    $service = new class {
        public array $events = [];

        public function logEvent(array $event): void
        {
            $this->events[] = $event;
        }
    };

    $writer = new FOSSBilling\Logging\DatabaseWriter($service);
    $event = ['message' => 'Example'];

    $writer->write($event, 'security');
    $writer->write($event, 'application');

    expect($service->events)->toBe([$event]);
});
