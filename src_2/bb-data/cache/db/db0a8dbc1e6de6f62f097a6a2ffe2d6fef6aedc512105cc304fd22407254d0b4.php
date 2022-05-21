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

/* mod_support_tickets.phtml */
class __TwigTemplate_25bdc6367c5d898af26aa7e9516ed9c65200b9e3fcff1eaf8f1a842c90e44133 extends Template
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
            'js' => [$this, 'block_js'],
        ];
    }

    protected function doGetParent(array $context)
    {
        // line 1
        return $this->loadTemplate(((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ajax", [], "any", false, false, false, 1)) ? ("layout_blank.phtml") : ("layout_default.phtml")), "mod_support_tickets.phtml", 1);
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 3
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_support_tickets.phtml", 3)->unwrap();
        // line 7
        $context["active_menu"] = "support";
        // line 1
        $this->getParent($context)->display($context, array_merge($this->blocks, $blocks));
    }

    // line 5
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo twig_escape_filter($this->env, gettext("Support tickets"), "html", null, true);
    }

    // line 9
    public function block_top_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 10
        echo "
";
        // line 11
        if (twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "show_filter", [], "any", false, false, false, 11)) {
            // line 12
            echo "<div class=\"widget filterWidget\">
    <div class=\"head\"><h5 class=\"iMagnify\">";
            // line 13
            echo twig_escape_filter($this->env, gettext("Filter support tickets"), "html", null, true);
            echo "</h5></div>
    <div class=\"body nopadding\">

        <form method=\"get\" action=\"\" class=\"mainForm\">
            <input type=\"hidden\" name=\"_url\" value=\"";
            // line 17
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "_url", [], "any", false, false, false, 17), "html", null, true);
            echo "\" />
            <fieldset>
                <div class=\"rowElem noborder\">
                    <label>";
            // line 20
            echo twig_escape_filter($this->env, gettext("Client ID"), "html", null, true);
            echo "</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"client_id\" value=\"";
            // line 22
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "client_id", [], "any", false, false, false, 22), "html", null, true);
            echo "\">
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>";
            // line 28
            echo twig_escape_filter($this->env, gettext("Order ID"), "html", null, true);
            echo "</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"order_id\" value=\"";
            // line 30
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "order_id", [], "any", false, false, false, 30), "html", null, true);
            echo "\">
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>";
            // line 36
            echo twig_escape_filter($this->env, gettext("Ticket subject"), "html", null, true);
            echo "</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"subject\" value=\"";
            // line 38
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "subject", [], "any", false, false, false, 38), "html", null, true);
            echo "\">
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>";
            // line 44
            echo twig_escape_filter($this->env, gettext("Ticket messages"), "html", null, true);
            echo "</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"content\" value=\"";
            // line 46
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "content", [], "any", false, false, false, 46), "html", null, true);
            echo "\">
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>";
            // line 52
            echo twig_escape_filter($this->env, gettext("Priority"), "html", null, true);
            echo "</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"priority\" value=\"";
            // line 54
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "priority", [], "any", false, false, false, 54), "html", null, true);
            echo "\">
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>";
            // line 60
            echo twig_escape_filter($this->env, gettext("Status"), "html", null, true);
            echo "</label>
                        <div class=\"formRight\">
                            ";
            // line 62
            echo twig_call_macro($macros["mf"], "macro_selectbox", ["status", twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "support_ticket_get_statuses", [0 => ["titles" => 1]], "method", false, false, false, 62), twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "status", [], "any", false, false, false, 62), 0, "All statuses"], 62, $context, $this->getSourceContext());
            echo "
                        </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>";
            // line 68
            echo twig_escape_filter($this->env, gettext("Help desk"), "html", null, true);
            echo ":</label>
                    <div class=\"formRight\">
                            ";
            // line 70
            echo twig_call_macro($macros["mf"], "macro_selectbox", ["support_helpdesk_id", twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "support_helpdesk_get_pairs", [], "any", false, false, false, 70), twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "support_helpdesk_id", [], "any", false, false, false, 70), 0, "All help desks"], 70, $context, $this->getSourceContext());
            echo "
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>";
            // line 76
            echo twig_escape_filter($this->env, gettext("Created at"), "html", null, true);
            echo "</label>
                    <div class=\"formRight moreFields\">
                        <ul>
                            <li style=\"width: 100px\"><input type=\"text\" name=\"date_from\" value=\"";
            // line 79
            if (twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "date_from", [], "any", false, false, false, 79)) {
                echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "date_from", [], "any", false, false, false, 79), "Y-m-d"), "html", null, true);
            }
            echo "\" placeholder=\"";
            echo twig_escape_filter($this->env, twig_date_format_filter($this->env, ($context["now"] ?? null), "Y-m-d"), "html", null, true);
            echo "\" class=\"datepicker\"/></li>
                            <li class=\"sep\">-</li>
                            <li style=\"width: 100px\"><input type=\"text\" name=\"date_to\" value=\"";
            // line 81
            if (twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "date_to", [], "any", false, false, false, 81)) {
                echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "date_to", [], "any", false, false, false, 81), "Y-m-d"), "html", null, true);
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
            // line 88
            echo twig_escape_filter($this->env, gettext("Filter"), "html", null, true);
            echo "\" class=\"greyishBtn submitForm\" />
            </fieldset>
        </form>
        <div class=\"fix\"></div>
    </div>
