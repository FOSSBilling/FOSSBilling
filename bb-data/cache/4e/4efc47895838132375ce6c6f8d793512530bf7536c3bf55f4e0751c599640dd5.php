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

/* mod_invoice_invoice.phtml */
class __TwigTemplate_fbf289d793dec2bde76a4634963ed632d65c53dc40ab978b36fd248b9b298ae9 extends \Twig\Template
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
            'sidebar2' => [$this, 'block_sidebar2'],
            'head' => [$this, 'block_head'],
            'js' => [$this, 'block_js'],
        ];
    }

    protected function doGetParent(array $context)
    {
        // line 1
        return $this->loadTemplate(((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ajax", [], "any", false, false, false, 1)) ? ("layout_blank.phtml") : ("layout_default.phtml")), "mod_invoice_invoice.phtml", 1);
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 2
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_invoice_invoice.phtml", 2)->unwrap();
        // line 4
        $context["active_menu"] = "invoice";
        // line 1
        $this->getParent($context)->display($context, array_merge($this->blocks, $blocks));
    }

    // line 3
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo gettext("Invoice");
        echo " ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "serie", [], "any", false, false, false, 3), "html", null, true);
        echo twig_escape_filter($this->env, sprintf("%05s", twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "nr", [], "any", false, false, false, 3)), "html", null, true);
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
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("invoice");
        echo "\">";
        echo gettext("Invoices");
        echo "</a></li>
    <li class=\"lastB\">";
        // line 11
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "serie", [], "any", false, false, false, 11), "html", null, true);
        echo twig_escape_filter($this->env, sprintf("%05s", twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "nr", [], "any", false, false, false, 11)), "html", null, true);
        echo "</li>
</ul>
";
    }

    // line 16
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 17
        echo "
