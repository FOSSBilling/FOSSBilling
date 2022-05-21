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
class __TwigTemplate_168650992c212632c1824e82381067d7619c045a4031287753120f4717758a2d extends Template
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
        echo "
";
        // line 3
        $context["profile"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "profile_get", [], "any", false, false, false, 3);
        // line 4
        $context["company"] = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_company", [], "any", false, false, false, 4);
        // line 5
        echo "
<!DOCTYPE html>
<html xmlns=\"http://www.w3.org/1999/xhtml\">
<head>
    <title>";
        // line 9
        $this->displayBlock('meta_title', $context, $blocks);
        echo " - ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["company"] ?? null), "name", [], "any", false, false, false, 9), "html", null, true);
        echo "</title>
    <meta http-equiv=\"content-type\" content=\"text/html; charset=utf-8\" />
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0\" />

    <link rel=\"shortcut icon\" href=\"favicon.ico\" />

    ";
        // line 15
        $this->loadTemplate("partial_bb_meta.phtml", "layout_default.phtml", 15)->display($context);
        // line 16
        echo "    ";
        $this->loadTemplate("partial_styles.phtml", "layout_default.phtml", 16)->display($context);
        // line 17
        echo "
    <script type=\"text/javascript\" src=\"js/boxbilling.min.js?v=";
        // line 18
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_version", [], "any", false, false, false, 18), "html", null, true);
        echo "\"></script>
    <script type=\"text/javascript\" src=\"js/bb-admin.js?v=";
        // line 19
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_version", [], "any", false, false, false, 19), "html", null, true);
        echo "\"></script>

    ";
        // line 21
        $this->displayBlock('head', $context, $blocks);
        // line 22
        echo "</head>

<body>
";
        // line 25
        if ( !($context["admin"] ?? null)) {
            // line 26
            echo "<script type=\"text/javascript\">\$(function(){bb.redirect(\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("staff/login");
            echo "\");});</script>
";
        } else {
            // line 28
            echo "<div id=\"topNav\">
    <div class=\"fixed\">
        <div class=\"wrapper\">
            <div class=\"welcome\">
                <a href=\"";
            // line 32
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("staff/profile");
            echo "\" title=\"\"><img src=\"";
            echo twig_escape_filter($this->env, twig_gravatar_filter(twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "email", [], "any", false, false, false, 32)), "html", null, true);
            echo "?size=20\" alt=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "name", [], "any", false, false, false, 32), "html", null, true);
            echo "\" /></a><span>";
            echo twig_escape_filter($this->env, gettext("Hi,"), "html", null, true);
            echo " ";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "name", [], "any", false, false, false, 32), "html", null, true);
            echo "!</span>
                ";
            // line 33
            $context["languages"] = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "extension_languages", [], "any", false, false, false, 33);
            // line 34
            echo "                ";
            if ((twig_length_filter($this->env, ($context["languages"] ?? null)) > 1)) {
                // line 35
                echo "                <span>
                    <select name=\"lang\" class=\"language_selector\" style=\"background-color: #262b2f; color:white;\">
                        ";
                // line 37
                $context['_parent'] = $context;
                $context['_seq'] = twig_ensure_traversable(($context["languages"] ?? null));
                foreach ($context['_seq'] as $context["_key"] => $context["lang"]) {
                    // line 38
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
                // line 40
                echo "                    </select>
                </span>
                ";
            }
            // line 43
            echo "            </div>
            <div class=\"userNav\">
                <ul>
                    <li class=\"loading\" style=\"display:none;\"><img src=\"images/loader.gif\" alt=\"\" /><span>";
            // line 46
            echo twig_escape_filter($this->env, gettext("Loading ..."), "html", null, true);
            echo "</span></li>
                    <li class=\"dd\"><span><i class=\"sprite-topnav sprite-topnav-register\" style=\"margin-right: 5px;\"></i>";
            // line 47
            echo twig_escape_filter($this->env, gettext("New"), "html", null, true);
            echo "</span>
                        ";
            // line 48
            $this->loadTemplate("partial_menu_top.phtml", "layout_default.phtml", 48)->display($context);
            // line 49
            echo "                    </li>
                    ";
            // line 50
            if (twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "system_is_allowed", [0 => ["mod" => "system"]], "method", false, false, false, 50)) {
                // line 51
                echo "                    <li class=\"dd\"><span><i class=\"sprite-topnav sprite-topnav-settings\" style=\"margin-right: 5px;\"></i>";
                echo twig_escape_filter($this->env, gettext("Settings"), "html", null, true);
                echo "</span>
                        <ul class=\"menu_body\">
                            <li><a href=\"";
                // line 53
                echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("system");
                echo "\" title=\"\">";
                echo twig_escape_filter($this->env, gettext("All settings"), "html", null, true);
                echo "</a></li>
                            <li><a href=\"";
                // line 54
                echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("theme");
                echo "/";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "extension_theme", [], "any", false, false, false, 54), "code", [], "any", false, false, false, 54), "html", null, true);
                echo "\" title=\"\">";
                echo twig_escape_filter($this->env, gettext("Theme settings"), "html", null, true);
                echo "</a></li>
                        </ul>
                    </li>
                    ";
            }
            // line 58
            echo "                    <li><a href=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/");
            echo "\" title=\"\" target=\"_blank\"><span><i class=\"sprite-topnav sprite-topnav-mainWebsite\" style=\"margin-right: 5px;\"></i>";
            echo twig_escape_filter($this->env, gettext("Visit site"), "html", null, true);
            echo "</span></a></li>
                    <li><a href=\"";
            // line 59
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/profile/logout");
            echo "\" title=\"\" class=\"api-link\" data-api-redirect=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("staff/login");
            echo "\"><span><i class=\"sprite-topnav sprite-topnav-logout\" style=\"margin-right: 5px;\"></i>";
            echo twig_escape_filter($this->env, gettext("Logout"), "html", null, true);
            echo "</span></a></li>
                </ul>
            </div>
            <div class=\"fix\"></div>
        </div>
    </div>
