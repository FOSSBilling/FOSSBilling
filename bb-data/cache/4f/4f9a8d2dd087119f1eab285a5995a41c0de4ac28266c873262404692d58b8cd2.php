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

/* partial_menu_top.phtml */
class __TwigTemplate_9c4ee4ade73284c3fa3cccdac52c8a1559a4bfa50f34efe8bdb092eab914889d extends \Twig\Template
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
        echo "                        <ul class=\"menu_body\">
                            ";
        // line 2
        if (twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "system_is_allowed", [0 => ["mod" => "order"]], "method", false, false, false, 2)) {
            // line 3
            echo "                            <li><a href=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("order#tab-new");
            echo "\" title=\"\">";
            echo gettext("Order");
            echo "</a></li>
                            ";
        }
        // line 5
        echo "                            
                            ";
        // line 6
        if (twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "system_is_allowed", [0 => ["mod" => "invoice"]], "method", false, false, false, 6)) {
            // line 7
            echo "                            <li><a href=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("invoice#tab-new");
            echo "\" title=\"\">";
            echo gettext("Invoice");
            echo "</a></li>
                            ";
        }
        // line 9
        echo "                            
                            ";
        // line 10
        if (twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "system_is_allowed", [0 => ["mod" => "client"]], "method", false, false, false, 10)) {
            // line 11
            echo "                            <li><a href=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("client#tab-new");
            echo "\" title=\"\">";
            echo gettext("Client");
            echo "</a></li>
                            ";
        }
        // line 13
        echo "                            
                            ";
        // line 14
        if (twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "system_is_allowed", [0 => ["mod" => "product"]], "method", false, false, false, 14)) {
            // line 15
            echo "                            <li><a href=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("product#tab-new");
            echo "\" title=\"\">";
            echo gettext("Product");
            echo "</a></li>
                            ";
        }
        // line 17
        echo "                            
                            ";
        // line 18
        if (twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "system_is_allowed", [0 => ["mod" => "support"]], "method", false, false, false, 18)) {
            // line 19
            echo "                            <li><a href=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support#tab-new");
            echo "\" title=\"\">";
            echo gettext("Support ticket");
            echo "</a></li>
                            ";
        }
        // line 21
        echo "                            
                            ";
        // line 22
        if (twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "system_is_allowed", [0 => ["mod" => "staff"]], "method", false, false, false, 22)) {
            // line 23
            echo "                            <li><a href=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("extension/settings/staff#tab-new");
            echo "\" title=\"\">";
            echo gettext("Staff member");
            echo "</a></li>
                            ";
        }
        // line 25
        echo "                            
                            ";
        // line 26
        if ((twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "extension_is_on", [0 => ["mod" => "news"]], "method", false, false, false, 26) && twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "system_is_allowed", [0 => ["mod" => "news"]], "method", false, false, false, 26))) {
            // line 27
            echo "                            <li><a href=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("news#tab-new");
            echo "\" title=\"\">";
            echo gettext("Announcement");
            echo "</a></li>
                            ";
        }
        // line 29
        echo "                            
                            ";
        // line 30
        if ((twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "extension_is_on", [0 => ["mod" => "forum"]], "method", false, false, false, 30) && twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "system_is_allowed", [0 => ["mod" => "forum"]], "method", false, false, false, 30))) {
            // line 31
            echo "                            <li><a href=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("forum#tab-new");
            echo "\" title=\"\">";
            echo gettext("Forum topic");
            echo "</a></li>
                            ";
        }
        // line 33
        echo "                        </ul>";
    }

    public function getTemplateName()
    {
        return "partial_menu_top.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  141 => 33,  133 => 31,  131 => 30,  128 => 29,  120 => 27,  118 => 26,  115 => 25,  107 => 23,  105 => 22,  102 => 21,  94 => 19,  92 => 18,  89 => 17,  81 => 15,  79 => 14,  76 => 13,  68 => 11,  66 => 10,  63 => 9,  55 => 7,  53 => 6,  50 => 5,  42 => 3,  40 => 2,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("                        <ul class=\"menu_body\">
                            {% if admin.system_is_allowed({\"mod\":\"order\"}) %}
                            <li><a href=\"{{ 'order#tab-new'|alink }}\" title=\"\">{% trans 'Order' %}</a></li>
                            {% endif %}
                            
                            {% if admin.system_is_allowed({\"mod\":\"invoice\"}) %}
                            <li><a href=\"{{ 'invoice#tab-new'|alink }}\" title=\"\">{% trans 'Invoice' %}</a></li>
                            {% endif %}
                            
                            {% if admin.system_is_allowed({\"mod\":\"client\"}) %}
                            <li><a href=\"{{ 'client#tab-new'|alink }}\" title=\"\">{% trans 'Client' %}</a></li>
                            {% endif %}
                            
                            {% if admin.system_is_allowed({\"mod\":\"product\"}) %}
                            <li><a href=\"{{ 'product#tab-new'|alink }}\" title=\"\">{% trans 'Product' %}</a></li>
                            {% endif %}
                            
                            {% if admin.system_is_allowed({\"mod\":\"support\"}) %}
                            <li><a href=\"{{ 'support#tab-new'|alink }}\" title=\"\">{% trans 'Support ticket' %}</a></li>
                            {% endif %}
                            
                            {% if admin.system_is_allowed({\"mod\":\"staff\"}) %}
                            <li><a href=\"{{ 'extension/settings/staff#tab-new'|alink }}\" title=\"\">{% trans 'Staff member' %}</a></li>
                            {% endif %}
                            
                            {% if guest.extension_is_on({\"mod\":\"news\"}) and admin.system_is_allowed({\"mod\":\"news\"}) %}
                            <li><a href=\"{{ 'news#tab-new'|alink }}\" title=\"\">{% trans 'Announcement' %}</a></li>
                            {% endif %}
                            
                            {% if guest.extension_is_on({\"mod\":\"forum\"}) and admin.system_is_allowed({\"mod\":\"forum\"}) %}
                            <li><a href=\"{{ 'forum#tab-new'|alink }}\" title=\"\">{% trans 'Forum topic' %}</a></li>
                            {% endif %}
                        </ul>", "partial_menu_top.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/src/bb-themes/admin_default/html/partial_menu_top.phtml");
    }
}
