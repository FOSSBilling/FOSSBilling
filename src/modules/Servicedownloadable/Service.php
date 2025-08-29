<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Servicedownloadable;

use FOSSBilling\Environment;
use FOSSBilling\InjectionAwareInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

class Service implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;
    private readonly Filesystem $filesystem;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    public function attachOrderConfig(\Model_Product $product, array &$data)
    {
        $config = json_decode($product->config ?? '', true) ?? [];
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
        $c = json_decode($order->config ?? '', true);
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

    public function toApiArray(\Model_ServiceDownloadable $model, $deep = false, $identity = null): array
    {
        $productService = $this->di['mod_service']('product');
        $result = [
            'path' => Path::join(PATH_UPLOADS, md5($model->filename)),
            'filename' => $model->filename,
        ];

        if ($identity instanceof \Model_Admin) {
            $result['downloads'] = $model->downloads;
        }

        return $result;
    }

    public function uploadProductFile(\Model_Product $productModel)
    {
        $productService = $this->di['mod_service']('product');
        $request = $this->di['request'];

        if ($request->files->count() == 0) {
            throw new \FOSSBilling\Exception('Error uploading file.');
        }
        $file = $request->files->get('file_data');
        $fileName = $file->getClientOriginalName();
        $fileNameHash = md5($fileName);
        $fileSavePath = PATH_UPLOADS;
        $file->move($fileSavePath, $fileNameHash);

        $config = json_decode($productModel->config ?? '', true) ?? [];

        // Remove old file.
        if (isset($config['filename'])) {
            $oldFilePath = Path::join(PATH_UPLOADS, md5($config['filename']));
            if ($this->filesystem->exists($oldFilePath)) {
                $this->filesystem->remove($oldFilePath);
            }
        }

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
                $oldconfig = json_decode($order['config'] ?? '', true);
                $oldconfig['filename'] = $fileName;

                // Save the change to the DB
                $ordermodel->config = json_encode($oldconfig);
                $ordermodel->updated_at = date('Y-m-d H:i:s');
                $this->di['db']->store($ordermodel);
            }
        }

        $config['filename'] = $fileName;
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

        if ($request->files->count() == 0) {
            throw new \FOSSBilling\Exception('Error uploading file.');
        }
        $file = $request->files->get('file_data');
        $fileName = $file->getClientOriginalName();
        $fileNameHash = md5($fileName);
        $fileSavePath = PATH_UPLOADS;
        $file->move($fileSavePath, $fileNameHash);

        $serviceDownloadable->filename = $fileName;
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

    public function sendFile(\Model_ServiceDownloadable $serviceDownloadable)
    {
        $info = $this->toApiArray($serviceDownloadable);

        $fileName = $info['filename'];
        $filePath = $info['path'];
        if (!$this->filesystem->exists($filePath)) {
            throw new \FOSSBilling\Exception('File cannot be downloaded at the moment. Please contact support.', null, 404);
        }

        // Increase download hit count.
        ++$serviceDownloadable->downloads;
        $serviceDownloadable->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($serviceDownloadable);

        // Send the file for download, unless in testing environment.
        if (!Environment::isTesting()) {
            $response = new Response($this->filesystem->readFile($filePath));

            $disposition = $response->headers->makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                $fileName
            );

            $response->headers->set('Content-Type', 'application/force-download');
            $response->headers->set('Content-Type', 'application/octet-stream');
            $response->headers->set('Content-Type', 'application/download');
            $response->headers->set('Content-Description', 'File Transfer');
            $response->headers->set('Content-Disposition', $disposition);
            $response->headers->set('Content-Transfer-Encoding', 'binary');

            $response->send();
        }

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
