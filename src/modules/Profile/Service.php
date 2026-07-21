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

use Box\Mod\Client\Entity\Client;
use Box\Mod\Staff\Entity\Admin;
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

    public function changeAdminPassword(Admin $admin, $new_password): bool
    {
        $event_params = ['id' => $admin->getId()];
        $this->di['events_manager']->fire(['event' => 'onBeforeAdminStaffProfilePasswordChange', 'params' => $event_params]);

        $admin->pass = $this->di['password']->hashIt($new_password);
        $admin->updated_at = date('Y-m-d H:i:s');
        if ($admin instanceof Admin) {
            $this->di['em']->persist($admin);
        } else {
            $this->di['em']->persist($admin);
        }
        $this->di['em']->flush();

        $event_params = [];
        $event_params['id'] = $admin->getId();
        $this->di['events_manager']->fire(['event' => 'onAfterAdminStaffProfilePasswordChange', 'params' => $event_params]);

        $this->di['logger']->info('Changed profile password');

        return true;
    }

    public function generateNewApiKey(Admin $admin): bool
    {
        $event_params = [];
        $event_params['id'] = $admin->getId();
        $this->di['events_manager']->fire(['event' => 'onBeforeAdminStaffApiKeyChange', 'params' => $event_params]);

        $admin->api_token = $this->di['tools']->generatePassword(32);
        $admin->updated_at = date('Y-m-d H:i:s');
        if ($admin instanceof Admin) {
            $this->di['em']->persist($admin);
        } else {
            $this->di['em']->persist($admin);
        }
        $this->di['em']->flush();

        $this->di['events_manager']->fire(['event' => 'onAfterAdminStaffApiKeyChange', 'params' => $event_params]);

        $this->di['logger']->info('Generated new API key');

        return true;
    }

    public function updateAdmin(Admin $admin, array $data): bool
    {
        $event_params = $data;
        $event_params['id'] = $admin->getId();
        $this->di['events_manager']->fire(['event' => 'onBeforeAdminStaffProfileUpdate', 'params' => $event_params]);

        $admin->email = $data['email'] ?? $admin->getEmail();
        $admin->name = $data['name'] ?? $admin->getName();
        $admin->signature = $data['signature'] ?? $admin->getSignature();
        if (array_key_exists('timezone', $data)) {
            $admin->timezone = i18n::validateTimezone($data['timezone']);
        }
        $admin->updated_at = date('Y-m-d H:i:s');
        if ($admin instanceof Admin) {
            $this->di['em']->persist($admin);
        } else {
            $this->di['em']->persist($admin);
        }
        $this->di['em']->flush();

        $event_params = [];
        $event_params['id'] = $admin->getId();
        $this->di['events_manager']->fire(['event' => 'onAfterAdminStaffProfileUpdate', 'params' => $event_params]);

        $this->di['logger']->info('Updated profile');

        return true;
    }

    public function getAdminIdentityArray(Admin $identity): array
    {
        return [
            'id' => $identity->getId(),
            'email' => $identity->getEmail(),
            'name' => $identity->getName(),
            'signature' => $identity->getSignature(),
            'status' => $identity->getStatus(),
            'api_token' => $identity->getApiToken(),
            'timezone' => $identity->getTimezone(),
            'created_at' => $identity->getCreatedAt()?->format('Y-m-d') ?? $identity->created_at,
            'updated_at' => $identity->getUpdatedAt()?->format('Y-m-d') ?? $identity->updated_at,
        ];
    }

    public function updateClient(Client $client, array $data = []): bool
    {
        $event_params = $data;
        $event_params['id'] = $client->getId();
        $this->di['events_manager']->fire(['event' => 'onBeforeClientProfileUpdate', 'params' => $event_params]);

        $mod = $this->di['mod']('client');
        $config = $mod->getConfig();
        $email = $data['email'] ?? '';
        if (
            $client->getEmail() != $email
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

            if ($client->getEmail() !== $email) {
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

        $client->first_name = $data['first_name'] ?? $client->getFirstName();
        if (array_key_exists('billing_email', $data)) {
            $client->billing_email = $data['billing_email'];
        }
        $client->last_name = $data['last_name'] ?? $client->getLastName();
        $client->gender = ClientValidator::validateGender($data['gender'] ?? $client->getGender());
        $client->birthday = ClientValidator::validateBirthday($data['birthday'] ?? $client->getBirthday());
        $client->company = $data['company'] ?? $client->getCompany();
        $client->company_vat = $data['company_vat'] ?? $client->getCompanyVat();
        $client->company_number = $data['company_number'] ?? $client->getCompanyNumber();
        $client->type = $data['type'] ?? $client->getType();
        $client->address_1 = $data['address_1'] ?? $client->getAddress1();
        $client->address_2 = $data['address_2'] ?? $client->getAddress2();
        $country = $data['country'] ?? $client->getCountry();
        if (!empty($country) && !Countries::exists($country)) {
            throw new InformationException('Invalid country code: :code', [':code' => $country]);
        }
        $client->country = $country;
        $client->postcode = $data['postcode'] ?? $client->getPostcode();
        $client->city = $data['city'] ?? $client->getCity();
        $client->state = $data['state'] ?? $client->getState();
        $lang = $data['lang'] ?? $client->getLang();
        if (!empty($lang) && !Locales::exists($lang)) {
            throw new InformationException('Invalid locale code: :code', [':code' => $lang]);
        }
        $client->lang = $lang;
        if (array_key_exists('timezone', $data)) {
            $client->timezone = i18n::validateTimezone($data['timezone']);
        }
        $client->notes = $data['notes'] ?? $client->getNotes();
        $client->custom_1 = $data['custom_1'] ?? $client->getCustom1();
        $client->custom_2 = $data['custom_2'] ?? $client->getCustom2();
        $client->custom_3 = $data['custom_3'] ?? $client->getCustom3();
        $client->custom_4 = $data['custom_4'] ?? $client->getCustom4();
        $client->custom_5 = $data['custom_5'] ?? $client->getCustom5();
        $client->custom_6 = $data['custom_6'] ?? $client->getCustom6();
        $client->custom_7 = $data['custom_7'] ?? $client->getCustom7();
        $client->custom_8 = $data['custom_8'] ?? $client->getCustom8();
        $client->custom_9 = $data['custom_9'] ?? $client->getCustom9();
        $client->custom_10 = $data['custom_10'] ?? $client->getCustom10();
        $client->custom_11 = $data['custom_11'] ?? $client->getCustom11();
        $client->custom_12 = $data['custom_12'] ?? $client->getCustom12();
        $client->custom_13 = $data['custom_13'] ?? $client->getCustom13();
        $client->custom_14 = $data['custom_14'] ?? $client->getCustom14();
        $client->custom_15 = $data['custom_15'] ?? $client->getCustom15();
        $client->custom_16 = $data['custom_16'] ?? $client->getCustom16();
        $client->custom_17 = $data['custom_17'] ?? $client->getCustom17();
        $client->custom_18 = $data['custom_18'] ?? $client->getCustom18();
        $client->custom_19 = $data['custom_19'] ?? $client->getCustom19();
        $client->custom_20 = $data['custom_20'] ?? $client->getCustom20();

        $client->updated_at = date('Y-m-d H:i:s');

        if ($client instanceof Client) {
            $this->di['em']->persist($client);
        } else {
            $this->di['em']->persist($client);
        }
        $this->di['em']->flush();

        $this->di['events_manager']->fire(['event' => 'onAfterClientProfileUpdate', 'params' => ['id' => $client->getId()]]);

        $this->di['logger']->info('Updated profile');

        return true;
    }

    public function resetApiKey(Client $client)
    {
        $client->api_token = $this->di['tools']->generatePassword(32);
        $client->updated_at = date('Y-m-d H:i:s');

        if ($client instanceof Client) {
            $this->di['em']->persist($client);
        } else {
            $this->di['em']->persist($client);
        }
        $this->di['em']->flush();

        $this->di['logger']->info('Generated new API key');

        return $client->getApiToken();
    }

    public function changeClientPassword(Client $client, $new_password): bool
    {
        $event_params = ['id' => $client->getId()];
        $this->di['events_manager']->fire(['event' => 'onBeforeClientProfilePasswordChange', 'params' => $event_params]);

        $client->pass = $this->di['password']->hashIt($new_password);
        if ($client instanceof Client) {
            $this->di['em']->persist($client);
        } else {
            $this->di['em']->persist($client);
        }
        $this->di['em']->flush();

        $this->di['events_manager']->fire(['event' => 'onAfterClientProfilePasswordChange', 'params' => ['id' => $client->getId()]]);

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

        return $this->di['em']->getConnection()->fetchAllAssociative($query);
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
        $this->di['em']->getConnection()->executeStatement('DELETE FROM session WHERE id = :id', ['id' => $session['id']]);
    }
}