</div>

<div id=\"header\" class=\"wrapper\">
    <div class=\"logo\"><a href=\"";
            // line 68
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("system");
            echo "\" title=\"\"><img src=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["company"] ?? null), "logo_url", [], "any", false, false, false, 68), "html", null, true);
            echo "\" alt=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["company"] ?? null), "name", [], "any", false, false, false, 68), "html", null, true);
            echo "\" style=\"max-height: 50px;\"/></a></div>
    <div class=\"middleNav\">
        
    \t<ul>
            ";
            // line 72
            if ((twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "system_is_allowed", [0 => ["mod" => "notification"]], "method", false, false, false, 72) && twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "extension_is_on", [0 => ["mod" => "notification"]], "method", false, false, false, 72))) {
                // line 73
                echo "            ";
                $context["count_notifications"] = twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "notification_get_list", [0 => ["per_page" => 1]], "method", false, false, false, 73), "total", [], "any", false, false, false, 73);
                // line 74
                echo "        \t<li class=\"iMegaphone\"><a href=\"";
                echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("notification");
                echo "\" title=\"\"><span>";
                echo twig_escape_filter($this->env, gettext("Notifications"), "html", null, true);
                echo "</span></a>";
                if (($context["count_notifications"] ?? null)) {
                    echo "<span class=\"numberMiddle\">";
                    echo twig_escape_filter($this->env, ($context["count_notifications"] ?? null), "html", null, true);
                    echo "</span>";
                }
                echo "</li>
            ";
            }
            // line 76
            echo "            
            ";
            // line 77
            if (twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "system_is_allowed", [0 => ["mod" => "order"]], "method", false, false, false, 77)) {
                // line 78
                echo "            ";
                $context["count_orders"] = twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "order_get_statuses", [], "any", false, false, false, 78), "failed_setup", [], "any", false, false, false, 78);
                // line 79
                echo "        \t<li class=\"iOrders\"><a href=\"";
                echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("order", ["status" => "failed_setup"]);
                echo "\" title=\"\"><span><i class=\"sprite-23 sprite-23-basket2\"></i>";
                echo twig_escape_filter($this->env, gettext("Orders"), "html", null, true);
                echo "</span></a>";
                if (($context["count_orders"] ?? null)) {
                    echo "<span class=\"numberMiddle\">";
                    echo twig_escape_filter($this->env, ($context["count_orders"] ?? null), "html", null, true);
                    echo "</span>";
                }
                echo "</li>
            ";
            }
            // line 81
            echo "            
            ";
            // line 82
            if (twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "system_is_allowed", [0 => ["mod" => "invoice"]], "method", false, false, false, 82)) {
                // line 83
                echo "            ";
                $context["count_invoices"] = twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "invoice_get_statuses", [], "any", false, false, false, 83), "unpaid", [], "any", false, false, false, 83);
                // line 84
                echo "        \t<li class=\"iInvoices\"><a href=\"";
                echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("invoice", ["status" => "unpaid"]);
                echo "\" title=\"\"><span><i class=\"sprite-23 sprite-23-money\"></i>";
                echo twig_escape_filter($this->env, gettext("Invoices"), "html", null, true);
                echo "</span></a>";
                if (($context["count_invoices"] ?? null)) {
                    echo "<span class=\"numberMiddle\">";
                    echo twig_escape_filter($this->env, ($context["count_invoices"] ?? null), "html", null, true);
                    echo "</span>";
                }
                echo "</li>
            ";
            }
            // line 86
            echo "            
            ";
            // line 87
            if (twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "system_is_allowed", [0 => ["mod" => "support"]], "method", false, false, false, 87)) {
                // line 88
                echo "            ";
                $context["count_tickets"] = twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "support_ticket_get_statuses", [], "any", false, false, false, 88), "open", [], "any", false, false, false, 88);
                // line 89
                echo "            ";
                $context["count_ptickets"] = twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "support_public_ticket_get_statuses", [], "any", false, false, false, 89), "open", [], "any", false, false, false, 89);
                // line 90
                echo "        \t<li class=\"iSpeech\"><a href=\"";
                echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support/public-tickets", ["status" => "open"]);
                echo "\" title=\"\"><span><i class=\"sprite-23 sprite-23-speech\"></i>";
                echo twig_escape_filter($this->env, gettext("Inquiries"), "html", null, true);
                echo "</span></a>";
                if (($context["count_ptickets"] ?? null)) {
                    echo "<span class=\"numberMiddle\">";
                    echo twig_escape_filter($this->env, ($context["count_ptickets"] ?? null), "html", null, true);
                    echo "</span>";
                }
                echo "</li>
        \t<li class=\"iMes\"><a href=\"";
                // line 91
                echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support", ["status" => "open"]);
                echo "\" title=\"\"><span><i class=\"sprite-23 sprite-23-dialog\"></i>";
                echo twig_escape_filter($this->env, gettext("Tickets"), "html", null, true);
                echo "</span></a>";
                if (($context["count_tickets"] ?? null)) {
                    echo "<span class=\"numberMiddle\">";
                    echo twig_escape_filter($this->env, ($context["count_tickets"] ?? null), "html", null, true);
                    echo "</span>";
                }
                echo "</li>
            ";
            }
            // line 93
            echo "            
            ";
            // line 94
            if (twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "system_is_allowed", [0 => ["mod" => "client"]], "method", false, false, false, 94)) {
                // line 95
                echo "            <li><a href=\"";
                echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("client");
                echo "\" title=\"\"><span><i class=\"sprite-23 sprite-23-user\"></i>";
                echo twig_escape_filter($this->env, gettext("Clients"), "html", null, true);
                echo "</span></a></li>
            ";
            }
            // line 97
            echo "            
        \t<li><a href=\"";
            // line 98
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("index");
            echo "\" title=\"\"><span><i class=\"sprite-23 sprite-23-home\"></i>";
            echo twig_escape_filter($this->env, gettext("Dashboard"), "html", null, true);
            echo "</span></a></li>
        </ul>
    </div>
    <div class=\"fix\"></div>
