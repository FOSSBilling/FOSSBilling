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
 * Clients API methods.
 */

namespace Box\Mod\Client\Api;

class Guest extends \Api_Abstract
{
    /**
     * Client signup action.
     *
     * @optional bool $auto_login - Auto login client after signup
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
     * @optional string $document_type - Related document type, ie: passport, driving license
     * @optional string $document_nr - Related document number, ie: passport number: LC45698122
     * @optional string $notes - Notes about client. Visible for admin only
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
     */
    public function create($data = [])
    {
        $config = $this->di['mod_config']('client');

        if (isset($config['disable_signup']) && $config['disable_signup']) {
            throw new \FOSSBilling\InformationException('New registrations are temporary disabled');
        }

        $required = [
            'email' => 'Email required',
            'first_name' => 'First name required',
            'password' => 'Password required',
            'password_confirm' => 'Password confirmation required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        if ($data['password'] != $data['password_confirm']) {
            throw new \FOSSBilling\InformationException('Passwords do not match.');
        }

        $this->getService()->checkExtraRequiredFields($data);
        $this->getService()->checkCustomFields($data);

        $this->di['validator']->isPasswordStrong($data['password']);
        $service = $this->getService();

        $email = $data['email'] ?? null;
        $email = $this->di['tools']->validateAndSanitizeEmail($email);
        $email = strtolower(trim($email));
        if ($service->clientAlreadyExists($email)) {
            throw new \FOSSBilling\InformationException('This email address is already registered.');
        }

        $client = $service->guestCreateClient($data);

        if (isset($config['require_email_confirmation']) && (bool) $config['require_email_confirmation']) {
            $service->sendEmailConfirmationForClient($client);
        }

        if ($data['auto_login'] ?? 0) {
            try {
                $this->login(['email' => $client->email, 'password' => $data['password']]);
            } catch (\Exception $e) {
                error_log($e->getMessage());
            }
        }

        return (int) $client->id;
    }

