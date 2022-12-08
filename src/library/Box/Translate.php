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
        PhpMyAdmin\MoTranslator\Loader::loadFunctions();

        $locale = $this->getLocale();
        $codeset = "UTF-8";
        @putenv('LANG='.$locale.'.'.$codeset);
        @putenv('LANGUAGE='.$locale.'.'.$codeset);
        // set locale
        if (!defined('LC_MESSAGES')) {
            define('LC_MESSAGES', 5);
        }
        if (!defined('LC_TIME')) {
            define('LC_TIME', 2);
        }
        _setlocale(LC_MESSAGES, $locale.'.'.$codeset);
        _textdomain(LC_TIME, $locale.'.'.$codeset);
        _bindtextdomain($this->domain, PATH_LANGS);
        _bind_textdomain_codeset($this->domain, $codeset);
        _textdomain($this->domain);
        
        function __trans($msgid, array $values = NULL)
        {
            if (empty($msgid)) {
                return null;
            }
            if (is_null($values)){
                return _gettext($msgid);
            } else {
                $string = strtr($msgid, $values);
                return _gettext($string);
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
        return __trans($msgid, $values);
    }
}