</div>


<div class=\"wrapper\">
    
    ";
            // line 107
            if (($context["hide_menu"] ?? null)) {
                // line 108
                echo "    
    ";
                // line 109
                $this->displayBlock('content_wide', $context, $blocks);
                // line 110
                echo "    
    ";
            } else {
                // line 112
                echo "    <div class=\"leftNav\">
    ";
                // line 113
                $this->displayBlock('left_top', $context, $blocks);
                echo "    
    ";
                // line 114
                $this->displayBlock('nav', $context, $blocks);
                // line 115
                echo "    ";
                $this->displayBlock('left_bottom', $context, $blocks);
                // line 116
                echo "    </div>
    
    ";
                // line 118
                $this->displayBlock('before_content', $context, $blocks);
                // line 119
                echo "    <div class=\"content\">

        <div class=\"breadCrumbHolder module\">
            <div class=\"breadCrumb module\">
                ";
                // line 123
                $this->displayBlock('breadcrumbs', $context, $blocks);
                // line 129
                echo "            </div>
        </div>

        ";
                // line 132
                $this->displayBlock('top_content', $context, $blocks);
                // line 133
                echo "        ";
                $this->displayBlock('content', $context, $blocks);
                // line 134
                echo "    </div>
    ";
            }
            // line 136
            echo "    <div class=\"fix\"></div>
</div>

