<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Profile;

use FOSSBilling\i18n;
use FOSSBilling\InformationException;
use FOSSBilling\InjectionAwareInterface;
use FOSSBilling\Tools;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Intl\Locales;

class Service implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

    public function getModulePermissions(): array
    {
        return [
            'can_always_access' => true,
            'hide_permissions' => true,
        ];
    }

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function changeAdminPassword(\Model_Admin $admin, $new_password): bool
    {
        $event_params = ['id' => $admin->id];
        $this->di['events_manager']->fire(['event' => 'onBeforeAdminStaffProfilePasswordChange', 'params' => $event_params]);

        $admin->pass = $this->di['password']->hashIt($new_password);
        $admin->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($admin);

        $event_params = [];
        $event_params['id'] = $admin->id;
        $this->di['events_manager']->fire(['event' => 'onAfterAdminStaffProfilePasswordChange', 'params' => $event_params]);

        $this->di['logger']->info('Changed profile password');

        return true;
    }

    public function generateNewApiKey(\Model_Admin $admin): bool
    {
        $event_params = [];
        $event_params['id'] = $admin->id;
        $this->di['events_manager']->fire(['event' => 'onBeforeAdminStaffApiKeyChange', 'params' => $event_params]);

        $admin->api_token = $this->di['tools']->generatePassword(32);
        $admin->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($admin);

        $this->di['events_manager']->fire(['event' => 'onAfterAdminStaffApiKeyChange', 'params' => $event_params]);

        $this->di['logger']->info('Generated new API key');

        return true;
    }

    public function updateAdmin(\Model_Admin $admin, array $data): bool
    {
        $event_params = $data;
        $event_params['id'] = $admin->id;
        $this->di['events_manager']->fire(['event' => 'onBeforeAdminStaffProfileUpdate', 'params' => $event_params]);

        $admin->email = $data['email'] ?? $admin->email;
        $admin->name = $data['name'] ?? $admin->name;
        $admin->signature = $data['signature'] ?? $admin->signature;
        if (array_key_exists('timezone', $data)) {
            $admin->timezone = i18n::validateTimezone($data['timezone']);
        }
        $admin->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($admin);

        $event_params = [];
        $event_params['id'] = $admin->id;
        $this->di['events_manager']->fire(['event' => 'onAfterAdminStaffProfileUpdate', 'params' => $event_params]);

        $this->di['logger']->info('Updated profile');

        return true;
    }

    public function getAdminIdentityArray(\Model_Admin $identity): array
    {
        return [
            'id' => $identity->id,
            'email' => $identity->email,
            'name' => $identity->name,
            'signature' => $identity->signature,
            'status' => $identity->status,
            'api_token' => $identity->api_token,
            'timezone' => $identity->timezone,
            'created_at' => $identity->created_at,
            'updated_at' => $identity->updated_at,
        ];
    }

    public function updateClient(\Model_Client $client, array $data = []): bool
    {
        $event_params = $data;
        $event_params['id'] = $client->id;
        $this->di['events_manager']->fire(['event' => 'onBeforeClientProfileUpdate', 'params' => $event_params]);

        $mod = $this->di['mod']('client');
        $config = $mod->getConfig();
        $email = $data['email'] ?? '';
        if (
            $client->email != $email
            && isset($config['disable_change_email'])
            && $config['disable_change_email']
        ) {
            throw new InformationException('Email address cannot be changed');
        }

        if (!empty($email)) {
            $this->di['tools']->validateAndSanitizeEmail($data['email']);

            $clientService = $this->di['mod_service']('client');
            if ($clientService->emailAlreadyRegistered($email, $client)) {
                throw new InformationException('This email address is already registered.');
            }

            if ($client->email !== $email) {
                $client->email = $email;
                $client->email_approved = false;

                $clientConfig = $this->di['mod_config']('client');
                if (isset($clientConfig['require_email_confirmation']) && $clientConfig['require_email_confirmation']) {
                    $clientService = $this->di['mod_service']('client');
                    $clientService->sendEmailConfirmationForClient($client);
                }
            }
        }

        if (isset($data['phone_cc']) && $data['phone_cc'] !== '') {
            $client->phone_cc = Tools::validatePhoneCC($data['phone_cc']);
        }

        if (isset($data['phone']) && is_string($data['phone']) && $data['phone'] !== '') {
            $client->phone = Tools::validatePhoneNumber($data['phone']);
        }

        $client->first_name = $data['first_name'] ?? $client->first_name;
        $client->last_name = $data['last_name'] ?? $client->last_name;
        $client->gender = ClientValidator::validateGender($data['gender'] ?? $client->gender);
        $client->birthday = ClientValidator::validateBirthday($data['birthday'] ?? $client->birthday);
        $client->company = $data['company'] ?? $client->company;
        $client->company_vat = $data['company_vat'] ?? $client->company_vat;
        $client->company_number = $data['company_number'] ?? $client->company_number;
        $client->type = $data['type'] ?? $client->type;
        $client->address_1 = $data['address_1'] ?? $client->address_1;
        $client->address_2 = $data['address_2'] ?? $client->address_2;
        $country = $data['country'] ?? $client->country;
        if (!empty($country) && !Countries::exists($country)) {
            throw new InformationException('Invalid country code: :code', [':code' => $country]);
        }
        $client->country = $country;
        $client->postcode = $data['postcode'] ?? $client->postcode;
        $client->city = $data['city'] ?? $client->city;
        $client->state = $data['state'] ?? $client->state;
        $lang = $data['lang'] ?? $client->lang;
        if (!empty($lang) && !Locales::exists($lang)) {
            throw new InformationException('Invalid locale code: :code', [':code' => $lang]);
        }
        $client->lang = $lang;
        if (array_key_exists('timezone', $data)) {
            $client->timezone = i18n::validateTimezone($data['timezone']);
        }
        $client->notes = $data['notes'] ?? $client->notes;
        $client->custom_1 = $data['custom_1'] ?? $client->custom_1;
        $client->custom_2 = $data['custom_2'] ?? $client->custom_2;
        $client->custom_3 = $data['custom_3'] ?? $client->custom_3;
        $client->custom_4 = $data['custom_4'] ?? $client->custom_4;
        $client->custom_5 = $data['custom_5'] ?? $client->custom_5;
        $client->custom_6 = $data['custom_6'] ?? $client->custom_6;
        $client->custom_7 = $data['custom_7'] ?? $client->custom_7;
        $client->custom_8 = $data['custom_8'] ?? $client->custom_8;
        $client->custom_9 = $data['custom_9'] ?? $client->custom_9;
        $client->custom_10 = $data['custom_10'] ?? $client->custom_10;
        $client->custom_11 = $data['custom_11'] ?? $client->custom_11;
        $client->custom_12 = $data['custom_12'] ?? $client->custom_12;
        $client->custom_13 = $data['custom_13'] ?? $client->custom_13;
        $client->custom_14 = $data['custom_14'] ?? $client->custom_14;
        $client->custom_15 = $data['custom_15'] ?? $client->custom_15;
        $client->custom_16 = $data['custom_16'] ?? $client->custom_16;
        $client->custom_17 = $data['custom_17'] ?? $client->custom_17;
        $client->custom_18 = $data['custom_18'] ?? $client->custom_18;
        $client->custom_19 = $data['custom_19'] ?? $client->custom_19;
        $client->custom_20 = $data['custom_20'] ?? $client->custom_20;

        $client->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($client);

        $this->di['events_manager']->fire(['event' => 'onAfterClientProfileUpdate', 'params' => ['id' => $client->id]]);

        $this->di['logger']->info('Updated profile');

        return true;
    }

    public function resetApiKey(\Model_Client $client)
    {
        $client->api_token = $this->di['tools']->generatePassword(32);
        $client->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($client);

        $this->di['logger']->info('Generated new API key');

        return $client->api_token;
    }

    public function changeClientPassword(\Model_Client $client, $new_password): bool
    {
        $event_params = ['id' => $client->id];
        $this->di['events_manager']->fire(['event' => 'onBeforeClientProfilePasswordChange', 'params' => $event_params]);

        $client->pass = $this->di['password']->hashIt($new_password);
        $this->di['db']->store($client);

        $this->di['events_manager']->fire(['event' => 'onAfterClientProfilePasswordChange', 'params' => ['id' => $client->id]]);

        $this->di['logger']->info('Changed profile password');

        return true;
    }

    public function logoutClient(): bool
    {
        $this->di['session']->destroy('client');
        $this->di['logger']->info('Logged out');

        return true;
    }

    public function invalidateSessions(?string $type = null, ?int $id = null): bool
    {
        if (empty($type)) {
            $auth = new \Box_Authorization($this->di);
            if ($auth->isAdminLoggedIn()) {
                $type = 'admin';
            } elseif ($auth->isClientLoggedIn()) {
                $type = 'client';
            } else {
                throw new \FOSSBilling\Exception('Unable to invalidate sessions, nobody is logged in');
            }
        }

        if (empty($id)) {
            switch ($type) {
                case 'admin':
                    $admin = $this->di['session']->get('admin');
                    $id = $admin['id'];

                    break;
                case 'client':
                    $id = $this->di['session']->get('client_id');

                    break;
            }
        }

        if ($type !== 'admin' && $type !== 'client') {
            throw new \FOSSBilling\Exception('Unable to invalidate sessions, an invalid type was used');
        }

        $sessions = $this->getSessions();
        foreach ($sessions as $session) {
            $this->deleteSessionIfMatching($session, $type, $id);
        }

        return true;
    }

    private function getSessions(): array
    {
        $query = 'SELECT * FROM session WHERE content IS NOT NULL AND content <> ""';

        return $this->di['db']->getAll($query);
    }

    private function deleteSessionIfMatching(array $session, string $type, int $id): void
    {
        $data = base64_decode((string) $session['content']);
        $stringStart = ($type === 'admin') ? 'admin|' : 'client_id|';
        if (!str_starts_with($data, $stringStart)) {
            return;
        }

        $data = str_replace($stringStart, '', $data);

        if ($type === 'admin') {
            $dataArray = $this->phpSessionDecode($data);
            if (is_array($dataArray) && isset($dataArray['id']) && (int) $dataArray['id'] === $id) {
                $this->trashSessionByArray($session);
            }
        } else {
            $clientId = $this->phpSessionDecode($data);
            if (is_int($clientId) && $clientId === $id) {
                $this->trashSessionByArray($session);
            }
        }
    }

    private function phpSessionDecode(string $data): array|int|false
    {
        if ($data === '' || !in_array($data[0], ['a', 'i'], true)) {
            return false;
        }

        $result = unserialize($data, ['allowed_classes' => false]);
        if (is_array($result) || is_int($result)) {
            return $result;
        }

        return false;
    }

    private function trashSessionByArray(array $session): void
    {
        $bean = $this->di['db']->dispense('session');
        $bean->import($session);
        $this->di['db']->trash($bean);
    }
}
