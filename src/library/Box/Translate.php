<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
if (!function_exists('__trans')) {
    /**
     * Translate a message with optional value substitution.
     *
     * @param string     $msgid  The message to translate
     * @param array|null $values Optional values to substitute in the translated message
     *
     * @return string The translated message
     */
    function __trans(string $msgid, ?array $values = null): string
    {
        $translated = _gettext($msgid);

        if (is_array($values)) {
            $translated = strtr($translated, $values);
        }

        return $translated;
    }
}

if (!function_exists('__pluralTrans')) {
    /**
     * Translate a plural message with optional value substitution.
     *
     * @param string     $msgid       The singular message to translate
     * @param string     $msgidPlural The plural message to translate
     * @param int        $number      The number to determine which form to use
     * @param array|null $values      Optional values to substitute in the translated message
     *
     * @return string The translated message
     */
    function __pluralTrans(string $msgid, string $msgidPlural, int $number, ?array $values = null): string
    {
        $translated = _ngettext($msgid, $msgidPlural, $number);

        if (is_array($values)) {
            $translated = strtr($translated, $values);
        }

        return $translated;
    }
}

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

    public function setup(): void
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

    public function __($msgid, ?array $values = null)
    {
        return __trans($msgid, $values);
    }
}
