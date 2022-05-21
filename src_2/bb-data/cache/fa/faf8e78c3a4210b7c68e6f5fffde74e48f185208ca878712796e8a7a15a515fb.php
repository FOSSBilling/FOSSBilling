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

/* mod_client_index.phtml */
class __TwigTemplate_d63eb74f531ffdf2ad08fec13f36ce3b9e20606f5cc48f4dc03ebb7b1823a3fe extends Template
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
        // line 2
        return "layout_default.phtml";
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 1
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_client_index.phtml", 1)->unwrap();
        // line 3
        $context["active_menu"] = "client";
        // line 2
        $this->parent = $this->loadTemplate("layout_default.phtml", "mod_client_index.phtml", 2);
        $this->parent->display($context, array_merge($this->blocks, $blocks));
    }

    // line 4
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo twig_escape_filter($this->env, gettext("Clients"), "html", null, true);
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
            echo twig_escape_filter($this->env, gettext("Filter clients"), "html", null, true);
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
            echo twig_escape_filter($this->env, gettext("ID"), "html", null, true);
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
            echo twig_escape_filter($this->env, gettext("Name"), "html", null, true);
            echo "</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"name\" value=\"";
            // line 26
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "name", [], "any", false, false, false, 26), "html", null, true);
            echo "\">
                    </div>
                    <div class=\"fix\"></div>
                </div>
                
                <div class=\"rowElem\">
                    <label>";
            // line 32
            echo twig_escape_filter($this->env, gettext("Company name"), "html", null, true);
            echo "</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"company\" value=\"";
            // line 34
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "company", [], "any", false, false, false, 34), "html", null, true);
            echo "\">
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>";
            // line 40
            echo twig_escape_filter($this->env, gettext("Email"), "html", null, true);
            echo "</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"email\" value=\"";
            // line 42
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "email", [], "any", false, false, false, 42), "html", null, true);
            echo "\" />
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>";
            // line 48
            echo twig_escape_filter($this->env, gettext("Group"), "html", null, true);
            echo ":</label>
                    <div class=\"formRight\">
                        ";
            // line 50
            echo twig_call_macro($macros["mf"], "macro_selectbox", ["group_id", twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "client_group_get_pairs", [], "any", false, false, false, 50), twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "group_id", [], "any", false, false, false, 50), 0, "All groups"], 50, $context, $this->getSourceContext());
            echo "
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>";
            // line 56
            echo twig_escape_filter($this->env, gettext("Status"), "html", null, true);
            echo ":</label>
                    <div class=\"formRight\">
                        <input type=\"radio\" name=\"status\" value=\"\"";
            // line 58
            if ( !twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "status", [], "any", false, false, false, 58)) {
                echo " checked=\"checked\"";
            }
            echo "/><label>";
            echo twig_escape_filter($this->env, gettext("All"), "html", null, true);
            echo "</label>
                        <input type=\"radio\" name=\"status\" value=\"active\"";
            // line 59
            if ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "status", [], "any", false, false, false, 59) == "active")) {
                echo " checked=\"checked\"";
            }
            echo "/><label>";
            echo twig_escape_filter($this->env, gettext("Active"), "html", null, true);
            echo "</label>
                        <input type=\"radio\" name=\"status\" value=\"suspended\"";
            // line 60
            if ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "status", [], "any", false, false, false, 60) == "suspended")) {
                echo " checked=\"checked\"";
            }
            echo " /><label>";
            echo twig_escape_filter($this->env, gettext("Suspended"), "html", null, true);
            echo "</label>
                        <input type=\"radio\" name=\"status\" value=\"canceled\"";
            // line 61
            if ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "status", [], "any", false, false, false, 61) == "canceled")) {
                echo " checked=\"checked\"";
            }
            echo " /><label>";
            echo twig_escape_filter($this->env, gettext("Canceled"), "html", null, true);
            echo "</label>
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>";
            // line 67
            echo twig_escape_filter($this->env, gettext("Registration date"), "html", null, true);
            echo "</label>
                    <div class=\"formRight moreFields\">
                        <ul>
                            <li style=\"width: 100px\"><input type=\"text\" name=\"date_from\" value=\"";
            // line 70
            if (twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "date_from", [], "any", false, false, false, 70)) {
                echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "date_from", [], "any", false, false, false, 70), "Y-m-d"), "html", null, true);
            }
            echo "\" placeholder=\"";
            echo twig_escape_filter($this->env, twig_date_format_filter($this->env, ($context["now"] ?? null), "Y-m-d"), "html", null, true);
            echo "\" class=\"datepicker\"/></li>
                            <li class=\"sep\">-</li>
                            <li style=\"width: 100px\"><input type=\"text\" name=\"date_to\" value=\"";
            // line 72
            if (twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "date_to", [], "any", false, false, false, 72)) {
                echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "date_to", [], "any", false, false, false, 72), "Y-m-d"), "html", null, true);
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
            // line 79
            echo twig_escape_filter($this->env, gettext("Filter"), "html", null, true);
            echo "\" class=\"greyishBtn submitForm\" />
            </fieldset>
        </form>
        <div class=\"fix\"></div>
    </div>