    /**
     * Client login action.
     *
     * @return array - session data
     *
     * @throws \FOSSBilling\InformationException
     */
    public function login($data)
    {
        $required = [
            'email' => 'Email required',
            'password' => 'Password required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);
        $this->di['tools']->validateAndSanitizeEmail($data['email'], true, false);

        $event_params = $data;
        $event_params['ip'] = $this->ip;
        $this->di['events_manager']->fire(['event' => 'onBeforeClientLogin', 'params' => $event_params]);

        $service = $this->getService();
        $client = $service->authorizeClient($data['email'], $data['password']);

        if (!$client instanceof \Model_Client) {
            $this->di['events_manager']->fire(['event' => 'onEventClientLoginFailed', 'params' => $event_params]);

            throw new \FOSSBilling\InformationException('Please check your login details.', [], 401);
        }

        $this->di['events_manager']->fire(['event' => 'onAfterClientLogin', 'params' => ['id' => $client->id, 'ip' => $this->ip]]);

        $oldSession = $this->di['session']->getId();
        session_regenerate_id();
        $result = $service->toSessionArray($client);
        $this->di['session']->set('client_id', $client->id);

        $this->di['logger']->info('Client #%s logged in', $client->id);
        $this->di['session']->delete('redirect_uri');

        $this->di['mod_service']('cart')->transferFromOtherSession($oldSession);

        return $result;
    }

    /**
     * Password reset confirmation email will be sent to email.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function reset_password($data)
    {
        $this->di['events_manager']->fire(['event' => 'onBeforePasswordResetClient']);

        // Validate required parameters
        $this->di['validator']->checkRequiredParamsForArray(['email' => 'Email required'], $data);

        // Sanitize email
        $data['email'] = $this->di['tools']->validateAndSanitizeEmail($data['email']);

        $this->di['events_manager']->fire(['event' => 'onBeforeGuestPasswordResetRequest', 'params' => $data]);

        // Fetch the client by email
        $c = $this->di['db']->findOne('Client', 'email = ?', [$data['email']]);
        if (!$c instanceof \Model_Client) {
            return true;
        }

        // Check if a password reset request exists
        $reset = $this->di['db']->findOne('ClientPasswordReset', 'client_id = ?', [$c->id]);

        // If no recent reset request exists, create a new one
        if (!$reset instanceof \Model_ClientPasswordReset) {
            $hash = hash('sha256', time() . random_bytes(13));
            $reset = $this->di['db']->dispense('ClientPasswordReset');
            $reset->client_id = $c->id;
            $reset->ip = $this->ip;
            $reset->hash = $hash;
            $reset->created_at = date('Y-m-d H:i:s');
            $reset->updated_at = date('Y-m-d H:i:s');
            $this->di['db']->store($reset);
        }

        // prepare reset email
        $email = [
            'to_client' => $c->id,
            'code' => 'mod_client_password_reset_request',
            'hash' => $reset->hash,
            'send_now' => true,
        ];

        $emailService = $this->di['mod_service']('email');

        // Send the email if the reset request has the same created_at and updated_at or if at least 1 full minute has passed since the last request.
        if ($reset->created_at == $reset->updated_at) {
            $emailService->sendTemplate($email);
        } elseif (strtotime($reset->updated_at) - time() + 60 < 0) {
            $emailService->sendTemplate($email);
        }

        // update the client password reset time
        $reset->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($reset);

        $this->di['logger']->info('Client requested password reset. Sent to email %s', $c->email);

        return true;
    }

    public function update_password($data)
    {
        $required = [
            'hash' => 'No Hash provided',
            'password' => 'Password required',
            'password_confirm' => 'Password confirmation required',
        ];
        $this->di['events_manager']->fire(['event' => 'onBeforeClientProfilePasswordReset', 'params' => $data['hash']]);

        $validator = $this->di['validator'];
        $validator->checkRequiredParamsForArray($required, $data);

        if ($data['password'] != $data['password_confirm']) {
            throw new \FOSSBilling\InformationException('Passwords do not match');
        }

        $reset = $this->di['db']->findOne('ClientPasswordReset', 'hash = ?', [$data['hash']]);
        if (!$reset instanceof \Model_ClientPasswordReset) {
            throw new \FOSSBilling\InformationException('The link has expired or you have already reset your password.');
        }

        if (strtotime($reset->created_at) - time() + 900 < 0) {
            throw new \FOSSBilling\InformationException('The link has expired or you have already reset your password.');
        }

        $c = $this->di['db']->getExistingModelById('Client', $reset->client_id, 'Client not found');
        $c->pass = $this->di['password']->hashIt($data['password']);
        $this->di['db']->store($c);

        $this->di['logger']->info('Client requested password reset. Sent to email %s', $c->email);

        // send email
        $email = [];
        $email['to_client'] = $c->id;
        $email['code'] = 'mod_client_password_reset_information';
        $emailService = $this->di['mod_service']('email');
        $emailService->sendTemplate($email);

        $this->di['db']->trash($reset);
        $this->di['events_manager']->fire(['event' => 'onAfterClientProfilePasswordReset', 'params' => ['id' => $c->id]]);

        return true;
    }

    /**
     * Check if given vat number is valid EU country VAT number
     * This method uses http://isvat.appspot.com/ method to validate VAT.
     *
     * @return bool true if VAT is valid, false if not
     */
    public function is_vat($data)
    {
        $required = [
            'country' => 'Country code',
            'vat' => 'Country VAT is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $cc = $data['country'];
        $vatnum = $data['vat'];

        // @todo add new service provider https://vatlayer.com/ check
        //         $url    = 'http://isvat.appspot.com/' . rawurlencode($cc) . '/' . rawurlencode($vatnum) . '/';
        return true;
    }

    /**
     * List of required fields for client registration.
     */
    public function required()
    {
        $config = $this->di['mod_config']('client');

        return $config['required'] ?? [];
    }

    /**
     * Array of custom fields for client registration.
     */
    public function custom_fields()
    {
        $config = $this->di['mod_config']('client');

        return $config['custom_fields'] ?? [];
    }

    public function is_email_validation_required(): bool
    {
        $config = $this->di['mod_config']('client');

        return (bool) ($config['require_email_confirmation'] ?? false);
    }
}
