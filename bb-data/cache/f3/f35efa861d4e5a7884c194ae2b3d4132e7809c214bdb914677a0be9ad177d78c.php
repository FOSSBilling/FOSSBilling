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

/* mod_servicehosting_server.phtml */
class __TwigTemplate_4584988aeb6bbdb1800a6cf8311cf89100e18332d9a83b8d8459a589259fdd66 extends \Twig\Template
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
        // line 1
        return "layout_default.phtml";
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 3
        $context["active_menu"] = "system";
        // line 1
        $this->parent = $this->loadTemplate("layout_default.phtml", "mod_servicehosting_server.phtml", 1);
        $this->parent->display($context, array_merge($this->blocks, $blocks));
    }

    // line 2
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo "Hosting management";
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
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("servicehosting");
        echo "\">";
        echo gettext("Hosting plans and servers");
        echo "</a></li>
    <li class=\"lastB\">";
        // line 10
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["server"] ?? null), "name", [], "any", false, false, false, 10), "html", null, true);
        echo "</li>
</ul>
";
    }

    // line 14
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 15
        echo "<div class=\"widget\">
    <div class=\"head\">
        <h5>Server management</h5>
    </div>

    <form method=\"post\" action=\"";
        // line 20
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/servicehosting/server_update");
        echo "\" id=\"server-update\" class=\"mainForm save api-form\" data-api-msg=\"Server updated\">
        <fieldset>
            <div class=\"rowElem noborder\">
                <label>";
        // line 23
        echo gettext("Name");
        echo ":</label>
                <div class=\"formRight\">
                    <input type=\"text\" name=\"name\" value=\"";
        // line 25
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["server"] ?? null), "name", [], "any", false, false, false, 25), "html", null, true);
        echo "\" required=\"required\" placeholder=\"Unique name to identify this server\">
                </div>
                <div class=\"fix\"></div>
            </div>
            <div class=\"rowElem\">
                <label>";
        // line 30
        echo gettext("Hostname");
        echo ":</label>
                <div class=\"formRight\">
                    <input type=\"text\" name=\"hostname\" value=\"";
        // line 32
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["server"] ?? null), "hostname", [], "any", false, false, false, 32), "html", null, true);
        echo "\" placeholder=\"server1.yourserverdomain.com\">
                </div>
                <div class=\"fix\"></div>
            </div>
            <div class=\"rowElem\">
                <label>";
        // line 37
        echo gettext("IP");
        echo ":</label>
                <div class=\"formRight\">
                    <input type=\"text\" name=\"ip\" value=\"";
        // line 39
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["server"] ?? null), "ip", [], "any", false, false, false, 39), "html", null, true);
        echo "\" required=\"required\" placeholder=\"Primary IP address of the server used to connect to it like: 123.123.123.123\">
                </div>
                <div class=\"fix\"></div>
            </div>
            ";
        // line 52
        echo "            <div class=\"rowElem\">
                <label>";
        // line 53
        echo gettext("Enable/Disable");
        echo ":</label>
                    <div class=\"formRight\">
                        <input type=\"radio\" name=\"active\" value=\"1\" ";
        // line 55
        if (twig_get_attribute($this->env, $this->source, ($context["server"] ?? null), "active", [], "any", false, false, false, 55)) {
            echo "checked=\"checked\"";
        }
        echo "/><label>Yes</label>
                        <input type=\"radio\" name=\"active\" value=\"0\" ";
        // line 56
        if ( !twig_get_attribute($this->env, $this->source, ($context["server"] ?? null), "active", [], "any", false, false, false, 56)) {
            echo "checked=\"checked\"";
        }
        echo "/><label>No</label>
                    </div>
                <div class=\"fix\"></div>
            </div>
        </fieldset>

        <fieldset>
            <legend>Server manager</legend>
            <div class=\"rowElem\">
                <label>";
        // line 65
        echo gettext("Server manager");
        echo ":</label>
                <div class=\"formRight\">
                    <select name=\"manager\">
                        ";
        // line 68
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "servicehosting_manager_get_pairs", [], "any", false, false, false, 68));
        foreach ($context['_seq'] as $context["code"] => $context["manager"]) {
            // line 69
            echo "                        <option value=\"";
            echo twig_escape_filter($this->env, $context["code"], "html", null, true);
            echo "\" ";
            if ((twig_get_attribute($this->env, $this->source, ($context["server"] ?? null), "manager", [], "any", false, false, false, 69) == $context["code"])) {
                echo "selected=\"selected\"";
            }
            echo ">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["manager"], "label", [], "any", false, false, false, 69), "html", null, true);
            echo "</option>
                        ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['code'], $context['manager'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 71
        echo "                    </select>
                </div>
                <div class=\"fix\"></div>
            </div>

            <div class=\"rowElem\">
                <label>";
        // line 77
        echo gettext("Username");
        echo ":</label>
                <div class=\"formRight\">
                    <input type=\"text\" name=\"username\" value=\"";
        // line 79
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["server"] ?? null), "username", [], "any", false, false, false, 79), "html", null, true);
        echo "\" placeholder=\"Login username to your server: root/reseller\">
                </div>
                <div class=\"fix\"></div>
            </div>

            <div class=\"rowElem\">
                <label>";
        // line 85
        echo gettext("Password");
        echo ":</label>
                <div class=\"formRight\">
                    <input type=\"password\" name=\"password\" value=\"";
        // line 87
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["server"] ?? null), "password", [], "any", false, false, false, 87), "html", null, true);
        echo "\" placeholder=\"Login password to your server\">
                </div>
                <div class=\"fix\"></div>
            </div>

            <div class=\"rowElem\">
                <label>";
        // line 93
        echo gettext("Access Hash (Instead of password for cPanel servers)");
        echo ":</label>
                <div class=\"formRight\">
                    <textarea name=\"accesshash\" cols=\"5\" rows=\"5\">";
        // line 95
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["server"] ?? null), "accesshash", [], "any", false, false, false, 95), "html", null, true);
        echo "</textarea>
                </div>
                <div class=\"fix\"></div>
            </div>

            <div class=\"rowElem\">
                <label>";
        // line 101
        echo gettext("Connection port");
        echo ":</label>
                <div class=\"formRight\">
                    <input type=\"text\" name=\"port\" value=\"";
        // line 103
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["server"] ?? null), "port", [], "any", false, false, false, 103), "html", null, true);
        echo "\" placeholder=\"Custom port. Use blank to use default. Used to connect to API\">
                </div>
                <div class=\"fix\"></div>
            </div>

            <div class=\"rowElem\">
                <label>";
        // line 109
        echo gettext("Use Secure connection");
        echo ":</label>
                    <div class=\"formRight\">
                        <input type=\"radio\" name=\"secure\" value=\"1\" ";
        // line 111
        if (twig_get_attribute($this->env, $this->source, ($context["server"] ?? null), "secure", [], "any", false, false, false, 111)) {
            echo "checked=\"checked\"";
        }
        echo "/><label>Yes</label>
                        <input type=\"radio\" name=\"secure\" value=\"0\" ";
        // line 112
        if ( !twig_get_attribute($this->env, $this->source, ($context["server"] ?? null), "secure", [], "any", false, false, false, 112)) {
            echo "checked=\"checked\"";
        }
        echo "/><label>No</label>
                    </div>
                <div class=\"fix\"></div>
            </div>

            <input type=\"button\" value=\"";
        // line 117
        echo gettext("Update and test connection");
        echo "\" class=\"greyishBtn submitForm\" id=\"test-connection\"/>
        </fieldset>


        <fieldset>
            <legend>Nameservers</legend>
            <div class=\"rowElem\">
                <label>";
        // line 124
        echo gettext("Nameserver 1");
        echo ":</label>
                <div class=\"formRight\">
                    <input type=\"text\" name=\"ns1\" value=\"";
        // line 126
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["server"] ?? null), "ns1", [], "any", false, false, false, 126), "html", null, true);
        echo "\" placeholder=\"ns1.yourdomain.com\">
                </div>
                <div class=\"fix\"></div>
            </div>

            <div class=\"rowElem\">
                <label>";
        // line 132
        echo gettext("Nameserver 2");
        echo ":</label>
                <div class=\"formRight\">
                    <input type=\"text\" name=\"ns2\" value=\"";
        // line 134
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["server"] ?? null), "ns2", [], "any", false, false, false, 134), "html", null, true);
        echo "\" placeholder=\"ns2.yourdomain.com\">
                </div>
                <div class=\"fix\"></div>
            </div>

            <div class=\"rowElem\">
                <label>";
        // line 140
        echo gettext("Nameserver 3");
        echo ":</label>
                <div class=\"formRight\">
                    <input type=\"text\" name=\"ns3\" value=\"";
        // line 142
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["server"] ?? null), "ns3", [], "any", false, false, false, 142), "html", null, true);
        echo "\" placeholder=\"ns3.yourdomain.com\">
                </div>
                <div class=\"fix\"></div>
            </div>
            <div class=\"rowElem\">
                <label>";
        // line 147
        echo gettext("Nameserver 4");
        echo ":</label>
                <div class=\"formRight\">
                    <input type=\"text\" name=\"ns4\" value=\"";
        // line 149
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["server"] ?? null), "ns4", [], "any", false, false, false, 149), "html", null, true);
        echo "\" placeholder=\"ns4.yourdomain.com\">
                </div>
                <div class=\"fix\"></div>
            </div>
            <input type=\"submit\" value=\"";
        // line 153
        echo gettext("Update server");
        echo "\" class=\"greyishBtn submitForm\" />
        </fieldset>

        <input type=\"hidden\" name=\"id\" value=\"";
        // line 156
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["server"] ?? null), "id", [], "any", false, false, false, 156), "html", null, true);
        echo "\" />
    </form>
