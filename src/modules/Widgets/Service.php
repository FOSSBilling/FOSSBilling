<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Widgets;

use FOSSBilling\InjectionAwareInterface;
use FOSSBilling\Interfaces\WidgetProviderInterface;
use Symfony\Contracts\Cache\ItemInterface;

class Service implements InjectionAwareInterface
{
    public const DEFAULT_PRIORITY = 10;
    public const CACHE_KEY = 'widgets_registry';

    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    /**
     * Get the widget registry from cache, rebuild if necessary.
     *
     * @return array<string, array<int, array{module: string, template: string, priority: int}>>
     */
    public function getRegistry(): array
    {
        return $this->di['cache']->get(self::CACHE_KEY, function (ItemInterface $item) {
            $item->expiresAfter(null); // No need to expire. We will manually invalidate on module activation/deactivation

            return $this->buildRegistry();
        });
    }

    /**
     * Build the widget registry by scanning all active modules.
     *
     * @return array<string, array<int, array{module: string, template: string, priority: int}>>
     */
    public function buildRegistry(): array
    {
        $registry = [];
        $modules = $this->di['mod_service']('Extension')->getCoreAndActiveModules();

        foreach ($modules as $modName) {
            try {
                $widgets = $this->getModuleWidgets($modName);

                foreach ($widgets as $widget) {
                    if (!isset($widget['slot']) || !isset($widget['template'])) {
                        continue; // Skip invalid widget definitions
                    }

                    $slot = $widget['slot'];
                    $priority = $widget['priority'] ?? self::DEFAULT_PRIORITY;

                    if (!is_int($priority) || $priority <= 0) {
                        $priority = self::DEFAULT_PRIORITY;
                    }

                    $registry[$slot][] = [
                        'module' => $modName,
                        'template' => $widget['template'],
                        'priority' => $priority,
                    ];
                }
            } catch (\Throwable $e) {
                // Log the error but continue with other modules
                $this->di['logger']->err("Error loading widgets from module '{$modName}': " . $e->getMessage());
            }
        }

        // Sort each slot's widgets by priority (ascending)
        foreach ($registry as &$widgets) {
            usort($widgets, fn ($a, $b): int => $a['priority'] <=> $b['priority']);
        }

        return $registry;
    }

    /**
     * Invalidate the widget cache, forcing a rebuild on next access.
     */
    public function invalidateCache(): void
    {
        $this->di['cache']->delete(self::CACHE_KEY);
    }

    /**
     * Get widgets for a specific slot.
     *
     * Slot naming convention: {area}.{namespace}.{location}[.{position}]
     * - area: 'admin' or 'client'
     * - namespace: 'theme' for layout slots, or module name for module-specific slots
     * - location: where the slot appears (e.g., 'footer', 'sidebar', 'details')
     * - position: optional refinement (e.g., 'start', 'end', 'before', 'after')
     *
     * Examples:
     * - 'client.theme.footer.end' - End of footer in client theme layout
     * - 'admin.theme.sidebar.top' - Top of sidebar in admin theme layout
     * - 'client.invoice.details.after' - After invoice details in Invoice module
     *
     * @param string $slot name of the slot (e.g., 'client.theme.footer.end')
     *
     * @return array<int, array{module: string, template: string, priority: int}> widgets for the slot
     */
    public function getSlotWidgets(string $slot): array
    {
        $registry = $this->getRegistry();

        return $registry[$slot] ?? [];
    }

    /**
     * Get a module's widget definitions.
     *
     * @param string $modName the module name
     *
     * @return array list of widget definitions
     */
    public function getModuleWidgets(string $modName): array
    {
        $mod = $this->di['mod']($modName);

        if ($mod->hasService()) {
            $service = $mod->getService();

            if ($this->serviceProvidesWidgets($service)) {
                return $service->getWidgets();
            }
        }

        return [];
    }

    /**
     * Check if a service class provides widgets.
     *
     * @param object $service the service class instance
     *
     * @return bool true if the service implements WidgetProviderInterface
     */
    private function serviceProvidesWidgets(object $service): bool
    {
        return $service instanceof WidgetProviderInterface;
    }

    /**
     * Get all widgets grouped by slot (for admin listing).
     *
     * @return array list of slots with their widgets
     */
    public function getWidgetList(): array
    {
        $registry = $this->getRegistry();
        $result = [];

        foreach ($registry as $slot => $widgets) {
            $result[] = [
                'slot' => $slot,
                'widgets' => $widgets,
                'count' => count($widgets),
            ];
        }

        // Sort by slot name for consistent display
        usort($result, fn ($a, $b): int => strcmp($a['slot'], $b['slot']));

        return $result;
    }

    /**
     * Event handler: Invalidate cache when a module is activated.
     */
    public static function onAfterAdminActivateExtension(\Box_Event $event): void
    {
        $params = $event->getParameters();

        if (isset($params['id'])) {
            $di = $event->getDi();
            $ext = $di['db']->load('extension', $params['id']);

            if (is_object($ext) && $ext->type === 'mod') {
                $di['mod_service']('Widgets')->invalidateCache();
            }
        }

        $event->setReturnValue(true);
    }

    /**
     * Event handler: Invalidate cache when a module is deactivated.
     */
    public static function onAfterAdminDeactivateExtension(\Box_Event $event): void
    {
        $params = $event->getParameters();

        if (($params['type'] ?? null) === 'mod') {
            $di = $event->getDi();
            $di['mod_service']('Widgets')->invalidateCache();
        }

        $event->setReturnValue(true);
    }
}
