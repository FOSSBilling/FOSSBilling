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

use FOSSBilling\Exception;
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
                $this->connect($widget);
            }
        }
    }

    /**
     * Determine if the service class has a getWidgets function.
     * 
     * @param $service Service class
     * @return bool
     */
    private function canBeConnected($service)
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
     * Get a module's widgets as an array
     * 
     * @param string $mod_name
     * @return array
     */
    public function getModuleWidgets(string $mod_name): array
    {
        if (empty($mod_name)) throw new Exception("The module name must not be empty.");

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
     * Connect a widget.
     */
    private function connect($widget)
    {
        var_dump($widget);
    }

    /**
     * Disconnect unavailable listeners.
     */
    private function disconnectUnavailable(?string $mod_name)
    {

    }

    /**
     * Connect the widgets of a newly activated module
     * 
     * @param \Box_Event $event
     * @return void
     */
    public static function onAfterAdminActivateExtension(\Box_Event $event)
    {

    }

    /**
     * Disconnect the widgets of a newly deactivated module
     * 
     * @param \Box_Event $event
     * @return void
     */
    public static function onAfterAdminDeactivateExtension(\Box_Event $event)
    {

    }
}
