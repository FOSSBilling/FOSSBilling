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

/**
 *Email logs and templates management
 */
namespace Box\Mod\Email\Api;

class Admin extends \Api_Abstract
{
    /**
     * Get list of sent emails
     *
     * @return array
     */
    public function email_get_list($data)
    {
        $per_page = $this->di['array_get']($data, 'per_page', $this->di['pager']->getPer_page());
        list($sql, $params) = $this->getService()->getSearchQuery($data);
        $pager = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);

        foreach ($pager['list'] as $key => $item) {
            $pager['list'][$key] = array(
                'id'           => $item['id'],
                'client_id'    => $item['client_id'],
                'sender'       => $item['sender'],
                'recipients'   => $item['recipients'],
                'subject'      => $item['subject'],
                'content_html' => $item['content_html'],
                'content_text' => $item['content_text'],
                'created_at'   => $item['created_at'],
                'updated_at'   => $item['updated_at'],
            );
        }

        return $pager;
    }

    /**
     * Get sent email details
     *
     * @param int $id - email ID
     * @return array
     * @throws Exception
     * @throws LogicException
     */
    public function email_get($data)
    {
        $required = array(
            'id' => 'Email ID is required',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $service = $this->getService();
        $model = $service->getEmailById($data['id']);
        return $service->toApiArray($model);
    }

    /**
     * Email send
     *
     * @param string $to - email to
     * @param string $to_name - to name
     * @param string $from_name - from name
     * @param string $from - from email
     * @param string $subject - from email
     * @param string $content - from email
     *
     * @optional int $client_id - log this message to client history
     *
     * @return bool
     * @throws Exception
     */
    public function send($data = array())
    {
        $required = array(
            'to' => 'Receiver Email is required',
            'to_name' => 'Receiver Name is required',
            'from' => 'Sender Name is required',
            'from_name' => 'Sender email is required',
            'subject' => 'Email subject is required',
            'content' => 'Email content is required',
        );
        $this->di['validator']->checkRequiredParamsForArray($required,  $data);
        $client_id = $this->di['array_get']($data, 'client_id');
        $emailService = $this->getService();

        return $emailService->sendMail(
            $this->di['array_get']($data, 'to'),
            $this->di['array_get']($data, 'from'),
            $this->di['array_get']($data, 'subject'),
            $this->di['array_get']($data, 'content'),
            $this->di['array_get']($data, 'to_name'),
            $this->di['array_get']($data, 'from_name'),
            $client_id
        );
    }

    /**
     * Resend email
     *
     * @param int $id - email ID
     * @return bool
     * @throws Exception
     * @throws LogicException
     */
    public function email_resend($data)
    {
        $required = array(
            'id' => 'Email ID is required',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->findOne('ActivityClientEmail', 'id = ?', array($data['id']));

        if (!$model instanceof \Model_ActivityClientEmail) {
            throw new \Box_Exception('Email not found');
        }

        return $this->getService()->resend($model);
    }

    /**
     * Delete sent email from logs
     *
     * @param int $id - email ID
     * @return bool
     * @throws Exception
     * @throws LogicException
     */
    public function email_delete($data)
    {
        $required = array(
            'id' => 'Email ID is required',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->findOne('ActivityClientEmail', 'id = ?', array($data['id']));

        if (!$model instanceof \Model_ActivityClientEmail) {
            throw new \Box_Exception('Email not found');
        }

        $id = $model->id;
        $this->di['db']->trash($model);

        $this->di['logger']->info('Deleted email #%s', $id);

        return true;
    }

    /**
     * Return list of email templates
     *
     * @return array
     */
    public function template_get_list($data)
    {
        $per_page = $this->di['array_get']($data, 'per_page', $this->di['pager']->getPer_page());
        list($sql, $params) = $this->getService()->templateGetSearchQuery($data);
        $pager = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);

        foreach ($pager['list'] as $key => $item) {
            $pager['list'][$key] = array(
                'id'          => $this->di['array_get']($item, 'id', ''),
                'action_code' => $this->di['array_get']($item, 'action_code', ''),
                'category'    => $this->di['array_get']($item, 'category', ''),
                'enabled'     => $this->di['array_get']($item, 'enabled', ''),
                'subject'     => $this->di['array_get']($item, 'subject', ''),
                'description' => $this->di['array_get']($item, 'description', ''),
            );

        }

        return $pager;
    }

    /**
     * Get email template full details
     *
     * @param int $id - template id
     * @return array
     * @throws Exception
     * @throws LogicException
     */
    public function template_get($data)
    {
        $required = array(
            'id' => 'Email ID is required',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('EmailTemplate', $data['id'], 'Email template not found');

        return $this->getService()->templateToApiArray($model, true);
    }

    /**
     * Delete email template
     *
     * @param int $id - email template ID
     * @return bool
     * @throws Exception
     * @throws LogicException
     */
    public function template_delete($data)
    {
        $required = array(
            'id' => 'Email ID is required',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->findOne('EmailTemplate', 'id = ?', array($data['id']));

        if (!$model instanceof \Model_EmailTemplate) {
            throw new \Box_Exception('Email template not found');
        }

        $id = $model->id;
        $this->di['db']->trash($model);

        $this->di['logger']->info('Deleted email template #%s', $id);

        return true;
    }

    /**
     * Create new email template. Creating new email template can be
     * combined with custom event hook.
     *
     * @param string $action_code - template action code
     * @param string $subject - Email subject
     * @param string $content - Email body
     *
     * @return int - newly created template id
     * @throws Exception
     */
    public function template_create($data)
    {
        $required = array(
            'action_code' => 'Email template code is required',
            'subject'     => 'Email template subject is required',
            'content'     => 'Email template content is required',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $enabled  = $this->di['array_get']($data, 'enabled', 0);
        $category = $this->di['array_get']($data, 'category');

        $templateModel = $this->getService()->templateCreate($data['action_code'], $data['subject'], $data['content'], $enabled, $category);

        return $templateModel->id;
    }

    /**
     * Update email template
     *
     * @param int $id - template id
     * @return boolean
     * @throws Exception
     * @throws LogicException
     */
    public function template_update($data)
    {
        $required = array(
            'id' => 'Email ID is required',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $enabled  = $this->di['array_get']($data, 'enabled');
        $category = $this->di['array_get']($data, 'category');
        $subject  = $this->di['array_get']($data, 'subject');
        $content  = $this->di['array_get']($data, 'content');

        $model = $this->di['db']->getExistingModelById('EmailTemplate', $data['id'], 'Email template not found');

        return $this->getService()->updateTemplate($model, $enabled, $category, $subject, $content);
    }


    /**
     * Reset email template to default
     *
     * @param string $code - template code
     * @return boolean
     */
    public function template_reset($data)
    {
        $required = array(
            'code' => 'Email template code was not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        return $this->getService()->resetTemplateByCode($data['code']);
    }

    /**
     * Generates email template preview
     *
     * @param int $id - template id
     * @optional string $_tpl - string to be rendered. Default is email template.
     * @return boolean
     */
    public function template_render($data)
    {
        $t            = $this->template_get($data);
        $vars         = $t['vars'];
        $vars['_tpl'] = $this->di['array_get']($data, '_tpl', $t['content']);
        $systemService = $this->di['mod_service']('System');
        return $systemService->renderString($vars['_tpl'], true, $vars);
    }

    /**
     * Generate email templates according to enabled extensions
     *
     * @return boolean
     */
    public function batch_template_generate()
    {
       return $this->getService()->templateBatchGenerate();
    }

    /**
     * Disable all email templates at once.
     *
     * @return boolean
     */
    public function batch_template_disable($data)
    {
        return  $this->getService()->templateBatchDisable();
    }

    /**
     * Enable all email templates at once
     *
     * @return boolean
     */
    public function batch_template_enable($data)
    {
        return  $this->getService()->templateBatchEnable();
    }

    /**
     * Sends test email to admins
     *
     * @param type $data
     * @return bool
     */
    public function send_test($data)
    {
        $email             = array();
        $email['to_staff'] = true;
        $email['code']     = 'mod_email_test';

        return $this->getService()->sendTemplate($email);
    }

    public function batch_sendmail()
    {
        return $this->getService()->batchSend();
    }


    /**
     * Send email template to email, client or staff members. If template with code does not exist,
     * it will be created. Default email template file must exist at mod_example/html_email/mod_example_code.phtml file
     *
     * @param string $code - Template code to send. Must be mod_%s_%s structure
     *
     * @optional string $to_staff - True to send to all active staff members. Default false
     * @optional string $to_client - Set client ID to send email to client. Default null
     * @optional string $to - receivers email
     *
     * @optional string $from - from email. Default - company email
     * @optional string $from_name - from name. Default - company name
     *
     * @optional string $default_subject - Default email subject if template does not exist
     * @optional string $default_template - Default email content if template does not exist
     * @optional string $default_description - Default template description if template does not exist.
     *
     * @optional mixed $custom - All parameters passed to this method are also available in email template: {{ custom }}
     *
     * @return bool
     */
    public function template_send($data)
    {
        $required = array(
            'code'         => 'Template code not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        if (!isset($data['to']) && !isset($data['to_staff']) && !isset($data['to_client'])) {
            throw new \Box_Exception('Receiver is not defined. Define to or to_client or to_staff parameter');
        }

        return $this->getService()->sendTemplate($data);
    }

    /**
     * Deletes email logs with given IDs
     *
     * @param array $ids - IDs for deletion
     *
     * @return bool
     */
    public function batch_delete($data)
    {
        $required = array(
            'ids' => 'IDs not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        foreach ($data['ids'] as $id) {
            $this->email_delete(array('id' => $id));
        }

        return true;
    }

}