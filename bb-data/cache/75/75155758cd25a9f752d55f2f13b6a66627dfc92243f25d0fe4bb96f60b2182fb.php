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
class __TwigTemplate_010e19a239495a5d237b79051c2774e5d8729ad4f6f65fef37d2ab34e3b7a3f4 extends \Twig\Template
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
        // line 2
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_support_tickets.phtml", 2)->unwrap();
        // line 4
        $context["active_menu"] = "support";
        // line 1
        $this->getParent($context)->display($context, array_merge($this->blocks, $blocks));
    }

    // line 3
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo gettext("Support tickets");
    }

    // line 5
    public function block_top_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 6
        if (twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "show_filter", [], "any", false, false, false, 6)) {
            // line 7
            echo "<div class=\"widget filterWidget\">
    <div class=\"head\"><h5 class=\"iMagnify\">";
            // line 8
            echo gettext("Filter support tickets");
            echo "</h5></div>
    <div class=\"body nopadding\">

        <form method=\"get\" action=\"\" class=\"mainForm\">
            <input type=\"hidden\" name=\"_url\" value=\"";
            // line 12
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "_url", [], "any", false, false, false, 12), "html", null, true);
            echo "\" />
            <fieldset>
                <div class=\"rowElem noborder\">
                    <label>";
            // line 15
            echo gettext("Client ID");
            echo "</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"client_id\" value=\"";
            // line 17
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "client_id", [], "any", false, false, false, 17), "html", null, true);
            echo "\">
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>";
            // line 23
            echo gettext("Order ID");
            echo "</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"order_id\" value=\"";
            // line 25
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "order_id", [], "any", false, false, false, 25), "html", null, true);
            echo "\">
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>";
            // line 31
            echo gettext("Ticket subject");
            echo "</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"subject\" value=\"";
            // line 33
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "subject", [], "any", false, false, false, 33), "html", null, true);
            echo "\">
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>";
            // line 39
            echo gettext("Ticket messages");
            echo "</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"content\" value=\"";
            // line 41
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "content", [], "any", false, false, false, 41), "html", null, true);
            echo "\">
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>";
            // line 47
            echo gettext("Priority");
            echo "</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"priority\" value=\"";
            // line 49
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "priority", [], "any", false, false, false, 49), "html", null, true);
            echo "\">
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>";
            // line 55
            echo gettext("Status");
            echo "</label>
                        <div class=\"formRight\">
                            ";
            // line 57
            echo twig_call_macro($macros["mf"], "macro_selectbox", ["status", twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "support_ticket_get_statuses", [0 => ["titles" => 1]], "method", false, false, false, 57), twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "status", [], "any", false, false, false, 57), 0, "All statuses"], 57, $context, $this->getSourceContext());
            echo "
                        </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>";
            // line 63
            echo gettext("Help desk");
            echo ":</label>
                    <div class=\"formRight\">
                            ";
            // line 65
            echo twig_call_macro($macros["mf"], "macro_selectbox", ["support_helpdesk_id", twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "support_helpdesk_get_pairs", [], "any", false, false, false, 65), twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "support_helpdesk_id", [], "any", false, false, false, 65), 0, "All help desks"], 65, $context, $this->getSourceContext());
            echo "
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>";
            // line 71
            echo gettext("Created at");
            echo "</label>
                    <div class=\"formRight moreFields\">
                        <ul>
                            <li style=\"width: 100px\"><input type=\"text\" name=\"date_from\" value=\"";
            // line 74
            if (twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "date_from", [], "any", false, false, false, 74)) {
                echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "date_from", [], "any", false, false, false, 74), "Y-m-d"), "html", null, true);
            }
            echo "\" placeholder=\"";
            echo twig_escape_filter($this->env, twig_date_format_filter($this->env, ($context["now"] ?? null), "Y-m-d"), "html", null, true);
            echo "\" class=\"datepicker\"/></li>
                            <li class=\"sep\">-</li>
                            <li style=\"width: 100px\"><input type=\"text\" name=\"date_to\" value=\"";
            // line 76
            if (twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "date_to", [], "any", false, false, false, 76)) {
                echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "date_to", [], "any", false, false, false, 76), "Y-m-d"), "html", null, true);
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
            // line 83
            echo gettext("Filter");
            echo "\" class=\"greyishBtn submitForm\" />
            </fieldset>
        </form>
        <div class=\"fix\"></div>
    </div>
