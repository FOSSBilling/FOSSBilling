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

/* mod_servicehosting_index.phtml */
class __TwigTemplate_328be5eea2a448ae37a6d1db1b79edc11b016b245e5cd0b5a040a79b90483abc extends \Twig\Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->blocks = [
            'meta_title' => [$this, 'block_meta_title'],
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
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_servicehosting_index.phtml", 1)->unwrap();
        // line 4
        $context["active_menu"] = "system";
        // line 2
        $this->parent = $this->loadTemplate("layout_default.phtml", "mod_servicehosting_index.phtml", 2);
        $this->parent->display($context, array_merge($this->blocks, $blocks));
    }

    // line 3
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo gettext("Hosting plans and servers");
    }

    // line 6
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 7
        echo "<div class=\"widget simpleTabs\">

    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">";
        // line 10
        echo gettext("Hosting plans and servers");
        echo "</a></li>
        <li><a href=\"#tab-new-server\">";
        // line 11
        echo gettext("New server");
        echo "</a></li>
        <li><a href=\"#tab-new-plan\">";
        // line 12
        echo gettext("New hosting plan");
        echo "</a></li>
    </ul>

    <div class=\"tabs_container\">
        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-index\">

            <div class=\"help\">
                <h5>";
        // line 20
        echo gettext("Servers");
        echo "</h5>
            </div>

            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <th>";
        // line 26
        echo gettext("Name");
        echo "</th>
                        <th>";
        // line 27
        echo gettext("IP");
        echo "</th>
                        <th>";
        // line 28
        echo gettext("Server manager");
        echo "</th>
                        <th>";
        // line 29
        echo gettext("Active");
        echo "</th>
                        <th style=\"width:22%\">&nbsp;</th>
                    </tr>
                </thead>
                <tbody>
                    ";
        // line 34
        $context["servers"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "servicehosting_server_get_list", [0 => ["per_page" => 100]], "method", false, false, false, 34);
        // line 35
        echo "                    ";
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["servers"] ?? null), "list", [], "any", false, false, false, 35));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["_key"] => $context["server"]) {
            // line 36
            echo "                    <tr>
                        <td>";
            // line 37
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["server"], "name", [], "any", false, false, false, 37), "html", null, true);
            echo "</td>
                        <td>";
            // line 38
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["server"], "ip", [], "any", false, false, false, 38), "html", null, true);
            echo "</td>
                        <td>";
            // line 39
            echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, $context["server"], "manager", [], "any", false, false, false, 39)], 39, $context, $this->getSourceContext());
            echo "</td>
                        <td>";
            // line 40
            echo twig_call_macro($macros["mf"], "macro_q", [twig_get_attribute($this->env, $this->source, $context["server"], "active", [], "any", false, false, false, 40)], 40, $context, $this->getSourceContext());
            echo "</td>
                        <td class=\"actions\">
                            <a class=\"bb-button btn14\" href=\"";
            // line 42
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["server"], "cpanel_url", [], "any", false, false, false, 42), "html", null, true);
            echo "\" target=\"_blank\"><img src=\"images/icons/dark/cog.png\" alt=\"\"></a>
                            <a class=\"bb-button btn14 api-link\" data-api-msg=\"Connected\" href=\"";
            // line 43
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/servicehosting/server_test_connection", ["id" => twig_get_attribute($this->env, $this->source, $context["server"], "id", [], "any", false, false, false, 43)]);
            echo "\" title=\"Test connection\"><img src=\"images/icons/dark/signal.png\" alt=\"\"></a>
                            <a class=\"bb-button btn14\" href=\"";
            // line 44
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/servicehosting/server");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["server"], "id", [], "any", false, false, false, 44), "html", null, true);
            echo "\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                            <a class=\"bb-button btn14 bb-rm-tr api-link\" data-api-confirm=\"Are you sure?\" data-api-redirect=\"";
            // line 45
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("servicehosting");
            echo "\" href=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/servicehosting/server_delete", ["id" => twig_get_attribute($this->env, $this->source, $context["server"], "id", [], "any", false, false, false, 45)]);
            echo "\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                        </td>
                    </tr>
                    ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 49
            echo "                    <tr>
                        <td colspan=\"7\">";
            // line 50
            echo gettext("The list is empty");
            echo "</td>
                    </tr>
                    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['server'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 53
        echo "                </tbody>
                <tfoot>
                    <tr>
                        <td colspan=\"7\"></td>
                    </tr>
                </tfoot>
            </table>

            <div class=\"help\">
                <h5>";
        // line 62
        echo gettext("Hosting plans");
        echo "</h5>
            </div>

            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td>";
        // line 68
        echo gettext("Title");
        echo "</td>
                        <td>";
        // line 69
        echo gettext("Addon domains");
        echo "</td>
                        <td>";
        // line 70
        echo gettext("Disk space");
        echo "</td>
                        <td>";
        // line 71
        echo gettext("Bandwidth");
        echo "</td>
                        <td style=\"width:13%\">&nbsp;</td>
                    </tr>
                </thead>
                
                <tbody>
                    ";
        // line 77
        $context["hps"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "servicehosting_hp_get_list", [0 => ["per_page" => 100]], "method", false, false, false, 77);
        // line 78
        echo "                    ";
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["hps"] ?? null), "list", [], "any", false, false, false, 78));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["_key"] => $context["hp"]) {
            // line 79
            echo "                    <tr>
                        <td>";
            // line 80
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["hp"], "name", [], "any", false, false, false, 80), "html", null, true);
            echo "</td>
                        <td>";
            // line 81
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["hp"], "max_addon", [], "any", false, false, false, 81), "html", null, true);
            echo "</td>
                        <td>";
            // line 82
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["hp"], "quota", [], "any", false, false, false, 82), "html", null, true);
            echo "</td>
                        <td>";
            // line 83
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["hp"], "bandwidth", [], "any", false, false, false, 83), "html", null, true);
            echo "</td>
                        <td class=\"actions\">
                            <a class=\"bb-button btn14\" href=\"";
            // line 85
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/servicehosting/plan");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["hp"], "id", [], "any", false, false, false, 85), "html", null, true);
            echo "\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                            <a class=\"bb-button btn14 bb-rm-tr api-link\" data-api-confirm=\"Are you sure?\" data-api-redirect=\"";
            // line 86
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("servicehosting");
            echo "\" href=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/servicehosting/hp_delete", ["id" => twig_get_attribute($this->env, $this->source, $context["hp"], "id", [], "any", false, false, false, 86)]);
            echo "\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                        </td>
                    </tr>
                    ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 90
            echo "                    <tr>
                        <td colspan=\"2\">";
            // line 91
            echo gettext("The list is empty");
            echo "</td>
                    </tr>
                    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['hp'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 94
        echo "                </tbody>
            </table>


        </div>

        <div class=\"tab_content nopadding\" id=\"tab-new-server\">
            <form method=\"post\" action=\"admin/servicehosting/server_create\" class=\"mainForm api-form\" data-api-redirect=\"";
        // line 101
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("servicehosting");
        echo "\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>";
        // line 104
        echo gettext("Name");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"name\" value=\"\" required=\"required\" placeholder=\"";
        // line 106
        echo gettext("Unique name to identify this server");
        echo "\">
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 111
        echo gettext("Hostname");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"hostname\" value=\"\" placeholder=\"";
        // line 113
        echo gettext("server1.yourserverdomain.com");
        echo "\">
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 118
        echo gettext("IP");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"ip\" value=\"\" required=\"required\" placeholder=\"";
        // line 120
        echo gettext("Primary IP address of the server used to connect to it like: 123.123.123.123");
        echo "\">
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 125
        echo gettext("Assigned IP Addresses");
        echo ":</label>
                        <div class=\"formRight\">
                            <textarea name=\"assigned_ips\" cols=\"5\" rows=\"5\" placeholder=\"";
        // line 127
        echo gettext("List the IP Addresses assigned to the server (One per line)");
        echo "\"></textarea>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 133
        echo gettext("Enable/Disable");
        echo ":</label>
                            <div class=\"formRight\">
                                <input type=\"radio\" name=\"active\" value=\"1\" checked=\"checked\"/><label>";
        // line 135
        echo gettext("Yes");
        echo "</label>
                                <input type=\"radio\" name=\"active\" value=\"0\"/><label>";
        // line 136
        echo gettext("No");
        echo "</label>
                            </div>
                        <div class=\"fix\"></div>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Nameservers</legend>
                    <div class=\"rowElem\">
                        <label>";
        // line 145
        echo gettext("Nameserver 1");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"ns1\" value=\"\" placeholder=\"";
        // line 147
        echo gettext("ns1.yourdomain.com");
        echo "\">
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 153
        echo gettext("Nameserver 2");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"ns2\" value=\"\" placeholder=\"";
        // line 155
        echo gettext("ns2.yourdomain.com");
        echo "\">
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 161
        echo gettext("Nameserver 3");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"ns3\" value=\"\" placeholder=\"";
        // line 163
        echo gettext("ns3.yourdomain.com");
        echo "\">
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 168
        echo gettext("Nameserver 4");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"ns4\" value=\"\" placeholder=\"";
        // line 170
        echo gettext("ns4.yourdomain.com");
        echo "\">
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Server manager</legend>
                    <div class=\"rowElem\">
                        <label>";
        // line 179
        echo gettext("Server manager");
        echo ":</label>
                        <div class=\"formRight\">
                            <select name=\"manager\">
                                ";
        // line 182
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "servicehosting_manager_get_pairs", [], "any", false, false, false, 182));
        foreach ($context['_seq'] as $context["code"] => $context["manager"]) {
            // line 183
            echo "                                <option value=\"";
            echo twig_escape_filter($this->env, $context["code"], "html", null, true);
            echo "\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["manager"], "label", [], "any", false, false, false, 183), "html", null, true);
            echo "</option>
                                ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['code'], $context['manager'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 185
        echo "                            </select>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 191
        echo gettext("Username");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"username\" value=\"\" placeholder=\"";
        // line 193
        echo gettext("Login username to your server: root/reseller");
        echo "\">
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <div class=\"rowElem\">
                        <label>";
        // line 199
        echo gettext("Password");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"password\" name=\"password\" value=\"\" placeholder=\"";
        // line 201
        echo gettext("Login password to your server");
        echo "\">
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 206
        echo gettext("Access Hash (Instead of password for cPanel servers)");
        echo ":</label>
                        <div class=\"formRight\">
                            <textarea name=\"accesshash\" cols=\"5\" rows=\"5\"></textarea>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <div class=\"rowElem\">
                        <label>";
        // line 214
        echo gettext("Use Secure connection");
        echo ":</label>
                            <div class=\"formRight\">
                                <input type=\"radio\" name=\"secure\" value=\"1\"/><label>";
        // line 216
        echo gettext("Yes");
        echo "</label>
                                <input type=\"radio\" name=\"secure\" value=\"0\" checked=\"checked\"/><label>";
        // line 217
        echo gettext("No");
        echo "</label>
                            </div>
                        <div class=\"fix\"></div>
                    </div>
                    <input type=\"submit\" value=\"";
        // line 221
        echo gettext("Add server");
        echo "\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-new-plan\">
            <div class=\"help\">
                <h3>";
        // line 228
        echo gettext("Adding new hosting plan");
        echo "</h3>
                <p>";
        // line 229
        echo gettext("Depending on server manager used to setup hosting account you may require provide additional parameters in next step. In this step provide basic hosting plan information.");
        echo "</p>
            </div>
            <form method=\"post\" action=\"";
        // line 231
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/servicehosting/hp_create");
        echo "\" class=\"mainForm api-form\" data-api-jsonp=\"onAfterHostingPlanCreate\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>";
        // line 234
        echo gettext("Name");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"name\" value=\"\" required=\"required\" placeholder=\"";
        // line 236
        echo gettext("Unique name to identify this hosting plan");
        echo "\">
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <div class=\"rowElem\">
                        <label>";
        // line 242
        echo gettext("Disk quota (MB)");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"quota\" value=\"1024\" placeholder=\"\">
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 249
        echo gettext("Bandwidth (MB)");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"bandwidth\" value=\"1024\" placeholder=\"\">
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <input type=\"submit\" value=\"";
        // line 256
        echo gettext("Create hosting plan");
        echo "\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>
    </div>
</div>



";
    }

    // line 268
    public function block_js($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 269
        echo "<script type=\"text/javascript\">
\$(function() {

});

function onAfterHostingPlanCreate(id) {
    bb.redirect(\"";
        // line 275
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("servicehosting/plan");
        echo "/\"+id);
}
</script>
";
    }

    public function getTemplateName()
    {
        return "mod_servicehosting_index.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  595 => 275,  587 => 269,  583 => 268,  569 => 256,  559 => 249,  549 => 242,  540 => 236,  535 => 234,  529 => 231,  524 => 229,  520 => 228,  510 => 221,  503 => 217,  499 => 216,  494 => 214,  483 => 206,  475 => 201,  470 => 199,  461 => 193,  456 => 191,  448 => 185,  437 => 183,  433 => 182,  427 => 179,  415 => 170,  410 => 168,  402 => 163,  397 => 161,  388 => 155,  383 => 153,  374 => 147,  369 => 145,  357 => 136,  353 => 135,  348 => 133,  339 => 127,  334 => 125,  326 => 120,  321 => 118,  313 => 113,  308 => 111,  300 => 106,  295 => 104,  289 => 101,  280 => 94,  271 => 91,  268 => 90,  257 => 86,  251 => 85,  246 => 83,  242 => 82,  238 => 81,  234 => 80,  231 => 79,  225 => 78,  223 => 77,  214 => 71,  210 => 70,  206 => 69,  202 => 68,  193 => 62,  182 => 53,  173 => 50,  170 => 49,  159 => 45,  153 => 44,  149 => 43,  145 => 42,  140 => 40,  136 => 39,  132 => 38,  128 => 37,  125 => 36,  119 => 35,  117 => 34,  109 => 29,  105 => 28,  101 => 27,  97 => 26,  88 => 20,  77 => 12,  73 => 11,  69 => 10,  64 => 7,  60 => 6,  53 => 3,  48 => 2,  46 => 4,  44 => 1,  37 => 2,);
    }

    public function getSourceContext()
    {
        return new Source("{% import \"macro_functions.phtml\" as mf %}
{% extends \"layout_default.phtml\" %}
{% block meta_title %}{% trans 'Hosting plans and servers' %}{% endblock %}
{% set active_menu = 'system' %}

{% block content %}
<div class=\"widget simpleTabs\">

    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">{% trans 'Hosting plans and servers' %}</a></li>
        <li><a href=\"#tab-new-server\">{% trans 'New server' %}</a></li>
        <li><a href=\"#tab-new-plan\">{% trans 'New hosting plan' %}</a></li>
    </ul>

    <div class=\"tabs_container\">
        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-index\">

            <div class=\"help\">
                <h5>{% trans 'Servers' %}</h5>
            </div>

            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <th>{% trans 'Name' %}</th>
                        <th>{% trans 'IP' %}</th>
                        <th>{% trans 'Server manager' %}</th>
                        <th>{% trans 'Active' %}</th>
                        <th style=\"width:22%\">&nbsp;</th>
                    </tr>
                </thead>
                <tbody>
                    {% set servers = admin.servicehosting_server_get_list({\"per_page\":100}) %}
                    {% for server in servers.list %}
                    <tr>
                        <td>{{server.name}}</td>
                        <td>{{server.ip}}</td>
                        <td>{{ mf.status_name(server.manager) }}</td>
                        <td>{{ mf.q(server.active) }}</td>
                        <td class=\"actions\">
                            <a class=\"bb-button btn14\" href=\"{{server.cpanel_url}}\" target=\"_blank\"><img src=\"images/icons/dark/cog.png\" alt=\"\"></a>
                            <a class=\"bb-button btn14 api-link\" data-api-msg=\"Connected\" href=\"{{ 'api/admin/servicehosting/server_test_connection'|link({'id' : server.id}) }}\" title=\"Test connection\"><img src=\"images/icons/dark/signal.png\" alt=\"\"></a>
                            <a class=\"bb-button btn14\" href=\"{{ '/servicehosting/server'|alink }}/{{server.id}}\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                            <a class=\"bb-button btn14 bb-rm-tr api-link\" data-api-confirm=\"Are you sure?\" data-api-redirect=\"{{ 'servicehosting'|alink }}\" href=\"{{ 'api/admin/servicehosting/server_delete'|link({'id' : server.id}) }}\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                        </td>
                    </tr>
                    {% else %}
                    <tr>
                        <td colspan=\"7\">{% trans 'The list is empty' %}</td>
                    </tr>
                    {% endfor %}
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan=\"7\"></td>
                    </tr>
                </tfoot>
            </table>

            <div class=\"help\">
                <h5>{% trans 'Hosting plans' %}</h5>
            </div>

            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td>{% trans 'Title' %}</td>
                        <td>{% trans 'Addon domains' %}</td>
                        <td>{% trans 'Disk space' %}</td>
                        <td>{% trans 'Bandwidth' %}</td>
                        <td style=\"width:13%\">&nbsp;</td>
                    </tr>
                </thead>
                
                <tbody>
                    {% set hps = admin.servicehosting_hp_get_list({\"per_page\":100}) %}
                    {% for hp in hps.list %}
                    <tr>
                        <td>{{hp.name}}</td>
                        <td>{{hp.max_addon}}</td>
                        <td>{{hp.quota}}</td>
                        <td>{{hp.bandwidth}}</td>
                        <td class=\"actions\">
                            <a class=\"bb-button btn14\" href=\"{{ '/servicehosting/plan'|alink }}/{{hp.id}}\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                            <a class=\"bb-button btn14 bb-rm-tr api-link\" data-api-confirm=\"Are you sure?\" data-api-redirect=\"{{ 'servicehosting'|alink }}\" href=\"{{ 'api/admin/servicehosting/hp_delete'|link({'id' : hp.id}) }}\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
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

        <div class=\"tab_content nopadding\" id=\"tab-new-server\">
            <form method=\"post\" action=\"admin/servicehosting/server_create\" class=\"mainForm api-form\" data-api-redirect=\"{{ 'servicehosting'|alink }}\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>{% trans 'Name' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"name\" value=\"\" required=\"required\" placeholder=\"{% trans 'Unique name to identify this server' %}\">
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Hostname' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"hostname\" value=\"\" placeholder=\"{% trans 'server1.yourserverdomain.com' %}\">
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'IP' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"ip\" value=\"\" required=\"required\" placeholder=\"{% trans 'Primary IP address of the server used to connect to it like: 123.123.123.123' %}\">
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Assigned IP Addresses' %}:</label>
                        <div class=\"formRight\">
                            <textarea name=\"assigned_ips\" cols=\"5\" rows=\"5\" placeholder=\"{% trans 'List the IP Addresses assigned to the server (One per line)' %}\"></textarea>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Enable/Disable' %}:</label>
                            <div class=\"formRight\">
                                <input type=\"radio\" name=\"active\" value=\"1\" checked=\"checked\"/><label>{% trans 'Yes' %}</label>
                                <input type=\"radio\" name=\"active\" value=\"0\"/><label>{% trans 'No' %}</label>
                            </div>
                        <div class=\"fix\"></div>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Nameservers</legend>
                    <div class=\"rowElem\">
                        <label>{% trans 'Nameserver 1' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"ns1\" value=\"\" placeholder=\"{% trans 'ns1.yourdomain.com' %}\">
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Nameserver 2' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"ns2\" value=\"\" placeholder=\"{% trans 'ns2.yourdomain.com' %}\">
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Nameserver 3' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"ns3\" value=\"\" placeholder=\"{% trans 'ns3.yourdomain.com' %}\">
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Nameserver 4' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"ns4\" value=\"\" placeholder=\"{% trans 'ns4.yourdomain.com' %}\">
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Server manager</legend>
                    <div class=\"rowElem\">
                        <label>{% trans 'Server manager' %}:</label>
                        <div class=\"formRight\">
                            <select name=\"manager\">
                                {% for code, manager in admin.servicehosting_manager_get_pairs %}
                                <option value=\"{{code}}\">{{ manager.label }}</option>
                                {% endfor %}
                            </select>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Username' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"username\" value=\"\" placeholder=\"{% trans 'Login username to your server: root/reseller' %}\">
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <div class=\"rowElem\">
                        <label>{% trans 'Password' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"password\" name=\"password\" value=\"\" placeholder=\"{% trans 'Login password to your server' %}\">
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Access Hash (Instead of password for cPanel servers)' %}:</label>
                        <div class=\"formRight\">
                            <textarea name=\"accesshash\" cols=\"5\" rows=\"5\"></textarea>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <div class=\"rowElem\">
                        <label>{% trans 'Use Secure connection' %}:</label>
                            <div class=\"formRight\">
                                <input type=\"radio\" name=\"secure\" value=\"1\"/><label>{% trans 'Yes' %}</label>
                                <input type=\"radio\" name=\"secure\" value=\"0\" checked=\"checked\"/><label>{% trans 'No' %}</label>
                            </div>
                        <div class=\"fix\"></div>
                    </div>
                    <input type=\"submit\" value=\"{% trans 'Add server' %}\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-new-plan\">
            <div class=\"help\">
                <h3>{% trans 'Adding new hosting plan' %}</h3>
                <p>{% trans 'Depending on server manager used to setup hosting account you may require provide additional parameters in next step. In this step provide basic hosting plan information.' %}</p>
            </div>
            <form method=\"post\" action=\"{{ 'api/admin/servicehosting/hp_create'|link}}\" class=\"mainForm api-form\" data-api-jsonp=\"onAfterHostingPlanCreate\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>{% trans 'Name' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"name\" value=\"\" required=\"required\" placeholder=\"{% trans 'Unique name to identify this hosting plan' %}\">
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <div class=\"rowElem\">
                        <label>{% trans 'Disk quota (MB)' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"quota\" value=\"1024\" placeholder=\"\">
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Bandwidth (MB)' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"bandwidth\" value=\"1024\" placeholder=\"\">
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <input type=\"submit\" value=\"{% trans 'Create hosting plan' %}\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>
    </div>
</div>



{% endblock %}


{% block js%}
<script type=\"text/javascript\">
\$(function() {

});

function onAfterHostingPlanCreate(id) {
    bb.redirect(\"{{ 'servicehosting/plan'|alink}}/\"+id);
}
</script>
{% endblock %}", "mod_servicehosting_index.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-themes/admin_default/html/mod_servicehosting_index.phtml");
    }
}
