<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Servicedownloadable\Api;

use Box\Mod\Order\Entity\Order;
use Box\Mod\Servicedownloadable\Entity\ServiceDownloadable;
use Box\Mod\Servicedownloadable\Entity\ServiceDownloadableFile;
use FOSSBilling\Validation\Api\RequiredParams;
use Symfony\Component\HttpFoundation\Response;

/**
 * Downloadable service management.
 */
class Client extends \FOSSBilling\Api\AbstractApi
{
    /**
     * Use GET to call this method. Sends file attached to order.
     * Sends file as attachment.
     */
    #[RequiredParams(['order_id' => 'Order ID is required', 'file_id' => 'File ID is required'])]
    public function send_file($data): Response
    {
        if (empty($data['order_id'])) {
            throw new \FOSSBilling\Exception('Order ID is required');
        }

        $identity = $this->getIdentity();
        $order = $this->getDi()['em']->getRepository(Order::class)->findOneBy(['id' => $data['order_id'], 'clientId' => $identity->getId()]);
        if (!$order instanceof Order) {
            throw new \FOSSBilling\InformationException('Order not found');
        }

        $orderService = $this->getDi()['mod_service']('order');
        $orderService->assertOrderUsable($order);
        $s = $orderService->getOrderService($order);
        if (!$s instanceof ServiceDownloadable || $order->getStatus() !== 'active') {
            throw new \FOSSBilling\Exception('Order is not activated');
        }

        $file = $this->getDi()['em']->getRepository(ServiceDownloadableFile::class)->find((int) $data['file_id']);
        if (!$file instanceof ServiceDownloadableFile) {
            throw new \FOSSBilling\InformationException('File not found');
        }

        $service = $this->getService();

        return $service->sendFile($file);
    }
}