</div>
";
        } else {
            // line 90
            $context["statuses"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "support_ticket_get_statuses", [], "any", false, false, false, 90);
            // line 91
            echo "<div class=\"stats\">
    <ul>
        <li><a href=\"";
            // line 93
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support", ["status" => "open"]);
            echo "\" class=\"count green\" title=\"\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["statuses"] ?? null), "open", [], "any", false, false, false, 93), "html", null, true);
            echo "</a><span>";
            echo gettext("Tickets waiting for staff reply");
            echo "</span></li>
        <li><a href=\"";
            // line 94
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support", ["status" => "on_hold"]);
            echo "\" class=\"count blue\" title=\"\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["statuses"] ?? null), "on_hold", [], "any", false, false, false, 94), "html", null, true);
            echo "</a><span>";
            echo gettext("Tickets waiting for client reply");
            echo "</span></li>
        <li><a href=\"";
            // line 95
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support", ["status" => "closed"]);
            echo "\" class=\"count grey\" title=\"\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["statuses"] ?? null), "closed", [], "any", false, false, false, 95), "html", null, true);
            echo "</a><span>";
            echo gettext("Solved tickets");
            echo "</span></li>
        <li class=\"last\"><a href=\"";
            // line 96
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support");
            echo "\" class=\"count grey\" title=\"\">";
            echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["statuses"] ?? null), "open", [], "any", false, false, false, 96) + twig_get_attribute($this->env, $this->source, ($context["statuses"] ?? null), "on_hold", [], "any", false, false, false, 96)) + twig_get_attribute($this->env, $this->source, ($context["statuses"] ?? null), "closed", [], "any", false, false, false, 96)), "html", null, true);
            echo "</a><span>";
            echo gettext("Total");
            echo "</span></li>
    </ul>
    <div class=\"fix\"></div>
</div>
";
        }
        // line 101
        echo "
";
    }

    // line 104
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 105
        echo "
