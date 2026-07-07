<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\System;

use Doctrine\DBAL\ArrayParameterType;
use FOSSBilling\Config;
use FOSSBilling\Environment;
use FOSSBilling\GeoIP\Reader;
use FOSSBilling\Sanitizer\BrowserHtmlSanitizer;
use FOSSBilling\SentryHelper;
use FOSSBilling\Twig\SandboxedStringRenderer;
use FOSSBilling\Version;
use Pimple\Container;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Contracts\Cache\ItemInterface;

class Service
{
    private const int MYSQL_DUPLICATE_ENTRY_ERROR = 23000;

    protected ?Container $di = null;

    private Filesystem $filesystem;

    public function __construct(?Filesystem $filesystem = null)
    {
        $this->filesystem = $filesystem ?? new Filesystem();
    }

    public function setDi(Container $di): void
    {
        $this->di = $di;
        if (isset($di['filesystem'])) {
            $this->filesystem = $di['filesystem'];
        }
    }

    public function getModulePermissions(): array
    {
        return [
            'view' => [
                'type' => 'bool',
                'display_name' => __trans('View System Information'),
                'description' => __trans('Allows the staff member to view system status, update availability, and other read-only system information.'),
            ],
            'manage_settings' => [
                'type' => 'bool',
                'display_name' => __trans('Manage System Settings'),
                'description' => __trans('Allows the staff member to view and manage general system settings.'),
            ],
            'manage_company_details' => [
                'type' => 'bool',
                'display_name' => __trans('Manage Company Details'),
                'description' => __trans('Allows the staff member to update company details as set under the system module.'),
            ],
            'manage_company_legal' => [
                'type' => 'bool',
                'display_name' => __trans('Manage Company Legal'),
                'description' => __trans('Allows the staff member to update company legal as set under the system module.'),
            ],
            'update_params' => [
                'type' => 'bool',
                'display_name' => __trans('Update System Parameters'),
                'description' => __trans('Allows the staff member to update system parameters through the system API endpoint.'),
            ],
            'invalidate_cache' => [
                'type' => 'bool',
                'display_name' => __trans('Invalidate Cache'),
                'description' => __trans('Allows the staff member to invalidate the FOSSBilling cache from within the system settings.'),
            ],
            'system_update' => [
                'type' => 'bool',
                'display_name' => __trans('Update FOSSBilling'),
                'description' => __trans('Allows the staff member to update FOSSBilling.'),
            ],
            'recheck_update' => [
                'type' => 'bool',
                'display_name' => __trans('Recheck for Updates'),
                'description' => __trans('Allows the staff member to clear cached update information and fetch the latest update metadata.'),
            ],
            'toggle_error_reporting' => [
                'type' => 'bool',
                'display_name' => __trans('Toggle Error Reporting'),
                'description' => __trans('Allows the staff member to enable or disable error reporting for this FOSSBilling instance.'),
            ],
            'manage_network_interface' => [
                'type' => 'bool',
                'display_name' => __trans('Manage the Network Interface'),
                'description' => __trans('Allows the staff member to fetch a list of all local interface IP addresses and set the default network interface for FOSSBilling to use.'),
            ],
        ];
    }

    public function getParamValue(string $param, $default = null)
    {
        if (empty($param)) {
            throw new \FOSSBilling\Exception('Parameter key is missing.');
        }

        $query = $this->di['dbal']->createQueryBuilder();
        $query
            ->select('value')
            ->from('setting')
            ->where('param = :param')
            ->setParameter('param', $param);

        $result = $query->executeQuery()->fetchOne();
        if ($result === false) {
            return $default;
        }

        return $result;
    }

