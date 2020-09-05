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
class __TwigTemplate_f79ce503b989c11a62fdcf010f4e417a0c41ead1f37638f950a51054e1cc2e1b extends \Twig\Template
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
        echo gettext("Clients");
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
            echo gettext("Filter clients");
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
            echo gettext("Name");
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
            echo gettext("Company name");
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
            echo gettext("Email");
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
            echo gettext("Group");
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
            echo gettext("Status");
            echo ":</label>
                    <div class=\"formRight\">
                        <input type=\"radio\" name=\"status\" value=\"\"";
            // line 58
            if ( !twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "status", [], "any", false, false, false, 58)) {
                echo " checked=\"checked\"";
            }
            echo "/><label>";
            echo gettext("All");
            echo "</label>
                        <input type=\"radio\" name=\"status\" value=\"active\"";
            // line 59
            if ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "status", [], "any", false, false, false, 59) == "active")) {
                echo " checked=\"checked\"";
            }
            echo "/><label>";
            echo gettext("Active");
            echo "</label>
                        <input type=\"radio\" name=\"status\" value=\"suspended\"";
            // line 60
            if ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "status", [], "any", false, false, false, 60) == "suspended")) {
                echo " checked=\"checked\"";
            }
            echo " /><label>";
            echo gettext("Suspended");
            echo "</label>
                        <input type=\"radio\" name=\"status\" value=\"canceled\"";
            // line 61
            if ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "status", [], "any", false, false, false, 61) == "canceled")) {
                echo " checked=\"checked\"";
            }
            echo " /><label>";
            echo gettext("Canceled");
            echo "</label>
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>";
            // line 67
            echo gettext("Registration date");
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
            echo gettext("Filter");
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
            echo gettext("Active");
            echo "</span></li>
        <li><a href=\"";
            // line 90
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("client", ["status" => "suspended"]);
            echo "\" class=\"count blue\" title=\"\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["count_clients"] ?? null), "suspended", [], "any", false, false, false, 90), "html", null, true);
            echo "</a><span>";
            echo gettext("Suspended");
            echo "</span></li>
        <li><a href=\"";
            // line 91
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("client", ["status" => "canceled"]);
            echo "\" class=\"count red\" title=\"\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["count_clients"] ?? null), "canceled", [], "any", false, false, false, 91), "html", null, true);
            echo "</a><span>";
            echo gettext("Canceled");
            echo "</span></li>
        <li class=\"last\"><a href=\"";
            // line 92
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("client");
            echo "\" class=\"count grey\" title=\"\">";
            echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["count_clients"] ?? null), "active", [], "any", false, false, false, 92) + twig_get_attribute($this->env, $this->source, ($context["count_clients"] ?? null), "canceled", [], "any", false, false, false, 92)) + twig_get_attribute($this->env, $this->source, ($context["count_clients"] ?? null), "suspended", [], "any", false, false, false, 92)), "html", null, true);
            echo "</a><span>";
            echo gettext("Total");
            echo "</span></li>
    </ul>
    <div class=\"fix\"></div>
