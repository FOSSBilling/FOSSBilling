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

/* mod_order_manage.phtml */
class __TwigTemplate_0dd20fa6ea2237728386d57164d0fa381bed55a851a001dca847c43e5d8796f2 extends \Twig\Template
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
            'js' => [$this, 'block_js'],
        ];
    }

    protected function doGetParent(array $context)
    {
        // line 1
        return $this->loadTemplate(((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ajax", [], "any", false, false, false, 1)) ? ("layout_blank.phtml") : ("layout_default.phtml")), "mod_order_manage.phtml", 1);
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 2
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_order_manage.phtml", 2)->unwrap();
        // line 4
        $context["addons"] = twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "order_addons", [0 => ["id" => twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "id", [], "any", false, false, false, 4)]], "method", false, false, false, 4);
        // line 11
        $context["service_partial"] = (("mod_service" . twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "service_type", [], "any", false, false, false, 11)) . "_manage.phtml");
        // line 12
        $context["upgradables"] = twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "order_upgradables", [0 => ["id" => twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "id", [], "any", false, false, false, 12)]], "method", false, false, false, 12);
        // line 1
        $this->getParent($context)->display($context, array_merge($this->blocks, $blocks));
    }

    // line 3
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "title", [], "any", false, false, false, 3), "html", null, true);
    }

    // line 6
    public function block_breadcrumb($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 7
        echo "<li><a href=\"";
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("service");
        echo "\">";
        echo gettext("Orders");
        echo "</a><span class=\"divider\">/</span></li>
";
        // line 8
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "title", [], "any", false, false, false, 8), "html", null, true);
        echo "
";
    }

    // line 14
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 15
        echo "
