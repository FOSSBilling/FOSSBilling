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

/* mod_order_index.phtml */
class __TwigTemplate_088c46b2a3b34e97e007ed07ba48af52ebde0616903acc147122bb2ccf05a008 extends \Twig\Template
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
            'head' => [$this, 'block_head'],
            'js' => [$this, 'block_js'],
        ];
    }

    protected function doGetParent(array $context)
    {
        // line 1
        return $this->loadTemplate(((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ajax", [], "any", false, false, false, 1)) ? ("layout_blank.phtml") : ("layout_default.phtml")), "mod_order_index.phtml", 1);
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 2
        $context["active_menu"] = "order";
        // line 3
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_order_index.phtml", 3)->unwrap();
        // line 1
        $this->getParent($context)->display($context, array_merge($this->blocks, $blocks));
    }

    // line 5
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo gettext("Orders");
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
            echo gettext("Filter orders");
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
            echo gettext("Title");
            echo "</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"title\" value=\"";
            // line 27
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "title", [], "any", false, false, false, 27), "html", null, true);
            echo "\">
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>";
            // line 33
            echo gettext("Status");
            echo "</label>
                    <div class=\"formRight\">
                        ";
            // line 35
            echo twig_call_macro($macros["mf"], "macro_selectbox", ["status", twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "order_get_status_pairs", [], "any", false, false, false, 35), twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "status", [], "any", false, false, false, 35), 0, "All statuses"], 35, $context, $this->getSourceContext());
            echo "
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>";
            // line 41
            echo gettext("Type");
            echo ":</label>
                    <div class=\"formRight\">
                        ";
            // line 43
            echo twig_call_macro($macros["mf"], "macro_selectbox", ["type", twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "product_get_types", [], "any", false, false, false, 43), twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "type", [], "any", false, false, false, 43), 0, "All types"], 43, $context, $this->getSourceContext());
            echo "
                    </div>
                    <div class=\"fix\"></div>
                </div>
                
                <div class=\"rowElem\">
                    <label>";
            // line 49
            echo gettext("Product");
            echo ":</label>
                    <div class=\"formRight\">
                        ";
            // line 51
            echo twig_call_macro($macros["mf"], "macro_selectbox", ["product_id", twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "product_get_pairs", [], "any", false, false, false, 51), twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "product_id", [], "any", false, false, false, 51), 0, "All products"], 51, $context, $this->getSourceContext());
            echo "
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>";
            // line 57
            echo gettext("Period");
            echo "</label>
                    <div class=\"formRight\">
                        ";
            // line 59
            echo twig_call_macro($macros["mf"], "macro_selectbox", ["period", twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_periods", [], "any", false, false, false, 59), twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "period", [], "any", false, false, false, 59), 0, "All periods"], 59, $context, $this->getSourceContext());
            echo "
                    </div>
                    <div class=\"fix\"></div>
                </div>
                
                <div class=\"rowElem\">
                    <label>";
            // line 65
            echo gettext("Invoice option");
            echo "</label>
                    <div class=\"formRight\">
                        ";
            // line 67
            echo twig_call_macro($macros["mf"], "macro_selectbox", ["invoice_option", twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "order_get_invoice_options", [], "any", false, false, false, 67), twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "invoice_option", [], "any", false, false, false, 67), 0, "All types"], 67, $context, $this->getSourceContext());
            echo "
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>";
            // line 73
            echo gettext("Creation date");
            echo "</label>
                    <div class=\"formRight moreFields\">
                        <ul>
                            <li style=\"width: 100px\"><input type=\"text\" name=\"date_from\" value=\"";
            // line 76
            if (twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "date_from", [], "any", false, false, false, 76)) {
                echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "date_from", [], "any", false, false, false, 76), "Y-m-d"), "html", null, true);
            }
            echo "\" placeholder=\"";
            echo twig_escape_filter($this->env, twig_date_format_filter($this->env, ($context["now"] ?? null), "Y-m-d"), "html", null, true);
            echo "\" class=\"datepicker\"/></li>
                            <li class=\"sep\">-</li>
                            <li style=\"width: 100px\"><input type=\"text\" name=\"date_to\" value=\"";
            // line 78
            if (twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "date_to", [], "any", false, false, false, 78)) {
                echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "date_to", [], "any", false, false, false, 78), "Y-m-d"), "html", null, true);
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
            // line 85
            echo gettext("Filter");
            echo "\" class=\"greyishBtn submitForm\" />
            </fieldset>
        </form>
        <div class=\"fix\"></div>
    </div>
