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
class __TwigTemplate_9f79de32825827639ea8f1708bd5c6152d36996d123ab622b57fd0f93d751ee7 extends Template
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
        // line 3
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_support_ticket.phtml", 3)->unwrap();
        // line 15
        $context["active_menu"] = "support";
        // line 1
        $this->getParent($context)->display($context, array_merge($this->blocks, $blocks));
    }

    // line 5
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "subject", [], "any", false, false, false, 5), "html", null, true);
        echo " - ";
        echo twig_escape_filter($this->env, twig_length_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "messages", [], "any", false, false, false, 5)), "html", null, true);
        echo " ";
        echo twig_escape_filter($this->env, gettext("message(s)"), "html", null, true);
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
        echo twig_escape_filter($this->env, gettext("Home"), "html", null, true);
        echo "</a></li>
    <li><a href=\"";
        // line 10
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support");
        echo "\">";
        echo twig_escape_filter($this->env, gettext("Support tickets"), "html", null, true);
        echo "</a></li>
    <li class=\"lastB\">#";
        // line 11
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "id", [], "any", false, false, false, 11), "html", null, true);
        echo " - ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "subject", [], "any", false, false, false, 11), "html", null, true);
        echo "</li>
</ul>
";
    }

    // line 17
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 18
        echo "<div class=\"widget simpleTabs tabsRight\">
    <div class=\"head\">
        <h5 class=\"iSpeech\">";
        // line 20
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "subject", [], "any", false, false, false, 20), "html", null, true);
        echo "</h5>
    </div>

    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">";
        // line 24
        echo twig_escape_filter($this->env, gettext("Ticket"), "html", null, true);
        echo "</a></li>
        <li><a href=\"#tab-manage\">";
        // line 25
        echo twig_escape_filter($this->env, gettext("Manage"), "html", null, true);
        echo "</a></li>
        <li><a href=\"#tab-notes\">";
        // line 26
        if ((twig_length_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "notes", [], "any", false, false, false, 26)) > 0)) {
            echo twig_escape_filter($this->env, twig_length_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "notes", [], "any", false, false, false, 26)), "html", null, true);
            echo " - ";
        }
        echo twig_escape_filter($this->env, gettext("Notes"), "html", null, true);
        echo "</a></li>
        <li><a href=\"#tab-support\">";
        // line 27
        echo twig_escape_filter($this->env, gettext("Client tickets"), "html", null, true);
        echo "</a></li>
    </ul>
    
    <div class=\"tabs_container\">
        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-index\">
            <table class=\"tableStatic wide\">
                <tbody>
                    <tr class=\"noborder\">
                        <td style=\"width: 30%\">";
        // line 36
        echo twig_escape_filter($this->env, gettext("Ticket ID"), "html", null, true);
        echo "</td>
                        <td><b>#";
        // line 37
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "id", [], "any", false, false, false, 37), "html", null, true);
        echo "</b></td>
                    </tr>

                    <tr>
                        <td>";
        // line 41
        echo twig_escape_filter($this->env, gettext("Client"), "html", null, true);
        echo "</td>
                        <td>#";
        // line 42
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "client", [], "any", false, false, false, 42), "id", [], "any", false, false, false, 42), "html", null, true);
        echo " <a href=\"";
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("client/manage");
        echo "/";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "client", [], "any", false, false, false, 42), "id", [], "any", false, false, false, 42), "html", null, true);
        echo "\">";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "client", [], "any", false, false, false, 42), "first_name", [], "any", false, false, false, 42), "html", null, true);
        echo " ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "client", [], "any", false, false, false, 42), "last_name", [], "any", false, false, false, 42), "html", null, true);
        echo "</a></td>
                    </tr>
                    
                    <tr>
                        <td>";
        // line 46
        echo twig_escape_filter($this->env, gettext("Help desk"), "html", null, true);
        echo "</td>
                        <td class=\"shd\">
                            ";
        // line 48
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["support_helpdesk_id", twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "support_helpdesk_get_pairs", [], "any", false, false, false, 48), twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "support_helpdesk_id", [], "any", false, false, false, 48), 1], 48, $context, $this->getSourceContext());
        echo "
                        </td>
                    </tr>

                    <tr>
                        <td>";
        // line 53
        echo twig_escape_filter($this->env, gettext("Status"), "html", null, true);
        echo "</td>
                        <td>
                            ";
        // line 55
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "support_ticket_get_statuses", [0 => ["titles" => 1]], "method", false, false, false, 55));
        foreach ($context['_seq'] as $context["tcode"] => $context["tstatus"]) {
            // line 56
            echo "                            <label><input class=\"tst\" type=\"radio\" name=\"status\" value=\"";
            echo twig_escape_filter($this->env, $context["tcode"], "html", null, true);
            echo "\" ";
            if ((twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "status", [], "any", false, false, false, 56) == $context["tcode"])) {
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
        // line 58
        echo "                        </td>
                    </tr>
                    
                    <tr>
                        <td>";
        // line 62
        echo twig_escape_filter($this->env, gettext("Time opened"), "html", null, true);
        echo "</td>
                        <td>";
        // line 63
        echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "created_at", [], "any", false, false, false, 63), "l, d F Y"), "html", null, true);
        echo "</td>
                    </tr>

                 </tbody>

                ";
        // line 68
        $context["task"] = twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "rel", [], "any", false, false, false, 68);
        // line 69
        echo "                <tbody>
                ";
        // line 70
        if (twig_get_attribute($this->env, $this->source, ($context["task"] ?? null), "task", [], "any", false, false, false, 70)) {
            // line 71
            echo "                <tr>
                    <td><label>";
            // line 72
            echo twig_escape_filter($this->env, gettext("Task"), "html", null, true);
            echo "</label></td>
                    <td><strong>";
            // line 73
            echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, ($context["task"] ?? null), "task", [], "any", false, false, false, 73)], 73, $context, $this->getSourceContext());
            echo "</strong></td>
                </tr>
                ";
        }
        // line 76
        echo "
                ";
        // line 77
        if ((twig_get_attribute($this->env, $this->source, ($context["task"] ?? null), "type", [], "any", false, false, false, 77) == "order")) {
            // line 78
            echo "                <tr>
                    <td><label>";
            // line 79
            echo twig_escape_filter($this->env, gettext("Related to"), "html", null, true);
            echo "</label></td>
                    <td><a href=\"";
            // line 80
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("order/manage");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["task"] ?? null), "id", [], "any", false, false, false, 80), "html", null, true);
            echo "\">Order #";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["task"] ?? null), "id", [], "any", false, false, false, 80), "html", null, true);
            echo "</a></td>
                </tr>
                ";
        }
        // line 83
        echo "
                ";
        // line 84
        if ((twig_get_attribute($this->env, $this->source, ($context["task"] ?? null), "task", [], "any", false, false, false, 84) == "upgrade")) {
            // line 85
            echo "                <tr>
                    <td><label>";
            // line 86
            echo twig_escape_filter($this->env, gettext("Upgrade to"), "html", null, true);
            echo "</label></td>
                    <td><a href=\"";
            // line 87
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("product/manage");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["task"] ?? null), "new_value", [], "any", false, false, false, 87), "html", null, true);
            echo "\">";
            echo twig_escape_filter($this->env, (($__internal_compile_0 = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "product_get_pairs", [], "any", false, false, false, 87)) && is_array($__internal_compile_0) || $__internal_compile_0 instanceof ArrayAccess ? ($__internal_compile_0[twig_get_attribute($this->env, $this->source, ($context["task"] ?? null), "new_value", [], "any", false, false, false, 87)] ?? null) : null), "html", null, true);
            echo "</a></td>
                </tr>
                ";
        }
        // line 90
        echo "
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan=\"2\">
                            <div class=\"aligncenter\">
                                ";
        // line 96
        if ((twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "status", [], "any", false, false, false, 96) != "closed")) {
            // line 97
            echo "                                <a href=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/support/ticket_close", ["id" => twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "id", [], "any", false, false, false, 97)]);
            echo "\" data-api-confirm=\"Are you sure?\" data-api-redirect=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support", ["status" => "open"]);
            echo "\" class=\"btn55 mr10 api-link\" >
                                    <img src=\"images/icons/middlenav/stop.png\" alt=\"\"><span>";
            // line 98
            echo twig_escape_filter($this->env, gettext("Close ticket"), "html", null, true);
            echo "</span>
                                </a>
                                ";
        }
        // line 101
        echo "                                <a href=\"";
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/support/ticket_delete", ["id" => twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "id", [], "any", false, false, false, 101)]);
        echo "\" data-api-confirm=\"Are you sure?\" data-api-redirect=\"";
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support");
        echo "\" class=\"btn55 mr10 api-link\">
                                    <img src=\"images/icons/middlenav/trash.png\" alt=\"\"><span>";
        // line 102
        echo twig_escape_filter($this->env, gettext("Delete"), "html", null, true);
        echo "</span>
                                </a>

                                ";
        // line 105
        if ((twig_get_attribute($this->env, $this->source, ($context["task"] ?? null), "status", [], "any", false, false, false, 105) == "pending")) {
            // line 106
            echo "                                <a href=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/support/task_complete", ["id" => twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "id", [], "any", false, false, false, 106)]);
            echo "\" class=\"btn55 mr10 api-link\" data-api-reload=\"1\">
                                    <img src=\"images/icons/middlenav/check.png\" alt=\"\" data-api-reload=\"1\"><span>";
            // line 107
            echo twig_escape_filter($this->env, gettext("Set Task complete"), "html", null, true);
            echo "</span>
                                </a>
                                ";
        }
        // line 110
        echo "                            </div>
                        </td>
                    </tr>
                </tfoot>
            </table>
            <div class=\"fix\"></div>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-manage\">
            <form method=\"post\" action=\"";
        // line 119
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/support/ticket_update");
        echo "\" class=\"mainForm api-form\" data-api-reload=\"1\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>";
        // line 122
        echo twig_escape_filter($this->env, gettext("Subject"), "html", null, true);
        echo "</label>
                        <div class=\"formRight noborder\">
                            <input type=\"text\" name=\"subject\" value=\"";
        // line 124
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "subject", [], "any", false, false, false, 124), "html", null, true);
        echo "\" required=\"required\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 130
        echo twig_escape_filter($this->env, gettext("Help desk"), "html", null, true);
        echo "</label>
                        <div class=\"formRight\">
                            ";
        // line 132
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["support_helpdesk_id", twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "support_helpdesk_get_pairs", [], "any", false, false, false, 132), twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "support_helpdesk_id", [], "any", false, false, false, 132), 1], 132, $context, $this->getSourceContext());
        echo "
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 138
        echo twig_escape_filter($this->env, gettext("Status"), "html", null, true);
        echo "</label>
                        <div class=\"formRight\">
                            ";
        // line 140
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["status", twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "support_ticket_get_statuses", [0 => ["titles" => 1]], "method", false, false, false, 140), twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "status", [], "any", false, false, false, 140), 1], 140, $context, $this->getSourceContext());
        echo "
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 146
        echo twig_escape_filter($this->env, gettext("Priority"), "html", null, true);
        echo "</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"priority\" value=\"";
        // line 148
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "priority", [], "any", false, false, false, 148), "html", null, true);
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                     <input type=\"submit\" value=\"";
        // line 152
        echo twig_escape_filter($this->env, gettext("Update"), "html", null, true);
        echo "\" class=\"greyishBtn submitForm\" />
                </fieldset>
                <input type=\"hidden\" name=\"id\" value=\"";
        // line 154
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "id", [], "any", false, false, false, 154), "html", null, true);
        echo "\">
            </form>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-notes\">

            <table class=\"tableStatic wide\">
            ";
        // line 161
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "notes", [], "any", false, false, false, 161));
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
            // line 162
            echo "                <tr ";
            if (twig_get_attribute($this->env, $this->source, $context["loop"], "first", [], "any", false, false, false, 162)) {
                echo "class=\"noborder\"";
            }
            echo ">
                    <td>";
            // line 163
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["note"], "note", [], "any", false, false, false, 163), "html", null, true);
            echo "</td>
                    <td style=\"width: 20%\"><a href=\"";
            // line 164
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("staff");
            echo "/manage/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["note"], "admin_id", [], "any", false, false, false, 164), "html", null, true);
            echo "\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["note"], "author", [], "any", false, false, false, 164), "name", [], "any", false, false, false, 164), "html", null, true);
            echo "</a></td>
                    <td style=\"width: 5%\">
                        <a href=\"";
            // line 166
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/support/note_delete", ["id" => twig_get_attribute($this->env, $this->source, $context["note"], "id", [], "any", false, false, false, 166)]);
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
        // line 170
        echo "                <tfoot>
                    <tr>
                        <td colspan=\"3\" ";
        // line 172
        if ((twig_length_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "notes", [], "any", false, false, false, 172)) == 0)) {
            echo "class=\"noborder\"";
        }
        echo ">

                            <form method=\"post\" action=\"";
        // line 174
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/support/note_create");
        echo "\" class=\"mainForm api-form\" data-api-reload=\"1\">
                                <fieldset>
                                    <textarea name=\"note\" cols=\"5\" rows=\"5\" required=\"required\" placeholder=\"Add new note\" style=\"width: 98%\"></textarea>
                                    <input type=\"submit\" value=\"";
        // line 177
        echo twig_escape_filter($this->env, gettext("Add note"), "html", null, true);
        echo "\" class=\"greyishBtn submitForm\" style=\" margin-top: 22px; width: 100px\"/>
                                    <input type=\"hidden\" name=\"ticket_id\" value=\"";
        // line 178
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "id", [], "any", false, false, false, 178), "html", null, true);
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
        // line 201
        $context["tickets"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "support_ticket_get_list", [0 => ["per_page" => "20", "client_id" => twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "client", [], "any", false, false, false, 201), "id", [], "any", false, false, false, 201)]], "method", false, false, false, 201);
        // line 202
        echo "                    ";
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["tickets"] ?? null), "list", [], "any", false, false, false, 202));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["_key"] => $context["ticket"]) {
            // line 203
            echo "                    <tr>
                        <td>";
            // line 204
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "id", [], "any", false, false, false, 204), "html", null, true);
            echo "</td>
                        <td>";
            // line 205
            echo twig_escape_filter($this->env, twig_truncate_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "subject", [], "any", false, false, false, 205), 30), "html", null, true);
            echo "</td>
                        <td>";
            // line 206
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["ticket"], "helpdesk", [], "any", false, false, false, 206), "name", [], "any", false, false, false, 206), "html", null, true);
            echo "</td>
                        <td>";
            // line 207
            echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, $context["ticket"], "status", [], "any", false, false, false, 207)], 207, $context, $this->getSourceContext());
            echo "</td>
                        <td class=\"actions\">
                            <a class=\"bb-button btn14\" href=\"";
            // line 209
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/support/ticket");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "id", [], "any", false, false, false, 209), "html", null, true);
            echo "\">
                                <img src=\"images/icons/dark/pencil.png\" alt=\"\">
                            </a>
                        </td>
                    </tr>
                    ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 215
            echo "                    <tr>
                        <td colspan=\"5\">";
            // line 216
            echo twig_escape_filter($this->env, gettext("The list is empty"), "html", null, true);
            echo "</td>
                    </tr>
                    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['ticket'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 219
        echo "                </tbody>

                <tfoot>
                    <tr>
                        <td colspan=\"5\">
                            <a href=\"";
        // line 224
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support", ["client_id" => twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "id", [], "any", false, false, false, 224)]);
        echo "#tab-new\" class=\"btnIconLeft mr10 mt5\">
                                <img src=\"images/icons/dark/help.png\" alt=\"\" class=\"icon\"><span>";
        // line 225
        echo twig_escape_filter($this->env, gettext("New support ticket"), "html", null, true);
        echo "</span>
                            </a>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

    </div>
</div>

<div class=\"conversation\">
";
        // line 237
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "messages", [], "any", false, false, false, 237));
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
            // line 238
            echo "<div class=\"widget ";
            echo ((twig_get_attribute($this->env, $this->source, $context["loop"], "last", [], "any", false, false, false, 238)) ? ("last") : (""));
            echo "\" id=\"";
            echo (((twig_get_attribute($this->env, $this->source, $context["message"], "id", [], "any", false, false, false, 238) == ($context["request_message"] ?? null))) ? ("direct-msg") : (""));
            echo "\">
    <div class=\"head\" style=\"cursor: pointer;\">
        <h5 class=\"no-icon\"><img src=\"";
            // line 240
            echo twig_escape_filter($this->env, twig_gravatar_filter(twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["message"], "author", [], "any", false, false, false, 240), "email", [], "any", false, false, false, 240)), "html", null, true);
            echo "?size=20\" alt=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["message"], "author", [], "any", false, false, false, 240), "name", [], "any", false, false, false, 240), "html", null, true);
            echo "\" class=\"gravatar\"/> ";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["message"], "author", [], "any", false, false, false, 240), "name", [], "any", false, false, false, 240), "html", null, true);
            echo "</h5>
        <h5 style=\"float:right;\"><a href=\"";
            // line 241
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/support/ticket");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "id", [], "any", false, false, false, 241), "html", null, true);
            echo "/message/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["message"], "id", [], "any", false, false, false, 241), "html", null, true);
            echo "\" style=\"color:inherit\">";
            echo twig_escape_filter($this->env, $this->extensions['Box_TwigExtensions']->twig_bb_datetime(twig_get_attribute($this->env, $this->source, $context["message"], "created_at", [], "any", false, false, false, 241)), "html", null, true);
            echo "</a></h5>
    </div>

    <div class=\"body\" style=\"display:";
            // line 244
            echo (((twig_get_attribute($this->env, $this->source, $context["loop"], "last", [], "any", false, false, false, 244) || ((twig_get_attribute($this->env, $this->source, $context["loop"], "index", [], "any", false, false, false, 244) + 1) == twig_length_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "messages", [], "any", false, false, false, 244))))) ? ("block") : ("none"));
            echo ";\">
        ";
            // line 245
            echo twig_bbmd_filter($this->env, twig_get_attribute($this->env, $this->source, $context["message"], "content", [], "any", false, false, false, 245));
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
        // line 250
        echo "</div>


