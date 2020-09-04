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

/* mod_support_ticket.phtml */
class __TwigTemplate_35b097c25c6dab162c62ac46bddcf8debc46a745747aa33c07cc5d68fd5be179 extends \Twig\Template
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
            'js' => [$this, 'block_js'],
            'head' => [$this, 'block_head'],
        ];
    }

    protected function doGetParent(array $context)
    {
        // line 1
        return $this->loadTemplate(((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ajax", [], "any", false, false, false, 1)) ? ("layout_blank.phtml") : ("layout_default.phtml")), "mod_support_ticket.phtml", 1);
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 2
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_support_ticket.phtml", 2)->unwrap();
        // line 13
        $context["active_menu"] = "support";
        // line 1
        $this->getParent($context)->display($context, array_merge($this->blocks, $blocks));
    }

    // line 3
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "subject", [], "any", false, false, false, 3), "html", null, true);
        echo " - ";
        echo twig_escape_filter($this->env, twig_length_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "messages", [], "any", false, false, false, 3)), "html", null, true);
        echo " ";
        echo gettext("message(s)");
    }

    // line 5
    public function block_breadcrumbs($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 6
        echo "<ul>
    <li class=\"firstB\"><a href=\"";
        // line 7
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/");
        echo "\">";
        echo gettext("Home");
        echo "</a></li>
    <li><a href=\"";
        // line 8
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support");
        echo "\">";
        echo gettext("Support tickets");
        echo "</a></li>
    <li class=\"lastB\">#";
        // line 9
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "id", [], "any", false, false, false, 9), "html", null, true);
        echo "  - ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "subject", [], "any", false, false, false, 9), "html", null, true);
        echo "</li>
</ul>
";
    }

    // line 14
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 15
        echo "
