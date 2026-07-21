<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

/**
 *Email logs and templates management.
 */

namespace Box\Mod\Email\Api;

use Box\Mod\Staff\Entity\AdminGroup;
use FOSSBilling\PaginationOptions;
use FOSSBilling\Validation\Api\RequiredParams;

class Admin extends \FOSSBilling\Api\AbstractApi
{
    /**
     * Get list of sent emails.
     *
     * @return array
     */
    public function email_get_list($data)
    {
        $this->checkPermissions('email', 'view_email_history');

        $repo = $this->getService()->getActivityClientEmailRepository();

        return $this->getDi()['pager']->paginateDoctrineQuery(
            $repo->getSearchQueryBuilder($data),
            PaginationOptions::fromArray($data),
        );
    }

    /**
     * Get sent email details.
     *
     * @return array
     */
    #[RequiredParams(['id' => 'Email ID was not passed'])]
    public function email_get($data)
    {
        $this->checkPermissions('email', 'view_email_history');

        $model = $this->getService()->getActivityClientEmailRepository()->findOneByIdOrFail((int) $data['id']);

        return $model->toApiArray();
    }

    /**
     * Email send.
     *
     * @optional int $client_id - log this message to client history
     */
    #[RequiredParams([
        'to' => 'Receiver email is required',
        'to_name' => 'Receiver name is required',
        'from' => 'Sender email is required',
        'from_name' => 'Sender name is required',
        'subject' => 'Email subject is required',
        'content' => 'Email content is required',
    ])]
    public function send(array $data): bool
    {
        $this->checkPermissions('email', 'send_emails');

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
    #[RequiredParams(['id' => 'Email ID was not passed'])]
    public function email_resend($data)
    {
        $this->checkPermissions('email', 'send_emails');

        $model = $this->getService()->getActivityClientEmailRepository()->findOneByIdOrFail((int) $data['id']);

        return $this->getService()->resend($model);
    }

    /**
     * Delete sent email from logs.
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['id' => 'Email ID was not passed'])]
    public function email_delete($data): bool
    {
        $this->checkPermissions('email', 'delete_email_history');

        $em = $this->getDi()['em'];
        $model = $this->getService()->getActivityClientEmailRepository()->findOneByIdOrFail((int) $data['id']);

        $id = $model->getId();
        $em->remove($model);
        $em->flush();

        $this->getDi()['logger']->info('Deleted email #%s', $id);

        return true;
    }

    /**
     * Return list of email templates.
     *
     * @return array
     */
    public function template_get_list($data)
    {
        $this->checkPermissions('email', 'view_templates');

        $pager = $this->getService()->getTemplateList($data);

        foreach ($pager['list'] as $key => $item) {
            $pager['list'][$key] = [
                'id' => $item['id'] ?? '',
                'action_code' => $item['action_code'] ?? '',
                'category' => $item['category'] ?? '',
                'enabled' => $item['enabled'] ?? '',
                'subject' => $item['subject'] ?? '',
                'description' => $item['description'] ?? '',
                'is_custom' => $item['is_custom'] ?? false,
                'has_default' => $item['has_default'] ?? false,
                'is_overridden' => $item['is_overridden'] ?? false,
                'has_error' => $item['has_error'] ?? false,
                'last_error' => $item['last_error'] ?? null,
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
    #[RequiredParams(['id' => 'Email ID was not passed'])]
    public function template_get($data)
    {
        $this->checkPermissions('email', 'view_templates');

        $model = $this->getService()->getTemplate((int) $data['id']);

        return $this->getService()->templateToApiArray($model, true);
    }

    /**
     * Delete email template.
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['id' => 'Email ID was not passed'])]
    public function template_delete($data): bool
    {
        $this->checkPermissions('email', 'manage_templates');

        $service = $this->getService();
        $template = $service->getTemplate((int) $data['id']);
        if (!$template->isCustom() && $service->hasDefaultTemplate($template->getActionCode())) {
            throw new \FOSSBilling\Exception('Only custom email templates can be deleted');
        }

        $id = $template->getId();
        $service->getTemplateGroupRepository()->deleteAssociationsForTemplate((int) $id);
        $this->getDi()['em']->remove($template);
        $this->getDi()['em']->flush();

        $this->getDi()['logger']->info('Deleted email template #%s', $id);

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
    #[RequiredParams([
        'action_code' => 'Email template code is required',
        'subject' => 'Email template subject is required',
        'content' => 'Email template content is required',
    ])]
    public function template_create($data)
    {
        $this->checkPermissions('email', 'manage_templates');

        $enabled = $data['enabled'] ?? 0;
        $category = $data['category'] ?? null;

        $template = $this->getService()->templateCreate($data['action_code'], $data['subject'], $data['content'], $enabled, $category);

        return $template->getId();
    }

    /**
     * Update email template.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['id' => 'Email ID was not passed'])]
    public function template_update($data)
    {
        $this->checkPermissions('email', 'manage_templates');

        $enabled = $data['enabled'] ?? null;
        $category = $data['category'] ?? null;
        $subject = $data['subject'] ?? null;
        $content = $data['content'] ?? null;

        $model = $this->getService()->getTemplate((int) $data['id']);

        return $this->getService()->updateTemplate($model, $enabled, $category, $subject, $content);
    }

    /**
     * List the staff groups a template is restricted to.
     *
     * @return array
     */
    #[RequiredParams(['id' => 'Email ID was not passed'])]
    public function template_group_get_list($data)
    {
        $this->checkPermissions('email', 'view_templates');

        $service = $this->getService();
        $template = $service->getTemplate((int) $data['id']);
        $groupIds = $service->getTemplateGroupIds($template);

        if ($groupIds === []) {
            return [];
        }

        $groups = $this->getDi()['mod_service']('staff')->getAdminGroupRepository()->findBy(['id' => $groupIds]);

        return array_map(
            static fn (AdminGroup $group): array => [
                'id' => $group->getId(),
                'name' => $group->getName(),
                'protected' => $group->isProtected(),
            ],
            $groups,
        );
    }

    /**
     * Restrict an email template to an additional staff group.
     */
    #[RequiredParams(['id' => 'Email ID was not passed', 'group_id' => 'Staff group ID was not passed'])]
    public function template_group_add($data): bool
    {
        $this->checkPermissions('email', 'manage_templates');

        $service = $this->getService();
        $template = $service->getTemplate((int) $data['id']);

        return $service->addTemplateToGroup($template, (int) $data['group_id']);
    }

    /**
     * Remove a staff group restriction from an email template.
     */
    #[RequiredParams(['id' => 'Email ID was not passed', 'group_id' => 'Staff group ID was not passed'])]
    public function template_group_remove($data): bool
    {
        $this->checkPermissions('email', 'manage_templates');

        $service = $this->getService();
        $template = $service->getTemplate((int) $data['id']);

        return $service->removeTemplateFromGroup($template, (int) $data['group_id']);
    }

    /**
     * Reset email template to default.
     *
     * @return bool
     */
    #[RequiredParams(['code' => 'Email template code was not passed'])]
    public function template_reset($data)
    {
        $this->checkPermissions('email', 'manage_templates');

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
        $this->checkPermissions('email', 'manage_templates');

        $model = $this->getService()->getTemplate((int) $data['id']);
        $t = $this->getService()->templateToApiArray($model, true);
        $vars = $t['vars'];
        $vars['_tpl'] = $data['_tpl'] ?? $t['content'];
        $systemService = $this->getDi()['mod_service']('System');

        return $systemService->renderEmailTplString($vars['_tpl'], $vars);
    }

    /**
     * Regenerate built-in email templates from their file-backed defaults.
     */
    public function batch_template_generate()
    {
        $this->checkPermissions('email', 'manage_templates');

        return $this->getService()->templateBatchRegenerate();
    }

    /**
     * Disable all email templates at once.
     *
     * @return bool
     */
    public function batch_template_disable($data)
    {
        $this->checkPermissions('email', 'manage_templates');

        return $this->getService()->templateBatchDisable();
    }

    /**
     * Enable all email templates at once.
     *
     * @return bool
     */
    public function batch_template_enable($data)
    {
        $this->checkPermissions('email', 'manage_templates');

        return $this->getService()->templateBatchEnable();
    }

    /**
     * Validate all email templates for Twig syntax errors.
     * Returns a summary of valid and invalid templates with error details.
     *
     * @return array
     */
    public function template_validate_all()
    {
        $this->checkPermissions('email', 'manage_templates');

        return $this->getService()->validateAllTemplates();
    }

    /**
     * Sends the test email to the currently authenticated admin / staff member.
     */
    public function send_test(array $data): bool
    {
        $this->checkPermissions('email', 'send_emails');

        $currentUser = $this->getDi()['loggedin_admin'] ?? null;

        $email = [
            'code' => 'mod_email_test',
            'to' => $currentUser?->getEmail() ?? '',
            'to_name' => $currentUser?->getName() ?? '',
            'send_now' => true,
            'throw_exceptions' => true,
            'staff_member_name' => $currentUser?->getName() ?? '',
        ];

        return $this->getService()->sendTemplate($email);
    }

    public function batch_sendmail()
    {
        $this->checkPermissions('email', 'send_emails');

        $extensionService = $this->getDi()['mod_service']('extension');
        if ($extensionService->isExtensionActive('mod', 'demo')) {
            return false;
        }

        return $this->getService()->batchSend();
    }

    /**
     * Send email template to email, client or staff members. If template with code does not exist,
     * it will be created. Default email template file must exist at mod_example/templates/email/mod_example_code.html.twig file.
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
    #[RequiredParams(['code' => 'Template code not passed'])]
    public function template_send($data)
    {
        $this->checkPermissions('email', 'send_emails');

        if (!isset($data['to']) && !isset($data['to_staff']) && !isset($data['to_client'])) {
            throw new \FOSSBilling\InformationException('Receiver is not defined. Define to or to_client or to_staff parameter');
        }

        return $this->getService()->sendTemplate($data);
    }

    /**
     * Deletes email templates with given IDs.
     */
    #[RequiredParams(['ids' => 'IDs were not passed'])]
    public function batch_template_delete($data): bool
    {
        $this->checkPermissions('email', 'manage_templates');

        foreach ($data['ids'] as $id) {
            $template = $this->getService()->getTemplate((int) $id);
            if (!$template->isCustom() && $this->getService()->hasDefaultTemplate($template->getActionCode())) {
                continue;
            }
            $this->getDi()['em']->remove($template);
        }
        $this->getDi()['em']->flush();

        return true;
    }

    /**
     * Deletes email logs with given IDs.
     */
    #[RequiredParams(['ids' => 'IDs were not passed'])]
    public function batch_delete($data): bool
    {
        $this->checkPermissions('email', 'delete_email_history');

        foreach ($data['ids'] as $id) {
            $this->email_delete(['id' => $id]);
        }

        return true;
    }

    public function get_queue(array $data)
    {
        $this->checkPermissions('email', 'view_email_history');

        $repo = $this->getService()->getQueuedEmailRepository();

        return $this->getDi()['pager']->paginateDoctrineQuery(
            $repo->getSearchQueryBuilder($data),
            PaginationOptions::fromArray($data),
        );
    }
}
