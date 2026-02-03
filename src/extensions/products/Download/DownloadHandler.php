<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\ProductType\Download;

use FOSSBilling\Environment;
use FOSSBilling\Exception;
use FOSSBilling\InjectionAwareInterface;
use FOSSBilling\Interfaces\ProductTypeHandlerInterface;
use FOSSBilling\ProductType\Download\Entity\Download;
use FOSSBilling\ProductType\Download\Repository\DownloadRepository;
use Pimple\Container;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

class DownloadHandler implements ProductTypeHandlerInterface, InjectionAwareInterface
{
    protected ?Container $di = null;
    private ?DownloadRepository $repository = null;
    private readonly Filesystem $filesystem;

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    public function setDi(Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Container
    {
        return $this->di;
    }

    protected function getRepository(): DownloadRepository
    {
        if ($this->repository === null) {
            $this->repository = $this->di['em']->getRepository(Download::class);
        }

        return $this->repository;
    }

    protected function loadEntity(int $id): Download
    {
        $entity = $this->getRepository()->find($id);
        if (!$entity instanceof Download) {
            throw new Exception('Download not found');
        }

        return $entity;
    }

    public function attachOrderConfig(\Model_Product $product, array $data): array
    {
        $config = json_decode($product->config ?? '', true) ?? [];
        $required = [
            'filename' => 'Product is not configured completely.',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $config);

        $data['filename'] = $config['filename'];

        return array_merge($config, $data);
    }

    public function validateOrderData(array &$data): void
    {
        $required = [
            'filename' => 'Filename is missing in product config',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);
    }

    public function create(\Model_ClientOrder $order): Download
    {
        $c = json_decode($order->config ?? '', true);
        if (!is_array($c)) {
            throw new Exception(sprintf('Order #%s config is missing', $order->id));
        }
        $this->validateOrderData($c);

        $download = new Download($order->client_id);
        $download->setFilename($c['filename']);
        $download->setDownloads(0);

        $em = $this->di['em'];
        $em->persist($download);
        $em->flush();

        return $download;
    }

    public function activate(\Model_ClientOrder $order): bool
    {
        return true;
    }

    public function renew(\Model_ClientOrder $order): bool
    {
        return true;
    }

    public function suspend(\Model_ClientOrder $order): bool
    {
        return true;
    }

    public function unsuspend(\Model_ClientOrder $order): bool
    {
        return true;
    }

    public function cancel(\Model_ClientOrder $order): bool
    {
        return true;
    }

    public function uncancel(\Model_ClientOrder $order): bool
    {
        return true;
    }

    public function delete(\Model_ClientOrder $order): void
    {
        $orderService = $this->di['mod_service']('order');
        $service = $orderService->getOrderService($order);

        if ($service instanceof Download) {
            $em = $this->di['em'];
            $em->remove($service);
            $em->flush();
        }
    }

    public function toApiArray(Download $model, $deep = false, $identity = null): array
    {
        $productService = $this->di['mod_service']('product');
        $result = [
            'path' => Path::join(PATH_UPLOADS, md5($model->getFilename())),
            'filename' => $model->getFilename(),
        ];

        if ($identity instanceof \Model_Admin) {
            $result['downloads'] = $model->getDownloads();
        }

        return $result;
    }

    public function uploadProductFile(\Model_Product $productModel): bool
    {
        $productService = $this->di['mod_service']('product');
        $request = $this->di['request'];

        if ($request->files->count() == 0) {
            throw new Exception('File upload failed: no files in request.');
        }
        $file = $request->files->get('file_data');
        $fileName = $file->getClientOriginalName();

        $errorCode = $file->getError();
        if ($errorCode !== UPLOAD_ERR_OK) {
            throw new Exception('File upload failed: ' . $this->errorMessage($errorCode));
        }

        $fileNameHash = md5((string) $fileName);
        $fileSavePath = PATH_UPLOADS;
        $file->move($fileSavePath, $fileNameHash);

        $config = json_decode($productModel->config ?? '', true) ?? [];

        if (isset($config['filename'])) {
            $oldFilePath = Path::join(PATH_UPLOADS, md5((string) $config['filename']));
            if ($this->filesystem->exists($oldFilePath)) {
                $this->filesystem->remove($oldFilePath);
            }
        }

        if (isset($config['update_orders']) && $config['update_orders']) {
            $orderService = $this->di['mod_service']('order');
            $orders = $productService->getOrdersForProduct($productModel);

            foreach ($orders as $order) {
                $ordermodel = $this->di['db']->getExistingModelById('ClientOrder', $order['id']);
                $serviceDownload = $orderService->getOrderService($ordermodel);

                $oldconfig = json_decode($order['config'] ?? '', true);
                $oldconfig['filename'] = $fileName;

                $ordermodel->config = json_encode($oldconfig);
                $ordermodel->updated_at = date('Y-m-d H:i:s');
                $this->di['db']->store($ordermodel);

                if ($serviceDownload instanceof Download) {
                    $this->updateProductFile($serviceDownload, $ordermodel, $fileName);
                }
            }
        }

        $config['filename'] = $fileName;
        $productModel->config = json_encode($config);
        $productModel->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($productModel);

        $this->di['logger']->info('Uploaded new file for product %s', $productModel->id);

        return true;
    }

    public function updateProductFile(Download $serviceDownload, \Model_ClientOrder $order, ?string $filename = null): bool
    {
        $request = $this->di['request'];

        if ($filename !== null) {
            $fileName = $filename;
        } elseif ($request->files->count() > 0) {
            $file = $request->files->get('file_data');
            $fileName = $file->getClientOriginalName();

            $errorCode = $file->getError();
            if ($errorCode !== UPLOAD_ERR_OK) {
                throw new Exception('File upload failed: ' . $this->errorMessage($errorCode));
            }

            $fileNameHash = md5((string) $fileName);
            $fileSavePath = PATH_UPLOADS;
            $file->move($fileSavePath, $fileNameHash);
        } else {
            $fileName = null;
            if (isset($order->config)) {
                $config = json_decode($order->config, true);
                $fileName = $config['filename'] ?? null;
            }
            if (!$fileName && $serviceDownload->getFilename()) {
                $fileName = $serviceDownload->getFilename();
            }
            if (!$fileName) {
                throw new Exception('No filename available for order file update');
            }
        }

        $serviceDownload->setFilename($fileName);

        $em = $this->di['em'];
        $em->persist($serviceDownload);
        $em->flush();

        $this->di['logger']->info('Uploaded new file for order %s', $order->id);

        return true;
    }

    private function errorMessage($errorCode): string
    {
        return match ($errorCode) {
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

    public function sendFile(Download $serviceDownload): bool
    {
        $info = $this->toApiArray($serviceDownload);

        $fileName = $info['filename'];
        $filePath = $info['path'];
        if (!$this->filesystem->exists($filePath)) {
            throw new Exception('File cannot be downloaded at the moment. Please contact support.', null, 404);
        }

        $serviceDownload->setDownloads($serviceDownload->getDownloads() + 1);

        $em = $this->di['em'];
        $em->persist($serviceDownload);
        $em->flush();

        if (!Environment::isTesting()) {
            $response = new Response($this->filesystem->readFile($filePath));

            $disposition = $response->headers->makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                $fileName
            );

            $response->headers->set('Content-Type', 'application/octet-stream');
            $response->headers->set('Content-Disposition', $disposition);
            $response->send();
        }

        $this->di['logger']->info('Downloaded service %s file', $serviceDownload->getId());

        return true;
    }

    public function saveProductConfig(\Model_Product $productModel, $data): bool
    {
        $config = json_decode($productModel->config ?? '', true) ?: [];
        $config['update_orders'] = isset($data['update_orders']) && (bool) $data['update_orders'];
        $productModel->config = json_encode($config);
        $productModel->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($productModel);

        return true;
    }

    public function sendProductFile(\Model_Product $product): bool
    {
        $config = $product->config;
        $config = json_decode($config ?? '', true) ?: [];

        if (!isset($config['filename'])) {
            throw new Exception('No file associated with this product.', null, 404);
        }

        $fileName = $config['filename'];
        $filePath = Path::join(PATH_UPLOADS, md5((string) $fileName));

        if (!$this->filesystem->exists($filePath)) {
            throw new Exception('File cannot be downloaded at the moment. Please contact support.', null, 404);
        }

        if (!Environment::isTesting()) {
            $response = new Response($this->filesystem->readFile($filePath));

            $disposition = $response->headers->makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                $fileName
            );

            $response->headers->set('Content-Type', 'application/octet-stream');
            $response->headers->set('Content-Disposition', $disposition);
            $response->send();
        }

        $this->di['logger']->info('Downloaded product %s file by admin.', $product->id);

        return true;
    }
}