<div class=\"widget simpleTabs tabsRight\">
    <div class=\"head\">
        <h5 class=\"iSpeech\">";
        // line 18
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "subject", [], "any", false, false, false, 18), "html", null, true);
        echo "</h5>
    </div>

    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">";
        // line 22
        echo gettext("Ticket");
        echo "</a></li>
        <li><a href=\"#tab-manage\">";
        // line 23
        echo gettext("Manage");
        echo "</a></li>
        <li><a href=\"#tab-notes\">";
        // line 24
        if ((twig_length_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "notes", [], "any", false, false, false, 24)) > 0)) {
            echo twig_escape_filter($this->env, twig_length_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "notes", [], "any", false, false, false, 24)), "html", null, true);
            echo " - ";
        }
        echo gettext("Notes");
        echo "</a></li>
        <li><a href=\"#tab-support\">";
        // line 25
        echo gettext("Client tickets");
        echo "</a></li>
    </ul>
    
    <div class=\"tabs_container\">
        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-index\">
            <table class=\"tableStatic wide\">
                <tbody>
                    <tr class=\"noborder\">
                        <td style=\"width: 30%\">";
        // line 34
        echo gettext("Ticket ID");
        echo "</td>
                        <td><b>#";
        // line 35
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "id", [], "any", false, false, false, 35), "html", null, true);
        echo "</b></td>
                    </tr>

                    <tr>
                        <td>";
        // line 39
        echo gettext("Client");
        echo "</td>
                        <td>#";
        // line 40
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "client", [], "any", false, false, false, 40), "id", [], "any", false, false, false, 40), "html", null, true);
        echo " <a href=\"";
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("client/manage");
        echo "/";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "client", [], "any", false, false, false, 40), "id", [], "any", false, false, false, 40), "html", null, true);
        echo "\">";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "client", [], "any", false, false, false, 40), "first_name", [], "any", false, false, false, 40), "html", null, true);
        echo " ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "client", [], "any", false, false, false, 40), "last_name", [], "any", false, false, false, 40), "html", null, true);
        echo "</a></td>
                    </tr>
                    
                    <tr>
                        <td>";
        // line 44
        echo gettext("Help desk");
        echo "</td>
                        <td class=\"shd\">
                            ";
        // line 46
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["support_helpdesk_id", twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "support_helpdesk_get_pairs", [], "any", false, false, false, 46), twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "support_helpdesk_id", [], "any", false, false, false, 46), 1], 46, $context, $this->getSourceContext());
        echo "
                        </td>
                    </tr>

                    <tr>
                        <td>";
        // line 51
        echo gettext("Status");
        echo "</td>
                        <td>
                            ";
        // line 53
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "support_ticket_get_statuses", [0 => ["titles" => 1]], "method", false, false, false, 53));
        foreach ($context['_seq'] as $context["tcode"] => $context["tstatus"]) {
            // line 54
            echo "                            <label><input class=\"tst\" type=\"radio\" name=\"status\" value=\"";
            echo twig_escape_filter($this->env, $context["tcode"], "html", null, true);
            echo "\" ";
            if ((twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "status", [], "any", false, false, false, 54) == $context["tcode"])) {
                echo "checked=\"checked\"";
            }
            echo " /> ";
            echo twig_escape_filter($this->env, $context["tstatus"], "html", null, true);
            echo "</label>
                            ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['tcode'], $context['tstatus'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 56
        echo "                        </td>
                    </tr>
                    
                    <tr>
                        <td>";
        // line 60
        echo gettext("Time opened");
        echo "</td>
                        <td>";
        // line 61
        echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "created_at", [], "any", false, false, false, 61), "l, d F Y"), "html", null, true);
        echo "</td>
                    </tr>

                 </tbody>

                ";
        // line 66
        $context["task"] = twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "rel", [], "any", false, false, false, 66);
        // line 67
        echo "                <tbody>
                ";
        // line 68
        if (twig_get_attribute($this->env, $this->source, ($context["task"] ?? null), "task", [], "any", false, false, false, 68)) {
            // line 69
            echo "                <tr>
                    <td><label>";
            // line 70
            echo gettext("Task");
            echo "</label></td>
                    <td><strong>";
            // line 71
            echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, ($context["task"] ?? null), "task", [], "any", false, false, false, 71)], 71, $context, $this->getSourceContext());
            echo "</strong></td>
                </tr>
                ";
        }
        // line 74
        echo "
                ";
        // line 75
        if ((twig_get_attribute($this->env, $this->source, ($context["task"] ?? null), "type", [], "any", false, false, false, 75) == "order")) {
            // line 76
            echo "                <tr>
                    <td><label>";
            // line 77
            echo gettext("Related to");
            echo "</label></td>
                    <td><a href=\"";
            // line 78
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("order/manage");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["task"] ?? null), "id", [], "any", false, false, false, 78), "html", null, true);
            echo "\">Order #";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["task"] ?? null), "id", [], "any", false, false, false, 78), "html", null, true);
            echo "</a></td>
                </tr>
                ";
        }
        // line 81
        echo "
                ";
        // line 82
        if ((twig_get_attribute($this->env, $this->source, ($context["task"] ?? null), "task", [], "any", false, false, false, 82) == "upgrade")) {
            // line 83
            echo "                <tr>
                    <td><label>";
            // line 84
            echo gettext("Upgrade to");
            echo "</label></td>
                    <td><a href=\"";
            // line 85
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("product/manage");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["task"] ?? null), "new_value", [], "any", false, false, false, 85), "html", null, true);
            echo "\">";
            echo twig_escape_filter($this->env, (($__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4 = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "product_get_pairs", [], "any", false, false, false, 85)) && is_array($__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4) || $__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4 instanceof ArrayAccess ? ($__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4[twig_get_attribute($this->env, $this->source, ($context["task"] ?? null), "new_value", [], "any", false, false, false, 85)] ?? null) : null), "html", null, true);
            echo "</a></td>
                </tr>
                ";
        }
        // line 88
        echo "
                </tbody>

                 <tfoot>
                     <tr>
                         <td colspan=\"2\">
                            <div class=\"aligncenter\">
                                ";
        // line 95
        if ((twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "status", [], "any", false, false, false, 95) != "closed")) {
            // line 96
            echo "                                <a href=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/support/ticket_close", ["id" => twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "id", [], "any", false, false, false, 96)]);
            echo "\" data-api-confirm=\"Are you sure?\" data-api-redirect=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support", ["status" => "open"]);
            echo "\" class=\"btn55 mr10 api-link\" ><img src=\"images/icons/middlenav/stop.png\" alt=\"\"><span>";
            echo gettext("Close ticket");
            echo "</span></a>
                                ";
        }
        // line 98
        echo "                                <a href=\"";
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/support/ticket_delete", ["id" => twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "id", [], "any", false, false, false, 98)]);
        echo "\" data-api-confirm=\"Are you sure?\" data-api-redirect=\"";
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support");
        echo "\" class=\"btn55 mr10 api-link\"><img src=\"images/icons/middlenav/trash.png\" alt=\"\"><span>";
        echo gettext("Delete");
        echo "</span></a>

                                ";
        // line 100
        if ((twig_get_attribute($this->env, $this->source, ($context["task"] ?? null), "status", [], "any", false, false, false, 100) == "pending")) {
            // line 101
            echo "                                <a href=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/support/task_complete", ["id" => twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "id", [], "any", false, false, false, 101)]);
            echo "\" class=\"btn55 mr10 api-link\" data-api-reload=\"1\"><img src=\"images/icons/middlenav/check.png\" alt=\"\" data-api-reload=\"1\"><span>";
            echo gettext("Set Task complete");
            echo "</span></a>
                                ";
        }
        // line 103
        echo "                            </div>
                         </td>
                     </tr>
                 </tfoot>
            </table>
            <div class=\"fix\"></div>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-manage\">
            <form method=\"post\" action=\"";
        // line 112
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/support/ticket_update");
        echo "\" class=\"mainForm api-form\" data-api-reload=\"1\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>";
        // line 115
        echo gettext("Subject");
        echo "</label>
                        <div class=\"formRight noborder\">
                            <input type=\"text\" name=\"subject\" value=\"";
        // line 117
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "subject", [], "any", false, false, false, 117), "html", null, true);
        echo "\" required=\"required\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 123
        echo gettext("Help desk");
        echo "</label>
                        <div class=\"formRight\">
                            ";
        // line 125
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["support_helpdesk_id", twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "support_helpdesk_get_pairs", [], "any", false, false, false, 125), twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "support_helpdesk_id", [], "any", false, false, false, 125), 1], 125, $context, $this->getSourceContext());
        echo "
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 131
        echo gettext("Status");
        echo "</label>
                        <div class=\"formRight\">
                            ";
        // line 133
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["status", twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "support_ticket_get_statuses", [0 => ["titles" => 1]], "method", false, false, false, 133), twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "status", [], "any", false, false, false, 133), 1], 133, $context, $this->getSourceContext());
        echo "
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 139
        echo gettext("Priority");
        echo "</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"priority\" value=\"";
        // line 141
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "priority", [], "any", false, false, false, 141), "html", null, true);
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                     <input type=\"submit\" value=\"";
        // line 145
        echo gettext("Update");
        echo "\" class=\"greyishBtn submitForm\" />
                </fieldset>
                <input type=\"hidden\" name=\"id\" value=\"";
        // line 147
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "id", [], "any", false, false, false, 147), "html", null, true);
        echo "\">
            </form>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-notes\">

            <table class=\"tableStatic wide\">
            ";
        // line 154
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "notes", [], "any", false, false, false, 154));
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
        foreach ($context['_seq'] as $context["_key"] => $context["note"]) {
            // line 155
            echo "                <tr ";
            if (twig_get_attribute($this->env, $this->source, $context["loop"], "first", [], "any", false, false, false, 155)) {
                echo "class=\"noborder\"";
            }
            echo ">
                    <td>";
            // line 156
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["note"], "note", [], "any", false, false, false, 156), "html", null, true);
            echo "</td>
                    <td style=\"width: 20%\"><a href=\"";
            // line 157
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("staff");
            echo "/manage/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["note"], "admin_id", [], "any", false, false, false, 157), "html", null, true);
            echo "\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["note"], "author", [], "any", false, false, false, 157), "name", [], "any", false, false, false, 157), "html", null, true);
            echo "</a></td>
                    <td style=\"width: 5%\">
                        <a href=\"";
            // line 159
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/support/note_delete", ["id" => twig_get_attribute($this->env, $this->source, $context["note"], "id", [], "any", false, false, false, 159)]);
            echo "\" data-api-confirm=\"Are you sure?\" data-api-reload=\"1\" class=\"bb-button btn14 api-link\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                    </td>
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
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['note'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 163
        echo "                <tfoot>
                    <tr>
                        <td colspan=\"3\" ";
        // line 165
        if ((twig_length_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "notes", [], "any", false, false, false, 165)) == 0)) {
            echo "class=\"noborder\"";
        }
        echo ">

                            <form method=\"post\" action=\"";
        // line 167
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/support/note_create");
        echo "\" class=\"mainForm api-form\" data-api-reload=\"1\">
                                <fieldset>
                                    <textarea name=\"note\" cols=\"5\" rows=\"5\" required=\"required\" placeholder=\"Add new note\" style=\"width: 98%\"></textarea>
                                    <input type=\"submit\" value=\"";
        // line 170
        echo gettext("Add note");
        echo "\" class=\"greyishBtn submitForm\" style=\" margin-top: 22px; width: 100px\"/>
                                    <input type=\"hidden\" name=\"ticket_id\" value=\"";
        // line 171
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "id", [], "any", false, false, false, 171), "html", null, true);
        echo "\">
                                </fieldset>
                            </form>

                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-support\">
            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td>ID</td>
                        <td width=\"60%\">Subject</td>
                        <td width=\"15%\">Help desk</td>
                        <td width=\"15%\">Status</td>
                        <td>&nbsp;</td>
                    </tr>
                </thead>

                <tbody>
                    ";
        // line 194
        $context["tickets"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "support_ticket_get_list", [0 => ["per_page" => "20", "client_id" => twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "client", [], "any", false, false, false, 194), "id", [], "any", false, false, false, 194)]], "method", false, false, false, 194);
        // line 195
        echo "                    ";
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["tickets"] ?? null), "list", [], "any", false, false, false, 195));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["_key"] => $context["ticket"]) {
            // line 196
            echo "                    <tr>
                        <td>";
            // line 197
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "id", [], "any", false, false, false, 197), "html", null, true);
            echo "</td>
                        <td>";
            // line 198
            echo twig_escape_filter($this->env, twig_truncate_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "subject", [], "any", false, false, false, 198), 30), "html", null, true);
            echo "</td>
                        <td>";
            // line 199
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["ticket"], "helpdesk", [], "any", false, false, false, 199), "name", [], "any", false, false, false, 199), "html", null, true);
            echo "</td>
                        <td>";
            // line 200
            echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, $context["ticket"], "status", [], "any", false, false, false, 200)], 200, $context, $this->getSourceContext());
            echo "</td>
                        <td class=\"actions\"><a class=\"bb-button btn14\" href=\"";
            // line 201
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/support/ticket");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "id", [], "any", false, false, false, 201), "html", null, true);
            echo "\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a></td>
                    </tr>
                    ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 204
            echo "                    <tr>
                        <td colspan=\"5\">";
            // line 205
            echo gettext("The list is empty");
            echo "</td>
                    </tr>
                    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['ticket'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 208
        echo "                </tbody>

                <tfoot>
                    <tr>
                        <td colspan=\"5\">
                            <a href=\"";
        // line 213
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support", ["client_id" => twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "id", [], "any", false, false, false, 213)]);
        echo "#tab-new\" class=\"btnIconLeft mr10 mt5\" ><img src=\"images/icons/dark/help.png\" alt=\"\" class=\"icon\"><span>";
        echo gettext("New support ticket");
        echo "</span></a>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

    </div>
</div>

<div class=\"conversation\">
";
        // line 224
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "messages", [], "any", false, false, false, 224));
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
        foreach ($context['_seq'] as $context["i"] => $context["message"]) {
            // line 225
            echo "<div class=\"widget ";
            echo ((twig_get_attribute($this->env, $this->source, $context["loop"], "last", [], "any", false, false, false, 225)) ? ("last") : (""));
            echo "\" id=\"";
            echo (((twig_get_attribute($this->env, $this->source, $context["message"], "id", [], "any", false, false, false, 225) == ($context["request_message"] ?? null))) ? ("direct-msg") : (""));
            echo "\">
    <div class=\"head\" style=\"cursor: pointer;\">
        <h5 class=\"no-icon\"><img src=\"";
            // line 227
            echo twig_escape_filter($this->env, twig_gravatar_filter(twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["message"], "author", [], "any", false, false, false, 227), "email", [], "any", false, false, false, 227)), "html", null, true);
            echo "?size=20\" alt=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["message"], "author", [], "any", false, false, false, 227), "name", [], "any", false, false, false, 227), "html", null, true);
            echo "\" class=\"gravatar\"/> ";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["message"], "author", [], "any", false, false, false, 227), "name", [], "any", false, false, false, 227), "html", null, true);
            echo "</h5>
        <h5 style=\"float:right;\"><a href=\"";
            // line 228
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/support/ticket");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "id", [], "any", false, false, false, 228), "html", null, true);
            echo "/message/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["message"], "id", [], "any", false, false, false, 228), "html", null, true);
            echo "\" style=\"color:inherit\">";
            echo twig_escape_filter($this->env, $this->extensions['Box_TwigExtensions']->twig_bb_datetime(twig_get_attribute($this->env, $this->source, $context["message"], "created_at", [], "any", false, false, false, 228)), "html", null, true);
            echo "</a></h5>
    </div>

    <div class=\"body\" style=\"display:";
            // line 231
            echo (((twig_get_attribute($this->env, $this->source, $context["loop"], "last", [], "any", false, false, false, 231) || ((twig_get_attribute($this->env, $this->source, $context["loop"], "index", [], "any", false, false, false, 231) + 1) == twig_length_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "messages", [], "any", false, false, false, 231))))) ? ("block") : ("none"));
            echo ";\">
        ";
            // line 232
            echo twig_bbmd_filter($this->env, twig_get_attribute($this->env, $this->source, $context["message"], "content", [], "any", false, false, false, 232));
            echo "
        <div class=\"clear\"></div>
    </div>
