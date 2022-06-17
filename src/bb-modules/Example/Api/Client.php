<?php
/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This file may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

/**
 * All public methods in this class are exposed to the client using API.
 * Always think about the information you are exposing.
 */

namespace Box\Mod\Example\Api;

class Client extends \Api_Abstract
{
    /**
     * From client API you can call any other module API.
     *
     * This method will collect data from all APIs and merge
     * into one result.
     *
     * Be careful not to expose sensitive data from the Admin API.
     */
    public function get_info($data)
    {
        // call custom event hook. All active modules will be notified
        $this->di['events_manager']->fire(['event' => 'onAfterClientCalledExampleModule', 'params' => ['key' => 'value']]);

        // Log message
        $this->di['logger']->info('Log message to log file');

        $systemService = $this->di['mod_service']('System');
        $clientService = $this->di['mod_service']('Client');

        $type = $this->di['array_get']($data, 'type', 'info');

        return [
            'data' => $data,
            'version' => $systemService->getVersion(),
            'profile' => $clientService->toApiArray($this->di['loggedin_client']),
            'messages' => $systemService->getMessages($type),
        ];
    }
}
