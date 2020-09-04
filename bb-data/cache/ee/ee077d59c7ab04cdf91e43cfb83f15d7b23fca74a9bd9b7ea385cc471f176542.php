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

/* mod_servicehosting_manage.phtml */
class __TwigTemplate_db442b9455adab8607916f60c974443e1a413d643cbe4ffb5e39951588c03947 extends \Twig\Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
        ];
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 1
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_servicehosting_manage.phtml", 1)->unwrap();
        // line 2
        $context["server"] = twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "server", [], "any", false, false, false, 2);
        // line 3
        $context["hp"] = twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "hosting_plan", [], "any", false, false, false, 3);
        // line 4
        echo "
<div class=\"help\">
    <h2>";
        // line 6
        echo gettext("Details");
        echo "</h2>
</div>
<table class=\"tableStatic wide\">
    <tbody>
        <tr class=\"noborder\">
            <td style=\"width: 30%;\">";
        // line 11
        echo gettext("Status");
        echo ":</td>
            <td>";
        // line 12
        echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "status", [], "any", false, false, false, 12)], 12, $context, $this->getSourceContext());
        echo "</td>
        </tr>

        <tr>
            <td>";
        // line 16
        echo gettext("Domain");
        echo ":</td>
            <td>
                <a target=\"_blank\" href=\"http://";
        // line 18
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "domain", [], "any", false, false, false, 18), "html", null, true);
        echo "\">";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "domain", [], "any", false, false, false, 18), "html", null, true);
        echo "</a>
            </td>
        </tr>

        <tr>
            <td>";
        // line 23
        echo gettext("Server Name");
        echo ":</td>
            <td><a href=\"";
        // line 24
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("servicehosting/server");
        echo "/";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["server"] ?? null), "id", [], "any", false, false, false, 24), "html", null, true);
        echo "\">";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["server"] ?? null), "name", [], "any", false, false, false, 24), "html", null, true);
        echo "</a></td>
        </tr>

        <tr>
            <td>";
        // line 28
        echo gettext("Hosting plan");
        echo ":</td>
            <td><a href=\"";
        // line 29
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("servicehosting/plan");
        echo "/";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["hp"] ?? null), "id", [], "any", false, false, false, 29), "html", null, true);
        echo "\">";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["hp"] ?? null), "name", [], "any", false, false, false, 29), "html", null, true);
        echo "</a></td>
        </tr>

        <tr>
            <td>";
        // line 33
        echo gettext("Server IP");
        echo ":</td>
            <td>";
        // line 34
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["server"] ?? null), "ip", [], "any", false, false, false, 34), "html", null, true);
        echo "</td>
        </tr>

        <tr>
            <td>";
        // line 38
        echo gettext("Account IP");
        echo ":</td>
            <td>";
        // line 39
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "ip", [], "any", false, false, false, 39), "html", null, true);
        echo "</td>
        </tr>

        <tr>
            <td>";
        // line 43
        echo gettext("Username");
        echo ":</td>
            <td>";
        // line 44
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "username", [], "any", false, false, false, 44), "html", null, true);
        echo "</td>
        </tr>

        <tr>
            <td>";
        // line 48
        echo gettext("Nameserver 1");
        echo ":</td>
            <td>";
        // line 49
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["server"] ?? null), "ns1", [], "any", false, false, false, 49), "html", null, true);
        echo "</td>
        </tr>

        <tr>
            <td>";
        // line 53
        echo gettext("Nameserver 2");
        echo ":</td>
            <td>";
        // line 54
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["server"] ?? null), "ns2", [], "any", false, false, false, 54), "html", null, true);
        echo "</td>
        </tr>

        ";
        // line 57
        if (twig_get_attribute($this->env, $this->source, ($context["server"] ?? null), "ns3", [], "any", false, false, false, 57)) {
            // line 58
            echo "        <tr>
            <td>";
            // line 59
            echo gettext("Nameserver 3");
            echo ":</td>
            <td>";
            // line 60
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["server"] ?? null), "ns3", [], "any", false, false, false, 60), "html", null, true);
            echo "</td>
        </tr>
        ";
        }
        // line 63
        echo "
        ";
        // line 64
        if (twig_get_attribute($this->env, $this->source, ($context["server"] ?? null), "ns4", [], "any", false, false, false, 64)) {
            // line 65
            echo "        <tr>
            <td>";
            // line 66
            echo gettext("Nameserver 4");
            echo ":</td>
            <td>";
            // line 67
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["server"] ?? null), "ns4", [], "any", false, false, false, 67), "html", null, true);
            echo "</td>
        </tr>
        ";
        }
        // line 70
        echo "
        <tr>
            <td>";
        // line 72
        echo gettext("Bandwidth");
        echo ":</td>
            <td>";
        // line 73
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["hp"] ?? null), "bandwidth", [], "any", false, false, false, 73), "html", null, true);
        echo " MB / ";
        echo gettext("per month");
        echo "</td>
        </tr>
        <tr>
            <td>";
        // line 76
        echo gettext("Disk quota");
        echo ":</td>
            <td>";
        // line 77
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["hp"] ?? null), "quota", [], "any", false, false, false, 77), "html", null, true);
        echo " MB</td>
        </tr>

    </tbody>
    <tfoot>
        <tr>
            <td colspan=\"2\">
                <div class=\"aligncenter\">
                    ";
        // line 85
        echo twig_escape_filter($this->env, ($context["order_actions"] ?? null), "html", null, true);
        echo "
                    <a class=\"btn55 mr10\" href=\"";
        // line 86
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["server"] ?? null), "cpanel_url", [], "any", false, false, false, 86), "html", null, true);
        echo "\" target=\"_blank\"><img src=\"images/icons/middlenav/linux.png\" alt=\"\"><span>";
        echo gettext("Jump to cPanel");
        echo "</span></a>
                    ";
        // line 87
        if (twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "reseller", [], "any", false, false, false, 87)) {
            // line 88
            echo "                    <a class=\"btn55 mr10\" href=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["server"] ?? null), "reseller_cpanel_url", [], "any", false, false, false, 88), "html", null, true);
            echo "\" target=\"_blank\"><img src=\"images/icons/middlenav/linux.png\" alt=\"\"><span>";
            echo gettext("Reseller control panel");
            echo "</span></a>
                    ";
        }
        // line 90
        echo "
                    <a href=\"";
        // line 91
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/servicehosting/sync", ["order_id" => twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "id", [], "any", false, false, false, 91)]);
        echo "\" data-api-confirm=\"Are you sure?\" data-api-msg=\"Account was synced\" class=\"btn55 mr10 api-link\"><img src=\"images/icons/middlenav/transfer.png\" alt=\"\"><span>Sync with server</span></a>
                </div>
            </td>
        </tr>
    </tfoot>
