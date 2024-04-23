<?php

/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Servicedownloadable;

use FOSSBilling\Environment;
use FOSSBilling\InjectionAwareInterface;

class Service implements InjectionAwareInterface
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

    public function attachOrderConfig(\Model_Product $product, array &$data)
    {
        $config = $product->config;
        isset($config) ? $config = json_decode($config, true) : $config = [];
        $required = [
            'filename' => 'Product is not configured completely.',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $config);

        $data['filename'] = $config['filename'];

        return array_merge($config, $data);
    }

    public function validateOrderData(array &$data)
    {
        $required = [
            'filename' => 'Filename is missing in product config',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);
    }

    /**
     * @return \Model_ServiceDownloadable
     */
    public function action_create(\Model_ClientOrder $order)
    {
        $c = json_decode($order->config, 1);
        if (!is_array($c)) {
            throw new \FOSSBilling\Exception(sprintf('Order #%s config is missing', $order->id));
        }
        $this->validateOrderData($c);

        $model = $this->di['db']->dispense('ServiceDownloadable');
        $model->client_id = $order->client_id;
        $model->filename = $c['filename'];
        $model->downloads = 0;
        $model->created_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        return $model;
    }

    public function action_activate(\Model_ClientOrder $order)
    {
        return true;
    }

    /**
     * @todo
     *
     * @return bool
     */
    public function action_renew(\Model_ClientOrder $order)
    {
        return true;
    }

    /**
     * @todo
     *
     * @return bool
     */
    public function action_suspend(\Model_ClientOrder $order)
    {
        return true;
    }

    /**
     * @todo
     *
     * @return bool
     */
    public function action_unsuspend(\Model_ClientOrder $order)
    {
        return true;
    }

    /**
     * @todo
     *
     * @return bool
     */
    public function action_cancel(\Model_ClientOrder $order)
    {
        return true;
    }

    /**
     * @todo
     *
     * @return bool
     */
    public function action_uncancel(\Model_ClientOrder $order)
    {
        return true;
    }

    /**
     * @todo
     *
     * @return void
     */
    public function action_delete(\Model_ClientOrder $order)
    {
        $orderService = $this->di['mod_service']('order');
        $service = $orderService->getOrderService($order);
        if ($service instanceof \Model_ServiceDownloadable) {
            $this->di['db']->trash($service);
        }
    }

    public function hitDownload(\Model_ServiceDownloadable $model)
    {
        ++$model->downloads;
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);
    }

    public function toApiArray(\Model_ServiceDownloadable $model, $deep = false, $identity = null): array
    {
        $productService = $this->di['mod_service']('product');
        $result = [
            'path' => $productService->getSavePath($model->filename),
            'filename' => $model->filename,
        ];

        if ($identity instanceof \Model_Admin) {
            $result['downloads'] = $model->downloads;
        }

        return $result;
    }

    public function uploadProductFile(\Model_Product $productModel)
    {
        $request = $this->di['request'];
        if ($request->hasFiles() == 0) {
            throw new \FOSSBilling\Exception('Error uploading file');
        }
        $files = $request->getUploadedFiles();
        $file = $files[0];

        $productService = $this->di['mod_service']('product');
        move_uploaded_file($file->getRealPath(), $productService->getSavePath($file->getName()));
        // End upload

        if ($productModel->config) {
            $config = json_decode($productModel->config, 1);
        } else {
            $config = [];
        }

        $productService->removeOldFile($config);

        // Check if update_orders is true and update all orders
        if (isset($config['update_orders']) && $config['update_orders']) {
            $orderService = $this->di['mod_service']('order');
            // get all orders with this product
            $orders = $productService->getOrdersForProduct($productModel);

            foreach ($orders as $order) {
                $ordermodel = $this->di['db']->getExistingModelById('ClientOrder', $order['id']);
                $serviceDownloadable = $orderService->getOrderService($ordermodel);
                $this->updateProductFile($serviceDownloadable, $ordermodel);

                // Update the filename
                $oldconfig = json_decode($order['config'], 1);
                $oldconfig['filename'] = $file->getName();

                // Save the change to the DB
                $ordermodel->config = json_encode($oldconfig);
                $ordermodel->updated_at = date('Y-m-d H:i:s');
                $this->di['db']->store($ordermodel);
            }
        }

        $config['filename'] = $file->getName();
        $productModel->config = json_encode($config);
        $productModel->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($productModel);

        $this->di['logger']->info('Uploaded new file for product %s', $productModel->id);

        return true;
    }

    /**
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function updateProductFile(\Model_ServiceDownloadable $serviceDownloadable, \Model_ClientOrder $order)
    {
        $request = $this->di['request'];
        if ($request->hasFiles() == 0) {
            throw new \FOSSBilling\Exception('Error uploading file');
        }
        $productService = $this->di['mod_service']('product');
        $files = $request->getUploadedFiles();
        $file = $files[0];
        move_uploaded_file($file->getRealPath(), $productService->getSavePath($file->getName()));
        // End upload

        $serviceDownloadable->filename = $file->getName();
        $serviceDownloadable->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($serviceDownloadable);

        $this->di['logger']->info('Uploaded new file for order %s', $order->id);

        return true;
    }

    private function _error_message($error_code)
    {
        return match ($error_code) {
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension',
            default => 'Unknown upload error',
        };
    }

    public function sendDownload($filename, $path)
    {
        if (Environment::isTesting()) {
            return;
        }

        header('Content-Type: application/force-download');
        header('Content-Type: application/octet-stream');
        header('Content-Type: application/download');
        header('Content-Description: File Transfer');
        header("Content-Disposition: attachment; filename=$filename" . ';');
        header('Content-Transfer-Encoding: binary');
        readfile($path);
        flush();
    }

    public function sendFile(\Model_ServiceDownloadable $serviceDownloadable)
    {
        $info = $this->toApiArray($serviceDownloadable);
        $filename = $info['filename'];
        $path = $info['path'];
        if (!file_exists($path)) {
            throw new \FOSSBilling\Exception('File cannot be downloaded at the moment. Please contact support', null, 404);
        }
        $this->hitDownload($serviceDownloadable);
        $this->sendDownload($filename, $path);

        $this->di['logger']->info('Downloaded service %s file', $serviceDownloadable->id);

        return true;
    }

    public function saveProductConfig(\Model_Product $productModel, $data)
    {
        $config = [];
        $config['update_orders'] = isset($data['update_orders']) && (bool) $data['update_orders'];
        $productModel->config = json_encode($config);
        $productModel->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($productModel);

        return true;
    }
}
