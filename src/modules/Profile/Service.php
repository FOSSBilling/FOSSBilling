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

        $admin->setPass($this->di['password']->hashIt($new_password));
        $this->di['em']->persist($admin);
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

        $admin->setApiToken($this->di['tools']->generatePassword(32));
        $this->di['em']->persist($admin);
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

        $admin->setEmail($data['email'] ?? $admin->getEmail());
        $admin->setName($data['name'] ?? $admin->getName());
        $admin->setSignature($data['signature'] ?? $admin->getSignature());
        if (array_key_exists('timezone', $data)) {
            $admin->setTimezone(i18n::validateTimezone($data['timezone']));
        }
        $this->di['em']->persist($admin);
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
            'created_at' => $identity->getCreatedAt()?->format('Y-m-d'),
            'updated_at' => $identity->getUpdatedAt()?->format('Y-m-d'),
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
                $client->setEmail($email);
                $client->setEmailApproved(false);

                $clientConfig = $this->di['mod_config']('client');
                if (isset($clientConfig['require_email_confirmation']) && $clientConfig['require_email_confirmation']) {
                    $clientService = $this->di['mod_service']('client');
                    $clientService->sendEmailConfirmationForClient($client);
                }
            }
        }

        if (isset($data['phone_cc']) && $data['phone_cc'] !== '') {
            $client->setPhoneCc((string) Tools::validatePhoneCC($data['phone_cc']));
        }

        if (isset($data['phone']) && is_string($data['phone']) && $data['phone'] !== '') {
            $client->setPhone(Tools::validatePhoneNumber($data['phone']));
        }

        $client->setFirstName($data['first_name'] ?? $client->getFirstName());
        if (array_key_exists('billing_email', $data)) {
            $client->setBillingEmail($data['billing_email']);
        }
        $client->setLastName($data['last_name'] ?? $client->getLastName());
        $client->setGender(ClientValidator::validateGender($data['gender'] ?? $client->getGender()));
        $birthday = ClientValidator::validateBirthday($data['birthday'] ?? $client->getBirthday()?->format('Y-m-d'));
        $client->setBirthday($birthday !== null ? new \DateTime($birthday) : null);
        $client->setCompany($data['company'] ?? $client->getCompany());
        $client->setCompanyVat($data['company_vat'] ?? $client->getCompanyVat());
        $client->setCompanyNumber($data['company_number'] ?? $client->getCompanyNumber());
        $client->setType($data['type'] ?? $client->getType());
        $client->setAddress1($data['address_1'] ?? $client->getAddress1());
        $client->setAddress2($data['address_2'] ?? $client->getAddress2());
        $country = $data['country'] ?? $client->getCountry();
        if (!empty($country) && !Countries::exists($country)) {
            throw new InformationException('Invalid country code: :code', [':code' => $country]);
        }
        $client->setCountry($country);
        $client->setPostcode($data['postcode'] ?? $client->getPostcode());
        $client->setCity($data['city'] ?? $client->getCity());
        $client->setState($data['state'] ?? $client->getState());
        $lang = $data['lang'] ?? $client->getLang();
        if (!empty($lang) && !Locales::exists($lang)) {
            throw new InformationException('Invalid locale code: :code', [':code' => $lang]);
        }
        $client->setLang($lang);
        if (array_key_exists('timezone', $data)) {
            $client->setTimezone(i18n::validateTimezone($data['timezone']));
        }
        $client->setNotes($data['notes'] ?? $client->getNotes());
        $client->setCustom1($data['custom_1'] ?? $client->getCustom1());
        $client->setCustom2($data['custom_2'] ?? $client->getCustom2());
        $client->setCustom3($data['custom_3'] ?? $client->getCustom3());
        $client->setCustom4($data['custom_4'] ?? $client->getCustom4());
        $client->setCustom5($data['custom_5'] ?? $client->getCustom5());
        $client->setCustom6($data['custom_6'] ?? $client->getCustom6());
        $client->setCustom7($data['custom_7'] ?? $client->getCustom7());
        $client->setCustom8($data['custom_8'] ?? $client->getCustom8());
        $client->setCustom9($data['custom_9'] ?? $client->getCustom9());
        $client->setCustom10($data['custom_10'] ?? $client->getCustom10());
        $client->setCustom11($data['custom_11'] ?? $client->getCustom11());
        $client->setCustom12($data['custom_12'] ?? $client->getCustom12());
        $client->setCustom13($data['custom_13'] ?? $client->getCustom13());
        $client->setCustom14($data['custom_14'] ?? $client->getCustom14());
        $client->setCustom15($data['custom_15'] ?? $client->getCustom15());
        $client->setCustom16($data['custom_16'] ?? $client->getCustom16());
        $client->setCustom17($data['custom_17'] ?? $client->getCustom17());
        $client->setCustom18($data['custom_18'] ?? $client->getCustom18());
        $client->setCustom19($data['custom_19'] ?? $client->getCustom19());
        $client->setCustom20($data['custom_20'] ?? $client->getCustom20());

        $this->di['em']->persist($client);
        $this->di['em']->flush();

        $this->di['events_manager']->fire(['event' => 'onAfterClientProfileUpdate', 'params' => ['id' => $client->getId()]]);

        $this->di['logger']->info('Updated profile');

        return true;
    }

    public function resetApiKey(Client $client): ?string
    {
        $client->setApiToken($this->di['tools']->generatePassword(32));

        $this->di['em']->persist($client);
        $this->di['em']->flush();

        $this->di['logger']->info('Generated new API key');

        return $client->getApiToken();
    }

    public function changeClientPassword(Client $client, $new_password): bool
    {
        $event_params = ['id' => $client->getId()];
        $this->di['events_manager']->fire(['event' => 'onBeforeClientProfilePasswordChange', 'params' => $event_params]);

        $client->setPass($this->di['password']->hashIt($new_password));
        $this->di['em']->persist($client);
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
                    $id = (int) $admin['id'];

                    break;
                case 'client':
                    $id = (int) $this->di['session']->get('client_id');

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
        $data = base64_decode((string) $session['content'], true);
        if ($data === false) {
            return;
        }

        $sessionData = $this->decodeSessionData($data);
        if (!is_array($sessionData)) {
            return;
        }

        if ($type === 'admin') {
            $admin = $sessionData['admin'] ?? null;
            if (is_array($admin) && isset($admin['id']) && (int) $admin['id'] === $id) {
                $this->trashSessionByArray($session);
            }
        } else {
            $clientId = $sessionData['client_id'] ?? null;
            if (is_int($clientId) && $clientId === $id) {
                $this->trashSessionByArray($session);
            }
        }
    }

    /**
     * Decodes raw PHP session serialization (the `php` serialize handler format)
     * into an array.
     *
     * Unlike matching against the serialized string, this does not depend on the
     * order the values were written in, so the identity keys are found wherever
     * they appear in the session data. It deliberately avoids session_decode(),
     * which requires an active session, so invalidation also works from contexts
     * without one (CLI, cron). Returns null when the data is malformed; callers
     * treat that as "no match" and leave the row alone.
     *
     * @return array<string, mixed>|null
     */
    private function decodeSessionData(string $data): ?array
    {
        $result = [];
        $references = [];
        $offset = 0;
        $length = strlen($data);

        while ($offset < $length) {
            $separator = strpos($data, '|', $offset);
            if ($separator === false) {
                return null;
            }

            $key = substr($data, $offset, $separator - $offset);
            if ($key === '') {
                return null;
            }

            $parsed = $this->parseSessionValue($data, $separator + 1, $references);
            if ($parsed === null) {
                return null;
            }

            [$value, $offset] = $parsed;
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * Parses a single PHP-serialized value starting at the given offset.
     *
     * @param array<int, mixed> $references values seen so far, for r/R references
     *
     * @return array{0: mixed, 1: int}|null the value and the offset of the first unconsumed byte
     */
    private function parseSessionValue(string $data, int $offset, array &$references): ?array
    {
        $type = $data[$offset] ?? null;

        switch ($type) {
            case 'N':
                if (($data[$offset + 1] ?? '') !== ';') {
                    return null;
                }

                return [null, $offset + 2];

            case 'b':
            case 'i':
            case 'd':
                $end = strpos($data, ';', $offset);
                if ($end === false) {
                    return null;
                }
                $raw = substr($data, $offset + 2, $end - $offset - 2);
                if ($type === 'b' && $raw !== '0' && $raw !== '1') {
                    return null;
                }
                if (($type === 'i' || $type === 'd') && !is_numeric($raw)) {
                    return null;
                }
                $value = match ($type) {
                    'b' => $raw === '1',
                    'i' => (int) $raw,
                    default => (float) $raw,
                };

                return [$value, $end + 1];

            case 's':
                if (($data[$offset + 1] ?? '') !== ':') {
                    return null;
                }
                $quote = strpos($data, ':"', $offset);
                if ($quote === false) {
                    return null;
                }
                $stringLength = substr($data, $offset + 2, $quote - $offset - 2);
                if (!ctype_digit($stringLength)) {
                    return null;
                }
                $stringLength = (int) $stringLength;
                $value = substr($data, $quote + 2, $stringLength);
                if (strlen($value) !== $stringLength || substr($data, $quote + 2 + $stringLength, 2) !== '";') {
                    return null;
                }
                $references[] = $value;

                return [$value, $quote + 2 + $stringLength + 2];

            case 'a':
            case 'O':
                $headerEnd = strpos($data, ':{', $offset);
                if ($headerEnd === false) {
                    return null;
                }
                $colon = strpos($data, ':', $offset);
                if ($colon === false || $colon > $headerEnd) {
                    return null;
                }
                if ($type === 'O') {
                    $classStart = strpos($data, ':"', $offset);
                    if ($classStart === false || $classStart > $headerEnd) {
                        return null;
                    }
                }
                $count = substr($data, $colon + 1, $headerEnd - $colon - 1);
                if (!ctype_digit($count)) {
                    return null;
                }
                $count = (int) $count;
                $values = [];
                $references[] = null;
                $referenceIndex = count($references) - 1;
                $position = $headerEnd + 2;
                for ($i = 0; $i < $count; ++$i) {
                    $parsedKey = $this->parseSessionValue($data, $position, $references);
                    if ($parsedKey === null) {
                        return null;
                    }
                    $parsedValue = $this->parseSessionValue($data, $parsedKey[1], $references);
                    if ($parsedValue === null) {
                        return null;
                    }
                    if (!is_int($parsedKey[0]) && !is_string($parsedKey[0])) {
                        return null;
                    }
                    $values[$parsedKey[0]] = $parsedValue[0];
                    $position = $parsedValue[1];
                }
                if (($data[$position] ?? '') !== '}') {
                    return null;
                }
                $references[$referenceIndex] = $values;

                return [$values, $position + 1];

            case 'r':
            case 'R':
                $end = strpos($data, ';', $offset);
                if ($end === false) {
                    return null;
                }
                $index = substr($data, $offset + 2, $end - $offset - 2);
                if (!ctype_digit($index) || !isset($references[(int) $index - 1])) {
                    return null;
                }

                return [$references[(int) $index - 1], $end + 1];

            default:
                return null;
        }
    }

    private function trashSessionByArray(array $session): void
    {
        $this->di['em']->getConnection()->executeStatement('DELETE FROM session WHERE id = :id', ['id' => $session['id']]);
    }
}
