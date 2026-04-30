<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Redirect;

use Box\Mod\Extension\Entity\ExtensionMeta;
use Box\Mod\Extension\Repository\ExtensionMetaRepository;
use FOSSBilling\Exception;

class Service implements \FOSSBilling\InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;
    private ?ExtensionMetaRepository $extensionMetaRepository = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
        $this->extensionMetaRepository = isset($this->di['em'])
            ? $this->di['em']->getRepository(ExtensionMeta::class)
            : null;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getModulePermissions(): array
    {
        return [
            'create_and_edit' => [
                'type' => 'bool',
                'display_name' => __trans('Create and edit redirects'),
                'description' => __trans('Allows the staff member to create and edit redirects.'),
            ],
            'delete' => [
                'type' => 'bool',
                'display_name' => __trans('Delete redirects'),
                'description' => __trans('Allows the staff member to delete redirects.'),
            ],
            'manage_settings' => [],
        ];
    }

    public function getExtensionMetaRepository(): ExtensionMetaRepository
    {
        if ($this->extensionMetaRepository === null) {
            if ($this->di === null) {
                throw new Exception('The dependency injection container has not been set.');
            }

            $this->extensionMetaRepository = $this->di['em']->getRepository(ExtensionMeta::class);
        }

        return $this->extensionMetaRepository;
    }

    public function getRedirects(): array
    {
        $redirects = $this->getExtensionMetaRepository()->findByExtensionAndScope('mod_redirect', null, null, null, ['id' => 'ASC']);

        return array_map($this->toApiArray(...), $redirects);
    }

    public function getRedirectByPath($path): ?string
    {
        $redirect = $this->getExtensionMetaRepository()->findOneByExtensionAndScope('mod_redirect', (string) $path);

        return $redirect?->getMetaValue();
    }

    public function get(int $id): ExtensionMeta
    {
        $redirect = $this->getExtensionMetaRepository()->findOneByExtensionAndId('mod_redirect', $id);
        if (!$redirect instanceof ExtensionMeta) {
            throw new Exception('Redirect not found');
        }

        return $redirect;
    }

    public function create(string $path, string $target): int
    {
        $path = $this->validatePath($path);
        $target = $this->validateTarget($target);

        $redirect = (new ExtensionMeta())
            ->setExtension('mod_redirect')
            ->setMetaKey($path)
            ->setMetaValue($target);

        $this->di['em']->persist($redirect);
        $this->di['em']->flush();

        $id = $redirect->getId();
        if ($id === null) {
            throw new Exception('Failed to create redirect.');
        }

        return $id;
    }

    public function update(ExtensionMeta $redirect, array $data): bool
    {
        if (isset($data['path'])) {
            $redirect->setMetaKey($this->validatePath((string) $data['path']));
        }

        if (isset($data['target'])) {
            $redirect->setMetaValue($this->validateTarget((string) $data['target']));
        }

        $this->di['em']->flush();

        return true;
    }

    public function delete(ExtensionMeta $redirect): bool
    {
        $this->di['em']->remove($redirect);
        $this->di['em']->flush();

        return true;
    }

    public function toApiArray(ExtensionMeta $redirect): array
    {
        return [
            'id' => $redirect->getId(),
            'path' => $redirect->getMetaKey(),
            'target' => $redirect->getMetaValue(),
        ];
    }

    public function validateTarget(string $target): string
    {
        $target = trim($target);

        if ($target === '' || strpbrk($target, "\r\n") !== false) {
            throw new Exception('Invalid redirect target.');
        }

        $scheme = parse_url($target, PHP_URL_SCHEME);
        if (is_string($scheme) && !in_array(strtolower($scheme), ['http', 'https'], true)) {
            throw new Exception('Only HTTP and HTTPS redirect targets are allowed.');
        }

        return $target;
    }

    public function validatePath(string $path): string
    {
        $path = trim($path, '/');

        if ($path === '' || strpbrk($path, "\r\n") !== false) {
            throw new Exception('Invalid redirect path.');
        }

        if (str_contains($path, '..')) {
            throw new Exception('Redirect path must not contain path traversal sequences.');
        }

        return $path;
    }
}
