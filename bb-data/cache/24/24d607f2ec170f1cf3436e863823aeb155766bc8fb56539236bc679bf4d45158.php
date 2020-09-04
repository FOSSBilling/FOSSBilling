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

/* mod_servicehosting_hp.phtml */
class __TwigTemplate_2f753d571849b1571465c400b403b4de6cd667c3933c529c3fa818dacaf79250 extends \Twig\Template
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
        $this->parent = $this->loadTemplate("layout_default.phtml", "mod_servicehosting_hp.phtml", 1);
        $this->parent->display($context, array_merge($this->blocks, $blocks));
    }

    // line 2
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo "Hosting management";
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
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("servicehosting");
        echo "\">";
        echo gettext("Hosting plans and servers");
        echo "</a></li>
    <li class=\"lastB\">";
        // line 9
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["hp"] ?? null), "name", [], "any", false, false, false, 9), "html", null, true);
        echo "</li>
</ul>
";
    }

    // line 13
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 14
        echo "<div class=\"widget\">

    <div class=\"head\">
        <h5 class=\"iList\">Manage hosting plan</h5>
    </div>

    <form method=\"post\" action=\"";
        // line 20
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/servicehosting/hp_update");
        echo "\" class=\"mainForm api-form\" data-api-msg=\"Hosting plan updated\">
        <fieldset>
            <div class=\"rowElem noborder\">
                <label>";
        // line 23
        echo gettext("Name");
        echo ":</label>
                <div class=\"formRight\">
                    <input type=\"text\" name=\"name\" value=\"";
        // line 25
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["hp"] ?? null), "name", [], "any", false, false, false, 25), "html", null, true);
        echo "\" required=\"required\" placeholder=\"Unique name to identify this hosting plan\">
                </div>
                <div class=\"fix\"></div>
            </div>

            <div class=\"rowElem\">
                <label>";
        // line 31
        echo gettext("Disk quota");
        echo ":</label>
                <div class=\"formRight\">
                    <input type=\"text\" name=\"quota\" value=\"";
        // line 33
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["hp"] ?? null), "quota", [], "any", false, false, false, 33), "html", null, true);
        echo "\" placeholder=\"\">
                </div>
                <div class=\"fix\"></div>
            </div>
            
            <div class=\"rowElem\">
                <label>";
        // line 39
        echo gettext("Bandwidth");
        echo ":</label>
                <div class=\"formRight\">
                    <input type=\"text\" name=\"bandwidth\" value=\"";
        // line 41
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["hp"] ?? null), "bandwidth", [], "any", false, false, false, 41), "html", null, true);
        echo "\" placeholder=\"\">
                </div>
                <div class=\"fix\"></div>
            </div>
            <div class=\"rowElem\">
                <label>";
        // line 46
        echo gettext("Max Addon domains");
        echo ":</label>
                <div class=\"formRight\">
                    <input type=\"text\" name=\"max_addon\" value=\"";
        // line 48
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["hp"] ?? null), "max_addon", [], "any", false, false, false, 48), "html", null, true);
        echo "\" placeholder=\"\">
                </div>
                <div class=\"fix\"></div>
            </div>
            <div class=\"rowElem\">
                <label>";
        // line 53
        echo gettext("Max FTP accounts");
        echo ":</label>
                <div class=\"formRight\">
                    <input type=\"text\" name=\"max_ftp\" value=\"";
        // line 55
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["hp"] ?? null), "max_ftp", [], "any", false, false, false, 55), "html", null, true);
        echo "\" placeholder=\"\">
                </div>
                <div class=\"fix\"></div>
            </div>
            <div class=\"rowElem\">
                <label>";
        // line 60
        echo gettext("Max SQL Databases");
        echo ":</label>
                <div class=\"formRight\">
                    <input type=\"text\" name=\"max_sql\" value=\"";
        // line 62
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["hp"] ?? null), "max_sql", [], "any", false, false, false, 62), "html", null, true);
        echo "\" placeholder=\"\">
                </div>
                <div class=\"fix\"></div>
            </div>
            <div class=\"rowElem\">
                <label>";
        // line 67
        echo gettext("Max Email Accounts");
        echo ":</label>
                <div class=\"formRight\">
                    <input type=\"text\" name=\"max_pop\" value=\"";
        // line 69
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["hp"] ?? null), "max_pop", [], "any", false, false, false, 69), "html", null, true);
        echo "\" placeholder=\"\">
                </div>
                <div class=\"fix\"></div>
            </div>
            <div class=\"rowElem\">
                <label>";
        // line 74
        echo gettext("Max Subdomains");
        echo ":</label>
                <div class=\"formRight\">
                    <input type=\"text\" name=\"max_sub\" value=\"";
        // line 76
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["hp"] ?? null), "max_sub", [], "any", false, false, false, 76), "html", null, true);
        echo "\" placeholder=\"\">
                </div>
                <div class=\"fix\"></div>
            </div>
            <div class=\"rowElem\">
                <label>";
        // line 81
        echo gettext("Max Parked Domains");
        echo ":</label>
                <div class=\"formRight\">
                    <input type=\"text\" name=\"max_park\" value=\"";
        // line 83
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["hp"] ?? null), "max_park", [], "any", false, false, false, 83), "html", null, true);
        echo "\" placeholder=\"\">
                </div>
                <div class=\"fix\"></div>
            </div>
            </fieldset>
        
        ";
        // line 89
        if ((twig_length_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["hp"] ?? null), "config", [], "any", false, false, false, 89)) > 0)) {
            // line 90
            echo "            <fieldset>
                <legend>";
            // line 91
            echo gettext("Server manager specific parameters");
            echo "</legend>
            ";
            // line 92
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["hp"] ?? null), "config", [], "any", false, false, false, 92));
            foreach ($context['_seq'] as $context["name"] => $context["value"]) {
                // line 93
                echo "            <div class=\"rowElem\">
                <label class=\"topLabel\">";
                // line 94
                echo twig_escape_filter($this->env, $context["name"], "html", null, true);
                echo ":</label>
                    <div class=\"formBottom\">
                        <textarea rows=\"2\" cols=\"\" name=\"config[";
                // line 96
                echo twig_escape_filter($this->env, $context["name"], "html", null, true);
                echo "]\">";
                echo twig_escape_filter($this->env, $context["value"], "html", null, true);
                echo "</textarea>
                    </div>
                <div class=\"fix\"></div>
            </div>
            ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['name'], $context['value'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 101
            echo "            </fieldset>
            ";
        }
        // line 103
        echo "            
            <fieldset>
            <input type=\"submit\" value=\"";
        // line 105
        echo gettext("Update hosting plan");
        echo "\" class=\"greyishBtn submitForm\" />
            <input type=\"hidden\" name=\"id\" value=\"";
        // line 106
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["hp"] ?? null), "id", [], "any", false, false, false, 106), "html", null, true);
        echo "\"/>
            </fieldset>
    </form>

    <div class=\"help\">
        <h3>";
        // line 111
        echo gettext("Hosting plan additional parameters");
        echo "</h3>
        <p>";
        // line 112
        echo gettext("Depending on server manager used to setup hosting account you may require provide additional parameters. List of parameters server managers requires you can find on extensions page.");
        echo "</p>
    </div>

    <form method=\"post\" action=\"";
        // line 115
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/servicehosting/hp_update");
        echo "\" class=\"mainForm save api-form\" data-api-reload=\"1\">
        <fieldset>
                <div class=\"floatleft twoOne\">
                <div class=\"rowElem noborder pb0\"><label class=\"topLabel\">Parameter name:</label><div class=\"formBottom\"><input type=\"text\" name=\"new_config_name\"></div><div class=\"fix\"></div></div>
                </div>
                <div class=\"floatright twoOne\">
                <div class=\"rowElem noborder\"><label class=\"topLabel\">Parameter value:</label><div class=\"formBottom\"><textarea rows=\"7\" cols=\"\" name=\"new_config_value\"></textarea></div><div class=\"fix\"></div></div>
                <input type=\"submit\" value=\"";
        // line 122
        echo gettext("Add new configuration field");
        echo "\" class=\"greyishBtn submitForm\" />
                </div>
                <div class=\"fix\"></div>
            <input type=\"hidden\" name=\"id\" value=\"";
        // line 125
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["hp"] ?? null), "id", [], "any", false, false, false, 125), "html", null, true);
        echo "\"/>
        </fieldset>
    </form>
