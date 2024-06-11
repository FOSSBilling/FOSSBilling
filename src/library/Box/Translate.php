<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Box_Translate
{
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
     *
     * @return Box_Translate
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;

        return $this;
    }

    public function setup()
    {
        PhpMyAdmin\MoTranslator\Loader::loadFunctions();

        $locale = $this->getLocale();
        if (empty($locale)) {
            // We are using the standard PHP Exception here rather than our custom one as ours requires translations to be setup, which we cannot do without the locale being defined.
            throw new Exception('Unable to set up FOSSBilling translation functionality, locale was undefined.');
        }

        $codeset = 'UTF-8';
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
     * @return Box_Translate
     */
    public function setDomain($domain)
    {
        $this->domain = $domain;

        return $this;
    }

    public function __($msgid, array $values = null)
    {
        return __trans($msgid, $values);
    }
}
