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

class Box_BeanHelper extends \RedBeanPHP\BeanHelper\SimpleFacadeBeanHelper implements \Box\InjectionAwareInterface
{
    protected $di;

    /**
     * @param mixed $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return mixed
     */
    public function getDi()
    {
        return $this->di;
    }

    public function getModelForBean( \RedBeanPHP\OODBBean $bean )
    {
        $prefix    = '\\Model_';
        $model     = $bean->getMeta( 'type' );
        $modelName = $prefix.$this->underscoreToCamelCase($model);

        if ( !class_exists( $modelName ) ) {
            return null;
        }

        $model = new $modelName();
        if($model instanceof \Box\InjectionAwareInterface) {
            $model->setDi( $this->di );
        }

        $model->loadBean( $bean );

        return $model;
    }

    private function underscoreToCamelCase( $string, $first_char_caps = true)
    {
        if( $first_char_caps === true )
        {
            $string[0] = strtoupper($string[0]);
        }
        $func = function($c){ return strtoupper($c[1]); };
        return preg_replace_callback('/_([a-z])/', $func, $string);
    }
} 