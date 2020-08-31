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

/* mod_invoice_index.phtml */
class __TwigTemplate_d3164f11433bbf3545dd921cbb1bbd1d79bf82e1d4a8ce846967e2df7a840861 extends \Twig\Template
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
        return $this->loadTemplate(((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ajax", [], "any", false, false, false, 1)) ? ("layout_blank.phtml") : ("layout_default.phtml")), "mod_invoice_index.phtml", 1);
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 2
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_invoice_index.phtml", 2)->unwrap();
        // line 4
        $context["active_menu"] = "invoice";
        // line 1
        $this->getParent($context)->display($context, array_merge($this->blocks, $blocks));
    }

    // line 3
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo gettext("Invoices");
    }

    // line 6
    public function block_top_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 7
        if (twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "show_filter", [], "any", false, false, false, 7)) {
            // line 8
            echo "<div class=\"widget filterWidget\">
    <div class=\"head\"><h5 class=\"iMagnify\">";
            // line 9
            echo gettext("Filter invoices");
            echo "</h5></div>
    <div class=\"body nopadding\">

        <form method=\"get\" action=\"\" class=\"mainForm\">
            <input type=\"hidden\" name=\"_url\" value=\"";
            // line 13
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "_url", [], "any", false, false, false, 13), "html", null, true);
            echo "\" />
            <fieldset>
                <div class=\"rowElem noborder\">
                    <label>";
            // line 16
            echo gettext("ID");
            echo "</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"id\" value=\"";
            // line 18
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "id", [], "any", false, false, false, 18), "html", null, true);
            echo "\">
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>";
            // line 24
            echo gettext("Nr");
            echo "</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"nr\" value=\"";
            // line 26
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "nr", [], "any", false, false, false, 26), "html", null, true);
            echo "\">
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>";
            // line 32
            echo gettext("Client");
            echo "</label>

                    <div class=\"formRight\">

                        <input type=\"text\" class=\"client_selector\"
                               placeholder=\"";
            // line 37
            echo gettext("Start typing clients name, email or ID");
            echo "\"
                        ";
            // line 38
            if (twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "client_id", [], "any", false, false, false, 38)) {
                // line 39
                echo "                            ";
                $context["client"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "client_get", [0 => ["id" => twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "client_id", [], "any", false, false, false, 39)]], "method", false, false, false, 39);
                // line 40
                echo "                            value=\"";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "first_name", [], "any", false, false, false, 40), "html", null, true);
                echo " ";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "last_name", [], "any", false, false, false, 40), "html", null, true);
                echo "\"
                        ";
            }
            // line 42
            echo "                            />
                        <input type=\"hidden\" name=\"client_id\" value=\"";
            // line 43
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "client_id", [], "any", false, false, false, 43), "html", null, true);
            echo "\" class=\"client_id\"/>
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>";
            // line 49
            echo gettext("Currency");
            echo "</label>
                    <div class=\"formRight\">
                        ";
            // line 51
            echo twig_call_macro($macros["mf"], "macro_selectbox", ["currency", twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "currency_get_pairs", [], "any", false, false, false, 51), twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "currency", [], "any", false, false, false, 51), 0, "All currencies"], 51, $context, $this->getSourceContext());
            echo "
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>";
            // line 57
            echo gettext("Status");
            echo "</label>
                        <div class=\"formRight\">
                            <input type=\"radio\" name=\"status\" value=\"0\"";
            // line 59
            if ( !twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "status", [], "any", false, false, false, 59)) {
                echo " checked=\"checked\"";
            }
            echo "/><label>";
            echo gettext("All statuses");
            echo "</label>
                            <input type=\"radio\" name=\"status\" value=\"paid\"";
            // line 60
            if ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "status", [], "any", false, false, false, 60) == "paid")) {
                echo " checked=\"checked\"";
            }
            echo "/><label>";
            echo gettext("Paid");
            echo "</label>
                            <input type=\"radio\" name=\"status\" value=\"unpaid\"";
            // line 61
            if ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "status", [], "any", false, false, false, 61) == "unpaid")) {
                echo " checked=\"checked\"";
            }
            echo " /><label>";
            echo gettext("Unpaid");
            echo "</label>
                            <input type=\"radio\" name=\"status\" value=\"refunded\"";
            // line 62
            if ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "status", [], "any", false, false, false, 62) == "refunded")) {
                echo " checked=\"checked\"";
            }
            echo " /><label>";
            echo gettext("Refunded");
            echo "</label>
                        </div>
                    <div class=\"fix\"></div>
                </div>

                ";
            // line 78
            echo "                <div class=\"rowElem\">
                    <label>";
            // line 79
            echo gettext("Issue date");
            echo "</label>
                    <div class=\"formRight moreFields\">
                        <ul>
                            <li style=\"width: 100px\"><input type=\"text\" name=\"date_from\" value=\"";
            // line 82
            if (twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "date_from", [], "any", false, false, false, 82)) {
                echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "date_from", [], "any", false, false, false, 82), "Y-m-d"), "html", null, true);
            }
            echo "\" placeholder=\"";
            echo twig_escape_filter($this->env, twig_date_format_filter($this->env, ($context["now"] ?? null), "Y-m-d"), "html", null, true);
            echo "\" class=\"datepicker\"/></li>
                            <li class=\"sep\">-</li>
                            <li style=\"width: 100px\"><input type=\"text\" name=\"date_to\" value=\"";
            // line 84
            if (twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "date_to", [], "any", false, false, false, 84)) {
                echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "date_to", [], "any", false, false, false, 84), "Y-m-d"), "html", null, true);
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
            // line 91
            echo gettext("Filter");
            echo "\" class=\"greyishBtn submitForm\" />
            </fieldset>
        </form>
        <div class=\"fix\"></div>
    </div>
