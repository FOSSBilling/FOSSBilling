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

/* partial_extensions.phtml */
class __TwigTemplate_351de0f26bfefc47bf5cd3cca6971f7c408c20b6f23e883587a1a348067ec73a extends \Twig\Template
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
        if (($context["header"] ?? null)) {
            // line 2
            echo "<div class=\"help\">
    <h5>";
            // line 3
            echo twig_escape_filter($this->env, ($context["header"] ?? null), "html", null, true);
            echo "</h5>
</div>
";
        }
        // line 6
        echo "<table class=\"tableStatic wide\">
    <thead>
        <tr>
            <td>&nbsp;</td>
            <td style=\"width: 30%\">Extension</td>
            <td>Description</td>
            <td width=\"18%\">&nbsp;</td>
        </tr>
    </thead>

    <tbody>
        ";
        // line 17
        $context["extensions"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "extension_get_latest", [0 => ["type" => ($context["type"] ?? null)]], "method", false, false, false, 17);
        // line 18
        echo "        ";
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(($context["extensions"] ?? null));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["_key"] => $context["extension"]) {
            // line 19
            echo "        <tr>
            <td><img src=\"";
            // line 20
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["extension"], "icon_url", [], "any", false, false, false, 20), "html", null, true);
            echo "\" alt=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["extension"], "name", [], "any", false, false, false, 20), "html", null, true);
            echo "\" style=\"width: 32px; height: 32px;\"/></td>
            <td>
                <a class=\"bb-button\" href=\"";
            // line 22
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["extension"], "project_url", [], "any", false, false, false, 22), "html", null, true);
            echo "\" target=\"_blank\" title=\"View extension details\">";
            echo twig_escape_filter($this->env, twig_truncate_filter($this->env, twig_get_attribute($this->env, $this->source, $context["extension"], "name", [], "any", false, false, false, 22), 40), "html", null, true);
            echo " ";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["extension"], "version", [], "any", false, false, false, 22), "html", null, true);
            echo "</a>
                <br/>by <a href=\"";
            // line 23
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["extension"], "author_url", [], "any", false, false, false, 23), "html", null, true);
            echo "\" target=\"_blank\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["extension"], "author", [], "any", false, false, false, 23), "html", null, true);
            echo "</a>
            </td>
            <td>";
            // line 25
            echo twig_escape_filter($this->env, twig_truncate_filter($this->env, twig_get_attribute($this->env, $this->source, $context["extension"], "description", [], "any", false, false, false, 25), 150), "html", null, true);
            echo "</td>
            <td class=\"actions\">
                <a class=\"bb-button btn14 api-link\" data-api-confirm=\"By installing '";
            // line 27
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["extension"], "name", [], "any", false, false, false, 27), "html", null, true);
            echo "' you agree with terms and conditions. Install only extensions you trust. Continue?\" data-api-reload=\"1\" href=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/extension/install", ["type" => twig_get_attribute($this->env, $this->source, $context["extension"], "type", [], "any", false, false, false, 27), "id" => twig_get_attribute($this->env, $this->source, $context["extension"], "id", [], "any", false, false, false, 27)]);
            echo "\" title=\"Install extension\"><img src=\"images/icons/dark/cog.png\" alt=\"\"></a>
                <a class=\"bb-button btn14\" href=\"";
            // line 28
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["extension"], "project_url", [], "any", false, false, false, 28), "html", null, true);
            echo "\" target=\"_blank\" title=\"Details\"><img src=\"images/icons/dark/globe.png\" alt=\"\"></a>
                <a class=\"bb-button btn14\" href=\"";
            // line 29
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["extension"], "download_url", [], "any", false, false, false, 29), "html", null, true);
            echo "\" title=\"Download ";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["extension"], "name", [], "any", false, false, false, 29), "html", null, true);
            echo "\"><img src=\"images/icons/dark/download.png\" alt=\"\"></a>
            </td>
        </tr>
        ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 33
            echo "        <tr>
            <td colspan=\"4\" class=\"aligncenter\"><a href=\"http://extensions.boxbilling.com/\" target=\"_blank\">Explore BoxBilling extensions</a></td>
        </tr>
        ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['extension'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 37
        echo "    </tbody>
    <tfoot>
        <tr>
            <td colspan=\"4\"></td>
        </tr>
    </tfoot>

</table>";
    }

    public function getTemplateName()
    {
        return "partial_extensions.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  129 => 37,  120 => 33,  109 => 29,  105 => 28,  99 => 27,  94 => 25,  87 => 23,  79 => 22,  72 => 20,  69 => 19,  63 => 18,  61 => 17,  48 => 6,  42 => 3,  39 => 2,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% if header %}
<div class=\"help\">
    <h5>{{ header }}</h5>
</div>
{% endif %}
<table class=\"tableStatic wide\">
    <thead>
        <tr>
            <td>&nbsp;</td>
            <td style=\"width: 30%\">Extension</td>
            <td>Description</td>
            <td width=\"18%\">&nbsp;</td>
        </tr>
    </thead>

    <tbody>
        {% set extensions = admin.extension_get_latest({\"type\":type}) %}
        {% for extension in extensions %}
        <tr>
            <td><img src=\"{{ extension.icon_url }}\" alt=\"{{ extension.name }}\" style=\"width: 32px; height: 32px;\"/></td>
            <td>
                <a class=\"bb-button\" href=\"{{ extension.project_url }}\" target=\"_blank\" title=\"View extension details\">{{ extension.name|truncate(40) }} {{ extension.version }}</a>
                <br/>by <a href=\"{{extension.author_url}}\" target=\"_blank\">{{extension.author}}</a>
            </td>
            <td>{{ extension.description|truncate(150) }}</td>
            <td class=\"actions\">
                <a class=\"bb-button btn14 api-link\" data-api-confirm=\"By installing '{{extension.name}}' you agree with terms and conditions. Install only extensions you trust. Continue?\" data-api-reload=\"1\" href=\"{{ 'api/admin/extension/install'|link ({'type' : extension.type, 'id' : extension.id }) }}\" title=\"Install extension\"><img src=\"images/icons/dark/cog.png\" alt=\"\"></a>
                <a class=\"bb-button btn14\" href=\"{{ extension.project_url }}\" target=\"_blank\" title=\"Details\"><img src=\"images/icons/dark/globe.png\" alt=\"\"></a>
                <a class=\"bb-button btn14\" href=\"{{ extension.download_url }}\" title=\"Download {{ extension.name }}\"><img src=\"images/icons/dark/download.png\" alt=\"\"></a>
            </td>
        </tr>
        {% else %}
        <tr>
            <td colspan=\"4\" class=\"aligncenter\"><a href=\"http://extensions.boxbilling.com/\" target=\"_blank\">Explore BoxBilling extensions</a></td>
        </tr>
        {% endfor %}
    </tbody>
    <tfoot>
        <tr>
            <td colspan=\"4\"></td>
        </tr>
    </tfoot>

</table>", "partial_extensions.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-themes/admin_default/html/partial_extensions.phtml");
    }
}
