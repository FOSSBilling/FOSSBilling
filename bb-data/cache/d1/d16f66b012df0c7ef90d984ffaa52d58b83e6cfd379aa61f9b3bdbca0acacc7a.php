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

/* mod_invoice_transaction.phtml */
class __TwigTemplate_ffc143f1ed2476d32290a303777abbe5bdd0edc1277ee368607f2f78129c6870 extends \Twig\Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->blocks = [
            'meta_title' => [$this, 'block_meta_title'],
            'breadcrumbs' => [$this, 'block_breadcrumbs'],
            'content' => [$this, 'block_content'],
        ];
    }

    protected function doGetParent(array $context)
    {
        // line 1
        return $this->loadTemplate(((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ajax", [], "any", false, false, false, 1)) ? ("layout_blank.phtml") : ("layout_default.phtml")), "mod_invoice_transaction.phtml", 1);
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 2
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_invoice_transaction.phtml", 2)->unwrap();
        // line 3
        $context["active_menu"] = "invoice";
        // line 1
        $this->getParent($context)->display($context, array_merge($this->blocks, $blocks));
    }

    // line 4
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo "Transaction ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "txn_id", [], "any", false, false, false, 4), "html", null, true);
    }

    // line 7
    public function block_breadcrumbs($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 8
        echo "<ul>
    <li class=\"firstB\"><a href=\"";
        // line 9
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/");
        echo "\">";
        echo gettext("Home");
        echo "</a></li>
    <li><a href=\"";
        // line 10
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("invoice/transactions");
        echo "\">";
        echo gettext("Transactions");
        echo "</a></li>
    <li class=\"lastB\">#";
        // line 11
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "id", [], "any", false, false, false, 11), "html", null, true);
        echo "</li>
</ul>
";
    }

    // line 15
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 16
        echo "