</table>

<div class=\"help\">
    <h2>";
        // line 99
        echo gettext("Change hosting plan");
        echo "</h2>
</div>

<form action=\"";
        // line 102
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/servicehosting/change_plan");
        echo "\" method=\"post\" class=\"mainForm api-form save\" data-api-msg=\"";
        echo gettext("Hosting plan changed");
        echo "\">
<fieldset>
    <div class=\"rowElem noborder\">
        <label>";
        // line 105
        echo gettext("New hosting plan");
        echo ":</label>
        <div class=\"formRight\">
            ";
        // line 107
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["plan_id", twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "servicehosting_hp_get_pairs", [], "any", false, false, false, 107), twig_get_attribute($this->env, $this->source, ($context["hp"] ?? null), "id", [], "any", false, false, false, 107), 1], 107, $context, $this->getSourceContext());
        echo "
        </div>
        <div class=\"fix\"></div>
    </div>

    <input type=\"hidden\" name=\"order_id\" value=\"";
        // line 112
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "id", [], "any", false, false, false, 112), "html", null, true);
        echo "\">
    <input type=\"submit\" value=\"";
        // line 113
        echo gettext("Change");
        echo "\" class=\"greyishBtn submitForm\" />
</fieldset>
</form>

<div class=\"help\">
    <h2>";
        // line 118
        echo gettext("Change account password");
        echo "</h2>
