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

/* mod_system_index.phtml */
class __TwigTemplate_dfff7fcd64304da64c306bf01a84846315b7f0c0f7a5608b420b38edf5fa8daa extends \Twig\Template
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
        ];
    }

    protected function doGetParent(array $context)
    {
        // line 1
        return $this->loadTemplate(((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ajax", [], "any", false, false, false, 1)) ? ("layout_blank.phtml") : ("layout_default.phtml")), "mod_system_index.phtml", 1);
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 2
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_system_index.phtml", 2)->unwrap();
        // line 4
        $context["active_menu"] = "system";
        // line 5
        $context["params"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "system_get_params", [], "any", false, false, false, 5);
        // line 1
        $this->getParent($context)->display($context, array_merge($this->blocks, $blocks));
    }

    // line 3
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo gettext("Settings");
    }

    // line 6
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 7
        echo "
<div class=\"widget simpleTabs\">

    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">";
        // line 11
        echo gettext("Settings");
        echo "</a></li>
        <li><a href=\"#tab-license\">";
        // line 12
        echo gettext("License");
        echo "</a></li>
    </ul>

    <div class=\"tabs_container\">
        <div class=\"fix\"></div>
        <div id=\"tab-index\" class=\"tab_content nopadding\">
            ";
        // line 18
        echo twig_call_macro($macros["mf"], "macro_table_search", [], 18, $context, $this->getSourceContext());
        echo "
            <table class=\"tableStatic wide\">
                <tbody>
                    ";
        // line 21
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "extension_get_list", [0 => twig_array_merge(["active" => 1, "has_settings" => 1], ($context["request"] ?? null))], "method", false, false, false, 21));
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
        foreach ($context['_seq'] as $context["_key"] => $context["ext"]) {
            // line 22
            echo "                    <tr ";
            echo ((twig_get_attribute($this->env, $this->source, $context["loop"], "first", [], "any", false, false, false, 22)) ? ("style=\"border-top:0;\"") : (""));
            echo " class=\"hover-row\">
                        <td style=\"width: 32px;\"><a href=\"";
            // line 23
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("extension/settings");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ext"], "id", [], "any", false, false, false, 23), "html", null, true);
            echo "\"><img src=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ext"], "icon_url", [], "any", false, false, false, 23), "html", null, true);
            echo "\" alt=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ext"], "name", [], "any", false, false, false, 23), "html", null, true);
            echo "\" style=\"width: 32px; height: 32px;\"/></a></td>
                        <td style=\"border: 0; font-weight: bold;\"><a href=\"";
            // line 24
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("extension/settings");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ext"], "id", [], "any", false, false, false, 24), "html", null, true);
            echo "\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ext"], "name", [], "any", false, false, false, 24), "html", null, true);
            echo "</a></td>
                        <td style=\"width: 5%; border: 0;\"><a class=\"bb-button btn14\" href=\"";
            // line 25
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("extension/settings");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ext"], "id", [], "any", false, false, false, 25), "html", null, true);
            echo "\"><img src=\"images/icons/dark/play.png\" alt=\"\" class=\"icon\" title=\"";
            echo gettext("Module settings");
            echo "\"></a></td>
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
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['ext'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 28
        echo "                </tbody>
            </table>
        </div>

        <div id=\"tab-license\" class=\"tab_content nopadding\">

            <div class=\"help\">
                <h3>";
        // line 35
        echo gettext("License");
        echo "</h3>
                <p>";
        // line 36
        echo gettext("After purchase you have received an e-mail with license key. Update license key to unlock all features of BoxBilling");
        echo "</p>
                <p>";
        // line 37
        echo gettext("To change license key, change <em>BB_LICENSE</em> value in <em>bb-config.php</em> file");
        echo ". </p>
                <p><a href=\"";
        // line 38
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("filemanager/ide", ["open" => "bb-config.php"]);
        echo "\" target=\"_blank\" target=\"_blank\">";
        echo gettext("Edit bb-config.php file");
        echo "</a> (File must be writable by web server)</p>
            </div>

            ";
        // line 41
        $context["env"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "system_env", [], "any", false, false, false, 41);
        // line 42
        echo "            ";
        $context["license"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "system_license_info", [], "any", false, false, false, 42);
        // line 43
        echo "            <table class=\"tableStatic wide\">
                <tbody>
                    <tr class=\"noborder\">
                        <td>";
        // line 46
        echo gettext("Licensed to");
        echo "</td>
                        <td align=\"right\">";
        // line 47
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["license"] ?? null), "licensed_to", [], "any", false, false, false, 47), "html", null, true);
        echo "</td>
                    </tr>
                    <tr>
                        <td>";
        // line 50
        echo gettext("Key");
        echo "</td>
                        <td align=\"right\">";
        // line 51
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["license"] ?? null), "key", [], "any", false, false, false, 51), "html", null, true);
        echo "</td>
                    </tr>

                    ";
        // line 54
        if (twig_get_attribute($this->env, $this->source, ($context["license"] ?? null), "expires_at", [], "any", false, false, false, 54)) {
            // line 55
            echo "                    <tr>
                        <td>";
            // line 56
            echo gettext("Expires at");
            echo "</td>
                        <td align=\"right\">";
            // line 57
            echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["license"] ?? null), "expires_at", [], "any", false, false, false, 57), "Y-m-d"), "html", null, true);
            echo "</td>
                    </tr>
                    ";
        }
        // line 60
        echo "                    
                    <tr>
                        <td>";
        // line 62
        echo gettext("IP");
        echo "</td>
                        <td align=\"right\">";
        // line 63
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["env"] ?? null), "ip", [], "any", false, false, false, 63), "html", null, true);
        echo "</td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>
    
