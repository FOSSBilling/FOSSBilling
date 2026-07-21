<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Email;

use Box\Mod\Client\Entity\Client;
use Box\Mod\Currency\Entity\Currency;
use Box\Mod\Email\Entity\ActivityClientEmail;
use Box\Mod\Email\Entity\EmailTemplate;
use Box\Mod\Email\Entity\EmailTemplateGroup;
use Box\Mod\Email\Entity\QueuedEmail;
use Box\Mod\Email\Repository\ActivityClientEmailRepository;
use Box\Mod\Email\Repository\EmailTemplateGroupRepository;
use Box\Mod\Email\Repository\EmailTemplateRepository;
use Box\Mod\Email\Repository\QueuedEmailRepository;
use FOSSBilling\Config;
use FOSSBilling\Environment;
use FOSSBilling\PaginationOptions;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

class Service implements \FOSSBilling\InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;
    protected EmailTemplateRepository $templateRepository;
    protected EmailTemplateGroupRepository $templateGroupRepository;
    protected ActivityClientEmailRepository $activityClientEmailRepository;
    protected QueuedEmailRepository $queuedEmailRepository;
    private Filesystem $filesystem;

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
        $this->templateRepository = $di['em']->getRepository(EmailTemplate::class);
        $this->templateGroupRepository = $di['em']->getRepository(EmailTemplateGroup::class);
        $this->activityClientEmailRepository = $di['em']->getRepository(ActivityClientEmail::class);
        $this->queuedEmailRepository = $di['em']->getRepository(QueuedEmail::class);
        if (isset($di['filesystem'])) {
            $this->filesystem = $di['filesystem'];
        }
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getTemplateRepository(): EmailTemplateRepository
    {
        return $this->templateRepository;
    }

    public function getTemplateGroupRepository(): EmailTemplateGroupRepository
    {
        return $this->templateGroupRepository;
    }

    public function getActivityClientEmailRepository(): ActivityClientEmailRepository
    {
        return $this->activityClientEmailRepository;
    }

    public function getQueuedEmailRepository(): QueuedEmailRepository
    {
        return $this->queuedEmailRepository;
    }

    public function getModulePermissions(): array
    {
        return [
            'view_email_history' => [
                'type' => 'bool',
                'display_name' => __trans('View email history'),
                'description' => __trans('Allows the staff member to view sent emails and queued email messages.'),
            ],
            'delete_email_history' => [
                'type' => 'bool',
                'display_name' => __trans('Delete email history'),
                'description' => __trans('Allows the staff member to delete sent email log entries.'),
            ],
            'send_emails' => [
                'type' => 'bool',
                'display_name' => __trans('Send emails'),
                'description' => __trans('Allows the staff member to send, resend, and process queued emails.'),
            ],
            'view_templates' => [
                'type' => 'bool',
                'display_name' => __trans('View email templates'),
                'description' => __trans('Allows the staff member to view email templates and template details.'),
            ],
            'manage_templates' => [
                'type' => 'bool',
                'display_name' => __trans('Manage email templates'),
                'description' => __trans('Allows the staff member to create, update, delete, reset, and regenerate email templates.'),
            ],
            'manage_settings' => [],
        ];
    }

    public function rmByClient(Client $client): bool
    {
        $this->getActivityClientEmailRepository()->deleteByClientId((int) $client->getId());

        return true;
    }

    public function setVars(EmailTemplate $template, array $vars): bool
    {
        $template->setVars($this->di['crypt']->encrypt(json_encode($vars), Config::getProperty('info.salt')));
        $this->di['em']->flush();

        return true;
    }

    public function getVars(EmailTemplate $template): array
    {
        $vars = [];
        if ($template->getVars() !== null && $template->getVars() !== '') {
            $json = $this->di['crypt']->decrypt($template->getVars(), Config::getProperty('info.salt'));
            $decoded = is_string($json) ? json_decode($json, true) : null;
            $vars = is_array($decoded) ? $decoded : [];
        }

        // Invoice templates must remain previewable before an invoice email has
        // populated the stored example variables.
        if (str_starts_with($template->getActionCode(), 'mod_invoice_')) {
            $invoice = is_array($vars['invoice'] ?? null) ? $vars['invoice'] : [];
            $invoice['total'] ??= 0;
            if (empty($invoice['currency'])) {
                $currency = $this->di['mod_service']('currency')->getCurrencyRepository()->findDefault();
                if ($currency instanceof Currency) {
                    $invoice['currency'] = $currency->getCode();
                }
            }
            $vars['invoice'] = $invoice;
        }

        $defaults = $this->getTemplateVarDefaults($template->getActionCode());
        if ($defaults !== []) {
            $vars = array_replace_recursive($defaults, $vars);
        }

        return $vars;
    }

    private function getTemplateVarDefaults(string $actionCode): array
    {
        $staff = [
            'id' => 1,
            'email' => 'staff@example.com',
            'name' => 'Staff Member',
            'signature' => '',
        ];
        $client = [
            'id' => 1,
            'email' => 'client@example.com',
            'email_approved' => true,
            'type' => 'individual',
            'company' => '',
            'company_vat' => '',
            'company_number' => '',
            'first_name' => 'Example',
            'last_name' => 'Client',
            'gender' => '',
            'birthday' => '',
            'phone_cc' => '',
            'phone' => '',
            'address_1' => '',
            'address_2' => '',
            'city' => '',
            'state' => '',
            'postcode' => '',
            'country' => '',
            'currency' => '',
            'lang' => '',
            'timezone' => '',
        ];
        $message = [
            'id' => 1,
            'content' => 'Example ticket message',
            'attachment' => '',
            'created_at' => '',
            'updated_at' => '',
            'author' => [
                'name' => 'Example Client',
                'role' => 'client',
            ],
        ];
        $ticket = [
            'id' => 1,
            'subject' => 'Example support ticket',
            'status' => 'open',
            'priority' => 100,
            'created_at' => '',
            'updated_at' => '',
            'replies' => 0,
            'first' => $message,
            'helpdesk' => [
                'id' => 1,
                'name' => 'Support',
                'can_reopen' => true,
            ],
            'author' => [
                'id' => 1,
                'name' => 'Example Client',
                'first_name' => 'Example',
                'last_name' => 'Client',
                'email' => 'client@example.com',
                'role' => 'client',
            ],
            'client' => [
                'id' => 1,
                'first_name' => 'Example',
                'last_name' => 'Client',
            ],
            'messages' => [$message],
        ];
        $staffTicket = $ticket;
        $staffTicket['client'] = $client;

        return match ($actionCode) {
            'mod_support_ticket_staff_open',
            'mod_support_ticket_staff_close',
            'mod_support_ticket_staff_reply' => ['c' => $client, 'ticket' => $ticket],
            'mod_staff_ticket_open',
            'mod_staff_ticket_reply',
            'mod_staff_ticket_close' => ['staff' => $staff, 'ticket' => $staffTicket],
            'mod_staff_password_reset_approve' => ['c' => $staff],
            'mod_staff_client_signup' => ['c' => $client, 'staff' => $staff],
            default => [],
        };
    }

    public function sendTemplate($data)
    {
        $required = [
            'code' => 'Template code not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        if (!isset($data['to']) && !isset($data['to_staff']) && !isset($data['to_client']) && !isset($data['to_admin'])) {
            throw new \FOSSBilling\InformationException('Receiver is not defined. Define to or to_client or to_staff or to_admin parameter');
        }
        $vars = $data;
        unset($vars['to'], $vars['to_client'], $vars['to_staff'], $vars['to_name'], $vars['from'], $vars['from_name'], $vars['to_admin']);
        unset($vars['default_description'], $vars['default_subject'], $vars['default_template'], $vars['code'], $vars['send_now'], $vars['throw_exceptions'], $vars['attachment']);

        $send_now = $data['send_now'] ?? false;
        $throw_exceptions = $data['throw_exceptions'] ?? false;
        $attachment = $data['attachment'] ?? null;

        $template = $this->getOrCreateTemplateByCode($data['code'], $data);

        // add additional variables to template
        if (isset($data['to_staff']) && $data['to_staff']) {
            $staffService = $this->di['mod_service']('staff');
            $groupIds = $this->templateGroupRepository->getGroupIdsForTemplate((int) $template->getId());
            $staffList = $groupIds === [] ? [] : $staffService->getAdminGroupMemberRepository()->getActiveStaffInGroups($groupIds);
            $staff = ['list' => $staffList];
            if ($staffList !== []) {
                $vars['staff'] = $this->safeStaffTemplateVars($staffList[0]);
            }
        }

        // add additional variables to template
        if (isset($data['to_client']) && $data['to_client'] > 0) {
            $clientService = $this->di['mod_service']('client');
            $customer = $clientService->get(['id' => $data['to_client']]);
            $customer = $clientService->toApiArray($customer);
            $vars['c'] = $customer;
        }

        // send email to admins
        if (isset($data['to_admin']) && $data['to_admin'] > 0) {
            /** @todo Doctrine: use Admin entity once Staff is migrated */
            $oneStaff = $this->di['dbal']->fetchAssociative('SELECT id, email, name, signature, timezone FROM admin WHERE id = :id', ['id' => $data['to_admin']]);
            $vars['c'] = $this->safeStaffTemplateVars($oneStaff);
        }

        $this->setVars($template, $vars);

        // do not send inactive template
        if (!$template->isEnabled()) {
            return false;
        }
        $systemService = $this->di['mod_service']('system');

        // Pick a timezone for the date filters. Client-bound and individual
        // staff-bound emails render in the recipient's timezone; broadcasts and
        // misc fall through to the config default. Staff broadcasts parse per
        // recipient in the send loop below; the value computed here is unused
        // for that branch.
        $recipientTimezone = null;
        if (isset($customer) && !empty($customer['timezone'])) {
            $recipientTimezone = (string) $customer['timezone'];
        } elseif (isset($oneStaff) && !empty($oneStaff['timezone'] ?? null)) {
            $recipientTimezone = (string) $oneStaff['timezone'];
        }

        [$subject, $content] = $this->_parse($template, $vars, $recipientTimezone);

        $emailMod = $this->di['mod']('email');
        $emailSettings = $emailMod->getConfig();

        $customEmail = $emailSettings['from_email'] ?? '';
        $customName = $emailSettings['from_name'] ?? '';

        $companyEmail = $systemService->getParamValue('company_email');
        $companyName = $systemService->getParamValue('company_name');

        $from = $data['from'] ?? (!empty($customEmail) ? $customEmail : $companyEmail);
        $from_name = $data['from_name'] ?? (!empty($customName) ? $customName : $companyName);

        $sent = false;

        if (!$from) {
            throw new \FOSSBilling\InformationException('The "from" email address cannot be empty');
        }

        if (isset($staff)) {
            foreach ($staff['list'] as $staff) {
                $staffVars = $vars;
                $staffVars['staff'] = $this->safeStaffTemplateVars($staff);
                $staffTimezone = !empty($staff['timezone'] ?? null) ? (string) $staff['timezone'] : null;
                [$subject, $content] = $this->_parse($template, $staffVars, $staffTimezone);

                $to = $staff['email'];
                $to_name = $staff['name'];
                $sent = $this->sendMail($to, $from, $subject, $content, $to_name, $from_name, null, $staff['id'], $send_now, $throw_exceptions, $attachment);
            }
        } elseif (isset($oneStaff)) {
            $to = $oneStaff['email'];
            $to_name = $oneStaff['name'];
            $sent = $this->sendMail($to, $from, $subject, $content, $to_name, $from_name, $oneStaff['id'], null, $send_now, $throw_exceptions, $attachment);
        } elseif (isset($customer)) {
            // Supplying both keeps the email associated with the client while allowing a
            // purpose-specific recipient, such as the client's billing address.
            $to = $data['to'] ?? $customer['email'];
            $to_name = $customer['first_name'] . ' ' . $customer['last_name'];
            $sent = $this->sendMail($to, $from, $subject, $content, $to_name, $from_name, $customer['id'], null, $send_now, $throw_exceptions, $attachment);
        } else {
            $to = $data['to'];
            $to_name = $data['to_name'] ?? null;
            $sent = $this->sendMail($to, $from, $subject, $content, $to_name, $from_name, null, null, $send_now, $throw_exceptions, $attachment);
        }

        return $sent;
    }

    private function safeStaffTemplateVars(array $staff): array
    {
        return [
            'id' => $staff['id'],
            'email' => $staff['email'],
            'name' => $staff['name'],
            'signature' => $staff['signature'],
        ];
    }

    /**
     * @param array{content: string, name: string, mime?: string}|null $attachment a file to attach to the email, e.g. a generated invoice PDF
     */
    public function sendMail($to, $from, $subject, $content, $to_name = null, $from_name = null, $client_id = null, $admin_id = null, bool $send_now = false, bool $throw_exceptions = false, ?array $attachment = null): bool
    {
        // Add the email to the queue
        $email = $this->_queue($to, $from, $subject, $content, $to_name, $from_name, $client_id, $admin_id, $attachment);
        if ($send_now) {
            $this->_sendFromQueue($email, $throw_exceptions);
        }

        return true;
    }

    private function _getDefaults($data): array
    {
        $code = $data['code'];

        $enabled = 0;
        $subject = $data['default_subject'] ?? ucwords(str_replace('_', ' ', $code));
        $content = $data['default_template'] ?? $this->_getVarsString();
        $description = $data['default_description'] ?? null;

        $matches = [];
        if (preg_match('/mod_([a-zA-Z0-9]+)_([a-zA-Z0-9]+)/i', (string) $code, $matches)) {
            $mod = $matches[1];
        } else {
            $mod = 'custom';
        }

        $path = Path::join(PATH_MODS, ucfirst($mod), 'templates/email', "{$code}.html.twig");

        if ($this->filesystem->exists($path)) {
            $tpl = $this->filesystem->readFile($path);

            $ms = [];
            preg_match('#{%.?block subject.?%}((.*?)+){%.?endblock.?%}#', $tpl, $ms);
            if (isset($ms[1])) {
                $subject = $ms[1];
            }

            $mc = [];
            preg_match('/{%.?block content.?%}((.*?\n)+){%.?endblock.?%}/m', $tpl, $mc);
            if (isset($mc[1])) {
                $content = $mc[1];
                $enabled = 1;
            }
        }

        return [$subject, $content, $description, $enabled, $mod];
    }

    private function getDefaultTemplate(string $code, array $data = []): ?array
    {
        $path = $this->getDefaultTemplatePath($code);
        if ($path === null) {
            return null;
        }

        [$subject, $content, $description, $enabled, $category] = $this->_getDefaults(array_merge($data, ['code' => $code]));

        return [
            'action_code' => $code,
            'category' => $category,
            'subject' => $subject,
            'content' => $content,
            'description' => $description,
            'enabled' => $enabled,
        ];
    }

    public function hasDefaultTemplate(string $code): bool
    {
        return $this->getDefaultTemplatePath($code) !== null;
    }

    private function getDefaultTemplatePath(string $code): ?string
    {
        $matches = [];
        if (!preg_match('/mod_([a-zA-Z0-9]+)_([a-zA-Z0-9]+)/i', $code, $matches)) {
            return null;
        }

        $module = ucfirst($matches[1]);
        $path = Path::join(PATH_MODS, $module, 'templates/email', "{$code}.html.twig");

        return $this->filesystem->exists($path) ? $path : null;
    }

    private function isCustomTemplate(EmailTemplate $template): bool
    {
        return $template->isCustom();
    }

    private function createBuiltinTemplateRecord(string $code, array $default): EmailTemplate
    {
        $template = new EmailTemplate($code);
        $template->setCategory($default['category'])
            ->setDescription($default['description'])
            ->setEnabled((bool) $default['enabled'])
            ->setIsCustom(false)
            ->setIsOverridden(false)
            ->setSubject($default['subject'])
            ->setContent($default['content']);

        $em = $this->di['em'];
        $em->persist($template);
        $em->flush();

        if ($default['category'] === 'staff') {
            $this->assignAllGroupsToTemplate($template);
        }

        return $template;
    }

    /**
     * Assigns every currently existing staff group to a freshly created staff
     * notification template, so it reaches everyone by default until an admin
     * narrows it down.
     */
    private function assignAllGroupsToTemplate(EmailTemplate $template): void
    {
        $groups = $this->di['mod_service']('staff')->getAdminGroupRepository()->findAll();

        foreach ($groups as $group) {
            $this->di['em']->persist(new EmailTemplateGroup($template, (int) $group->getId()));
        }

        $this->di['em']->flush();
    }

    private function createTemplateRecordFromData(string $code, array $data): EmailTemplate
    {
        $default = $this->getDefaultTemplate($code, $data);
        if ($default !== null) {
            return $this->createBuiltinTemplateRecord($code, $default);
        }

        [$subject, $content, $description, $enabled, $category] = $this->_getDefaults($data);

        $template = new EmailTemplate($code);
        $template->setCategory($category)
            ->setEnabled((bool) $enabled)
            ->setDescription($description)
            ->setSubject($subject)
            ->setContent($content)
            ->setIsCustom(true);

        $em = $this->di['em'];
        $em->persist($template);
        $em->flush();

        return $template;
    }

    private function getOrCreateTemplateByCode(string $code, array $data = []): EmailTemplate
    {
        $template = $this->getTemplateRepository()->findOneByActionCode($code);
        if ($template instanceof EmailTemplate) {
            $default = $this->getDefaultTemplate($code, $data);
            if ($default !== null && !$this->isCustomTemplate($template)) {
                $this->syncBuiltinTemplate($template, $default);
            }

            return $template;
        }

        return $this->createTemplateRecordFromData($code, $data);
    }

    private function syncBuiltinTemplate(EmailTemplate $template, array $default): void
    {
        $updated = false;

        if (($template->getCategory() === null || $template->getCategory() === '') && $default['category'] !== null) {
            $template->setCategory($default['category']);
            $updated = true;
        }

        if (($template->getDescription() === null || $template->getDescription() === '') && $default['description'] !== null) {
            $template->setDescription($default['description']);
            $updated = true;
        }

        if (!$template->isOverridden()) {
            if ($template->getSubject() !== $default['subject']) {
                $template->setSubject($default['subject']);
                $updated = true;
            }
            if ($template->getContent() !== $default['content']) {
                $template->setContent($default['content']);
                $updated = true;
            }
        }

        if ($updated) {
            $this->di['em']->flush();
        }
    }

    private function resetBuiltinTemplate(EmailTemplate $template, array $default): void
    {
        $template->setSubject($default['subject'])
            ->setContent($default['content'])
            ->setIsOverridden(false)
            ->clearError();
    }

    private function getEffectiveTemplateParts(EmailTemplate $template): array
    {
        if ($this->isCustomTemplate($template)) {
            return [$template->getSubject() ?? '', $template->getContent() ?? ''];
        }

        if ($template->getSubject() !== null && $template->getContent() !== null) {
            return [$template->getSubject(), $template->getContent()];
        }

        $default = $this->getDefaultTemplate($template->getActionCode());
        if ($default !== null) {
            $subject = $template->getSubject() ?? $default['subject'];
            $content = $template->getContent() ?? $default['content'];

            return [$subject ?? '', $content ?? ''];
        }

        return [$template->getSubject() ?? '', $template->getContent() ?? ''];
    }

    private function _queue($to, $from, $subject, $content, $to_name = null, $from_name = null, $client_id = null, $admin_id = null, ?array $attachment = null): QueuedEmail
    {
        $em = $this->di['em'];

        $queue = new QueuedEmail();
        $queue->setRecipient((string) $to);
        $queue->setSender((string) $from);
        $queue->setSubject((string) $subject);
        $queue->setContent((string) $content);
        $queue->setToName($to_name !== null ? (string) $to_name : null);
        $queue->setFromName($from_name !== null ? (string) $from_name : null);
        $queue->setClientId($client_id !== null ? (int) $client_id : null);
        $queue->setAdminId($admin_id !== null ? (int) $admin_id : null);
        $queue->setStatus(QueuedEmail::STATUS_PENDING);
        $queue->setPriority(1);
        $queue->setTries(0);

        if ($attachment !== null && isset($attachment['content'], $attachment['name'])) {
            $queue->setAttachmentName((string) $attachment['name']);
            $queue->setAttachmentContent((string) $attachment['content']);
            $queue->setAttachmentMime((string) ($attachment['mime'] ?? 'application/octet-stream'));
        }

        $em->persist($queue);
        $em->flush();

        return $queue;
    }

    private function _getVarsString(): string
    {
        $str = '{% apply markdown_to_html %}' . PHP_EOL . PHP_EOL;
        $str .= 'This email template was automatically generated by FOSSBilling extension.   ' . PHP_EOL;
        $str .= 'Template is ready to be modified.   ' . PHP_EOL;
        $str .= 'Email templates support a limited sandboxed Twig environment.   ' . PHP_EOL;
        $str .= 'Use variables passed to the template together with the available globals such as **guest.system_company**, **guest.system_email**, and **FOSSBillingVersion**.' . PHP_EOL . PHP_EOL;
        $str .= 'Example values available in email templates:' . PHP_EOL . PHP_EOL;
        $str .= '{{ FOSSBillingVersion }}' . PHP_EOL . PHP_EOL;
        $str .= '{{ guest.system_company.name }}' . PHP_EOL . PHP_EOL;
        $str .= '{{ guest.system_email.signature }}' . PHP_EOL . PHP_EOL;
        $str .= "{{ now|date('Y-m-d') }}" . PHP_EOL . PHP_EOL;

        return $str . '{% endapply %}';
    }

    private function _parse(EmailTemplate $template, array $vars, ?string $timezone = null): array
    {
        $systemService = $this->di['mod_service']('System');
        [$subjectTemplate, $contentTemplate] = $this->getEffectiveTemplateParts($template);

        try {
            $pc = $systemService->renderEmailTplString($contentTemplate, $vars, $timezone);
            $ps = $systemService->renderEmailTplString($subjectTemplate, $vars, $timezone);

            if ($template->hasError()) {
                $template->clearError();
                $this->di['em']->flush();
            }

            return [$ps, $pc];
        } catch (\FOSSBilling\Exception $e) {
            $template->setLastError($e->getMessage());
            $template->setErrorCheckedAt(new \DateTimeImmutable());
            $this->di['em']->flush();

            throw $e;
        }
    }

    public function resend(ActivityClientEmail $email): bool
    {
        $extensionService = $this->di['mod_service']('extension');
        if ($extensionService->isExtensionActive('mod', 'demo')) {
            return false;
        }

        if (Environment::isTesting()) {
            // @phpstan-ignore if.alwaysFalse
            if (DEBUG) {
                $this->di['logger']->setChannel('email')->info('Skipping email sending in test environment');
            }

            return true;
        }

        $clientService = $this->di['mod_service']('client');
        $customer = $clientService->get(['id' => $email->getClientId()]);
        $customer = $clientService->toApiArray($customer);

        $systemService = $this->di['mod_service']('system');
        $from_name = $systemService->getParamValue('company_name');

        $this->sendMail($email->getRecipients(), $email->getSender(), $email->getSubject(), $email->getContentHtml(), $customer['first_name'] . ' ' . $customer['last_name'], $from_name, $email->getClientId(), null, false, false, $this->loggedAttachmentToArray($email));

        $this->di['logger']->info('Resent email #%s', $email->getId());

        return true;
    }

    /**
     * @return array{content: string, name: string, mime: string}|null
     */
    private function loggedAttachmentToArray(ActivityClientEmail $email): ?array
    {
        $content = $email->getAttachmentContent();
        if ($content === null) {
            return null;
        }

        return [
            'content' => $content,
            'name' => $email->getAttachmentName() ?? 'attachment',
            'mime' => $email->getAttachmentMime() ?? 'application/octet-stream',
        ];
    }

    public function queueGetSearchQuery($data): array
    {
        $query = 'SELECT * FROM email_queue';

        $id = $data['id'] ?? null;
        $search = $data['search'] ?? null;
        $recipient = $data['recipient'] ?? null;
        $subject = $data['subject'] ?? null;
        $status = $data['status'] ?? null;
        $tries = $data['tries'] ?? null;
        $date_from = $data['date_from'] ?? null;
        $date_to = $data['date_to'] ?? null;

        $where = [];
        $bindings = [];

        if ($id !== null && $id !== '') {
            $where[] = 'id = :id';
            $bindings['id'] = (int) $id;
        }

        if ($search) {
            $search = "%$search%";

            $where[] = '(recipient LIKE :recipient OR subject LIKE :subject OR content LIKE :content OR to_name LIKE :to_name)';

            $bindings['recipient'] = $search;
            $bindings['subject'] = $search;
            $bindings['content'] = $search;
            $bindings['to_name'] = $search;
        }

        if ($recipient !== null && $recipient !== '') {
            $where[] = 'recipient LIKE :filter_recipient';
            $bindings['filter_recipient'] = '%' . $recipient . '%';
        }

        if ($subject !== null && $subject !== '') {
            $where[] = 'subject LIKE :filter_subject';
            $bindings['filter_subject'] = '%' . $subject . '%';
        }

        if ($status !== null && $status !== '') {
            $where[] = 'status = :status';
            $bindings['status'] = $status;
        }

        if ($tries !== null && $tries !== '') {
            $where[] = 'tries = :tries';
            $bindings['tries'] = (int) $tries;
        }

        if ($date_from !== null && $date_from !== '') {
            $where[] = 'created_at >= :date_from';
            $bindings['date_from'] = date('Y-m-d 00:00:00', strtotime((string) $date_from));
        }

        if ($date_to !== null && $date_to !== '') {
            $where[] = 'created_at <= :date_to';
            $bindings['date_to'] = date('Y-m-d 23:59:59', strtotime((string) $date_to));
        }

        if (!empty($where)) {
            $query = $query . ' WHERE ' . implode(' AND ', $where);
        }

        $query .= ' ORDER BY updated_at DESC';

        return [$query, $bindings];
    }

    public function templateToApiArray(EmailTemplate $template, bool $deep = false): array
    {
        $isCustom = $this->isCustomTemplate($template);
        [$subject, $content] = $this->getEffectiveTemplateParts($template);

        $isOverridden = false;
        if (!$isCustom) {
            $isOverridden = $template->isOverridden();
        }

        $data = [
            'id' => $template->getId(),
            'action_code' => $template->getActionCode(),
            'category' => $template->getCategory(),
            'enabled' => $template->isEnabled(),
            'subject' => $subject,
            'description' => $template->getDescription(),
            'is_custom' => $isCustom,
            'has_default' => !$isCustom && $this->hasDefaultTemplate($template->getActionCode()),
            'is_overridden' => $isOverridden,
        ];
        if ($deep) {
            $data['content'] = $content;
            $data['vars'] = $this->getVars($template);
            $data['subject_override'] = $isCustom ? null : $template->getSubject();
            $data['content_override'] = $isCustom ? null : $template->getContent();
            $data['has_error'] = $template->hasError();
            $data['last_error'] = $template->getLastError();
        }

        return $data;
    }

    private function templateRowToApiArray(array $template): array
    {
        $actionCode = (string) ($template['action_code'] ?? '');
        $isCustom = (bool) ($template['is_custom'] ?? false);
        $isOverridden = !$isCustom && (bool) ($template['is_overridden'] ?? false);
        $default = (!$isCustom && $actionCode !== '') ? $this->getDefaultTemplate($actionCode) : null;

        $subject = $template['subject'] ?? null;
        $description = $template['description'] ?? null;
        $category = $template['category'] ?? null;

        if ($default !== null) {
            $category ??= $default['category'];
            $description ??= $default['description'];

            if (!$isOverridden || $subject === null || $subject === '') {
                $subject = $default['subject'];
            }
        }

        return [
            'id' => $template['id'] ?? null,
            'action_code' => $actionCode,
            'category' => $category,
            'enabled' => (bool) ($template['enabled'] ?? false),
            'subject' => $subject ?? '',
            'description' => $description,
            'is_custom' => $isCustom,
            'has_default' => !$isCustom && $default !== null,
            'is_overridden' => $isOverridden,
            'has_error' => !empty($template['last_error']),
            'last_error' => $template['last_error'] ?? null,
        ];
    }

    public function updateTemplate(EmailTemplate $template, $enabled = null, $category = null, $subject = null, $content = null): bool
    {
        if (isset($enabled)) {
            $template->setEnabled((bool) $enabled);
        }

        if (isset($category)) {
            $template->setCategory($category);
        }

        $systemService = $this->di['mod_service']('System');
        $vars = $this->getVars($template);

        $isCustom = $this->isCustomTemplate($template);
        $default = $isCustom ? null : $this->getDefaultTemplate($template->getActionCode());

        if (isset($subject)) {
            $vars['_tpl'] = $subject;
            $systemService->renderEmailTplString($subject, $vars);
            $template->setSubject($subject);
        }

        if (isset($content)) {
            $vars['_tpl'] = $content;
            $systemService->renderEmailTplString($content, $vars);
            $template->setContent($content);
        }

        $template->clearError();

        if (!$isCustom && $default !== null) {
            $subjectMatches = $template->getSubject() === $default['subject'];
            $contentMatches = $template->getContent() === $default['content'];
            $template->setIsOverridden(!($subjectMatches && $contentMatches));
        }

        $this->di['em']->flush();
        $this->di['logger']->info('Updated email template #%s', $template->getId());

        return true;
    }

    /**
     * @return int[]
     */
    public function getTemplateGroupIds(EmailTemplate $template): array
    {
        return $this->templateGroupRepository->getGroupIdsForTemplate((int) $template->getId());
    }

    public function addTemplateToGroup(EmailTemplate $template, int $groupId): bool
    {
        $group = $this->di['mod_service']('staff')->getAdminGroupRepository()->find($groupId);
        if ($group === null) {
            throw new \FOSSBilling\InformationException('Staff group not found');
        }

        if ($this->templateGroupRepository->findAssociation((int) $template->getId(), $groupId) instanceof EmailTemplateGroup) {
            return true;
        }

        $this->di['em']->persist(new EmailTemplateGroup($template, $groupId));
        $this->di['em']->flush();

        $this->di['logger']->info('Assigned email template #%s to staff group #%s', $template->getId(), $groupId);

        return true;
    }

    public function removeTemplateFromGroup(EmailTemplate $template, int $groupId): bool
    {
        $association = $this->templateGroupRepository->findAssociation((int) $template->getId(), $groupId);
        if (!$association instanceof EmailTemplateGroup) {
            return true;
        }

        $this->di['em']->remove($association);
        $this->di['em']->flush();

        $this->di['logger']->info('Removed email template #%s from staff group #%s', $template->getId(), $groupId);

        return true;
    }

    public function resetTemplateByCode($code): bool
    {
        $default = $this->getDefaultTemplate((string) $code);
        if ($default === null) {
            throw new \FOSSBilling\Exception('Email template :code does not have a file-backed default', [':code' => $code]);
        }

        $template = $this->getOrCreateTemplateByCode((string) $code, ['code' => $code]);
        if ($this->isCustomTemplate($template)) {
            throw new \FOSSBilling\Exception('Custom email template :code cannot be reset to a default', [':code' => $code]);
        }

        $this->resetBuiltinTemplate($template, $default);
        $this->di['em']->flush();
        $this->di['logger']->info('Reset email template: %s', $template->getActionCode());

        return true;
    }

    public function templateCreate($actionCode, $subject, $content, $enabled = 0, $category = null): EmailTemplate
    {
        $systemService = $this->di['mod_service']('System');

        if ($subject !== null && $subject !== '') {
            $systemService->renderEmailTplString($subject, []);
        }

        if ($content !== null && $content !== '') {
            $systemService->renderEmailTplString($content, []);
        }

        $template = new EmailTemplate($actionCode);
        $template->setSubject($subject)
            ->setEnabled((bool) $enabled)
            ->setCategory($category)
            ->setContent($content)
            ->setIsCustom(true);

        $em = $this->di['em'];
        $em->persist($template);
        $em->flush();

        $this->di['logger']->info('Added new email template #%s', $template->getId());

        return $template;
    }

    public function templateBatchGenerate(): bool
    {
        return $this->syncFileBackedTemplates();
    }

    public function templateBatchRegenerate(): bool
    {
        $regenerated = 0;

        foreach ($this->getTemplateRepository()->findAll() as $template) {
            if ($this->isCustomTemplate($template)) {
                continue;
            }

            $default = $this->getDefaultTemplate($template->getActionCode());
            if ($default === null) {
                continue;
            }

            $this->resetBuiltinTemplate($template, $default);
            ++$regenerated;
        }

        $this->di['em']->flush();
        $this->di['logger']->info(sprintf('Regenerated %d existing file-backed email templates.', $regenerated));

        return true;
    }

    private function syncFileBackedTemplates(): bool
    {
        $extensionService = $this->di['mod_service']('extension');
        $templatesByCode = [];
        foreach ($this->getTemplateRepository()->findAll() as $template) {
            $templatesByCode[$template->getActionCode()] = $template;
        }

        $finder = new Finder();
        $finder = $finder->files()->in(PATH_MODS . '/*/templates/email/')->name('*.html.twig');

        foreach ($finder as $file) {
            $code = $file->getBasename('.html.twig');
            $module = strtolower(Path::getFilenameWithoutExtension(Path::getDirectory(Path::getDirectory($file->getPath()))));

            // Skip if module is not active.
            if (!$extensionService->isExtensionActive('mod', $module)) {
                continue;
            }

            $template = $templatesByCode[$code] ?? null;
            $default = $this->getDefaultTemplate($code, ['code' => $code]);
            if ($default === null) {
                continue;
            }

            if (!$template instanceof EmailTemplate) {
                $this->createBuiltinTemplateRecord($code, $default);

                continue;
            }

            if (!$this->isCustomTemplate($template)) {
                $this->syncBuiltinTemplate($template, $default);
            }
        }

        $this->di['logger']->info('Synced file-backed email templates for installed modules.');

        return true;
    }

    public function templateBatchDisable(): bool
    {
        $this->templateBatchGenerate();
        $this->getTemplateRepository()->setAllEnabled(false);
        $this->di['logger']->info('Disabled all email templates');

        return true;
    }

    public function templateBatchEnable(): bool
    {
        $this->templateBatchGenerate();
        $this->getTemplateRepository()->setAllEnabled(true);
        $this->di['logger']->info('Enabled all email templates');

        return true;
    }

    public function getTemplate(int $id): EmailTemplate
    {
        $template = $this->getTemplateRepository()->find($id);
        if (!$template instanceof EmailTemplate) {
            throw new \FOSSBilling\Exception('Email template not found');
        }

        if (!$this->isCustomTemplate($template)) {
            $default = $this->getDefaultTemplate($template->getActionCode());
            if ($default !== null) {
                $this->syncBuiltinTemplate($template, $default);
            }
        }

        return $template;
    }

    public function getTemplateList(array $data = []): array
    {
        $qb = $this->getTemplateRepository()->getSearchQueryBuilder($data);

        $result = $this->di['pager']->paginateDoctrineQuery($qb, PaginationOptions::fromArray($data));

        $list = [];
        foreach ($result['list'] as $templateRow) {
            if ($templateRow instanceof EmailTemplate) {
                $templateRow = $templateRow->toApiArray();
            }

            if (!is_array($templateRow)) {
                continue;
            }

            $list[] = $this->templateRowToApiArray($templateRow);
        }
        $result['list'] = $list;

        return $result;
    }

    public function getBrokenTemplateCount(): int
    {
        return $this->getTemplateRepository()->countBroken();
    }

    public function validateAllTemplates(): array
    {
        $templates = $this->getTemplateRepository()->findAll();
        $results = ['valid' => 0, 'invalid' => 0, 'errors' => []];
        $systemService = $this->di['mod_service']('System');

        foreach ($templates as $template) {
            $error = null;

            try {
                [$subjectTemplate, $contentTemplate] = $this->getEffectiveTemplateParts($template);
                $vars = $this->getVars($template);
                $systemService->renderEmailTplString($contentTemplate, $vars);
                $systemService->renderEmailTplString($subjectTemplate, $vars);
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }

            if ($error !== null) {
                $template->setLastError($error);
                $template->setErrorCheckedAt(new \DateTimeImmutable());
                $this->di['logger']->warning(sprintf('Email template validation failed for "%s": %s', $template->getActionCode(), $error));
                ++$results['invalid'];
                $results['errors'][] = [
                    'id' => $template->getId(),
                    'action_code' => $template->getActionCode(),
                    'error' => $error,
                ];
            } else {
                if ($template->hasError()) {
                    $template->clearError();
                }
                ++$results['valid'];
            }
        }

        $this->di['em']->flush();

        return $results;
    }

    /**
     * Sends emails from queue, respecting the configured time limit and emails per cron limit.
     * If an email fails to be sent, it will be skipped and retried on the next cron run until the retry limit is reached.
     */
    public function batchSend(): void
    {
        $mod = $this->di['mod']('email');
        $settings = $mod->getConfig();

        $time_limit = ($settings['time_limit'] ?? 5) * 60;
        $sendPerCron = $settings['queue_once'] ?? 0;

        $start = time();

        $mailQueue = $this->getQueuedEmailRepository()->findDueBatch((int) $sendPerCron);

        foreach ($mailQueue as $email) {
            $this->_sendFromQueue($email);
            if ($time_limit && time() - $start > $time_limit) {
                break;
            }
        }
    }

    /**
     * @return array{content: string, name: string, mime: string}|null
     */
    private function queuedAttachmentToArray(QueuedEmail $queue): ?array
    {
        $content = $queue->getAttachmentContent();
        if ($content === null) {
            return null;
        }

        return [
            'content' => $content,
            'name' => $queue->getAttachmentName() ?? 'attachment',
            'mime' => $queue->getAttachmentMime() ?? 'application/octet-stream',
        ];
    }

    private function _sendFromQueue(QueuedEmail $queue, bool $throw_exceptions = false): bool
    {
        $extensionService = $this->di['mod_service']('extension');
        if ($extensionService->isExtensionActive('mod', 'demo')) {
            return false;
        }
        $queue->setStatus('sending');
        $this->di['em']->flush();

        $queue->setContent($queue->getContent() . PHP_EOL);

        $mod = $this->di['mod']('email');
        $settings = $mod->getConfig();
        $log = isset($settings['log_enabled']) && $settings['log_enabled'];

        $transport = $settings['mailer'] ?? 'sendmail';
        $sender = [
            'email' => $queue->getSender(),
            'name' => $queue->getFromName(),
        ];
        $recipient = [
            'email' => $queue->getRecipient(),
            'name' => $queue->getToName(),
        ];

        $attachment = $this->queuedAttachmentToArray($queue);

        try {
            $mail = new \FOSSBilling\Mail($sender, $recipient, $queue->getSubject(), $queue->getContent(), $transport, $settings['custom_dsn'] ?? null);

            if ($attachment !== null) {
                $mail->attach($attachment['content'], $attachment['name'], $attachment['mime']);
            }

            if (!empty($settings['reply_to'])) {
                if (filter_var($settings['reply_to'], FILTER_VALIDATE_EMAIL)) {
                    $mail->addReplyTo($settings['reply_to']);
                } else {
                    $this->di['logger']->setChannel('email')->warning('Skipping invalid Reply-To address: ' . $settings['reply_to']);
                }
            }

            if (!Environment::isProduction()) {
                $this->di['logger']->setChannel('email')->info('Skip email sending. Application ENV: ' . Environment::getCurrentEnvironment());

                return true;
            }

            $mail->send($settings);

            // It sent without causing an exception (error), so we are safe to log it now
            if ($log) {
                $activityService = $this->di['mod_service']('activity');
                $activityService->logEmail($queue->getSubject(), $queue->getClientId(), $queue->getSender(), $queue->getRecipient(), $queue->getContent(), null, $attachment);
            }

            try {
                $this->di['em']->remove($queue);
                $this->di['em']->flush();
            } catch (\Exception $e) {
                $this->di['logger']->setChannel('email')->error($e->getMessage());
            }
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $this->di['logger']->setChannel('email')->error($e->getMessage());

            if ($queue->getPriority()) {
                $queue->setPriority($queue->getPriority() - 1);
            }

            $queue->setStatus(QueuedEmail::STATUS_FAILED);
            $queue->setTries($queue->getTries() + 1);
            $this->di['em']->flush();

            $maxTries = $settings['cancel_after'] ?? 5;
            if ($queue->getTries() > $maxTries) {
                // The email failed to send after the max number of tries. This might be because of a server error, so let's be sure to log it which gives the client the ability to resend it.
                if ($log) {
                    $activityService = $this->di['mod_service']('activity');
                    $activityService->logEmail($queue->getSubject(), $queue->getClientId(), $queue->getSender(), $queue->getRecipient(), $queue->getContent(), null, $attachment);
                }
                $this->di['em']->remove($queue);
                $this->di['em']->flush();
            }

            if ($throw_exceptions) {
                // If the error message is long, truncate it and inform the user the rest is in the error log.
                $truncated = (strlen($message) > 350) ? __trans('Error message truncated due to length, please check the error log for the complete message: ') . substr($message, 0, 350) . '...' : $message;

                throw new \FOSSBilling\Exception($truncated);
            }
        }

        return true;
    }
}
