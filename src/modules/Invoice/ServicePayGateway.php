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
use Box\Mod\Invoice\Repository\PayGatewayRepository;
use FOSSBilling\InjectionAwareInterface;
use FOSSBilling\Tools;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\Finder\Finder;

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

        $finder = new Finder();
        $finder->files()
            ->in(Path::join(PATH_LIBRARY, 'Payment', 'Adapter'))
            ->name('*.php')
            ->depth('== 0');

        $adapters = [];
        foreach ($finder as $file) {
            $adapter = $file->getFilenameWithoutExtension();
            if (!array_key_exists($adapter, $exists)) {
                $adapters[] = $adapter;
            }
        }

        try {
            $subFinder = new Finder();
            $subFinder->files()
                ->in(Path::join(PATH_LIBRARY, 'Payment', 'Adapter', '*'))
                ->name('*.php')
                ->depth('== 0');
            foreach ($subFinder as $file) {
                $adapter = $file->getFilenameWithoutExtension();
                if (!array_key_exists($adapter, $exists)) {
                    $adapters[] = $adapter;
                }
            }
        } catch (DirectoryNotFoundException) {
            // If the subdirectory does not exist, ignore the exception.
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
     * Config field names for this gateway's adapter whose stored values must
     * be hidden in the API and admin UI: the adapter's own declared secrets
     * plus any form field marked `'secret' => true`.
     *
     * @return string[]
     */
    public function getSecretFields(PayGateway $model): array
    {
        $secrets = [];

        try {
            $class = $this->getAdapterClassName($model);
            if (is_callable($class . '::getSecretFields')) {
                $declared = $class::getSecretFields();
                $secrets = array_merge($secrets, $declared);
            }
        } catch (\Throwable) {
            // Gateway adapter could not be resolved; fall back to the form's own 'secret' flags below.
        }

        $form = $this->getAdapterConfig($model)['form'] ?? [];
        if (is_array($form)) {
            foreach ($form as $name => $element) {
                $options = $element[1] ?? [];
                if (!empty($options['secret'])) {
                    $secrets[] = (string) $name;
                }
            }
        }

        return array_values(array_unique($secrets));
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
                    ? $this->normalizeSecretValue((string) $key, $value, $existingConfig[$key] ?? null, $model)
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
     * Verify that the gateway configuration would be accepted by the adapter
     * by attempting to instantiate it. This is used to enforce that the
     * required keys for the currently selected test mode are present before
     * persisting an "enabled" gateway update.
     */
    private function validateGatewayConfig(PayGateway $model, array $config, bool $testMode): void
    {
        $adapterConfig = $config;
        $adapterConfig['test_mode'] = $testMode;

        try {
            $class = $this->getAdapterClassName($model);
            if (!class_exists($class)) {
                return;
            }
            new $class($adapterConfig);
        } catch (\Payment_Exception $e) {
            throw new \FOSSBilling\Exception($e->getMessage(), null, 819);
        } catch (\Throwable $e) {
            throw new \FOSSBilling\Exception('Payment gateway configuration error: ' . $e->getMessage(), null, 819);
        }
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
        $id = $model->getId();
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
                $adapter = $this->getPaymentAdapter($gtw);
                $config = $adapter->getConfig();

                if (!empty($config['logo'])) {
                    $gateway['logo'] = $config['logo'];
                    $gateway['logo']['logo'] = $this->resolveGatewayLogo($config['logo']);
                }

                $result[] = $gateway;
            }
        }

        return $result;
    }

    public function resolveGatewayLogo(array $logoConfig): string
    {
        $filename = $logoConfig['logo'] ?? 'default.png';

        $libraryPath = Path::join(PATH_LIBRARY, 'Payment', 'Adapter', $filename);
        $publicPath = Path::join(PATH_ROOT, 'public', 'gateways', $filename);

        if ($this->filesystem->exists($libraryPath)) {
            return $this->di['tools']->url("/library/Payment/Adapter/{$filename}");
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

    public function getPaymentAdapter(PayGateway $pg, ?Invoice $model = null, $optional = []): object
    {
        $config = json_decode($pg->getConfig() ?? '', true) ?? [];
        $defaults = [];
        $defaults['auto_redirect'] = false;
        $defaults['gateway_id'] = (int) $pg->getId();
        $defaults['test_mode'] = $pg->isTestMode();
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

        if (isset($optional['auto_redirect'])) {
            $defaults['auto_redirect'] = $optional['auto_redirect'];
        }
        $defaults['logo'] = null;

        $config = array_merge($config, $defaults);

        $class = $this->getAdapterClassName($pg);

        if (!class_exists($class)) {
            throw new \FOSSBilling\Exception('Payment gateway :adapter was not found.', [':adapter' => $class]);
        }

        $adapter = new $class($config);

        if (method_exists($adapter, 'setDi')) {
            $adapter->setDi($this->di);
        }

        return $adapter;
    }

    private function _getAllowTuple(PayGateway $model): array
    {
        $adapter_config = $this->getAdapterConfig($model);
        $single = $adapter_config['supports_one_time_payments'] ?? false;
        $recurrent = $adapter_config['supports_subscriptions'] ?? false;

        return [
            $single,
            $recurrent,
        ];
    }

    public function getAdapterConfig(PayGateway $pg): array
    {
        $class = $this->getAdapterClassName($pg);

        if (!class_exists($class)) {
            throw new \FOSSBilling\Exception('Payment gateway :adapter was not found', [':adapter' => $pg->getGateway()]);
        }

        if (!method_exists($class, 'getConfig')) {
            $this->di['logger']->error("Payment $class gateway does not have getConfig method");

            return [];
        }

        // @phpstan-ignore argument.type
        return call_user_func([$class, 'getConfig']);
    }

    public function getAdapterClassName(PayGateway $pg): string
    {
        $gateway = $pg->getGateway();
        if ($gateway === null || $gateway === '') {
            throw new \FOSSBilling\Exception('Payment gateway :adapter was not found', [':adapter' => '']);
        }
        $class = "Payment_Adapter_{$gateway}";

        if (!class_exists($class)) {
            $nestedFile = Path::join(PATH_LIBRARY, 'Payment', 'Adapter', $gateway, "{$gateway}.php");
            $flatFile = Path::join(PATH_LIBRARY, 'Payment', 'Adapter', "{$gateway}.php");

            if ($this->filesystem->exists($nestedFile)) {
                require_once $nestedFile;
            } elseif ($this->filesystem->exists($flatFile)) {
                require_once $flatFile;
            }
        }

        return $class;
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
