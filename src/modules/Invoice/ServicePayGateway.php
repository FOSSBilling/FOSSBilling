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
use Box\Mod\Staff\Entity\Admin;
use FOSSBilling\InjectionAwareInterface;
use FOSSBilling\Tools;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\Finder\Finder;

class ServicePayGateway implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;
    private ?PayGatewayRepository $payGatewayRepository = null;

    public function __construct(private ?Filesystem $filesystem = null)
    {
    }

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
        if (isset($di['filesystem'])) {
            $this->filesystem = $di['filesystem'];
        }
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getSearchQuery(array $data): array
    {
        $sql = 'SELECT *
            FROM pay_gateway
            WHERE 1 ';

        $search = $data['search'] ?? null;
        $enabled = $data['enabled'] ?? null;
        $allowSingle = $data['allow_single'] ?? null;
        $allowRecurrent = $data['allow_recurrent'] ?? null;
        $testMode = $data['test_mode'] ?? null;
        $params = [];
        if ($search) {
            $sql .= 'AND (name LIKE :search OR gateway LIKE :search) ';
            $params['search'] = "%$search%";
        }

        if ($enabled !== null && $enabled !== '') {
            $sql .= 'AND enabled = :enabled ';
            $params['enabled'] = (int) $enabled;
        }

        if ($allowSingle !== null && $allowSingle !== '') {
            $sql .= 'AND allow_single = :allow_single ';
            $params['allow_single'] = (int) $allowSingle;
        }

        if ($allowRecurrent !== null && $allowRecurrent !== '') {
            $sql .= 'AND allow_recurrent = :allow_recurrent ';
            $params['allow_recurrent'] = (int) $allowRecurrent;
        }

        if ($testMode !== null && $testMode !== '') {
            $sql .= 'AND test_mode = :test_mode ';
            $params['test_mode'] = (int) $testMode;
        }

        $sql .= ' ORDER by gateway ASC';

        return [$sql, $params];
    }

    /**
     * @return mixed[]
     */
    public function getPairs(): array
    {
        $sql = 'SELECT id, gateway, name
            FROM pay_gateway';

        $rows = $this->di['dbal']->fetchAllAssociative($sql);
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

        $rows = $this->di['dbal']->fetchAllAssociative($sql);
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

        $this->di['logger']->info('Installed new payment gateway %s', $code);

        return true;
    }

    public function toApiArray(PayGateway $model, $deep = false, $identity = null): array
    {
        [$single, $recurrent] = $this->_getAllowTuple($model);

        $result = [
            'id' => $model instanceof PayGateway ? $model->getId() : $model->id,
            'code' => $model instanceof PayGateway ? $model->getGateway() : $model->gateway,
            'title' => $model instanceof PayGateway ? $model->getName() : $model->name,
            'allow_single' => $model instanceof PayGateway ? $model->isAllowSingle() : $model->allow_single,
            'allow_recurrent' => $model instanceof PayGateway ? $model->isAllowRecurrent() : $model->allow_recurrent,
            'accepted_currencies' => $this->getAcceptedCurrencies($model),
        ];

        if ($identity instanceof Admin) {
            $result['supports_one_time_payments'] = $single;
            $result['supports_subscriptions'] = $recurrent;
            $config = $model instanceof PayGateway ? $model->getConfig() : $model->config;
            $result['config'] = json_decode($config ?? '', true) ?? [];
            $result['form'] = $this->getFormElements($model);
            $result['description'] = $this->getDescription($model);
            $result['enabled'] = $model instanceof PayGateway ? $model->isEnabled() : $model->enabled;
            $result['test_mode'] = $model instanceof PayGateway ? $model->isTestMode() : $model->test_mode;
            $result['callback'] = $this->getCallbackUrl($model);
        }

        return $result;
    }

    public function copy(PayGateway $model): int
    {
        $new = new PayGateway();
        $new->setName($model->getName() . ' (Copy)');
        $new->setGateway($model->getGateway());
        $new->setEnabled(false);
        $new->setAcceptedCurrencies($model->getAcceptedCurrencies());
        $new->setTestMode((bool) $model->isTestMode());
        $new->setConfig($model->getConfig());
        $this->di['em']->persist($new);
        $this->di['em']->flush();
        $newId = $new->getId();
        $this->di['logger']->info('Copied payment gateway #%s - %s', $newId, $model->getGateway());

        return $newId;
    }

    public function update(PayGateway $model, array $data): bool
    {
        $model->name = $data['title'] ?? $model->getName();

        $newEnabled = isset($data['enabled']) ? (bool) $data['enabled'] : (bool) $model->isEnabled();
        $newTestMode = isset($data['test_mode']) ? (bool) $data['test_mode'] : (bool) $model->isTestMode();
        $mergedConfig = json_decode($model->getConfig() ?? '', true) ?? [];
        if (isset($data['config']) && is_array($data['config'])) {
            $mergedConfig = array_merge($mergedConfig, $data['config']);
        }

        if ($newEnabled) {
            $this->validateGatewayConfig($model, $mergedConfig, $newTestMode);
        }

        if (isset($data['config']) && is_array($data['config'])) {
            $model->config = json_encode($data['config']);
        }

        if (isset($data['accepted_currencies']) && is_array($data['accepted_currencies'])) {
            $model->accepted_currencies = json_encode($data['accepted_currencies']);
        }

        $model->enabled = $newEnabled;
        $model->allow_single = (bool) ($data['allow_single'] ?? $model->isAllowSingle());
        $model->allow_recurrent = (bool) ($data['allow_recurrent'] ?? $model->isAllowRecurrent());
        $model->test_mode = $newTestMode;
        $this->di['em']->persist($model);
        $this->di['em']->flush();
        $this->di['logger']->info('Updated payment gateway %s', $model->getGateway());

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

    public function delete(PayGateway $model): bool
    {
        $id = $model->getId();
        $this->di['em']->remove($model);
        $this->di['em']->flush();
        $this->di['logger']->info('Removed payment gateway %s', $id);

        return true;
    }

    /**
     * @return mixed[]
     */
    public function getActive(array $data): array
    {
        $format = $data['format'] ?? null;

        $gateways = $this->getPayGatewayRepository()->findEnabledOrderedByIdDesc();
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
        return (bool) ($model instanceof PayGateway ? $model->isAllowRecurrent() : $model->allow_recurrent);
    }

    public function canPerformSinglePayment(PayGateway $model): bool
    {
        return (bool) ($model instanceof PayGateway ? $model->isAllowSingle() : $model->allow_single);
    }

    public function getPaymentAdapter(PayGateway $pg, ?Invoice $model = null, $optional = []): object
    {
        $pgConfig = $pg instanceof PayGateway ? $pg->getConfig() : $pg->config;
        $pgId = $pg instanceof PayGateway ? $pg->getId() : $pg->id;
        $pgTestMode = $pg instanceof PayGateway ? $pg->isTestMode() : $pg->test_mode;

        $config = json_decode($pgConfig ?? '', true) ?? [];
        $defaults = [];
        $defaults['auto_redirect'] = false;
        $defaults['gateway_id'] = (int) $pgId;
        $defaults['test_mode'] = $pgTestMode;
        $defaults['return_url'] = $this->getReturnUrl($pg, $model);
        $defaults['cancel_url'] = $this->getCancelUrl($pg, $model);
        $defaults['notify_url'] = $this->getCallbackUrl($pg, $model);
        $defaults['redirect_url'] = $this->getCallbackRedirect($pg, $model);
        $defaults['continue_shopping_url'] = $this->di['tools']->url('/order');
        $defaults['single_page'] = true;
        if ($model instanceof Invoice) {
            $defaults['thankyou_url'] = $this->di['url']->link("/invoice/thank-you/{$model->getHash()}", ['restore_token' => Tools::createSessionRestoreToken(session_id())]);
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

        $gatewayName = $pg instanceof PayGateway ? $pg->getGateway() : $pg->gateway;

        if (!class_exists($class)) {
            throw new \FOSSBilling\Exception('Payment gateway :adapter was not found', [':adapter' => $gatewayName]);
        }

        if (!method_exists($class, 'getConfig')) {
            error_log("Payment $class gateway does not have getConfig method");

            return [];
        }

        // @phpstan-ignore argument.type
        return call_user_func([$class, 'getConfig']);
    }

    public function getAdapterClassName(PayGateway $pg): string
    {
        $gateway = $pg instanceof PayGateway ? $pg->getGateway() : $pg->gateway;
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
        $acceptedCurrencies = $model instanceof PayGateway ? $model->getAcceptedCurrencies() : $model->accepted_currencies;

        if ($acceptedCurrencies === null || empty($acceptedCurrencies)) {
            $currencyService = $this->di['mod_service']('currency');
            /** @var \Box\Mod\Currency\Repository\CurrencyRepository $currencyRepository */
            $currencyRepository = $currencyService->getCurrencyRepository();

            return array_keys($currencyRepository->getPairs());
        }

        return json_decode($acceptedCurrencies ?? '', true);
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

    /**
     * @param Invoice $model
     */
    public function getCallbackUrl(PayGateway $pg, $model = null): string
    {
        $pgId = $pg instanceof PayGateway ? $pg->getId() : $pg->id;
        $p = [
            'gateway_id' => $pgId,
        ];
        if ($model instanceof Invoice) {
            $p['invoice_id'] = $model->getId();
        }

        return SYSTEM_URL . 'ipn.php?' . http_build_query($p);
    }

    /**
     * @param Invoice $model
     */
    private function getReturnUrl(PayGateway $pg, $model = null): string
    {
        if ($model instanceof Invoice) {
            return $this->di['url']->link("/invoice/{$model->getHash()}", ['status' => 'ok', 'restore_token' => Tools::createSessionRestoreToken(session_id())]);
        }

        return $this->di['url']->link('/invoice', ['status' => 'ok', 'restore_token' => Tools::createSessionRestoreToken(session_id())]);
    }

    /**
     * @param Invoice $model
     */
    private function getCancelUrl(PayGateway $pg, $model = null): string
    {
        if ($model instanceof Invoice) {
            return $this->di['url']->link("/invoice/{$model->getHash()}", ['status' => 'cancel', 'restore_token' => Tools::createSessionRestoreToken(session_id())]);
        }

        return $this->di['url']->link('/invoice', ['status' => 'cancel', 'restore_token' => Tools::createSessionRestoreToken(session_id())]);
    }

    /**
     * @param Invoice $model
     */
    private function getCallbackRedirect(PayGateway $pg, $model = null): string
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

    private function getPayGatewayRepository(): PayGatewayRepository
    {
        if ($this->payGatewayRepository === null) {
            $this->payGatewayRepository = $this->di['em']->getRepository(PayGateway::class);
        }

        return $this->payGatewayRepository;
    }
}