<div class=\"row\">
    <article class=\"";
        // line 17
        if ((twig_length_filter($this->env, ($context["addons"] ?? null)) > 0)) {
            echo "span6";
        } else {
            echo "span12";
        }
        echo " data-block\">
        <div class=\"data-container\">
            <header>
                <h1>";
        // line 20
        echo gettext("Service details");
        echo "</h1>
                <p>";
        // line 21
        echo gettext("All information about your service");
        echo "</p>
            </header>
            <section>
                <table class=\"table table-striped table-bordered table-condensed\">
                    <tbody>
                    <tr>
                        <td><label>";
        // line 27
        echo gettext("Order");
        echo "</label></td>
                        <td>#";
        // line 28
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "id", [], "any", false, false, false, 28), "html", null, true);
        echo "</td>
                    </tr>

                    <tr>
                        <td><label>";
        // line 32
        echo gettext("Product name");
        echo "</label></td>
                        <td><strong>";
        // line 33
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "title", [], "any", false, false, false, 33), "html", null, true);
        echo "</strong></td>
                    </tr>

                    <tr>
                        <td><label>";
        // line 37
        echo gettext("Payment amount");
        echo "</label></td>
                        <td>";
        // line 38
        echo twig_money($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "total", [], "any", false, false, false, 38), twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "currency", [], "any", false, false, false, 38));
        echo "</td>
                    </tr>

                    ";
        // line 41
        if (twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "period", [], "any", false, false, false, 41)) {
            // line 42
            echo "                    <tr>
                        <td><label>";
            // line 43
            echo gettext("Billing cycle");
            echo "</label></td>
                        <td>";
            // line 44
            echo twig_period_title($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "period", [], "any", false, false, false, 44));
            echo "</td>
                    </tr>
                    ";
        }
        // line 47
        echo "
                    <tr>
                        <td><label>";
        // line 49
        echo gettext("Order status");
        echo "</label></td>
                        <td><span class=\"label ";
        // line 50
        if ((twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "status", [], "any", false, false, false, 50) == "active")) {
            echo "label-success";
        } elseif ((twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "status", [], "any", false, false, false, 50) == "pending_setup")) {
            echo "label-warning";
        }
        echo "\">";
        echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "status", [], "any", false, false, false, 50)], 50, $context, $this->getSourceContext());
        echo "</span></td>
                    </tr>

                    <tr>
                        <td><label>";
        // line 54
        echo gettext("Order created");
        echo "</label></td>
                        <td>";
        // line 55
        echo twig_escape_filter($this->env, $this->extensions['Box_TwigExtensions']->twig_bb_date(twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "created_at", [], "any", false, false, false, 55)), "html", null, true);
        echo "</td>
                    </tr>

                    <tr>
                        <td><label>";
        // line 59
        echo gettext("Activated at");
        echo "</label></td>
                        <td>";
        // line 60
        if (twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "activated_at", [], "any", false, false, false, 60)) {
            echo twig_escape_filter($this->env, $this->extensions['Box_TwigExtensions']->twig_bb_date(twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "activated_at", [], "any", false, false, false, 60)), "html", null, true);
        } else {
            echo "-";
        }
        echo "</td>
                    </tr>

                    ";
        // line 63
        if (twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "period", [], "any", false, false, false, 63)) {
            // line 64
            echo "                    <tr>
                        <td><label>";
            // line 65
            echo gettext("Renewal date");
            echo " ";
            if (twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "expires_at", [], "any", false, false, false, 65)) {
                echo " in ";
                echo twig_escape_filter($this->env, twig_daysleft_filter(twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "expires_at", [], "any", false, false, false, 65)), "html", null, true);
                echo " day(s) ";
            }
            echo "</label></td>
                        <td>";
            // line 66
            if (twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "expires_at", [], "any", false, false, false, 66)) {
                echo twig_escape_filter($this->env, $this->extensions['Box_TwigExtensions']->twig_bb_date(twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "expires_at", [], "any", false, false, false, 66)), "html", null, true);
            } else {
                echo "-";
            }
            echo "</td>
                    </tr>
                    ";
        }
        // line 69
        echo "
                    ";
        // line 70
        if (twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "notes", [], "any", false, false, false, 70)) {
            // line 71
            echo "                    <tr>
                        <td><label>";
            // line 72
            echo gettext("Order notes");
            echo "</label></td>
                        <td>";
            // line 73
            echo twig_bbmd_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "notes", [], "any", false, false, false, 73));
            echo "</td>
                    </tr>
                    ";
        }
        // line 76
        echo "
                    ";
        // line 77
        if ((twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "active_tickets", [], "any", false, false, false, 77) > 0)) {
            // line 78
            echo "                    <tr>
                        <td><label>";
            // line 79
            echo gettext("Active support tickets");
            echo "</label></td>
                        <td>
                            <div class=\"num\"><a href=\"";
            // line 81
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("support");
            echo "\" class=\"redNum\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "active_tickets", [], "any", false, false, false, 81), "html", null, true);
            echo "</a></div>
                            ";
            // line 83
            echo "                            ";
            // line 84
            echo "                        </td>
                    </tr>
                    ";
        }
        // line 87
        echo "                    </tbody>

                    <tfoot>
                    <tr>
                        <td colspan=\"2\">

                            ";
        // line 93
        if (twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "period", [], "any", false, false, false, 93)) {
            // line 94
            echo "                            <button class=\"btn btn-primary btn-small\" type=\"button\" id=\"renewal-button\">";
            echo gettext("Renew now");
            echo "</button>
                            ";
        }
        // line 96
        echo "
                            ";
        // line 97
        if (($context["upgradables"] ?? null)) {
            // line 98
            echo "                            <a href=\"#submit-upgrade-ticket\" class=\"btn btn-success btn-small\" type=\"button\" id=\"request-upgrade\" data-toggle=\"modal\">";
            echo gettext("Request Upgrade");
            echo "</a>
                            ";
        }
        // line 100
        echo "
                            <a href=\"#submit-ticket\" class=\"btn btn-info btn-small\" type=\"button\" id=\"open-ticket\" data-toggle=\"modal\">";
        // line 101
        echo gettext("Open ticket");
        echo "</a>
                            
                            ";
        // line 103
        if ((twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "status", [], "any", false, false, false, 103) == "active")) {
            // line 104
            echo "                            <a href=\"#submit-cancellation-ticket\" class=\"btn btn-primary btn-warning btn-small\" type=\"button\" data-toggle=\"modal\">";
            echo gettext("Request Cancellation");
            echo "</a>
                            ";
        }
        // line 106
        echo "
                        </td>
                    </tr>
                    </tfoot>
                </table>
                <p><a class=\"btn btn-small\" href=\"";
        // line 111
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/order/service");
        echo "\">";
        echo gettext("Back to services");
        echo "</a></p>
            </section>
        </div>
    </article>
    ";
        // line 115
        if ((twig_length_filter($this->env, ($context["addons"] ?? null)) < 1)) {
            echo "</div>";
        }
        // line 116
        echo "
