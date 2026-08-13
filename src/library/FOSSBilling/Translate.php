<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

class Translate
{
    private string $domain = 'messages';

    private string $locale = 'en_US';

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    public function setup(): void
    {
        \PhpMyAdmin\MoTranslator\Loader::loadFunctions();

        if ($this->locale === '') {
            // Our translated exception cannot be used until the locale is configured.
            throw new \Exception('Unable to set up FOSSBilling translation functionality, locale was undefined.');
        }

        \Locale::setDefault($this->locale);

        $codeset = 'UTF-8';
        if (!defined('LC_MESSAGES')) {
            define('LC_MESSAGES', 5);
        }
        if (!defined('LC_TIME')) {
            define('LC_TIME', 2);
        }

        \_setlocale(LC_MESSAGES, $this->locale . '.' . $codeset);
        \_setlocale(LC_TIME, $this->locale . '.' . $codeset);
        \_bindtextdomain($this->domain, PATH_LANGS);
        \_bind_textdomain_codeset($this->domain, $codeset);
        \_textdomain($this->domain);
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): self
    {
        $this->domain = $domain;

        return $this;
    }
}
