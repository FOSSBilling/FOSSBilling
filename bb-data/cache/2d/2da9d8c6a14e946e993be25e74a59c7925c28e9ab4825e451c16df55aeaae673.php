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

/* partial_menu.phtml */
class __TwigTemplate_a14aab44bc8b8a182c0e35c8202bba1b823d6d2c29deaf887ee154016be2909b extends \Twig\Template
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
        echo "<ul id=\"menu\">
";
        // line 2
        $context["navigation"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "extension_get_navigation", [0 => ["url" => twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_current_url", [], "any", false, false, false, 2)]], "method", false, false, false, 2);
        // line 3
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(($context["navigation"] ?? null));
        foreach ($context['_seq'] as $context["location"] => $context["group"]) {
            // line 4
            echo "    ";
            if (twig_get_attribute($this->env, $this->source, $context["group"], "subpages", [], "any", false, false, false, 4)) {
                // line 5
                echo "        <li class=\"";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["group"], "class", [], "any", false, false, false, 5), "html", null, true);
                echo "\" data-nav-index=\"";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["group"], "index", [], "any", false, false, false, 5), "html", null, true);
                echo "\" data-nav-location=\"";
                echo twig_escape_filter($this->env, $context["location"], "html", null, true);
                echo "\">
            <a class=\"exp corner\"";
                // line 6
                if ((twig_get_attribute($this->env, $this->source, $context["group"], "active", [], "any", false, false, false, 6) || (($context["active_menu"] ?? null) == $context["location"]))) {
                    echo " id=\"current\"";
                }
                echo " href=\"#\">
                <span><i class=\"";
                // line 7
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["group"], "sprite_class", [], "any", false, false, false, 7), "html", null, true);
                echo "\"></i>";
                echo twig_escape_filter($this->env, gettext(twig_get_attribute($this->env, $this->source, $context["group"], "label", [], "any", false, false, false, 7)), "html", null, true);
                echo "</span>
            </a>
            <ul class=\"sub\" style=\"display: none;\">
            ";
                // line 10
                $context['_parent'] = $context;
                $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, $context["group"], "subpages", [], "any", false, false, false, 10));
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
                foreach ($context['_seq'] as $context["_key"] => $context["subpage"]) {
                    // line 11
                    echo "                <li class=\"";
                    if (twig_get_attribute($this->env, $this->source, $context["loop"], "last", [], "any", false, false, false, 11)) {
                        echo "last";
                    }
                    if (twig_get_attribute($this->env, $this->source, $context["subpage"], "active", [], "any", false, false, false, 11)) {
                        echo " active";
                    }
                    echo "\" data-nav-index=\"";
                    echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["subpage"], "index", [], "any", false, false, false, 11), "html", null, true);
                    echo "\">
                    <a class=\"";
                    // line 12
                    echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["subpage"], "class", [], "any", false, false, false, 12), "html", null, true);
                    echo "\" href=\"";
                    echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["subpage"], "uri", [], "any", false, false, false, 12), "html", null, true);
                    echo "\">";
                    echo twig_escape_filter($this->env, gettext(twig_get_attribute($this->env, $this->source, $context["subpage"], "label", [], "any", false, false, false, 12)), "html", null, true);
                    echo "</a>
                </li>
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
                unset($context['_seq'], $context['_iterated'], $context['_key'], $context['subpage'], $context['_parent'], $context['loop']);
                $context = array_intersect_key($context, $_parent) + $_parent;
                // line 15
                echo "            </ul>
        </li>
    ";
            }
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['location'], $context['group'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 19
        echo "</ul>";
    }

    public function getTemplateName()
    {
        return "partial_menu.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  131 => 19,  122 => 15,  101 => 12,  89 => 11,  72 => 10,  64 => 7,  58 => 6,  49 => 5,  46 => 4,  42 => 3,  40 => 2,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("<ul id=\"menu\">
{% set navigation = admin.extension_get_navigation({\"url\":guest.system_current_url}) %}
{% for location,group in navigation %}
    {% if group.subpages %}
        <li class=\"{{ group.class }}\" data-nav-index=\"{{ group.index }}\" data-nav-location=\"{{location}}\">
            <a class=\"exp corner\"{% if group.active or active_menu == location %} id=\"current\"{% endif %} href=\"#\">
                <span><i class=\"{{ group.sprite_class }}\"></i>{{ group.label|trans }}</span>
            </a>
            <ul class=\"sub\" style=\"display: none;\">
            {% for subpage in group.subpages %}
                <li class=\"{% if loop.last %}last{% endif %}{% if subpage.active %} active{% endif %}\" data-nav-index=\"{{ subpage.index }}\">
                    <a class=\"{{ subpage.class }}\" href=\"{{ subpage.uri}}\">{{ subpage.label|trans }}</a>
                </li>
            {% endfor %}
            </ul>
        </li>
    {% endif %}
{% endfor %}
</ul>", "partial_menu.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/src/bb-themes/admin_default/html/partial_menu.phtml");
    }
}