";
        // line 117
        if ((twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "group_master", [], "any", false, false, false, 117) == 1)) {
            // line 118
            if ((twig_length_filter($this->env, ($context["addons"] ?? null)) > 0)) {
                // line 119
                echo "    <article class=\"span6 data-block\">
        <div class=\"data-container\">
            <header>
                <h1>";
                // line 122
                echo gettext("Addons");
                echo "</h1>
                <p>";
                // line 123
                echo gettext("Addons you have ordered with this service");
                echo "</p>
            </header>
            <section>

                <table class=\"table table-striped table-bordered table-condensed\">
                    <thead>
                    <tr>
                        <th>";
                // line 130
                echo gettext("Product/Service");
                echo "</th>
                        <th>";
                // line 131
                echo gettext("Price");
                echo "</th>
                        <th>";
                // line 132
                echo gettext("Billing Cycle");
                echo "</th>
                        <th>";
                // line 133
                echo gettext("Next due date");
                echo "</th>
                        <th>";
                // line 134
                echo gettext("Status");
                echo "</th>
                        <th>&nbsp</th>
                    </tr>
                    </thead>
                    <tbody>
                    ";
                // line 139
                $context['_parent'] = $context;
                $context['_seq'] = twig_ensure_traversable(($context["addons"] ?? null));
                foreach ($context['_seq'] as $context["i"] => $context["addon"]) {
                    // line 140
                    echo "                    <tr class=\"";
                    echo twig_escape_filter($this->env, twig_cycle([0 => "odd", 1 => "even"], $context["i"]), "html", null, true);
                    echo "\">
                        <td>";
                    // line 141
                    echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["addon"], "title", [], "any", false, false, false, 141), "html", null, true);
                    echo "</td>
                        <td>";
                    // line 142
                    echo twig_money($this->env, twig_get_attribute($this->env, $this->source, $context["addon"], "total", [], "any", false, false, false, 142), twig_get_attribute($this->env, $this->source, $context["addon"], "currency", [], "any", false, false, false, 142));
                    echo "</td>
                        <td>";
                    // line 143
                    echo twig_period_title($this->env, twig_get_attribute($this->env, $this->source, $context["addon"], "period", [], "any", false, false, false, 143));
                    echo "</td>
                        <td>";
                    // line 144
                    if (twig_get_attribute($this->env, $this->source, $context["addon"], "expires_at", [], "any", false, false, false, 144)) {
                        echo twig_escape_filter($this->env, $this->extensions['Box_TwigExtensions']->twig_bb_date(twig_get_attribute($this->env, $this->source, $context["addon"], "expires_at", [], "any", false, false, false, 144)), "html", null, true);
                    } else {
                        echo "-";
                    }
                    echo "</td>
                        <td><span class=\"label ";
                    // line 145
                    if ((twig_get_attribute($this->env, $this->source, $context["addon"], "status", [], "any", false, false, false, 145) == "active")) {
                        echo "label-success";
                    } elseif ((twig_get_attribute($this->env, $this->source, $context["addon"], "status", [], "any", false, false, false, 145) == "pending_setup")) {
                        echo "label-warning";
                    }
                    echo "\">";
                    echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, $context["addon"], "status", [], "any", false, false, false, 145)], 145, $context, $this->getSourceContext());
                    echo "</span></td>
                        <td class=\"actions\"><a class=\"bb-button\" href=\"";
                    // line 146
                    echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/order/service/manage");
                    echo "/";
                    echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["addon"], "id", [], "any", false, false, false, 146), "html", null, true);
                    echo "\"><span class=\"dark-icon i-drag\"></span></a></td>
                    </tr>
                    ";
                }
                $_parent = $context['_parent'];
                unset($context['_seq'], $context['_iterated'], $context['i'], $context['addon'], $context['_parent'], $context['loop']);
                $context = array_intersect_key($context, $_parent) + $_parent;
                // line 149
                echo "                    </tbody>
                </table>
            </section>
</div>
</article>
</div>
";
            }
        }
        // line 157
        echo "

<div id=\"submit-ticket\" class=\"modal hide fade\" tabindex=\"-1\" role=\"dialog\" aria-labelledby=\"myModalLabel\" aria-hidden=\"true\">
    <div class=\"modal-header\">
        <button type=\"button\" class=\"close\" data-dismiss=\"modal\" aria-hidden=\"true\">&times;</button>
        <h3>
            ";
        // line 163
        echo gettext("Submit new support ticket");
        // line 164
        echo "        </h3>
    </div>
    <div class=\"modal-body\">
        <form action=\"\" method=\"post\" style=\"background: none\" class=\"open-ticket\">
            <fieldset>
                <div class=\"control-group\">
                    <label>";
        // line 170
        echo gettext("Help desk");
        echo ": </label>
                    <div class=\"controls\">
                    ";
        // line 172
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["support_helpdesk_id", twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "support_helpdesk_get_pairs", [], "any", false, false, false, 172), twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "support_helpdesk_id", [], "any", false, false, false, 172), 1], 172, $context, $this->getSourceContext());
        echo "
                    </div>
                </div>

                <div class=\"control-group\">
                    <label>";
        // line 177
        echo gettext("Subject");
        echo ": </label>
                    <div class=\"controls\">
                        <input type=\"text\" name=\"subject\" value=\"";
        // line 179
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "subject", [], "any", false, false, false, 179));
        echo "\" required=\"required\" class=\"span5\"/>
                    </div>
                </div>

                <div class=\"control-group\">
                    <label>";
        // line 184
        echo gettext("Message");
        echo ": </label>
                    <div class=\"controls\">
                        <textarea name=\"content\" cols=\"5\" rows=\"5\" required=\"required\"  class=\"span5\">";
        // line 186
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "content", [], "any", false, false, false, 186));
        echo "</textarea>
                    </div>
                </div>

                <input type=\"hidden\" name=\"rel_type\" value=\"order\">
                <input type=\"hidden\" name=\"rel_id\" value=\"";
        // line 191
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "id", [], "any", false, false, false, 191), "html", null, true);
        echo "\">
            </fieldset>


    </div>
    <div class=\"modal-footer\">
        <input class=\"btn btn-primary btn-large\" type=\"submit\" value=\"";
        // line 197
        echo gettext("Submit");
        echo "\">
    </div>
    </form>
