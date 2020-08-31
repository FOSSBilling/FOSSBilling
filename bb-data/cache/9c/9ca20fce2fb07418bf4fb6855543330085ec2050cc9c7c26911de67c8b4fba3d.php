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

/* partial_menu.phtml */
class __TwigTemplate_f1b861756c78f631bdb3592e79e4a9247356c5fdf2b5de2232197c4531fb4b3b extends \Twig\Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
        ];
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 1
        echo "<ul class=\"main-navigation nav-collapse collapse\">

    ";
        // line 3
        if (twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "side_menu_dashboard", [], "any", false, false, false, 3)) {
            // line 4
            echo "    <li class=\"main-menu\">
        <a class=\"no-submenu\" href=\"";
            // line 5
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/");
            echo "\"><span class=\"awe-home\"></span>";
            echo gettext("Dashboard");
            echo "</a>
    </li>
    ";
        }
        // line 8
        echo "    
    ";
        // line 9
        if (twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "side_menu_order", [], "any", false, false, false, 9)) {
            // line 10
            echo "    <li class=\"main-menu\">
        <a href=\"#\" class=\"order-button\"><span class=\"awe-shopping-cart\"></span>";
            // line 11
            echo gettext("Order");
            echo "</a>
    </li>
    ";
        }
        // line 14
        echo "
    ";
        // line 15
        if (twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "side_menu_support", [], "any", false, false, false, 15)) {
            // line 16
            echo "    <li class=\"main-menu\">
        <a href=\"";
            // line 17
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/support");
            echo "\"><span class=\"awe-wrench\"></span>";
            echo gettext("Support");
            echo "</a>
    </li>
    ";
        }
        // line 20
        echo "
    ";
        // line 21
        if (twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "side_menu_services", [], "any", false, false, false, 21)) {
            // line 22
            echo "    <li class=\"main-menu\">
        <a href=\"";
            // line 23
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/order/service");
            echo "\"><span class=\"awe-cogs\"></span>";
            echo gettext("Services");
            echo "</a>
    </li>
    ";
        }
        // line 26
        echo "
    ";
        // line 27
        if (twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "side_menu_invoices", [], "any", false, false, false, 27)) {
            // line 28
            echo "    <li class=\"main-menu\">
        <a href=\"";
            // line 29
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/invoice");
            echo "\"><span class=\"awe-file-alt\"></span>";
            echo gettext("Invoices");
            echo "</a>
    </li>
    ";
        }
        // line 32
        echo "
    ";
        // line 33
        if (twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "side_menu_emails", [], "any", false, false, false, 33)) {
            // line 34
            echo "    <li class=\"main-menu\">
        <a  href=\"";
            // line 35
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/email");
            echo "\"><span class=\"awe-envelope-alt\"></span>";
            echo gettext("Emails");
            echo "</a>
    </li>
    ";
        }
        // line 38
        echo "
    ";
        // line 39
        if (twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "side_menu_payments", [], "any", false, false, false, 39)) {
            // line 40
            echo "    <li class=\"main-menu\">
        <a href=\"";
            // line 41
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/client/balance");
            echo "\"><span class=\"awe-credit-card\"></span>";
            echo gettext("Payments history");
            echo "</a>
    </li>
    ";
        }
        // line 44
        echo "
    ";
        // line 45
        if (twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "extension_is_on", [0 => ["mod" => "news"]], "method", false, false, false, 45)) {
            // line 46
            echo "    <li class=\"main-menu\">
        <a href=\"";
            // line 47
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/news");
            echo "\"><span class=\"awe-edit\"></span>";
            echo gettext("Blog");
            echo "</a>
    </li>
    ";
        }
        // line 50
        echo "
    ";
        // line 51
        if (twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "extension_is_on", [0 => ["mod" => "kb"]], "method", false, false, false, 51)) {
            // line 52
            echo "    <li class=\"main-menu\">
        <a href=\"";
            // line 53
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/kb");
            echo "\"><span class=\"awe-book\"></span>";
            echo gettext("Knowledge Base");
            echo "</a>
    </li>
    ";
        }
        // line 56
        echo "
    ";
        // line 57
        if (twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "extension_is_on", [0 => ["mod" => "forum"]], "method", false, false, false, 57)) {
            // line 58
            echo "    <li class=\"main-menu\">
        <a href=\"";
            // line 59
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/forum");
            echo "\"><span class=\"awe-comments\"></span>";
            echo gettext("Forum");
            echo "</a>
    </li>
    ";
        }
        // line 62
        echo "</ul>

