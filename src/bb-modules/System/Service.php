<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (https://www.boxbilling.org)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

namespace Box\Mod\System;
use SebastianBergmann\Exporter\Exception;

class Service
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
     * @param string $param
     * @param boolean $default
     */
    public function getParamValue($param, $default = NULL)
    {
        if(empty($param)) {
            throw new \Box_Exception('Parameter key is missing');
        }

        $query = "SELECT value
                FROM setting
                WHERE param = :param
               ";
        $pdo = $this->di['pdo'];
        $stmt = $pdo->prepare($query);
        $stmt->execute(array('param'=>$param));
        $results = $stmt->fetchColumn();
        if($results === false) {
            return $default;
        }
        return $results;
    }

    public function setParamValue($param, $value, $createIfNotExists = true)
    {
        $pdo = $this->di['pdo'];

        if($this->paramExists($param)) {
            $query="UPDATE setting SET value = :value WHERE param = :param";
            $stmt = $pdo->prepare($query);
            $stmt->execute(array('param'=>$param, 'value'=>$value));
        } else if($createIfNotExists) {
            try {
                $query="INSERT INTO setting (param, value, created_at, updated_at) VALUES (:param, :value, :created_at, :updated_at)";
                $stmt = $pdo->prepare($query);
                $stmt->execute(array('param'=>$param, 'value'=>$value, 'created_at'=>date('Y-m-d H:i:s'), 'updated_at'=>date('Y-m-d H:i:s')));
            } catch(\Exception $e) {
                //ignore duplicate key error
                if($e->getCode() != 23000) {
                    throw $e;
                }
            }
        }

        return true;
    }

    public function paramExists($param)
    {
        $pdo = $this->di['pdo'];
        $q = "SELECT id
              FROM setting
              WHERE param = :param";
        $stmt = $pdo->prepare($q);
        $stmt->execute(array('param'=>$param));
        $results = $stmt->fetchColumn();
        return (bool)$results;
    }

    /**
     * @param string[] $params
     */
    private function _getMultipleParams($params)
    {
        if (!is_array($params)){
            return array();
        }
        $query = "SELECT param, value
                FROM setting
                WHERE param IN('".implode("', '", $params)."')
               ";
        $rows = $this->di['db']->getAll($query);
        $result = array();
        foreach($rows as $row){
            $result[ $row['param'] ] = $row['value'];
        }
        return $result;
    }

    public function getCompany()
    {
        $c = array(
            'company_name',
            'company_email',
            'company_tel',
            'company_signature',
            'company_logo',
            'company_address_1',
            'company_address_2',
            'company_address_3',

            'company_account_number',
            'company_number',
            'company_note',
            'company_privacy_policy',
            'company_tos',
            'company_vat_number',
        );
        $results = $this->_getMultipleParams($c);
        return array(
            'www'       =>  $this->di['config']['url'],
            'name'      =>  $this->di['array_get']($results, 'company_name', NULL),
            'email'     =>  $this->di['array_get']($results, 'company_email', NULL),
            'tel'       =>  $this->di['array_get']($results, 'company_tel', NULL),
            'signature' =>  $this->di['array_get']($results, 'company_signature', NULL),
            'logo_url'  =>  $this->di['array_get']($results, 'company_logo', NULL),
            'address_1' =>  $this->di['array_get']($results, 'company_address_1', NULL),
            'address_2' =>  $this->di['array_get']($results, 'company_address_2', NULL),
            'address_3' =>  $this->di['array_get']($results, 'company_address_3', NULL),
            'account_number'    =>  $this->di['array_get']($results, 'company_account_number', NULL),
            'number'            =>  $this->di['array_get']($results, 'company_number', NULL),
            'note'              =>  $this->di['array_get']($results, 'company_note', NULL),
            'privacy_policy'    =>  $this->di['array_get']($results, 'company_privacy_policy', NULL),
            'tos'               =>  $this->di['array_get']($results, 'company_tos', NULL),
            'vat_number'        =>  $this->di['array_get']($results, 'company_vat_number', NULL),
        );
    }

    public function getLanguages($deep = false)
    {
        $path = BB_PATH_LANGS;
        $locales = array();
        if ($handle = opendir($path)) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry != ".svn" && $entry != "." && $entry != ".." && is_dir($path . DIRECTORY_SEPARATOR . $entry)) {
                    $locales[] = $entry;
                }
            }
            closedir($handle);
        }
        sort($locales);
        if (!$deep) {
            return $locales;
        }

        $details = array();

        foreach ($locales as $locale) {
            $file = $path . '/' . $locale . '/LC_MESSAGES/messages.po';

            if (file_exists($file)) {
                $lNames = $this->getLocales();
                if (isset($lNames[$locale]) && !empty($lNames[$locale])) {
                    $title = $lNames[$locale];
                }else{
                    $title = null;
                }
                $details[] = array(
                    'locale' => $locale,
                    'title'  => $title,
                );
            }
        }

        return $details;
    }
    
    public function getParams($data)
    {
        $query = "SELECT param, value
                  FROM setting";
        $rows = $this->di['db']->getAll($query);
        $result = array();
        foreach($rows as $row){
            $result[ $row['param'] ] = $row['value'];
        }
        return $result;
    }

    public function updateParams($data)
    {
        $this->di['events_manager']->fire(array('event'=>'onBeforeAdminSettingsUpdate', 'params'=>$data));

        foreach($data as $key=>$val) {
            $this->setParamValue($key, $val, true);
        }

        $this->di['events_manager']->fire(array('event'=>'onAfterAdminSettingsUpdate'));

        $this->di['logger']->info('Updated system general settings');
        return true;
    }

    public function getMessages($type)
    {
        $msgs = array();

        try {
            $updater = $this->di['updater'];
            if($updater->getCanUpdate()) {
                $version = $updater->getLatestVersion();
                $msgs['info'][] = sprintf('BoxBilling %s is available for download.', $version);
            }
        } catch(\Exception $e) {
            error_log($e->getMessage());
        }
        $last_exec = $this->getParamValue('last_cron_exec');
        if(!$last_exec) {
            $msgs['info'][] = 'Cron was never executed. Make sure you have setup cron job.';
        }

        $install = BB_PATH_ROOT.'/install';
        if($this->di['tools']->fileExists(BB_PATH_ROOT.'/install')) {
            $msgs['info'][] = sprintf('Install module "%s" still exists. Please remove it for security reasons.', $install);
        }

        if($this->getVersion() == "0.0.1") {
            $msgs['info'][] = 'BoxBilling couldn\'t find valid version information. This is okay if you downloaded BoxBilling directly from the master branch, instead of a released version. But beware, the master branch may not be stable enough for production use.';
        }

        if(!extension_loaded('openssl')) {
            $msgs['info'][] = sprintf('BoxBilling requires %s extension to be enabled on this server for security reasons.', 'php openssl');
        }

        return $this->di['array_get']($msgs, $type, array());
    }

    public function templateExists($file, $identity = null)
    {
        if ($identity instanceof \Model_Admin) {
            $client = false;
        } else {
            $client = true;
        }
        $themeService = $this->di['mod_service']('theme');
        $theme = $themeService->getThemeConfig($client);
        foreach ($theme['paths'] as $path) {
            if ($this->di['tools']->fileExists($path . DIRECTORY_SEPARATOR . $file)) {
                return true;
            }
        }

        return false;
    }

    public function renderString($tpl, $try_render, $vars)
    {
        $twig = $this->di['twig'];
        //add client api if _client_id is set
        if(isset($vars['_client_id'])) {
            $identity = $this->di['db']->load('Client', $vars['_client_id']);
            if($identity instanceof \Model_Client) {
                try{
                    $twig->addGlobal('client', $this->di['api_client']);
                }
                catch (\Exception $e){
                    error_log('api_client could not be added to template: '.$e->getMessage());
                }
            }
        }
        else{
            // attempt adding admin api to twig
        try {
            $twig->addGlobal('admin', $this->di['api_admin']);
        } catch(\Exception $e) {
            //skip if admin is not logged in
        }
        }

        try {
            $template = $twig->load($tpl);
            $parsed   = $template->render($vars);
        } catch (\Exception $e) {
            //$twig->load throws error when $tpl is string
            $parsed = $this->createTemplateFromString($tpl, $try_render, $vars);

        }

        return $parsed;
    }

    public function createTemplateFromString($tpl, $try_render, $vars){
        try{
                $twig = $this->di['twig'];
                $template = $twig->createTemplate($tpl);
                $parsed   = $template->render($vars);
        }
        catch(\Exception $e){
            $parsed = $tpl;
            if (!$try_render) {
                throw $e;
            }
        }

        return $parsed;
    }

    public function clearCache()
    {
        $this->di['tools']->emptyFolder(BB_PATH_CACHE);
        return true;
    }

    public function getEnv($ip)
    {
        if(isset($ip)) {
            try {
                return $this->di['tools']->get_url('https://api.ipify.org', 2);
            } catch(\Exception $e) {
                return '';
            }
        }

        $r = $this->di['requirements'];
        $data = $r->getInfo();
        $data['last_patch'] = $this->getParamValue('last_patch');

        return $data;
    }

    public function getCurrentUrl()
    {
        $request = $this->di['request'];
        $pageURL = 'http';
        $https = $request->getScheme();
        if (isset($https) && $https == "https") {
            $pageURL .= "s";
        }
        $pageURL .= "://";

        $serverPort = $request->getServer("SERVER_PORT");
        if (isset($serverPort) && $serverPort != "80" && $serverPort != "443") {
            $pageURL .= $request->getServer("SERVER_NAME").":".$request->getServer("SERVER_PORT");
        } else {
            $pageURL .= $request->getServer("SERVER_NAME");
        }

        $this_page = $request->getURI();
        if (strpos($this_page, "?") !== false) {
            $a = explode("?", $this_page);
            $this_page = reset($a);
        }
        return $pageURL . $this_page;
    }

    public function getPeriod($code)
    {
        $p = \Box_Period::getPredefined();
        if(isset($p[$code])) {
            return $p[$code];
        }

        $p = new \Box_Period($code);
        return $p->getTitle();
    }

    public function getPublicParamValue($param)
    {
        $query = "SELECT value
                FROM setting
                WHERE param = :param
                AND public = 1
               ";

        $pdo = $this->di['pdo'];
        $stmt = $pdo->prepare($query);
        $stmt->execute(array('param'=>$param));
        $results = $stmt->fetchColumn();
        if($results === false) {
            throw new \Box_Exception('Parameter :param does not exist', array(':param'=>$param));
        }
        return $results;
    }

    public function getLocales()
    {
        return array
        (
            'aa'    => 'Afar',
            'ab'    => 'Abkhazian',
            'af'    => 'Afrikaans',
            'af_ZA' => 'Afrikaans (South Africa)',
            'am'    => 'Amharic',
            'am_ET' => 'Amharic (Ethiopia)',
            'ar'    => 'Arabic',
            'ar_AA' => 'Arabic (Unitag)',
            'ar_SA' => 'Arabic (Saudi Arabia)',
            'as'    => 'Assamese',
            'as_IN' => 'Assamese (India)',
            'ay'    => 'Aymara',
            'az'    => 'Azerbaijani',
            'az_AZ' => 'Azerbaijani (Azerbaijan)',
            'ba'    => 'Bashkir',
            'be'    => 'Belarusian',
            'be_BY' => 'Belarusian (Belarus)',
            'bg'    => 'Bulgarian',
            'bg_BG' => 'Bulgarian (Bulgaria)',
            'bh'    => 'Bihari',
            'bi'    => 'Bislama',
            'bn'    => 'Bengali',
            'bn_BD' => 'Bengali (Bangladesh)',
            'bn_ID' => 'Bengali (India)',
            'bo'    => 'Tibetan',
            'bo_CN' => 'Tibetan (China)',
            'br'    => 'Breton',
            'bs'    => 'Bosnian',
            'bs_BA' => 'Bosnian (Bosnia and Herzegovina)',
            'ca'    => 'Catalan',
            'ca_ES' => 'Catalan (Spain)',
            'co'    => 'Corsican',
            'cr'    => 'Cree',
            'cs'    => 'Czech',
            'cs_CZ' => 'Czech (Czech Republic)',
            'cy'    => 'Welsh',
            'cy_GB' => 'Welsh (United Kingdom)',
            'da'    => 'Danish',
            'da_DK' => 'Danish (Denmark)',
            'de'    => 'German',
            'de_AT' => 'German (Austria)',
            'de_CH' => 'German (Switzerland)',
            'de_DE' => 'German (Germany)',
            'dz'    => 'Dzongkha',
            'dz_BT' => 'Dzongkha (Bhutan)',
            'el'    => 'Greek',
            'el_GR' => 'Greek (Greece)',
            'en'    => 'English',
            'en_AU' => 'English (Australia)',
            'en_CA' => 'English (Canada)',
            'en_GB' => 'English (United Kingdom)',
            'en_IE' => 'English (Ireland)',
            'en_US' => 'English (United States)',
            'en_ZA' => 'English (South Africa)',
            'eo'    => 'Esperanto',
            'es'    => 'Spanish',
            'es_AR' => 'Spanish (Argentina)',
            'es_BO' => 'Spanish (Bolivia)',
            'es_CL' => 'Spanish (Chile)',
            'es_CO' => 'Spanish (Colombia)',
            'es_CR' => 'Spanish (Costa Rica)',
            'es_DO' => 'Spanish (Dominican Republic)',
            'es_EC' => 'Spanish (Ecuador)',
            'es_ES' => 'Spanish (Spain)',
            'es_MX' => 'Spanish (Mexico)',
            'es_NI' => 'Spanish (Nicaragua)',
            'es_PA' => 'Spanish (Panama)',
            'es_PE' => 'Spanish (Peru)',
            'es_PR' => 'Spanish (Puerto Rico)',
            'es_PY' => 'Spanish (Paraguay)',
            'es_SV' => 'Spanish (El Salvador)',
            'es_UY' => 'Spanish (Uruguay)',
            'es_VE' => 'Spanish (Venezuela)',
            'et'    => 'Estonian',
            'et_EE' => 'Estonian (Estonia)',
            'eu'    => 'Basque',
            'eu_ES' => 'Basque (Spain)',
            'fa'    => 'Persian',
            'fa_IR' => 'Persian (Iran)',
            'fi'    => 'Finnish',
            'fi_FI' => 'Finnish (Finland)',
            'fj'    => 'Fiji',
            'fo'    => 'Faroese',
            'fo_FO' => 'Faroese (Faroe Islands)',
            'fr'    => 'French',
            'fr_CA' => 'French (Canada)',
            'fr_CH' => 'French (Switzerland)',
            'fr_FR' => 'French (France)',
            'fy'    => 'Frisian',
            'fy_NL' => 'Frisian (Netherlands)',
            'ga'    => 'Irish',
            'ga_IE' => 'Irish (Ireland)',
            'gd'    => 'Scots Gaelic',
            'gl'    => 'Galician',
            'gl_ES' => 'Galician (Spain)',
            'gn'    => 'Guarani',
            'gu'    => 'Gujarati',
            'gu_IN' => 'Gujarati (India)',
            'ha'    => 'Hausa',
            'he'    => 'Hebrew',
            'he_IL' => 'Hebrew (Israel)',
            'hi'    => 'Hindi',
            'hi_IN' => 'Hindi (India)',
            'hr'    => 'Croatian',
            'hr_HR' => 'Croatian (Croatia)',
            'hu'    => 'Hungarian',
            'hu_HU' => 'Hungarian (Hungary)',
            'hy'    => 'Armenian',
            'hy_AM' => 'Armenian (Armenia)',
            'ia'    => 'Interlingua',
            'id'    => 'Indonesian',
            'id_ID' => 'Indonesian (Indonesia)',
            'ie'    => 'Interlingue',
            'ik'    => 'Inupiak',
            'is'    => 'Icelandic',
            'is_IS' => 'Icelandic (Iceland)',
            'it'    => 'Italian',
            'it_CH' => 'Italian (Switzerland)',
            'it_IT' => 'Italian (Italy)',
            'iu'    => 'Inuktitut (Eskimo)',
            'ja'    => 'Japanese',
            'ja_JP' => 'Japanese (Japan)',
            'jv'    => 'Javanese',
            'ka'    => 'Georgian',
            'ka_GE' => 'Georgian (Georgia)',
            'kk'    => 'Kazakh',
            'kk_KZ' => 'Kazakh (Kazakhstan)',
            'kl'    => 'Greenlandic',
            'km'    => 'Cambodian',
            'kn'    => 'Kannada',
            'kn_IN' => 'Kannada (India)',
            'ko'    => 'Korean',
            'ko_KR' => 'Korean (Korea)',
            'ks'    => 'Kashmiri',
            'ks_IN' => 'Kashmiri (India)',
            'ku'    => 'Kurdish',
            'ku_IQ' => 'Kurdish (Iraq)',
            'ky'    => 'Kirghiz',
            'la'    => 'Latin',
            'ln'    => 'Lingala',
            'lo'    => 'Lao',
            'lo_LA' => 'Lao (Laos)',
            'lt'    => 'Lithuanian',
            'lt_LT' => 'Lithuanian (Lithuania)',
            'lv'    => 'Latvian',
            'lv_LV' => 'Latvian (Latvia)',
            'mg'    => 'Malagasy',
            'mi'    => 'Maori',
            'mk'    => 'Macedonian',
            'mk_MK' => 'Macedonian (Macedonia)',
            'ml'    => 'Malayalam',
            'ml_IN' => 'Malayalam (India)',
            'mn'    => 'Mongolian',
            'mn_MN' => 'Mongolian (Mongolia)',
            'mo'    => 'Moldavian',
            'mr'    => 'Marathi',
            'mr_IN' => 'Marathi (India)',
            'ms'    => 'Malay',
            'ms_MY' => 'Malay (Malaysia)',
            'mt'    => 'Maltese',
            'mt_MT' => 'Maltese (Malta)',
            'my'    => 'Burmese',
            'my_MM' => 'Burmese (Myanmar)',
            'na'    => 'Nauru',
            'ne'    => 'Nepali',
            'ne_NP' => 'Nepali (Nepal)',
            'nl'    => 'Dutch',
            'nl_BE' => 'Dutch (Belgium)',
            'nl_NL' => 'Dutch (Netherlands)',
            'no'    => 'Norwegian',
            'no_NO' => 'Norwegian (Norway)',
            'oc'    => 'Occitan',
            'or'    => 'Oriya',
            'or_IN' => 'Oriya (India)',
            'pa'    => 'Punjabi',
            'pa_IN' => 'Punjabi (India)',
            'pl'    => 'Polish',
            'pl_PL' => 'Polish (Poland)',
            'ps'    => 'Pashto, Pushto',
            'pt'    => 'Portuguese',
            'pt_BR' => 'Portuguese (Brazil)',
            'pt_PT' => 'Portuguese (Portugal)',
            'qu'    => 'Quechua',
            'rm'    => 'Romansh',
            'rn'    => 'Kirundi',
            'ro'    => 'Romanian',
            'ro_RO' => 'Romanian (Romania)',
            'ru'    => 'Russian',
            'ru_RU' => 'Russian (Russia)',
            'rw'    => 'Kinyarwanda',
            'sa'    => 'Sanskrit',
            'sd'    => 'Sindhi',
            'sg'    => 'Sango',
            'sh'    => 'Serbo-Croatian',
            'si'    => 'Sinhala',
            'si_LK' => 'Sinhala (Sri Lanka)',
            'sk'    => 'Slovak',
            'sk_SK' => 'Slovak (Slovakia)',
            'sl'    => 'Slovenian',
            'sl_SI' => 'Slovenian (Slovenia)',
            'sm'    => 'Samoan',
            'sn'    => 'Shona',
            'so'    => 'Somali',
            'sq'    => 'Albanian',
            'sq_AL' => 'Albanian (Albania)',
            'sr'    => 'Serbian',
            'sr_RS' => 'Serbian (Serbia)',
            'ss'    => 'Siswati',
            'st'    => 'Sotho',
            'st_ZA' => 'Sotho (South Africa)',
            'su'    => 'Sudanese',
            'sv'    => 'Swedish',
            'sv_FI' => 'Swedish (Finland)',
            'sv_SE' => 'Swedish (Sweden)',
            'sw'    => 'Swahili',
            'sw_KE' => 'Swahili (Kenya)',
            'ta'    => 'Tamil',
            'ta_IN' => 'Tamil (India)',
            'ta_LK' => 'Tamil (Sri Lanka)',
            'te'    => 'Telugu',
            'te_IN' => 'Telugu (India)',
            'tg'    => 'Tajik',
            'tg_TJ' => 'Tajik (Tajikistan)',
            'th'    => 'Thai',
            'th_TH' => 'Thai (Thailand)',
            'ti'    => 'Tigrinya',
            'tk'    => 'Turkmen',
            'tl'    => 'Tagalog',
            'tl_PH' => 'Tagalog (Philippines)',
            'tn'    => 'Setswana',
            'to'    => 'Tonga',
            'tr'    => 'Turkish',
            'tr_TR' => 'Turkish (Turkey)',
            'ts'    => 'Tsonga',
            'tt'    => 'Tatar',
            'tw'    => 'Twi',
            'ug'    => 'Uigur',
            'uk'    => 'Ukrainian',
            'uk_UA' => 'Ukrainian (Ukraine)',
            'ur'    => 'Urdu',
            'ur_PK' => 'Urdu (Pakistan)',
            'uz'    => 'Uzbek',
            'vi'    => 'Vietnamese',
            'vi_VN' => 'Vietnamese (Vietnam)',
            'vo'    => 'Volapuk',
            'wo'    => 'Wolof',
            'wo_SN' => 'Wolof (Senegal)',
            'xh'    => 'Xhosa',
            'yi'    => 'Yiddish',
            'yo'    => 'Yoruba',
            'za'    => 'Zhuang',
            'zh'    => 'Chinese',
            'zh_CN' => 'Chinese (China)',
            'zh_HK' => 'Chinese (Hong Kong)',
            'zh_TW' => 'Chinese (Taiwan)',
            'zu'    => 'Zulu',
            'zu_ZA' => 'Zulu (South Africa)'
        );
    }

    public function getCountries()
    {
        //default countries
        $countries = array(
            "AF" => "Afghanistan",
            "AX" => "Aland Islands",
            "AL" => "Albania",
            "DZ" => "Algeria",
            "AS" => "American Samoa",
            "AD" => "Andorra",
            "AO" => "Angola",
            "AI" => "Anguilla",
            "AQ" => "Antarctica",
            "AG" => "Antigua and Barbuda",
            "AR" => "Argentina",
            "AM" => "Armenia",
            "AW" => "Aruba",
            "AU" => "Australia",
            "AT" => "Austria",
            "AZ" => "Azerbaijan",
            "BS" => "Bahamas",
            "BH" => "Bahrain",
            "BD" => "Bangladesh",
            "BB" => "Barbados",
            "BY" => "Belarus",
            "BE" => "Belgium",
            "BZ" => "Belize",
            "BJ" => "Benin",
            "BM" => "Bermuda",
            "BT" => "Bhutan",
            "BO" => "Bolivia",
            "BQ" => "Bonaire, Sint Eustatius and Saba",
            "BA" => "Bosnia and Herzegovina",
            "BW" => "Botswana",
            "BR" => "Brazil",
            "IO" => "British Indian Ocean Territory",
            "VG" => "British Virgin Islands",
            "BN" => "Brunei",
            "BG" => "Bulgaria",
            "BF" => "Burkina Faso",
            "BI" => "Burundi",
            "CV" => "Cabo Verde",
            "KH" => "Cambodia",
            "CM" => "Cameroon",
            "CA" => "Canada",
            "KY" => "Cayman Islands",
            "CF" => "Central African Republic",
            "TD" => "Chad",
            "CL" => "Chile",
            "CN" => "China",
            "CX" => "Christmas Island",
            "CC" => "Cocos (Keeling) Islands",
            "CO" => "Colombia",
            "KM" => "Comoros",
            "CD" => "Congo (Democratic Republic of the)",
            "CG" => "Congo (Republic of the)",
            "CK" => "Cook Islands",
            "CR" => "Costa Rica",
            "CI" => "Cote D'Ivoire",
            "HR" => "Croatia",
            "CU" => "Cuba",
            "CW" => "Curacao",
            "CY" => "Cyprus",
            "CZ" => "Czechia",
            "DK" => "Denmark",
            "DJ" => "Djibouti",
            "DM" => "Dominica",
            "DO" => "Dominican Republic",
            "EC" => "Ecuador",
            "EG" => "Egypt",
            "SV" => "El Salvador",
            "GQ" => "Equatorial Guinea",
            "ER" => "Eritrea",
            "EE" => "Estonia",
            "SZ" => "Eswatini",
            "ET" => "Ethiopia",
            "FK" => "Falkland Islands (Islas Malvinas)",
            "FO" => "Faroe Islands",
            "FJ" => "Fiji",
            "FI" => "Finland",
            "FR" => "France",
            "GF" => "French Guiana",
            "PF" => "French Polynesia",
            "TF" => "French Southern Territories",
            "GA" => "Gabon",
            "GM" => "Gambia",
            "GE" => "Georgia",
            "DE" => "Germany",
            "GH" => "Ghana",
            "GI" => "Gibraltar",
            "GR" => "Greece",
            "GL" => "Greenland",
            "GD" => "Grenada",
            "GP" => "Guadeloupe",
            "GU" => "Guam",
            "GT" => "Guatemala",
            "GG" => "Guernsey",
            "GN" => "Guinea",
            "GW" => "Guinea-Bissau",
            "GY" => "Guyana",
            "HT" => "Haiti",
            "HN" => "Honduras",
            "HK" => "Hong Kong",
            "HU" => "Hungary",
            "IS" => "Iceland",
            "IN" => "India",
            "ID" => "Indonesia",
            "IR" => "Iran",
            "IQ" => "Iraq",
            "IE" => "Ireland",
            "IM" => "Isle of Man",
            "IL" => "Israel",
            "IT" => "Italy",
            "JM" => "Jamaica",
            "JP" => "Japan",
            "JE" => "Jersey",
            "JO" => "Jordan",
            "KZ" => "Kazakhstan",
            "KE" => "Kenya",
            "KI" => "Kiribati",
            "KW" => "Kuwait",
            "KG" => "Kyrgyzstan",
            "LA" => "Laos",
            "LV" => "Latvia",
            "LB" => "Lebanon",
            "LS" => "Lesotho",
            "LR" => "Liberia",
            "LY" => "Libya",
            "LI" => "Liechtenstein",
            "LT" => "Lithuania",
            "LU" => "Luxembourg",
            "MO" => "Macau",
            "MG" => "Madagascar",
            "MW" => "Malawi",
            "MY" => "Malaysia",
            "MV" => "Maldives",
            "ML" => "Mali",
            "MT" => "Malta",
            "MH" => "Marshall Islands",
            "MQ" => "Martinique",
            "MR" => "Mauritania",
            "MU" => "Mauritius",
            "YT" => "Mayotte",
            "MX" => "Mexico",
            "FM" => "Micronesia",
            "MD" => "Moldova",
            "MC" => "Monaco",
            "MN" => "Mongolia",
            "ME" => "Montenegro",
            "MS" => "Montserrat",
            "MA" => "Morocco",
            "MZ" => "Mozambique",
            "MM" => "Myanmar (Burma)",
            "NA" => "Namibia",
            "NR" => "Nauru",
            "NP" => "Nepal",
            "NL" => "Netherlands",
            "NC" => "New Caledonia",
            "NZ" => "New Zealand",
            "NI" => "Nicaragua",
            "NE" => "Niger",
            "NG" => "Nigeria",
            "NU" => "Niue",
            "NF" => "Norfolk Island",
            "KP" => "North Korea",
            "MK" => "North Macedonia",
            "MP" => "Northern Mariana Islands",
            "NO" => "Norway",
            "OM" => "Oman",
            "PK" => "Pakistan",
            "PW" => "Palau",
            "PS" => "Palestine",
            "PA" => "Panama",
            "PG" => "Papua New Guinea",
            "PY" => "Paraguay",
            "PE" => "Peru",
            "PH" => "Philippines",
            "PN" => "Pitcairn Islands",
            "PL" => "Poland",
            "PT" => "Portugal",
            "PR" => "Puerto Rico",
            "QA" => "Qatar",
            "RE" => "Reunion",
            "RO" => "Romania",
            "RU" => "Russia",
            "RW" => "Rwanda",
            "BL" => "Saint Barthelemy",
            "SH" => "Saint Helena, Ascension and Tristan da Cunha",
            "KN" => "Saint Kitts And Nevis",
            "LC" => "Saint Lucia",
            "MF" => "Saint Martin",
            "VC" => "Saint Vincent and the Grenadines",
            "PM" => "Saint Pierre And Miquelon",
            "WS" => "Samoa",
            "SM" => "San Marino",
            "ST" => "Sao Tome And Principe",
            "SA" => "Saudi Arabia",
            "SN" => "Senegal",
            "RS" => "Serbia",
            "SC" => "Seychelles",
            "SL" => "Sierra Leone",
            "SG" => "Singapore",
            "SX" => "Sint Maarten",
            "SK" => "Slovakia",
            "SI" => "Slovenia",
            "SB" => "Solomon Islands",
            "SO" => "Somalia",
            "ZA" => "South Africa",
            "GS" => "South Georgia and the South Sandwich Islands",
            "SS" => "South Sudan",
            "KR" => "South Korea",
            "ES" => "Spain",
            "LK" => "Sri Lanka",
            "SD" => "Sudan",
            "SR" => "Suriname",
            "SJ" => "Svalbard and Jan Mayen",
            "SE" => "Sweden",
            "CH" => "Switzerland",
            "SY" => "Syria",
            "TW" => "Taiwan",
            "TJ" => "Tajikistan",
            "TZ" => "Tanzania",
            "TH" => "Thailand",
            "TP" => "Timor-Leste",
            "TG" => "Togo",
            "TK" => "Tokelau",
            "TO" => "Tonga",
            "TT" => "Trinidad and Tobago",
            "TN" => "Tunisia",
            "TR" => "Turkey",
            "TM" => "Turkmenistan",
            "TC" => "Turks and Caicos Islands",
            "TV" => "Tuvalu",
            "UG" => "Uganda",
            "UA" => "Ukraine",
            "AE" => "United Arab Emirates",
            "GB" => "United Kingdom",
            "US" => "United States",
            "UM" => "United States Minor Outlying Islands",
            "UY" => "Uruguay",
            "UZ" => "Uzbekistan",
            "VU" => "Vanuatu",
            "VA" => "Vatican City",
            "VE" => "Venezuela",
            "VN" => "Vietnam",
            "VI" => "Virgin Islands (U.S.)",
            "WF" => "Wallis and Futuna",
            "EH" => "Western Sahara",
            "YE" => "Yemen",
            "ZM" => "Zambia",
            "ZW" => "Zimbabwe"
        );

        $mod    = $this->di['mod']('system');
        $config = $mod->getConfig();
        if (isset($config['countries'])) {
            preg_match_all('#([A-Z]{2})=(.+)#', $config['countries'], $matches);
            if (isset($matches[1]) && !empty($matches[1]) && isset($matches[2]) && !empty($matches[2])) {
                if (count($matches[1] == count($matches[2]))){
                    $countries = array_combine($matches[1], $matches[2]);
                }
            }
        }

        return $countries;
    }

    public function getEuCountries()
    {
        $list = array(
            'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI',
            'FR', 'GR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL',
            'PL', 'PT', 'RO', 'SE', 'SI', 'SK');
        $c = $this->getCountries();
        $res = array();
        foreach ($list as $code) {
            if (!isset($c[$code])) { continue;};
            $res[$code] = $c[$code];
        }
        return $res;
    }

    public function getEuVat()
    {
        return array(
            'AT' => 20, //Austria
            'BE' => 21, //Belgium
            'BG' => 20, //Bulgaria
            'HR' => 25, //Croatia
            'CY' => 19, //Cyprus
            'CZ' => 21, //Czech Republic
            'DK' => 25, //Denmark
            'EE' => 20, //Estonia
            'FI' => 24, //Finland
            'FR' => 20, //France
            'DE' => 19, //Germany
            'GR' => 24, //Greece
            'HU' => 27, //Hungary
            'IE' => 23, //Ireland
            'IT' => 22, //Italy
            'LV' => 21, //Latvia
            'LT' => 21, //Lithuania
            'LU' => 17, //Luxembourg
            'MT' => 18, //Malta
            'NL' => 21, //Netherlands
            'PL' => 23, //Poland
            'PT' => 23, //Portugal
            'RO' => 19, //Romania
            'SK' => 20, //Slovakia
            'SI' => 22, //Slovenia
            'ES' => 21, //Spain
            'SE' => 25, //Sweden
        );
    }

    public function getStates()
    {
        return array (
            'AK' => 'Alaska',
            'AL' => 'Alabama',
            'AR' => 'Arkansas',
            'AZ' => 'Arizona',
            'CA' => 'California',
            'CO' => 'Colorado',
            'CT' => 'Connecticut',
            'DE' => 'Delaware',
            'FL' => 'Florida',
            'GA' => 'Georgia',
            'HI' => 'Hawaii',
            'IA' => 'Iowa',
            'ID' => 'Idaho',
            'IL' => 'Illinois',
            'IN' => 'Indiana',
            'KS' => 'Kansas',
            'KY' => 'Kentucky',
            'LA' => 'Louisiana',
            'MA' => 'Massachusetts',
            'MD' => 'Maryland',
            'ME' => 'Maine',
            'MI' => 'Michigan',
            'MN' => 'Minnesota',
            'MO' => 'Missouri',
            'MS' => 'Mississippi',
            'MT' => 'Montana',
            'NC' => 'North Carolina',
            'ND' => 'North Dakota',
            'NE' => 'Nebraska',
            'NH' => 'New Hampshire',
            'NJ' => 'New Jersey',
            'NM' => 'New Mexico',
            'NV' => 'Nevada',
            'NY' => 'New York',
            'OH' => 'Ohio',
            'OK' => 'Oklahoma',
            'OR' => 'Oregon',
            'PA' => 'Pennsylvania',
            'RI' => 'Rhode Island',
            'SC' => 'South Carolina',
            'SD' => 'South Dakota',
            'TN' => 'Tennessee',
            'TX' => 'Texas',
            'UT' => 'Utah',
            'VA' => 'Virginia',
            'VT' => 'Vermont',
            'WA' => 'Washington',
            'WI' => 'Wisconsin',
            'WV' => 'West Virginia',
            'WY' => 'Wyoming',
        );
    }

    public function getPhoneCodes($data)
    {
        $codes = array(
            'AF' => '93',
            'AL' => '355',
            'DZ' => '213',
            'AS' => '1-684',
            'AD' => '376',
            'AO' => '244',
            'AQ' => '244',
            'AI' => '1-264',
            'AG' => '1-268',
            'AR' => '54',
            'AM' => '7',
            'AW' => '297',
            'AU' => '61',
            'AT' => '43',
            'AZ' => '994',
            'BS' => '1-242',
            'BH' => '973',
            'BD' => '880',
            'BB' => '1-246',
            'BY' => '375',
            'BE' => '32',
            'BZ' => '501',
            'BJ' => '229',
            'BM' => '1-441',
            'BT' => '975',
            'BO' => '591',
            'BQ' => '599',
            'BA' => '387',
            'BW' => '267',
            'BR' => '55',
            'BN' => '673',
            'BG' => '359',
            'BF' => '226',
            'BI' => '257',
            'BV' => '257',
            'KH' => '855',
            'CM' => '237',
            'CA' => '1',
            'CV' => '238',
            'KY' => '1-345',
            'CF' => '236',
            'TD' => '235',
            'CL' => '56',
            'CN' => '86',
            'CO' => '57',
            'KM' => '269',
            'CG' => '242',
            'CD' => '243',
            'CK' => '682',
            'CR' => '506',
            'CI' => '225',
            'HR' => '385',
            'CU' => '53',
            'CW' => '599',
            'CY' => '357',
            'CZ' => '420',
            'CC' => '11',
            'CX' => '61',
            'DK' => '45',
            'DJ' => '253',
            'DM' => '1-767',
            'DO' => '1-809',
            'EC' => '593',
            'EG' => '20',
            'SV' => '503',
            'GQ' => '240',
            'ER' => '291',
            'EE' => '372',
            'ET' => '251',
            'FO' => '298',
            'FK' => '500',
            'FJ' => '679',
            'FI' => '358',
            'FR' => '33',
            'GF' => '594',
            'PF' => '689',
            'GA' => '241',
            'GM' => '220',
            'DE' => '49',
            'GH' => '233',
            'GI' => '350',
            'GB' => '44',
            'GR' => '30',
            'GL' => '299',
            'GD' => '1-473',
            'GP' => '590',
            'GU' => '1-671',
            'GT' => '502',
            'GN' => '224',
            'GW' => '245',
            'GY' => '592',
            'HT' => '509',
            'HN' => '504',
            'HK' => '852',
            'HU' => '36',
            'IS' => '354',
            'IN' => '91',
            'ID' => '62',
            'IR' => '98',
            'IQ' => '964',
            'IE' => '353',
            'IL' => '972',
            'IT' => '39',
            'JM' => '1-876',
            'JP' => '81',
            'JO' => '962',
            'KZ' => '7',
            'KE' => '254',
            'KI' => '686',
            'KP' => '850',
            'KR' => '82',
            'KW' => '965',
            'KG' => '996',
            'LA' => '856',
            'LV' => '371',
            'LB' => '961',
            'LS' => '266',
            'LR' => '231',
            'LY' => '218',
            'LI' => '423',
            'LT' => '370',
            'LU' => '352',
            'MO' => '853',
            'MK' => '389',
            'MG' => '261',
            'MW' => '265',
            'MY' => '60',
            'MV' => '960',
            'ML' => '223',
            'MT' => '356',
            'MH' => '692',
            'MQ' => '596',
            'MR' => '222',
            'MU' => '230',
            'YT' => '269',
            'MX' => '52',
            'FM' => '691',
            'MD' => '373',
            'MC' => '377',
            'MN' => '976',
            'ME' => '382',
            'MS' => '1-664',
            'MA' => '212',
            'MZ' => '258',
            'MM' => '95',
            'NA' => '264',
            'NR' => '674',
            'NP' => '977',
            'NL' => '31',
            'AN' => '599',
            'NC' => '687',
            'NZ' => '64',
            'NI' => '505',
            'NE' => '227',
            'NG' => '234',
            'NU' => '683',
            'MP' => '1-670',
            'NO' => '47',
            'OM' => '968',
            'PK' => '92',
            'PW' => '680',
            'PS' => '970',
            'PA' => '507',
            'PG' => '675',
            'PY' => '595',
            'PE' => '51',
            'PH' => '63',
            'PL' => '48',
            'PT' => '351',
            'PR' => '1',
            'QA' => '974',
            'RE' => '262',
            'RO' => '40',
            'RU' => '7',
            'RW' => '250',
            'SH' => '290',
            'KN' => '1-869',
            'LC' => '1-758',
            'PM' => '508',
            'VC' => '1-784',
            'WS' => '685',
            'SM' => '378',
            'ST' => '239',
            'SA' => '966',
            'SN' => '221',
            'RS' => '381',
            'SC' => '248',
            'SL' => '232',
            'SG' => '65',
            'SX' => '599',
            'SK' => '421',
            'SI' => '386',
            'SB' => '677',
            'SO' => '252',
            'ZA' => '27',
            'ES' => '34',
            'LK' => '94',
            'SD' => '249',
            'SR' => '597',
            'SZ' => '268',
            'SE' => '46',
            'CH' => '41',
            'SY' => '963',
            'TW' => '886',
            'TJ' => '992',
            'TZ' => '255',
            'TH' => '66',
            'TL' => '670',
            'TG' => '228',
            'TK' => '690',
            'TO' => '676',
            'TT' => '1-868',
            'TP' => '670',
            'TN' => '216',
            'TR' => '90',
            'TM' => '993',
            'TC' => '1-649',
            'TV' => '688',
            'UG' => '256',
            'UA' => '380',
            'NF' => '',
            'PN' => '',
            'EH' => '',
            'YU' => '',
            'EL' => '30',
            'AE' => '971',
            'GE' => '995',
            'US' => '1',
            'UY' => '598',
            'UZ' => '998',
            'VU' => '678',
            'VA' => '379',
            'VE' => '58',
            'VN' => '84',
            'VG' => '1-284',
            'VI' => '1-340',
            'WF' => '681',
            'YE' => '967',
            'ZM' => '260',
            'ZW' => '263',
        );

        if(isset($data['country'])) {
            if(array_key_exists($data['country'], $codes)) {
                return $codes[$data['country']];
            } else {
                throw new \Box_Exception('Country :code phone code is not registered', array(':code'=>$data['country']));
            }
        }

        return array(
            '7940' => 'Abkhazia +7940',
            '99544' => 'Abkhazia +99544',
            '93' => 'Afghanistan +93',
            '355' => 'Albania +355',
            '213' => 'Algeria +213',
            '1684' => 'American Samoa +1684',
            '376' => 'Andorra +376',
            '244' => 'Angola +244',
            '1264' => 'Anguilla +1264',
            '1268' => 'Antigua and Barbuda +1268',
            '54' => 'Argentina +54',
            '374' => 'Armenia +374',
            '297' => 'Aruba +297',
            '247' => 'Ascension +247',
            '61' => 'Australia +61',
            '43' => 'Austria +43',
            '994' => 'Azerbaijan +994',
            '1242' => 'Bahamas +1242',
            '973' => 'Bahrain +973',
            '880' => 'Bangladesh +880',
            '1246' => 'Barbados +1246',
            '375' => 'Belarus +375',
            '32' => 'Belgium +32',
            '501' => 'Belize +501',
            '229' => 'Benin +229',
            '1441' => 'Bermuda +1441',
            '975' => 'Bhutan +975',
            '591' => 'Bolivia +591',
            '387' => 'Bosnia and Herzegovina +387',
            '267' => 'Botswana +267',
            '55' => 'Brazil +55',
            '246' => 'British Indian Ocean Territory +246',
            '1284' => 'British Virgin Islands +1284',
            '673' => 'Brunei +673',
            '359' => 'Bulgaria +359',
            '226' => 'Burkina Faso +226',
            '257' => 'Burundi +257',
            '855' => 'Cambodia +855',
            '237' => 'Cameroon +237',
            '238' => 'Cape Verde +238',
            '1345' => 'Cayman Islands +1345',
            '236' => 'Central African Republic +236',
            '235' => 'Chad +235',
            '56' => 'Chile +56',
            '86' => 'China +86',
            '57' => 'Colombia +57',
            '269' => 'Comoros +269',
            '242' => 'Congo +242',
            '243' => 'Congo - Kinshasa +243',
            '682' => 'Cook Islands +682',
            '506' => 'Costa Rica +506',
            '385' => 'Croatia +385',
            '5399' => 'Cuba (Guantanamo Bay) +5399',
            '53' => 'Cuba +53',
            '599' => 'Curaçao +599',
            '357' => 'Cyprus +357',
            '420' => 'Czech Republic +420',
            '45' => 'Denmark +45',
            '253' => 'Djibouti +253',
            '1767' => 'Dominica +1767',
            '1809' => 'Dominican Republic +1809',
            '1829' => 'Dominican Republic +1829',
            '1849' => 'Dominican Republic +1849',
            '88213' => 'EMSAT (Mobile Satellite service) +88213',
            '670' => 'East Timor +670',
            '593' => 'Ecuador+593',
            '20' => 'Egypt +20',
            '503' => 'El Salvador +503',
            '8812' => 'Ellipso (Mobile Satellite service) +8812',
            '8813' => 'Ellipso (Mobile Satellite service) +8813',
            '240' => 'Equatorial Guinea +240',
            '291' => 'Eritrea +291',
            '372' => 'Estonia +372',
            '251' => 'Ethiopia +251',
            '500' => 'Falkland Islands +500',
            '298' => 'Faroe Islands +298',
            '679' => 'Fiji +679',
            '358' => 'Finland +358',
            '33' => 'France +33',
            '594' => 'French Guiana +594',
            '689' => 'French Polynesia +689',
            '241' => 'Gabon +241',
            '220' => 'Gambia +220',
            '995' => 'Georgia +995',
            '49' => 'Germany +49',
            '233' => 'Ghana +233',
            '350' => 'Gibraltar +350',
            '881' => 'Global Mobile Satellite System (GMSS) +881',
            '8818' => 'Globalstar (Mobile Satellite Service) +8818',
            '8819' => 'Globalstar (Mobile Satellite Service) +8819',
            '30' => 'Greece +30',
            '299' => 'Greenland +299',
            '1473' => 'Grenada +1473',
            '1671' => 'Guam +1671',
            '502' => 'Guatemala +502',
            '224' => 'Guinea +224',
            '245' => 'Guinea-Bissau +245',
            '592' => 'Guyana +592',
            '509' => 'Haiti +509',
            '504' => 'Honduras +504',
            '852' => 'Hong Kong SAR China +852',
            '36' => 'Hungary +36',
            '8810' => 'ICO Global (Mobile Satellite Service) +8810',
            '8811' => 'ICO Global (Mobile Satellite Service) +8811',
            '354' => 'Iceland +354',
            '91' => 'India +91',
            '62' => 'Indonesia +62',
            '870' => 'Inmarsat SNAC +870',
            '800' => 'International Freephone Service +800',
            '808' => 'International Shared Cost Service (ISCS) +808',
            '964' => 'Iraq +964',
            '353' => 'Ireland +353',
            '8816' => 'Iridium (Mobile Satellite service) +8816',
            '8817' => 'Iridium (Mobile Satellite service) +8817',
            '972' => 'Israel +972',
            '39' => 'Italy +39',
            '225' => 'Ivory Coast +225',
            '1876' => 'Jamaica +1876',
            '81' => 'Japan +81',
            '962' => 'Jordan +962',
            '76' => 'Kazakhstan +76',
            '77' => 'Kazakhstan +77',
            '254' => 'Kenya +254',
            '686' => 'Kiribati +686',
            '965' => 'Kuwait +965',
            '996' => 'Kyrgyzstan +996',
            '856' => 'Laos +856',
            '371' => 'Latvia +371',
            '961' => 'Lebanon +961',
            '266' => 'Lesotho +266',
            '231' => 'Liberia +231',
            '218' => 'Libya +218',
            '423' => 'Liechtenstein +423',
            '370' => 'Lithuania +370',
            '352' => 'Luxembourg +352',
            '853' => 'Macau SAR China +853',
            '389' => 'Macedonia +389',
            '261' => 'Madagascar +261',
            '265' => 'Malawi +265',
            '60' => 'Malaysia +60',
            '960' => 'Maldives +960',
            '223' => 'Mali +223',
            '356' => 'Malta +356',
            '692' => 'Marshall Islands +692',
            '596' => 'Martinique +596',
            '222' => 'Mauritania +222',
            '230' => 'Mauritius +230',
            '52' => 'Mexico +52',
            '691' => 'Micronesia +691',
            '373' => 'Moldova +373',
            '377' => 'Monaco +377',
            '976' => 'Mongolia +976',
            '382' => 'Montenegro +382',
            '1664' => 'Montserrat +1664',
            '212' => 'Morocco +212',
            '258' => 'Mozambique +258',
            '95' => 'Myanmar +95',
            '264' => 'Namibia +264',
            '674' => 'Nauru +674',
            '977' => 'Nepal +977',
            '31' => 'Netherlands +31',
            '687' => 'New Caledonia +687',
            '64' => 'New Zealand +64',
            '505' => 'Nicaragua +505',
            '227' => 'Niger +227',
            '234' => 'Nigeria +234',
            '683' => 'Niue +683',
            '672' => 'Norfolk Island +672',
            '850' => 'North Korea +850',
            '1670' => 'Northern Mariana Islands +1670',
            '47' => 'Norway +47',
            '968' => 'Oman +968',
            '92' => 'Pakistan +92',
            '680' => 'Palau +680',
            '970' => 'Palestinian Territory +970',
            '507' => 'Panama +507',
            '675' => 'Papua New Guinea +675',
            '595' => 'Paraguay +595',
            '51' => 'Peru +51',
            '63' => 'Philippines +63',
            '48' => 'Poland +48',
            '351' => 'Portugal +351',
            '1787' => 'Puerto Rico +1787',
            '1939' => 'Puerto Rico +1939',
            '974' => 'Qatar +974',
            '40' => 'Romania +40',
            '7' => 'Russia +7',
            '250' => 'Rwanda +250',
            '262' => 'Réunion +262',
            '290' => 'Saint Helena +290',
            '1869' => 'Saint Kitts and Nevis +1869',
            '1758' => 'Saint Lucia +1758',
            '590' => 'Saint Martin +590',
            '508' => 'Saint Pierre and Miquelon +508',
            '1784' => 'Saint Vincent and the Grenadines +1784',
            '685' => 'Samoa +685',
            '378' => 'San Marino +378',
            '966' => 'Saudi Arabia +966',
            '221' => 'Senegal +221',
            '381' => 'Serbia +381',
            '248' => 'Seychelles +248',
            '232' => 'Sierra Leone +232',
            '65' => 'Singapore +65',
            '1721' => 'Sint Maarten (from May 31, 2010) +1721',
            '421' => 'Slovakia +421',
            '386' => 'Slovenia +386',
            '677' => 'Solomon Islands +677',
            '252' => 'Somalia +252',
            '27' => 'South Africa +27',
            '82' => 'South Korea +82',
            '34' => 'Spain +34',
            '94' => 'Sri Lanka +94',
            '249' => 'Sudan +249',
            '597' => 'Suriname +597',
            '268' => 'Swaziland +268',
            '46' => 'Sweden +46',
            '41' => 'Switzerland +41',
            '963' => 'Syria +963',
            '239' => 'São Tomé and Príncipe +239',
            '886' => 'Taiwan +886',
            '992' => 'Tajikistan +992',
            '66' => 'Thailand +66',
            '88216' => 'Thuraya (Mobile Satellite service) +88216',
            '228' => 'Togo +228',
            '690' => 'Tokelau +690',
            '676' => 'Tonga +676',
            '1868' => 'Trinidad and Tobago +1868',
            '216' => 'Tunisia +216',
            '90' => 'Turkey +90',
            '993' => 'Turkmenistan +993',
            '1649' => 'Turks and Caicos Islands +1649',
            '688' => 'Tuvalu +688',
            '1340' => 'U.S. Virgin Islands +1340',
            '256' => 'Uganda +256',
            '380' => 'Ukraine +380',
            '971' => 'United Arab Emirates +971',
            '878' => 'Universal Personal Telecommunications (UPT) +878',
            '598' => 'Uruguay +598',
            '998' => 'Uzbekistan +998',
            '678' => 'Vanuatu +678',
            '379' => 'Vatican +379',
            '39066' => 'Vatican +39066',
            '58' => 'Venezuela +58',
            '84' => 'Vietnam +84',
            '1808' => 'Wake Island +1808',
            '681' => 'Wallis and Futuna +681',
            '967' => 'Yemen +967',
            '260' => 'Zambia +260',
            '255' => 'Zanzibar +255',
            '263' => 'Zimbabwe +263',
        );
    }

    /**
     * Call this method in API to check limits for entries
     */
    public function checkLimits($model, $limit = 2)
    {

    }

    public function getNameservers()
    {
        $query = "SELECT param, value FROM setting WHERE param IN ('nameserver_1', 'nameserver_2', 'nameserver_3', 'nameserver_4')";

        return $this->di['db']->getAssoc($query);
    }

    public function getVersion()
    {
        return \Box_Version::VERSION;
    }

    public function getPendingMessages()
    {
        $messages = $this->di['session']->get('pending_messages');

        if (!is_array($messages)){
            return array();
        }

        return $messages;
    }

    public function setPendingMessage($msg)
    {
        $messages = $this->getPendingMessages();
        array_push($messages, $msg);
        $this->di['session']->set('pending_messages', $messages);
        return true;
    }

    public function clearPendingMessages()
    {
        $this->di['session']->delete('pending_messages');
        return true;
    }
}
