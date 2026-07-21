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

use Box\Mod\Product\Entity\Product;
use Box\Mod\Servicedownloadable\Entity\ServiceDownloadable;
use Box\Mod\Servicedownloadable\Entity\ServiceDownloadableFile;
use Box\Mod\Servicedownloadable\Repository\ServiceDownloadableFileRepository;
use FOSSBilling\InjectionAwareInterface;
use FOSSBilling\Tools;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

class Service implements InjectionAwareInterface
{
    private const string FILES_CONFIG_KEY = 'files';

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
        $files = $this->validateFileDefinitions($config[self::FILES_CONFIG_KEY] ?? null);
        if ($files === []) {
            throw new \FOSSBilling\Exception('Product is not configured completely.');
        }

        $data[self::FILES_CONFIG_KEY] = $files;

        return array_merge($config, $data);
    }

    public function validateOrderData(array &$data): void
    {
        $data[self::FILES_CONFIG_KEY] = $this->validateFileDefinitions($data[self::FILES_CONFIG_KEY] ?? null);
        if ($data[self::FILES_CONFIG_KEY] === []) {
            throw new \FOSSBilling\Exception('Downloadable files are missing in product config');
        }
    }

    public function action_create(\Model_ClientOrder $order): ServiceDownloadable
    {
        $config = json_decode($order->config ?? '', true);
        if (!is_array($config)) {
            throw new \FOSSBilling\Exception(sprintf('Order #%s config is missing', $order->id));
        }
        $this->validateOrderData($config);

        $service = (new ServiceDownloadable())->setClientId((int) $order->client_id);
        foreach ($config[self::FILES_CONFIG_KEY] as $position => $file) {
            $service->addFile($this->createServiceFile($file, $position));
        }

        $this->di['em']->persist($service);
        $this->di['em']->flush();

        return $service;
    }

    public function action_activate(\Model_ClientOrder $order): bool
    {
        return true;
    }

    /**
     * @todo
     */
    public function action_renew(\Model_ClientOrder $order): bool
    {
        return true;
    }

    /**
     * @todo
     */
    public function action_suspend(\Model_ClientOrder $order): bool
    {
        return true;
    }

    /**
     * @todo
     */
    public function action_unsuspend(\Model_ClientOrder $order): bool
    {
        return true;
    }

    /**
     * @todo
     */
    public function action_cancel(\Model_ClientOrder $order): bool
    {
        return true;
    }

    /**
     * @todo
     */
    public function action_uncancel(\Model_ClientOrder $order): bool
    {
        return true;
    }

    /**
     * @todo
     */
    public function action_delete(\Model_ClientOrder $order): void
    {
        $orderService = $this->di['mod_service']('order');
        $service = $orderService->getOrderService($order);
        if ($service instanceof ServiceDownloadable) {
            $storedFilenames = array_map(
                static fn (ServiceDownloadableFile $file): string => $file->getStoredFilename(),
                $service->getFiles()->toArray(),
            );
            $this->di['em']->remove($service);
            $this->di['em']->flush();

            foreach ($storedFilenames as $storedFilename) {
                $this->removeStoredFileIfOrphaned($storedFilename);
            }
        }
    }

    public function toApiArray(ServiceDownloadable $model, $deep = false, $identity = null): array
    {
        $files = [];
        foreach ($model->getFiles() as $file) {
            $item = [
                'id' => $file->getId(),
                'filename' => $file->getFilename(),
                'label' => $file->getLabel(),
                'description' => $file->getDescription(),
            ];

            if ($identity instanceof \Model_Admin) {
                $item['path'] = $this->getStoredFilePath($file->getStoredFilename());
                $item['downloads'] = $file->getDownloads();
            }
            $files[] = $item;
        }

        return ['files' => $files];
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
        if ($this->getFileRepository()->isStoredFilenameReferenced($storedFilename)) {
            return true;
        }

        $count = (int) $this->di['db']->getCell(
            'SELECT COUNT(*) FROM product WHERE config LIKE :pattern',
            [':pattern' => '%' . $storedFilename . '%']
        );
        if ($count > 0) {
            return true;
        }

        $count = (int) $this->di['db']->getCell(
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

    public function uploadProductFile(Product $productModel, array $data = []): bool
    {
        $file = $this->getUploadedFile();
        $fileDefinition = $this->createUploadedFileDefinition($file, $data);

        $this->di['em']->wrapInTransaction(function () use ($productModel, $fileDefinition): void {
            $config = json_decode($productModel->getConfig() ?? '', true) ?? [];
            $config[self::FILES_CONFIG_KEY] ??= [];
            $config[self::FILES_CONFIG_KEY][] = $fileDefinition;
            $productModel->setConfig(json_encode($config, JSON_THROW_ON_ERROR));
            $productModel->setUpdatedAt(new \DateTime());

            if (Tools::normalizeBoolean($config['update_orders'] ?? false)) {
                $this->addFileToExistingOrders($productModel, $fileDefinition);
            }
        });
        $this->di['logger']->info('Uploaded new file for product %s', $productModel->getId());

        return true;
    }

    public function updateProductFile(Product $product, array $data): bool
    {
        $fileKey = $this->validateFileKey($data['file_id'] ?? null);
        $config = json_decode($product->getConfig() ?? '', true) ?: [];
        $index = $this->findFileDefinitionIndex($config[self::FILES_CONFIG_KEY] ?? [], $fileKey);
        $config[self::FILES_CONFIG_KEY][$index]['label'] = $this->normalizeLabel($data['label'] ?? null);
        $config[self::FILES_CONFIG_KEY][$index]['description'] = $this->normalizeDescription($data['description'] ?? null);

        $this->di['em']->wrapInTransaction(function () use ($product, $config, $index): void {
            $product->setConfig(json_encode($config, JSON_THROW_ON_ERROR));

            if (Tools::normalizeBoolean($config['update_orders'] ?? false)) {
                $this->updateFileInExistingOrders($product, $config[self::FILES_CONFIG_KEY][$index]);
            }
        });

        return true;
    }

    public function removeProductFile(Product $product, mixed $fileKey): bool
    {
        $fileKey = $this->validateFileKey($fileKey);
        $config = json_decode($product->getConfig() ?? '', true) ?: [];
        $index = $this->findFileDefinitionIndex($config[self::FILES_CONFIG_KEY] ?? [], $fileKey);
        $storedFilename = $config[self::FILES_CONFIG_KEY][$index]['stored_filename'];
        array_splice($config[self::FILES_CONFIG_KEY], $index, 1);

        $this->di['em']->wrapInTransaction(function () use ($product, $config, $fileKey): void {
            $product->setConfig(json_encode($config, JSON_THROW_ON_ERROR));

            if (Tools::normalizeBoolean($config['update_orders'] ?? false)) {
                $this->removeFileFromExistingOrders($product, $fileKey);
            }
        });
        $this->removeStoredFileIfOrphaned($storedFilename);

        return true;
    }

    public function uploadOrderFile(ServiceDownloadable $service, \Model_ClientOrder $order, array $data = []): bool
    {
        $file = $this->getUploadedFile();
        $definition = $this->createUploadedFileDefinition($file, $data);
        $this->di['em']->wrapInTransaction(function () use ($service, $order, $definition): void {
            $service->addFile($this->createServiceFile($definition, $service->getFiles()->count()));

            $config = json_decode($order->config ?? '', true) ?: [];
            $config[self::FILES_CONFIG_KEY] ??= [];
            $config[self::FILES_CONFIG_KEY][] = $definition;
            $this->saveOrderConfig($order, $config);
        });

        return true;
    }

    public function removeOrderFile(ServiceDownloadable $service, \Model_ClientOrder $order, int $fileId): bool
    {
        $file = $service->findFileById($fileId);
        if (!$file instanceof ServiceDownloadableFile) {
            throw new \FOSSBilling\InformationException('File not found');
        }

        $storedFilename = $file->getStoredFilename();
        $fileKey = $file->getFileKey();
        $this->di['em']->wrapInTransaction(function () use ($service, $order, $file, $fileKey): void {
            $service->removeFile($file);
            $config = json_decode($order->config ?? '', true) ?: [];
            $config[self::FILES_CONFIG_KEY] = array_values(array_filter(
                $config[self::FILES_CONFIG_KEY] ?? [],
                static fn (array $definition): bool => ($definition['id'] ?? null) !== $fileKey,
            ));
            $this->saveOrderConfig($order, $config);
        });
        $this->removeStoredFileIfOrphaned($storedFilename);

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

    public function sendFile(ServiceDownloadableFile $file, bool $countDownload = true): Response
    {
        $filePath = $this->getStoredFilePath($file->getStoredFilename());
        if (!$this->filesystem->exists($filePath)) {
            throw new \FOSSBilling\Exception('File cannot be downloaded at the moment. Please contact support.', null, 404);
        }

        if ($countDownload) {
            $file->incrementDownloads();
            $this->di['em']->flush();
        }

        $response = new BinaryFileResponse($filePath);

        $disposition = $response->headers->makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $file->getFilename()
        );

        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->headers->set('Content-Disposition', $disposition);

        $this->di['logger']->info('Downloaded service file %s', $file->getId());

        return $response;
    }

    public function saveProductConfig(Product $productModel, $data): bool
    {
        $config = json_decode($productModel->getConfig() ?? '', true) ?: [];
        $config['update_orders'] = Tools::normalizeBoolean($data['update_orders'] ?? false);
        $updatedAt = new \DateTime();
        $productModel->setConfig(json_encode($config, JSON_THROW_ON_ERROR));
        $productModel->setUpdatedAt($updatedAt);
        $this->di['em']->flush();

        return true;
    }

    public function sendProductFile(Product $product, mixed $fileKey): Response
    {
        $config = json_decode($product->getConfig() ?? '', true) ?: [];
        $fileKey = $this->validateFileKey($fileKey);
        $index = $this->findFileDefinitionIndex($config[self::FILES_CONFIG_KEY] ?? [], $fileKey);
        $definition = $config[self::FILES_CONFIG_KEY][$index];
        $filePath = $this->getStoredFilePath($definition['stored_filename']);

        if (!$this->filesystem->exists($filePath)) {
            throw new \FOSSBilling\Exception('File cannot be downloaded at the moment. Please contact support.', null, 404);
        }

        $response = new BinaryFileResponse($filePath);

        $disposition = $response->headers->makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $definition['filename']
        );

        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->headers->set('Content-Disposition', $disposition);

        $this->di['logger']->info('Downloaded product %s file by admin.', $product->getId());

        return $response;
    }

    private function getUploadedFile(): \Symfony\Component\HttpFoundation\File\UploadedFile
    {
        $file = $this->di['request']->files->get('file_data');
        if (!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            throw new \FOSSBilling\Exception('File upload failed: no files in request.');
        }

        $errorCode = $file->getError();
        if ($errorCode !== UPLOAD_ERR_OK) {
            throw new \FOSSBilling\Exception('File upload failed: ' . $this->_error_message($errorCode));
        }
        $this->validateFileUpload($file);

        return $file;
    }

    private function createUploadedFileDefinition(\Symfony\Component\HttpFoundation\File\UploadedFile $file, array $data): array
    {
        $filename = $this->validateDisplayFilename($file->getClientOriginalName());
        $label = $this->normalizeLabel($data['label'] ?? null);
        $description = $this->normalizeDescription($data['description'] ?? null);

        return [
            'id' => bin2hex(random_bytes(16)),
            'filename' => $filename,
            'stored_filename' => $this->storeUploadedFile($file),
            'label' => $label,
            'description' => $description,
        ];
    }

    private function createServiceFile(array $definition, int $sortOrder): ServiceDownloadableFile
    {
        $file = new ServiceDownloadableFile(
            $this->validateFileKey($definition['id'] ?? null),
            $this->validateDisplayFilename($definition['filename'] ?? null),
            $this->validateStoredFilename($definition['stored_filename'] ?? null),
        );

        return $file
            ->setLabel($this->normalizeLabel($definition['label'] ?? null))
            ->setDescription($this->normalizeDescription($definition['description'] ?? null))
            ->setSortOrder($sortOrder);
    }

    private function validateFileDefinitions(mixed $files): array
    {
        if (!is_array($files)) {
            return [];
        }

        $validated = [];
        $fileKeys = [];
        foreach ($files as $file) {
            if (!is_array($file)) {
                throw new \FOSSBilling\Exception('Downloadable file configuration is invalid');
            }
            $fileKey = $this->validateFileKey($file['id'] ?? null);
            if (isset($fileKeys[$fileKey])) {
                throw new \FOSSBilling\Exception('Downloadable file configuration contains duplicate file IDs');
            }
            $fileKeys[$fileKey] = true;
            $validated[] = [
                'id' => $fileKey,
                'filename' => $this->validateDisplayFilename($file['filename'] ?? null),
                'stored_filename' => $this->validateStoredFilename($file['stored_filename'] ?? null),
                'label' => $this->normalizeLabel($file['label'] ?? null),
                'description' => $this->normalizeDescription($file['description'] ?? null),
            ];
        }

        return $validated;
    }

    private function validateFileKey(mixed $fileKey): string
    {
        if (!is_string($fileKey) || preg_match('/\A[a-f0-9]{32}\z/', $fileKey) !== 1) {
            throw new \FOSSBilling\InformationException('File not found');
        }

        return $fileKey;
    }

    private function normalizeOptionalText(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function normalizeLabel(mixed $value): ?string
    {
        $label = $this->normalizeOptionalText($value);
        if ($label !== null && mb_strlen($label) > 255) {
            throw new \FOSSBilling\Exception('File label cannot exceed 255 characters');
        }

        return $label;
    }

    private function normalizeDescription(mixed $value): ?string
    {
        $description = $this->normalizeOptionalText($value);
        if ($description !== null && mb_strlen($description) > 1000) {
            throw new \FOSSBilling\Exception('File description cannot exceed 1000 characters');
        }

        return $description;
    }

    private function validateDisplayFilename(mixed $filename): string
    {
        if (!is_string($filename) || $filename === '' || mb_strlen($filename) > 255) {
            throw new \FOSSBilling\Exception('Downloadable filename is invalid');
        }

        return $filename;
    }

    private function findFileDefinitionIndex(array $files, string $fileKey): int
    {
        foreach ($files as $index => $file) {
            if (($file['id'] ?? null) === $fileKey) {
                return $index;
            }
        }

        throw new \FOSSBilling\InformationException('File not found');
    }

    private function addFileToExistingOrders(Product $product, array $definition): void
    {
        $this->forEachProductOrder($product, function (\Model_ClientOrder $order, ?ServiceDownloadable $service) use ($definition): void {
            $config = json_decode($order->config ?? '', true) ?: [];
            $config[self::FILES_CONFIG_KEY] ??= [];
            $config[self::FILES_CONFIG_KEY][] = $definition;
            $this->saveOrderConfig($order, $config);
            $service?->addFile($this->createServiceFile($definition, $service->getFiles()->count()));
        });
    }

    private function updateFileInExistingOrders(Product $product, array $definition): void
    {
        $this->forEachProductOrder($product, function (\Model_ClientOrder $order, ?ServiceDownloadable $service) use ($definition): void {
            $config = json_decode($order->config ?? '', true) ?: [];
            foreach ($config[self::FILES_CONFIG_KEY] ?? [] as $index => $file) {
                if (($file['id'] ?? null) === $definition['id']) {
                    $config[self::FILES_CONFIG_KEY][$index] = $definition;
                }
            }
            $this->saveOrderConfig($order, $config);

            $serviceFile = $service?->findFileByKey($definition['id']);
            if ($serviceFile instanceof ServiceDownloadableFile) {
                $serviceFile
                    ->setLabel($definition['label'])
                    ->setDescription($definition['description']);
            }
        });
    }

    private function removeFileFromExistingOrders(Product $product, string $fileKey): void
    {
        $this->forEachProductOrder($product, function (\Model_ClientOrder $order, ?ServiceDownloadable $service) use ($fileKey): void {
            $config = json_decode($order->config ?? '', true) ?: [];
            $config[self::FILES_CONFIG_KEY] = array_values(array_filter(
                $config[self::FILES_CONFIG_KEY] ?? [],
                static fn (array $file): bool => ($file['id'] ?? null) !== $fileKey,
            ));
            $this->saveOrderConfig($order, $config);

            $serviceFile = $service?->findFileByKey($fileKey);
            if ($serviceFile instanceof ServiceDownloadableFile) {
                $service->removeFile($serviceFile);
            }
        });
    }

    private function forEachProductOrder(Product $product, callable $callback): void
    {
        $productService = $this->di['mod_service']('product');
        $orderService = $this->di['mod_service']('order');
        foreach ($productService->getOrdersForProduct($product) as $orderData) {
            $order = $this->di['db']->getExistingModelById('ClientOrder', $orderData['id']);
            $service = $orderService->getOrderService($order);
            $callback($order, $service instanceof ServiceDownloadable ? $service : null);
        }
    }

    private function saveOrderConfig(\Model_ClientOrder $order, array $config): void
    {
        $order->config = json_encode($config, JSON_THROW_ON_ERROR);
        $order->updated_at = date('Y-m-d H:i:s');
        $this->di['em']->getConnection()->update('client_order', [
            'config' => $order->config,
            'updated_at' => $order->updated_at,
        ], ['id' => $order->id]);
    }

    private function getFileRepository(): ServiceDownloadableFileRepository
    {
        return $this->di['em']->getRepository(ServiceDownloadableFile::class);
    }
}
