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

use Box\InjectionAwareInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use League\CommonMark\GithubFlavoredMarkdownConverter;

class Box_TwigExtensions extends AbstractExtension implements InjectionAwareInterface
{
    protected $di;

    /**
     * @param mixed $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return mixed
     */
    public function getDi()
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
            /**
             * 'trans' filter rewrite same filter from Symfony\Bridge\Twig\Extension\TranslationExtension
             * for compatible with outdated php-gettext library.
             *
             * TODO: Use Symfony\Component\Translation\Loader\MoFileLoader and remove php-gettext hardcoded library.
             */
            'trans' => new TwigFilter('trans', '__trans'),

            'alink' => new TwigFilter('alink', [$this, 'twig_bb_admin_link_filter'], ['is_safe' => ['html']]),

            'link' => new TwigFilter('link', [$this, 'twig_bb_client_link_filter'], ['is_safe' => ['html']]),

            'gravatar' => new TwigFilter('gravatar', [$this, 'twig_gravatar_filter']),

            'markdown' => new TwigFilter('markdown', [$this, 'twig_markdown_filter'], ['needs_environment' => true, 'is_safe' => ['html']]),

            'truncate' => new TwigFilter('truncate', [$this, 'twig_truncate_filter'], ['needs_environment' => true]),

            'timeago' => new TwigFilter('timeago', [$this, 'twig_timeago_filter']),

            'daysleft' => new TwigFilter('daysleft', [$this, 'twig_daysleft_filter']),

            'size' => new TwigFilter('size', [$this, 'twig_size_filter']),

            'ipcountryname' => new TwigFilter('ipcountryname', [$this, 'twig_ipcountryname_filter']),

            'number' => new TwigFilter('number', [$this, 'twig_number_filter']),

            'period_title' => new TwigFilter('period_title', [$this, 'twig_period_title'], ['needs_environment' => true, 'is_safe' => ['html']]),

            'autolink' => new TwigFilter('autolink', [$this, 'twig_autolink_filter']),

            'bb_date' => new TwigFilter('bb_date', [$this, 'twig_bb_date']),

            'bb_datetime' => new TwigFilter('bb_datetime', [$this, 'twig_bb_datetime']),

            'img_tag' => new TwigFilter('img_tag', [$this, 'twig_img_tag'], ['needs_environment' => false, 'is_safe' => ['html']]),

            'script_tag' => new TwigFilter('script_tag', [$this, 'twig_script_tag'], ['needs_environment' => false, 'is_safe' => ['html']]),

            'stylesheet_tag' => new TwigFilter('stylesheet_tag', [$this, 'twig_stylesheet_tag'], ['needs_environment' => false, 'is_safe' => ['html']]),

            'mod_asset_url' => new TwigFilter('mod_asset_url', [$this, 'twig_mod_asset_url']),

            'asset_url' => new TwigFilter('asset_url', [$this, 'twig_asset_url'], ['needs_environment' => true, 'is_safe' => ['html']]),

            'library_url' => new TwigFilter('library_url', [$this, 'twig_library_url'], ['needs_environment' => true, 'is_safe' => ['html']]),

            'money' => new TwigFilter('money', [$this, 'twig_money'], ['needs_environment' => true, 'is_safe' => ['html']]),

            'money_without_currency' => new TwigFilter('money_without_currency', [$this, 'twig_money_without_currency'], ['needs_environment' => true, 'is_safe' => ['html']]),

            'money_convert' => new TwigFilter('money_convert', [$this, 'twig_money_convert'], ['needs_environment' => true, 'is_safe' => ['html']]),

            'money_convert_without_currency' => new TwigFilter('money_convert_without_currency', [$this, 'money_convert_without_currency'], ['needs_environment' => true, 'is_safe' => ['html']]),
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

    public function twig_bb_date($time, $format = null)
    {
        $locale_date_format = $this->di['config']['locale_date_format'];
        $format = is_null($format) ? $locale_date_format : $format;

        return date($format, strtotime($time));
    }

    public function twig_bb_datetime($time, $format = null)
    {
        $locale_date_format = $this->di['config']['locale_date_format'];
        $locale_time_format = $this->di['config']['locale_time_format'];
        $format = is_null($format) ? $locale_date_format . $locale_time_format : $format;

        return date($format, strtotime($time));
    }

    public function twig_ipcountryname_filter($value)
    {
        if (empty($value)) {
            return '';
        }

        try {
            $record = $this->di['geoip']->country($value);

            return $record->country->name;
        } catch (Exception $e) {
            return '';
        }
    }

    public function twig_bb_client_link_filter($link, $params = null)
    {
        if (null === $this->di['url']) {
            return null;
        }

        return $this->di['url']->link($link, $params);
    }

    public function twig_bb_admin_link_filter($link, $params = null)
    {
        return $this->di['url']->adminLink($link, $params);
    }

    function twig_period_title(Twig\Environment $env, $period)
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
        return BB_URL . 'modules/' . ucfirst($mod) . '/assets/' . $asset;
    }

    public function twig_asset_url(Twig\Environment $env, $asset)
    {
        $globals = $env->getGlobals();

        return BB_URL . 'themes/' . $globals['current_theme'] . '/assets/' . $asset;
    }

    public function twig_library_url(Twig\Environment $env, $path)
    {
        $globals = $env->getGlobals();

        return BB_URL . 'library/' . $path;
    }

    public function twig_img_tag($path, $alt = null)
    {
        $alt = is_null($alt) ? pathinfo($path, PATHINFO_BASENAME) : $alt;

        return sprintf('<img src="%s" alt="%s" title="%s"/>', htmlspecialchars($path), htmlspecialchars($alt), htmlspecialchars($alt));
    }

    public function twig_script_tag($path)
    {
        return sprintf('<script type="text/javascript" src="%s?%s"></script>', $path, Box_Version::VERSION);
    }

    public function twig_stylesheet_tag($path, $media = 'screen')
    {
        return sprintf('<link rel="stylesheet" type="text/css" href="%s?v=%s" media="%s" />', $path, Box_Version::VERSION, $media);
    }

    public function twig_gravatar_filter($email, $size = 20)
    {
        return (new Box_Tools())->get_gravatar($email, $size);
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
        return number_format($number, $decimals, $dec_point, $thousands_sep);
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
        $lngh = [1, 60, 3600, 86400, 604800, 2630880, 31570560, 315705600];
        $no = 0;

        for ($v = sizeof($lngh) - 1; ($v >= 0) && (($no = $dif / $lngh[$v]) <= 1); --$v) {
        }

        if ($v < 0) {
            $v = 0;
        }

        $_tm = $cur_tm - ($dif % $lngh[$v]);

        $no = floor($no);

        if (1 != $no) {
            $pds[$v] .= 's';
        }
        $x = sprintf('%d %s ', $no, $pds[$v]);

        return $x;
    }

    public function twig_size_filter($value)
    {
        $precision = 2;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($value, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    public function twig_markdown_filter(Twig\Environment $env, $value)
    {
        $content = $value ?? '';
        $markdownParser = new GithubFlavoredMarkdownConverter(['html_input' => 'escape', 'allow_unsafe_links' => false, 'max_nesting_level' => 50]);
        return $markdownParser->convert($content);
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
}