</div>
";
        }
    }

    // line 99
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 100
        echo "<div class=\"widget simpleTabs\">

    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">";
        // line 103
        echo gettext("Clients");
        echo "</a></li>
        <li><a href=\"#tab-new\">";
        // line 104
        echo gettext("New client");
        echo "</a></li>
        <li><a href=\"#tab-groups\">";
        // line 105
        echo gettext("Groups");
        echo "</a></li>
        <li><a href=\"#tab-new-group\">";
        // line 106
        echo gettext("New group");
        echo "</a></li>
    </ul>

    <div class=\"tabs_container\">

        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-index\">
            ";
        // line 113
        echo twig_call_macro($macros["mf"], "macro_table_search", [], 113, $context, $this->getSourceContext());
        echo "
            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td style=\"width: 2%\"><input type=\"checkbox\" class=\"batch-delete-master-checkbox\"/></td>
                        <td colspan=\"2\">";
        // line 118
        echo gettext("Name");
        echo "</td>
                        <td>";
        // line 119
        echo gettext("Company");
        echo "</td>
                        <td width=\"30%\">";
        // line 120
        echo gettext("Email");
        echo "</td>
                        <td>";
        // line 121
        echo gettext("Status");
        echo "</td>
                        <td>";
        // line 122
        echo gettext("Date");
        echo "</td>
                        <td width=\"13%\">&nbsp;</td>
                    </tr>
                </thead>

                <tbody>
                    ";
        // line 128
        $context["clients"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "client_get_list", [0 => twig_array_merge(["per_page" => 30, "page" => twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 128)], ($context["request"] ?? null))], "method", false, false, false, 128);
        // line 129
        echo "                    ";
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["clients"] ?? null), "list", [], "any", false, false, false, 129));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["_key"] => $context["client"]) {
            // line 130
            echo "                    <tr>
                        <td><input type=\"checkbox\" class=\"batch-delete-checkbox\" data-item-id=\"";
            // line 131
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["client"], "id", [], "any", false, false, false, 131), "html", null, true);
            echo "\"/></td>
                        <td>
                            <a href=\"";
            // line 133
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("client/manage");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["client"], "id", [], "any", false, false, false, 133), "html", null, true);
            echo "\"><img src=\"";
            echo twig_escape_filter($this->env, twig_gravatar_filter(twig_get_attribute($this->env, $this->source, $context["client"], "email", [], "any", false, false, false, 133)), "html", null, true);
            echo "?size=20\" alt=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["client"], "id", [], "any", false, false, false, 133), "html", null, true);
            echo "\" /></a>
                        </td>
                        <td>
                            <span class=\"flag flag-";
            // line 136
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["client"], "country", [], "any", false, false, false, 136), "html", null, true);
            echo "\" title=\"";
            echo twig_escape_filter($this->env, (($__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4 = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_countries", [], "any", false, false, false, 136)) && is_array($__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4) || $__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4 instanceof ArrayAccess ? ($__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4[twig_get_attribute($this->env, $this->source, $context["client"], "country", [], "any", false, false, false, 136)] ?? null) : null), "html", null, true);
            echo "\"></span>
                            <a href=\"";
            // line 137
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("client/manage");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["client"], "id", [], "any", false, false, false, 137), "html", null, true);
            echo "\" title=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["client"], "first_name", [], "any", false, false, false, 137), "html", null, true);
            echo " ";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["client"], "last_name", [], "any", false, false, false, 137), "html", null, true);
            echo "\">";
            echo twig_escape_filter($this->env, twig_truncate_filter($this->env, twig_get_attribute($this->env, $this->source, $context["client"], "first_name", [], "any", false, false, false, 137), "1", null, "."), "html", null, true);
            echo " ";
            echo twig_escape_filter($this->env, twig_truncate_filter($this->env, twig_get_attribute($this->env, $this->source, $context["client"], "last_name", [], "any", false, false, false, 137), 15), "html", null, true);
            echo "</a></td>
                        <td><a href=\"";
            // line 138
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("client/manage");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["client"], "id", [], "any", false, false, false, 138), "html", null, true);
            echo "\" title=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["client"], "company", [], "any", false, false, false, 138), "html", null, true);
            echo "\">";
            echo twig_escape_filter($this->env, twig_truncate_filter($this->env, ((twig_get_attribute($this->env, $this->source, $context["client"], "company", [], "any", true, true, false, 138)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, $context["client"], "company", [], "any", false, false, false, 138), "-")) : ("-")), 30), "html", null, true);
            echo "</a></td>
                        <td><a href=\"";
            // line 139
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("client/manage");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["client"], "id", [], "any", false, false, false, 139), "html", null, true);
            echo "\" title=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["client"], "email", [], "any", false, false, false, 139), "html", null, true);
            echo "\">";
            echo twig_escape_filter($this->env, twig_truncate_filter($this->env, twig_get_attribute($this->env, $this->source, $context["client"], "email", [], "any", false, false, false, 139), 30), "html", null, true);
            echo "</a></td>
                        <td>";
            // line 140
            echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, $context["client"], "status", [], "any", false, false, false, 140)], 140, $context, $this->getSourceContext());
            echo "</td>
                        <td>";
            // line 141
            echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, $context["client"], "created_at", [], "any", false, false, false, 141), "Y-m-d"), "html", null, true);
            echo "</td>
                        <td>
                            <a class=\"btn14 bb-rm-tr api-link\" href=\"";
            // line 143
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/client/delete", ["id" => twig_get_attribute($this->env, $this->source, $context["client"], "id", [], "any", false, false, false, 143)]);
            echo "\" data-api-confirm=\"Are you sure?\" data-api-reload=\"1\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                            <a class=\"btn14\" href=\"";
            // line 144
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("client/manage");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["client"], "id", [], "any", false, false, false, 144), "html", null, true);
            echo "\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                        </td>
                    </tr>
                    ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 148
            echo "                    <tr>
                        <td colspan=\"7\">";
            // line 149
            echo gettext("The list is empty");
            echo "</td>
                    </tr>
                    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['client'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 152
        echo "                </tbody>
            </table>

            ";
        // line 155
        $this->loadTemplate("partial_batch_delete.phtml", "mod_client_index.phtml", 155)->display(twig_array_merge($context, ["action" => "admin/client/batch_delete"]));
        // line 156
        echo "            ";
        $this->loadTemplate("partial_pagination.phtml", "mod_client_index.phtml", 156)->display(twig_array_merge($context, ["list" => ($context["clients"] ?? null), "url" => "client"]));
        // line 157
        echo "        </div>

        <div class=\"fix\"></div>

        <div class=\"tab_content nopadding\" id=\"tab-new\">

            <form method=\"post\" action=\"";
        // line 163
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/client/create");
        echo "\" class=\"mainForm api-form save\" data-api-redirect=\"";
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("client");
        echo "\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>";
        // line 166
        echo gettext("Status");
        echo ":</label>
                        <div class=\"formRight noborder\">
                            <input type=\"radio\" name=\"status\" value=\"active\" checked=\"checked\"/><label>";
        // line 168
        echo gettext("Active");
        echo "</label>
                            <input type=\"radio\" name=\"status\" value=\"canceled\"/><label>";
        // line 169
        echo gettext("Canceled");
        echo "</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 174
        echo gettext("Group");
        echo ":</label>
                        <div class=\"formRight\">
                            ";
        // line 176
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["group_id", twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "client_group_get_pairs", [], "any", false, false, false, 176), twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "group_id", [], "any", false, false, false, 176), 0, "Select group"], 176, $context, $this->getSourceContext());
        echo "
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 181
        echo gettext("Email");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"email\" value=\"";
        // line 183
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "email", [], "any", false, false, false, 183), "html", null, true);
        echo "\" required=\"required\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 188
        echo gettext("Name");
        echo ":</label>
                        <div class=\"formRight moreFields\">
                            <ul>
                                <li style=\"width: 200px\"><input type=\"text\" name=\"first_name\" value=\"";
        // line 191
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "first_name", [], "any", false, false, false, 191), "html", null, true);
        echo "\" required=\"required\"/></li>
                                <li class=\"sep\"></li>
                                <li style=\"width: 200px\"><input type=\"text\" name=\"last_name\" value=\"";
        // line 193
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "last_name", [], "any", false, false, false, 193), "html", null, true);
        echo "\"/></li>
                            </ul>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 199
        echo gettext("Company");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"company\" value=\"";
        // line 201
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "company", [], "any", false, false, false, 201), "html", null, true);
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 206
        echo gettext("Address Line 1");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"address_1\" value=\"";
        // line 208
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "address_1", [], "any", false, false, false, 208), "html", null, true);
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 213
        echo gettext("Address Line 2");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"address_2\" value=\"";
        // line 215
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "address_2", [], "any", false, false, false, 215), "html", null, true);
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 220
        echo gettext("City");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"city\" value=\"";
        // line 222
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "city", [], "any", false, false, false, 222), "html", null, true);
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 227
        echo gettext("State");
        echo ":</label>
                        <div class=\"formRight\">
                            ";
        // line 230
        echo "                            <input type=\"text\" name=\"state\" value=\"";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "state", [], "any", false, false, false, 230), "html", null, true);
        echo "\" />
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 235
        echo gettext("Country");
        echo ":</label>
                        <div class=\"formRight\">
                            ";
        // line 237
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["country", twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_countries", [], "any", false, false, false, 237), twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "country", [], "any", false, false, false, 237), 0, "Select country"], 237, $context, $this->getSourceContext());
        echo "
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 242
        echo gettext("Postcode");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"postcode\" value=\"";
        // line 244
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "postcode", [], "any", false, false, false, 244), "html", null, true);
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 249
        echo gettext("Phone");
        echo ":</label>
                        <div class=\"formRight moreFields\">
                            <ul>
                                <li><input type=\"text\" name=\"phone_cc\" value=\"";
        // line 252
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "phone_cc", [], "any", false, false, false, 252), "html", null, true);
        echo "\"/></li>
                                <li class=\"sep\"></li>
                                <li style=\"width: 200px;\"><input type=\"text\" name=\"phone\" value=\"";
        // line 254
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "phone", [], "any", false, false, false, 254), "html", null, true);
        echo "\"/></li>
                            </ul>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 261
        echo gettext("Currency");
        echo ":</label>
                        <div class=\"formRight\">
                            ";
        // line 263
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["currency", twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "currency_get_pairs", [], "any", false, false, false, 263), twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "currency", [], "any", false, false, false, 263), 0, "Select currency"], 263, $context, $this->getSourceContext());
        echo "
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 269
        echo gettext("Password");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"password\" name=\"password\" value=\"\" required=\"required\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <input type=\"submit\" value=\"";
        // line 276
        echo gettext("Create");
        echo "\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-groups\">
            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td>";
        // line 285
        echo gettext("Title");
        echo "</td>
                        <td width=\"13%\">";
        // line 286
        echo gettext("Actions");
        echo "</td>
                    </tr>
                </thead>

                <tbody>
                    ";
        // line 291
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "client_group_get_pairs", [], "any", false, false, false, 291));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["id"] => $context["group"]) {
            // line 292
            echo "                    <tr>
                        <td>";
            // line 293
            echo twig_escape_filter($this->env, $context["group"], "html", null, true);
            echo "</td>
                        <td>
                            <a class=\"btn14\" href=\"";
            // line 295
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("client/group");
            echo "/";
            echo twig_escape_filter($this->env, $context["id"], "html", null, true);
            echo "\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                            <a class=\"btn14 api-link bb-rm-tr\" data-api-reload=\"1\" data-api-confirm=\"Are you sure?\" href=\"";
            // line 296
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/client/group_delete", ["id" => $context["id"]]);
            echo "\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                        </td>
                    </tr>
                    ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 300
            echo "                    <tr>
                        <td colspan=\"2\">";
            // line 301
            echo gettext("The list is empty");
            echo "</td>
                    </tr>
                    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['id'], $context['group'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 304
        echo "                </tbody>
            </table>
        </div>


        <div class=\"tab_content nopadding\" id=\"tab-new-group\">

            <form method=\"post\" action=\"";
        // line 311
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/client/group_create");
        echo "\" class=\"mainForm api-form save\" data-api-redirect=\"";
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("client");
        echo "\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>";
        // line 314
        echo gettext("Title");
        echo ":</label>
                        <div class=\"formRight noborder\">
                            <input type=\"text\" name=\"title\" value=\"";
        // line 316
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "title", [], "any", false, false, false, 316), "html", null, true);
        echo "\" required=\"required\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <input type=\"submit\" value=\"";
        // line 321
        echo gettext("Create");
        echo "\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>

    </div>