</div>
";
        } else {
            // line 98
            $context["statuses"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "invoice_get_statuses", [], "any", false, false, false, 98);
            // line 99
            echo "<div class=\"stats\">
    <ul>
        <li><a href=\"";
            // line 101
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("invoice", ["status" => "unpaid"]);
            echo "\" class=\"count blue\" title=\"\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["statuses"] ?? null), "unpaid", [], "any", false, false, false, 101), "html", null, true);
            echo "</a><span>";
            echo gettext("Unpaid invoices");
            echo "</span></li>
        <li><a href=\"";
            // line 102
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("invoice", ["status" => "refunded"]);
            echo "\" class=\"count red\" title=\"\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["statuses"] ?? null), "refunded", [], "any", false, false, false, 102), "html", null, true);
            echo "</a><span>";
            echo gettext("Refunded invoices");
            echo "</span></li>
        <li><a href=\"";
            // line 103
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("invoice", ["status" => "paid"]);
            echo "\" class=\"count green\" title=\"\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["statuses"] ?? null), "paid", [], "any", false, false, false, 103), "html", null, true);
            echo "</a><span>";
            echo gettext("Paid invoices");
            echo "</span></li>
        <li class=\"last\"><a href=\"";
            // line 104
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("invoice");
            echo "\" class=\"count grey\" title=\"\">";
            echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["statuses"] ?? null), "paid", [], "any", false, false, false, 104) + twig_get_attribute($this->env, $this->source, ($context["statuses"] ?? null), "unpaid", [], "any", false, false, false, 104)) + twig_get_attribute($this->env, $this->source, ($context["statuses"] ?? null), "refunded", [], "any", false, false, false, 104)), "html", null, true);
            echo "</a><span>";
            echo gettext("Total");
            echo "</span></li>
    </ul>
    <div class=\"fix\"></div>
</div>
";
        }
        // line 109
        echo "
";
    }

    // line 112
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 113
        echo "

