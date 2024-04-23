<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use FOSSBilling\InjectionAwareInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

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

    /**
     * Returns a list of filters to add to the existing list.
     *
     * @return array An array of filters
     */
    public function getFilters()
    {
        return [
            'trans' => new TwigFilter('trans', '__trans'),

            'alink' => new TwigFilter('alink', $this->twig_bb_admin_link_filter(...), ['is_safe' => ['html']]),
            'link' => new TwigFilter('link', $this->twig_bb_client_link_filter(...), ['is_safe' => ['html']]),
            'autolink' => new TwigFilter('autolink', $this->twig_autolink_filter(...)),

            'gravatar' => new TwigFilter('gravatar', $this->twig_gravatar_filter(...)),

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

            // We override these default twig filters so we can explicitly disable it from calling certain functions that may leak data or allow commands to be executed on the system.
            'filter' => new TwigFilter('filter', $this->filteredFilter(...)),
            'map' => new TwigFilter('map', $this->filteredMap(...)),
            'reduce' => new TwigFilter('reduce', $this->filteredReduce(...)),
            'sort' => new TwigFilter('sort', $this->filteredSort(...)),
        ];
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'bb';
    }

    public function twig_ipcountryname_filter($value)
    {
        if (empty($value)) {
            return '';
        }

        try {
            $record = $this->di['geoip']->country($value);

            return $record->country->name;
        } catch (Exception) {
            return '';
        }
    }

    public function twig_bb_client_link_filter($link, $params = null)
    {
        if ($this->di['url'] === null) {
            return null;
        }

        return $this->di['url']->link($link, $params);
    }

    public function twig_bb_admin_link_filter($link, $params = null)
    {
        return $this->di['url']->adminLink($link, $params);
    }

    public function twig_period_title(Twig\Environment $env, $period)
    {
        $globals = $env->getGlobals();
        $api_guest = $globals['guest'];

        return $api_guest->system_period_title(['code' => $period]);
    }

    public function twig_money_convert(Twig\Environment $env, $price, $currency = null)
    {
        $globals = $env->getGlobals();
        $api_guest = $globals['guest'];
        if (is_null($currency)) {
            $c = $api_guest->cart_get_currency();
            $currency = $c['code'];
        }

        return $api_guest->currency_format(['price' => $price, 'code' => $currency, 'convert' => true]);
    }

    public function money_convert_without_currency(Twig\Environment $env, $price, $currency = null, $without_currency = false)
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

    public function twig_mod_asset_url($asset, $mod)
    {
        return SYSTEM_URL . 'modules/' . ucfirst($mod) . '/assets/' . $asset;
    }

    public function twig_asset_url(Twig\Environment $env, $asset)
    {
        $globals = $env->getGlobals();

        return SYSTEM_URL . 'themes/' . $globals['current_theme'] . '/assets/' . $asset;
    }

    public function twig_library_url($path)
    {
        return SYSTEM_URL . 'library/' . $path;
    }

    public function twig_img_tag($path, $alt = null)
    {
        $alt = is_null($alt) ? pathinfo($path, PATHINFO_BASENAME) : $alt;

        return sprintf('<img src="%s" alt="%s" title="%s"/>', htmlspecialchars($path), htmlspecialchars($alt), htmlspecialchars($alt));
    }

    public function twig_script_tag($path)
    {
        return sprintf('<script type="text/javascript" src="%s?%s"></script>', $path, FOSSBilling\Version::VERSION);
    }

    public function twig_stylesheet_tag($path, $media = 'screen')
    {
        return sprintf('<link rel="stylesheet" type="text/css" href="%s?v=%s" media="%s" />', $path, FOSSBilling\Version::VERSION, $media);
    }

    public function twig_gravatar_filter($email, $size = 20)
    {
        if (empty($email)) {
            return '';
        }

        $url = 'https://www.gravatar.com/avatar/';
        $url .= md5(strtolower(trim($email)));

        return $url . "?s=$size&d=mp&r=g";
    }

    public function twig_autolink_filter($text)
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

        return preg_replace_callback($pattern, $callback, $text);
    }

    public function twig_number_filter($number, $decimals = 2, $dec_point = '.', $thousands_sep = '')
    {
        if (is_null($number)) {
            $number = '0';
        }

        return number_format(floatval($number), $decimals, $dec_point, $thousands_sep);
    }

    public function twig_daysleft_filter($iso8601)
    {
        $timediff = strtotime($iso8601) - time();

        return intval($timediff / 86400);
    }

    public function twig_timeago_filter($iso8601)
    {
        $cur_tm = time();
        $dif = $cur_tm - strtotime($iso8601);
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

    public function twig_size_filter($value)
    {
        $precision = 2;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($value, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= 1024 ** $pow;

        return round($bytes, $precision) . ' ' . $units[$pow];
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
}
