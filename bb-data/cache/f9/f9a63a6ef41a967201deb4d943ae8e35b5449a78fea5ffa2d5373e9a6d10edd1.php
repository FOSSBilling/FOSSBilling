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

/* mod_invoice_gateways.phtml */
class __TwigTemplate_32e26dd39b4af38dbeb5c6263c55d2a1c796f6e44904163842ec7a773234c205 extends \Twig\Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->blocks = [
            'meta_title' => [$this, 'block_meta_title'],
            'content' => [$this, 'block_content'],
        ];
    }

    protected function doGetParent(array $context)
    {
        // line 1
        return $this->loadTemplate(((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ajax", [], "any", false, false, false, 1)) ? ("layout_blank.phtml") : ("layout_default.phtml")), "mod_invoice_gateways.phtml", 1);
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 2
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_invoice_gateways.phtml", 2)->unwrap();
        // line 3
        $context["active_menu"] = "system";
        // line 1
        $this->getParent($context)->display($context, array_merge($this->blocks, $blocks));
    }

    // line 4
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo "Payment gateways";
    }

    // line 6
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 7
        echo "<div class=\"widget simpleTabs\">

    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">";
        // line 10
        echo gettext("Payment gateways");
        echo "</a></li>
        <li><a href=\"#tab-new\">";
        // line 11
        echo gettext("New payment gateway");
        echo "</a></li>
    </ul>

    <div class=\"tabs_container\">
        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-index\">
            ";
        // line 17
        echo twig_call_macro($macros["mf"], "macro_table_search", [], 17, $context, $this->getSourceContext());
        echo "
            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td>";
        // line 21
        echo gettext("Title");
        echo "</td>
                        <td>";
        // line 22
        echo gettext("Code");
        echo "</td>
                        <td>";
        // line 23
        echo gettext("Enabled");
        echo "</td>
                        <td>";
        // line 24
        echo gettext("Allow one time payments");
        echo "</td>
                        <td>";
        // line 25
        echo gettext("Allow subscriptions");
        echo "</td>
                        <td style=\"width: 18%\">&nbsp;</td>
                    </tr>
                </thead>

                <tbody>
                ";
        // line 31
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "invoice_gateway_get_list", [0 => twig_array_merge(["per_page" => 100], ($context["request"] ?? null))], "method", false, false, false, 31), "list", [], "any", false, false, false, 31));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["_key"] => $context["gateway"]) {
            // line 32
            echo "                <tr>
                    <td>";
            // line 33
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["gateway"], "title", [], "any", false, false, false, 33), "html", null, true);
            echo "</td>
                    <td>";
            // line 34
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["gateway"], "code", [], "any", false, false, false, 34), "html", null, true);
            echo "</td>
                    <td>";
            // line 35
            echo twig_call_macro($macros["mf"], "macro_q", [twig_get_attribute($this->env, $this->source, $context["gateway"], "enabled", [], "any", false, false, false, 35)], 35, $context, $this->getSourceContext());
            echo "</td>
                    <td>";
            // line 36
            echo twig_call_macro($macros["mf"], "macro_q", [twig_get_attribute($this->env, $this->source, $context["gateway"], "allow_single", [], "any", false, false, false, 36)], 36, $context, $this->getSourceContext());
            echo "</td>
                    <td>";
            // line 37
            echo twig_call_macro($macros["mf"], "macro_q", [twig_get_attribute($this->env, $this->source, $context["gateway"], "allow_recurrent", [], "any", false, false, false, 37)], 37, $context, $this->getSourceContext());
            echo "</td>
                    <td class=\"actions\">
                        <a class=\"bb-button btn14\" href=\"";
            // line 39
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/invoice/gateway");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["gateway"], "id", [], "any", false, false, false, 39), "html", null, true);
            echo "\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                        <a class=\"bb-button btn14 api-link\" href=\"";
            // line 40
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/invoice/gateway_copy", ["id" => twig_get_attribute($this->env, $this->source, $context["gateway"], "id", [], "any", false, false, false, 40)]);
            echo "\" data-api-redirect=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("invoice/gateways");
            echo "\" title=\"Copy\"><img src=\"images/icons/dark/baloons.png\" alt=\"\"></a>
                        <a class=\"bb-button btn14 bb-rm-tr api-link\" data-api-confirm=\"Are you sure?\" data-api-redirect=\"";
            // line 41
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("invoice/gateways");
            echo "\" href=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/invoice/gateway_delete", ["id" => twig_get_attribute($this->env, $this->source, $context["gateway"], "id", [], "any", false, false, false, 41)]);
            echo "\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                    </td>
                </tr>
                </tbody>

                ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 47
            echo "                <tbody>
                    <tr>
                        <td colspan=\"5\">
                            ";
            // line 50
            echo gettext("The list is empty");
            // line 51
            echo "                        </td>
                    </tr>
                </tbody>
                ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['gateway'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 55
        echo "            </table>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-new\">
            ";
        // line 59
        $this->loadTemplate("partial_extensions.phtml", "mod_invoice_gateways.phtml", 59)->display(twig_to_array(["type" => "payment-gateway", "header" => "List of payment gateways on extensions site"]));
        // line 60
        echo "            <div class=\"body\">
                <h1 class=\"pt10\">";
        // line 61
        echo gettext("Adding new payment gateway");
        echo "</h1>
                <p>";
        // line 62
        echo gettext("Although BoxBilling ships with most popular payment gateways, you can install other gateways.");
        echo "</p>
                <p>";
        // line 63
        echo gettext("Follow these instructions to install new payment gateway.");
        echo "</p>
                <div class=\"pt20 list arrowGrey\">
                    <ul>
                        <li>";
        // line 66
        echo gettext("Check for payment gateway at");
        echo " <a href=\"http://extensions.boxbilling.com/\" target=\"_blank\">BoxBilling extensions site</a> ";
        echo gettext("for more payment gateways");
        echo "</li>
                        <li>";
        // line 67
        echo gettext("Download payment gateway extension file and place it to");
        echo "<strong>";
        echo twig_escape_filter($this->env, twig_constant("BB_PATH_LIBRARY"), "html", null, true);
        echo "/Payment/Adapter</strong></li>
                        <li>";
        // line 68
        echo gettext("After file is uploaded in place, reload this page.");
        echo "</li>
                        <li>";
        // line 69
        echo gettext("Select uploaded file name and click on install.");
        echo "</li>
                        <li>";
        // line 70
        echo gettext("Payment gateway will be installed in BoxBilling and can be configured in \"Payment gateways\" tab.");
        echo "</li>
                        <li>";
        // line 71
        echo gettext("For developers. Read");
        echo " <a href=\"http://docs.boxbilling.com/en/latest/reference/extension.html#payment-gateway\" target=\"_blank\">BoxBilling documentation</a> ";
        echo gettext("to learn how to create your own payment gateway and share it with community");
        echo "</li>
                    </ul>
                </div>
            </div>

            ";
        // line 76
        if ((twig_length_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "invoice_gateway_get_available", [], "any", false, false, false, 76)) > 0)) {
            // line 77
            echo "            <table class=\"tableStatic wide\">
            <thead>
                <tr>
                    <td>";
            // line 80
            echo gettext("Code");
            echo "</td>
                    <td style=\"width: 5%\">";
            // line 81
            echo gettext("Install");
            echo "</td>
                </tr>
            </thead>

            <tbody>
            ";
            // line 86
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "invoice_gateway_get_available", [], "any", false, false, false, 86));
            $context['_iterated'] = false;
            foreach ($context['_seq'] as $context["_key"] => $context["gtw"]) {
                // line 87
                echo "            <tr>
                <td>";
                // line 88
                echo twig_escape_filter($this->env, $context["gtw"], "html", null, true);
                echo "</td>
                <td class=\"actions\">
                    <a class=\"bb-button btn14 api-link\" href=\"";
                // line 90
                echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/invoice/gateway_install", ["code" => $context["gtw"]]);
                echo "\" data-api-redirect=\"";
                echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("invoice/gateways");
                echo "\" title=\"Install\"><img src=\"images/icons/dark/cog.png\" alt=\"\"></a>
                </td>
            </tr>
            </tbody>

            ";
                $context['_iterated'] = true;
            }
            if (!$context['_iterated']) {
                // line 96
                echo "            <tbody>
                <tr>
                    <td colspan=\"5\">
                        ";
                // line 99
                echo gettext("All payment gateways installed");
                // line 100
                echo "                    </td>
                </tr>
            </tbody>
            ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['gtw'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 104
            echo "        </table>
                ";
        }
        // line 106
        echo "        </div>
        
    </div>
</div>

";
    }

    public function getTemplateName()
    {
        return "mod_invoice_gateways.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  306 => 106,  302 => 104,  293 => 100,  291 => 99,  286 => 96,  273 => 90,  268 => 88,  265 => 87,  260 => 86,  252 => 81,  248 => 80,  243 => 77,  241 => 76,  231 => 71,  227 => 70,  223 => 69,  219 => 68,  213 => 67,  207 => 66,  201 => 63,  197 => 62,  193 => 61,  190 => 60,  188 => 59,  182 => 55,  173 => 51,  171 => 50,  166 => 47,  153 => 41,  147 => 40,  141 => 39,  136 => 37,  132 => 36,  128 => 35,  124 => 34,  120 => 33,  117 => 32,  112 => 31,  103 => 25,  99 => 24,  95 => 23,  91 => 22,  87 => 21,  80 => 17,  71 => 11,  67 => 10,  62 => 7,  58 => 6,  51 => 4,  47 => 1,  45 => 3,  43 => 2,  36 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}
{% import \"macro_functions.phtml\" as mf %}
{% set active_menu = 'system' %}
{% block meta_title %}Payment gateways{% endblock %}

{% block content %}
<div class=\"widget simpleTabs\">

    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">{% trans 'Payment gateways' %}</a></li>
        <li><a href=\"#tab-new\">{% trans 'New payment gateway' %}</a></li>
    </ul>

    <div class=\"tabs_container\">
        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-index\">
            {{ mf.table_search }}
            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td>{% trans 'Title' %}</td>
                        <td>{% trans 'Code' %}</td>
                        <td>{% trans 'Enabled' %}</td>
                        <td>{% trans 'Allow one time payments' %}</td>
                        <td>{% trans 'Allow subscriptions' %}</td>
                        <td style=\"width: 18%\">&nbsp;</td>
                    </tr>
                </thead>

                <tbody>
                {% for gateway in admin.invoice_gateway_get_list({\"per_page\":100}|merge(request)).list %}
                <tr>
                    <td>{{ gateway.title }}</td>
                    <td>{{ gateway.code }}</td>
                    <td>{{ mf.q(gateway.enabled) }}</td>
                    <td>{{ mf.q(gateway.allow_single) }}</td>
                    <td>{{ mf.q(gateway.allow_recurrent) }}</td>
                    <td class=\"actions\">
                        <a class=\"bb-button btn14\" href=\"{{ '/invoice/gateway'|alink }}/{{gateway.id}}\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                        <a class=\"bb-button btn14 api-link\" href=\"{{ 'api/admin/invoice/gateway_copy'|link({'id' : gateway.id}) }}\" data-api-redirect=\"{{ 'invoice/gateways'|alink }}\" title=\"Copy\"><img src=\"images/icons/dark/baloons.png\" alt=\"\"></a>
                        <a class=\"bb-button btn14 bb-rm-tr api-link\" data-api-confirm=\"Are you sure?\" data-api-redirect=\"{{ 'invoice/gateways'|alink }}\" href=\"{{ 'api/admin/invoice/gateway_delete'|link({'id' : gateway.id}) }}\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                    </td>
                </tr>
                </tbody>

                {% else %}
                <tbody>
                    <tr>
                        <td colspan=\"5\">
                            {% trans 'The list is empty' %}
                        </td>
                    </tr>
                </tbody>
                {% endfor %}
            </table>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-new\">
            {% include \"partial_extensions.phtml\" with {'type': 'payment-gateway', 'header':\"List of payment gateways on extensions site\"} only %}
            <div class=\"body\">
                <h1 class=\"pt10\">{% trans 'Adding new payment gateway' %}</h1>
                <p>{% trans 'Although BoxBilling ships with most popular payment gateways, you can install other gateways.' %}</p>
                <p>{% trans 'Follow these instructions to install new payment gateway.' %}</p>
                <div class=\"pt20 list arrowGrey\">
                    <ul>
                        <li>{% trans 'Check for payment gateway at' %} <a href=\"http://extensions.boxbilling.com/\" target=\"_blank\">BoxBilling extensions site</a> {% trans 'for more payment gateways' %}</li>
                        <li>{% trans 'Download payment gateway extension file and place it to' %}<strong>{{ constant('BB_PATH_LIBRARY') }}/Payment/Adapter</strong></li>
                        <li>{% trans 'After file is uploaded in place, reload this page.' %}</li>
                        <li>{% trans 'Select uploaded file name and click on install.' %}</li>
                        <li>{% trans 'Payment gateway will be installed in BoxBilling and can be configured in \\\"Payment gateways\\\" tab.' %}</li>
                        <li>{% trans 'For developers. Read' %} <a href=\"http://docs.boxbilling.com/en/latest/reference/extension.html#payment-gateway\" target=\"_blank\">BoxBilling documentation</a> {% trans 'to learn how to create your own payment gateway and share it with community' %}</li>
                    </ul>
                </div>
            </div>

            {% if admin.invoice_gateway_get_available|length > 0 %}
            <table class=\"tableStatic wide\">
            <thead>
                <tr>
                    <td>{% trans 'Code' %}</td>
                    <td style=\"width: 5%\">{% trans 'Install' %}</td>
                </tr>
            </thead>

            <tbody>
            {% for gtw in admin.invoice_gateway_get_available %}
            <tr>
                <td>{{ gtw }}</td>
                <td class=\"actions\">
                    <a class=\"bb-button btn14 api-link\" href=\"{{ 'api/admin/invoice/gateway_install'|link({'code' : gtw}) }}\" data-api-redirect=\"{{ 'invoice/gateways'|alink }}\" title=\"Install\"><img src=\"images/icons/dark/cog.png\" alt=\"\"></a>
                </td>
            </tr>
            </tbody>

            {% else %}
            <tbody>
                <tr>
                    <td colspan=\"5\">
                        {% trans 'All payment gateways installed' %}
                    </td>
                </tr>
            </tbody>
            {% endfor %}
        </table>
                {% endif %}
        </div>
        
    </div>
</div>

{% endblock %}
", "mod_invoice_gateways.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-themes/admin_default/html/mod_invoice_gateways.phtml");
    }
}
