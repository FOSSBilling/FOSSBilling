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


namespace Box\Mod\Massmailer\Api;

class Admin extends \Api_Abstract
{
    /**
     * Get paginated list of active mail messages
     *
     * @optional string $status - filter list by status
     * @optional string $search - search query to search for mail messages
     *
     * @return array
     */
    public function get_list($data)
    {
        list($sql, $params) = $this->getService()->getSearchQuery($data);
        $per_page = $this->di['array_get']($data, 'per_page', $this->di['pager']->getPer_page());
        $pager = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);
        foreach ($pager['list'] as $key => $item) {
            $pager['list'][$key] = $this->getService()->toApiArray($item);
        }
        return $pager;
    }

    /**
     * Get mail message by id
     *
     * @param int $id - mail message ID
     *
     * @return array
     */
    public function get($data)
    {
        $model = $this->_getMessage($data);
        return $this->getService()->toApiArray($model);
    }

    /**
     * Update mail message
     *
     * @param int $id - mail message id
     *
     * @optional string $subject - mail message title
     * @optional string $content - mail message content
     * @optional string $status - mail message status
     * @optional string $from_name - mail message email from name
     * @optional string $from_email - mail message email from email
     * @optional array $filter  - filter parameters to select clients
     *
     * @return bool
     */
    public function update($data)
    {
        $model = $this->_getMessage($data);

        $model->content = $this->di['array_get']($data, 'content', $model->content);
        $model->subject = $this->di['array_get']($data, 'subject', $model->subject);
        $model->status = $this->di['array_get']($data, 'status', $model->status);
        if(isset($data['filter'])) {
            $model->filter = json_encode($data['filter']);
        }

        if(isset($data['from_name'])) {
            if(empty($data['from_name'])) {
                throw new \Box_Exception('Message from name can not be empty');
            }
            $model->from_name = $data['from_name'];
        }

        if(isset($data['from_email'])) {
            $this->di['validator']->isEmailValid($data['from_email']);
            $model->from_email = $data['from_email'];
        }

        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        $this->di['logger']->info('Updated mail message #%s', $model->id);
        return TRUE;
    }

    /**
     * Create mail message
     *
     * @param string $subject - mail message subject
     *
     * @optional string $content - mail message content
     *
     * @return bool
     */
    public function create($data)
    {
        $required = array(
            'subject' => 'Message subject not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $default_content = '{% apply markdown %}
Hi {{ c.first_name }} {{ c.last_name }},

Your email is: {{ c.email }}

Aenean vut sagittis in natoque tortor. Facilisis magnis duis nec eros! Augue 
sed quis tortor porttitor? Rhoncus tortor pid et a enim dis adipiscing eros 
facilisis nunc. Phasellus dis odio lacus pulvinar vel lundium dapibus turpis.

Urna parturient, ultricies nascetur? Et a. Elementum in dapibus ut vel ut 
magna tempor, dapibus lacus sed? Ut velit dignissim placerat, tristique pid 
vut amet et nunc! Elementum dolor, dictumst porta ultrices. Rhoncus, amet. 

Order our services at {{ "order"|link }}

{{ guest.system_company.name }} - {{ guest.system_company.signature }}
{% endapply %}
        ';
        $systemService = $this->di['mod_service']('system');
        $company = $systemService->getCompany();

        $model = $this->di['db']->dispense('mod_massmailer');
        $model->from_email = $company['email'];
        $model->from_name = $company['name'];
        $model->subject = $data['subject'];
        $model->content = isset($data['content']) ? $data['content'] : $default_content;
        $model->status = 'draft';
        $model->created_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');

        $id = $this->di['db']->store($model);

        $this->di['logger']->info('Created mail message #%s', $model->id);
        return $id;
    }

    /**
     * Send test mail message by ID to client
     *
     * @param int $id - mail message ID
     *
     * @return bool
     */
    public function send_test($data)
    {
        $model = $this->_getMessage($data);
        $client_id = $this->_getTestClientId();

        if(empty($model->content)) {
            throw new \Box_Exception('Add some content before sending message');
        }

        $this->getService()->sendMessage($model, $client_id);

        $this->di['logger']->info('Sent test mail message #%s to client ', $model->id);
        return true;
    }

    /**
     * Send mail message by ID
     *
     * @param int $id - mail message ID
     *
     * @return bool
     */
    public function send($data)
    {
        $model = $this->_getMessage($data);

        if(empty($model->content)) {
            throw new \Box_Exception('Add some content before sending message');
        }

        $mod = $this->di['mod']('massmailer');
        $c = $mod->getConfig();
        $interval = isset($c['interval']) ? (int)$c['interval'] : 30;
        $max = isset($c['limit']) ? (int)$c['limit'] : 25;

        $clients = $this->getService()->getMessageReceivers($model, $data);
        foreach($clients as $c) {
            $d = array(
                'queue'     => 'massmailer',
                'mod'       => 'massmailer',
                'handler'   => 'sendMail',
                'max'       => $max,
                'interval'  => $interval,
                'params'    => array('msg_id'=>$model->id, 'client_id'=>$c['id']),
            );
            $this->di['api_admin']->queue_message_add($d);
        }

        $model->status = 'sent';
        $model->sent_at = date('Y-m-d H:i:s');
        $id = $this->di['db']->store($model);

        $this->di['logger']->info('Added mass mail messages #%s to queue', $id);
        return true;
    }

    /**
     * Copy mail message by ID
     *
     * @param int $id - mail message ID
     *
     * @return bool
     */
    public function copy($data)
    {
        $model = $this->_getMessage($data);

        $copy             = $this->di['db']->dispense('mod_massmailer');
        $copy->from_email = $model->from_email;
        $copy->from_name  = $model->from_name;
        $copy->subject    = $model->subject . ' (Copy)';
        $copy->content    = $model->content;
        $copy->filter     = $model->filter;
        $copy->status     = 'draft';
        $copy->created_at = date('Y-m-d H:i:s');
        $copy->updated_at = date('Y-m-d H:i:s');

        $copyId = $this->di['db']->store($copy);

        $this->di['logger']->info('Copied mail message #%s to #%s', $model->id, $copyId);

        return $copyId;
    }

    /**
     * Get message receivers list
     *
     * @param int $id - mail message ID
     *
     * @return array
     */
    public function receivers($data)
    {
        $model = $this->_getMessage($data);
        return $this->getService()->getMessageReceivers($model, $data);
    }

    /**
     * Delete mail message by ID
     *
     * @param int $id - mail message ID
     *
     * @return bool
     */
    public function delete($data)
    {
        $model = $this->_getMessage($data);
        $id = $model->id;

        $this->di['db']->trash($model);

        $this->di['logger']->info('Removed mail message #%s', $id);
        return true;
    }

    /**
     * Generate preview text
     *
     * @param int $id - message id
     *
     * @return array - parsed subject and content strings
     */
    public function preview($data)
    {
        $model = $this->_getMessage($data);
        $client_id = $this->_getTestClientId();
        list($ps, $pc) = $this->getService()->getParsed($model, $client_id);
        return array(
            'subject'   =>  $ps,
            'content'   =>  $pc,
        );
    }

    private function _getTestClientId()
    {
        $mod = $this->di['mod']('massmailer');
        $c = $mod->getConfig();

        $required = array(
            'test_client_id' => 'Client ID needs to be configured in mass mailer settings.',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $c);

        return (int)$c['test_client_id'];
    }

    private function _getMessage($data)
    {
        $required = array(
            'id' => 'Message ID not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('mod_massmailer', $data['id'], 'Message not found');

        return $model;
    }
}