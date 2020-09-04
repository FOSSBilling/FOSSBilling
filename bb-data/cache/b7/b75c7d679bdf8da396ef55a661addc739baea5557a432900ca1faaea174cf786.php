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

/* mod_invoice_tax.phtml */
class __TwigTemplate_2527d23c6425c130eccd41fcd0e2b5472121d945c316a92c8adf3d6e77923b82 extends \Twig\Template
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
        return "layout_default.phtml";
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 2
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_invoice_tax.phtml", 2)->unwrap();
        // line 4
        $context["active_menu"] = "system";
        // line 1
        $this->parent = $this->loadTemplate("layout_default.phtml", "mod_invoice_tax.phtml", 1);
        $this->parent->display($context, array_merge($this->blocks, $blocks));
    }

    // line 3
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo "Tax";
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
        echo gettext("Tax rules");
        echo "</a></li>
        <li><a href=\"#tab-new\">";
        // line 11
        echo gettext("New tax rule");
        echo "</a></li>
        <li><a href=\"#tab-settings\">";
        // line 12
        echo gettext("Tax settings");
        echo "</a></li>
        <li><a href=\"#tab-eu-vat\">";
        // line 13
        echo gettext("EU VAT");
        echo "</a></li>
    </ul>

    <div class=\"tabs_container\">
        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-index\">
            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td style=\"width: 2%\"><input type=\"checkbox\" class=\"batch-delete-master-checkbox\"/></td>
                        <td>";
        // line 23
        echo gettext("Name");
        echo "</td>
                        <td>";
        // line 24
        echo gettext("Country");
        echo "</td>
                        <td>";
        // line 25
        echo gettext("State/Region");
        echo "</td>
                        <td>";
        // line 26
        echo gettext("Tax rate");
        echo "</td>
                        <td>";
        // line 27
        echo gettext("Actions");
        echo "</td>
                    </tr>
                </thead>

                <tbody>
                ";
        // line 32
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "invoice_tax_get_list", [0 => ["per_page" => 100]], "method", false, false, false, 32), "list", [], "any", false, false, false, 32));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["_key"] => $context["tax"]) {
            // line 33
            echo "                <tr>
                    <td><input type=\"checkbox\" class=\"batch-delete-checkbox\" data-item-id=\"";
            // line 34
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tax"], "id", [], "any", false, false, false, 34), "html", null, true);
            echo "\"/></td>
                    <td>";
            // line 35
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tax"], "name", [], "any", false, false, false, 35), "html", null, true);
            echo "</td>
                    <td>
                        ";
            // line 37
            if (twig_get_attribute($this->env, $this->source, $context["tax"], "country", [], "any", false, false, false, 37)) {
                // line 38
                echo "                        ";
                echo twig_escape_filter($this->env, (($__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4 = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_countries", [], "any", false, false, false, 38)) && is_array($__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4) || $__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4 instanceof ArrayAccess ? ($__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4[twig_get_attribute($this->env, $this->source, $context["tax"], "country", [], "any", false, false, false, 38)] ?? null) : null), "html", null, true);
                echo "
                        ";
            } else {
                // line 40
                echo "                        ";
                echo gettext("Applies to any country");
                // line 41
                echo "                        ";
            }
            // line 42
            echo "                    </td>
                    <td>
                        ";
            // line 44
            if (twig_get_attribute($this->env, $this->source, $context["tax"], "state", [], "any", false, false, false, 44)) {
                // line 45
                echo "                        ";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tax"], "state", [], "any", false, false, false, 45), "html", null, true);
                echo "
                        ";
            } else {
                // line 47
                echo "                        ";
                echo gettext("Applies to any state");
                // line 48
                echo "                        ";
            }
            // line 49
            echo "                    </td>
                    <td>";
            // line 50
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tax"], "taxrate", [], "any", false, false, false, 50), "html", null, true);
            echo "%</td>
                    <td class=\"actions\" style=\"width: 8%;\">
                        <a class=\"bb-button btn14\" href=\"";
            // line 52
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("invoice/tax");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tax"], "id", [], "any", false, false, false, 52), "html", null, true);
            echo "\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                        <a class=\"bb-button btn14 bb-rm-tr api-link\" data-api-reload=\"1\" data-api-confirm=\"Are you sure?\" href=\"";
            // line 53
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/invoice/tax_delete", ["id" => twig_get_attribute($this->env, $this->source, $context["tax"], "id", [], "any", false, false, false, 53)]);
            echo "\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                    </td>

                </tr>
                </tbody>

                ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 60
            echo "                <tbody>
                    <tr>
                        <td colspan=\"5\">
                            ";
            // line 63
            echo gettext("The list is empty");
            // line 64
            echo "                        </td>
                    </tr>
                </tbody>
                ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['tax'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 68
        echo "            </table>
            ";
        // line 69
        $this->loadTemplate("partial_batch_delete.phtml", "mod_invoice_tax.phtml", 69)->display(twig_array_merge($context, ["action" => "admin/invoice/batch_delete_tax"]));
        // line 70
        echo "
        </div>
        
        <div class=\"tab_content nopadding\" id=\"tab-settings\">
            <form method=\"post\" action=\"";
        // line 74
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/system/update_params");
        echo "\" class=\"mainForm save api-form\" data-api-msg=\"Tax settings updated\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>";
        // line 77
        echo gettext("Enable tax support");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"radio\" name=\"tax_enabled\" value=\"1\"";
        // line 79
        if (twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "system_param", [0 => ["key" => "tax_enabled"]], "method", false, false, false, 79)) {
            echo " checked=\"checked\"";
        }
        echo " /><label>";
        echo gettext("Yes");
        echo "</label>
                            <input type=\"radio\" name=\"tax_enabled\" value=\"0\"";
        // line 80
        if ( !twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "system_param", [0 => ["key" => "tax_enabled"]], "method", false, false, false, 80)) {
            echo " checked=\"checked\"";
        }
        echo " /><label>";
        echo gettext("No");
        echo "</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <input type=\"submit\" value=\"";
        // line 84
        echo gettext("Update");
        echo "\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-eu-vat\">

            <div class=\"help\">
                <h3>";
        // line 92
        echo gettext("Automatic VAT Tax Rules Setup");
        echo "</h3>
                <p>";
        // line 93
        echo gettext("If you would like BoxBilling to automatically setup the EU VAT tax rules for you for all EU Member States then simply enter your VAT Label & local VAT rate below and click the submit button. <strong>This action will delete any existing tax rules</strong> and configure the VAT rates for all EU countries.");
        echo "</p>
            </div>
            <br/>
            <div class=\"aligncenter\">
                <a href=\"";
        // line 97
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/invoice/tax_setup_eu");
        echo "\" title=\"\" class=\"btn55 mr10 api-link\" data-api-reload=\"1\" data-api-confirm=\"It will overwrite your existing VAT rules. Are you sure?\"><img src=\"images/icons/middlenav/power.png\" alt=\"\"><span>Generate VAT rates</span></a>
            </div>
            <br/>
        </div>
        
        <div class=\"tab_content nopadding\" id=\"tab-new\">

            <form method=\"post\" action=\"";
        // line 104
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/invoice/tax_create");
        echo "\" class=\"mainForm save api-form\" data-api-reload=\"1\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>";
        // line 107
        echo gettext("Tax title");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"name\" value=\"";
        // line 109
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "name", [], "any", false, false, false, 109), "html", null, true);
        echo "\" required=\"required\" placeholder=\"";
        echo gettext("sales Tax");
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 114
        echo gettext("Tax rate");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"taxrate\" value=\"";
        // line 116
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "taxrate", [], "any", false, false, false, 116), "html", null, true);
        echo "\" required=\"required\" placeholder=\"";
        echo gettext("18");
        echo "\" style=\"width: 100px\"/> %
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 121
        echo gettext("Country");
        echo ":</label>
                        <div class=\"formRight\">
                            ";
        // line 123
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["country", twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_countries", [], "any", false, false, false, 123), twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "country", [], "any", false, false, false, 123), 0, "Apply to all countries"], 123, $context, $this->getSourceContext());
        echo "
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 128
        echo gettext("State");
        echo ":</label>
                        <div class=\"formRight\">
                            ";
        // line 131
        echo "                            <input type=\"text\" name=\"state\" value=\"";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "state", [], "any", false, false, false, 131), "html", null, true);
        echo "\" placeholder=\"";
        echo gettext("Leave empty to apply to all states");
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <input type=\"submit\" value=\"";
        // line 135
        echo gettext("Create");
        echo "\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>
    </div>
