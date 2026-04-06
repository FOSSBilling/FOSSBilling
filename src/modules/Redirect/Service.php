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

    public function getExtensionMetaRepository(): ExtensionMetaRepository
    {
        if ($this->extensionMetaRepository === null) {
            if ($this->di === null) {
                throw new \FOSSBilling\Exception('The dependency injection container has not been set.');
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
            throw new \FOSSBilling\Exception('Redirect not found');
        }

        return $redirect;
    }

    public function create(string $path, string $target): int
    {
        $redirect = (new ExtensionMeta())
            ->setExtension('mod_redirect')
            ->setMetaKey($path)
            ->setMetaValue($target);

        $this->di['em']->persist($redirect);
        $this->di['em']->flush();

        $id = $redirect->getId();
        if ($id === null) {
            throw new \FOSSBilling\Exception('Failed to create redirect.');
        }

        return $id;
    }

    public function update(ExtensionMeta $redirect, array $data): bool
    {
        $redirect
            ->setMetaKey(trim(htmlspecialchars((string) ($data['path'] ?? $redirect->getMetaKey()), ENT_QUOTES | ENT_HTML5, 'UTF-8'), '/'))
            ->setMetaValue(trim(htmlspecialchars((string) ($data['target'] ?? $redirect->getMetaValue()), ENT_QUOTES | ENT_HTML5, 'UTF-8'), '/'));

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
}
