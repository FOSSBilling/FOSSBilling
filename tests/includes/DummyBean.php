<?php
// Define a dummy class for RedBeanPHP, so test can easily be adjusted for future versions of RedBeanPHP.
class DummyBean extends \RedBeanPHP\OODBBean
{
    function __construct()
    {
        parent::__construct();
        $this->initializeForDispense('dummybean');
    }
}
