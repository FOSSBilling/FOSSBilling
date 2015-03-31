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


namespace Box\Mod\Theme\Model;

class Theme
{
    private $name;

    public function __construct($name)
    {
        $this->name = $name;

        if(!file_exists($this->getPath())) {
            throw new \Box_Exception('Theme ":name" does not exists', array(':name'=>$name));
        }
    }

    public function getName()
    {
        return $this->name;
    }

    public function isAdminAreaTheme()
    {
        return (strpos($this->name, 'admin_') !== false);
    }

    public function isAssetsPathWritable()
    {
        return is_writable($this->getPathAssets());
    }

    public function getSnippets()
    {
        $path = $this->getPathHtml();
        $snippets = glob($path .DIRECTORY_SEPARATOR. 'snippet_*.phtml');
        $result = array();
        foreach($snippets as $snippet) {
            $result[basename($snippet)] = str_replace('snippet_','', pathinfo($snippet, PATHINFO_FILENAME));
        }
        return $result;
    }

    public function getUploadedAssets()
    {
        $assets_folder = $this->getPathAssets();
        $files = $this->getSettingsPageFiles();
        $uploaded  = array();
        foreach($files as $file) {
            if(file_exists($assets_folder .DIRECTORY_SEPARATOR. $file)) {
                $uploaded[] = array(
                    'name'  => $file,
                    'url'   => $this->getUrl().'/assets/'.$file,
                );
            }
        }
        return $uploaded;
    }

    private function getSettingsPageFiles()
    {
        $str = $this->getSettingsPageHtml();
        if(empty($str)) {
            return array();
        }

        $dom = new \DOMDocument();
        $dom->loadHTML($str);
        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query("//input[@type='file']/@name");
        $files = array();
        foreach($nodes as $node) {
            $files[] = $node->textContent;
        }
        return $files;
    }

    /**
     * @return string
     */
    public function getSettingsPageHtml()
    {
        $spp = $this->getPathConfig() .DIRECTORY_SEPARATOR. 'settings.html';
        if(!file_exists($spp)) {
            error_log('Theme '. $this->getName() .' does not have settings page');
            return '';
        }

        $settings_page = file_get_contents($spp);
        $settings_page = $this->strip_tags_content($settings_page, '<script><style>');

        //remove style attributes
        $settings_page = preg_replace('/(<[^>]+) style=".*?"/i', '$1', $settings_page);

        //fix unclosed texarea
        $settings_page = preg_replace('/<textarea (.*)\/>/i', "<textarea $1></textarea>", $settings_page);

        return $settings_page;
    }

    private function getSettingsData()
    {
        $cp = $this->getPathSettingsDataFile();
        if(!file_exists($cp)) {
            return array();
        }

        $json = file_get_contents($cp);
        $array = json_decode($json, 1);
        if(!is_array($array)) {
            return array();
        }

        return $array;
    }

    public function getPresetsFromSettingsDataFile()
    {
        $array = $this->getSettingsData();
        return isset($array['presets']) ? $array['presets'] : array();
    }

    public function getCurrentPreset()
    {
        $array = $this->getSettingsData();
        return isset($array['current'])? $array['current'] :  'Default';
    }

    public function getPresetFromSettingsDataFile($preset)
    {
        $array = $this->getPresetsFromSettingsDataFile();
        return (is_array($array) && isset($array[$preset])) ? $array[$preset] : array();
    }

    public function getUrl()
    {
        return BB_URL . 'bb-themes/'.$this->name;
    }

    public function getPath()
    {
        return BB_PATH_THEMES . DIRECTORY_SEPARATOR . $this->name;
    }

    public function getPathConfig()
    {
        return $this->getPath() . DIRECTORY_SEPARATOR .'config';
    }

    public function getPathAssets()
    {
        return $this->getPath() . DIRECTORY_SEPARATOR .'assets';
    }

    public function getPathHtml()
    {
        return $this->getPath() . DIRECTORY_SEPARATOR .'html';
    }

    public function getPathSettingsDataFile()
    {
        return $this->getPathConfig() . DIRECTORY_SEPARATOR .'settings_data.json';
    }

    /**
     * @param string $text
     */
    private function strip_tags_content($text, $tags = '', $invert = TRUE)
    {

        preg_match_all('/<(.+?)[\s]*\/?[\s]*>/si', trim($tags), $tags);
        $tags = array_unique($tags[1]);

        if(is_array($tags) && count($tags) > 0) {
            if($invert === FALSE) {
                return preg_replace('@<(?!(?:'. implode('|', $tags) .')\b)(\w+)\b.*?>.*?</\1>@si', '', $text);
            }
            else {
                return preg_replace('@<('. implode('|', $tags) .')\b.*?>.*?</\1>@si', '', $text);
            }
        }
        elseif($invert === FALSE) {
            return preg_replace('@<(\w+)\b.*?>.*?</\1>@si', '', $text);
        }
        return $text;
    }
}