<script type=\"text/javascript\">
    \$('.main-menu').each(function(index){
        var menu_link = \$(this).children('a').attr('href');
        if (\"";
        // line 67
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_current_url", [], "any", false, false, false, 67), "html", null, true);
        echo "\" ==\"";
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/");
        echo "\"){
            \$('.main-menu:first').addClass(\"current\");
        }
        else if ('";
        // line 70
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_current_url", [], "any", false, false, false, 70), "html", null, true);
        echo "'.indexOf(menu_link) >= 0 && menu_link != \"";
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/");
        echo "\"){
            \$(this).addClass(\"current\");
      }
    });

</script>";
    }

    public function getTemplateName()
    {
        return "partial_menu.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  211 => 70,  203 => 67,  196 => 62,  188 => 59,  185 => 58,  183 => 57,  180 => 56,  172 => 53,  169 => 52,  167 => 51,  164 => 50,  156 => 47,  153 => 46,  151 => 45,  148 => 44,  140 => 41,  137 => 40,  135 => 39,  132 => 38,  124 => 35,  121 => 34,  119 => 33,  116 => 32,  108 => 29,  105 => 28,  103 => 27,  100 => 26,  92 => 23,  89 => 22,  87 => 21,  84 => 20,  76 => 17,  73 => 16,  71 => 15,  68 => 14,  62 => 11,  59 => 10,  57 => 9,  54 => 8,  46 => 5,  43 => 4,  41 => 3,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("<ul class=\"main-navigation nav-collapse collapse\">

    {% if settings.side_menu_dashboard %}
    <li class=\"main-menu\">
        <a class=\"no-submenu\" href=\"{{ '/'|link }}\"><span class=\"awe-home\"></span>{% trans 'Dashboard' %}</a>
    </li>
    {% endif %}
    
    {% if settings.side_menu_order %}
    <li class=\"main-menu\">
        <a href=\"#\" class=\"order-button\"><span class=\"awe-shopping-cart\"></span>{% trans 'Order' %}</a>
    </li>
    {% endif %}

    {% if settings.side_menu_support %}
    <li class=\"main-menu\">
        <a href=\"{{ '/support'|link }}\"><span class=\"awe-wrench\"></span>{% trans 'Support' %}</a>
    </li>
    {% endif %}

    {% if settings.side_menu_services %}
    <li class=\"main-menu\">
        <a href=\"{{ '/order/service'|link }}\"><span class=\"awe-cogs\"></span>{% trans 'Services' %}</a>
    </li>
    {% endif %}

    {% if settings.side_menu_invoices %}
    <li class=\"main-menu\">
        <a href=\"{{ '/invoice'|link }}\"><span class=\"awe-file-alt\"></span>{% trans 'Invoices' %}</a>
    </li>
    {% endif %}

    {% if settings.side_menu_emails %}
    <li class=\"main-menu\">
        <a  href=\"{{ '/email'|link }}\"><span class=\"awe-envelope-alt\"></span>{% trans 'Emails' %}</a>
    </li>
    {% endif %}

    {% if settings.side_menu_payments %}
    <li class=\"main-menu\">
        <a href=\"{{ '/client/balance'|link }}\"><span class=\"awe-credit-card\"></span>{% trans 'Payments history' %}</a>
    </li>
    {% endif %}

    {% if guest.extension_is_on({\"mod\":\"news\"}) %}
    <li class=\"main-menu\">
        <a href=\"{{ '/news'|link }}\"><span class=\"awe-edit\"></span>{% trans 'Blog' %}</a>
    </li>
    {% endif %}

    {% if guest.extension_is_on({\"mod\":\"kb\"}) %}
    <li class=\"main-menu\">
        <a href=\"{{ '/kb'|link }}\"><span class=\"awe-book\"></span>{% trans 'Knowledge Base' %}</a>
    </li>
    {% endif %}

    {% if guest.extension_is_on({\"mod\":\"forum\"}) %}
    <li class=\"main-menu\">
        <a href=\"{{ '/forum'|link }}\"><span class=\"awe-comments\"></span>{% trans 'Forum' %}</a>
    </li>
    {% endif %}
</ul>

<script type=\"text/javascript\">
    \$('.main-menu').each(function(index){
        var menu_link = \$(this).children('a').attr('href');
        if (\"{{ guest.system_current_url }}\" ==\"{{ '/'|link }}\"){
            \$('.main-menu:first').addClass(\"current\");
        }
        else if ('{{ guest.system_current_url }}'.indexOf(menu_link) >= 0 && menu_link != \"{{ '/'|link }}\"){
            \$(this).addClass(\"current\");
      }
    });

</script>", "partial_menu.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-themes/huraga/html/partial_menu.phtml");
    }
}
