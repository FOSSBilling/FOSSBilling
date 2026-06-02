<?php

declare(strict_types=1);
/**
 * FOSSBilling.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * Copyright FOSSBilling 2022
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

namespace Box\Mod\Seo\Api;

class Admin extends \Api_Abstract
{
    /**
     * Returns SEO information. When the pings were was last sent.
     *
     * @return array
     */
    public function info($data)
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('seo', 'view');

        return $this->getService()->getInfo();
    }

    /**
     * Ping every search engine to let them know that the sitemap has been updated.
     *
     * @return bool
     */
    public function ping_all()
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('seo', 'manage');

        $extensionService = $this->di['mod_service']('extension');
        $config = $extensionService->getConfig('mod_seo');

        return $this->getService()->pingSitemap($config, true);
    }
}
