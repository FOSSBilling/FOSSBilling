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

/* mod_invoice_subscription.phtml */
class __TwigTemplate_8e4466d1c8801e1d870245387f8ba529e1acf7c3d3340b7bd475a6e2a03831b1 extends \Twig\Template
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
        return $this->loadTemplate(((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ajax", [], "any", false, false, false, 1)) ? ("layout_blank.phtml") : ("layout_default.phtml")), "mod_invoice_subscription.phtml", 1);
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 2
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_invoice_subscription.phtml", 2)->unwrap();
        // line 3
        $context["active_menu"] = "invoice";
        // line 1
        $this->getParent($context)->display($context, array_merge($this->blocks, $blocks));
    }

    // line 4
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo "Subscription ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["subscription"] ?? null), "sid", [], "any", false, false, false, 4), "html", null, true);
    }

    // line 6
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 7
        echo "
<div class=\"widget simpleTabs\">

    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">";
        // line 11
        echo gettext("Subscription");
        echo "</a></li>
        <li><a href=\"#tab-manage\">";
        // line 12
        echo gettext("Manage");
        echo "</a></li>
    </ul>

    <div class=\"tab_container\">

        <div class=\"tab_content nopadding\" id=\"tab-index\">
            <div class=\"help\">
                <h3>";
        // line 19
        echo gettext("Subscription details");
        echo "</h3>
            </div>

            <table class=\"tableStatic wide\">
                <tbody>
                    <tr class=\"noborder\">
                        <td>";
        // line 25
        echo gettext("Client");
        echo "</td>
                        <td><a href=\"";
        // line 26
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("client/manage");
        echo "/";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["subscription"] ?? null), "client", [], "any", false, false, false, 26), "id", [], "any", false, false, false, 26), "html", null, true);
        echo "\">";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["subscription"] ?? null), "client", [], "any", false, false, false, 26), "first_name", [], "any", false, false, false, 26), "html", null, true);
        echo " ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["subscription"] ?? null), "client", [], "any", false, false, false, 26), "last_name", [], "any", false, false, false, 26), "html", null, true);
        echo "</a></td>
                    </tr>

                    <tr>
                        <td>";
        // line 30
        echo gettext("Amount");
        echo "</td>
                        <td>";
        // line 31
        echo twig_call_macro($macros["mf"], "macro_currency_format", [twig_get_attribute($this->env, $this->source, ($context["subscription"] ?? null), "amount", [], "any", false, false, false, 31), twig_get_attribute($this->env, $this->source, ($context["subscription"] ?? null), "currency", [], "any", false, false, false, 31)], 31, $context, $this->getSourceContext());
        echo " ";
        echo twig_call_macro($macros["mf"], "macro_period_name", [twig_get_attribute($this->env, $this->source, ($context["subscription"] ?? null), "period", [], "any", false, false, false, 31)], 31, $context, $this->getSourceContext());
        echo "</td>
                    </tr>

                    <tr>
                        <td>";
        // line 35
        echo gettext("Payment gateway");
        echo "</td>
                        <td>";
        // line 36
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["subscription"] ?? null), "gateway", [], "any", false, false, false, 36), "title", [], "any", false, false, false, 36), "html", null, true);
        echo "</td>
                    </tr>

                    <tr>
                        <td>";
        // line 40
        echo gettext("Subscription ID on payment gateway");
        echo "</td>
                        <td>";
        // line 41
        echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["subscription"] ?? null), "sid", [], "any", true, true, false, 41)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["subscription"] ?? null), "sid", [], "any", false, false, false, 41), "-")) : ("-")), "html", null, true);
        echo "</td>
                    </tr>

                    <tr>
                        <td>";
        // line 45
        echo gettext("Status");
        echo "</td>
                        <td>";
        // line 46
        echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, ($context["subscription"] ?? null), "status", [], "any", false, false, false, 46)], 46, $context, $this->getSourceContext());
        echo "</td>
                    </tr>

                    <tr>
                        <td>";
        // line 50
        echo gettext("Created at");
        echo "</td>
                        <td>";
        // line 51
        echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["subscription"] ?? null), "created_at", [], "any", false, false, false, 51), "l, d F Y"), "html", null, true);
        echo "</td>
                    </tr>

                    ";
        // line 54
        if ((twig_get_attribute($this->env, $this->source, ($context["subscription"] ?? null), "created_at", [], "any", false, false, false, 54) != twig_get_attribute($this->env, $this->source, ($context["subscription"] ?? null), "updated_at", [], "any", false, false, false, 54))) {
            // line 55
            echo "                    <tr>
                        <td>";
            // line 56
            echo gettext("Updated at");
            echo "</td>
                        <td>";
            // line 57
            echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["subscription"] ?? null), "updated_at", [], "any", false, false, false, 57), "l, d F Y"), "html", null, true);
            echo "</td>
                    </tr>
                    ";
        }
        // line 60
        echo "                 </tbody>

                 <tfoot>
                     <tr>
                         <td colspan=\"2\">
                            <div class=\"aligncenter\">
                                <a class=\"btn55 mr10 api-link\" href=\"";
        // line 66
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/invoice/subscription_delete", ["id" => twig_get_attribute($this->env, $this->source, ($context["subscription"] ?? null), "id", [], "any", false, false, false, 66)]);
        echo "\" data-api-confirm=\"Are you sure?\" data-api-redirect=\"";
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("invoice/subscriptions");
        echo "\"><img src=\"images/icons/middlenav/trash.png\" alt=\"\"><span>";
        echo gettext("Delete");
        echo "</span></a>
                            </div>
                         </td>
                     </tr>
                 </tfoot>
            </table>

        </div>

        <div id=\"tab-manage\" class=\"tab_content nopadding\">
            <form method=\"post\" action=\"";
        // line 76
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/invoice/subscription_update");
        echo "\" class=\"mainForm save api-form\" data-api-reload=\"1\">
                <fieldset>
                    <div class=\"rowElem\">
                        <label>";
        // line 79
        echo gettext("Payment Gateway");
        echo ":</label>
                        <div class=\"formRight\">
                            ";
        // line 81
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["gateway_id", twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "invoice_gateway_get_pairs", [], "any", false, false, false, 81), twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["subscription"] ?? null), "gateway", [], "any", false, false, false, 81), "id", [], "any", false, false, false, 81), 0, "Select payment gateway"], 81, $context, $this->getSourceContext());
        echo "
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 86
        echo gettext("Subscription ID on payment gateway");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"sid\" value=\"";
        // line 88
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["subscription"] ?? null), "sid", [], "any", false, false, false, 88), "html", null, true);
        echo "\" required=\"required\" placeholder=\"\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 93
        echo gettext("Status");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"radio\" name=\"status\" value=\"active\" ";
        // line 95
        if ((twig_get_attribute($this->env, $this->source, ($context["subscription"] ?? null), "status", [], "any", false, false, false, 95) == "active")) {
            echo " checked=\"checked\"";
        }
        echo "/><label>Active</label>
                            <input type=\"radio\" name=\"status\" value=\"canceled\" ";
        // line 96
        if ((twig_get_attribute($this->env, $this->source, ($context["subscription"] ?? null), "status", [], "any", false, false, false, 96) == "canceled")) {
            echo " checked=\"checked\"";
        }
        echo "/><label>Canceled</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 101
        echo gettext("Period");
        echo ":</label>
                        <div class=\"formRight\">
                            ";
        // line 103
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["period", twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_periods", [], "any", false, false, false, 103), twig_get_attribute($this->env, $this->source, ($context["subscription"] ?? null), "period", [], "any", false, false, false, 103), 1, "Select period"], 103, $context, $this->getSourceContext());
        echo "
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <div class=\"rowElem\">
                        <label>";
        // line 109
        echo gettext("Amount");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"amount\" value=\"";
        // line 111
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["subscription"] ?? null), "amount", [], "any", false, false, false, 111), "html", null, true);
        echo "\" required=\"required\" placeholder=\"\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 117
        echo gettext("Currency");
        echo "</label>
                        <div class=\"formRight\">
                            ";
        // line 119
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["currency", twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "currency_get_pairs", [], "any", false, false, false, 119), twig_get_attribute($this->env, $this->source, ($context["subscription"] ?? null), "currency", [], "any", false, false, false, 119), 1, "Select currency"], 119, $context, $this->getSourceContext());
        echo "
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <input type=\"submit\" value=\"";
        // line 124
        echo gettext("Update");
        echo "\" class=\"greyishBtn submitForm\" />
                    <input type=\"hidden\" name=\"id\" value=\"";
        // line 125
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["subscription"] ?? null), "id", [], "any", false, false, false, 125), "html", null, true);
        echo "\" />
                </fieldset>
            </form>
        </div>

    </div>

    <div class=\"fix\"></div>