<div class=\"widget simpleTabs tabsRight\">
    <div class=\"head\">
        <h5>";
        // line 20
        echo gettext("Invoice");
        echo " #";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "id", [], "any", false, false, false, 20), "html", null, true);
        echo "</h5>
    </div>

    <ul class=\"tabs\">
        <li><a href=\"#tab-info\">Details</a></li>
        <li><a href=\"#tab-manage\">Manage</a></li>
        <li><a href=\"#tab-texts\">Texts</a></li>
        <li><a href=\"#tab-buyer-credentials\">Client credentials</a></li>
        <li><a href=\"#tab-seller-credentials\">Company credentials</a></li>
    </ul>

    <div class=\"tabs_container\">
        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-info\">
            <div class=\"block\">
                <table class=\"tableStatic wide\">
                    <tbody>
                    <tr class=\"noborder\">
                        <td style=\"width: 20%\"><label>";
        // line 38
        echo gettext("ID");
        echo "</label></td>
                        <td>";
        // line 39
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "id", [], "any", false, false, false, 39), "html", null, true);
        echo "</td>
                    </tr>
                    <tr>
                        <td><label>";
        // line 42
        echo gettext("Number");
        echo "</label></td>
                        <td>";
        // line 43
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "serie", [], "any", false, false, false, 43), "html", null, true);
        echo twig_escape_filter($this->env, sprintf("%05s", twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "nr", [], "any", false, false, false, 43)), "html", null, true);
        echo "</td>
                    </tr>
                    <tr>
                        <td><label>";
        // line 46
        echo gettext("Currency");
        echo "</label></td>
                        <td>";
        // line 47
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "currency", [], "any", false, false, false, 47), "html", null, true);
        echo "</td>
                    </tr>
                    <tr>
                        <td><label>";
        // line 50
        echo gettext("Client");
        echo "</label></td>
                        <td><a href=\"";
        // line 51
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("client/manage");
        echo "/";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "client", [], "any", false, false, false, 51), "id", [], "any", false, false, false, 51), "html", null, true);
        echo "\">";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "client", [], "any", false, false, false, 51), "first_name", [], "any", false, false, false, 51), "html", null, true);
        echo " ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "client", [], "any", false, false, false, 51), "last_name", [], "any", false, false, false, 51), "html", null, true);
        echo "</a></td>
                    </tr>

                    <tr>
                        <td><label>";
        // line 55
        echo gettext("Status");
        echo "</label></td>
                        <td>";
        // line 56
        echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "status", [], "any", false, false, false, 56)], 56, $context, $this->getSourceContext());
        echo "</td>
                    </tr>

                    ";
        // line 59
        if (twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "tax", [], "any", false, false, false, 59)) {
            // line 60
            echo "                    <tr>
                        <td><label>";
            // line 61
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "taxname", [], "any", false, false, false, 61), "html", null, true);
            echo " ";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "taxrate", [], "any", false, false, false, 61), "html", null, true);
            echo "%</label></td>
                        <td>";
            // line 62
            echo twig_call_macro($macros["mf"], "macro_currency_format", [twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "tax", [], "any", false, false, false, 62), twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "currency", [], "any", false, false, false, 62)], 62, $context, $this->getSourceContext());
            echo "</td>
                    </tr>
                    ";
        }
        // line 65
        echo "                    
                    <tr>
                        <td><label>";
        // line 67
        echo gettext("Total");
        echo "</label></td>
                        <td>";
        // line 68
        echo twig_call_macro($macros["mf"], "macro_currency_format", [twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "total", [], "any", false, false, false, 68), twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "currency", [], "any", false, false, false, 68)], 68, $context, $this->getSourceContext());
        echo "</td>
                    </tr>
                    
                    <tr>
                        <td><label>";
        // line 72
        echo gettext("Created at");
        echo "</label></td>
                        <td>";
        // line 73
        echo twig_escape_filter($this->env, $this->extensions['Box_TwigExtensions']->twig_bb_date(twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "created_at", [], "any", false, false, false, 73)), "html", null, true);
        echo "</td>
                    </tr>

                    <tr>
                        <td><label>";
        // line 77
        echo gettext("Due at");
        echo "</label></td>
                        <td>";
        // line 78
        echo twig_escape_filter($this->env, $this->extensions['Box_TwigExtensions']->twig_bb_date(twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "due_at", [], "any", false, false, false, 78)), "html", null, true);
        echo "</td>
                    </tr>

                    <tr>
                        <td><label>";
        // line 82
        echo gettext("Paid at");
        echo "</label></td>
                        <td>";
        // line 83
        if (twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "paid_at", [], "any", false, false, false, 83)) {
            echo twig_escape_filter($this->env, $this->extensions['Box_TwigExtensions']->twig_bb_date(twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "paid_at", [], "any", false, false, false, 83)), "html", null, true);
        } else {
            echo "-";
        }
        echo "</td>
                    </tr>

                    <tr>
                        <td><label>";
        // line 87
        echo gettext("Reminded at");
        echo "</label></td>
                        <td>";
        // line 88
        if (twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "reminded_at", [], "any", false, false, false, 88)) {
            echo twig_escape_filter($this->env, $this->extensions['Box_TwigExtensions']->twig_bb_date(twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "reminded_at", [], "any", false, false, false, 88)), "html", null, true);
            echo " (";
            echo twig_escape_filter($this->env, twig_timeago_filter(twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "reminded_at", [], "any", false, false, false, 88)), "html", null, true);
            echo " ago) ";
        }
        echo "</td>
                    </tr>
                    
                    ";
        // line 91
        if (twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "notes", [], "any", false, false, false, 91)) {
            // line 92
            echo "                    <tr>
                        <td><label>";
            // line 93
            echo gettext("Notes");
            echo "</label></td>
                        <td>";
            // line 94
            echo twig_bbmd_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "notes", [], "any", false, false, false, 94));
            echo "</td>
                    </tr>
                    ";
        }
        // line 97
        echo "                    </tbody>
                    
                    <tfoot>
                        <tr>
                            <td colspan=\"2\">
                                <div class=\"aligncenter\">
                                    <a href=\"";
        // line 103
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("invoice");
        echo "/";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "hash", [], "any", false, false, false, 103), "html", null, true);
        echo "\" title=\"\" class=\"btn55 mr10\" target=\"_blank\"><img src=\"images/icons/middlenav/preview.png\" alt=\"\"><span>View as client</span></a>
                                    <a href=\"";
        // line 104
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/invoice/delete", ["id" => twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "id", [], "any", false, false, false, 104)]);
        echo "\" data-api-confirm=\"Are you sure?\"  title=\"\" class=\"btn55 mr10 api-link\" data-api-redirect=\"";
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("invoice");
        echo "\"><img src=\"images/icons/middlenav/trash.png\" alt=\"\"><span>Delete</span></a>
                                    ";
        // line 105
        if ((twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "status", [], "any", false, false, false, 105) == "unpaid")) {
            // line 106
            echo "                                    <a href=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/invoice/send_reminder", ["id" => twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "id", [], "any", false, false, false, 106)]);
            echo "\" title=\"\" class=\"btn55 mr10 api-link\" data-api-msg=\"Payment reminder was sent\"><img src=\"images/icons/middlenav/mail.png\" alt=\"\"><span>Send reminder</span></a>
                                    <a href=\"";
            // line 107
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/invoice/mark_as_paid", ["id" => twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "id", [], "any", false, false, false, 107), "execute" => 1]);
            echo "\" title=\"\" class=\"btn55 mr10 api-link\" data-api-reload=\"Invoice marked as paid\"><img src=\"images/icons/middlenav/play2.png\" alt=\"\"><span>Mark as paid</span></a>
                                    ";
        }
        // line 109
        echo "                                    
                                    ";
        // line 110
        if ((twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "status", [], "any", false, false, false, 110) == "paid")) {
            // line 111
            echo "                                    <a href=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/invoice/refund", ["id" => twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "id", [], "any", false, false, false, 111)]);
            echo "\" data-api-confirm=\"Are you sure?\"  title=\"\" class=\"btn55 mr10 api-link\" data-api-redirect=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("invoice");
            echo "\"><img src=\"images/icons/middlenav/refresh4.png\" alt=\"\"><span>Refund</span></a>
                                    ";
        }
        // line 113
        echo "                                    <a href=\"";
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("invoice/pdf");
        echo "/";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "hash", [], "any", false, false, false, 113), "html", null, true);
        echo "\" target=\"_blank\" class=\"btn55 mr10\"><img src=\"images/icons/middlenav/incoming.png\" alt=\"\"><span>PDF</span></a>
                                </div>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

        </div>

        <div class=\"tab_content nopadding\" id=\"tab-manage\">
            <form action=\"";
        // line 124
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/invoice/update");
        echo "\" method=\"post\" class=\"mainForm api-form\" data-api-reload=\"Invoice updated\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>";
        // line 127
        echo gettext("Status");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"radio\" name=\"status\" value=\"paid\"";
        // line 129
        if ((twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "status", [], "any", false, false, false, 129) == "paid")) {
            echo " checked=\"checked\"";
        }
        echo "/><label>Paid</label>
                            <input type=\"radio\" name=\"status\" value=\"unpaid\"";
        // line 130
        if ((twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "status", [], "any", false, false, false, 130) == "unpaid")) {
            echo " checked=\"checked\"";
        }
        echo " /><label>Unpaid</label>
                            <input type=\"radio\" name=\"status\" value=\"refunded\"";
        // line 131
        if ((twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "status", [], "any", false, false, false, 131) == "refunded")) {
            echo " checked=\"checked\"";
        }
        echo " /><label>Refunded</label>
                            <input type=\"radio\" name=\"status\" value=\"canceled\"";
        // line 132
        if ((twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "status", [], "any", false, false, false, 132) == "canceled")) {
            echo " checked=\"checked\"";
        }
        echo " /><label>Canceled</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 138
        echo gettext("Approved");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"radio\" name=\"approved\" value=\"1\"";
        // line 140
        if (twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "approved", [], "any", false, false, false, 140)) {
            echo " checked=\"checked\"";
        }
        echo "/><label>Yes</label>
                            <input type=\"radio\" name=\"approved\" value=\"0\"";
        // line 141
        if ( !twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "approved", [], "any", false, false, false, 141)) {
            echo " checked=\"checked\"";
        }
        echo " /><label>No</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 147
        echo gettext("Serie and number");
        echo ":</label>
                        <div class=\"formRight moreFields\">
                            <ul>
                                <li style=\"width: 150px\">
                                    <input type=\"text\" name=\"serie\" value=\"";
        // line 151
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "serie", [], "any", false, false, false, 151), "html", null, true);
        echo "\"/>
                                </li>
                                <li class=\"sep\">&nbsp;</li>
                                <li style=\"width: 150px\">
                                    <input type=\"text\" name=\"nr\" value=\"";
        // line 155
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "nr", [], "any", false, false, false, 155), "html", null, true);
        echo "\"/>
                                </li>
                            </ul>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 163
        echo gettext("Tax");
        echo ":</label>
                        <div class=\"formRight moreFields\">
                            <ul>
                                <li style=\"width: 150px\">
                                    <input type=\"text\" name=\"taxname\" value=\"";
        // line 167
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "taxname", [], "any", false, false, false, 167), "html", null, true);
        echo "\"/>
                                </li>
                                <li class=\"sep\">&nbsp;</li>
                                <li style=\"width: 40px\">
                                    <input type=\"text\" name=\"taxrate\" value=\"";
        // line 171
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "taxrate", [], "any", false, false, false, 171), "html", null, true);
        echo "\"/>
                                </li>
                                <li class=\"sep\">%</li>
                            </ul>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 179
        echo gettext("Payment method");
        echo ":</label>
                        <div class=\"formRight\">
                            ";
        // line 181
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["gateway_id", twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "invoice_gateways", [0 => ["format" => "pairs"]], "method", false, false, false, 181), twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "gateway_id", [], "any", false, false, false, 181), 0, "Select payment method"], 181, $context, $this->getSourceContext());
        echo "
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 187
        echo gettext("Created at");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"created_at\" value=\"";
        // line 189
        echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "created_at", [], "any", false, false, false, 189), "Y-m-d"), "html", null, true);
        echo "\" class=\"datepicker\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 195
        echo gettext("Due at");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"due_at\" value=\"";
        // line 197
        if (twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "due_at", [], "any", false, false, false, 197)) {
            echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "due_at", [], "any", false, false, false, 197), "Y-m-d"), "html", null, true);
        }
        echo "\" class=\"datepicker\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 203
        echo gettext("Paid at");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"paid_at\" value=\"";
        // line 205
        if (twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "paid_at", [], "any", false, false, false, 205)) {
            echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "paid_at", [], "any", false, false, false, 205), "Y-m-d"), "html", null, true);
        }
        echo "\" class=\"datepicker\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 211
        echo gettext("Notes");
        echo ":</label>
                        <div class=\"formRight\">
                            <textarea name=\"notes\" cols=\"5\" rows=\"6\">";
        // line 213
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "notes", [], "any", false, false, false, 213), "html", null, true);
        echo "</textarea>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <input type=\"submit\" value=\"";
        // line 218
        echo gettext("Update");
        echo "\" class=\"greyishBtn submitForm\" />
                    <input type=\"hidden\" name=\"id\" value=\"";
        // line 219
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "id", [], "any", false, false, false, 219), "html", null, true);
        echo "\" />
                </fieldset>
            </form>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-texts\">
            <form action=\"";
        // line 225
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/invoice/update");
        echo "\" method=\"post\" class=\"mainForm api-form\" data-api-msg=\"Invoice updated\">
                <fieldset>
                    <label class=\"topLabel\">";
        // line 227
        echo gettext("Text before invoice items table");
        echo "</label>
                    <textarea name=\"text_1\"  cols=\"5\" rows=\"6\" class=\"bb-textarea\"/>";
        // line 228
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "text_1", [], "any", false, false, false, 228), "html", null, true);
        echo "</textarea>
                </fieldset>
                
                <fieldset>    
                    <label class=\"topLabel\">";
        // line 232
        echo gettext("Text after invoice items table");
        echo "</label>
                    <textarea name=\"text_2\" cols=\"5\" rows=\"6\" class=\"bb-textarea\"/>";
        // line 233
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "text_2", [], "any", false, false, false, 233), "html", null, true);
        echo "</textarea>
                    <input type=\"submit\" value=\"";
        // line 234
        echo gettext("Update");
        echo "\" class=\"greyishBtn submitForm\" />
                    <input type=\"hidden\" name=\"id\" value=\"";
        // line 235
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "id", [], "any", false, false, false, 235), "html", null, true);
        echo "\" />
                </fieldset>
            </form>

            <div class=\"fix\"></div>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-seller-credentials\">
            ";
        // line 243
        $context["seller"] = twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "seller", [], "any", false, false, false, 243);
        // line 244
        echo "            <form action=\"";
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/invoice/update");
        echo "\" method=\"post\" class=\"mainForm api-form\" data-api-msg=\"Invoice updated\">
                <fieldset>
                    <legend>Company details at the moment of purchase</legend>

                    <div class=\"rowElem noborder\">
                        <label>";
        // line 249
        echo gettext("Company");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"seller_company\" value=\"";
        // line 251
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["seller"] ?? null), "company", [], "any", false, false, false, 251), "html", null, true);
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <div class=\"rowElem\">
                        <label>";
        // line 257
        echo gettext("Company VAT");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"seller_company_vat\" value=\"";
        // line 259
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["seller"] ?? null), "company_vat", [], "any", false, false, false, 259), "html", null, true);
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <div class=\"rowElem\">
                        <label>";
        // line 265
        echo gettext("Company Number");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"seller_company_number\" value=\"";
        // line 267
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["seller"] ?? null), "company_number", [], "any", false, false, false, 267), "html", null, true);
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 273
        echo gettext("Address");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"seller_address\" value=\"";
        // line 275
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["seller"] ?? null), "address", [], "any", false, false, false, 275), "html", null, true);
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 281
        echo gettext("Phone");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"seller_phone\" value=\"";
        // line 283
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["seller"] ?? null), "phone", [], "any", false, false, false, 283), "html", null, true);
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 289
        echo gettext("Email");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"seller_email\" value=\"";
        // line 291
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["seller"] ?? null), "email", [], "any", false, false, false, 291), "html", null, true);
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <input type=\"submit\" value=\"";
        // line 296
        echo gettext("Update");
        echo "\" class=\"greyishBtn submitForm\" />
                    <input type=\"hidden\" name=\"id\" value=\"";
        // line 297
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "id", [], "any", false, false, false, 297), "html", null, true);
        echo "\" />
                </fieldset>
            </form>

            <div class=\"fix\"></div>
        </div>


        <div class=\"tab_content nopadding\" id=\"tab-buyer-credentials\">
            ";
        // line 306
        $context["buyer"] = twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "buyer", [], "any", false, false, false, 306);
        // line 307
        echo "            <form action=\"";
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/invoice/update");
        echo "\" method=\"post\" class=\"mainForm api-form\" data-api-msg=\"Invoice updated\">
                <fieldset>
                    <legend>Client details at the moment of purchase</legend>

                    <div class=\"rowElem noborder\">
                        <label>";
        // line 312
        echo gettext("First name");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"buyer_first_name\" value=\"";
        // line 314
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["buyer"] ?? null), "first_name", [], "any", false, false, false, 314), "html", null, true);
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 320
        echo gettext("Last name");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"buyer_last_name\" value=\"";
        // line 322
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["buyer"] ?? null), "last_name", [], "any", false, false, false, 322), "html", null, true);
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 328
        echo gettext("Company");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"buyer_company\" value=\"";
        // line 330
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["buyer"] ?? null), "company", [], "any", false, false, false, 330), "html", null, true);
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <div class=\"rowElem\">
                        <label>";
        // line 336
        echo gettext("Company VAT");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"buyer_company_vat\" value=\"";
        // line 338
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["buyer"] ?? null), "company_vat", [], "any", false, false, false, 338), "html", null, true);
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <div class=\"rowElem\">
                        <label>";
        // line 344
        echo gettext("Company Number");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"buyer_company_number\" value=\"";
        // line 346
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["buyer"] ?? null), "company_number", [], "any", false, false, false, 346), "html", null, true);
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 352
        echo gettext("Address");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"buyer_address\" value=\"";
        // line 354
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["buyer"] ?? null), "address", [], "any", false, false, false, 354), "html", null, true);
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 360
        echo gettext("City");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"buyer_city\" value=\"";
        // line 362
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["buyer"] ?? null), "city", [], "any", false, false, false, 362), "html", null, true);
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 368
        echo gettext("State");
        echo ":</label>
                        <div class=\"formRight\">
                            ";
        // line 371
        echo "                            <input type=\"text\" name=\"buyer_state\" value=\"";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["buyer"] ?? null), "state", [], "any", false, false, false, 371), "html", null, true);
        echo "\" />
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 377
        echo gettext("Country");
        echo ":</label>
                        <div class=\"formRight\">
                            ";
        // line 379
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["buyer_country", twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_countries", [], "any", false, false, false, 379), twig_get_attribute($this->env, $this->source, ($context["buyer"] ?? null), "country", [], "any", false, false, false, 379), 0, "Select country"], 379, $context, $this->getSourceContext());
        echo "
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 385
        echo gettext("Phone");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"buyer_phone\" value=\"";
        // line 387
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["buyer"] ?? null), "phone", [], "any", false, false, false, 387), "html", null, true);
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 393
        echo gettext("Zip");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"buyer_zip\" value=\"";
        // line 395
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["buyer"] ?? null), "zip", [], "any", false, false, false, 395), "html", null, true);
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 401
        echo gettext("Email");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"buyer_email\" value=\"";
        // line 403
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["buyer"] ?? null), "email", [], "any", false, false, false, 403), "html", null, true);
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <input type=\"submit\" value=\"";
        // line 408
        echo gettext("Update");
        echo "\" class=\"greyishBtn submitForm\" />
                    <input type=\"hidden\" name=\"id\" value=\"";
        // line 409
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "id", [], "any", false, false, false, 409), "html", null, true);
        echo "\" />
                </fieldset>
            </form>

            <div class=\"fix\"></div>
        </div>

    </div>