<div class=\"widget simpleTabs\">

    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">";
        // line 20
        echo gettext("Transaction");
        echo "</a></li>
        <li><a href=\"#tab-manage\">";
        // line 21
        echo gettext("Manage");
        echo "</a></li>
        <li><a href=\"#tab-ipn\">";
        // line 22
        echo gettext("IPN");
        echo "</a></li>
        <li><a href=\"#tab-note\">";
        // line 23
        echo gettext("Notes");
        echo "</a></li>
    </ul>

    <div class=\"tab_container\">

        <div class=\"tab_content nopadding\" id=\"tab-index\">
            <div class=\"help\">
                <h3>";
        // line 30
        echo gettext("Transaction details");
        echo " #";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "id", [], "any", false, false, false, 30), "html", null, true);
        echo "</h3>
            </div>

            ";
        // line 33
        if (twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "error", [], "any", false, false, false, 33)) {
            // line 34
            echo "            <div class=\"body\">
                <strong class=\"red\">";
            // line 35
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "error_code", [], "any", false, false, false, 35), "html", null, true);
            echo " - ";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "error", [], "any", false, false, false, 35), "html", null, true);
            echo "</strong>
                <p>";
            // line 36
            echo gettext("If you are sure that this transaction is valid you can update transaction details in &quot;Manage&quot; tab and try processing transaction again");
            echo "</p>
            </div>
            ";
        }
        // line 39
        echo "

            <table class=\"tableStatic wide\">
                <tbody>
                    <tr ";
        // line 43
        if ( !twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "error", [], "any", false, false, false, 43)) {
            echo "class=\"noborder\"";
        }
        echo ">
                        <td style=\"width: 35%\">";
        // line 44
        echo gettext("ID");
        echo "</td>
                        <td>";
        // line 45
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "id", [], "any", false, false, false, 45), "html", null, true);
        echo "</td>
                    </tr>

                    <tr>
                        <td>";
        // line 49
        echo gettext("Invoice Id");
        echo "</td>
                        <td><a href=\"";
        // line 50
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("invoice/manage");
        echo "/";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "invoice_id", [], "any", false, false, false, 50), "html", null, true);
        echo "\">";
        echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "invoice_id", [], "any", true, true, false, 50)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "invoice_id", [], "any", false, false, false, 50), "-")) : ("-")), "html", null, true);
        echo "</a></td>
                    </tr>

                    <tr>
                        <td>";
        // line 54
        echo gettext("Amount");
        echo "</td>
                        <td>";
        // line 55
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "amount", [], "any", false, false, false, 55), "html", null, true);
        echo " ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "currency", [], "any", false, false, false, 55), "html", null, true);
        echo "</td>
                    </tr>

                    <tr>
                        <td>";
        // line 59
        echo gettext("Payment gateway");
        echo "</td>
                        <td>";
        // line 60
        echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "gateway", [], "any", true, true, false, 60)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "gateway", [], "any", false, false, false, 60), "-")) : ("-")), "html", null, true);
        echo "</td>
                    </tr>

                    <tr>
                        <td>";
        // line 64
        echo gettext("Transaction ID on payment gateway");
        echo "</td>
                        <td>";
        // line 65
        echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "txn_id", [], "any", true, true, false, 65)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "txn_id", [], "any", false, false, false, 65), "-")) : ("-")), "html", null, true);
        echo "</td>
                    </tr>

                    <tr>
                        <td>";
        // line 69
        echo gettext("Transaction status on payment gateway");
        echo "</td>
                        <td>";
        // line 70
        echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "txn_status", [], "any", false, false, false, 70)], 70, $context, $this->getSourceContext());
        echo "</td>
                    </tr>

                    <tr>
                        <td>";
        // line 74
        echo gettext("Status");
        echo "</td>
                        <td>";
        // line 75
        echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "status", [], "any", false, false, false, 75)], 75, $context, $this->getSourceContext());
        echo "</td>
                    </tr>

                    <tr>
                        <td>";
        // line 79
        echo gettext("IP");
        echo "</td>
                        <td>";
        // line 80
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "ip", [], "any", false, false, false, 80), "html", null, true);
        echo " ";
        echo twig_escape_filter($this->env, _twig_default_filter($this->extensions['Box_TwigExtensions']->twig_ipcountryname_filter(twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "ip", [], "any", false, false, false, 80)), "Unknown"), "html", null, true);
        echo "</td>
                    </tr>

                    ";
        // line 83
        if (twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "note", [], "any", false, false, false, 83)) {
            // line 84
            echo "                    <tr>
                        <td>";
            // line 85
            echo gettext("Note");
            echo "</td>
                        <td>";
            // line 86
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "note", [], "any", false, false, false, 86), "html", null, true);
            echo "</td>
                    </tr>
                    ";
        }
        // line 89
        echo "
                    <tr>
                        <td>";
        // line 91
        echo gettext("Received at");
        echo "</td>
                        <td>";
        // line 92
        echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "created_at", [], "any", false, false, false, 92), "l, d F Y"), "html", null, true);
        echo "</td>
                    </tr>

                    ";
        // line 95
        if ((twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "created_at", [], "any", false, false, false, 95) != twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "updated_at", [], "any", false, false, false, 95))) {
            // line 96
            echo "                    <tr>
                        <td>";
            // line 97
            echo gettext("Updated at");
            echo "</td>
                        <td>";
            // line 98
            echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "updated_at", [], "any", false, false, false, 98), "l, d F Y"), "html", null, true);
            echo "</td>
                    </tr>
                    ";
        }
        // line 101
        echo "                 </tbody>

                 <tfoot>
                     <tr>
                         <td colspan=\"2\">
                            <div class=\"aligncenter\">
                                <a class=\"btn55 mr10 api-link\" href=\"";
        // line 107
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/invoice/transaction_process", ["id" => twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "id", [], "any", false, false, false, 107)]);
        echo "\" data-api-reload=\"1\"><img src=\"images/icons/middlenav/refresh4.png\" alt=\"\"><span>";
        echo gettext("Process");
        echo "</span></a>
                                <a class=\"btn55 mr10 api-link\" href=\"";
        // line 108
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/invoice/transaction_delete", ["id" => twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "id", [], "any", false, false, false, 108)]);
        echo "\" data-api-confirm=\"Are you sure?\" data-api-redirect=\"";
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("invoice/transactions");
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
        // line 118
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/invoice/transaction_update");
        echo "\" class=\"mainForm save api-form\" data-api-reload=\"Transaction updated\">
                <fieldset>
                    <legend>Transaction payment information</legend>
                    <div class=\"rowElem\">
                        <label>";
        // line 122
        echo gettext("Invoice ID");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"invoice_id\" value=\"";
        // line 124
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "invoice_id", [], "any", false, false, false, 124), "html", null, true);
        echo "\" placeholder=\"\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 129
        echo gettext("Amount");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"amount\" value=\"";
        // line 131
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "amount", [], "any", false, false, false, 131), "html", null, true);
        echo "\" placeholder=\"\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 136
        echo gettext("Currency");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"currency\" value=\"";
        // line 138
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "currency", [], "any", false, false, false, 138), "html", null, true);
        echo "\" placeholder=\"\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>";
        // line 145
        echo gettext("Transaction processing information");
        echo "</legend>
                    <div class=\"rowElem\">
                        <label>";
        // line 147
        echo gettext("Payment Gateway");
        echo ":</label>
                        <div class=\"formRight\">
                            ";
        // line 149
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["gateway_id", twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "invoice_gateway_get_pairs", [], "any", false, false, false, 149), twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "gateway_id", [], "any", false, false, false, 149), 0, "Select payment gateway"], 149, $context, $this->getSourceContext());
        echo "
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 154
        echo gettext("Validate IPN");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"radio\" name=\"validate_ipn\" value=\"1\"";
        // line 156
        if (twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "validate_ipn", [], "any", false, false, false, 156)) {
            echo " checked=\"checked\"";
        }
        echo "/><label>Yes</label>
                            <input type=\"radio\" name=\"validate_ipn\" value=\"0\"";
        // line 157
        if ( !twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "validate_ipn", [], "any", false, false, false, 157)) {
            echo " checked=\"checked\"";
        }
        echo " /><label>No</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>";
        // line 164
        echo gettext("Transaction information on payment gateway");
        echo "</legend>
                    <div class=\"rowElem\">
                        <label>";
        // line 166
        echo gettext("Transaction type");
        echo ":</label>
                        <div class=\"formRight\">
                            ";
        // line 168
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["type", twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "invoice_transaction_types", [], "any", false, false, false, 168), twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "type", [], "any", false, false, false, 168), 0, "Select transaction type"], 168, $context, $this->getSourceContext());
        echo "
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 173
        echo gettext("Transaction status on Payment Gateway");
        echo ":</label>
                        <div class=\"formRight\">
                            ";
        // line 175
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["txn_status", twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "invoice_transaction_gateway_statuses", [], "any", false, false, false, 175), twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "txn_status", [], "any", false, false, false, 175), 0, "Select status"], 175, $context, $this->getSourceContext());
        echo "
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 180
        echo gettext("Transaction ID on Payment Gateway");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"txn_id\" value=\"";
        // line 182
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "txn_id", [], "any", false, false, false, 182), "html", null, true);
        echo "\" placeholder=\"\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <input type=\"submit\" value=\"";
        // line 186
        echo gettext("Update");
        echo "\" class=\"greyishBtn submitForm\" />
                    <input type=\"hidden\" name=\"id\" value=\"";
        // line 187
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "id", [], "any", false, false, false, 187), "html", null, true);
        echo "\"/>
                </fieldset>
            </form>
        </div>

        <div id=\"tab-note\" class=\"tab_content nopadding\">
            <div class=\"help\">
                <h3>";
        // line 194
        echo gettext("Note about this transaction");
        echo "</h3>
            </div>
            <form method=\"post\" action=\"";
        // line 196
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/invoice/transaction_update");
        echo "\" class=\"mainForm save api-form\" data-api-msg=\"Transaction note updated\">
                <fieldset>
                    <div class=\"rowElem\">
                        <div class=\"formBottom\">
                            <textarea name=\"note\" cols=\"\" rows=\"10\">";
        // line 200
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "note", [], "any", false, false, false, 200), "html", null, true);
        echo "</textarea>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                </fieldset>

                <fieldset>
                    <input type=\"submit\" value=\"";
        // line 207
        echo gettext("Update");
        echo "\" class=\"greyishBtn submitForm\" />
                    <input type=\"hidden\" name=\"id\" value=\"";
        // line 208
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "id", [], "any", false, false, false, 208), "html", null, true);
        echo "\"/>
                </fieldset>
            </form>
        </div>

        <div id=\"tab-ipn\" class=\"tab_content nopadding\">


            <div class=\"help\">
                <h3>";
        // line 217
        echo gettext("GET");
        echo "</h3>
            </div>
            ";
        // line 219
        if (twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "ipn", [], "any", false, false, false, 219), "get", [], "any", false, false, false, 219)) {
            // line 220
            echo "            <table class=\"tableStatic wide\">
                <tbody>
                    ";
            // line 222
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "ipn", [], "any", false, false, false, 222), "get", [], "any", false, false, false, 222));
            $context['loop'] = [
              'parent' => $context['_parent'],
              'index0' => 0,
              'index'  => 1,
              'first'  => true,
            ];
            if (is_array($context['_seq']) || (is_object($context['_seq']) && $context['_seq'] instanceof \Countable)) {
                $length = count($context['_seq']);
                $context['loop']['revindex0'] = $length - 1;
                $context['loop']['revindex'] = $length;
                $context['loop']['length'] = $length;
                $context['loop']['last'] = 1 === $length;
            }
            foreach ($context['_seq'] as $context["key"] => $context["val"]) {
                // line 223
                echo "                    <tr ";
                if (twig_get_attribute($this->env, $this->source, $context["loop"], "first", [], "any", false, false, false, 223)) {
                    echo "class=\"noborder\"";
                }
                echo ">
                        <td width=\"30%\">";
                // line 224
                echo twig_escape_filter($this->env, $context["key"], "html", null, true);
                echo "</td>
                        <td>";
                // line 225
                echo twig_escape_filter($this->env, $context["val"], "html", null, true);
                echo "</td>
                    </tr>
                    ";
                ++$context['loop']['index0'];
                ++$context['loop']['index'];
                $context['loop']['first'] = false;
                if (isset($context['loop']['length'])) {
                    --$context['loop']['revindex0'];
                    --$context['loop']['revindex'];
                    $context['loop']['last'] = 0 === $context['loop']['revindex0'];
                }
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['key'], $context['val'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 228
            echo "                </tbody>
            </table>
            ";
        } else {
            // line 231
            echo "            <div class=\"body\">
                <p>";
            // line 232
            echo gettext("No GET parameters received");
            echo "</p>
            </div>
            ";
        }
        // line 235
        echo "

            <div class=\"help\">
                <h3>";
        // line 238
        echo gettext("POST");
        echo "</h3>
            </div>
            ";
        // line 240
        if (twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "ipn", [], "any", false, false, false, 240), "post", [], "any", false, false, false, 240)) {
            // line 241
            echo "            <table class=\"tableStatic wide\">
                <tbody>
                    ";
            // line 243
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "ipn", [], "any", false, false, false, 243), "post", [], "any", false, false, false, 243));
            $context['loop'] = [
              'parent' => $context['_parent'],
              'index0' => 0,
              'index'  => 1,
              'first'  => true,
            ];
            if (is_array($context['_seq']) || (is_object($context['_seq']) && $context['_seq'] instanceof \Countable)) {
                $length = count($context['_seq']);
                $context['loop']['revindex0'] = $length - 1;
                $context['loop']['revindex'] = $length;
                $context['loop']['length'] = $length;
                $context['loop']['last'] = 1 === $length;
            }
            foreach ($context['_seq'] as $context["key"] => $context["val"]) {
                // line 244
                echo "                    <tr ";
                if (twig_get_attribute($this->env, $this->source, $context["loop"], "first", [], "any", false, false, false, 244)) {
                    echo "class=\"noborder\"";
                }
                echo ">
                        <td width=\"30%\">";
                // line 245
                echo twig_escape_filter($this->env, $context["key"], "html", null, true);
                echo "</td>
                        <td>";
                // line 246
                echo twig_escape_filter($this->env, $context["val"], "html", null, true);
                echo "</td>
                    </tr>
                    ";
                ++$context['loop']['index0'];
                ++$context['loop']['index'];
                $context['loop']['first'] = false;
                if (isset($context['loop']['length'])) {
                    --$context['loop']['revindex0'];
                    --$context['loop']['revindex'];
                    $context['loop']['last'] = 0 === $context['loop']['revindex0'];
                }
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['key'], $context['val'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 249
            echo "                </tbody>
            </table>
            ";
        } else {
            // line 252
            echo "            <div class=\"body\">
                <p>";
            // line 253
            echo gettext("No POST parameters received");
            echo "</p>
            </div>
            ";
        }
        // line 256
        echo "
            <div class=\"help\">
                <h3>";
        // line 258
        echo gettext("SERVER");
        echo "</h3>
            </div>
            ";
        // line 260
        if (twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "ipn", [], "any", false, false, false, 260), "server", [], "any", false, false, false, 260)) {
            // line 261
            echo "            <table class=\"tableStatic wide\">
                <tbody>
                    ";
            // line 263
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "ipn", [], "any", false, false, false, 263), "server", [], "any", false, false, false, 263));
            $context['loop'] = [
              'parent' => $context['_parent'],
              'index0' => 0,
              'index'  => 1,
              'first'  => true,
            ];
            if (is_array($context['_seq']) || (is_object($context['_seq']) && $context['_seq'] instanceof \Countable)) {
                $length = count($context['_seq']);
                $context['loop']['revindex0'] = $length - 1;
                $context['loop']['revindex'] = $length;
                $context['loop']['length'] = $length;
                $context['loop']['last'] = 1 === $length;
            }
            foreach ($context['_seq'] as $context["key"] => $context["val"]) {
                // line 264
                echo "                    <tr ";
                if (twig_get_attribute($this->env, $this->source, $context["loop"], "first", [], "any", false, false, false, 264)) {
                    echo "class=\"noborder\"";
                }
                echo ">
                        <td width=\"30%\">";
                // line 265
                echo twig_escape_filter($this->env, $context["key"], "html", null, true);
                echo "</td>
                        <td>";
                // line 266
                echo twig_escape_filter($this->env, $context["val"], "html", null, true);
                echo "</td>
                    </tr>
                    ";
                ++$context['loop']['index0'];
                ++$context['loop']['index'];
                $context['loop']['first'] = false;
                if (isset($context['loop']['length'])) {
                    --$context['loop']['revindex0'];
                    --$context['loop']['revindex'];
                    $context['loop']['last'] = 0 === $context['loop']['revindex0'];
                }
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['key'], $context['val'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 269
            echo "                </tbody>
            </table>
            ";
        } else {
            // line 272
            echo "            <div class=\"body\">
                <p>";
            // line 273
            echo gettext("No SERVER parameters logged");
            echo "</p>
            </div>
            ";
        }
        // line 276
        echo "

            <div class=\"help\">
                <h3>";
        // line 279
        echo twig_escape_filter($this->env, twig_upper_filter($this->env, "http_raw_post_data"), "html", null, true);
        echo "</h3>
            </div>
            ";
        // line 281
        if (twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "ipn", [], "any", false, false, false, 281), "http_raw_post_data", [], "any", false, false, false, 281)) {
            // line 282
            echo "            <div class=\"body\">
                ";
            // line 283
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["transaction"] ?? null), "ipn", [], "any", false, false, false, 283), "http_raw_post_data", [], "any", false, false, false, 283), "html", null, true);
            echo "
            </div>
            ";
        } else {
            // line 286
            echo "            <div class=\"body\">
                <p>No ";
            // line 287
            echo twig_escape_filter($this->env, twig_upper_filter($this->env, "http_raw_post_data"), "html", null, true);
            echo " parameters received</p>
            </div>
            ";
        }
        // line 290
        echo "            <div class=\"fix\"></div>
        </div>
    </div>

    <div class=\"fix\"></div>
