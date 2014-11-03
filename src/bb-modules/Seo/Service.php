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

namespace Box\Mod\Seo;

class Service
{
    public function pingSitemap($api, $config)
    {
        $key = 'mod_seo_last_sitemap_submit';
        $last_time = $api->system_param(array('key'=>$key));
        if($last_time && (time() - strtotime($last_time)) < 86400) {
            return false;
        }
        
        $url = urldecode(BB_URL.'sitemap.xml');
        if(isset($config['sitemap_google']) && $config['sitemap_google']) {
            $link = "http://www.google.com/webmasters/tools/ping?sitemap=" . $url;
            $curl = new Box_Curl($link);
            $curl->request();
            error_log('Submitted sitemap to Google');
        }
        
        if(isset($config['sitemap_yahoo']) && $config['sitemap_yahoo']) {
            $link = "http://search.yahooapis.com/SiteExplorerService/V1/updateNotification?appid=SitemapWriter&url=" . $url;
            $curl = new Box_Curl($link);
            $curl->request();
            error_log('Submitted sitemap to Yahoo');
        }
        
        if(isset($config['sitemap_bing']) && $config['sitemap_bing']) {
            $link = "http://www.bing.com/webmaster/ping.aspx?siteMap=" . $url;
            $curl = new Box_Curl($link);
            $curl->request();
            error_log('Submitted sitemap to Bing');
        }
        
        $api->system_update_params(array($key=>date('c')));
        
        return true;
    }
    
    public function pingRss($api, $config)
    {
        //@todo
        return false;
        
        $rss        = '';
        $title      = '';
        $homepage   = BB_URL;

        $rss        = urldecode($rss);
        $title      = urldecode($title);
        $homepage   = urldecode($homepage);

        $fp = @fopen("http://rpc.weblogs.com/pingSiteForm?name=$title&url=" . $rss, "r");
        @fclose($fp);
        $fp = @fopen("http://pingomatic.com/ping/?title=$title&blogurl=$homepage&rssurl=". $rss."&chk_weblogscom=on&chk_blogs=on&chk_feedburner=on&chk_syndic8=on&chk_newsgator=on&chk_myyahoo=on&chk_pubsubcom=on&chk_blogdigger=on&chk_blogstreet=on&chk_moreover=on&chk_weblogalot=on&chk_icerocket=on&chk_newsisfree=on&chk_topicexchange=on&chk_google=on&chk_tailrank=on&chk_postrank=on&chk_skygrid=on&chk_collecta=on&chk_superfeedr=on&chk_audioweblogs=on&chk_rubhub=on&chk_geourl=on&chk_a2b=on&chk_blogshares=on", "r");
        @fclose($fp);
        
        return true;
    }
    
    public static function onBeforeAdminCronRun(Box_Event $event)
    {
        $api = $event->getApiAdmin();
        $config = $api->extension_config_get(array("ext"=>"mod_seo"));
        
        try {
            $s = new self;
            $s->pingSitemap($api, $config);
            $s->pingRss($api, $config);
        } catch(Exeption $e) {
            error_log($e->getMessage());
        }
        
        return true;
    }
}