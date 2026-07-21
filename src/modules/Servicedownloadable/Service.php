<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Servicedownloadable;

use Box\Mod\Order\Entity\Order;
use Box\Mod\Product\Entity\Product;
use Box\Mod\Servicedownloadable\Entity\ServiceDownloadable;
use Box\Mod\Servicedownloadable\Repository\ServiceDownloadableRepository;
use Box\Mod\Staff\Entity\Admin;
use FOSSBilling\InjectionAwareInterface;
use FOSSBilling\Tools;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

class Service implements InjectionAwareInterface
{
    private const string STORED_FILENAME_CONFIG_KEY = 'stored_filename';

    private const array DEFAULT_ALLOWED_EXTENSIONS = [
        'zip', 'tar', 'gz', 'tgz', 'bz2', 'xz', 'rar', '7z',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'txt', 'csv', 'xml', 'json', 'yml', 'yaml', 'sql',
        'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp',
        'mp3', 'wav', 'ogg', 'mp4', 'm4v', 'mov', 'avi', 'mkv', 'webm',
        'exe', 'msi', 'dmg', 'pkg', 'deb', 'rpm', 'apk', 'ipa',
        'jar', 'war', 'ear', 'iso', 'bin', 'img',
    ];

    private const array DEFAULT_ALLOWED_MIME_TYPES = [
        'application/octet-stream',
        'application/zip',
        'application/x-zip-compressed',
        'application/x-tar',
        'application/gzip',
        'application/x-gzip',
        'application/x-bzip2',
        'application/x-xz',
        'application/x-rar-compressed',
        'application/x-7z-compressed',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/json',
        'application/xml',
        'application/sql',
        'application/x-msdownload',
        'application/vnd.microsoft.portable-executable',
        'application/x-apple-diskimage',
        'application/vnd.android.package-archive',
        'application/java-archive',
        'application/x-iso9660-image',
        'image/png',
        'image/jpeg',
        'image/gif',
        'image/svg+xml',
        'image/webp',
        'audio/mpeg',
        'audio/wav',
        'audio/ogg',
        'video/mp4',
        'video/quicktime',
        'video/x-msvideo',
        'video/x-matroska',
        'video/webm',
        'text/plain',
        'text/csv',
        'text/xml',
        'text/yaml',
    ];

    protected ?\Pimple\Container $di = null;
    private Filesystem $filesystem;

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

    public function getModulePermissions(): array
    {
        return [
            'manage' => [
                'type' => 'bool',
                'display_name' => __trans('Manage downloadable products'),
                'description' => __trans('Allows the staff member to upload, update, and manage downloadable product files.'),
            ],
        ];
    }

    public function getServiceDownloadableRepository(): ServiceDownloadableRepository
    {
        return $this->di['em']->getRepository(ServiceDownloadable::class);
    }

    private function getAllowedFileTypes(): array
    {
        return [
            'extensions' => self::DEFAULT_ALLOWED_EXTENSIONS,
            'mime_types' => self::DEFAULT_ALLOWED_MIME_TYPES,
        ];
    }

    private function validateFileUpload(\Symfony\Component\HttpFoundation\File\UploadedFile $file): void
    {
        $allowedTypes = $this->getAllowedFileTypes();

        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = strtolower((string) $file->getMimeType());

        if (!in_array($extension, $allowedTypes['extensions'], true)) {
            throw new \FOSSBilling\Exception('File extension :ext is not allowed. Allowed extensions: :allowed', [':ext' => $extension, ':allowed' => implode(', ', $allowedTypes['extensions'])]);
        }

        if (!$this->isAllowedMimeType($mimeType, $allowedTypes['mime_types']) && $this->di->offsetExists('logger')) {
            $this->di['logger']->warning(
                'Accepting downloadable upload %s with unexpected MIME type %s because the extension %s is allowed',
                $file->getClientOriginalName(),
                $mimeType,
                $extension
            );
        }
    }

    private function isAllowedMimeType(string $mimeType, array $allowedMimeTypes): bool
    {
        return $mimeType === '' || $mimeType === 'application/octet-stream' || in_array($mimeType, $allowedMimeTypes, true);
    }

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    public function attachOrderConfig(Product $product, array &$data): array
    {
        $config = json_decode($product->getConfig() ?? '', true) ?? [];
        $required = [
            'filename' => 'Product is not configured completely.',
            self::STORED_FILENAME_CONFIG_KEY => 'Product is not configured completely.',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $config);

        $data['filename'] = $config['filename'];
        $data[self::STORED_FILENAME_CONFIG_KEY] = $this->validateStoredFilename($config[self::STORED_FILENAME_CONFIG_KEY] ?? null);

        return array_merge($config, $data);
    }

