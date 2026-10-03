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
use Box\Mod\Client\Event\AfterClientLoginEvent;
use Box\Mod\Client\Event\AfterClientPasswordResetEvent;
use Box\Mod\Client\Event\BeforeClientLoginEvent;
use Box\Mod\Client\Event\BeforeClientPasswordResetConfirmationEvent;
use Box\Mod\Client\Event\BeforeClientPasswordResetEvent;
use Box\Mod\Client\Event\BeforeClientPasswordResetRequestEvent;
use Box\Mod\Client\Event\ClientLoginFailedEvent;
use Box\Mod\Client\Service;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use FOSSBilling\Doctrine\EntityManagerFactory;
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
    public function create($data = []): bool
    {
        $startedAt = microtime(true);

        try {
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

            $this->checkCaptchaIfEnabled($data);

            // Keyed independently of the IP limiter above so that spreading
            // probes across IPs doesn't help an attacker hammer one address.
            // Check the quota without consuming it. A token is recorded only
            // after client validation succeeds, so malformed submissions
            // cannot exhaust another address's signup quota.
            $emailLimit = $this->getDi()['rate_limiter']->consume('client_signup_email', $email, 0);

            $autoLogin = Tools::normalizeBoolean($config['auto_login_after_signup'] ?? true, true);

            if ($emailLimit->isLimited() || $service->clientAlreadyExists($email)) {
                if (!$emailLimit->isLimited()) {
                    $this->getDi()['rate_limiter']->consume('client_signup_email', $email);
                }

                return $this->handleExistingOrRateLimitedSignup($email, $data, $autoLogin);
            }

            try {
                $client = $service->guestCreateClient($data);
            } catch (UniqueConstraintViolationException $exception) {
                $this->resetEntityManagerAfterViolation($service);

                // guestCreateClient() only persists a Client, whose sole
                // unique key is `client.email`. Re-check so an unrelated
                // constraint failure still surfaces instead of being masked
                // as an existing-account signup.
                try {
                    $duplicate = $service->clientAlreadyExists($email);
                } catch (\Throwable) {
                    throw $exception;
                }

                if (!$duplicate) {
                    throw $exception;
                }

                // The zero-token probe above passed (a limited result would
                // have returned early), so record the quota use just like the
                // existing-account path does.
                $this->getDi()['rate_limiter']->consume('client_signup_email', $email);

                return $this->handleExistingOrRateLimitedSignup($email, $data, $autoLogin);
            }

            $this->getDi()['rate_limiter']->consume('client_signup_email', $email);

            if (isset($config['require_email_confirmation']) && (bool) $config['require_email_confirmation']) {
                $service->sendEmailConfirmationForClient($client);
            }

            if ($autoLogin) {
                try {
                    $this->login(['email' => $client->getEmail(), 'password' => $data['password']]);
                } catch (\Throwable $e) {
                    $this->getDi()['logger']->error($e->getMessage());
                }
            }

            return true;
        } finally {
            RandomizedTimeFloor::apply($startedAt, 300, 450);
        }
    }

    private function handleExistingOrRateLimitedSignup(string $email, array $data, bool $autoLogin): bool
    {
        // Never disclose whether this address is already registered:
        // no distinct error, no duplicate row, and the same return
        // value as a genuine signup below. Falling through to an
        // ordinary login attempt keeps the response and any session
        // side effects identical to the success path, reusing
        // login()'s own timing- and message-safe handling instead of
        // reimplementing it here.
        $this->getDi()['logger']->withChannel('security')->info('Client signup declined for an existing or rate-limited email from IP {ip}.', ['ip' => $this->getIp()]);

        if ($autoLogin) {
            try {
                $this->login(['email' => $email, 'password' => $data['password']]);
            } catch (\Throwable $e) {
                $this->getDi()['logger']->error($e->getMessage());
            }
        }

        return true;
    }

    private function resetEntityManagerAfterViolation(object $service): void
    {
        $di = $this->getDi();
        if (!$di->offsetExists('em')) {
            return;
        }

        $em = $di['em'];
        if (!$em instanceof EntityManagerInterface || $em->isOpen()) {
            return;
        }

        // A failed flush closes the EntityManager; replace it so the
        // duplicate re-check and fallback login below use a usable one.
        try {
            $freshEm = EntityManagerFactory::create();
        } catch (\Throwable) {
            return;
        }

        unset($di['em']);
        $di['em'] = $freshEm;

        try {
            if ($service instanceof Service) {
                $service->setDi($di);
            }
        } catch (\Throwable) {
            // The fallback login already tolerates failures; keep the
            // generic signup response even if the refresh fails.
        }
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

            $this->getDi()['event_dispatcher']->dispatch(new BeforeClientLoginEvent($this->ip));

            $service = $this->getService();
            $client = $service->authorizeClient($data['email'], $data['password']);

            if (!$client instanceof Client) {
                $this->getDi()['event_dispatcher']->dispatch(new ClientLoginFailedEvent($this->ip));

                throw new \FOSSBilling\InformationException('Please check your login details.', [], 401);
            }

            $this->getDi()['event_dispatcher']->dispatch(new AfterClientLoginEvent((int) $client->getId(), $this->ip));

            $oldSession = $this->getDi()['session']->getId();
            $this->getDi()['session']->regenerateId();
            $result = $service->toSessionArray($client);
            $this->getDi()['session']->set('client_id', $client->getId());

            $this->getDi()['logger']->info('Client #{client_id} logged in', ['client_id' => $client->getId()]);
            $this->getDi()['session']->delete('redirect_uri');

            if (!empty($client->getLang())) {
                $this->getDi()['cookie_queue']->queue(CookieNames::LOCALE, $client->getLang(), strtotime('+1 month'), '/');
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
            $this->getDi()['event_dispatcher']->dispatch(new BeforeClientPasswordResetEvent($this->getIp()));
            $service = $this->getDi()['mod_service']('client');

            // Sanitize email
            $data['email'] = $this->getDi()['tools']->validateAndSanitizeEmail($data['email']);

            $ipLimit = $this->getDi()['rate_limiter']->consume('client_password_reset_ip', (string) $this->getIp());
            if ($ipLimit->isLimited()) {
                $this->getDi()['logger']->withChannel('security')->info('Client password reset rate limited from IP {ip}.', ['ip' => $this->getIp()]);

                return true;
            }

            $emailLimit = $this->getDi()['rate_limiter']->consume('client_password_reset_email', (string) $data['email']);
            if ($emailLimit->isLimited()) {
                $this->getDi()['logger']->withChannel('security')->info('Client password reset rate limited for an account from IP {ip}.', ['ip' => $this->getIp()]);

                return true;
            }

            $this->checkCaptchaIfEnabled($data);

            $this->getDi()['event_dispatcher']->dispatch(new BeforeClientPasswordResetRequestEvent($this->getIp()));

            $em = $this->getDi()['em'];
            $client = $em->getRepository(Client::class)->findOneByEmailAndActive($data['email']);
            if (!$client instanceof Client) {
                $this->getDi()['logger']->withChannel('security')->info('Client password reset requested for an unknown account from IP {ip}.', ['ip' => $this->getIp()]);

                return true;
            }

            if ($client->getStatus() !== Client::ACTIVE) {
                $this->getDi()['logger']->withChannel('security')->info('Client password reset requested for ineligible client #{client_id} from IP {ip}: account status {status}.', ['client_id' => $client->getId(), 'ip' => $this->getIp(), 'status' => $client->getStatus()]);

                return true;
            }

            $hash = $service->createPasswordResetRequestForClient($client);
            $service->sendPasswordResetRequestEmailForClient($client, $hash);

            $this->getDi()['logger']->withChannel('security')->info('Client password reset email queued for client #{client_id} from IP {ip}.', ['client_id' => $client->getId(), 'ip' => $this->getIp()]);

            return true;
        } finally {
            RandomizedTimeFloor::apply($startedAt, 300, 450);
        }
    }

    #[RequiredParams(['hash' => 'No Hash provided', 'password' => 'Password required', 'password_confirm' => 'Password confirmation required'])]
    public function update_password($data): bool
    {
        $startedAt = microtime(true);

        try {
            $this->getDi()['rate_limiter']->consumeOrThrow('client_password_reset_confirm_post_ip', (string) $this->getIp());

            $this->getDi()['event_dispatcher']->dispatch(new BeforeClientPasswordResetConfirmationEvent($this->getIp()));

            $this->getDi()['validator']->passwordsMatch($data);
            $this->getDi()['validator']->isPasswordStrong($data['password']);

            $em = $this->getDi()['em'];
            $reset = $em->getRepository(ClientPasswordReset::class)->findOneByHash($data['hash']);
            if (!$reset instanceof ClientPasswordReset) {
                $this->getDi()['logger']->withChannel('security')->info('Client password reset confirmation failed from IP {ip}: reset token not found', ['ip' => $this->getIp()]);

                throw new \FOSSBilling\InformationException('The link has expired or you have already reset your password.');
            }

            if (strtotime((string) $reset->getCreatedAt()?->format('Y-m-d H:i:s')) - time() + 900 < 0) {
                $this->getDi()['logger']->withChannel('security')->info('Client password reset confirmation failed for client #{client_id} from IP {ip}: reset token expired', ['client_id' => $reset->getClient()?->getId(), 'ip' => $this->getIp()]);

                throw new \FOSSBilling\InformationException('The link has expired or you have already reset your password.');
            }

            $client = $reset->getClient();
            if (!$client instanceof Client) {
                throw new \FOSSBilling\InformationException('The link has expired or you have already reset your password.');
            }

            if ($client->getStatus() !== Client::ACTIVE) {
                $this->getDi()['logger']->withChannel('security')->info('Client password reset confirmation failed for client #{client_id} from IP {ip}: account status {status}', ['client_id' => $client->getId(), 'ip' => $this->getIp(), 'status' => $client->getStatus()]);

                throw new \FOSSBilling\InformationException('The link has expired or you have already reset your password.');
            }

            $client->setPass($this->getDi()['password']->hashIt($data['password']));
            $em->persist($client);
            $em->remove($reset);
            $em->flush();

            $profileService = $this->getDi()['mod_service']('profile');
            $profileService->invalidateSessions('client', (int) $client->getId());

            $this->getDi()['logger']->withChannel('security')->info('Client password reset completed for client #{client_id} from IP {ip}', ['client_id' => $client->getId(), 'ip' => $this->getIp()]);

            // send email
            $email = [];
            $email['to_client'] = $client->getId();
            $email['code'] = 'mod_client_password_reset_information';
            $emailService = $this->getDi()['mod_service']('email');
            $emailService->sendTemplate($email);
            $this->getDi()['event_dispatcher']->dispatch(new AfterClientPasswordResetEvent((int) $client->getId()));

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