<div class=\"widget simpleTabs\">

    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">";
        // line 109
        echo gettext("Support tickets");
        echo "</a></li>
        <li><a href=\"#tab-new\">";
        // line 110
        echo gettext("New ticket");
        echo "</a></li>
        <li><a href=\"#tab-email\">";
        // line 111
        echo gettext("New email");
        echo "</a></li>
    </ul>

    <div class=\"tabs_container\">

        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-index\">

            ";
        // line 119
        echo twig_call_macro($macros["mf"], "macro_table_search", [], 119, $context, $this->getSourceContext());
        echo "
            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td style=\"width: 2%\"><input type=\"checkbox\" class=\"batch-delete-master-checkbox\"/></td>
                        <td colspan=\"2\">";
        // line 124
        echo gettext("Client");
        echo "</td>
                        <td>";
        // line 125
        echo gettext("Subject");
        echo "</td>
                        <td>";
        // line 126
        echo gettext("Status");
        echo "</td>
                        <td>";
        // line 127
        echo gettext("Helpdesk");
        echo "</td>
                        <td style=\"width: 13%\">&nbsp;</td>
                    </tr>
                </thead>

                <tbody>
                    ";
        // line 133
        $context["tickets"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "support_ticket_get_list", [0 => twig_array_merge(["per_page" => 30, "page" => twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 133)], ($context["request"] ?? null))], "method", false, false, false, 133);
        // line 134
        echo "                    ";
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["tickets"] ?? null), "list", [], "any", false, false, false, 134));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["i"] => $context["ticket"]) {
            // line 135
            echo "                    <tr class=\"priority_";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "priority", [], "any", false, false, false, 135), "html", null, true);
            echo "\">
                        <td><input type=\"checkbox\" class=\"batch-delete-checkbox\" data-item-id=\"";
            // line 136
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "id", [], "any", false, false, false, 136), "html", null, true);
            echo "\"/></td>
                        <td style=\"width:5%\"><a href=\"";
            // line 137
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("client/manage");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "client_id", [], "any", false, false, false, 137), "html", null, true);
            echo "\"><img src=\"";
            echo twig_escape_filter($this->env, twig_gravatar_filter(twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["ticket"], "client", [], "any", false, false, false, 137), "email", [], "any", false, false, false, 137)), "html", null, true);
            echo "?size=20\" alt=\"gravatar\" /></a></td>
                        <td style=\"width:20%\"><a href=\"";
            // line 138
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("client/manage");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "client_id", [], "any", false, false, false, 138), "html", null, true);
            echo "\">";
            echo twig_escape_filter($this->env, twig_truncate_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["ticket"], "client", [], "any", false, false, false, 138), "first_name", [], "any", false, false, false, 138), 1, null, "."), "html", null, true);
            echo " ";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["ticket"], "client", [], "any", false, false, false, 138), "last_name", [], "any", false, false, false, 138), "html", null, true);
            echo "</a></td>
                        <td>
                            <a href=\"";
            // line 140
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support/ticket");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "id", [], "any", false, false, false, 140), "html", null, true);
            echo "#reply-box\">#";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "id", [], "any", false, false, false, 140), "html", null, true);
            echo " - ";
            echo twig_escape_filter($this->env, twig_truncate_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "subject", [], "any", false, false, false, 140), 50), "html", null, true);
            echo " (";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "replies", [], "any", false, false, false, 140), "html", null, true);
            echo ")</a>
                            <br/>
                            ";
            // line 142
            if (twig_length_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "notes", [], "any", false, false, false, 142))) {
                // line 143
                echo "                            <a href=\"#\" rel=\"";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "id", [], "any", false, false, false, 143), "html", null, true);
                echo "\" title=\"";
                echo twig_escape_filter($this->env, twig_length_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "notes", [], "any", false, false, false, 143)), "html", null, true);
                echo "\" class=\"show-notes\"><img src=\"images/icons/dark/notebook.png\" alt=\"\" /></a>
                            ";
            }
            // line 145
            echo "                            ";
            echo twig_escape_filter($this->env, twig_timeago_filter(twig_get_attribute($this->env, $this->source, $context["ticket"], "updated_at", [], "any", false, false, false, 145)), "html", null, true);
            echo " ";
            echo gettext("ago");
            // line 146
            echo "                        </td>
                        <td>";
            // line 147
            echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, $context["ticket"], "status", [], "any", false, false, false, 147)], 147, $context, $this->getSourceContext());
            echo "</td>
                        <td>";
            // line 148
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["ticket"], "helpdesk", [], "any", false, false, false, 148), "name", [], "any", false, false, false, 148), "html", null, true);
            echo "</td>
                        <td class=\"actions\">
                            <a class=\"bb-button btn14\" href=\"";
            // line 150
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/support/ticket");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "id", [], "any", false, false, false, 150), "html", null, true);
            echo "#reply-box\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                            <a class=\"bb-button btn14 bb-rm-tr api-link\" data-api-confirm=\"Are you sure?\" data-api-redirect=\"";
            // line 151
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support");
            echo "\" href=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/support/ticket_delete", ["id" => twig_get_attribute($this->env, $this->source, $context["ticket"], "id", [], "any", false, false, false, 151)]);
            echo "\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                        </td>
                    </tr>

                    ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 156
            echo "
                    <tr>
                        <td colspan=\"6\">";
            // line 158
            echo gettext("The list is empty");
            echo "</td>
                    </tr>

                    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['i'], $context['ticket'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 162
        echo "
                </tbody>
            </table>
            ";
        // line 165
        $this->loadTemplate("partial_batch_delete.phtml", "mod_support_tickets.phtml", 165)->display(twig_array_merge($context, ["action" => "admin/support/batch_delete"]));
        // line 166
        echo "            ";
        $this->loadTemplate("partial_pagination.phtml", "mod_support_tickets.phtml", 166)->display(twig_array_merge($context, ["list" => ($context["tickets"] ?? null), "url" => "support"]));
        // line 167
        echo "
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-new\">
            <div class=\"help\">
                <h3>";
        // line 172
        echo gettext("Open ticket for existing client");
        echo "</h3>
            </div>

            <form method=\"post\" action=\"";
        // line 175
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/support/ticket_create");
        echo "\" class=\"mainForm api-form\" data-api-redirect=\"";
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support");
        echo "\">
                <fieldset>
                    ";
        // line 177
        if ( !twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "client_id", [], "any", false, false, false, 177)) {
            // line 178
            echo "                    <div class=\"rowElem noborder\">
                        <label>";
            // line 179
            echo gettext("Client");
            echo "</label>
                        <div class=\"formRight noborder\">
                            <input type=\"text\" id=\"client_selector\" placeholder=\"";
            // line 181
            echo gettext("Start typing clients name, email or ID");
            echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    ";
        }
        // line 186
        echo "                    <div class=\"rowElem\">
                        <label>";
        // line 187
        echo gettext("Help desk");
        echo "</label>
                        <div class=\"formRight\">
                            ";
        // line 189
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["support_helpdesk_id", twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "support_helpdesk_get_pairs", [], "any", false, false, false, 189), "", 1], 189, $context, $this->getSourceContext());
        echo "
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 195
        echo gettext("Subject");
        echo "</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"subject\" value=\"";
        // line 197
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "subject", [], "any", false, false, false, 197), "html", null, true);
        echo "\" required=\"required\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 203
        echo gettext("Message");
        echo "</label>
                        <div class=\"formRight\">
                        <textarea name=\"content\" cols=\"5\" rows=\"10\" required=\"required\" id=\"msg-area-";
        // line 205
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["message"] ?? null), "id", [], "any", false, false, false, 205), "html", null, true);
        echo "\">

