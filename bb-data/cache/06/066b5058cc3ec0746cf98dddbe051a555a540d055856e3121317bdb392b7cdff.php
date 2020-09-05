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

/* layout_default.phtml */
class __TwigTemplate_18e85aa419219f296f5d6bfb529c56be01871b916b12ef14915ebc065cc22d34 extends \Twig\Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
            'meta_title' => [$this, 'block_meta_title'],
            'head' => [$this, 'block_head'],
            'content_wide' => [$this, 'block_content_wide'],
            'left_top' => [$this, 'block_left_top'],
            'nav' => [$this, 'block_nav'],
            'left_bottom' => [$this, 'block_left_bottom'],
            'before_content' => [$this, 'block_before_content'],
            'breadcrumbs' => [$this, 'block_breadcrumbs'],
            'top_content' => [$this, 'block_top_content'],
            'content' => [$this, 'block_content'],
            'js' => [$this, 'block_js'],
        ];
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 1
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "layout_default.phtml", 1)->unwrap();
        // line 2
        $context["profile"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "profile_get", [], "any", false, false, false, 2);
        // line 3
        $context["company"] = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_company", [], "any", false, false, false, 3);
        // line 4
        echo "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">
<html xmlns=\"http://www.w3.org/1999/xhtml\">
<head>
    <title>";
        // line 7
        $this->displayBlock('meta_title', $context, $blocks);
        echo " - ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["company"] ?? null), "name", [], "any", false, false, false, 7), "html", null, true);
        echo "</title>
    <meta http-equiv=\"content-type\" content=\"text/html; charset=utf-8\" />
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0\" />

    <link rel=\"shortcut icon\" href=\"favicon.ico\" />

    ";
        // line 13
        $this->loadTemplate("partial_bb_meta.phtml", "layout_default.phtml", 13)->display($context);
        // line 14
        echo "    
    ";
        // line 15
        $this->loadTemplate("partial_styles.phtml", "layout_default.phtml", 15)->display($context);
        // line 16
        echo "
    <script type=\"text/javascript\" src=\"js/boxbilling.min.js?v=";
        // line 17
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_version", [], "any", false, false, false, 17), "html", null, true);
        echo "\"></script>
    <script type=\"text/javascript\" src=\"js/bb-admin.js?v=";
        // line 18
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_version", [], "any", false, false, false, 18), "html", null, true);
        echo "\"></script>

    ";
        // line 20
        $this->displayBlock('head', $context, $blocks);
        // line 21
        echo "</head>

