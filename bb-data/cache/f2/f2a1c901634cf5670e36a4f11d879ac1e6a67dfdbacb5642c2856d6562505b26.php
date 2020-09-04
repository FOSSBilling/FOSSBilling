<?php

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Extension\SandboxExtension;
use Twig\Markup;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Source;
use Twig\Template;

/* mod_system_settings.phtml */
class __TwigTemplate_fc6f10561c72ec214f5e34d9f791f20115d4329b4bc369d5682bcb94ea6a3e7e extends \Twig\Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->blocks = [
            'meta_title' => [$this, 'block_meta_title'],
            'breadcrumbs' => [$this, 'block_breadcrumbs'],
            'content' => [$this, 'block_content'],
            'head' => [$this, 'block_head'],
        ];
    }

    protected function doGetParent(array $context)
    {
        // line 1
        return $this->loadTemplate(((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ajax", [], "any", false, false, false, 1)) ? ("layout_blank.phtml") : ("layout_default.phtml")), "mod_system_settings.phtml", 1);
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 2
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_system_settings.phtml", 2)->unwrap();
        // line 4
        $context["active_menu"] = "system";
        // line 1
        $this->getParent($context)->display($context, array_merge($this->blocks, $blocks));
    }

    // line 3
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo gettext("System settings");
    }

    // line 6
    public function block_breadcrumbs($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 7
        echo "<ul>
    <li class=\"firstB\"><a href=\"";
        // line 8
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/");
        echo "\">";
        echo gettext("Home");
        echo "</a></li>
    <li><a href=\"";
        // line 9
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("system");
        echo "\">";
        echo gettext("Settings");
        echo "</a></li>
    <li class=\"lastB\">";
        // line 10
        echo gettext("System settings");
        echo "</li>
</ul>
";
    }

    // line 14
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 15
        echo "
";
        // line 16
        $context["new_params"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "extension_config_get", [0 => ["ext" => "mod_system"]], "method", false, false, false, 16);
        // line 17
        $context["params"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "system_get_params", [], "any", false, false, false, 17);
        // line 18
        echo "
<div class=\"widget simpleTabs\">

    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">";
        // line 22
        echo gettext("Company details");
        echo "</a></li>
        <li><a href=\"#tab-ftp\">";
        // line 23
        echo gettext("FTP layer");
        echo "</a></li>
        <li><a href=\"#tab-countries\">";
        // line 24
        echo gettext("Countries");
        echo "</a></li>
        <li><a href=\"#tab-cache\">";
        // line 25
        echo gettext("Cache");
        echo "</a></li>
    </ul>
    
    <div class=\"tabs_container\">
        <div class=\"fix\"></div>
        <div id=\"tab-index\" class=\"tab_content nopadding\">

            <form method=\"post\" action=\"";
        // line 32
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/system/update_params");
        echo "\" class=\"mainForm api-form\" data-api-msg=\"Company updated\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>";
        // line 35
        echo gettext("Name");
        echo "</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"company_name\" value=\"";
        // line 37
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["params"] ?? null), "company_name", [], "any", false, false, false, 37), "html", null, true);
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 43
        echo gettext("Email");
        echo "</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"company_email\" value=\"";
        // line 45
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["params"] ?? null), "company_email", [], "any", false, false, false, 45), "html", null, true);
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 51
        echo gettext("Phone");
        echo "</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"company_tel\" value=\"";
        // line 53
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["params"] ?? null), "company_tel", [], "any", false, false, false, 53), "html", null, true);
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 59
        echo gettext("Address");
        echo "</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"company_address_1\" value=\"";
        // line 61
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["params"] ?? null), "company_address_1", [], "any", false, false, false, 61), "html", null, true);
        echo "\" placeholder=\"Address line 1\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem noborder\">
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"company_address_2\" value=\"";
        // line 68
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["params"] ?? null), "company_address_2", [], "any", false, false, false, 68), "html", null, true);
        echo "\" placeholder=\"Address line 2\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem noborder\">
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"company_address_3\" value=\"";
        // line 75
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["params"] ?? null), "company_address_3", [], "any", false, false, false, 75), "html", null, true);
        echo "\" placeholder=\"Address line 3\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label class=\"topLabel\">";
        // line 81
        echo gettext("Logo URL");
        echo "</label>
                        <div class=\"formBottom\">
                            <input type=\"text\" name=\"company_logo\" value=\"";
        // line 83
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["params"] ?? null), "company_logo", [], "any", false, false, false, 83), "html", null, true);
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                       <label class=\"topLabel\">";
        // line 89
        echo gettext("Company number, chamber of commerce number");
        echo "</label>
                        <div class=\"formBottom\">
                            <input type=\"text\" name=\"company_number\" value=\"";
        // line 91
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["params"] ?? null), "company_number", [], "any", false, false, false, 91), "html", null, true);
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label class=\"topLabel\">";
        // line 97
        echo gettext("VAT number");
        echo "</label>
                        <div class=\"formBottom\">
                            <input type=\"text\" name=\"company_vat_number\" value=\"";
        // line 99
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["params"] ?? null), "company_vat_number", [], "any", false, false, false, 99), "html", null, true);
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label class=\"topLabel\">";
        // line 105
        echo gettext("Bank Account number");
        echo "</label>
                        <div class=\"formBottom\">
                            <input type=\"text\" name=\"company_account_number\" value=\"";
        // line 107
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["params"] ?? null), "company_account_number", [], "any", false, false, false, 107), "html", null, true);
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label class=\"topLabel\">";
        // line 113
        echo gettext("Signature");
        echo "</label>
                        <div class=\"formBottom\">
                            <textarea name=\"company_signature\" rows=\"5\" cols=\"5\">";
        // line 115
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["params"] ?? null), "company_signature", [], "any", false, false, false, 115), "html", null, true);
        echo "</textarea>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                <input type=\"submit\" value=\"";
        // line 120
        echo gettext("Update settings");
        echo "\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>

            <form method=\"post\" action=\"";
        // line 124
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/system/update_params");
        echo "\" class=\"mainForm api-form\" data-api-msg=\"Company updated\">
                <fieldset>
                    <legend>";
        // line 126
        echo gettext("Company terms of service, legal regulation");
        echo "</legend>
                    <textarea name=\"company_tos\" rows=\"5\" cols=\"5\" class=\"bb-textarea\">";
        // line 127
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["params"] ?? null), "company_tos", [], "any", false, false, false, 127), "html", null, true);
        echo "</textarea>
                </fieldset>

                <fieldset>
                    <legend>";
        // line 131
        echo gettext("Company privacy policy");
        echo "</legend>
                    <textarea name=\"company_privacy_policy\" rows=\"5\" cols=\"5\" class=\"bb-textarea\">";
        // line 132
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["params"] ?? null), "company_privacy_policy", [], "any", false, false, false, 132), "html", null, true);
        echo "</textarea>
                </fieldset>

                <fieldset>
                    <legend>";
        // line 136
        echo gettext("Notes");
        echo "</legend>
                    <textarea name=\"company_note\" rows=\"5\" cols=\"5\" class=\"bb-textarea\">";
        // line 137
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["params"] ?? null), "company_note", [], "any", false, false, false, 137), "html", null, true);
        echo "</textarea>
                </fieldset>

                <fieldset>
                    <input type=\"submit\" value=\"";
        // line 141
        echo gettext("Update settings");
        echo "\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
            
        </div>
        
        <div id=\"tab-countries\" class=\"tab_content nopadding\">
            <form method=\"post\" action=\"";
        // line 148
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/extension/config_save");
        echo "\" class=\"mainForm api-form\" data-api-msg=\"";
        echo gettext("Countries updated");
        echo "\">
                <fieldset>
