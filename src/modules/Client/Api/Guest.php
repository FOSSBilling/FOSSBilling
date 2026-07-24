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
 * Clients API methods.
 */

namespace Box\Mod\Client\Api;

use Box\Mod\Client\Entity\Client;
use Box\Mod\Client\Entity\ClientPasswordReset;
use FOSSBilling\Http\CookieNames;
use FOSSBilling\Security\RandomizedTimeFloor;
use FOSSBilling\Tools;
use FOSSBilling\Validation\Api\RequiredParams;

class Guest extends \FOSSBilling\Api\AbstractApi
{
    /**
     * Client signup action.
     *
     * @optional string $last_name - last name
     * @optional string $aid - Alternative id. Usually used by import tools.
     * @optional string $gender - Gender - values: male|female|nonbinary|other
     * @optional string $country - Country
     * @optional string $city - city
     * @optional string $birthday - Birthday
     * @optional string $type - Identifies client type: company or individual
     * @optional string $company - Company
     * @optional string $company_vat - Company VAT number
     * @optional string $company_number - Company number
     * @optional string $address_1 - Address line 1
     * @optional string $address_2 - Address line 2
     * @optional string $postcode - zip or postcode
     * @optional string $state - country state
     * @optional string $phone - Phone number
     * @optional string $phone_cc - Phone country code
     * @optional string $notes - Notes about client. Visible for admin only
     * @optional string $lang - Client language
     * @optional string $timezone - IANA timezone identifier (e.g. "America/New_York"). Used to localize dates and times shown to the client.
     * @optional string $custom_1 - Custom field 1
     * @optional string $custom_2 - Custom field 2
     * @optional string $custom_3 - Custom field 3
     * @optional string $custom_4 - Custom field 4
     * @optional string $custom_5 - Custom field 5
     * @optional string $custom_6 - Custom field 6
     * @optional string $custom_7 - Custom field 7
     * @optional string $custom_8 - Custom field 8
     * @optional string $custom_9 - Custom field 9
     * @optional string $custom_10 - Custom field 10
     * @optional string $custom_11 - Custom field 11
     * @optional string $custom_12 - Custom field 12
     * @optional string $custom_13 - Custom field 13
     * @optional string $custom_14 - Custom field 14
     * @optional string $custom_15 - Custom field 15
     * @optional string $custom_16 - Custom field 16
     * @optional string $custom_17 - Custom field 17
     * @optional string $custom_18 - Custom field 18
     * @optional string $custom_19 - Custom field 19
     * @optional string $custom_20 - Custom field 20
     */
    #[RequiredParams(['email' => 'Email required', 'first_name' => 'First name required', 'password' => 'Password required', 'password_confirm' => 'Password confirmation required'])]
    public function create($data = []): int
    {
        $this->getDi()['rate_limiter']->consumeOrThrow('client_signup', (string) $this->getIp());

        $config = $this->getDi()['mod_config']('client');

        if (isset($config['disable_signup']) && $config['disable_signup']) {
            throw new \FOSSBilling\InformationException('New registrations are temporarily disabled');
        }

        $this->getDi()['validator']->passwordsMatch($data);

        $this->getService()->checkExtraRequiredFields($data);
        $this->getService()->checkCustomFields($data);

        $this->getDi()['validator']->isPasswordStrong($data['password']);
        $service = $this->getService();

        $email = $data['email'] ?? null;
        $email = $this->getDi()['tools']->validateAndSanitizeEmail($email);
        $email = strtolower(trim((string) $email));
        if ($service->clientAlreadyExists($email)) {
            throw new \FOSSBilling\InformationException('This email address is already registered.');
        }

        $client = $service->guestCreateClient($data);

        if (isset($config['require_email_confirmation']) && (bool) $config['require_email_confirmation']) {
            $service->sendEmailConfirmationForClient($client);
        }

        if (Tools::normalizeBoolean($config['auto_login_after_signup'] ?? true, true)) {
            try {
                $this->login(['email' => $client->getEmail(), 'password' => $data['password']]);
            } catch (\Throwable $e) {
                error_log($e->getMessage());
            }
        }

        return (int) $client->getId();
    }