    public function setParamValue($param, $value, $createIfNotExists = true): bool
    {
        // Skip this param if the user isn't permitted to update it.
        if (!$this->canUpdateParam($param)) {
            return true;
        }

        if ($this->paramExists($param)) {
            $query = $this->di['dbal']->createQueryBuilder();
            $query
                ->update('setting')
                ->set('value', ':value')
                ->where('param = :param')
                ->setParameter('param', $param)
                ->setParameter('value', $value)
                ->executeStatement();
        } elseif ($createIfNotExists) {
            try {
                $query = $this->di['dbal']->createQueryBuilder();
                $query
                    ->insert('setting')
                    ->values([
                        'param' => ':param',
                        'value' => ':value',
                        'created_at' => ':created_at',
                        'updated_at' => ':updated_at',
                    ])
                    ->setParameter('param', $param)
                    ->setParameter('value', $value)
                    ->setParameter('created_at', date('Y-m-d H:i:s'))
                    ->setParameter('updated_at', date('Y-m-d H:i:s'))
                    ->executeStatement();
            } catch (\Exception $e) {
                if ($e->getCode() != self::MYSQL_DUPLICATE_ENTRY_ERROR) {
                    throw $e;
                }
            }
        }

        return true;
    }

    public function paramExists($param): bool
    {
        $query = $this->di['dbal']->createQueryBuilder();
        $query
            ->select('id')
            ->from('setting')
            ->where('param = :param')
            ->setParameter('param', $param);

        $result = $query->executeQuery()->fetchOne();

        return (bool) $result;
    }

    /**
     * Fetch setting values for the provided setting keys.
     *
     * @param string[] $params
     *
     * @return mixed[]
     */
    private function getSettingsByParams(array $params): array
    {
        foreach ($params as $param) {
            if (!preg_match('/^[a-z0-9_]+$/', (string) $param)) {
                throw new \FOSSBilling\InformationException('Invalid parameter name, received: param_.', ['param_' => $param]);
            }
        }
        $query = $this->di['dbal']->createQueryBuilder();
        $query
            ->select('param', 'value')
            ->from('setting')
            ->where('param IN (:params)')
            ->setParameter('params', $params, ArrayParameterType::STRING);

        $rows = $query->executeQuery()->fetchAllAssociative();
        $result = [];
        foreach ($rows as $row) {
            $result[$row['param']] = $row['value'];
        }

        return $result;
    }

    public function getCompany(): array
    {
        $c = [
            'company_name',
            'company_email',
            'company_tel',
            'company_signature',
            'company_logo',
            'company_logo_dark',
            'company_favicon',
            'company_address_1',
            'company_address_2',
            'company_address_3',
            'company_bank_name',
            'company_bic',
            'company_display_bank_info',
            'company_bank_info_pagebottom',
            'company_account_number',
            'company_number',
            'company_note',
            'company_privacy_policy',
            'company_tos',
            'company_vat_number',
        ];
        $results = $this->getSettingsByParams($c);

        $logoUrl = $results['company_logo'] ?? null;
        if ($logoUrl !== null && !str_contains((string) $logoUrl, 'http')) {
            $logoUrl = SYSTEM_URL . $logoUrl;
        }

        $logoUrlDark = $results['company_logo_dark'] ?? null;
        if ($logoUrlDark !== null && !str_contains((string) $logoUrlDark, 'http')) {
            $logoUrlDark = SYSTEM_URL . $logoUrlDark;
        }
        $logoUrlDark ??= $logoUrl;

        $faviconUrl = $results['company_favicon'] ?? null;
        if ($faviconUrl !== null && !str_contains((string) $faviconUrl, 'http')) {
            $faviconUrl = SYSTEM_URL . $faviconUrl;
        }

        return [
            'www' => SYSTEM_URL,
            'name' => isset($results['company_name']) ? htmlspecialchars((string) $results['company_name'], ENT_QUOTES, 'UTF-8') : null,
            'email' => isset($results['company_email']) ? htmlspecialchars((string) $results['company_email'], ENT_QUOTES, 'UTF-8') : null,
            'tel' => isset($results['company_tel']) ? htmlspecialchars((string) $results['company_tel'], ENT_QUOTES, 'UTF-8') : null,
            'signature' => $results['company_signature'] ?? null,
            'logo_url' => $logoUrl,
            'logo_url_dark' => $logoUrlDark,
            'favicon_url' => $faviconUrl,
            'address_1' => isset($results['company_address_1']) ? htmlspecialchars((string) $results['company_address_1'], ENT_QUOTES, 'UTF-8') : null,
            'address_2' => isset($results['company_address_2']) ? htmlspecialchars((string) $results['company_address_2'], ENT_QUOTES, 'UTF-8') : null,
            'address_3' => isset($results['company_address_3']) ? htmlspecialchars((string) $results['company_address_3'], ENT_QUOTES, 'UTF-8') : null,
            'account_number' => $results['company_account_number'] ?? null,
            'bank_name' => isset($results['company_bank_name']) ? htmlspecialchars((string) $results['company_bank_name'], ENT_QUOTES, 'UTF-8') : null,
            'bic' => isset($results['company_bic']) ? htmlspecialchars((string) $results['company_bic'], ENT_QUOTES, 'UTF-8') : null,
            'display_bank_info' => $results['company_display_bank_info'] ?? null,
            'bank_info_pagebottom' => $results['company_bank_info_pagebottom'] ?? null,
            'number' => isset($results['company_number']) ? htmlspecialchars((string) $results['company_number'], ENT_QUOTES, 'UTF-8') : null,
            'note' => $results['company_note'] ?? null,
            'privacy_policy' => $results['company_privacy_policy'] ?? null,
            'tos' => $results['company_tos'] ?? null,
            'vat_number' => isset($results['company_vat_number']) ? htmlspecialchars((string) $results['company_vat_number'], ENT_QUOTES, 'UTF-8') : null,
        ];
    }

