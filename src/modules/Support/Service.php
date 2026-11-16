<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Support;

use Box\Mod\Client\Entity\Client;
use Box\Mod\Order\Entity\Order;
use Box\Mod\Support\Entity\CannedResponse;
use Box\Mod\Support\Entity\CannedResponseCategory;
use Box\Mod\Support\Entity\Helpdesk;
use Box\Mod\Support\Entity\KbArticle;
use Box\Mod\Support\Entity\KbArticleCategory;
use Box\Mod\Support\Entity\SupportTicket;
use Box\Mod\Support\Entity\SupportTicketMessage;
use Box\Mod\Support\Entity\SupportTicketMessageHistory;
use Box\Mod\Support\Entity\SupportTicketNote;
use Box\Mod\Support\Event\AfterTicketClosedEvent;
use Box\Mod\Support\Event\AfterTicketOpenedEvent;
use Box\Mod\Support\Event\AfterTicketRepliedEvent;
use Box\Mod\Support\Event\BeforeGuestTicketCreateEvent;
use Box\Mod\Support\Event\BeforeTicketCreateEvent;
use Box\Mod\Support\Event\TicketActorRole;
use Box\Mod\Support\Repository\CannedResponseCategoryRepository;
use Box\Mod\Support\Repository\CannedResponseRepository;
use Box\Mod\Support\Repository\HelpdeskRepository;
use Box\Mod\Support\Repository\KbArticleCategoryRepository;
use Box\Mod\Support\Repository\KbArticleRepository;
use Box\Mod\Support\Repository\SupportTicketMessageHistoryRepository;
use Box\Mod\Support\Repository\SupportTicketMessageRepository;
use Box\Mod\Support\Repository\SupportTicketNoteRepository;
use Box\Mod\Support\Repository\SupportTicketRepository;
use FOSSBilling\Core\Doctrine\RowLock;
use FOSSBilling\Core\Doctrine\SqlExpr;
use FOSSBilling\Core\Exception\InformationException;
use FOSSBilling\Core\Twig\Markdown\FOSSBillingMarkdown;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