    /**
     * Client login action.
     *
     * @return array - session data
     *
     * @throws \FOSSBilling\InformationException
     */
    #[RequiredParams(['email' => 'Email required', 'password' => 'Password required'])]
    public function login($data)
    {
        $startedAt = microtime(true);

        try {
            $this->getDi()['tools']->validateAndSanitizeEmail($data['email'], true, false);

            $event_params = $data;
            $event_params['ip'] = $this->ip;
            $this->getDi()['events_manager']->fire(['event' => 'onBeforeClientLogin', 'params' => $event_params]);

            $service = $this->getService();
            $client = $service->authorizeClient($data['email'], $data['password']);

            if (!$client instanceof Client) {
                $this->getDi()['events_manager']->fire(['event' => 'onEventClientLoginFailed', 'params' => $event_params]);

                throw new \FOSSBilling\InformationException('Please check your login details.', [], 401);
            }

            $this->getDi()['events_manager']->fire(['event' => 'onAfterClientLogin', 'params' => ['id' => $client->getId(), 'ip' => $this->ip]]);

            $oldSession = $this->getDi()['session']->getId();
            $this->getDi()['session']->regenerateId();
            $result = $service->toSessionArray($client);
            $this->getDi()['session']->set('client_id', $client->getId());

            $this->getDi()['logger']->info('Client #%s logged in', $client->getId());
            $this->getDi()['session']->delete('redirect_uri');

            if (!empty($client->getLang())) {
                $this->getDi()['cookie_queue']->queue(CookieNames::LOCALE, (string) $client->getLang(), strtotime('+1 month'), '/');
            }

            $this->getDi()['mod_service']('cart')->transferFromOtherSession($oldSession);

            return $result;
        } finally {
            RandomizedTimeFloor::apply($startedAt);
        }
    }