</div>
";
        } else {
            // line 86
            $context["count_clients"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "client_get_statuses", [], "any", false, false, false, 86);
            // line 87
            echo "<div class=\"stats\">
    <ul>
        <li><a href=\"";
            // line 89
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("client", ["status" => "active"]);
            echo "\" class=\"count green\" title=\"\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["count_clients"] ?? null), "active", [], "any", false, false, false, 89), "html", null, true);
            echo "</a><span>";
            echo twig_escape_filter($this->env, gettext("Active"), "html", null, true);
            echo "</span></li>
        <li><a href=\"";
            // line 90
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("client", ["status" => "suspended"]);
            echo "\" class=\"count blue\" title=\"\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["count_clients"] ?? null), "suspended", [], "any", false, false, false, 90), "html", null, true);
            echo "</a><span>";
            echo twig_escape_filter($this->env, gettext("Suspended"), "html", null, true);
            echo "</span></li>
        <li><a href=\"";
            // line 91
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("client", ["status" => "canceled"]);
            echo "\" class=\"count red\" title=\"\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["count_clients"] ?? null), "canceled", [], "any", false, false, false, 91), "html", null, true);
            echo "</a><span>";
            echo twig_escape_filter($this->env, gettext("Canceled"), "html", null, true);
            echo "</span></li>
        <li class=\"last\">
            <a href=\"";
            // line 93
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("client");
            echo "\" class=\"count grey\" title=\"\">";
            echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["count_clients"] ?? null), "active", [], "any", false, false, false, 93) + twig_get_attribute($this->env, $this->source, ($context["count_clients"] ?? null), "canceled", [], "any", false, false, false, 93)) + twig_get_attribute($this->env, $this->source, ($context["count_clients"] ?? null), "suspended", [], "any", false, false, false, 93)), "html", null, true);
            echo "</a><span>";
            echo twig_escape_filter($this->env, gettext("Total"), "html", null, true);
            echo "</span>
        </li>
    </ul>
    <div class=\"fix\"></div>