</div>
";
        } else {
            // line 95
            $context["statuses"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "support_ticket_get_statuses", [], "any", false, false, false, 95);
            // line 96
            echo "<div class=\"stats\">
    <ul>
        <li>
            <a href=\"";
            // line 99
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support", ["status" => "open"]);
            echo "\" class=\"count green\" title=\"\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["statuses"] ?? null), "open", [], "any", false, false, false, 99), "html", null, true);
            echo "</a>
            <span>";
            // line 100
            echo twig_escape_filter($this->env, gettext("Tickets waiting for staff reply"), "html", null, true);
            echo "</span>
        </li>
        <li>
            <a href=\"";
            // line 103
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support", ["status" => "on_hold"]);
            echo "\" class=\"count blue\" title=\"\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["statuses"] ?? null), "on_hold", [], "any", false, false, false, 103), "html", null, true);
            echo "</a>
            <span>";
            // line 104
            echo twig_escape_filter($this->env, gettext("Tickets waiting for client reply"), "html", null, true);
            echo "</span>
        </li>
        <li>
            <a href=\"";
            // line 107
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support", ["status" => "closed"]);
            echo "\" class=\"count grey\" title=\"\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["statuses"] ?? null), "closed", [], "any", false, false, false, 107), "html", null, true);
            echo "</a>
            <span>";
            // line 108
            echo twig_escape_filter($this->env, gettext("Solved tickets"), "html", null, true);
            echo "</span>
        </li>
        <li class=\"last\">
            <a href=\"";
            // line 111
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support");
            echo "\" class=\"count grey\" title=\"\">";
            echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["statuses"] ?? null), "open", [], "any", false, false, false, 111) + twig_get_attribute($this->env, $this->source, ($context["statuses"] ?? null), "on_hold", [], "any", false, false, false, 111)) + twig_get_attribute($this->env, $this->source, ($context["statuses"] ?? null), "closed", [], "any", false, false, false, 111)), "html", null, true);
            echo "</a>
            <span>";
            // line 112
            echo twig_escape_filter($this->env, gettext("Total"), "html", null, true);
            echo "</span>
        </li>
    </ul>
    <div class=\"fix\"></div>
</div>
";
        }
        // line 118
        echo "