</div>


<div class=\"widget\">
    <div class=\"head\">
        <h5>";
        // line 422
        echo gettext("Invoice items");
        echo "</h5>
    </div>

";
        // line 425
        if ( !twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "approved", [], "any", false, false, false, 425)) {
            // line 426
            echo "
    <form action=\"";
            // line 427
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/invoice/update");
            echo "\" method=\"post\" class=\"mainForm api-form\" data-api-reload=\"1\">
        <fieldset>
        <table class=\"tableStatic wide\">
            <thead>
                <tr>
                    <th>";
            // line 432
            echo gettext("Title");
            echo "</th>
                    <th class=\"currency\">";
            // line 433
            echo gettext("Price");
            echo "</th>
                    <th>";
            // line 434
            echo gettext("Tax");
            echo "</th>
                    <th class=\"actions\">&nbsp;</th>
                </tr>
            </thead>

            <tbody>
                ";
            // line 440
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "lines", [], "any", false, false, false, 440));
            foreach ($context['_seq'] as $context["i"] => $context["item"]) {
                // line 441
                echo "                <tr>
                    <td style=\"width: 60%;\"><input type=\"text\" name=\"items[";
                // line 442
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["item"], "id", [], "any", false, false, false, 442), "html", null, true);
                echo "][title]\" value=\"";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["item"], "title", [], "any", false, false, false, 442), "html", null, true);
                echo "\" style=\"width:98%\"></td>
                    <td style=\"width: 20%;\"><input type=\"text\" name=\"items[";
                // line 443
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["item"], "id", [], "any", false, false, false, 443), "html", null, true);
                echo "][price]\" value=\"";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["item"], "price", [], "any", false, false, false, 443), "html", null, true);
                echo "\" style=\"width:80px\"> ";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "currency", [], "any", false, false, false, 443), "html", null, true);
                echo "</td>
                    <td style=\"width: 5%;\">
                        <input type=\"checkbox\" name=\"items[";
                // line 445
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["item"], "id", [], "any", false, false, false, 445), "html", null, true);
                echo "][taxed]\" value=\"1\" ";
                if (twig_get_attribute($this->env, $this->source, $context["item"], "taxed", [], "any", false, false, false, 445)) {
                    echo "checked=\"checked\"";
                }
                echo "/>
                    </td>
                    <td style=\"width: 5%;\">
                        <a class=\"bb-button btn14 api-link\" data-api-confirm=\"Are you sure?\" href=\"";
                // line 448
                echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/invoice/item_delete", ["id" => twig_get_attribute($this->env, $this->source, $context["item"], "id", [], "any", false, false, false, 448)]);
                echo "\" data-api-reload=\"1\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                    </td>
                </tr>
                ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['i'], $context['item'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 452
            echo "
                <tr>
                    <td style=\"width: 70%;\"><input type=\"text\" name=\"new_item[title]\" value=\"\" style=\"width:98%\" placeholder=\"New line description\"></td>
                    <td style=\"width: 20%;\"><input type=\"text\" name=\"new_item[price]\" value=\"\" style=\"width:80px\" placeholder=\"Price\"> ";
            // line 455
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "currency", [], "any", false, false, false, 455), "html", null, true);
            echo "</td>
                    <td><input type=\"checkbox\" name=\"new_item[taxed]\" value=\"1\"/></td>
                    <td>&nbsp;</td>
                </tr>
            </tbody>

            <tfoot>
                <tr>
                    <td colspan=\"4\" class=\"currency\">
                        Subtotal: ";
            // line 464
            echo twig_call_macro($macros["mf"], "macro_currency_format", [twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "subtotal", [], "any", false, false, false, 464), twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "currency", [], "any", false, false, false, 464)], 464, $context, $this->getSourceContext());
            echo "
                    </td>
                </tr>
                <tr>
                    <td colspan=\"4\" class=\"currency\">
                        ";
            // line 469
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "taxname", [], "any", false, false, false, 469), "html", null, true);
            echo " ";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "taxrate", [], "any", false, false, false, 469), "html", null, true);
            echo "%: ";
            echo twig_call_macro($macros["mf"], "macro_currency_format", [twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "tax", [], "any", false, false, false, 469), twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "currency", [], "any", false, false, false, 469)], 469, $context, $this->getSourceContext());
            echo "
                    </td>
                </tr>
                <tr>
                    <td colspan=\"4\" class=\"currency\">
                        Total: ";
            // line 474
            echo twig_call_macro($macros["mf"], "macro_currency_format", [twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "total", [], "any", false, false, false, 474), twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "currency", [], "any", false, false, false, 474)], 474, $context, $this->getSourceContext());
            echo "
                    </td>
                </tr>
                <tr>
                    <td colspan=\"4\">
                        ";
            // line 479
            if ( !twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "approved", [], "any", false, false, false, 479)) {
                // line 480
                echo "                        <a href=\"";
                echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/invoice/approve", ["id" => twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "id", [], "any", false, false, false, 480)]);
                echo "\"  title=\"\" class=\"btnIconLeft mr10 api-link\" data-api-reload=\"";
                echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("invoices");
                echo "\"><img src=\"images/icons/dark/check.png\" alt=\"\" class=\"icon\"><span>Approve</span></a>
                        ";
            }
            // line 482
            echo "                        <input type=\"submit\" value=\"";
            echo gettext("Update");
            echo "\" class=\"greyishBtn submitForm\" style=\"width:100px\"/>
                    </td>
                </tr>
            </tfoot>
        </table>
        </fieldset>

        <input type=\"hidden\" name=\"id\" value=\"";
            // line 489
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "id", [], "any", false, false, false, 489), "html", null, true);
            echo "\" />
    </form>