</div>

<div id=\"submit-cancellation-ticket\" class=\"modal hide fade open-ticket\" tabindex=\"-1\" role=\"dialog\" aria-labelledby=\"myModalLabel\" aria-hidden=\"true\">
    <div class=\"modal-header\">
        <button type=\"button\" class=\"close\" data-dismiss=\"modal\" aria-hidden=\"true\">&times;</button>
        <h3>
            ";
        // line 206
        echo gettext("Submit cancellation request");
        // line 207
        echo "        </h3>
    </div>
    <div class=\"modal-body\">
        <form action=\"\" method=\"post\" style=\"background:none\" class=\"request-cancellation\">
            <div class=\"control-group\">
                <label>";
        // line 212
        echo gettext("Help desk");
        echo ": </label>
                <div class=\"controls\">
                    ";
        // line 214
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["support_helpdesk_id", twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "support_helpdesk_get_pairs", [], "any", false, false, false, 214), twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "support_helpdesk_id", [], "any", false, false, false, 214), 1], 214, $context, $this->getSourceContext());
        echo "
                </div>
            </div>

            <div class=\"control-group\">
                <label>";
        // line 219
        echo gettext("Subject");
        echo ": </label>
                <div class=\"controls\">
                    <input type=\"text\" name=\"subject\" value=\"";
        // line 221
        echo gettext("I would like to cancel");
        echo " ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "title", [], "any", false, false, false, 221), "html", null, true);
        echo "\" required=\"required\" class=\"span5\"/>
                </div>
            </div>

            <div class=\"control-group\">
                <label>";
        // line 226
        echo gettext("Reason");
        echo ": </label>
                <div class=\"controls\">
                    <textarea name=\"content\" cols=\"5\" rows=\"5\" required=\"required\" class=\"span5\">";
        // line 228
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "content", [], "any", false, false, false, 228));
        echo "</textarea>
                </div>
            </div>
                <input type=\"hidden\" name=\"rel_type\" value=\"order\">
                <input type=\"hidden\" name=\"rel_id\" value=\"";
        // line 232
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "id", [], "any", false, false, false, 232), "html", null, true);
        echo "\">
                <input type=\"hidden\" name=\"rel_task\" value=\"cancel\">
    </div>
    <div class=\"modal-footer\">
        <input class=\"btn btn-primary btn-large\" type=\"submit\" value=\"";
        // line 236
        echo gettext("Submit");
        echo "\">
    </div>
    </form>
</div>

<div id=\"submit-upgrade-ticket\" class=\"modal hide fade open-ticket\" tabindex=\"-1\" role=\"dialog\" aria-labelledby=\"myModalLabel\" aria-hidden=\"true\">
    <div class=\"modal-header\">
        <button type=\"button\" class=\"close\" data-dismiss=\"modal\" aria-hidden=\"true\">&times;</button>
        <h3>
            ";
        // line 245
        echo gettext("Submit upgrade request");
        // line 246
        echo "        </h3>
    </div>
    <div class=\"modal-body\">
        <form action=\"\" method=\"post\" class=\"request-upgrade\" data-api-url=\"client/support/ticket_create\" data-api-msg=\"Upgrade request received\" style=\"background: none\">
            <fieldset>
                <div class=\"control-group\">
                    <label>";
        // line 252
        echo gettext("Subject");
        echo ": </label>
                    <div class=\"controls\">
                        <input type=\"text\" name=\"subject\" value=\"";
        // line 254
        echo gettext("I would like to upgrade");
        echo " ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "title", [], "any", false, false, false, 254), "html", null, true);
        echo "\" required=\"required\" class=\"span5\"/>
                    </div>
                </div>

                <div class=\"control-group\">
                    <label>";
        // line 259
        echo gettext("Help desk");
        echo ": </label>
                    <div class=\"controls\">
                        ";
        // line 261
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["support_helpdesk_id", twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "support_helpdesk_get_pairs", [], "any", false, false, false, 261), twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "support_helpdesk_id", [], "any", false, false, false, 261), 1], 261, $context, $this->getSourceContext());
        echo "
                    </div>
                </div>


                <div class=\"control-group\">
                    <label>";
        // line 267
        echo gettext("Upgrade to");
        echo ": </label>
                    <div class=\"controls\">
                        ";
        // line 269
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["rel_new_value", ($context["upgradables"] ?? null), "", 1], 269, $context, $this->getSourceContext());
        echo "
                    </div>
                </div>

                <div class=\"control-group\">
                    <label>";
        // line 274
        echo gettext("Notes");
        echo ": </label>
                    <div class=\"controls\">
                        <textarea name=\"content\" cols=\"5\" rows=\"5\" placeholder=\"Your comment\" class=\"span5\">";
        // line 276
        echo gettext("I would like to upgrade");
        echo " ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "title", [], "any", false, false, false, 276), "html", null, true);
        echo "</textarea>
                    </div>
                </div>

                <input type=\"hidden\" name=\"rel_type\" value=\"order\">
                <input type=\"hidden\" name=\"rel_id\" value=\"";
        // line 281
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "id", [], "any", false, false, false, 281), "html", null, true);
        echo "\">
                <input type=\"hidden\" name=\"rel_task\" value=\"upgrade\">
            </fieldset>
    </div>
    <div class=\"modal-footer\">
        <input class=\"btn btn-primary btn-large\" type=\"submit\" value=\"";
        // line 286
        echo gettext("Request");
        echo "\">
    </div>
    </form>