";
    }

    // line 121
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 122
        echo "<div class=\"widget simpleTabs\">
    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">";
        // line 124
        echo twig_escape_filter($this->env, gettext("Support tickets"), "html", null, true);
        echo "</a></li>
        <li><a href=\"#tab-new\">";
        // line 125
        echo twig_escape_filter($this->env, gettext("New ticket"), "html", null, true);
        echo "</a></li>
        <li><a href=\"#tab-email\">";
        // line 126
        echo twig_escape_filter($this->env, gettext("New email"), "html", null, true);
        echo "</a></li>
    </ul>

    <div class=\"tabs_container\">
        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-index\">
            ";
        // line 132
        echo twig_call_macro($macros["mf"], "macro_table_search", [], 132, $context, $this->getSourceContext());
        echo "
            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td style=\"width: 2%\"><input type=\"checkbox\" class=\"batch-delete-master-checkbox\"/></td>
                        <td colspan=\"2\">";
        // line 137
        echo twig_escape_filter($this->env, gettext("Client"), "html", null, true);
        echo "</td>
                        <td>";
        // line 138
        echo twig_escape_filter($this->env, gettext("Subject"), "html", null, true);
        echo "</td>
                        <td>";
        // line 139
        echo twig_escape_filter($this->env, gettext("Status"), "html", null, true);
        echo "</td>
                        <td>";
        // line 140
        echo twig_escape_filter($this->env, gettext("Helpdesk"), "html", null, true);
        echo "</td>
                        <td style=\"width: 13%\">&nbsp;</td>
                    </tr>
                </thead>

                <tbody>
                    ";
        // line 146
        $context["tickets"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "support_ticket_get_list", [0 => twig_array_merge(["per_page" => 30, "page" => twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 146)], ($context["request"] ?? null))], "method", false, false, false, 146);
        // line 147
        echo "                    ";
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["tickets"] ?? null), "list", [], "any", false, false, false, 147));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["i"] => $context["ticket"]) {
            // line 148
            echo "                    <tr class=\"priority_";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "priority", [], "any", false, false, false, 148), "html", null, true);
            echo "\">
                        <td><input type=\"checkbox\" class=\"batch-delete-checkbox\" data-item-id=\"";
            // line 149
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "id", [], "any", false, false, false, 149), "html", null, true);
            echo "\"/></td>
                        <td style=\"width:5%\">
                            <a href=\"";
            // line 151
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("client/manage");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "client_id", [], "any", false, false, false, 151), "html", null, true);
            echo "\"><img src=\"";
            echo twig_escape_filter($this->env, twig_gravatar_filter(twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["ticket"], "client", [], "any", false, false, false, 151), "email", [], "any", false, false, false, 151)), "html", null, true);
            echo "?size=20\" alt=\"gravatar\" /></a>
                        </td>
                        <td style=\"width:20%\">
                            <a href=\"";
            // line 154
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("client/manage");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "client_id", [], "any", false, false, false, 154), "html", null, true);
            echo "\">";
            echo twig_escape_filter($this->env, twig_truncate_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["ticket"], "client", [], "any", false, false, false, 154), "first_name", [], "any", false, false, false, 154), 1, null, "."), "html", null, true);
            echo " ";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["ticket"], "client", [], "any", false, false, false, 154), "last_name", [], "any", false, false, false, 154), "html", null, true);
            echo "</a>
                        </td>
                        <td>
                            <a href=\"";
            // line 157
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support/ticket");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "id", [], "any", false, false, false, 157), "html", null, true);
            echo "#reply-box\">#";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "id", [], "any", false, false, false, 157), "html", null, true);
            echo " - ";
            echo twig_escape_filter($this->env, twig_truncate_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "subject", [], "any", false, false, false, 157), 50), "html", null, true);
            echo " (";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "replies", [], "any", false, false, false, 157), "html", null, true);
            echo ")</a>
                            <br/>
                            ";
            // line 159
            if (twig_length_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "notes", [], "any", false, false, false, 159))) {
                // line 160
                echo "                            <a href=\"#\" rel=\"";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "id", [], "any", false, false, false, 160), "html", null, true);
                echo "\" title=\"";
                echo twig_escape_filter($this->env, twig_length_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "notes", [], "any", false, false, false, 160)), "html", null, true);
                echo "\" class=\"show-notes\">
                                <img src=\"images/icons/dark/notebook.png\" alt=\"\" />
                            </a>
                            ";
            }
            // line 164
            echo "                            ";
            echo twig_escape_filter($this->env, twig_timeago_filter(twig_get_attribute($this->env, $this->source, $context["ticket"], "updated_at", [], "any", false, false, false, 164)), "html", null, true);
            echo " ";
            echo twig_escape_filter($this->env, gettext("ago"), "html", null, true);
            echo "
                        </td>
                        <td>";
            // line 166
            echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, $context["ticket"], "status", [], "any", false, false, false, 166)], 166, $context, $this->getSourceContext());
            echo "</td>
                        <td>";
            // line 167
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["ticket"], "helpdesk", [], "any", false, false, false, 167), "name", [], "any", false, false, false, 167), "html", null, true);
            echo "</td>
                        <td class=\"actions\">
                            <a class=\"bb-button btn14\" href=\"";
            // line 169
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/support/ticket");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "id", [], "any", false, false, false, 169), "html", null, true);
            echo "#reply-box\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                            <a class=\"bb-button btn14 bb-rm-tr api-link\" data-api-confirm=\"Are you sure?\" data-api-redirect=\"";
            // line 170
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support");
            echo "\" href=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/support/ticket_delete", ["id" => twig_get_attribute($this->env, $this->source, $context["ticket"], "id", [], "any", false, false, false, 170)]);
            echo "\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                        </td>
                    </tr>
                    ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 174
            echo "                    <tr>
                        <td colspan=\"6\">";
            // line 175
            echo twig_escape_filter($this->env, gettext("The list is empty"), "html", null, true);
            echo "</td>
                    </tr>
                    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['i'], $context['ticket'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 178
        echo "                </tbody>
            </table>
            ";
        // line 180
        $this->loadTemplate("partial_batch_delete.phtml", "mod_support_tickets.phtml", 180)->display(twig_array_merge($context, ["action" => "admin/support/batch_delete"]));
        // line 181
        echo "            ";
        $this->loadTemplate("partial_pagination.phtml", "mod_support_tickets.phtml", 181)->display(twig_array_merge($context, ["list" => ($context["tickets"] ?? null), "url" => "support"]));
        // line 182
        echo "
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-new\">
            <div class=\"help\">
                <h3>";
        // line 187
        echo twig_escape_filter($this->env, gettext("Open ticket for existing client"), "html", null, true);
        echo "</h3>
            </div>

            <form method=\"post\" action=\"";
        // line 190
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/support/ticket_create");
        echo "\" class=\"mainForm api-form\" data-api-redirect=\"";
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support");
        echo "\">
                <fieldset>
                    ";
        // line 192
        if ( !twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "client_id", [], "any", false, false, false, 192)) {
            // line 193
            echo "                    <div class=\"rowElem noborder\">
                        <label>";
            // line 194
            echo twig_escape_filter($this->env, gettext("Client"), "html", null, true);
            echo "</label>
                        <div class=\"formRight noborder\">
                            <input type=\"text\" id=\"client_selector\" placeholder=\"";
            // line 196
            echo twig_escape_filter($this->env, gettext("Start typing clients name, email or ID"), "html", null, true);
            echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    ";
        }
        // line 201
        echo "                    <div class=\"rowElem\">
                        <label>";
        // line 202
        echo twig_escape_filter($this->env, gettext("Help desk"), "html", null, true);
        echo "</label>
                        <div class=\"formRight\">
                            ";
        // line 204
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["support_helpdesk_id", twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "support_helpdesk_get_pairs", [], "any", false, false, false, 204), "", 1], 204, $context, $this->getSourceContext());
        echo "
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 210
        echo twig_escape_filter($this->env, gettext("Subject"), "html", null, true);
        echo "</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"subject\" value=\"";
        // line 212
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "subject", [], "any", false, false, false, 212), "html", null, true);
        echo "\" required=\"required\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 218
        echo twig_escape_filter($this->env, gettext("Message"), "html", null, true);
        echo "</label>
                        <div class=\"formRight\">
                            <textarea name=\"content\" cols=\"5\" rows=\"10\" required=\"required\" id=\"msg-area-";
        // line 220
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["message"] ?? null), "id", [], "any", false, false, false, 220), "html", null, true);
        echo "\">
                                ";
        // line 221
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "signature", [], "any", false, false, false, 221), "html", null, true);
        echo "
                            </textarea>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <input type=\"hidden\" name=\"client_id\" value=\"";
        // line 227
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "client_id", [], "any", false, false, false, 227), "html", null, true);
        echo "\" id=\"client_id\"/>
                    <input type=\"submit\" value=\"";
        // line 228
        echo twig_escape_filter($this->env, gettext("Create"), "html", null, true);
        echo "\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-email\">
            <div class=\"help\">
                <h3>";
        // line 235
        echo twig_escape_filter($this->env, gettext("Open public ticket for non client"), "html", null, true);
        echo "</h3>
            </div>

            <form method=\"post\" action=\"";
        // line 238
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/support/public_ticket_create");
        echo "\" class=\"mainForm api-form\" data-api-jsonp=\"onAfterPublicTicketCreate\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>";
        // line 241
        echo twig_escape_filter($this->env, gettext("Receivers name"), "html", null, true);
        echo "</label>
                        <div class=\"formRight noborder\">
                            <input type=\"text\" name=\"name\" required=\"required\" placeholder=\"";
        // line 243
        echo twig_escape_filter($this->env, gettext("John Smith"), "html", null, true);
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <div class=\"rowElem\">
                        <label>";
        // line 249
        echo twig_escape_filter($this->env, gettext("Email"), "html", null, true);
        echo "</label>
                        <div class=\"formRight noborder\">
                            <input type=\"text\" name=\"email\" required=\"required\" placeholder=\"";
        // line 251
        echo twig_escape_filter($this->env, gettext("to@gmail.com"), "html", null, true);
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 257
        echo twig_escape_filter($this->env, gettext("Subject"), "html", null, true);
        echo "</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"subject\" value=\"";
        // line 259
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "subject", [], "any", false, false, false, 259), "html", null, true);
        echo "\" required=\"required\" placeholder=\"";
        echo twig_escape_filter($this->env, gettext("Email subject"), "html", null, true);
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 265
        echo twig_escape_filter($this->env, gettext("Message"), "html", null, true);
        echo "</label>
                        <div class=\"formRight\">
                            <textarea name=\"message\" cols=\"5\" rows=\"10\" required=\"required\">
                                ";
        // line 268
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "signature", [], "any", false, false, false, 268), "html", null, true);
        echo "
                            </textarea>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <input type=\"hidden\" name=\"client_id\" value=\"";
        // line 274
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "client_id", [], "any", false, false, false, 274), "html", null, true);
        echo "\" id=\"client_id\"/>
                    <input type=\"submit\" value=\"";
        // line 275
        echo twig_escape_filter($this->env, gettext("Create"), "html", null, true);
        echo "\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>
    </div>