    /**
     * @return mixed[]
     */
    public function getParams($data): array
    {
        $query = 'SELECT param, value
                  FROM setting';
        $rows = $this->di['db']->getAll($query);
        $result = [];
        foreach ($rows as $row) {
            $result[$row['param']] = $row['value'];
        }

        return $result;
    }

    public function updateParams($data): bool
    {
        $this->di['events_manager']->fire(['event' => 'onBeforeAdminSettingsUpdate', 'params' => $data]);

        foreach ($data as $key => $val) {
            if (!$this->canUpdateParam($key)) {
                throw new \FOSSBilling\InformationException('You do not have permission to update the parameter :param', [':param' => $key]);
            }
        }

        foreach ($data as $key => $val) {
            $this->setParamValue($key, $val, true);
        }

        $this->di['events_manager']->fire(['event' => 'onAfterAdminSettingsUpdate']);

        $this->di['logger']->info('Updated system general settings');

        return true;
    }

    private function createAdminAlert(
        string $type,
        string $message,
        ?string $title = null,
        array $buttons = [],
        bool $dismissible = true,
    ): array {
        $defaultTitles = [
            'danger' => __trans('Danger!'),
            'warning' => __trans('Warning'),
            'info' => __trans('Information'),
            'success' => __trans('Success'),
        ];

        return [
            'type' => $type,
            'title' => $title ?? ($defaultTitles[$type] ?? __trans('Notice')),
            'message' => $message,
            'buttons' => $buttons,
            'dismissible' => $dismissible,
        ];
    }

