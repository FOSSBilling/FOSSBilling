<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\System;

use FOSSBilling\Config;
use FOSSBilling\Environment;
use FOSSBilling\GeoIP\Reader;
use FOSSBilling\SentryHelper;
use FOSSBilling\Version;
use Pimple\Container;
use PrinsFrank\Standards\Country\CountryAlpha2;
use PrinsFrank\Standards\CountryCallingCode\CountryCallingCode;
use PrinsFrank\Standards\Language\LanguageAlpha2;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Contracts\Cache\ItemInterface;

class Service
{
    protected ?Container $di = null;
    private readonly Filesystem $filesystem;

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    public function setDi(Container $di): void
    {
        $this->di = $di;
    }

    public function getModulePermissions(): array
    {
        return [
            'can_always_access' => true,
            'manage_settings' => [],
            'manage_company_details' => [
                'type' => 'bool',
                'display_name' => __trans('Manage company details'),
                'description' => __trans('Allows the staff member to update company details as set under the system module.'),
            ],
            'manage_company_legal' => [
                'type' => 'bool',
                'display_name' => __trans('Manage company legal'),
                'description' => __trans('Allows the staff member to update company legal as set under the system module.'),
            ],
            'invalidate_cache' => [
                'type' => 'bool',
                'display_name' => __trans('Invalidate cache'),
                'description' => __trans('Allows the staff member to invalidate the FOSSBilling cache from within the system settings.'),
            ],
            'system_update' => [
                'type' => 'bool',
                'display_name' => __trans('Update FOSSBilling'),
                'description' => __trans('Allows the staff member to update FOSSBilling.'),
            ],
            'manage_network_interface' => [
                'type' => 'bool',
                'display_name' => __trans('Manage the network interface'),
                'description' => __trans('Allows the staff member to fetch a list of all local interface IP addresses and set the default network interface for FOSSBilling to use.'),
            ],
        ];
    }

    /**
     * @param string $param
     * @param bool   $default
     */
    public function getParamValue($param, $default = null)
    {
        if (empty($param)) {
            throw new \FOSSBilling\Exception('Parameter key is missing');
        }

        $query = 'SELECT value
                FROM setting
                WHERE param = :param
                ';
        $pdo = $this->di['pdo'];
        $stmt = $pdo->prepare($query);
        $stmt->execute(['param' => $param]);
        $results = $stmt->fetchColumn();
        if ($results === false) {
            return $default;
        }

        return $results;
    }