</div>
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
        unset($context['_seq'], $context['_iterated'], $context['i'], $context['message'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 237
        echo "</div>


<div class=\"widget\" id=\"reply-box\">
    <div class=\"head\">
        <h5 class=\"iSpeech\">";
        // line 242
        echo gettext("Reply as");
        echo " ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "name", [], "any", false, false, false, 242), "html", null, true);
        echo "</h5>
        ";
        // line 243
        $this->loadTemplate("mod_support_canned_selector.phtml", "mod_support_ticket.phtml", 243)->display($context);
        // line 244
        echo "    </div>

    <form method=\"post\" action=\"";
        // line 246
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/support/ticket_reply");
        echo "\" class=\"mainForm api-form\" data-api-redirect=\"";
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support", ["status" => "open"]);
        echo "\">
        <fieldset>
            <textarea name=\"content\" cols=\"5\" rows=\"20\" required=\"required\" class=\"bb-textarea\" id=\"rt\">Hello ";
        // line 248
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "client", [], "any", false, false, false, 248), "first_name", [], "any", false, false, false, 248), "html", null, true);
        echo ",
";
        // line 249
        echo twig_escape_filter($this->env, ($context["canned_delay_message"] ?? null), "html", null, true);
        echo "



";
        // line 253
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "signature", [], "any", false, false, false, 253), "html", null, true);
        echo "
