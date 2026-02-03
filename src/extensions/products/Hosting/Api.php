<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\ProductType\Hosting;

use FOSSBilling\ProductType\Hosting\Entity\Hosting;
use FOSSBilling\Validation\Api\RequiredParams;
use FOSSBilling\Validation\Api\RequiredRole;

class Api extends \Api_Abstract
{
    #[RequiredRole(['admin'])]
    public function admin_change_plan($data): bool
    {
        if (!isset($data['plan_id'])) {
            throw new \FOSSBilling\Exception('plan_id is missing');
        }

        [$order, $s] = $this->getServiceForAdmin($data);
        $plan = $this->di['db']->getExistingModelById('ExtProductHostingPlan', $data['plan_id'], 'Hosting plan not found');

        $service = $this->getService();

        return (bool) $service->changeAccountPlan($order, $s, $plan);
    }

    #[RequiredRole(['admin'])]
    public function admin_change_username($data): bool
    {
        [$order, $s] = $this->getServiceForAdmin($data);
        $service = $this->getService();

        return (bool) $service->changeAccountUsername($order, $s, $data);
    }

    #[RequiredRole(['admin'])]
    public function admin_change_ip($data): bool
    {
        [$order, $s] = $this->getServiceForAdmin($data);
        $service = $this->getService();

        return (bool) $service->changeAccountIp($order, $s, $data);
    }

    #[RequiredRole(['admin'])]
    public function admin_change_domain($data): bool
    {
        [$order, $s] = $this->getServiceForAdmin($data);
        $service = $this->getService();

        return (bool) $service->changeAccountDomain($order, $s, $data);
    }

    #[RequiredRole(['admin'])]
    public function admin_change_password($data): bool
    {
        [$order, $s] = $this->getServiceForAdmin($data);
        $service = $this->getService();

        return (bool) $service->changeAccountPassword($order, $s, $data);
    }

    #[RequiredRole(['admin'])]
    public function admin_sync($data): bool
    {
        [$order, $s] = $this->getServiceForAdmin($data);
        $service = $this->getService();

        return (bool) $service->sync($order, $s);
    }

    #[RequiredRole(['admin'])]
    public function admin_update($data): bool
    {
        [, $s] = $this->getServiceForAdmin($data);
        $service = $this->getService();

        return (bool) $service->update($s, $data);
    }

    #[RequiredRole(['admin'])]
    public function admin_manager_get_pairs($data)
    {
        return $this->getService()->getServerManagers();
    }

    #[RequiredRole(['admin'])]
    public function admin_server_get_pairs($data)
    {
        return $this->getService()->getServerPairs();
    }

    #[RequiredRole(['admin'])]
    public function admin_server_get_list($data)
    {
        [$sql, $params] = $this->getService()->getServersSearchQuery($data);
        $per_page = $data['per_page'] ?? $this->di['pager']->getDefaultPerPage();
        $result = $this->di['pager']->getPaginatedResultSet($sql, $params, $per_page);

        foreach ($result['list'] as $key => $server) {
            $bean = $this->di['db']->dispense('ExtProductHostingServer')->unbox();
            $bean->import($server);
            $model = $bean->box();

            $result['list'][$key] = $this->getService()->toHostingServerApiArray($model, false, $this->getIdentity());
        }

        return $result;
    }

    #[RequiredRole(['admin'])]
    public function admin_account_get_list($data)
    {
        [$sql, $params] = $this->getService()->getAccountsSearchQuery($data);
        $per_page = $data['per_page'] ?? $this->di['pager']->getDefaultPerPage();
        $result = $this->di['pager']->getPaginatedResultSet($sql, $params, $per_page);
        $orderService = $this->di['mod_service']('order');

        foreach ($result['list'] as $key => $account) {
            $bean = $this->di['db']->dispense('ExtProductHosting')->unbox();
            $bean->import($account);
            $model = $bean->box();

            $order = $this->di['db']->findOne(
                'ClientOrder',
                '(product_type = "hosting" OR (product_type IS NULL AND service_type = "hosting")) AND service_id = :service_id',
                [':service_id' => $model->id]
            );

            $result['list'][$key] = $this->getService()->toHostingAccountApiArray($model, true, $this->getIdentity());

            if ($order) {
                $result['list'][$key]['order'] = $orderService->toApiArray($order);
                $result['list'][$key]['client'] = $result['list'][$key]['order']['client'];

                unset($result['list'][$key]['order']['client']);
            } else {
                $result['list'][$key]['order'] = null;
            }
        }

        return $result;
    }

