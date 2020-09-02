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


class Box_Event implements ArrayAccess, \Box\InjectionAwareInterface
{
    protected $di         = null;
    protected $value      = null;
    protected $processed  = false;
    protected $subject    = null;
    protected $name       = '';
    protected $parameters = null;
    protected $api_guest  = null;
    protected $api_admin  = null;

  /**
   * Constructs a new sfEvent.
   *
   * @param mixed   $subject      The subject
   * @param string  $name         The event name
   * @param array   $parameters   An array of parameters
   */
  public function __construct($subject, $name, $parameters = array(), $api_admin = null, $api_guest = null)
  {
    $this->subject = $subject;
    $this->name = $name;
    
    $this->parameters = $parameters;
    
    $this->api_admin = $api_admin;
    $this->api_guest = $api_guest;
  }

    /**
     * @param Box_Di|null $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return Box_Di|null
     */
    public function getDi()
    {
        return $this->di;
    }

  /**
   * Returns the subject.
   *
   * @return mixed The subject
   */
  public function getSubject()
  {
    return $this->subject;
  }
  
  public function getApiAdmin()
  {
    return $this->api_admin;
  }
  
  public function getApiGuest()
  {
    return $this->api_guest;
  }

  /**
   * Returns the event name.
   *
   * @return string The event name
   */
  public function getName()
  {
    return $this->name;
  }

  /**
   * Sets the return value for this event.
   *
   * @param mixed $value The return value
   */
  public function setReturnValue($value)
  {
    $this->value = $value;
  }

  /**
   * Returns the return value.
   *
   * @return mixed The return value
   */
  public function getReturnValue()
  {
    return $this->value;
  }

  /**
   * Sets the processed flag.
   *
   * @param Boolean $processed The processed flag value
   */
  public function setProcessed($processed)
  {
    $this->processed = (boolean) $processed;
  }

  /**
   * Returns whether the event has been processed by a listener or not.
   *
   * @return Boolean true if the event has been processed, false otherwise
   */
  public function isProcessed()
  {
    return $this->processed;
  }

  /**
   * Returns the event parameters.
   *
   * @return array The event parameters
   */
  public function getParameters()
  {
    return $this->parameters;
  }

  /**
   * Returns true if the parameter exists (implements the ArrayAccess interface).
   *
   * @param  string  $name  The parameter name
   *
   * @return Boolean true if the parameter exists, false otherwise
   */
  public function offsetExists($name)
  {
    return array_key_exists($name, $this->parameters);
  }

  /**
   * Returns a parameter value (implements the ArrayAccess interface).
   *
   * @param  string  $name  The parameter name
   *
   * @return mixed  The parameter value
   */
  public function offsetGet($name)
  {
    if (!array_key_exists($name, $this->parameters))
    {
      throw new InvalidArgumentException(sprintf('The event "%s" has no "%s" parameter.', $this->name, $name));
    }

    return $this->parameters[$name];
  }

  /**
   * Sets a parameter (implements the ArrayAccess interface).
   *
   * @param string  $name   The parameter name
   * @param mixed   $value  The parameter value
   */
  public function offsetSet($name, $value)
  {
    $this->parameters[$name] = $value;
  }

  /**
   * Removes a parameter (implements the ArrayAccess interface).
   *
   * @param string $name    The parameter name
   */
  public function offsetUnset($name)
  {
    unset($this->parameters[$name]);
  }
}
