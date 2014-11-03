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


class Model_ActivityClientEmailTable implements \Box\InjectionAwareInterface
{
    /**
     * @var \Box_Di
     */
    protected $di;

    /**
     * @param Box_Di $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return Box_Di
     */
    public function getDi()
    {
        return $this->di;
    }

    /**
     * @param array $data
     */
    public function logEvent($data)
    {
        $entry = $this->di['db']->dispense('ActivityClientEmail');
        $entry->client_id       = isset($data['client_id']) ? $data['client_id'] : NULL;
        $entry->sender          = isset($data['sender']) ? $data['sender'] : NULL;
        $entry->recipients      = isset($data['recipients']) ? $data['recipients'] : NULL;
        $entry->subject         = isset($data['subject']) ? $data['subject'] : NULL;
        $entry->content_html    = isset($data['content_html']) ? $data['content_html'] : NULL;
        $entry->content_text    = isset($data['content_text']) ? $data['content_text'] : NULL;
        $entry->created_at      = isset($data['timestamp']) ? $data['timestamp'] : NULL;
        $entry->updated_at      = isset($data['timestamp']) ? $data['timestamp'] : NULL;
        $this->di['db']->store($entry);
    }
}