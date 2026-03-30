<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Redirect;

class Service implements \FOSSBilling\InjectionAwareInterface
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

    public function getRedirects(): array
    {
        $extensionService = $this->di['mod_service']('extension');
        $redirects = $extensionService->findMeta('mod_redirect', null, null, null, ['id' => 'ASC']);

        return array_map($this->toApiArray(...), $redirects);
    }

    public function getRedirectByPath($path): ?string
    {
        $extensionService = $this->di['mod_service']('extension');
        $redirect = $extensionService->getMeta('mod_redirect', (string) $path);

        return $redirect?->getMetaValue();
    }

    public function get(int $id): \Box\Mod\Extension\Entity\ExtensionMeta
    {
        $extensionService = $this->di['mod_service']('extension');
        $redirect = $extensionService->getMetaById('mod_redirect', $id);
        if ($redirect === null) {
            throw new \FOSSBilling\Exception('Redirect not found');
        }

        return $redirect;
    }

    public function create(string $path, string $target): int
    {
        $extensionService = $this->di['mod_service']('extension');
        $redirect = $extensionService->createMeta('mod_redirect', $path, $target);

        $id = $redirect->getId();
        if ($id === null) {
            throw new \FOSSBilling\Exception('Failed to create redirect.');
        }

        return $id;
    }

    public function update(\Box\Mod\Extension\Entity\ExtensionMeta $redirect, array $data): bool
    {
        $path = trim(htmlspecialchars((string) ($data['path'] ?? $redirect->getMetaKey()), ENT_QUOTES | ENT_HTML5, 'UTF-8'), '/');
        $target = trim(htmlspecialchars((string) ($data['target'] ?? $redirect->getMetaValue()), ENT_QUOTES | ENT_HTML5, 'UTF-8'), '/');

        $extensionService = $this->di['mod_service']('extension');
        $extensionService->updateMeta($redirect, $path, $target);

        return true;
    }

    public function delete(\Box\Mod\Extension\Entity\ExtensionMeta $redirect): bool
    {
        $extensionService = $this->di['mod_service']('extension');
        $extensionService->removeMeta($redirect);

        return true;
    }

    public function toApiArray(\Box\Mod\Extension\Entity\ExtensionMeta $redirect): array
    {
        return [
            'id' => $redirect->getId(),
            'path' => $redirect->getMetaKey(),
            'target' => $redirect->getMetaValue(),
        ];
    }
}
