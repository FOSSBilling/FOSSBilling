<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

/**
 *Email logs and templates management.
 */

namespace Box\Mod\Email\Api;

class Admin extends \Api_Abstract
{
    /**
     * Get list of sent emails.
     *
     * @return array
     */
    public function email_get_list($data)
    {
        $per_page = $data['per_page'] ?? $this->di['pager']->getPer_page();
        [$sql, $params] = $this->getService()->getSearchQuery($data);
        $pager = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);

        foreach ($pager['list'] as $key => $item) {
            $pager['list'][$key] = [
                'id' => $item['id'],
                'client_id' => $item['client_id'],
                'sender' => $item['sender'],
                'recipients' => $item['recipients'],
                'subject' => $item['subject'],
                'content_html' => $item['content_html'],
                'content_text' => $item['content_text'],
                'created_at' => $item['created_at'],
                'updated_at' => $item['updated_at'],
            ];
        }

        return $pager;
    }

    /**
     * Get sent email details.
     *
     * @return array
     */
    public function email_get($data)
    {
        $required = [
            'id' => 'Email ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $service = $this->getService();
        $model = $service->getEmailById($data['id']);

        return $service->toApiArray($model);
    }

    /**
     * Email send.
     *
     * @optional int $client_id - log this message to client history
     *
     * @return bool
     */
    public function send($data = [])
    {
        $required = [
            'to' => 'Receiver Email is required',
            'to_name' => 'Receiver Name is required',
            'from' => 'Sender Name is required',
            'from_name' => 'Sender email is required',
            'subject' => 'Email subject is required',
            'content' => 'Email content is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);
        $client_id = $data['client_id'] ?? null;
        $emailService = $this->getService();

        return $emailService->sendMail(
            $data['to'] ?? null,
            $data['from'] ?? null,
            $data['subject'] ?? null,
            $data['content'] ?? null,
            $data['to_name'] ?? null,
            $data['from_name'] ?? null,
            $client_id
        );
    }

    /**
     * Resend email.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function email_resend($data)
    {
        $required = [
            'id' => 'Email ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->findOne('ActivityClientEmail', 'id = ?', [$data['id']]);

        if (!$model instanceof \Model_ActivityClientEmail) {
            throw new \FOSSBilling\Exception('Email not found');
        }

        return $this->getService()->resend($model);
    }

    /**
     * Delete sent email from logs.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function email_delete($data)
    {
        $required = [
            'id' => 'Email ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->findOne('ActivityClientEmail', 'id = ?', [$data['id']]);

        if (!$model instanceof \Model_ActivityClientEmail) {
            throw new \FOSSBilling\Exception('Email not found');
        }

        $id = $model->id;
        $this->di['db']->trash($model);

        $this->di['logger']->info('Deleted email #%s', $id);

        return true;
    }

    /**
     * Return list of email templates.
     *
     * @return array
     */
    public function template_get_list($data)
    {
        $per_page = $data['per_page'] ?? $this->di['pager']->getPer_page();
        [$sql, $params] = $this->getService()->templateGetSearchQuery($data);
        $pager = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);

        foreach ($pager['list'] as $key => $item) {
            $pager['list'][$key] = [
                'id' => $item['id'] ?? '',
                'action_code' => $item['action_code'] ?? '',
                'category' => $item['category'] ?? '',
                'enabled' => $item['enabled'] ?? '',
                'subject' => $item['subject'] ?? '',
                'description' => $item['description'] ?? '',
            ];
        }

        return $pager;
    }

    /**
     * Get email template full details.
     *
     * @return array
     *
     * @throws \FOSSBilling\Exception
     */
    public function template_get($data)
    {
        $required = [
            'id' => 'Email ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('EmailTemplate', $data['id'], 'Email template not found');

        return $this->getService()->templateToApiArray($model, true);
    }

    /**
     * Delete email template.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function template_delete($data)
    {
        $required = [
            'id' => 'Email ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->findOne('EmailTemplate', 'id = ?', [$data['id']]);

        if (!$model instanceof \Model_EmailTemplate) {
            throw new \FOSSBilling\Exception('Email template not found');
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
     * @return int - newly created template id
     *
     * @throws \FOSSBilling\Exception
     */
    public function template_create($data)
    {
        $required = [
            'action_code' => 'Email template code is required',
            'subject' => 'Email template subject is required',
            'content' => 'Email template content is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $enabled = $data['enabled'] ?? 0;
        $category = $data['category'] ?? null;

        $templateModel = $this->getService()->templateCreate($data['action_code'], $data['subject'], $data['content'], $enabled, $category);

        return $templateModel->id;
    }

    /**
     * Update email template.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     * =     */
    public function template_update($data)
    {
        $required = [
            'id' => 'Email ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $enabled = $data['enabled'] ?? null;
        $category = $data['category'] ?? null;
        $subject = $data['subject'] ?? null;
        $content = $data['content'] ?? null;

        $model = $this->di['db']->getExistingModelById('EmailTemplate', $data['id'], 'Email template not found');

        return $this->getService()->updateTemplate($model, $enabled, $category, $subject, $content);
    }

    /**
     * Reset email template to default.
     *
     * @return bool
     */
    public function template_reset($data)
    {
        $required = [
            'code' => 'Email template code was not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        return $this->getService()->resetTemplateByCode($data['code']);
    }

    /**
     * Generates email template preview.
     *
     * @optional string $_tpl - string to be rendered. Default is email template.
     *
     * @return bool
     */
    public function template_render($data)
    {
        $t = $this->template_get($data);
        $vars = $t['vars'];
        $vars['_tpl'] = $data['_tpl'] ?? $t['content'];
        $systemService = $this->di['mod_service']('System');

        return $systemService->renderString($vars['_tpl'], true, $vars);
    }

    /**
     * Generate email templates according to enabled extensions.
     *
     * @return bool
     */
    public function batch_template_generate()
    {
        return $this->getService()->templateBatchGenerate();
    }

    /**
     * Disable all email templates at once.
     *
     * @return bool
     */
    public function batch_template_disable($data)
    {
        return $this->getService()->templateBatchDisable();
    }

    /**
     * Enable all email templates at once.
     *
     * @return bool
     */
    public function batch_template_enable($data)
    {
        return $this->getService()->templateBatchEnable();
    }

    /**
     * Sends the test email to the currently authenticated admin / staff member.
     */
    public function send_test(array $data): bool
    {
        $currentUser = $this->di['loggedin_admin'];

        $email = [
            'code' => 'mod_email_test',
            'to' => $currentUser->email,
            'to_name' => $currentUser->name,
            'send_now' => true,
            'throw_exceptions' => true,
            'staff_member_name' => $currentUser->name,
        ];

        return $this->getService()->sendTemplate($email);
    }

    public function batch_sendmail()
    {
        $di = $this->getDi();
        $extensionService = $di['mod_service']('extension');
        if ($extensionService->isExtensionActive('mod', 'demo')) {
            return false;
        }

        return $this->getService()->batchSend();
    }

    /**
     * Send email template to email, client or staff members. If template with code does not exist,
     * it will be created. Default email template file must exist at mod_example/html_email/mod_example_code.html.twig file.
     *
     * @optional string $to_staff - True to send to all active staff members. Default false
     * @optional string $to_client - Set client ID to send email to client. Default null
     * @optional string $to - receivers email
     * @optional string $from - from email. Default - company email
     * @optional string $from_name - from name. Default - company name
     * @optional string $default_subject - Default email subject if template does not exist
     * @optional string $default_template - Default email content if template does not exist
     * @optional string $default_description - Default template description if template does not exist.
     * @optional mixed $custom - All parameters passed to this method are also available in email template: {{ custom }}
     *
     * @return bool
     */
    public function template_send($data)
    {
        $required = [
            'code' => 'Template code not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        if (!isset($data['to']) && !isset($data['to_staff']) && !isset($data['to_client'])) {
            throw new \FOSSBilling\InformationException('Receiver is not defined. Define to or to_client or to_staff parameter');
        }

        return $this->getService()->sendTemplate($data);
    }

    /**
     * Deletes email logs with given IDs.
     *
     * @return bool
     */
    public function batch_delete($data)
    {
        $required = [
            'ids' => 'IDs not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        foreach ($data['ids'] as $id) {
            $this->email_delete(['id' => $id]);
        }

        return true;
    }

    public function get_queue(array $data)
    {
        $per_page = $data['per_page'] ?? $this->di['pager']->getPer_page();
        [$sql, $params] = $this->getService()->queueGetSearchQuery($data);
        $pager = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);

        foreach ($pager['list'] as $key => $item) {
            $pager['list'][$key] = [
                'id' => $item['id'] ?? '',
                'recipient' => $item['recipient'] ?? '',
                'subject' => $item['subject'] ?? '',
                'content' => $item['content'] ?? '',
                'to_name' => $item['to_name'] ?? '',
                'status' => $item['status'] ?? '',
                'tries' => $item['tries'] ?? '',
                'created_at' => $item['created_at'] ?? '',
                'updated_at' => $item['updated_at'] ?? '',
            ];
        }

        return $pager;
    }
}
