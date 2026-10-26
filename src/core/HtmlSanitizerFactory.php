<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Core;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

/**
 * Central factory for HtmlSanitizerConfig and cached HtmlSanitizer instances.
 *
 * Every config is built from a shared hardened base (allowSafeElements + URL
 * schemes + unlimited input length). Configs are immutable (cloned on mutation)
 * and safe to share.
 *
 * The factory also lazily caches the corresponding HtmlSanitizer per config
 * so callers never front-load sanitizers into the DI container and never
 * allocate a DOM parser per sanitize() call. Prefer the get*Sanitizer()
 * accessors over `new HtmlSanitizer(Factory::create*Config())` when a shared
 * instance is appropriate (most cases).
 */
final class HtmlSanitizerFactory
{
    private const array ALLOWED_LINK_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    private const array ALLOWED_MEDIA_SCHEMES = ['http', 'https'];

    /** @var array<string, HtmlSanitizerInterface> */
    private static array $sanitizers = [];

    /**
     * Hardened base: W3C safe elements/attributes only, no relative URLs by
     * default, no truncation. Every named config MUST start from this.
     */
    public static function createBaseConfig(): HtmlSanitizerConfig
    {
        return (new HtmlSanitizerConfig())
            ->allowSafeElements()
            ->allowLinkSchemes(self::ALLOWED_LINK_SCHEMES)
            ->allowMediaSchemes(self::ALLOWED_MEDIA_SCHEMES)
            ->withMaxInputLength(-1);
    }

    /**
     * General HTML for email signatures, announcement content_html, etc.
     * Minimal extension over safe: <a href/title>, <code>, <pre>, and
     * forced rel/noopener for external link safety. No relative URLs.
     */
    public static function createContentConfig(): HtmlSanitizerConfig
    {
        return self::createBaseConfig()
            ->allowElement('a', ['href', 'title'])
            ->allowElement('code')
            ->allowElement('pre')
            ->forceAttribute('a', 'rel', 'noopener noreferrer');
    }

    /**
     * Markdown-rendered HTML. league/commonmark already escapes raw HTML
     * (html_input=>escape) and blocks javascript: links; this is defense-in-depth.
     * Allows relative links/medias so internal docs (/kb/123) and relative images
     * produced by GFM survive sanitization.
     */
    public static function createMarkdownConfig(): HtmlSanitizerConfig
    {
        return self::createContentConfig()
            ->allowRelativeLinks()
            ->allowRelativeMedias();
    }

    /**
     * Payment/registrar adapter fragments rendered inside a sandboxed Twig env.
     * Needs relative URL support (adapters often emit form actions, img src).
     */
    public static function createAdapterConfig(): HtmlSanitizerConfig
    {
        return self::createBaseConfig()
            ->allowRelativeLinks()
            ->allowRelativeMedias();
    }

    /**
     * Theme settings page fragments. Extends the adapter config with form
     * elements (input/select/option/optgroup/textarea) and the minimal
     * presentational attributes those fragments need. Uses allowAttribute
     * (subtractive) rather than allowElement('*') so unsafe attributes
     * (style, on*, …) remain stripped per W3CReference safe set.
     */
    public static function createThemeSettingsConfig(): HtmlSanitizerConfig
    {
        $config = self::createAdapterConfig()
            ->allowElement('input', [
                'type', 'name', 'value', 'id', 'checked', 'placeholder', 'accept',
                'multiple', 'min', 'max', 'step', 'readonly', 'disabled', 'required',
                'autocomplete', 'size', 'maxlength', 'minlength', 'pattern',
            ])
            ->allowElement('select', [
                'name', 'id', 'multiple', 'disabled', 'required', 'size',
            ])
            ->allowElement('option', [
                'value', 'selected', 'disabled', 'label',
            ])
            ->allowElement('optgroup', [
                'label', 'disabled',
            ])
            ->allowElement('textarea', [
                'name', 'id', 'rows', 'cols', 'placeholder', 'readonly', 'disabled',
                'required', 'maxlength', 'minlength', 'wrap',
            ]);

        // Presentational attributes needed by theme fragments — applied to all
        // currently-allowed elements without re-enabling unsafe ones.
        // allowAttribute is subtractive: it keeps the attribute only on the
        // listed elements and strips it elsewhere, so we add each separately
        // to the wildcard set via a second pass of allowElement was not viable.
        // Instead, re-allow the safe presentational attributes via allowAttribute('*').
        $config = $config
            ->allowAttribute('class', '*')
            ->allowAttribute('id', '*')
            ->allowAttribute('title', '*')
            ->allowAttribute('for', ['label']);

        return $config;
    }