</div>

";
    }

    // line 162
    public function block_js($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 163
        echo "<script type=\"text/javascript\">
\$(function() {

    \$('#test-connection').click(function(){
        \$('#server-update').submit();
        bb.post('admin/servicehosting/server_test_connection', {id:";
        // line 168
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["server"] ?? null), "id", [], "any", false, false, false, 168), "html", null, true);
        echo "}, function(result){
            bb.msg('Successfully connected to server');
        });
        return false;
    });

});
</script>

";
    }

    public function getTemplateName()
    {
        return "mod_servicehosting_server.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  367 => 168,  360 => 163,  356 => 162,  347 => 156,  341 => 153,  334 => 149,  329 => 147,  321 => 142,  316 => 140,  307 => 134,  302 => 132,  293 => 126,  288 => 124,  278 => 117,  268 => 112,  262 => 111,  257 => 109,  248 => 103,  243 => 101,  234 => 95,  229 => 93,  220 => 87,  215 => 85,  206 => 79,  201 => 77,  193 => 71,  178 => 69,  174 => 68,  168 => 65,  154 => 56,  148 => 55,  143 => 53,  140 => 52,  133 => 39,  128 => 37,  120 => 32,  115 => 30,  107 => 25,  102 => 23,  96 => 20,  89 => 15,  85 => 14,  78 => 10,  72 => 9,  66 => 8,  63 => 7,  59 => 6,  52 => 2,  47 => 1,  45 => 3,  38 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends \"layout_default.phtml\" %}
{% block meta_title %}Hosting management{% endblock %}
{% set active_menu = 'system' %}


{% block breadcrumbs %}
<ul>
    <li class=\"firstB\"><a href=\"{{ '/'|alink }}\">{% trans 'Home' %}</a></li>
    <li><a href=\"{{ 'servicehosting'|alink }}\">{% trans 'Hosting plans and servers' %}</a></li>
    <li class=\"lastB\">{{ server.name }}</li>
</ul>
{% endblock %}

{% block content %}
<div class=\"widget\">
    <div class=\"head\">
        <h5>Server management</h5>
    </div>

    <form method=\"post\" action=\"{{ 'api/admin/servicehosting/server_update'|link }}\" id=\"server-update\" class=\"mainForm save api-form\" data-api-msg=\"Server updated\">
        <fieldset>
            <div class=\"rowElem noborder\">
                <label>{% trans 'Name' %}:</label>
                <div class=\"formRight\">
                    <input type=\"text\" name=\"name\" value=\"{{ server.name }}\" required=\"required\" placeholder=\"Unique name to identify this server\">
                </div>
                <div class=\"fix\"></div>
            </div>
            <div class=\"rowElem\">
                <label>{% trans 'Hostname' %}:</label>
                <div class=\"formRight\">
                    <input type=\"text\" name=\"hostname\" value=\"{{ server.hostname }}\" placeholder=\"server1.yourserverdomain.com\">
                </div>
                <div class=\"fix\"></div>
            </div>
            <div class=\"rowElem\">
                <label>{% trans 'IP' %}:</label>
                <div class=\"formRight\">
                    <input type=\"text\" name=\"ip\" value=\"{{ server.ip }}\" required=\"required\" placeholder=\"Primary IP address of the server used to connect to it like: 123.123.123.123\">
                </div>
                <div class=\"fix\"></div>
            </div>
            {#
            <div class=\"rowElem\">
                <label>{% trans 'Assigned IP Addresses' %}:</label>
                <div class=\"formRight\">
                    <textarea name=\"assigned_ips\" cols=\"5\" rows=\"5\" placeholder=\"List the IP Addresses assigned to the server (One per line)\">{% for v in server.assigned_ips %}{{ v }}{{constant(\"PHP_EOL\")}}{% endfor %}</textarea>
                </div>
                <div class=\"fix\"></div>
            </div>
            #}
            <div class=\"rowElem\">
                <label>{% trans 'Enable/Disable' %}:</label>
                    <div class=\"formRight\">
                        <input type=\"radio\" name=\"active\" value=\"1\" {% if server.active %}checked=\"checked\"{% endif %}/><label>Yes</label>
                        <input type=\"radio\" name=\"active\" value=\"0\" {% if not server.active %}checked=\"checked\"{% endif %}/><label>No</label>
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
                        <option value=\"{{code}}\" {% if server.manager == code %}selected=\"selected\"{% endif %}>{{ manager.label }}</option>
                        {% endfor %}
                    </select>
                </div>
                <div class=\"fix\"></div>
            </div>

            <div class=\"rowElem\">
                <label>{% trans 'Username' %}:</label>
                <div class=\"formRight\">
                    <input type=\"text\" name=\"username\" value=\"{{ server.username }}\" placeholder=\"Login username to your server: root/reseller\">
                </div>
                <div class=\"fix\"></div>
            </div>

            <div class=\"rowElem\">
                <label>{% trans 'Password' %}:</label>
                <div class=\"formRight\">
                    <input type=\"password\" name=\"password\" value=\"{{ server.password }}\" placeholder=\"Login password to your server\">
                </div>
                <div class=\"fix\"></div>
            </div>

            <div class=\"rowElem\">
                <label>{% trans 'Access Hash (Instead of password for cPanel servers)' %}:</label>
                <div class=\"formRight\">
                    <textarea name=\"accesshash\" cols=\"5\" rows=\"5\">{{ server.accesshash }}</textarea>
                </div>
                <div class=\"fix\"></div>
            </div>

            <div class=\"rowElem\">
                <label>{% trans 'Connection port' %}:</label>
                <div class=\"formRight\">
                    <input type=\"text\" name=\"port\" value=\"{{ server.port }}\" placeholder=\"Custom port. Use blank to use default. Used to connect to API\">
                </div>
                <div class=\"fix\"></div>
            </div>

            <div class=\"rowElem\">
                <label>{% trans 'Use Secure connection' %}:</label>
                    <div class=\"formRight\">
                        <input type=\"radio\" name=\"secure\" value=\"1\" {% if server.secure %}checked=\"checked\"{% endif %}/><label>Yes</label>
                        <input type=\"radio\" name=\"secure\" value=\"0\" {% if not server.secure %}checked=\"checked\"{% endif %}/><label>No</label>
                    </div>
                <div class=\"fix\"></div>
            </div>

            <input type=\"button\" value=\"{% trans 'Update and test connection' %}\" class=\"greyishBtn submitForm\" id=\"test-connection\"/>
        </fieldset>


        <fieldset>
            <legend>Nameservers</legend>
            <div class=\"rowElem\">
                <label>{% trans 'Nameserver 1' %}:</label>
                <div class=\"formRight\">
                    <input type=\"text\" name=\"ns1\" value=\"{{ server.ns1 }}\" placeholder=\"ns1.yourdomain.com\">
                </div>
                <div class=\"fix\"></div>
            </div>

            <div class=\"rowElem\">
                <label>{% trans 'Nameserver 2' %}:</label>
                <div class=\"formRight\">
                    <input type=\"text\" name=\"ns2\" value=\"{{ server.ns2 }}\" placeholder=\"ns2.yourdomain.com\">
                </div>
                <div class=\"fix\"></div>
            </div>

            <div class=\"rowElem\">
                <label>{% trans 'Nameserver 3' %}:</label>
                <div class=\"formRight\">
                    <input type=\"text\" name=\"ns3\" value=\"{{ server.ns3 }}\" placeholder=\"ns3.yourdomain.com\">
                </div>
                <div class=\"fix\"></div>
            </div>
            <div class=\"rowElem\">
                <label>{% trans 'Nameserver 4' %}:</label>
                <div class=\"formRight\">
                    <input type=\"text\" name=\"ns4\" value=\"{{ server.ns4 }}\" placeholder=\"ns4.yourdomain.com\">
                </div>
                <div class=\"fix\"></div>
            </div>
            <input type=\"submit\" value=\"{% trans 'Update server' %}\" class=\"greyishBtn submitForm\" />
        </fieldset>

        <input type=\"hidden\" name=\"id\" value=\"{{ server.id }}\" />
    </form>
</div>

{% endblock %}

{% block js%}
<script type=\"text/javascript\">
\$(function() {

    \$('#test-connection').click(function(){
        \$('#server-update').submit();
        bb.post('admin/servicehosting/server_test_connection', {id:{{server.id}}}, function(result){
            bb.msg('Successfully connected to server');
        });
        return false;
    });

});
</script>

{% endblock %}
", "mod_servicehosting_server.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-themes/admin_default/html/mod_servicehosting_server.phtml");
    }
}