    /**
     * Password reset confirmation email will be sent to email.
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['email' => 'Email required'])]
    public function reset_password($data): bool
    {
        $startedAt = microtime(true);

        try {
            $this->getDi()['events_manager']->fire(['event' => 'onBeforePasswordResetClient']);
            $service = $this->getDi()['mod_service']('client');

            // Sanitize email
            $data['email'] = $this->getDi()['tools']->validateAndSanitizeEmail($data['email']);

            $ipLimit = $this->getDi()['rate_limiter']->consume('client_password_reset_ip', (string) $this->getIp());
            if ($ipLimit->isLimited()) {
                $this->getDi()['logger']->setChannel('security')->info('Client password reset rate limited from IP %s: email %s', $this->getIp(), $data['email']);

                return true;
            }

            $emailLimit = $this->getDi()['rate_limiter']->consume('client_password_reset_email', (string) $data['email']);
            if ($emailLimit->isLimited()) {
                $this->getDi()['logger']->setChannel('security')->info('Client password reset rate limited for email %s from IP %s', $data['email'], $this->getIp());

                return true;
            }

            $this->checkPasswordResetCaptcha($data);

            $this->getDi()['events_manager']->fire(['event' => 'onBeforeGuestPasswordResetRequest', 'params' => $data]);

            $em = $this->getDi()['em'];
            $client = $em->getRepository(Client::class)->findOneByEmailAndActive($data['email']);
            if (!$client instanceof Client) {
                $this->getDi()['logger']->setChannel('security')->info('Client password reset requested for unknown email %s from IP %s', $data['email'], $this->getIp());

                return true;
            }

            if ($client->getStatus() !== Client::ACTIVE) {
                $this->getDi()['logger']->setChannel('security')->info('Client password reset requested for ineligible client #%s from IP %s: email %s, account status %s', $client->getId(), $this->getIp(), $data['email'], $client->getStatus());

                return true;
            }

            $hash = $service->createPasswordResetRequestForClient($client);
            $service->sendPasswordResetRequestEmailForClient($client, $hash);

            $this->getDi()['logger']->setChannel('security')->info('Client password reset email queued for client #%s from IP %s: email %s', $client->getId(), $this->getIp(), $data['email']);

            return true;
        } finally {
            RandomizedTimeFloor::apply($startedAt, 300, 450);
        }
    }

    private function checkPasswordResetCaptcha(array $data): void
    {
        $extensionService = $this->getDi()['mod_service']('extension');
        if (!$extensionService->isExtensionActive('mod', 'antispam')) {
            return;
        }

        $this->getDi()['mod_service']('Antispam')->checkCaptcha($data);
    }

    #[RequiredParams(['hash' => 'No Hash provided', 'password' => 'Password required', 'password_confirm' => 'Password confirmation required'])]
    public function update_password($data): bool
    {
        $startedAt = microtime(true);

        try {
            $this->getDi()['rate_limiter']->consumeOrThrow('client_password_reset_confirm_post_ip', (string) $this->getIp());

            $this->getDi()['events_manager']->fire(['event' => 'onBeforeClientProfilePasswordReset', 'params' => $data['hash']]);

            $this->getDi()['validator']->passwordsMatch($data);
            $this->getDi()['validator']->isPasswordStrong($data['password']);

            $em = $this->getDi()['em'];
            $reset = $em->getRepository(ClientPasswordReset::class)->findOneByHash($data['hash']);
            if (!$reset instanceof ClientPasswordReset) {
                $this->getDi()['logger']->setChannel('security')->info('Client password reset confirmation failed from IP %s: reset token not found', $this->getIp());

                throw new \FOSSBilling\InformationException('The link has expired or you have already reset your password.');
            }

            if (strtotime((string) $reset->getCreatedAt()?->format('Y-m-d H:i:s')) - time() + 900 < 0) {
                $this->getDi()['logger']->setChannel('security')->info('Client password reset confirmation failed for client #%s from IP %s: reset token expired', $reset->getClientId(), $this->getIp());

                throw new \FOSSBilling\InformationException('The link has expired or you have already reset your password.');
            }

            $client = $reset->getClientId() !== null ? $em->getRepository(Client::class)->find($reset->getClientId()) : null;
            if (!$client instanceof Client) {
                throw new \FOSSBilling\InformationException('The link has expired or you have already reset your password.');
            }

            if ($client->getStatus() !== Client::ACTIVE) {
                $this->getDi()['logger']->setChannel('security')->info('Client password reset confirmation failed for client #%s from IP %s: account status %s', $client->getId(), $this->getIp(), $client->getStatus());

                throw new \FOSSBilling\InformationException('The link has expired or you have already reset your password.');
            }

            $client->setPass($this->getDi()['password']->hashIt($data['password']));
            $em->persist($client);
            $em->remove($reset);
            $em->flush();

            $profileService = $this->getDi()['mod_service']('profile');
            $profileService->invalidateSessions('client', (int) $client->getId());

            $this->getDi()['logger']->setChannel('security')->info('Client password reset completed for client #%s from IP %s', $client->getId(), $this->getIp());

            // send email
            $email = [];
            $email['to_client'] = $client->getId();
            $email['code'] = 'mod_client_password_reset_information';
            $emailService = $this->getDi()['mod_service']('email');
            $emailService->sendTemplate($email);
            $this->getDi()['events_manager']->fire(['event' => 'onAfterClientProfilePasswordReset', 'params' => ['id' => $client->getId()]]);

            return true;
        } finally {
            RandomizedTimeFloor::apply($startedAt);
        }
    }

    /**
     * List of required fields for client registration.
     */
    public function required()
    {
        $config = $this->getDi()['mod_config']('client');

        return $config['required'] ?? [];
    }

    /**
     * Array of custom fields for client registration, sorted alphabetically by title.
     */
    public function custom_fields()
    {
        $config = $this->getDi()['mod_config']('client');
        $customFields = $config['custom_fields'] ?? [];
        $customFields = is_array($customFields) ? $customFields : [];

        foreach ($customFields as $fieldName => $field) {
            $field = is_array($field) ? $field : [];
            $title = $field['title'] ?? '';

            $field['title'] = is_scalar($title) ? (string) $title : '';
            $field['active'] = Tools::normalizeBoolean($field['active'] ?? false);
            $field['required'] = Tools::normalizeBoolean($field['required'] ?? false);
            $customFields[$fieldName] = $field;
        }

        uasort($customFields, fn ($a, $b): int => strnatcasecmp($a['title'], $b['title']));

        return $customFields;
    }

    public function is_email_validation_required(): bool
    {
        $config = $this->getDi()['mod_config']('client');

        return (bool) ($config['require_email_confirmation'] ?? false);
    }
}