<div id=\"footer\">
\t<div class=\"wrapper\">
        ";
            // line 141
            $this->loadTemplate("partial_footer.phtml", "layout_default.phtml", 141)->display(twig_array_merge($context, ["product" => ($context["product"] ?? null)]));
            // line 142
            echo "    </div>
</div>
<div class=\"loading dim\"></div>    
    ";
            // line 145
            $this->displayBlock('js', $context, $blocks);
            // line 146
            echo "    <noscript id=\"noscript\">
        <div class=\"msg error\">
        NOTE: Many features on BoxBilling require Javascript and cookies. You can enable both via your browser's preference settings.
        </div>
    </noscript>
";
        }
        // line 152
        echo "</body>
</html>";
    }

    // line 9
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
    }

    // line 21
    public function block_head($context, array $blocks = [])
    {
        $macros = $this->macros;
    }

    // line 109
    public function block_content_wide($context, array $blocks = [])
    {
        $macros = $this->macros;
    }

    // line 113
    public function block_left_top($context, array $blocks = [])
    {
        $macros = $this->macros;
    }

    // line 114
    public function block_nav($context, array $blocks = [])
    {
        $macros = $this->macros;
        $this->loadTemplate("partial_menu.phtml", "layout_default.phtml", 114)->display($context);
    }

    // line 115
    public function block_left_bottom($context, array $blocks = [])
    {
        $macros = $this->macros;
    }

    // line 118
    public function block_before_content($context, array $blocks = [])
    {
        $macros = $this->macros;
    }

    // line 123
    public function block_breadcrumbs($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 124
        echo "                <ul>
                    <li class=\"firstB\"><a href=\"";
        // line 125
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/");
        echo "\">";
        echo twig_escape_filter($this->env, gettext("Home"), "html", null, true);
        echo "</a></li>
                    <li class=\"lastB\">";
        // line 126
        $this->displayBlock("meta_title", $context, $blocks);
        echo "</li>
                </ul>
                ";
    }

    // line 132
    public function block_top_content($context, array $blocks = [])
    {
        $macros = $this->macros;
    }

    // line 133
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
    }

    // line 145
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
        return array (  517 => 145,  511 => 133,  505 => 132,  498 => 126,  492 => 125,  489 => 124,  485 => 123,  479 => 118,  473 => 115,  466 => 114,  460 => 113,  454 => 109,  448 => 21,  442 => 9,  437 => 152,  429 => 146,  427 => 145,  422 => 142,  420 => 141,  413 => 136,  409 => 134,  406 => 133,  404 => 132,  399 => 129,  397 => 123,  391 => 119,  389 => 118,  385 => 116,  382 => 115,  380 => 114,  376 => 113,  373 => 112,  369 => 110,  367 => 109,  364 => 108,  362 => 107,  348 => 98,  345 => 97,  337 => 95,  335 => 94,  332 => 93,  319 => 91,  306 => 90,  303 => 89,  300 => 88,  298 => 87,  295 => 86,  281 => 84,  278 => 83,  276 => 82,  273 => 81,  259 => 79,  256 => 78,  254 => 77,  251 => 76,  237 => 74,  234 => 73,  232 => 72,  221 => 68,  205 => 59,  198 => 58,  187 => 54,  181 => 53,  175 => 51,  173 => 50,  170 => 49,  168 => 48,  164 => 47,  160 => 46,  155 => 43,  150 => 40,  137 => 38,  133 => 37,  129 => 35,  126 => 34,  124 => 33,  112 => 32,  106 => 28,  100 => 26,  98 => 25,  93 => 22,  91 => 21,  86 => 19,  82 => 18,  79 => 17,  76 => 16,  74 => 15,  63 => 9,  57 => 5,  55 => 4,  53 => 3,  50 => 2,  48 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% import \"macro_functions.phtml\" as mf %}

{% set profile = admin.profile_get %}
{% set company = guest.system_company %}

<!DOCTYPE html>
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
                <a href=\"{{ 'staff/profile'|alink }}\" title=\"\"><img src=\"{{ profile.email|gravatar }}?size=20\" alt=\"{{ profile.name }}\" /></a><span>{{ 'Hi,'|trans }} {{ profile.name }}!</span>
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
                    <li class=\"loading\" style=\"display:none;\"><img src=\"images/loader.gif\" alt=\"\" /><span>{{ 'Loading ...'|trans }}</span></li>
                    <li class=\"dd\"><span><i class=\"sprite-topnav sprite-topnav-register\" style=\"margin-right: 5px;\"></i>{{ 'New'|trans }}</span>
                        {% include \"partial_menu_top.phtml\" %}
                    </li>
                    {% if admin.system_is_allowed({ \"mod\": \"system\" }) %}
                    <li class=\"dd\"><span><i class=\"sprite-topnav sprite-topnav-settings\" style=\"margin-right: 5px;\"></i>{{ 'Settings'|trans }}</span>
                        <ul class=\"menu_body\">
                            <li><a href=\"{{ 'system'|alink }}\" title=\"\">{{ 'All settings'|trans }}</a></li>
                            <li><a href=\"{{ 'theme'|alink }}/{{ guest.extension_theme.code }}\" title=\"\">{{ 'Theme settings'|trans }}</a></li>
                        </ul>
                    </li>
                    {% endif %}
                    <li><a href=\"{{ '/'|link }}\" title=\"\" target=\"_blank\"><span><i class=\"sprite-topnav sprite-topnav-mainWebsite\" style=\"margin-right: 5px;\"></i>{{ 'Visit site'|trans }}</span></a></li>
                    <li><a href=\"{{ 'api/admin/profile/logout'|link }}\" title=\"\" class=\"api-link\" data-api-redirect=\"{{ 'staff/login'|alink }}\"><span><i class=\"sprite-topnav sprite-topnav-logout\" style=\"margin-right: 5px;\"></i>{{ 'Logout'|trans }}</span></a></li>
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
        \t<li class=\"iMegaphone\"><a href=\"{{ 'notification'|alink }}\" title=\"\"><span>{{ 'Notifications'|trans }}</span></a>{% if count_notifications %}<span class=\"numberMiddle\">{{ count_notifications }}</span>{% endif %}</li>
            {% endif %}
            
            {% if admin.system_is_allowed({\"mod\":\"order\"}) %}
            {% set count_orders = admin.order_get_statuses.failed_setup %}
        \t<li class=\"iOrders\"><a href=\"{{ 'order'|alink({'status' : 'failed_setup'}) }}\" title=\"\"><span><i class=\"sprite-23 sprite-23-basket2\"></i>{{ 'Orders'|trans }}</span></a>{% if count_orders %}<span class=\"numberMiddle\">{{ count_orders }}</span>{% endif %}</li>
            {% endif %}
            
            {% if admin.system_is_allowed({\"mod\":\"invoice\"}) %}
            {% set count_invoices = admin.invoice_get_statuses.unpaid %}
        \t<li class=\"iInvoices\"><a href=\"{{ 'invoice'|alink({'status' : 'unpaid'}) }}\" title=\"\"><span><i class=\"sprite-23 sprite-23-money\"></i>{{ 'Invoices'|trans }}</span></a>{% if count_invoices %}<span class=\"numberMiddle\">{{ count_invoices }}</span>{% endif %}</li>
            {% endif %}
            
            {% if admin.system_is_allowed({\"mod\":\"support\"}) %}
            {% set count_tickets = admin.support_ticket_get_statuses.open %}
            {% set count_ptickets = admin.support_public_ticket_get_statuses.open %}
        \t<li class=\"iSpeech\"><a href=\"{{ 'support/public-tickets'|alink({'status' : 'open'}) }}\" title=\"\"><span><i class=\"sprite-23 sprite-23-speech\"></i>{{ 'Inquiries'|trans }}</span></a>{% if count_ptickets %}<span class=\"numberMiddle\">{{ count_ptickets }}</span>{% endif %}</li>
        \t<li class=\"iMes\"><a href=\"{{ 'support'|alink({'status' : 'open'}) }}\" title=\"\"><span><i class=\"sprite-23 sprite-23-dialog\"></i>{{ 'Tickets'|trans }}</span></a>{% if count_tickets %}<span class=\"numberMiddle\">{{ count_tickets }}</span>{% endif %}</li>
            {% endif %}
            
            {% if admin.system_is_allowed({\"mod\":\"client\"}) %}
            <li><a href=\"{{ 'client'|alink }}\" title=\"\"><span><i class=\"sprite-23 sprite-23-user\"></i>{{ 'Clients'|trans }}</span></a></li>
            {% endif %}
            
        \t<li><a href=\"{{ 'index'|alink }}\" title=\"\"><span><i class=\"sprite-23 sprite-23-home\"></i>{{ 'Dashboard'|trans }}</span></a></li>
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
                    <li class=\"firstB\"><a href=\"{{ '/'|alink }}\">{{ 'Home'|trans }}</a></li>
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
</html>", "layout_default.phtml", "/shared/httpd/up-boxbilling/FOSSBilling/src/bb-themes/admin_default/html/layout_default.phtml");
    }
}