</div>
";
    }

    // line 283
    public function block_js($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 284
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

    \$('a.show-notes').click(function() {
        bb.post('admin/support/ticket_get', { id: \$(this).attr('rel') }, function(result){
            var html = \$('<div>');
            \$.each(result.notes, function(i, v){
                html.append(\$('<div>').html(v.note));
                html.append(\$('<hr>'));
            });
            jAlert(html, \"";
        // line 321
        echo twig_escape_filter($this->env, gettext("Notes"), "html", null, true);
        echo "\");
        });
        
        return false;
    });
});

function onAfterPublicTicketCreate(result) {
    bb.redirect(\"";
        // line 329
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support/public-ticket");
        echo "/\" + result);
}
</script>
";
    }

    public function getTemplateName()
    {
        return "mod_support_tickets.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  700 => 329,  689 => 321,  650 => 284,  646 => 283,  635 => 275,  631 => 274,  622 => 268,  616 => 265,  605 => 259,  600 => 257,  591 => 251,  586 => 249,  577 => 243,  572 => 241,  566 => 238,  560 => 235,  550 => 228,  546 => 227,  537 => 221,  533 => 220,  528 => 218,  519 => 212,  514 => 210,  505 => 204,  500 => 202,  497 => 201,  489 => 196,  484 => 194,  481 => 193,  479 => 192,  472 => 190,  466 => 187,  459 => 182,  456 => 181,  454 => 180,  450 => 178,  441 => 175,  438 => 174,  427 => 170,  421 => 169,  416 => 167,  412 => 166,  404 => 164,  394 => 160,  392 => 159,  379 => 157,  367 => 154,  357 => 151,  352 => 149,  347 => 148,  341 => 147,  339 => 146,  330 => 140,  326 => 139,  322 => 138,  318 => 137,  310 => 132,  301 => 126,  297 => 125,  293 => 124,  289 => 122,  285 => 121,  280 => 118,  271 => 112,  265 => 111,  259 => 108,  253 => 107,  247 => 104,  241 => 103,  235 => 100,  229 => 99,  224 => 96,  222 => 95,  212 => 88,  198 => 81,  189 => 79,  183 => 76,  174 => 70,  169 => 68,  160 => 62,  155 => 60,  146 => 54,  141 => 52,  132 => 46,  127 => 44,  118 => 38,  113 => 36,  104 => 30,  99 => 28,  90 => 22,  85 => 20,  79 => 17,  72 => 13,  69 => 12,  67 => 11,  64 => 10,  60 => 9,  53 => 5,  49 => 1,  47 => 7,  45 => 3,  38 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}

{% import \"macro_functions.phtml\" as mf %}

{% block meta_title %}{{ 'Support tickets'|trans }}{% endblock %}

{% set active_menu = 'support' %}

{% block top_content %}

{% if request.show_filter %}
<div class=\"widget filterWidget\">
    <div class=\"head\"><h5 class=\"iMagnify\">{{ 'Filter support tickets'|trans }}</h5></div>
    <div class=\"body nopadding\">

        <form method=\"get\" action=\"\" class=\"mainForm\">
            <input type=\"hidden\" name=\"_url\" value=\"{{ request._url }}\" />
            <fieldset>
                <div class=\"rowElem noborder\">
                    <label>{{ 'Client ID'|trans }}</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"client_id\" value=\"{{ request.client_id }}\">
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>{{ 'Order ID'|trans }}</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"order_id\" value=\"{{ request.order_id }}\">
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>{{ 'Ticket subject'|trans }}</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"subject\" value=\"{{ request.subject }}\">
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>{{ 'Ticket messages'|trans }}</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"content\" value=\"{{ request.content }}\">
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>{{ 'Priority'|trans }}</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"priority\" value=\"{{ request.priority }}\">
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>{{ 'Status'|trans }}</label>
                        <div class=\"formRight\">
                            {{ mf.selectbox('status', admin.support_ticket_get_statuses({\"titles\":1}), request.status, 0, \"All statuses\") }}
                        </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>{{ 'Help desk'|trans }}:</label>
                    <div class=\"formRight\">
                            {{ mf.selectbox('support_helpdesk_id', admin.support_helpdesk_get_pairs, request.support_helpdesk_id, 0, \"All help desks\") }}
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>{{ 'Created at'|trans }}</label>
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
                <input type=\"submit\" value=\"{{ 'Filter'|trans }}\" class=\"greyishBtn submitForm\" />
            </fieldset>
        </form>
        <div class=\"fix\"></div>
    </div>
</div>
{% else %}
{% set statuses = admin.support_ticket_get_statuses %}
<div class=\"stats\">
    <ul>
        <li>
            <a href=\"{{ 'support'|alink({'status' : 'open'}) }}\" class=\"count green\" title=\"\">{{ statuses.open }}</a>
            <span>{{ 'Tickets waiting for staff reply'|trans }}</span>
        </li>
        <li>
            <a href=\"{{ 'support'|alink({'status' : 'on_hold'}) }}\" class=\"count blue\" title=\"\">{{ statuses.on_hold }}</a>
            <span>{{ 'Tickets waiting for client reply'|trans }}</span>
        </li>
        <li>
            <a href=\"{{ 'support'|alink({'status' : 'closed'}) }}\" class=\"count grey\" title=\"\">{{ statuses.closed }}</a>
            <span>{{ 'Solved tickets'|trans }}</span>
        </li>
        <li class=\"last\">
            <a href=\"{{ 'support'|alink }}\" class=\"count grey\" title=\"\">{{ statuses.open + statuses.on_hold + statuses.closed }}</a>
            <span>{{ 'Total'|trans }}</span>
        </li>
    </ul>
    <div class=\"fix\"></div>
</div>
{% endif %}

{% endblock %}

{% block content %}
<div class=\"widget simpleTabs\">
    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">{{ 'Support tickets'|trans }}</a></li>
        <li><a href=\"#tab-new\">{{ 'New ticket'|trans }}</a></li>
        <li><a href=\"#tab-email\">{{ 'New email'|trans }}</a></li>
    </ul>

    <div class=\"tabs_container\">
        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-index\">
            {{ mf.table_search }}
            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td style=\"width: 2%\"><input type=\"checkbox\" class=\"batch-delete-master-checkbox\"/></td>
                        <td colspan=\"2\">{{ 'Client'|trans }}</td>
                        <td>{{ 'Subject'|trans }}</td>
                        <td>{{ 'Status'|trans }}</td>
                        <td>{{ 'Helpdesk'|trans }}</td>
                        <td style=\"width: 13%\">&nbsp;</td>
                    </tr>
                </thead>

                <tbody>
                    {% set tickets = admin.support_ticket_get_list({\"per_page\":30, \"page\":request.page}|merge(request)) %}
                    {% for i, ticket in tickets.list %}
                    <tr class=\"priority_{{ ticket.priority }}\">
                        <td><input type=\"checkbox\" class=\"batch-delete-checkbox\" data-item-id=\"{{ ticket.id }}\"/></td>
                        <td style=\"width:5%\">
                            <a href=\"{{ 'client/manage'|alink }}/{{ ticket.client_id }}\"><img src=\"{{ ticket.client.email|gravatar }}?size=20\" alt=\"gravatar\" /></a>
                        </td>
                        <td style=\"width:20%\">
                            <a href=\"{{ 'client/manage'|alink }}/{{ ticket.client_id }}\">{{ ticket.client.first_name|truncate(1, null, '.') }} {{ ticket.client.last_name }}</a>
                        </td>
                        <td>
                            <a href=\"{{ 'support/ticket'|alink }}/{{ticket.id}}#reply-box\">#{{ ticket.id }} - {{ ticket.subject|truncate(50) }} ({{ ticket.replies }})</a>
                            <br/>
                            {% if ticket.notes|length %}
                            <a href=\"#\" rel=\"{{ ticket.id }}\" title=\"{{ ticket.notes|length }}\" class=\"show-notes\">
                                <img src=\"images/icons/dark/notebook.png\" alt=\"\" />
                            </a>
                            {% endif %}
                            {{ ticket.updated_at|timeago }} {{ 'ago'|trans }}
                        </td>
                        <td>{{ mf.status_name(ticket.status) }}</td>
                        <td>{{ ticket.helpdesk.name }}</td>
                        <td class=\"actions\">
                            <a class=\"bb-button btn14\" href=\"{{ '/support/ticket'|alink }}/{{ticket.id}}#reply-box\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                            <a class=\"bb-button btn14 bb-rm-tr api-link\" data-api-confirm=\"Are you sure?\" data-api-redirect=\"{{ 'support'|alink }}\" href=\"{{ 'api/admin/support/ticket_delete'|link({'id' : ticket.id}) }}\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                        </td>
                    </tr>
                    {% else %}
                    <tr>
                        <td colspan=\"6\">{{ 'The list is empty'|trans }}</td>
                    </tr>
                    {% endfor %}
                </tbody>
            </table>
            {% include \"partial_batch_delete.phtml\" with { 'action': 'admin/support/batch_delete' } %}
            {% include \"partial_pagination.phtml\" with { 'list': tickets, 'url': 'support' } %}

        </div>

        <div class=\"tab_content nopadding\" id=\"tab-new\">
            <div class=\"help\">
                <h3>{{ 'Open ticket for existing client'|trans }}</h3>
            </div>

            <form method=\"post\" action=\"{{ 'api/admin/support/ticket_create'|link }}\" class=\"mainForm api-form\" data-api-redirect=\"{{ 'support'|alink }}\">
                <fieldset>
                    {% if not request.client_id %}
                    <div class=\"rowElem noborder\">
                        <label>{{ 'Client'|trans }}</label>
                        <div class=\"formRight noborder\">
                            <input type=\"text\" id=\"client_selector\" placeholder=\"{{ 'Start typing clients name, email or ID'|trans }}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    {% endif %}
                    <div class=\"rowElem\">
                        <label>{{ 'Help desk'|trans }}</label>
                        <div class=\"formRight\">
                            {{ mf.selectbox('support_helpdesk_id', admin.support_helpdesk_get_pairs, '', 1) }}
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{{ 'Subject'|trans }}</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"subject\" value=\"{{ticket.subject}}\" required=\"required\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{{ 'Message'|trans }}</label>
                        <div class=\"formRight\">
                            <textarea name=\"content\" cols=\"5\" rows=\"10\" required=\"required\" id=\"msg-area-{{message.id}}\">
                                {{ profile.signature }}
                            </textarea>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <input type=\"hidden\" name=\"client_id\" value=\"{{ request.client_id }}\" id=\"client_id\"/>
                    <input type=\"submit\" value=\"{{ 'Create'|trans }}\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-email\">
            <div class=\"help\">
                <h3>{{ 'Open public ticket for non client'|trans }}</h3>
            </div>

            <form method=\"post\" action=\"{{ 'api/admin/support/public_ticket_create'|link }}\" class=\"mainForm api-form\" data-api-jsonp=\"onAfterPublicTicketCreate\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>{{ 'Receivers name'|trans }}</label>
                        <div class=\"formRight noborder\">
                            <input type=\"text\" name=\"name\" required=\"required\" placeholder=\"{{ 'John Smith'|trans }}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <div class=\"rowElem\">
                        <label>{{ 'Email'|trans }}</label>
                        <div class=\"formRight noborder\">
                            <input type=\"text\" name=\"email\" required=\"required\" placeholder=\"{{ 'to@gmail.com'|trans }}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{{ 'Subject'|trans }}</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"subject\" value=\"{{ ticket.subject }}\" required=\"required\" placeholder=\"{{ 'Email subject'|trans }}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{{ 'Message'|trans }}</label>
                        <div class=\"formRight\">
                            <textarea name=\"message\" cols=\"5\" rows=\"10\" required=\"required\">
                                {{ profile.signature }}
                            </textarea>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <input type=\"hidden\" name=\"client_id\" value=\"{{ request.client_id }}\" id=\"client_id\"/>
                    <input type=\"submit\" value=\"{{ 'Create'|trans }}\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>
    </div>
</div>
{% endblock %}

{% block js %}
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

    \$('a.show-notes').click(function() {
        bb.post('admin/support/ticket_get', { id: \$(this).attr('rel') }, function(result){
            var html = \$('<div>');
            \$.each(result.notes, function(i, v){
                html.append(\$('<div>').html(v.note));
                html.append(\$('<hr>'));
            });
            jAlert(html, \"{{ 'Notes'|trans }}\");
        });
        
        return false;
    });
});

function onAfterPublicTicketCreate(result) {
    bb.redirect(\"{{ 'support/public-ticket'|alink }}/\" + result);
}
</script>
{% endblock %}
", "mod_support_tickets.phtml", "/shared/httpd/up-boxbilling/FOSSBilling/src/bb-themes/admin_default/html/mod_support_tickets.phtml");
    }
}
