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

/* mod_invoice_transactions.phtml */
class __TwigTemplate_7b65e7b63dbaccafa272913a62ac483f240ac528cdcc501d76d275c190283c33 extends \Twig\Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->blocks = [
            'meta_title' => [$this, 'block_meta_title'],
            'top_content' => [$this, 'block_top_content'],
            'content' => [$this, 'block_content'],
        ];
    }

    protected function doGetParent(array $context)
    {
        // line 1
        return $this->loadTemplate(((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ajax", [], "any", false, false, false, 1)) ? ("layout_blank.phtml") : ("layout_default.phtml")), "mod_invoice_transactions.phtml", 1);
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 2
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_invoice_transactions.phtml", 2)->unwrap();
        // line 4
        $context["active_menu"] = "invoice";
        // line 1
        $this->getParent($context)->display($context, array_merge($this->blocks, $blocks));
    }

    // line 3
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo gettext("Transactions");
    }

    // line 7
    public function block_top_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 8
        if (twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "show_filter", [], "any", false, false, false, 8)) {
            // line 9
            echo "<div class=\"widget filterWidget\">
    <div class=\"head\"><h5 class=\"iMagnify\">";
            // line 10
            echo gettext("Filter transactions");
            echo "</h5></div>
    <div class=\"body nopadding\">

        <form method=\"get\" action=\"\" class=\"mainForm\">
            <input type=\"hidden\" name=\"_url\" value=\"";
            // line 14
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "_url", [], "any", false, false, false, 14), "html", null, true);
            echo "\" />
            <fieldset>
                <div class=\"rowElem noborder\">
                    <label>";
            // line 17
            echo gettext("ID");
            echo "</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"id\" value=\"";
            // line 19
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "id", [], "any", false, false, false, 19), "html", null, true);
            echo "\">
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>";
            // line 25
            echo gettext("ID on payment gateway");
            echo "</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"txn_id\" value=\"";
            // line 27
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "txn_id", [], "any", false, false, false, 27), "html", null, true);
            echo "\">
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>";
            // line 33
            echo gettext("Invoice Id");
            echo "</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"invoice_id\" value=\"";
            // line 35
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "invoice_id", [], "any", false, false, false, 35), "html", null, true);
            echo "\">
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>";
            // line 41
            echo gettext("Currency");
            echo "</label>
                    <div class=\"formRight\">
                        ";
            // line 43
            echo twig_call_macro($macros["mf"], "macro_selectbox", ["currency", twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "currency_get_pairs", [], "any", false, false, false, 43), twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "currency", [], "any", false, false, false, 43), 0, "All currencies"], 43, $context, $this->getSourceContext());
            echo "
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>";
            // line 49
            echo gettext("Status");
            echo "</label>
                        <div class=\"formRight\">
                            ";
            // line 51
            echo twig_call_macro($macros["mf"], "macro_selectbox", ["status", twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "invoice_transaction_get_statuses_pairs", [], "any", false, false, false, 51), twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "status", [], "any", false, false, false, 51), 0, "All statuses"], 51, $context, $this->getSourceContext());
            echo "
                        </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>";
            // line 57
            echo gettext("Payment Gateway");
            echo ":</label>
                    <div class=\"formRight\">
                        ";
            // line 59
            echo twig_call_macro($macros["mf"], "macro_selectbox", ["gateway_id", twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "invoice_gateway_get_pairs", [], "any", false, false, false, 59), twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "gateway_id", [], "any", false, false, false, 59), 0, "All payment gateways"], 59, $context, $this->getSourceContext());
            echo "
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>";
            // line 65
            echo gettext("Received at");
            echo "</label>
                    <div class=\"formRight moreFields\">
                        <ul>
                            <li style=\"width: 100px\"><input type=\"text\" name=\"date_from\" value=\"";
            // line 68
            if (twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "date_from", [], "any", false, false, false, 68)) {
                echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "date_from", [], "any", false, false, false, 68), "Y-m-d"), "html", null, true);
            }
            echo "\" placeholder=\"";
            echo twig_escape_filter($this->env, twig_date_format_filter($this->env, ($context["now"] ?? null), "Y-m-d"), "html", null, true);
            echo "\" class=\"datepicker\"/></li>
                            <li class=\"sep\">-</li>
                            <li style=\"width: 100px\"><input type=\"text\" name=\"date_to\" value=\"";
            // line 70
            if (twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "date_to", [], "any", false, false, false, 70)) {
                echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "date_to", [], "any", false, false, false, 70), "Y-m-d"), "html", null, true);
            }
            echo "\" placeholder=\"";
            echo twig_escape_filter($this->env, twig_date_format_filter($this->env, ($context["now"] ?? null), "Y-m-d"), "html", null, true);
            echo "\" class=\"datepicker\"/></li>
                        </ul>
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <input type=\"hidden\" name=\"show_filter\" value=\"1\" />
                <input type=\"submit\" value=\"";
            // line 77
            echo gettext("Filter");
            echo "\" class=\"greyishBtn submitForm\" />
            </fieldset>
        </form>
        <div class=\"fix\"></div>
    </div>
