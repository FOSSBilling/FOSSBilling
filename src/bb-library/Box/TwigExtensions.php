<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (http://www.boxbilling.com)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */


class Box_TwigExtensions extends Twig\Extension\AbstractExtension implements \Box\InjectionAwareInterface
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
        return array(
            'alink'     => new Twig\TwigFilter('alink', array($this, 'twig_bb_admin_link_filter'), array('is_safe' => array('html'))),
            'link'      => new Twig\TwigFilter('link', array($this, 'twig_bb_client_link_filter'), array('is_safe' => array('html'))),
            'gravatar'  => new Twig\TwigFilter('gravatar','twig_gravatar_filter'),
            'markdown'  => new Twig\TwigFilter('markdown','twig_markdown_filter', array('needs_environment' => true, 'is_safe' => array('html'))),
            'truncate'  => new Twig\TwigFilter('truncate','twig_truncate_filter', array('needs_environment' => true)),
            'timeago'   => new Twig\TwigFilter('timeago','twig_timeago_filter'),
            'daysleft'  => new Twig\TwigFilter('daysleft','twig_daysleft_filter'),
            'size'      => new Twig\TwigFilter('size', 'twig_size_filter'),
            'ipcountryname' => new Twig\TwigFilter('ipcountryname', array($this, 'twig_ipcountryname_filter')),
            'number' => new Twig\TwigFilter('number', 'twig_number_filter'),
            'period_title' => new Twig\TwigFilter('period_title', 'twig_period_title', array('needs_environment' => true, 'is_safe' => array('html'))),
            'autolink' => new Twig\TwigFilter('autolink', 'twig_autolink_filter'),
            'bbmd' => new Twig\TwigFilter('bbmd', 'twig_bbmd_filter', array('needs_environment' => true, 'is_safe' => array('html'))),

            'bb_date' => new Twig\TwigFilter('bb_date', array($this, 'twig_bb_date')),
            'bb_datetime' => new Twig\TwigFilter('bb_datetime', array($this, 'twig_bb_datetime')),

            'img_tag' => new Twig\TwigFilter('img_tag', 'twig_img_tag', array('needs_environment' => false, 'is_safe' => array('html'))),
            'script_tag' => new Twig\TwigFilter('script_tag', 'twig_script_tag', array('needs_environment' => false, 'is_safe' => array('html'))),
            'stylesheet_tag' => new Twig\TwigFilter('stylesheet_tag', 'twig_stylesheet_tag', array('needs_environment' => false, 'is_safe' => array('html'))),

            'mod_asset_url' => new Twig\TwigFilter('mod_asset_url', 'twig_mod_asset_url'),
            'asset_url' => new Twig\TwigFilter('asset_url', 'twig_asset_url', array('needs_environment' => true, 'is_safe' => array('html'))),

            'money' => new Twig\TwigFilter('money', 'twig_money', array('needs_environment' => true, 'is_safe' => array('html'))),
            'money_without_currency' => new Twig\TwigFilter('money_without_currency','twig_money_without_currency', array('needs_environment' => true, 'is_safe' => array('html'))),
            'money_convert' => new Twig\TwigFilter('money_convert', 'twig_money_convert', array('needs_environment' => true, 'is_safe' => array('html'))),
            'money_convert_without_currency' => new Twig\TwigFilter('money_convert_without_currency', array('needs_environment' => true, 'is_safe' => array('html'))),
        );
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
        return strftime($format, strtotime($time));
    }

    public function twig_bb_datetime($time, $format = null)
    {
        $locale_date_format = $this->di['config']['locale_date_format'];
        $locale_time_format = $this->di['config']['locale_time_format'];
        $format = is_null($format) ? $locale_date_format . $locale_time_format : $format;
        return strftime($format, strtotime($time));
    }

    function twig_ipcountryname_filter($value)
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

    function twig_bb_client_link_filter($link, $params = null)
    {
        if($this->di['url'] === null) {
            return null;
        }
        return $this->di['url']->link($link, $params);
    }

    function twig_bb_admin_link_filter($link, $params = null)
    {
        return $this->di['url']->adminLink($link, $params);
    }

}

function twig_period_title(Twig\Environment $env, $period)
{
    $globals = $env->getGlobals();
    $api_guest = $globals['guest'];
    return $api_guest->system_period_title(array('code'=>$period));
}

function twig_money_convert(Twig\Environment $env, $price, $currency = null)
{
    $globals = $env->getGlobals();
    $api_guest = $globals['guest'];
    if(is_null($currency)) {
        $c = $api_guest->cart_get_currency();
        $currency = $c['code'];
    }
    return $api_guest->currency_format(array("price" => $price, 'code'=>$currency, 'convert'=>true));
}

