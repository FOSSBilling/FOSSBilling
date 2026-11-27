<?php

declare(strict_types=1);

use Box\Mod\Cron\Event\BeforeAdminCronRunEvent;
use Box\Mod\Massmailer\Entity\MassmailerMessage;
use Box\Mod\Massmailer\Repository\MassmailerMessageRepository;
use Box\Mod\Massmailer\Service;
use FOSSBilling\Core\Exception\InformationException;

function massmailerSendMessage(int $id, string $status = MassmailerMessage::STATUS_DRAFT): MassmailerMessage
{
    $message = new MassmailerMessage(id: $id);
    $message->setSubject('Notice')->setContent('Hello')->setStatus($status);

    return $message;
}

/**
 * A service whose messages all go to $clientIds (newest first, as
 * getMessageReceivers() returns them). sendMessage() records each client in
 * $sent, or throws for the clients in $failFor.
 *
 * @param list<int>               $clientIds
 * @param list<MassmailerMessage> $messages  what the repository finds by status
 * @param list<int>               $failFor
 */
function massmailerSendService(array $clientIds, ArrayObject $sent, array $messages = [], array $failFor = [], ?ArrayObject $log = null): Service
{
    $di = new Pimple\Container();
    $entityManager = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $entityManager->shouldReceive('flush');
    $di['em'] = $entityManager;
    $logger = Mockery::mock(Monolog\Logger::class)->shouldIgnoreMissing();
    $logger->shouldReceive('error')->andReturnUsing(function (string $message, array $context = []) use ($log): void {
        $log?->append(strtr($message, array_combine(array_map(fn (string $placeholder): string => '{' . $placeholder . '}', array_keys($context)), array_map(strval(...), $context))));
    });
    $di['logger'] = $logger;

    $repository = Mockery::mock(MassmailerMessageRepository::class);
    $repository->shouldReceive('findBy')->andReturnUsing(
        fn (array $criteria): array => array_values(array_filter($messages, fn (MassmailerMessage $candidate): bool => $candidate->getStatus() === $criteria['status']))
    );

    $entityManager->shouldReceive('getRepository')->with(MassmailerMessage::class)->andReturn($repository);

    $service = Mockery::mock(Service::class)->makePartial();
    $service->setDi($di);
    $service->shouldReceive('getMessageRepository')->andReturn($repository);
    $service->shouldReceive('getMessageReceivers')->andReturn(array_map(fn (int $id): array => ['id' => $id], $clientIds));
    $service->shouldReceive('sendMessage')->andReturnUsing(function (MassmailerMessage $message, int $clientId) use ($sent, $failFor): bool {
        if (in_array($clientId, $failFor, true)) {
            throw new InformationException('Client does not have a valid email address');
        }
        $sent->append("{$message->getId()}:{$clientId}");

        return true;
    });

    return $service;
}

/**
 * A clock that moves on one second each time it is read.
 */
function massmailerSendClock(): Closure
{
    $now = 1000.0;

    return function () use (&$now): float {
        return $now++;
    };
}

test('queueForSending marks a draft sending without queueing any email', function (): void {
    $sent = new ArrayObject();
    $message = massmailerSendMessage(7);

    expect(massmailerSendService([30, 20, 10], $sent)->queueForSending($message))->toBe(3)
        ->and($message->getStatus())->toBe(MassmailerMessage::STATUS_SENDING)
        ->and($message->toApiArray()['send_progress'])->toBe(['total' => 3, 'queued' => 0, 'failed' => 0])
        ->and($sent->getArrayCopy())->toBe([]);
});

test('queueForSending refuses a message that is sending, sent or matches no client', function (string $status, array $clients, string $error): void {
    expect(fn () => massmailerSendService($clients, new ArrayObject())->queueForSending(massmailerSendMessage(7, $status)))
        ->toThrow(InformationException::class, $error);
})->with([
    'sent' => [MassmailerMessage::STATUS_SENT, [10], 'already been sent'],
    'sending' => [MassmailerMessage::STATUS_SENDING, [10], 'already being sent'],
    'no clients' => [MassmailerMessage::STATUS_DRAFT, [], 'No clients match'],
]);

test('sendQueuedBatch queues newest client first, resumes where it stopped and marks the message sent', function (): void {
    $sent = new ArrayObject();
    $message = massmailerSendMessage(7);
    $service = massmailerSendService([50, 40, 30, 20, 10], $sent, [$message]);
    $service->queueForSending($message);

    // The clock is read for the deadline, then before each client: three
    // seconds is enough for two clients.
    expect($service->sendQueuedBatch(3, massmailerSendClock()))->toBe(2)
        ->and($sent->getArrayCopy())->toBe(['7:50', '7:40'])
        ->and($message->getStatus())->toBe(MassmailerMessage::STATUS_SENDING)
        ->and($message->getSendLastClientId())->toBe(40);

    expect($service->sendQueuedBatch(60, massmailerSendClock()))->toBe(3)
        ->and($sent->getArrayCopy())->toBe(['7:50', '7:40', '7:30', '7:20', '7:10'])
        ->and($message->getStatus())->toBe(MassmailerMessage::STATUS_SENT)
        ->and($message->getSentAt())->not->toBeNull()
        ->and($message->toApiArray()['send_progress'])->toBe(['total' => 5, 'queued' => 5, 'failed' => 0]);

    // A sent message is not picked up again.
    expect($service->sendQueuedBatch())->toBe(0)
        ->and($sent)->toHaveCount(5);
});

test('a client that cannot be queued is logged and skipped, and the others still receive it', function (): void {
    $sent = new ArrayObject();
    $log = new ArrayObject();
    $message = massmailerSendMessage(7);
    $service = massmailerSendService([30, 20, 10], $sent, [$message], failFor: [20], log: $log);
    $service->queueForSending($message);

    $service->sendQueuedBatch();

    expect($sent->getArrayCopy())->toBe(['7:30', '7:10'])
        ->and($message->getStatus())->toBe(MassmailerMessage::STATUS_SENT)
        ->and($message->toApiArray()['send_progress'])->toBe(['total' => 3, 'queued' => 2, 'failed' => 1])
        ->and($log->getArrayCopy())->toBe(['Mass mail message #7 could not be queued for client #20: Client does not have a valid email address']);
});

test('the cron listener sends the queued messages', function (): void {
    $sent = new ArrayObject();
    $message = massmailerSendMessage(9);
    $service = massmailerSendService([20, 10], $sent, [$message]);
    $service->queueForSending($message);

    $service->sendQueuedBeforeAdminCronRun(new BeforeAdminCronRunEvent());

    expect($sent->getArrayCopy())->toBe(['9:20', '9:10'])
        ->and($message->getStatus())->toBe(MassmailerMessage::STATUS_SENT);
});