<div class=\"widget\" id=\"reply-box\">
    <div class=\"head\">
        <h5 class=\"iSpeech\">";
        // line 255
        echo twig_escape_filter($this->env, gettext("Reply as"), "html", null, true);
        echo " ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "name", [], "any", false, false, false, 255), "html", null, true);
        echo "</h5>
        ";
        // line 256
        $this->loadTemplate("mod_support_canned_selector.phtml", "mod_support_ticket.phtml", 256)->display($context);
        // line 257
        echo "    </div>

    <form method=\"post\" action=\"";
        // line 259
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/support/ticket_reply");
        echo "\" class=\"mainForm api-form\" data-api-redirect=\"";
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support", ["status" => "open"]);
        echo "\">
        <fieldset>
            <textarea name=\"content\" cols=\"5\" rows=\"20\" required=\"required\" class=\"bb-textarea\" id=\"rt\">Hello ";
        // line 261
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "client", [], "any", false, false, false, 261), "first_name", [], "any", false, false, false, 261), "html", null, true);
        echo ",
";
        // line 262
        echo twig_escape_filter($this->env, ($context["canned_delay_message"] ?? null), "html", null, true);
        echo "



";
        // line 266
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "signature", [], "any", false, false, false, 266), "html", null, true);
        echo "
";
        // line 267
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "helpdesk", [], "any", false, false, false, 267), "signature", [], "any", false, false, false, 267), "html", null, true);
        echo "</textarea>
            <input type=\"hidden\" name=\"id\" value=\"";
        // line 268
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "id", [], "any", false, false, false, 268), "html", null, true);
        echo "\">
            <input type=\"submit\" value=\"";
        // line 269
        echo twig_escape_filter($this->env, gettext("Post"), "html", null, true);
        echo "\" class=\"greyishBtn submitForm\" />

            <div class=\"body\">
                <a href=\"#\"  class=\"btnIconLeft mr10\" id=\"toggleMessages\" ><span>";
        // line 272
        echo twig_escape_filter($this->env, gettext("Show/Hide messages"), "html", null, true);
        echo "</span></a>

                ";
        // line 274
        if (((twig_length_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "messages", [], "any", false, false, false, 274)) > 2) && (twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "status", [], "any", false, false, false, 274) != "closed"))) {
            // line 275
            echo "                <a href=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/support/ticket_close", ["id" => twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "id", [], "any", false, false, false, 275)]);
            echo "\" data-api-confirm=\"Are you sure?\" data-api-redirect=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support", ["status" => "open"]);
            echo "\" class=\"btnIconLeft mr10 api-link\" >
                    <span>";
            // line 276
            echo twig_escape_filter($this->env, gettext("Close ticket"), "html", null, true);
            echo "</span>
                </a>
                ";
        }
        // line 279
        echo "            </div>
        </fieldset>
    </form>
    <div class=\"clear\"></div>
