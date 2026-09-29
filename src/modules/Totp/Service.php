<?php

declare(strict_types=1);

namespace Box\Mod\Totp;

use Box\Mod\Client\Entity\Client;
use Box\Mod\Client\Event\AfterClientLoginEvent;
use Box\Mod\Staff\Entity\Admin;
use Box\Mod\Staff\Event\AfterAdminLoginEvent;
use Box\Mod\Totp\Entity\TotpCredential;
use FOSSBilling\Config;
use FOSSBilling\Doctrine\SchemaSynchronizer;
use FOSSBilling\InformationException;
use FOSSBilling\InjectionAwareInterface;
use FOSSBilling\Interfaces\WidgetProviderInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

class Service implements InjectionAwareInterface, WidgetProviderInterface
{
    private const int SECRET_BYTES = 20;
    private const int CODE_PERIOD = 30;
    private const int CODE_DIGITS = 6;
    private const int RECOVERY_CODE_COUNT = 10;

    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getModulePermissions(): array
    {
        return [
            'manage' => [
                'type' => 'bool',
                'display_name' => __trans('Manage TOTP settings'),
                'description' => __trans('Allows staff to manage TOTP settings for accounts.'),
            ],
            'manage_settings' => [],
        ];
    }

    public function getConfig(): array
    {
        $config = $this->di['mod_service']('Extension')->getConfig('mod_totp');

        return [
            'allow_client' => filter_var($config['allow_client'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'allow_admin' => filter_var($config['allow_admin'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'security_notice' => filter_var($config['security_notice'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'custom_issuer_enabled' => filter_var($config['custom_issuer_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'issuer' => $this->getIssuer($config),
        ];
    }

    private function getIssuer(array $config): string
    {
        $company = trim((string) Config::getProperty('info.company', 'FOSSBilling')) ?: 'FOSSBilling';
        if (!filter_var($config['custom_issuer_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return $company;
        }

        return trim((string) ($config['issuer'] ?? '')) ?: $company;
    }

    public function getWidgets(): array
    {
        return [
            [
                'slot' => 'client.page.login.form.before',
                'template' => 'mod_totp_client_login',
            ],
            [
                'slot' => 'admin.staff.login.form.before',
                'template' => 'mod_totp_admin_login',
            ],
            [
                'slot' => 'client.profile.tabs',
                'template' => 'mod_totp_client_profile_tab',
            ],
            [
                'slot' => 'client.profile.tab_content',
                'template' => 'mod_totp_client_profile',
            ],
            [
                'slot' => 'admin.staff.profile.tabs',
                'template' => 'mod_totp_admin_profile_tab',
            ],
            [
                'slot' => 'admin.staff.profile.tab_content',
                'template' => 'mod_totp_admin_profile',
            ],
            [
                'slot' => 'admin.client.profile.actions',
                'template' => 'mod_totp_admin_client_profile',
            ],
            [
                'slot' => 'admin.client.summary.rows',
                'template' => 'mod_totp_admin_client_summary',
            ],
            [
                'slot' => 'admin.theme.content.before',
                'template' => 'mod_totp_admin_security_notice',
            ],
            [
                'slot' => 'client.index.dashboard.client.before',
                'template' => 'mod_totp_client_security_notice',
            ],
        ];
    }

    public function install(): bool
    {
        SchemaSynchronizer::syncEntities($this->di['em'], [TotpCredential::class]);

        return true;
    }

    public function status(string $ownerType, int $ownerId): array
    {
        $credential = $this->find($ownerType, $ownerId);

        return ['allowed' => $this->isAllowed($ownerType), 'notice_enabled' => $this->getConfig()['security_notice'], 'configured' => $credential !== null, 'enabled' => $credential?->isEnabled() ?? false, 'recovery_codes' => count($credential?->getRecoveryCodes() ?? [])];
    }

    public function setup(string $ownerType, int $ownerId, string $label): array
    {
        if (!$this->isAllowed($ownerType)) {
            throw new InformationException('Two-factor authentication is disabled for this account type.');
        }

        $secret = $this->base32Encode(random_bytes(self::SECRET_BYTES));
        $credential = $this->find($ownerType, $ownerId) ?? new TotpCredential();
        $credential->setOwnerType($ownerType)->setOwnerId($ownerId)->setSecret($this->encrypt($secret))->setEnabled(false)->setRecoveryCodes([]);
        $this->di['em']->persist($credential);
        $this->di['em']->flush();
        $issuer = rawurlencode($this->getConfig()['issuer']);

        return ['secret' => $secret, 'uri' => sprintf('otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d', $issuer, rawurlencode($label), $secret, $issuer, self::CODE_DIGITS, self::CODE_PERIOD)];
    }

    public function enable(string $ownerType, int $ownerId, string $code): array
    {
        $credential = $this->find($ownerType, $ownerId);
        if (!$credential instanceof TotpCredential || !$this->verify($this->decrypt($credential->getSecret()), $code)) {
            throw new InformationException('The verification code is invalid.');
        }
        $recoveryCodes = $this->generateRecoveryCodes();
        $credential->setEnabled(true)->setRecoveryCodes(array_map(static fn (string $recoveryCode): string => password_hash($recoveryCode, PASSWORD_DEFAULT), $recoveryCodes));
        $this->di['em']->flush();

        return ['recovery_codes' => $recoveryCodes];
    }

    public function disable(string $ownerType, int $ownerId, string $code): bool
    {
        $credential = $this->find($ownerType, $ownerId);
        if (!$credential instanceof TotpCredential || !$this->verify($this->decrypt($credential->getSecret()), $code)) {
            throw new InformationException('The verification code is invalid.');
        }
        $credential->setEnabled(false)->setRecoveryCodes([]);
        $this->di['em']->flush();

        return true;
    }

    public function adminDisable(string $ownerType, int $ownerId): bool
    {
        $credential = $this->find($ownerType, $ownerId);
        if (!$credential) {
            return false;
        }

        $credential->setEnabled(false)->setRecoveryCodes([]);
        $this->di['em']->flush();

        return true;
    }

    public function regenerateRecoveryCodes(string $ownerType, int $ownerId, string $code): array
    {
        $credential = $this->find($ownerType, $ownerId);
        if (!$credential instanceof TotpCredential || !$credential->isEnabled() || !$this->consumeCode($credential, $code)) {
            throw new InformationException('The verification code is invalid.');
        }
        $recoveryCodes = $this->generateRecoveryCodes();
        $credential->setRecoveryCodes(array_map(static fn (string $recoveryCode): string => password_hash($recoveryCode, PASSWORD_DEFAULT), $recoveryCodes));
        $this->di['em']->flush();

        return ['recovery_codes' => $recoveryCodes];
    }

    #[AsEventListener]
    public function requireClientTotp(AfterClientLoginEvent $event): void
    {
        $this->requireTotp('client', $event->clientId);
    }

    #[AsEventListener]
    public function requireAdminTotp(AfterAdminLoginEvent $event): void
    {
        $this->requireTotp('admin', $event->adminId);
    }

    private function requireTotp(string $ownerType, int $ownerId): void
    {
        if (!$this->isAllowed($ownerType)) {
            return;
        }

        $credential = $this->find($ownerType, $ownerId);
        if (!$credential?->isEnabled()) {
            return;
        }

        $this->di['rate_limiter']->consumeOrThrow('totp_login', $ownerType . ':' . $ownerId);
        $this->di['session']->set('totp_challenge', ['owner_type' => $ownerType, 'owner_id' => $ownerId, 'created_at' => time()]);

        throw new InformationException('Two-factor authentication is required.', [], 401);
    }

    public function completeChallenge(string $code): bool
    {
        $challenge = $this->di['session']->get('totp_challenge');
        if (!is_array($challenge) || time() - (int) ($challenge['created_at'] ?? 0) > 300) {
            $this->di['session']->delete('totp_challenge');

            throw new InformationException('The two-factor authentication session has expired.', [], 401);
        }

        $ownerType = (string) ($challenge['owner_type'] ?? '');
        $ownerId = (int) ($challenge['owner_id'] ?? 0);
        $this->di['rate_limiter']->consumeOrThrow('totp_login', $ownerType . ':' . $ownerId);
        $credential = $this->find($ownerType, $ownerId);
        if (!$credential?->isEnabled() || !$this->consumeCode($credential, $code)) {
            throw new InformationException('The verification code is invalid.', [], 401);
        }

        $oldSession = $this->di['session']->getId();
        $this->di['session']->regenerateId();
        $this->di['session']->delete('totp_challenge');

        if ($ownerType === 'client') {
            $client = $this->di['em']->getRepository(Client::class)->find($ownerId);
            if (!$client instanceof Client) {
                throw new InformationException('Client account not found.', [], 401);
            }
            $this->di['session']->set('client_id', $client->getId());
            $this->di['mod_service']('cart')->transferFromOtherSession($oldSession);
        } elseif ($ownerType === 'admin') {
            $admin = $this->di['em']->getRepository(Admin::class)->find($ownerId);
            if (!$admin instanceof Admin) {
                throw new InformationException('Staff account not found.', [], 401);
            }
            $this->di['session']->set('admin', ['id' => $admin->getId(), 'email' => $admin->getEmail(), 'name' => $admin->getName()]);
        } else {
            throw new InformationException('Invalid two-factor authentication session.', [], 401);
        }

        return true;
    }

    private function isAllowed(string $ownerType): bool
    {
        $config = $this->getConfig();

        return $ownerType === 'admin' ? $config['allow_admin'] : $config['allow_client'];
    }

    private function consumeCode(TotpCredential $credential, string $code): bool
    {
        $secret = $this->decrypt($credential->getSecret());
        if ($this->verify($secret, $code)) {
            return true;
        }
        foreach ($credential->getRecoveryCodes() as $index => $hash) {
            if (password_verify(strtoupper(trim($code)), $hash)) {
                $codes = $credential->getRecoveryCodes();
                unset($codes[$index]);
                $credential->setRecoveryCodes($codes);

                return true;
            }
        }

        return false;
    }

    private function find(string $ownerType, int $ownerId): ?TotpCredential
    {
        return $this->di['em']->getRepository(TotpCredential::class)->findOneBy(['ownerType' => $ownerType, 'ownerId' => $ownerId]);
    }

    private function encrypt(string $value): string
    {
        return $this->di['crypt']->encrypt($value, (string) Config::getProperty('info.salt'));
    }

    private function decrypt(string $value): string
    {
        $result = $this->di['crypt']->decrypt($value, (string) Config::getProperty('info.salt'));

        return is_string($result) ? $result : '';
    }

    private function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; ++$i) {
            $codes[] = strtoupper(bin2hex(random_bytes(5)));
        }

        return $codes;
    }

    private function verify(string $secret, string $code): bool
    {
        $code = preg_replace('/[\s-]+/', '', $code) ?? '';
        if (!preg_match('/^\d{' . self::CODE_DIGITS . '}$/', $code) || $secret === '') {
            return false;
        }
        $counter = intdiv(time(), self::CODE_PERIOD);
        for ($offset = -2; $offset <= 2; ++$offset) {
            $binary = pack('N2', 0, $counter + $offset);
            $hash = hash_hmac('sha1', $binary, $this->base32Decode($secret), true);
            $position = ord($hash[19]) & 0x0F;
            $value = ((ord($hash[$position]) & 0x7F) << 24) | ((ord($hash[$position + 1]) & 0xFF) << 16) | ((ord($hash[$position + 2]) & 0xFF) << 8) | (ord($hash[$position + 3]) & 0xFF);
            if (hash_equals(str_pad((string) ($value % (10 ** self::CODE_DIGITS)), self::CODE_DIGITS, '0', STR_PAD_LEFT), $code)) {
                return true;
            }
        }

        return false;
    }

    private function base32Encode(string $value): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (unpack('C*', $value) as $byte) {
            $bits .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
        }
        $encoded = '';
        foreach (str_split(str_pad($bits, (int) ceil(strlen($bits) / 5) * 5, '0'), 5) as $chunk) {
            $encoded .= $alphabet[bindec($chunk)];
        }

        return $encoded;
    }

    private function base32Decode(string $value): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split(strtoupper($value)) as $character) {
            $position = strpos($alphabet, $character);
            if ($position === false) {
                return '';
            }
            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }
        $bytes = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $bytes .= chr(bindec($chunk));
            }
        }

        return $bytes;
    }
}