    /**
     * @return mixed[][]
     */
    public function getMessages($type = null): array
    {
        $messages = [];

        // Check if there's an update available
        try {
            $updater = $this->di['updater'];
            if ($updater->isUpdateAvailable()) {
                $version = $updater->getLatestVersion();
                $updateUrl = $this->di['url']->adminLink('system/update');
                $messages[] = $this->createAdminAlert(
                    'info',
                    __trans('FOSSBilling :version is available for download.', [':version' => $version]),
                    __trans('Update Available'),
                    [[
                        'link' => $updateUrl,
                        'text' => __trans('Review Update'),
                        'type' => 'primary',
                    ]]
                );
            }
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }

        // Check if FOSSBilling is behind on database patches
        try {
            $updater = $this->di['updater'];
            if ($updater->isBehindOnDBPatches()) {
                $messages[] = $this->createAdminAlert(
                    'warning',
                    __trans('Your FOSSBilling database is behind on database patches. Apply the pending patches to avoid issues.'),
                    __trans('Database Patches Pending'),
                    [[
                        'link' => $this->di['url']->adminLink('system/update'),
                        'text' => __trans('Apply Patches'),
                        'type' => 'warning',
                    ]]
                );
            }
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }

        if (Environment::isProduction()) {
            $last_exec = $this->getParamValue('last_cron_exec');
            $cronUrl = $this->di['url']->adminLink('extension/settings/cron');

            if (!$last_exec) {
                $messages[] = $this->createAdminAlert(
                    'danger',
                    __trans('Cron was never executed, please ensure you have configured the cronjob or else scheduled tasks within FOSSBilling will not behave correctly.'),
                    buttons: [[
                        'link' => $cronUrl,
                        'text' => __trans('Open Cron Settings'),
                        'type' => 'danger',
                    ]]
                );
            } elseif ((time() - strtotime((string) $last_exec)) / 60 >= 15) {
                $messages[] = $this->createAdminAlert(
                    'danger',
                    __trans("FOSSBilling has detected that cron hasn't been run in an abnormal time period. Please ensure the cronjob is configured to be run every 5 minutes."),
                    buttons: [[
                        'link' => $cronUrl,
                        'text' => __trans('Open Cron Settings'),
                        'type' => 'danger',
                    ]]
                );
            }
        }

        /*
         * The below logic is to help ensure that we nudge the user when needed about error reporting.
         */
        if (Environment::isProduction()) {
            // Get the last time we've nudged the user about error reporting
            $lastErrorReportingNudge = $this->getParamValue('last_error_reporting_nudge');

            $result = $this->di['cache']->get('error_reporting_nudge', function (ItemInterface $item) use ($lastErrorReportingNudge) {
                $item->expiresAfter(15 * 60);
                $url = $this->di['url']->adminLink('extension/settings/system');
                $this->setParamValue('last_error_reporting_nudge', Version::VERSION);

                if (!$lastErrorReportingNudge) {
                    // The user upgraded from a version that didn't have error reporting functionality, so let's nudge them about it now.
                    return [
                        'text' => __trans("We'd appreciate it if you'd consider opting into error reporting for FOSSBilling. Doing so will help us improve the software and provide you with a better experience. (Message will remain for 15 minutes)"),
                        'url' => $url,
                    ];
                } elseif ((version_compare(SentryHelper::last_change, $lastErrorReportingNudge) === 1) && Config::getProperty('debug_and_monitoring.report_errors', false) && !Version::isPreviewVersion()) {
                    /*
                     * The installation already had error reporting enabled, but something has changed so we should nudge the user to review the changes.
                     * This message is cached for a full 24 hours to help ensure it is seen.
                     */
                    $item->expiresAfter(60 * 60 * 24);

                    return [
                        'text' => __trans("Error reporting in FOSSBilling has changed since you last reviewed it. You may want to consider reviewing the changes to see what's been changed. (This message will remain for 24 hours)"),
                        'url' => $url,
                    ];
                }

                return [];
            });

            if ($result) {
                $messages[] = $this->createAdminAlert(
                    'info',
                    $result['text'],
                    buttons: [[
                        'link' => $result['url'],
                        'text' => __trans('Review Settings'),
                        'type' => 'primary',
                    ]]
                );
            }
        }

        $install = Path::join(PATH_ROOT, 'install');
        if ($this->filesystem->exists($install)) {
            $messages[] = $this->createAdminAlert(
                'danger',
                __trans('Installer (":path") still exists. Please remove it for security reasons.', [':path' => $install])
            );
        }

        if (!extension_loaded('openssl')) {
            $messages[] = $this->createAdminAlert(
                'warning',
                __trans('FOSSBilling requires :extension extension to be enabled on this server for security reasons.', [':extension' => 'php openssl'])
            );
        }

        try {
            $emailService = $this->di['mod_service']('email');
            $brokenTemplates = $emailService->getBrokenTemplateCount();
            if ($brokenTemplates > 0) {
                $emailSettingsUrl = $this->di['url']->adminLink('extension/settings/email');
                $messages[] = $this->createAdminAlert(
                    'warning',
                    __trans(':count email template(s) have syntax errors and cannot send emails. Please review and fix them.', [':count' => $brokenTemplates]),
                    __trans('Broken Email Templates'),
                    [[
                        'link' => $emailSettingsUrl,
                        'text' => __trans('View Email Templates'),
                        'type' => 'warning',
                    ]]
                );
            }
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }

        if ($type === null || $type === '') {
            return $messages;
        }

        return array_values(array_filter(
            $messages,
            static fn (array $message): bool => ($message['type'] ?? null) === $type
        ));
    }

