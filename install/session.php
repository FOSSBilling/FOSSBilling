<?php

class Session
{
    private $_handler = null;

    public function __construct()
    {
        $this->_handler = Box_SessionFile::getInstance();
    }

    public function getId()
    {
        return $this->_handler->getId();
    }

    public function delete($key)
    {
        return $this->_handler->delete($key);
    }

    public function get($key)
    {
        return $this->_handler->get($key);
    }
    
    public function set($key, $value)
    {
        $this->_handler->set($key, $value);
    }

    public function destroy()
    {
        $this->_handler->destroy();
    }
}

class Box_SessionFile
{
    const SESSION_STARTED       = TRUE;
    const SESSION_NOT_STARTED   = FALSE;

    protected $sessionState = self::SESSION_NOT_STARTED;

    protected static $instance;

    public static function getInstance()
    {
        if ( !isset(self::$instance))
        {
            self::$instance = new self;
            if(!self::$instance->sessionExists() && !headers_sent()) {
                session_name('BOXSID');
                self::$instance->sessionState = session_start();
            }
        }
        return self::$instance;
    }

    public function getId()
    {
        return session_id();
    }

    public function destroy()
    {
        if(self::$instance->sessionExists()) {
            session_destroy();
        }
    }

    public function delete($key)
    {
        if(isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
        return TRUE;
    }

    private function sessionExists()
    {
        if(!isset($_SESSION)) {
            return false;
        }

        if (ini_get('session.use_cookies') == '1' && isset($_COOKIE[session_name()])) {
            return true;
        } elseif ($this->sessionState) {
            return true;
        }

        return false;
    }

    public function get($key)
    {
        return $this->__get($key);
    }

    public function set($key, $value)
    {
        return $this->__set($key, $value);
    }

    public function __get($key)
    {
        return isset($_SESSION[$key]) ? $_SESSION[$key] : NULL;
    }

    public function __set($key, $value)
    {
        $_SESSION[$key] = $value;
    }

    public function __isset( $name )
    {
        return isset($_SESSION[$name]);
    }

    public function __unset( $name )
    {
        unset( $_SESSION[$name] );
    }
}