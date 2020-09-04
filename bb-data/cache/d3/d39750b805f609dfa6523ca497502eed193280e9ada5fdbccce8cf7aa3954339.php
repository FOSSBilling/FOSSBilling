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

/* mod_client_manage.phtml */
class __TwigTemplate_8866b400f4f92a33e5a39066668ffd243601b7bb449653d2bfce13fb43b1a393 extends \Twig\Template
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
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_client_manage.phtml", 1)->unwrap();
        // line 3
        $context["active_menu"] = "client";
        // line 2
        $this->parent = $this->loadTemplate("layout_default.phtml", "mod_client_manage.phtml", 2);
        $this->parent->display($context, array_merge($this->blocks, $blocks));
    }

    // line 4
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "first_name", [], "any", false, false, false, 4), "html", null, true);
        echo " ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "last_name", [], "any", false, false, false, 4), "html", null, true);
    }

    // line 6
    public function block_breadcrumbs($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 7
        echo "<ul>
    <li class=\"firstB\"><a href=\"";
        // line 8
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/");
        echo "\">";
        echo gettext("Home");
        echo "</a></li>
    <li><a href=\"";
        // line 9
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("client");
        echo "\">";
        echo gettext("Clients");
        echo "</a></li>
    <li class=\"lastB\">";
        // line 10
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "first_name", [], "any", false, false, false, 10), "html", null, true);
        echo " ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "last_name", [], "any", false, false, false, 10), "html", null, true);
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
<div class=\"widget simpleTabs\">

    <ul class=\"tabs\">
        <li><a href=\"#tab-info\">";
        // line 19
        echo gettext("Profile");
        echo "</a></li>
        <li><a href=\"#tab-profile\">";
        // line 20
        echo gettext("Edit");
        echo "</a></li>
        <li><a href=\"#tab-orders\">";
        // line 21
        echo gettext("Orders");
        echo "</a></li>
        <li><a href=\"#tab-invoice\">";
        // line 22
        echo gettext("Invoices");
        echo "</a></li>
        <li><a href=\"#tab-support\">";
        // line 23
        echo gettext("Tickets");
        echo "</a></li>
        <li><a href=\"#tab-balance\">";
        // line 24
        echo gettext("Payments");
        echo "</a></li>
        <li><a href=\"#tab-history\">";
        // line 25
        echo gettext("Logins");
        echo "</a></li>
        <li><a href=\"#tab-emails\">";
        // line 26
        echo gettext("Emails");
        echo "</a></li>
        <li><a href=\"#tab-transactions\">";
        // line 27
        echo gettext("Transactions");
        echo "</a></li>
    </ul>

    <div class=\"tabs_container\">
        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-info\">

            <div style=\"position: relative;\">
            <img src=\"";
        // line 35
        echo twig_escape_filter($this->env, twig_gravatar_filter(twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "email", [], "any", false, false, false, 35)), "html", null, true);
        echo "?size=100\" alt=\"";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "first_name", [], "any", false, false, false, 35), "html", null, true);
        echo " ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "last_name", [], "any", false, false, false, 35), "html", null, true);
        echo "\" style=\"right: 0; margin: 15px 15px 0 15px; position: absolute; border: 2px solid white; box-shadow: 0px 0px 10px 0px;\"/>
            </div>

            <table class=\"tableStatic wide\">
                <tbody>
                    <tr class=\"noborder\">
                        <td style=\"width: 15%\">ID:</td>
                        <td>";
        // line 42
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "id", [], "any", false, false, false, 42), "html", null, true);
        echo "</td>
                    </tr>

                    <tr>
                        <td>Name:</td>
                        <td><strong class=\"red\">";
        // line 47
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "first_name", [], "any", false, false, false, 47), "html", null, true);
        echo " ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "last_name", [], "any", false, false, false, 47), "html", null, true);
        echo "</strong></td>
                    </tr>

                    <tr>
                        <td>Company:</td>
                        <td><strong class=\"green\">";
        // line 52
        echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "company", [], "any", true, true, false, 52)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "company", [], "any", false, false, false, 52), "-")) : ("-")), "html", null, true);
        echo "</strong></td>
                    </tr>
                    <tr>
                        <td>Email:</td>
                        <td>";
        // line 56
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "email", [], "any", false, false, false, 56), "html", null, true);
        echo "</td>
                    </tr>
                    <tr>
                        <td>Status:</td>
                        <td>";
        // line 60
        echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "status", [], "any", false, false, false, 60)], 60, $context, $this->getSourceContext());
        echo "</td>
                    </tr>
                    <tr>
                        <td>Group:</td>
                        <td>";
        // line 64
        echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "group", [], "any", true, true, false, 64)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "group", [], "any", false, false, false, 64), "-")) : ("-")), "html", null, true);
        echo "</td>
                    </tr>
                    <tr>
                        <td>Currency:</td>
                        <td>";
        // line 68
        echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "currency", [], "any", true, true, false, 68)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "currency", [], "any", false, false, false, 68), "-")) : ("-")), "html", null, true);
        echo "</td>
                    </tr>
                    <tr>
                        <td>IP:</td>
                        <td>";
        // line 72
        echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "ip", [], "any", true, true, false, 72)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "ip", [], "any", false, false, false, 72), "-")) : ("-")), "html", null, true);
        echo " ";
        echo twig_escape_filter($this->env, _twig_default_filter($this->extensions['Box_TwigExtensions']->twig_ipcountryname_filter(twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "ip", [], "any", false, false, false, 72)), "Unknown"), "html", null, true);
        echo "</td>
                    </tr>
                    <tr>
                        <td>API Key:</td>
                        <td>";
        // line 76
        echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "api_token", [], "any", true, true, false, 76)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "api_token", [], "any", false, false, false, 76), "-")) : ("-")), "html", null, true);
        echo "</td>
                    </tr>
                    <tr>
                        <td>Address:</td>
                        <td>
                            <span class=\"flag flag-";
        // line 81
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "country", [], "any", false, false, false, 81), "html", null, true);
        echo "\" title=\"";
        echo twig_escape_filter($this->env, (($__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4 = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_countries", [], "any", false, false, false, 81)) && is_array($__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4) || $__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4 instanceof ArrayAccess ? ($__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4[twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "country", [], "any", false, false, false, 81)] ?? null) : null), "html", null, true);
        echo "\"></span>
                            ";
        // line 82
        echo twig_escape_filter($this->env, (($__internal_62824350bc4502ee19dbc2e99fc6bdd3bd90e7d8dd6e72f42c35efd048542144 = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_countries", [], "any", false, false, false, 82)) && is_array($__internal_62824350bc4502ee19dbc2e99fc6bdd3bd90e7d8dd6e72f42c35efd048542144) || $__internal_62824350bc4502ee19dbc2e99fc6bdd3bd90e7d8dd6e72f42c35efd048542144 instanceof ArrayAccess ? ($__internal_62824350bc4502ee19dbc2e99fc6bdd3bd90e7d8dd6e72f42c35efd048542144[twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "country", [], "any", false, false, false, 82)] ?? null) : null), "html", null, true);
        echo " ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "state", [], "any", false, false, false, 82), "html", null, true);
        echo " ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "address_1", [], "any", false, false, false, 82), "html", null, true);
        echo " ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "address_2", [], "any", false, false, false, 82), "html", null, true);
        echo " ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "city", [], "any", false, false, false, 82), "html", null, true);
        echo " ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "postcode", [], "any", false, false, false, 82), "html", null, true);
        echo "
                        </td>
                    </tr>
                    <tr>
                        <td>Registered at:</td>
                        <td>";
        // line 87
        echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "created_at", [], "any", false, false, false, 87), "M d, Y"), "html", null, true);
        echo "</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan=\"2\">
                            <a href=\"";
        // line 93
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("client/login");
        echo "/";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "id", [], "any", false, false, false, 93), "html", null, true);
        echo "\" class=\"btnIconLeft mr10 mt5\" target=\"_blank\"><img src=\"images/icons/dark/adminUser.png\" alt=\"\" class=\"icon\"><span>Login to client area</span></a>
                            <a href=\"";
        // line 94
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/client/delete", ["id" => twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "id", [], "any", false, false, false, 94)]);
        echo "\" data-api-confirm=\"Are you sure?\" data-api-redirect=\"";
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("client");
        echo "\" class=\"btnIconLeft mr10 mt5 api-link\" ><img src=\"images/icons/dark/trash.png\" alt=\"\" class=\"icon\"><span>Delete</span></a>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-profile\">

            <div class=\"help\">
                <h3>";
        // line 104
        echo gettext("Client profile details");
        echo "</h3>
            </div>

            <form method=\"post\" action=\"";
        // line 107
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/client/update");
        echo "\" class=\"mainForm api-form save\" data-api-msg=\"";
        echo gettext("Client Profile updated");
        echo "\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>";
        // line 110
        echo gettext("Status");
        echo ":</label>
                        <div class=\"formRight noborder\">
                            <input type=\"radio\" name=\"status\" value=\"active\"";
        // line 112
        if ((twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "status", [], "any", false, false, false, 112) == "active")) {
            echo " checked=\"checked\"";
        }
        echo "/><label>";
        echo gettext("Active");
        echo "</label>
                            <input type=\"radio\" name=\"status\" value=\"suspended\"";
        // line 113
        if ((twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "status", [], "any", false, false, false, 113) == "suspended")) {
            echo " checked=\"checked\"";
        }
        echo " /><label>";
        echo gettext("Suspended");
        echo "</label>
                            <input type=\"radio\" name=\"status\" value=\"canceled\"";
        // line 114
        if ((twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "status", [], "any", false, false, false, 114) == "canceled")) {
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
        // line 119
        echo gettext("Type");
        echo ":</label>
                        <div class=\"formRight noborder\">
                            <input type=\"radio\" name=\"type\" value=\"individual\"";
        // line 121
        if ((twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "type", [], "any", false, false, false, 121) == "individual")) {
            echo " checked=\"checked\"";
        }
        echo "/><label>";
        echo gettext("Individual");
        echo "</label>
                            <input type=\"radio\" name=\"type\" value=\"company\"";
        // line 122
        if ((twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "type", [], "any", false, false, false, 122) == "company")) {
            echo " checked=\"checked\"";
        }
        echo " /><label>";
        echo gettext("Company");
        echo "</label>
                            <input type=\"radio\" name=\"type\" value=\"\"";
        // line 123
        if ( !twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "type", [], "any", false, false, false, 123)) {
            echo " checked=\"checked\"";
        }
        echo " /><label>";
        echo gettext("Other/Unknown");
        echo "</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 129
        echo gettext("Mail approved");
        echo ":</label>
                        <div class=\"formRight noborder\">
                            <input type=\"radio\" name=\"email_approved\" value=\"1\"";
        // line 131
        if ((twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "email_approved", [], "any", false, false, false, 131) == 1)) {
            echo " checked=\"checked\"";
        }
        echo "/><label>";
        echo gettext("Yes");
        echo "</label>
                            <input type=\"radio\" name=\"email_approved\" value=\"0\"";
        // line 132
        if ((twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "email_approved", [], "any", false, false, false, 132) != 1)) {
            echo " checked=\"checked\"";
        }
        echo " /><label>";
        echo gettext("No");
        echo "</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>



                    <div class=\"rowElem\">
                        <label>";
        // line 140
        echo gettext("Group");
        echo ":</label>
                        <div class=\"formRight\">
                            ";
        // line 142
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["group_id", twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "client_group_get_pairs", [], "any", false, false, false, 142), twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "group_id", [], "any", false, false, false, 142), 0, "Select group"], 142, $context, $this->getSourceContext());
        echo "
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 147
        echo gettext("Email");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"email\" value=\"";
        // line 149
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "email", [], "any", false, false, false, 149), "html", null, true);
        echo "\" required=\"required\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 154
        echo gettext("Name");
        echo ":</label>
                        <div class=\"formRight moreFields\">
                            <ul>
                                <li style=\"width: 250px\"><input type=\"text\" name=\"first_name\" value=\"";
        // line 157
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "first_name", [], "any", false, false, false, 157), "html", null, true);
        echo "\" required=\"required\"/></li>
                                <li class=\"sep\"></li>
                                <li style=\"width: 245px\"><input type=\"text\" name=\"last_name\" value=\"";
        // line 159
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "last_name", [], "any", false, false, false, 159), "html", null, true);
        echo "\" /></li>
                            </ul>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 165
        echo gettext("Date of birth");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"birthday\" value=\"";
        // line 167
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "birthday", [], "any", false, false, false, 167), "html", null, true);
        echo "\" />
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\" id=\"company-details\">
                        <label>";
        // line 172
        echo gettext("Company details");
        echo ":</label>
                        <div class=\"formRight moreFields\">
                            <ul>
                                <li style=\"width: 170px\"><input type=\"text\" name=\"company\" value=\"";
        // line 175
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "company", [], "any", false, false, false, 175), "html", null, true);
        echo "\" title=\"Company name\" placeholder=\"Company name\"/></li>
                                <li class=\"sep\"></li>
                                <li style=\"width: 150px\"><input type=\"text\" name=\"company_vat\" value=\"";
        // line 177
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "company_vat", [], "any", false, false, false, 177), "html", null, true);
        echo "\" title=\"Company VAT number\" placeholder=\"Company VAT number\"/></li>
                                <li class=\"sep\"></li>
                                <li style=\"width: 150px\"><input type=\"text\" name=\"company_number\" value=\"";
        // line 179
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "company_number", [], "any", false, false, false, 179), "html", null, true);
        echo "\"  title=\"Company number\" placeholder=\"Company number\"/></li>
                                <li class=\"sep\"></li>
                            </ul>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <input type=\"submit\" value=\"";
        // line 185
        echo gettext("Update");
        echo "\" class=\"greyishBtn submitForm\" />
                </fieldset>

                <fieldset>
                    <legend>";
        // line 189
        echo gettext("Address and contact details");
        echo "</legend>
                    <div class=\"rowElem\">
                        <label>";
        // line 191
        echo gettext("Address Line 1");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"address_1\" value=\"";
        // line 193
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "address_1", [], "any", false, false, false, 193), "html", null, true);
        echo "\" />
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 198
        echo gettext("Address Line 2");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"address_2\" value=\"";
        // line 200
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "address_2", [], "any", false, false, false, 200), "html", null, true);
        echo "\" />
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 206
        echo gettext("Country");
        echo ":</label>
                        <div class=\"formRight\">
                            ";
        // line 208
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["country", twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_countries", [], "any", false, false, false, 208), twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "country", [], "any", false, false, false, 208), 0, "Select country"], 208, $context, $this->getSourceContext());
        echo "
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 213
        echo gettext("State");
        echo ":</label>
                        <div class=\"formRight\">
                            ";
        // line 216
        echo "                            <input type=\"text\" name=\"state\" value=\"";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "state", [], "any", false, false, false, 216), "html", null, true);
        echo "\" />
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 221
        echo gettext("City");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"city\" value=\"";
        // line 223
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "city", [], "any", false, false, false, 223), "html", null, true);
        echo "\" />
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 228
        echo gettext("Postcode");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"postcode\" value=\"";
        // line 230
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "postcode", [], "any", false, false, false, 230), "html", null, true);
        echo "\" />
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 235
        echo gettext("Phone");
        echo ":</label>
                        <div class=\"formRight moreFields\">
                            <ul>
                                <li><input type=\"text\" name=\"phone_cc\" value=\"";
        // line 238
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "phone_cc", [], "any", false, false, false, 238), "html", null, true);
        echo "\" /></li>
                                <li class=\"sep\"></li>
                                <li style=\"width: 200px;\"><input type=\"text\" name=\"phone\" value=\"";
        // line 240
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "phone", [], "any", false, false, false, 240), "html", null, true);
        echo "\" /></li>
                            </ul>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 246
        echo gettext("Passport number");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"document_nr\" value=\"";
        // line 248
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "document_nr", [], "any", false, false, false, 248), "html", null, true);
        echo "\" />
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <input type=\"submit\" value=\"";
        // line 252
        echo gettext("Update");
        echo "\" class=\"greyishBtn submitForm\" />
                </fieldset>

                <fieldset>
                    <legend>";
        // line 256
        echo gettext("Additional settings");
        echo "</legend>
                    <div class=\"rowElem\">
                        <label>";
        // line 258
        echo gettext("Alternative ID");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"aid\" value=\"";
        // line 260
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "aid", [], "any", false, false, false, 260), "html", null, true);
        echo "\" placeholder=\"";
        echo gettext("Used to identify client on foreign system. Usually used by importers");
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 266
        echo gettext("Currency");
        echo ":</label>
                        <div class=\"formRight\">
                            ";
        // line 268
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["currency", twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "currency_get_pairs", [], "any", false, false, false, 268), twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "currency", [], "any", false, false, false, 268), 0, "Select currency"], 268, $context, $this->getSourceContext());
        echo "
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 274
        echo gettext("Exempt from tax");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"radio\" name=\"tax_exempt\" value=\"1\"";
        // line 276
        if (twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "tax_exempt", [], "any", false, false, false, 276)) {
            echo " checked=\"checked\"";
        }
        echo "/><label>Yes</label>
                            <input type=\"radio\" name=\"tax_exempt\" value=\"0\"";
        // line 277
        if ( !twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "tax_exempt", [], "any", false, false, false, 277)) {
            echo " checked=\"checked\"";
        }
        echo " /><label>No</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 282
        echo gettext("Signed up time");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"created_at\" value=\"";
        // line 284
        echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "created_at", [], "any", false, false, false, 284), "Y-m-d"), "html", null, true);
        echo "\" />
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 289
        echo gettext("Notes");
        echo ":</label>
                        <div class=\"formRight\">
                            <textarea name=\"notes\" cols=\"5\" rows=\"5\">";
        // line 291
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "notes", [], "any", false, false, false, 291), "html", null, true);
        echo "</textarea>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <input type=\"submit\" value=\"";
        // line 295
        echo gettext("Update profile");
        echo "\" class=\"greyishBtn submitForm\" />
                    <input type=\"hidden\" name=\"id\" value=\"";
        // line 296
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "id", [], "any", false, false, false, 296), "html", null, true);
        echo "\"/>
                </fieldset>
            </form>

            <div class=\"help\">
                <h3>";
        // line 301
        echo gettext("Change password");
        echo "</h3>
            </div>
            <form method=\"post\" action=\"";
        // line 303
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/client/change_password");
        echo "\" class=\"mainForm api-form\" data-api-msg=\"";
        echo gettext("Password changed");
        echo "\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>";
        // line 306
        echo gettext("Password");
        echo "</label>
                        <div class=\"formRight\">
                            <input type=\"password\" name=\"password\" value=\"\" required=\"required\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 314
        echo gettext("Password confirm");
        echo "</label>
                        <div class=\"formRight\">
                            <input type=\"password\" name=\"password_confirm\" value=\"\" required=\"required\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <input type=\"submit\" value=\"";
        // line 321
        echo gettext("Change password");
        echo "\" class=\"greyishBtn submitForm\" />
                    <input type=\"hidden\" name=\"id\" value=\"";
        // line 322
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "id", [], "any", false, false, false, 322), "html", null, true);
        echo "\"/>
                </fieldset>
            </form>

            <div class=\"help\">
                <h3>";
        // line 327
        echo gettext("Custom fields");
        echo "</h3>
                <p>";
        // line 328
        echo gettext("Use these fields to define custom client details");
        echo "</p>
            </div>

            <form method=\"post\" action=\"";
        // line 331
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/client/update");
        echo "\" class=\"mainForm api-form save\" data-api-msg=\"";
        echo gettext("Client Profile custom fields updated");
        echo "\">
                <fieldset>
                    ";
        // line 333
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(range(1, 10));
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
        foreach ($context['_seq'] as $context["_key"] => $context["i"]) {
            // line 334
            echo "                    ";
            $context["fn"] = ("custom_" . $context["i"]);
            // line 335
            echo "                    <div class=\"rowElem";
            if (twig_get_attribute($this->env, $this->source, $context["loop"], "first", [], "any", false, false, false, 335)) {
                echo " noborder";
            }
            echo "\">
                        <label>";
            // line 336
            echo gettext("Custom field");
            echo " ";
            echo twig_escape_filter($this->env, $context["i"], "html", null, true);
            echo "</label>
                        <div class=\"formRight\">
                            <textarea name=\"custom_";
            // line 338
            echo twig_escape_filter($this->env, $context["i"], "html", null, true);
            echo "\" cols=\"5\" rows=\"5\">";
            echo twig_escape_filter($this->env, (($__internal_1cfccaec8dd2e8578ccb026fbe7f2e7e29ac2ed5deb976639c5fc99a6ea8583b = ($context["client"] ?? null)) && is_array($__internal_1cfccaec8dd2e8578ccb026fbe7f2e7e29ac2ed5deb976639c5fc99a6ea8583b) || $__internal_1cfccaec8dd2e8578ccb026fbe7f2e7e29ac2ed5deb976639c5fc99a6ea8583b instanceof ArrayAccess ? ($__internal_1cfccaec8dd2e8578ccb026fbe7f2e7e29ac2ed5deb976639c5fc99a6ea8583b[($context["fn"] ?? null)] ?? null) : null), "html", null, true);
            echo "</textarea>
                        </div>
                        <div class=\"fix\"></div>
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
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['i'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 343
        echo "
                    <input type=\"submit\" value=\"";
        // line 344
        echo gettext("Update");
        echo "\" class=\"greyishBtn submitForm\" />
                    <input type=\"hidden\" name=\"id\" value=\"";
        // line 345
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "id", [], "any", false, false, false, 345), "html", null, true);
        echo "\"/>
                </fieldset>
            </form>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-balance\">
            <div class=\"help\">
                <h3>";
        // line 352
        echo gettext("Client payments history");
        echo "</h3>
            </div>

            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <th style=\"width: 15%\">";
        // line 358
        echo gettext("Amount");
        echo "</th>
                        <th>";
        // line 359
        echo gettext("Description");
        echo "</th>
                        <th style=\"width: 20%\">";
        // line 360
        echo gettext("Date");
        echo "</th>
                        <th style=\"width: 5%\">&nbsp;</th>
                    </tr>
                </thead>

                <tbody>
                    ";
        // line 366
        $context["payments"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "client_balance_get_list", [0 => ["per_page" => 20, "client_id" => twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "id", [], "any", false, false, false, 366)]], "method", false, false, false, 366);
        // line 367
        echo "                    ";
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["payments"] ?? null), "list", [], "any", false, false, false, 367));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["i"] => $context["tx"]) {
            // line 368
            echo "                    <tr>
                        <td>";
            // line 369
            echo twig_call_macro($macros["mf"], "macro_currency_format", [twig_get_attribute($this->env, $this->source, $context["tx"], "amount", [], "any", false, false, false, 369), twig_get_attribute($this->env, $this->source, $context["tx"], "currency", [], "any", false, false, false, 369)], 369, $context, $this->getSourceContext());
            echo "</td>
                        <td>";
            // line 370
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tx"], "description", [], "any", false, false, false, 370), "html", null, true);
            echo "</td>
                        <td>";
            // line 371
            echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tx"], "created_at", [], "any", false, false, false, 371), "Y-m-d H:i"), "html", null, true);
            echo "</td>
                        <td>
                            <a class=\"bb-button btn14 bb-rm-tr api-link\" data-api-confirm=\"Are you sure?\" href=\"";
            // line 373
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/client/balance_delete", ["id" => twig_get_attribute($this->env, $this->source, $context["tx"], "id", [], "any", false, false, false, 373)]);
            echo "\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                        </td>
                    </tr>
                    ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 377
            echo "                    <tr>
                        <td colspan=\"4\">";
            // line 378
            echo gettext("The list is empty");
            echo "</td>
                    </tr>
                    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['i'], $context['tx'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 381
        echo "                </tbody>

                <tfoot>
                    <tr>
                        <th class=\"currency\" colspan=\"4\">";
        // line 385
        echo gettext("Balance");
        echo ": ";
        echo twig_call_macro($macros["mf"], "macro_currency_format", [twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "balance", [], "any", false, false, false, 385), twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "currency", [], "any", false, false, false, 385)], 385, $context, $this->getSourceContext());
        echo "</th>
                    </tr>
                </tfoot>
            </table>

            <div class=\"help\">
                <h3>";
        // line 391
        echo gettext("Add funds for client");
        echo "</h3>
            </div>

            <form method=\"post\" action=\"";
        // line 394
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/client/balance_add_funds");
        echo "\" class=\"mainForm api-form save\" data-api-msg=\"";
        echo gettext("Funds added");
        echo "\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>";
        // line 397
        echo gettext("Amount");
        echo ":</label>
                        <div class=\"formRight noborder\">
                            <input type=\"text\" name=\"amount\" value=\"\" style=\"width: 100px;\" required=\"required\"/> ";
        // line 399
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "currency", [], "any", false, false, false, 399), "html", null, true);
        echo "
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 405
        echo gettext("Description");
        echo ":</label>
                        <div class=\"formRight noborder\">
                            <input type=\"text\" name=\"description\" value=\"\"  required=\"required\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <input type=\"submit\" value=\"";
        // line 412
        echo gettext("Add funds");
        echo "\" class=\"greyishBtn submitForm\" />
                    <input type=\"hidden\" name=\"id\" value=\"";
        // line 413
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "id", [], "any", false, false, false, 413), "html", null, true);
        echo "\"/>
                </fieldset>
            </form>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-orders\">
            <div class=\"help\">
                <h3>";
        // line 420
        echo gettext("Client orders");
        echo "</h3>
            </div>

            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td>ID</td>
                        <td width=\"40%\">Title</td>
                        <td width=\"20%\">Amount</td>
                        <td width=\"20%\">Period</td>
                        <td width=\"20%\">Status</td>
                        <td>&nbsp;</td>
                    </tr>
                </thead>
                <tbody>
                    ";
        // line 435
        $context["orders"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "order_get_list", [0 => ["per_page" => "20", "client_id" => twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "id", [], "any", false, false, false, 435)]], "method", false, false, false, 435);
        // line 436
        echo "                    ";
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["orders"] ?? null), "list", [], "any", false, false, false, 436));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["_key"] => $context["order"]) {
            // line 437
            echo "                    <tr>
                        <td>";
            // line 438
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["order"], "id", [], "any", false, false, false, 438), "html", null, true);
            echo "</td>
                        <td>";
            // line 439
            echo twig_escape_filter($this->env, twig_truncate_filter($this->env, twig_get_attribute($this->env, $this->source, $context["order"], "title", [], "any", false, false, false, 439), 30), "html", null, true);
            echo "</td>
                        <td>";
            // line 440
            echo twig_call_macro($macros["mf"], "macro_currency_format", [twig_get_attribute($this->env, $this->source, $context["order"], "total", [], "any", false, false, false, 440), twig_get_attribute($this->env, $this->source, $context["order"], "currency", [], "any", false, false, false, 440)], 440, $context, $this->getSourceContext());
            echo "</td>
                        <td>";
            // line 441
            echo twig_call_macro($macros["mf"], "macro_period_name", [twig_get_attribute($this->env, $this->source, $context["order"], "period", [], "any", false, false, false, 441)], 441, $context, $this->getSourceContext());
            echo "</td>
                        <td>";
            // line 442
            echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, $context["order"], "status", [], "any", false, false, false, 442)], 442, $context, $this->getSourceContext());
            echo "</td>
                        <td class=\"actions\"><a class=\"bb-button btn14\" href=\"";
            // line 443
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/order/manage");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["order"], "id", [], "any", false, false, false, 443), "html", null, true);
            echo "\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a></td>
                    </tr>
                    ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 446
            echo "                    <tr>
                        <td colspan=\"6\">";
            // line 447
            echo gettext("The list is empty");
            echo "</td>
                    </tr>
                    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['order'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 450
        echo "                </tbody>

                <tfoot>
                    <tr>
                        <td colspan=\"6\">
                            <a href=\"";
        // line 455
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("order", ["client_id" => twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "id", [], "any", false, false, false, 455)]);
        echo "#tab-new\" class=\"btnIconLeft mr10 mt5\"><img src=\"images/icons/dark/money.png\" alt=\"\" class=\"icon\"><span>New order</span></a>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-invoice\">
            <div class=\"help\">
                <h3>";
        // line 464
        echo gettext("Client invoices");
        echo "</h3>
            </div>

            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td>#</td>
                        <td width=\"15%\">Amount</td>
                        <td width=\"30%\">Issued at</td>
                        <td width=\"30%\">Paid at</td>
                        <td width=\"15%\">Status</td>
                        <td>&nbsp;</td>
                    </tr>
                </thead>
                <tbody>
                    ";
        // line 479
        $context["invoices"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "invoice_get_list", [0 => ["per_page" => "100", "client_id" => twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "id", [], "any", false, false, false, 479)]], "method", false, false, false, 479);
        // line 480
        echo "                    ";
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["invoices"] ?? null), "list", [], "any", false, false, false, 480));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["_key"] => $context["invoice"]) {
            // line 481
            echo "                    <tr>
                        <td>";
            // line 482
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["invoice"], "serie_nr", [], "any", false, false, false, 482), "html", null, true);
            echo "</td>
                        <td>";
            // line 483
            echo twig_call_macro($macros["mf"], "macro_currency_format", [twig_get_attribute($this->env, $this->source, $context["invoice"], "total", [], "any", false, false, false, 483), twig_get_attribute($this->env, $this->source, $context["invoice"], "currency", [], "any", false, false, false, 483)], 483, $context, $this->getSourceContext());
            echo "</td>
                        <td>";
            // line 484
            echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, $context["invoice"], "created_at", [], "any", false, false, false, 484), "Y-m-d"), "html", null, true);
            echo "</td>
                        <td>";
            // line 485
            if (twig_get_attribute($this->env, $this->source, $context["invoice"], "paid_at", [], "any", false, false, false, 485)) {
                echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, $context["invoice"], "paid_at", [], "any", false, false, false, 485), "Y-m-d"), "html", null, true);
            } else {
                echo "-";
            }
            echo "</td>
                        <td>";
            // line 486
            echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, $context["invoice"], "status", [], "any", false, false, false, 486)], 486, $context, $this->getSourceContext());
            echo "</td>
                        <td class=\"actions\"><a class=\"bb-button btn14\" href=\"";
            // line 487
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/invoice/manage");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["invoice"], "id", [], "any", false, false, false, 487), "html", null, true);
            echo "\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a></td>
                    </tr>
                    ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 490
            echo "                    <tr>
                        <td colspan=\"6\">";
            // line 491
            echo gettext("The list is empty");
            echo "</td>
                    </tr>
                    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['invoice'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 494
        echo "                </tbody>

                <tfoot>
                    <tr>
                        <td colspan=\"6\">
                            <a href=\"";
        // line 499
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/invoice/prepare", ["client_id" => twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "id", [], "any", false, false, false, 499)]);
        echo "\" class=\"btnIconLeft mr10 mt5 api-link\" data-api-jsonp=\"onAfterInvoicePrepared\" ><img src=\"images/icons/dark/money.png\" alt=\"\" class=\"icon\"><span>New invoice</span></a>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-support\">
            <div class=\"help\">
                <h3>";
        // line 508
        echo gettext("Client support tickets");
        echo "</h3>
            </div>
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
        // line 522
        $context["tickets"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "support_ticket_get_list", [0 => ["per_page" => "20", "client_id" => twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "id", [], "any", false, false, false, 522)]], "method", false, false, false, 522);
        // line 523
        echo "                    ";
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["tickets"] ?? null), "list", [], "any", false, false, false, 523));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["_key"] => $context["ticket"]) {
            // line 524
            echo "                    <tr>
                        <td>";
            // line 525
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "id", [], "any", false, false, false, 525), "html", null, true);
            echo "</td>
                        <td>";
            // line 526
            echo twig_escape_filter($this->env, twig_truncate_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "subject", [], "any", false, false, false, 526), 30), "html", null, true);
            echo "</td>
                        <td>";
            // line 527
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["ticket"], "helpdesk", [], "any", false, false, false, 527), "name", [], "any", false, false, false, 527), "html", null, true);
            echo "</td>
                        <td>";
            // line 528
            echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, $context["ticket"], "status", [], "any", false, false, false, 528)], 528, $context, $this->getSourceContext());
            echo "</td>
                        <td class=\"actions\"><a class=\"bb-button btn14\" href=\"";
            // line 529
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/support/ticket");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "id", [], "any", false, false, false, 529), "html", null, true);
            echo "\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a></td>
                    </tr>
                    ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 532
            echo "                    <tr>
                        <td colspan=\"5\">";
            // line 533
            echo gettext("The list is empty");
            echo "</td>
                    </tr>
                    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['ticket'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 536
        echo "                </tbody>

                <tfoot>
                    <tr>
                        <td colspan=\"5\">
                            <a href=\"";
        // line 541
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support", ["client_id" => twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "id", [], "any", false, false, false, 541)]);
        echo "#tab-new\" class=\"btnIconLeft mr10 mt5\" ><img src=\"images/icons/dark/help.png\" alt=\"\" class=\"icon\"><span>New support ticket</span></a>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-history\">
            <div class=\"help\">
                <h3>";
        // line 550
        echo gettext("Logins history");
        echo "</h3>
            </div>

            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <th>";
        // line 556
        echo gettext("IP");
        echo "</th>
                        <th>";
        // line 557
        echo gettext("Country");
        echo "</th>
                        <th>";
        // line 558
        echo gettext("Date");
        echo "</th>
                    </tr>
                </thead>

                <tbody>
                    ";
        // line 563
        $context["logins"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "client_login_history_get_list", [0 => ["per_page" => 10, "page" => twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 563), "client_id" => twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "id", [], "any", false, false, false, 563)]], "method", false, false, false, 563);
        // line 564
        echo "                    ";
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["logins"] ?? null), "list", [], "any", false, false, false, 564));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["i"] => $context["login"]) {
            // line 565
            echo "                    <tr>
                        <td>";
            // line 566
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["login"], "ip", [], "any", false, false, false, 566), "html", null, true);
            echo "</td>
                        <td>";
            // line 567
            echo twig_escape_filter($this->env, _twig_default_filter($this->extensions['Box_TwigExtensions']->twig_ipcountryname_filter(twig_get_attribute($this->env, $this->source, $context["login"], "ip", [], "any", false, false, false, 567)), "Unknown"), "html", null, true);
            echo "</td>
                        <td>";
            // line 568
            echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, $context["login"], "created_at", [], "any", false, false, false, 568), "l, d F Y"), "html", null, true);
            echo "</td>
                    </tr>
                    ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 571
            echo "                    <tr>
                        <td colspan=\"3\">";
            // line 572
            echo gettext("The list is empty");
            echo "</td>
                    </tr>
                    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['i'], $context['login'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 575
        echo "                </tbody>
            </table>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-emails\">
            <div class=\"help\">
                <h3>";
        // line 581
        echo gettext("Emails sent to client");
        echo "</h3>
            </div>

            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <th>";
        // line 587
        echo gettext("Email subject");
        echo "</th>
                        <th>";
        // line 588
        echo gettext("To");
        echo "</th>
                        <th>";
        // line 589
        echo gettext("Date sent");
        echo "</th>
                        <th style=\"width: 5%\">&nbsp;</th>
                    </tr>
                </thead>

                <tbody>
                    ";
        // line 595
        $context["emails"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "email_email_get_list", [0 => ["per_page" => "20", "client_id" => twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "id", [], "any", false, false, false, 595)]], "method", false, false, false, 595);
        // line 596
        echo "                    ";
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["emails"] ?? null), "list", [], "any", false, false, false, 596));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["i"] => $context["email"]) {
            // line 597
            echo "                    <tr>
                        <td>";
            // line 598
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["email"], "subject", [], "any", false, false, false, 598), "html", null, true);
            echo "</td>
                        <td>";
            // line 599
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["email"], "recipients", [], "any", false, false, false, 599), "html", null, true);
            echo "</td>
                        <td>";
            // line 600
            echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, $context["email"], "created_at", [], "any", false, false, false, 600), "l, d F Y"), "html", null, true);
            echo "</td>
                        <td class=\"actions\"><a class=\"bb-button btn14\" href=\"";
            // line 601
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("email");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["email"], "id", [], "any", false, false, false, 601), "html", null, true);
            echo "\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a></td>
                    </tr>
                    ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 604
            echo "                    <tr>
                        <td colspan=\"4\">";
            // line 605
            echo gettext("The list is empty");
            echo "</td>
                    </tr>
                    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['i'], $context['email'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 608
        echo "                </tbody>
            </table>
            ";
        // line 610
        $this->loadTemplate("partial_pagination.phtml", "mod_client_manage.phtml", 610)->display(twig_array_merge($context, ["list" => ($context["emails"] ?? null)]));
        // line 611
        echo "        </div>

        <div class=\"tab_content nopadding\" id=\"tab-transactions\">
            <div class=\"help\">
                <h3>";
        // line 615
        echo gettext("Transactions received");
        echo "</h3>
            </div>

            ";
        // line 618
        $context["transactions"] = twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "invoice_transaction_get_list", [0 => ["client_id" => twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "id", [], "any", false, false, false, 618), "per_page" => 100]], "method", false, false, false, 618), "list", [], "any", false, false, false, 618);
        // line 619
        echo "            <table class=\"tableStatic wide\">
                <thead>
                <tr>
                    <th>";
        // line 622
        echo gettext("ID");
        echo "</th>
                    <th>";
        // line 623
        echo gettext("Type");
        echo "</th>
                    <th>";
        // line 624
        echo gettext("Gateway");
        echo "</th>
                    <th>";
        // line 625
        echo gettext("Amount");
        echo "</th>
                    <th>";
        // line 626
        echo gettext("Status");
        echo "</th>
                    <th>";
        // line 627
        echo gettext("Date");
        echo "</th>
                    <th>&nbsp;</th>
                </tr>
                </thead>

                <tbody>
                ";
        // line 633
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(($context["transactions"] ?? null));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["i"] => $context["tx"]) {
            // line 634
            echo "                    <tr>
                        <td>";
            // line 635
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tx"], "txn_id", [], "any", false, false, false, 635), "html", null, true);
            echo "</td>
                        <td>";
            // line 636
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tx"], "type", [], "any", false, false, false, 636), "html", null, true);
            echo "</td>
                        <td>";
            // line 637
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tx"], "gateway", [], "any", false, false, false, 637), "html", null, true);
            echo "</td>
                        <td>";
            // line 638
            echo twig_call_macro($macros["mf"], "macro_currency_format", [twig_get_attribute($this->env, $this->source, $context["tx"], "amount", [], "any", false, false, false, 638), twig_get_attribute($this->env, $this->source, $context["tx"], "currency", [], "any", false, false, false, 638)], 638, $context, $this->getSourceContext());
            echo "</td>
                        <td>";
            // line 639
            echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, $context["tx"], "status", [], "any", false, false, false, 639)], 639, $context, $this->getSourceContext());
            echo "</td>
                        <td>";
            // line 640
            echo twig_escape_filter($this->env, $this->extensions['Box_TwigExtensions']->twig_bb_datetime(twig_get_attribute($this->env, $this->source, $context["tx"], "created_at", [], "any", false, false, false, 640)), "html", null, true);
            echo "</td>
                        <td style=\"width: 5%\">
                            <a class=\"bb-button btn14\" href=\"";
            // line 642
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/invoice/transaction");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tx"], "id", [], "any", false, false, false, 642), "html", null, true);
            echo "\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                        </td>
                    </tr>
                ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 646
            echo "                    <tr>
                        <td colspan=\"7\">";
            // line 647
            echo gettext("The list is empty");
            echo "</td>
                    </tr>
                ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['i'], $context['tx'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 650
        echo "                </tbody>
            </table>
        </div>
    </div>
</div>


";
        // line 734
        echo "
";
    }

    // line 738
    public function block_js($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 739
        echo "<script type=\"text/javascript\">
\$(function() {

});

function onAfterInvoicePrepared(id) {
    bb.redirect(\"";
        // line 745
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("invoice/manage/");
        echo "/\"+id);
}
</script>
";
    }

    public function getTemplateName()
    {
        return "mod_client_manage.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  1522 => 745,  1514 => 739,  1510 => 738,  1505 => 734,  1496 => 650,  1487 => 647,  1484 => 646,  1473 => 642,  1468 => 640,  1464 => 639,  1460 => 638,  1456 => 637,  1452 => 636,  1448 => 635,  1445 => 634,  1440 => 633,  1431 => 627,  1427 => 626,  1423 => 625,  1419 => 624,  1415 => 623,  1411 => 622,  1406 => 619,  1404 => 618,  1398 => 615,  1392 => 611,  1390 => 610,  1386 => 608,  1377 => 605,  1374 => 604,  1364 => 601,  1360 => 600,  1356 => 599,  1352 => 598,  1349 => 597,  1343 => 596,  1341 => 595,  1332 => 589,  1328 => 588,  1324 => 587,  1315 => 581,  1307 => 575,  1298 => 572,  1295 => 571,  1287 => 568,  1283 => 567,  1279 => 566,  1276 => 565,  1270 => 564,  1268 => 563,  1260 => 558,  1256 => 557,  1252 => 556,  1243 => 550,  1231 => 541,  1224 => 536,  1215 => 533,  1212 => 532,  1202 => 529,  1198 => 528,  1194 => 527,  1190 => 526,  1186 => 525,  1183 => 524,  1177 => 523,  1175 => 522,  1158 => 508,  1146 => 499,  1139 => 494,  1130 => 491,  1127 => 490,  1117 => 487,  1113 => 486,  1105 => 485,  1101 => 484,  1097 => 483,  1093 => 482,  1090 => 481,  1084 => 480,  1082 => 479,  1064 => 464,  1052 => 455,  1045 => 450,  1036 => 447,  1033 => 446,  1023 => 443,  1019 => 442,  1015 => 441,  1011 => 440,  1007 => 439,  1003 => 438,  1000 => 437,  994 => 436,  992 => 435,  974 => 420,  964 => 413,  960 => 412,  950 => 405,  941 => 399,  936 => 397,  928 => 394,  922 => 391,  911 => 385,  905 => 381,  896 => 378,  893 => 377,  884 => 373,  879 => 371,  875 => 370,  871 => 369,  868 => 368,  862 => 367,  860 => 366,  851 => 360,  847 => 359,  843 => 358,  834 => 352,  824 => 345,  820 => 344,  817 => 343,  796 => 338,  789 => 336,  782 => 335,  779 => 334,  762 => 333,  755 => 331,  749 => 328,  745 => 327,  737 => 322,  733 => 321,  723 => 314,  712 => 306,  704 => 303,  699 => 301,  691 => 296,  687 => 295,  680 => 291,  675 => 289,  667 => 284,  662 => 282,  652 => 277,  646 => 276,  641 => 274,  632 => 268,  627 => 266,  616 => 260,  611 => 258,  606 => 256,  599 => 252,  592 => 248,  587 => 246,  578 => 240,  573 => 238,  567 => 235,  559 => 230,  554 => 228,  546 => 223,  541 => 221,  532 => 216,  527 => 213,  519 => 208,  514 => 206,  505 => 200,  500 => 198,  492 => 193,  487 => 191,  482 => 189,  475 => 185,  466 => 179,  461 => 177,  456 => 175,  450 => 172,  442 => 167,  437 => 165,  428 => 159,  423 => 157,  417 => 154,  409 => 149,  404 => 147,  396 => 142,  391 => 140,  376 => 132,  368 => 131,  363 => 129,  350 => 123,  342 => 122,  334 => 121,  329 => 119,  317 => 114,  309 => 113,  301 => 112,  296 => 110,  288 => 107,  282 => 104,  267 => 94,  261 => 93,  252 => 87,  234 => 82,  228 => 81,  220 => 76,  211 => 72,  204 => 68,  197 => 64,  190 => 60,  183 => 56,  176 => 52,  166 => 47,  158 => 42,  144 => 35,  133 => 27,  129 => 26,  125 => 25,  121 => 24,  117 => 23,  113 => 22,  109 => 21,  105 => 20,  101 => 19,  95 => 15,  91 => 14,  82 => 10,  76 => 9,  70 => 8,  67 => 7,  63 => 6,  54 => 4,  49 => 2,  47 => 3,  45 => 1,  38 => 2,);
    }

    public function getSourceContext()
    {
        return new Source("{% import \"macro_functions.phtml\" as mf %}
{% extends \"layout_default.phtml\" %}
{% set active_menu = 'client' %}
{% block meta_title %}{{ client.first_name }} {{ client.last_name }}{% endblock %}

{% block breadcrumbs %}
<ul>
    <li class=\"firstB\"><a href=\"{{ '/'|alink }}\">{% trans 'Home' %}</a></li>
    <li><a href=\"{{ 'client'|alink }}\">{% trans 'Clients' %}</a></li>
    <li class=\"lastB\">{{ client.first_name }} {{ client.last_name }}</li>
</ul>
{% endblock %}

{% block content %}

<div class=\"widget simpleTabs\">

    <ul class=\"tabs\">
        <li><a href=\"#tab-info\">{% trans 'Profile' %}</a></li>
        <li><a href=\"#tab-profile\">{% trans 'Edit' %}</a></li>
        <li><a href=\"#tab-orders\">{% trans 'Orders' %}</a></li>
        <li><a href=\"#tab-invoice\">{% trans 'Invoices' %}</a></li>
        <li><a href=\"#tab-support\">{% trans 'Tickets' %}</a></li>
        <li><a href=\"#tab-balance\">{% trans 'Payments' %}</a></li>
        <li><a href=\"#tab-history\">{% trans 'Logins' %}</a></li>
        <li><a href=\"#tab-emails\">{% trans 'Emails' %}</a></li>
        <li><a href=\"#tab-transactions\">{% trans 'Transactions' %}</a></li>
    </ul>

    <div class=\"tabs_container\">
        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-info\">

            <div style=\"position: relative;\">
            <img src=\"{{ client.email|gravatar }}?size=100\" alt=\"{{ client.first_name }} {{ client.last_name }}\" style=\"right: 0; margin: 15px 15px 0 15px; position: absolute; border: 2px solid white; box-shadow: 0px 0px 10px 0px;\"/>
            </div>

            <table class=\"tableStatic wide\">
                <tbody>
                    <tr class=\"noborder\">
                        <td style=\"width: 15%\">ID:</td>
                        <td>{{ client.id }}</td>
                    </tr>

                    <tr>
                        <td>Name:</td>
                        <td><strong class=\"red\">{{ client.first_name }} {{ client.last_name }}</strong></td>
                    </tr>

                    <tr>
                        <td>Company:</td>
                        <td><strong class=\"green\">{{ client.company|default('-') }}</strong></td>
                    </tr>
                    <tr>
                        <td>Email:</td>
                        <td>{{ client.email }}</td>
                    </tr>
                    <tr>
                        <td>Status:</td>
                        <td>{{ mf.status_name(client.status) }}</td>
                    </tr>
                    <tr>
                        <td>Group:</td>
                        <td>{{ client.group|default('-') }}</td>
                    </tr>
                    <tr>
                        <td>Currency:</td>
                        <td>{{ client.currency|default('-') }}</td>
                    </tr>
                    <tr>
                        <td>IP:</td>
                        <td>{{ client.ip|default('-') }} {{ client.ip|ipcountryname|default('Unknown') }}</td>
                    </tr>
                    <tr>
                        <td>API Key:</td>
                        <td>{{ client.api_token|default('-') }}</td>
                    </tr>
                    <tr>
                        <td>Address:</td>
                        <td>
                            <span class=\"flag flag-{{ client.country }}\" title=\"{{ guest.system_countries[client.country] }}\"></span>
                            {{ guest.system_countries[client.country] }} {{ client.state }} {{ client.address_1 }} {{ client.address_2 }} {{ client.city }} {{ client.postcode }}
                        </td>
                    </tr>
                    <tr>
                        <td>Registered at:</td>
                        <td>{{ client.created_at|date('M d, Y') }}</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan=\"2\">
                            <a href=\"{{ 'client/login'|alink }}/{{client.id}}\" class=\"btnIconLeft mr10 mt5\" target=\"_blank\"><img src=\"images/icons/dark/adminUser.png\" alt=\"\" class=\"icon\"><span>Login to client area</span></a>
                            <a href=\"{{ 'api/admin/client/delete'|link({'id' : client.id}) }}\" data-api-confirm=\"Are you sure?\" data-api-redirect=\"{{ 'client'|alink }}\" class=\"btnIconLeft mr10 mt5 api-link\" ><img src=\"images/icons/dark/trash.png\" alt=\"\" class=\"icon\"><span>Delete</span></a>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-profile\">

            <div class=\"help\">
                <h3>{% trans 'Client profile details' %}</h3>
            </div>

            <form method=\"post\" action=\"{{ 'api/admin/client/update'|link }}\" class=\"mainForm api-form save\" data-api-msg=\"{% trans 'Client Profile updated' %}\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>{% trans 'Status' %}:</label>
                        <div class=\"formRight noborder\">
                            <input type=\"radio\" name=\"status\" value=\"active\"{% if client.status == 'active' %} checked=\"checked\"{% endif %}/><label>{% trans 'Active' %}</label>
                            <input type=\"radio\" name=\"status\" value=\"suspended\"{% if client.status == 'suspended' %} checked=\"checked\"{% endif %} /><label>{% trans 'Suspended' %}</label>
                            <input type=\"radio\" name=\"status\" value=\"canceled\"{% if client.status == 'canceled' %} checked=\"checked\"{% endif %} /><label>{% trans 'Canceled' %}</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Type' %}:</label>
                        <div class=\"formRight noborder\">
                            <input type=\"radio\" name=\"type\" value=\"individual\"{% if client.type == 'individual' %} checked=\"checked\"{% endif %}/><label>{% trans 'Individual' %}</label>
                            <input type=\"radio\" name=\"type\" value=\"company\"{% if client.type == 'company' %} checked=\"checked\"{% endif %} /><label>{% trans 'Company' %}</label>
                            <input type=\"radio\" name=\"type\" value=\"\"{% if not client.type %} checked=\"checked\"{% endif %} /><label>{% trans 'Other/Unknown' %}</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Mail approved' %}:</label>
                        <div class=\"formRight noborder\">
                            <input type=\"radio\" name=\"email_approved\" value=\"1\"{% if client.email_approved == 1 %} checked=\"checked\"{% endif %}/><label>{% trans 'Yes' %}</label>
                            <input type=\"radio\" name=\"email_approved\" value=\"0\"{% if client.email_approved != 1 %} checked=\"checked\"{% endif %} /><label>{% trans 'No' %}</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>



                    <div class=\"rowElem\">
                        <label>{% trans 'Group' %}:</label>
                        <div class=\"formRight\">
                            {{ mf.selectbox('group_id', admin.client_group_get_pairs, client.group_id, 0, 'Select group') }}
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Email' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"email\" value=\"{{client.email}}\" required=\"required\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Name' %}:</label>
                        <div class=\"formRight moreFields\">
                            <ul>
                                <li style=\"width: 250px\"><input type=\"text\" name=\"first_name\" value=\"{{client.first_name}}\" required=\"required\"/></li>
                                <li class=\"sep\"></li>
                                <li style=\"width: 245px\"><input type=\"text\" name=\"last_name\" value=\"{{client.last_name}}\" /></li>
                            </ul>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Date of birth' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"birthday\" value=\"{{client.birthday}}\" />
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\" id=\"company-details\">
                        <label>{% trans 'Company details' %}:</label>
                        <div class=\"formRight moreFields\">
                            <ul>
                                <li style=\"width: 170px\"><input type=\"text\" name=\"company\" value=\"{{client.company}}\" title=\"Company name\" placeholder=\"Company name\"/></li>
                                <li class=\"sep\"></li>
                                <li style=\"width: 150px\"><input type=\"text\" name=\"company_vat\" value=\"{{client.company_vat}}\" title=\"Company VAT number\" placeholder=\"Company VAT number\"/></li>
                                <li class=\"sep\"></li>
                                <li style=\"width: 150px\"><input type=\"text\" name=\"company_number\" value=\"{{client.company_number}}\"  title=\"Company number\" placeholder=\"Company number\"/></li>
                                <li class=\"sep\"></li>
                            </ul>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <input type=\"submit\" value=\"{% trans 'Update' %}\" class=\"greyishBtn submitForm\" />
                </fieldset>

                <fieldset>
                    <legend>{% trans 'Address and contact details' %}</legend>
                    <div class=\"rowElem\">
                        <label>{% trans 'Address Line 1' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"address_1\" value=\"{{client.address_1}}\" />
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Address Line 2' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"address_2\" value=\"{{client.address_2}}\" />
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Country' %}:</label>
                        <div class=\"formRight\">
                            {{ mf.selectbox('country', guest.system_countries, client.country, 0, 'Select country') }}
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'State' %}:</label>
                        <div class=\"formRight\">
                            {# mf.selectbox('state', guest.system_states, client.state, 0, 'Select state') #}
                            <input type=\"text\" name=\"state\" value=\"{{ client.state }}\" />
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'City' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"city\" value=\"{{client.city}}\" />
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Postcode' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"postcode\" value=\"{{client.postcode}}\" />
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Phone' %}:</label>
                        <div class=\"formRight moreFields\">
                            <ul>
                                <li><input type=\"text\" name=\"phone_cc\" value=\"{{client.phone_cc}}\" /></li>
                                <li class=\"sep\"></li>
                                <li style=\"width: 200px;\"><input type=\"text\" name=\"phone\" value=\"{{client.phone}}\" /></li>
                            </ul>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Passport number' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"document_nr\" value=\"{{client.document_nr}}\" />
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <input type=\"submit\" value=\"{% trans 'Update' %}\" class=\"greyishBtn submitForm\" />
                </fieldset>

                <fieldset>
                    <legend>{% trans 'Additional settings' %}</legend>
                    <div class=\"rowElem\">
                        <label>{% trans 'Alternative ID' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"aid\" value=\"{{client.aid}}\" placeholder=\"{% trans 'Used to identify client on foreign system. Usually used by importers' %}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Currency' %}:</label>
                        <div class=\"formRight\">
                            {{ mf.selectbox('currency', guest.currency_get_pairs, client.currency, 0, 'Select currency') }}
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Exempt from tax' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"radio\" name=\"tax_exempt\" value=\"1\"{% if client.tax_exempt %} checked=\"checked\"{% endif %}/><label>Yes</label>
                            <input type=\"radio\" name=\"tax_exempt\" value=\"0\"{% if not client.tax_exempt %} checked=\"checked\"{% endif %} /><label>No</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Signed up time' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"created_at\" value=\"{{client.created_at|date('Y-m-d')}}\" />
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Notes' %}:</label>
                        <div class=\"formRight\">
                            <textarea name=\"notes\" cols=\"5\" rows=\"5\">{{client.notes}}</textarea>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <input type=\"submit\" value=\"{% trans 'Update profile' %}\" class=\"greyishBtn submitForm\" />
                    <input type=\"hidden\" name=\"id\" value=\"{{ client.id }}\"/>
                </fieldset>
            </form>

            <div class=\"help\">
                <h3>{% trans 'Change password' %}</h3>
            </div>
            <form method=\"post\" action=\"{{ 'api/admin/client/change_password'|link }}\" class=\"mainForm api-form\" data-api-msg=\"{% trans 'Password changed' %}\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>{% trans 'Password' %}</label>
                        <div class=\"formRight\">
                            <input type=\"password\" name=\"password\" value=\"\" required=\"required\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Password confirm' %}</label>
                        <div class=\"formRight\">
                            <input type=\"password\" name=\"password_confirm\" value=\"\" required=\"required\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <input type=\"submit\" value=\"{% trans 'Change password' %}\" class=\"greyishBtn submitForm\" />
                    <input type=\"hidden\" name=\"id\" value=\"{{ client.id }}\"/>
                </fieldset>
            </form>

            <div class=\"help\">
                <h3>{% trans 'Custom fields' %}</h3>
                <p>{% trans 'Use these fields to define custom client details' %}</p>
            </div>

            <form method=\"post\" action=\"{{ 'api/admin/client/update'|link }}\" class=\"mainForm api-form save\" data-api-msg=\"{% trans 'Client Profile custom fields updated' %}\">
                <fieldset>
                    {% for i in 1..10 %}
                    {% set fn = 'custom_'~i %}
                    <div class=\"rowElem{% if loop.first%} noborder{% endif %}\">
                        <label>{% trans 'Custom field' %} {{i}}</label>
                        <div class=\"formRight\">
                            <textarea name=\"custom_{{i}}\" cols=\"5\" rows=\"5\">{{client[fn]}}</textarea>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    {% endfor %}

                    <input type=\"submit\" value=\"{% trans 'Update' %}\" class=\"greyishBtn submitForm\" />
                    <input type=\"hidden\" name=\"id\" value=\"{{ client.id }}\"/>
                </fieldset>
            </form>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-balance\">
            <div class=\"help\">
                <h3>{% trans 'Client payments history' %}</h3>
            </div>

            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <th style=\"width: 15%\">{% trans 'Amount' %}</th>
                        <th>{% trans 'Description' %}</th>
                        <th style=\"width: 20%\">{% trans 'Date' %}</th>
                        <th style=\"width: 5%\">&nbsp;</th>
                    </tr>
                </thead>

                <tbody>
                    {% set payments = admin.client_balance_get_list({\"per_page\":20, \"client_id\":client.id}) %}
                    {% for i, tx in payments.list %}
                    <tr>
                        <td>{{mf.currency_format(tx.amount, tx.currency)}}</td>
                        <td>{{tx.description}}</td>
                        <td>{{tx.created_at|date(\"Y-m-d H:i\")}}</td>
                        <td>
                            <a class=\"bb-button btn14 bb-rm-tr api-link\" data-api-confirm=\"Are you sure?\" href=\"{{ 'api/admin/client/balance_delete'|link({'id' : tx.id}) }}\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                        </td>
                    </tr>
                    {% else %}
                    <tr>
                        <td colspan=\"4\">{% trans 'The list is empty' %}</td>
                    </tr>
                    {% endfor %}
                </tbody>

                <tfoot>
                    <tr>
                        <th class=\"currency\" colspan=\"4\">{% trans 'Balance' %}: {{ mf.currency_format(client.balance, client.currency) }}</th>
                    </tr>
                </tfoot>
            </table>

            <div class=\"help\">
                <h3>{% trans 'Add funds for client' %}</h3>
            </div>

            <form method=\"post\" action=\"{{ 'api/admin/client/balance_add_funds'|link }}\" class=\"mainForm api-form save\" data-api-msg=\"{% trans 'Funds added' %}\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>{% trans 'Amount' %}:</label>
                        <div class=\"formRight noborder\">
                            <input type=\"text\" name=\"amount\" value=\"\" style=\"width: 100px;\" required=\"required\"/> {{ client.currency }}
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Description' %}:</label>
                        <div class=\"formRight noborder\">
                            <input type=\"text\" name=\"description\" value=\"\"  required=\"required\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <input type=\"submit\" value=\"{% trans 'Add funds' %}\" class=\"greyishBtn submitForm\" />
                    <input type=\"hidden\" name=\"id\" value=\"{{ client.id }}\"/>
                </fieldset>
            </form>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-orders\">
            <div class=\"help\">
                <h3>{% trans 'Client orders' %}</h3>
            </div>

            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td>ID</td>
                        <td width=\"40%\">Title</td>
                        <td width=\"20%\">Amount</td>
                        <td width=\"20%\">Period</td>
                        <td width=\"20%\">Status</td>
                        <td>&nbsp;</td>
                    </tr>
                </thead>
                <tbody>
                    {% set orders = admin.order_get_list({\"per_page\":\"20\", \"client_id\":client.id}) %}
                    {% for order in orders.list %}
                    <tr>
                        <td>{{order.id}}</td>
                        <td>{{order.title|truncate(30) }}</td>
                        <td>{{ mf.currency_format( order.total, order.currency) }}</td>
                        <td>{{ mf.period_name( order.period) }}</td>
                        <td>{{ mf.status_name(order.status) }}</td>
                        <td class=\"actions\"><a class=\"bb-button btn14\" href=\"{{ '/order/manage'|alink }}/{{order.id}}\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a></td>
                    </tr>
                    {% else %}
                    <tr>
                        <td colspan=\"6\">{% trans 'The list is empty' %}</td>
                    </tr>
                    {% endfor %}
                </tbody>

                <tfoot>
                    <tr>
                        <td colspan=\"6\">
                            <a href=\"{{ 'order'|alink({'client_id' : client.id})}}#tab-new\" class=\"btnIconLeft mr10 mt5\"><img src=\"images/icons/dark/money.png\" alt=\"\" class=\"icon\"><span>New order</span></a>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-invoice\">
            <div class=\"help\">
                <h3>{% trans 'Client invoices' %}</h3>
            </div>

            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td>#</td>
                        <td width=\"15%\">Amount</td>
                        <td width=\"30%\">Issued at</td>
                        <td width=\"30%\">Paid at</td>
                        <td width=\"15%\">Status</td>
                        <td>&nbsp;</td>
                    </tr>
                </thead>
                <tbody>
                    {% set invoices = admin.invoice_get_list({\"per_page\":\"100\", \"client_id\":client.id}) %}
                    {% for invoice in invoices.list %}
                    <tr>
                        <td>{{invoice.serie_nr}}</td>
                        <td>{{ mf.currency_format( invoice.total, invoice.currency) }}</td>
                        <td>{{ invoice.created_at|date('Y-m-d') }}</td>
                        <td>{% if invoice.paid_at %}{{ invoice.paid_at|date('Y-m-d') }}{% else %}-{% endif %}</td>
                        <td>{{ mf.status_name(invoice.status) }}</td>
                        <td class=\"actions\"><a class=\"bb-button btn14\" href=\"{{ '/invoice/manage'|alink }}/{{invoice.id}}\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a></td>
                    </tr>
                    {% else %}
                    <tr>
                        <td colspan=\"6\">{% trans 'The list is empty' %}</td>
                    </tr>
                    {% endfor %}
                </tbody>

                <tfoot>
                    <tr>
                        <td colspan=\"6\">
                            <a href=\"{{ 'api/admin/invoice/prepare'|link({'client_id' : client.id}) }}\" class=\"btnIconLeft mr10 mt5 api-link\" data-api-jsonp=\"onAfterInvoicePrepared\" ><img src=\"images/icons/dark/money.png\" alt=\"\" class=\"icon\"><span>New invoice</span></a>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-support\">
            <div class=\"help\">
                <h3>{% trans 'Client support tickets' %}</h3>
            </div>
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
                    {% set tickets = admin.support_ticket_get_list({\"per_page\":\"20\", \"client_id\":client.id}) %}
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
                            <a href=\"{{ 'support'|alink({'client_id' : client.id})}}#tab-new\" class=\"btnIconLeft mr10 mt5\" ><img src=\"images/icons/dark/help.png\" alt=\"\" class=\"icon\"><span>New support ticket</span></a>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-history\">
            <div class=\"help\">
                <h3>{% trans 'Logins history' %}</h3>
            </div>

            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <th>{% trans 'IP' %}</th>
                        <th>{% trans 'Country' %}</th>
                        <th>{% trans 'Date' %}</th>
                    </tr>
                </thead>

                <tbody>
                    {% set logins = admin.client_login_history_get_list({\"per_page\":10, \"page\":request.page, \"client_id\":client.id}) %}
                    {% for i, login in logins.list %}
                    <tr>
                        <td>{{login.ip}}</td>
                        <td>{{login.ip|ipcountryname|default('Unknown')}}</td>
                        <td>{{login.created_at|date('l, d F Y')}}</td>
                    </tr>
                    {% else %}
                    <tr>
                        <td colspan=\"3\">{% trans 'The list is empty' %}</td>
                    </tr>
                    {% endfor %}
                </tbody>
            </table>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-emails\">
            <div class=\"help\">
                <h3>{% trans 'Emails sent to client' %}</h3>
            </div>

            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <th>{% trans 'Email subject' %}</th>
                        <th>{% trans 'To' %}</th>
                        <th>{% trans 'Date sent' %}</th>
                        <th style=\"width: 5%\">&nbsp;</th>
                    </tr>
                </thead>

                <tbody>
                    {% set emails = admin.email_email_get_list({\"per_page\":\"20\", \"client_id\":client.id}) %}
                    {% for i, email in emails.list %}
                    <tr>
                        <td>{{email.subject}}</td>
                        <td>{{ email.recipients }}</td>
                        <td>{{email.created_at|date('l, d F Y')}}</td>
                        <td class=\"actions\"><a class=\"bb-button btn14\" href=\"{{ 'email'|alink }}/{{email.id}}\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a></td>
                    </tr>
                    {% else %}
                    <tr>
                        <td colspan=\"4\">{% trans 'The list is empty' %}</td>
                    </tr>
                    {% endfor %}
                </tbody>
            </table>
            {% include \"partial_pagination.phtml\" with {'list': emails} %}
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-transactions\">
            <div class=\"help\">
                <h3>{% trans 'Transactions received' %}</h3>
            </div>

            {% set transactions = admin.invoice_transaction_get_list({\"client_id\":client.id,\"per_page\":100 }).list %}
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
                {% else %}
                    <tr>
                        <td colspan=\"7\">{% trans 'The list is empty' %}</td>
                    </tr>
                {% endfor %}
                </tbody>
            </table>
        </div>
    </div>
</div>


{#
<div class=\"widgets\">
    <div class=\"left\">
        <div class=\"widget\">
            <div class=\"head\">
                <h5 class=\"iDayCalendar\">{{ client.first_name }} {{ client.last_name }}</h5>
                <div class=\"num\"><span>Balance:</span><a href=\"#\" class=\"blueNum\">{{ mf.currency_format(client.balance, client.currency) }}</a></div>
            </div>
            <table class=\"tableStatic wide\">
                <tbody>
                    <tr class=\"noborder\">
                        <td>Company:</td>
                        <td><strong class=\"red\">{{ client.company }}</strong></td>
                    </tr>
                    <tr>
                        <td>Registered at:</td>
                        <td>{{ client.created_at|date('M d, Y') }}</td>
                    </tr>
                    <tr>
                        <td>Email:</td>
                        <td><a href=\"#\" title=\"\">{{ client.email }}</a></td>
                    </tr>
                    <tr>
                        <td>Status:</td>
                        <td><a href=\"#\" class=\"green\">{{ mf.status_name(client.status) }}</a></td>
                    </tr>
                    <tr>
                        <td>IP:</td>
                        <td><span class=\"expire\">{{ client.ip }} {{ client.ip|ipcountryname|default('Unknown') }}</span></td>
                    </tr>
                    <tr>
                        <td>Address:</td>
                        <td>{{ client.address_1 }} {{ client.address_2 }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class=\"right\">

        <div class=\"widget\">
            <div class=\"head\"><h5 class=\"iMoney\">2 Latest orders</h5><div class=\"num\"><span>Total:</span><a href=\"{{ 'order'|alink({'client_id' : client.id}) }}\" class=\"greenNum\">{{ orders.total }}</a></div></div>

            {% set orders = admin.order_get_list({\"per_page\":\"2\", \"client_id\":client.id}) %}
            {% for order in orders.list %}
            <div class=\"supTicket{% if loop.first %} nobg{% endif %}\">
                <div class=\"issueType\">
                    <span class=\"issueInfo\"><a href=\"{{ 'order/manage'|alink }}/{{ order.id }}\" title=\"\">{{ order.title|truncate(45) }}</a></span>
                    <span class=\"issueNum\"><a href=\"{{ 'order/manage'|alink }}/{{ order.id }}\" title=\"\">[ {{ order.id }} ]</a></span>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"issueSummary\">
                    <a href=\"{{ 'order/manage'|alink }}/{{ order.id }}\" title=\"\" class=\"floatleft\"><img src=\"{{ order.client.email|gravatar }}?size=35\" alt=\"\"></a>
                    <div class=\"ticketInfo\">
                        <ul>
                            <li>Current order status:</li>
                            <li class=\"even\"><strong class=\"green\">[ {{ order.status }} ]</strong></li>
                            <li>User email:</li>
                            <li class=\"even\"><a href=\"{{ 'client/manage'|alink }}/{{ order.client_id }}\" title=\"\">{{ order.client.email }}</a></li>
                        </ul>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"fix\"></div>
                </div>
            </div>
            {% else %}
            <div class=\"supTicket{% if loop.first %} nobg{% endif %}\">
                The list is empty
            </div>
            {% endfor %}
        </div>
    </div>
    <div class=\"fix\"></div>
</div>
#}

{% endblock %}


{% block js%}
<script type=\"text/javascript\">
\$(function() {

});

function onAfterInvoicePrepared(id) {
    bb.redirect(\"{{'invoice/manage/'|alink}}/\"+id);
}
</script>
{% endblock %}
", "mod_client_manage.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-themes/admin_default/html/mod_client_manage.phtml");
    }
}