    #[RequiredRole(['admin'])]
    #[RequiredParams([
        'name' => 'Server name was not passed',
        'ip' => 'Server IP was not passed',
        'manager' => 'Server manager was not specified',
    ])]
    public function admin_server_create($data): int
    {
        $service = $this->getService();

        return (int) $service->createServer($data['name'], $data['ip'], $data['manager'], $data);
    }

    #[RequiredRole(['admin'])]
    #[RequiredParams(['id' => 'Server ID was not passed'])]
    public function admin_server_get($data)
    {
        $model = $this->di['db']->getExistingModelById('ExtProductHostingServer', $data['id'], 'Server not found');
        $service = $this->getService();

        return $service->toHostingServerApiArray($model, true, $this->getIdentity());
    }

    #[RequiredRole(['admin'])]
    #[RequiredParams(['id' => 'Server ID was not passed'])]
    public function admin_server_delete($data): bool
    {
        $model = $this->di['db']->getExistingModelById('ExtProductHostingServer', $data['id'], 'Server not found');

        $hosting_services = $this->di['db']->find('ExtProductHosting', 'ext_product_hosting_server_id = :cart_id', [':cart_id' => $data['id']]);
        $count = is_array($hosting_services) ? count($hosting_services) : 0;

        if ($count > 0) {
            throw new \FOSSBilling\InformationException('Hosting server is used by :count: service hostings', [':count:' => $count], 704);
        }

        return (bool) $this->getService()->deleteServer($model);
    }

    #[RequiredRole(['admin'])]
    #[RequiredParams(['id' => 'Server ID was not passed'])]
    public function admin_server_update($data): bool
    {
        $model = $this->di['db']->getExistingModelById('ExtProductHostingServer', $data['id'], 'Server not found');
        $service = $this->getService();

        $data['config'] = [
            'userprefix' => $data['userprefix'] ?? null,
        ];

        return (bool) $service->updateServer($model, $data);
    }

    #[RequiredRole(['admin'])]
    #[RequiredParams(['id' => 'Server ID was not passed'])]
    public function admin_server_test_connection($data): bool
    {
        $model = $this->di['db']->getExistingModelById('ExtProductHostingServer', $data['id'], 'Server not found');

        return (bool) $this->getService()->testConnection($model);
    }

    #[RequiredRole(['admin'])]
    public function admin_hp_get_pairs($data)
    {
        return $this->getService()->getHpPairs();
    }

    #[RequiredRole(['admin'])]
    public function admin_hp_get_list($data)
    {
        [$sql, $params] = $this->getService()->getHpSearchQuery($data);
        $per_page = $data['per_page'] ?? $this->di['pager']->getDefaultPerPage();
        $pager = $this->di['pager']->getPaginatedResultSet($sql, $params, $per_page);
        foreach ($pager['list'] as $key => $item) {
            $model = $this->di['db']->getExistingModelById('ExtProductHostingPlan', $item['id'], 'Post not found');
            $pager['list'][$key] = $this->getService()->toHostingHpApiArray($model, false, $this->getIdentity());
        }

        return $pager;
    }

    #[RequiredRole(['admin'])]
    #[RequiredParams(['id' => 'Hosting plan ID was not passed'])]
    public function admin_hp_delete($data): bool
    {
        $model = $this->di['db']->getExistingModelById('ExtProductHostingPlan', $data['id'], 'Hosting plan not found');

        $hosting_services = $this->di['db']->find('ExtProductHosting', 'ext_product_hosting_plan_id = :cart_id', [':cart_id' => $data['id']]);

        $count = is_array($hosting_services) ? count($hosting_services) : 0;
        if ($count > 0) {
            throw new \FOSSBilling\InformationException('Hosting plan is used by :count: service hostings', [':count:' => $count], 704);
        }

        return (bool) $this->getService()->deleteHp($model);
    }

    #[RequiredRole(['admin'])]
    #[RequiredParams(['id' => 'Hosting plan ID was not passed'])]
    public function admin_hp_get($data)
    {
        $model = $this->di['db']->getExistingModelById('ExtProductHostingPlan', $data['id'], 'Hosting plan not found');

        return $this->getService()->toHostingHpApiArray($model, true, $this->getIdentity());
    }

    #[RequiredRole(['admin'])]
    #[RequiredParams(['id' => 'Hosting plan ID was not passed'])]
    public function admin_hp_update($data): bool
    {
        $model = $this->di['db']->getExistingModelById('ExtProductHostingPlan', $data['id'], 'Hosting plan not found');

        $service = $this->getService();

        return (bool) $service->updateHp($model, $data);
    }

    #[RequiredRole(['admin'])]
    #[RequiredParams(['name' => 'Hosting plan name was not passed'])]
    public function admin_hp_create($data): int
    {
        $service = $this->getService();

        return (int) $service->createHp($data['name'], $data);
    }

    #[RequiredRole(['client'])]
    public function client_change_username($data)
    {
        [$order, $s] = $this->getServiceForClient($data);

        return $this->getService()->changeAccountUsername($order, $s, $data);
    }

    #[RequiredRole(['client'])]
    public function client_change_domain($data)
    {
        [$order, $s] = $this->getServiceForClient($data);

        return $this->getService()->changeAccountDomain($order, $s, $data);
    }

    #[RequiredRole(['client'])]
    public function client_change_password($data)
    {
        [$order, $s] = $this->getServiceForClient($data);

        return $this->getService()->changeAccountPassword($order, $s, $data);
    }

    #[RequiredRole(['client'])]
    public function client_hp_get_pairs($data)
    {
        return $this->getService()->getHpPairs();
    }

    #[RequiredRole(['client'])]
    public function client_get_login_url(array $data): string
    {
        [$order, $s] = $this->getServiceForClient($data);

        return $this->getService()->generateLoginUrl($s);
    }

    #[RequiredRole(['guest'])]
    #[RequiredParams(['product_id' => 'Product ID is missing'])]
    public function guest_free_tlds($data = [])
    {
        $product_id = $data['product_id'] ?? 0;
        $product = $this->di['db']->getExistingModelById('Product', $product_id, 'Product was not found');

        if ($product->type !== \Model_Product::HOSTING) {
            $friendlyName = ucfirst(__trans('Product type'));

            throw new \FOSSBilling\Exception(':friendlyName: is invalid', [':friendlyName:' => $friendlyName]);
        }

        return $this->getService()->getFreeTlds($product);
    }

    protected function getServiceForAdmin($data): array
    {
        $required = [
            'order_id' => 'Order ID name is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $order = $this->di['db']->getExistingModelById('ClientOrder', $data['order_id'], 'Order not found');
        $orderService = $this->di['mod_service']('order');
        $s = $orderService->getOrderService($order);
        if (!$s instanceof Hosting) {
            throw new \FOSSBilling\Exception('Order is not activated');
        }

        return [$order, $s];
    }

    protected function getServiceForClient($data): array
    {
        if (!isset($data['order_id'])) {
            throw new \FOSSBilling\Exception('Order ID is required');
        }
        $identity = $this->getIdentity();
        $order = $this->di['db']->findOne('ClientOrder', 'id = ? and client_id = ?', [$data['order_id'], $identity->id]);
        if (!$order instanceof \Model_ClientOrder) {
            throw new \FOSSBilling\Exception('Order not found');
        }

        $orderService = $this->di['mod_service']('order');
        $s = $orderService->getOrderService($order);
        if (!$s instanceof Hosting) {
            throw new \FOSSBilling\Exception('Order is not activated');
        }

        return [$order, $s];
    }
}