</div>
";
    }

    public function getTemplateName()
    {
        return "mod_system_index.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  232 => 63,  228 => 62,  224 => 60,  218 => 57,  214 => 56,  211 => 55,  209 => 54,  203 => 51,  199 => 50,  193 => 47,  189 => 46,  184 => 43,  181 => 42,  179 => 41,  171 => 38,  167 => 37,  163 => 36,  159 => 35,  150 => 28,  129 => 25,  121 => 24,  111 => 23,  106 => 22,  89 => 21,  83 => 18,  74 => 12,  70 => 11,  64 => 7,  60 => 6,  53 => 3,  49 => 1,  47 => 5,  45 => 4,  43 => 2,  36 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}
{% import \"macro_functions.phtml\" as mf %}
{% block meta_title %}{% trans 'Settings' %}{% endblock %}
{% set active_menu = 'system' %}
{% set params = admin.system_get_params %}
{% block content %}

<div class=\"widget simpleTabs\">

    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">{% trans 'Settings' %}</a></li>
        <li><a href=\"#tab-license\">{% trans 'License' %}</a></li>
    </ul>

    <div class=\"tabs_container\">
        <div class=\"fix\"></div>
        <div id=\"tab-index\" class=\"tab_content nopadding\">
            {{ mf.table_search }}
            <table class=\"tableStatic wide\">
                <tbody>
                    {% for ext in admin.extension_get_list({\"active\":1, \"has_settings\":1}|merge(request)) %}
                    <tr {{ loop.first ? 'style=\"border-top:0;\"' : '' }} class=\"hover-row\">
                        <td style=\"width: 32px;\"><a href=\"{{ 'extension/settings'|alink }}/{{ext.id}}\"><img src=\"{{ ext.icon_url }}\" alt=\"{{ext.name}}\" style=\"width: 32px; height: 32px;\"/></a></td>
                        <td style=\"border: 0; font-weight: bold;\"><a href=\"{{ 'extension/settings'|alink }}/{{ext.id}}\">{{ ext.name }}</a></td>
                        <td style=\"width: 5%; border: 0;\"><a class=\"bb-button btn14\" href=\"{{ 'extension/settings'|alink }}/{{ext.id}}\"><img src=\"images/icons/dark/play.png\" alt=\"\" class=\"icon\" title=\"{% trans 'Module settings' %}\"></a></td>
                    </tr>
                    {% endfor %}
                </tbody>
            </table>
        </div>

        <div id=\"tab-license\" class=\"tab_content nopadding\">

            <div class=\"help\">
                <h3>{% trans 'License' %}</h3>
                <p>{% trans 'After purchase you have received an e-mail with license key. Update license key to unlock all features of BoxBilling' %}</p>
                <p>{% trans 'To change license key, change <em>BB_LICENSE</em> value in <em>bb-config.php</em> file' %}. </p>
                <p><a href=\"{{ 'filemanager/ide'|alink({'open' : 'bb-config.php'}) }}\" target=\"_blank\" target=\"_blank\">{% trans 'Edit bb-config.php file' %}</a> (File must be writable by web server)</p>
            </div>

            {% set env = admin.system_env %}
            {% set license = admin.system_license_info %}
            <table class=\"tableStatic wide\">
                <tbody>
                    <tr class=\"noborder\">
                        <td>{% trans 'Licensed to' %}</td>
                        <td align=\"right\">{{ license.licensed_to }}</td>
                    </tr>
                    <tr>
                        <td>{% trans 'Key' %}</td>
                        <td align=\"right\">{{ license.key }}</td>
                    </tr>

                    {% if license.expires_at %}
                    <tr>
                        <td>{% trans 'Expires at' %}</td>
                        <td align=\"right\">{{ license.expires_at|date('Y-m-d') }}</td>
                    </tr>
                    {% endif %}
                    
                    <tr>
                        <td>{% trans 'IP' %}</td>
                        <td align=\"right\">{{ env.ip }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>
    
</div>
{% endblock %}", "mod_system_index.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-themes/admin_default/html/mod_system_index.phtml");
    }
}