<div class=\"widget simpleTabs\">
    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">";
        // line 117
        echo gettext("Invoices");
        echo "</a></li>
        <li><a href=\"#tab-new\">";
        // line 118
        echo gettext("New Invoice");
        echo "</a></li>
    </ul>

    <div class=\"tabs_container\">
        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-index\">

            ";
        // line 125
        echo twig_call_macro($macros["mf"], "macro_table_search", [], 125, $context, $this->getSourceContext());
        echo "
            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td style=\"width: 2%\"><input type=\"checkbox\" class=\"batch-delete-master-checkbox\"/></td>
                        <td colspan=\"3\">";
        // line 130
        echo gettext("Invoice");
        echo "</td>
                        <td>";
        // line 131
        echo gettext("Amount");
        echo "</td>
                        <td>";
        // line 132
        echo gettext("Issued at");
        echo "</td>
                        <td>";
        // line 133
        echo gettext("Paid at");
        echo "</td>
                        <td>";
        // line 134
        echo gettext("Status");
        echo "</td>
                        <td width=\"13%\">&nbsp;</td>
                    </tr>
                </thead>
                <tbody>
                    ";
        // line 139
        $context["invoices"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "invoice_get_list", [0 => twig_array_merge(["per_page" => 30, "page" => twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 139)], ($context["request"] ?? null))], "method", false, false, false, 139);
        // line 140
        echo "                    ";
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["invoices"] ?? null), "list", [], "any", false, false, false, 140));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["_key"] => $context["invoice"]) {
            // line 141
            echo "                    <tr>
                        <td><input type=\"checkbox\" class=\"batch-delete-checkbox\" data-item-id=\"";
            // line 142
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["invoice"], "id", [], "any", false, false, false, 142), "html", null, true);
            echo "\"/></td>
                        <td style=\"width:5%;\"><img src=\"";
            // line 143
            echo twig_escape_filter($this->env, twig_gravatar_filter(twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["invoice"], "buyer", [], "any", false, false, false, 143), "email", [], "any", false, false, false, 143)), "html", null, true);
            echo "?size=20\" alt=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["invoice"], "buyer", [], "any", false, false, false, 143), "email", [], "any", false, false, false, 143), "html", null, true);
            echo "\" title=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["invoice"], "buyer", [], "any", false, false, false, 143), "first_name", [], "any", false, false, false, 143), "html", null, true);
            echo " ";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["invoice"], "buyer", [], "any", false, false, false, 143), "last_name", [], "any", false, false, false, 143), "html", null, true);
            echo "\"/></td>
                        <td><a href=\"";
            // line 144
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("client");
            echo "/manage/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["invoice"], "client", [], "any", false, false, false, 144), "id", [], "any", false, false, false, 144), "html", null, true);
            echo "\">";
            echo twig_escape_filter($this->env, twig_truncate_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["invoice"], "client", [], "any", false, false, false, 144), "first_name", [], "any", false, false, false, 144), 1, null, "."), "html", null, true);
            echo " ";
            echo twig_escape_filter($this->env, twig_truncate_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["invoice"], "client", [], "any", false, false, false, 144), "last_name", [], "any", false, false, false, 144), 20), "html", null, true);
            echo "</a></td>
                        <td style=\"width:15%;\" title=\"";
            // line 145
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["invoice"], "id", [], "any", false, false, false, 145), "html", null, true);
            echo "\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["invoice"], "serie_nr", [], "any", false, false, false, 145), "html", null, true);
            echo "</td>
                        <td>";
            // line 146
            echo twig_call_macro($macros["mf"], "macro_currency_format", [twig_get_attribute($this->env, $this->source, $context["invoice"], "total", [], "any", false, false, false, 146), twig_get_attribute($this->env, $this->source, $context["invoice"], "currency", [], "any", false, false, false, 146)], 146, $context, $this->getSourceContext());
            echo "</td>
                        <td>";
            // line 147
            echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, $context["invoice"], "created_at", [], "any", false, false, false, 147), "Y-m-d"), "html", null, true);
            echo "</td>
                        <td>";
            // line 148
            if (twig_get_attribute($this->env, $this->source, $context["invoice"], "paid_at", [], "any", false, false, false, 148)) {
                echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, $context["invoice"], "paid_at", [], "any", false, false, false, 148), "Y-m-d"), "html", null, true);
            } else {
                echo "-";
            }
            echo "</td>
                        <td>";
            // line 149
            echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, $context["invoice"], "status", [], "any", false, false, false, 149)], 149, $context, $this->getSourceContext());
            echo "</td>
                        <td class=\"actions\">
                            <a class=\"bb-button btn14\" href=\"";
            // line 151
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/invoice/manage");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["invoice"], "id", [], "any", false, false, false, 151), "html", null, true);
            echo "\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                            <a class=\"btn14 bb-rm-tr api-link\" href=\"";
            // line 152
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/invoice/delete", ["id" => twig_get_attribute($this->env, $this->source, $context["invoice"], "id", [], "any", false, false, false, 152)]);
            echo "\" data-api-confirm=\"Are you sure?\" data-api-reload=\"1\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                        </td>
                    </tr>
                    ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 156
            echo "                    <tr>
                        <td colspan=\"8\">";
            // line 157
            echo gettext("The list is empty");
            echo "</td>
                    </tr>
                    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['invoice'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 160
        echo "                </tbody>
            </table>

            ";
        // line 163
        $this->loadTemplate("partial_batch_delete.phtml", "mod_invoice_index.phtml", 163)->display(twig_array_merge($context, ["action" => "admin/invoice/batch_delete"]));
        // line 164
        echo "            ";
        $this->loadTemplate("partial_pagination.phtml", "mod_invoice_index.phtml", 164)->display(twig_array_merge($context, ["list" => ($context["invoices"] ?? null), "url" => "invoice"]));
        // line 165
        echo "        </div>

        <div class=\"tab_content nopadding\" id=\"tab-new\">
            <form method=\"post\" action=\"";
        // line 168
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/invoice/prepare");
        echo "\" class=\"mainForm api-form\" data-api-jsonp=\"onAfterInvoicePrepared\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>";
        // line 171
        echo gettext("Client");
        echo "</label>
                        <div class=\"formRight\">
                            <input type=\"text\" class=\"client_selector\" placeholder=\"";
        // line 173
        echo gettext("Start typing clients name, email or ID");
        echo "\"/>
                            <input type=\"hidden\" name=\"client_id\" value=\"";
        // line 174
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "client_id", [], "any", false, false, false, 174), "html", null, true);
        echo "\" class=\"client_id\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                     <input type=\"submit\" value=\"";
        // line 179
        echo gettext("Prepare");
        echo "\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>
    </div>
