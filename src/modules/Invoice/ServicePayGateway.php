<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Invoice;

use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\PayGateway;
use Box\Mod\Invoice\Entity\Subscription;
use Box\Mod\Invoice\Entity\Transaction;
use Box\Mod\Invoice\Repository\PayGatewayRepository;
use FOSSBilling\Extension\Contract\Payment\CheckoutUrls;
use FOSSBilling\Extension\Contract\Payment\ContextAware;
use FOSSBilling\Extension\Contract\Payment\Gateway;
use FOSSBilling\Extension\Contract\Payment\SupportsSubscriptions;
use FOSSBilling\Extension\Contract\Payment\ValidatesSettings;
use FOSSBilling\Extension\ExtensionType;
use FOSSBilling\Extension\Manifest;
use FOSSBilling\InjectionAwareInterface;
use FOSSBilling\Tools;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class ServicePayGateway implements InjectionAwareInterface
{
    /**
     * Sent by the admin UI in place of a secret config field's value to mean
     * "keep the currently stored value". Blank input means the same thing;
     * this sentinel exists only for clients that can't send an empty string.
     */
    public const string CREDENTIAL_KEEP_SENTINEL = '__KEEP__';

    protected ?\Pimple\Container $di = null;

    protected PayGatewayRepository $payGatewayRepository;

    public function __construct(private ?Filesystem $filesystem = null)
    {
    }

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
        if (isset($di['filesystem'])) {
            $this->filesystem = $di['filesystem'];
        }
        $this->payGatewayRepository = $di['em']->getRepository(PayGateway::class);
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getPayGatewayRepository(): PayGatewayRepository
    {
        return $this->payGatewayRepository;
    }

    /**
     * @return mixed[]
     */
    public function getPairs(): array
    {
        $sql = 'SELECT id, gateway, name
            FROM pay_gateway';

        $rows = $this->di['em']->getConnection()->fetchAllAssociative($sql);
        $result = [];
        foreach ($rows as $row) {
            $result[$row['id']] = $row['name'];
        }

        return $result;
    }

    /**
     * @return mixed[]
     */
    public function getAvailable(): array
    {
        $sql = 'SELECT id, gateway, name
            FROM pay_gateway';

        $rows = $this->di['em']->getConnection()->fetchAllAssociative($sql);
        $exists = [];
        foreach ($rows as $row) {
            $exists[$row['gateway']] = $row['name'];
        }

        $adapters = [];
        foreach ($this->di['extension_locator']->listInstalled(ExtensionType::Gateway) as $adapter) {
            if (!array_key_exists($adapter, $exists)) {
                $adapters[] = $adapter;
            }
        }

        return $adapters;
    }

    public function install($code): bool
    {
        $available = $this->getAvailable();
        if (!in_array($code, $available)) {
            throw new \FOSSBilling\Exception('Payment gateway is not available for installation.');
        }

        $new = new PayGateway();
        $new->setName($code);
        $new->setGateway($code);
        $new->setEnabled(false);
        $new->setAcceptedCurrencies(null);
        $new->setTestMode(false);
        $new->setConfig(null);
        $this->di['em']->persist($new);
        $this->di['em']->flush();

        $this->di['logger']->info('Installed new payment gateway {code}', ['code' => $code]);

        return true;
    }

    public function toApiArray(PayGateway $model, $deep = false, $identity = null): array
    {
        [$single, $recurrent] = $this->_getAllowTuple($model);

        $result = [
            'id' => $model->getId(),
            'code' => $model->getGateway(),
            'title' => $model->getName(),
            'allow_single' => $model->isAllowSingle(),
            'allow_recurrent' => $model->isAllowRecurrent(),
            'accepted_currencies' => $this->getAcceptedCurrencies($model),
        ];

        if ($identity instanceof \Box\Mod\Staff\Entity\Admin) {
            $config = json_decode($model->getConfig() ?? '', true) ?? [];
            $secretFields = $this->getSecretFields($model);
            foreach ($secretFields as $field) {
                $value = $config[$field] ?? null;
                $config[$field] = null;
                $config[$field . '_set'] = $value !== null && $value !== '';
            }

            $result['supports_one_time_payments'] = $single;
            $result['supports_subscriptions'] = $recurrent;
            $result['config'] = $config;
            $result['secret_fields'] = $secretFields;
            $result['form'] = $this->getFormElements($model);
            $result['description'] = $this->getDescription($model);
            $result['enabled'] = $model->isEnabled();
            $result['test_mode'] = $model->isTestMode();
            $result['callback'] = $this->getCallbackUrl($model);
        }

        return $result;
    }

    /**
     * Config field names for this gateway whose stored values must be hidden in
     * the API and admin UI: the settings its manifest marks `secret`.
     *
     * @return string[]
     */
    public function getSecretFields(PayGateway $model): array
    {
        try {
            $settings = $this->manifestFor($model)->settings;
        } catch (\Throwable) {
            // The gateway is not installed, so there is nothing it could be asked to hide.
            return [];
        }

        $secrets = [];
        foreach ($settings as $field) {
            if (!empty($field['secret'])) {
                $secrets[] = (string) $field['name'];
            }
        }

        return $secrets;
    }

    public function copy(PayGateway $model): int
    {
        $new = new PayGateway();
        $new->setName($model->getName() . ' (Copy)');
        $new->setGateway($model->getGateway());
        $new->setEnabled(false);
        $new->setAcceptedCurrencies($model->getAcceptedCurrencies());
        $new->setTestMode($model->isTestMode());
        $new->setConfig($model->getConfig());
        $this->di['em']->persist($new);
        $this->di['em']->flush();
        $newId = (int) $new->getId();
        $this->di['logger']->info('Copied payment gateway #{gateway_id} - {gateway}', ['gateway_id' => $newId, 'gateway' => $model->getGateway()]);

        return $newId;
    }

    public function update(PayGateway $model, array $data): bool
    {
        $model->setName($data['title'] ?? $model->getName());

        $newEnabled = isset($data['enabled']) ? (bool) $data['enabled'] : $model->isEnabled();
        $newTestMode = isset($data['test_mode']) ? (bool) $data['test_mode'] : $model->isTestMode();
        $existingConfig = json_decode($model->getConfig() ?? '', true) ?? [];
        $mergedConfig = $existingConfig;
        if (isset($data['config']) && is_array($data['config'])) {
            $secretFields = $this->getSecretFields($model);
            foreach ($data['config'] as $key => $value) {
                $mergedConfig[$key] = in_array($key, $secretFields, true)
                    ? $this->normalizeSecretValue($key, $value, $existingConfig[$key] ?? null, $model)
                    : $value;
            }
        }

        if ($newEnabled) {
            $this->validateGatewayConfig($model, $mergedConfig, $newTestMode);
        }

        if (isset($data['config']) && is_array($data['config'])) {
            $model->setConfig(json_encode($mergedConfig));
        }

        if (isset($data['accepted_currencies']) && is_array($data['accepted_currencies'])) {
            $model->setAcceptedCurrencies(json_encode($data['accepted_currencies']));
        }

        $model->setEnabled($newEnabled);
        $model->setAllowSingle((bool) ($data['allow_single'] ?? $model->isAllowSingle()));
        $model->setAllowRecurrent((bool) ($data['allow_recurrent'] ?? $model->isAllowRecurrent()));
        $model->setTestMode($newTestMode);
        $this->di['em']->flush();
        $this->di['logger']->info('Updated payment gateway {model_gateway}', ['model_gateway' => $model->getGateway()]);

        return true;
    }

    /**
     * Validate that a gateway's settings would be accepted, before persisting
     * an "enabled" update: the manifest's `settings` schema is checked first
     * (required fields for the chosen enabled/test-mode combination), then
     * the adapter's own ValidatesSettings capability, if it implements one,
     * gets a chance to enforce cross-field rules the schema can't express.
     *
     * This replaces the old `new $class($config)` inside a try/catch, which
     * offered no useful error beyond whatever the constructor happened to
     * throw.
     */
    private function validateGatewayConfig(PayGateway $model, array $config, bool $testMode): void
    {
        $class = $this->getAdapterClassName($model);
        if (!class_exists($class)) {
            return;
        }

        try {
            $manifest = $this->manifestFor($model);
            $this->validateSettingsAgainstSchema($manifest->settings, $config, $testMode);

            $instance = new $class($config);
            if ($instance instanceof ValidatesSettings) {
                $instance->validateSettings($config);
            }
        } catch (\FOSSBilling\Extension\Contract\Payment\Exception $e) {
            throw new \FOSSBilling\Exception($e->getMessage(), null, 819);
        } catch (\Throwable $e) {
            throw new \FOSSBilling\Exception('Payment gateway configuration error: ' . $e->getMessage(), null, 819);
        }
    }

    /**
     * @param list<array<string, mixed>> $fields
     */
    private function validateSettingsAgainstSchema(array $fields, array $config, bool $testMode): void
    {
        $context = ['enabled' => true, 'test_mode' => $testMode];

        foreach ($fields as $field) {
            $name = (string) $field['name'];
            $value = $config[$name] ?? null;

            if ($this->isSettingsFieldRequired($field, $context) && ($value === null || $value === '')) {
                throw new \FOSSBilling\Exception('Payment gateway configuration error: :field is required', [':field' => $field['label'] ?? $name]);
            }
        }
    }

    /**
     * @param array<string, mixed> $field
     * @param array<string, bool>  $context
     */
    private function isSettingsFieldRequired(array $field, array $context): bool
    {
        if (isset($field['required_when']) && is_array($field['required_when'])) {
            foreach ($field['required_when'] as $key => $expected) {
                if (($context[$key] ?? null) !== $expected) {
                    return false;
                }
            }

            return true;
        }

        return (bool) ($field['required'] ?? false);
    }

    /**
     * Returns the value to store for a secret config field. Blank, whitespace-only
     * or {@see CREDENTIAL_KEEP_SENTINEL} inputs preserve the existing value;
     * everything else replaces it. A successful rotation is logged (the value
     * itself is never logged).
     */
    private function normalizeSecretValue(string $field, mixed $incoming, mixed $existing, PayGateway $model): mixed
    {
        if ($incoming === null || !is_scalar($incoming)) {
            return $existing;
        }

        $incoming = (string) $incoming;

        if (trim($incoming) === '' || $incoming === self::CREDENTIAL_KEEP_SENTINEL) {
            return $existing;
        }

        if ($incoming !== $existing) {
            $adminId = $this->di['loggedin_admin']->getId() ?? 'unknown';
            $this->di['logger']->info('Rotated {field} for payment gateway {gateway_id} by admin {admin_id}', ['field' => $field, 'gateway_id' => (string) $model->getId(), 'admin_id' => (string) $adminId]);
        }

        return $incoming;
    }

    public function delete(PayGateway $model): bool
    {
        $id = (int) $model->getId();

        if ($this->di['em']->getRepository(Invoice::class)->existsByGatewayId($id)) {
            throw new \FOSSBilling\InformationException('Cannot remove payment gateway with existing invoices');
        }

        if ($this->di['em']->getRepository(Subscription::class)->existsByGatewayId($id)) {
            throw new \FOSSBilling\InformationException('Cannot remove payment gateway with existing subscriptions');
        }

        if ($this->di['em']->getRepository(Transaction::class)->existsByGatewayId($id)) {
            throw new \FOSSBilling\InformationException('Cannot remove payment gateway with existing transactions');
        }

        $this->di['em']->remove($model);
        $this->di['em']->flush();
        $this->di['logger']->info('Removed payment gateway {id}', ['id' => $id]);

        return true;
    }

    /**
     * @return mixed[]
     */
    public function getActive(array $data): array
    {
        $format = $data['format'] ?? null;

        $gateways = $this->payGatewayRepository->findEnabledOrderedByIdDesc();
        $result = [];
        foreach ($gateways as $gtw) {
            if ($format == 'pairs') {
                $result[$gtw->getId()] = $gtw->getName();
            } else {
                $gateway = $this->toApiArray($gtw);
                $config = $this->getAdapterConfig($gtw);

                if (!empty($config['logo'])) {
                    $gateway['logo'] = $config['logo'];
                    $gateway['logo']['logo'] = $this->resolveGatewayLogo((string) $gtw->getGateway(), $config['logo']);
                } else {
                    // Templates read gtw.logo.logo/height/width unconditionally,
                    // so always provide the key even when the adapter ships no logo.
                    $gateway['logo'] = [
                        'logo' => $this->resolveGatewayLogo((string) $gtw->getGateway(), []),
                        'height' => '50px',
                        'width' => '50px',
                    ];
                }

                $result[] = $gateway;
            }
        }

        return $result;
    }

    public function resolveGatewayLogo(string $gateway, array $logoConfig): string
    {
        $filename = $logoConfig['file'] ?? 'default.png';

        $extensionPath = Path::join(ExtensionType::Gateway->pathFor($gateway), $filename);
        $publicPath = Path::join(PATH_ROOT, 'public', 'gateways', $filename);

        if ($this->filesystem->exists($extensionPath)) {
            return $this->di['tools']->url(sprintf('/extensions/%s/%s/%s', ExtensionType::Gateway->value, $gateway, $filename));
        }

        if ($this->filesystem->exists($publicPath)) {
            return $this->di['tools']->url("/public/gateways/{$filename}");
        }

        return $this->di['tools']->url('/public/gateways/default.png');
    }

    public function canPerformRecurrentPayment(PayGateway $model): bool
    {
        return $model->isAllowRecurrent();
    }

    public function canPerformSinglePayment(PayGateway $model): bool
    {
        return $model->isAllowSingle();
    }

    /**
     * Whether the gateway settles payments through explicit admin approval
     * instead of a verifiable gateway callback.
     *
     * Read from the gateway's manifest rather than from its class, so a
     * misconfigured automated gateway (missing API keys) still reports its
     * capability. An unknown or unreadable gateway defaults to automated handling.
     */
    public static function isManualApprovalGateway(?string $code): bool
    {
        if ($code === null || preg_match('/\A[A-Za-z0-9_]+\z/', $code) !== 1) {
            return false;
        }

        try {
            return Manifest::fromDirectory(ExtensionType::Gateway->pathFor($code), $code, ExtensionType::Gateway)->manualApproval;
        } catch (\FOSSBilling\InformationException) {
            return false;
        }
    }

    public function getPaymentAdapter(PayGateway $pg, ?Invoice $model = null, $optional = []): object
    {
        $config = json_decode($pg->getConfig() ?? '', true) ?? [];
        $config['test_mode'] = $pg->isTestMode();
        $config['gateway_id'] = (int) $pg->getId();

        $class = $this->getAdapterClassName($pg);

        if (!class_exists($class)) {
            throw new \FOSSBilling\Exception('Payment gateway :adapter was not found.', [':adapter' => $class]);
        }

        // PayPalEmail and Stripe still take their return/cancel/notify URLs
        // smuggled into the settings array their constructor receives,
        // rather than receiving them per-checkout via CheckoutRequest::$urls.
        // Delete this branch once both are migrated onto Gateway.
        if (!is_a($class, Gateway::class, true)) {
            $config = array_merge($config, $this->legacyUrlConfig($pg, $model, $optional));
        }

        $adapter = new $class($config);

        // Gateways built against the payment contract get the narrow context.
        // Those still using the container get it until they are migrated.
        if ($adapter instanceof ContextAware) {
            $adapter->setContext(new PaymentContext($this->di));
        } elseif (method_exists($adapter, 'setDi')) {
            $adapter->setDi($this->di);
        }

        return $adapter;
    }

    /**
     * The return/cancel/callback URLs a Gateway-typed adapter receives via
     * CheckoutRequest::$urls for a specific checkout.
     */
    public function getCheckoutUrls(PayGateway $pg, Invoice $model): CheckoutUrls
    {
        return new CheckoutUrls(
            return: $this->getReturnUrl($pg, $model),
            cancel: $this->getCancelUrl($pg, $model),
            callback: $this->getCallbackRedirect($pg, $model),
        );
    }

    /**
     * @return mixed[]
     */
    private function legacyUrlConfig(PayGateway $pg, ?Invoice $model, array $optional): array
    {
        $defaults = [];
        $defaults['auto_redirect'] = $optional['auto_redirect'] ?? false;
        $defaults['return_url'] = $this->getReturnUrl($pg, $model);
        $defaults['cancel_url'] = $this->getCancelUrl($pg, $model);
        $defaults['notify_url'] = $this->getCallbackUrl($pg, $model);
        $defaults['redirect_url'] = $this->getCallbackRedirect($pg, $model);
        $defaults['continue_shopping_url'] = $this->di['tools']->url('/order');
        $defaults['single_page'] = true;
        if ($model instanceof Invoice) {
            $defaults['thankyou_url'] = $this->di['url']->link("/invoice/thank-you/{$model->getHash()}", ['restore_token' => Tools::createSessionRestoreToken($this->di['session']->getId())]);
            $defaults['invoice_url'] = $this->di['tools']->url("/invoice/{$model->getHash()}");
        }
        $defaults['logo'] = null;

        return $defaults;
    }

    /**
     * Whether this gateway can take a recurring payment and, separately, can
     * take a one-time payment. `supports_one_time_payments` no longer gates
     * anything — implementing Gateway (or, on the legacy path, `getHtml()`)
     * is itself the claim that one-time payments work. `supports_subscriptions`
     * is `instanceof SupportsSubscriptions`, checked against the class
     * without constructing it, so listing gateways never has to run one.
     */
    private function _getAllowTuple(PayGateway $model): array
    {
        $class = $this->getAdapterClassName($model);
        $recurrent = class_exists($class) && is_a($class, SupportsSubscriptions::class, true);

        return [true, $recurrent];
    }

    public function getAdapterConfig(PayGateway $pg): array
    {
        $class = $this->getAdapterClassName($pg);

        if (!class_exists($class)) {
            throw new \FOSSBilling\Exception('Payment gateway :adapter was not found', [':adapter' => $pg->getGateway()]);
        }

        $manifest = $this->manifestFor($pg);

        return [
            'description' => $manifest->description,
            'form' => $manifest->settings,
            'logo' => $manifest->logo,
            'embeddable' => $manifest->embeddable,
        ];
    }

    private function manifestFor(PayGateway $pg): Manifest
    {
        return $this->di['extension_locator']->manifest(ExtensionType::Gateway, (string) $pg->getGateway());
    }

    public function getAdapterClassName(PayGateway $pg): string
    {
        $gateway = $pg->getGateway();
        if ($gateway === null || $gateway === '') {
            throw new \FOSSBilling\Exception('Payment gateway :adapter was not found', [':adapter' => '']);
        }

        return $this->di['extension_locator']->resolveClass(ExtensionType::Gateway, $gateway);
    }

    public function getAcceptedCurrencies(PayGateway $model): array
    {
        $accepted = $model->getAcceptedCurrencies();
        if ($accepted === null || empty($accepted)) {
            $currencyService = $this->di['mod_service']('currency');
            /** @var \Box\Mod\Currency\Repository\CurrencyRepository $currencyRepository */
            $currencyRepository = $currencyService->getCurrencyRepository();

            return array_keys($currencyRepository->getPairs());
        }

        $decoded = json_decode($accepted, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function getFormElements(PayGateway $model): array
    {
        $config = $this->getAdapterConfig($model);
        if (isset($config['form']) && is_array($config['form'])) {
            return $config['form'];
        }

        return [];
    }

    public function getDescription(PayGateway $model): ?string
    {
        $config = $this->getAdapterConfig($model);

        return $config['description'] ?? null;
    }

    public function getCallbackUrl(PayGateway $pg, ?Invoice $model = null): string
    {
        $p = [
            'gateway_id' => $pg->getId(),
        ];
        if ($model instanceof Invoice) {
            $p['invoice_id'] = $model->getId();
        }

        return SYSTEM_URL . 'ipn.php?' . http_build_query($p);
    }

    private function getReturnUrl(PayGateway $pg, ?Invoice $model = null): string
    {
        if ($model instanceof Invoice) {
            return $this->di['url']->link("/invoice/{$model->getHash()}", ['status' => 'ok', 'restore_token' => Tools::createSessionRestoreToken($this->di['session']->getId())]);
        }

        return $this->di['url']->link('/invoice', ['status' => 'ok', 'restore_token' => Tools::createSessionRestoreToken($this->di['session']->getId())]);
    }

    private function getCancelUrl(PayGateway $pg, ?Invoice $model = null): string
    {
        if ($model instanceof Invoice) {
            return $this->di['url']->link("/invoice/{$model->getHash()}", ['status' => 'cancel', 'restore_token' => Tools::createSessionRestoreToken($this->di['session']->getId())]);
        }

        return $this->di['url']->link('/invoice', ['status' => 'cancel', 'restore_token' => Tools::createSessionRestoreToken($this->di['session']->getId())]);
    }

    private function getCallbackRedirect(PayGateway $pg, ?Invoice $model = null): string
    {
        $p = [
            'gateway_id' => $pg->getId(),
        ];

        if ($model instanceof Invoice) {
            $p['invoice_id'] = $model->getId();
            $p['invoice_hash'] = $model->getHash();
            $p['redirect'] = 1;
        }

        return SYSTEM_URL . 'ipn.php?' . http_build_query($p);
    }
}
