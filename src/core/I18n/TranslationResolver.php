<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace FOSSBilling\Core\I18n;

use PhpMyAdmin\MoTranslator\Loader;
use PhpMyAdmin\MoTranslator\Translator as MoTranslator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * Resolves an explicit module source, then core, then the original English text.
 */
final class TranslationResolver
{
    private static ?Loader $moduleLoader = null;

    public static function translate(string $msgid, ?string $from = null): string
    {
        foreach (self::translators($from) as $translator) {
            if ($translator->exists($msgid)) {
                $translated = $translator->gettext($msgid);
                if ($translated !== '') {
                    return $translated;
                }
            }
        }

        return $msgid;
    }

    public static function translatePlural(string $msgid, string $msgidPlural, int $number, ?string $from = null): string
    {
        foreach (self::translators($from) as $translator) {
            if ($translator->exists($msgid . "\0" . $msgidPlural)) {
                $translated = $translator->ngettext($msgid, $msgidPlural, $number);
                if ($translated !== '') {
                    return $translated;
                }
            }
        }

        return $number === 1 ? $msgid : $msgidPlural;
    }

    /** @return \Generator<int, MoTranslator> */
    private static function translators(?string $from): \Generator
    {
        $core = Loader::getInstance();
        if ($from === null) {
            // Preserve the default domain selected by existing integrations.
            yield $core->getTranslator();

            return;
        }

        if ($from === 'core') {
            yield $core->getTranslator('messages');

            return;
        }

        if (preg_match('/\A[a-z][a-z0-9_-]*\z/', $from) === 1 && defined('PATH_MODS')) {
            $modulePath = Path::join(PATH_MODS, ucfirst($from));
            if ((new Filesystem())->exists($modulePath)) {
                // A separate loader prevents a module named "messages" from
                // rebinding core's domain, and never changes the global locale.
                $modules = self::$moduleLoader ??= new Loader();
                $modules->setlocale($core->setlocale(''));
                $modules->bindtextdomain($from, Path::join($modulePath, 'locale'));

                yield $modules->getTranslator($from);
            } else {
                self::reportUnknownSource($from);
            }
        } else {
            self::reportUnknownSource($from);
        }

        yield $core->getTranslator('messages');
    }

    private static function reportUnknownSource(string $from): void
    {
        if (defined('DEBUG') && DEBUG) {
            error_log('Unknown translation source: ' . json_encode($from));
        }
    }
}