</div>
";
    }

    public function getTemplateName()
    {
        return "mod_servicehosting_hp.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  302 => 125,  296 => 122,  286 => 115,  280 => 112,  276 => 111,  268 => 106,  264 => 105,  260 => 103,  256 => 101,  243 => 96,  238 => 94,  235 => 93,  231 => 92,  227 => 91,  224 => 90,  222 => 89,  213 => 83,  208 => 81,  200 => 76,  195 => 74,  187 => 69,  182 => 67,  174 => 62,  169 => 60,  161 => 55,  156 => 53,  148 => 48,  143 => 46,  135 => 41,  130 => 39,  121 => 33,  116 => 31,  107 => 25,  102 => 23,  96 => 20,  88 => 14,  84 => 13,  77 => 9,  71 => 8,  65 => 7,  62 => 6,  58 => 5,  51 => 2,  46 => 1,  44 => 3,  37 => 1,);
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
    <li class=\"lastB\">{{ hp.name }}</li>
</ul>
{% endblock %}

{% block content %}
<div class=\"widget\">

    <div class=\"head\">
        <h5 class=\"iList\">Manage hosting plan</h5>
    </div>

    <form method=\"post\" action=\"{{ 'api/admin/servicehosting/hp_update'|link }}\" class=\"mainForm api-form\" data-api-msg=\"Hosting plan updated\">
        <fieldset>
            <div class=\"rowElem noborder\">
                <label>{% trans 'Name' %}:</label>
                <div class=\"formRight\">
                    <input type=\"text\" name=\"name\" value=\"{{ hp.name }}\" required=\"required\" placeholder=\"Unique name to identify this hosting plan\">
                </div>
                <div class=\"fix\"></div>
            </div>

            <div class=\"rowElem\">
                <label>{% trans 'Disk quota' %}:</label>
                <div class=\"formRight\">
                    <input type=\"text\" name=\"quota\" value=\"{{ hp.quota }}\" placeholder=\"\">
                </div>
                <div class=\"fix\"></div>
            </div>
            
            <div class=\"rowElem\">
                <label>{% trans 'Bandwidth' %}:</label>
                <div class=\"formRight\">
                    <input type=\"text\" name=\"bandwidth\" value=\"{{ hp.bandwidth }}\" placeholder=\"\">
                </div>
                <div class=\"fix\"></div>
            </div>
            <div class=\"rowElem\">
                <label>{% trans 'Max Addon domains' %}:</label>
                <div class=\"formRight\">
                    <input type=\"text\" name=\"max_addon\" value=\"{{ hp.max_addon }}\" placeholder=\"\">
                </div>
                <div class=\"fix\"></div>
            </div>
            <div class=\"rowElem\">
                <label>{% trans 'Max FTP accounts' %}:</label>
                <div class=\"formRight\">
                    <input type=\"text\" name=\"max_ftp\" value=\"{{ hp.max_ftp }}\" placeholder=\"\">
                </div>
                <div class=\"fix\"></div>
            </div>
            <div class=\"rowElem\">
                <label>{% trans 'Max SQL Databases' %}:</label>
                <div class=\"formRight\">
                    <input type=\"text\" name=\"max_sql\" value=\"{{ hp.max_sql }}\" placeholder=\"\">
                </div>
                <div class=\"fix\"></div>
            </div>
            <div class=\"rowElem\">
                <label>{% trans 'Max Email Accounts' %}:</label>
                <div class=\"formRight\">
                    <input type=\"text\" name=\"max_pop\" value=\"{{ hp.max_pop }}\" placeholder=\"\">
                </div>
                <div class=\"fix\"></div>
            </div>
            <div class=\"rowElem\">
                <label>{% trans 'Max Subdomains' %}:</label>
                <div class=\"formRight\">
                    <input type=\"text\" name=\"max_sub\" value=\"{{ hp.max_sub }}\" placeholder=\"\">
                </div>
                <div class=\"fix\"></div>
            </div>
            <div class=\"rowElem\">
                <label>{% trans 'Max Parked Domains' %}:</label>
                <div class=\"formRight\">
                    <input type=\"text\" name=\"max_park\" value=\"{{ hp.max_park }}\" placeholder=\"\">
                </div>
                <div class=\"fix\"></div>
            </div>
            </fieldset>
        
        {% if hp.config|length > 0 %}
            <fieldset>
                <legend>{% trans 'Server manager specific parameters' %}</legend>
            {% for name, value in hp.config %}
            <div class=\"rowElem\">
                <label class=\"topLabel\">{{ name }}:</label>
                    <div class=\"formBottom\">
                        <textarea rows=\"2\" cols=\"\" name=\"config[{{ name }}]\">{{ value }}</textarea>
                    </div>
                <div class=\"fix\"></div>
            </div>
            {% endfor %}
            </fieldset>
            {% endif %}
            
            <fieldset>
            <input type=\"submit\" value=\"{% trans 'Update hosting plan' %}\" class=\"greyishBtn submitForm\" />
            <input type=\"hidden\" name=\"id\" value=\"{{ hp.id }}\"/>
            </fieldset>
    </form>

    <div class=\"help\">
        <h3>{% trans 'Hosting plan additional parameters' %}</h3>
        <p>{% trans 'Depending on server manager used to setup hosting account you may require provide additional parameters. List of parameters server managers requires you can find on extensions page.' %}</p>
    </div>

    <form method=\"post\" action=\"{{ 'api/admin/servicehosting/hp_update'|link }}\" class=\"mainForm save api-form\" data-api-reload=\"1\">
        <fieldset>
                <div class=\"floatleft twoOne\">
                <div class=\"rowElem noborder pb0\"><label class=\"topLabel\">Parameter name:</label><div class=\"formBottom\"><input type=\"text\" name=\"new_config_name\"></div><div class=\"fix\"></div></div>
                </div>
                <div class=\"floatright twoOne\">
                <div class=\"rowElem noborder\"><label class=\"topLabel\">Parameter value:</label><div class=\"formBottom\"><textarea rows=\"7\" cols=\"\" name=\"new_config_value\"></textarea></div><div class=\"fix\"></div></div>
                <input type=\"submit\" value=\"{% trans 'Add new configuration field' %}\" class=\"greyishBtn submitForm\" />
                </div>
                <div class=\"fix\"></div>
            <input type=\"hidden\" name=\"id\" value=\"{{ hp.id }}\"/>
        </fieldset>
    </form>
</div>
{% endblock %}", "mod_servicehosting_hp.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-themes/admin_default/html/mod_servicehosting_hp.phtml");
    }
}
