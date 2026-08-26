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

use Box\Mod\Staff\Entity\Admin;
use Box\Mod\Staff\Entity\AdminPasswordReset;
use FOSSBilling\Security\RandomizedTimeFloor;
use FOSSBilling\Validation\Api\RequiredParams;

class Guest extends \FOSSBilling\Api\AbstractApi
{
    /**
     * Login to admin area and save information to session.
     *
     * @return array
     *
     * @throws \FOSSBilling\Exception\BaseException
     */
    #[RequiredParams(['email' => 'Email required', 'password' => 'Password required'])]
    public function login($data)
    {
        $startedAt = microtime(true);

        try {
            $data['email'] = \FOSSBilling\Validation\EmailValidator::validateAndSanitizeEmail($data['email'], true, false);

            $config = $this->getModule()->getConfig();

            // check ip
            if (!empty($config['allowed_ips']) && isset($config['check_ip']) && $config['check_ip']) {
                $allowed_ips = explode(PHP_EOL, (string) $config['allowed_ips']);
                $allowed_ips = array_map(trim(...), $allowed_ips);
                if (!in_array($this->getIp(), $allowed_ips)) {
                    throw new \FOSSBilling\Exception\InformationException('You are not allowed to login to admin area from this IP address.', null, 403);
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

            $config = $this->getModule()->getConfig();
            if (isset($config['public']['reset_pw']) && $config['public']['reset_pw'] == '0') {
                throw new \FOSSBilling\Exception\InformationException('Password reset has been disabled');
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

            $reset = is_string($data['code'])
                ? $this->getDi()['em']->getRepository(AdminPasswordReset::class)->findOneByHash($data['code'])
                : null;
            if (!$reset instanceof AdminPasswordReset) {
                $this->getDi()['logger']->withChannel('security')->info('Staff password reset confirmation failed from IP {ip}: reset token not found', ['ip' => $this->getIp()]);

                throw new \FOSSBilling\Exception\InformationException('The link has expired or you have already confirmed the password reset.');
            }

            if (strtotime((string) $reset->getCreatedAt()?->format('Y-m-d H:i:s')) - time() + 900 < 0) {
                $this->getDi()['logger']->withChannel('security')->info('Staff password reset confirmation failed for admin #{admin_id} from IP {ip}: reset token expired', ['admin_id' => $reset->getAdmin()?->getId(), 'ip' => $this->getIp()]);

                throw new \FOSSBilling\Exception\InformationException('The link has expired or you have already confirmed the password reset.');
            }

            $admin = $reset->getAdmin();
            if (!$admin instanceof Admin) {
                throw new \FOSSBilling\Exception\InformationException('Admin not found');
            }

            if ($admin->getStatus() !== Admin::STATUS_ACTIVE || $admin->isCron()) {
                $this->getDi()['logger']->withChannel('security')->info('Staff password reset confirmation failed for admin #{admin_id} from IP {ip}: account status {status}, system name {system_name}', ['admin_id' => $admin->getId(), 'ip' => $this->getIp(), 'status' => $admin->getStatus(), 'system_name' => $admin->getSystemName()]);

                throw new \FOSSBilling\Exception\InformationException('The link has expired or you have already confirmed the password reset.');
            }

            $admin->setPass($this->getDi()['password']->hashIt($data['password']));
            $this->getDi()['em']->persist($admin);
            $this->getDi()['em']->flush();

            $profileService = $this->getDi()['mod_service']('profile');
            $profileService->invalidateSessions('admin', (int) $admin->getId());

            $this->getDi()['logger']->withChannel('security')->info('Staff password reset completed for admin #{admin_id} from IP {ip}', ['admin_id' => $admin->getId(), 'ip' => $this->getIp()]);

            $this->getDi()['events_manager']->fire(['event' => 'onAfterPasswordResetStaff', 'params' => ['id' => $admin->getId()]]);

            // send email
            $email = [];
            $email['to_admin'] = $admin->getId();
            $email['code'] = 'mod_staff_password_reset_approve';
            $emailService = $this->getDi()['mod_service']('email');
            $emailService->sendTemplate($email);

            $this->getDi()['em']->remove($reset);
            $this->getDi()['em']->flush();
        } finally {
            RandomizedTimeFloor::apply($startedAt, 300, 450);
        }
    }

    #[RequiredParams(['email' => 'Email required'])]
    public function passwordreset(array $data): bool
    {
        $config = $this->getModule()->getConfig();
        if (isset($config['public']['reset_pw']) && $config['public']['reset_pw'] == '0') {
            throw new \FOSSBilling\Exception\InformationException('Password reset has been disabled');
        }

        $startedAt = microtime(true);

        try {
            $this->getDi()['events_manager']->fire(['event' => 'onBeforePasswordResetStaff']);
            $data['email'] = \FOSSBilling\Validation\EmailValidator::validateAndSanitizeEmail($data['email']);

            $ipLimit = $this->getDi()['rate_limiter']->consume('staff_password_reset_ip', (string) $this->getIp());
            if ($ipLimit->isLimited()) {
                $this->getDi()['logger']->withChannel('security')->info('Staff password reset rate limited from IP {ip}: email {email}', ['ip' => $this->getIp(), 'email' => $data['email']]);

                return true;
            }

            $this->checkCaptchaIfEnabled($data);

            $emailLimit = $this->getDi()['rate_limiter']->consume('staff_password_reset_email', (string) $data['email']);
            if ($emailLimit->isLimited()) {
                $this->getDi()['logger']->withChannel('security')->info('Staff password reset rate limited for email {email} from IP {ip}', ['email' => $data['email'], 'ip' => $this->getIp()]);

                return true;
            }

            $c = $this->getDi()['em']->getRepository(Admin::class)->findOneBy(['email' => $data['email']]);

            if (!$c instanceof Admin) {
                $this->getDi()['logger']->withChannel('security')->info('Staff password reset requested for unknown email {email} from IP {ip}', ['email' => $data['email'], 'ip' => $this->getIp()]);

                return true;
            }

            if ($c->getStatus() !== Admin::STATUS_ACTIVE || $c->isCron()) {
                $this->getDi()['logger']->withChannel('security')->info('Staff password reset requested for ineligible admin #{admin_id} from IP {ip}: email {email}, account status {status}, system name {system_name}', ['admin_id' => $c->getId(), 'ip' => $this->getIp(), 'email' => $data['email'], 'status' => $c->getStatus(), 'system_name' => $c->getSystemName()]);

                return true;
            }

            $hash = hash('sha256', random_bytes(32));

            $reset = new AdminPasswordReset();
            $reset->setAdmin($c);
            $reset->setIp($this->ip);
            $reset->setHash($hash);
            $this->getDi()['em']->persist($reset);
            $this->getDi()['em']->flush();

            // send email
            $email = [];
            $email['to_admin'] = $c->getId();
            $email['code'] = 'mod_staff_password_reset_request';
            $email['hash'] = $hash;
            $emailService = $this->getDi()['mod_service']('email');
            $emailService->sendTemplate($email);

            $this->getDi()['logger']->withChannel('security')->info('Staff password reset email queued for admin #{admin_id} from IP {ip}: email {email}', ['admin_id' => $c->getId(), 'ip' => $this->getIp(), 'email' => $data['email']]);

            return true;
        } finally {
            RandomizedTimeFloor::apply($startedAt);
        }
    }
}