";
        // line 207
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "signature", [], "any", false, false, false, 207), "html", null, true);
        echo "</textarea>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <input type=\"hidden\" name=\"client_id\" value=\"";
        // line 212
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "client_id", [], "any", false, false, false, 212), "html", null, true);
        echo "\" id=\"client_id\"/>
                    <input type=\"submit\" value=\"";
        // line 213
        echo gettext("Create");
        echo "\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-email\">
            <div class=\"help\">
                <h3>";
        // line 220
        echo gettext("Open public ticket for non client");
        echo "</h3>
            </div>

            <form method=\"post\" action=\"";
        // line 223
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/support/public_ticket_create");
        echo "\" class=\"mainForm api-form\" data-api-jsonp=\"onAfterPublicTicketCreate\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>";
        // line 226
        echo gettext("Receivers name");
        echo "</label>
                        <div class=\"formRight noborder\">
                            <input type=\"text\" name=\"name\" required=\"required\" placeholder=\"";
        // line 228
        echo gettext("John Smith");
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <div class=\"rowElem\">
                        <label>";
        // line 234
        echo gettext("Email");
        echo "</label>
                        <div class=\"formRight noborder\">
                            <input type=\"text\" name=\"email\" required=\"required\" placeholder=\"";
        // line 236
        echo gettext("to@gmail.com");
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 242
        echo gettext("Subject");
        echo "</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"subject\" value=\"";
        // line 244
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "subject", [], "any", false, false, false, 244), "html", null, true);
        echo "\" required=\"required\" placeholder=\"";
        echo gettext("Email subject");
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 250
        echo gettext("Message");
        echo "</label>
                        <div class=\"formRight\">
                        <textarea name=\"message\" cols=\"5\" rows=\"10\" required=\"required\">

";
        // line 254
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "signature", [], "any", false, false, false, 254), "html", null, true);
        echo "</textarea>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <input type=\"hidden\" name=\"client_id\" value=\"";
        // line 259
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "client_id", [], "any", false, false, false, 259), "html", null, true);
        echo "\" id=\"client_id\"/>
                    <input type=\"submit\" value=\"";
        // line 260
        echo gettext("Create");
        echo "\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>

    </div>
    