</div>

";
    }

    // line 189
    public function block_head($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 190
        echo "<link href=\"css/jquery-ui.css\" rel=\"stylesheet\" type=\"text/css\" />
<script type=\"text/javascript\" src=\"js/jquery-ui.js\"></script>
";
    }

    // line 194
    public function block_js($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 195
        echo "<script type=\"text/javascript\">
\$(function() {

\t\$('.client_selector').autocomplete({
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
            \$(\".client_selector\").val(ui.item.label);
            \$(\".client_id\").val(ui.item.value);
            return false;
        }
    });

});

    function onAfterInvoicePrepared(id) {
        bb.redirect(\"";
        // line 228
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("invoice/manage/");
        echo "/\"+id);
    }
</script>
";
    }

    public function getTemplateName()
    {
        return "mod_invoice_index.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  536 => 228,  501 => 195,  497 => 194,  491 => 190,  487 => 189,  475 => 179,  467 => 174,  463 => 173,  458 => 171,  452 => 168,  447 => 165,  444 => 164,  442 => 163,  437 => 160,  428 => 157,  425 => 156,  416 => 152,  410 => 151,  405 => 149,  397 => 148,  393 => 147,  389 => 146,  383 => 145,  373 => 144,  363 => 143,  359 => 142,  356 => 141,  350 => 140,  348 => 139,  340 => 134,  336 => 133,  332 => 132,  328 => 131,  324 => 130,  316 => 125,  306 => 118,  302 => 117,  296 => 113,  292 => 112,  287 => 109,  275 => 104,  267 => 103,  259 => 102,  251 => 101,  247 => 99,  245 => 98,  235 => 91,  221 => 84,  212 => 82,  206 => 79,  203 => 78,  191 => 62,  183 => 61,  175 => 60,  167 => 59,  162 => 57,  153 => 51,  148 => 49,  139 => 43,  136 => 42,  128 => 40,  125 => 39,  123 => 38,  119 => 37,  111 => 32,  102 => 26,  97 => 24,  88 => 18,  83 => 16,  77 => 13,  70 => 9,  67 => 8,  65 => 7,  61 => 6,  54 => 3,  50 => 1,  48 => 4,  46 => 2,  39 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}
{% import \"macro_functions.phtml\" as mf %}
{% block meta_title %}{% trans 'Invoices' %}{% endblock %}
{% set active_menu = 'invoice' %}

{% block top_content %}
{% if request.show_filter %}
<div class=\"widget filterWidget\">
    <div class=\"head\"><h5 class=\"iMagnify\">{% trans 'Filter invoices' %}</h5></div>
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
                    <label>{% trans 'Nr' %}</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"nr\" value=\"{{ request.nr }}\">
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>{% trans 'Client' %}</label>

                    <div class=\"formRight\">

                        <input type=\"text\" class=\"client_selector\"
                               placeholder=\"{% trans 'Start typing clients name, email or ID' %}\"
                        {% if request.client_id %}
                            {% set client = admin.client_get({\"id\":request.client_id}) %}
                            value=\"{{ client.first_name }} {{ client.last_name }}\"
                        {% endif %}
                            />
                        <input type=\"hidden\" name=\"client_id\" value=\"{{ request.client_id }}\" class=\"client_id\"/>
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
                            <input type=\"radio\" name=\"status\" value=\"0\"{% if not request.status %} checked=\"checked\"{% endif %}/><label>{% trans 'All statuses' %}</label>
                            <input type=\"radio\" name=\"status\" value=\"paid\"{% if request.status == 'paid' %} checked=\"checked\"{% endif %}/><label>{% trans 'Paid' %}</label>
                            <input type=\"radio\" name=\"status\" value=\"unpaid\"{% if request.status == 'unpaid' %} checked=\"checked\"{% endif %} /><label>{% trans 'Unpaid' %}</label>
                            <input type=\"radio\" name=\"status\" value=\"refunded\"{% if request.status == 'refunded' %} checked=\"checked\"{% endif %} /><label>{% trans 'Refunded' %}</label>
                        </div>
                    <div class=\"fix\"></div>
                </div>

                {#
                <div class=\"rowElem\">
                    <label>{% trans 'Approved' %}</label>
                        <div class=\"formRight\">
                            <input type=\"radio\" name=\"approved\" value=\"\"{% if not request.approved %} checked=\"checked\"{% endif %}/><label>All</label>
                            <input type=\"radio\" name=\"approved\" value=\"0\"{% if request.approved == \"1\"%} checked=\"checked\"{% endif %} /><label>Pending approval</label>
                            <input type=\"radio\" name=\"approved\" value=\"1\"{% if request.approved == \"0\" %} checked=\"checked\"{% endif %}/><label>Approved</label>
                        </div>
                    <div class=\"fix\"></div>
                </div>
                #}
                <div class=\"rowElem\">
                    <label>{% trans 'Issue date' %}</label>
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
{% set statuses = admin.invoice_get_statuses %}
<div class=\"stats\">
    <ul>
        <li><a href=\"{{ 'invoice'|alink({'status' : 'unpaid'}) }}\" class=\"count blue\" title=\"\">{{ statuses.unpaid }}</a><span>{% trans 'Unpaid invoices' %}</span></li>
        <li><a href=\"{{ 'invoice'|alink({'status' : 'refunded'}) }}\" class=\"count red\" title=\"\">{{ statuses.refunded }}</a><span>{% trans 'Refunded invoices' %}</span></li>
        <li><a href=\"{{ 'invoice'|alink({'status' : 'paid'}) }}\" class=\"count green\" title=\"\">{{ statuses.paid }}</a><span>{% trans 'Paid invoices' %}</span></li>
        <li class=\"last\"><a href=\"{{ 'invoice'|alink }}\" class=\"count grey\" title=\"\">{{ statuses.paid + statuses.unpaid + statuses.refunded}}</a><span>{% trans 'Total' %}</span></li>
    </ul>
    <div class=\"fix\"></div>
</div>
{% endif %}

{% endblock %}

{% block content %}


<div class=\"widget simpleTabs\">
    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">{% trans 'Invoices' %}</a></li>
        <li><a href=\"#tab-new\">{% trans 'New Invoice' %}</a></li>
    </ul>

    <div class=\"tabs_container\">
        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-index\">

            {{ mf.table_search }}
            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td style=\"width: 2%\"><input type=\"checkbox\" class=\"batch-delete-master-checkbox\"/></td>
                        <td colspan=\"3\">{% trans 'Invoice' %}</td>
                        <td>{% trans 'Amount' %}</td>
                        <td>{% trans 'Issued at' %}</td>
                        <td>{% trans 'Paid at' %}</td>
                        <td>{% trans 'Status' %}</td>
                        <td width=\"13%\">&nbsp;</td>
                    </tr>
                </thead>
                <tbody>
                    {% set invoices = admin.invoice_get_list({\"per_page\":30, \"page\":request.page}|merge(request)) %}
                    {% for invoice in invoices.list %}
                    <tr>
                        <td><input type=\"checkbox\" class=\"batch-delete-checkbox\" data-item-id=\"{{ invoice.id }}\"/></td>
                        <td style=\"width:5%;\"><img src=\"{{ invoice.buyer.email|gravatar }}?size=20\" alt=\"{{ invoice.buyer.email }}\" title=\"{{invoice.buyer.first_name}} {{invoice.buyer.last_name}}\"/></td>
                        <td><a href=\"{{ 'client'|alink }}/manage/{{ invoice.client.id }}\">{{invoice.client.first_name|truncate(1, null, '.')}} {{invoice.client.last_name|truncate(20)}}</a></td>
                        <td style=\"width:15%;\" title=\"{{invoice.id}}\">{{ invoice.serie_nr }}</td>
                        <td>{{ mf.currency_format( invoice.total, invoice.currency) }}</td>
                        <td>{{ invoice.created_at|date('Y-m-d') }}</td>
                        <td>{% if invoice.paid_at %}{{ invoice.paid_at|date('Y-m-d') }}{% else %}-{% endif %}</td>
                        <td>{{ mf.status_name(invoice.status) }}</td>
                        <td class=\"actions\">
                            <a class=\"bb-button btn14\" href=\"{{ '/invoice/manage'|alink }}/{{invoice.id}}\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                            <a class=\"btn14 bb-rm-tr api-link\" href=\"{{ 'api/admin/invoice/delete'|link({'id' : invoice.id}) }}\" data-api-confirm=\"Are you sure?\" data-api-reload=\"1\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                        </td>
                    </tr>
                    {% else %}
                    <tr>
                        <td colspan=\"8\">{% trans 'The list is empty' %}</td>
                    </tr>
                    {% endfor %}
                </tbody>
            </table>

            {% include \"partial_batch_delete.phtml\" with {'action' : 'admin/invoice/batch_delete'} %}
            {% include \"partial_pagination.phtml\" with {'list': invoices, 'url':'invoice'} %}
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-new\">
            <form method=\"post\" action=\"{{ 'api/admin/invoice/prepare'|link }}\" class=\"mainForm api-form\" data-api-jsonp=\"onAfterInvoicePrepared\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>{% trans 'Client' %}</label>
                        <div class=\"formRight\">
                            <input type=\"text\" class=\"client_selector\" placeholder=\"{% trans 'Start typing clients name, email or ID' %}\"/>
                            <input type=\"hidden\" name=\"client_id\" value=\"{{ request.client_id }}\" class=\"client_id\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                     <input type=\"submit\" value=\"{% trans 'Prepare' %}\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>
    </div>
</div>

{% endblock %}


{% block head %}
<link href=\"css/jquery-ui.css\" rel=\"stylesheet\" type=\"text/css\" />
<script type=\"text/javascript\" src=\"js/jquery-ui.js\"></script>
{% endblock %}

{% block js%}
<script type=\"text/javascript\">
\$(function() {

\t\$('.client_selector').autocomplete({
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
            \$(\".client_selector\").val(ui.item.label);
            \$(\".client_id\").val(ui.item.value);
            return false;
        }
    });

});

    function onAfterInvoicePrepared(id) {
        bb.redirect(\"{{'invoice/manage/'|alink}}/\"+id);
    }
</script>
{% endblock %}", "mod_invoice_index.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-themes/admin_default/html/mod_invoice_index.phtml");
    }
}
