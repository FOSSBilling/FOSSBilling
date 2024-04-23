<?php

/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Support;

use FOSSBilling\InformationException;

class Service implements \FOSSBilling\InjectionAwareInterface
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

    public static function onAfterClientOpenTicket(\Box_Event $event)
    {
        $di = $event->getDi();
        $params = $event->getParameters();
        $supportService = $di['mod_service']('support');
        $emailService = $di['mod_service']('email');

        try {
            $ticketObj = $supportService->getTicketById($params['id']);
            $identity = $di['loggedin_client'];
            $ticketArr = $supportService->toApiArray($ticketObj, true, $identity);

            $email = [];
            $email['to_client'] = $ticketObj->client_id;
            $email['code'] = 'mod_support_ticket_open';
            $email['ticket'] = $ticketArr;
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            error_log($exc->getMessage());
        }
    }

    public static function onAfterAdminOpenTicket(\Box_Event $event)
    {
        $di = $event->getDi();
        $supportService = $di['mod_service']('support');
        $emailService = $di['mod_service']('email');
        $params = $event->getParameters();

        try {
            $ticketObj = $supportService->getTicketById($params['id']);
            $identity = $di['loggedin_admin'];
            $ticketArr = $supportService->toApiArray($ticketObj, true, $identity);

            $email = [];
            $email['to_client'] = $ticketObj->client_id;
            $email['code'] = 'mod_support_ticket_staff_open';
            $email['ticket'] = $ticketArr;
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            error_log($exc->getMessage());
        }
    }

    public static function onAfterAdminCloseTicket(\Box_Event $event)
    {
        $di = $event->getDi();
        $supportService = $di['mod_service']('support');
        $emailService = $di['mod_service']('email');
        $params = $event->getParameters();

        try {
            $identity = $di['loggedin_admin'];
            $ticketObj = $supportService->getTicketById($params['id']);
            $ticketArr = $supportService->toApiArray($ticketObj, true, $identity);

            $email = [];
            $email['to_client'] = $ticketObj->client_id;
            $email['code'] = 'mod_support_ticket_staff_close';
            $email['ticket'] = $ticketArr;
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            error_log($exc->getMessage());
        }
    }

    public static function onAfterAdminReplyTicket(\Box_Event $event)
    {
        $di = $event->getDi();
        $supportService = $di['mod_service']('support');
        $emailService = $di['mod_service']('email');
        $params = $event->getParameters();

        try {
            $ticketObj = $supportService->getTicketById($params['id']);
            $identity = $di['loggedin_admin'];
            $ticketArr = $supportService->toApiArray($ticketObj, true, $identity);

            $email = [];
            $email['to_client'] = $ticketObj->client_id;
            $email['code'] = 'mod_support_ticket_staff_reply';
            $email['ticket'] = $ticketArr;
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            error_log($exc->getMessage());
        }
    }

    public static function onAfterGuestPublicTicketOpen(\Box_Event $event)
    {
        $di = $event->getDi();
        $supportService = $di['mod_service']('support');
        $emailService = $di['mod_service']('email');
        $params = $event->getParameters();

        try {
            $ticketObj = $supportService->getPublicTicketById($params['id']);
            $ticketArr = $supportService->publicToApiArray($ticketObj, true);

            $email = [];
            $email['to'] = $ticketArr['author_email'];
            $email['to_name'] = $ticketArr['author_name'];
            $email['code'] = 'mod_support_pticket_open';
            $email['ticket'] = $ticketArr;
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            error_log($exc->getMessage());
        }
    }

    public static function onAfterAdminPublicTicketOpen(\Box_Event $event)
    {
        $di = $event->getDi();
        $supportService = $di['mod_service']('support');
        $emailService = $di['mod_service']('email');
        $params = $event->getParameters();

        try {
            $ticketObj = $supportService->getPublicTicketById($params['id']);
            $identity = $di['loggedin_admin'];
            $ticketArr = $supportService->publicToApiArray($ticketObj, true, $identity);

            $email = [];
            $email['to'] = $ticketArr['author_email'];
            $email['to_name'] = $ticketArr['author_name'];
            $email['code'] = 'mod_support_pticket_staff_open';
            $email['ticket'] = $ticketArr;
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            error_log($exc->getMessage());
        }
    }

    public static function onAfterAdminPublicTicketReply(\Box_Event $event)
    {
        $di = $event->getDi();
        $supportService = $di['mod_service']('support');
        $emailService = $di['mod_service']('email');
        $params = $event->getParameters();

        try {
            $ticketObj = $supportService->getPublicTicketById($params['id']);
            $identity = $di['loggedin_admin'];
            $ticketArr = $supportService->publicToApiArray($ticketObj, true, $identity);

            $email = [];
            $email['to'] = $ticketArr['author_email'];
            $email['to_name'] = $ticketArr['author_name'];
            $email['code'] = 'mod_support_pticket_staff_reply';
            $email['ticket'] = $ticketArr;
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            error_log($exc->getMessage());
        }
    }

    public static function onAfterAdminPublicTicketClose(\Box_Event $event)
    {
        $di = $event->getDi();
        $supportService = $di['mod_service']('support');
        $emailService = $di['mod_service']('email');
        $params = $event->getParameters();

        try {
            $ticketObj = $supportService->getPublicTicketById($params['id']);
            $identity = $di['loggedin_admin'];
            $ticketArr = $supportService->publicToApiArray($ticketObj, true, $identity);

            $email = [];
            $email['to'] = $ticketArr['author_email'];
            $email['to_name'] = $ticketArr['author_name'];
            $email['code'] = 'mod_support_pticket_staff_close';
            $email['ticket'] = $ticketArr;
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            error_log($exc->getMessage());
        }
    }

    public function getTicketById($id)
    {
        return $this->di['db']->getExistingModelById('SupportTicket', $id, 'Ticket not found');
    }

    public function getPublicTicketById($id)
    {
        return $this->di['db']->getExistingModelById('SupportPTicket', $id, 'Ticket not found');
    }

    /**
     * Return array of ticket statuses.
     */
    public function getStatuses()
    {
        return [
            \Model_SupportTicket::OPENED => 'Open',
            \Model_SupportTicket::ONHOLD => 'On hold',
            \Model_SupportTicket::CLOSED => 'Closed',
        ];
    }

    /**
     * Find ticket for client.
     *
     * @param int $id
     *
     * @return \Model_SupportTicket
     */
    public function findOneByClient(\Model_Client $c, $id)
    {
        $bindings = [
            ':id' => $id,
            ':client_id' => $c->id,
        ];

        $ticket = $this->di['db']->findOne('SupportTicket', 'id = :id AND client_id = :client_id', $bindings);

        if (!$ticket instanceof \Model_SupportTicket) {
            throw new \FOSSBilling\Exception('Ticket not found');
        }

        return $ticket;
    }

    public function getSearchQuery($data)
    {
        $query = 'SELECT st.*
                FROM support_ticket st
                JOIN support_ticket_message stm ON stm.support_ticket_id = st.id
                LEFT JOIN client c ON st.client_id = c.id';

        $search = $data['search'] ?? null;
        $id = $data['id'] ?? null;
        $status = $data['status'] ?? null;
        $client = $data['client'] ?? null;
        $client_id = $data['client_id'] ?? null;
        $order_id = $data['order_id'] ?? null;
        $subject = $data['subject'] ?? null;
        $content = $data['content'] ?? null;
        $helpdesk = $data['support_helpdesk_id'] ?? null;
        $created_at = $data['created_at'] ?? null;
        $date_from = $data['date_from'] ?? null;
        $date_to = $data['date_to'] ?? null;
        $priority = $data['priority'] ?? null;

        $where = [];
        $bindings = [];

        if ($id) {
            $where[] = 'st.id = :ticket_id';
            $bindings[':ticket_id'] = $id;
        }

        if ($priority) {
            $where[] = 'st.priority = :priority';
            $bindings[':priority'] = $priority;
        }

        if ($helpdesk) {
            $where[] = 'st.support_helpdesk_id = :support_helpdesk_id';
            $bindings[':support_helpdesk_id'] = $helpdesk;
        }

        if ($client) {
            $where[] = 'c.first_name LIKE :first_name';
            $where[] = 'c.last_name LIKE :last_name';
            $bindings[':first_name'] = '%' . $client . '%';
            $bindings[':last_name'] = '%' . $client . '%';
        }

        if ($client_id) {
            $where[] = 'c.id = :client_id';
            $bindings[':client_id'] = $client_id;
        }

        if ($content) {
            $where[] = 'stm.content LIKE :content';
            $bindings[':content'] = '%' . $content . '%';
        }

        if ($subject) {
            $where[] = 'st.subject LIKE :subject';
            $bindings[':subject'] = '%' . $subject . '%';
        }

        if ($status) {
            $where[] = 'st.status = :status';
            $bindings[':status'] = $status;
        }

        if ($order_id) {
            $where[] = 'st.rel_type = :rel_type AND st.rel_id = :rel_id';
            $bindings[':rel_type'] = \Model_SupportTicket::REL_TYPE_ORDER;
            $bindings[':rel_id'] = $order_id;
        }

        if ($created_at) {
            $where[] = "DATE_FORMAT(st.created_at, '%Y-%m-%d') = :created_at";
            $bindings[':created_at'] = date('Y-m-d', strtotime($created_at));
        }

        if ($date_from) {
            $where[] = 'UNIX_TIMESTAMP(st.created_at) >= :date_from';
            $bindings[':date_from'] = strtotime($date_from);
        }

        if ($date_to) {
            $where[] = 'UNIX_TIMESTAMP(st.created_at) <= :date_to';
            $bindings[':date_to'] = strtotime($date_to);
        }
        // smartSearch
        if ($search) {
            if (is_numeric($search)) {
                $where[] = 'st.id = :ticket_id';
                $bindings[':ticket_id'] = $search;
            } else {
                $search = '%' . $search . '%';
                $where[] = '(stm.content LIKE :content OR st.subject LIKE :subject)';
                $bindings[':content'] = $search;
                $bindings[':subject'] = $search;
            }
        }

        if (!empty($where)) {
            $query = $query . ' WHERE ' . implode(' AND ', $where);
        }

        $query .= ' GROUP BY st.id ORDER BY st.priority ASC, st.id DESC';

        return [$query, $bindings];
    }

    public function counter()
    {
        $query = 'SELECT status, COUNT(id) as counter
                    FROM support_ticket
                    GROUP BY status';

        $data = $this->di['db']->getAssoc($query);

        return [
            'total' => array_sum($data),
            \Model_SupportTicket::OPENED => $data[\Model_SupportTicket::OPENED] ?? 0,
            \Model_SupportTicket::CLOSED => $data[\Model_SupportTicket::CLOSED] ?? 0,
            \Model_SupportTicket::ONHOLD => $data[\Model_SupportTicket::ONHOLD] ?? 0,
        ];
    }

    public function getLatest()
    {
        return $this->di['db']->find('SupportTicket', 'ORDER BY id DESC LIMIT 10');
    }

    public function getExpired()
    {
        $bindings = [
            ':status' => \Model_SupportTicket::ONHOLD,
        ];

        $sql = 'SELECT st.*
                FROM support_ticket as st
                    LEFT JOIN support_helpdesk sh ON sh.id = st.support_helpdesk_id
                WHERE st.status = :status
                AND DATE_ADD(st.updated_at, INTERVAL sh.close_after HOUR) < NOW()
                ORDER BY st.id ASC';

        return $this->di['db']->getAll($sql, $bindings);
    }

    public function countByStatus($status)
    {
        $query = "SELECT COUNT(m.id) as counter FROM support_ticket
                WHERE 'status' = :'status' GROUP BY 'status' LIMIT 1";

        return $this->di['db']->getCell($query, [':status' => $status]);
    }

    public function getActiveTicketsCountForOrder(\Model_ClientOrder $model)
    {
        $query = "SELECT COUNT(id) as counter FROM support_ticket
                WHERE rel_id = :order_id
                AND rel_type = 'order'
                AND (status = :status1 OR status = :status2)";

        $bindings = [
            ':order_id' => $model->id,
            ':status1' => \Model_SupportTicket::OPENED,
            ':status2' => \Model_SupportTicket::ONHOLD,
        ];

        return $this->di['db']->getCell($query, $bindings);
    }

    public function checkIfTaskAlreadyExists(\Model_Client $client, $rel_id, $rel_type, $rel_task)
    {
        $bindings = [
            ':client_id' => $client->id,
            ':rel_id' => $rel_id,
            ':rel_type' => $rel_type,
            ':rel_task' => $rel_task,
            ':rel_status' => \Model_SupportTicket::REL_STATUS_PENDING,
        ];

        $ticket = $this->di['db']->findOne(
            'SupportTicket',
            'client_id = :client_id
            AND rel_id = :rel_id
            AND rel_type = :rel_type
            AND rel_task = :rel_task
            AND rel_status = :rel_status',
            $bindings
        );

        return $ticket instanceof \Model_SupportTicket;
    }

    public function closeTicket(\Model_SupportTicket $ticket, $identity)
    {
        $ticket->status = \Model_SupportTicket::CLOSED;
        $ticket->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($ticket);

        if ($identity instanceof \Model_Admin) {
            $this->di['events_manager']->fire(['event' => 'onAfterAdminCloseTicket', 'params' => ['id' => $ticket->id]]);
        } elseif ($identity instanceof \Model_Client) {
            $this->di['events_manager']->fire(['event' => 'onAfterClientCloseTicket', 'params' => ['id' => $ticket->id]]);
        }

        $this->di['logger']->info('Closed ticket "%s"', $ticket->id);

        return true;
    }

    public function autoClose(\Model_SupportTicket $model)
    {
        $model->status = \Model_SupportTicket::CLOSED;
        $model->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($model);
        $this->di['logger']->info('Ticket %s was closed', $model->id);

        return true;
    }

    public function canBeReopened(\Model_SupportTicket $model)
    {
        if ($model->status != \Model_SupportTicket::CLOSED) {
            return true;
        }

        $helpdesk = $this->di['db']->getExistingModelById('SupportHelpdesk', $model->support_helpdesk_id);

        return (bool) $helpdesk->can_reopen;
    }

    /**
     * @return mixed[]
     */
    private function _getRelDetails(\Model_SupportTicket $model): array
    {
        if (!$model->rel_type || !$model->rel_id) {
            return [];
        }

        $result = [
            'id' => $model->rel_id,
            'type' => $model->rel_type,
            'task' => $model->rel_task,
            'new_value' => $model->rel_new_value,
            'status' => $model->rel_status,
        ];

        $client = $this->di['db']->load('Client', $model->client_id);

        if ($model->rel_type == \Model_SupportTicket::REL_TYPE_ORDER) {
            $orderService = $this->di['mod_service']('order');
            $o = $orderService->findForClientById($client, $model->rel_id);
            if ($o instanceof \Model_ClientOrder) {
                $result['order'] = $orderService->toApiArray($o, false);
            }
        }

        return $result;
    }

    public function rmByClient(\Model_Client $client)
    {
        $clientTickets = $this->di['db']->find('SupportTicket', 'client_id = :client_id', [':client_id' => $client->id]);
        foreach ($clientTickets as $ticket) {
            $this->di['db']->trash($ticket);
        }
    }

    public function rm(\Model_SupportTicket $model)
    {
        $supportTicketNotes = $this->di['db']->find('SupportTicketNote', 'support_ticket_id = :support_ticket_id', [':support_ticket_id' => $model->id]);
        foreach ($supportTicketNotes as $note) {
            $this->di['db']->trash($note);
        }

        $supportTicketMessages = $this->di['db']->find('SupportTicketMessage', 'support_ticket_id = :support_ticket_id', [':support_ticket_id' => $model->id]);
        foreach ($supportTicketMessages as $message) {
            $this->di['db']->trash($message);
        }

        $id = $model->id;

        $this->di['db']->trash($model);

        $this->di['logger']->info('Removed ticket "%s"', $id);

        return true;
    }

    public function toApiArray(\Model_SupportTicket $model, $deep = true, $identity = null)
    {
        $firstSupportTicketMessage = $this->di['db']->findOne('SupportTicketMessage', 'support_ticket_id = :support_ticket_id ORDER by id ASC LIMIT 1', [':support_ticket_id' => $model->id]);
        $supportHelpdesk = $this->di['db']->load('SupportHelpdesk', $model->support_helpdesk_id);

        $data = $this->di['db']->toArray($model);
        $data['replies'] = $this->messageGetRepliesCount($model);
        $data['first'] = $this->messageToApiArray($firstSupportTicketMessage);
        $data['helpdesk'] = $this->helpdeskToApiArray($supportHelpdesk);
        $data['client'] = $this->getClientApiArrayForTicket($model);

        if ($deep) {
            $messages = $this->messageGetTicketMessages($model);
            foreach ($messages as $msg) {
                $data['messages'][] = $this->messageToApiArray($msg);
            }
        }

        if ($identity instanceof \Model_Admin) {
            $data['rel'] = $this->_getRelDetails($model);
            $data['priority'] = $model->priority;
            $supportTicketNotes = $this->di['db']->find('SupportTicketNote', 'support_ticket_id = :support_ticket_id', [':support_ticket_id' => $model->id]);

            foreach ($supportTicketNotes as $note) {
                $data['notes'][] = $this->noteToApiArray($note);
            }
        }

        return $data;
    }

    public function getClientApiArrayForTicket(\Model_SupportTicket $ticket)
    {
        $client = $this->di['db']->load('Client', $ticket->client_id);

        if ($client instanceof \Model_Client) {
            $clientService = $this->di['mod_service']('client');

            return $clientService->toApiArray($client);
        } else {
            error_log('Missing client for ticket ' . $ticket->id);

            return [];
        }
    }

    public function noteGetAuthorDetails(\Model_SupportTicketNote $model)
    {
        $admin = $this->di['db']->load('Admin', $model->admin_id);

        return [
            'name' => $admin->getFullName(),
            'email' => $admin->email,
        ];
    }

    public function noteRm(\Model_SupportTicketNote $model)
    {
        $id = $model->id;
        $this->di['db']->trash($model);

        $this->di['logger']->info('Removed note #%s', $id);

        return true;
    }

    public function noteToApiArray(\Model_SupportTicketNote $model, $deep = false, $identity = null)
    {
        $data = $this->di['db']->toArray($model);
        $data['author'] = $this->noteGetAuthorDetails($model);

        return $data;
    }

    public function helpdeskGetSearchQuery($data)
    {
        $query = 'SELECT * FROM support_helpdesk';

        $search = $data['search'] ?? null;

        $where = [];
        $bindings = [];

        if ($search) {
            $search = '%' . $search . '%';
            $where[] = '(name LIKE :name OR email LIKE :email OR signature LIKE :signature)';
            $bindings[':name'] = $search;
            $bindings[':email'] = $search;
            $bindings[':signature'] = $search;
        }

        if (!empty($where)) {
            $query = $query . ' WHERE ' . implode(' AND ', $where);
        }
        $query .= ' ORDER BY id DESC';

        return [$query, $bindings];
    }

    public function helpdeskGetPairs()
    {
        return $this->di['db']->getAssoc('SELECT id, name FROM support_helpdesk');
    }

    public function helpdeskRm(\Model_SupportHelpdesk $model)
    {
        $id = $model->id;

        $tickets = $this->di['db']->find('SupportTicket', 'support_helpdesk_id = :support_helpdesk_id', [':support_helpdesk_id' => $model->id]);
        if ((is_countable($tickets) ? count($tickets) : 0) > 0) {
            throw new InformationException('Cannot remove helpdesk which has tickets');
        }
        $this->di['db']->trash($model);
        $this->di['logger']->info('Deleted helpdesk #%s', $id);

        return true;
    }

    public function helpdeskToApiArray(\Model_SupportHelpdesk $model)
    {
        return $this->di['db']->toArray($model);
    }

    public function messageGetTicketMessages(\Model_SupportTicket $model)
    {
        return $this->di['db']->find('supportTicketMessage', 'support_ticket_id = :support_ticket_id ORDER BY id ASC', [':support_ticket_id' => $model->id]);
    }

    public function messageGetRepliesCount(\Model_SupportTicket $model)
    {
        $query = 'SELECT COUNT(id) as counter
                    FROM support_ticket_message
                    WHERE support_ticket_id = :support_ticket_id
                    GROUP BY support_ticket_id';

        $bindings = [
            ':support_ticket_id' => $model->id,
        ];

        return $this->di['db']->getCell($query, $bindings);
    }

    public function messageGetAuthorDetails(\Model_SupportTicketMessage $model)
    {
        if ($model->admin_id) {
            $author = $this->di['db']->load('Admin', $model->admin_id);
        } else {
            $author = $this->di['db']->load('Client', $model->client_id);
        }

        if (!$author) {
            return [];
        }

        return [
            'name' => $author->getFullName(),
            'email' => $author->email,
        ];
    }

    public function messageToApiArray(\Model_SupportTicketMessage $model)
    {
        $data = $this->di['db']->toArray($model);
        $data['author'] = $this->messageGetAuthorDetails($model);

        return $data;
    }

    public function ticketUpdate(\Model_SupportTicket $model, $data)
    {
        $model->support_helpdesk_id = $data['support_helpdesk_id'] ?? $model->support_helpdesk_id;
        $model->status = $data['status'] ?? $model->status;
        $model->subject = $data['subject'] ?? $model->subject;
        $model->priority = $data['priority'] ?? $model->priority;
        $model->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($model);

        $this->di['logger']->info('Updated ticket #%s', $model->id);

        return true;
    }

    public function ticketMessageUpdate(\Model_SupportTicketMessage $model, $content)
    {
        $model->content = $content;
        $model->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($model);

        return true;
    }

    /**
     * @param \Model_Admin $identity
     */
    public function ticketReply(\Model_SupportTicket $ticket, $identity, $content)
    {
        $msg = $this->di['db']->dispense('SupportTicketMessage');
        $msg->support_ticket_id = $ticket->id;
        if ($identity instanceof \Model_Admin) {
            $msg->admin_id = $identity->id;
        } elseif ($identity instanceof \Model_Client) {
            $msg->client_id = $identity->id;
        }
        $msg->content = $content;
        $msg->ip = $this->di['request']->getClientAddress();
        $msg->created_at = date('Y-m-d H:i:s');
        $msg->updated_at = date('Y-m-d H:i:s');
        $msgId = $this->di['db']->store($msg);

        if ($identity instanceof \Model_Admin) {
            $ticket->status = \Model_SupportTicket::ONHOLD;
        } elseif ($identity instanceof \Model_Client) {
            $ticket->status = \Model_SupportTicket::OPENED;
        }

        $ticket->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($ticket);

        if ($identity instanceof \Model_Admin) {
            $this->di['events_manager']->fire(['event' => 'onAfterAdminReplyTicket', 'params' => ['id' => $ticket->id]]);
        } elseif ($identity instanceof \Model_Client) {
            $this->di['events_manager']->fire(['event' => 'onAfterClientReplyTicket', 'params' => ['id' => $ticket->id]]);
        }

        $this->di['logger']->info('Replied to ticket "%s"', $ticket->id);

        return $msgId;
    }

    public function ticketCreateForAdmin(\Model_Client $client, \Model_SupportHelpdesk $helpdesk, $data, \Model_Admin $identity)
    {
        $status = $data['status'] ?? \Model_SupportTicket::ONHOLD;

        $this->di['events_manager']->fire(['event' => 'onBeforeAdminOpenTicket', 'params' => $data]);

        $ticket = $this->di['db']->dispense('SupportTicket');
        $ticket->client_id = $client->id;
        $ticket->status = $status;
        $ticket->subject = $data['subject'];
        $ticket->support_helpdesk_id = $helpdesk->id;
        $ticket->created_at = date('Y-m-d H:i:s');
        $ticket->updated_at = date('Y-m-d H:i:s');
        $ticketId = $this->di['db']->store($ticket);

        $msg = $this->di['db']->dispense('SupportTicketMessage');
        $msg->admin_id = $identity->id;
        $msg->support_ticket_id = $ticketId;
        $msg->content = $data['content'];
        $msg->ip = $this->di['request']->getClientAddress();
        $msg->created_at = date('Y-m-d H:i:s');
        $msg->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($msg);

        $this->di['events_manager']->fire(['event' => 'onAfterAdminOpenTicket', 'params' => ['id' => $ticketId]]);

        $this->di['logger']->info('Admin opened new ticket "%s"', $ticketId);

        return (int) $ticketId;
    }

    public function ticketCreateForGuest($data)
    {
        $extensionService = $this->di['mod_service']('extension');
        $config = $extensionService->getConfig('mod_support');

        if (isset($config['disable_public_tickets']) && $config['disable_public_tickets']) {
            throw new InformationException("We currently aren't accepting support tickets from unregistered users. Please use another contact method.");
        }

        $data['email'] = $this->di['tools']->validateAndSanitizeEmail($data['email']);

        $event_params = $data;
        $event_params['ip'] = $this->di['request']->getClientAddress();
        $altered = $this->di['events_manager']->fire(['event' => 'onBeforeGuestPublicTicketOpen', 'params' => $event_params]);

        $status = 'open';
        $subject = $data['subject'] ?? null;
        $message = $data['message'] ?? null;

        if (is_array($altered)) {
            $status = $altered['status'] ?? null;
            $subject = $altered['subject'] ?? null;
            $message = $altered['message'] ?? null;
        }

        $ticket = $this->di['db']->dispense('SupportPTicket');
        $ticket->hash = bin2hex(random_bytes(random_int(100, 127)));
        $ticket->author_name = $data['name'];
        $ticket->author_email = $data['email'];
        $ticket->subject = $subject;
        $ticket->status = $status;
        $ticket->created_at = date('Y-m-d H:i:s');
        $ticket->updated_at = date('Y-m-d H:i:s');
        $ticketId = $this->di['db']->store($ticket);

        $msg = $this->di['db']->dispense('SupportPTicketMessage');
        $msg->support_p_ticket_id = $ticket->id;
        $msg->content = $message;
        $msg->ip = $this->di['request']->getClientAddress();
        $msg->created_at = date('Y-m-d H:i:s');
        $msg->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($msg);

        $this->di['events_manager']->fire(['event' => 'onAfterGuestPublicTicketOpen', 'params' => ['id' => $ticketId]]);

        $this->di['logger']->info('"%s" opened public ticket "%s"', $ticket->author_email, $ticketId);

        return $ticket->hash;
    }

    public function canClientSubmitNewTicket(\Model_Client $client, array $config)
    {
        $hours = $config['wait_hours'];

        $lastTicket = $this->di['db']->findOne('SupportTicket', 'client_id = :client_id ORDER BY created_at DESC', [':client_id' => $client->id]);
        if (!$lastTicket instanceof \Model_SupportTicket) {
            return true;
        }

        $timeSinceLast = round(abs(strtotime($lastTicket->created_at) - strtotime(date('Y-m-d H:i:s'))) / 3600, 0);

        if ($timeSinceLast < $hours) {
            throw new InformationException(sprintf('You can submit one ticket per %s hours. %s hours left', $hours, $hours - $timeSinceLast));
        }

        return true;
    }

    public function ticketCreateForClient(\Model_Client $client, \Model_SupportHelpdesk $helpdesk, array $data)
    {
        // @todo validate task params
        $rel_id = $data['rel_id'] ?? null;
        $rel_type = $data['rel_type'] ?? null;

        $rel_task = $data['rel_task'] ?? null;
        $rel_new_value = $data['rel_new_value'] ?? null;
        $rel_status = isset($data['rel_task']) ? \Model_SupportTicket::REL_STATUS_PENDING : \Model_SupportTicket::REL_STATUS_COMPLETE;

        if ($rel_task == 'upgrade') {
            if (!is_null($rel_id) && $rel_type == \Model_SupportTicket::REL_TYPE_ORDER) {
                $orderService = $this->di['mod_service']('order');
                $o = $orderService->findForClientById($client, $rel_id);
                if (!$o instanceof \Model_ClientOrder) {
                    throw new \FOSSBilling\Exception('Order ID does not exist');
                }
            }

            if (!isset($o) || empty($rel_new_value)) {
                throw new \FOSSBilling\Exception('You must provide both an order ID and a new product ID in order to request an upgrade.');
            }

            $product = $this->di['db']->getExistingModelById('Product', $o->product_id);
            $allowedUpgrades = json_decode($product->upgrades ?? '');
            if (!in_array($rel_new_value, $allowedUpgrades)) {
                $upgrade = $this->di['db']->getExistingModelById('Product', $rel_new_value);

                throw new InformationException('Sorry, but ":product" is not allowed to be upgraded to ":upgrade"', [':product' => $product->title, ':upgrade' => $upgrade->title ?? 'unknown']);
            }
        }

        // check if support ticket with same uncompleted task already exists
        if ($rel_id && $rel_type && $rel_task && $this->checkIfTaskAlreadyExists($client, $rel_id, $rel_type, $rel_task)) {
            throw new InformationException('We have already received this request.');
        }

        $mod = $this->di['mod']('support');
        $config = $mod->getConfig();

        if (isset($config['wait_hours']) && is_numeric($config['wait_hours'])) {
            $this->canClientSubmitNewTicket($client, $config);
        }

        $event_params = $data;
        $event_params['client_id'] = $client->id;
        $this->di['events_manager']->fire(['event' => 'onBeforeClientOpenTicket', 'params' => $event_params]);

        $ticket = $this->di['db']->dispense('SupportTicket');
        $ticket->client_id = $client->id;
        $ticket->subject = $data['subject'];
        $ticket->support_helpdesk_id = $helpdesk->id;
        $ticket->created_at = date('Y-m-d H:i:s');
        $ticket->updated_at = date('Y-m-d H:i:s');

        // related task with ticket
        $ticket->rel_id = $rel_id;
        $ticket->rel_type = $rel_type;
        $ticket->rel_task = $rel_task;
        $ticket->rel_new_value = $rel_new_value;
        $ticket->rel_status = $rel_status;

        $ticketId = $this->di['db']->store($ticket);

        $this->messageCreateForTicket($ticket, $client, $data['content']);

        $this->di['events_manager']->fire(['event' => 'onAfterClientOpenTicket', 'params' => ['id' => $ticket->id]]);

        if (
            isset($config['autorespond_enable'])
            && $config['autorespond_enable']
            && isset($config['autorespond_message_id'])
            && !empty($config['autorespond_message_id'])
        ) {
            $this->cannedReply($ticket, $config['autorespond_message_id']);
        }

        $this->di['logger']->info('Submitted new ticket "%s"', $ticketId);

        return (int) $ticketId;
    }

    private function cannedReply(\Model_SupportTicket $ticket, $cannedId)
    {
        try {
            $cannedObj = $this->di['db']->getExistingModelById('SupportPr', $cannedId, 'Canned reply not found');
            $canned = $this->cannedToApiArray($cannedObj);
            $staffService = $this->di['mod_service']('staff');
            $admin = $staffService->getCronAdmin();
            if (isset($canned['content']) && $admin instanceof \Model_Admin) {
                $this->ticketReply($ticket, $admin, $canned['content']);
            }
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }
    }

    /**
     * @param \Model_Client $identity
     */
    public function messageCreateForTicket(\Model_SupportTicket $ticket, $identity, $content)
    {
        $msg = $this->di['db']->dispense('SupportTicketMessage');
        $msg->support_ticket_id = $ticket->id;
        if ($identity instanceof \Model_Admin) {
            $msg->admin_id = $identity->id;
        } elseif ($identity instanceof \Model_Client) {
            $msg->client_id = $identity->id;
        } else {
            throw new \FOSSBilling\Exception('Identity is invalid');
        }
        $msg->content = $content;
        $msg->ip = $this->di['request']->getClientAddress();
        $msg->created_at = date('Y-m-d H:i:s');
        $msg->updated_at = date('Y-m-d H:i:s');

        return $this->di['db']->store($msg);
    }

    public function publicGetStatuses()
    {
        return [
            \Model_SupportPTicket::OPENED => 'Open',
            \Model_SupportPTicket::ONHOLD => 'On hold',
            \Model_SupportPTicket::CLOSED => 'Closed',
        ];
    }

    public function publicFindOneByHash($hash)
    {
        $bindings = [
            ':hash' => $hash,
        ];

        $publicTicket = $this->di['db']->findOne('SupportPTicket', 'hash = :hash', $bindings);
        if (!$publicTicket instanceof \Model_SupportPTicket) {
            throw new \FOSSBilling\Exception('Public ticket not found');
        }

        return $publicTicket;
    }

    public function publicGetSearchQuery($data)
    {
        $query = 'SELECT spt.* FROM support_p_ticket spt
        LEFT JOIN support_p_ticket_message sptm
        ON spt.id = sptm.support_p_ticket_id';

        $search = $data['search'] ?? null;

        $id = $data['id'] ?? null;
        $status = $data['status'] ?? null;
        $name = $data['name'] ?? null;
        $email = $data['email'] ?? null;
        $subject = $data['subject'] ?? null;
        $content = $data['content'] ?? null;

        $where = [];
        $bindings = [];

        if ($id) {
            $where[] = 'spt.id  = :p_ticket_id';
            $bindings[':p_ticket_id'] = $id;
        }

        if ($status) {
            $where[] = 'spt.status  = :p_ticket_status';
            $bindings[':p_ticket_status'] = $status;
        }

        if ($email) {
            $where[] = 'spt.author_email  = :p_ticket_author_email';
            $bindings[':p_ticket_author_email'] = $email;
        }

        if ($name) {
            $where[] = 'spt.author_name  = :p_ticket_author_name';
            $bindings[':p_ticket_author_name'] = $name;
        }

        if ($content) {
            $where[] = 'spt.content LIKE :p_ticket_content';
            $bindings[':p_ticket_content'] = "%$content%";
        }

        if ($subject) {
            $where[] = 'spt.subject LIKE :p_ticket_subject';
            $bindings[':p_ticket_subject'] = "%$subject%";
        }

        // smartSearch
        if ($search) {
            if (is_numeric($search)) {
                $where[] = 'spt.id = :p_ticket_id';
                $bindings[':p_ticket_id'] = $search;
            } else {
                $search = '%' . $search . '%';
                $where[] = 'sptm.content LIKE :p_message_content OR spt.subject LIKE :p_ticket_subject';
                $bindings[':p_message_content'] = $search;
                $bindings[':p_ticket_subject'] = $search;
            }
        }

        if (!empty($where)) {
            $query = $query . ' WHERE ' . implode(' AND ', $where);
        }

        $query .= ' GROUP BY spt.id, sptm.id ORDER BY spt.id DESC, sptm.id ASC';

        return [$query, $bindings];
    }

    public function publicCounter()
    {
        $query = 'SELECT status, COUNT(id) as counter
                FROM support_p_ticket
                GROUP BY status';

        $data = $this->di['db']->getAssoc($query);

        return [
            'total' => array_sum($data),
            \Model_SupportPTicket::OPENED => $data[\Model_SupportPTicket::OPENED] ?? 0,
            \Model_SupportPTicket::CLOSED => $data[\Model_SupportPTicket::CLOSED] ?? 0,
            \Model_SupportPTicket::ONHOLD => $data[\Model_SupportPTicket::ONHOLD] ?? 0,
        ];
    }

    public function publicGetLatest()
    {
        return $this->di['db']->find('SupportPTicket', 'ORDER BY id DESC Limit 10');
    }

    public function publicCountByStatus($status)
    {
        $query = 'SELECT COUNT(id) as counter
                FROM support_p_ticket
                WHERE status = :status
                GROUP BY status';

        return $this->di['db']->getCell($query, [':status' => $status]);
    }

    public function publicGetExpired()
    {
        $bindings = [
            ':status' => \Model_SupportPTicket::ONHOLD,
        ];

        return $this->di['db']->find('SupportPTicket', 'status = :status AND DATE_ADD(updated_at, INTERVAL 48 HOUR) < NOW() ORDER BY id ASC', $bindings);
    }

    public function publicCloseTicket(\Model_SupportPTicket $model, $identity)
    {
        $model->status = 'closed';
        $model->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($model);

        if ($identity instanceof \Model_Admin) {
            $this->di['events_manager']->fire(['event' => 'onAfterAdminPublicTicketClose', 'params' => ['id' => $model->id]]);
            $this->di['logger']->info('Public Ticket %s was closed', $model->id);
        } elseif ($identity instanceof \Model_Guest) {
            $this->di['events_manager']->fire(['event' => 'onAfterGuestPublicTicketClose', 'params' => ['id' => $model->id]]);
            $this->di['logger']->info('"%s" closed public ticket "%s"', $model->author_email, $model->id);
        }

        return true;
    }

    public function publicAutoClose(\Model_SupportPTicket $model)
    {
        $model->status = 'closed';
        $model->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($model);

        $this->di['logger']->info('Public Ticket %s was closed', $model->id);

        return true;
    }

    public function publicRm(\Model_SupportPTicket $model)
    {
        $id = $model->id;
        $bindings = [
            ':support_p_ticket_id' => $model->id,
        ];
        $messages = $this->di['db']->find('SupportPTicketMessage', 'support_p_ticket_id = :support_p_ticket_id', $bindings);

        foreach ($messages as $message) {
            $this->di['db']->trash($message);
        }

        $this->di['db']->trash($model);

        $this->di['logger']->info('Deleted public ticket #%s', $id);

        return true;
    }

    public function publicToApiArray(\Model_SupportPTicket $model, $deep = true)
    {
        $data = $this->di['db']->toArray($model);
        $messages = [];
        $messagesArr = $this->di['db']->find('SupportPTicketMessage', 'support_p_ticket_id = :support_p_ticket_id ORDER BY id', [':support_p_ticket_id' => $model->id]);
        foreach ($messagesArr as $msg) {
            $messages[] = $this->publicMessageToApiArray($msg);
        }

        $first = reset($messagesArr);
        if ($first instanceof \Model_SupportPTicketMessage) {
            $data['author'] = $this->publicMessageGetAuthorDetails($first);
        } else {
            $data['author'] = [];
        }
        $data['messages'] = $messages;

        return $data;
    }

    public function publicMessageGetAuthorDetails(\Model_SupportPTicketMessage $model)
    {
        if ($model->admin_id) {
            $author = $this->di['db']->getExistingModelById('Admin', $model->admin_id);

            return [
                'name' => $author->getFullName(),
                'email' => $author->email,
            ];
        }

        $ticket = $this->di['db']->getExistingModelById('SupportPTicket', $model->support_p_ticket_id);

        return [
            'name' => $ticket->author_name,
            'email' => $ticket->author_email,
        ];
    }

    public function publicMessageToApiArray(\Model_SupportPTicketMessage $model, $deep = true)
    {
        $data = $this->di['db']->toArray($model);
        $data['author'] = $this->publicMessageGetAuthorDetails($model);

        return $data;
    }

    public function publicTicketCreate($data, \Model_Admin $identity)
    {
        $data['email'] = $this->di['tools']->validateAndSanitizeEmail($data['email']);

        $this->di['events_manager']->fire(['event' => 'onBeforeAdminPublicTicketOpen', 'params' => $data]);

        $ticket = $this->di['db']->dispense('SupportPTicket');
        $ticket->hash = bin2hex(random_bytes(random_int(100, 127)));
        $ticket->author_name = $data['name'];
        $ticket->author_email = $data['email'];
        $ticket->subject = $data['subject'];
        $ticket->status = 'open';
        $ticket->created_at = date('Y-m-d H:i:s');
        $ticket->updated_at = date('Y-m-d H:i:s');
        $ticketId = $this->di['db']->store($ticket);

        $msg = $this->di['db']->dispense('SupportPTicketMessage');
        $msg->support_p_ticket_id = $ticketId;
        $msg->admin_id = $identity->id;
        $msg->content = $data['message'];
        $msg->ip = $this->di['request']->getClientAddress();
        $msg->created_at = date('Y-m-d H:i:s');
        $msg->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($msg);

        $this->di['events_manager']->fire(['event' => 'onAfterAdminPublicTicketOpen', 'params' => ['id' => $ticketId]]);

        $this->di['logger']->info('Opened public ticket for email "%s"', $ticket->author_email);

        return $ticketId;
    }

    public function publicTicketUpdate(\Model_SupportPTicket $model, $data)
    {
        $model->subject = $data['subject'] ?? $model->subject;
        $model->status = $data['status'] ?? $model->status;
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        $this->di['logger']->info('Updated public ticket #%s', $model->id);

        return true;
    }

    public function publicTicketReply(\Model_SupportPTicket $ticket, \Model_Admin $identity, $content)
    {
        $msg = $this->di['db']->dispense('SupportPTicketMessage');
        $msg->support_p_ticket_id = $ticket->id;
        $msg->admin_id = $identity->id;
        $msg->content = $content;
        $msg->ip = $this->di['request']->getClientAddress();
        $msg->created_at = date('Y-m-d H:i:s');
        $msg->updated_at = date('Y-m-d H:i:s');
        $messageId = $this->di['db']->store($msg);

        $ticket->status = \Model_SupportPTicket::ONHOLD;
        $ticket->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($ticket);

        $this->di['events_manager']->fire(['event' => 'onAfterAdminPublicTicketReply', 'params' => ['id' => $ticket->id]]);

        $this->di['logger']->info('Replied to public ticket "%s"', $ticket->id);

        return $messageId;
    }

    public function publicTicketReplyForGuest(\Model_SupportPTicket $ticket, $message)
    {
        $msg = $this->di['db']->dispense('SupportPTicketMessage');
        $msg->support_p_ticket_id = $ticket->id;
        $msg->content = $message;
        $msg->ip = $this->di['request']->getClientAddress();
        $msg->created_at = date('Y-m-d H:i:s');
        $msg->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($msg);

        $ticket->status = \Model_SupportPTicket::OPENED;
        $ticket->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($ticket);

        $this->di['events_manager']->fire(['event' => 'onAfterGuestPublicTicketReply', 'params' => ['id' => $ticket->id]]);

        $this->di['logger']->info('Client "%s" replied to public ticket "%s"', $ticket->author_email, $ticket->id);

        return $ticket->hash;
    }

    public function helpdeskUpdate(\Model_SupportHelpdesk $model, $data)
    {
        $model->name = $data['name'] ?? $model->name;
        $model->email = $data['email'] ?? $model->email;
        $model->can_reopen = $data['can_reopen'] ?? $model->can_reopen;
        $model->close_after = $data['close_after'] ?? $model->close_after;
        $model->signature = $data['signature'] ?? $model->signature;
        $model->updated_at = date('Y-m-d H:i:s');
        $id = $this->di['db']->store($model);

        $this->di['logger']->info('Updated helpdesk #%s', $id);

        return true;
    }

    public function helpdeskCreate($data)
    {
        $model = $this->di['db']->dispense('SupportHelpdesk');
        $model->name = $data['name'];
        $model->email = $data['email'] ?? null;
        $model->can_reopen = $data['can_reopen'] ?? null;
        $model->close_after = $data['close_after'] ?? null;
        $model->signature = $data['signature'] ?? null;
        $model->created_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');
        $id = $this->di['db']->store($model);

        $this->di['logger']->info('Created helpdesk #%s', $id);

        return $id;
    }

    public function cannedGetSearchQuery($data)
    {
        $query = 'SELECT sp.* FROM support_pr sp
                LEFT JOIN support_pr_category spc
                ON spc.id = sp.support_pr_category_id';

        $search = $data['search'] ?? null;

        $where = [];
        $bindings = [];

        if ($search) {
            $search = '%' . $search . '%';
            $where[] = 'title LIKE :title OR content LIKE :content';
            $bindings[':title'] = $search;
            $bindings[':content'] = $search;
        }

        if (!empty($where)) {
            $query = $query . ' WHERE ' . implode(' AND ', $where);
        }

        $query .= ' ORDER BY sp.support_pr_category_id ASC';

        return [$query, $bindings];
    }

    /**
     * @return non-empty-array[]
     */
    public function cannedGetGroupedPairs(): array
    {
        $query = 'SELECT sp.title as r_title, spc.title as c_title FROM support_pr sp
                LEFT JOIN support_pr_category spc
                ON spc.id = sp.support_pr_category_id';

        $data = $this->di['db']->getAll($query);
        $res = [];
        foreach ($data as $r) {
            $res[$r['c_title']][$r['id']] = $r['r_title'];
        }

        return $res;
    }

    public function cannedRm(\Model_SupportPr $model)
    {
        $id = $model->id;

        $this->di['db']->trash($model);

        $this->di['logger']->info('Deleted canned response #%s', $id);

        return true;
    }

    public function cannedToApiArray(\Model_SupportPr $model)
    {
        $result = $this->di['db']->toArray($model);
        $category = $this->di['db']->load('SupportPrCategory', $model->support_pr_category_id);
        if ($category instanceof \Model_SupportPrCategory) {
            $result['category'] = [
                'id' => $category->id,
                'title' => $category->title,
            ];
        } else {
            $result['category'] = [];
        }

        return $result;
    }

    public function cannedCategoryGetPairs()
    {
        return $this->di['db']->getAssoc('SELECT id, title FROM support_pr_category');
    }

    public function cannedCategoryRm(\Model_SupportPrCategory $model)
    {
        $id = $model->id;
        $this->di['db']->trash($model);
        $this->di['logger']->info('Deleted canned response category #%s', $id);

        return true;
    }

    public function cannedCategoryToApiArray(\Model_SupportPrCategory $model)
    {
        return $this->di['db']->toArray($model);
    }

    public function cannedCreate($title, $categoryId, $content = null)
    {
        $systemService = $this->di['mod_service']('system');
        $systemService->checkLimits('Model_SupportPr', 5);

        $model = $this->di['db']->dispense('SupportPr');
        $model->support_pr_category_id = $categoryId;
        $model->title = $title;
        $model->content = $content;
        $model->created_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');
        $id = $this->di['db']->store($model);

        $this->di['logger']->info('Created new canned response #%s', $id);

        return $id;
    }

    public function cannedUpdate(\Model_SupportPr $model, $data)
    {
        $model->support_pr_category_id = $data['category_id'] ?? $model->support_pr_category_id;
        $model->title = $data['title'] ?? $model->title;
        $model->content = $data['content'] ?? $model->content;
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        $this->di['logger']->info('Updated canned response #%s', $model->id);

        return true;
    }

    public function cannedCategoryCreate($title)
    {
        $model = $this->di['db']->dispense('SupportPrCategory');
        $model->title = $title;
        $model->created_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');
        $id = $this->di['db']->store($model);

        $this->di['logger']->info('Created new canned response category #%s', $id);

        return $id;
    }

    public function cannedCategoryUpdate(\Model_SupportPrCategory $model, $title)
    {
        $model->title = $title;
        $model->updated_at = date('Y-m-d H:i:s');
        $id = $this->di['db']->store($model);

        $this->di['logger']->info('Updated canned response category #%s', $id);

        return true;
    }

    public function noteCreate(\Model_SupportTicket $ticket, \Model_Admin $identity, $note)
    {
        $model = $this->di['db']->dispense('SupportTicketNote');
        $model->support_ticket_id = $ticket->id;
        $model->admin_id = $identity->id;
        $model->note = $note;
        $model->created_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');
        $id = $this->di['db']->store($model);

        $this->di['logger']->info('Added note to ticket #%s', $id);

        return $id;
    }

    public function ticketTaskComplete(\Model_SupportTicket $model)
    {
        $model->rel_status = \Model_SupportTicket::REL_STATUS_COMPLETE;
        $model->updated_at = date('Y-m-d H:i:s');
        $id = $this->di['db']->store($model);

        $this->di['logger']->info('Marked ticket #%s task as complete', $id);

        return true;
    }

    /*
     * Knowledge Base Functions.
     */

    public function kbEnabled()
    {
        $extensionService = $this->di['mod_service']('extension');
        $config = $extensionService->getConfig('mod_support');

        return isset($config['kb_enable']) && $config['kb_enable'] == 'on';
    }

    public function kbSearchArticles($status = null, $search = null, $cat = null, $per_page = 100, $page = null)
    {
        $filter = [];

        $sql = '
            SELECT *
            FROM support_kb_article
            WHERE 1
        ';

        if ($cat) {
            $sql .= ' AND kb_article_category_id = :cid';
            $filter[':cid'] = $cat;
        }

        if ($status) {
            $sql .= ' AND status = :status';
            $filter[':status'] = $status;
        }

        if ($search) {
            $sql .= ' AND title LIKE :q OR content LIKE :q';
            $filter[':q'] = "%$search%";
        }

        $sql .= ' ORDER BY kb_article_category_id DESC, views DESC';

        return $this->di['pager']->getSimpleResultSet($sql, $filter, $per_page, $page);
    }

    public function kbFindActiveArticleById($id)
    {
        $bindings = [
            ':id' => $id,
            ':status' => \Model_SupportKbArticle::ACTIVE,
        ];

        return $this->di['db']->findOne('SupportKbArticle', 'id = :id AND status=:status', $bindings);
    }

    public function kbFindActiveArticleBySlug($slug)
    {
        $bindings = [
            ':slug' => $slug,
            ':status' => \Model_SupportKbArticle::ACTIVE,
        ];

        return $this->di['db']->findOne('SupportKbArticle', 'slug = :slug AND status=:status', $bindings);
    }

    public function kbFindActive()
    {
        return $this->di['db']->find('SupportKbArticle', 'status=:status', [':status' => \Model_SupportKbArticle::ACTIVE]);
    }

    public function kbHitView(\Model_SupportKbArticle $model)
    {
        ++$model->views;
        $this->di['db']->store($model);
    }

    public function kbRm(\Model_SupportKbArticle $model)
    {
        $id = $model->id;
        $this->di['db']->trash($model);
        $this->di['logger']->info('Deleted knowledge base article #%s', $id);
    }

    public function kbToApiArray(\Model_SupportKbArticle $model, $deep = false, $identity = null): array
    {
        $data = [
            'id' => $model->id,
            'slug' => $model->slug,
            'title' => $model->title,
            'views' => $model->views,
            'created_at' => $model->created_at,
            'status' => $model->status,
            'updated_at' => $model->updated_at,
        ];

        $cat = $this->di['db']->getExistingModelById('SupportKbArticleCategory', $model->kb_article_category_id, 'Knowledge base category not found');
        $data['category'] = [
            'id' => $cat->id,
            'slug' => $cat->slug,
            'title' => $cat->title,
        ];

        if ($deep) {
            $data['content'] = $model->content;
        }

        if ($identity instanceof \Model_Admin) {
            $data['status'] = $model->status;
            $data['kb_article_category_id'] = $model->kb_article_category_id;
        }

        return $data;
    }

    public function kbCreateArticle($articleCategoryId, $title, $status = null, $content = null)
    {
        if (!isset($status)) {
            $status = \Model_SupportKbArticle::DRAFT;
        }

        $model = $this->di['db']->dispense('SupportKbArticle');
        $model->kb_article_category_id = $articleCategoryId;
        $model->title = $title;
        $model->slug = $this->di['tools']->slug($title);
        $model->status = $status;
        $model->content = $content;
        $model->updated_at = date('Y-m-d H:i:s');
        $model->created_at = date('Y-m-d H:i:s');
        $id = $this->di['db']->store($model);

        $this->di['logger']->info('Created new knowledge base article #%s', $id);

        return $id;
    }

    public function kbUpdateArticle($id, $articleCategoryId = null, $title = null, $slug = null, $status = null, $content = null, $views = null)
    {
        $model = $this->di['db']->findOne('SupportKbArticle', 'id = ?', [$id]);

        if (!$model instanceof \Model_SupportKbArticle) {
            throw new \FOSSBilling\Exception('Article not found');
        }

        if (isset($articleCategoryId)) {
            $model->kb_article_category_id = $articleCategoryId;
        }

        if (isset($title)) {
            $model->title = $title;
        }

        if (isset($slug)) {
            $model->slug = $slug;
        }

        if (isset($status)) {
            $model->status = $status;
        }

        if (isset($content)) {
            $model->content = $content;
        }

        if (isset($views)) {
            $model->views = $views;
        }
        $model->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($model);

        $this->di['logger']->info('Updated knowledge base article #%s', $id);

        return true;
    }

    public function kbCategoryGetSearchQuery($data)
    {
        $sql = '
        SELECT kac.*
        FROM support_kb_article_category kac
        LEFT JOIN support_kb_article ka ON kac.id  = ka.kb_article_category_id';

        $article_status = $data['article_status'] ?? null;
        $query = $data['q'] ?? null;

        $where = [];
        $bindings = [];
        if ($article_status) {
            $where[] = 'ka.status = :status';

            $bindings[':status'] = $article_status;
        }

        if ($query) {
            $where[] = '(ka.title LIKE :title OR ka.content LIKE :content)';

            $bindings[':title'] = "%$query%";
            $bindings[':content'] = "%$query%";
        }

        if (!empty($where)) {
            $sql = $sql . ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' GROUP BY kac.id ORDER BY kac.id DESC';

        return [$sql, $bindings];
    }

    public function kbCategoryFindAll()
    {
        $sql = 'SELECT kac.*, a.*
                FROM support_kb_article_category kac
                LEFT JOIN support_kb_article ka
                ON kac.id  = ka.kb_article_category_id
                ';

        return $this->di['db']->getAll($sql);
    }

    public function kbCategoryGetPairs()
    {
        $sql = 'SELECT id, title FROM support_kb_article_category';

        return $this->di['db']->getAssoc($sql);
    }

    public function kbCategoryRm(\Model_SupportKbArticleCategory $model)
    {
        $bindings = [
            ':kb_article_category_id' => $model->id,
        ];

        $articlesCount = $this->di['db']->getCell('SELECT count(*) as cnt FROM support_kb_article WHERE kb_article_category_id = :kb_article_category_id', $bindings);

        if ($articlesCount > 0) {
            throw new InformationException('Cannot remove category which has articles');
        }

        $id = $model->id;

        $this->di['db']->trash($model);

        $this->di['logger']->info('Deleted knowledge base category #%s', $id);

        return true;
    }

    public function kbCategoryToApiArray(\Model_SupportKbArticleCategory $model, $identity = null, $query = null)
    {
        $data = $this->di['db']->toArray($model);

        $sql = 'kb_article_category_id = :category_id';
        $bindings = [
            ':category_id' => $model->id,
        ];

        if (!$identity instanceof \Model_Admin) {
            $sql .= " AND status = 'active'";
        }

        if ($query) {
            $sql .= 'AND (title LIKE :title OR content LIKE :content)';
            $query = "%$query%";
            $bindings[':content'] = $query;
            $bindings[':title'] = $query;
        }

        $articles = $this->di['db']->find('SupportKbArticle', $sql, $bindings);

        foreach ($articles as $article) {
            $data['articles'][] = $this->kbToApiArray($article, false, $identity);
        }

        return $data;
    }

    public function kbCreateCategory($title, $description = null)
    {
        $systemService = $this->di['mod_service']('system');
        $systemService->checkLimits('Model_SupportKbArticleCategory', 2);

        $model = $this->di['db']->dispense('SupportKbArticleCategory');
        $model->title = $title;
        $model->description = $description;
        $model->slug = $this->di['tools']->slug($title);
        $model->updated_at = date('Y-m-d H:i:s');
        $model->created_at = date('Y-m-d H:i:s');

        $id = $this->di['db']->store($model);

        $this->di['logger']->info('Created new knowledge base category #%s', $id);

        return $id;
    }

    public function kbUpdateCategory(\Model_SupportKbArticleCategory $model, $title = null, $slug = null, $description = null)
    {
        if (isset($title)) {
            $model->title = $title;
        }

        if (isset($slug)) {
            $model->slug = $slug;
        }

        if (isset($description)) {
            $model->description = $description;
        }

        $model->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($model);

        $this->di['logger']->info('Updated knowledge base category #%s', $model->id);

        return true;
    }

    public function kbFindCategoryById($id)
    {
        return $this->di['db']->getExistingModelById('SupportKbArticleCategory', $id, 'Knowledge base category not found');
    }

    public function kbFindCategoryBySlug($slug)
    {
        $bindings = [
            ':slug' => $slug,
        ];

        return $this->di['db']->findOne('SupportKbArticleCategory', 'slug = :slug', $bindings);
    }
}