    /**
     * Get the Central Alerts System messages sent to this installation.
     *
     * @return array - array of messages
     */
    public function getCasMessages(): array
    {
        try {
            return $this->di['central_alerts']->filterAlerts();
        } catch (\FOSSBilling\Exception $e) {
            return [
                $this->createAdminAlert('warning', $e->getMessage()),
            ];
        }
    }

    public function templateExists($file, $identity = null): bool
    {
        if ($identity instanceof \Model_Admin) {
            $client = false;
        } else {
            $client = true;
        }
        $themeService = $this->di['mod_service']('theme');
        $theme = $themeService->getThemeConfig($client);
        foreach ($theme['paths'] as $path) {
            if ($this->filesystem->exists(Path::join($path, $file))) {
                return true;
            }
        }

        return false;
    }

    public function renderAdapterTplString(string $tpl, array $vars): string
    {
        $twigFactory = $this->di['twig_factory'];
        $twig = $twigFactory->createAdapterEnvironment();

        $rendered = SandboxedStringRenderer::render(
            $twig,
            $tpl,
            $vars,
            'Payment adapter template',
            function (\Twig\Sandbox\SecurityError $e): void {
                $this->di['logger']->setChannel('security')->warning('Payment adapter template sandbox violation', [
                    'error' => $e->getMessage(),
                ]);
            }
        );

        return BrowserHtmlSanitizer::sanitizeAdapterHtml($rendered);
    }

    /**
     * Render a template string using the sandboxed email Twig environment.
     * Use this for database-stored templates (email templates, mass mailer).
     *
     * @param string      $tpl      The template string to render
     * @param array       $vars     Variables to pass to the template
     * @param string|null $timezone Optional IANA timezone for date formatting;
     *                              pass the recipient's so emails render in
     *                              their local time. Falls back to the active
     *                              user's timezone, then the config.
     *
     * @return string The rendered template
     *
     * @throws \FOSSBilling\InformationException If template violates sandbox policy or has syntax errors
     */
    public function renderEmailTplString(string $tpl, array $vars, ?string $timezone = null): string
    {
        $twigFactory = $this->di['twig_factory'];
        $twig = $twigFactory->createEmailEnvironment($timezone);

        return SandboxedStringRenderer::render(
            $twig,
            $tpl,
            $vars,
            'Email template',
            function (\Twig\Sandbox\SecurityError $e): void {
                $this->di['logger']->setChannel('security')->warning('Email template sandbox violation', [
                    'error' => $e->getMessage(),
                ]);
            }
        );
    }

    public function checkEmailTplSyntax(string $tpl): void
    {
        $twigFactory = $this->di['twig_factory'];
        $twig = $twigFactory->createEmailEnvironment();

        try {
            $stream = $twig->tokenize(new \Twig\Source($tpl, '__validation__'));
            $twig->parse($stream);
        } catch (\Twig\Error\SyntaxError $e) {
            throw new \FOSSBilling\InformationException('Email template syntax error: ' . $e->getMessage());
        } catch (\Twig\Sandbox\SecurityError $e) {
            throw new \FOSSBilling\InformationException('Email template contains disallowed Twig syntax: ' . $e->getMessage());
        }
    }