";
        } else {
            // line 493
            echo "    <table class=\"tableStatic wide\">
        <thead>
            <tr>
                <th  style=\"width: 11%;\" colspan=\"2\"></th>
                <th style=\"width: 70%;\">";
            // line 497
            echo gettext("Title");
            echo "</th>
                <th style=\"width: 7%;\">";
            // line 498
            echo gettext("Tax");
            echo "</th>
                <th class=\"currency\" style=\"width: 15%;\">";
            // line 499
            echo gettext("Total");
            echo "</th>
            </tr>
        </thead>

        <tbody>
            ";
            // line 504
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "lines", [], "any", false, false, false, 504));
            foreach ($context['_seq'] as $context["i"] => $context["item"]) {
                // line 505
                echo "
            <tr>
                <td>";
                // line 507
                echo twig_escape_filter($this->env, ($context["i"] + 1), "html", null, true);
                echo ".</td>
                <td>
                    <div class=\"bull task ";
                // line 509
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["item"], "status", [], "any", false, false, false, 509), "html", null, true);
                echo "\" title=\"Task status: ";
                echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, $context["item"], "status", [], "any", false, false, false, 509)], 509, $context, $this->getSourceContext());
                echo "\"></div>
                    <div class=\"bull charge ";
                // line 510
                echo ((twig_get_attribute($this->env, $this->source, $context["item"], "charged", [], "any", false, false, false, 510)) ? ("yes") : ("no"));
                echo "\" title=\"";
                echo ((twig_get_attribute($this->env, $this->source, $context["item"], "charged", [], "any", false, false, false, 510)) ? ("Charged from client balance") : ("Not charged from client balance"));
                echo "\"></div>
                </td>
                <td>
                ";
                // line 513
                if (twig_get_attribute($this->env, $this->source, $context["item"], "order_id", [], "any", false, false, false, 513)) {
                    // line 514
                    echo "                <a href=\"";
                    echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("order/manage");
                    echo "/";
                    echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["item"], "order_id", [], "any", false, false, false, 514), "html", null, true);
                    echo "\">";
                    echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["item"], "title", [], "any", false, false, false, 514), "html", null, true);
                    echo " ";
                    if ((twig_get_attribute($this->env, $this->source, $context["item"], "quantity", [], "any", false, false, false, 514) > 1)) {
                        echo " x ";
                        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["item"], "quantity", [], "any", false, false, false, 514), "html", null, true);
                        echo " ";
                    }
                    echo "</a>
                ";
                } else {
                    // line 516
                    echo "                ";
                    echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["item"], "title", [], "any", false, false, false, 516), "html", null, true);
                    echo " ";
                    if ((twig_get_attribute($this->env, $this->source, $context["item"], "quantity", [], "any", false, false, false, 516) > 1)) {
                        echo " x ";
                        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["item"], "quantity", [], "any", false, false, false, 516), "html", null, true);
                        echo " ";
                    }
                    // line 517
                    echo "                ";
                }
                // line 518
                echo "                </td>
                <td>";
                // line 519
                echo twig_call_macro($macros["mf"], "macro_currency_format", [twig_get_attribute($this->env, $this->source, $context["item"], "tax", [], "any", false, false, false, 519), twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "currency", [], "any", false, false, false, 519)], 519, $context, $this->getSourceContext());
                echo "</td>
                <td class=\"currency\">";
                // line 520
                echo twig_call_macro($macros["mf"], "macro_currency_format", [twig_get_attribute($this->env, $this->source, $context["item"], "total", [], "any", false, false, false, 520), twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "currency", [], "any", false, false, false, 520)], 520, $context, $this->getSourceContext());
                echo "</td>
            </tr>

            ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['i'], $context['item'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 524
            echo "        </tbody>
        
        <tfoot>
            <tr>
                <td colspan=\"5\" class=\"currency\">
                    Subtotal: ";
            // line 529
            echo twig_call_macro($macros["mf"], "macro_currency_format", [twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "subtotal", [], "any", false, false, false, 529), twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "currency", [], "any", false, false, false, 529)], 529, $context, $this->getSourceContext());
            echo "
                </td>
            </tr>
            <tr>
                <td colspan=\"5\" class=\"currency\">
                    ";
            // line 534
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "taxname", [], "any", false, false, false, 534), "html", null, true);
            echo " ";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "taxrate", [], "any", false, false, false, 534), "html", null, true);
            echo "%: ";
            echo twig_call_macro($macros["mf"], "macro_currency_format", [twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "tax", [], "any", false, false, false, 534), twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "currency", [], "any", false, false, false, 534)], 534, $context, $this->getSourceContext());
            echo "
                </td>
            </tr>
            <tr>
                <td colspan=\"5\" class=\"currency\">
                    Total: ";
            // line 539
            echo twig_call_macro($macros["mf"], "macro_currency_format", [twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "total", [], "any", false, false, false, 539), twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "currency", [], "any", false, false, false, 539)], 539, $context, $this->getSourceContext());
            echo "
                </td>
            </tr>
        </tfoot>
    </table>