</div>
";
        } else {
            // line 84
            $context["statuses"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "invoice_transaction_get_statuses", [], "any", false, false, false, 84);
            // line 85
            echo "<div class=\"stats\">
    <ul>
        <li><a href=\"";
            // line 87
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("invoice/transactions", ["status" => "processed"]);
            echo "\" class=\"count green\" title=\"\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["statuses"] ?? null), "processed", [], "any", false, false, false, 87), "html", null, true);
            echo "</a><span>";
            echo gettext("Processed");
            echo "</span></li>
        <li><a href=\"";
            // line 88
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("invoice/transactions", ["status" => "approved"]);
            echo "\" class=\"count blue\" title=\"\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["statuses"] ?? null), "approved", [], "any", false, false, false, 88), "html", null, true);
            echo "</a><span>";
            echo gettext("Approved");
            echo "</span></li>
        <li><a href=\"";
            // line 89
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("invoice/transactions", ["status" => "error"]);
            echo "\" class=\"count red\" title=\"\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["statuses"] ?? null), "error", [], "any", false, false, false, 89), "html", null, true);
            echo "</a><span>";
            echo gettext("Error");
            echo "</span></li>
        <li class=\"last\"><a href=\"";
            // line 90
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("invoice/transactions");
            echo "\" class=\"count grey\" title=\"\">";
            echo twig_escape_filter($this->env, (((twig_get_attribute($this->env, $this->source, ($context["statuses"] ?? null), "received", [], "any", false, false, false, 90) + twig_get_attribute($this->env, $this->source, ($context["statuses"] ?? null), "approved", [], "any", false, false, false, 90)) + twig_get_attribute($this->env, $this->source, ($context["statuses"] ?? null), "processed", [], "any", false, false, false, 90)) + twig_get_attribute($this->env, $this->source, ($context["statuses"] ?? null), "error", [], "any", false, false, false, 90)), "html", null, true);
            echo "</a><span>";
            echo gettext("Total");
            echo "</span></li>
    </ul>
    <div class=\"fix\"></div>
</div>
";
        }
        // line 95
        echo "
";
    }

    // line 98
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 99
        echo "
