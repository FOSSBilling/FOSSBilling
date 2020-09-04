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

/* mod_invoice_subscriptions.phtml */
class __TwigTemplate_03e2bf5053760a06a4d085232ebd574f9fc1606a28cd5cbe911fdcd2737be36c extends \Twig\Template
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
            'js' => [$this, 'block_js'],
        ];
    }

    protected function doGetParent(array $context)
    {
        // line 1
        return $this->loadTemplate(((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ajax", [], "any", false, false, false, 1)) ? ("layout_blank.phtml") : ("layout_default.phtml")), "mod_invoice_subscriptions.phtml", 1);
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 2
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_invoice_subscriptions.phtml", 2)->unwrap();
        // line 4
        $context["active_menu"] = "invoice";
        // line 1
        $this->getParent($context)->display($context, array_merge($this->blocks, $blocks));
    }

    // line 3
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo gettext("Subscriptions");
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
        echo gettext("Subscriptions");
        echo "</a></li>
        <li><a href=\"#tab-new\">";
        // line 12
        echo gettext("New subscription");
        echo "</a></li>
    </ul>

    <div class=\"tabs_container\">

        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-index\">

        ";
        // line 20
        echo twig_call_macro($macros["mf"], "macro_table_search", [], 20, $context, $this->getSourceContext());
        echo "
        <table class=\"tableStatic wide\">
            <thead>
                <tr>
                    <td style=\"width: 2%\"><input type=\"checkbox\" class=\"batch-delete-master-checkbox\"/></td>
                    <th colspan=\"2\">";
        // line 25
        echo gettext("ID");
        echo "</th>
                    <th>";
        // line 26
        echo gettext("Status");
        echo "</th>
                    <th>";
        // line 27
        echo gettext("Gateway");
        echo "</th>
                    <th>";
        // line 28
        echo gettext("Amount");
        echo "</th>
                    <th width=\"13%\">&nbsp;</th>
                </tr>
            </thead>

            <tbody>
                ";
        // line 34
        $context["subscriptions"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "invoice_subscription_get_list", [0 => twig_array_merge(["per_page" => 30, "page" => twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 34)], ($context["request"] ?? null))], "method", false, false, false, 34);
        // line 35
        echo "                ";
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["subscriptions"] ?? null), "list", [], "any", false, false, false, 35));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["i"] => $context["subscription"]) {
            // line 36
            echo "                <tr>
                    <td><input type=\"checkbox\" class=\"batch-delete-checkbox\" data-item-id=\"";
            // line 37
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["subscription"], "id", [], "any", false, false, false, 37), "html", null, true);
            echo "\"/></td>
                    <td style=\"width:5%;\"><a href=\"";
            // line 38
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("client/manage");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["subscription"], "client", [], "any", false, false, false, 38), "id", [], "any", false, false, false, 38), "html", null, true);
            echo "\"><img src=\"";
            echo twig_escape_filter($this->env, twig_gravatar_filter(twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["subscription"], "client", [], "any", false, false, false, 38), "email", [], "any", false, false, false, 38)), "html", null, true);
            echo "?size=20\" alt=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["subscription"], "client", [], "any", false, false, false, 38), "email", [], "any", false, false, false, 38), "html", null, true);
            echo "\" title=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["subscription"], "client", [], "any", false, false, false, 38), "first_name", [], "any", false, false, false, 38), "html", null, true);
            echo " ";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["subscription"], "client", [], "any", false, false, false, 38), "last_name", [], "any", false, false, false, 38), "html", null, true);
            echo "\"/></a></td>
                    <td>";
            // line 39
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["subscription"], "sid", [], "any", false, false, false, 39), "html", null, true);
            echo "</td>
                    <td>";
            // line 40
            echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, $context["subscription"], "status", [], "any", false, false, false, 40)], 40, $context, $this->getSourceContext());
            echo "</td>
                    <td>";
            // line 41
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["subscription"], "gateway", [], "any", false, false, false, 41), "title", [], "any", false, false, false, 41), "html", null, true);
            echo "</td>
                    <td>";
            // line 42
            echo twig_call_macro($macros["mf"], "macro_currency_format", [twig_get_attribute($this->env, $this->source, $context["subscription"], "amount", [], "any", false, false, false, 42), twig_get_attribute($this->env, $this->source, $context["subscription"], "currency", [], "any", false, false, false, 42)], 42, $context, $this->getSourceContext());
            echo " ";
            echo twig_call_macro($macros["mf"], "macro_period_name", [twig_get_attribute($this->env, $this->source, $context["subscription"], "period", [], "any", false, false, false, 42)], 42, $context, $this->getSourceContext());
            echo "</td>
                    <td class=\"actions\">
                        <a class=\"btn14\" href=\"";
            // line 44
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("invoice/subscription");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["subscription"], "id", [], "any", false, false, false, 44), "html", null, true);
            echo "\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                        <a class=\"btn14 bb-rm-tr api-link\" href=\"";
            // line 45
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/invoice/subscription_delete", ["id" => twig_get_attribute($this->env, $this->source, $context["subscription"], "id", [], "any", false, false, false, 45)]);
            echo "\" data-api-confirm=\"Are you sure?\" data-api-reload=\"1\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                    </td>
                </tr>
                ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 49
            echo "                <tr>
                    <td colspan=\"6\">
                        ";
            // line 51
            echo gettext("The list is empty");
            // line 52
            echo "                    </td>
                </tr>
                ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['i'], $context['subscription'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 55
        echo "            </tbody>
        </table>

            ";
        // line 58
        $this->loadTemplate("partial_batch_delete.phtml", "mod_invoice_subscriptions.phtml", 58)->display(twig_array_merge($context, ["action" => "admin/invoice/batch_delete_subscription"]));
        // line 59
        echo "            ";
        $this->loadTemplate("partial_pagination.phtml", "mod_invoice_subscriptions.phtml", 59)->display(twig_array_merge($context, ["list" => ($context["subscriptions"] ?? null), "url" => "invoice/subscriptions"]));
        // line 60
        echo "    </div>
        
        <div class=\"tab_content nopadding\" id=\"tab-new\">

            <form method=\"post\" action=\"";
        // line 64
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/invoice/subscription_create");
        echo "\" class=\"mainForm save api-form\" data-api-reload=\"1\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>";
        // line 67
        echo gettext("Client");
        echo "</label>
                        <div class=\"formRight\">
                            <input type=\"text\" id=\"client_selector\" placeholder=\"";
        // line 69
        echo gettext("Start typing clients name, email or ID");
        echo "\"/>
                            <input type=\"hidden\" name=\"client_id\" value=\"";
        // line 70
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "client_id", [], "any", false, false, false, 70), "html", null, true);
        echo "\" id=\"client_id\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 75
        echo gettext("Payment Gateway");
        echo ":</label>
                        <div class=\"formRight\">
                            ";
        // line 77
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["gateway_id", twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "invoice_gateway_get_pairs", [], "any", false, false, false, 77), twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "gateway_id", [], "any", false, false, false, 77), 0, "Select payment gateway"], 77, $context, $this->getSourceContext());
        echo "
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 82
        echo gettext("Subscription ID on payment gateway");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"sid\" value=\"";
        // line 84
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "sid", [], "any", false, false, false, 84), "html", null, true);
        echo "\" required=\"required\" placeholder=\"\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 89
        echo gettext("Status");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"radio\" name=\"status\" value=\"active\" checked=\"checked\"/><label>";
        // line 91
        echo gettext("Active");
        echo "</label>
                            <input type=\"radio\" name=\"status\" value=\"canceled\" /><label>";
        // line 92
        echo gettext("Canceled");
        echo "</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 97
        echo gettext("Period");
        echo ":</label>
                        <div class=\"formRight\">
                            ";
        // line 99
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["period", twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_periods", [], "any", false, false, false, 99), twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "period", [], "any", false, false, false, 99), 1, "Select period"], 99, $context, $this->getSourceContext());
        echo "
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <div class=\"rowElem\">
                        <label>";
        // line 105
        echo gettext("Amount");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"amount\" value=\"";
        // line 107
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "amount", [], "any", false, false, false, 107), "html", null, true);
        echo "\" required=\"required\" placeholder=\"\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 113
        echo gettext("Currency");
        echo "</label>
                        <div class=\"formRight\">
                            ";
        // line 115
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["currency", twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "currency_get_pairs", [], "any", false, false, false, 115), twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "currency", [], "any", false, false, false, 115), 1, "Select currency"], 115, $context, $this->getSourceContext());
        echo "
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <input type=\"submit\" value=\"";
        // line 120
        echo gettext("Create");
        echo "\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
            </div>
        
    </div>
