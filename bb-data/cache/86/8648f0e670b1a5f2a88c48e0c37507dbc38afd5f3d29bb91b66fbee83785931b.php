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
class __TwigTemplate_6eff3eed338cf5dbcefb575b05ff79f00799153b9bcf5ed3fe54f17f05067ddb extends \Twig\Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
            'js' => [$this, 'block_js'],
        ];
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 1
        if ((twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "status", [], "any", false, false, false, 1) == "active")) {
            // line 2
            echo "<div class=\"row\">
    <article class=\"span12 data-block\">
        <div class=\"data-container\">
            <header>
                <h2>";
            // line 6
            echo gettext("Manage hosting account");
            echo "</h2>
                <ul class=\"data-header-actions\">
                    <li class=\"domain-tabs active\"><a href=\"#tab-details\" class=\"btn btn-inverse btn-alt\">";
            // line 8
            echo gettext("Details");
            echo "</a></li>
                    <li class=\"domain-tabs\"><a href=\"#tab-change-pass\" class=\"btn btn-inverse btn-alt\">";
            // line 9
            echo gettext("Password");
            echo "</a></li>
                    <li class=\"domain-tabs\"><a href=\"#tab-change-domain\" class=\"btn btn-inverse btn-alt\">";
            // line 10
            echo gettext("Domain");
            echo "</a></li>
                    <li class=\"domain-tabs\"><a href=\"#tab-change-username\" class=\"btn btn-inverse btn-alt\">";
            // line 11
            echo gettext("Username");
            echo "</a></li>
                </ul>
            </header>
            <section class=\"tab-content\">
                <div class=\"tab-pane active\" id=\"tab-details\">
                    <h3>";
            // line 16
            echo gettext("Details");
            echo "</h3>
                        ";
            // line 17
            $context["server"] = twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "server", [], "any", false, false, false, 17);
            // line 18
            echo "                        ";
            $context["hp"] = twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "hosting_plan", [], "any", false, false, false, 18);
            // line 19
            echo "                        <table class=\"table table-striped table-bordered table-condensed\">
                            <tbody>
                            <tr>
                                <td>";
            // line 22
            echo gettext("Domain");
            echo ":</td>
                                <td>
                                    <a target=\"_blank\" href=\"http://";
            // line 24
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "domain", [], "any", false, false, false, 24), "html", null, true);
            echo "\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "domain", [], "any", false, false, false, 24), "html", null, true);
            echo "</a>
                                </td>
                            </tr>

                            <tr>
                                <td>";
            // line 29
            echo gettext("Server IP");
            echo ":</td>
                                <td>";
            // line 30
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["server"] ?? null), "ip", [], "any", false, false, false, 30), "html", null, true);
            echo "</td>
                            </tr>

                            <tr>
                                <td>";
            // line 34
            echo gettext("Server Hostname");
            echo ":</td>
                                <td>";
            // line 35
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["server"] ?? null), "hostname", [], "any", false, false, false, 35), "html", null, true);
            echo "</td>
                            </tr>

                            <tr>
                                <td>";
            // line 39
            echo gettext("Username");
            echo ":</td>
                                <td>";
            // line 40
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "username", [], "any", false, false, false, 40), "html", null, true);
            echo "</td>
                            </tr>

                            <tr>
                                <td>";
            // line 44
            echo gettext("Password");
            echo ":</td>
                                <td>******</td>
                            </tr>

                            <tr>
                                <td>";
            // line 49
            echo gettext("Hosting plan");
            echo ":</td>
                                <td>";
            // line 50
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["hp"] ?? null), "name", [], "any", false, false, false, 50), "html", null, true);
            echo "</td>
                            </tr>

                            <tr>
                                <td>";
            // line 54
            echo gettext("Bandwidth");
            echo ":</td>
                                <td>";
            // line 55
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["hp"] ?? null), "bandwidth", [], "any", false, false, false, 55), "html", null, true);
            echo " MB / ";
            echo gettext("per month");
            echo "</td>
                            </tr>
                            <tr>
                                <td>";
            // line 58
            echo gettext("Disk quota");
            echo ":</td>
                                <td>";
            // line 59
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["hp"] ?? null), "quota", [], "any", false, false, false, 59), "html", null, true);
            echo " MB</td>
                            </tr>

                            </tbody>
                        </table>
                        <div class=\"control-group\">
                            <div class=\"controls\">
                            ";
            // line 66
            if (twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "domain_order_id", [], "any", false, false, false, 66)) {
                // line 67
                echo "                                    <a class=\"btn btn-primary\" href=\"";
                echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/order/service/manage");
                echo "/";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "domain_order_id", [], "any", false, false, false, 67), "html", null, true);
                echo "\">";
                echo gettext("Manage domain");
                echo "</a>
                            ";
            }
            // line 69
            echo "                                    <a class=\"btn btn-primary\" href=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["server"] ?? null), "cpanel_url", [], "any", false, false, false, 69), "html", null, true);
            echo "\" target=\"_blank\">";
            echo gettext("Jump to cPanel");
            echo "</a>
                            ";
            // line 70
            if (twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "reseller", [], "any", false, false, false, 70)) {
                // line 71
                echo "                                    <a class=\"btn btn-primary\" href=\"";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["server"] ?? null), "reseller_cpanel_url", [], "any", false, false, false, 71), "html", null, true);
                echo "\" target=\"_blank\">";
                echo gettext("Reseller control panel");
                echo "</a>
                            ";
            }
            // line 73
            echo "                            </div>
                        </div>
                </div>
                <div class=\"tab-pane\" id=\"tab-change-pass\">
                    <h3>";
            // line 77
            echo gettext("Change your FTP/cPanel/SSH password.");
            echo "</h3>
                        <form action=\"\" method=\"post\" id=\"change-password\" class=\"form-horizontal\">
                            <fieldset>
                                <div class=\"control-group\">
                                    <label class=\"control-label\" >";
            // line 81
            echo gettext("Password");
            echo ": </label>
                                    <div class=\"controls\">
                                        <input type=\"password\" name=\"password\" value=\"";
            // line 83
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "password", [], "any", false, false, false, 83), "html", null, true);
            echo "\" required=\"required\">
                                    </div>
                                </div>
                                <div class=\"control-group\">
                                    <label class=\"control-label\" >";
            // line 87
            echo gettext("Password Confirm");
            echo ": </label>
                                    <div class=\"controls\">
                                        <input type=\"password\" name=\"password_confirm\" value=\"";
            // line 89
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "password_confirm", [], "any", false, false, false, 89), "html", null, true);
            echo "\" required=\"required\">
                                    </div>
                                </div>

                                <input type=\"hidden\" name=\"order_id\" value=\"";
            // line 93
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "id", [], "any", false, false, false, 93), "html", null, true);
            echo "\">
                                <div class=\"control-group\">
                                    <div class=\"controls\">
                                        <button class=\"btn btn-primary\" type=\"submit\" value=\"";
            // line 96
            echo gettext("Change password");
            echo "\">";
            echo gettext("Change password");
            echo "</button>
                                    </div>
                                </div>
                            </fieldset>
                        </form>
                </div>

                <div class=\"tab-pane\" id=\"tab-change-domain\">
                    <h3>";
            // line 104
            echo gettext("Change domain");
            echo "</h3>
                        <form action=\"\" method=\"post\" id=\"change-domain\" class=\"form-horizontal\">
                            <fieldset>
                                <div class=\"control-group\">
                                    <label class=\"control-label\" >";
            // line 108
            echo gettext("New domain");
            echo ": </label>
                                    <div class=\"controls\">
                                        <input type=\"text\" name=\"sld\" value=\"";
            // line 110
            echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "domain", [], "any", true, true, false, 110)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "domain", [], "any", false, false, false, 110), twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "sld", [], "any", false, false, false, 110))) : (twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "sld", [], "any", false, false, false, 110))), "html", null, true);
            echo "\" required=\"required\" class=\"span2\">
                                        <input type=\"text\" name=\"tld\" value=\"";
            // line 111
            echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "domain", [], "any", true, true, false, 111)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "domain", [], "any", false, false, false, 111), twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "tld", [], "any", false, false, false, 111))) : (twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "tld", [], "any", false, false, false, 111))), "html", null, true);
            echo "\" required=\"required\" class=\"span1\">
                                    </div>
                                </div>
                                <input type=\"hidden\" name=\"order_id\" value=\"";
            // line 114
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "id", [], "any", false, false, false, 114), "html", null, true);
            echo "\">
                                <div class=\"control-group\">
                                    <div class=\"controls\">
                                        <button class=\"btn btn-primary\" type=\"submit\" value=\"";
            // line 117
            echo gettext("Change domain");
            echo "\">";
            echo gettext("Change domain");
            echo "</button>
                                    </div>
                                </div>
                            </fieldset>
                        </form>
                </div>

                <div class=\"tab-pane\" id=\"tab-change-username\">
                    <h3>";
            // line 125
            echo gettext("Change username");
            echo "</h3>
                        <form action=\"\" method=\"post\" id=\"change-username\" class=\"form-horizontal\">
                            <fieldset>
                            <div class=\"control-group\">
                                <label class=\"control-label\" >";
            // line 129
            echo gettext("Username");
            echo ": </label>
                                <div class=\"controls\">
                                    <input type=\"text\" name=\"username\" value=\"";
            // line 131
            echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "username", [], "any", true, true, false, 131)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "username", [], "any", false, false, false, 131), twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "username", [], "any", false, false, false, 131))) : (twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "username", [], "any", false, false, false, 131))), "html", null, true);
            echo "\" required=\"required\">
                                </div>
                            </div>

                                <input type=\"hidden\" name=\"order_id\" value=\"";
            // line 135
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "id", [], "any", false, false, false, 135), "html", null, true);
            echo "\">
                                <div class=\"control-group\">
                                    <div class=\"controls\">
                                        <button class=\"btn btn-primary\" type=\"submit\" value=\"";
            // line 138
            echo gettext("Change username");
            echo "\">";
            echo gettext("Change username");
            echo "</button>
                                    </div>
                                </div>
                            </fieldset>
                        </form>
                </div>
            </section>    
        </div>
    </article>
