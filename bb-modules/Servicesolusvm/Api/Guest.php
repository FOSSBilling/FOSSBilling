<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (http://www.boxbilling.com)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

namespace Box\Mod\Servicesolusvm\Api;
/**
 * Solusvm service management
 */
class Guest extends \Api_Abstract
{
    /**
     * Return operating system templates available on solusvm master server
     * @param string $type - virtualization type
     * @return array 
     */
    public function get_templates($data)
    {
        try {
            $type = $this->di['array_get']($data, 'type', 'openvz');
            $templates = $this->getService()->getTemplates($type);
        } catch (\Exception $exc) {
            $templates = array();
            if(BB_DEBUG) error_log($exc);
        }
        
        return $templates;
    }
}