</div>

<form action=\"";
        // line 121
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/servicehosting/change_password");
        echo "\" method=\"post\" class=\"mainForm api-form save\" data-api-msg=\"";
        echo gettext("Account password changed");
        echo "\">
<fieldset>
    <div class=\"rowElem noborder\">
        <label>";
        // line 124
        echo gettext("Password");
        echo ":</label>
        <div class=\"formRight\">
            <input type=\"password\" name=\"password\" required=\"required\"/>
        </div>
        <div class=\"fix\"></div>
    </div>
    <div class=\"rowElem\">
        <label>";
        // line 131
        echo gettext("Password Confirm");
        echo ":</label>
        <div class=\"formRight\">
            <input type=\"password\" name=\"password_confirm\"  required=\"required\"/>
        </div>
        <div class=\"fix\"></div>
    </div>

    <input type=\"hidden\" name=\"order_id\" value=\"";
        // line 138
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "id", [], "any", false, false, false, 138), "html", null, true);
        echo "\">
    <input type=\"submit\" value=\"";
        // line 139
        echo gettext("Change");
        echo "\" class=\"greyishBtn submitForm\" />
</fieldset>
</form>

<div class=\"help\">
    <h2>";
        // line 144
        echo gettext("Change IP");
        echo "</h2>
</div>

<form method=\"post\" action=\"";
        // line 147
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/servicehosting/change_ip");
        echo "\" class=\"mainForm api-form save\" data-api-msg=\"";
        echo gettext("Account IP changed");
        echo "\">
<fieldset>
    <div class=\"rowElem\">
        <label>";
        // line 150
        echo gettext("IP");
        echo ": </label>
        <div class=\"formRight\">
            <input type=\"text\" name=\"ip\" value=\"";
        // line 152
        echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ip", [], "any", true, true, false, 152)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ip", [], "any", false, false, false, 152), twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "ip", [], "any", false, false, false, 152))) : (twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "ip", [], "any", false, false, false, 152))), "html", null, true);
        echo "\" required=\"required\">
        </div>
        <div class=\"fix\"></div>
    </div>

    <input type=\"hidden\" name=\"order_id\" value=\"";
        // line 157
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "id", [], "any", false, false, false, 157), "html", null, true);
        echo "\">
    <input class=\"greyishBtn submitForm\" type=\"submit\" value=\"";
        // line 158
        echo gettext("Change");
        echo "\">
</fieldset>
</form>

<div class=\"help\">
    <h2>";
        // line 163
        echo gettext("Change username");
        echo "</h2>
</div>

<form method=\"post\" action=\"";
        // line 166
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/servicehosting/change_username");
        echo "\" class=\"mainForm api-form save\" data-api-msg=\"";
        echo gettext("Account username changed");
        echo "\">
<fieldset>
    <div class=\"rowElem\">
        <label>";
        // line 169
        echo gettext("Username");
        echo ": </label>
        <div class=\"formRight\">
            <input type=\"text\" name=\"username\" value=\"";
        // line 171
        echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "username", [], "any", true, true, false, 171)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "username", [], "any", false, false, false, 171), twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "username", [], "any", false, false, false, 171))) : (twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "username", [], "any", false, false, false, 171))), "html", null, true);
        echo "\" required=\"required\">
        </div>
        <div class=\"fix\"></div>
    </div>

    <input type=\"hidden\" name=\"order_id\" value=\"";
        // line 176
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "id", [], "any", false, false, false, 176), "html", null, true);
        echo "\">
    <input class=\"greyishBtn submitForm\" type=\"submit\" value=\"";
        // line 177
        echo gettext("Change");
        echo "\">