</div>
";
    }

    // line 130
    public function block_js($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 131
        echo "<script type=\"text/javascript\">
\$(function() {

\t\$('#client_selector').autocomplete({
        source: function( request, response ) {
            \$.ajax({
                url: bb.restUrl('admin/client/get_pairs'),
                dataType: \"json\",
                data: {
                    per_page: 10,
                    search: request.term
                },
                success: function( data ) {
                    response( \$.map( data.result, function( name, id) {
                        return {
                            label: name,
                            value: id
                        }
                    }));
                }
            });
        },
        minLength: 1,
        select: function( event, ui ) {
            \$(\"#client_selector\").val(ui.item.label);
            \$(\"#client_id\").val(ui.item.value);
            return false;
        }
    });

});

</script>
";
    }

    public function getTemplateName()
    {
        return "mod_invoice_subscriptions.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  329 => 131,  325 => 130,  313 => 120,  305 => 115,  300 => 113,  291 => 107,  286 => 105,  277 => 99,  272 => 97,  264 => 92,  260 => 91,  255 => 89,  247 => 84,  242 => 82,  234 => 77,  229 => 75,  221 => 70,  217 => 69,  212 => 67,  206 => 64,  200 => 60,  197 => 59,  195 => 58,  190 => 55,  182 => 52,  180 => 51,  176 => 49,  167 => 45,  161 => 44,  154 => 42,  150 => 41,  146 => 40,  142 => 39,  128 => 38,  124 => 37,  121 => 36,  115 => 35,  113 => 34,  104 => 28,  100 => 27,  96 => 26,  92 => 25,  84 => 20,  73 => 12,  69 => 11,  63 => 7,  59 => 6,  52 => 3,  48 => 1,  46 => 4,  44 => 2,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}
{% import \"macro_functions.phtml\" as mf %}
{% block meta_title %}{% trans 'Subscriptions' %}{% endblock %}
{% set active_menu = 'invoice' %}

{% block content %}

<div class=\"widget simpleTabs\">

    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">{% trans 'Subscriptions' %}</a></li>
        <li><a href=\"#tab-new\">{% trans 'New subscription' %}</a></li>
    </ul>

    <div class=\"tabs_container\">

        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-index\">

        {{ mf.table_search }}
        <table class=\"tableStatic wide\">
            <thead>
                <tr>
                    <td style=\"width: 2%\"><input type=\"checkbox\" class=\"batch-delete-master-checkbox\"/></td>
                    <th colspan=\"2\">{% trans 'ID' %}</th>
                    <th>{% trans 'Status' %}</th>
                    <th>{% trans 'Gateway' %}</th>
                    <th>{% trans 'Amount' %}</th>
                    <th width=\"13%\">&nbsp;</th>
                </tr>
            </thead>

            <tbody>
                {% set subscriptions = admin.invoice_subscription_get_list({\"per_page\":30, \"page\":request.page}|merge(request)) %}
                {% for i, subscription in subscriptions.list %}
                <tr>
                    <td><input type=\"checkbox\" class=\"batch-delete-checkbox\" data-item-id=\"{{ subscription.id }}\"/></td>
                    <td style=\"width:5%;\"><a href=\"{{ 'client/manage'|alink }}/{{ subscription.client.id }}\"><img src=\"{{ subscription.client.email|gravatar }}?size=20\" alt=\"{{ subscription.client.email }}\" title=\"{{subscription.client.first_name}} {{subscription.client.last_name}}\"/></a></td>
                    <td>{{ subscription.sid }}</td>
                    <td>{{ mf.status_name(subscription.status) }}</td>
                    <td>{{ subscription.gateway.title }}</td>
                    <td>{{ mf.currency_format( subscription.amount, subscription.currency) }} {{ mf.period_name(subscription.period) }}</td>
                    <td class=\"actions\">
                        <a class=\"btn14\" href=\"{{ 'invoice/subscription'|alink }}/{{ subscription.id }}\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                        <a class=\"btn14 bb-rm-tr api-link\" href=\"{{ 'api/admin/invoice/subscription_delete'|link({'id' : subscription.id}) }}\" data-api-confirm=\"Are you sure?\" data-api-reload=\"1\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                    </td>
                </tr>
                {% else %}
                <tr>
                    <td colspan=\"6\">
                        {% trans 'The list is empty' %}
                    </td>
                </tr>
                {% endfor %}
            </tbody>
        </table>

            {% include \"partial_batch_delete.phtml\" with {'action' : 'admin/invoice/batch_delete_subscription'} %}
            {% include \"partial_pagination.phtml\" with {'list': subscriptions, 'url':'invoice/subscriptions'} %}
    </div>
        
        <div class=\"tab_content nopadding\" id=\"tab-new\">

            <form method=\"post\" action=\"{{ 'api/admin/invoice/subscription_create'|link }}\" class=\"mainForm save api-form\" data-api-reload=\"1\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>{% trans 'Client' %}</label>
                        <div class=\"formRight\">
                            <input type=\"text\" id=\"client_selector\" placeholder=\"{% trans 'Start typing clients name, email or ID' %}\"/>
                            <input type=\"hidden\" name=\"client_id\" value=\"{{ request.client_id }}\" id=\"client_id\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Payment Gateway' %}:</label>
                        <div class=\"formRight\">
                            {{ mf.selectbox('gateway_id', admin.invoice_gateway_get_pairs, request.gateway_id, 0, 'Select payment gateway') }}
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Subscription ID on payment gateway' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"sid\" value=\"{{request.sid}}\" required=\"required\" placeholder=\"\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Status' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"radio\" name=\"status\" value=\"active\" checked=\"checked\"/><label>{% trans 'Active' %}</label>
                            <input type=\"radio\" name=\"status\" value=\"canceled\" /><label>{% trans 'Canceled' %}</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Period' %}:</label>
                        <div class=\"formRight\">
                            {{ mf.selectbox('period', guest.system_periods, request.period, 1, 'Select period') }}
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <div class=\"rowElem\">
                        <label>{% trans 'Amount' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"amount\" value=\"{{request.amount}}\" required=\"required\" placeholder=\"\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Currency' %}</label>
                        <div class=\"formRight\">
                            {{ mf.selectbox('currency', guest.currency_get_pairs, request.currency, 1, 'Select currency') }}
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


{% block js%}
<script type=\"text/javascript\">
\$(function() {

\t\$('#client_selector').autocomplete({
        source: function( request, response ) {
            \$.ajax({
                url: bb.restUrl('admin/client/get_pairs'),
                dataType: \"json\",
                data: {
                    per_page: 10,
                    search: request.term
                },
                success: function( data ) {
                    response( \$.map( data.result, function( name, id) {
                        return {
                            label: name,
                            value: id
                        }
                    }));
                }
            });
        },
        minLength: 1,
        select: function( event, ui ) {
            \$(\"#client_selector\").val(ui.item.label);
            \$(\"#client_id\").val(ui.item.value);
            return false;
        }
    });

});

</script>
{% endblock %}", "mod_invoice_subscriptions.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-themes/admin_default/html/mod_invoice_subscriptions.phtml");
    }
}