    public function validateOrderData(array &$data): void
    {
        $required = [
            'filename' => 'Filename is missing in product config',
            self::STORED_FILENAME_CONFIG_KEY => 'Stored filename is missing in product config',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);
        $data[self::STORED_FILENAME_CONFIG_KEY] = $this->validateStoredFilename($data[self::STORED_FILENAME_CONFIG_KEY] ?? null);
    }

    /**
     * @return ServiceDownloadable
     */
    public function action_create(Order $order)
    {
        $c = json_decode($order->getConfig() ?? '', true);
        if (!is_array($c)) {
            throw new \FOSSBilling\Exception(sprintf('Order #%s config is missing', $order->getId()));
        }
        $this->validateOrderData($c);

        $model = new ServiceDownloadable();
        $model->setClientId($order->getClientId());
        $model->setFilename($c['filename']);
        $model->setStoredFilename($c[self::STORED_FILENAME_CONFIG_KEY]);
        $model->setDownloads(0);

        $this->di['em']->persist($model);
        $this->di['em']->flush();

        return $model;
    }

    public function action_activate(Order $order): bool
    {
        return true;
    }

    /**
     * @todo
     */
    public function action_renew(Order $order): bool
    {
        return true;
    }

    /**
     * @todo
     */
    public function action_suspend(Order $order): bool
    {
        return true;
    }

    /**
     * @todo
     */
    public function action_unsuspend(Order $order): bool
    {
        return true;
    }

    /**
     * @todo
     */
    public function action_cancel(Order $order): bool
    {
        return true;
    }

    /**
     * @todo
     */
    public function action_uncancel(Order $order): bool
    {
        return true;
    }

    /**
     * @todo
     */
    public function action_delete(Order $order): void
    {
        $orderService = $this->di['mod_service']('order');
        $service = $orderService->getOrderService($order);
        if ($service instanceof ServiceDownloadable) {
            $this->di['em']->remove($service);
            $this->di['em']->flush();
        }
    }

    public function toApiArray(ServiceDownloadable $model, $deep = false, $identity = null): array
    {
        $result = [
            'filename' => $model instanceof ServiceDownloadable ? $model->getFilename() : $model->getFilename(),
        ];

        if ($identity instanceof Admin) {
            $result['path'] = $this->getStoredFilePath(
                $model instanceof ServiceDownloadable ? $model->getStoredFilename() : $model->getStoredFilename()
            );
            $result['downloads'] = $model instanceof ServiceDownloadable ? $model->getDownloads() : $model->getDownloads();
        }

        return $result;
    }

    private function validateStoredFilename(mixed $storedFilename): string
    {
        if (!is_string($storedFilename) || preg_match('/\A[a-f0-9]{64}\z/', $storedFilename) !== 1) {
            throw new \FOSSBilling\Exception('File is not available at the moment. Please contact support.', null, 404);
        }

        return $storedFilename;
    }

    private function getStoredFilePath(mixed $storedFilename): string
    {
        return Path::join(PATH_UPLOADS, $this->validateStoredFilename($storedFilename));
    }

    private function generateStoredFilename(): string
    {
        do {
            $storedFilename = bin2hex(random_bytes(32));
            $filePath = $this->getStoredFilePath($storedFilename);
        } while ($this->filesystem->exists($filePath));

        return $storedFilename;
    }

    private function storeUploadedFile(\Symfony\Component\HttpFoundation\File\UploadedFile $file): string
    {
        $storedFilename = $this->generateStoredFilename();
        $file->move(PATH_UPLOADS, $storedFilename);

        return $storedFilename;
    }

    private function isStoredFilenameReferenced(string $storedFilename): bool
    {
        $count = (int) $this->di['em']->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM service_downloadable WHERE stored_filename = :stored_filename',
            [':stored_filename' => $storedFilename]
        );
        if ($count > 0) {
            return true;
        }

