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


class Box_Translate implements \Box\InjectionAwareInterface
{
    /**
     * @var \Box_Di
     */
    protected $di = NULL;

    protected $domain = 'messages';

    protected $locale = 'en_US';

    /**
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * @param string $locale
     * @return Box_Translate
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;
        return $this;
    }

    /**
     * @param Box_Di $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return Box_Di
     */
    public function getDi()
    {
        return $this->di;
    }

    public function setup()
    {
        $locale = $this->getLocale();
        $codeset = "UTF-8";
        if(!function_exists('gettext')) {
            require_once BB_PATH_LIBRARY . '/php-gettext/gettext.inc';
            T_setlocale(LC_MESSAGES, $locale.'.'.$codeset);
            T_setlocale(LC_TIME, $locale.'.'.$codeset);
            T_bindtextdomain($this->domain, BB_PATH_LANGS);
            T_bind_textdomain_codeset($this->domain, $codeset);
            T_textdomain($this->domain);
        } else {
            @putenv('LANG='.$locale.'.'.$codeset);
            @putenv('LANGUAGE='.$locale.'.'.$codeset);
            // set locale
            if (!defined('LC_MESSAGES')) define('LC_MESSAGES', 5);
            if (!defined('LC_TIME')) define('LC_TIME', 2);
            setlocale(LC_MESSAGES, $locale.'.'.$codeset);
            setlocale(LC_TIME, $locale.'.'.$codeset);
            bindtextdomain($this->domain, BB_PATH_LANGS);
            if(function_exists('bind_textdomain_codeset')) bind_textdomain_codeset($this->domain, $codeset);
            textdomain($this->domain);

            if (!function_exists('__')) {
                function __($msgid, array $values = NULL)
                {
                    if (empty($msgid)) return null;
                    $string = gettext($msgid);
                    return empty($values) ? $string : strtr($string, $values);
                }
            }
        }
    }

    /**
     * @return string
     */
    public function getDomain()
    {
        return $this->domain;
    }

    /**
     * @param $domain
     * @return Box_Translate
     */
    public function setDomain($domain)
    {
        $this->domain = $domain;
        return $this;
    }

    public function __($msgid, array $values = NULL)
    {
        return __($msgid, $values);
    }
}