    public function setParamValue($param, $value, $createIfNotExists = true): bool
    {
        // Skip this param if the user isn't permitted to update it.
        if (!$this->canUpdateParam($param)) {
            return true;
        }

        $pdo = $this->di['pdo'];
        if ($this->paramExists($param)) {
            $query = 'UPDATE setting SET value = :value WHERE param = :param';
            $stmt = $pdo->prepare($query);
            $stmt->execute(['param' => $param, 'value' => $value]);
        } elseif ($createIfNotExists) {
            try {
                $query = 'INSERT INTO setting (param, value, created_at, updated_at) VALUES (:param, :value, :created_at, :updated_at)';
                $stmt = $pdo->prepare($query);
                $stmt->execute(['param' => $param, 'value' => $value, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
            } catch (\Exception $e) {
                // ignore duplicate key error
                if ($e->getCode() != 23000) {
                    throw $e;
                }
            }
        }

        return true;
    }

    public function paramExists($param): bool
    {
        $pdo = $this->di['pdo'];
        $q = 'SELECT id
              FROM setting
              WHERE param = :param';
        $stmt = $pdo->prepare($q);
        $stmt->execute(['param' => $param]);
        $results = $stmt->fetchColumn();

        return (bool) $results;
    }

    /**
     * @param string[] $params
     *
     * @return mixed[]
     */
    private function _getMultipleParams($params): array
    {
        if (!is_array($params)) {
            return [];
        }
        foreach ($params as $param) {
            if (!preg_match('/^[a-z0-9_]+$/', $param)) {
                throw new \FOSSBilling\InformationException('Invalid parameter name, received: param_', ['param_' => $param]);
            }
        }
        $query = "SELECT param, value
                FROM setting
                WHERE param IN('" . implode("', '", $params) . "')
                ";
        $result = [];
        $rows = $this->di['db']->getAll($query);
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
        $results = $this->_getMultipleParams($c);

        $logoUrl = $results['company_logo'] ?? null;
        if ($logoUrl !== null && !str_contains($logoUrl, 'http')) {
            $logoUrl = SYSTEM_URL . $logoUrl;
        }

        $logoUrlDark = $results['company_logo_dark'] ?? null;
        if ($logoUrlDark !== null && !str_contains($logoUrlDark, 'http')) {
            $logoUrlDark = SYSTEM_URL . $logoUrlDark;
        }
        $logoUrlDark ??= $logoUrl;

        $faviconUrl = $results['company_favicon'] ?? null;
        if ($faviconUrl !== null && !str_contains($faviconUrl, 'http')) {
            $faviconUrl = SYSTEM_URL . $faviconUrl;
        }

        return [
            'www' => SYSTEM_URL,
            'name' => isset($results['company_name']) ? htmlspecialchars($results['company_name'], ENT_QUOTES, 'UTF-8') : null,
            'email' => isset($results['company_email']) ? htmlspecialchars($results['company_email'], ENT_QUOTES, 'UTF-8') : null,
            'tel' => isset($results['company_tel']) ? htmlspecialchars($results['company_tel'], ENT_QUOTES, 'UTF-8') : null,
            'signature' => $results['company_signature'] ?? null,
            'logo_url' => $logoUrl,
            'logo_url_dark' => $logoUrlDark,
            'favicon_url' => $faviconUrl,
            'address_1' => isset($results['company_address_1']) ? htmlspecialchars($results['company_address_1'], ENT_QUOTES, 'UTF-8') : null,
            'address_2' => isset($results['company_address_2']) ? htmlspecialchars($results['company_address_2'], ENT_QUOTES, 'UTF-8') : null,
            'address_3' => isset($results['company_address_3']) ? htmlspecialchars($results['company_address_3'], ENT_QUOTES, 'UTF-8') : null,
            'account_number' => $results['company_account_number'] ?? null,
            'bank_name' => isset($results['company_bank_name']) ? htmlspecialchars($results['company_bank_name'], ENT_QUOTES, 'UTF-8') : null,
            'bic' => isset($results['company_bic']) ? htmlspecialchars($results['company_bic'], ENT_QUOTES, 'UTF-8') : null,
            'display_bank_info' => $results['company_display_bank_info'] ?? null,
            'bank_info_pagebottom' => $results['company_bank_info_pagebottom'] ?? null,
            'number' => isset($results['company_number']) ? htmlspecialchars($results['company_number'], ENT_QUOTES, 'UTF-8') : null,
            'note' => $results['company_note'] ?? null,
            'privacy_policy' => $results['company_privacy_policy'] ?? null,
            'tos' => $results['company_tos'] ?? null,
            'vat_number' => isset($results['company_vat_number']) ? htmlspecialchars($results['company_vat_number'], ENT_QUOTES, 'UTF-8') : null,
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
            $this->setParamValue($key, $val, true);
        }

        $this->di['events_manager']->fire(['event' => 'onAfterAdminSettingsUpdate']);

        $this->di['logger']->info('Updated system general settings');

        return true;
    }

    public function getMessages($type)
    {
        $msgs = [];

        // Check if there's an update available
        try {
            $updater = $this->di['updater'];
            if ($updater->isUpdateAvailable()) {
                $version = $updater->getLatestVersion();
                $updateUrl = $this->di['url']->adminLink('system/update');
                $msgs['info'][] = [
                    'text' => "FOSSBilling {$version} is available for download.",
                    'url' => $updateUrl,
                ];
            }
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }

        $last_exec = $this->getParamValue('last_cron_exec');
        $disableAutoCron = Config::getProperty('disable_auto_cron', true);

        if (Environment::isProduction()) {
            $cronService = $this->di['mod_service']('cron');
            $cronUrl = $this->di['url']->adminLink('extension/settings/cron');

            // Perform the fallback behavior if enabled
            if (!$disableAutoCron && (!$last_exec || (time() - strtotime((string) $last_exec)) / 60 >= 15)) {
                $cronService->runCrons();
            }

            // And now return the correctly message for the given situation
            if (!$last_exec) {
                $msgs['danger'][] = [
                    'text' => __trans('Cron was never executed, please ensure you have configured the cronjob or else scheduled tasks within FOSSBilling will not behave correctly.'),
                    'url' => $cronUrl,
                ];
            } elseif ((time() - strtotime((string) $last_exec)) / 60 >= 15) {
                $msgs['danger'][] = [
                    'text' => __trans("FOSSBilling has detected that cron hasn't been run in an abnormal time period. Please ensure the cronjob is configured to be run every 5 minutes."),
                    'url' => $cronUrl,
                ];
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
                } else {
                    return [];
                }
            });

            if ($result) {
                $msgs['info'][] = $result;
            }
        }

        $install = Path::join(PATH_ROOT, 'install');
        if (!Environment::isDevelopment() && $this->filesystem->exists($install)) {
            $msgs['danger'][] = [
                'text' => sprintf('Install module "%s" still exists. Please remove it for security reasons.', $install),
            ];
        }

        if (!extension_loaded('openssl')) {
            $msgs['warning'][] = [
                'text' => sprintf('FOSSBilling requires %s extension to be enabled on this server for security reasons.', 'php openssl'),
            ];
        }

        return $msgs[$type] ?? [];
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
                [
                    'type' => 'warning',
                    'message' => "Warning: {$e->getMessage()}",
                ],
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

    public function renderString($tpl, $try_render, $vars)
    {
        $twig = $this->di['twig'];
        // add client api if _client_id is set
        if (isset($vars['_client_id'])) {
            $identity = $this->di['db']->load('Client', $vars['_client_id']);
            if ($identity instanceof \Model_Client) {
                try {
                    $twig->addGlobal('client', $this->di['api_client']);
                } catch (\Exception $e) {
                    error_log("api_client could not be added to template: {$e->getMessage()}.");
                }
            }
        } else {
            // attempt adding admin api to twig
            try {
                if ($this->di['auth']->isAdminLoggedIn()) {
                    $twig->addGlobal('admin', $this->di['api_admin']);
                }
            } catch (\Exception) {
                // skip if admin is not logged in
            }
        }
        if (is_null($tpl)) {
            return $this->createTemplateFromString('No template was provided, please contact the site administrator', $try_render, $vars);
        }

        try {
            $template = $twig->load($tpl);
            $parsed = $template->render($vars);
        } catch (\Exception) {
            // $twig->load throws error when $tpl is string
            $parsed = $this->createTemplateFromString($tpl, $try_render, $vars);
        }

        return $parsed;
    }

    public function createTemplateFromString($tpl, $try_render, $vars)
    {
        try {
            $twig = $this->di['twig'];
            $template = $twig->createTemplate($tpl);
            $parsed = $template->render($vars);
        } catch (\Exception $e) {
            $parsed = $tpl;
            if (!$try_render) {
                throw $e;
            }
        }

        return $parsed;
    }

    public function clearCache(): bool
    {
        $this->filesystem->remove(PATH_CACHE);
        $this->filesystem->mkdir(PATH_CACHE);

        return true;
    }

    public function getEnv($ip = null)
    {
        if ($ip) {
            try {
                return \FOSSBilling\Tools::getExternalIP();
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
        $pageScheme = $_SERVER['HTTPS'] ? 'https' : 'http';
        $pageURL = $pageScheme . '://';

        $serverPort = $_SERVER['SERVER_PORT'] ?? null;
        if (isset($serverPort) && $serverPort != '80' && $serverPort != '443') {
            $pageURL .= $_SERVER['SERVER_NAME'] ?? null . ':' . $serverPort;
        } else {
            $pageURL .= $_SERVER['SERVER_NAME'] ?? null;
        }

        $this_page = $_SERVER['REQUEST_URI'] ?? '';
        if (str_contains((string) $this_page, '?')) {
            $a = explode('?', (string) $this_page);
            $this_page = reset($a);
        }

        return $pageURL . $this_page;
    }

    public function getPeriod($code)
    {
        $p = \Box_Period::getPredefined();
        if (isset($p[$code])) {
            return $p[$code];
        }

        $p = new \Box_Period($code);

        return $p->getTitle();
    }

    public function getPublicParamValue($param)
    {
        $query = 'SELECT value
                FROM setting
                WHERE param = :param
                AND public = 1
               ';

        $pdo = $this->di['pdo'];
        $stmt = $pdo->prepare($query);
        $stmt->execute(['param' => $param]);
        $results = $stmt->fetchColumn();
        if ($results === false) {
            throw new \FOSSBilling\Exception('Parameter :param does not exist', [':param' => $param]);
        }

        return $results;
    }

    /**
     * Returns a full list of ISO3166-1 Alpha2 country codes & their titles.
     *
     * @param bool $translatedTitle set to true to have the title displayed in one of the countries native languages
     *
     * @return string[]
     */
    public function getCountries(bool $translatedTitle = false): array
    {
        $countries = [];
        foreach (CountryAlpha2::cases() as $country) {
            if ($translatedTitle) {
                $language = $country->getOfficialAndDeFactoLanguages()[0];
            } else {
                $language = LanguageAlpha2::English;
            }
            $countries[$country->value] = $country->getNameInLanguage($language);
        }

        $mod = $this->di['mod']('system');
        $config = $mod->getConfig();
        if (isset($config['countries'])) {
            preg_match_all('#([A-Z]{2})=(.+)#', $config['countries'], $matches);
            if (isset($matches[1]) && !empty($matches[1]) && isset($matches[2]) && !empty($matches[2])) {
                if ((is_countable($matches[1]) ? count($matches[1]) : 0) == (is_countable($matches[2]) ? count($matches[2]) : 0)) {
                    $countries = array_combine($matches[1], $matches[2]);
                }
            }
        }

        return $countries;
    }

    /**
     * @return mixed[]
     */
    public function getEuCountries(): array
    {
        $list = [
            'AT',
            'BE',
            'BG',
            'HR',
            'CY',
            'CZ',
            'DE',
            'DK',
            'EE',
            'ES',
            'FI',
            'FR',
            'GR',
            'HU',
            'IE',
            'IT',
            'LT',
            'LU',
            'LV',
            'MT',
            'NL',
            'PL',
            'PT',
            'RO',
            'SE',
            'SI',
            'SK',
        ];
        $c = $this->getCountries();
        $res = [];
        foreach ($list as $code) {
            if (!isset($c[$code])) {
                continue;
            }
            $res[$code] = $c[$code];
        }

        return $res;
    }

    public function getStates(): array
    {
        return [
            'AK' => 'Alaska',
            'AL' => 'Alabama',
            'AR' => 'Arkansas',
            'AZ' => 'Arizona',
            'CA' => 'California',
            'CO' => 'Colorado',
            'CT' => 'Connecticut',
            'DE' => 'Delaware',
            'FL' => 'Florida',
            'GA' => 'Georgia',
            'HI' => 'Hawaii',
            'IA' => 'Iowa',
            'ID' => 'Idaho',
            'IL' => 'Illinois',
            'IN' => 'Indiana',
            'KS' => 'Kansas',
            'KY' => 'Kentucky',
            'LA' => 'Louisiana',
            'MA' => 'Massachusetts',
            'MD' => 'Maryland',
            'ME' => 'Maine',
            'MI' => 'Michigan',
            'MN' => 'Minnesota',
            'MO' => 'Missouri',
            'MS' => 'Mississippi',
            'MT' => 'Montana',
            'NC' => 'North Carolina',
            'ND' => 'North Dakota',
            'NE' => 'Nebraska',
            'NH' => 'New Hampshire',
            'NJ' => 'New Jersey',
            'NM' => 'New Mexico',
            'NV' => 'Nevada',
            'NY' => 'New York',
            'OH' => 'Ohio',
            'OK' => 'Oklahoma',
            'OR' => 'Oregon',
            'PA' => 'Pennsylvania',
            'RI' => 'Rhode Island',
            'SC' => 'South Carolina',
            'SD' => 'South Dakota',
            'TN' => 'Tennessee',
            'TX' => 'Texas',
            'UT' => 'Utah',
            'VA' => 'Virginia',
            'VT' => 'Vermont',
            'WA' => 'Washington',
            'WI' => 'Wisconsin',
            'WV' => 'West Virginia',
            'WY' => 'Wyoming',
        ];
    }

    public function getPhoneCodes(array $data)
    {
        // If we are looking for a specific country phone code, return it if found or else generate an error
        try {
            if (isset($data['country'])) {
                $country = CountryAlpha2::from($data['country']);

                return CountryCallingCode::forCountry($country)[0]->value;
            }
        } catch (\ValueError) {
            throw new \FOSSBilling\InformationException('Country :code phone code is not registered', [':code' => $data['country']]);
        }

        $codes = [];
        foreach (CountryCallingCode::cases() as $code) {
            $country = $code->getCountriesAlpha2()[0] ?? null;
            if ($country === null) {
                continue;
            }
            $codes[$code->value] = $country->getNameInLanguage(LanguageAlpha2::English);
        }

        return $codes;
    }

    /**
     * Call this method in API to check limits for entries.
     */
    public function checkLimits($model, $limit = 2)
    {
    }

    public function getNameservers()
    {
        $query = "SELECT param, value FROM setting WHERE param IN ('nameserver_1', 'nameserver_2', 'nameserver_3', 'nameserver_4')";

        return $this->di['db']->getAssoc($query);
    }

    public function getVersion(): string
    {
        return Version::VERSION;
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
        Reader::updateDefaultDatabases();

        try {
            // Prune the classmap to remove classes which are no longer on the disk or that have moved.
            $loader = new \FOSSBilling\AutoLoader();
            $loader->getAntLoader()->pruneClassmap();

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
            'hide_version_public',
            'hide_company_public',
            'company_signature',
        ];
        $comaony_legal = ['company_tos', 'company_privacy_policy', 'company_note'];

        $staff_service = $this->di['mod_service']('Staff');
        if (in_array($param, $company) && !$staff_service->hasPermission(null, 'system', 'manage_company_details')) {
            return false;
        }

        if (in_array($param, $comaony_legal) && !$staff_service->hasPermission(null, 'system', 'manage_company_legal')) {
            return false;
        }

        return true;
    }
}