";
        // line 254
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "helpdesk", [], "any", false, false, false, 254), "signature", [], "any", false, false, false, 254), "html", null, true);
        echo "</textarea>
            <input type=\"hidden\" name=\"id\" value=\"";
        // line 255
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "id", [], "any", false, false, false, 255), "html", null, true);
        echo "\">
            <input type=\"submit\" value=\"";
        // line 256
        echo gettext("Post");
        echo "\" class=\"greyishBtn submitForm\" />


            <div class=\"body\">
                <a href=\"#\"  class=\"btnIconLeft mr10\" id=\"toggleMessages\" ><span>";
        // line 260
        echo gettext("Show/Hide messages");
        echo "</span></a>

                ";
        // line 262
        if (((twig_length_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "messages", [], "any", false, false, false, 262)) > 2) && (twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "status", [], "any", false, false, false, 262) != "closed"))) {
            // line 263
            echo "                <a href=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/support/ticket_close", ["id" => twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "id", [], "any", false, false, false, 263)]);
            echo "\" data-api-confirm=\"Are you sure?\" data-api-redirect=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support", ["status" => "open"]);
            echo "\" class=\"btnIconLeft mr10 api-link\" ><span>";
            echo gettext("Close ticket");
            echo "</span></a>
                ";
        }
        // line 265
        echo "            </div>
        </fieldset>
    </form>

    <div class=\"clear\"></div>