    public static function getContentSanitizer(): HtmlSanitizerInterface
    {
        return self::$sanitizers['content'] ??= new HtmlSanitizer(self::createContentConfig());
    }

    public static function getMarkdownSanitizer(): HtmlSanitizerInterface
    {
        return self::$sanitizers['markdown'] ??= new HtmlSanitizer(self::createMarkdownConfig());
    }

    public static function getAdapterSanitizer(): HtmlSanitizerInterface
    {
        return self::$sanitizers['adapter'] ??= new HtmlSanitizer(self::createAdapterConfig());
    }

    public static function getThemeSettingsSanitizer(): HtmlSanitizerInterface
    {
        return self::$sanitizers['theme_settings'] ??= new HtmlSanitizer(self::createThemeSettingsConfig());
    }

    /**
     * @param 'content'|'markdown'|'adapter'|'theme_settings' $context
     */
    public static function getSanitizer(string $context = 'content'): HtmlSanitizerInterface
    {
        return match ($context) {
            'markdown' => self::getMarkdownSanitizer(),
            'adapter' => self::getAdapterSanitizer(),
            'theme_settings' => self::getThemeSettingsSanitizer(),
            default => self::getContentSanitizer(),
        };
    }

    /**
     * Sanitize general content for storage.
     *
     * Text-only callers pass $allowHtml = false: the content is HTML-encoded so no
     * markup survives, since the HTML sanitizer incorrectly strips text that follows
     * unrecognized tags like <foo>.
     *
     * @param string $content   The content to sanitize. If empty, returns an empty string.
     * @param bool   $allowHtml Whether to allow safe HTML tags (default: true for rich content)
     */
    public static function sanitizeContent(string $content = '', bool $allowHtml = true): string
    {
        if ($content === '') {
            return '';
        }

        $content = str_replace("\0", '', $content);

        if (!$allowHtml) {
            // Strip all HTML tags for plain text
            return htmlspecialchars(self::sanitizePlainText($content), ENT_QUOTES | ENT_HTML5, 'UTF-8', false);
        }

        return trim(self::getSanitizer('content')->sanitize($content));
    }

    /**
     * Sanitize plain text for storage without HTML-encoding it.
     *
     * Output contexts must still escape the returned value. Encoding during input would store HTML
     * entities as data and cause them to be displayed literally when the output is escaped again.
     */
    public static function sanitizePlainText(string $content = ''): string
    {
        return trim(strip_tags(str_replace("\0", '', $content)));
    }

    /**
     * Sanitize Markdown content for storage.
     *
     * Unlike sanitizeContent(), this does NOT run an HTML sanitizer because the content is Markdown,
     * not HTML. Running an HTML sanitizer on Markdown corrupts it: characters like < and > that users
     * type as literal text get misinterpreted as unknown HTML elements and dropped together with all
     * subsequent text.
     *
     * XSS protection for Markdown content is handled at render time by the markdown_to_html Twig
     * filter, which is configured with html_input=escape so any raw HTML in the Markdown is safely
     * escaped rather than executed.
     *
     * @param string $content the Markdown content to sanitize
     */
    public static function sanitizeMarkdownContent(string $content = ''): string
    {
        if ($content === '') {
            return '';
        }

        return trim(str_replace("\0", '', $content));
    }

    /**
     * Convenience wrapper that mirrors Symfony's sanitize() but centralizes
     * the null-byte strip and trim that Symfony handles as `�` (visible) while
     * FOSSBilling historically strips silently to `''`. Keeps the 7 HTML call
     * sites from duplicating `str_replace("\0",'',…)` + `trim()`.
     *
     * @param 'content'|'markdown'|'adapter'|'theme_settings' $context
     */
    public static function sanitize(string $html, string $context = 'content'): string
    {
        if ($html === '') {
            return '';
        }

        return trim(self::getSanitizer($context)->sanitize(str_replace("\0", '', $html)));
    }
}