</div>

";
    }

    public function getTemplateName()
    {
        return "mod_invoice_transaction.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  758 => 290,  752 => 287,  749 => 286,  743 => 283,  740 => 282,  738 => 281,  733 => 279,  728 => 276,  722 => 273,  719 => 272,  714 => 269,  697 => 266,  693 => 265,  686 => 264,  669 => 263,  665 => 261,  663 => 260,  658 => 258,  654 => 256,  648 => 253,  645 => 252,  640 => 249,  623 => 246,  619 => 245,  612 => 244,  595 => 243,  591 => 241,  589 => 240,  584 => 238,  579 => 235,  573 => 232,  570 => 231,  565 => 228,  548 => 225,  544 => 224,  537 => 223,  520 => 222,  516 => 220,  514 => 219,  509 => 217,  497 => 208,  493 => 207,  483 => 200,  476 => 196,  471 => 194,  461 => 187,  457 => 186,  450 => 182,  445 => 180,  437 => 175,  432 => 173,  424 => 168,  419 => 166,  414 => 164,  402 => 157,  396 => 156,  391 => 154,  383 => 149,  378 => 147,  373 => 145,  363 => 138,  358 => 136,  350 => 131,  345 => 129,  337 => 124,  332 => 122,  325 => 118,  308 => 108,  302 => 107,  294 => 101,  288 => 98,  284 => 97,  281 => 96,  279 => 95,  273 => 92,  269 => 91,  265 => 89,  259 => 86,  255 => 85,  252 => 84,  250 => 83,  242 => 80,  238 => 79,  231 => 75,  227 => 74,  220 => 70,  216 => 69,  209 => 65,  205 => 64,  198 => 60,  194 => 59,  185 => 55,  181 => 54,  170 => 50,  166 => 49,  159 => 45,  155 => 44,  149 => 43,  143 => 39,  137 => 36,  131 => 35,  128 => 34,  126 => 33,  118 => 30,  108 => 23,  104 => 22,  100 => 21,  96 => 20,  90 => 16,  86 => 15,  79 => 11,  73 => 10,  67 => 9,  64 => 8,  60 => 7,  52 => 4,  48 => 1,  46 => 3,  44 => 2,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}
{% import \"macro_functions.phtml\" as mf %}
{% set active_menu = 'invoice' %}
{% block meta_title %}Transaction {{ transaction.txn_id }}{% endblock %}


{% block breadcrumbs %}
<ul>
    <li class=\"firstB\"><a href=\"{{ '/'|alink }}\">{% trans 'Home' %}</a></li>
    <li><a href=\"{{ 'invoice/transactions'|alink }}\">{% trans 'Transactions' %}</a></li>
    <li class=\"lastB\">#{{transaction.id}}</li>
</ul>
{% endblock %}

{% block content %}

<div class=\"widget simpleTabs\">

    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">{% trans 'Transaction' %}</a></li>
        <li><a href=\"#tab-manage\">{% trans 'Manage' %}</a></li>
        <li><a href=\"#tab-ipn\">{% trans 'IPN' %}</a></li>
        <li><a href=\"#tab-note\">{% trans 'Notes' %}</a></li>
    </ul>

    <div class=\"tab_container\">

        <div class=\"tab_content nopadding\" id=\"tab-index\">
            <div class=\"help\">
                <h3>{% trans 'Transaction details' %} #{{transaction.id}}</h3>
            </div>

            {% if transaction.error %}
            <div class=\"body\">
                <strong class=\"red\">{{ transaction.error_code }} - {{ transaction.error }}</strong>
                <p>{% trans 'If you are sure that this transaction is valid you can update transaction details in &quot;Manage&quot; tab and try processing transaction again' %}</p>
            </div>
            {% endif%}


            <table class=\"tableStatic wide\">
                <tbody>
                    <tr {% if not transaction.error %}class=\"noborder\"{% endif %}>
                        <td style=\"width: 35%\">{% trans 'ID' %}</td>
                        <td>{{transaction.id}}</td>
                    </tr>

                    <tr>
                        <td>{% trans 'Invoice Id' %}</td>
                        <td><a href=\"{{ 'invoice/manage'|alink }}/{{transaction.invoice_id}}\">{{transaction.invoice_id|default('-')}}</a></td>
                    </tr>

                    <tr>
                        <td>{% trans 'Amount' %}</td>
                        <td>{{transaction.amount}} {{transaction.currency}}</td>
                    </tr>

                    <tr>
                        <td>{% trans 'Payment gateway' %}</td>
                        <td>{{transaction.gateway|default('-')}}</td>
                    </tr>

                    <tr>
                        <td>{% trans 'Transaction ID on payment gateway' %}</td>
                        <td>{{transaction.txn_id|default('-')}}</td>
                    </tr>

                    <tr>
                        <td>{% trans 'Transaction status on payment gateway' %}</td>
                        <td>{{ mf.status_name(transaction.txn_status) }}</td>
                    </tr>

                    <tr>
                        <td>{% trans 'Status' %}</td>
                        <td>{{ mf.status_name(transaction.status) }}</td>
                    </tr>

                    <tr>
                        <td>{% trans 'IP' %}</td>
                        <td>{{ transaction.ip }} {{ transaction.ip|ipcountryname|default('Unknown') }}</td>
                    </tr>

                    {% if transaction.note %}
                    <tr>
                        <td>{% trans 'Note' %}</td>
                        <td>{{ transaction.note }}</td>
                    </tr>
                    {% endif %}

                    <tr>
                        <td>{% trans 'Received at' %}</td>
                        <td>{{transaction.created_at|date('l, d F Y')}}</td>
                    </tr>

                    {% if transaction.created_at != transaction.updated_at %}
                    <tr>
                        <td>{% trans 'Updated at' %}</td>
                        <td>{{transaction.updated_at|date('l, d F Y')}}</td>
                    </tr>
                    {% endif %}
                 </tbody>

                 <tfoot>
                     <tr>
                         <td colspan=\"2\">
                            <div class=\"aligncenter\">
                                <a class=\"btn55 mr10 api-link\" href=\"{{ 'api/admin/invoice/transaction_process'|link({'id' : transaction.id}) }}\" data-api-reload=\"1\"><img src=\"images/icons/middlenav/refresh4.png\" alt=\"\"><span>{% trans 'Process' %}</span></a>
                                <a class=\"btn55 mr10 api-link\" href=\"{{ 'api/admin/invoice/transaction_delete'|link({'id' : transaction.id}) }}\" data-api-confirm=\"Are you sure?\" data-api-redirect=\"{{ 'invoice/transactions'|alink }}\"><img src=\"images/icons/middlenav/trash.png\" alt=\"\"><span>{% trans 'Delete' %}</span></a>
                            </div>
                         </td>
                     </tr>
                 </tfoot>
            </table>

        </div>

        <div id=\"tab-manage\" class=\"tab_content nopadding\">
            <form method=\"post\" action=\"{{ 'api/admin/invoice/transaction_update'|link }}\" class=\"mainForm save api-form\" data-api-reload=\"Transaction updated\">
                <fieldset>
                    <legend>Transaction payment information</legend>
                    <div class=\"rowElem\">
                        <label>{% trans 'Invoice ID' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"invoice_id\" value=\"{{transaction.invoice_id}}\" placeholder=\"\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Amount' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"amount\" value=\"{{transaction.amount}}\" placeholder=\"\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Currency' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"currency\" value=\"{{transaction.currency}}\" placeholder=\"\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>{% trans 'Transaction processing information' %}</legend>
                    <div class=\"rowElem\">
                        <label>{% trans 'Payment Gateway' %}:</label>
                        <div class=\"formRight\">
                            {{ mf.selectbox('gateway_id', admin.invoice_gateway_get_pairs, transaction.gateway_id, 0, 'Select payment gateway') }}
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Validate IPN' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"radio\" name=\"validate_ipn\" value=\"1\"{% if transaction.validate_ipn  %} checked=\"checked\"{% endif %}/><label>Yes</label>
                            <input type=\"radio\" name=\"validate_ipn\" value=\"0\"{% if not transaction.validate_ipn %} checked=\"checked\"{% endif %} /><label>No</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>{% trans 'Transaction information on payment gateway' %}</legend>
                    <div class=\"rowElem\">
                        <label>{% trans 'Transaction type' %}:</label>
                        <div class=\"formRight\">
                            {{ mf.selectbox('type', admin.invoice_transaction_types, transaction.type, 0, 'Select transaction type') }}
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Transaction status on Payment Gateway' %}:</label>
                        <div class=\"formRight\">
                            {{ mf.selectbox('txn_status', admin.invoice_transaction_gateway_statuses, transaction.txn_status, 0, 'Select status') }}
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Transaction ID on Payment Gateway' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"txn_id\" value=\"{{transaction.txn_id}}\" placeholder=\"\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <input type=\"submit\" value=\"{% trans 'Update' %}\" class=\"greyishBtn submitForm\" />
                    <input type=\"hidden\" name=\"id\" value=\"{{ transaction.id }}\"/>
                </fieldset>
            </form>
        </div>

        <div id=\"tab-note\" class=\"tab_content nopadding\">
            <div class=\"help\">
                <h3>{% trans 'Note about this transaction' %}</h3>
            </div>
            <form method=\"post\" action=\"{{ 'api/admin/invoice/transaction_update'|link }}\" class=\"mainForm save api-form\" data-api-msg=\"Transaction note updated\">
                <fieldset>
                    <div class=\"rowElem\">
                        <div class=\"formBottom\">
                            <textarea name=\"note\" cols=\"\" rows=\"10\">{{ transaction.note }}</textarea>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                </fieldset>

                <fieldset>
                    <input type=\"submit\" value=\"{% trans 'Update' %}\" class=\"greyishBtn submitForm\" />
                    <input type=\"hidden\" name=\"id\" value=\"{{ transaction.id }}\"/>
                </fieldset>
            </form>
        </div>

        <div id=\"tab-ipn\" class=\"tab_content nopadding\">


            <div class=\"help\">
                <h3>{% trans 'GET' %}</h3>
            </div>
            {% if transaction.ipn.get %}
            <table class=\"tableStatic wide\">
                <tbody>
                    {% for key,val in transaction.ipn.get %}
                    <tr {% if loop.first %}class=\"noborder\"{% endif %}>
                        <td width=\"30%\">{{ key }}</td>
                        <td>{{ val }}</td>
                    </tr>
                    {% endfor %}
                </tbody>
            </table>
            {% else %}
            <div class=\"body\">
                <p>{% trans 'No GET parameters received' %}</p>
            </div>
            {% endif %}


            <div class=\"help\">
                <h3>{% trans 'POST' %}</h3>
            </div>
            {% if transaction.ipn.post %}
            <table class=\"tableStatic wide\">
                <tbody>
                    {% for key,val in transaction.ipn.post %}
                    <tr {% if loop.first %}class=\"noborder\"{% endif %}>
                        <td width=\"30%\">{{ key }}</td>
                        <td>{{ val }}</td>
                    </tr>
                    {% endfor %}
                </tbody>
            </table>
            {% else %}
            <div class=\"body\">
                <p>{% trans 'No POST parameters received' %}</p>
            </div>
            {% endif %}

            <div class=\"help\">
                <h3>{% trans 'SERVER' %}</h3>
            </div>
            {% if transaction.ipn.server %}
            <table class=\"tableStatic wide\">
                <tbody>
                    {% for key,val in transaction.ipn.server %}
                    <tr {% if loop.first %}class=\"noborder\"{% endif %}>
                        <td width=\"30%\">{{ key }}</td>
                        <td>{{ val }}</td>
                    </tr>
                    {% endfor %}
                </tbody>
            </table>
            {% else %}
            <div class=\"body\">
                <p>{% trans 'No SERVER parameters logged' %}</p>
            </div>
            {% endif %}


            <div class=\"help\">
                <h3>{{ 'http_raw_post_data'|upper}}</h3>
            </div>
            {% if transaction.ipn.http_raw_post_data %}
            <div class=\"body\">
                {{transaction.ipn.http_raw_post_data }}
            </div>
            {% else %}
            <div class=\"body\">
                <p>No {{ 'http_raw_post_data'|upper}} parameters received</p>
            </div>
            {% endif %}
            <div class=\"fix\"></div>
        </div>
    </div>

    <div class=\"fix\"></div>
</div>

{% endblock %}
", "mod_invoice_transaction.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-themes/admin_default/html/mod_invoice_transaction.phtml");
    }
}