</div>
";
        }
    }

    // line 101
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 102
        echo "<div class=\"widget simpleTabs\">

    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">";
        // line 105
        echo twig_escape_filter($this->env, gettext("Clients"), "html", null, true);
        echo "</a></li>
        <li><a href=\"#tab-new\">";
        // line 106
        echo twig_escape_filter($this->env, gettext("New client"), "html", null, true);
        echo "</a></li>
        <li><a href=\"#tab-groups\">";
        // line 107
        echo twig_escape_filter($this->env, gettext("Groups"), "html", null, true);
        echo "</a></li>
        <li><a href=\"#tab-new-group\">";
        // line 108
        echo twig_escape_filter($this->env, gettext("New group"), "html", null, true);
        echo "</a></li>
    </ul>

    <div class=\"tabs_container\">

        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-index\">
            ";
        // line 115
        echo twig_call_macro($macros["mf"], "macro_table_search", [], 115, $context, $this->getSourceContext());
        echo "
            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td style=\"width: 2%\"><input type=\"checkbox\" class=\"batch-delete-master-checkbox\"/></td>
                        <td colspan=\"2\">";
        // line 120
        echo twig_escape_filter($this->env, gettext("Name"), "html", null, true);
        echo "</td>
                        <td>";
        // line 121
        echo twig_escape_filter($this->env, gettext("Company"), "html", null, true);
        echo "</td>
                        <td width=\"30%\">";
        // line 122
        echo twig_escape_filter($this->env, gettext("Email"), "html", null, true);
        echo "</td>
                        <td>";
        // line 123
        echo twig_escape_filter($this->env, gettext("Status"), "html", null, true);
        echo "</td>
                        <td>";
        // line 124
        echo twig_escape_filter($this->env, gettext("Date"), "html", null, true);
        echo "</td>
                        <td width=\"13%\">&nbsp;</td>
                    </tr>
                </thead>

                <tbody>
                    ";
        // line 130
        $context["clients"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "client_get_list", [0 => twig_array_merge(["per_page" => 30, "page" => twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 130)], ($context["request"] ?? null))], "method", false, false, false, 130);
        // line 131
        echo "                    ";
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["clients"] ?? null), "list", [], "any", false, false, false, 131));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["_key"] => $context["client"]) {
            // line 132
            echo "                    <tr>
                        <td><input type=\"checkbox\" class=\"batch-delete-checkbox\" data-item-id=\"";
            // line 133
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["client"], "id", [], "any", false, false, false, 133), "html", null, true);
            echo "\"/></td>
                        <td>
                            <a href=\"";
            // line 135
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("client/manage");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["client"], "id", [], "any", false, false, false, 135), "html", null, true);
            echo "\"><img src=\"";
            echo twig_escape_filter($this->env, twig_gravatar_filter(twig_get_attribute($this->env, $this->source, $context["client"], "email", [], "any", false, false, false, 135)), "html", null, true);
            echo "?size=20\" alt=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["client"], "id", [], "any", false, false, false, 135), "html", null, true);
            echo "\" /></a>
                        </td>
                        <td>
                            <span class=\"flag flag-";
            // line 138
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["client"], "country", [], "any", false, false, false, 138), "html", null, true);
            echo "\" title=\"";
            echo twig_escape_filter($this->env, (($__internal_compile_0 = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_countries", [], "any", false, false, false, 138)) && is_array($__internal_compile_0) || $__internal_compile_0 instanceof ArrayAccess ? ($__internal_compile_0[twig_get_attribute($this->env, $this->source, $context["client"], "country", [], "any", false, false, false, 138)] ?? null) : null), "html", null, true);
            echo "\"></span>
                            <a href=\"";
            // line 139
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("client/manage");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["client"], "id", [], "any", false, false, false, 139), "html", null, true);
            echo "\" title=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["client"], "first_name", [], "any", false, false, false, 139), "html", null, true);
            echo " ";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["client"], "last_name", [], "any", false, false, false, 139), "html", null, true);
            echo "\">";
            echo twig_escape_filter($this->env, twig_truncate_filter($this->env, twig_get_attribute($this->env, $this->source, $context["client"], "first_name", [], "any", false, false, false, 139), "1", null, "."), "html", null, true);
            echo " ";
            echo twig_escape_filter($this->env, twig_truncate_filter($this->env, twig_get_attribute($this->env, $this->source, $context["client"], "last_name", [], "any", false, false, false, 139), 15), "html", null, true);
            echo "</a></td>
                        <td><a href=\"";
            // line 140
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("client/manage");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["client"], "id", [], "any", false, false, false, 140), "html", null, true);
            echo "\" title=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["client"], "company", [], "any", false, false, false, 140), "html", null, true);
            echo "\">";
            echo twig_escape_filter($this->env, twig_truncate_filter($this->env, ((twig_get_attribute($this->env, $this->source, $context["client"], "company", [], "any", true, true, false, 140)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, $context["client"], "company", [], "any", false, false, false, 140), "-")) : ("-")), 30), "html", null, true);
            echo "</a></td>
                        <td><a href=\"";
            // line 141
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("client/manage");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["client"], "id", [], "any", false, false, false, 141), "html", null, true);
            echo "\" title=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["client"], "email", [], "any", false, false, false, 141), "html", null, true);
            echo "\">";
            echo twig_escape_filter($this->env, twig_truncate_filter($this->env, twig_get_attribute($this->env, $this->source, $context["client"], "email", [], "any", false, false, false, 141), 30), "html", null, true);
            echo "</a></td>
                        <td>";
            // line 142
            echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, $context["client"], "status", [], "any", false, false, false, 142)], 142, $context, $this->getSourceContext());
            echo "</td>
                        <td>";
            // line 143
            echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, $context["client"], "created_at", [], "any", false, false, false, 143), "Y-m-d"), "html", null, true);
            echo "</td>
                        <td>
                            <a class=\"btn14 bb-rm-tr api-link\" href=\"";
            // line 145
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/client/delete", ["id" => twig_get_attribute($this->env, $this->source, $context["client"], "id", [], "any", false, false, false, 145)]);
            echo "\" data-api-confirm=\"Are you sure?\" data-api-reload=\"1\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                            <a class=\"btn14\" href=\"";
            // line 146
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("client/manage");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["client"], "id", [], "any", false, false, false, 146), "html", null, true);
            echo "\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                        </td>
                    </tr>
                    ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 150
            echo "                    <tr>
                        <td colspan=\"7\">";
            // line 151
            echo twig_escape_filter($this->env, gettext("The list is empty"), "html", null, true);
            echo "</td>
                    </tr>
                    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['client'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 154
        echo "                </tbody>
            </table>

            ";
        // line 157
        $this->loadTemplate("partial_batch_delete.phtml", "mod_client_index.phtml", 157)->display(twig_array_merge($context, ["action" => "admin/client/batch_delete"]));
        // line 158
        echo "            ";
        $this->loadTemplate("partial_pagination.phtml", "mod_client_index.phtml", 158)->display(twig_array_merge($context, ["list" => ($context["clients"] ?? null), "url" => "client"]));
        // line 159
        echo "        </div>

        <div class=\"fix\"></div>

        <div class=\"tab_content nopadding\" id=\"tab-new\">

            <form method=\"post\" action=\"";
        // line 165
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/client/create");
        echo "\" class=\"mainForm api-form save\" data-api-redirect=\"";
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("client");
        echo "\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>";
        // line 168
        echo twig_escape_filter($this->env, gettext("Status"), "html", null, true);
        echo ":</label>
                        <div class=\"formRight noborder\">
                            <input type=\"radio\" name=\"status\" value=\"active\" checked=\"checked\"/><label>";
        // line 170
        echo twig_escape_filter($this->env, gettext("Active"), "html", null, true);
        echo "</label>
                            <input type=\"radio\" name=\"status\" value=\"canceled\"/><label>";
        // line 171
        echo twig_escape_filter($this->env, gettext("Canceled"), "html", null, true);
        echo "</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 176
        echo twig_escape_filter($this->env, gettext("Group"), "html", null, true);
        echo ":</label>
                        <div class=\"formRight\">
                            ";
        // line 178
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["group_id", twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "client_group_get_pairs", [], "any", false, false, false, 178), twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "group_id", [], "any", false, false, false, 178), 0, "Select group"], 178, $context, $this->getSourceContext());
        echo "
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 183
        echo twig_escape_filter($this->env, gettext("Email"), "html", null, true);
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"email\" value=\"";
        // line 185
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "email", [], "any", false, false, false, 185), "html", null, true);
        echo "\" required=\"required\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 190
        echo twig_escape_filter($this->env, gettext("Name"), "html", null, true);
        echo ":</label>
                        <div class=\"formRight moreFields\">
                            <ul>
                                <li style=\"width: 200px\"><input type=\"text\" name=\"first_name\" value=\"";
        // line 193
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "first_name", [], "any", false, false, false, 193), "html", null, true);
        echo "\" required=\"required\"/></li>
                                <li class=\"sep\"></li>
                                <li style=\"width: 200px\"><input type=\"text\" name=\"last_name\" value=\"";
        // line 195
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "last_name", [], "any", false, false, false, 195), "html", null, true);
        echo "\"/></li>
                            </ul>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 201
        echo twig_escape_filter($this->env, gettext("Company"), "html", null, true);
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"company\" value=\"";
        // line 203
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "company", [], "any", false, false, false, 203), "html", null, true);
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 208
        echo twig_escape_filter($this->env, gettext("Address Line 1"), "html", null, true);
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"address_1\" value=\"";
        // line 210
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "address_1", [], "any", false, false, false, 210), "html", null, true);
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 215
        echo twig_escape_filter($this->env, gettext("Address Line 2"), "html", null, true);
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"address_2\" value=\"";
        // line 217
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "address_2", [], "any", false, false, false, 217), "html", null, true);
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 222
        echo twig_escape_filter($this->env, gettext("City"), "html", null, true);
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"city\" value=\"";
        // line 224
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "city", [], "any", false, false, false, 224), "html", null, true);
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 229
        echo twig_escape_filter($this->env, gettext("State"), "html", null, true);
        echo ":</label>
                        <div class=\"formRight\">
                            ";
        // line 232
        echo "                            <input type=\"text\" name=\"state\" value=\"";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "state", [], "any", false, false, false, 232), "html", null, true);
        echo "\" />
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 237
        echo twig_escape_filter($this->env, gettext("Country"), "html", null, true);
        echo ":</label>
                        <div class=\"formRight\">
                            ";
        // line 239
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["country", twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_countries", [], "any", false, false, false, 239), twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "country", [], "any", false, false, false, 239), 0, "Select country"], 239, $context, $this->getSourceContext());
        echo "
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 244
        echo twig_escape_filter($this->env, gettext("Postcode"), "html", null, true);
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"postcode\" value=\"";
        // line 246
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "postcode", [], "any", false, false, false, 246), "html", null, true);
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 251
        echo twig_escape_filter($this->env, gettext("Phone"), "html", null, true);
        echo ":</label>
                        <div class=\"formRight moreFields\">
                            <ul>
                                <li><input type=\"text\" name=\"phone_cc\" value=\"";
        // line 254
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "phone_cc", [], "any", false, false, false, 254), "html", null, true);
        echo "\"/></li>
                                <li class=\"sep\"></li>
                                <li style=\"width: 200px;\"><input type=\"text\" name=\"phone\" value=\"";
        // line 256
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "phone", [], "any", false, false, false, 256), "html", null, true);
        echo "\"/></li>
                            </ul>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 263
        echo twig_escape_filter($this->env, gettext("Currency"), "html", null, true);
        echo ":</label>
                        <div class=\"formRight\">
                            ";
        // line 265
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["currency", twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "currency_get_pairs", [], "any", false, false, false, 265), twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "currency", [], "any", false, false, false, 265), 0, "Select currency"], 265, $context, $this->getSourceContext());
        echo "
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 271
        echo twig_escape_filter($this->env, gettext("Password"), "html", null, true);
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"password\" name=\"password\" value=\"\" required=\"required\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <input type=\"submit\" value=\"";
        // line 278
        echo twig_escape_filter($this->env, gettext("Create"), "html", null, true);
        echo "\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-groups\">
            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td>";
        // line 287
        echo twig_escape_filter($this->env, gettext("Title"), "html", null, true);
        echo "</td>
                        <td width=\"13%\">";
        // line 288
        echo twig_escape_filter($this->env, gettext("Actions"), "html", null, true);
        echo "</td>
                    </tr>
                </thead>

                <tbody>
                    ";
        // line 293
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "client_group_get_pairs", [], "any", false, false, false, 293));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["id"] => $context["group"]) {
            // line 294
            echo "                    <tr>
                        <td>";
            // line 295
            echo twig_escape_filter($this->env, $context["group"], "html", null, true);
            echo "</td>
                        <td>
                            <a class=\"btn14\" href=\"";
            // line 297
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("client/group");
            echo "/";
            echo twig_escape_filter($this->env, $context["id"], "html", null, true);
            echo "\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                            <a class=\"btn14 api-link bb-rm-tr\" data-api-reload=\"1\" data-api-confirm=\"Are you sure?\" href=\"";
            // line 298
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/client/group_delete", ["id" => $context["id"]]);
            echo "\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                        </td>
                    </tr>
                    ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 302
            echo "                    <tr>
                        <td colspan=\"2\">";
            // line 303
            echo twig_escape_filter($this->env, gettext("The list is empty"), "html", null, true);
            echo "</td>
                    </tr>
                    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['id'], $context['group'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 306
        echo "                </tbody>
            </table>
        </div>


        <div class=\"tab_content nopadding\" id=\"tab-new-group\">

            <form method=\"post\" action=\"";
        // line 313
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/client/group_create");
        echo "\" class=\"mainForm api-form save\" data-api-redirect=\"";
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("client");
        echo "\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>";
        // line 316
        echo twig_escape_filter($this->env, gettext("Title"), "html", null, true);
        echo ":</label>
                        <div class=\"formRight noborder\">
                            <input type=\"text\" name=\"title\" value=\"";
        // line 318
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "title", [], "any", false, false, false, 318), "html", null, true);
        echo "\" required=\"required\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <input type=\"submit\" value=\"";
        // line 323
        echo twig_escape_filter($this->env, gettext("Create"), "html", null, true);
        echo "\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>

    </div>