function money_convert_without_currency(Twig\Environment $env, $price, $currency = null, $without_currency = false)
{
    $globals = $env->getGlobals();
    $api_guest = $globals['guest'];
    if(is_null($currency)) {
        $c = $api_guest->cart_get_currency();
        $currency = $c['code'];
    }
    return $api_guest->currency_format(array("price" => $price, 'code'=>$currency, 'convert'=>true, 'without_currency'=>true));
}

function twig_money(Twig\Environment $env, $price, $currency = null)
{
    $globals = $env->getGlobals();
    $api_guest = $globals['guest'];
    return $api_guest->currency_format(array("price" => $price, 'code'=>$currency, 'convert'=>false));
}

function twig_money_without_currency(Twig\Environment $env, $price, $currency = null)
{
    $globals = $env->getGlobals();
    $api_guest = $globals['guest'];
    return $api_guest->currency_format(array("price" => $price, 'code'=>$currency, 'convert'=>false, 'without_currency'=>true));
}

function twig_mod_asset_url($asset, $mod)
{
    return BB_URL . 'bb-modules/'.ucfirst($mod).'/assets/'.$asset;
}

function twig_asset_url(Twig\Environment $env, $asset)
{
    $globals = $env->getGlobals();
    return BB_URL . 'bb-themes/'.$globals['current_theme'].'/assets/'.$asset;
}

function twig_img_tag($path, $alt = null)
{
    $alt = is_null($alt) ? pathinfo($path, PATHINFO_BASENAME) : $alt;
    return sprintf('<img src="%s" alt="%s" title="%s"/>', $path, $alt, $alt);
}

function twig_script_tag($path)
{
    return sprintf('<script type="text/javascript" src="%s?%s"></script>', $path, Box_Version::VERSION);
}

function twig_stylesheet_tag($path, $media = 'screen')
{
    return sprintf('<link rel="stylesheet" type="text/css" href="%s?v=%s" media="%s" />', $path, Box_Version::VERSION, $media);
}

function twig_gravatar_filter($email, $size = null)
{
	$protocol = BB_SSL ? 'https' : 'http';
	$link     = $protocol . '://gravatar.com/avatar/' . md5($email);
	if ($size) {
		$link .= '/?s=' . $size;
	}

	return $link;
}

function twig_autolink_filter($text)
{
   $pattern  = '#\b(([\w-]+://?|www[.])[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/)))#';

   $callback = function($matches)
   {
       $url       = array_shift($matches);
       $url_parts = parse_url($url);
       if(!isset($url_parts["scheme"])) {
           $url = 'http://' . $url;
       }

       return sprintf('<a target="_blank" href="%s">%s</a>', $url, $url);
   };

   return preg_replace_callback($pattern, $callback, $text);
}

function twig_number_filter($number, $decimals = 2, $dec_point = '.', $thousands_sep = '')
{
    return number_format($number, $decimals, $dec_point, $thousands_sep);
}

function twig_daysleft_filter($iso8601)
{
    $timediff = strtotime($iso8601) - time();
    return intval($timediff/86400);
}

function twig_timeago_filter($iso8601)
{
    $cur_tm = time(); $dif = $cur_tm-strtotime($iso8601);
    $pds = array(__('second'),__('minute'),__('hour'),__('day'),__('week'),__('month'),__('year'),__('decade'));
    $lngh = array(1,60,3600,86400,604800,2630880,31570560,315705600);
    for($v = sizeof($lngh)-1; ($v >= 0)&&(($no = $dif/$lngh[$v])<=1); $v--); if($v < 0) $v = 0; $_tm = $cur_tm-($dif%$lngh[$v]);

    $no = floor($no); if($no <> 1) $pds[$v] .='s'; $x=sprintf("%d %s ",$no,$pds[$v]);
    return $x;
}

function twig_size_filter($value)
{
    $precision = 2;
    $units = array('B', 'KB', 'MB', 'GB', 'TB');

    $bytes = max($value, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);

    $bytes /= pow(1024, $pow);

    return round($bytes, $precision) . ' ' . $units[$pow];
}

function twig_markdown_filter(Twig\Environment $env, $value)
{
    $markdownParser = new \Michelf\MarkdownExtra;
    // Michelf Markdown version 1.7.0 and up
    $markdownParser->hard_wrap = true;
    return $markdownParser->transform($value); //htmlspecialchars($value, ENT_NOQUOTES)
}

function twig_truncate_filter(Twig\Environment $env, $value, $length = 30, $preserve = false, $separator = '...')
{
    mb_internal_encoding("UTF-8");
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

/**
 * BoxBilling markdown
 */
function twig_bbmd_filter(Twig\Environment $env, $value)
{
    $value = twig_markdown_filter($env, $value);
    return $value;
}