</div>

";
    }

    public function getTemplateName()
    {
        return "mod_invoice_tax.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  345 => 135,  335 => 131,  330 => 128,  322 => 123,  317 => 121,  307 => 116,  302 => 114,  292 => 109,  287 => 107,  281 => 104,  271 => 97,  264 => 93,  260 => 92,  249 => 84,  238 => 80,  230 => 79,  225 => 77,  219 => 74,  213 => 70,  211 => 69,  208 => 68,  199 => 64,  197 => 63,  192 => 60,  180 => 53,  174 => 52,  169 => 50,  166 => 49,  163 => 48,  160 => 47,  154 => 45,  152 => 44,  148 => 42,  145 => 41,  142 => 40,  136 => 38,  134 => 37,  129 => 35,  125 => 34,  122 => 33,  117 => 32,  109 => 27,  105 => 26,  101 => 25,  97 => 24,  93 => 23,  80 => 13,  76 => 12,  72 => 11,  68 => 10,  63 => 7,  59 => 6,  52 => 3,  47 => 1,  45 => 4,  43 => 2,  36 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends \"layout_default.phtml\" %}
{% import \"macro_functions.phtml\" as mf %}
{% block meta_title %}Tax{% endblock %}
{% set active_menu = 'system' %}

{% block content %}
<div class=\"widget simpleTabs\">

    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">{% trans 'Tax rules' %}</a></li>
        <li><a href=\"#tab-new\">{% trans 'New tax rule' %}</a></li>
        <li><a href=\"#tab-settings\">{% trans 'Tax settings' %}</a></li>
        <li><a href=\"#tab-eu-vat\">{% trans 'EU VAT' %}</a></li>
    </ul>

    <div class=\"tabs_container\">
        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-index\">
            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td style=\"width: 2%\"><input type=\"checkbox\" class=\"batch-delete-master-checkbox\"/></td>
                        <td>{% trans 'Name' %}</td>
                        <td>{% trans 'Country' %}</td>
                        <td>{% trans 'State/Region' %}</td>
                        <td>{% trans 'Tax rate' %}</td>
                        <td>{% trans 'Actions' %}</td>
                    </tr>
                </thead>

                <tbody>
                {% for tax in admin.invoice_tax_get_list({\"per_page\":100}).list %}
                <tr>
                    <td><input type=\"checkbox\" class=\"batch-delete-checkbox\" data-item-id=\"{{ tax.id }}\"/></td>
                    <td>{{ tax.name }}</td>
                    <td>
                        {% if tax.country %}
                        {{ guest.system_countries[tax.country] }}
                        {% else %}
                        {% trans 'Applies to any country' %}
                        {% endif %}
                    </td>
                    <td>
                        {% if tax.state %}
                        {{ tax.state }}
                        {% else %}
                        {% trans 'Applies to any state' %}
                        {% endif %}
                    </td>
                    <td>{{ tax.taxrate }}%</td>
                    <td class=\"actions\" style=\"width: 8%;\">
                        <a class=\"bb-button btn14\" href=\"{{ 'invoice/tax'|alink}}/{{ tax.id }}\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                        <a class=\"bb-button btn14 bb-rm-tr api-link\" data-api-reload=\"1\" data-api-confirm=\"Are you sure?\" href=\"{{ 'api/admin/invoice/tax_delete'|link({'id' : tax.id}) }}\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
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
            {% include \"partial_batch_delete.phtml\" with {'action' : 'admin/invoice/batch_delete_tax'} %}

        </div>
        
        <div class=\"tab_content nopadding\" id=\"tab-settings\">
            <form method=\"post\" action=\"{{ 'api/admin/system/update_params'|link }}\" class=\"mainForm save api-form\" data-api-msg=\"Tax settings updated\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>{% trans 'Enable tax support' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"radio\" name=\"tax_enabled\" value=\"1\"{% if admin.system_param({\"key\":\"tax_enabled\"}) %} checked=\"checked\"{% endif %} /><label>{% trans 'Yes' %}</label>
                            <input type=\"radio\" name=\"tax_enabled\" value=\"0\"{% if not admin.system_param({\"key\":\"tax_enabled\"}) %} checked=\"checked\"{% endif %} /><label>{% trans 'No' %}</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <input type=\"submit\" value=\"{% trans 'Update' %}\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-eu-vat\">

            <div class=\"help\">
                <h3>{% trans 'Automatic VAT Tax Rules Setup' %}</h3>
                <p>{% trans 'If you would like BoxBilling to automatically setup the EU VAT tax rules for you for all EU Member States then simply enter your VAT Label & local VAT rate below and click the submit button. <strong>This action will delete any existing tax rules</strong> and configure the VAT rates for all EU countries.' %}</p>
            </div>
            <br/>
            <div class=\"aligncenter\">
                <a href=\"{{ 'api/admin/invoice/tax_setup_eu'|link}}\" title=\"\" class=\"btn55 mr10 api-link\" data-api-reload=\"1\" data-api-confirm=\"It will overwrite your existing VAT rules. Are you sure?\"><img src=\"images/icons/middlenav/power.png\" alt=\"\"><span>Generate VAT rates</span></a>
            </div>
            <br/>
        </div>
        
        <div class=\"tab_content nopadding\" id=\"tab-new\">

            <form method=\"post\" action=\"{{ 'api/admin/invoice/tax_create'|link }}\" class=\"mainForm save api-form\" data-api-reload=\"1\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>{% trans 'Tax title' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"name\" value=\"{{request.name}}\" required=\"required\" placeholder=\"{% trans 'sales Tax' %}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Tax rate' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"taxrate\" value=\"{{request.taxrate}}\" required=\"required\" placeholder=\"{% trans '18' %}\" style=\"width: 100px\"/> %
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Country' %}:</label>
                        <div class=\"formRight\">
                            {{ mf.selectbox('country', guest.system_countries, request.country, 0, 'Apply to all countries') }}
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'State' %}:</label>
                        <div class=\"formRight\">
                            {# mf.selectbox('state', guest.system_states, request.state, 0, 'Apply to all states') #}
                            <input type=\"text\" name=\"state\" value=\"{{ request.state }}\" placeholder=\"{% trans 'Leave empty to apply to all states' %}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <input type=\"submit\" value=\"{% trans 'Create' %}\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>
    </div>
</div>

{% endblock %}
", "mod_invoice_tax.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-themes/admin_default/html/mod_invoice_tax.phtml");
    }
}