</div>
";
        } else {
            // line 92
            $context["count_orders"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "order_get_statuses", [], "any", false, false, false, 92);
            // line 93
            echo "<div class=\"stats\">
    <ul>
        <li><a href=\"";
            // line 95
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("order", ["status" => "pending_setup"]);
            echo "\" class=\"count green\" title=\"\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["count_orders"] ?? null), "pending_setup", [], "any", false, false, false, 95), "html", null, true);
            echo "</a><span>";
            echo gettext("Pending setup");
            echo "</span></li>
        <li><a href=\"";
            // line 96
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("order", ["status" => "active"]);
            echo "\" class=\"count blue\" title=\"\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["count_orders"] ?? null), "active", [], "any", false, false, false, 96), "html", null, true);
            echo "</a><span>";
            echo gettext("Active");
            echo "</span></li>
        <li><a href=\"";
            // line 97
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("order", ["status" => "suspended"]);
            echo "\" class=\"count red\" title=\"\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["count_orders"] ?? null), "suspended", [], "any", false, false, false, 97), "html", null, true);
            echo "</a><span>";
            echo gettext("Suspended");
            echo "</span></li>
        <li class=\"last\"><a href=\"";
            // line 98
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("order");
            echo "\" class=\"count grey\" title=\"\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["count_orders"] ?? null), "total", [], "any", false, false, false, 98), "html", null, true);
            echo " </a><span>";
            echo gettext("Total");
            echo "</span></li>
    </ul>
    <div class=\"fix\"></div>
</div>

";
        }
        // line 104
        echo "
";
    }

    // line 107
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 108
        echo "