<body>
";
        // line 24
        if ( !($context["admin"] ?? null)) {
            // line 25
            echo "<script type=\"text/javascript\">\$(function(){bb.redirect(\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("staff/login");
            echo "\");});</script>
";
        } else {
            // line 27
            echo "<div id=\"topNav\">
    <div class=\"fixed\">
        <div class=\"wrapper\">
            <div class=\"welcome\">
                <a href=\"";
            // line 31
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("staff/profile");
            echo "\" title=\"\"><img src=\"";
            echo twig_escape_filter($this->env, twig_gravatar_filter(twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "email", [], "any", false, false, false, 31)), "html", null, true);
            echo "?size=20\" alt=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "name", [], "any", false, false, false, 31), "html", null, true);
            echo "\" /></a><span>";
            echo gettext("Hi,");
            echo " ";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "name", [], "any", false, false, false, 31), "html", null, true);
            echo "!</span>
                ";
            // line 32
            $context["languages"] = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "extension_languages", [], "any", false, false, false, 32);
            // line 33
            echo "                ";
            if ((twig_length_filter($this->env, ($context["languages"] ?? null)) > 1)) {
                // line 34
                echo "                <span>
                    <select name=\"lang\" class=\"language_selector\" style=\"background-color: #262b2f; color:white;\">
                        ";
                // line 36
                $context['_parent'] = $context;
                $context['_seq'] = twig_ensure_traversable(($context["languages"] ?? null));
                foreach ($context['_seq'] as $context["_key"] => $context["lang"]) {
                    // line 37
                    echo "                        <option value=\"";
                    echo twig_escape_filter($this->env, $context["lang"], "html", null, true);
                    echo "\" class=\"lang_";
                    echo twig_escape_filter($this->env, $context["lang"], "html", null, true);
                    echo "\">";
                    echo twig_escape_filter($this->env, gettext($context["lang"]), "html", null, true);
                    echo "</option>
                        ";
                }
                $_parent = $context['_parent'];
                unset($context['_seq'], $context['_iterated'], $context['_key'], $context['lang'], $context['_parent'], $context['loop']);
                $context = array_intersect_key($context, $_parent) + $_parent;
                // line 39
                echo "                    </select>
                </span>
                ";
            }
            // line 42
            echo "            </div>
            <div class=\"userNav\">
                <ul>
                    <li class=\"loading\" style=\"display:none;\"><img src=\"images/loader.gif\" alt=\"\" /><span>";
            // line 45
            echo gettext("Loading ...");
            echo "</span></li>
                    <li class=\"dd\"><span><i class=\"sprite-topnav sprite-topnav-register\" style=\"margin-right: 5px;\"></i>";
            // line 46
            echo gettext("New");
            echo "</span>
                        ";
            // line 47
            $this->loadTemplate("partial_menu_top.phtml", "layout_default.phtml", 47)->display($context);
            // line 48
            echo "                    </li>
                    ";
            // line 49
            if (twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "system_is_allowed", [0 => ["mod" => "system"]], "method", false, false, false, 49)) {
                // line 50
                echo "                    <li class=\"dd\"><span><i class=\"sprite-topnav sprite-topnav-settings\" style=\"margin-right: 5px;\"></i>";
                echo gettext("Settings");
                echo "</span>
                        <ul class=\"menu_body\">
                            <li><a href=\"";
                // line 52
                echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("system");
                echo "\" title=\"\">";
                echo gettext("All settings");
                echo "</a></li>
                            <li><a href=\"";
                // line 53
                echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("theme");
                echo "/";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "extension_theme", [], "any", false, false, false, 53), "code", [], "any", false, false, false, 53), "html", null, true);
                echo "\" title=\"\">";
                echo gettext("Theme settings");
                echo "</a></li>
                        </ul>
                    </li>
                    ";
            }
            // line 57
            echo "                    <li><a href=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/");
            echo "\" title=\"\" target=\"_blank\"><span><i class=\"sprite-topnav sprite-topnav-mainWebsite\" style=\"margin-right: 5px;\"></i>";
            echo gettext("Visit site");
            echo "</span></a></li>
                    <li><a href=\"";
            // line 58
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/profile/logout");
            echo "\" title=\"\" class=\"api-link\" data-api-redirect=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("staff/login");
            echo "\"><span><i class=\"sprite-topnav sprite-topnav-logout\" style=\"margin-right: 5px;\"></i>";
            echo gettext("Logout");
            echo "</span></a></li>
                </ul>
            </div>
            <div class=\"fix\"></div>
        </div>
    </div>
</div>