";
        // line 150
        $context["default_countries"] = ('' === $tmp = "US=United States
AF=Afghanistan
AL=Albania
DZ=Algeria
AS=American Samoa
AD=Andorra
AO=Angola
AI=Anguilla
AQ=Antarctica
AG=Antigua and Barbuda
AR=Argentina
AM=Armenia
AW=Aruba
AU=Australia
AT=Austria
AZ=Azerbaijan
BS=Bahamas
BH=Bahrain
BD=Bangladesh
BB=Barbados
BY=Belarus
BE=Belgium
BZ=Belize
BJ=Benin
BM=Bermuda
BT=Bhutan
BO=Bolivia
BA=Bosnia and Herzegovina
BW=Botswana
BR=Brazil
BN=Brunei
BG=Bulgaria
BF=Burkina Faso
BI=Burundi
KH=Cambodia
CM=Cameroon
CA=Canada
CV=Cape Verde
KY=Cayman Islands
CF=Central African Republic
TD=Chad
CL=Chile
CN=China
CX=Christmas Island
CC=Cocos (Keeling) Islands
CO=Colombia
KM=Comoros
CG=Congo - Brazzaville
CD=Congo - Kinshasa
CK=Cook Islands
CR=Costa Rica
CI=Cote D'Ivoire
HR=Croatia
CU=Cuba
CY=Cyprus
CZ=Czech Republic
DK=Denmark
DJ=Djibouti
DM=Dominica
DO=Dominican Republic
TP=East Timor
EC=Ecuador
EG=Egypt
SV=El Salvador
GQ=Equatorial Guinea
ER=Eritrea
EE=Estonia
ET=Ethiopia
FO=Faroe Islands
FJ=Fiji
FI=Finland
FR=France
GF=French Guiana
PF=French Polynesia
GA=Gabon
GB=United Kingdom
GM=Gambia
GE=Georgia
DE=Germany
GH=Ghana
GI=Gibraltar
GR=Greece
GL=Greenland
GD=Grenada
GP=Guadeloupe
GU=Guam
GT=Guatemala
GN=Guinea
GW=Guinea-Bissau
GY=Guyana
HT=Haiti
EL=Hellenic Republic (Greece)
HN=Honduras
HK=Hong Kong
HU=Hungary
IS=Iceland
IN=India
ID=Indonesia
IR=Iran
IQ=Iraq
IE=Ireland
IL=Israel
IT=Italy
JM=Jamaica
JP=Japan
JO=Jordan
KZ=Kazakhstan
KE=Kenya
KI=Kiribati
KW=Kuwait
KG=Kyrgyzstan
LA=Laos
LV=Latvia
LB=Lebanon
LS=Lesotho
LR=Liberia
LY=Libya
LI=Liechtenstein
LT=Lithuania
LU=Luxembourg
MO=Macau
MK=Macedonia
MG=Madagascar
MW=Malawi
MY=Malaysia
MV=Maldives
ML=Mali
MT=Malta
MH=Marshall Islands
MQ=Martinique
MR=Mauritania
MU=Mauritius
YT=Mayotte
MX=Mexico
MD=Moldova
MC=Monaco
MN=Mongolia
ME=Montenegro
MS=Montserrat
MA=Morocco
MZ=Mozambique
MM=Myanmar (Burma)
NA=Namibia
NR=Nauru
NP=Nepal
NL=Netherlands
AN=Netherlands Antilles
NC=New Caledonia
NZ=New Zealand
NI=Nicaragua
NE=Niger
NG=Nigeria
NU=Niue
NF=Norfolk Island
MP=Northern Mariana Islands
NO=Norway
OM=Oman
PK=Pakistan
PW=Palau
PA=Panama
PG=Papua New Guinea
PY=Paraguay
PE=Peru
PH=Philippines
PN=Pitcairn Islands
PL=Poland
PT=Portugal
PR=Puerto Rico
QA=Qatar
RE=Reunion
RO=Romania
RU=Russia
RW=Rwanda
KN=Saint Kitts And Nevis
LC=Saint Lucia
WS=Samoa
SM=San Marino
ST=Sao Tome And Principe
SA=Saudi Arabia
SN=Senegal
RS=Serbia
SC=Seychelles
SL=Sierra Leone
SG=Singapore
SK=Slovakia
SI=Slovenia
SB=Solomon Islands
SO=Somalia
ZA=South Africa
KR=South Korea
ES=Spain
LK=Sri Lanka
SH=St. Helena
PM=St. Pierre And Miquelon
SD=Sudan
SR=Suriname
SZ=Swaziland
SE=Sweden
CH=Switzerland
SY=Syria
TW=Taiwan
TJ=Tajikistan
TZ=Tanzania
TH=Thailand
TG=Togo
TK=Tokelau
TO=Tonga
TT=Trinidad and Tobago
TN=Tunisia
TR=Turkey
TM=Turkmenistan
TC=Turks and Caicos Islands
TV=Tuvalu
UG=Uganda
UA=Ukraine
AE=United Arab Emirates
UY=Uruguay
UZ=Uzbekistan
VU=Vanuatu
VA=Vatican City
VE=Venezuela
VN=Vietnam
VI=Virgin Islands (U.S.)
EH=Western Sahara
YE=Yemen
ZM=Zambia
ZW=Zimbabwe
") ? '' : new Markup($tmp, $this->env->getCharset());
        // line 379
        echo "                    <div class=\"rowElem noborder\">
                        <label class=\"topLabel\">";
        // line 380
        echo gettext("List of countries");
        echo "</label>
                        <div class=\"formBottom\">
                            <textarea name=\"countries\" cols=\"5\" rows=\"50\" placeholder=\"US=United States\">";
        // line 382
        echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["new_params"] ?? null), "countries", [], "any", true, true, false, 382)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["new_params"] ?? null), "countries", [], "any", false, false, false, 382), ($context["default_countries"] ?? null))) : (($context["default_countries"] ?? null))), "html", null, true);
        echo "</textarea>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <input type=\"submit\" value=\"";
        // line 387
        echo gettext("Update settings");
        echo "\" class=\"greyishBtn submitForm\" />
                    <input type=\"hidden\" name=\"ext\" value=\"mod_system\" />
                </fieldset>
            </form>
        </div>

        <div id=\"tab-cache\" class=\"tab_content nopadding\">

            <a href=\"";
        // line 395
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/system/clear_cache");
        echo "\" class=\"api-link\" data-api-msg=\"";
        echo gettext("Cache folder cleared");
        echo "\">";
        echo gettext("Invalidate cache");
        echo "</a>

        </div>

        <div id=\"tab-ftp\" class=\"tab_content nopadding\">
            <div class=\"help\">
                <h3>";
        // line 401
        echo gettext("FTP layer for BoxBilling");
        echo "</h3>
                <p>";
        // line 402
        echo gettext("FTP is used to manage BoxBilling modules and updates.");
        echo "</p>
            </div>

            <form method=\"post\" action=\"";
        // line 405
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/system/update_params");
        echo "\" class=\"mainForm api-form\" data-api-msg=\"";
        echo gettext("FTP settings updated");
        echo "\">
                <fieldset>
                <div class=\"rowElem\">
                    <label class=\"topLabel\">";
        // line 408
        echo gettext("<strong>FTP Hostname</strong> - can be a numerical URL 127.0.0.1 is the default address for a locally hosted server, it might be this, it might be the full URL as my example above with the File Transfer Protocol (FTP) prefix, it might be the http:// protocol it might be SFTP (secure FTP) even secure http (https). Your host will be able to advise you on the correct format.");
        echo "</label>
                    <div class=\"formBottom\">
                        <input type=\"text\" name=\"ftp_host\" value=\"";
        // line 410
        echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["params"] ?? null), "ftp_host", [], "any", true, true, false, 410)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["params"] ?? null), "ftp_host", [], "any", false, false, false, 410), "localhost")) : ("localhost")), "html", null, true);
        echo "\"/>
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label class=\"topLabel\">";
        // line 416
        echo gettext("<strong>FTP port</strong> - is nearly always 21 unless the host has changed this they will have notified you of this. Secure FTP generally uses port 22.");
        echo "</label>
                    <div class=\"formBottom\">
                        <input type=\"text\" name=\"ftp_port\" value=\"";
        // line 418
        echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["params"] ?? null), "ftp_port", [], "any", true, true, false, 418)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["params"] ?? null), "ftp_port", [], "any", false, false, false, 418), 21)) : (21)), "html", null, true);
        echo "\"/>
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label class=\"topLabel\">";
        // line 424
        echo gettext("<strong>FTP user</strong> - In most cases, this is the specific user name your host has set up for you to access your Web site via FTP- It is the name you use in your FTP client. This could be an alphanumeric, it could be your name, even your e-mail address, If you don't know this speak to your host. You may have the facility on your Web account to set up additional FTP users if that is the case (and it's more secure) you are recommended to do this");
        echo "</label>
                    <div class=\"formBottom\">
                        <input type=\"text\" name=\"ftp_user\" value=\"";
        // line 426
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["params"] ?? null), "ftp_user", [], "any", false, false, false, 426), "html", null, true);
        echo "\"/>
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label class=\"topLabel\">";
        // line 432
        echo gettext("<strong>FTP password</strong> - this is the password you enter in your FTP client.");
        echo "</label>
                    <div class=\"formBottom\">
                        <input type=\"password\" name=\"ftp_password\" value=\"";
        // line 434
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["params"] ?? null), "ftp_password", [], "any", false, false, false, 434), "html", null, true);
        echo "\"/>
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label class=\"topLabel\">";
        // line 440
        echo gettext("<strong>FTP root</strong> - This is generally the directory in which BoxBilling is installed. This setting is very important to be setup correctly as it very specifically depends on the Host server settings. In can be just / BUT it might be htdocs/boxbilling, public_html/, /public_html/boxbilling or other. It is a variable that BoxBilling has absolutely no control over. You will need to clarify this with your host.");
        echo "</label>
                    <div class=\"formBottom\">
                        <input type=\"text\" name=\"ftp_root\" value=\"";
        // line 442
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["params"] ?? null), "ftp_root", [], "any", false, false, false, 442), "html", null, true);
        echo "\"/>
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <input type=\"submit\" value=\"";
        // line 447
        echo gettext("Update settings");
        echo "\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>

    </div>