<div class=\"widget simpleTabs\">
    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">";
        // line 111
        echo gettext("Orders");
        echo "</a></li>
        <li><a href=\"#tab-new\">";
        // line 112
        echo gettext("New order");
        echo "</a></li>
    </ul>

    <div class=\"tabs_container\">
        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-index\">
        ";
        // line 118
        echo twig_call_macro($macros["mf"], "macro_table_search", [], 118, $context, $this->getSourceContext());
        echo "
        <table class=\"tableStatic wide\" style=\"table-layout: fixed\">
            <thead>
                <tr>
                    <td style=\"width: 3%\"><input type=\"checkbox\" class=\"batch-delete-master-checkbox\"/></td>
                    <td style=\"width: 5%\">";
        // line 123
        echo gettext("ID");
        echo "</td>
                    <td width=\"13%\">";
        // line 124
        echo gettext("Client");
        echo "</td>
                    <td width=\"45%\">";
        // line 125
        echo gettext("Title");
        echo "</td>
                    <td style=\"width: 7%\">";
        // line 126
        echo gettext("Amount");
        echo "</td>
                    <td>";
        // line 127
        echo gettext("Period");
        echo "</td>
                    <td>";
        // line 128
        echo gettext("Status");
        echo "</td>
                    <td style=\"width: 10%\">&nbsp;</td>
                </tr>
            </thead>

            <tbody>
                ";
        // line 134
        $context["orders"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "order_get_list", [0 => twig_array_merge(["per_page" => 30, "page" => twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 134)], ($context["request"] ?? null))], "method", false, false, false, 134);
        // line 135
        echo "                ";
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["orders"] ?? null), "list", [], "any", false, false, false, 135));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["i"] => $context["order"]) {
            // line 136
            echo "                <tr>
                    <td><input type=\"checkbox\" class=\"batch-delete-checkbox\" data-item-id=\"";
            // line 137
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["order"], "id", [], "any", false, false, false, 137), "html", null, true);
            echo "\"/></td>
                    <td>";
            // line 138
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["order"], "id", [], "any", false, false, false, 138), "html", null, true);
            echo "</td>
                    <td class=\"truncate\">
                        <span style=\"float: left;\">
                            <a href=\"";
            // line 141
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("client/manage");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["order"], "client_id", [], "any", false, false, false, 141), "html", null, true);
            echo "\"><img src=\"";
            echo twig_escape_filter($this->env, twig_gravatar_filter(twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["order"], "client", [], "any", false, false, false, 141), "email", [], "any", false, false, false, 141)), "html", null, true);
            echo "?size=20\" alt=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["order"], "client", [], "any", false, false, false, 141), "email", [], "any", false, false, false, 141), "html", null, true);
            echo "\" /></a>
                        </span>
                        <span style=\"margin-left: 10px;\">
                            <a href=\"";
            // line 144
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("client/manage");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["order"], "client_id", [], "any", false, false, false, 144), "html", null, true);
            echo "\">";
            echo twig_escape_filter($this->env, twig_truncate_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["order"], "client", [], "any", false, false, false, 144), "first_name", [], "any", false, false, false, 144), "1", null, "."), "html", null, true);
            echo " ";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["order"], "client", [], "any", false, false, false, 144), "last_name", [], "any", false, false, false, 144), "html", null, true);
            echo "</a>
                        </span>
                    </td>
                    <td class=\"truncate\">";
            // line 147
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["order"], "title", [], "any", false, false, false, 147), "html", null, true);
            echo "</td>
                    <td>";
            // line 148
            echo twig_call_macro($macros["mf"], "macro_currency_format", [twig_get_attribute($this->env, $this->source, $context["order"], "total", [], "any", false, false, false, 148), twig_get_attribute($this->env, $this->source, $context["order"], "currency", [], "any", false, false, false, 148)], 148, $context, $this->getSourceContext());
            echo "</td>
                    <td>";
            // line 149
            echo twig_call_macro($macros["mf"], "macro_period_name", [twig_get_attribute($this->env, $this->source, $context["order"], "period", [], "any", false, false, false, 149)], 149, $context, $this->getSourceContext());
            echo "</td>
                    <td>";
            // line 150
            echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, $context["order"], "status", [], "any", false, false, false, 150)], 150, $context, $this->getSourceContext());
            echo "</td>
                    <td class=\"actions\">
                        <a class=\"bb-button btn14\" href=\"";
            // line 152
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/order/manage");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["order"], "id", [], "any", false, false, false, 152), "html", null, true);
            echo "\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                        <a class=\"bb-button btn14 bb-rm-tr api-link\" data-api-confirm=\"Are you sure?\" href=\"";
            // line 153
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/order/delete", ["id" => twig_get_attribute($this->env, $this->source, $context["order"], "id", [], "any", false, false, false, 153)]);
            echo "\" data-api-redirect=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("order");
            echo "\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                    </td>
                </tr>
                ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 157
            echo "                <tr>
                    <td colspan=\"8\">";
            // line 158
            echo gettext("The list is empty");
            echo "</td>
                </tr>
                ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['i'], $context['order'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 161
        echo "            </tbody>
        </table>
        ";
        // line 163
        $this->loadTemplate("partial_pagination.phtml", "mod_order_index.phtml", 163)->display(twig_array_merge($context, ["list" => ($context["orders"] ?? null), "url" => "order"]));
        // line 164
        echo "        ";
        $this->loadTemplate("partial_batch_delete.phtml", "mod_order_index.phtml", 164)->display(twig_array_merge($context, ["action" => "admin/order/batch_delete"]));
        // line 165
        echo "        </div>
    </div>
    
    <div class=\"tab_content nopadding\" id=\"tab-new\">
        <form method=\"post\" action=\"";
        // line 169
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("order/new");
        echo "\" class=\"mainForm\">
            <fieldset>
                <div class=\"rowElem noborder\">
                    <label>";
        // line 172
        echo gettext("Client");
        echo "</label>
                    <div class=\"formRight\">
                        ";
        // line 174
        if ( !twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "client_id", [], "any", false, false, false, 174)) {
            // line 175
            echo "                        <input type=\"text\" id=\"client_selector\" placeholder=\"";
            echo gettext("Start typing clients name, email or ID");
            echo "\" required=\"required\"/>
                        <input type=\"hidden\" name=\"client_id\" value=\"";
            // line 176
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "client_id", [], "any", false, false, false, 176), "html", null, true);
            echo "\" id=\"client_id\"/>
                        ";
        } else {
            // line 178
            echo "                            ";
            $context["client"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "client_get", [0 => ["id" => twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "client_id", [], "any", false, false, false, 178)]], "method", false, false, false, 178);
            // line 179
            echo "                            ";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "first_name", [], "any", false, false, false, 179), "html", null, true);
            echo " ";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "last_name", [], "any", false, false, false, 179), "html", null, true);
            echo "
                        ";
        }
        // line 181
        echo "                    </div>
                    <div class=\"fix\"></div>
                </div>
                <div class=\"rowElem\">
                    <label>";
        // line 185
        echo gettext("Product");
        echo ":</label>
                    <div class=\"formRight\">
                        ";
        // line 187
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["product_id", twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "product_get_pairs", [], "any", false, false, false, 187), twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "product_id", [], "any", false, false, false, 187), 1], 187, $context, $this->getSourceContext());
        echo "
                    </div>
                    <div class=\"fix\"></div>
                </div>
                
            <input type=\"submit\" value=\"";
        // line 192
        echo gettext("Continue");
        echo "\" class=\"greyishBtn submitForm\" />
            ";
        // line 193
        if (twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "client_id", [], "any", false, false, false, 193)) {
            // line 194
            echo "            <input type=\"hidden\" name=\"client_id\" value=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "client_id", [], "any", false, false, false, 194), "html", null, true);
            echo "\" />
            ";
        }
        // line 196
        echo "            </fieldset>
        </form>
    </div>