</div>

";
    }

    // line 331
    public function block_js($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 332
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
        return array (  783 => 332,  779 => 331,  766 => 321,  758 => 316,  753 => 314,  745 => 311,  736 => 304,  727 => 301,  724 => 300,  715 => 296,  709 => 295,  704 => 293,  701 => 292,  696 => 291,  688 => 286,  684 => 285,  672 => 276,  662 => 269,  653 => 263,  648 => 261,  638 => 254,  633 => 252,  627 => 249,  619 => 244,  614 => 242,  606 => 237,  601 => 235,  592 => 230,  587 => 227,  579 => 222,  574 => 220,  566 => 215,  561 => 213,  553 => 208,  548 => 206,  540 => 201,  535 => 199,  526 => 193,  521 => 191,  515 => 188,  507 => 183,  502 => 181,  494 => 176,  489 => 174,  481 => 169,  477 => 168,  472 => 166,  464 => 163,  456 => 157,  453 => 156,  451 => 155,  446 => 152,  437 => 149,  434 => 148,  423 => 144,  419 => 143,  414 => 141,  410 => 140,  400 => 139,  390 => 138,  376 => 137,  370 => 136,  358 => 133,  353 => 131,  350 => 130,  344 => 129,  342 => 128,  333 => 122,  329 => 121,  325 => 120,  321 => 119,  317 => 118,  309 => 113,  299 => 106,  295 => 105,  291 => 104,  287 => 103,  282 => 100,  278 => 99,  264 => 92,  256 => 91,  248 => 90,  240 => 89,  236 => 87,  234 => 86,  224 => 79,  210 => 72,  201 => 70,  195 => 67,  182 => 61,  174 => 60,  166 => 59,  158 => 58,  153 => 56,  144 => 50,  139 => 48,  130 => 42,  125 => 40,  116 => 34,  111 => 32,  102 => 26,  97 => 24,  88 => 18,  83 => 16,  77 => 13,  70 => 9,  67 => 8,  65 => 7,  61 => 6,  54 => 4,  49 => 2,  47 => 3,  45 => 1,  38 => 2,);
    }

    public function getSourceContext()
    {
        return new Source("{% import \"macro_functions.phtml\" as mf %}
{% extends \"layout_default.phtml\" %}
{% set active_menu = 'client' %}
{% block meta_title %}{% trans 'Clients' %}{% endblock %}

{% block top_content %}
{% if request.show_filter %}
<div class=\"widget filterWidget\">
    <div class=\"head\"><h5 class=\"iMagnify\">{% trans 'Filter clients' %}</h5></div>
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
                    <label>{% trans 'Name' %}</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"name\" value=\"{{ request.name }}\">
                    </div>
                    <div class=\"fix\"></div>
                </div>
                
                <div class=\"rowElem\">
                    <label>{% trans 'Company name' %}</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"company\" value=\"{{ request.company }}\">
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>{% trans 'Email' %}</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"email\" value=\"{{ request.email }}\" />
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>{% trans 'Group' %}:</label>
                    <div class=\"formRight\">
                        {{ mf.selectbox('group_id', admin.client_group_get_pairs, request.group_id, 0, 'All groups') }}
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>{% trans 'Status' %}:</label>
                    <div class=\"formRight\">
                        <input type=\"radio\" name=\"status\" value=\"\"{% if not request.status %} checked=\"checked\"{% endif %}/><label>{% trans 'All' %}</label>
                        <input type=\"radio\" name=\"status\" value=\"active\"{% if request.status == 'active' %} checked=\"checked\"{% endif %}/><label>{% trans 'Active' %}</label>
                        <input type=\"radio\" name=\"status\" value=\"suspended\"{% if request.status == 'suspended' %} checked=\"checked\"{% endif %} /><label>{% trans 'Suspended' %}</label>
                        <input type=\"radio\" name=\"status\" value=\"canceled\"{% if request.status == 'canceled' %} checked=\"checked\"{% endif %} /><label>{% trans 'Canceled' %}</label>
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>{% trans 'Registration date' %}</label>
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
{% set count_clients = admin.client_get_statuses %}
<div class=\"stats\">
    <ul>
        <li><a href=\"{{ 'client'|alink({'status' : 'active'}) }}\" class=\"count green\" title=\"\">{{ count_clients.active }}</a><span>{% trans 'Active' %}</span></li>
        <li><a href=\"{{ 'client'|alink({'status' : 'suspended'}) }}\" class=\"count blue\" title=\"\">{{ count_clients.suspended }}</a><span>{% trans 'Suspended' %}</span></li>
        <li><a href=\"{{ 'client'|alink({'status' : 'canceled'}) }}\" class=\"count red\" title=\"\">{{ count_clients.canceled }}</a><span>{% trans 'Canceled' %}</span></li>
        <li class=\"last\"><a href=\"{{ 'client'|alink }}\" class=\"count grey\" title=\"\">{{count_clients.active + count_clients.canceled + count_clients.suspended}}</a><span>{% trans 'Total' %}</span></li>
    </ul>
    <div class=\"fix\"></div>
</div>
{% endif %}
{% endblock %}

{% block content %}
<div class=\"widget simpleTabs\">

    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">{% trans 'Clients' %}</a></li>
        <li><a href=\"#tab-new\">{% trans 'New client' %}</a></li>
        <li><a href=\"#tab-groups\">{% trans 'Groups' %}</a></li>
        <li><a href=\"#tab-new-group\">{% trans 'New group' %}</a></li>
    </ul>

    <div class=\"tabs_container\">

        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-index\">
            {{ mf.table_search }}
            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td style=\"width: 2%\"><input type=\"checkbox\" class=\"batch-delete-master-checkbox\"/></td>
                        <td colspan=\"2\">{% trans 'Name' %}</td>
                        <td>{% trans 'Company' %}</td>
                        <td width=\"30%\">{% trans 'Email' %}</td>
                        <td>{% trans 'Status' %}</td>
                        <td>{% trans 'Date' %}</td>
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
                        <td colspan=\"7\">{% trans 'The list is empty' %}</td>
                    </tr>
                    {% endfor %}
                </tbody>
            </table>

            {% include \"partial_batch_delete.phtml\" with {'action' : 'admin/client/batch_delete'} %}
            {% include \"partial_pagination.phtml\" with {'list': clients, 'url':'client'} %}
        </div>

        <div class=\"fix\"></div>

        <div class=\"tab_content nopadding\" id=\"tab-new\">

            <form method=\"post\" action=\"{{ 'api/admin/client/create'|link }}\" class=\"mainForm api-form save\" data-api-redirect=\"{{ 'client'|alink }}\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>{% trans 'Status' %}:</label>
                        <div class=\"formRight noborder\">
                            <input type=\"radio\" name=\"status\" value=\"active\" checked=\"checked\"/><label>{% trans 'Active' %}</label>
                            <input type=\"radio\" name=\"status\" value=\"canceled\"/><label>{% trans 'Canceled' %}</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Group' %}:</label>
                        <div class=\"formRight\">
                            {{ mf.selectbox('group_id', admin.client_group_get_pairs, request.group_id, 0, 'Select group') }}
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Email' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"email\" value=\"{{request.email}}\" required=\"required\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Name' %}:</label>
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
                        <label>{% trans 'Company' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"company\" value=\"{{request.company}}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Address Line 1' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"address_1\" value=\"{{request.address_1}}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Address Line 2' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"address_2\" value=\"{{request.address_2}}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'City' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"city\" value=\"{{request.city}}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'State' %}:</label>
                        <div class=\"formRight\">
                            {# mf.selectbox('state', guest.system_states, request.state, 0, 'Select state') #}
                            <input type=\"text\" name=\"state\" value=\"{{ request.state }}\" />
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Country' %}:</label>
                        <div class=\"formRight\">
                            {{ mf.selectbox('country', guest.system_countries, request.country, 0, 'Select country') }}
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Postcode' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"postcode\" value=\"{{request.postcode}}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Phone' %}:</label>
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
                        <label>{% trans 'Currency' %}:</label>
                        <div class=\"formRight\">
                            {{ mf.selectbox('currency', guest.currency_get_pairs, request.currency, 0, 'Select currency') }}
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Password' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"password\" name=\"password\" value=\"\" required=\"required\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <input type=\"submit\" value=\"{% trans 'Create' %}\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-groups\">
            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td>{% trans 'Title' %}</td>
                        <td width=\"13%\">{% trans 'Actions' %}</td>
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
                        <td colspan=\"2\">{% trans 'The list is empty' %}</td>
                    </tr>
                    {% endfor %}
                </tbody>
            </table>
        </div>


        <div class=\"tab_content nopadding\" id=\"tab-new-group\">

            <form method=\"post\" action=\"{{ 'api/admin/client/group_create'|link }}\" class=\"mainForm api-form save\" data-api-redirect=\"{{ 'client'|alink }}\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>{% trans 'Title' %}:</label>
                        <div class=\"formRight noborder\">
                            <input type=\"text\" name=\"title\" value=\"{{request.title}}\" required=\"required\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <input type=\"submit\" value=\"{% trans 'Create' %}\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>

    </div>
</div>

{% endblock %}

{% block js %}



{% endblock %}", "mod_client_index.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/src/bb-themes/admin_default/html/mod_client_index.phtml");
    }
}
