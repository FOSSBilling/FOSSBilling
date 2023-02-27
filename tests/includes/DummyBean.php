<?php
// Define a dummy class for RedBeanPHP, so test can easily be adjusted for future versions of RedBeanPHP.
class DummyBean extends \RedBeanPHP\OODBBean
{
    function __construct()
    {
        $bean = new \RedBeanPHP\OODBBean();
        $this->__info['changelist'] = array();
        return $bean;
    }
}