</div>

";
    }

    // line 203
    public function block_head($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 204
        echo "<link href=\"css/jquery-ui.css\" rel=\"stylesheet\" type=\"text/css\" />
<script type=\"text/javascript\" src=\"js/jquery-ui.js\"></script>
";
    }

    // line 208
    public function block_js($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 209
        if ( !twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "client_id", [], "any", false, false, false, 209)) {
            // line 210
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
    }

    public function getTemplateName()
    {
        return "mod_order_index.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  523 => 210,  521 => 209,  517 => 208,  511 => 204,  507 => 203,  498 => 196,  492 => 194,  490 => 193,  486 => 192,  478 => 187,  473 => 185,  467 => 181,  459 => 179,  456 => 178,  451 => 176,  446 => 175,  444 => 174,  439 => 172,  433 => 169,  427 => 165,  424 => 164,  422 => 163,  418 => 161,  409 => 158,  406 => 157,  395 => 153,  389 => 152,  384 => 150,  380 => 149,  376 => 148,  372 => 147,  360 => 144,  348 => 141,  342 => 138,  338 => 137,  335 => 136,  329 => 135,  327 => 134,  318 => 128,  314 => 127,  310 => 126,  306 => 125,  302 => 124,  298 => 123,  290 => 118,  281 => 112,  277 => 111,  272 => 108,  268 => 107,  263 => 104,  250 => 98,  242 => 97,  234 => 96,  226 => 95,  222 => 93,  220 => 92,  210 => 85,  196 => 78,  187 => 76,  181 => 73,  172 => 67,  167 => 65,  158 => 59,  153 => 57,  144 => 51,  139 => 49,  130 => 43,  125 => 41,  116 => 35,  111 => 33,  102 => 27,  97 => 25,  88 => 19,  83 => 17,  77 => 14,  70 => 10,  67 => 9,  65 => 8,  61 => 7,  54 => 5,  50 => 1,  48 => 3,  46 => 2,  39 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}
{% set active_menu = 'order' %}
{% import \"macro_functions.phtml\" as mf %}

{% block meta_title %}{% trans 'Orders' %}{% endblock %}

{% block top_content %}
{% if request.show_filter %}
<div class=\"widget filterWidget\">
    <div class=\"head\"><h5 class=\"iMagnify\">{% trans 'Filter orders' %}</h5></div>
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
                    <label>{% trans 'Title' %}</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"title\" value=\"{{ request.title }}\">
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>{% trans 'Status' %}</label>
                    <div class=\"formRight\">
                        {{ mf.selectbox('status', admin.order_get_status_pairs, request.status, 0, 'All statuses') }}
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>{% trans 'Type' %}:</label>
                    <div class=\"formRight\">
                        {{ mf.selectbox('type', admin.product_get_types, request.type, 0, 'All types') }}
                    </div>
                    <div class=\"fix\"></div>
                </div>
                
                <div class=\"rowElem\">
                    <label>{% trans 'Product' %}:</label>
                    <div class=\"formRight\">
                        {{ mf.selectbox('product_id', admin.product_get_pairs, request.product_id, 0, 'All products') }}
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>{% trans 'Period' %}</label>
                    <div class=\"formRight\">
                        {{ mf.selectbox('period', guest.system_periods, request.period, 0, 'All periods') }}
                    </div>
                    <div class=\"fix\"></div>
                </div>
                
                <div class=\"rowElem\">
                    <label>{% trans 'Invoice option' %}</label>
                    <div class=\"formRight\">
                        {{ mf.selectbox('invoice_option', admin.order_get_invoice_options, request.invoice_option, 0, 'All types') }}
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>{% trans 'Creation date' %}</label>
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
{% set count_orders = admin.order_get_statuses %}
<div class=\"stats\">
    <ul>
        <li><a href=\"{{ 'order'|alink({'status' : 'pending_setup'}) }}\" class=\"count green\" title=\"\">{{ count_orders.pending_setup }}</a><span>{% trans 'Pending setup' %}</span></li>
        <li><a href=\"{{ 'order'|alink({'status' : 'active'}) }}\" class=\"count blue\" title=\"\">{{ count_orders.active }}</a><span>{% trans 'Active' %}</span></li>
        <li><a href=\"{{ 'order'|alink({'status' : 'suspended'}) }}\" class=\"count red\" title=\"\">{{ count_orders.suspended }}</a><span>{% trans 'Suspended' %}</span></li>
        <li class=\"last\"><a href=\"{{ 'order'|alink }}\" class=\"count grey\" title=\"\">{{ count_orders.total }} </a><span>{% trans 'Total' %}</span></li>
    </ul>
    <div class=\"fix\"></div>
</div>

{% endif %}

{% endblock %}

{% block content %}

<div class=\"widget simpleTabs\">
    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">{% trans 'Orders' %}</a></li>
        <li><a href=\"#tab-new\">{% trans 'New order' %}</a></li>
    </ul>

    <div class=\"tabs_container\">
        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-index\">
        {{ mf.table_search }}
        <table class=\"tableStatic wide\" style=\"table-layout: fixed\">
            <thead>
                <tr>
                    <td style=\"width: 3%\"><input type=\"checkbox\" class=\"batch-delete-master-checkbox\"/></td>
                    <td style=\"width: 5%\">{% trans 'ID' %}</td>
                    <td width=\"13%\">{% trans 'Client' %}</td>
                    <td width=\"45%\">{% trans 'Title' %}</td>
                    <td style=\"width: 7%\">{% trans 'Amount' %}</td>
                    <td>{% trans 'Period' %}</td>
                    <td>{% trans 'Status' %}</td>
                    <td style=\"width: 10%\">&nbsp;</td>
                </tr>
            </thead>

            <tbody>
                {% set orders = admin.order_get_list({\"per_page\":30, \"page\":request.page}|merge(request)) %}
                {% for i, order in orders.list %}
                <tr>
                    <td><input type=\"checkbox\" class=\"batch-delete-checkbox\" data-item-id=\"{{ order.id }}\"/></td>
                    <td>{{order.id}}</td>
                    <td class=\"truncate\">
                        <span style=\"float: left;\">
                            <a href=\"{{ 'client/manage'|alink }}/{{ order.client_id }}\"><img src=\"{{ order.client.email|gravatar }}?size=20\" alt=\"{{ order.client.email }}\" /></a>
                        </span>
                        <span style=\"margin-left: 10px;\">
                            <a href=\"{{ 'client/manage'|alink }}/{{ order.client_id }}\">{{order.client.first_name|truncate('1', null, '.')}} {{order.client.last_name}}</a>
                        </span>
                    </td>
                    <td class=\"truncate\">{{order.title }}</td>
                    <td>{{ mf.currency_format( order.total, order.currency) }}</td>
                    <td>{{ mf.period_name(order.period) }}</td>
                    <td>{{ mf.status_name(order.status) }}</td>
                    <td class=\"actions\">
                        <a class=\"bb-button btn14\" href=\"{{ '/order/manage'|alink }}/{{order.id}}\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                        <a class=\"bb-button btn14 bb-rm-tr api-link\" data-api-confirm=\"Are you sure?\" href=\"{{ 'api/admin/order/delete'|link({'id' : order.id}) }}\" data-api-redirect=\"{{ 'order'|alink }}\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                    </td>
                </tr>
                {% else %}
                <tr>
                    <td colspan=\"8\">{% trans 'The list is empty' %}</td>
                </tr>
                {% endfor %}
            </tbody>
        </table>
        {% include \"partial_pagination.phtml\" with {'list': orders, 'url':'order'} %}
        {% include \"partial_batch_delete.phtml\" with {'action' : 'admin/order/batch_delete'} %}
        </div>
    </div>
    
    <div class=\"tab_content nopadding\" id=\"tab-new\">
        <form method=\"post\" action=\"{{ 'order/new'|alink }}\" class=\"mainForm\">
            <fieldset>
                <div class=\"rowElem noborder\">
                    <label>{% trans 'Client' %}</label>
                    <div class=\"formRight\">
                        {% if not request.client_id %}
                        <input type=\"text\" id=\"client_selector\" placeholder=\"{% trans 'Start typing clients name, email or ID' %}\" required=\"required\"/>
                        <input type=\"hidden\" name=\"client_id\" value=\"{{ request.client_id }}\" id=\"client_id\"/>
                        {% else %}
                            {% set client = admin.client_get({\"id\":request.client_id}) %}
                            {{ client.first_name }} {{ client.last_name }}
                        {% endif %}
                    </div>
                    <div class=\"fix\"></div>
                </div>
                <div class=\"rowElem\">
                    <label>{% trans 'Product' %}:</label>
                    <div class=\"formRight\">
                        {{ mf.selectbox('product_id', admin.product_get_pairs, request.product_id, 1) }}
                    </div>
                    <div class=\"fix\"></div>
                </div>
                
            <input type=\"submit\" value=\"{% trans 'Continue' %}\" class=\"greyishBtn submitForm\" />
            {% if request.client_id %}
            <input type=\"hidden\" name=\"client_id\" value=\"{{ request.client_id}}\" />
            {% endif %}
            </fieldset>
        </form>
    </div>
</div>

{% endblock %}

{% block head %}
<link href=\"css/jquery-ui.css\" rel=\"stylesheet\" type=\"text/css\" />
<script type=\"text/javascript\" src=\"js/jquery-ui.js\"></script>
{% endblock %}

{% block js%}
{% if not request.client_id %}
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
{% endif %}
{% endblock %}", "mod_order_index.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-themes/admin_default/html/mod_order_index.phtml");
    }
}