<div id=\"header\" class=\"wrapper\">
    <div class=\"logo\"><a href=\"";
            // line 67
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("system");
            echo "\" title=\"\"><img src=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["company"] ?? null), "logo_url", [], "any", false, false, false, 67), "html", null, true);
            echo "\" alt=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["company"] ?? null), "name", [], "any", false, false, false, 67), "html", null, true);
            echo "\" style=\"max-height: 50px;\"/></a></div>
    <div class=\"middleNav\">
        
    \t<ul>
            ";
            // line 71
            if ((twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "system_is_allowed", [0 => ["mod" => "notification"]], "method", false, false, false, 71) && twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "extension_is_on", [0 => ["mod" => "notification"]], "method", false, false, false, 71))) {
                // line 72
                echo "            ";
                $context["count_notifications"] = twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "notification_get_list", [0 => ["per_page" => 1]], "method", false, false, false, 72), "total", [], "any", false, false, false, 72);
                // line 73
                echo "        \t<li class=\"iMegaphone\"><a href=\"";
                echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("notification");
                echo "\" title=\"\"><span>";
                echo gettext("Notifications");
                echo "</span></a>";
                if (($context["count_notifications"] ?? null)) {
                    echo "<span class=\"numberMiddle\">";
                    echo twig_escape_filter($this->env, ($context["count_notifications"] ?? null), "html", null, true);
                    echo "</span>";
                }
                echo "</li>
            ";
            }
            // line 75
            echo "            
            ";
            // line 76
            if (twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "system_is_allowed", [0 => ["mod" => "order"]], "method", false, false, false, 76)) {
                // line 77
                echo "            ";
                $context["count_orders"] = twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "order_get_statuses", [], "any", false, false, false, 77), "failed_setup", [], "any", false, false, false, 77);
                // line 78
                echo "        \t<li class=\"iOrders\"><a href=\"";
                echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("order", ["status" => "failed_setup"]);
                echo "\" title=\"\"><span><i class=\"sprite-23 sprite-23-basket2\"></i>";
                echo gettext("Orders");
                echo "</span></a>";
                if (($context["count_orders"] ?? null)) {
                    echo "<span class=\"numberMiddle\">";
                    echo twig_escape_filter($this->env, ($context["count_orders"] ?? null), "html", null, true);
                    echo "</span>";
                }
                echo "</li>
            ";
            }
            // line 80
            echo "            
            ";
            // line 81
            if (twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "system_is_allowed", [0 => ["mod" => "invoice"]], "method", false, false, false, 81)) {
                // line 82
                echo "            ";
                $context["count_invoices"] = twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "invoice_get_statuses", [], "any", false, false, false, 82), "unpaid", [], "any", false, false, false, 82);
                // line 83
                echo "        \t<li class=\"iInvoices\"><a href=\"";
                echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("invoice", ["status" => "unpaid"]);
                echo "\" title=\"\"><span><i class=\"sprite-23 sprite-23-money\"></i>";
                echo gettext("Invoices");
                echo "</span></a>";
                if (($context["count_invoices"] ?? null)) {
                    echo "<span class=\"numberMiddle\">";
                    echo twig_escape_filter($this->env, ($context["count_invoices"] ?? null), "html", null, true);
                    echo "</span>";
                }
                echo "</li>
            ";
            }
            // line 85
            echo "            
            ";
            // line 86
            if (twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "system_is_allowed", [0 => ["mod" => "support"]], "method", false, false, false, 86)) {
                // line 87
                echo "            ";
                $context["count_tickets"] = twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "support_ticket_get_statuses", [], "any", false, false, false, 87), "open", [], "any", false, false, false, 87);
                // line 88
                echo "            ";
                $context["count_ptickets"] = twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "support_public_ticket_get_statuses", [], "any", false, false, false, 88), "open", [], "any", false, false, false, 88);
                // line 89
                echo "        \t<li class=\"iSpeech\"><a href=\"";
                echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support/public-tickets", ["status" => "open"]);
                echo "\" title=\"\"><span><i class=\"sprite-23 sprite-23-speech\"></i>";
                echo gettext("Inquiries");
                echo "</span></a>";
                if (($context["count_ptickets"] ?? null)) {
                    echo "<span class=\"numberMiddle\">";
                    echo twig_escape_filter($this->env, ($context["count_ptickets"] ?? null), "html", null, true);
                    echo "</span>";
                }
                echo "</li>
        \t<li class=\"iMes\"><a href=\"";
                // line 90
                echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support", ["status" => "open"]);
                echo "\" title=\"\"><span><i class=\"sprite-23 sprite-23-dialog\"></i>";
                echo gettext("Tickets");
                echo "</span></a>";
                if (($context["count_tickets"] ?? null)) {
                    echo "<span class=\"numberMiddle\">";
                    echo twig_escape_filter($this->env, ($context["count_tickets"] ?? null), "html", null, true);
                    echo "</span>";
                }
                echo "</li>
            ";
            }
            // line 92
            echo "            
            ";
            // line 93
            if (twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "system_is_allowed", [0 => ["mod" => "client"]], "method", false, false, false, 93)) {
                // line 94
                echo "            <li><a href=\"";
                echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("client");
                echo "\" title=\"\"><span><i class=\"sprite-23 sprite-23-user\"></i>";
                echo gettext("Clients");
                echo "</span></a></li>
            ";
            }
            // line 96
            echo "            
        \t<li><a href=\"";
            // line 97
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("index");
            echo "\" title=\"\"><span><i class=\"sprite-23 sprite-23-home\"></i>";
            echo gettext("Dashboard");
            echo "</span></a></li>
        </ul>
    </div>
    <div class=\"fix\"></div>
</div>


