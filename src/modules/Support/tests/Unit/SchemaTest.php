<?php

declare(strict_types=1);

use Box\Mod\Support\Entity\SupportEscalationApplied;
use Box\Mod\Support\Entity\SupportTicketAssignment;
use Box\Mod\Support\Entity\SupportTicketFeedback;
use Box\Mod\Support\Entity\SupportTicketFeedbackRequest;
use Box\Mod\Support\Entity\SupportTicketFile;
use Box\Mod\Support\Entity\SupportTicketMerge;
use Box\Mod\Support\Entity\SupportTicketMessageRating;
use Box\Mod\Support\Entity\SupportTicketSecret;
use Box\Mod\Support\Entity\SupportTicketSecretEvent;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\Tools\SchemaTool;
use FOSSBilling\Core\Doctrine\EntityManagerFactory;

test('fresh support schemas retain the upgrade lookup indexes and unique tokens', function (): void {
    $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $em = EntityManagerFactory::create($db);
    $classes = [SupportEscalationApplied::class, SupportTicketAssignment::class, SupportTicketFeedback::class,
        SupportTicketFeedbackRequest::class, SupportTicketFile::class, SupportTicketMerge::class,
        SupportTicketMessageRating::class, SupportTicketSecret::class, SupportTicketSecretEvent::class];
    (new SchemaTool($em))->createSchema(array_map($em->getClassMetadata(...), $classes));
    $columnNames = static function (Doctrine\DBAL\Schema\Index|Doctrine\DBAL\Schema\UniqueConstraint $index): array {
        $names = $index instanceof Doctrine\DBAL\Schema\Index
            ? array_map(static fn ($column) => $column->getColumnName(), $index->getIndexedColumns())
            : $index->getColumnNames();

        return array_map(static fn ($name): string => $name->getIdentifier()->getValue(), $names);
    };
    $expected = [
        'support_escalation_applied' => [['rule_id', 'ticket_id'], ['ticket_id']],
        'support_ticket_assignment' => [['admin_id']],
        'support_ticket_feedback' => [['ticket_id'], ['admin_id', 'created_at']],
        'support_ticket_file' => [['token'], ['message_id'], ['ticket_id'], ['client_id'], ['session_hash', 'message_id'], ['uploader_kind', 'uploader_id', 'created_at']],
        'support_ticket_merge' => [['merged_into'], ['access_hash']],
        'support_ticket_message_rating' => [['ticket_id'], ['admin_id', 'rated_at']],
        'support_ticket_secret' => [['ticket_id'], ['created_at']],
        'support_ticket_secret_event' => [['ticket_id']],
    ];
    foreach ($expected as $table => $columnSets) {
        $definition = $db->createSchemaManager()->introspectTableByUnquotedName($table);
        $indexes = [...$definition->getIndexes(), ...$definition->getUniqueConstraints()];
        $actual = array_map($columnNames, array_values($indexes));
        foreach ($columnSets as $columns) {
            expect($actual)->toContain($columns);
        }
    }
    foreach (['support_ticket_file', 'support_ticket_feedback_request'] as $table) {
        $definition = $db->createSchemaManager()->introspectTableByUnquotedName($table);
        $indexes = [...$definition->getIndexes(), ...$definition->getUniqueConstraints()];
        expect(array_filter($indexes, static fn ($index): bool => ($index instanceof Doctrine\DBAL\Schema\UniqueConstraint || $index->isUnique()) && $columnNames($index) === ['token']))->not->toBeEmpty();
    }
    // Verify enforcement through the actual generated schema, not just metadata.
    $file = ['token' => str_repeat('a', 32), 'client_id' => 1, 'uploader_kind' => 'client', 'uploader_id' => 1,
        'session_hash' => str_repeat('b', 64), 'name' => 'test.txt', 'mime' => 'text/plain', 'size' => 1, 'created_at' => '2026-01-01 00:00:00'];
    $db->insert('support_ticket_file', $file);
    expect(fn () => $db->insert('support_ticket_file', $file))->toThrow(UniqueConstraintViolationException::class);
    $request = ['ticket_id' => 1, 'token' => str_repeat('c', 64), 'sent_at' => '2026-01-01 00:00:00'];
    $db->insert('support_ticket_feedback_request', $request);
    $request['ticket_id'] = 2;
    expect(fn () => $db->insert('support_ticket_feedback_request', $request))->toThrow(UniqueConstraintViolationException::class);
    $db->close();
});