";
        }
        // line 545
        echo "
</div>

";
        // line 548
        $context["transactions"] = twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "invoice_transaction_get_list", [0 => ["invoice_id" => twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "id", [], "any", false, false, false, 548), "per_page" => 100]], "method", false, false, false, 548), "list", [], "any", false, false, false, 548);
        // line 549
        echo "
";
        // line 550
        if ((twig_length_filter($this->env, ($context["transactions"] ?? null)) > 0)) {
            // line 551
            echo "
<div class=\"widget\">
    <div class=\"head\">
        <h5>";
            // line 554
            echo gettext("Transactions");
            echo "</h5>
    </div>
    <table class=\"tableStatic wide\">
        <thead>
            <tr>
                <th>";
            // line 559
            echo gettext("ID");
            echo "</th>
                <th>";
            // line 560
            echo gettext("Type");
            echo "</th>
                <th>";
            // line 561
            echo gettext("Gateway");
            echo "</th>
                <th>";
            // line 562
            echo gettext("Amount");
            echo "</th>
                <th>";
            // line 563
            echo gettext("Status");
            echo "</th>
                 <th>";
            // line 564
            echo gettext("Date");
            echo "</th>
                <th>&nbsp;</th>
            </tr>
        </thead>

        <tbody>
            ";
            // line 570
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(($context["transactions"] ?? null));
            foreach ($context['_seq'] as $context["i"] => $context["tx"]) {
                // line 571
                echo "            <tr>
                <td>";
                // line 572
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tx"], "txn_id", [], "any", false, false, false, 572), "html", null, true);
                echo "</td>
                <td>";
                // line 573
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tx"], "type", [], "any", false, false, false, 573), "html", null, true);
                echo "</td>
                <td>";
                // line 574
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tx"], "gateway", [], "any", false, false, false, 574), "html", null, true);
                echo "</td>
                <td>";
                // line 575
                echo twig_call_macro($macros["mf"], "macro_currency_format", [twig_get_attribute($this->env, $this->source, $context["tx"], "amount", [], "any", false, false, false, 575), twig_get_attribute($this->env, $this->source, $context["tx"], "currency", [], "any", false, false, false, 575)], 575, $context, $this->getSourceContext());
                echo "</td>
                <td>";
                // line 576
                echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, $context["tx"], "status", [], "any", false, false, false, 576)], 576, $context, $this->getSourceContext());
                echo "</td>
                <td>";
                // line 577
                echo twig_escape_filter($this->env, $this->extensions['Box_TwigExtensions']->twig_bb_datetime(twig_get_attribute($this->env, $this->source, $context["tx"], "created_at", [], "any", false, false, false, 577)), "html", null, true);
                echo "</td>
                <td style=\"width: 5%\">
                    <a class=\"bb-button btn14\" href=\"";
                // line 579
                echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/invoice/transaction");
                echo "/";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tx"], "id", [], "any", false, false, false, 579), "html", null, true);
                echo "\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                </td>
            </tr>
            ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['i'], $context['tx'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 583
            echo "        </tbody>
    </table>
</div>

";
        }
        // line 588
        echo "
";
    }

    // line 591
    public function block_sidebar2($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 592
        echo "
<div class=\"widget\">
    <div class=\"head\">
        <h2 class=\"dark-icon i-services\">";
        // line 595
        echo gettext("Actions");
        echo "</h2>
    </div>
    <div class=\"block\">
        <button class=\"bb-button\" type=\"button\" onclick=\"window.print()\"><span class=\"dark-icon i-print\"></span> ";
        // line 598
        echo gettext("Print");
        echo "</button>
        ";
        // line 599
        if ((twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "status", [], "any", false, false, false, 599) == "unpaid")) {
            // line 600
            echo "        <a class=\"bb-button api-link\" href=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/client/invoice/delete", ["hash" => twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "hash", [], "any", false, false, false, 600)]);
            echo "\"  data-api-confirm=\"Are you sure?\" data-api-redirect=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("invoice");
            echo "\"><span class=\"dark-icon i-bin\"></span> ";
            echo gettext("Delete");
            echo "</a>
        ";
        }
        // line 602
        echo "    </div>
</div>


