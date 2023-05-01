<?php

/**
 * FOSSBilling.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * Copyright FOSSBilling 2022
 * This software may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

namespace Box\Mod\Formbuilder\Controller;

class Client implements \Box\InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

    /**
     * @param \Pimple\Container $di
     * @return void
     */
    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    /**
     * @return \Pimple\Container|null
     */
    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function register(\Box_App &$app)
    {
        $app->get('/formbuilder/:id', 'get_form', ['id' => '[0-9]+'], static::class);
    }

    public function get_form(\Box_App $app, $id)
    {
        return $app->render('mod_formbuilder_build', ['id' => $id]);
    }
}
