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
 *Staff methods.
 */

namespace Box\Mod\Staff\Api;

use FOSSBilling\Security\RandomizedTimeFloor;
use FOSSBilling\Validation\Api\RequiredParams;

class Guest extends \FOSSBilling\Api\AbstractApi
{
    /**
     * Login to admin area and save information to session.
     *
     * @return array
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['email' => 'Email required', 'password' => 'Password required'])]
    public function login($data)
    {
        $startedAt = microtime(true);

        try {
            $data['email'] = $this->getDi()['tools']->validateAndSanitizeEmail($data['email'], true, false);

            $config = $this->getMod()->getConfig();

            // check ip
            if (!empty($config['allowed_ips']) && isset($config['check_ip']) && $config['check_ip']) {
                $allowed_ips = explode(PHP_EOL, (string) $config['allowed_ips']);
                $allowed_ips = array_map(trim(...), $allowed_ips);
                if (!in_array($this->getIp(), $allowed_ips)) {
                    throw new \FOSSBilling\InformationException('You are not allowed to login to admin area from this IP address.', null, 403);
                }
            }

            $result = $this->getService()->login($data['email'], $data['password'], $this->getIp());
            $this->getDi()['session']->delete('redirect_uri');

            return $result;
        } finally {
            RandomizedTimeFloor::apply($startedAt);
        }
    }

    public function update_password($data): void
    {
        $startedAt = microtime(true);

        try {
            $this->getDi()['rate_limiter']->consumeOrThrow('staff_password_reset_confirm_post_ip', (string) $this->getIp());

            $config = $this->getMod()->getConfig();
            if (isset($config['public']['reset_pw']) && $config['public']['reset_pw'] == '0') {
                throw new \FOSSBilling\InformationException('Password reset has been disabled');
            }
            $this->getDi()['events_manager']->fire(['event' => 'onBeforePasswordResetStaff']);
            $required = [
                'code' => 'Code required',
                'password' => 'Password required',
                'password_confirm' => 'Password confirmation required',
            ];

            $validator = $this->getDi()['validator'];
            $validator->checkRequiredParamsForArray($required, $data);
            $validator->passwordsMatch($data);
            $validator->isPasswordStrong($data['password']);

            $reset = $this->getDi()['db']->findOne('AdminPasswordReset', 'hash = ?', [$data['code']]);
            if (!$reset instanceof \Model_AdminPasswordReset) {
                $this->getDi()['logger']->setChannel('security')->info('Staff password reset confirmation failed from IP %s: reset token not found', $this->getIp());

                throw new \FOSSBilling\InformationException('The link has expired or you have already confirmed the password reset.');
            }

            if (strtotime($reset->created_at) - time() + 900 < 0) {
                $this->getDi()['logger']->setChannel('security')->info('Staff password reset confirmation failed for admin #%s from IP %s: reset token expired', $reset->admin_id, $this->getIp());

                throw new \FOSSBilling\InformationException('The link has expired or you have already confirmed the password reset.');
            }

            $admin = $this->getDi()['db']->getExistingModelById('Admin', $reset->admin_id, 'Admin not found');

            if ($admin->status !== \Model_Admin::STATUS_ACTIVE || $admin->isCron()) {
                $this->getDi()['logger']->setChannel('security')->info('Staff password reset confirmation failed for admin #%s from IP %s: account status %s, system name %s', $admin->id, $this->getIp(), $admin->status, $admin->system_name);

                throw new \FOSSBilling\InformationException('The link has expired or you have already confirmed the password reset.');
            }

            $admin->pass = $this->getDi()['password']->hashIt($data['password']);
            $this->getDi()['db']->store($admin);

            $this->getDi()['logger']->setChannel('security')->info('Staff password reset completed for admin #%s from IP %s', $admin->id, $this->getIp());

            $this->getDi()['events_manager']->fire(['event' => 'onAfterPasswordResetStaff', 'params' => ['id' => $admin->id]]);

            // send email
            $email = [];
            $email['to_admin'] = $admin->id;
            $email['code'] = 'mod_staff_password_reset_approve';
            $emailService = $this->getDi()['mod_service']('email');
            $emailService->sendTemplate($email);

            $this->getDi()['db']->trash($reset);
        } finally {
            RandomizedTimeFloor::apply($startedAt, 300, 450);
        }
    }

    public function passwordreset(array $data): bool
    {
        $config = $this->getMod()->getConfig();
        if (isset($config['public']['reset_pw']) && $config['public']['reset_pw'] == '0') {
            throw new \FOSSBilling\InformationException('Password reset has been disabled');
        }

        $startedAt = microtime(true);

        try {
            $this->getDi()['events_manager']->fire(['event' => 'onBeforePasswordResetStaff']);
            $required = [
                'email' => 'Email required',
            ];
            $validator = $this->getDi()['validator'];
            $validator->checkRequiredParamsForArray($required, $data);
            $data['email'] = $this->getDi()['tools']->validateAndSanitizeEmail($data['email']);

            $ipLimit = $this->getDi()['rate_limiter']->consume('staff_password_reset_ip', (string) $this->getIp());
            if ($ipLimit->isLimited()) {
                $this->getDi()['logger']->setChannel('security')->info('Staff password reset rate limited from IP %s: email %s', $this->getIp(), $data['email']);

                return true;
            }

            $this->checkPasswordResetCaptcha($data);

            $emailLimit = $this->getDi()['rate_limiter']->consume('staff_password_reset_email', (string) $data['email']);
            if ($emailLimit->isLimited()) {
                $this->getDi()['logger']->setChannel('security')->info('Staff password reset rate limited for email %s from IP %s', $data['email'], $this->getIp());

                return true;
            }

            $c = $this->getDi()['db']->findOne('Admin', 'email = ?', [$data['email']]);

            if (!$c instanceof \Model_Admin) {
                $this->getDi()['logger']->setChannel('security')->info('Staff password reset requested for unknown email %s from IP %s', $data['email'], $this->getIp());

                return true;
            }

            if ($c->status !== \Model_Admin::STATUS_ACTIVE || $c->isCron()) {
                $this->getDi()['logger']->setChannel('security')->info('Staff password reset requested for ineligible admin #%s from IP %s: email %s, account status %s, system name %s', $c->id, $this->getIp(), $data['email'], $c->status, $c->system_name);

                return true;
            }

            $hash = hash('sha256', random_bytes(32));

            $reset = $this->getDi()['db']->dispense('AdminPasswordReset');
            $reset->admin_id = $c->id;
            $reset->ip = $this->ip;
            $reset->hash = $hash;
            $reset->created_at = date('Y-m-d H:i:s');
            $reset->updated_at = date('Y-m-d H:i:s');
            $this->getDi()['db']->store($reset);

            // send email
            $email = [];
            $email['to_admin'] = $c->id;
            $email['code'] = 'mod_staff_password_reset_request';
            $email['hash'] = $hash;
            $emailService = $this->getDi()['mod_service']('email');
            $emailService->sendTemplate($email);

            $this->getDi()['logger']->setChannel('security')->info('Staff password reset email queued for admin #%s from IP %s: email %s', $c->id, $this->getIp(), $data['email']);

            return true;
        } finally {
            RandomizedTimeFloor::apply($startedAt);
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
}