</div>

";
    }

    // line 333
    public function block_js($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 334
        echo "


";
    }

    public function getTemplateName()
    {
        return "mod_client_index.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  785 => 334,  781 => 333,  768 => 323,  760 => 318,  755 => 316,  747 => 313,  738 => 306,  729 => 303,  726 => 302,  717 => 298,  711 => 297,  706 => 295,  703 => 294,  698 => 293,  690 => 288,  686 => 287,  674 => 278,  664 => 271,  655 => 265,  650 => 263,  640 => 256,  635 => 254,  629 => 251,  621 => 246,  616 => 244,  608 => 239,  603 => 237,  594 => 232,  589 => 229,  581 => 224,  576 => 222,  568 => 217,  563 => 215,  555 => 210,  550 => 208,  542 => 203,  537 => 201,  528 => 195,  523 => 193,  517 => 190,  509 => 185,  504 => 183,  496 => 178,  491 => 176,  483 => 171,  479 => 170,  474 => 168,  466 => 165,  458 => 159,  455 => 158,  453 => 157,  448 => 154,  439 => 151,  436 => 150,  425 => 146,  421 => 145,  416 => 143,  412 => 142,  402 => 141,  392 => 140,  378 => 139,  372 => 138,  360 => 135,  355 => 133,  352 => 132,  346 => 131,  344 => 130,  335 => 124,  331 => 123,  327 => 122,  323 => 121,  319 => 120,  311 => 115,  301 => 108,  297 => 107,  293 => 106,  289 => 105,  284 => 102,  280 => 101,  265 => 93,  256 => 91,  248 => 90,  240 => 89,  236 => 87,  234 => 86,  224 => 79,  210 => 72,  201 => 70,  195 => 67,  182 => 61,  174 => 60,  166 => 59,  158 => 58,  153 => 56,  144 => 50,  139 => 48,  130 => 42,  125 => 40,  116 => 34,  111 => 32,  102 => 26,  97 => 24,  88 => 18,  83 => 16,  77 => 13,  70 => 9,  67 => 8,  65 => 7,  61 => 6,  54 => 4,  49 => 2,  47 => 3,  45 => 1,  38 => 2,);
    }

    public function getSourceContext()
    {
        return new Source("{% import \"macro_functions.phtml\" as mf %}
{% extends \"layout_default.phtml\" %}
{% set active_menu = 'client' %}
{% block meta_title %}{{ 'Clients'|trans }}{% endblock %}

{% block top_content %}
{% if request.show_filter %}
<div class=\"widget filterWidget\">
    <div class=\"head\"><h5 class=\"iMagnify\">{{ 'Filter clients'|trans }}</h5></div>
    <div class=\"body nopadding\">

        <form method=\"get\" action=\"\" class=\"mainForm\">
            <input type=\"hidden\" name=\"_url\" value=\"{{ request._url }}\" />
            <fieldset>
                <div class=\"rowElem noborder\">
                    <label>{{ 'ID'|trans }}</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"id\" value=\"{{ request.id }}\">
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>{{ 'Name'|trans }}</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"name\" value=\"{{ request.name }}\">
                    </div>
                    <div class=\"fix\"></div>
                </div>
                
                <div class=\"rowElem\">
                    <label>{{ 'Company name'|trans }}</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"company\" value=\"{{ request.company }}\">
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>{{ 'Email'|trans }}</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"email\" value=\"{{ request.email }}\" />
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>{{ 'Group'|trans }}:</label>
                    <div class=\"formRight\">
                        {{ mf.selectbox('group_id', admin.client_group_get_pairs, request.group_id, 0, 'All groups') }}
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>{{ 'Status'|trans }}:</label>
                    <div class=\"formRight\">
                        <input type=\"radio\" name=\"status\" value=\"\"{% if not request.status %} checked=\"checked\"{% endif %}/><label>{{ 'All'|trans }}</label>
                        <input type=\"radio\" name=\"status\" value=\"active\"{% if request.status == 'active' %} checked=\"checked\"{% endif %}/><label>{{ 'Active'|trans }}</label>
                        <input type=\"radio\" name=\"status\" value=\"suspended\"{% if request.status == 'suspended' %} checked=\"checked\"{% endif %} /><label>{{ 'Suspended'|trans }}</label>
                        <input type=\"radio\" name=\"status\" value=\"canceled\"{% if request.status == 'canceled' %} checked=\"checked\"{% endif %} /><label>{{ 'Canceled'|trans }}</label>
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>{{ 'Registration date'|trans }}</label>
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
{% set count_clients = admin.client_get_statuses %}
<div class=\"stats\">
    <ul>
        <li><a href=\"{{ 'client'|alink({'status' : 'active'}) }}\" class=\"count green\" title=\"\">{{ count_clients.active }}</a><span>{{ 'Active'|trans }}</span></li>
        <li><a href=\"{{ 'client'|alink({'status' : 'suspended'}) }}\" class=\"count blue\" title=\"\">{{ count_clients.suspended }}</a><span>{{ 'Suspended'|trans }}</span></li>
        <li><a href=\"{{ 'client'|alink({'status' : 'canceled'}) }}\" class=\"count red\" title=\"\">{{ count_clients.canceled }}</a><span>{{ 'Canceled'|trans }}</span></li>
        <li class=\"last\">
            <a href=\"{{ 'client'|alink }}\" class=\"count grey\" title=\"\">{{count_clients.active + count_clients.canceled + count_clients.suspended}}</a><span>{{ 'Total'|trans }}</span>
        </li>
    </ul>
    <div class=\"fix\"></div>
</div>
{% endif %}
{% endblock %}

{% block content %}
<div class=\"widget simpleTabs\">

    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">{{ 'Clients'|trans }}</a></li>
        <li><a href=\"#tab-new\">{{ 'New client'|trans }}</a></li>
        <li><a href=\"#tab-groups\">{{ 'Groups'|trans }}</a></li>
        <li><a href=\"#tab-new-group\">{{ 'New group'|trans }}</a></li>
    </ul>

    <div class=\"tabs_container\">

        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-index\">
            {{ mf.table_search }}
            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td style=\"width: 2%\"><input type=\"checkbox\" class=\"batch-delete-master-checkbox\"/></td>
                        <td colspan=\"2\">{{ 'Name'|trans }}</td>
                        <td>{{ 'Company'|trans }}</td>
                        <td width=\"30%\">{{ 'Email'|trans }}</td>
                        <td>{{ 'Status'|trans }}</td>
                        <td>{{ 'Date'|trans }}</td>
                        <td width=\"13%\">&nbsp;</td>
                    </tr>
                </thead>

                <tbody>
                    {% set clients = admin.client_get_list({\"per_page\":30, \"page\":request.page}|merge(request)) %}
                    {% for client in clients.list %}
                    <tr>
                        <td><input type=\"checkbox\" class=\"batch-delete-checkbox\" data-item-id=\"{{ client.id }}\"/></td>
                        <td>
                            <a href=\"{{ 'client/manage'|alink }}/{{ client.id }}\"><img src=\"{{ client.email|gravatar }}?size=20\" alt=\"{{ client.id }}\" /></a>
                        </td>
                        <td>
                            <span class=\"flag flag-{{ client.country }}\" title=\"{{ guest.system_countries[client.country] }}\"></span>
                            <a href=\"{{ 'client/manage'|alink }}/{{ client.id }}\" title=\"{{ client.first_name }} {{ client.last_name }}\">{{ client.first_name|truncate('1', null, '.') }} {{ client.last_name|truncate(15) }}</a></td>
                        <td><a href=\"{{ 'client/manage'|alink }}/{{ client.id }}\" title=\"{{ client.company }}\">{{ client.company|default('-')|truncate(30) }}</a></td>
                        <td><a href=\"{{ 'client/manage'|alink }}/{{ client.id }}\" title=\"{{ client.email }}\">{{ client.email|truncate(30) }}</a></td>
                        <td>{{ mf.status_name(client.status) }}</td>
                        <td>{{ client.created_at|date('Y-m-d') }}</td>
                        <td>
                            <a class=\"btn14 bb-rm-tr api-link\" href=\"{{ 'api/admin/client/delete'|link({'id' : client.id}) }}\" data-api-confirm=\"Are you sure?\" data-api-reload=\"1\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                            <a class=\"btn14\" href=\"{{ 'client/manage'|alink }}/{{ client.id }}\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                        </td>
                    </tr>
                    {% else %}
                    <tr>
                        <td colspan=\"7\">{{ 'The list is empty'|trans }}</td>
                    </tr>
                    {% endfor %}
                </tbody>
            </table>

            {% include \"partial_batch_delete.phtml\" with { 'action' : 'admin/client/batch_delete' } %}
            {% include \"partial_pagination.phtml\" with { 'list': clients, 'url':'client' } %}
        </div>

        <div class=\"fix\"></div>

        <div class=\"tab_content nopadding\" id=\"tab-new\">

            <form method=\"post\" action=\"{{ 'api/admin/client/create'|link }}\" class=\"mainForm api-form save\" data-api-redirect=\"{{ 'client'|alink }}\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>{{ 'Status'|trans }}:</label>
                        <div class=\"formRight noborder\">
                            <input type=\"radio\" name=\"status\" value=\"active\" checked=\"checked\"/><label>{{ 'Active'|trans }}</label>
                            <input type=\"radio\" name=\"status\" value=\"canceled\"/><label>{{ 'Canceled'|trans }}</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{{ 'Group'|trans }}:</label>
                        <div class=\"formRight\">
                            {{ mf.selectbox('group_id', admin.client_group_get_pairs, request.group_id, 0, 'Select group') }}
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{{ 'Email'|trans }}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"email\" value=\"{{request.email}}\" required=\"required\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{{ 'Name'|trans }}:</label>
                        <div class=\"formRight moreFields\">
                            <ul>
                                <li style=\"width: 200px\"><input type=\"text\" name=\"first_name\" value=\"{{request.first_name}}\" required=\"required\"/></li>
                                <li class=\"sep\"></li>
                                <li style=\"width: 200px\"><input type=\"text\" name=\"last_name\" value=\"{{request.last_name}}\"/></li>
                            </ul>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{{ 'Company'|trans }}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"company\" value=\"{{request.company}}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{{ 'Address Line 1'|trans }}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"address_1\" value=\"{{request.address_1}}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{{ 'Address Line 2'|trans }}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"address_2\" value=\"{{request.address_2}}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{{ 'City'|trans }}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"city\" value=\"{{request.city}}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{{ 'State'|trans }}:</label>
                        <div class=\"formRight\">
                            {# mf.selectbox('state', guest.system_states, request.state, 0, 'Select state') #}
                            <input type=\"text\" name=\"state\" value=\"{{ request.state }}\" />
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{{ 'Country'|trans }}:</label>
                        <div class=\"formRight\">
                            {{ mf.selectbox('country', guest.system_countries, request.country, 0, 'Select country') }}
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{{ 'Postcode'|trans }}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"postcode\" value=\"{{request.postcode}}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{{ 'Phone'|trans }}:</label>
                        <div class=\"formRight moreFields\">
                            <ul>
                                <li><input type=\"text\" name=\"phone_cc\" value=\"{{request.phone_cc}}\"/></li>
                                <li class=\"sep\"></li>
                                <li style=\"width: 200px;\"><input type=\"text\" name=\"phone\" value=\"{{request.phone}}\"/></li>
                            </ul>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{{ 'Currency'|trans }}:</label>
                        <div class=\"formRight\">
                            {{ mf.selectbox('currency', guest.currency_get_pairs, request.currency, 0, 'Select currency') }}
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{{ 'Password'|trans }}:</label>
                        <div class=\"formRight\">
                            <input type=\"password\" name=\"password\" value=\"\" required=\"required\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <input type=\"submit\" value=\"{{ 'Create'|trans }}\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-groups\">
            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td>{{ 'Title'|trans }}</td>
                        <td width=\"13%\">{{ 'Actions'|trans }}</td>
                    </tr>
                </thead>

                <tbody>
                    {% for id,group in admin.client_group_get_pairs %}
                    <tr>
                        <td>{{ group }}</td>
                        <td>
                            <a class=\"btn14\" href=\"{{ 'client/group'|alink }}/{{ id }}\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                            <a class=\"btn14 api-link bb-rm-tr\" data-api-reload=\"1\" data-api-confirm=\"Are you sure?\" href=\"{{ 'api/admin/client/group_delete'|link({'id' : id}) }}\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                        </td>
                    </tr>
                    {% else %}
                    <tr>
                        <td colspan=\"2\">{{ 'The list is empty'|trans }}</td>
                    </tr>
                    {% endfor %}
                </tbody>
            </table>
        </div>


        <div class=\"tab_content nopadding\" id=\"tab-new-group\">

            <form method=\"post\" action=\"{{ 'api/admin/client/group_create'|link }}\" class=\"mainForm api-form save\" data-api-redirect=\"{{ 'client'|alink }}\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>{{ 'Title'|trans }}:</label>
                        <div class=\"formRight noborder\">
                            <input type=\"text\" name=\"title\" value=\"{{request.title}}\" required=\"required\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <input type=\"submit\" value=\"{{ 'Create'|trans }}\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>

    </div>
</div>

{% endblock %}

{% block js %}



{% endblock %}", "mod_client_index.phtml", "/shared/httpd/up-boxbilling/FOSSBilling/src/bb-themes/admin_default/html/mod_client_index.phtml");
    }
}
