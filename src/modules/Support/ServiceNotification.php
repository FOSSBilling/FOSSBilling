<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Support;

use Box\Mod\Support\Entity\TicketNotification;
use Box\Mod\Support\Repository\TicketNotificationRepository;
use FOSSBilling\InjectionAwareInterface;

class ServiceNotification implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getRepository(): TicketNotificationRepository
    {
        return $this->di['em']->getRepository(TicketNotification::class);
    }

    public function getFeed(int $clientId): array
    {
        $repo = $this->getRepository();
        $summary = $repo->summary($clientId);
        $summary['list'] = array_map(static function (TicketNotification $notification): array {
            $message = $notification->getMessage();
            $ticket = $message->getTicket();

            return [
                'id' => $notification->getId(),
                'ticket_id' => $ticket->getId(),
                'message_id' => $message->getId(),
                'subject' => $ticket->getSubject(),
                'created_at' => $notification->getCreatedAt()?->format('Y-m-d H:i:s'),
                'read_at' => $notification->getReadAt()?->format('Y-m-d H:i:s'),
            ];
        }, $repo->recent($clientId));

        return $summary;
    }
}