        $count = (int) $this->di['em']->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM product WHERE config LIKE :pattern',
            [':pattern' => '%' . $storedFilename . '%']
        );
        if ($count > 0) {
            return true;
        }

        $count = (int) $this->di['em']->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM client_order WHERE config LIKE :pattern',
            [':pattern' => '%' . $storedFilename . '%']
        );

        return $count > 0;
    }

    private function isValidStoredFilename(mixed $storedFilename): bool
    {
        return is_string($storedFilename) && preg_match('/\A[a-f0-9]{64}\z/', $storedFilename) === 1;
    }

    private function removeStoredFileIfOrphaned(string $storedFilename): void
    {
        if ($this->isValidStoredFilename($storedFilename) && !$this->isStoredFilenameReferenced($storedFilename)) {
            $filePath = $this->getStoredFilePath($storedFilename);
            if ($this->filesystem->exists($filePath)) {
                $this->filesystem->remove($filePath);
            }
        }
    }

    public function uploadProductFile(Product $productModel): bool
    {
        $productService = $this->di['mod_service']('product');
        $request = $this->di['request'];

        if ($request->files->count() == 0) {
            throw new \FOSSBilling\Exception('File upload failed: no files in request.');
        }
        $file = $request->files->get('file_data');
        $fileName = $file->getClientOriginalName();

        $errorCode = $file->getError();
        if ($errorCode !== UPLOAD_ERR_OK) {
            throw new \FOSSBilling\Exception('File upload failed: ' . $this->_error_message($errorCode));
        }

        $this->validateFileUpload($file);

        $storedFilename = $this->storeUploadedFile($file);

        $config = json_decode($productModel->getConfig() ?? '', true) ?? [];
        $oldStoredFilename = $config[self::STORED_FILENAME_CONFIG_KEY] ?? null;

        if (isset($config['update_orders']) && $config['update_orders']) {
            $orderService = $this->di['mod_service']('order');
            $orders = $productService->getOrdersForProduct($productModel);

            foreach ($orders as $order) {
                $ordermodel = $this->di['em']->getRepository(\Box\Mod\Order\Entity\Order::class)->find($order['id']);
                $serviceDownloadable = $orderService->getOrderService($ordermodel);

                $oldconfig = json_decode($order['config'] ?? '', true) ?: [];
                $oldconfig['filename'] = $fileName;
                $oldconfig[self::STORED_FILENAME_CONFIG_KEY] = $storedFilename;
                $ordermodel->config = json_encode($oldconfig);

                $this->updateProductFile($serviceDownloadable, $ordermodel, $fileName, $storedFilename);
            }
        }

        $config['filename'] = $fileName;
        $config[self::STORED_FILENAME_CONFIG_KEY] = $storedFilename;
        $updatedAt = new \DateTime();
        $productModel->setConfig(json_encode($config));
        $productModel->setUpdatedAt($updatedAt);
        $this->di['em']->flush();

        $this->di['logger']->info('Uploaded new file for product %s', $productModel->getId());

        if ($oldStoredFilename !== null && $oldStoredFilename !== $storedFilename) {
            $this->removeStoredFileIfOrphaned($oldStoredFilename);
        }

        return true;
    }

    /**
     * @throws \FOSSBilling\Exception
     */
    public function updateProductFile(ServiceDownloadable $serviceDownloadable, Order $order, ?string $filename = null, ?string $storedFilename = null): bool
    {
        $request = $this->di['request'];
        $oldStoredFilename = $serviceDownloadable instanceof ServiceDownloadable ? $serviceDownloadable->getStoredFilename() : $serviceDownloadable->getStoredFilename();

        if ($filename !== null) {
            $fileName = $filename;
            if ($storedFilename === null) {
                throw new \FOSSBilling\Exception('No stored filename available for order file update');
            }
        } elseif ($request->files->count() > 0) {
            $file = $request->files->get('file_data');
            $fileName = $file->getClientOriginalName();

            $errorCode = $file->getError();
            if ($errorCode !== UPLOAD_ERR_OK) {
                throw new \FOSSBilling\Exception('File upload failed: ' . $this->_error_message($errorCode));
            }

            $this->validateFileUpload($file);
            $storedFilename = $this->storeUploadedFile($file);
        } else {
            $fileName = null;
            if ($order->getConfig() !== null) {
                $config = json_decode($order->getConfig(), true);
                $fileName = $config['filename'] ?? null;
                $storedFilename = $config[self::STORED_FILENAME_CONFIG_KEY] ?? null;
            }
            if (!$fileName && $serviceDownloadable->getFilename() !== null) {
                $fileName = $serviceDownloadable instanceof ServiceDownloadable ? $serviceDownloadable->getFilename() : $serviceDownloadable->getFilename();
            }
            if (!$fileName) {
                throw new \FOSSBilling\Exception('No filename available for order file update');
            }
            if (!$storedFilename && $serviceDownloadable->getStoredFilename() !== null) {
                $storedFilename = $serviceDownloadable instanceof ServiceDownloadable ? $serviceDownloadable->getStoredFilename() : $serviceDownloadable->getStoredFilename();
            }
            if (!$storedFilename) {
                throw new \FOSSBilling\Exception('No stored filename available for order file update');
            }
        }

        $storedFilename = $this->validateStoredFilename($storedFilename);

        if ($serviceDownloadable instanceof ServiceDownloadable) {
            $serviceDownloadable->setFilename($fileName);
            $serviceDownloadable->setStoredFilename($storedFilename);
            $serviceDownloadable->setUpdatedAt(new \DateTime());
        } else {
            $serviceDownloadable->filename = $fileName;
            $serviceDownloadable->stored_filename = $storedFilename;
            $serviceDownloadable->updated_at = date('Y-m-d H:i:s');
        }
        $this->di['em']->persist($serviceDownloadable);

        $config = json_decode($order->getConfig() ?? '', true) ?: [];
        $config['filename'] = $fileName;
        $config[self::STORED_FILENAME_CONFIG_KEY] = $storedFilename;
        $order->config = json_encode($config);
        $order->updated_at = date('Y-m-d H:i:s');
        $this->di['em']->persist($order);

        $this->di['em']->flush();

        $this->di['logger']->info('Uploaded new file for order %s', $order->getId());

        if (isset($oldStoredFilename) && $oldStoredFilename !== $storedFilename) {
            $this->removeStoredFileIfOrphaned($oldStoredFilename);
        }

        return true;
    }

    private function _error_message($error_code): string
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

    public function sendFile(ServiceDownloadable $serviceDownloadable): Response
    {
        $fileName = $serviceDownloadable instanceof ServiceDownloadable ? $serviceDownloadable->getFilename() : $serviceDownloadable->getFilename();
        $storedFilename = $serviceDownloadable instanceof ServiceDownloadable ? $serviceDownloadable->getStoredFilename() : $serviceDownloadable->getStoredFilename();
        if (!$storedFilename) {
            throw new \FOSSBilling\Exception('File cannot be downloaded at the moment. Please contact support.', null, 404);
        }

        $filePath = $this->getStoredFilePath($storedFilename);
        if (!$this->filesystem->exists($filePath)) {
            throw new \FOSSBilling\Exception('File cannot be downloaded at the moment. Please contact support.', null, 404);
        }

        if ($serviceDownloadable instanceof ServiceDownloadable) {
            $serviceDownloadable->setDownloads(($serviceDownloadable->getDownloads() ?? 0) + 1);
            $serviceDownloadable->setUpdatedAt(new \DateTime());
        } else {
            ++$serviceDownloadable->downloads;
            $serviceDownloadable->updated_at = date('Y-m-d H:i:s');
        }
        $this->di['em']->persist($serviceDownloadable);
        $this->di['em']->flush();

        $response = new BinaryFileResponse($filePath);

        $disposition = $response->headers->makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $fileName
        );

        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->headers->set('Content-Disposition', $disposition);

        $this->di['logger']->info('Downloaded service %s file', $serviceDownloadable instanceof ServiceDownloadable ? $serviceDownloadable->getId() : $serviceDownloadable->getId());

        return $response;
    }

    public function saveProductConfig(Product $productModel, $data): bool
    {
        $config = json_decode($productModel->getConfig() ?? '', true) ?: [];
        $config['update_orders'] = Tools::normalizeBoolean($data['update_orders'] ?? false);
        $updatedAt = new \DateTime();
        $productModel->setConfig(json_encode($config));
        $productModel->setUpdatedAt($updatedAt);
        $this->di['em']->flush();

        return true;
    }

    /**
     * Sends the file associated with a product for download.
     *
     * @throws \FOSSBilling\Exception
     */
    public function sendProductFile(Product $product): Response
    {
        $config = json_decode($product->getConfig() ?? '', true) ?: [];

        if (!isset($config['filename'], $config[self::STORED_FILENAME_CONFIG_KEY])) {
            throw new \FOSSBilling\Exception('No file associated with this product.', null, 404);
        }

        $fileName = $config['filename'];
        $filePath = $this->getStoredFilePath($config[self::STORED_FILENAME_CONFIG_KEY]);

        if (!$this->filesystem->exists($filePath)) {
            throw new \FOSSBilling\Exception('File cannot be downloaded at the moment. Please contact support.', null, 404);
        }

        $response = new BinaryFileResponse($filePath);

        $disposition = $response->headers->makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $fileName
        );

        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->headers->set('Content-Disposition', $disposition);

        $this->di['logger']->info('Downloaded product %s file by admin.', $product->getId());

        return $response;
    }
}
