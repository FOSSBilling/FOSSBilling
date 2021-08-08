<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (http://www.boxbilling.com)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

namespace Box\Mod\Support;

class Service implements \Box\InjectionAwareInterface
{
    protected $di;

    public function setDi($di)
    {
        $this->di = $di;
    }

    public function getDi()
    {
        return $this->di;
    }

    public static function onAfterClientOpenTicket(\Box_Event $event)
    {
        $di = $event->getDi();
        $params       = $event->getParameters();
        $supportService = $di['mod_service']('support');
        $emailService = $di['mod_service']('email');

        try {
            $ticketObj = $supportService->getTicketById($params['id']);
            $identity = $di['loggedin_client'];
            $ticketArr = $supportService->toApiArray($ticketObj, true, $identity);

            $email              = array();
            $email['to_client'] = $ticketObj->client_id;
            $email['code']      = 'mod_support_ticket_open';
            $email['ticket']    = $ticketArr;
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
        $params       = $event->getParameters();


        try {
            $ticketObj = $supportService->getTicketById($params['id']);
            $identity =  $di['loggedin_admin'];
            $ticketArr = $supportService->toApiArray($ticketObj, true, $identity);

            $email              = array();
            $email['to_client'] = $ticketObj->client_id;
            $email['code']      = 'mod_support_ticket_staff_open';
            $email['ticket']    = $ticketArr;
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
        $params       = $event->getParameters();

        try {
            $identity =  $di['loggedin_admin'];
            $ticketObj = $supportService->getTicketById($params['id']);
            $ticketArr = $supportService->toApiArray($ticketObj, true, $identity);

            $email              = array();
            $email['to_client'] = $ticketObj->client_id;
            $email['code']      = 'mod_support_ticket_staff_close';
            $email['ticket']    = $ticketArr;
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
        $params       = $event->getParameters();

        try {
            $ticketObj = $supportService->getTicketById($params['id']);
            $identity =  $di['loggedin_admin'];
            $ticketArr = $supportService->toApiArray($ticketObj, true, $identity);

            $email              = array();
            $email['to_client'] = $ticketObj->client_id;
            $email['code']      = 'mod_support_ticket_staff_reply';
            $email['ticket']    = $ticketArr;
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
        $params       = $event->getParameters();

        try {
            $ticketObj = $supportService->getPublicTicketById($params['id']);
            $ticketArr = $supportService->publicToApiArray($ticketObj, true);

            $email            = array();
            $email['to']      = $ticketArr['author_email'];
            $email['to_name'] = $ticketArr['author_name'];
            $email['code']    = 'mod_support_pticket_open';
            $email['ticket']  = $ticketArr;
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
        $params       = $event->getParameters();

        try {
            $ticketObj = $supportService->getPublicTicketById($params['id']);
            $identity =  $di['loggedin_admin'];
            $ticketArr = $supportService->publicToApiArray($ticketObj, true, $identity);

            $email            = array();
            $email['to']      = $ticketArr['author_email'];
            $email['to_name'] = $ticketArr['author_name'];
            $email['code']    = 'mod_support_pticket_staff_open';
            $email['ticket']  = $ticketArr;
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
        $params       = $event->getParameters();

        try {
            $ticketObj = $supportService->getPublicTicketById($params['id']);
            $identity =  $di['loggedin_admin'];
            $ticketArr = $supportService->publicToApiArray($ticketObj, true, $identity);

            $email            = array();
            $email['to']      = $ticketArr['author_email'];
            $email['to_name'] = $ticketArr['author_name'];
            $email['code']    = 'mod_support_pticket_staff_reply';
            $email['ticket']  = $ticketArr;
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
        $params       = $event->getParameters();

        try {
            $ticketObj = $supportService->getPublicTicketById($params['id']);
            $identity =  $di['loggedin_admin'];
            $ticketArr = $supportService->publicToApiArray($ticketObj, true, $identity);

            $email            = array();
            $email['to']      = $ticketArr['author_email'];
            $email['to_name'] = $ticketArr['author_name'];
            $email['code']    = 'mod_support_pticket_staff_close';
            $email['ticket']  = $ticketArr;
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
        return $this->di['db']->getExistingModelById('SupportPTicket', $id, 'Ticket not found');;
    }

    /**
     * Return array of ticket statuses
     */
    public function getStatuses()
    {
        $data = array(
            \Model_SupportTicket::OPENED => 'Open',
            \Model_SupportTicket::ONHOLD => 'On hold',
            \Model_SupportTicket::CLOSED => 'Closed',
        );

        return $data;
    }

    /**
     * Find ticket for client
     * @param \Model_Client $c
     * @param int $id
     * @return \Model_SupportTicket
     */
    public function findOneByClient(\Model_Client $c, $id)
    {
        $bindings = array(
            ':id'        => $id,
            ':client_id' => $c->id
        );

        $ticket = $this->di['db']->findOne('SupportTicket', 'id = :id AND client_id = :client_id', $bindings);

        if (!$ticket instanceof \Model_SupportTicket) {
            throw new \Box_Exception('Ticket not found');
        }

        return $ticket;
    }

    public function getSearchQuery($data)
    {
        $query = "SELECT st.*
                FROM support_ticket st
                JOIN support_ticket_message stm ON stm.support_ticket_id = st.id
                LEFT JOIN client c ON st.client_id = c.id";

        $search     = $this->di['array_get']($data, 'search', NULL);
        $id         = $this->di['array_get']($data, 'id', NULL);
        $status     = $this->di['array_get']($data, 'status', NULL);
        $client     = $this->di['array_get']($data, 'client', NULL);
        $client_id  = $this->di['array_get']($data, 'client_id', NULL);
        $order_id   = $this->di['array_get']($data, 'order_id', NULL);
        $subject    = $this->di['array_get']($data, 'subject', NULL);
        $content    = $this->di['array_get']($data, 'content', NULL);
        $helpdesk   = $this->di['array_get']($data, 'support_helpdesk_id', NULL);
        $created_at = $this->di['array_get']($data, 'created_at', NULL);
        $date_from  = $this->di['array_get']($data, 'date_from', NULL);
        $date_to    = $this->di['array_get']($data, 'date_to', NULL);
        $priority   = $this->di['array_get']($data, 'priority', NULL);

        $where    = array();
        $bindings = array();

        if ($id) {
            $where[]                = "st.id = :ticket_id";
            $bindings[':ticket_id'] = $id;
        }

        if ($priority) {
            $where[]               = "st.priority = :priority";
            $bindings[':priority'] = $priority;
        }

        if ($helpdesk) {
            $where[]                          = "st.support_helpdesk_id = :support_helpdesk_id";
            $bindings[':support_helpdesk_id'] = $helpdesk;
        }

        if ($client) {
            $where[]                  = "c.first_name LIKE :first_name";
            $where[]                  = "c.last_name LIKE :last_name";
            $bindings[':first_name'] = "%" . $client . "%";
            $bindings[':last_name'] = "%" . $client . "%";
        }

        if ($client_id) {
            $where[]                = "c.id = :client_id";
            $bindings[':client_id'] = $client_id;
        }

        if ($content) {
            $where[]              = "stm.content LIKE :content";
            $bindings[':content'] = "%" . $content . "%";
        }

        if ($subject) {
            $where[]              = "st.subject LIKE :subject";
            $bindings[':subject'] = "%" . $subject . "%";
        }

        if ($status) {
            $where[]             = "st.status = :status";
            $bindings[':status'] = $status;
        }

        if ($order_id) {
            $where[]               = "st.rel_type = :rel_type AND st.rel_id = :rel_id";
            $bindings[':rel_type'] = \Model_SupportTicket::REL_TYPE_ORDER;
            $bindings[':rel_id']   = $order_id;
        }

        if ($created_at) {
            $where[]                 = "DATE_FORMAT(st.created_at, '%Y-%m-%d') = :created_at";
            $bindings[':created_at'] = date('Y-m-d', strtotime($created_at));
        }

        if ($date_from) {
            $where[]                = "UNIX_TIMESTAMP(st.created_at) >= :date_from";
            $bindings[':date_from'] = strtotime($date_from);
        }

        if ($date_to) {
            $where[]              = "UNIX_TIMESTAMP(st.created_at) <= :date_to";
            $bindings[':date_to'] = strtotime($date_to);
        }
        //smartSearch
        if ($search) {
            if (is_numeric($search)) {
                $where[]                = "st.id = :ticket_id";
                $bindings[':ticket_id'] = $search;
            } else {
                $search               = "%" . $search . "%";
                $where[]              = "(stm.content LIKE :content OR st.subject LIKE :subject)";
                $bindings[':content'] = $search;
                $bindings[':subject'] = $search;
            }
        }

        if (!empty($where)) {
            $query = $query . ' WHERE ' . implode(' AND ', $where);
        }

        $query .= " GROUP BY st.id ORDER BY st.priority ASC, st.id DESC";

        return array($query, $bindings);
    }

    public function counter()
    {
        $query = "SELECT status, COUNT(id) as counter
                    FROM support_ticket
                    GROUP BY status";

        $data = $this->di['db']->getAssoc($query);

        return array(
            'total'                      => array_sum($data),
            \Model_SupportTicket::OPENED => $this->di['array_get']($data, \Model_SupportTicket::OPENED, 0),
            \Model_SupportTicket::CLOSED => $this->di['array_get']($data, \Model_SupportTicket::CLOSED, 0),
            \Model_SupportTicket::ONHOLD => $this->di['array_get']($data, \Model_SupportTicket::ONHOLD, 0),
        );
    }

    public function getLatest()
    {
        return $this->di['db']->find('SupportTicket', 'ORDER BY id DESC LIMIT 10');
    }

    public function getExpired()
    {
        $bindings = array(
            ':status' => \Model_SupportTicket::ONHOLD
        );

        $sql = "SELECT st.*
                FROM support_ticket as st
                    LEFT JOIN support_helpdesk sh ON sh.id = st.support_helpdesk_id
                WHERE st.status = :status
                AND DATE_ADD(st.updated_at, INTERVAL sh.close_after HOUR) < NOW()
                ORDER BY st.id ASC";

        return $this->di['db']->getAll($sql, $bindings);
    }

    public function countByStatus($status)
    {
        $query = "SELECT COUNT(m.id) as counter FROM support_ticket
                WHERE 'status' = :'status' GROUP BY 'status' LIMIT 1";

        return $this->di['db']->getCell($query, array(':status' => $status));
    }

    public function getActiveTicketsCountForOrder(\Model_ClientOrder $model)
    {
        $query = "SELECT COUNT(id) as counter FROM support_ticket
                WHERE rel_id = :order_id
                AND rel_type = 'order'
                AND (status = :status1 OR status = :status2)";

        $bindings = array(
            ':order_id' => $model->id,
            ':status1'  => \Model_SupportTicket::OPENED,
            ':status2'  => \Model_SupportTicket::ONHOLD,
        );

        return $this->di['db']->getCell($query, $bindings);
    }

    public function checkIfTaskAlreadyExists(\Model_Client $client, $rel_id, $rel_type, $rel_task)
    {
        $bindings = array(
            ':client_id'  => $client->id,
            ':rel_id'     => $rel_id,
            ':rel_type'   => $rel_type,
            ':rel_task'   => $rel_task,
            ':rel_status' => \Model_SupportTicket::REL_STATUS_PENDING
        );

        $ticket = $this->di['db']->findOne(
            'SupportTicket',
            'client_id = :client_id
            AND rel_id = :rel_id
            AND rel_type = :rel_type
            AND rel_task = :rel_task
            AND rel_status = :rel_status',
            $bindings);

        return ($ticket instanceof \Model_SupportTicket);
    }

    public function closeTicket(\Model_SupportTicket $ticket, $identity)
    {
        $ticket->status     = \Model_SupportTicket::CLOSED;
        $ticket->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($ticket);

        if ($identity instanceof \Model_Admin) {
            $this->di['events_manager']->fire(array('event' => 'onAfterAdminCloseTicket', 'params' => array('id' => $ticket->id)));
        } elseif ($identity instanceof \Model_Client) {
            $this->di['events_manager']->fire(array('event' => 'onAfterClientCloseTicket', 'params' => array('id' => $ticket->id)));
        }

        $this->di['logger']->info('Closed ticket "%s"', $ticket->id);

        return true;
    }

    public function autoClose(\Model_SupportTicket $model)
    {
        $model->status     = \Model_SupportTicket::CLOSED;
        $model->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($model);
        $this->di['logger']->info('Ticket %s was closed', $model->id);

        return TRUE;
    }

    public function canBeReopened(\Model_SupportTicket $model)
    {
        if ($model->status != \Model_SupportTicket::CLOSED) {
            return true;
        }

        $helpdesk = $this->di['db']->getExistingModelById('SupportHelpdesk', $model->support_helpdesk_id);

        return (bool)$helpdesk->can_reopen;
    }

    private function _getRelDetails(\Model_SupportTicket $model)
    {
        if (!$model->rel_type || !$model->rel_id) {
            return array();
        }

        $result = array(
            'id'        => $model->rel_id,
            'type'      => $model->rel_type,
            'task'      => $model->rel_task,
            'new_value' => $model->rel_new_value,
            'status'    => $model->rel_status,
        );

        $client = $this->di['db']->load('Client', $model->client_id);

        if ($model->rel_type == \Model_SupportTicket::REL_TYPE_ORDER) {
            $orderService = $this->di['mod_service']('order');
            $o            = $orderService->findForClientById($client, $model->rel_id);
            if ($o instanceof \Model_ClientOrder) {
                $result['order'] = $orderService->toApiArray($o, false);
            }
        }

        return $result;
    }

    public function rmByClient(\Model_Client $client)
    {
        $clientTickets = $this->di['db']->find('SupportTicket', 'client_id = :client_id', array(':client_id' => $client->id));
        foreach ($clientTickets as $ticket) {
            $this->di['db']->trash($ticket);
        }
    }

    public function rm(\Model_SupportTicket $model)
    {
        $supportTicketNotes = $this->di['db']->find('SupportTicketNote', 'support_ticket_id = :support_ticket_id', array(':support_ticket_id' => $model->id));
        foreach ($supportTicketNotes as $note) {
            $this->di['db']->trash($note);
        }

        $supportTicketMessages = $this->di['db']->find('SupportTicketMessage', 'support_ticket_id = :support_ticket_id', array(':support_ticket_id' => $model->id));
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
        $firstSupportTicketMessage = $this->di['db']->findOne('SupportTicketMessage', 'support_ticket_id = :support_ticket_id ORDER by id ASC LIMIT 1', array(':support_ticket_id' => $model->id));
        $supportHelpdesk           = $this->di['db']->load('SupportHelpdesk', $model->support_helpdesk_id);

        $data             = $this->di['db']->toArray($model);
        $data['replies']  = $this->messageGetRepliesCount($model);
        $data['first']    = $this->messageToApiArray($firstSupportTicketMessage);
        $data['helpdesk'] = $this->helpdeskToApiArray($supportHelpdesk);
        $data['client']     = $this->getClientApiArrayForTicket($model);

        if ($deep) {
            $messages = $this->messageGetTicketMessages($model);
            foreach ($messages as $msg) {
                $data['messages'][] = $this->messageToApiArray($msg);
            }
        }

        if ($identity instanceof \Model_Admin) {
            $data['rel']        = $this->_getRelDetails($model);
            $data['priority']   = $model->priority;
            $supportTicketNotes = $this->di['db']->find('SupportTicketNote', 'support_ticket_id = :support_ticket_id', array(':support_ticket_id' => $model->id));

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

            return array();
        }
    }

    public function noteGetAuthorDetails(\Model_SupportTicketNote $model)
    {
        $admin = $this->di['db']->load('Admin', $model->admin_id);

        return array(
            'name'  => $admin->getFullName(),
            'email' => $admin->email,
        );
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
        $data           = $this->di['db']->toArray($model);
        $data['author'] = $this->noteGetAuthorDetails($model);

        return $data;
    }

    public function helpdeskGetSearchQuery($data)
    {
        $query = "SELECT * FROM support_helpdesk";

        $search = $this->di['array_get']($data, 'search', NULL);

        $where    = array();
        $bindings = array();

        if ($search) {
            $search                 = "%" . $search . "%";
            $where[]                = "(name LIKE :name OR email LIKE :email OR signature LIKE :signature)";
            $bindings[':name']      = $search;
            $bindings[':email']     = $search;
            $bindings[':signature'] = $search;
        }

        if (!empty($where)) {
            $query = $query . ' WHERE ' . implode(' AND ', $where);
        }
        $query .= " ORDER BY id DESC";

        return array($query, $bindings);
    }

    public function helpdeskGetPairs()
    {
        return $this->di['db']->getAssoc("SELECT id, name FROM support_helpdesk");;
    }

    public function helpdeskRm(\Model_SupportHelpdesk $model)
    {
        $id = $model->id;

        $tickets = $this->di['db']->find('SupportTicket', 'support_helpdesk_id = :support_helpdesk_id', array(':support_helpdesk_id' => $model->id));
        if (count($tickets) > 0) {
            throw new \Box_Exception('Can not remove helpdesk which has tickets');
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
        return $this->di['db']->find('supportTicketMessage', 'support_ticket_id = :support_ticket_id ORDER BY id ASC', array(':support_ticket_id' => $model->id));
    }

    public function messageGetRepliesCount(\Model_SupportTicket $model)
    {
        $query = "SELECT COUNT(id) as counter
                    FROM support_ticket_message
                    WHERE support_ticket_id = :support_ticket_id
                    GROUP BY support_ticket_id";

        $bindings = array(
            ':support_ticket_id' => $model->id
        );

        return $this->di['db']->getCell($query, $bindings);
    }

    public function messageGetAuthorDetails(\Model_SupportTicketMessage $model)
    {
        if ($model->admin_id) {
            $author = $this->di['db']->load('Admin', $model->admin_id);
        } else {
            $author = $this->di['db']->load('Client', $model->client_id);;
        }

        return array(
            'name'  => $author->getFullName(),
            'email' => $author->email,
        );
    }

    public function messageToApiArray(\Model_SupportTicketMessage $model)
    {
        $data           = $this->di['db']->toArray($model);
        $data['author'] = $this->messageGetAuthorDetails($model);

        return $data;
    }

    public function ticketUpdate(\Model_SupportTicket $model, $data)
    {
        $model->support_helpdesk_id = $this->di['array_get']($data, 'support_helpdesk_id', $model->support_helpdesk_id);
        $model->status = $this->di['array_get']($data, 'status', $model->status);
        $model->subject = $this->di['array_get']($data, 'subject', $model->subject);
        $model->priority = $this->di['array_get']($data, 'priority', $model->priority);
        $model->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($model);

        $this->di['logger']->info('Updated ticket #%s', $model->id);

        return true;
    }

    public function ticketMessageUpdate(\Model_SupportTicketMessage $model, $content)
    {
        $model->content    = $content;
        $model->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($model);

        return true;
    }

    /**
     * @param \Model_Admin $identity
     */
    public function ticketReply(\Model_SupportTicket $ticket, $identity, $content)
    {
        $msg                    = $this->di['db']->dispense('SupportTicketMessage');
        $msg->support_ticket_id = $ticket->id;
        if ($identity instanceof \Model_Admin) {
            $msg->admin_id = $identity->id;
        } elseif ($identity instanceof \Model_Client) {
            $msg->client_id = $identity->id;
        }
        $msg->content    = $content;
        $msg->ip         = $this->di['request']->getClientAddress();
        $msg->created_at = date('Y-m-d H:i:s');
        $msg->updated_at = date('Y-m-d H:i:s');
        $msgId           = $this->di['db']->store($msg);

        if ($identity instanceof \Model_Admin) {
            $ticket->status = \Model_SupportTicket::ONHOLD;
        } elseif ($identity instanceof \Model_Client) {
            $ticket->status = \Model_SupportTicket::OPENED;
        }

        $ticket->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($ticket);

        if ($identity instanceof \Model_Admin) {
            $this->di['events_manager']->fire(array('event' => 'onAfterAdminReplyTicket', 'params' => array('id' => $ticket->id)));
        } elseif ($identity instanceof \Model_Client) {
            $this->di['events_manager']->fire(array('event' => 'onAfterClientReplyTicket', 'params' => array('id' => $ticket->id)));
        }

        $this->di['logger']->info('Replied to ticket "%s"', $ticket->id);

        return $msgId;
    }

    public function ticketCreateForAdmin(\Model_Client $client, \Model_SupportHelpdesk $helpdesk, $data, \Model_Admin $identity)
    {
        $status = $this->di['array_get']($data, 'status', \Model_SupportTicket::ONHOLD);

        $this->di['events_manager']->fire(array('event' => 'onBeforeAdminOpenTicket', 'params' => $data));

        $ticket = $this->di['db']->dispense('SupportTicket');;
        $ticket->client_id           = $client->id;
        $ticket->status              = $status;
        $ticket->subject             = $data['subject'];
        $ticket->support_helpdesk_id = $helpdesk->id;
        $ticket->created_at          = date('Y-m-d H:i:s');
        $ticket->updated_at          = date('Y-m-d H:i:s');
        $ticketId                    = $this->di['db']->store($ticket);

        $msg                    = $this->di['db']->dispense('SupportTicketMessage');
        $msg->admin_id          = $identity->id;
        $msg->support_ticket_id = $ticketId;
        $msg->content           = $data['content'];
        $msg->ip                = $this->di['request']->getClientAddress();
        $msg->created_at        = date('Y-m-d H:i:s');
        $msg->updated_at        = date('Y-m-d H:i:s');
        $this->di['db']->store($msg);


        $this->di['events_manager']->fire(array('event' => 'onAfterAdminOpenTicket', 'params' => array('id' => $ticketId)));

        $this->di['logger']->info('Admin opened new ticket "%s"', $ticketId);

        return (int)$ticketId;
    }

    public function ticketCreateForGuest($data)
    {
        $this->di['validator']->isEmailValid($data['email']);

        $event_params       = $data;
        $event_params['ip'] = $this->di['request']->getClientAddress();
        $altered            = $this->di['events_manager']->fire(array('event' => 'onBeforeGuestPublicTicketOpen', 'params' => $event_params));

        $status = 'open';
        $subject = $this->di['array_get']($data, 'subject');
        $message = $this->di['array_get']($data, 'message');

        if (is_array($altered)){
            $status  = $this->di['array_get']($altered, 'status');
            $subject = $this->di['array_get']($altered, 'subject');
            $message = $this->di['array_get']($altered, 'message');
        }

        $ticket               = $this->di['db']->dispense('SupportPTicket');
        $ticket->hash         = sha1(uniqid());
        $ticket->author_name  = $data['name'];
        $ticket->author_email = $data['email'];
        $ticket->subject      = $subject;
        $ticket->status       = $status;
        $ticket->created_at   = date('Y-m-d H:i:s');
        $ticket->updated_at   = date('Y-m-d H:i:s');
        $ticketId             = $this->di['db']->store($ticket);

        $msg                      = $this->di['db']->dispense('SupportPTicketMessage');
        $msg->support_p_ticket_id = $ticket->id;
        $msg->content             = $message;
        $msg->ip                  = $this->di['request']->getClientAddress();
        $msg->created_at          = date('Y-m-d H:i:s');
        $msg->updated_at          = date('Y-m-d H:i:s');
        $this->di['db']->store($msg);

        $this->di['events_manager']->fire(array('event' => 'onAfterGuestPublicTicketOpen', 'params' => array('id' => $ticketId)));

        $this->di['logger']->info('"%s" opened public ticket "%s"', $ticket->author_email, $ticketId);

        return $ticket->hash;
    }

    public function canClientSubmitNewTicket(\Model_Client $client, array $config)
    {
        $hours = $config['wait_hours'];

        $lastTicket = $this->di['db']->findOne('SupportTicket', 'client_id = :client_id ORDER BY created_at DESC', array(':client_id' => $client->id));
        if (!$lastTicket instanceof \Model_SupportTicket) {
            return true;
        }

        $timeSinceLast = round(abs(strtotime($lastTicket->created_at) - strtotime(date('Y-m-d H:i:s'))) / 3600, 0);

        if ($timeSinceLast < $hours) {
            throw new \Box_Exception(sprintf('You can submit one ticket per %s hours. %s hours left', $hours, $hours - $timeSinceLast));
        }

        return true;
    }

    public function ticketCreateForClient(\Model_Client $client, \Model_SupportHelpdesk $helpdesk, array $data)
    {
        //@todo validate task params
        $rel_id        = $this->di['array_get']($data, 'rel_id', NULL);
        $rel_type      = $this->di['array_get']($data, 'rel_type', NULL);


        if (!is_null($rel_id) && $rel_type == \Model_SupportTicket::REL_TYPE_ORDER) {
            $orderService = $this->di['mod_service']('order');
            $o            = $orderService->findForClientById($client, $rel_id);
            if (!$o instanceof \Model_ClientOrder) {
               throw new \Box_Exception('Order ID does not exist');
            }
        }

        $rel_task      = $this->di['array_get']($data, 'rel_task', NULL);
        $rel_new_value = $this->di['array_get']($data, 'rel_new_value', NULL);
        $rel_status    = isset($data['rel_task']) ? \Model_SupportTicket::REL_STATUS_PENDING : \Model_SupportTicket::REL_STATUS_COMPLETE;

        // check if support ticket with same uncompleted task already exists
        if ($rel_id && $rel_type && $rel_task && $this->checkIfTaskAlreadyExists($client, $rel_id, $rel_type, $rel_task)) {
            throw new \Box_Exception('We have already received this request.');
        }

        $mod    = $this->di['mod']('support');
        $config = $mod->getConfig();

        if (isset($config['wait_hours']) && is_numeric($config['wait_hours'])) {
            $this->canClientSubmitNewTicket($client, $config);
        }

        $event_params              = $data;
        $event_params['client_id'] = $client->id;
        $this->di['events_manager']->fire(array('event' => 'onBeforeClientOpenTicket', 'params' => $event_params));


        $ticket                      = $this->di['db']->dispense('SupportTicket');
        $ticket->client_id           = $client->id;
        $ticket->subject             = $data['subject'];
        $ticket->support_helpdesk_id = $helpdesk->id;
        $ticket->created_at          = date('Y-m-d H:i:s');
        $ticket->updated_at          = date('Y-m-d H:i:s');

        // related task with ticket
        $ticket->rel_id        = $rel_id;
        $ticket->rel_type      = $rel_type;
        $ticket->rel_task      = $rel_task;
        $ticket->rel_new_value = $rel_new_value;
        $ticket->rel_status    = $rel_status;

        $ticketId = $this->di['db']->store($ticket);

        $this->messageCreateForTicket($ticket, $client, $data['content']);

        $this->di['events_manager']->fire(array('event' => 'onAfterClientOpenTicket', 'params' => array('id' => $ticket->id)));

        if (isset($config['autorespond_enable'])
            && $config['autorespond_enable']
            && isset($config['autorespond_message_id'])
            && !empty($config['autorespond_message_id'])
        ) {
            $this->cannedReply($ticket, $config['autorespond_message_id']);
        }

        $this->di['logger']->info('Submitted new ticket "%s"', $ticketId);

        return (int)$ticketId;
    }

    private function cannedReply(\Model_SupportTicket $ticket, $cannedId)
    {
        try {
            $cannedObj    = $this->di['db']->getExistingModelById('SupportPr', $cannedId, 'Canned reply not found');
            $canned       = $this->cannedToApiArray($cannedObj);
            $staffService = $this->di['mod_service']('staff');
            $admin        = $staffService->getCronAdmin();
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
        $msg                    = $this->di['db']->dispense('SupportTicketMessage');
        $msg->support_ticket_id = $ticket->id;
        if ($identity instanceof \Model_Admin) {
            $msg->admin_id = $identity->id;
        } elseif ($identity instanceof \Model_Client) {
            $msg->client_id = $identity->id;
        } else {
            throw new \Box_Exception('Identity is not valid');
        }
        $msg->content    = $content;
        $msg->ip         = $this->di['request']->getClientAddress();
        $msg->created_at = date('Y-m-d H:i:s');
        $msg->updated_at = date('Y-m-d H:i:s');

        return $this->di['db']->store($msg);
    }

    public function publicGetStatuses()
    {
        $data = array(
            \Model_SupportPTicket::OPENED => 'Open',
            \Model_SupportPTicket::ONHOLD => 'On hold',
            \Model_SupportPTicket::CLOSED => 'Closed',
        );

        return $data;
    }

    public function publicFindOneByHash($hash)
    {
        $bindings = array(
            ':hash' => $hash
        );

        $publicTicket = $this->di['db']->findOne('SupportPTicket', 'hash = :hash', $bindings);
        if (!$publicTicket instanceof \Model_SupportPTicket) {
            throw new \Box_Exception('Public ticket not found');
        }

        return $publicTicket;
    }

    public function publicGetSearchQuery($data)
    {
        $query = "SELECT spt.* FROM support_p_ticket spt
        LEFT JOIN support_p_ticket_message sptm
        ON spt.id = sptm.support_p_ticket_id";

        $search = $this->di['array_get']($data, 'search', NULL);

        $id      = $this->di['array_get']($data, 'id', NULL);
        $status  = $this->di['array_get']($data, 'status', NULL);
        $name    = $this->di['array_get']($data, 'name', NULL);
        $email   = $this->di['array_get']($data, 'email', NULL);
        $subject = $this->di['array_get']($data, 'subject', NULL);
        $content = $this->di['array_get']($data, 'content', NULL);

        $where    = array();
        $bindings = array();

        if ($id) {
            $where[]                  = "spt.id  = :p_ticket_id";
            $bindings[':p_ticket_id'] = $id;
        }

        if ($status) {
            $where[]                      = "spt.status  = :p_ticket_status";
            $bindings[':p_ticket_status'] = $status;
        }

        if ($email) {
            $where[]                            = "spt.author_email  = :p_ticket_author_email";
            $bindings[':p_ticket_author_email'] = $email;
        }

        if ($name) {
            $where[]                           = "spt.author_name  = :p_ticket_author_name";
            $bindings[':p_ticket_author_name'] = $name;
        }

        if ($content) {
            $where[]                       = "spt.content LIKE :p_ticket_content";
            $bindings[':p_ticket_content'] = "%$content%";
        }

        if ($subject) {
            $where[]                       = "spt.subject LIKE :p_ticket_subject";
            $bindings[':p_ticket_subject'] = "%$subject%";
        }

        //smartSearch
        if ($search) {
            if (is_numeric($search)) {
                $where[]                  = "spt.id = :p_ticket_id";
                $bindings[':p_ticket_id'] = $search;
            } else {
                $search                         = "%" . $search . "%";
                $where[]                        = "sptm.content LIKE :p_message_content OR spt.subject LIKE :p_ticket_subject";
                $bindings[':p_message_content'] = $search;
                $bindings[':p_ticket_subject']  = $search;
            }
        }

        if (!empty($where)) {
            $query = $query . ' WHERE ' . implode(' AND ', $where);
        }

        $query .= " GROUP BY spt.id, sptm.id ORDER BY spt.id DESC, sptm.id ASC";

        return array($query, $bindings);
    }

    public function publicCounter()
    {
        $query = "SELECT status, COUNT(id) as counter
                FROM support_p_ticket
                GROUP BY status";

        $data = $this->di['db']->getAssoc($query);

        return array(
            'total'                       => array_sum($data),
            \Model_SupportPTicket::OPENED => isset($data[\Model_SupportPTicket::OPENED]) ? $data[\Model_SupportPTicket::OPENED] : 0,
            \Model_SupportPTicket::CLOSED => isset($data[\Model_SupportPTicket::CLOSED]) ? $data[\Model_SupportPTicket::CLOSED] : 0,
            \Model_SupportPTicket::ONHOLD => isset($data[\Model_SupportPTicket::ONHOLD]) ? $data[\Model_SupportPTicket::ONHOLD] : 0,
        );
    }

    public function publicGetLatest()
    {
        return $this->di['db']->find('SupportPTicket', 'ORDER BY id DESC Limit 10');
    }

    public function publicCountByStatus($status)
    {
        $query = "SELECT COUNT(id) as counter
                FROM support_p_ticket
                WHERE status = :status
                GROUP BY status";

        return $this->di['db']->getCell($query, array(':status' => $status));
    }

    public function publicGetExpired()
    {
        $bindings = array(
            ':status' => \Model_SupportPTicket::ONHOLD
        );

        $publicTicket = $this->di['db']->find('SupportPTicket', 'status = :status AND DATE_ADD(updated_at, INTERVAL 48 HOUR) < NOW() ORDER BY id ASC', $bindings);

        return $publicTicket;
    }

    public function publicCloseTicket(\Model_SupportPTicket $model, $identity)
    {
        $model->status     = 'closed';
        $model->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($model);

        if ($identity instanceof \Model_Admin) {
            $this->di['events_manager']->fire(array('event' => 'onAfterAdminPublicTicketClose', 'params' => array('id' => $model->id)));
            $this->di['logger']->info('Public Ticket %s was closed', $model->id);
        } elseif ($identity instanceof \Model_Guest) {
            $this->di['events_manager']->fire(array('event' => 'onAfterGuestPublicTicketClose', 'params' => array('id' => $model->id)));
            $this->di['logger']->info('"%s" closed public ticket "%s"', $model->author_email, $model->id);

        }

        return true;
    }

    public function publicAutoClose(\Model_SupportPTicket $model)
    {
        $model->status     = 'closed';
        $model->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($model);

        $this->di['logger']->info('Public Ticket %s was closed', $model->id);

        return TRUE;
    }

    public function publicRm(\Model_SupportPTicket $model)
    {
        $id       = $model->id;
        $bindings = array(
            ':support_p_ticket_id' => $model->id
        );
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
        $data        = $this->di['db']->toArray($model);
        $messages    = array();
        $messagesArr = $this->di['db']->find('SupportPTicketMessage', 'support_p_ticket_id = :support_p_ticket_id ORDER BY id', array(':support_p_ticket_id' => $model->id));
        foreach ($messagesArr as $msg) {
            $messages[] = $this->publicMessageToApiArray($msg);
        }

        $first = reset($messagesArr);
        if ($first instanceof \Model_SupportPTicketMessage) {
            $data['author'] = $this->publicMessageGetAuthorDetails($first);
        } else {
            $data['author'] = array();
        }
        $data['messages'] = $messages;

        return $data;
    }

    public function publicMessageGetAuthorDetails(\Model_SupportPTicketMessage $model)
    {
        if ($model->admin_id) {
            $author = $this->di['db']->getExistingModelById('Admin', $model->admin_id);

            return array(
                'name'  => $author->getFullName(),
                'email' => $author->email,
            );
        }

        $ticket = $this->di['db']->getExistingModelById('SupportPTicket', $model->support_p_ticket_id);

        return array(
            'name'  => $ticket->author_name,
            'email' => $ticket->author_email,
        );
    }

    public function publicMessageToApiArray(\Model_SupportPTicketMessage $model, $deep = true)
    {
        $data           = $this->di['db']->toArray($model);
        $data['author'] = $this->publicMessageGetAuthorDetails($model);

        return $data;
    }

    public function publicTicketCreate($data, \Model_Admin $identity)
    {
        $this->di['validator']->isEmailValid($data['email']);

        $this->di['events_manager']->fire(array('event' => 'onBeforeAdminPublicTicketOpen', 'params' => $data));

        $ticket               = $this->di['db']->dispense('SupportPTicket');
        $ticket->hash         = sha1(uniqid());
        $ticket->author_name  = $data['name'];
        $ticket->author_email = $data['email'];
        $ticket->subject      = $data['subject'];
        $ticket->status       = 'open';
        $ticket->created_at   = date('Y-m-d H:i:s');
        $ticket->updated_at   = date('Y-m-d H:i:s');
        $ticketId             = $this->di['db']->store($ticket);

        $msg                      = $this->di['db']->dispense('SupportPTicketMessage');
        $msg->support_p_ticket_id = $ticketId;
        $msg->admin_id            = $identity->id;
        $msg->content             = $data['message'];
        $msg->ip                  = $this->di['request']->getClientAddress();
        $msg->created_at          = date('Y-m-d H:i:s');
        $msg->updated_at          = date('Y-m-d H:i:s');
        $this->di['db']->store($msg);

        $this->di['events_manager']->fire(array('event' => 'onAfterAdminPublicTicketOpen', 'params' => array('id' => $ticketId)));

        $this->di['logger']->info('Opened public ticket for email "%s"', $ticket->author_email);

        return $ticketId;
    }

    public function publicTicketUpdate(\Model_SupportPTicket $model, $data)
    {
        $model->subject = $this->di['array_get']($data, 'subject', $model->subject);
        $model->status = $this->di['array_get']($data, 'status', $model->status);
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        $this->di['logger']->info('Updated public ticket #%s', $model->id);

        return true;
    }

    public function publicTicketReply(\Model_SupportPTicket $ticket, \Model_Admin $identity, $content)
    {
        $msg                      = $this->di['db']->dispense('SupportPTicketMessage');
        $msg->support_p_ticket_id = $ticket->id;
        $msg->admin_id            = $identity->id;
        $msg->content             = $content;
        $msg->ip                  = $this->di['request']->getClientAddress();
        $msg->created_at          = date('Y-m-d H:i:s');
        $msg->updated_at          = date('Y-m-d H:i:s');
        $messageId                = $this->di['db']->store($msg);

        $ticket->status     = \Model_SupportPTicket::ONHOLD;
        $ticket->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($ticket);

        $this->di['events_manager']->fire(array('event' => 'onAfterAdminPublicTicketReply', 'params' => array('id' => $ticket->id)));

        $this->di['logger']->info('Replied to public ticket "%s"', $ticket->id);

        return $messageId;
    }

    public function publicTicketReplyForGuest(\Model_SupportPTicket $ticket, $message)
    {
        $msg                      = $this->di['db']->dispense('SupportPTicketMessage');
        $msg->support_p_ticket_id = $ticket->id;
        $msg->content             = $message;
        $msg->ip                  = $this->di['request']->getClientAddress();
        $msg->created_at          = date('Y-m-d H:i:s');
        $msg->updated_at          = date('Y-m-d H:i:s');
        $this->di['db']->store($msg);

        $ticket->status     = \Model_SupportPTicket::OPENED;
        $ticket->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($ticket);

        $this->di['events_manager']->fire(array('event' => 'onAfterGuestPublicTicketReply', 'params' => array('id' => $ticket->id)));

        $this->di['logger']->info('Client "%s" replied to public ticket "%s"', $ticket->author_email, $ticket->id);

        return $ticket->hash;
    }

    public function helpdeskUpdate(\Model_SupportHelpdesk $model, $data)
    {
        $model->name = $this->di['array_get']($data, 'name', $model->name);
        $model->email = $this->di['array_get']($data, 'email', $model->email);
        $model->can_reopen = $this->di['array_get']($data, 'can_reopen', $model->can_reopen);
        $model->close_after = $this->di['array_get']($data, 'close_after', $model->close_after);
        $model->signature = $this->di['array_get']($data, 'signature', $model->signature);
        $model->updated_at = date('Y-m-d H:i:s');
        $id                = $this->di['db']->store($model);

        $this->di['logger']->info('Updated helpdesk #%s', $id);

        return true;
    }

    public function helpdeskCreate($data)
    {
        $model              = $this->di['db']->dispense('SupportHelpdesk');
        $model->name        = $data['name'];
        $model->email       = $this->di['array_get']($data, 'email', NULL);
        $model->can_reopen  = $this->di['array_get']($data, 'can_reopen', NULL);
        $model->close_after = $this->di['array_get']($data, 'close_after', NULL);
        $model->signature   = $this->di['array_get']($data, 'signature', NULL);
        $model->created_at  = date('Y-m-d H:i:s');
        $model->updated_at  = date('Y-m-d H:i:s');
        $id                 = $this->di['db']->store($model);

        $this->di['logger']->info('Created helpdesk #%s', $id);

        return $id;
    }

    public function cannedGetSearchQuery($data)
    {
        $query = "SELECT sp.* FROM support_pr sp
                LEFT JOIN support_pr_category spc
                ON spc.id = sp.support_pr_category_id";

        $search = $this->di['array_get']($data, 'search', NULL);

        $where    = array();
        $bindings = array();

        if ($search) {
            $search               = "%" . $search . "%";
            $where[]              = "title LIKE :title OR content LIKE :content";
            $bindings[':title']   = $search;
            $bindings[':content'] = $search;
        }

        if (!empty($where)) {
            $query = $query . ' WHERE ' . implode(' AND ', $where);
        }

        $query .= " ORDER BY sp.support_pr_category_id ASC";

        return array($query, $bindings);
    }

    public function cannedGetGroupedPairs()
    {
        $query = "SELECT sp.title as r_title, spc.title as c_title FROM support_pr sp
                LEFT JOIN support_pr_category spc
                ON spc.id = sp.support_pr_category_id";

        $data = $this->di['db']->getAll($query);
        $res  = array();
        foreach ($data as $r) {
            $res[$r['c_title']][$r['id']] = $r['r_title'];
        }

        return $res;
    }

    public function cannedRm(\Model_SupportPr $model)
    {
        $id = $model->id;

        $this->di['db']->trash($model);

        $this->di['logger']->info('Deleted canned reponse #%s', $id);

        return true;
    }

    public function cannedToApiArray(\Model_SupportPr $model)
    {
        $result   = $this->di['db']->toArray($model);
        $category = $this->di['db']->load('SupportPrCategory', $model->support_pr_category_id);
        if ($category instanceof \Model_SupportPrCategory) {
            $result['category'] = array(
                'id'    => $category->id,
                'title' => $category->title,
            );
        } else {
            $result['category'] = array();
        }

        return $result;
    }

    public function cannedCategoryGetPairs()
    {
        return $this->di['db']->getAssoc("SELECT id, title FROM support_pr_category");
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

        $model                         = $this->di['db']->dispense('SupportPr');
        $model->support_pr_category_id = $categoryId;
        $model->title                  = $title;
        $model->content                = $content;
        $model->created_at             = date('Y-m-d H:i:s');
        $model->updated_at             = date('Y-m-d H:i:s');
        $id                            = $this->di['db']->store($model);

        $this->di['logger']->info('Created new canned response #%s', $id);

        return $id;
    }

    public function cannedUpdate(\Model_SupportPr $model, $data)
    {
        $model->support_pr_category_id = $this->di['array_get']($data, 'category_id', $model->support_pr_category_id);
        $model->title = $this->di['array_get']($data, 'title', $model->title);
        $model->content = $this->di['array_get']($data, 'content', $model->content);
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        $this->di['logger']->info('Updated canned response #%s', $model->id);

        return true;
    }

    public function cannedCategoryCreate($title)
    {
        $model             = $this->di['db']->dispense('SupportPrCategory');
        $model->title      = $title;
        $model->created_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');
        $id                = $this->di['db']->store($model);

        $this->di['logger']->info('Created new canned response category #%s', $id);

        return $id;
    }

    public function cannedCategoryUpdate(\Model_SupportPrCategory $model, $title)
    {
        $model->title      = $title;
        $model->updated_at = date('Y-m-d H:i:s');
        $id                = $this->di['db']->store($model);

        $this->di['logger']->info('Updated canned response category #%s', $id);

        return true;
    }

    public function noteCreate(\Model_SupportTicket $ticket, \Model_Admin $identity, $note)
    {
        $model                    = $this->di['db']->dispense('SupportTicketNote');
        $model->support_ticket_id = $ticket->id;
        $model->admin_id          = $identity->id;
        $model->note              = $note;
        $model->created_at        = date('Y-m-d H:i:s');
        $model->updated_at        = date('Y-m-d H:i:s');
        $id                       = $this->di['db']->store($model);

        $this->di['logger']->info('Added note to ticket #%s', $id);

        return $id;
    }

    public function ticketTaskComplete(\Model_SupportTicket $model)
    {
        $model->rel_status = \Model_SupportTicket::REL_STATUS_COMPLETE;
        $model->updated_at = date('Y-m-d H:i:s');
        $id                = $this->di['db']->store($model);

        $this->di['logger']->info('Marked ticket #%s task as complete', $id);

        return true;
    }

}