";
    }

    // line 608
    public function block_head($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo twig_call_macro($macros["mf"], "macro_bb_editor", [".bb-textarea"], 608, $context, $this->getSourceContext());
    }

    // line 609
    public function block_js($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 611
        echo "<script type=\"text/javascript\">
\$(function() {
    \$('input[name=gateway]:first').attr('checked', true);
    \$('#pay-now-button').click(function(){
        var link = \$('input[name=gateway]:checked').val();
        bb.redirect(link);
        return false;
    });
});
</script>
";
    }

    public function getTemplateName()
    {
        return "mod_invoice_invoice.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  1332 => 611,  1328 => 609,  1321 => 608,  1313 => 602,  1303 => 600,  1301 => 599,  1297 => 598,  1291 => 595,  1286 => 592,  1282 => 591,  1277 => 588,  1270 => 583,  1258 => 579,  1253 => 577,  1249 => 576,  1245 => 575,  1241 => 574,  1237 => 573,  1233 => 572,  1230 => 571,  1226 => 570,  1217 => 564,  1213 => 563,  1209 => 562,  1205 => 561,  1201 => 560,  1197 => 559,  1189 => 554,  1184 => 551,  1182 => 550,  1179 => 549,  1177 => 548,  1172 => 545,  1163 => 539,  1151 => 534,  1143 => 529,  1136 => 524,  1126 => 520,  1122 => 519,  1119 => 518,  1116 => 517,  1107 => 516,  1091 => 514,  1089 => 513,  1081 => 510,  1075 => 509,  1070 => 507,  1066 => 505,  1062 => 504,  1054 => 499,  1050 => 498,  1046 => 497,  1040 => 493,  1033 => 489,  1022 => 482,  1014 => 480,  1012 => 479,  1004 => 474,  992 => 469,  984 => 464,  972 => 455,  967 => 452,  957 => 448,  947 => 445,  938 => 443,  932 => 442,  929 => 441,  925 => 440,  916 => 434,  912 => 433,  908 => 432,  900 => 427,  897 => 426,  895 => 425,  889 => 422,  873 => 409,  869 => 408,  861 => 403,  856 => 401,  847 => 395,  842 => 393,  833 => 387,  828 => 385,  819 => 379,  814 => 377,  804 => 371,  799 => 368,  790 => 362,  785 => 360,  776 => 354,  771 => 352,  762 => 346,  757 => 344,  748 => 338,  743 => 336,  734 => 330,  729 => 328,  720 => 322,  715 => 320,  706 => 314,  701 => 312,  692 => 307,  690 => 306,  678 => 297,  674 => 296,  666 => 291,  661 => 289,  652 => 283,  647 => 281,  638 => 275,  633 => 273,  624 => 267,  619 => 265,  610 => 259,  605 => 257,  596 => 251,  591 => 249,  582 => 244,  580 => 243,  569 => 235,  565 => 234,  561 => 233,  557 => 232,  550 => 228,  546 => 227,  541 => 225,  532 => 219,  528 => 218,  520 => 213,  515 => 211,  504 => 205,  499 => 203,  488 => 197,  483 => 195,  474 => 189,  469 => 187,  460 => 181,  455 => 179,  444 => 171,  437 => 167,  430 => 163,  419 => 155,  412 => 151,  405 => 147,  394 => 141,  388 => 140,  383 => 138,  372 => 132,  366 => 131,  360 => 130,  354 => 129,  349 => 127,  343 => 124,  326 => 113,  318 => 111,  316 => 110,  313 => 109,  308 => 107,  303 => 106,  301 => 105,  295 => 104,  289 => 103,  281 => 97,  275 => 94,  271 => 93,  268 => 92,  266 => 91,  255 => 88,  251 => 87,  240 => 83,  236 => 82,  229 => 78,  225 => 77,  218 => 73,  214 => 72,  207 => 68,  203 => 67,  199 => 65,  193 => 62,  187 => 61,  184 => 60,  182 => 59,  176 => 56,  172 => 55,  159 => 51,  155 => 50,  149 => 47,  145 => 46,  138 => 43,  134 => 42,  128 => 39,  124 => 38,  101 => 20,  96 => 17,  92 => 16,  84 => 11,  78 => 10,  72 => 9,  69 => 8,  65 => 7,  55 => 3,  51 => 1,  49 => 4,  47 => 2,  40 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}
{% import \"macro_functions.phtml\" as mf %}
{% block meta_title %}{% trans 'Invoice' %} {{ invoice.serie }}{{ \"%05s\"|format(invoice.nr) }}{% endblock %}
{% set active_menu = 'invoice' %}


{% block breadcrumbs %}
<ul>
    <li class=\"firstB\"><a href=\"{{ '/'|alink }}\">{% trans 'Home' %}</a></li>
    <li><a href=\"{{ 'invoice'|alink }}\">{% trans 'Invoices' %}</a></li>
    <li class=\"lastB\">{{ invoice.serie }}{{ \"%05s\"|format(invoice.nr) }}</li>
</ul>
{% endblock %}


{% block content %}

<div class=\"widget simpleTabs tabsRight\">
    <div class=\"head\">
        <h5>{% trans 'Invoice' %} #{{ invoice.id }}</h5>
    </div>

    <ul class=\"tabs\">
        <li><a href=\"#tab-info\">Details</a></li>
        <li><a href=\"#tab-manage\">Manage</a></li>
        <li><a href=\"#tab-texts\">Texts</a></li>
        <li><a href=\"#tab-buyer-credentials\">Client credentials</a></li>
        <li><a href=\"#tab-seller-credentials\">Company credentials</a></li>
    </ul>

    <div class=\"tabs_container\">
        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-info\">
            <div class=\"block\">
                <table class=\"tableStatic wide\">
                    <tbody>
                    <tr class=\"noborder\">
                        <td style=\"width: 20%\"><label>{% trans 'ID' %}</label></td>
                        <td>{{ invoice.id }}</td>
                    </tr>
                    <tr>
                        <td><label>{% trans 'Number' %}</label></td>
                        <td>{{ invoice.serie }}{{ \"%05s\"|format(invoice.nr) }}</td>
                    </tr>
                    <tr>
                        <td><label>{% trans 'Currency' %}</label></td>
                        <td>{{ invoice.currency }}</td>
                    </tr>
                    <tr>
                        <td><label>{% trans 'Client' %}</label></td>
                        <td><a href=\"{{ 'client/manage'|alink }}/{{ invoice.client.id }}\">{{ invoice.client.first_name }} {{ invoice.client.last_name }}</a></td>
                    </tr>

                    <tr>
                        <td><label>{% trans 'Status' %}</label></td>
                        <td>{{ mf.status_name(invoice.status) }}</td>
                    </tr>

                    {% if invoice.tax %}
                    <tr>
                        <td><label>{{ invoice.taxname }} {{ invoice.taxrate }}%</label></td>
                        <td>{{ mf.currency_format(invoice.tax, invoice.currency) }}</td>
                    </tr>
                    {% endif %}
                    
                    <tr>
                        <td><label>{% trans 'Total' %}</label></td>
                        <td>{{ mf.currency_format(invoice.total, invoice.currency) }}</td>
                    </tr>
                    
                    <tr>
                        <td><label>{% trans 'Created at' %}</label></td>
                        <td>{{ invoice.created_at|bb_date }}</td>
                    </tr>

                    <tr>
                        <td><label>{% trans 'Due at' %}</label></td>
                        <td>{{ invoice.due_at|bb_date }}</td>
                    </tr>

                    <tr>
                        <td><label>{% trans 'Paid at' %}</label></td>
                        <td>{% if invoice.paid_at %}{{ invoice.paid_at|bb_date }}{% else %}-{% endif %}</td>
                    </tr>

                    <tr>
                        <td><label>{% trans 'Reminded at' %}</label></td>
                        <td>{% if invoice.reminded_at %}{{ invoice.reminded_at|bb_date }} ({{invoice.reminded_at|timeago}} ago) {% endif %}</td>
                    </tr>
                    
                    {% if invoice.notes %}
                    <tr>
                        <td><label>{% trans 'Notes' %}</label></td>
                        <td>{{ invoice.notes|bbmd }}</td>
                    </tr>
                    {% endif %}
                    </tbody>
                    
                    <tfoot>
                        <tr>
                            <td colspan=\"2\">
                                <div class=\"aligncenter\">
                                    <a href=\"{{ 'invoice'|link }}/{{ invoice.hash }}\" title=\"\" class=\"btn55 mr10\" target=\"_blank\"><img src=\"images/icons/middlenav/preview.png\" alt=\"\"><span>View as client</span></a>
                                    <a href=\"{{ 'api/admin/invoice/delete'|link({'id' : invoice.id}) }}\" data-api-confirm=\"Are you sure?\"  title=\"\" class=\"btn55 mr10 api-link\" data-api-redirect=\"{{ 'invoice'|alink }}\"><img src=\"images/icons/middlenav/trash.png\" alt=\"\"><span>Delete</span></a>
                                    {% if invoice.status == 'unpaid' %}
                                    <a href=\"{{ 'api/admin/invoice/send_reminder' | link({'id' : invoice.id})}}\" title=\"\" class=\"btn55 mr10 api-link\" data-api-msg=\"Payment reminder was sent\"><img src=\"images/icons/middlenav/mail.png\" alt=\"\"><span>Send reminder</span></a>
                                    <a href=\"{{ 'api/admin/invoice/mark_as_paid'|link({'id' : invoice.id, 'execute' : 1}) }}\" title=\"\" class=\"btn55 mr10 api-link\" data-api-reload=\"Invoice marked as paid\"><img src=\"images/icons/middlenav/play2.png\" alt=\"\"><span>Mark as paid</span></a>
                                    {% endif %}
                                    
                                    {% if invoice.status == 'paid' %}
                                    <a href=\"{{ 'api/admin/invoice/refund'|link({'id' : invoice.id}) }}\" data-api-confirm=\"Are you sure?\"  title=\"\" class=\"btn55 mr10 api-link\" data-api-redirect=\"{{ 'invoice'|alink }}\"><img src=\"images/icons/middlenav/refresh4.png\" alt=\"\"><span>Refund</span></a>
                                    {% endif %}
                                    <a href=\"{{ 'invoice/pdf' | link }}/{{invoice.hash}}\" target=\"_blank\" class=\"btn55 mr10\"><img src=\"images/icons/middlenav/incoming.png\" alt=\"\"><span>PDF</span></a>
                                </div>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

        </div>

        <div class=\"tab_content nopadding\" id=\"tab-manage\">
            <form action=\"{{ 'api/admin/invoice/update'|link }}\" method=\"post\" class=\"mainForm api-form\" data-api-reload=\"Invoice updated\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>{% trans 'Status' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"radio\" name=\"status\" value=\"paid\"{% if invoice.status == 'paid' %} checked=\"checked\"{% endif %}/><label>Paid</label>
                            <input type=\"radio\" name=\"status\" value=\"unpaid\"{% if invoice.status == 'unpaid' %} checked=\"checked\"{% endif %} /><label>Unpaid</label>
                            <input type=\"radio\" name=\"status\" value=\"refunded\"{% if invoice.status == 'refunded' %} checked=\"checked\"{% endif %} /><label>Refunded</label>
                            <input type=\"radio\" name=\"status\" value=\"canceled\"{% if invoice.status == 'canceled' %} checked=\"checked\"{% endif %} /><label>Canceled</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Approved' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"radio\" name=\"approved\" value=\"1\"{% if invoice.approved  %} checked=\"checked\"{% endif %}/><label>Yes</label>
                            <input type=\"radio\" name=\"approved\" value=\"0\"{% if not invoice.approved %} checked=\"checked\"{% endif %} /><label>No</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Serie and number' %}:</label>
                        <div class=\"formRight moreFields\">
                            <ul>
                                <li style=\"width: 150px\">
                                    <input type=\"text\" name=\"serie\" value=\"{{ invoice.serie }}\"/>
                                </li>
                                <li class=\"sep\">&nbsp;</li>
                                <li style=\"width: 150px\">
                                    <input type=\"text\" name=\"nr\" value=\"{{ invoice.nr }}\"/>
                                </li>
                            </ul>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Tax' %}:</label>
                        <div class=\"formRight moreFields\">
                            <ul>
                                <li style=\"width: 150px\">
                                    <input type=\"text\" name=\"taxname\" value=\"{{ invoice.taxname }}\"/>
                                </li>
                                <li class=\"sep\">&nbsp;</li>
                                <li style=\"width: 40px\">
                                    <input type=\"text\" name=\"taxrate\" value=\"{{ invoice.taxrate }}\"/>
                                </li>
                                <li class=\"sep\">%</li>
                            </ul>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Payment method' %}:</label>
                        <div class=\"formRight\">
                            {{ mf.selectbox('gateway_id', guest.invoice_gateways({\"format\":\"pairs\"}), invoice.gateway_id, 0, 'Select payment method') }}
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Created at' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"created_at\" value=\"{{ invoice.created_at|date('Y-m-d') }}\" class=\"datepicker\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Due at' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"due_at\" value=\"{% if invoice.due_at %}{{ invoice.due_at|date('Y-m-d') }}{% endif %}\" class=\"datepicker\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Paid at' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"paid_at\" value=\"{% if invoice.paid_at %}{{ invoice.paid_at|date('Y-m-d') }}{% endif %}\" class=\"datepicker\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Notes' %}:</label>
                        <div class=\"formRight\">
                            <textarea name=\"notes\" cols=\"5\" rows=\"6\">{{ invoice.notes }}</textarea>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <input type=\"submit\" value=\"{% trans 'Update' %}\" class=\"greyishBtn submitForm\" />
                    <input type=\"hidden\" name=\"id\" value=\"{{ invoice.id }}\" />
                </fieldset>
            </form>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-texts\">
            <form action=\"{{ 'api/admin/invoice/update'|link }}\" method=\"post\" class=\"mainForm api-form\" data-api-msg=\"Invoice updated\">
                <fieldset>
                    <label class=\"topLabel\">{% trans 'Text before invoice items table' %}</label>
                    <textarea name=\"text_1\"  cols=\"5\" rows=\"6\" class=\"bb-textarea\"/>{{ invoice.text_1 }}</textarea>
                </fieldset>
                
                <fieldset>    
                    <label class=\"topLabel\">{% trans 'Text after invoice items table' %}</label>
                    <textarea name=\"text_2\" cols=\"5\" rows=\"6\" class=\"bb-textarea\"/>{{ invoice.text_2 }}</textarea>
                    <input type=\"submit\" value=\"{% trans 'Update' %}\" class=\"greyishBtn submitForm\" />
                    <input type=\"hidden\" name=\"id\" value=\"{{ invoice.id }}\" />
                </fieldset>
            </form>

            <div class=\"fix\"></div>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-seller-credentials\">
            {% set seller = invoice.seller %}
            <form action=\"{{ 'api/admin/invoice/update'|link }}\" method=\"post\" class=\"mainForm api-form\" data-api-msg=\"Invoice updated\">
                <fieldset>
                    <legend>Company details at the moment of purchase</legend>

                    <div class=\"rowElem noborder\">
                        <label>{% trans 'Company' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"seller_company\" value=\"{{ seller.company }}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <div class=\"rowElem\">
                        <label>{% trans 'Company VAT' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"seller_company_vat\" value=\"{{ seller.company_vat }}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <div class=\"rowElem\">
                        <label>{% trans 'Company Number' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"seller_company_number\" value=\"{{ seller.company_number }}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Address' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"seller_address\" value=\"{{ seller.address }}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Phone' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"seller_phone\" value=\"{{ seller.phone }}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Email' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"seller_email\" value=\"{{ seller.email }}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <input type=\"submit\" value=\"{% trans 'Update' %}\" class=\"greyishBtn submitForm\" />
                    <input type=\"hidden\" name=\"id\" value=\"{{ invoice.id }}\" />
                </fieldset>
            </form>

            <div class=\"fix\"></div>
        </div>


        <div class=\"tab_content nopadding\" id=\"tab-buyer-credentials\">
            {% set buyer = invoice.buyer %}
            <form action=\"{{ 'api/admin/invoice/update'|link }}\" method=\"post\" class=\"mainForm api-form\" data-api-msg=\"Invoice updated\">
                <fieldset>
                    <legend>Client details at the moment of purchase</legend>

                    <div class=\"rowElem noborder\">
                        <label>{% trans 'First name' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"buyer_first_name\" value=\"{{ buyer.first_name }}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Last name' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"buyer_last_name\" value=\"{{ buyer.last_name }}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Company' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"buyer_company\" value=\"{{ buyer.company }}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <div class=\"rowElem\">
                        <label>{% trans 'Company VAT' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"buyer_company_vat\" value=\"{{ buyer.company_vat }}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <div class=\"rowElem\">
                        <label>{% trans 'Company Number' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"buyer_company_number\" value=\"{{ buyer.company_number }}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Address' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"buyer_address\" value=\"{{ buyer.address }}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'City' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"buyer_city\" value=\"{{ buyer.city }}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'State' %}:</label>
                        <div class=\"formRight\">
                            {# mf.selectbox('buyer_state', guest.system_states, buyer.state, 0, 'Select state') #}
                            <input type=\"text\" name=\"buyer_state\" value=\"{{ buyer.state }}\" />
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Country' %}:</label>
                        <div class=\"formRight\">
                            {{ mf.selectbox('buyer_country', guest.system_countries, buyer.country, 0, 'Select country') }}
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Phone' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"buyer_phone\" value=\"{{ buyer.phone }}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Zip' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"buyer_zip\" value=\"{{ buyer.zip }}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Email' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"buyer_email\" value=\"{{ buyer.email }}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <input type=\"submit\" value=\"{% trans 'Update' %}\" class=\"greyishBtn submitForm\" />
                    <input type=\"hidden\" name=\"id\" value=\"{{ invoice.id }}\" />
                </fieldset>
            </form>

            <div class=\"fix\"></div>
        </div>

    </div>
</div>


<div class=\"widget\">
    <div class=\"head\">
        <h5>{% trans 'Invoice items' %}</h5>
    </div>

{% if not invoice.approved %}

    <form action=\"{{ 'api/admin/invoice/update'|link }}\" method=\"post\" class=\"mainForm api-form\" data-api-reload=\"1\">
        <fieldset>
        <table class=\"tableStatic wide\">
            <thead>
                <tr>
                    <th>{% trans 'Title' %}</th>
                    <th class=\"currency\">{% trans 'Price' %}</th>
                    <th>{% trans 'Tax' %}</th>
                    <th class=\"actions\">&nbsp;</th>
                </tr>
            </thead>

            <tbody>
                {% for i, item in invoice.lines %}
                <tr>
                    <td style=\"width: 60%;\"><input type=\"text\" name=\"items[{{ item.id }}][title]\" value=\"{{ item.title }}\" style=\"width:98%\"></td>
                    <td style=\"width: 20%;\"><input type=\"text\" name=\"items[{{ item.id }}][price]\" value=\"{{ item.price }}\" style=\"width:80px\"> {{ invoice.currency }}</td>
                    <td style=\"width: 5%;\">
                        <input type=\"checkbox\" name=\"items[{{ item.id }}][taxed]\" value=\"1\" {% if item.taxed %}checked=\"checked\"{% endif %}/>
                    </td>
                    <td style=\"width: 5%;\">
                        <a class=\"bb-button btn14 api-link\" data-api-confirm=\"Are you sure?\" href=\"{{ 'api/admin/invoice/item_delete'|link({'id' : item.id}) }}\" data-api-reload=\"1\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                    </td>
                </tr>
                {% endfor %}

                <tr>
                    <td style=\"width: 70%;\"><input type=\"text\" name=\"new_item[title]\" value=\"\" style=\"width:98%\" placeholder=\"New line description\"></td>
                    <td style=\"width: 20%;\"><input type=\"text\" name=\"new_item[price]\" value=\"\" style=\"width:80px\" placeholder=\"Price\"> {{ invoice.currency }}</td>
                    <td><input type=\"checkbox\" name=\"new_item[taxed]\" value=\"1\"/></td>
                    <td>&nbsp;</td>
                </tr>
            </tbody>

            <tfoot>
                <tr>
                    <td colspan=\"4\" class=\"currency\">
                        Subtotal: {{ mf.currency_format(invoice.subtotal, invoice.currency) }}
                    </td>
                </tr>
                <tr>
                    <td colspan=\"4\" class=\"currency\">
                        {{ invoice.taxname }} {{ invoice.taxrate }}%: {{ mf.currency_format(invoice.tax, invoice.currency) }}
                    </td>
                </tr>
                <tr>
                    <td colspan=\"4\" class=\"currency\">
                        Total: {{ mf.currency_format(invoice.total, invoice.currency) }}
                    </td>
                </tr>
                <tr>
                    <td colspan=\"4\">
                        {% if not invoice.approved %}
                        <a href=\"{{ 'api/admin/invoice/approve'|link({'id' : invoice.id}) }}\"  title=\"\" class=\"btnIconLeft mr10 api-link\" data-api-reload=\"{{ 'invoices'|alink }}\"><img src=\"images/icons/dark/check.png\" alt=\"\" class=\"icon\"><span>Approve</span></a>
                        {% endif %}
                        <input type=\"submit\" value=\"{% trans 'Update' %}\" class=\"greyishBtn submitForm\" style=\"width:100px\"/>
                    </td>
                </tr>
            </tfoot>
        </table>
        </fieldset>

        <input type=\"hidden\" name=\"id\" value=\"{{ invoice.id }}\" />
    </form>

{% else %}
    <table class=\"tableStatic wide\">
        <thead>
            <tr>
                <th  style=\"width: 11%;\" colspan=\"2\"></th>
                <th style=\"width: 70%;\">{% trans 'Title' %}</th>
                <th style=\"width: 7%;\">{% trans 'Tax' %}</th>
                <th class=\"currency\" style=\"width: 15%;\">{% trans 'Total' %}</th>
            </tr>
        </thead>

        <tbody>
            {% for i, item in invoice.lines %}

            <tr>
                <td>{{ i+1 }}.</td>
                <td>
                    <div class=\"bull task {{ item.status }}\" title=\"Task status: {{ mf.status_name(item.status) }}\"></div>
                    <div class=\"bull charge {{ item.charged ? 'yes' : 'no' }}\" title=\"{{ item.charged ? 'Charged from client balance' : 'Not charged from client balance' }}\"></div>
                </td>
                <td>
                {% if item.order_id %}
                <a href=\"{{ 'order/manage'|alink }}/{{ item.order_id }}\">{{ item.title }} {% if item.quantity > 1 %} x {{ item.quantity }} {# item.unit #}{% endif %}</a>
                {% else %}
                {{ item.title }} {% if item.quantity > 1 %} x {{ item.quantity }} {# item.unit #}{% endif %}
                {% endif %}
                </td>
                <td>{{ mf.currency_format(item.tax,invoice.currency) }}</td>
                <td class=\"currency\">{{ mf.currency_format( item.total, invoice.currency) }}</td>
            </tr>

            {% endfor %}
        </tbody>
        
        <tfoot>
            <tr>
                <td colspan=\"5\" class=\"currency\">
                    Subtotal: {{ mf.currency_format(invoice.subtotal, invoice.currency) }}
                </td>
            </tr>
            <tr>
                <td colspan=\"5\" class=\"currency\">
                    {{ invoice.taxname }} {{ invoice.taxrate }}%: {{ mf.currency_format(invoice.tax, invoice.currency) }}
                </td>
            </tr>
            <tr>
                <td colspan=\"5\" class=\"currency\">
                    Total: {{ mf.currency_format(invoice.total, invoice.currency) }}
                </td>
            </tr>
        </tfoot>
    </table>
{% endif %}

</div>

{% set transactions = admin.invoice_transaction_get_list({\"invoice_id\":invoice.id,\"per_page\":100 }).list %}

{% if transactions|length > 0 %}

<div class=\"widget\">
    <div class=\"head\">
        <h5>{% trans 'Transactions' %}</h5>
    </div>
    <table class=\"tableStatic wide\">
        <thead>
            <tr>
                <th>{% trans 'ID' %}</th>
                <th>{% trans 'Type' %}</th>
                <th>{% trans 'Gateway' %}</th>
                <th>{% trans 'Amount' %}</th>
                <th>{% trans 'Status' %}</th>
                 <th>{% trans 'Date' %}</th>
                <th>&nbsp;</th>
            </tr>
        </thead>

        <tbody>
            {% for i, tx in transactions %}
            <tr>
                <td>{{tx.txn_id}}</td>
                <td>{{tx.type}}</td>
                <td>{{tx.gateway}}</td>
                <td>{{ mf.currency_format( tx.amount, tx.currency) }}</td>
                <td>{{ mf.status_name(tx.status) }}</td>
                <td>{{tx.created_at|bb_datetime}}</td>
                <td style=\"width: 5%\">
                    <a class=\"bb-button btn14\" href=\"{{ '/invoice/transaction'|alink }}/{{tx.id}}\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                </td>
            </tr>
            {% endfor %}
        </tbody>
    </table>
</div>

{% endif %}

{% endblock %}

{% block sidebar2 %}

<div class=\"widget\">
    <div class=\"head\">
        <h2 class=\"dark-icon i-services\">{% trans 'Actions' %}</h2>
    </div>
    <div class=\"block\">
        <button class=\"bb-button\" type=\"button\" onclick=\"window.print()\"><span class=\"dark-icon i-print\"></span> {% trans 'Print' %}</button>
        {% if invoice.status == 'unpaid' %}
        <a class=\"bb-button api-link\" href=\"{{ 'api/client/invoice/delete'|link({'hash' : invoice.hash}) }}\"  data-api-confirm=\"Are you sure?\" data-api-redirect=\"{{ 'invoice'|alink }}\"><span class=\"dark-icon i-bin\"></span> {% trans 'Delete' %}</a>
        {% endif %}
    </div>
</div>


{% endblock %}

{% block head %}{{ mf.bb_editor('.bb-textarea') }}{% endblock %}
{% block js %}
{% autoescape \"js\" %}
<script type=\"text/javascript\">
\$(function() {
    \$('input[name=gateway]:first').attr('checked', true);
    \$('#pay-now-button').click(function(){
        var link = \$('input[name=gateway]:checked').val();
        bb.redirect(link);
        return false;
    });
});
</script>
{% endautoescape %}
{% endblock %}", "mod_invoice_invoice.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-themes/admin_default/html/mod_invoice_invoice.phtml");
    }
}
