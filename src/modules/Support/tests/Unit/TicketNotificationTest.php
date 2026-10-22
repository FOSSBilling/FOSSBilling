<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

use Box\Mod\Support\Entity\Helpdesk;
use Box\Mod\Support\Entity\SupportTicket;
use Box\Mod\Support\Entity\SupportTicketMessage;
use Box\Mod\Support\Entity\TicketNotification;
use Box\Mod\Support\Service;
use Box\Mod\Support\ServiceNotification;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use FOSSBilling\Identity\Guest;
use FOSSBilling\InformationException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

beforeEach(function (): void {
    $config = ORMSetup::createAttributeMetadataConfig([PATH_MODS . '/Support/Entity'], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Tests\\NotificationProxies');
    $this->em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
    $this->em->getConnection()->executeStatement('PRAGMA foreign_keys = ON');
    (new SchemaTool($this->em))->createSchema(array_map($this->em->getClassMetadata(...), [Helpdesk::class, SupportTicket::class, SupportTicketMessage::class, TicketNotification::class]));
    $this->di = new Pimple\Container([
        'em' => $this->em,
        'request' => Request::create('/', 'POST'),
        'logger' => new Tests\Helpers\TestLogger(),
        'event_dispatcher' => new EventDispatcher(),
    ]);
    $this->support = new Service();
    $this->support->setDi($this->di);
    $this->notifications = new ServiceNotification();
    $this->notifications->setDi($this->di);
    $this->repo = $this->notifications->getRepository();
    $this->staff = Tests\Helpers\admin(['id' => 1]);
    $this->ticket = (new SupportTicket())->setClientId(1)->setSubject('Invoice question')->setStatus(SupportTicket::STATUS_OPEN);
    $this->em->persist($this->ticket);
    $this->em->flush();
});

afterEach(function (): void {
    $this->em->close();
});

test('only staff messages for registered clients create notifications, including admin-opened tickets', function (): void {
    $this->support->ticketReply($this->ticket, $this->staff, 'A staff reply');
    $this->support->ticketReply($this->ticket, Tests\Helpers\client(['id' => 1]), 'A client reply');
    $this->support->ticketReply($this->ticket, new Guest(), 'A guest reply');
    $this->support->messageCreateForTicket($this->ticket, $this->staff, 'Another staff message');
    $guestTicket = (new SupportTicket())->setSubject('Guest ticket');
    $this->em->persist($guestTicket);
    $this->em->flush();
    $this->support->ticketReply($guestTicket, $this->staff, 'Guest reply');
    $helpdesk = (new Helpdesk())->setName('Support');
    $this->em->persist($helpdesk);
    $this->em->flush();
    $this->support->ticketCreateForAdmin(1, $helpdesk, ['subject' => 'Proactive support', 'content' => 'Hello'], $this->staff);

    expect($this->repo->count([]))->toBe(3)
        ->and($this->notifications->getFeed(1)['unread_count'])->toBe(3)
        ->and($this->notifications->getFeed(2)['list'])->toBe([]);
});

test('reading is persistent and idempotent and does not change ticket status', function (): void {
    $this->support->ticketReply($this->ticket, $this->staff, 'Reply');
    $feed = $this->notifications->getFeed(1);
    expect($feed['unread_count'])->toBe(1)->and($feed['list'][0]['subject'])->toBe('Invoice question');
    $id = $feed['list'][0]['id'];
    $this->repo->markRead(1, $id);
    $this->em->clear();
    $readAt = $this->notifications->getFeed(1)['list'][0]['read_at'];
    $this->repo->markRead(1, $id);
    $this->em->clear();

    expect($readAt)->not->toBeNull()
        ->and($this->notifications->getFeed(1)['unread_count'])->toBe(0)
        ->and($this->notifications->getFeed(1)['list'][0]['read_at'])->toBe($readAt)
        ->and($this->em->find(SupportTicket::class, 1)->getStatus())->toBe(SupportTicket::STATUS_ONHOLD);
});

test('another client cannot see or mark a notification read', function (): void {
    $this->support->ticketReply($this->ticket, $this->staff, 'Private reply');
    $id = $this->notifications->getFeed(1)['list'][0]['id'];
    expect(fn () => $this->repo->markRead(2, $id))->toThrow(InformationException::class, 'Notification not found');
    $this->repo->markAllRead(2, PHP_INT_MAX);
    $this->repo->markTicketRead(2, 1, PHP_INT_MAX);

    expect($this->repo->summary(1)['unread_count'])->toBe(1)
        ->and($this->notifications->getFeed(2)['unread_count'])->toBe(0);
});

test('mark all read leaves concurrent replies unread', function (): void {
    $this->support->ticketReply($this->ticket, $this->staff, 'First reply');
    $boundary = $this->notifications->getFeed(1)['latest_id'];
    $this->support->ticketReply($this->ticket, $this->staff, 'Concurrent reply');
    $this->repo->markAllRead(1, $boundary);
    $this->em->clear();

    expect($this->notifications->getFeed(1)['unread_count'])->toBe(1)
        ->and($this->notifications->getFeed(1)['list'][0]['read_at'])->toBeNull();
});

test('viewing a conversation marks only the displayed replies read', function (): void {
    $first = $this->support->ticketReply($this->ticket, $this->staff, 'Visible reply');
    $later = $this->support->ticketReply($this->ticket, $this->staff, 'Later reply');
    $otherTicket = (new SupportTicket())->setClientId(1)->setSubject('Other ticket');
    $this->em->persist($otherTicket);
    $this->em->flush();
    $this->support->ticketReply($otherTicket, $this->staff, 'Other conversation');
    $this->repo->markTicketRead(1, (int) $this->ticket->getId(), $first);
    $this->em->clear();
    $feed = $this->notifications->getFeed(1);

    expect($feed['unread_count'])->toBe(2)
        ->and(array_column(array_filter($feed['list'], fn ($n) => $n['read_at'] !== null), 'message_id'))->toBe([$first])
        ->and($later)->toBeGreaterThan($first);
});

test('reassigned and deleted tickets do not leak notifications', function (): void {
    $this->support->ticketReply($this->ticket, $this->staff, 'Reply');
    $id = $this->notifications->getFeed(1)['list'][0]['id'];
    $this->ticket->setClientId(2);
    $this->em->flush();
    expect($this->notifications->getFeed(1)['list'])->toBe([])
        ->and($this->notifications->getFeed(2)['list'])->toBe([]);
    expect(fn () => $this->repo->markRead(1, $id))->toThrow(InformationException::class);
    $this->em->remove($this->ticket);
    $this->em->flush();
    expect($this->repo->count([]))->toBe(0);
});

test('feed is bounded and prioritizes unread replies ahead of newer read replies', function (): void {
    for ($i = 0; $i < 12; ++$i) {
        $this->support->ticketReply($this->ticket, $this->staff, 'Reply ' . $i);
    }
    $feed = $this->notifications->getFeed(1);
    $this->repo->markRead(1, $feed['latest_id']);
    $this->em->clear();
    $feed = $this->notifications->getFeed(1);
    expect($feed['list'])->toHaveCount(10)
        ->and($feed['unread_count'])->toBe(11)
        ->and($feed['list'][0]['read_at'])->toBeNull()
        ->and(array_column($feed['list'], 'id'))->not->toContain($feed['latest_id']);
});

test('client API derives ownership from the authenticated identity', function (): void {
    $this->support->ticketReply($this->ticket, $this->staff, 'Private reply');
    $id = $this->notifications->getFeed(1)['list'][0]['id'];
    $notifications = $this->notifications;
    $this->di['mod_service'] = fn () => static fn () => $notifications;
    $api = new Box\Mod\Support\Api\Client();
    $api->setDi($this->di);
    $api->setService($this->support);
    $api->setIdentity(Tests\Helpers\client(['id' => 2]));

    expect($api->notification_get_list()['list'])->toBe([]);
    expect(fn () => $api->notification_mark_read(['id' => $id, 'client_id' => 1]))->toThrow(InformationException::class);
    expect(fn () => $api->notification_read_ticket(['ticket_id' => 1, 'through_message_id' => PHP_INT_MAX, 'client_id' => 1]))->toThrow(InformationException::class);
    $api->notification_mark_all_read(['through_id' => PHP_INT_MAX, 'client_id' => 1]);
    expect($this->repo->summary(1)['unread_count'])->toBe(1);
});

test('historical messages are not retroactively classified as unread notifications', function (): void {
    $message = (new SupportTicketMessage())->setTicket($this->ticket)->setAdminId(1)->setContent('Historical reply');
    $this->em->persist($message);
    $this->em->flush();
    expect($this->notifications->getFeed(1)['unread_count'])->toBe(0);
    $this->support->ticketReply($this->ticket, $this->staff, 'New reply');
    expect($this->notifications->getFeed(1)['unread_count'])->toBe(1);
});

test('notification read mutations reject GET requests', function (): void {
    $this->di['request'] = Request::create('/', 'GET');
    $api = new Box\Mod\Support\Api\Client();
    $api->setDi($this->di);
    $api->setIdentity(Tests\Helpers\client(['id' => 1]));
    expect(fn () => $api->notification_mark_read(['id' => 1]))->toThrow(InformationException::class, 'This action requires POST');
    expect(fn () => $api->notification_mark_all_read(['through_id' => 1]))->toThrow(InformationException::class, 'This action requires POST');
    expect(fn () => $api->notification_read_ticket(['ticket_id' => 1, 'through_message_id' => 1]))->toThrow(InformationException::class, 'This action requires POST');
});
