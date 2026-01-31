<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Order\Controller;

use FOSSBilling\Exception;

class Client implements \FOSSBilling\InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function register(\Box_App &$app): void
    {
        $app->get('/order', 'get_products', [], static::class);
        $app->get('/order/service', 'get_orders', [], static::class);
        $app->get('/order/:id', 'get_configure_product', ['id' => '[0-9]+'], static::class);
        $app->get('/order/:slug', 'get_configure_product_by_slug', ['slug' => '[a-z0-9-]+'], static::class);
        $app->get('/order/service/manage/:id', 'get_order', ['id' => '[0-9]+'], static::class);
    }

    public function get_products(\Box_App $app): string
    {
        return $app->render('mod_order_index');
    }

    public function get_configure_product_by_slug(\Box_App $app, $slug): string
    {
        $api = $this->di['api_guest'];
        $product = $api->product_get(['slug' => $slug]);
        [$tpl, $tplFile] = $this->resolveTemplateName($product['type'], 'order');
        if ($api->system_template_exists(['file' => $tplFile])) {
            return $app->render($tpl, ['product' => $product]);
        }

        return $app->render('mod_order_product', ['product' => $product]);
    }

    public function get_configure_product(\Box_App $app, $id): string
    {
        $api = $this->di['api_guest'];
        $product = $api->product_get(['id' => $id]);
        [$tpl, $tplFile] = $this->resolveTemplateName($product['type'], 'order');
        if ($api->system_template_exists(['file' => $tplFile])) {
            return $app->render($tpl, ['product' => $product]);
        }

        return $app->render('mod_order_product', ['product' => $product]);
    }

    public function get_orders(\Box_App $app): string
    {
        $this->di['is_client_logged'];

        return $app->render('mod_order_list');
    }

    public function get_order(\Box_App $app, $id): string
    {
        $api = $this->di['api_client'];
        $data = [
            'id' => $id,
        ];
        $order = $api->order_get($data);

        $typeCode = $order['product_type'] ?? $order['service_type'];
        $servicePartial = $this->resolveTemplate(
            $typeCode,
            'manage'
        );

        return $app->render('mod_order_manage', [
            'order' => $order,
            'service_partial' => $servicePartial,
        ]);
    }

    private function resolveTemplate(string $type, string $kind): string
    {
        if (!$this->di || !isset($this->di['product_type_registry'])) {
            throw new Exception('Product type registry is not available');
        }

        try {
            return $this->di['product_type_registry']->getTemplate($type, $kind);
        } catch (\Throwable) {
            if ($kind === 'order') {
                return 'mod_order_product';
            }

            return sprintf('ext_product_%s_%s.html.twig', $type, $kind);
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveTemplateName(string $type, string $kind): array
    {
        $template = $this->resolveTemplate($type, $kind);

        if (str_ends_with($template, '.html.twig')) {
            return [substr($template, 0, -10), $template];
        }

        return [$template, $template . '.html.twig'];
    }
}