</fieldset>
</form>

<div class=\"help\">
    <h2>";
        // line 182
        echo gettext("Change domain");
        echo "</h2>
</div>
<form method=\"post\" action=\"";
        // line 184
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/servicehosting/change_domain");
        echo "\" class=\"mainForm api-form save\" data-api-msg=\"";
        echo gettext("Account domain changed");
        echo "\">
<fieldset>
    <div class=\"rowElem\">
        <label>";
        // line 187
        echo gettext("Domain");
        echo ": </label>
        <div class=\"formRight moreFields\">
            <ul>
                <li style=\"width: 200px\"><input type=\"text\" name=\"sld\" value=\"";
        // line 190
        echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "domain", [], "any", true, true, false, 190)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "domain", [], "any", false, false, false, 190), twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "sld", [], "any", false, false, false, 190))) : (twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "sld", [], "any", false, false, false, 190))), "html", null, true);
        echo "\" required=\"required\"></li>
                <li class=\"sep\">-</li>
                <li style=\"width: 100px\"><input type=\"text\" name=\"tld\" value=\"";
        // line 192
        echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "domain", [], "any", true, true, false, 192)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "domain", [], "any", false, false, false, 192), twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "tld", [], "any", false, false, false, 192))) : (twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "tld", [], "any", false, false, false, 192))), "html", null, true);
        echo "\" required=\"required\"></li>
            </ul>
        </div>

        <div class=\"fix\"></div>
    </div>

    <input type=\"hidden\" name=\"order_id\" value=\"";
        // line 199
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "id", [], "any", false, false, false, 199), "html", null, true);
        echo "\">
    <input class=\"greyishBtn submitForm\" type=\"submit\" value=\"";
        // line 200
        echo gettext("Change");
        echo "\">
