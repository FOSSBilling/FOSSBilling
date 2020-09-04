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

/* mod_order_list.phtml */
class __TwigTemplate_30a6fb16552ccb66438eef120a6e2894fc7ceb7ec81f0d79702543719bdf2d72 extends \Twig\Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->blocks = [
            'meta_title' => [$this, 'block_meta_title'],
            'breadcrumb' => [$this, 'block_breadcrumb'],
            'content' => [$this, 'block_content'],
        ];
    }

    protected function doGetParent(array $context)
    {
        // line 1
        return $this->loadTemplate(((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ajax", [], "any", false, false, false, 1)) ? ("layout_blank.phtml") : ("layout_default.phtml")), "mod_order_list.phtml", 1);
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 3
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_order_list.phtml", 3)->unwrap();
        // line 1
        $this->getParent($context)->display($context, array_merge($this->blocks, $blocks));
    }

    // line 5
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo gettext("My Products & Services");
    }

    // line 7
    public function block_breadcrumb($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 8
        echo "<li class=\"service\">";
        echo gettext("Orders");
        echo "</li>
";
    }

    // line 11
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 12
        echo "<div class=\"row\">
    <article class=\"span12 data-block\">
        <div class=\"data-container\">
            <header>
                <h1>";
        // line 16
        echo gettext("Orders");
        echo "</h1>
                <p>";
        // line 17
        echo gettext("All of your orders are displayed here. Click on any order to get full information about it.");
        echo "</p>
            </header>

            <section>
                <table class=\"table table-striped table-bordered table-condensed table-hover\">
            <thead>
                <tr>
                    <th>";
        // line 24
        echo gettext("Product/Service");
        echo "</th>
                    <th>";
        // line 25
        echo gettext("Price");
        echo "</th>
                    <th>";
        // line 26
        echo gettext("Next due date");
        echo "</th>
                    <th>";
        // line 27
        echo gettext("Status");
        echo "</th>
                    <th>&nbsp</th>
                </tr>
            </thead>
            <tbody>
                ";
        // line 32
        $context["orders"] = twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "order_get_list", [0 => ["per_page" => 10, "page" => twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 32), "hide_addons" => 1]], "method", false, false, false, 32);
        // line 33
        echo "                ";
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["orders"] ?? null), "list", [], "any", false, false, false, 33));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["i"] => $context["order"]) {
            // line 34
            echo "                <tr class=\"";
            echo twig_escape_filter($this->env, twig_cycle([0 => "odd", 1 => "even"], $context["i"]), "html", null, true);
            echo "\">
                    <td><a href=\"";
            // line 35
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/order/service/manage");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["order"], "id", [], "any", false, false, false, 35), "html", null, true);
            echo "\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["order"], "title", [], "any", false, false, false, 35), "html", null, true);
            echo "</a></td>
                    <td>";
            // line 36
            echo twig_money($this->env, twig_get_attribute($this->env, $this->source, $context["order"], "total", [], "any", false, false, false, 36), twig_get_attribute($this->env, $this->source, $context["order"], "currency", [], "any", false, false, false, 36));
            echo " ";
            if (twig_get_attribute($this->env, $this->source, $context["order"], "period", [], "any", false, false, false, 36)) {
                echo twig_period_title($this->env, twig_get_attribute($this->env, $this->source, $context["order"], "period", [], "any", false, false, false, 36));
            }
            echo "</td>
                    <td>";
            // line 37
            if (twig_get_attribute($this->env, $this->source, $context["order"], "expires_at", [], "any", false, false, false, 37)) {
                echo twig_escape_filter($this->env, $this->extensions['Box_TwigExtensions']->twig_bb_date(twig_get_attribute($this->env, $this->source, $context["order"], "expires_at", [], "any", false, false, false, 37)), "html", null, true);
            } else {
                echo "-";
            }
            echo "</td>
                    <td><span class=\"label ";
            // line 38
            if ((twig_get_attribute($this->env, $this->source, $context["order"], "status", [], "any", false, false, false, 38) == "active")) {
                echo "label-success";
            } elseif ((twig_get_attribute($this->env, $this->source, $context["order"], "status", [], "any", false, false, false, 38) == "pending_setup")) {
                echo "label-warning";
            }
            echo "\">";
            echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, $context["order"], "status", [], "any", false, false, false, 38)], 38, $context, $this->getSourceContext());
            echo "</span></td>
                    <td class=\"actions\"><a class=\"bb-button\" href=\"";
            // line 39
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/order/service/manage");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["order"], "id", [], "any", false, false, false, 39), "html", null, true);
            echo "\"><span class=\"dark-icon i-drag\"></span></a></td>
                </tr>
                ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 42
            echo "                <tr>
                    <td colspan=\"5\">";
            // line 43
            echo gettext("The list is empty");
            echo "</td>
                </tr>
                ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['i'], $context['order'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 46
        echo "            </tbody>

        </table>
                ";
        // line 49
        $this->loadTemplate("partial_pagination.phtml", "mod_order_list.phtml", 49)->display(twig_array_merge($context, ["list" => ($context["orders"] ?? null)]));
        // line 50
        echo "            </section>
        </article>
    </div>
</div>
";
    }

    public function getTemplateName()
    {
        return "mod_order_list.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  188 => 50,  186 => 49,  181 => 46,  172 => 43,  169 => 42,  159 => 39,  149 => 38,  141 => 37,  133 => 36,  125 => 35,  120 => 34,  114 => 33,  112 => 32,  104 => 27,  100 => 26,  96 => 25,  92 => 24,  82 => 17,  78 => 16,  72 => 12,  68 => 11,  61 => 8,  57 => 7,  50 => 5,  46 => 1,  44 => 3,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}

{% import \"macro_functions.phtml\" as mf %}

{% block meta_title %}{% trans 'My Products & Services' %}{% endblock %}

{% block breadcrumb %}
<li class=\"service\">{% trans 'Orders' %}</li>
{% endblock %}

{% block content %}
<div class=\"row\">
    <article class=\"span12 data-block\">
        <div class=\"data-container\">
            <header>
                <h1>{% trans 'Orders' %}</h1>
                <p>{% trans 'All of your orders are displayed here. Click on any order to get full information about it.' %}</p>
            </header>

            <section>
                <table class=\"table table-striped table-bordered table-condensed table-hover\">
            <thead>
                <tr>
                    <th>{% trans 'Product/Service' %}</th>
                    <th>{% trans 'Price' %}</th>
                    <th>{% trans 'Next due date' %}</th>
                    <th>{% trans 'Status' %}</th>
                    <th>&nbsp</th>
                </tr>
            </thead>
            <tbody>
                {% set orders = client.order_get_list({\"per_page\":10, \"page\":request.page, \"hide_addons\":1}) %}
                {% for i, order in orders.list %}
                <tr class=\"{{ cycle(['odd', 'even'], i) }}\">
                    <td><a href=\"{{ '/order/service/manage'|link }}/{{order.id}}\">{{order.title}}</a></td>
                    <td>{{  order.total | money(order.currency) }} {% if order.period%}{{ order.period | period_title }}{% endif %}</td>
                    <td>{% if order.expires_at %}{{order.expires_at|bb_date}}{% else %}-{% endif %}</td>
                    <td><span class=\"label {% if order.status == 'active'%}label-success{% elseif order.status == 'pending_setup' %}label-warning{% endif %}\">{{ mf.status_name(order.status) }}</span></td>
                    <td class=\"actions\"><a class=\"bb-button\" href=\"{{ '/order/service/manage'|link }}/{{order.id}}\"><span class=\"dark-icon i-drag\"></span></a></td>
                </tr>
                {% else %}
                <tr>
                    <td colspan=\"5\">{% trans 'The list is empty' %}</td>
                </tr>
                {% endfor %}
            </tbody>

        </table>
                {% include \"partial_pagination.phtml\" with {'list': orders} %}
            </section>
        </article>
    </div>
</div>
{% endblock %}", "mod_order_list.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-modules/Order/html_client/mod_order_list.phtml");
    }
}
