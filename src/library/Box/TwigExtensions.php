<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use FOSSBilling\InjectionAwareInterface;
use Symfony\Component\Filesystem\Path;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class Box_TwigExtensions extends AbstractExtension implements InjectionAwareInterface
{
    protected ?Pimple\Container $di = null;

    public function setDi(?Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Pimple\Container
    {
        return $this->di;
    }

    private function getLoadedAssets(): array
    {
        if (!$this->di->offsetExists('loaded_assets')) {
            $this->di['loaded_assets'] = [];
        }

        return $this->di['loaded_assets'];
    }

    private function markAssetAsLoaded(string $path): void
    {
        $assets = $this->getLoadedAssets();
        $assets[] = $this->normalizeAssetPath($path);
        $this->di['loaded_assets'] = $assets;
    }

    private function isAssetLoaded(string $path): bool
    {
        $normalizedPath = $this->normalizeAssetPath($path);
        $loadedAssets = $this->getLoadedAssets();

        return in_array($normalizedPath, $loadedAssets, true);
    }

    private function normalizeAssetPath(string $path): string
    {
        $path = trim($path);

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $parsed = parse_url($path);
            $path = $parsed['path'] ?? $path;
        }

        $qPos = strpos($path, '?');
        if ($qPos !== false) {
            $path = substr($path, 0, $qPos);
        }

        return ltrim($path, '/\\');
    }

    /**
     * Returns a list of filters to add to the existing list.
     *
     * @return array An array of filters
     */
    #[Override]
    public function getFilters()
    {
        return [
            'trans' => new TwigFilter('trans', '__trans'),

            'alink' => new TwigFilter('alink', $this->twig_bb_admin_link_filter(...), ['is_safe' => ['html']]),
            'link' => new TwigFilter('link', $this->twig_bb_client_link_filter(...), ['is_safe' => ['html']]),
            'autolink' => new TwigFilter('autolink', $this->twig_autolink_filter(...)),

            'markdown' => new TwigFilter('markdown', $this->twig_markdown_filter(...), ['needs_environment' => true, 'is_safe' => ['html']]),

            'truncate' => new TwigFilter('truncate', $this->twig_truncate_filter(...), ['needs_environment' => true]),

            'timeago' => new TwigFilter('timeago', $this->twig_timeago_filter(...)),
            'daysleft' => new TwigFilter('daysleft', $this->twig_daysleft_filter(...)),

            'size' => new TwigFilter('size', $this->twig_size_filter(...)),

            'ipcountryname' => new TwigFilter('ipcountryname', $this->twig_ipcountryname_filter(...)),

            'number' => new TwigFilter('number', $this->twig_number_filter(...)),

            'period_title' => new TwigFilter('period_title', $this->twig_period_title(...), ['needs_environment' => true, 'is_safe' => ['html']]),

            'img_tag' => new TwigFilter('img_tag', $this->twig_img_tag(...), ['needs_environment' => false, 'is_safe' => ['html']]),
            'script_tag' => new TwigFilter('script_tag', $this->twig_script_tag(...), ['needs_environment' => false, 'is_safe' => ['html']]),
            'stylesheet_tag' => new TwigFilter('stylesheet_tag', $this->twig_stylesheet_tag(...), ['needs_environment' => false, 'is_safe' => ['html']]),
            'mod_asset_url' => new TwigFilter('mod_asset_url', $this->twig_mod_asset_url(...)),
            'asset_url' => new TwigFilter('asset_url', $this->twig_asset_url(...), ['needs_environment' => true, 'is_safe' => ['html']]),
            'library_url' => new TwigFilter('library_url', $this->twig_library_url(...), ['is_safe' => ['html']]),

            'money' => new TwigFilter('money', $this->twig_money(...), ['needs_environment' => true, 'is_safe' => ['html']]),
            'money_without_currency' => new TwigFilter('money_without_currency', $this->twig_money_without_currency(...), ['needs_environment' => true, 'is_safe' => ['html']]),
            'money_convert' => new TwigFilter('money_convert', $this->twig_money_convert(...), ['needs_environment' => true, 'is_safe' => ['html']]),
            'money_convert_without_currency' => new TwigFilter('money_convert_without_currency', $this->money_convert_without_currency(...), ['needs_environment' => true, 'is_safe' => ['html']]),

            'iplookup' => new TwigFilter('iplookup', $this->ipLookupLink(...), ['is_safe' => ['html']]),

            'hash' => new TwigFilter('hash', $this->twig_hash(...)),

            // We override these default twig filters so we can explicitly disable it from calling certain functions that may leak data or allow commands to be executed on the system.
            'filter' => new TwigFilter('filter', $this->filteredFilter(...)),
            'map' => new TwigFilter('map', $this->filteredMap(...)),
            'reduce' => new TwigFilter('reduce', $this->filteredReduce(...)),
            'sort' => new TwigFilter('sort', $this->filteredSort(...)),
        ];
    }

    /**
     * Returns a list of functions to add to the existing list.
     *
     * @return array An array of functions
     */
    #[Override]
    public function getFunctions()
    {
        return [
            new TwigFunction('render_widgets', $this->twig_render_widgets(...), ['needs_environment' => true, 'is_safe' => ['html']]),
            new TwigFunction('svg_sprite', $this->twig_svg_sprite(...), ['needs_environment' => true, 'is_safe' => ['html']]),

            // FOSSBilling API functions
            new TwigFunction('fb_api', $this->fb_api(...), ['is_safe' => ['html']]),
            new TwigFunction('fb_api_form', $this->fb_api_form(...), ['is_safe' => ['html']]),
            new TwigFunction('fb_api_link', $this->fb_api_link(...), ['is_safe' => ['html']]),
        ];
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName(): string
    {
        return 'bb';
    }

    /**
     * Part of the Widgets module. Renders the widgets of a specified slot.
     *
     * @param \Twig\Environment $env     the Twig environment (injected automatically)
     * @param string            $slot    name of the slot
     * @param array             $context optional slot context, such as order or client details
     *
     * @return string slot content
     */
    public function twig_render_widgets(\Twig\Environment $env, string $slot, array $context = []): string
    {
        $widgets = $this->di['mod_service']('Widgets')->getSlotWidgets($slot);

        if (empty($widgets)) {
            return '';
        }

        $output = '';

        foreach ($widgets as $widget) {
            try {
                $templateName = 'widgets/' . $widget['template'] . '.html.twig';
                $output .= $env->render($templateName, $context);
            } catch (\Throwable $e) {
                // Render error widget on failure
                $output .= $env->render('widgets/mod_widgets_error.html.twig', array_merge($context, [
                    'widget' => [
                        'slot' => $slot,
                        'mod_name' => $widget['module'],
                        'template' => $widget['template'],
                    ],
                    'error' => \FOSSBilling\Environment::isDevelopment() ? $e->getMessage() : null,
                ]));
            }
        }

        return $output;
    }

    public function twig_ipcountryname_filter($value)
    {
        if (empty($value)) {
            return '';
        }

        try {
            $record = $this->di['geoip']->country($value);

            return $record->name;
        } catch (Exception) {
            return '';
        }
    }

    public function twig_bb_client_link_filter($link, ?array $params = null)
    {
        if ($this->di['url'] === null) {
            return null;
        }

        return $this->di['url']->link($link, $params);
    }

    public function twig_bb_admin_link_filter($link, ?array $params = null)
    {
        return $this->di['url']->adminLink($link, $params);
    }

    public function twig_period_title(Twig\Environment $env, $period)
    {
        $globals = $env->getGlobals();
        $api_guest = $globals['guest'];

        return $api_guest->system_period_title(['code' => $period]);
    }

    public function twig_money_convert(Twig\Environment $env, $price, ?string $currency = null)
    {
        $globals = $env->getGlobals();
        $api_guest = $globals['guest'];
        if (is_null($currency)) {
            $c = $api_guest->cart_get_currency();
            $currency = $c['code'];
        }

        return $api_guest->currency_format(['price' => $price, 'code' => $currency, 'convert' => true]);
    }

    public function money_convert_without_currency(Twig\Environment $env, $price, ?string $currency = null)
    {
        $globals = $env->getGlobals();
        $api_guest = $globals['guest'];
        if (is_null($currency)) {
            $c = $api_guest->cart_get_currency();
            $currency = $c['code'];
        }

        return $api_guest->currency_format(['price' => $price, 'code' => $currency, 'convert' => true, 'without_currency' => true]);
    }

    public function twig_money(Twig\Environment $env, $price, $currency = null)
    {
        $globals = $env->getGlobals();
        $api_guest = $globals['guest'];

        return $api_guest->currency_format(['price' => $price, 'code' => $currency, 'convert' => false]);
    }

    public function twig_money_without_currency(Twig\Environment $env, $price, $currency = null)
    {
        $globals = $env->getGlobals();
        $api_guest = $globals['guest'];

        return $api_guest->currency_format(['price' => $price, 'code' => $currency, 'convert' => false, 'without_currency' => true]);
    }

    public function twig_mod_asset_url($asset, $mod): string
    {
        return SYSTEM_URL . 'modules/' . ucfirst((string) $mod) . '/assets/' . $asset;
    }

    public function twig_asset_url(Twig\Environment $env, $asset): string
    {
        $globals = $env->getGlobals();

        return SYSTEM_URL . 'themes/' . $globals['current_theme'] . '/assets/' . $asset;
    }

    public function twig_library_url($path): string
    {
        return SYSTEM_URL . 'library/' . $path;
    }

    public function twig_img_tag($path, $alt = null): string
    {
        $alt = is_null($alt) ? pathinfo((string) $path, PATHINFO_BASENAME) : $alt;

        return sprintf('<img src="%s" alt="%s" title="%s"/>', htmlspecialchars((string) $path), htmlspecialchars($alt), htmlspecialchars($alt));
    }

    public function twig_script_tag($path): string
    {
        if ($this->isAssetLoaded($path)) {
            return '';
        }

        $this->markAssetAsLoaded($path);

        return sprintf('<script src="%s?%s"></script>', $path, FOSSBilling\Version::VERSION);
    }

    public function twig_stylesheet_tag($path, $media = 'screen'): string
    {
        if ($this->isAssetLoaded($path)) {
            return '';
        }

        $this->markAssetAsLoaded($path);

        return sprintf('<link rel="stylesheet" type="text/css" href="%s?v=%s" media="%s" />', $path, FOSSBilling\Version::VERSION, $media);
    }

    public function twig_autolink_filter($text): ?string
    {
        $pattern = '#\b(([\w-]+://?|www[.])[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/)))#';

        $callback = function ($matches) {
            $url = array_shift($matches);
            $url_parts = parse_url($url);

            if (!isset($url_parts['scheme'])) {
                $url = 'http://' . $url;
            }

            return sprintf('<a target="_blank" href="%s">%s</a>', $url, $url);
        };

        return preg_replace_callback($pattern, $callback, (string) $text);
    }

    public function twig_number_filter($number, $decimals = 2, $dec_point = '.', $thousands_sep = ''): string
    {
        if (is_null($number)) {
            $number = '0';
        }

        return number_format(floatval($number), $decimals, $dec_point, $thousands_sep);
    }

    public function twig_daysleft_filter($iso8601): int
    {
        $timediff = strtotime((string) $iso8601) - time();

        return intval($timediff / 86400);
    }

    public function twig_timeago_filter($iso8601): string
    {
        $cur_tm = time();
        $dif = $cur_tm - strtotime((string) $iso8601);
        $pds = [__trans('second'), __trans('minute'), __trans('hour'), __trans('day'), __trans('week'), __trans('month'), __trans('year'), __trans('decade')];
        $lngh = [1, 60, 3600, 86400, 604800, 2_630_880, 31_570_560, 315_705_600];
        $no = 0;

        for ($v = sizeof($lngh) - 1; ($v >= 0) && (($no = $dif / $lngh[$v]) <= 1); --$v) {
        }

        if ($v < 0) {
            $v = 0;
        }

        $_tm = $cur_tm - ($dif % $lngh[$v]);

        $no = floor($no);

        if ($no != 1) {
            $pds[$v] .= 's';
        }

        return sprintf('%d %s ', $no, $pds[$v]);
    }

    public function twig_size_filter($value): string
    {
        return FOSSBilling\Tools::humanReadableBytes($value);
    }

    public function twig_markdown_filter(Twig\Environment $env, $value)
    {
        return $this->di['parse_markdown']($value);
    }

    public function twig_truncate_filter(Twig\Environment $env, $value, $length = 30, $preserve = false, $separator = '...')
    {
        mb_internal_encoding('UTF-8');

        if (is_null($value)) {
            $value = '';
        }
        if (mb_strlen($value) > $length) {
            if ($preserve) {
                if (false !== ($breakpoint = mb_strpos($value, ' ', $length))) {
                    $length = $breakpoint;
                }
            }

            return mb_substr($value, 0, $length) . $separator;
        }

        return $value;
    }

    public function twig_hash($value, $algo = 'xxh128'): string
    {
        if (!in_array($algo, hash_algos(), true)) {
            throw new \InvalidArgumentException(sprintf('Hash algorithm "%s" is not supported.', $algo));
        }
        return hash($algo, (string) $value);
    }

    public function filteredFilter($array, $arrow)
    {
        if (!$arrow instanceof Closure) {
            return;
        }

        return array_filter($array, $arrow, \ARRAY_FILTER_USE_BOTH);
    }

    public function filteredMap($array, $arrow)
    {
        if (!$arrow instanceof Closure) {
            return;
        }

        $r = [];
        foreach ($array as $k => $v) {
            $r[$k] = $arrow($v, $k);
        }

        return $r;
    }

    public function filteredReduce($array, $arrow, $initial = null)
    {
        if (!$arrow instanceof Closure) {
            return;
        }

        $accumulator = $initial;
        foreach ($array as $key => $value) {
            $accumulator = $arrow($accumulator, $value, $key);
        }

        return $accumulator;
    }

    public function filteredSort($array, $arrow)
    {
        if (!$arrow instanceof Closure) {
            return;
        }

        if ($array instanceof Traversable) {
            $array = iterator_to_array($array);
        } elseif (!\is_array($array)) {
            return $array;
        }

        if ($arrow instanceof Closure) {
            uasort($array, $arrow);
        } else {
            asort($array);
        }

        return $array;
    }

    public function ipLookupLink(?string $ip): string
    {
        if ($ip === null || $ip === '') {
            return '';
        }

        $link = $this->di['url']->adminLink('security/iplookup', ['ip' => $ip]);

        return "<a href='{$link}' target='_blank' class='iplookuplink'>{$ip}</a>";
    }

    public function twig_svg_sprite(Twig\Environment $env): string
    {
        $globals = $env->getGlobals();
        $themeCode = $globals['theme']['code'] ?? null;

        if ($themeCode === null) {
            return '';
        }

        $spritePath = Path::join(PATH_THEMES, $themeCode, 'assets/build/symbol/icons-sprite.svg');

        if (!file_exists($spritePath)) {
            return '';
        }

        return file_get_contents($spritePath);
    }

    /**
     * Core function for generating data-fb-api attributes.
     *
     * @param array $config API configuration
     *
     * @return string HTML attribute string
     *
     * @throws RuntimeException on invalid configuration
     */
    public function fb_api(array $config): string
    {
        $config = $this->validateFbApiConfig($config);

        try {
            $json = json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $e) {
            error_log('fb_api: failed to encode JSON: ' . $e->getMessage());

            return 'data-fb-api=\'{}\'';
        }

        return 'data-fb-api=\'' . $json . '\'';
    }

    /**
     * Generate data-fb-api attribute for forms, or full <form> tag.
     *
     * Usage - just attribute (backward compatible):
     *   <form method="post" action="{{ 'api/admin/order/update'|link }}" {{ fb_api_form({reload: true}) }}>
     *       ...fields...
     *   </form>
     *
     * Usage - full tag (simplified):
     *   {{ fb_api_form({tag: 'form', action: 'api/admin/order/update'|link, reload: true, content: '...fields...'}) }}
     *
     * @param array $config Config with optional 'tag', 'action', 'content', and other API options
     *
     * @return string HTML attribute string or full <form> tag
     *
     * @throws RuntimeException on invalid configuration
     */
    public function fb_api_form(array $config = []): string
    {
        $tag = $config['tag'] ?? null;
        $content = $config['content'] ?? '';
        $action = $config['action'] ?? null;
        unset($config['tag'], $config['content'], $config['action']);

        $config['type'] = 'form';

        try {
            $json = json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $e) {
            throw new RuntimeException('fb_api_form: failed to encode JSON: ' . $e->getMessage());
        }

        $attr = 'method="post" data-fb-api=\'' . $json . '\'';

        if ($tag === 'form') {
            $actionAttr = $action ? 'action="' . htmlspecialchars((string) $action, ENT_QUOTES, 'UTF-8') . '" ' : '';

            return '<form ' . $actionAttr . $attr . '>' . $content . '</form>';
        }

        return $attr;
    }

    /**
     * Generate data-fb-api attribute for links, or full <a> tag.
     *
     * Usage - just attribute (backward compatible):
     *   <a href="{{ 'api/admin/client/delete'|link({id: client.id}) }}" {{ fb_api_link({reload: true}) }}>Delete</a>
     *
     * Usage - full tag (eliminates duplicate href):
     *   {{ fb_api_link({tag: 'a', href: 'api/admin/client/delete'|link({id: client.id}), reload: true, content: '<svg class="icon"><use xlink:href="#delete"/></svg><span>Delete</span>'}) }}
     *
     * @param array $config Config with optional 'tag', 'href', 'content', and other API options
     *
     * @return string HTML attribute string or full <a> tag
     *
     * @throws RuntimeException on invalid configuration
     */
    public function fb_api_link(array $config): string
    {
        $tag = $config['tag'] ?? null;
        $content = $config['content'] ?? '';
        $href = $config['href'] ?? null;
        unset($config['tag'], $config['content']);

        $config['type'] = 'link';
        $attr = $this->fb_api($config);

        if ($tag === 'a') {
            $hrefAttr = $href ? 'href="' . htmlspecialchars((string) $href, ENT_QUOTES, 'UTF-8') . '" ' : '';

            return '<a ' . $hrefAttr . $attr . '>' . $content . '</a>';
        }

        $hrefAttr = $href ? 'href="' . htmlspecialchars((string) $href, ENT_QUOTES, 'UTF-8') . '" ' : '';

        return $hrefAttr . $attr;
    }

    /**
     * Validate fb_api configuration and normalize values.
     *
     * @param array $config Raw configuration
     *
     * @return array Validated and normalized configuration
     *
     * @throws RuntimeException on invalid options
     */
    private function validateFbApiConfig(array $config): array
    {
        $allowedKeys = ['type', 'href', 'endpoint', 'params', 'message', 'redirect', 'reload', 'modal', 'callback'];

        foreach (array_keys($config) as $key) {
            if (!in_array($key, $allowedKeys, true)) {
                $suggestion = $this->getClosestMatch($key, $allowedKeys);
                $hint = $suggestion ? " Did you mean: '{$suggestion}'?" : '';

                throw new RuntimeException("fb_api: unknown option '{$key}'{$hint}");
            }
        }

        if (isset($config['modal'])) {
            $config['modal'] = $this->validateModalConfig($config['modal']);
        }

        foreach (['href', 'message', 'redirect', 'callback'] as $key) {
            if (isset($config[$key]) && !is_string($config[$key])) {
                throw new RuntimeException(sprintf('fb_api: "%s" must be a string', $key));
            }
        }

        if (isset($config['reload']) && !is_bool($config['reload'])) {
            throw new RuntimeException('fb_api: "reload" must be a boolean');
        }

        if (isset($config['params']) && !is_array($config['params'])) {
            throw new RuntimeException('fb_api: "params" must be an array');
        }

        return $config;
    }

    /**
     * Validate modal configuration.
     *
     * @param array $modal Modal configuration
     *
     * @return array Validated modal configuration
     *
     * @throws RuntimeException on invalid modal options
     */
    private function validateModalConfig(array $modal): array
    {
        $allowedModalKeys = ['type', 'title', 'content', 'button', 'buttonColor', 'label', 'value', 'key'];

        foreach (array_keys($modal) as $key) {
            if (!in_array($key, $allowedModalKeys, true)) {
                throw new RuntimeException("fb_api.modal: unknown option '{$key}'");
            }
        }

        $allowedTypes = ['confirm', 'danger', 'prompt'];
        $modalType = $modal['type'] ?? null;

        if ($modalType === null) {
            throw new RuntimeException("fb_api.modal: 'type' is required");
        }

        if (!in_array($modalType, $allowedTypes, true)) {
            throw new RuntimeException("fb_api.modal: invalid type '{$modalType}'. Allowed: " . implode(', ', $allowedTypes));
        }

        if ($modalType === 'prompt' && !isset($modal['key'])) {
            throw new RuntimeException("fb_api.modal: 'key' is required for 'prompt' type");
        }

        return $modal;
    }

    /**
     * Find closest matching string from a list (for helpful error messages).
     *
     * @param string $input   User input
     * @param array  $options Available options
     *
     * @return string|null Closest match or null
     */
    private function getClosestMatch(string $input, array $options): ?string
    {
        $closest = null;
        $minDistance = PHP_INT_MAX;

        foreach ($options as $option) {
            $distance = levenshtein($input, $option);
            if ($distance < $minDistance && $distance <= 3) {
                $minDistance = $distance;
                $closest = $option;
            }
        }

        return $closest;
    }
}
