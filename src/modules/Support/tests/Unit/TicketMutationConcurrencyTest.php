<?php

declare(strict_types=1);

use Box\Mod\Support\Entity\Helpdesk;
use Box\Mod\Support\Entity\SupportTicket;
use Box\Mod\Support\Entity\SupportTicketMessage;
use Box\Mod\Support\Entity\SupportTicketMessageHistory;
use Box\Mod\Support\Entity\SupportTicketNote;
use Box\Mod\Support\Service;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Tools\SchemaTool;
use FOSSBilling\Core\Doctrine\EntityManagerFactory;
use FOSSBilling\Core\Exception\InformationException;
use Symfony\Component\Filesystem\Filesystem;

function supportMutationDatabase(string $driver): array
{
    $filesystem = new Filesystem();
    $dir = sys_get_temp_dir() . '/fb-support-mutation-' . bin2hex(random_bytes(6));
    $filesystem->mkdir($dir);
    $control = null;
    $name = null;
    if ($driver === 'sqlite') {
        $params = ['driver' => 'pdo_sqlite', 'path' => $dir . '/test.sqlite'];
    } else {
        $raw = getenv('ATTACHMENT_TEST_' . strtoupper($driver) . '_PARAMS');
        if ($raw === false) {
            $filesystem->remove($dir);
            PHPUnit\Framework\Assert::markTestSkipped('An isolated database server is required.');
        }
        $params = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        $control = DriverManager::getConnection($params);
        $name = 'fb_mutation_' . bin2hex(random_bytes(6));
        $control->executeStatement('CREATE DATABASE ' . $control->quoteSingleIdentifier($name));
        $params['dbname'] = $name;
    }
    $db = DriverManager::getConnection($params);
    $em = EntityManagerFactory::create($db);
    $metadata = array_map($em->getClassMetadata(...), [Helpdesk::class, SupportTicket::class, SupportTicketMessage::class, SupportTicketNote::class, SupportTicketMessageHistory::class]);
    // Reproduce upgraded installations: no foreign keys enforce deletion here.
    foreach ((new SchemaTool($em))->getCreateSchemaSql($metadata) as $sql) {
        if (!str_contains($sql, 'ADD CONSTRAINT')) {
            $db->executeStatement($sql);
        }
    }
    $ticket = (new SupportTicket())->setSubject('Concurrency')->setStatus('open');
    $message = (new SupportTicketMessage())->setSupportTicket($ticket)->setAdminId(9)->setContent('Original');
    $em->persist($ticket);
    $em->persist($message);
    $em->flush();
    $di = Tests\Helpers\container();
    $di['em'] = $em;
    $service = new Service();
    $service->setDi($di);
    $cleanup = function () use ($db, $control, $name, $filesystem, $dir): void {
        if ($db->isTransactionActive()) {
            $db->rollBack();
        }
        $db->close();
        if ($control !== null) {
            $control->executeStatement('DROP DATABASE ' . $control->quoteSingleIdentifier($name));
            $control->close();
        }
        $filesystem->remove($dir);
    };

    return [$db, $params, $service, $ticket, $message, $cleanup];
}

test('ticket child writes reject a deleted parent on legacy schemas', function (string $action): void {
    [$db, $params, $service, $ticket, $message, $cleanup] = supportMutationDatabase('sqlite');

    try {
        $db->delete('support_ticket_message', ['id' => 1]);
        $db->delete('support_ticket', ['id' => 1]);
        $admin = Tests\Helpers\admin(['id' => 9]);
        $write = match ($action) {
            'note' => fn () => $service->noteCreate($ticket, $admin, 'Private note'),
            'message' => fn () => $service->messageCreateForTicket($ticket, $admin, 'Direct reply'),
            'edit' => fn () => $service->ticketMessageUpdate($message, 'Edited', $admin),
        };
        expect($write)->toThrow(InformationException::class);
        foreach (['support_ticket_message', 'support_ticket_note', 'support_ticket_message_history'] as $table) {
            expect((int) $db->fetchOne('SELECT COUNT(*) FROM ' . $table))->toBe(0);
        }
    } finally {
        $cleanup();
    }
})->with(['note', 'message', 'edit']);

test('message editing records the latest committed content in history', function (): void {
    [$db, $params, $service, $ticket, $message, $cleanup] = supportMutationDatabase('sqlite');

    try {
        $db->update('support_ticket_message', ['content' => 'Other staff edit'], ['id' => 1]);
        $service->ticketMessageUpdate($message, 'Original', Tests\Helpers\admin(['id' => 9]));
        expect($db->fetchOne('SELECT content FROM support_ticket_message_history'))->toBe('Other staff edit')
            ->and($db->fetchOne('SELECT content FROM support_ticket_message WHERE id = 1'))->toBe('Original');
    } finally {
        $cleanup();
    }
});

test('ticket deletion serializes concurrent note message and history writes', function (string $driver, string $action): void {
    [$db, $params, $service, $ticket, $message, $cleanup] = supportMutationDatabase($driver);
    $process = null;
    $pipes = [];

    try {
        $db->beginTransaction();
        $db->executeStatement('UPDATE support_ticket SET status = status WHERE id = 1');
        $env = array_merge(getenv(), ['SUPPORT_MUTATION_DB' => json_encode($params, JSON_THROW_ON_ERROR), 'SUPPORT_MUTATION_ACTION' => $action]);
        $process = proc_open([PHP_BINARY, PATH_TESTS . '/Support/SupportMutationWorker.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
        stream_set_timeout($pipes[1], 15);
        expect(trim((string) fgets($pipes[1])))->toBe('ready');
        fwrite($pipes[0], "go\n");
        expect(trim((string) fgets($pipes[1])))->toBe('writing');
        usleep(150000);
        expect(proc_get_status($process)['running'])->toBeTrue();
        $db->delete('support_ticket_message', ['id' => 1]);
        $db->delete('support_ticket', ['id' => 1]);
        $db->commit();
        $result = json_decode((string) fgets($pipes[1]), true, flags: JSON_THROW_ON_ERROR);
        expect($result['error'] ?? null)->toBe(InformationException::class);
        foreach (['support_ticket_message', 'support_ticket_note', 'support_ticket_message_history'] as $table) {
            expect((int) $db->fetchOne('SELECT COUNT(*) FROM ' . $table))->toBe(0);
        }
    } finally {
        if (is_resource($process)) {
            proc_terminate($process);
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            proc_close($process);
        }
        $cleanup();
    }
})->with(['sqlite', 'mysql', 'pgsql'])->with(['note', 'message', 'edit']);