<div class=\"widget\">
    <div class=\"head\"><h5 class=\"iFrames\">";
        // line 101
        echo gettext("Transactions");
        echo "</h5></div>
    
    ";
        // line 103
        echo twig_call_macro($macros["mf"], "macro_table_search", [], 103, $context, $this->getSourceContext());
        echo "
    <table class=\"tableStatic wide\">
        <thead>
            <tr>
                <td style=\"width: 2%\"><input type=\"checkbox\" class=\"batch-delete-master-checkbox\"/></td>
                <td width=\"5%\">";
        // line 108
        echo gettext("Invoice");
        echo "</td>
                <td>";
        // line 109
        echo gettext("Type");
        echo "</td>
                <td>";
        // line 110
        echo gettext("Status");
        echo "</td>
                <td>";
        // line 111
        echo gettext("Gateway");
        echo "</td>
                <td>";
        // line 112
        echo gettext("Amount");
        echo "</td>
                <td>";
        // line 113
        echo gettext("Date");
        echo "</td>
                <td width=\"18%\">&nbsp;</td>
            </tr>
        </thead>

        <tbody>
            ";
        // line 119
        $context["transactions"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "invoice_transaction_get_list", [0 => twig_array_merge(["per_page" => 30, "page" => twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 119)], ($context["request"] ?? null))], "method", false, false, false, 119);
        // line 120
        echo "            ";
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["transactions"] ?? null), "list", [], "any", false, false, false, 120));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["i"] => $context["tx"]) {
            // line 121
            echo "            <tr>
                <td><input type=\"checkbox\" class=\"batch-delete-checkbox\" data-item-id=\"";
            // line 122
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tx"], "id", [], "any", false, false, false, 122), "html", null, true);
            echo "\"/></td>
                <td>";
            // line 123
            if (twig_get_attribute($this->env, $this->source, $context["tx"], "invoice_id", [], "any", false, false, false, 123)) {
                echo "<a href=\"";
                echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("invoice/manage");
                echo "/";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tx"], "invoice_id", [], "any", false, false, false, 123), "html", null, true);
                echo "\">#";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tx"], "invoice_id", [], "any", false, false, false, 123), "html", null, true);
                echo "</a>";
            } else {
                echo "n/a";
            }
            echo "</td>
                <td>";
            // line 124
            echo twig_call_macro($macros["mf"], "macro_status_name", [((twig_get_attribute($this->env, $this->source, $context["tx"], "type", [], "any", true, true, false, 124)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, $context["tx"], "type", [], "any", false, false, false, 124), "-")) : ("-"))], 124, $context, $this->getSourceContext());
            echo "</td>
                <td>";
            // line 125
            if (twig_get_attribute($this->env, $this->source, $context["tx"], "error", [], "any", false, false, false, 125)) {
                echo "<a href=\"#\" onclick=\"bb.msg('";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tx"], "error", [], "any", false, false, false, 125), "html", null, true);
                echo "','";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tx"], "error_code", [], "any", false, false, false, 125), "html", null, true);
                echo "'); return false;\">";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tx"], "error_code", [], "any", false, false, false, 125), "html", null, true);
                echo "</a>";
            } else {
                echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, $context["tx"], "status", [], "any", false, false, false, 125)], 125, $context, $this->getSourceContext());
            }
            echo "</td>
                <td>";
            // line 126
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tx"], "gateway", [], "any", false, false, false, 126), "html", null, true);
            echo "</td>
                <td>";
            // line 127
            echo twig_call_macro($macros["mf"], "macro_currency_format", [twig_get_attribute($this->env, $this->source, $context["tx"], "amount", [], "any", false, false, false, 127), twig_get_attribute($this->env, $this->source, $context["tx"], "currency", [], "any", false, false, false, 127)], 127, $context, $this->getSourceContext());
            echo "</td>
                <td>";
            // line 128
            echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tx"], "created_at", [], "any", false, false, false, 128), "Y-m-d H:i"), "html", null, true);
            echo "</td>
                <td class=\"actions\">
                    <a class=\"btn14\" href=\"";
            // line 130
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("invoice/transaction");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tx"], "id", [], "any", false, false, false, 130), "html", null, true);
            echo "\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                    <a class=\"btn14 api-link\" href=\"";
            // line 131
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/invoice/transaction_process", ["id" => twig_get_attribute($this->env, $this->source, $context["tx"], "id", [], "any", false, false, false, 131)]);
            echo "\" data-api-msg=\"Processed\" title=\"Process again\"><img src=\"images/icons/dark/refresh4.png\" alt=\"\"></a>
                    <a class=\"btn14 bb-rm-tr api-link\" href=\"";
            // line 132
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/invoice/transaction_delete", ["id" => twig_get_attribute($this->env, $this->source, $context["tx"], "id", [], "any", false, false, false, 132)]);
            echo "\" data-api-confirm=\"Are you sure?\" data-api-reload=\"1\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                </td>
            </tr>
            ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 136
            echo "            <tr>
                <td colspan=\"7\">
                    ";
            // line 138
            echo gettext("The list is empty");
            // line 139
            echo "                </td>
            </tr>
            ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['i'], $context['tx'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 142
        echo "        </tbody>
    </table>


</div>
";
        // line 147
        $this->loadTemplate("partial_batch_delete.phtml", "mod_invoice_transactions.phtml", 147)->display(twig_array_merge($context, ["action" => "admin/invoice/batch_delete_transaction"]));
        // line 148
        $this->loadTemplate("partial_pagination.phtml", "mod_invoice_transactions.phtml", 148)->display(twig_array_merge($context, ["list" => ($context["transactions"] ?? null), "url" => "invoice/transactions"]));
    }

    public function getTemplateName()
    {
        return "mod_invoice_transactions.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  403 => 148,  401 => 147,  394 => 142,  386 => 139,  384 => 138,  380 => 136,  371 => 132,  367 => 131,  361 => 130,  356 => 128,  352 => 127,  348 => 126,  334 => 125,  330 => 124,  316 => 123,  312 => 122,  309 => 121,  303 => 120,  301 => 119,  292 => 113,  288 => 112,  284 => 111,  280 => 110,  276 => 109,  272 => 108,  264 => 103,  259 => 101,  255 => 99,  251 => 98,  246 => 95,  234 => 90,  226 => 89,  218 => 88,  210 => 87,  206 => 85,  204 => 84,  194 => 77,  180 => 70,  171 => 68,  165 => 65,  156 => 59,  151 => 57,  142 => 51,  137 => 49,  128 => 43,  123 => 41,  114 => 35,  109 => 33,  100 => 27,  95 => 25,  86 => 19,  81 => 17,  75 => 14,  68 => 10,  65 => 9,  63 => 8,  59 => 7,  52 => 3,  48 => 1,  46 => 4,  44 => 2,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}
{% import \"macro_functions.phtml\" as mf %}
{% block meta_title %}{% trans 'Transactions' %}{% endblock %}
{% set active_menu = 'invoice' %}


{% block top_content %}
{% if request.show_filter %}
<div class=\"widget filterWidget\">
    <div class=\"head\"><h5 class=\"iMagnify\">{% trans 'Filter transactions' %}</h5></div>
    <div class=\"body nopadding\">

        <form method=\"get\" action=\"\" class=\"mainForm\">
            <input type=\"hidden\" name=\"_url\" value=\"{{ request._url }}\" />
            <fieldset>
                <div class=\"rowElem noborder\">
                    <label>{% trans 'ID' %}</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"id\" value=\"{{ request.id }}\">
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>{% trans 'ID on payment gateway' %}</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"txn_id\" value=\"{{ request.txn_id }}\">
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>{% trans 'Invoice Id' %}</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"invoice_id\" value=\"{{ request.invoice_id }}\">
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>{% trans 'Currency' %}</label>
                    <div class=\"formRight\">
                        {{ mf.selectbox('currency', guest.currency_get_pairs, request.currency, 0, 'All currencies') }}
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>{% trans 'Status' %}</label>
                        <div class=\"formRight\">
                            {{ mf.selectbox('status', admin.invoice_transaction_get_statuses_pairs, request.status, 0, 'All statuses') }}
                        </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>{% trans 'Payment Gateway' %}:</label>
                    <div class=\"formRight\">
                        {{ mf.selectbox('gateway_id', admin.invoice_gateway_get_pairs, request.gateway_id, 0, 'All payment gateways') }}
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>{% trans 'Received at' %}</label>
                    <div class=\"formRight moreFields\">
                        <ul>
                            <li style=\"width: 100px\"><input type=\"text\" name=\"date_from\" value=\"{% if request.date_from %}{{ request.date_from|date('Y-m-d') }}{%endif%}\" placeholder=\"{{ now|date('Y-m-d') }}\" class=\"datepicker\"/></li>
                            <li class=\"sep\">-</li>
                            <li style=\"width: 100px\"><input type=\"text\" name=\"date_to\" value=\"{% if request.date_to %}{{ request.date_to|date('Y-m-d') }}{%endif%}\" placeholder=\"{{ now|date('Y-m-d') }}\" class=\"datepicker\"/></li>
                        </ul>
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <input type=\"hidden\" name=\"show_filter\" value=\"1\" />
                <input type=\"submit\" value=\"{% trans 'Filter' %}\" class=\"greyishBtn submitForm\" />
            </fieldset>
        </form>
        <div class=\"fix\"></div>
    </div>
</div>
{% else %}
{% set statuses = admin.invoice_transaction_get_statuses %}
<div class=\"stats\">
    <ul>
        <li><a href=\"{{ 'invoice/transactions'|alink({'status' : 'processed'}) }}\" class=\"count green\" title=\"\">{{ statuses.processed }}</a><span>{% trans 'Processed' %}</span></li>
        <li><a href=\"{{ 'invoice/transactions'|alink({'status' : 'approved'}) }}\" class=\"count blue\" title=\"\">{{ statuses.approved }}</a><span>{% trans 'Approved' %}</span></li>
        <li><a href=\"{{ 'invoice/transactions'|alink({'status' : 'error'}) }}\" class=\"count red\" title=\"\">{{ statuses.error }}</a><span>{% trans 'Error' %}</span></li>
        <li class=\"last\"><a href=\"{{ 'invoice/transactions'|alink }}\" class=\"count grey\" title=\"\">{{ statuses.received + statuses.approved + statuses.processed + statuses.error }}</a><span>{% trans 'Total' %}</span></li>
    </ul>
    <div class=\"fix\"></div>
</div>
{% endif %}

{% endblock %}

{% block content %}

<div class=\"widget\">
    <div class=\"head\"><h5 class=\"iFrames\">{% trans 'Transactions' %}</h5></div>
    
    {{ mf.table_search }}
    <table class=\"tableStatic wide\">
        <thead>
            <tr>
                <td style=\"width: 2%\"><input type=\"checkbox\" class=\"batch-delete-master-checkbox\"/></td>
                <td width=\"5%\">{% trans 'Invoice' %}</td>
                <td>{% trans 'Type' %}</td>
                <td>{% trans 'Status' %}</td>
                <td>{% trans 'Gateway' %}</td>
                <td>{% trans 'Amount' %}</td>
                <td>{% trans 'Date' %}</td>
                <td width=\"18%\">&nbsp;</td>
            </tr>
        </thead>

        <tbody>
            {% set transactions = admin.invoice_transaction_get_list({\"per_page\":30, \"page\":request.page}|merge(request)) %}
            {% for i, tx in transactions.list %}
            <tr>
                <td><input type=\"checkbox\" class=\"batch-delete-checkbox\" data-item-id=\"{{ tx.id }}\"/></td>
                <td>{% if tx.invoice_id %}<a href=\"{{ 'invoice/manage'|alink }}/{{tx.invoice_id}}\">#{{ tx.invoice_id }}</a>{%else%}n/a{% endif %}</td>
                <td>{{mf.status_name(tx.type|default('-')) }}</td>
                <td>{% if tx.error %}<a href=\"#\" onclick=\"bb.msg('{{ tx.error }}','{{ tx.error_code }}'); return false;\">{{ tx.error_code }}</a>{% else %}{{mf.status_name(tx.status) }}{% endif %}</td>
                <td>{{tx.gateway}}</td>
                <td>{{ mf.currency_format( tx.amount, tx.currency) }}</td>
                <td>{{tx.created_at|date('Y-m-d H:i')}}</td>
                <td class=\"actions\">
                    <a class=\"btn14\" href=\"{{ 'invoice/transaction'|alink }}/{{ tx.id }}\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                    <a class=\"btn14 api-link\" href=\"{{ 'api/admin/invoice/transaction_process'|link({'id' : tx.id}) }}\" data-api-msg=\"Processed\" title=\"Process again\"><img src=\"images/icons/dark/refresh4.png\" alt=\"\"></a>
                    <a class=\"btn14 bb-rm-tr api-link\" href=\"{{ 'api/admin/invoice/transaction_delete'|link({'id' : tx.id}) }}\" data-api-confirm=\"Are you sure?\" data-api-reload=\"1\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                </td>
            </tr>
            {% else %}
            <tr>
                <td colspan=\"7\">
                    {% trans 'The list is empty' %}
                </td>
            </tr>
            {% endfor %}
        </tbody>
    </table>


</div>
{% include \"partial_batch_delete.phtml\" with {'action':'admin/invoice/batch_delete_transaction'} %}
{% include \"partial_pagination.phtml\" with {'list': transactions, 'url':'invoice/transactions'} %}
{% endblock %}", "mod_invoice_transactions.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-themes/admin_default/html/mod_invoice_transactions.phtml");
    }
}