</div>
";
    }

    // line 286
    public function block_js($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 287
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
        bb.get('admin/support/ticket_update', {
            id: ";
        // line 312
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "id", [], "any", false, false, false, 312), "html", null, true);
        echo ",
            support_helpdesk_id: \$(this).val()
        });
    });
    
    \$('input.tst').click(function(){
        bb.get('admin/support/ticket_update', {
            id: ";
        // line 319
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "id", [], "any", false, false, false, 319), "html", null, true);
        echo ",
            status: \$(this).val()
        });
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

    // line 351
    public function block_head($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo twig_call_macro($macros["mf"], "macro_bb_editor", [".bb-textarea"], 351, $context, $this->getSourceContext());
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
        return array (  829 => 351,  794 => 319,  784 => 312,  757 => 287,  753 => 286,  744 => 279,  738 => 276,  731 => 275,  729 => 274,  724 => 272,  718 => 269,  714 => 268,  710 => 267,  706 => 266,  699 => 262,  695 => 261,  688 => 259,  684 => 257,  682 => 256,  676 => 255,  669 => 250,  650 => 245,  646 => 244,  634 => 241,  626 => 240,  618 => 238,  601 => 237,  586 => 225,  582 => 224,  575 => 219,  566 => 216,  563 => 215,  550 => 209,  545 => 207,  541 => 206,  537 => 205,  533 => 204,  530 => 203,  524 => 202,  522 => 201,  496 => 178,  492 => 177,  486 => 174,  479 => 172,  475 => 170,  457 => 166,  448 => 164,  444 => 163,  437 => 162,  420 => 161,  410 => 154,  405 => 152,  398 => 148,  393 => 146,  384 => 140,  379 => 138,  370 => 132,  365 => 130,  356 => 124,  351 => 122,  345 => 119,  334 => 110,  328 => 107,  323 => 106,  321 => 105,  315 => 102,  308 => 101,  302 => 98,  295 => 97,  293 => 96,  285 => 90,  275 => 87,  271 => 86,  268 => 85,  266 => 84,  263 => 83,  253 => 80,  249 => 79,  246 => 78,  244 => 77,  241 => 76,  235 => 73,  231 => 72,  228 => 71,  226 => 70,  223 => 69,  221 => 68,  213 => 63,  209 => 62,  203 => 58,  188 => 56,  184 => 55,  179 => 53,  171 => 48,  166 => 46,  151 => 42,  147 => 41,  140 => 37,  136 => 36,  124 => 27,  116 => 26,  112 => 25,  108 => 24,  101 => 20,  97 => 18,  93 => 17,  84 => 11,  78 => 10,  72 => 9,  69 => 8,  65 => 7,  54 => 5,  50 => 1,  48 => 15,  46 => 3,  39 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}

{% import \"macro_functions.phtml\" as mf %}

{% block meta_title %}{{ ticket.subject }} - {{ ticket.messages|length }} {{ 'message(s)'|trans }}{% endblock %}

{% block breadcrumbs %}
<ul>
    <li class=\"firstB\"><a href=\"{{ '/'|alink }}\">{{ 'Home'|trans }}</a></li>
    <li><a href=\"{{ 'support'|alink }}\">{{ 'Support tickets'|trans }}</a></li>
    <li class=\"lastB\">#{{ ticket.id }} - {{ ticket.subject }}</li>
</ul>
{% endblock %}

{% set active_menu = 'support' %}

{% block content %}
<div class=\"widget simpleTabs tabsRight\">
    <div class=\"head\">
        <h5 class=\"iSpeech\">{{ ticket.subject }}</h5>
    </div>

    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">{{ 'Ticket'|trans }}</a></li>
        <li><a href=\"#tab-manage\">{{ 'Manage'|trans }}</a></li>
        <li><a href=\"#tab-notes\">{% if ticket.notes|length > 0%}{{ ticket.notes|length }} - {% endif %}{{ 'Notes'|trans }}</a></li>
        <li><a href=\"#tab-support\">{{ 'Client tickets'|trans }}</a></li>
    </ul>
    
    <div class=\"tabs_container\">
        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-index\">
            <table class=\"tableStatic wide\">
                <tbody>
                    <tr class=\"noborder\">
                        <td style=\"width: 30%\">{{ 'Ticket ID'|trans }}</td>
                        <td><b>#{{ ticket.id }}</b></td>
                    </tr>

                    <tr>
                        <td>{{ 'Client'|trans }}</td>
                        <td>#{{ ticket.client.id }} <a href=\"{{ 'client/manage'|alink }}/{{ ticket.client.id }}\">{{ ticket.client.first_name }} {{ ticket.client.last_name }}</a></td>
                    </tr>
                    
                    <tr>
                        <td>{{ 'Help desk'|trans }}</td>
                        <td class=\"shd\">
                            {{ mf.selectbox('support_helpdesk_id', admin.support_helpdesk_get_pairs, ticket.support_helpdesk_id, 1) }}
                        </td>
                    </tr>

                    <tr>
                        <td>{{ 'Status'|trans }}</td>
                        <td>
                            {% for tcode,tstatus in admin.support_ticket_get_statuses({\"titles\":1}) %}
                            <label><input class=\"tst\" type=\"radio\" name=\"status\" value=\"{{tcode}}\" {% if ticket.status == tcode %}checked=\"checked\"{% endif %} /> {{ tstatus }}</label>
                            {% endfor %}
                        </td>
                    </tr>
                    
                    <tr>
                        <td>{{ 'Time opened'|trans }}</td>
                        <td>{{ ticket.created_at|date('l, d F Y') }}</td>
                    </tr>

                 </tbody>

                {% set task = ticket.rel %}
                <tbody>
                {% if task.task %}
                <tr>
                    <td><label>{{ 'Task'|trans }}</label></td>
                    <td><strong>{{ mf.status_name(task.task) }}</strong></td>
                </tr>
                {% endif %}

                {% if task.type == 'order' %}
                <tr>
                    <td><label>{{ 'Related to'|trans }}</label></td>
                    <td><a href=\"{{ 'order/manage'|alink }}/{{ task.id }}\">Order #{{ task.id }}</a></td>
                </tr>
                {% endif %}

                {% if task.task == 'upgrade' %}
                <tr>
                    <td><label>{{ 'Upgrade to'|trans }}</label></td>
                    <td><a href=\"{{ 'product/manage'|alink }}/{{ task.new_value }}\">{{ admin.product_get_pairs[task.new_value] }}</a></td>
                </tr>
                {% endif %}

                </tbody>
                <tfoot>
                    <tr>
                        <td colspan=\"2\">
                            <div class=\"aligncenter\">
                                {% if ticket.status != 'closed' %}
                                <a href=\"{{ 'api/admin/support/ticket_close'|link({'id' : ticket.id}) }}\" data-api-confirm=\"Are you sure?\" data-api-redirect=\"{{ 'support'|alink({'status' : 'open' }) }}\" class=\"btn55 mr10 api-link\" >
                                    <img src=\"images/icons/middlenav/stop.png\" alt=\"\"><span>{{ 'Close ticket'|trans }}</span>
                                </a>
                                {% endif %}
                                <a href=\"{{ 'api/admin/support/ticket_delete'|link({'id' : ticket.id}) }}\" data-api-confirm=\"Are you sure?\" data-api-redirect=\"{{ 'support'|alink }}\" class=\"btn55 mr10 api-link\">
                                    <img src=\"images/icons/middlenav/trash.png\" alt=\"\"><span>{{ 'Delete'|trans }}</span>
                                </a>

                                {% if task.status == 'pending' %}
                                <a href=\"{{ 'api/admin/support/task_complete'|link({'id' : ticket.id }) }}\" class=\"btn55 mr10 api-link\" data-api-reload=\"1\">
                                    <img src=\"images/icons/middlenav/check.png\" alt=\"\" data-api-reload=\"1\"><span>{{ 'Set Task complete'|trans }}</span>
                                </a>
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
                        <label>{{ 'Subject'|trans }}</label>
                        <div class=\"formRight noborder\">
                            <input type=\"text\" name=\"subject\" value=\"{{ticket.subject}}\" required=\"required\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{{ 'Help desk'|trans }}</label>
                        <div class=\"formRight\">
                            {{ mf.selectbox('support_helpdesk_id', admin.support_helpdesk_get_pairs, ticket.support_helpdesk_id, 1) }}
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{{ 'Status'|trans }}</label>
                        <div class=\"formRight\">
                            {{ mf.selectbox('status', admin.support_ticket_get_statuses({ \"titles\": 1 }), ticket.status, 1) }}
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{{ 'Priority'|trans }}</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"priority\" value=\"{{ticket.priority}}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                     <input type=\"submit\" value=\"{{ 'Update'|trans }}\" class=\"greyishBtn submitForm\" />
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
                                    <input type=\"submit\" value=\"{{ 'Add note'|trans }}\" class=\"greyishBtn submitForm\" style=\" margin-top: 22px; width: 100px\"/>
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
                        <td class=\"actions\">
                            <a class=\"bb-button btn14\" href=\"{{ '/support/ticket'|alink }}/{{ ticket.id }}\">
                                <img src=\"images/icons/dark/pencil.png\" alt=\"\">
                            </a>
                        </td>
                    </tr>
                    {% else %}
                    <tr>
                        <td colspan=\"5\">{{ 'The list is empty'|trans }}</td>
                    </tr>
                    {% endfor %}
                </tbody>

                <tfoot>
                    <tr>
                        <td colspan=\"5\">
                            <a href=\"{{ 'support'|alink({ 'client_id': client.id}) }}#tab-new\" class=\"btnIconLeft mr10 mt5\">
                                <img src=\"images/icons/dark/help.png\" alt=\"\" class=\"icon\"><span>{{ 'New support ticket'|trans }}</span>
                            </a>
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
        <h5 class=\"iSpeech\">{{ 'Reply as'|trans }} {{ profile.name }}</h5>
        {% include 'mod_support_canned_selector.phtml' %}
    </div>

    <form method=\"post\" action=\"{{ 'api/admin/support/ticket_reply'|link }}\" class=\"mainForm api-form\" data-api-redirect=\"{{ 'support'|alink({'status' : 'open' }) }}\">
        <fieldset>
            <textarea name=\"content\" cols=\"5\" rows=\"20\" required=\"required\" class=\"bb-textarea\" id=\"rt\">Hello {{ ticket.client.first_name }},
{{ canned_delay_message }}



{{ profile.signature }}
{{ ticket.helpdesk.signature }}</textarea>
            <input type=\"hidden\" name=\"id\" value=\"{{ ticket.id }}\">
            <input type=\"submit\" value=\"{{ 'Post'|trans }}\" class=\"greyishBtn submitForm\" />

            <div class=\"body\">
                <a href=\"#\"  class=\"btnIconLeft mr10\" id=\"toggleMessages\" ><span>{{ 'Show/Hide messages'|trans }}</span></a>

                {% if ticket.messages|length > 2 and ticket.status != 'closed' %}
                <a href=\"{{ 'api/admin/support/ticket_close'|link({'id' : ticket.id }) }}\" data-api-confirm=\"Are you sure?\" data-api-redirect=\"{{ 'support'|alink({'status' : 'open' }) }}\" class=\"btnIconLeft mr10 api-link\" >
                    <span>{{ 'Close ticket'|trans }}</span>
                </a>
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
        bb.get('admin/support/ticket_update', {
            id: {{ ticket.id }},
            support_helpdesk_id: \$(this).val()
        });
    });
    
    \$('input.tst').click(function(){
        bb.get('admin/support/ticket_update', {
            id: {{ ticket.id }},
            status: \$(this).val()
        });
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

{% block head %}{{ mf.bb_editor('.bb-textarea') }}{% endblock %}
", "mod_support_ticket.phtml", "/shared/httpd/up-boxbilling/FOSSBilling/src/bb-themes/admin_default/html/mod_support_ticket.phtml");
    }
}
