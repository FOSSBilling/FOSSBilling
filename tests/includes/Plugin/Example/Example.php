<?php
class Example
{
    private $config = array();
    
    public function __construct(array $options = null)
    {
        $this->config = $options;
    }
    
    public function activate($service, $order, $params = array())
    {
        
    }
    
    public function renew($service, $order, $params = array())
    {
        
    }
    
    public function suspend($service, $order, $params = array())
    {
        
    }
    
    public function unsuspend($service, $order, $params = array())
    {
        
    }
    
    public function cancel($service, $order, $params = array())
    {
        return $this->suspend($service, $order, $params);
    }
    
    public function uncancel($service, $order, $params = array())
    {
        return $this->unsuspend($service, $order, $params);
    }
    
    public function delete($service, $order, $params = array())
    {
        
    }
    
    public function get_config()
    {
        return $this->config;
    }
    
    /**
     * Custom function to return passed params
     * 
     * @param type $service
     * @param type $order
     * @param type $params
     * @return type 
     */
    public function return_params($service, $order, $params = array())
    {
        return $params;
    }
}