<div class=\"wrapper\">
    
    ";
            // line 106
            if (($context["hide_menu"] ?? null)) {
                // line 107
                echo "    
    ";
                // line 108
                $this->displayBlock('content_wide', $context, $blocks);
                // line 109
                echo "    
    ";
            } else {
                // line 111
                echo "    <div class=\"leftNav\">
    ";
                // line 112
                $this->displayBlock('left_top', $context, $blocks);
                echo "    
    ";
                // line 113
                $this->displayBlock('nav', $context, $blocks);
                // line 114
                echo "    ";
                $this->displayBlock('left_bottom', $context, $blocks);
                // line 115
                echo "    </div>
    
    ";
                // line 117
                $this->displayBlock('before_content', $context, $blocks);
                // line 118
                echo "    <div class=\"content\">

        <div class=\"breadCrumbHolder module\">
            <div class=\"breadCrumb module\">
                ";
                // line 122
                $this->displayBlock('breadcrumbs', $context, $blocks);
                // line 128
                echo "            </div>
        </div>

        ";
                // line 131
                $this->displayBlock('top_content', $context, $blocks);
                // line 132
                echo "        ";
                $this->displayBlock('content', $context, $blocks);
                // line 133
                echo "    </div>
    ";
            }
            // line 135
            echo "    <div class=\"fix\"></div>
</div>

<div id=\"footer\">
\t<div class=\"wrapper\">
        ";
            // line 140
            $this->loadTemplate("partial_footer.phtml", "layout_default.phtml", 140)->display(twig_array_merge($context, ["product" => ($context["product"] ?? null)]));
            // line 141
            echo "    </div>
</div>
<div class=\"loading dim\"></div>    
    ";
            // line 144
            $this->displayBlock('js', $context, $blocks);
            // line 145
            echo "    <noscript id=\"noscript\">
        <div class=\"msg error\">
        NOTE: Many features on BoxBilling require Javascript and cookies. You can enable both via your browser's preference settings.
        </div>
    </noscript>
";
        }
        // line 151
        echo "</body>