</div>

";
    }

    // line 274
    public function block_js($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 275
        echo "<script type=\"text/javascript\">
function setSelRange(inputEl, selStart, selEnd) { 
   if (inputEl.setSelectionRange) { 
     inputEl.focus(); 
     inputEl.setSelectionRange(selStart, selEnd); 
   } else if (inputEl.createTextRange) { 
     var range = inputEl.createTextRange(); 
     range.collapse(true); 
     range.moveEnd('character', selEnd); 
     range.moveStart('character', selStart); 
     range.select(); 
   }
}

\$(function() {

    \$('#reply-box textarea').focus();
    var ta = document.getElementById('rt');
    var pos = ta.innerHTML.indexOf('\\n') + 2;
    setSelRange(ta, pos, pos);
 
    \$('.shd select').change(function(){
        bb.get('admin/support/ticket_update', {id:";
        // line 297
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "id", [], "any", false, false, false, 297), "html", null, true);
        echo ", support_helpdesk_id: \$(this).val()});
    });
    
    \$('input.tst').click(function(){
        bb.get('admin/support/ticket_update', {id:";
        // line 301
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "id", [], "any", false, false, false, 301), "html", null, true);
        echo ", status: \$(this).val()});
    });

    \$('.conversation').on('click', '.head', function(e){
        if( e.target !== this )
            return;
        \$(this).siblings('.body').toggle();
        return false;
    });

    if (\$('#direct-msg').length > 0){
        \$('#direct-msg > .body').show();
        \$('html, body').animate({
            scrollTop: \$('#direct-msg').offset().top-50
        }, 500);
        \$('#direct-msg').css(\"background-color\", \"rgb(228, 228, 228)\");
    }

    var showAllMessages = false;
    \$('.api-form').on('click', '#toggleMessages', function(e) {
        e.preventDefault();
        showAllMessages = !showAllMessages;
        \$('.conversation .body').toggle(showAllMessages);

    });
});
</script>
";
    }

    // line 330
    public function block_head($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo twig_call_macro($macros["mf"], "macro_bb_editor", [".bb-textarea"], 330, $context, $this->getSourceContext());
    }

    public function getTemplateName()
    {
        return "mod_support_ticket.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  806 => 330,  774 => 301,  767 => 297,  743 => 275,  739 => 274,  728 => 265,  718 => 263,  716 => 262,  711 => 260,  704 => 256,  700 => 255,  696 => 254,  692 => 253,  685 => 249,  681 => 248,  674 => 246,  670 => 244,  668 => 243,  662 => 242,  655 => 237,  636 => 232,  632 => 231,  620 => 228,  612 => 227,  604 => 225,  587 => 224,  571 => 213,  564 => 208,  555 => 205,  552 => 204,  542 => 201,  538 => 200,  534 => 199,  530 => 198,  526 => 197,  523 => 196,  517 => 195,  515 => 194,  489 => 171,  485 => 170,  479 => 167,  472 => 165,  468 => 163,  450 => 159,  441 => 157,  437 => 156,  430 => 155,  413 => 154,  403 => 147,  398 => 145,  391 => 141,  386 => 139,  377 => 133,  372 => 131,  363 => 125,  358 => 123,  349 => 117,  344 => 115,  338 => 112,  327 => 103,  319 => 101,  317 => 100,  307 => 98,  297 => 96,  295 => 95,  286 => 88,  276 => 85,  272 => 84,  269 => 83,  267 => 82,  264 => 81,  254 => 78,  250 => 77,  247 => 76,  245 => 75,  242 => 74,  236 => 71,  232 => 70,  229 => 69,  227 => 68,  224 => 67,  222 => 66,  214 => 61,  210 => 60,  204 => 56,  189 => 54,  185 => 53,  180 => 51,  172 => 46,  167 => 44,  152 => 40,  148 => 39,  141 => 35,  137 => 34,  125 => 25,  117 => 24,  113 => 23,  109 => 22,  102 => 18,  97 => 15,  93 => 14,  84 => 9,  78 => 8,  72 => 7,  69 => 6,  65 => 5,  54 => 3,  50 => 1,  48 => 13,  46 => 2,  39 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}
{% import \"macro_functions.phtml\" as mf %}
{% block meta_title %}{{ticket.subject}} - {{ ticket.messages|length }} {% trans 'message(s)' %}{% endblock %}

{% block breadcrumbs %}
<ul>
    <li class=\"firstB\"><a href=\"{{ '/'|alink }}\">{% trans 'Home' %}</a></li>
    <li><a href=\"{{ 'support'|alink }}\">{% trans 'Support tickets' %}</a></li>
    <li class=\"lastB\">#{{ticket.id}}  - {{ticket.subject}}</li>
</ul>
{% endblock %}

{% set active_menu = 'support' %}
{% block content %}

<div class=\"widget simpleTabs tabsRight\">
    <div class=\"head\">
        <h5 class=\"iSpeech\">{{ ticket.subject }}</h5>
    </div>

    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">{% trans 'Ticket' %}</a></li>
        <li><a href=\"#tab-manage\">{% trans 'Manage' %}</a></li>
        <li><a href=\"#tab-notes\">{% if ticket.notes|length > 0%}{{ticket.notes|length}} - {% endif %}{% trans 'Notes' %}</a></li>
        <li><a href=\"#tab-support\">{% trans 'Client tickets' %}</a></li>
    </ul>
    
    <div class=\"tabs_container\">
        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-index\">
            <table class=\"tableStatic wide\">
                <tbody>
                    <tr class=\"noborder\">
                        <td style=\"width: 30%\">{% trans 'Ticket ID' %}</td>
                        <td><b>#{{ticket.id}}</b></td>
                    </tr>

                    <tr>
                        <td>{% trans 'Client' %}</td>
                        <td>#{{ticket.client.id}} <a href=\"{{ 'client/manage'|alink }}/{{ ticket.client.id }}\">{{ticket.client.first_name}} {{ticket.client.last_name}}</a></td>
                    </tr>
                    
                    <tr>
                        <td>{% trans 'Help desk' %}</td>
                        <td class=\"shd\">
                            {{ mf.selectbox('support_helpdesk_id', admin.support_helpdesk_get_pairs, ticket.support_helpdesk_id, 1) }}
                        </td>
                    </tr>

                    <tr>
                        <td>{% trans 'Status' %}</td>
                        <td>
                            {% for tcode,tstatus in admin.support_ticket_get_statuses({\"titles\":1}) %}
                            <label><input class=\"tst\" type=\"radio\" name=\"status\" value=\"{{tcode}}\" {% if ticket.status == tcode %}checked=\"checked\"{% endif %} /> {{ tstatus }}</label>
                            {% endfor %}
                        </td>
                    </tr>
                    
                    <tr>
                        <td>{% trans 'Time opened' %}</td>
                        <td>{{ticket.created_at|date('l, d F Y')}}</td>
                    </tr>

                 </tbody>

                {% set task = ticket.rel %}
                <tbody>
                {% if task.task %}
                <tr>
                    <td><label>{% trans 'Task' %}</label></td>
                    <td><strong>{{ mf.status_name(task.task) }}</strong></td>
                </tr>
                {% endif %}

                {% if task.type == 'order' %}
                <tr>
                    <td><label>{% trans 'Related to' %}</label></td>
                    <td><a href=\"{{ 'order/manage'|alink }}/{{ task.id }}\">Order #{{ task.id }}</a></td>
                </tr>
                {% endif %}

                {% if task.task == 'upgrade' %}
                <tr>
                    <td><label>{% trans 'Upgrade to' %}</label></td>
                    <td><a href=\"{{ 'product/manage'|alink }}/{{task.new_value}}\">{{ admin.product_get_pairs[task.new_value] }}</a></td>
                </tr>
                {% endif %}

                </tbody>

                 <tfoot>
                     <tr>
                         <td colspan=\"2\">
                            <div class=\"aligncenter\">
                                {% if ticket.status != 'closed' %}
                                <a href=\"{{ 'api/admin/support/ticket_close'|link({'id' : ticket.id}) }}\" data-api-confirm=\"Are you sure?\" data-api-redirect=\"{{ 'support'|alink({'status' : 'open' }) }}\" class=\"btn55 mr10 api-link\" ><img src=\"images/icons/middlenav/stop.png\" alt=\"\"><span>{% trans 'Close ticket' %}</span></a>
                                {% endif %}
                                <a href=\"{{ 'api/admin/support/ticket_delete'|link({'id' : ticket.id}) }}\" data-api-confirm=\"Are you sure?\" data-api-redirect=\"{{ 'support'|alink }}\" class=\"btn55 mr10 api-link\"><img src=\"images/icons/middlenav/trash.png\" alt=\"\"><span>{% trans 'Delete' %}</span></a>

                                {% if task.status == 'pending' %}
                                <a href=\"{{ 'api/admin/support/task_complete'|link({'id' : ticket.id }) }}\" class=\"btn55 mr10 api-link\" data-api-reload=\"1\"><img src=\"images/icons/middlenav/check.png\" alt=\"\" data-api-reload=\"1\"><span>{% trans 'Set Task complete' %}</span></a>
                                {% endif %}
                            </div>
                         </td>
                     </tr>
                 </tfoot>
            </table>
            <div class=\"fix\"></div>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-manage\">
            <form method=\"post\" action=\"{{ 'api/admin/support/ticket_update'|link }}\" class=\"mainForm api-form\" data-api-reload=\"1\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>{% trans 'Subject' %}</label>
                        <div class=\"formRight noborder\">
                            <input type=\"text\" name=\"subject\" value=\"{{ticket.subject}}\" required=\"required\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Help desk' %}</label>
                        <div class=\"formRight\">
                            {{ mf.selectbox('support_helpdesk_id', admin.support_helpdesk_get_pairs, ticket.support_helpdesk_id, 1) }}
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Status' %}</label>
                        <div class=\"formRight\">
                            {{ mf.selectbox('status', admin.support_ticket_get_statuses({\"titles\":1}), ticket.status, 1) }}
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Priority' %}</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"priority\" value=\"{{ticket.priority}}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                     <input type=\"submit\" value=\"{% trans 'Update' %}\" class=\"greyishBtn submitForm\" />
                </fieldset>
                <input type=\"hidden\" name=\"id\" value=\"{{ ticket.id }}\">
            </form>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-notes\">

            <table class=\"tableStatic wide\">
            {% for note in ticket.notes %}
                <tr {% if loop.first %}class=\"noborder\"{% endif %}>
                    <td>{{ note.note }}</td>
                    <td style=\"width: 20%\"><a href=\"{{ 'staff'|alink }}/manage/{{ note.admin_id }}\">{{ note.author.name }}</a></td>
                    <td style=\"width: 5%\">
                        <a href=\"{{ 'api/admin/support/note_delete'|link({'id' : note.id}) }}\" data-api-confirm=\"Are you sure?\" data-api-reload=\"1\" class=\"bb-button btn14 api-link\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                    </td>
                </tr>
            {% endfor %}
                <tfoot>
                    <tr>
                        <td colspan=\"3\" {% if ticket.notes|length == 0 %}class=\"noborder\"{% endif %}>

                            <form method=\"post\" action=\"{{ 'api/admin/support/note_create'|link }}\" class=\"mainForm api-form\" data-api-reload=\"1\">
                                <fieldset>
                                    <textarea name=\"note\" cols=\"5\" rows=\"5\" required=\"required\" placeholder=\"Add new note\" style=\"width: 98%\"></textarea>
                                    <input type=\"submit\" value=\"{% trans 'Add note' %}\" class=\"greyishBtn submitForm\" style=\" margin-top: 22px; width: 100px\"/>
                                    <input type=\"hidden\" name=\"ticket_id\" value=\"{{ ticket.id }}\">
                                </fieldset>
                            </form>

                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-support\">
            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td>ID</td>
                        <td width=\"60%\">Subject</td>
                        <td width=\"15%\">Help desk</td>
                        <td width=\"15%\">Status</td>
                        <td>&nbsp;</td>
                    </tr>
                </thead>

                <tbody>
                    {% set tickets = admin.support_ticket_get_list({\"per_page\":\"20\", \"client_id\":ticket.client.id}) %}
                    {% for ticket in tickets.list %}
                    <tr>
                        <td>{{ ticket.id }}</td>
                        <td>{{ ticket.subject|truncate(30) }}</td>
                        <td>{{ ticket.helpdesk.name }}</td>
                        <td>{{ mf.status_name(ticket.status) }}</td>
                        <td class=\"actions\"><a class=\"bb-button btn14\" href=\"{{ '/support/ticket'|alink }}/{{ticket.id}}\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a></td>
                    </tr>
                    {% else %}
                    <tr>
                        <td colspan=\"5\">{% trans 'The list is empty' %}</td>
                    </tr>
                    {% endfor %}
                </tbody>

                <tfoot>
                    <tr>
                        <td colspan=\"5\">
                            <a href=\"{{ 'support'|alink({'client_id' : client.id})}}#tab-new\" class=\"btnIconLeft mr10 mt5\" ><img src=\"images/icons/dark/help.png\" alt=\"\" class=\"icon\"><span>{% trans 'New support ticket' %}</span></a>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

    </div>
</div>

<div class=\"conversation\">
{% for i, message in ticket.messages %}
<div class=\"widget {{ loop.last ? 'last' : '' }}\" id=\"{{ message.id == request_message ? 'direct-msg' : ''}}\">
    <div class=\"head\" style=\"cursor: pointer;\">
        <h5 class=\"no-icon\"><img src=\"{{ message.author.email|gravatar }}?size=20\" alt=\"{{ message.author.name }}\" class=\"gravatar\"/> {{ message.author.name }}</h5>
        <h5 style=\"float:right;\"><a href=\"{{ '/support/ticket'|alink }}/{{ticket.id}}/message/{{message.id}}\" style=\"color:inherit\">{{ message.created_at|bb_datetime }}</a></h5>
    </div>

    <div class=\"body\" style=\"display:{{ loop.last or loop.index+1 == ticket.messages|length ? 'block' : 'none' }};\">
        {{ message.content|bbmd }}
        <div class=\"clear\"></div>
    </div>
</div>
{% endfor %}
</div>


<div class=\"widget\" id=\"reply-box\">
    <div class=\"head\">
        <h5 class=\"iSpeech\">{% trans 'Reply as' %} {{ profile.name }}</h5>
        {%  include 'mod_support_canned_selector.phtml' %}
    </div>

    <form method=\"post\" action=\"{{ 'api/admin/support/ticket_reply'|link }}\" class=\"mainForm api-form\" data-api-redirect=\"{{ 'support'|alink({'status' : 'open' }) }}\">
        <fieldset>
            <textarea name=\"content\" cols=\"5\" rows=\"20\" required=\"required\" class=\"bb-textarea\" id=\"rt\">Hello {{ ticket.client.first_name }},
{{ canned_delay_message }}



{{ profile.signature }}
{{ ticket.helpdesk.signature }}</textarea>
            <input type=\"hidden\" name=\"id\" value=\"{{ ticket.id }}\">
            <input type=\"submit\" value=\"{% trans 'Post' %}\" class=\"greyishBtn submitForm\" />


            <div class=\"body\">
                <a href=\"#\"  class=\"btnIconLeft mr10\" id=\"toggleMessages\" ><span>{% trans 'Show/Hide messages' %}</span></a>

                {% if ticket.messages|length > 2 and ticket.status != 'closed' %}
                <a href=\"{{ 'api/admin/support/ticket_close'|link({'id' : ticket.id }) }}\" data-api-confirm=\"Are you sure?\" data-api-redirect=\"{{ 'support'|alink({'status' : 'open' }) }}\" class=\"btnIconLeft mr10 api-link\" ><span>{% trans 'Close ticket' %}</span></a>
                {% endif %}
            </div>
        </fieldset>
    </form>

    <div class=\"clear\"></div>
</div>

{% endblock %}

{% block js%}
<script type=\"text/javascript\">
function setSelRange(inputEl, selStart, selEnd) { 
   if (inputEl.setSelectionRange) { 
     inputEl.focus(); 
     inputEl.setSelectionRange(selStart, selEnd); 
   } else if (inputEl.createTextRange) { 
     var range = inputEl.createTextRange(); 
     range.collapse(true); 
     range.moveEnd('character', selEnd); 
     range.moveStart('character', selStart); 
     range.select(); 
   }
}

\$(function() {

    \$('#reply-box textarea').focus();
    var ta = document.getElementById('rt');
    var pos = ta.innerHTML.indexOf('\\n') + 2;
    setSelRange(ta, pos, pos);
 
    \$('.shd select').change(function(){
        bb.get('admin/support/ticket_update', {id:{{ticket.id}}, support_helpdesk_id: \$(this).val()});
    });
    
    \$('input.tst').click(function(){
        bb.get('admin/support/ticket_update', {id:{{ticket.id}}, status: \$(this).val()});
    });

    \$('.conversation').on('click', '.head', function(e){
        if( e.target !== this )
            return;
        \$(this).siblings('.body').toggle();
        return false;
    });

    if (\$('#direct-msg').length > 0){
        \$('#direct-msg > .body').show();
        \$('html, body').animate({
            scrollTop: \$('#direct-msg').offset().top-50
        }, 500);
        \$('#direct-msg').css(\"background-color\", \"rgb(228, 228, 228)\");
    }

    var showAllMessages = false;
    \$('.api-form').on('click', '#toggleMessages', function(e) {
        e.preventDefault();
        showAllMessages = !showAllMessages;
        \$('.conversation .body').toggle(showAllMessages);

    });
});
</script>
{% endblock %}

{% block head %}{{ mf.bb_editor('.bb-textarea') }}{% endblock %}", "mod_support_ticket.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-themes/admin_default/html/mod_support_ticket.phtml");
    }
}