</div>

";
        // line 291
        if (twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_template_exists", [0 => ["file" => ($context["service_partial"] ?? null)]], "method", false, false, false, 291)) {
            // line 292
            echo "    ";
            $context["service"] = twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "order_service", [0 => ["id" => twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "id", [], "any", false, false, false, 292)]], "method", false, false, false, 292);
            // line 293
            echo "    ";
            $this->loadTemplate(($context["service_partial"] ?? null), "mod_order_manage.phtml", 293)->display(twig_array_merge($context, ["order" => ($context["order"] ?? null), "service" => ($context["service"] ?? null)]));
        } else {
            // line 295
            echo "    ";
        }
        // line 297
        echo "
";
    }

    // line 300
    public function block_js($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 302
        echo "<script type=\"text/javascript\">
\$(function() {
    \$('#renewal-button').click(function(e){
            e.preventDefault();
        if(confirm(\"This will generate new invoice. Are you sure you want to continue?\")) {
            bb.post(
                'client/invoice/renewal_invoice',
                {order_id: ";
        // line 309
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "id", [], "any", false, false, false, 309), "js", null, true);
        echo " },
                function(result) {
                    bb.redirect('";
        // line 311
        echo twig_escape_filter($this->env, $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("invoice"), "js", null, true);
        echo "' + '/' + result);
                }
            );
    }
    });

       \$('.open-ticket').submit(function(){
       \$('.wait').show();
        bb.post(
            'client/support/ticket_create',
            \$(this).serialize(),
            function(result) {
                \$('#submit-ticket').modal('hide')
                bb.msg('Ticket was submitted. If you want to track conversation please go to support section');
                \$('.wait').hide();
            }
        );
        return false;
    });

    \$('.request-cancellation').submit(function(){
        \$('.wait').show();
        bb.post(
            'client/support/ticket_create',
            \$(this).serialize(),
            function(result) {
                \$('#submit-cancellation-ticket').modal('hide')
                bb.msg('Service cancellation request received');
                \$('.wait').hide();
            }
        );
        return false;
    });

    \$('.request-upgrade').submit(function(){
        \$('.wait').show();
        bb.post(
            'client/support/ticket_create',
            \$(this).serialize(),
            function(result) {
                \$('#submit-upgrade-ticket').modal('hide')
                bb.msg('Service upgrade request received');
                \$('.wait').hide();
            }
        );
        return false;
    });
});
</script>
";
    }

    public function getTemplateName()
    {
        return "mod_order_manage.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  711 => 311,  706 => 309,  697 => 302,  693 => 300,  688 => 297,  685 => 295,  681 => 293,  678 => 292,  676 => 291,  668 => 286,  660 => 281,  650 => 276,  645 => 274,  637 => 269,  632 => 267,  623 => 261,  618 => 259,  608 => 254,  603 => 252,  595 => 246,  593 => 245,  581 => 236,  574 => 232,  567 => 228,  562 => 226,  552 => 221,  547 => 219,  539 => 214,  534 => 212,  527 => 207,  525 => 206,  513 => 197,  504 => 191,  496 => 186,  491 => 184,  483 => 179,  478 => 177,  470 => 172,  465 => 170,  457 => 164,  455 => 163,  447 => 157,  437 => 149,  426 => 146,  416 => 145,  408 => 144,  404 => 143,  400 => 142,  396 => 141,  391 => 140,  387 => 139,  379 => 134,  375 => 133,  371 => 132,  367 => 131,  363 => 130,  353 => 123,  349 => 122,  344 => 119,  342 => 118,  340 => 117,  337 => 116,  333 => 115,  324 => 111,  317 => 106,  311 => 104,  309 => 103,  304 => 101,  301 => 100,  295 => 98,  293 => 97,  290 => 96,  284 => 94,  282 => 93,  274 => 87,  269 => 84,  267 => 83,  261 => 81,  256 => 79,  253 => 78,  251 => 77,  248 => 76,  242 => 73,  238 => 72,  235 => 71,  233 => 70,  230 => 69,  220 => 66,  210 => 65,  207 => 64,  205 => 63,  195 => 60,  191 => 59,  184 => 55,  180 => 54,  167 => 50,  163 => 49,  159 => 47,  153 => 44,  149 => 43,  146 => 42,  144 => 41,  138 => 38,  134 => 37,  127 => 33,  123 => 32,  116 => 28,  112 => 27,  103 => 21,  99 => 20,  89 => 17,  85 => 15,  81 => 14,  75 => 8,  68 => 7,  64 => 6,  57 => 3,  53 => 1,  51 => 12,  49 => 11,  47 => 4,  45 => 2,  38 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}
{% import \"macro_functions.phtml\" as mf %}
{% block meta_title %}{{ order.title }}{% endblock %}
{% set addons = client.order_addons({\"id\":order.id}) %}

{% block breadcrumb %}
<li><a href=\"{{ 'service' | link}}\">{% trans 'Orders' %}</a><span class=\"divider\">/</span></li>
{{ order.title }}
{% endblock %}

{% set service_partial = \"mod_service\" ~ order.service_type ~ \"_manage.phtml\" %}
{% set upgradables = client.order_upgradables({'id':order.id}) %}

{% block content %}

<div class=\"row\">
    <article class=\"{% if addons|length > 0 %}span6{% else %}span12{% endif %} data-block\">
        <div class=\"data-container\">
            <header>
                <h1>{% trans 'Service details' %}</h1>
                <p>{% trans 'All information about your service' %}</p>
            </header>
            <section>
                <table class=\"table table-striped table-bordered table-condensed\">
                    <tbody>
                    <tr>
                        <td><label>{% trans 'Order' %}</label></td>
                        <td>#{{ order.id }}</td>
                    </tr>

                    <tr>
                        <td><label>{% trans 'Product name' %}</label></td>
                        <td><strong>{{ order.title }}</strong></td>
                    </tr>

                    <tr>
                        <td><label>{% trans 'Payment amount' %}</label></td>
                        <td>{{ order.total | money(order.currency) }}</td>
                    </tr>

                    {% if order.period %}
                    <tr>
                        <td><label>{% trans 'Billing cycle' %}</label></td>
                        <td>{{ order.period | period_title }}</td>
                    </tr>
                    {% endif %}

                    <tr>
                        <td><label>{% trans 'Order status' %}</label></td>
                        <td><span class=\"label {% if order.status == 'active'%}label-success{% elseif order.status == 'pending_setup' %}label-warning{% endif %}\">{{ mf.status_name(order.status) }}</span></td>
                    </tr>

                    <tr>
                        <td><label>{% trans 'Order created' %}</label></td>
                        <td>{{ order.created_at|bb_date }}</td>
                    </tr>

                    <tr>
                        <td><label>{% trans 'Activated at' %}</label></td>
                        <td>{% if order.activated_at %}{{ order.activated_at|bb_date }}{% else %}-{% endif %}</td>
                    </tr>

                    {% if order.period %}
                    <tr>
                        <td><label>{% trans 'Renewal date' %} {% if order.expires_at %} in {{ order.expires_at|daysleft }} day(s) {% endif %}</label></td>
                        <td>{% if order.expires_at %}{{ order.expires_at|bb_date }}{% else %}-{% endif %}</td>
                    </tr>
                    {% endif %}

                    {% if order.notes %}
                    <tr>
                        <td><label>{% trans 'Order notes' %}</label></td>
                        <td>{{ order.notes|bbmd }}</td>
                    </tr>
                    {% endif %}

                    {% if order.active_tickets > 0 %}
                    <tr>
                        <td><label>{% trans 'Active support tickets' %}</label></td>
                        <td>
                            <div class=\"num\"><a href=\"{{ 'support'|link }}\" class=\"redNum\">{{ order.active_tickets }}</a></div>
                            {# <div class=\"num\"><a href=\"{{ 'support'|link }}\" class=\"greenNum\">{{ order.active_tickets }}</a></div> #}
                            {# <div class=\"num\"><a href=\"{{ 'support'|link }}\" class=\"bludNum\">{{ order.active_tickets }}</a></div> #}
                        </td>
                    </tr>
                    {% endif %}
                    </tbody>

                    <tfoot>
                    <tr>
                        <td colspan=\"2\">

                            {% if order.period %}
                            <button class=\"btn btn-primary btn-small\" type=\"button\" id=\"renewal-button\">{% trans 'Renew now' %}</button>
                            {% endif %}

                            {% if upgradables %}
                            <a href=\"#submit-upgrade-ticket\" class=\"btn btn-success btn-small\" type=\"button\" id=\"request-upgrade\" data-toggle=\"modal\">{% trans 'Request Upgrade' %}</a>
                            {% endif %}

                            <a href=\"#submit-ticket\" class=\"btn btn-info btn-small\" type=\"button\" id=\"open-ticket\" data-toggle=\"modal\">{% trans 'Open ticket' %}</a>
                            
                            {% if order.status == 'active' %}
                            <a href=\"#submit-cancellation-ticket\" class=\"btn btn-primary btn-warning btn-small\" type=\"button\" data-toggle=\"modal\">{% trans 'Request Cancellation' %}</a>
                            {% endif %}

                        </td>
                    </tr>
                    </tfoot>
                </table>
                <p><a class=\"btn btn-small\" href=\"{{ '/order/service'|link }}\">{% trans 'Back to services' %}</a></p>
            </section>
        </div>
    </article>
    {% if addons|length < 1 %}</div>{% endif %}

{% if order.group_master == 1 %}
{% if addons|length > 0 %}
    <article class=\"span6 data-block\">
        <div class=\"data-container\">
            <header>
                <h1>{% trans 'Addons' %}</h1>
                <p>{% trans 'Addons you have ordered with this service' %}</p>
            </header>
            <section>

                <table class=\"table table-striped table-bordered table-condensed\">
                    <thead>
                    <tr>
                        <th>{% trans 'Product/Service' %}</th>
                        <th>{% trans 'Price' %}</th>
                        <th>{% trans 'Billing Cycle' %}</th>
                        <th>{% trans 'Next due date' %}</th>
                        <th>{% trans 'Status' %}</th>
                        <th>&nbsp</th>
                    </tr>
                    </thead>
                    <tbody>
                    {% for i, addon in addons %}
                    <tr class=\"{{ cycle(['odd', 'even'], i) }}\">
                        <td>{{addon.title}}</td>
                        <td>{{ addon.total | money(addon.currency) }}</td>
                        <td>{{ addon.period | period_title }}</td>
                        <td>{% if addon.expires_at %}{{addon.expires_at|bb_date }}{% else %}-{% endif %}</td>
                        <td><span class=\"label {% if addon.status == 'active'%}label-success{% elseif addon.status == 'pending_setup' %}label-warning{% endif %}\">{{ mf.status_name(addon.status) }}</span></td>
                        <td class=\"actions\"><a class=\"bb-button\" href=\"{{ '/order/service/manage'|link }}/{{addon.id}}\"><span class=\"dark-icon i-drag\"></span></a></td>
                    </tr>
                    {% endfor %}
                    </tbody>
                </table>
            </section>
</div>
</article>
</div>
{% endif %}
{% endif %}


<div id=\"submit-ticket\" class=\"modal hide fade\" tabindex=\"-1\" role=\"dialog\" aria-labelledby=\"myModalLabel\" aria-hidden=\"true\">
    <div class=\"modal-header\">
        <button type=\"button\" class=\"close\" data-dismiss=\"modal\" aria-hidden=\"true\">&times;</button>
        <h3>
            {% trans 'Submit new support ticket' %}
        </h3>
    </div>
    <div class=\"modal-body\">
        <form action=\"\" method=\"post\" style=\"background: none\" class=\"open-ticket\">
            <fieldset>
                <div class=\"control-group\">
                    <label>{% trans 'Help desk' %}: </label>
                    <div class=\"controls\">
                    {{ mf.selectbox('support_helpdesk_id', client.support_helpdesk_get_pairs, request.support_helpdesk_id, 1) }}
                    </div>
                </div>

                <div class=\"control-group\">
                    <label>{% trans 'Subject' %}: </label>
                    <div class=\"controls\">
                        <input type=\"text\" name=\"subject\" value=\"{{ request.subject|e }}\" required=\"required\" class=\"span5\"/>
                    </div>
                </div>

                <div class=\"control-group\">
                    <label>{% trans 'Message' %}: </label>
                    <div class=\"controls\">
                        <textarea name=\"content\" cols=\"5\" rows=\"5\" required=\"required\"  class=\"span5\">{{ request.content|e }}</textarea>
                    </div>
                </div>

                <input type=\"hidden\" name=\"rel_type\" value=\"order\">
                <input type=\"hidden\" name=\"rel_id\" value=\"{{ order.id }}\">
            </fieldset>


    </div>
    <div class=\"modal-footer\">
        <input class=\"btn btn-primary btn-large\" type=\"submit\" value=\"{% trans 'Submit' %}\">
    </div>
    </form>
</div>

<div id=\"submit-cancellation-ticket\" class=\"modal hide fade open-ticket\" tabindex=\"-1\" role=\"dialog\" aria-labelledby=\"myModalLabel\" aria-hidden=\"true\">
    <div class=\"modal-header\">
        <button type=\"button\" class=\"close\" data-dismiss=\"modal\" aria-hidden=\"true\">&times;</button>
        <h3>
            {% trans 'Submit cancellation request' %}
        </h3>
    </div>
    <div class=\"modal-body\">
        <form action=\"\" method=\"post\" style=\"background:none\" class=\"request-cancellation\">
            <div class=\"control-group\">
                <label>{% trans 'Help desk' %}: </label>
                <div class=\"controls\">
                    {{ mf.selectbox('support_helpdesk_id', client.support_helpdesk_get_pairs, request.support_helpdesk_id, 1) }}
                </div>
            </div>

            <div class=\"control-group\">
                <label>{% trans 'Subject' %}: </label>
                <div class=\"controls\">
                    <input type=\"text\" name=\"subject\" value=\"{% trans 'I would like to cancel' %} {{ order.title }}\" required=\"required\" class=\"span5\"/>
                </div>
            </div>

            <div class=\"control-group\">
                <label>{% trans 'Reason' %}: </label>
                <div class=\"controls\">
                    <textarea name=\"content\" cols=\"5\" rows=\"5\" required=\"required\" class=\"span5\">{{ request.content|e }}</textarea>
                </div>
            </div>
                <input type=\"hidden\" name=\"rel_type\" value=\"order\">
                <input type=\"hidden\" name=\"rel_id\" value=\"{{ order.id }}\">
                <input type=\"hidden\" name=\"rel_task\" value=\"cancel\">
    </div>
    <div class=\"modal-footer\">
        <input class=\"btn btn-primary btn-large\" type=\"submit\" value=\"{% trans 'Submit' %}\">
    </div>
    </form>
</div>

<div id=\"submit-upgrade-ticket\" class=\"modal hide fade open-ticket\" tabindex=\"-1\" role=\"dialog\" aria-labelledby=\"myModalLabel\" aria-hidden=\"true\">
    <div class=\"modal-header\">
        <button type=\"button\" class=\"close\" data-dismiss=\"modal\" aria-hidden=\"true\">&times;</button>
        <h3>
            {% trans 'Submit upgrade request' %}
        </h3>
    </div>
    <div class=\"modal-body\">
        <form action=\"\" method=\"post\" class=\"request-upgrade\" data-api-url=\"client/support/ticket_create\" data-api-msg=\"Upgrade request received\" style=\"background: none\">
            <fieldset>
                <div class=\"control-group\">
                    <label>{% trans 'Subject' %}: </label>
                    <div class=\"controls\">
                        <input type=\"text\" name=\"subject\" value=\"{% trans 'I would like to upgrade' %} {{ order.title }}\" required=\"required\" class=\"span5\"/>
                    </div>
                </div>

                <div class=\"control-group\">
                    <label>{% trans 'Help desk' %}: </label>
                    <div class=\"controls\">
                        {{ mf.selectbox('support_helpdesk_id', client.support_helpdesk_get_pairs, request.support_helpdesk_id, 1) }}
                    </div>
                </div>


                <div class=\"control-group\">
                    <label>{% trans 'Upgrade to' %}: </label>
                    <div class=\"controls\">
                        {{ mf.selectbox('rel_new_value', upgradables, '', 1) }}
                    </div>
                </div>

                <div class=\"control-group\">
                    <label>{% trans 'Notes' %}: </label>
                    <div class=\"controls\">
                        <textarea name=\"content\" cols=\"5\" rows=\"5\" placeholder=\"Your comment\" class=\"span5\">{% trans 'I would like to upgrade' %} {{ order.title }}</textarea>
                    </div>
                </div>

                <input type=\"hidden\" name=\"rel_type\" value=\"order\">
                <input type=\"hidden\" name=\"rel_id\" value=\"{{ order.id }}\">
                <input type=\"hidden\" name=\"rel_task\" value=\"upgrade\">
            </fieldset>
    </div>
    <div class=\"modal-footer\">
        <input class=\"btn btn-primary btn-large\" type=\"submit\" value=\"{% trans 'Request' %}\">
    </div>
    </form>
</div>

{% if guest.system_template_exists({\"file\":service_partial})%}
    {% set service = client.order_service({\"id\":order.id}) %}
    {% include service_partial with {'order': order, 'service': service} %}
{% else %}
    {# trans 'Service does not have configuration page' #}
{% endif %}

{% endblock %}

{% block js %}
{% autoescape \"js\" %}
<script type=\"text/javascript\">
\$(function() {
    \$('#renewal-button').click(function(e){
            e.preventDefault();
        if(confirm(\"This will generate new invoice. Are you sure you want to continue?\")) {
            bb.post(
                'client/invoice/renewal_invoice',
                {order_id: {{order.id}} },
                function(result) {
                    bb.redirect('{{\"invoice\"|link}}' + '/' + result);
                }
            );
    }
    });

       \$('.open-ticket').submit(function(){
       \$('.wait').show();
        bb.post(
            'client/support/ticket_create',
            \$(this).serialize(),
            function(result) {
                \$('#submit-ticket').modal('hide')
                bb.msg('Ticket was submitted. If you want to track conversation please go to support section');
                \$('.wait').hide();
            }
        );
        return false;
    });

    \$('.request-cancellation').submit(function(){
        \$('.wait').show();
        bb.post(
            'client/support/ticket_create',
            \$(this).serialize(),
            function(result) {
                \$('#submit-cancellation-ticket').modal('hide')
                bb.msg('Service cancellation request received');
                \$('.wait').hide();
            }
        );
        return false;
    });

    \$('.request-upgrade').submit(function(){
        \$('.wait').show();
        bb.post(
            'client/support/ticket_create',
            \$(this).serialize(),
            function(result) {
                \$('#submit-upgrade-ticket').modal('hide')
                bb.msg('Service upgrade request received');
                \$('.wait').hide();
            }
        );
        return false;
    });
});
</script>
{% endautoescape %}
{% endblock %}", "mod_order_manage.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-modules/Order/html_client/mod_order_manage.phtml");
    }
}