</div>

";
    }

    public function getTemplateName()
    {
        return "mod_invoice_subscription.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  303 => 125,  299 => 124,  291 => 119,  286 => 117,  277 => 111,  272 => 109,  263 => 103,  258 => 101,  248 => 96,  242 => 95,  237 => 93,  229 => 88,  224 => 86,  216 => 81,  211 => 79,  205 => 76,  188 => 66,  180 => 60,  174 => 57,  170 => 56,  167 => 55,  165 => 54,  159 => 51,  155 => 50,  148 => 46,  144 => 45,  137 => 41,  133 => 40,  126 => 36,  122 => 35,  113 => 31,  109 => 30,  96 => 26,  92 => 25,  83 => 19,  73 => 12,  69 => 11,  63 => 7,  59 => 6,  51 => 4,  47 => 1,  45 => 3,  43 => 2,  36 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}
{% import \"macro_functions.phtml\" as mf %}
{% set active_menu = 'invoice' %}
{% block meta_title %}Subscription {{ subscription.sid }}{% endblock %}

{% block content %}

<div class=\"widget simpleTabs\">

    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">{% trans 'Subscription' %}</a></li>
        <li><a href=\"#tab-manage\">{% trans 'Manage' %}</a></li>
    </ul>

    <div class=\"tab_container\">

        <div class=\"tab_content nopadding\" id=\"tab-index\">
            <div class=\"help\">
                <h3>{% trans 'Subscription details' %}</h3>
            </div>

            <table class=\"tableStatic wide\">
                <tbody>
                    <tr class=\"noborder\">
                        <td>{% trans 'Client' %}</td>
                        <td><a href=\"{{ 'client/manage'|alink }}/{{subscription.client.id}}\">{{ subscription.client.first_name }} {{ subscription.client.last_name }}</a></td>
                    </tr>

                    <tr>
                        <td>{% trans 'Amount' %}</td>
                        <td>{{ mf.currency_format( subscription.amount, subscription.currency) }} {{ mf.period_name(subscription.period) }}</td>
                    </tr>

                    <tr>
                        <td>{% trans 'Payment gateway' %}</td>
                        <td>{{subscription.gateway.title}}</td>
                    </tr>

                    <tr>
                        <td>{% trans 'Subscription ID on payment gateway' %}</td>
                        <td>{{subscription.sid|default('-')}}</td>
                    </tr>

                    <tr>
                        <td>{% trans 'Status' %}</td>
                        <td>{{ mf.status_name(subscription.status) }}</td>
                    </tr>

                    <tr>
                        <td>{% trans 'Created at' %}</td>
                        <td>{{subscription.created_at|date('l, d F Y')}}</td>
                    </tr>

                    {% if subscription.created_at != subscription.updated_at %}
                    <tr>
                        <td>{% trans 'Updated at' %}</td>
                        <td>{{subscription.updated_at|date('l, d F Y')}}</td>
                    </tr>
                    {% endif %}
                 </tbody>

                 <tfoot>
                     <tr>
                         <td colspan=\"2\">
                            <div class=\"aligncenter\">
                                <a class=\"btn55 mr10 api-link\" href=\"{{ 'api/admin/invoice/subscription_delete'|link({'id' : subscription.id}) }}\" data-api-confirm=\"Are you sure?\" data-api-redirect=\"{{ 'invoice/subscriptions'|alink }}\"><img src=\"images/icons/middlenav/trash.png\" alt=\"\"><span>{% trans 'Delete' %}</span></a>
                            </div>
                         </td>
                     </tr>
                 </tfoot>
            </table>

        </div>

        <div id=\"tab-manage\" class=\"tab_content nopadding\">
            <form method=\"post\" action=\"{{ 'api/admin/invoice/subscription_update'|link }}\" class=\"mainForm save api-form\" data-api-reload=\"1\">
                <fieldset>
                    <div class=\"rowElem\">
                        <label>{% trans 'Payment Gateway' %}:</label>
                        <div class=\"formRight\">
                            {{ mf.selectbox('gateway_id', admin.invoice_gateway_get_pairs, subscription.gateway.id, 0, 'Select payment gateway') }}
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Subscription ID on payment gateway' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"sid\" value=\"{{subscription.sid}}\" required=\"required\" placeholder=\"\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Status' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"radio\" name=\"status\" value=\"active\" {% if subscription.status == 'active' %} checked=\"checked\"{% endif %}/><label>Active</label>
                            <input type=\"radio\" name=\"status\" value=\"canceled\" {% if subscription.status == 'canceled' %} checked=\"checked\"{% endif %}/><label>Canceled</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Period' %}:</label>
                        <div class=\"formRight\">
                            {{ mf.selectbox('period', guest.system_periods, subscription.period, 1, 'Select period') }}
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <div class=\"rowElem\">
                        <label>{% trans 'Amount' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"amount\" value=\"{{subscription.amount}}\" required=\"required\" placeholder=\"\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Currency' %}</label>
                        <div class=\"formRight\">
                            {{ mf.selectbox('currency', guest.currency_get_pairs, subscription.currency, 1, 'Select currency') }}
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <input type=\"submit\" value=\"{% trans 'Update' %}\" class=\"greyishBtn submitForm\" />
                    <input type=\"hidden\" name=\"id\" value=\"{{ subscription.id }}\" />
                </fieldset>
            </form>
        </div>

    </div>

    <div class=\"fix\"></div>
</div>

{% endblock %}", "mod_invoice_subscription.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-themes/admin_default/html/mod_invoice_subscription.phtml");
    }
}