</div>

";
            // line 149
            $this->displayBlock('js', $context, $blocks);
        }
    }

    public function block_js($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 150
        echo "<script type=\"text/javascript\">
\$(function() {
    \$('.domain-tabs a').bind('click',function(e){
        e.preventDefault();
        \$(this).tab('show');
    });

    \$('#change-domain').bind('submit',function(event){
        bb.post(
            'client/servicehosting/change_domain',
            \$(this).serialize(),
            function(result) {
                bb.msg('Domain name was changed');
            }
        );
        return false;
    });

    \$('#change-username').bind('submit',function(event){
        bb.post(
            'client/servicehosting/change_username',
            \$(this).serialize(),
            function(result) {
                bb.msg('Account Username was changed');
            }
        );
        return false;
    });

    \$('#change-password').bind('submit',function(event){
        bb.post(
            'client/servicehosting/change_password',
            \$(this).serialize(),
            function(result) {
                bb.msg('Account Password was changed');
            }
        );
        return false;
    });

});
</script>
";
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
        return array (  353 => 150,  345 => 149,  329 => 138,  323 => 135,  316 => 131,  311 => 129,  304 => 125,  291 => 117,  285 => 114,  279 => 111,  275 => 110,  270 => 108,  263 => 104,  250 => 96,  244 => 93,  237 => 89,  232 => 87,  225 => 83,  220 => 81,  213 => 77,  207 => 73,  199 => 71,  197 => 70,  190 => 69,  180 => 67,  178 => 66,  168 => 59,  164 => 58,  156 => 55,  152 => 54,  145 => 50,  141 => 49,  133 => 44,  126 => 40,  122 => 39,  115 => 35,  111 => 34,  104 => 30,  100 => 29,  90 => 24,  85 => 22,  80 => 19,  77 => 18,  75 => 17,  71 => 16,  63 => 11,  59 => 10,  55 => 9,  51 => 8,  46 => 6,  40 => 2,  38 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% if order.status == 'active' %}
<div class=\"row\">
    <article class=\"span12 data-block\">
        <div class=\"data-container\">
            <header>
                <h2>{% trans 'Manage hosting account' %}</h2>
                <ul class=\"data-header-actions\">
                    <li class=\"domain-tabs active\"><a href=\"#tab-details\" class=\"btn btn-inverse btn-alt\">{% trans 'Details' %}</a></li>
                    <li class=\"domain-tabs\"><a href=\"#tab-change-pass\" class=\"btn btn-inverse btn-alt\">{% trans 'Password' %}</a></li>
                    <li class=\"domain-tabs\"><a href=\"#tab-change-domain\" class=\"btn btn-inverse btn-alt\">{% trans 'Domain' %}</a></li>
                    <li class=\"domain-tabs\"><a href=\"#tab-change-username\" class=\"btn btn-inverse btn-alt\">{% trans 'Username' %}</a></li>
                </ul>
            </header>
            <section class=\"tab-content\">
                <div class=\"tab-pane active\" id=\"tab-details\">
                    <h3>{% trans 'Details' %}</h3>
                        {% set server = service.server %}
                        {% set hp = service.hosting_plan %}
                        <table class=\"table table-striped table-bordered table-condensed\">
                            <tbody>
                            <tr>
                                <td>{% trans 'Domain' %}:</td>
                                <td>
                                    <a target=\"_blank\" href=\"http://{{ service.domain }}\">{{ service.domain }}</a>
                                </td>
                            </tr>

                            <tr>
                                <td>{% trans 'Server IP' %}:</td>
                                <td>{{ server.ip }}</td>
                            </tr>

                            <tr>
                                <td>{% trans 'Server Hostname' %}:</td>
                                <td>{{ server.hostname }}</td>
                            </tr>

                            <tr>
                                <td>{% trans 'Username' %}:</td>
                                <td>{{ service.username }}</td>
                            </tr>

                            <tr>
                                <td>{% trans 'Password' %}:</td>
                                <td>******</td>
                            </tr>

                            <tr>
                                <td>{% trans 'Hosting plan' %}:</td>
                                <td>{{ hp.name }}</td>
                            </tr>

                            <tr>
                                <td>{% trans 'Bandwidth' %}:</td>
                                <td>{{ hp.bandwidth }} MB / {% trans 'per month' %}</td>
                            </tr>
                            <tr>
                                <td>{% trans 'Disk quota' %}:</td>
                                <td>{{ hp.quota }} MB</td>
                            </tr>

                            </tbody>
                        </table>
                        <div class=\"control-group\">
                            <div class=\"controls\">
                            {% if service.domain_order_id %}
                                    <a class=\"btn btn-primary\" href=\"{{ '/order/service/manage'|link }}/{{service.domain_order_id}}\">{% trans 'Manage domain' %}</a>
                            {% endif %}
                                    <a class=\"btn btn-primary\" href=\"{{ server.cpanel_url }}\" target=\"_blank\">{% trans 'Jump to cPanel' %}</a>
                            {% if service.reseller %}
                                    <a class=\"btn btn-primary\" href=\"{{ server.reseller_cpanel_url }}\" target=\"_blank\">{% trans 'Reseller control panel' %}</a>
                            {% endif %}
                            </div>
                        </div>
                </div>
                <div class=\"tab-pane\" id=\"tab-change-pass\">
                    <h3>{% trans 'Change your FTP/cPanel/SSH password.' %}</h3>
                        <form action=\"\" method=\"post\" id=\"change-password\" class=\"form-horizontal\">
                            <fieldset>
                                <div class=\"control-group\">
                                    <label class=\"control-label\" >{% trans 'Password' %}: </label>
                                    <div class=\"controls\">
                                        <input type=\"password\" name=\"password\" value=\"{{ request.password }}\" required=\"required\">
                                    </div>
                                </div>
                                <div class=\"control-group\">
                                    <label class=\"control-label\" >{% trans 'Password Confirm' %}: </label>
                                    <div class=\"controls\">
                                        <input type=\"password\" name=\"password_confirm\" value=\"{{ request.password_confirm }}\" required=\"required\">
                                    </div>
                                </div>

                                <input type=\"hidden\" name=\"order_id\" value=\"{{ order.id }}\">
                                <div class=\"control-group\">
                                    <div class=\"controls\">
                                        <button class=\"btn btn-primary\" type=\"submit\" value=\"{% trans 'Change password' %}\">{% trans 'Change password' %}</button>
                                    </div>
                                </div>
                            </fieldset>
                        </form>
                </div>

                <div class=\"tab-pane\" id=\"tab-change-domain\">
                    <h3>{% trans 'Change domain' %}</h3>
                        <form action=\"\" method=\"post\" id=\"change-domain\" class=\"form-horizontal\">
                            <fieldset>
                                <div class=\"control-group\">
                                    <label class=\"control-label\" >{% trans 'New domain' %}: </label>
                                    <div class=\"controls\">
                                        <input type=\"text\" name=\"sld\" value=\"{{ request.domain|default(service.sld) }}\" required=\"required\" class=\"span2\">
                                        <input type=\"text\" name=\"tld\" value=\"{{ request.domain|default(service.tld) }}\" required=\"required\" class=\"span1\">
                                    </div>
                                </div>
                                <input type=\"hidden\" name=\"order_id\" value=\"{{ order.id }}\">
                                <div class=\"control-group\">
                                    <div class=\"controls\">
                                        <button class=\"btn btn-primary\" type=\"submit\" value=\"{% trans 'Change domain' %}\">{% trans 'Change domain' %}</button>
                                    </div>
                                </div>
                            </fieldset>
                        </form>
                </div>

                <div class=\"tab-pane\" id=\"tab-change-username\">
                    <h3>{% trans 'Change username' %}</h3>
                        <form action=\"\" method=\"post\" id=\"change-username\" class=\"form-horizontal\">
                            <fieldset>
                            <div class=\"control-group\">
                                <label class=\"control-label\" >{% trans 'Username' %}: </label>
                                <div class=\"controls\">
                                    <input type=\"text\" name=\"username\" value=\"{{ request.username|default(service.username) }}\" required=\"required\">
                                </div>
                            </div>

                                <input type=\"hidden\" name=\"order_id\" value=\"{{ order.id }}\">
                                <div class=\"control-group\">
                                    <div class=\"controls\">
                                        <button class=\"btn btn-primary\" type=\"submit\" value=\"{% trans 'Change username' %}\">{% trans 'Change username' %}</button>
                                    </div>
                                </div>
                            </fieldset>
                        </form>
                </div>
            </section>    
        </div>
    </article>
</div>

{% block js %}
<script type=\"text/javascript\">
\$(function() {
    \$('.domain-tabs a').bind('click',function(e){
        e.preventDefault();
        \$(this).tab('show');
    });

    \$('#change-domain').bind('submit',function(event){
        bb.post(
            'client/servicehosting/change_domain',
            \$(this).serialize(),
            function(result) {
                bb.msg('Domain name was changed');
            }
        );
        return false;
    });

    \$('#change-username').bind('submit',function(event){
        bb.post(
            'client/servicehosting/change_username',
            \$(this).serialize(),
            function(result) {
                bb.msg('Account Username was changed');
            }
        );
        return false;
    });

    \$('#change-password').bind('submit',function(event){
        bb.post(
            'client/servicehosting/change_password',
            \$(this).serialize(),
            function(result) {
                bb.msg('Account Password was changed');
            }
        );
        return false;
    });

});
</script>
{% endblock %}
{% endif %}", "mod_servicehosting_manage.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-modules/Servicehosting/html_client/mod_servicehosting_manage.phtml");
    }
}