</div>

";
    }

    // line 272
    public function block_js($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 273
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

    \$('a.show-notes').click(function(){
        bb.post('admin/support/ticket_get', { id:\$(this).attr('rel') }, function(result){
            var html = \$('<div>');
            \$.each(result.notes, function(i, v){
                html.append(\$('<div>').html(v.note));
                html.append(\$('<hr>'));
            });
            jAlert(html, '";
        // line 310
        echo gettext("Notes");
        echo "');
        });
        
        return false;
    });

});

function onAfterPublicTicketCreate(result) {
    bb.redirect(\"";
        // line 319
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support/public-ticket");
        echo "/\"+result);
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
        return array (  687 => 319,  675 => 310,  636 => 273,  632 => 272,  618 => 260,  614 => 259,  606 => 254,  599 => 250,  588 => 244,  583 => 242,  574 => 236,  569 => 234,  560 => 228,  555 => 226,  549 => 223,  543 => 220,  533 => 213,  529 => 212,  521 => 207,  516 => 205,  511 => 203,  502 => 197,  497 => 195,  488 => 189,  483 => 187,  480 => 186,  472 => 181,  467 => 179,  464 => 178,  462 => 177,  455 => 175,  449 => 172,  442 => 167,  439 => 166,  437 => 165,  432 => 162,  422 => 158,  418 => 156,  406 => 151,  400 => 150,  395 => 148,  391 => 147,  388 => 146,  383 => 145,  375 => 143,  373 => 142,  360 => 140,  349 => 138,  341 => 137,  337 => 136,  332 => 135,  326 => 134,  324 => 133,  315 => 127,  311 => 126,  307 => 125,  303 => 124,  295 => 119,  284 => 111,  280 => 110,  276 => 109,  270 => 105,  266 => 104,  261 => 101,  249 => 96,  241 => 95,  233 => 94,  225 => 93,  221 => 91,  219 => 90,  209 => 83,  195 => 76,  186 => 74,  180 => 71,  171 => 65,  166 => 63,  157 => 57,  152 => 55,  143 => 49,  138 => 47,  129 => 41,  124 => 39,  115 => 33,  110 => 31,  101 => 25,  96 => 23,  87 => 17,  82 => 15,  76 => 12,  69 => 8,  66 => 7,  64 => 6,  60 => 5,  53 => 3,  49 => 1,  47 => 4,  45 => 2,  38 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}
{% import \"macro_functions.phtml\" as mf %}
{% block meta_title %}{% trans 'Support tickets' %}{% endblock %}
{% set active_menu = 'support' %}
{% block top_content %}
{% if request.show_filter %}
<div class=\"widget filterWidget\">
    <div class=\"head\"><h5 class=\"iMagnify\">{% trans 'Filter support tickets' %}</h5></div>
    <div class=\"body nopadding\">

        <form method=\"get\" action=\"\" class=\"mainForm\">
            <input type=\"hidden\" name=\"_url\" value=\"{{ request._url }}\" />
            <fieldset>
                <div class=\"rowElem noborder\">
                    <label>{% trans 'Client ID' %}</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"client_id\" value=\"{{ request.client_id }}\">
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>{% trans 'Order ID' %}</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"order_id\" value=\"{{ request.order_id }}\">
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>{% trans 'Ticket subject' %}</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"subject\" value=\"{{ request.subject }}\">
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>{% trans 'Ticket messages' %}</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"content\" value=\"{{ request.content }}\">
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>{% trans 'Priority' %}</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"priority\" value=\"{{ request.priority }}\">
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>{% trans 'Status' %}</label>
                        <div class=\"formRight\">
                            {{ mf.selectbox('status', admin.support_ticket_get_statuses({\"titles\":1}), request.status, 0, \"All statuses\") }}
                        </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>{% trans 'Help desk' %}:</label>
                    <div class=\"formRight\">
                            {{ mf.selectbox('support_helpdesk_id', admin.support_helpdesk_get_pairs, request.support_helpdesk_id, 0, \"All help desks\") }}
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>{% trans 'Created at' %}</label>
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
{% set statuses = admin.support_ticket_get_statuses %}
<div class=\"stats\">
    <ul>
        <li><a href=\"{{ 'support'|alink({'status' : 'open'}) }}\" class=\"count green\" title=\"\">{{ statuses.open }}</a><span>{% trans 'Tickets waiting for staff reply' %}</span></li>
        <li><a href=\"{{ 'support'|alink({'status' : 'on_hold'}) }}\" class=\"count blue\" title=\"\">{{ statuses.on_hold }}</a><span>{% trans 'Tickets waiting for client reply' %}</span></li>
        <li><a href=\"{{ 'support'|alink({'status' : 'closed'}) }}\" class=\"count grey\" title=\"\">{{ statuses.closed }}</a><span>{% trans 'Solved tickets' %}</span></li>
        <li class=\"last\"><a href=\"{{ 'support'|alink }}\" class=\"count grey\" title=\"\">{{ statuses.open + statuses.on_hold + statuses.closed }}</a><span>{% trans 'Total' %}</span></li>
    </ul>
    <div class=\"fix\"></div>
</div>
{% endif %}

{% endblock %}

{% block content %}

<div class=\"widget simpleTabs\">

    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">{% trans 'Support tickets' %}</a></li>
        <li><a href=\"#tab-new\">{% trans 'New ticket' %}</a></li>
        <li><a href=\"#tab-email\">{% trans 'New email' %}</a></li>
    </ul>

    <div class=\"tabs_container\">

        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-index\">

            {{ mf.table_search }}
            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td style=\"width: 2%\"><input type=\"checkbox\" class=\"batch-delete-master-checkbox\"/></td>
                        <td colspan=\"2\">{% trans 'Client' %}</td>
                        <td>{% trans 'Subject' %}</td>
                        <td>{% trans 'Status' %}</td>
                        <td>{% trans 'Helpdesk' %}</td>
                        <td style=\"width: 13%\">&nbsp;</td>
                    </tr>
                </thead>

                <tbody>
                    {% set tickets = admin.support_ticket_get_list({\"per_page\":30, \"page\":request.page}|merge(request)) %}
                    {% for i, ticket in tickets.list %}
                    <tr class=\"priority_{{ ticket.priority }}\">
                        <td><input type=\"checkbox\" class=\"batch-delete-checkbox\" data-item-id=\"{{ ticket.id }}\"/></td>
                        <td style=\"width:5%\"><a href=\"{{ 'client/manage'|alink }}/{{ ticket.client_id }}\"><img src=\"{{ ticket.client.email|gravatar }}?size=20\" alt=\"gravatar\" /></a></td>
                        <td style=\"width:20%\"><a href=\"{{ 'client/manage'|alink }}/{{ ticket.client_id }}\">{{ ticket.client.first_name|truncate(1, null, '.') }} {{ ticket.client.last_name }}</a></td>
                        <td>
                            <a href=\"{{ 'support/ticket'|alink }}/{{ticket.id}}#reply-box\">#{{ticket.id}} - {{ticket.subject|truncate(50)}} ({{ ticket.replies }})</a>
                            <br/>
                            {% if ticket.notes|length %}
                            <a href=\"#\" rel=\"{{ticket.id}}\" title=\"{{ ticket.notes|length }}\" class=\"show-notes\"><img src=\"images/icons/dark/notebook.png\" alt=\"\" /></a>
                            {% endif %}
                            {{ticket.updated_at|timeago}} {% trans 'ago' %}
                        </td>
                        <td>{{mf.status_name(ticket.status)}}</td>
                        <td>{{ticket.helpdesk.name}}</td>
                        <td class=\"actions\">
                            <a class=\"bb-button btn14\" href=\"{{ '/support/ticket'|alink }}/{{ticket.id}}#reply-box\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                            <a class=\"bb-button btn14 bb-rm-tr api-link\" data-api-confirm=\"Are you sure?\" data-api-redirect=\"{{ 'support'|alink }}\" href=\"{{ 'api/admin/support/ticket_delete'|link({'id' : ticket.id}) }}\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                        </td>
                    </tr>

                    {% else %}

                    <tr>
                        <td colspan=\"6\">{% trans 'The list is empty' %}</td>
                    </tr>

                    {% endfor %}

                </tbody>
            </table>
            {% include \"partial_batch_delete.phtml\" with {'action':'admin/support/batch_delete'} %}
            {% include \"partial_pagination.phtml\" with {'list': tickets, 'url':'support'} %}

        </div>

        <div class=\"tab_content nopadding\" id=\"tab-new\">
            <div class=\"help\">
                <h3>{% trans 'Open ticket for existing client' %}</h3>
            </div>

            <form method=\"post\" action=\"{{ 'api/admin/support/ticket_create'|link }}\" class=\"mainForm api-form\" data-api-redirect=\"{{ 'support'|alink }}\">
                <fieldset>
                    {% if not request.client_id %}
                    <div class=\"rowElem noborder\">
                        <label>{% trans 'Client' %}</label>
                        <div class=\"formRight noborder\">
                            <input type=\"text\" id=\"client_selector\" placeholder=\"{% trans %}Start typing clients name, email or ID{% endtrans %}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    {% endif %}
                    <div class=\"rowElem\">
                        <label>{% trans 'Help desk' %}</label>
                        <div class=\"formRight\">
                            {{ mf.selectbox('support_helpdesk_id', admin.support_helpdesk_get_pairs, '', 1) }}
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Subject' %}</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"subject\" value=\"{{ticket.subject}}\" required=\"required\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Message' %}</label>
                        <div class=\"formRight\">
                        <textarea name=\"content\" cols=\"5\" rows=\"10\" required=\"required\" id=\"msg-area-{{message.id}}\">

{{ profile.signature }}</textarea>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <input type=\"hidden\" name=\"client_id\" value=\"{{ request.client_id }}\" id=\"client_id\"/>
                    <input type=\"submit\" value=\"{% trans 'Create' %}\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-email\">
            <div class=\"help\">
                <h3>{% trans 'Open public ticket for non client' %}</h3>
            </div>

            <form method=\"post\" action=\"{{ 'api/admin/support/public_ticket_create'|link }}\" class=\"mainForm api-form\" data-api-jsonp=\"onAfterPublicTicketCreate\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>{% trans 'Receivers name' %}</label>
                        <div class=\"formRight noborder\">
                            <input type=\"text\" name=\"name\" required=\"required\" placeholder=\"{% trans %}John Smith{% endtrans %}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <div class=\"rowElem\">
                        <label>{% trans 'Email' %}</label>
                        <div class=\"formRight noborder\">
                            <input type=\"text\" name=\"email\" required=\"required\" placeholder=\"{% trans %}to@gmail.com{% endtrans %}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Subject' %}</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"subject\" value=\"{{ticket.subject}}\" required=\"required\" placeholder=\"{% trans %}Email subject{% endtrans %}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Message' %}</label>
                        <div class=\"formRight\">
                        <textarea name=\"message\" cols=\"5\" rows=\"10\" required=\"required\">

{{ profile.signature }}</textarea>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <input type=\"hidden\" name=\"client_id\" value=\"{{ request.client_id }}\" id=\"client_id\"/>
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

    \$('a.show-notes').click(function(){
        bb.post('admin/support/ticket_get', { id:\$(this).attr('rel') }, function(result){
            var html = \$('<div>');
            \$.each(result.notes, function(i, v){
                html.append(\$('<div>').html(v.note));
                html.append(\$('<hr>'));
            });
            jAlert(html, '{% trans \"Notes\" %}');
        });
        
        return false;
    });

});

function onAfterPublicTicketCreate(result) {
    bb.redirect(\"{{ 'support/public-ticket'|alink }}/\"+result);
}

</script>
{% endblock %}", "mod_support_tickets.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-themes/admin_default/html/mod_support_tickets.phtml");
    }
}
