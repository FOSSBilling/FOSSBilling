<?php
/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Widgets;

use FOSSBilling\Environment;
use FOSSBilling\Exception;
use FOSSBilling\InjectionAwareInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class Service implements InjectionAwareInterface
{
    public const DEFAULT_PRIORITY = 10;

    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getSearchQuery($data): array
    {
        $sql = 'SELECT * FROM widgets';
        $params = [];
        $conditions = [];

        $mod_name = $data['mod'] ?? null;
        $slot = $data['slot'] ?? null;

        if (!empty($mod_name)) {
            $conditions[] = "mod_name = :mod_name";
            $params['mod_name'] = $mod_name;
        }

        if (!empty($slot)) {
            $conditions[] = "slot = :slot";
            $params['slot'] = $slot;
        }

        if ($conditions) $sql .= ' WHERE ' . implode(' AND ', $conditions);

        $sql = $sql . " ORDER BY id DESC";

        return [$sql, $params];
    }

    public function batchConnect(?string $mod_name = null): void
    {
        $mods = [];

        if ($mod_name !== null) {
            $mods[] = $mod_name;
        } else {
            $mods = $this->di['mod_service']('extension')->getCoreAndActiveModules();
        }

        // Clean up the existing list before we add to it
        $this->disconnectUnavailable($mod_name);

        foreach ($mods as $mod_name) {
            $widgets = $this->getModuleWidgets($mod_name);

            foreach ($widgets as $widget) {
                $this->register($mod_name, $widget);
            }
        }
    }

    /**
     * Register a widget (store it in the database).
     * 
     * @param string $mod name of the module
     * @param array $widget a valid widget array
     */
    private function register(string $mod, array $widget): void
    {
        $required = [
            'slot' => 'Slot name must be specified for the widget.',
            'template' => 'Template name must be specified for the widget.',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $widget);

        $priority = $widget['priority'] ?? self::DEFAULT_PRIORITY;

        $q = "SELECT id
            FROM widgets
            WHERE mod_name = :mod
            AND slot = :slot
            AND template = :template";

        $existingId = $this->di['db']->getCell($q, [
            'mod' => $mod,
            'slot' => $widget['slot'],
            'template' => $widget['template']
        ]);

        if (!is_int($priority) || $priority <= 0) {
            throw new Exception('Widget priority must be a positive integer.');
        }

        if ($existingId) {
            // Update existing entry
            $meta = $this->di['db']->load('widgets', $existingId);
            $meta->priority = $priority;
            $meta->updated_at = date('Y-m-d H:i:s');
        } else {
            // Create new entry
            $meta = $this->di['db']->dispense('widgets');
            $meta->mod_name = $mod;
            $meta->slot = $widget['slot'];
            $meta->template = $widget['template'];
            $meta->priority = $priority;
            $meta->created_at = date('Y-m-d H:i:s');
            $meta->updated_at = date('Y-m-d H:i:s');
        }

        $this->di['db']->store($meta);
    }

    /**
     * Render the widgets for a specific slot.
     * 
     * @param string $slot Name of the slot
     * @param array $params Optional context passed by the parent template such as order or product data
     * @return string Final content of the slot
     */
    public function renderSlot(string $slot, array $params = []): string
    {
        $q = "SELECT * FROM widgets WHERE slot = :slot AND enabled = 1 ORDER BY priority ASC";
        $widgets = $this->di['db']->getAll($q, ['slot' => $slot]);

        $systemService = $this->di['mod_service']('System');
        $output = '';

        foreach ($widgets as $widget) {
            // Read the template and render it
            try {
                $template = $this->readTemplateContent($widget['mod_name'], $widget['template']);
                
                $output .= $systemService->renderString($template, false, $params) . '<br />';
            } catch (\Exception $e) {
                $template = $this->readTemplateContent('widgets', 'mod_widgets_error');
                
                $p = ['widget' => $widget];

                if (Environment::isDevelopment()) {
                    $p['error'] = $e->getMessage();
                }
                
                $output .= $systemService->renderString($template, false, $p) . '<br />';
            }
        }

        return $output;
    }

    /**
     * Disconnect widgets that are no longer available or no longer declared in the module.
     *
     * @param string|null $mod_name If specified, only checks widgets from that module.
     * @return void
     */
    private function disconnectUnavailable(?string $mod_name = null): void
    {
        $params = [];
        $sql = "SELECT * FROM widgets";
        
        if ($mod_name !== null) {
            $sql .= " WHERE mod_name = :mod";
            $params['mod'] = $mod_name;
        }

        $dbWidgets = $this->di['db']->getAll($sql, $params);

        $mods = array_values(array_unique(array_column($dbWidgets, 'mod_name')));

        foreach ($mods as $mod) {
            $validWidgetsMap = [];

            try {
                $modWidgets = $this->getModuleWidgets($mod);

                foreach ($modWidgets as $w) {
                    $key = $w['slot'] . ':' . $w['template'];
                    $validWidgetsMap[$key] = true;
                }
                
                foreach ($dbWidgets as $w) {
                    if ($w['mod_name'] !== $mod) continue; // only check the widgets for the relevant module
                    
                    $key = $w['slot'] . ':' . $w['template'];
                    
                    if (!isset($validWidgetsMap[$key])) {
                        $this->di['db']->exec('DELETE FROM widgets WHERE id = :id', ['id' => $w['id']]);
                    }
                }
            } catch (\Exception $e) {
                // If the module is inactive or errors out for some reason, delete all its widgets
                $this->di['db']->exec('DELETE FROM widgets WHERE mod_name = :mod_name', ['mod_name' => $mod]);
            }
        }
    }

    /**
     * Determine if the service class has a getWidgets function.
     * 
     * @param object $service Service class
     * @return bool
     */
    private function canBeConnected($service): bool
    {
        $reflector = new \ReflectionClass($service);
        
        if ($reflector->hasMethod('getWidgets')) {
            $method = $reflector->getMethod('getWidgets');

            // Make sure the method is public and requires no arguments
            if ($method->isPublic() && $method->getNumberOfRequiredParameters() === 0) {
                return true;
            } else {
                throw new Exception("The getWidgets() method in {$reflector->name} must be public and take no required parameters.");
            }
        }

        return false;
    }

    /**
     * Read the content of a template file. Hacky way until our Twig loader is refactored.
     * @param string $mod_name
     * @param string $template
     * @return string
     */
    private function readTemplateContent(string $mod_name, string $template): string
    {
        $filesystem = new Filesystem();
        $service = $this->di['mod_service']('theme');
        
        // Define the template paths
        if (defined('ADMIN_AREA') && ADMIN_AREA === true) {
            $theme = $service->getCurrentAdminAreaTheme();

            $paths = [
                Path::join(PATH_THEMES, $theme['code'], 'html', 'widgets', $template . '.html.twig'),
                Path::join(PATH_MODS, ucfirst($mod_name), 'html_admin', 'widgets', $template . '.html.twig')
            ];
        } else {
            $code = $service->getCurrentClientAreaThemeCode();

            $paths = [
                Path::join(PATH_THEMES, $code, 'html', 'widgets', $template . '.html.twig'),
                Path::join(PATH_MODS, ucfirst($mod_name), 'html_client', 'widgets', $template . '.html.twig')
            ];
        }

        // Read the template
        foreach ($paths as $path) {
            try {
                if ($filesystem->exists($path)) {
                    return $filesystem->readFile($path);
                }
            } catch (IOException $e) {
                // Continue to next path if current path fails to read
                continue;
            }
        }

        throw new Exception("Widget template file not found. Paths checked: \n\n" . implode(",\n", $paths));
    }

    /**
     * Get a module's widgets as an array
     * 
     * @param string $mod_name
     * @return array
     */
    public function getModuleWidgets(string $mod_name): array
    {
        $mod = $this->di['mod']($mod_name);

        if ($mod->hasService()) {
            $service = $mod->getService();
                
            if ($this->canBeConnected($service)) {
                return $service->getWidgets();
            }
        }

        return [];
    }

    /**
     * Connect the widgets of a newly activated module
     * 
     * @param \Box_Event $event
     * @return void
     */
    public static function onAfterAdminActivateExtension(\Box_Event $event): void
    {
        $params = $event->getParameters();

        if (!isset($params['id'])) {
            $event->setReturnValue(false);
        } else {
            $di = $event->getDi();

            $ext = $di['db']->load('extension', $params['id']);
            
            if (is_object($ext) && $ext->type === 'mod') {
                $service = $di['mod_service']('widgets');
                $service->batchConnect($ext->name);
            }
            $event->setReturnValue(true);
        }
    }

    /**
     * Disconnect the widgets of a newly deactivated module
     * 
     * @param \Box_Event $event
     * @return void
     */
    public static function onAfterAdminDeactivateExtension(\Box_Event $event): void
    {
        $di = $event->getDi();
        $params = $event->getParameters();
        
        if ($params['type'] == 'mod') {
            $q = "DELETE FROM widgets WHERE mod_name = :mod";
            
            // A quirk of FOSSBilling: here, "id" refers to the module name,
            // but in the onAfterAdminActivateExtension event, "id" indeed is the numeric module ID.
            // The module name also isn't supplied with the event data, so you need to fetch it from the `extension` table first.
            $di['db']->exec($q, ['mod' => $params['id']]);
        }

        $event->setReturnValue(true);
    }
}