</html>";
    }

    // line 7
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
    }

    // line 20
    public function block_head($context, array $blocks = [])
    {
        $macros = $this->macros;
    }

    // line 108
    public function block_content_wide($context, array $blocks = [])
    {
        $macros = $this->macros;
    }

    // line 112
    public function block_left_top($context, array $blocks = [])
    {
        $macros = $this->macros;
    }

    // line 113
    public function block_nav($context, array $blocks = [])
    {
        $macros = $this->macros;
        $this->loadTemplate("partial_menu.phtml", "layout_default.phtml", 113)->display($context);
    }

    // line 114
    public function block_left_bottom($context, array $blocks = [])
    {
        $macros = $this->macros;
    }

    // line 117
    public function block_before_content($context, array $blocks = [])
    {
        $macros = $this->macros;
    }

    // line 122
    public function block_breadcrumbs($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 123
        echo "                <ul>
                    <li class=\"firstB\"><a href=\"";
        // line 124
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/");
        echo "\">";
        echo gettext("Home");
        echo "</a></li>
                    <li class=\"lastB\">";
        // line 125
        $this->displayBlock("meta_title", $context, $blocks);
        echo "</li>
                </ul>
                ";
    }

    // line 131
    public function block_top_content($context, array $blocks = [])
    {
        $macros = $this->macros;
    }

    // line 132
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
    }

    // line 144
    public function block_js($context, array $blocks = [])
    {
        $macros = $this->macros;
    }

    public function getTemplateName()
    {
        return "layout_default.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  515 => 144,  509 => 132,  503 => 131,  496 => 125,  490 => 124,  487 => 123,  483 => 122,  477 => 117,  471 => 114,  464 => 113,  458 => 112,  452 => 108,  446 => 20,  440 => 7,  435 => 151,  427 => 145,  425 => 144,  420 => 141,  418 => 140,  411 => 135,  407 => 133,  404 => 132,  402 => 131,  397 => 128,  395 => 122,  389 => 118,  387 => 117,  383 => 115,  380 => 114,  378 => 113,  374 => 112,  371 => 111,  367 => 109,  365 => 108,  362 => 107,  360 => 106,  346 => 97,  343 => 96,  335 => 94,  333 => 93,  330 => 92,  317 => 90,  304 => 89,  301 => 88,  298 => 87,  296 => 86,  293 => 85,  279 => 83,  276 => 82,  274 => 81,  271 => 80,  257 => 78,  254 => 77,  252 => 76,  249 => 75,  235 => 73,  232 => 72,  230 => 71,  219 => 67,  203 => 58,  196 => 57,  185 => 53,  179 => 52,  173 => 50,  171 => 49,  168 => 48,  166 => 47,  162 => 46,  158 => 45,  153 => 42,  148 => 39,  135 => 37,  131 => 36,  127 => 34,  124 => 33,  122 => 32,  110 => 31,  104 => 27,  98 => 25,  96 => 24,  91 => 21,  89 => 20,  84 => 18,  80 => 17,  77 => 16,  75 => 15,  72 => 14,  70 => 13,  59 => 7,  54 => 4,  52 => 3,  50 => 2,  48 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% import \"macro_functions.phtml\" as mf %}
{% set profile = admin.profile_get %}
{% set company = guest.system_company %}
<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">
<html xmlns=\"http://www.w3.org/1999/xhtml\">
<head>
    <title>{% block meta_title %}{% endblock %} - {{ company.name }}</title>
    <meta http-equiv=\"content-type\" content=\"text/html; charset=utf-8\" />
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0\" />

    <link rel=\"shortcut icon\" href=\"favicon.ico\" />

    {% include \"partial_bb_meta.phtml\" %}
    
    {% include \"partial_styles.phtml\" %}

    <script type=\"text/javascript\" src=\"js/boxbilling.min.js?v={{guest.system_version}}\"></script>
    <script type=\"text/javascript\" src=\"js/bb-admin.js?v={{guest.system_version}}\"></script>

    {% block head %}{% endblock %}
</head>

<body>
{% if not admin %}
<script type=\"text/javascript\">\$(function(){bb.redirect(\"{{ 'staff/login'|alink }}\");});</script>
{% else %}
<div id=\"topNav\">
    <div class=\"fixed\">
        <div class=\"wrapper\">
            <div class=\"welcome\">
                <a href=\"{{ 'staff/profile'|alink }}\" title=\"\"><img src=\"{{ profile.email|gravatar }}?size=20\" alt=\"{{ profile.name }}\" /></a><span>{% trans 'Hi,' %} {{ profile.name }}!</span>
                {% set languages = guest.extension_languages %}
                {% if languages|length > 1 %}
                <span>
                    <select name=\"lang\" class=\"language_selector\" style=\"background-color: #262b2f; color:white;\">
                        {% for lang in languages %}
                        <option value=\"{{ lang }}\" class=\"lang_{{ lang }}\">{{ lang|trans }}</option>
                        {% endfor %}
                    </select>
                </span>
                {% endif %}
            </div>
            <div class=\"userNav\">
                <ul>
                    <li class=\"loading\" style=\"display:none;\"><img src=\"images/loader.gif\" alt=\"\" /><span>{% trans 'Loading ...' %}</span></li>
                    <li class=\"dd\"><span><i class=\"sprite-topnav sprite-topnav-register\" style=\"margin-right: 5px;\"></i>{% trans 'New' %}</span>
                        {% include \"partial_menu_top.phtml\" %}
                    </li>
                    {% if admin.system_is_allowed({\"mod\":\"system\"}) %}
                    <li class=\"dd\"><span><i class=\"sprite-topnav sprite-topnav-settings\" style=\"margin-right: 5px;\"></i>{% trans 'Settings' %}</span>
                        <ul class=\"menu_body\">
                            <li><a href=\"{{ 'system'|alink }}\" title=\"\">{% trans 'All settings' %}</a></li>
                            <li><a href=\"{{ 'theme'|alink }}/{{ guest.extension_theme.code }}\" title=\"\">{% trans 'Theme settings' %}</a></li>
                        </ul>
                    </li>
                    {% endif %}
                    <li><a href=\"{{ '/'|link }}\" title=\"\" target=\"_blank\"><span><i class=\"sprite-topnav sprite-topnav-mainWebsite\" style=\"margin-right: 5px;\"></i>{% trans 'Visit site' %}</span></a></li>
                    <li><a href=\"{{ 'api/admin/profile/logout'|link }}\" title=\"\" class=\"api-link\" data-api-redirect=\"{{ 'staff/login'|alink }}\"><span><i class=\"sprite-topnav sprite-topnav-logout\" style=\"margin-right: 5px;\"></i>{% trans 'Logout' %}</span></a></li>
                </ul>
            </div>
            <div class=\"fix\"></div>
        </div>
    </div>
</div>

<div id=\"header\" class=\"wrapper\">
    <div class=\"logo\"><a href=\"{{ 'system'|alink }}\" title=\"\"><img src=\"{{ company.logo_url }}\" alt=\"{{ company.name }}\" style=\"max-height: 50px;\"/></a></div>
    <div class=\"middleNav\">
        
    \t<ul>
            {% if admin.system_is_allowed({\"mod\":\"notification\"}) and guest.extension_is_on({\"mod\":\"notification\"}) %}
            {% set count_notifications = admin.notification_get_list({\"per_page\":1}).total %}
        \t<li class=\"iMegaphone\"><a href=\"{{ 'notification'|alink }}\" title=\"\"><span>{% trans 'Notifications' %}</span></a>{% if count_notifications %}<span class=\"numberMiddle\">{{ count_notifications }}</span>{% endif %}</li>
            {% endif %}
            
            {% if admin.system_is_allowed({\"mod\":\"order\"}) %}
            {% set count_orders = admin.order_get_statuses.failed_setup %}
        \t<li class=\"iOrders\"><a href=\"{{ 'order'|alink({'status' : 'failed_setup'}) }}\" title=\"\"><span><i class=\"sprite-23 sprite-23-basket2\"></i>{% trans 'Orders' %}</span></a>{% if count_orders %}<span class=\"numberMiddle\">{{ count_orders }}</span>{% endif %}</li>
            {% endif %}
            
            {% if admin.system_is_allowed({\"mod\":\"invoice\"}) %}
            {% set count_invoices = admin.invoice_get_statuses.unpaid %}
        \t<li class=\"iInvoices\"><a href=\"{{ 'invoice'|alink({'status' : 'unpaid'}) }}\" title=\"\"><span><i class=\"sprite-23 sprite-23-money\"></i>{% trans 'Invoices' %}</span></a>{% if count_invoices %}<span class=\"numberMiddle\">{{ count_invoices }}</span>{% endif %}</li>
            {% endif %}
            
            {% if admin.system_is_allowed({\"mod\":\"support\"}) %}
            {% set count_tickets = admin.support_ticket_get_statuses.open %}
            {% set count_ptickets = admin.support_public_ticket_get_statuses.open %}
        \t<li class=\"iSpeech\"><a href=\"{{ 'support/public-tickets'|alink({'status' : 'open'}) }}\" title=\"\"><span><i class=\"sprite-23 sprite-23-speech\"></i>{% trans 'Inquiries' %}</span></a>{% if count_ptickets %}<span class=\"numberMiddle\">{{ count_ptickets }}</span>{% endif %}</li>
        \t<li class=\"iMes\"><a href=\"{{ 'support'|alink({'status' : 'open'}) }}\" title=\"\"><span><i class=\"sprite-23 sprite-23-dialog\"></i>{% trans 'Tickets' %}</span></a>{% if count_tickets %}<span class=\"numberMiddle\">{{ count_tickets }}</span>{% endif %}</li>
            {% endif %}
            
            {% if admin.system_is_allowed({\"mod\":\"client\"}) %}
            <li><a href=\"{{ 'client'|alink }}\" title=\"\"><span><i class=\"sprite-23 sprite-23-user\"></i>{% trans 'Clients' %}</span></a></li>
            {% endif %}
            
        \t<li><a href=\"{{ 'index'|alink }}\" title=\"\"><span><i class=\"sprite-23 sprite-23-home\"></i>{% trans 'Dashboard' %}</span></a></li>
        </ul>
    </div>
    <div class=\"fix\"></div>
</div>


<div class=\"wrapper\">
    
    {% if hide_menu %}
    
    {% block content_wide %}{% endblock %}
    
    {% else %}
    <div class=\"leftNav\">
    {% block left_top %}{% endblock %}    
    {% block nav %}{% include \"partial_menu.phtml\" %}{% endblock %}
    {% block left_bottom %}{% endblock %}
    </div>
    
    {% block before_content %}{% endblock %}
    <div class=\"content\">

        <div class=\"breadCrumbHolder module\">
            <div class=\"breadCrumb module\">
                {% block breadcrumbs %}
                <ul>
                    <li class=\"firstB\"><a href=\"{{ '/'|alink }}\">{% trans 'Home' %}</a></li>
                    <li class=\"lastB\">{{ block('meta_title') }}</li>
                </ul>
                {% endblock %}
            </div>
        </div>

        {% block top_content %}{% endblock %}
        {% block content %}{% endblock %}
    </div>
    {% endif %}
    <div class=\"fix\"></div>
</div>

<div id=\"footer\">
\t<div class=\"wrapper\">
        {% include \"partial_footer.phtml\" with {'product': product} %}
    </div>
</div>
<div class=\"loading dim\"></div>    
    {% block js %}{% endblock %}
    <noscript id=\"noscript\">
        <div class=\"msg error\">
        NOTE: Many features on BoxBilling require Javascript and cookies. You can enable both via your browser's preference settings.
        </div>
    </noscript>
{% endif %}
</body>
</html>", "layout_default.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/src/bb-themes/admin_default/html/layout_default.phtml");
    }
}