    public function clearCache(?string $cachePath = null): bool
    {
        $path = $cachePath ?? PATH_CACHE;
        $this->filesystem->remove($path);
        $this->filesystem->mkdir($path);

        return true;
    }

    public function getEnv(bool $fetchExternalIp = false)
    {
        if ($fetchExternalIp) {
            try {
                return $this->di['tools']->getExternalIP();
            } catch (\Exception) {
                return '';
            }
        }

        $r = new \FOSSBilling\Requirements();
        $data = $r->checkCompat();
        $data['last_patch'] = $this->getParamValue('last_patch');

        return $data;
    }

    public function getCurrentUrl(): string
    {
        $request = $this->di['request'];

        return $request->getSchemeAndHttpHost() . strtok($request->getRequestUri(), '?');
    }

    public function getPeriod($code)
    {
        if (!is_scalar($code)) {
            return '-';
        }

        $code = (string) $code;
        if ($code === '' || $code === '0') {
            return '-';
        }

        $p = \Box_Period::getPredefined();
        if (isset($p[$code])) {
            return $p[$code];
        }

        $p = new \Box_Period($code);

        return $p->getTitle();
    }

    public function getPublicParamValue($param)
    {
        $query = $this->di['dbal']->createQueryBuilder();
        $query
            ->select('value')
            ->from('setting')
            ->where('param = :param')
            ->andWhere('public = 1')
            ->setParameter('param', $param);

        $result = $query->executeQuery()->fetchOne();
        if ($result === false) {
            throw new \FOSSBilling\Exception('Parameter :param does not exist', [':param' => $param]);
        }

        return $result;
    }

    public function getNameservers()
    {
        $query = "SELECT param, value FROM setting WHERE param IN ('nameserver_1', 'nameserver_2', 'nameserver_3', 'nameserver_4')";

        return $this->di['db']->getAssoc($query);
    }

    public function getPendingMessages()
    {
        $messages = $this->di['session']->get('pending_messages');

        if (!is_array($messages)) {
            return [];
        }

        return $messages;
    }

    public function setPendingMessage($msg): bool
    {
        $messages = $this->getPendingMessages();
        $messages[] = $msg;
        $this->di['session']->set('pending_messages', $messages);

        return true;
    }

    public function clearPendingMessages(): bool
    {
        $this->di['session']->delete('pending_messages');

        return true;
    }

    public static function onBeforeAdminCronRun(\Box_Event $event): void
    {
        $di = $event->getDi();
        /** @var Reader $geoipReader */
        $geoipReader = (new \ReflectionClass(Reader::class))->newInstanceWithoutConstructor();
        $geoipReader->setDi($di);
        $geoipReader->updateDefaultDatabases();

        try {
            // Prune the FS cache
            $cache = $di['cache'];
            if ($cache->prune()) {
                $di['logger']->setChannel('cron')->info('Pruned the filesystem cache');
            }
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }
    }

    private function canUpdateParam(string $param): bool
    {
        $company = [
            'company_name',
            'company_email',
            'company_tel',
            'company_address_1',
            'company_address_2',
            'company_address_3',
            'company_logo',
            'company_logo_dark',
            'company_favicon',
            'company_number',
            'company_vat_number',
            'company_account_number',
            'company_bank_name',
            'company_bic',
            'company_display_bank_info',
            'company_bank_info_pagebottom',
            'hide_company_public',
            'company_signature',
        ];
        $company_legal = ['company_tos', 'company_privacy_policy', 'company_note'];

        $staff_service = $this->di['mod_service']('Staff');
        if (in_array($param, $company) && !$staff_service->hasPermission(null, 'system', 'manage_company_details')) {
            return false;
        }

        if (in_array($param, $company_legal) && !$staff_service->hasPermission(null, 'system', 'manage_company_legal')) {
            return false;
        }

        return true;
    }
}