</div>

";
    }

    // line 457
    public function block_head($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo twig_call_macro($macros["mf"], "macro_bb_editor", [".bb-textarea"], 457, $context, $this->getSourceContext());
    }

    public function getTemplateName()
    {
        return "mod_system_settings.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  707 => 457,  694 => 447,  686 => 442,  681 => 440,  672 => 434,  667 => 432,  658 => 426,  653 => 424,  644 => 418,  639 => 416,  630 => 410,  625 => 408,  617 => 405,  611 => 402,  607 => 401,  594 => 395,  583 => 387,  575 => 382,  570 => 380,  567 => 379,  338 => 150,  331 => 148,  321 => 141,  314 => 137,  310 => 136,  303 => 132,  299 => 131,  292 => 127,  288 => 126,  283 => 124,  276 => 120,  268 => 115,  263 => 113,  254 => 107,  249 => 105,  240 => 99,  235 => 97,  226 => 91,  221 => 89,  212 => 83,  207 => 81,  198 => 75,  188 => 68,  178 => 61,  173 => 59,  164 => 53,  159 => 51,  150 => 45,  145 => 43,  136 => 37,  131 => 35,  125 => 32,  115 => 25,  111 => 24,  107 => 23,  103 => 22,  97 => 18,  95 => 17,  93 => 16,  90 => 15,  86 => 14,  79 => 10,  73 => 9,  67 => 8,  64 => 7,  60 => 6,  53 => 3,  49 => 1,  47 => 4,  45 => 2,  38 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}
{% import \"macro_functions.phtml\" as mf %}
{% block meta_title %}{% trans 'System settings' %}{% endblock %}
{% set active_menu = 'system' %}

{% block breadcrumbs %}
<ul>
    <li class=\"firstB\"><a href=\"{{ '/'|alink }}\">{% trans 'Home' %}</a></li>
    <li><a href=\"{{ 'system'|alink }}\">{% trans 'Settings' %}</a></li>
    <li class=\"lastB\">{% trans 'System settings' %}</li>
</ul>
{% endblock %}

{% block content %}

{% set new_params = admin.extension_config_get({\"ext\":\"mod_system\"}) %}
{% set params = admin.system_get_params %}

<div class=\"widget simpleTabs\">

    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">{% trans 'Company details' %}</a></li>
        <li><a href=\"#tab-ftp\">{% trans 'FTP layer' %}</a></li>
        <li><a href=\"#tab-countries\">{% trans 'Countries' %}</a></li>
        <li><a href=\"#tab-cache\">{% trans 'Cache' %}</a></li>
    </ul>
    
    <div class=\"tabs_container\">
        <div class=\"fix\"></div>
        <div id=\"tab-index\" class=\"tab_content nopadding\">

            <form method=\"post\" action=\"{{ 'api/admin/system/update_params'|link }}\" class=\"mainForm api-form\" data-api-msg=\"Company updated\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>{% trans 'Name' %}</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"company_name\" value=\"{{params.company_name}}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Email' %}</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"company_email\" value=\"{{params.company_email}}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Phone' %}</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"company_tel\" value=\"{{params.company_tel}}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Address' %}</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"company_address_1\" value=\"{{params.company_address_1}}\" placeholder=\"Address line 1\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem noborder\">
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"company_address_2\" value=\"{{params.company_address_2}}\" placeholder=\"Address line 2\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem noborder\">
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"company_address_3\" value=\"{{params.company_address_3}}\" placeholder=\"Address line 3\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label class=\"topLabel\">{% trans 'Logo URL' %}</label>
                        <div class=\"formBottom\">
                            <input type=\"text\" name=\"company_logo\" value=\"{{params.company_logo}}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                       <label class=\"topLabel\">{% trans 'Company number, chamber of commerce number' %}</label>
                        <div class=\"formBottom\">
                            <input type=\"text\" name=\"company_number\" value=\"{{params.company_number}}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label class=\"topLabel\">{% trans 'VAT number' %}</label>
                        <div class=\"formBottom\">
                            <input type=\"text\" name=\"company_vat_number\" value=\"{{params.company_vat_number}}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label class=\"topLabel\">{% trans 'Bank Account number' %}</label>
                        <div class=\"formBottom\">
                            <input type=\"text\" name=\"company_account_number\" value=\"{{params.company_account_number}}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label class=\"topLabel\">{% trans 'Signature' %}</label>
                        <div class=\"formBottom\">
                            <textarea name=\"company_signature\" rows=\"5\" cols=\"5\">{{params.company_signature}}</textarea>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                <input type=\"submit\" value=\"{% trans 'Update settings' %}\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>

            <form method=\"post\" action=\"{{ 'api/admin/system/update_params'|link }}\" class=\"mainForm api-form\" data-api-msg=\"Company updated\">
                <fieldset>
                    <legend>{% trans 'Company terms of service, legal regulation' %}</legend>
                    <textarea name=\"company_tos\" rows=\"5\" cols=\"5\" class=\"bb-textarea\">{{params.company_tos}}</textarea>
                </fieldset>

                <fieldset>
                    <legend>{% trans 'Company privacy policy' %}</legend>
                    <textarea name=\"company_privacy_policy\" rows=\"5\" cols=\"5\" class=\"bb-textarea\">{{params.company_privacy_policy}}</textarea>
                </fieldset>

                <fieldset>
                    <legend>{% trans 'Notes' %}</legend>
                    <textarea name=\"company_note\" rows=\"5\" cols=\"5\" class=\"bb-textarea\">{{params.company_note}}</textarea>
                </fieldset>

                <fieldset>
                    <input type=\"submit\" value=\"{% trans 'Update settings' %}\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
            
        </div>
        
        <div id=\"tab-countries\" class=\"tab_content nopadding\">
            <form method=\"post\" action=\"{{ 'api/admin/extension/config_save'|link }}\" class=\"mainForm api-form\" data-api-msg=\"{% trans 'Countries updated' %}\">
                <fieldset>
{% set default_countries %}
US=United States
AF=Afghanistan
AL=Albania
DZ=Algeria
AS=American Samoa
AD=Andorra
AO=Angola
AI=Anguilla
AQ=Antarctica
AG=Antigua and Barbuda
AR=Argentina
AM=Armenia
AW=Aruba
AU=Australia
AT=Austria
AZ=Azerbaijan
BS=Bahamas
BH=Bahrain
BD=Bangladesh
BB=Barbados
BY=Belarus
BE=Belgium
BZ=Belize
BJ=Benin
BM=Bermuda
BT=Bhutan
BO=Bolivia
BA=Bosnia and Herzegovina
BW=Botswana
BR=Brazil
BN=Brunei
BG=Bulgaria
BF=Burkina Faso
BI=Burundi
KH=Cambodia
CM=Cameroon
CA=Canada
CV=Cape Verde
KY=Cayman Islands
CF=Central African Republic
TD=Chad
CL=Chile
CN=China
CX=Christmas Island
CC=Cocos (Keeling) Islands
CO=Colombia
KM=Comoros
CG=Congo - Brazzaville
CD=Congo - Kinshasa
CK=Cook Islands
CR=Costa Rica
CI=Cote D'Ivoire
HR=Croatia
CU=Cuba
CY=Cyprus
CZ=Czech Republic
DK=Denmark
DJ=Djibouti
DM=Dominica
DO=Dominican Republic
TP=East Timor
EC=Ecuador
EG=Egypt
SV=El Salvador
GQ=Equatorial Guinea
ER=Eritrea
EE=Estonia
ET=Ethiopia
FO=Faroe Islands
FJ=Fiji
FI=Finland
FR=France
GF=French Guiana
PF=French Polynesia
GA=Gabon
GB=United Kingdom
GM=Gambia
GE=Georgia
DE=Germany
GH=Ghana
GI=Gibraltar
GR=Greece
GL=Greenland
GD=Grenada
GP=Guadeloupe
GU=Guam
GT=Guatemala
GN=Guinea
GW=Guinea-Bissau
GY=Guyana
HT=Haiti
EL=Hellenic Republic (Greece)
HN=Honduras
HK=Hong Kong
HU=Hungary
IS=Iceland
IN=India
ID=Indonesia
IR=Iran
IQ=Iraq
IE=Ireland
IL=Israel
IT=Italy
JM=Jamaica
JP=Japan
JO=Jordan
KZ=Kazakhstan
KE=Kenya
KI=Kiribati
KW=Kuwait
KG=Kyrgyzstan
LA=Laos
LV=Latvia
LB=Lebanon
LS=Lesotho
LR=Liberia
LY=Libya
LI=Liechtenstein
LT=Lithuania
LU=Luxembourg
MO=Macau
MK=Macedonia
MG=Madagascar
MW=Malawi
MY=Malaysia
MV=Maldives
ML=Mali
MT=Malta
MH=Marshall Islands
MQ=Martinique
MR=Mauritania
MU=Mauritius
YT=Mayotte
MX=Mexico
MD=Moldova
MC=Monaco
MN=Mongolia
ME=Montenegro
MS=Montserrat
MA=Morocco
MZ=Mozambique
MM=Myanmar (Burma)
NA=Namibia
NR=Nauru
NP=Nepal
NL=Netherlands
AN=Netherlands Antilles
NC=New Caledonia
NZ=New Zealand
NI=Nicaragua
NE=Niger
NG=Nigeria
NU=Niue
NF=Norfolk Island
MP=Northern Mariana Islands
NO=Norway
OM=Oman
PK=Pakistan
PW=Palau
PA=Panama
PG=Papua New Guinea
PY=Paraguay
PE=Peru
PH=Philippines
PN=Pitcairn Islands
PL=Poland
PT=Portugal
PR=Puerto Rico
QA=Qatar
RE=Reunion
RO=Romania
RU=Russia
RW=Rwanda
KN=Saint Kitts And Nevis
LC=Saint Lucia
WS=Samoa
SM=San Marino
ST=Sao Tome And Principe
SA=Saudi Arabia
SN=Senegal
RS=Serbia
SC=Seychelles
SL=Sierra Leone
SG=Singapore
SK=Slovakia
SI=Slovenia
SB=Solomon Islands
SO=Somalia
ZA=South Africa
KR=South Korea
ES=Spain
LK=Sri Lanka
SH=St. Helena
PM=St. Pierre And Miquelon
SD=Sudan
SR=Suriname
SZ=Swaziland
SE=Sweden
CH=Switzerland
SY=Syria
TW=Taiwan
TJ=Tajikistan
TZ=Tanzania
TH=Thailand
TG=Togo
TK=Tokelau
TO=Tonga
TT=Trinidad and Tobago
TN=Tunisia
TR=Turkey
TM=Turkmenistan
TC=Turks and Caicos Islands
TV=Tuvalu
UG=Uganda
UA=Ukraine
AE=United Arab Emirates
UY=Uruguay
UZ=Uzbekistan
VU=Vanuatu
VA=Vatican City
VE=Venezuela
VN=Vietnam
VI=Virgin Islands (U.S.)
EH=Western Sahara
YE=Yemen
ZM=Zambia
ZW=Zimbabwe
{% endset %}
                    <div class=\"rowElem noborder\">
                        <label class=\"topLabel\">{% trans 'List of countries' %}</label>
                        <div class=\"formBottom\">
                            <textarea name=\"countries\" cols=\"5\" rows=\"50\" placeholder=\"US=United States\">{{ new_params.countries | default(default_countries) }}</textarea>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <input type=\"submit\" value=\"{% trans 'Update settings' %}\" class=\"greyishBtn submitForm\" />
                    <input type=\"hidden\" name=\"ext\" value=\"mod_system\" />
                </fieldset>
            </form>
        </div>

        <div id=\"tab-cache\" class=\"tab_content nopadding\">

            <a href=\"{{ 'api/admin/system/clear_cache'|link }}\" class=\"api-link\" data-api-msg=\"{% trans 'Cache folder cleared' %}\">{% trans 'Invalidate cache' %}</a>

        </div>

        <div id=\"tab-ftp\" class=\"tab_content nopadding\">
            <div class=\"help\">
                <h3>{% trans 'FTP layer for BoxBilling' %}</h3>
                <p>{% trans 'FTP is used to manage BoxBilling modules and updates.' %}</p>
            </div>

            <form method=\"post\" action=\"{{ 'api/admin/system/update_params'|link }}\" class=\"mainForm api-form\" data-api-msg=\"{% trans 'FTP settings updated' %}\">
                <fieldset>
                <div class=\"rowElem\">
                    <label class=\"topLabel\">{% trans %}<strong>FTP Hostname</strong> - can be a numerical URL 127.0.0.1 is the default address for a locally hosted server, it might be this, it might be the full URL as my example above with the File Transfer Protocol (FTP) prefix, it might be the http:// protocol it might be SFTP (secure FTP) even secure http (https). Your host will be able to advise you on the correct format.{% endtrans %}</label>
                    <div class=\"formBottom\">
                        <input type=\"text\" name=\"ftp_host\" value=\"{{params.ftp_host|default('localhost')}}\"/>
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label class=\"topLabel\">{% trans %}<strong>FTP port</strong> - is nearly always 21 unless the host has changed this they will have notified you of this. Secure FTP generally uses port 22.{% endtrans %}</label>
                    <div class=\"formBottom\">
                        <input type=\"text\" name=\"ftp_port\" value=\"{{params.ftp_port|default(21)}}\"/>
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label class=\"topLabel\">{% trans %}<strong>FTP user</strong> - In most cases, this is the specific user name your host has set up for you to access your Web site via FTP- It is the name you use in your FTP client. This could be an alphanumeric, it could be your name, even your e-mail address, If you don't know this speak to your host. You may have the facility on your Web account to set up additional FTP users if that is the case (and it's more secure) you are recommended to do this{% endtrans %}</label>
                    <div class=\"formBottom\">
                        <input type=\"text\" name=\"ftp_user\" value=\"{{params.ftp_user}}\"/>
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label class=\"topLabel\">{% trans %}<strong>FTP password</strong> - this is the password you enter in your FTP client.{% endtrans %}</label>
                    <div class=\"formBottom\">
                        <input type=\"password\" name=\"ftp_password\" value=\"{{params.ftp_password}}\"/>
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label class=\"topLabel\">{% trans %}<strong>FTP root</strong> - This is generally the directory in which BoxBilling is installed. This setting is very important to be setup correctly as it very specifically depends on the Host server settings. In can be just / BUT it might be htdocs/boxbilling, public_html/, /public_html/boxbilling or other. It is a variable that BoxBilling has absolutely no control over. You will need to clarify this with your host.{% endtrans %}</label>
                    <div class=\"formBottom\">
                        <input type=\"text\" name=\"ftp_root\" value=\"{{params.ftp_root}}\"/>
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <input type=\"submit\" value=\"{% trans 'Update settings' %}\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>

    </div>
</div>

{% endblock %}

{% block head %}{{ mf.bb_editor('.bb-textarea') }}{% endblock %}", "mod_system_settings.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-modules/System/html_admin/mod_system_settings.phtml");
    }
}