</fieldset>
</form>";
    }

    public function getTemplateName()
    {
        return "mod_servicehosting_manage.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  464 => 200,  460 => 199,  450 => 192,  445 => 190,  439 => 187,  431 => 184,  426 => 182,  418 => 177,  414 => 176,  406 => 171,  401 => 169,  393 => 166,  387 => 163,  379 => 158,  375 => 157,  367 => 152,  362 => 150,  354 => 147,  348 => 144,  340 => 139,  336 => 138,  326 => 131,  316 => 124,  308 => 121,  302 => 118,  294 => 113,  290 => 112,  282 => 107,  277 => 105,  269 => 102,  263 => 99,  252 => 91,  249 => 90,  241 => 88,  239 => 87,  233 => 86,  229 => 85,  218 => 77,  214 => 76,  206 => 73,  202 => 72,  198 => 70,  192 => 67,  188 => 66,  185 => 65,  183 => 64,  180 => 63,  174 => 60,  170 => 59,  167 => 58,  165 => 57,  159 => 54,  155 => 53,  148 => 49,  144 => 48,  137 => 44,  133 => 43,  126 => 39,  122 => 38,  115 => 34,  111 => 33,  100 => 29,  96 => 28,  85 => 24,  81 => 23,  71 => 18,  66 => 16,  59 => 12,  55 => 11,  47 => 6,  43 => 4,  41 => 3,  39 => 2,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% import \"macro_functions.phtml\" as mf %}
{% set server = service.server %}
{% set hp = service.hosting_plan %}

<div class=\"help\">
    <h2>{% trans 'Details' %}</h2>
</div>
<table class=\"tableStatic wide\">
    <tbody>
        <tr class=\"noborder\">
            <td style=\"width: 30%;\">{% trans 'Status' %}:</td>
            <td>{{mf.status_name(order.status)}}</td>
        </tr>

        <tr>
            <td>{% trans 'Domain' %}:</td>
            <td>
                <a target=\"_blank\" href=\"http://{{ service.domain }}\">{{ service.domain }}</a>
            </td>
        </tr>

        <tr>
            <td>{% trans 'Server Name' %}:</td>
            <td><a href=\"{{ 'servicehosting/server'|alink }}/{{ server.id }}\">{{ server.name }}</a></td>
        </tr>

        <tr>
            <td>{% trans 'Hosting plan' %}:</td>
            <td><a href=\"{{ 'servicehosting/plan'|alink }}/{{ hp.id }}\">{{ hp.name }}</a></td>
        </tr>

        <tr>
            <td>{% trans 'Server IP' %}:</td>
            <td>{{ server.ip }}</td>
        </tr>

        <tr>
            <td>{% trans 'Account IP' %}:</td>
            <td>{{ service.ip }}</td>
        </tr>

        <tr>
            <td>{% trans 'Username' %}:</td>
            <td>{{ service.username }}</td>
        </tr>

        <tr>
            <td>{% trans 'Nameserver 1' %}:</td>
            <td>{{ server.ns1 }}</td>
        </tr>

        <tr>
            <td>{% trans 'Nameserver 2' %}:</td>
            <td>{{ server.ns2 }}</td>
        </tr>

        {% if server.ns3 %}
        <tr>
            <td>{% trans 'Nameserver 3' %}:</td>
            <td>{{ server.ns3 }}</td>
        </tr>
        {% endif %}

        {% if server.ns4 %}
        <tr>
            <td>{% trans 'Nameserver 4' %}:</td>
            <td>{{ server.ns4 }}</td>
        </tr>
        {% endif %}

        <tr>
            <td>{% trans 'Bandwidth' %}:</td>
            <td>{{ hp.bandwidth }} MB / {% trans 'per month' %}</td>
        </tr>
        <tr>
            <td>{% trans 'Disk quota' %}:</td>
            <td>{{ hp.quota }} MB</td>
        </tr>

    </tbody>
    <tfoot>
        <tr>
            <td colspan=\"2\">
                <div class=\"aligncenter\">
                    {{ order_actions }}
                    <a class=\"btn55 mr10\" href=\"{{ server.cpanel_url }}\" target=\"_blank\"><img src=\"images/icons/middlenav/linux.png\" alt=\"\"><span>{% trans 'Jump to cPanel' %}</span></a>
                    {% if service.reseller %}
                    <a class=\"btn55 mr10\" href=\"{{ server.reseller_cpanel_url }}\" target=\"_blank\"><img src=\"images/icons/middlenav/linux.png\" alt=\"\"><span>{% trans 'Reseller control panel' %}</span></a>
                    {% endif %}

                    <a href=\"{{ 'api/admin/servicehosting/sync'|link({'order_id' : order.id }) }}\" data-api-confirm=\"Are you sure?\" data-api-msg=\"Account was synced\" class=\"btn55 mr10 api-link\"><img src=\"images/icons/middlenav/transfer.png\" alt=\"\"><span>Sync with server</span></a>
                </div>
            </td>
        </tr>
    </tfoot>
</table>

<div class=\"help\">
    <h2>{% trans 'Change hosting plan' %}</h2>
</div>

<form action=\"{{ 'api/admin/servicehosting/change_plan'|link }}\" method=\"post\" class=\"mainForm api-form save\" data-api-msg=\"{% trans 'Hosting plan changed' %}\">
<fieldset>
    <div class=\"rowElem noborder\">
        <label>{% trans 'New hosting plan' %}:</label>
        <div class=\"formRight\">
            {{ mf.selectbox('plan_id', admin.servicehosting_hp_get_pairs, hp.id, 1) }}
        </div>
        <div class=\"fix\"></div>
    </div>

    <input type=\"hidden\" name=\"order_id\" value=\"{{ order.id }}\">
    <input type=\"submit\" value=\"{% trans 'Change' %}\" class=\"greyishBtn submitForm\" />
</fieldset>
</form>

<div class=\"help\">
    <h2>{% trans 'Change account password' %}</h2>
</div>

<form action=\"{{ 'api/admin/servicehosting/change_password'|link }}\" method=\"post\" class=\"mainForm api-form save\" data-api-msg=\"{% trans 'Account password changed' %}\">
<fieldset>
    <div class=\"rowElem noborder\">
        <label>{% trans 'Password' %}:</label>
        <div class=\"formRight\">
            <input type=\"password\" name=\"password\" required=\"required\"/>
        </div>
        <div class=\"fix\"></div>
    </div>
    <div class=\"rowElem\">
        <label>{% trans 'Password Confirm' %}:</label>
        <div class=\"formRight\">
            <input type=\"password\" name=\"password_confirm\"  required=\"required\"/>
        </div>
        <div class=\"fix\"></div>
    </div>

    <input type=\"hidden\" name=\"order_id\" value=\"{{ order.id }}\">
    <input type=\"submit\" value=\"{% trans 'Change' %}\" class=\"greyishBtn submitForm\" />
</fieldset>
</form>

<div class=\"help\">
    <h2>{% trans 'Change IP' %}</h2>
</div>

<form method=\"post\" action=\"{{ 'api/admin/servicehosting/change_ip'|link }}\" class=\"mainForm api-form save\" data-api-msg=\"{% trans 'Account IP changed' %}\">
<fieldset>
    <div class=\"rowElem\">
        <label>{% trans 'IP' %}: </label>
        <div class=\"formRight\">
            <input type=\"text\" name=\"ip\" value=\"{{ request.ip|default(service.ip) }}\" required=\"required\">
        </div>
        <div class=\"fix\"></div>
    </div>

    <input type=\"hidden\" name=\"order_id\" value=\"{{ order.id }}\">
    <input class=\"greyishBtn submitForm\" type=\"submit\" value=\"{% trans 'Change' %}\">
</fieldset>
</form>

<div class=\"help\">
    <h2>{% trans 'Change username' %}</h2>
</div>

<form method=\"post\" action=\"{{ 'api/admin/servicehosting/change_username'|link }}\" class=\"mainForm api-form save\" data-api-msg=\"{% trans 'Account username changed' %}\">
<fieldset>
    <div class=\"rowElem\">
        <label>{% trans 'Username' %}: </label>
        <div class=\"formRight\">
            <input type=\"text\" name=\"username\" value=\"{{ request.username|default(service.username) }}\" required=\"required\">
        </div>
        <div class=\"fix\"></div>
    </div>

    <input type=\"hidden\" name=\"order_id\" value=\"{{ order.id }}\">
    <input class=\"greyishBtn submitForm\" type=\"submit\" value=\"{% trans 'Change' %}\">
</fieldset>
</form>

<div class=\"help\">
    <h2>{% trans 'Change domain' %}</h2>
</div>
<form method=\"post\" action=\"{{ 'api/admin/servicehosting/change_domain'|link }}\" class=\"mainForm api-form save\" data-api-msg=\"{% trans 'Account domain changed' %}\">
<fieldset>
    <div class=\"rowElem\">
        <label>{% trans 'Domain' %}: </label>
        <div class=\"formRight moreFields\">
            <ul>
                <li style=\"width: 200px\"><input type=\"text\" name=\"sld\" value=\"{{ request.domain|default(service.sld) }}\" required=\"required\"></li>
                <li class=\"sep\">-</li>
                <li style=\"width: 100px\"><input type=\"text\" name=\"tld\" value=\"{{ request.domain|default(service.tld) }}\" required=\"required\"></li>
            </ul>
        </div>

        <div class=\"fix\"></div>
    </div>

    <input type=\"hidden\" name=\"order_id\" value=\"{{ order.id }}\">
    <input class=\"greyishBtn submitForm\" type=\"submit\" value=\"{% trans 'Change' %}\">
</fieldset>
</form>", "mod_servicehosting_manage.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-themes/admin_default/html/mod_servicehosting_manage.phtml");
    }
}
