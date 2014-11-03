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


class Model_SupportPTicketMessageTable
{
    public function getAuthorDetails(Model_SupportPTicketMessage $model)
    {
        if($model->admin_id) {
            $author = $model->Admin;
            return array(
                'name'   =>  $author->getFullName(),
                'email'  =>  $author->email,
            );
        }

        $ticket = $model->SupportPTicket;
        return array(
            'name'   =>  $ticket->author_name,
            'email'  =>  $ticket->author_email,
        );
    }

    public function toApiArray(Model_SupportPTicketMessage $model, $deep = true)
    {
        $data = $model->toArray(false);
        $data['author'] = $this->getAuthorDetails($model);
        return $data;
    }
}