class Service implements \FOSSBilling\Core\Container\InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;
    protected KbArticleRepository $kbArticleRepository;
    protected KbArticleCategoryRepository $kbArticleCategoryRepository;
    protected CannedResponseRepository $cannedResponseRepository;
    protected CannedResponseCategoryRepository $cannedResponseCategoryRepository;
    protected HelpdeskRepository $helpdeskRepository;
    protected SupportTicketRepository $supportTicketRepository;
    protected SupportTicketMessageRepository $supportTicketMessageRepository;
    protected SupportTicketNoteRepository $supportTicketNoteRepository;
    protected SupportTicketMessageHistoryRepository $supportTicketMessageHistoryRepository;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
        $em = $di['em'];
        $this->kbArticleRepository = $em->getRepository(KbArticle::class);
        $this->kbArticleCategoryRepository = $em->getRepository(KbArticleCategory::class);
        $this->cannedResponseRepository = $em->getRepository(CannedResponse::class);
        $this->cannedResponseCategoryRepository = $em->getRepository(CannedResponseCategory::class);
        $this->helpdeskRepository = $em->getRepository(Helpdesk::class);
        $this->supportTicketRepository = $em->getRepository(SupportTicket::class);
        $this->supportTicketMessageRepository = $em->getRepository(SupportTicketMessage::class);
        $this->supportTicketNoteRepository = $em->getRepository(SupportTicketNote::class);
        $this->supportTicketMessageHistoryRepository = $em->getRepository(SupportTicketMessageHistory::class);
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getKbArticleRepository(): KbArticleRepository
    {
        return $this->kbArticleRepository;
    }

    public function getKbArticleCategoryRepository(): KbArticleCategoryRepository
    {
        return $this->kbArticleCategoryRepository;
    }

    public function getCannedResponseRepository(): CannedResponseRepository
    {
        return $this->cannedResponseRepository;
    }

    public function getCannedResponseCategoryRepository(): CannedResponseCategoryRepository
    {
        return $this->cannedResponseCategoryRepository;
    }

    public function getSupportTicketRepository(): SupportTicketRepository
    {
        return $this->supportTicketRepository;
    }

    public function getSupportTicketMessageRepository(): SupportTicketMessageRepository
    {
        return $this->supportTicketMessageRepository;
    }

    public function getSupportTicketNoteRepository(): SupportTicketNoteRepository
    {
        return $this->supportTicketNoteRepository;
    }

    public function getSupportTicketMessageHistoryRepository(): SupportTicketMessageHistoryRepository
    {
        return $this->supportTicketMessageHistoryRepository;
    }

    public function getHelpdeskRepository(): HelpdeskRepository
    {
        return $this->helpdeskRepository;
    }

    public function getModulePermissions(): array
    {
        return [
            'view' => [
                'type' => 'bool',
                'display_name' => __trans('View support tickets'),
                'description' => __trans('Allows the staff member to view tickets, inquiries, helpdesks, canned responses, and knowledge base articles.'),
            ],
            'manage_tickets' => [
                'type' => 'bool',
                'display_name' => __trans('Manage tickets'),
                'description' => __trans('Allows the staff member to create, update, reply to, close, and delete tickets and inquiries.'),
            ],
            'manage_helpdesk' => [
                'type' => 'bool',
                'display_name' => __trans('Manage helpdesks'),
                'description' => __trans('Allows the staff member to create, update, and delete helpdesks.'),
            ],
            'manage_canned' => [
                'type' => 'bool',
                'display_name' => __trans('Manage canned responses'),
                'description' => __trans('Allows the staff member to create, update, and delete canned responses and categories.'),
            ],
            'manage_kb' => [
                'type' => 'bool',
                'display_name' => __trans('Manage knowledge base'),
                'description' => __trans('Allows the staff member to create, update, and delete knowledge base articles and categories.'),
            ],
            'manage_settings' => [],
            'open_secrets' => [
                'type' => 'bool',
                'display_name' => __trans('Open ticket secrets'),
                'description' => __trans('Allows the staff member to open the encrypted secrets clients attached to tickets. Ticket access alone does not reveal them.'),
            ],
        ];
    }

    #[AsEventListener]
    public function notifyTicketOpened(AfterTicketOpenedEvent $event): void
    {
        $di = $this->di ?? throw new \LogicException('The Support service dependency injection container has not been set.');
        $supportService = $di['mod_service']('support');
        $emailService = $di['mod_service']('email');

        try {
            $ticketObj = $supportService->getTicketById($event->ticketId);
            $isGuestTicket = $ticketObj->isGuestTicket();
            $identity = match ($event->actor) {
                TicketActorRole::ADMIN => $di['loggedin_admin'],
                TicketActorRole::CLIENT => $di['loggedin_client'],
                TicketActorRole::GUEST => null,
            };
            $ticketArr = $supportService->toApiArray($ticketObj, true, $identity);

            $email = [];
            if ($isGuestTicket) {
                $email['to'] = $ticketObj->getAuthorEmail();
                $email['to_name'] = $ticketObj->getAuthorName();
            } else {
                $email['to_client'] = $ticketObj->getClientId();
            }
            $email['code'] = $event->actor === TicketActorRole::ADMIN ? 'mod_support_ticket_staff_open' : 'mod_support_ticket_open';
            $email['ticket'] = $ticketArr;
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            $message = $event->actor === TicketActorRole::ADMIN ? 'Failed to send admin ticket open email' : 'Failed to send ticket open email';
            $di['logger']->withChannel('email')->error($message, ['exception' => $exc]);
        }
    }

    #[AsEventListener]
    public function notifyTicketClosed(AfterTicketClosedEvent $event): void
    {
        if ($event->actor !== TicketActorRole::ADMIN) {
            return;
        }

        $di = $this->di ?? throw new \LogicException('The Support service dependency injection container has not been set.');
        $supportService = $di['mod_service']('support');
        $emailService = $di['mod_service']('email');

        $send = function () use ($di, $supportService, $emailService, $event): void {
            try {
                $identity = $event->actorId !== null
                    ? $di['em']->getRepository(\Box\Mod\Staff\Entity\Admin::class)->find($event->actorId)
                    : $di['loggedin_admin'];
                $ticketObj = $supportService->getTicketById($event->ticketId);
                $ticketArr = $supportService->toApiArray($ticketObj, true, $identity);

                $email = [];
                if ($ticketObj->isGuestTicket()) {
                    $email['to'] = $ticketObj->getAuthorEmail();
                    $email['to_name'] = $ticketObj->getAuthorName();
                } else {
                    $email['to_client'] = $ticketObj->getClientId();
                }
                $email['code'] = 'mod_support_ticket_staff_close';
                $email['ticket'] = $ticketArr;
                $emailService->sendTemplate($email);
            } catch (\Exception $exc) {
                $di['logger']->withChannel('email')->error('Failed to send ticket close email', ['exception' => $exc]);

                throw $exc;
            }
        };
        if ($event->deliveryId !== null) {
            TicketCloseOutbox::once($di, $event->deliveryId, 'notification_queued_at', $send);
        } else {
            $send();
        }
    }

    #[AsEventListener]
    public function notifyTicketReplied(AfterTicketRepliedEvent $event): void
    {
        if ($event->actor !== TicketActorRole::ADMIN) {
            return;
        }

        $di = $this->di ?? throw new \LogicException('The Support service dependency injection container has not been set.');
        $supportService = $di['mod_service']('support');
        $emailService = $di['mod_service']('email');

        try {
            $ticketObj = $supportService->getTicketById($event->ticketId);
            $identity = $di['loggedin_admin'];
            $ticketArr = $supportService->toApiArray($ticketObj, true, $identity);

            $email = [];
            if ($ticketObj->isGuestTicket()) {
                $email['to'] = $ticketObj->getAuthorEmail();
                $email['to_name'] = $ticketObj->getAuthorName();
            } else {
                $email['to_client'] = $ticketObj->getClientId();
            }
            $email['code'] = 'mod_support_ticket_staff_reply';
            $email['ticket'] = $ticketArr;
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            $di['logger']->withChannel('email')->error('Failed to send ticket reply email', ['exception' => $exc]);
        }
    }

    public function getTicketById(int $id): SupportTicket
    {
        return $this->getSupportTicketRepository()->findOneByIdOrFail($id);
    }

    public function getTicketMessageById(int $id): SupportTicketMessage
    {
        return $this->getSupportTicketMessageRepository()->findOneByIdOrFail($id);
    }

    public function getTicketNoteById(int $id): SupportTicketNote
    {
        return $this->getSupportTicketNoteRepository()->findOneByIdOrFail($id);
    }

    /**
     * Return array of ticket statuses.
     */
    public function getStatuses(): array
    {
        return [
            SupportTicket::STATUS_OPEN => 'Open',
            SupportTicket::STATUS_ONHOLD => 'On Hold',
            SupportTicket::STATUS_CLOSED => 'Closed',
        ];
    }

    /**
     * Find ticket for client.
     */
    public function findOneByClient(Client $c, int $id): SupportTicket
    {
        return $this->getSupportTicketRepository()->findOneByClientOrFail((int) $c->getId(), $id);
    }

    public function counter(): array
    {
        $data = $this->getSupportTicketRepository()->countGroupedByStatus();

        return [
            'total' => array_sum($data),
            SupportTicket::STATUS_OPEN => $data[SupportTicket::STATUS_OPEN] ?? 0,
            SupportTicket::STATUS_CLOSED => $data[SupportTicket::STATUS_CLOSED] ?? 0,
            SupportTicket::STATUS_ONHOLD => $data[SupportTicket::STATUS_ONHOLD] ?? 0,
        ];
    }

    public function getLatest(): array
    {
        return $this->getSupportTicketRepository()->findLatest();
    }

    public function getExpired(): array
    {
        return $this->getSupportTicketRepository()->findExpiredOnHold(new \DateTime());
    }

    public function countByStatus(string $status): int
    {
        return $this->getSupportTicketRepository()->countByStatus($status);
    }

    public function checkIfTaskAlreadyExists(Client $client, int $rel_id, string $rel_type, string $rel_task): bool
    {
        return $this->getSupportTicketRepository()->hasPendingTaskForClient((int) $client->getId(), $rel_id, $rel_type, $rel_task);
    }

    public function closeTicket(SupportTicket $ticket, \Box\Mod\Staff\Entity\Admin|Client|\FOSSBilling\Core\Identity\Guest $identity): bool
    {
        $actor = match (true) {
            $identity instanceof \Box\Mod\Staff\Entity\Admin => TicketActorRole::ADMIN,
            $identity instanceof Client => TicketActorRole::CLIENT,
            default => TicketActorRole::GUEST,
        };
        $actorId = $identity instanceof \Box\Mod\Staff\Entity\Admin || $identity instanceof Client ? $identity->getId() : null;
        $result = $this->closeTicketInTransaction($ticket, false, $actor, $actorId);
        if (!$result['closed']) {
            return true;
        }
        TicketCloseOutbox::deliver($this->di, $result['event_id']);
        $this->di['logger']->info('Closed ticket "{ticket_id}"', ['ticket_id' => $ticket->getId()]);

        return true;
    }

    public function autoClose(SupportTicket $model): bool
    {
        $result = $this->closeTicketInTransaction($model, true, null, null, true);
        if (!$result['closed']) {
            return false;
        }
        // The cron delivers these after its other work, so a failed ORM flush
        // cannot prevent later tickets and maintenance tasks from running.
        $this->di['logger']->info('Ticket {model_id} was closed', ['model_id' => $model->getId()]);

        return true;
    }

    /** @return array{closed: bool, event_id: ?string} */
    private function closeTicketInTransaction(SupportTicket $ticket, bool $requireExpired = false, ?TicketActorRole $actor = null, ?int $actorId = null, bool $feedbackOnly = false): array
    {
        $id = $ticket->getId();
        if ($id === null) {
            throw new InformationException('Ticket not found.');
        }
        $em = $this->di['em'];

        return $em->wrapInTransaction(function () use ($em, $ticket, $id, $requireExpired, $actor, $actorId, $feedbackOnly): array {
            $db = $em->getConnection();
            $status = $db->fetchOne('SELECT status FROM support_ticket WHERE id = ?' . RowLock::suffix($db), [$id]);
            if ($status === false) {
                throw new InformationException('Ticket not found.');
            }
            $em->refresh($ticket);
            if ($requireExpired && $status !== SupportTicket::STATUS_CLOSED) {
                $expiresAt = SqlExpr::addHours($db, 'st.updated_at', 'sh.close_after');
                $eligible = $db->fetchOne(
                    "SELECT 1 FROM support_ticket st LEFT JOIN support_helpdesk sh ON sh.id = st.support_helpdesk_id WHERE st.id = ? AND st.status = ? AND {$expiresAt} < ?",
                    [$id, SupportTicket::STATUS_ONHOLD, date('Y-m-d H:i:s')]
                );
                if ($eligible === false) {
                    return ['closed' => false, 'event_id' => null];
                }
            }
            if ($status !== SupportTicket::STATUS_CLOSED) {
                $ticket->close();
                $em->flush();
            }
            // A failed purge rolls back the status change and suppresses events.
            TicketSecrets::forgetTicket($db, $id);

            $closed = $status !== SupportTicket::STATUS_CLOSED;
            $eventId = null;
            if ($closed) {
                $eventId = $feedbackOnly ? TicketCloseOutbox::enqueueFeedback($db, $id) : ($actor !== null ? TicketCloseOutbox::enqueue($db, $id, $actor, $actorId) : null);
            }

            return ['closed' => $closed, 'event_id' => $eventId];
        });
    }

    public function canBeReopened(SupportTicket $model): bool
    {
        return $model->canBeReopen();
    }

    /**
     * @return mixed[]
     */
    private function _getRelDetails(SupportTicket $model): array
    {
        $result = [
            'id' => $model->getRelId() ?: null,
            'type' => $model->getRelType() ?: null,
            'task' => $model->getRelTask() ?: null,
            'new_value' => $model->getRelNewValue() ?: null,
            'status' => $model->getRelStatus() ?: null,
        ];

        if (!$model->getRelType() || !$model->getRelId()) {
            return $result;
        }

        if ($model->getRelType() === SupportTicket::REL_TYPE_ORDER) {
            $clientId = $model->getClientId();
            if ($clientId !== null && $this->fetchClientSummary($clientId) !== null) {
                $order = $this->di['mod_service']('order')->findByClientIdAndOrderId($clientId, (int) $model->getRelId());
                if ($order instanceof Order) {
                    $result['order'] = $this->di['mod_service']('order')->toApiArray($order, false);
                }
            }
        }

        return $result;
    }

    public function rmByClient(Client $client): void
    {
        foreach ($this->getSupportTicketRepository()->findByClientId((int) $client->getId()) as $ticket) {
            $this->removeTicket($ticket);
        }
        TicketFiles::forgetClient($this->di['em']->getConnection(), (int) $client->getId());
    }

    public function rm(SupportTicket $model): bool
    {
        $id = $model->getId();
        $this->removeTicket($model);
        $this->di['logger']->info('Removed ticket "{id}"', ['id' => $id]);

        return true;
    }

    /** Serialize deletion with ticket replies, ratings, secrets, and close events. */
    private function removeTicket(SupportTicket $ticket): void
    {
        $id = $ticket->getId();
        if ($id === null) {
            throw new InformationException('Ticket not found.');
        }
        $em = $this->di['em'];
        $em->wrapInTransaction(function () use ($em, $ticket, $id): void {
            $db = $em->getConnection();
            if ($db->fetchOne('SELECT id FROM support_ticket WHERE id = ?' . RowLock::suffix($db), [$id]) === false) {
                throw new InformationException('Ticket not found.');
            }
            $em->refresh($ticket);
            if ($db->fetchOne('SELECT 1 FROM ' . TicketCloseOutbox::TABLE . ' WHERE ticket_id = ? AND delivered_at IS NULL LIMIT 1', [$id]) !== false) {
                throw new InformationException('This ticket has a pending close event. Retry after it has been delivered.');
            }
            foreach ($this->getSupportTicketNoteRepository()->findByTicketId($id) as $note) {
                $em->remove($note);
            }
            foreach ($this->getSupportTicketMessageRepository()->findByTicketId($id) as $message) {
                foreach ($this->getSupportTicketMessageHistoryRepository()->findByMessageId((int) $message->getId()) as $history) {
                    $em->remove($history);
                }
                $em->remove($message);
            }
            $schema = $db->createSchemaManager();
            foreach (['support_ticket_pin', 'support_ticket_assignment', 'support_ticket_message_rating', 'support_ticket_feedback', 'support_ticket_feedback_request', 'support_ticket_merge'] as $table) {
                if ($schema->tablesExist([$table])) {
                    $db->delete($table, ['ticket_id' => $id]);
                }
            }
            if ($schema->tablesExist(['support_ticket_merge'])) {
                $db->delete('support_ticket_merge', ['merged_into' => $id]);
            }
            TicketSecrets::forgetTicket($db, $id, true);
            $db->delete(TicketCloseOutbox::TABLE, ['ticket_id' => $id]);
            $em->remove($ticket);
            $em->flush();
        });
    }

    public function toApiArray(SupportTicket $model, bool $deep = true, \Box\Mod\Staff\Entity\Admin|Client|null $identity = null): array
    {
        $firstSupportTicketMessage = $this->getSupportTicketMessageRepository()->findFirstByTicketId($model->getId() ?? 0);
        $helpdeskId = $model->getSupportHelpdeskId();
        $helpdesk = $helpdeskId !== null ? $this->getHelpdeskRepository()->find($helpdeskId) : null;

        $data = $model->toApiArray($identity);
        $data['replies'] = $this->messageGetRepliesCount($model);
        $data['priority_level'] = TicketPriority::level($model->getPriority());
        $data['first'] = $firstSupportTicketMessage instanceof SupportTicketMessage ? $this->messageToApiArray($firstSupportTicketMessage, true, $identity) : null;
        $data['helpdesk'] = $helpdesk instanceof Helpdesk ? $helpdesk->toApiArray($identity) : null;
        $data['author'] = $this->getTicketAuthor($model, $identity);

        $data['client'] = $this->getClientApiArrayForTicket($model, $identity);

        if ($deep) {
            $messages = $this->getSupportTicketMessageRepository()->findByTicketId($model->getId() ?? 0);
            foreach ($messages as $msg) {
                $data['messages'][] = $this->messageToApiArray($msg, true, $identity);
            }
            $data['ratings_on'] = TicketFeedback::ratingsOn($this->di);
            $data['message_ratings'] = TicketFeedback::ratings($this->di, (int) $model->getId());
            // Attachments ride with the messages for staff and the owning client.
            $isOwner = $identity instanceof Client && (int) $model->getClientId() === (int) $identity->getId();
            if ($identity instanceof \Box\Mod\Staff\Entity\Admin || $isOwner) {
                $data = TicketFiles::withFiles($this->di, $data);
            }
        }

        if ($identity instanceof \Box\Mod\Staff\Entity\Admin) {
            $data['rel'] = $this->_getRelDetails($model);
            $data['priority'] = $model->getPriority();
            $data['assigned_to'] = TicketAssign::assignees($this->di, [(int) $model->getId()])[(int) $model->getId()] ?? null;
            $data['notes'] = [];
            $supportTicketNotes = $this->getSupportTicketNoteRepository()->findByTicketId($model->getId() ?? 0);

            foreach ($supportTicketNotes as $note) {
                $data['notes'][] = $this->noteToApiArray($note);
            }

            if ($deep) {
                $data['feedback'] = TicketFeedback::forTicket($this->di, (int) $model->getId());
                $data['merged_tickets'] = TicketMerge::mergedInto($this->di, (int) $model->getId());
            }
        } elseif ($deep && $model->getStatus() === SupportTicket::STATUS_CLOSED) {
            // Client and guest views of a closed ticket link the feedback form.
            $data['feedback_url'] = TicketFeedback::openLink($this->di, (int) $model->getId());
        }

        return $data;
    }

    /**
     * Apply identity-based field stripping to a raw ticket row.
     *
     * Used by the batch fetcher ({@see getBatchForApi()}), which operates on
     * associative arrays rather than hydrated entities and therefore cannot
     * use {@see SupportTicket::toApiArray()}.
     */
    private function ticketToApiArray(array $data, \Box\Mod\Staff\Entity\Admin|Client|null $identity = null): array
    {
        if (!empty($data['access_hash'])) {
            $data['hash'] = $data['access_hash'];
        }

        if ($identity instanceof \Box\Mod\Staff\Entity\Admin) {
            $data['priority_level'] = TicketPriority::level($data['priority'] ?? null);

            return $data;
        }

        $data['priority_level'] = TicketPriority::level($data['priority'] ?? null);
        unset(
            $data['support_helpdesk_id'],
            $data['client_id'],
            $data['priority'],
            $data['access_hash'],
            $data['rel_type'],
            $data['rel_id'],
            $data['rel_task'],
            $data['rel_new_value'],
            $data['rel_status'],
        );

        return $data;
    }

    /**
     * Get multiple tickets in a batch for API response.
     *
     * @param array                                   $ids      Array of ticket IDs to fetch
     * @param bool                                    $deep     Whether to include full message history
     * @param \Box\Mod\Staff\Entity\Admin|Client|null $identity The requesting identity
     *
     * @return array Array of ticket API arrays. Missing IDs are silently skipped.
     */
    public function getBatchForApi(array $ids, bool $deep = false, $identity = null): array
    {
        $ids = $this->normalizeIds($ids);
        if (empty($ids)) {
            return [];
        }

        if ($deep || $identity instanceof \Box\Mod\Staff\Entity\Admin) {
            return $this->getBatchForApiWithModels($ids, $deep, $identity);
        }

        $tickets = $this->getSupportTicketRepository()->findBatchRowsByIds($ids);
        if (empty($tickets)) {
            return [];
        }

        $tickets = $this->orderRowsByIds($tickets, $ids);
        $ticketIds = array_column($tickets, 'id');
        $helpdeskIds = $this->normalizeIds(array_column($tickets, 'support_helpdesk_id'));
        $clientIds = $this->normalizeIds(array_column($tickets, 'client_id'));

        $replyCounts = $this->getSupportTicketMessageRepository()->countRepliesByTicketIds($ticketIds);

        $firstMessages = [];
        if (!empty($ticketIds)) {
            $firstMessageIdsByTicket = $this->getSupportTicketMessageRepository()->findFirstIdsByTicketIds($ticketIds);
            $messageIds = array_values($firstMessageIdsByTicket);
            if (!empty($messageIds)) {
                $messages = $this->getSupportTicketMessageRepository()->findByIds($messageIds);
                foreach ($messages as $message) {
                    $ticket = $message->getSupportTicket();
                    $firstMessages[$ticket instanceof SupportTicket ? ($ticket->getId() ?? 0) : 0] = $message;
                }
            }
        }

        $helpdesks = [];
        if (!empty($helpdeskIds)) {
            $helpdeskModels = $this->getHelpdeskRepository()->findByIds($helpdeskIds);
            foreach ($helpdeskModels as $helpdesk) {
                $helpdesks[$helpdesk->getId()] = $helpdesk;
            }
        }

        $clients = [];
        $clientAuthors = [];
        if (!empty($clientIds)) {
            $clientRows = $this->di['em']->getConnection()->fetchAllAssociative(
                'SELECT id, first_name, last_name, email FROM client WHERE id IN (?)',
                [$clientIds],
                [\Doctrine\DBAL\ArrayParameterType::INTEGER]
            );
            foreach ($clientRows as $row) {
                $id = (int) $row['id'];
                $first = (string) $row['first_name'];
                $last = (string) $row['last_name'];
                $clients[$id] = [
                    'id' => $id,
                    'first_name' => $first,
                    'last_name' => $last,
                ];
                $clientAuthors[$id] = [
                    'id' => $id,
                    'name' => trim($first . ' ' . $last),
                    'first_name' => $first,
                    'last_name' => $last,
                    'email' => (string) $row['email'],
                    'role' => 'client',
                ];
            }
        }

        $result = [];
        foreach ($tickets as $ticket) {
            $data = $this->ticketToApiArray($ticket, $identity);
            $data['replies'] = $replyCounts[$ticket['id']] ?? 0;
            $data['first'] = isset($firstMessages[$ticket['id']]) ? $this->messageToApiArray($firstMessages[$ticket['id']], true, $identity) : null;

            $helpdesk = $helpdesks[$ticket['support_helpdesk_id']] ?? null;
            $data['helpdesk'] = $helpdesk instanceof Helpdesk ? $helpdesk->toApiArray($identity) : null;

            if (empty($ticket['client_id']) && !empty($ticket['access_hash'])) {
                $data['author'] = [
                    'name' => $ticket['author_name'],
                    'email' => $ticket['author_email'],
                    'role' => 'guest',
                ];
                $data['client'] = [];
            } elseif (!isset($clients[$ticket['client_id']])) {
                $this->di['logger']->error('Missing client for ticket ' . $ticket['id']);
                // The client row is gone: fall back to the stored contact
                // details so templates rendering author.name/email/role keep working.
                $data['author'] = [
                    'name' => $ticket['author_name'] ?? '',
                    'email' => $ticket['author_email'] ?? '',
                    'role' => 'guest',
                ];
                $data['client'] = [];
            } else {
                $data['author'] = $clientAuthors[$ticket['client_id']];
                $data['client'] = $clients[$ticket['client_id']];
            }

            $result[] = $data;
        }

        return $result;
    }

    private function getBatchForApiWithModels(array $ids, bool $deep, $identity): array
    {
        $tickets = $this->getSupportTicketRepository()->findByIds($ids);
        if (empty($tickets)) {
            return [];
        }

        $ticketsById = [];
        foreach ($tickets as $ticket) {
            $id = $ticket->getId();
            if ($id !== null) {
                $ticketsById[$id] = $ticket;
            }
        }

        $result = [];
        foreach ($ids as $id) {
            if (isset($ticketsById[$id])) {
                $result[] = $this->toApiArray($ticketsById[$id], $deep, $identity);
            }
        }

        return $result;
    }

    private function normalizeIds(array $ids): array
    {
        return array_values(array_unique(array_map(intval(...), array_filter($ids, is_numeric(...)))));
    }

    private function orderRowsByIds(array $rows, array $ids): array
    {
        $rowsById = [];
        foreach ($rows as $row) {
            $rowsById[(int) $row['id']] = $row;
        }

        $ordered = [];
        foreach ($ids as $id) {
            if (isset($rowsById[$id])) {
                $ordered[] = $rowsById[$id];
            }
        }

        return $ordered;
    }

    private function getClientApiArrayForTicket(SupportTicket $ticket, \Box\Mod\Staff\Entity\Admin|Client|null $identity = null): array
    {
        if ($ticket->isGuestTicket()) {
            return [];
        }

        $clientId = $ticket->getClientId();
        if ($clientId === null) {
            return [];
        }

        if ($identity instanceof \Box\Mod\Staff\Entity\Admin) {
            $client = $this->di['em']->getRepository(Client::class)->find($clientId);

            return $client instanceof Client
                ? $this->clientToTicketApiArray($client, $identity)
                : [];
        }

        $summary = $this->fetchClientSummary($clientId);
        if ($summary === null) {
            $this->di['logger']->error('Missing client for ticket ' . $ticket->getId());

            return [];
        }

        return [
            'id' => $summary['id'],
            'first_name' => $summary['first_name'],
            'last_name' => $summary['last_name'],
        ];
    }

    private function getTicketAuthor(SupportTicket $ticket, \Box\Mod\Staff\Entity\Admin|Client|null $identity = null): array
    {
        if ($ticket->isGuestTicket()) {
            $author = [
                'name' => $ticket->getAuthorName(),
                'role' => 'guest',
            ];

            if ($identity instanceof \Box\Mod\Staff\Entity\Admin || $identity === null) {
                $author['email'] = $ticket->getAuthorEmail();
            }

            return $author;
        }

        $clientId = $ticket->getClientId();
        $client = $clientId !== null ? $this->fetchClientSummary($clientId) : null;

        if ($client !== null) {
            return [
                'id' => $client['id'],
                'name' => $client['name'],
                'first_name' => $client['first_name'],
                'last_name' => $client['last_name'],
                'email' => $client['email'],
                'role' => 'client',
            ];
        }
        $this->di['logger']->error('Missing client for ticket ' . $ticket->getId());

        // The client row is gone: fall back to the stored contact details
        // so templates rendering author.name/email/role keep working.
        return [
            'name' => $ticket->getAuthorName() ?? '',
            'email' => $ticket->getAuthorEmail() ?? '',
            'role' => 'guest',
        ];
    }

    private function clientToTicketApiArray(Client $client, \Box\Mod\Staff\Entity\Admin|Client|null $identity = null): array
    {
        if ($identity instanceof \Box\Mod\Staff\Entity\Admin) {
            $clientService = $this->di['mod_service']('client');

            return $clientService->toApiArray($client, false, $identity);
        }

        return [
            'id' => $client->getId(),
            'first_name' => $client->getFirstName(),
            'last_name' => $client->getLastName(),
        ];
    }

    /**
     * Fetch a minimal `id / name / email` summary for an admin row via DBAL.
     *
     * @return array{id: int, name: string, email: string}|null
     *
     * @todo Doctrine: replace with Admin entity once Staff is migrated
     */
    private function fetchAdminSummary(int $adminId): ?array
    {
        $row = $this->di['em']->getConnection()->fetchAssociative(
            'SELECT id, name, email FROM admin WHERE id = :id',
            ['id' => $adminId]
        );

        return $row === false ? null : [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'email' => (string) $row['email'],
        ];
    }

    /**
     * Fetch a minimal `id / first_name / last_name / email / name` summary
     * for a client row via DBAL.
     *
     * The synthesized `name` field concatenates first and last name to mimic
     * the entity {@see Client::getFullName()} behaviour.
     *
     * Name and email fields are nullable, so only a missing row means a
     * missing client.
     *
     * @return array{id: int, first_name: string, last_name: string, email: string, name: string}|null
     */
    private function fetchClientSummary(int $clientId): ?array
    {
        $row = $this->di['em']->getConnection()->fetchAssociative(
            'SELECT id, first_name, last_name, email FROM client WHERE id = :id',
            ['id' => $clientId]
        );

        if ($row === false) {
            return null;
        }

        $first = (string) $row['first_name'];
        $last = (string) $row['last_name'];
        $fullName = trim($first . ' ' . $last);

        return [
            'id' => (int) $row['id'],
            'first_name' => $first,
            'last_name' => $last,
            'email' => (string) $row['email'],
            'name' => $fullName,
        ];
    }

    public function noteGetAuthorDetails(SupportTicketNote $model): array
    {
        $adminId = $model->getAdminId();
        $admin = $adminId !== null ? $this->fetchAdminSummary($adminId) : null;

        return [
            'name' => $admin['name'] ?? null,
            'email' => $admin['email'] ?? null,
        ];
    }

    public function noteRm(SupportTicketNote $model): bool
    {
        $id = $model->getId();
        $this->di['em']->remove($model);
        $this->di['em']->flush();

        $this->di['logger']->info('Removed note #{id}', ['id' => $id]);

        return true;
    }

    public function noteToApiArray(SupportTicketNote $model, bool $deep = false, \Box\Mod\Staff\Entity\Admin|Client|null $identity = null): array
    {
        $data = [
            'id' => $model->getId(),
            'support_ticket_id' => $model->getSupportTicket()?->getId(),
            'admin_id' => $model->getAdminId(),
            'note' => $model->getNote(),
            'created_at' => $model->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updated_at' => $model->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
        $data['author'] = $this->noteGetAuthorDetails($model);

        return $data;
    }

    public function helpdeskRm(Helpdesk $model): bool
    {
        $id = $model->getId();

        if ($id !== null && $this->getHelpdeskRepository()->countTickets($id) > 0) {
            throw new InformationException('Cannot remove helpdesk which has tickets');
        }

        HiddenHelpdesks::forget($this->di, (int) $id);
        TicketFeedback::deleteHelpdesk($this->di, (int) $id);
        $this->di['em']->remove($model);
        $this->di['em']->flush();
        $this->di['logger']->info('Deleted helpdesk #{id}', ['id' => $id]);

        return true;
    }

    public function messageGetRepliesCount(SupportTicket $model): int
    {
        return $this->getSupportTicketMessageRepository()->countByTicketId($model->getId() ?? 0);
    }

    public function messageGetAuthorDetails(SupportTicketMessage $model, \Box\Mod\Staff\Entity\Admin|Client|null $identity = null): array
    {
        $adminId = $model->getAdminId();
        $clientId = $model->getClientId();
        $ticket = $model->getSupportTicket();

        if ($adminId) {
            /** @todo Doctrine: use Admin entity once Staff is migrated */
            $author = $this->fetchAdminSummary($adminId);
            $role = 'admin';
        } elseif ($clientId) {
            $author = $this->fetchClientSummary($clientId);
            $role = 'client';
        } else {
            if ($ticket instanceof SupportTicket && $ticket->isGuestTicket()) {
                return [
                    'name' => $ticket->getAuthorName(),
                    'email' => $ticket->getAuthorEmail(),
                    'role' => 'guest',
                ];
            }

            return [];
        }

        if ($author === null) {
            return [];
        }

        $result = [
            'name' => $author['name'],
            'role' => $role,
        ];

        if ($identity instanceof \Box\Mod\Staff\Entity\Admin) {
            $result['email'] = $author['email'];
        }

        return $result;
    }

    public function messageToApiArray(SupportTicketMessage $model, bool $deep = true, \Box\Mod\Staff\Entity\Admin|Client|null $identity = null): array
    {
        $data = $model->toApiArray($identity);
        $data['author'] = $this->messageGetAuthorDetails($model, $identity);

        return $data;
    }

    public function ticketUpdate(SupportTicket $model, array $data, bool $deferFeedback = false): bool
    {
        $id = $model->getId();
        if ($id === null) {
            throw new InformationException('Ticket not found.');
        }
        $em = $this->di['em'];
        $eventId = $em->wrapInTransaction(function () use ($em, $model, $data, $id): ?string {
            $db = $em->getConnection();
            $previousStatus = $db->fetchOne('SELECT status FROM support_ticket WHERE id = ?' . RowLock::suffix($db), [$id]);
            if ($previousStatus === false) {
                throw new InformationException('Ticket not found.');
            }
            $em->refresh($model);
            if (isset($data['support_helpdesk_id'])) {
                $helpdesk = $this->getHelpdeskRepository()->find((int) $data['support_helpdesk_id']);
                if ($helpdesk instanceof Helpdesk) {
                    $model->setSupportHelpdesk($helpdesk);
                }
            }
            if (isset($data['status'])) {
                $model->setStatus($data['status']);
            }
            if (isset($data['subject'])) {
                $model->setSubject($data['subject']);
            }
            if (isset($data['priority'])) {
                $model->setPriority(TicketPriority::fromInput($data['priority']));
            }
            $model->setUpdatedAt(new \DateTime());
            $em->flush();
            if ($model->getStatus() === SupportTicket::STATUS_CLOSED) {
                TicketSecrets::forgetTicket($db, $id);
                if ($previousStatus !== SupportTicket::STATUS_CLOSED) {
                    return TicketCloseOutbox::enqueueFeedback($db, $id);
                }
            }

            return null;
        });
        if ($eventId !== null && !$deferFeedback) {
            TicketCloseOutbox::deliver($this->di, $eventId);
        }

        $this->di['logger']->info('Updated ticket #{model_id}', ['model_id' => $id]);

        return true;
    }

    public function ticketMessageUpdate(SupportTicketMessage $model, string $content, \Box\Mod\Staff\Entity\Admin $identity): bool
    {
        SupportTicketValidator::validateContent($content);

        $em = $this->di['em'];
        $ticketId = $em->getConnection()->fetchOne('SELECT support_ticket_id FROM support_ticket_message WHERE id = ?', [$model->getId()]);
        if ($ticketId === false || $ticketId === null) {
            throw new InformationException('Ticket message not found.');
        }
        $em->wrapInTransaction(function () use ($em, $model, $content, $identity, $ticketId): void {
            $this->lockTicketForWrite((int) $ticketId);
            $currentTicketId = $em->getConnection()->fetchOne('SELECT support_ticket_id FROM support_ticket_message WHERE id = ?' . RowLock::suffix($em->getConnection()), [$model->getId()]);
            if ($currentTicketId === false || (int) $currentTicketId !== (int) $ticketId) {
                throw new InformationException('The message was deleted or moved. Reload the ticket before editing it.');
            }
            $em->refresh($model);
            if ($model->getAdminId() === null) {
                throw new InformationException('Only admin replies can be edited');
            }
            $previousContent = (string) $model->getContent();
            if ($previousContent === $content) {
                return;
            }
            $history = new SupportTicketMessageHistory();
            $history->setMessage($model);
            $history->setAdminId((int) $identity->getId());
            $history->setContent($previousContent);
            $em->persist($history);
            $model->setContent($content);
            $em->flush();
        });

        $this->di['logger']->info('Edited ticket message #{model_id}', ['model_id' => $model->getId()]);

        return true;
    }

    /**
     * @return array[]
     */
    public function getMessageHistory(SupportTicketMessage $message): array
    {
        // Always render with the admin theme's Markdown defaults: this is admin-only functionality,
        // and ADMIN_AREA isn't set to true for api/admin/... requests the way it is for admin page loads.
        $markdown = new FOSSBillingMarkdown($this->di, isAdmin: true);

        $result = [];
        foreach ($this->getSupportTicketMessageHistoryRepository()->findByMessageId((int) $message->getId()) as $history) {
            $data = $history->toApiArray();
            $data['content_html'] = $markdown->convert((string) $data['content']);
            $result[] = $data;
        }

        return $result;
    }

    public function ticketReply(SupportTicket $ticket, \Box\Mod\Staff\Entity\Admin|Client|\FOSSBilling\Core\Identity\Guest $identity, string $content): int
    {
        SupportTicketValidator::validateContent($content);

        $em = $this->di['em'];
        $id = $ticket->getId();
        if ($id === null) {
            throw new InformationException('Ticket not found.');
        }
        $msg = $em->wrapInTransaction(function () use ($em, $ticket, $identity, $content, $id): SupportTicketMessage {
            $db = $em->getConnection();
            if ($db->fetchOne('SELECT status FROM support_ticket WHERE id = ?' . RowLock::suffix($db), [$id]) === false) {
                throw new InformationException('Ticket not found.');
            }
            $em->refresh($ticket);
            if (!$identity instanceof \Box\Mod\Staff\Entity\Admin && !$ticket->canBeReopen()) {
                throw new InformationException('Ticket cannot be reopened.');
            }
            $msg = new SupportTicketMessage();
            $msg->setSupportTicket($ticket);
            if ($identity instanceof \Box\Mod\Staff\Entity\Admin) {
                $msg->setAdminId((int) $identity->getId());
            } elseif ($identity instanceof Client) {
                $msg->setClientId((int) $identity->getId());
            }
            $msg->setContent($content);
            $msg->setIp($this->di['request']->getClientIp());
            $em->persist($msg);
            $em->flush();

            $ticket->setStatus($identity instanceof \Box\Mod\Staff\Entity\Admin ? SupportTicket::STATUS_ONHOLD : SupportTicket::STATUS_OPEN);
            $ticket->setUpdatedAt(new \DateTime());
            $em->flush();

            return $msg;
        });

        $actor = match (true) {
            $identity instanceof \Box\Mod\Staff\Entity\Admin => TicketActorRole::ADMIN,
            $identity instanceof Client => TicketActorRole::CLIENT,
            default => TicketActorRole::GUEST,
        };
        $this->di['event_dispatcher']->dispatch(new AfterTicketRepliedEvent((int) $ticket->getId(), $actor));

        $this->di['logger']->info('Replied to ticket "{ticket_id}"', ['ticket_id' => $ticket->getId()]);

        TicketFiles::attachFromRequest($this->di, (int) $ticket->getId(), $identity instanceof \Box\Mod\Staff\Entity\Admin, (int) $msg->getId());

        return (int) $msg->getId();
    }

    public function ticketCreateForAdmin(int $clientId, Helpdesk $helpdesk, array $data, \Box\Mod\Staff\Entity\Admin $identity): int
    {
        $status = $data['status'] ?? SupportTicket::STATUS_ONHOLD;

        SupportTicketValidator::validateContent($data['content'] ?? null);

        $this->di['event_dispatcher']->dispatch(new BeforeTicketCreateEvent(TicketActorRole::ADMIN, $clientId, $data));

        $em = $this->di['em'];
        $ticket = new SupportTicket();
        $ticket->setClientId($clientId);
        $ticket->setStatus($status);
        $ticket->setSubject($data['subject']);
        $ticket->setSupportHelpdesk($helpdesk);

        // The ticket and its first message are saved together, so a failed
        // message leaves no empty ticket behind.
        $em->wrapInTransaction(function () use ($ticket, $identity, $data): void {
            $em = $this->di['em'];
            $em->persist($ticket);
            $em->flush();

            $msg = new SupportTicketMessage();
            $msg->setAdminId((int) $identity->getId());
            $msg->setSupportTicket($ticket);
            $msg->setContent($data['content']);
            $msg->setIp($this->di['request']->getClientIp());
            $em->persist($msg);
            $em->flush();
        });

        $this->di['event_dispatcher']->dispatch(new AfterTicketOpenedEvent((int) $ticket->getId(), TicketActorRole::ADMIN));

        $this->di['logger']->info('Admin opened new ticket "{ticket_id}"', ['ticket_id' => $ticket->getId()]);

        TicketFiles::attachFromRequest($this->di, (int) $ticket->getId(), true);

        return (int) $ticket->getId();
    }

    public function ticketCreateForGuest(array $data): string
    {
        if (!$this->guestTicketsEnabled()) {
            throw new InformationException("We currently aren't accepting support tickets from unregistered users. Please use another contact method.");
        }

        $data['email'] = \FOSSBilling\Core\Validation\EmailValidator::validateAndSanitizeEmail($data['email']);
        $data['content'] ??= $data['message'] ?? null;

        SupportTicketValidator::validateTicketCreation($data);

        $event_params = $data;
        $event_params['author_role'] = 'guest';
        $event_params['ip'] = $this->di['request']->getClientIp();

        $ticketEvent = $this->di['event_dispatcher']->dispatch(new BeforeGuestTicketCreateEvent(
            input: $event_params,
            status: 'open',
            subject: $data['subject'] ?? null,
            message: $data['content'] ?? null,
        ));

        $helpdesk = isset($data['support_helpdesk_id'])
            ? $this->getHelpdeskRepository()->find((int) $data['support_helpdesk_id'])
            : null;
        if ($helpdesk instanceof Helpdesk && HiddenHelpdesks::isHidden($this->di, (int) $helpdesk->getId())) {
            $helpdesk = null;
        }
        if (!$helpdesk instanceof Helpdesk && !isset($data['support_helpdesk_id'])) {
            // No help desk chosen: the first one clients see, rather than a hidden one.
            $visible = HiddenHelpdesks::visiblePairs($this->di, $this->getHelpdeskRepository()->getPairs());
            $firstId = array_key_first($visible);
            $helpdesk = $firstId !== null ? $this->getHelpdeskRepository()->find($firstId) : null;
        }

        if (!$helpdesk instanceof Helpdesk) {
            throw new \FOSSBilling\Core\Exception\BaseException('Helpdesk invalid');
        }

        SupportTicketValidator::validateContent($ticketEvent->getMessage());

        $em = $this->di['em'];
        $ticket = new SupportTicket();
        $ticket->setAccessHash(bin2hex(random_bytes(random_int(15, 30))));
        $ticket->setSupportHelpdesk($helpdesk);
        $ticket->setPriority(TicketPriority::fromInput($data['priority'] ?? null));
        $ticket->setAuthorName($data['name']);
        $ticket->setAuthorEmail($data['email']);
        $ticket->setSubject($ticketEvent->getSubject());
        $ticket->setStatus($ticketEvent->getStatus());
        // The ticket and its first message are saved together, so a failed
        // message leaves no empty ticket behind.
        $em->wrapInTransaction(function () use ($ticket, $ticketEvent): void {
            $em = $this->di['em'];
            $em->persist($ticket);
            $em->flush();

            $msg = new SupportTicketMessage();
            $msg->setSupportTicket($ticket);
            $msg->setContent($ticketEvent->getMessage());
            $msg->setIp($this->di['request']->getClientIp());
            $em->persist($msg);
            $em->flush();
        });

        $this->di['event_dispatcher']->dispatch(new AfterTicketOpenedEvent((int) $ticket->getId(), TicketActorRole::GUEST));

        $this->di['logger']->info('"{author_email}" opened guest ticket "{ticket_id}"', ['author_email' => $ticket->getAuthorEmail(), 'ticket_id' => $ticket->getId()]);

        TicketFiles::attachFromRequest($this->di, (int) $ticket->getId(), false);

        return $ticket->getAccessHash();
    }

    public function guestTicketsEnabled(): bool
    {
        $extensionService = $this->di['mod_service']('extension');
        $config = $extensionService->getConfig('mod_support');

        $disableGuestTickets = $config['disable_guest_tickets'] ?? $config['disable_public_tickets'] ?? false;

        return !$disableGuestTickets;
    }

    public function canClientSubmitNewTicket(Client $client, array $config): bool
    {
        $hours = $config['wait_hours'];

        $lastTicket = $this->getSupportTicketRepository()->findOneBy(
            ['clientId' => (int) $client->getId()],
            ['createdAt' => 'DESC']
        );
        if (!$lastTicket instanceof SupportTicket) {
            return true;
        }

        $createdAt = $lastTicket->getCreatedAt();
        $createdAtStr = $createdAt?->format('Y-m-d H:i:s');
        $timeSinceLast = $createdAtStr !== null ? round(abs(strtotime($createdAtStr) - strtotime(date('Y-m-d H:i:s'))) / 3600, 0) : 0;

        if ($timeSinceLast < $hours) {
            throw new InformationException(sprintf('You can submit one ticket per %s hours. %s hours left', $hours, $hours - $timeSinceLast));
        }

        return true;
    }

    public function ticketCreateForClient(Client $client, Helpdesk $helpdesk, array $data): int
    {
        SupportTicketValidator::validateTicketCreation($data);

        if (isset($data['rel_id'])) {
            if (filter_var($data['rel_id'], FILTER_VALIDATE_INT) === false) {
                throw new \FOSSBilling\Core\Exception\BaseException('rel_id must be a valid integer, received: :value', [':value' => $data['rel_id']]);
            }
            $rel_id = (int) $data['rel_id'];
        } else {
            $rel_id = null;
        }

        $rel_type = $data['rel_type'] ?? null;

        $rel_task = $data['rel_task'] ?? null;
        $rel_new_value = $data['rel_new_value'] ?? null;
        $rel_status = isset($data['rel_task']) ? SupportTicket::REL_STATUS_PENDING : SupportTicket::REL_STATUS_COMPLETE;

        $order = null;
        if ($rel_id !== null && $rel_type === SupportTicket::REL_TYPE_ORDER) {
            $orderService = $this->di['mod_service']('order');
            $order = $orderService->findForClientById($client, $rel_id);
            if (!$order instanceof Order) {
                throw new \FOSSBilling\Core\Exception\BaseException('You do not have permission to reference this order.');
            }
        }

        if ($rel_task === SupportTicket::REL_TASK_UPGRADE) {
            if (!$order instanceof Order) {
                throw new \FOSSBilling\Core\Exception\BaseException('You must provide both an order ID and a new product ID in order to request an upgrade.');
            }

            if (filter_var($rel_new_value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                throw new \FOSSBilling\Core\Exception\BaseException('rel_new_value must be a valid positive integer product ID, received: :value', [':value' => $rel_new_value]);
            }

            $productService = $this->di['mod_service']('product');
            $productService->assertUpgradeAllowedByIds((int) $order->getProductId(), (int) $rel_new_value);
        }

        if ($rel_id && $rel_type && $rel_task && $this->checkIfTaskAlreadyExists($client, $rel_id, $rel_type, $rel_task)) {
            throw new InformationException('We have already received this request.');
        }

        $mod = $this->di['mod']('support');
        $config = $mod->getConfig();

        if (isset($config['wait_hours']) && is_numeric($config['wait_hours'])) {
            $this->canClientSubmitNewTicket($client, $config);
        }

        $event_params = $data;
        $event_params['author_role'] = 'client';
        $event_params['client_id'] = $client->getId();
        $this->di['event_dispatcher']->dispatch(new BeforeTicketCreateEvent(TicketActorRole::CLIENT, (int) $client->getId(), $event_params));

        $ticket = new SupportTicket();
        $ticket->setClientId((int) $client->getId());
        $ticket->setSubject($data['subject']);
        $ticket->setSupportHelpdesk($helpdesk);
        $ticket->setPriority(TicketPriority::fromInput($data['priority'] ?? null));

        // related task with ticket
        $ticket->setRelId($rel_id);
        $ticket->setRelType($rel_type);
        $ticket->setRelTask($rel_task);
        $ticket->setRelNewValue($rel_new_value);
        $ticket->setRelStatus($rel_status);

        // The ticket and its first message are saved together, so a failed
        // message leaves no empty ticket behind.
        $this->di['em']->wrapInTransaction(function () use ($ticket, $client, $data): void {
            $this->di['em']->persist($ticket);
            $this->di['em']->flush();

            $this->messageCreateForTicket($ticket, $client, $data['content']);
        });

        $this->di['event_dispatcher']->dispatch(new AfterTicketOpenedEvent((int) $ticket->getId(), TicketActorRole::CLIENT));

        if (
            isset($config['autorespond_enable'])
            && $config['autorespond_enable']
            && isset($config['autorespond_message_id'])
            && !empty($config['autorespond_message_id'])
        ) {
            $this->sendAutoresponderCannedReply($ticket, $config['autorespond_message_id']);
        }

        $this->di['logger']->info('Submitted new ticket "{ticket_id}"', ['ticket_id' => $ticket->getId()]);

        TicketFiles::attachFromRequest($this->di, (int) $ticket->getId(), false);

        return (int) $ticket->getId();
    }

    private function sendAutoresponderCannedReply(SupportTicket $ticket, $cannedId): void
    {
        try {
            $cannedResponse = $this->getCannedResponseRepository()->find((int) $cannedId);

            if (!$cannedResponse instanceof CannedResponse) {
                $this->di['logger']->warning('Autoresponder: canned response #{canned_id} not found, skipping reply for ticket #{ticket_id}', ['canned_id' => $cannedId, 'ticket_id' => $ticket->getId()]);

                return;
            }

            $canned = $cannedResponse->toApiArray();

            $staffService = $this->di['mod_service']('staff');
            $admin = $staffService->getCronAdmin();

            if (isset($canned['content']) && $admin instanceof \Box\Mod\Staff\Entity\Admin) {
                $this->ticketReply($ticket, $admin, $canned['content']);
            }
        } catch (\Exception $e) {
            $this->di['logger']->error('Autoresponder canned reply failed.', ['exception' => $e]);
        }
    }

    /**
     * @param Client $identity
     */
    public function messageCreateForTicket(SupportTicket $ticket, \Box\Mod\Staff\Entity\Admin|Client $identity, string $content): int
    {
        SupportTicketValidator::validateContent($content);

        $em = $this->di['em'];
        $msg = $em->wrapInTransaction(function () use ($em, $ticket, $identity, $content): SupportTicketMessage {
            $this->lockTicketForWrite((int) $ticket->getId());
            $msg = new SupportTicketMessage();
            $msg->setSupportTicket($ticket);
            if ($identity instanceof \Box\Mod\Staff\Entity\Admin) {
                $msg->setAdminId((int) $identity->getId());
            } elseif ($identity instanceof Client) {
                $msg->setClientId((int) $identity->getId());
            } else {
                throw new \FOSSBilling\Core\Exception\BaseException('Identity is invalid');
            }
            $msg->setContent($content);
            $msg->setIp($this->di['request']->getClientIp());
            $em->persist($msg);
            $em->flush();

            return $msg;
        });

        return (int) $msg->getId();
    }

    public function findOneByHash(string $hash): SupportTicket
    {
        $guestTicket = $this->getSupportTicketRepository()->findOneByAccessHash($hash);
        if (!$guestTicket instanceof SupportTicket) {
            throw new \FOSSBilling\Core\Exception\BaseException('Guest ticket not found');
        }

        return $guestTicket;
    }

    public function helpdeskUpdate(Helpdesk $model, array $data): bool
    {
        if (array_key_exists('name', $data)) {
            $model->setName($data['name']);
        }
        if (array_key_exists('email', $data)) {
            $model->setEmail($data['email']);
        }
        if (array_key_exists('can_reopen', $data)) {
            $model->setCanReopen($data['can_reopen']);
        }
        if (array_key_exists('close_after', $data)) {
            $model->setCloseAfter($data['close_after']);
        }
        if (array_key_exists('signature', $data)) {
            $model->setSignature($data['signature']);
        }
        if (array_key_exists('hidden', $data)) {
            HiddenHelpdesks::save($this->di, (int) $model->getId(), $data['hidden']);
        }
        if (array_key_exists('feedback_request', $data)) {
            TicketFeedback::saveHelpdesk($this->di, (int) $model->getId(), $data['feedback_request']);
        }

        $this->di['em']->flush();

        $this->di['logger']->info('Updated helpdesk #{model_id}', ['model_id' => $model->getId()]);

        return true;
    }

    public function helpdeskCreate(array $data): int
    {
        $model = (new Helpdesk())
            ->setName($data['name'])
            ->setEmail($data['email'] ?? null)
            ->setCanReopen($data['can_reopen'] ?? null)
            // Doctrine always includes a mapped column in its INSERT, so an omitted close_after
            // would otherwise write a literal NULL and bypass the column's own DB-level default -
            // silently excluding this helpdesk's tickets from ever being auto-closed
            // (findExpiredOnHold()'s SqlExpr::addHours(..., sh.close_after) propagates NULL, same
            // as MySQL's DATE_ADD(..., INTERVAL NULL HOUR)).
            ->setCloseAfter($data['close_after'] ?? Helpdesk::DEFAULT_CLOSE_AFTER_HOURS)
            ->setSignature($data['signature'] ?? null);

        $this->di['em']->persist($model);
        $this->di['em']->flush();

        $id = (int) $model->getId();

        $this->di['logger']->info('Created helpdesk #{id}', ['id' => $id]);

        return $id;
    }

    public function cannedRm(CannedResponse $model): bool
    {
        $id = $model->getId();

        $this->di['em']->remove($model);
        $this->di['em']->flush();

        $this->di['logger']->info('Deleted canned response #{id}', ['id' => $id]);

        return true;
    }

    public function cannedCategoryRm(CannedResponseCategory $model): bool
    {
        $id = $model->getId();
        $responsesCount = $id !== null ? $this->getCannedResponseRepository()->countByCategoryId($id) : 0;

        if ($responsesCount > 0) {
            throw new InformationException('Cannot remove category which has canned responses');
        }

        $this->di['em']->remove($model);
        $this->di['em']->flush();
        $this->di['logger']->info('Deleted canned response category #{id}', ['id' => $id]);

        return true;
    }

    public function cannedCreate(string $title, int $categoryId, ?string $content = null): int
    {
        $category = $this->getCannedResponseCategoryRepository()->find($categoryId);
        if (!$category instanceof CannedResponseCategory) {
            throw new \FOSSBilling\Core\Exception\BaseException('Canned category not found');
        }

        $model = (new CannedResponse())
            ->setCategory($category)
            ->setTitle($title)
            ->setContent($content);

        $this->di['em']->persist($model);
        $this->di['em']->flush();

        $id = (int) $model->getId();

        $this->di['logger']->info('Created new canned response #{id}', ['id' => $id]);

        return $id;
    }

    public function cannedUpdate(CannedResponse $model, array $data): bool
    {
        if (isset($data['category_id'])) {
            $category = $this->getCannedResponseCategoryRepository()->find((int) $data['category_id']);
            if (!$category instanceof CannedResponseCategory) {
                throw new \FOSSBilling\Core\Exception\BaseException('Canned category not found');
            }

            $model->setCategory($category);
        }

        if (isset($data['title'])) {
            $model->setTitle($data['title']);
        }

        if (array_key_exists('content', $data)) {
            $model->setContent($data['content']);
        }

        $this->di['em']->flush();

        $this->di['logger']->info('Updated canned response #{model_id}', ['model_id' => $model->getId()]);

        return true;
    }

    public function cannedCategoryCreate(string $title): int
    {
        $model = (new CannedResponseCategory())
            ->setTitle($title);

        $this->di['em']->persist($model);
        $this->di['em']->flush();

        $id = (int) $model->getId();

        $this->di['logger']->info('Created new canned response category #{id}', ['id' => $id]);

        return $id;
    }

    public function cannedCategoryUpdate(CannedResponseCategory $model, ?string $title = null): bool
    {
        if (isset($title)) {
            $model->setTitle($title);
        }

        $this->di['em']->flush();

        $this->di['logger']->info('Updated canned response category #{model_id}', ['model_id' => $model->getId()]);

        return true;
    }

    public function noteCreate(SupportTicket $ticket, \Box\Mod\Staff\Entity\Admin $identity, string $note): int
    {
        $em = $this->di['em'];
        $model = $em->wrapInTransaction(function () use ($em, $ticket, $identity, $note): SupportTicketNote {
            $this->lockTicketForWrite((int) $ticket->getId());
            $model = new SupportTicketNote();
            $model->setSupportTicket($ticket);
            $model->setAdminId((int) $identity->getId());
            $model->setNote($note);
            $em->persist($model);
            $em->flush();

            return $model;
        });

        $id = (int) $model->getId();
        $this->di['logger']->info('Added note to ticket #{id}', ['id' => $id]);

        return $id;
    }

    /** Serialize child writes with ticket deletion and merge, including legacy schemas without FKs. */
    private function lockTicketForWrite(int $id): void
    {
        $db = $this->di['em']->getConnection();
        if ($db->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\SQLitePlatform) {
            $db->executeStatement('UPDATE support_ticket SET id = id WHERE id = ?', [$id]);
        }
        if ($db->fetchOne('SELECT id FROM support_ticket WHERE id = ?' . RowLock::suffix($db), [$id]) === false) {
            throw new InformationException('Ticket not found.');
        }
    }

    public function ticketTaskComplete(SupportTicket $model): bool
    {
        $model->markTaskComplete();
        $this->di['em']->flush();

        $this->di['logger']->info('Marked ticket #{model_id} task as complete', ['model_id' => $model->getId()]);

        return true;
    }

    /*
     * Knowledge Base Functions.
     */

    public function kbEnabled(): bool
    {
        $extensionService = $this->di['mod_service']('extension');
        $config = $extensionService->getConfig('mod_support');

        return \FOSSBilling\Core\Utils\Normalizer::normalizeBoolean($config['kb_enable'] ?? true, true);
    }

    public function kbArticleViewsEnabled(): bool
    {
        $extensionService = $this->di['mod_service']('extension');
        $config = $extensionService->getConfig('mod_support');

        return \FOSSBilling\Core\Utils\Normalizer::normalizeBoolean($config['kb_article_views_enable'] ?? true, true);
    }

    public function kbSuggestionsEnabled(string $area): bool
    {
        if (!$this->kbEnabled()) {
            return false;
        }

        $key = match ($area) {
            'contact' => 'kb_suggestions_contact',
            'ticket' => 'kb_suggestions_ticket',
            default => null,
        };

        if ($key === null) {
            return false;
        }

        $extensionService = $this->di['mod_service']('extension');
        $config = $extensionService->getConfig('mod_support');

        return \FOSSBilling\Core\Utils\Normalizer::normalizeBoolean($config[$key] ?? false);
    }

    public function kbRm(KbArticle $model): void
    {
        $id = $model->getId();
        $this->di['em']->remove($model);
        $this->di['em']->flush();
        $this->di['logger']->info('Deleted Knowledge Base article #{id}', ['id' => $id]);
    }

    public function kbCreateArticle(int $articleCategoryId, string $title, ?string $status = null, ?string $content = null): int
    {
        $status = $this->normalizeKbArticleStatus($status ?? KbArticle::DRAFT);
        $category = $this->getKbArticleCategoryRepository()->find($articleCategoryId);
        if (!$category instanceof KbArticleCategory) {
            throw new \FOSSBilling\Core\Exception\BaseException('Knowledge Base category not found');
        }

        $model = (new KbArticle())
            ->setCategory($category)
            ->setTitle($title)
            ->setSlug(\FOSSBilling\Core\Utils\Str::slug($title))
            ->setStatus($status)
            ->setContent($content);

        $this->di['em']->persist($model);
        $this->di['em']->flush();

        $id = (int) $model->getId();
        $this->di['logger']->info('Created new knowledge base article #{id}', ['id' => $id]);

        return $id;
    }

    public function kbUpdateArticle(int $id, ?int $articleCategoryId = null, ?string $title = null, ?string $slug = null, ?string $status = null, ?string $content = null, ?int $views = null): bool
    {
        $status = $status !== null ? $this->normalizeKbArticleStatus($status) : null;
        $model = $this->getKbArticleRepository()->find($id);

        if (!$model instanceof KbArticle) {
            throw new \FOSSBilling\Core\Exception\BaseException('Article not found');
        }

        if (isset($articleCategoryId)) {
            $category = $this->getKbArticleCategoryRepository()->find($articleCategoryId);
            if (!$category instanceof KbArticleCategory) {
                throw new \FOSSBilling\Core\Exception\BaseException('Knowledge Base category not found');
            }

            $model->setCategory($category);
        }

        if (isset($title)) {
            $model->setTitle($title);
        }

        if (isset($slug)) {
            $model->setSlug($slug);
        }

        if (isset($status)) {
            $model->setStatus($status);
        }

        if (isset($content)) {
            $model->setContent($content);
        }

        if (isset($views)) {
            $model->setViews($views);
        }

        $this->di['em']->flush();

        $this->di['logger']->info('Updated knowledge base article #{id}', ['id' => $id]);

        return true;
    }

    private function normalizeKbArticleStatus(string $status): string
    {
        $status = strtolower(trim($status));
        if (!in_array($status, [KbArticle::ACTIVE, KbArticle::DRAFT], true)) {
            throw new \FOSSBilling\Core\Exception\BaseException('Invalid knowledge base article status: :status', [':status' => $status]);
        }

        return $status;
    }

    public function kbCategoryRm(KbArticleCategory $model): bool
    {
        $id = $model->getId();
        $articlesCount = $id !== null ? $this->getKbArticleRepository()->countByCategoryId($id) : 0;

        if ($articlesCount > 0) {
            throw new InformationException('Cannot remove category which has articles');
        }

        HiddenKbCategories::forget($this->di, (int) $id);
        $this->di['em']->remove($model);
        $this->di['em']->flush();

        $this->di['logger']->info('Deleted knowledge base category #{id}', ['id' => $id]);

        return true;
    }

    public function kbCreateCategory(string $title, ?string $description = null): int
    {
        $model = (new KbArticleCategory())
            ->setTitle($title)
            ->setDescription($description)
            ->setSlug(\FOSSBilling\Core\Utils\Str::slug($title));

        $this->di['em']->persist($model);
        $this->di['em']->flush();

        $id = (int) $model->getId();
        $this->di['logger']->info('Created new knowledge base category #{id}', ['id' => $id]);

        return $id;
    }

    public function kbUpdateCategory(KbArticleCategory $model, ?string $title = null, ?string $slug = null, ?string $description = null): bool
    {
        if (isset($title)) {
            $model->setTitle($title);
        }

        if (isset($slug)) {
            $model->setSlug($slug);
        }

        if (isset($description)) {
            $model->setDescription($description);
        }

        $this->di['em']->flush();

        $this->di['logger']->info('Updated Knowledge Base category #{model_id}', ['model_id' => $model->getId()]);

        return true;
    }
}
