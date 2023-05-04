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
 * with this source code in the file LICENSE.
 */

class Box_Translate implements \FOSSBilling\InjectionAwareInterface
{
    protected ?\Pimple\Container $di;

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
     * @param \Pimple\Container $di
     */
    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    /**
     * @return \Pimple\Container
     */
    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function setup()
    {
        PhpMyAdmin\MoTranslator\Loader::loadFunctions();

        $locale = $this->getLocale();
        if(empty($locale)){
            //We are using the standard PHP Exception here rather than our custom one as ours requires translations to be setup, which we cannot do without the locale being defined.
            throw new Exception("Unable to setup FOSSBilling translation functionality, locale was undefined.");
        }

        $codeset = "UTF-8";
        @putenv('LANG=' . $locale . '.' . $codeset);
        @putenv('LANGUAGE=' . $locale . '.' . $codeset);
        // set locale
        if (!defined('LC_MESSAGES')) {
            define('LC_MESSAGES', 5);
        }
        if (!defined('LC_TIME')) {
            define('LC_TIME', 2);
        }
        _setlocale(LC_MESSAGES, $locale . '.' . $codeset);
        _setlocale(LC_TIME, $locale . '.' . $codeset);
        _bindtextdomain($this->domain, PATH_LANGS);
        _bind_textdomain_codeset($this->domain, $codeset);
        _textdomain($this->domain);

        function __trans(string $msgid, array $values = null)
        {
            $translated = _gettext($msgid);

            if (is_array($values)) {
                $translated = strtr($translated, $values);
            }

            return $translated;
        }

        function __pluralTrans(string $msgid, string $msgidPlural, int $number, array $values = null)
        {
            $translated = _ngettext($msgid, $msgidPlural, $number);

            if (is_array($values)) {
                $translated = strtr($translated, $values);
            }

            return $translated;
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
