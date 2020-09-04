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

/* mod_orderbutton_addons.phtml */
class __TwigTemplate_b41c3b3219095a62d19d0e5e3f79d25d03ada3aafcb67afcb19afacfe83824e2 extends \Twig\Template
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
        if ((twig_length_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "addons", [], "any", false, false, false, 1)) > 0)) {
            // line 2
            echo "<hr/>
<section>
    <header>
        <h2>";
            // line 5
            echo gettext("Addons & extras");
            echo "</h2>
    </header>
</section>
<div class=\"row-fluid\">
        <table class=\"table table-condensed\">
            <tbody>
            ";
            // line 11
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "addons", [], "any", false, false, false, 11));
            foreach ($context['_seq'] as $context["_key"] => $context["addon"]) {
                // line 12
                echo "            <label for=\"addon_";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["addon"], "id", [], "any", false, false, false, 12), "html", null, true);
                echo "\">
            <tr>
                <td>
                    <input type=\"hidden\" name=\"addons[";
                // line 15
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["addon"], "id", [], "any", false, false, false, 15), "html", null, true);
                echo "][selected]\" value=\"0\">
                    <input type=\"checkbox\" name=\"addons[";
                // line 16
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["addon"], "id", [], "any", false, false, false, 16), "html", null, true);
                echo "][selected]\" value=\"1\" id=\"addon_";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["addon"], "id", [], "any", false, false, false, 16), "html", null, true);
                echo "\">
                </td>

                <td ";
                // line 19
                if ( !twig_get_attribute($this->env, $this->source, $context["addon"], "icon_url", [], "any", false, false, false, 19)) {
                    echo "style=\"display: none\"";
                }
                echo ">
                    <label for=\"addon_";
                // line 20
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["addon"], "id", [], "any", false, false, false, 20), "html", null, true);
                echo "\"><img src=\"";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["addon"], "icon_url", [], "any", false, false, false, 20), "html", null, true);
                echo "\" alt=\"\" width=\"36\"/></label>
                </td>

                <td>
                    <label for=\"addon_";
                // line 24
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["addon"], "id", [], "any", false, false, false, 24), "html", null, true);
                echo "\"><h3>";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["addon"], "title", [], "any", false, false, false, 24), "html", null, true);
                echo "</h3></label>
                    ";
                // line 25
                echo twig_bbmd_filter($this->env, twig_get_attribute($this->env, $this->source, $context["addon"], "description", [], "any", false, false, false, 25));
                echo "
                </td>

                <td class=\"currency\">
                    <label for=\"addon_";
                // line 29
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["addon"], "id", [], "any", false, false, false, 29), "html", null, true);
                echo "\">
                    ";
                // line 30
                if ((twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["addon"], "pricing", [], "any", false, false, false, 30), "type", [], "any", false, false, false, 30) === "recurrent")) {
                    // line 31
                    echo "                        ";
                    $context["periods"] = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_periods", [], "any", false, false, false, 31);
                    // line 32
                    echo "                        <select name=\"addons[";
                    echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["addon"], "id", [], "any", false, false, false, 32), "html", null, true);
                    echo "][period]\" rel=\"addon-periods-";
                    echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["addon"], "id", [], "any", false, false, false, 32), "html", null, true);
                    echo "\">
                        ";
                    // line 33
                    $context['_parent'] = $context;
                    $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["addon"], "pricing", [], "any", false, false, false, 33), "recurrent", [], "any", false, false, false, 33));
                    foreach ($context['_seq'] as $context["code"] => $context["prices"]) {
                        // line 34
                        echo "                        ";
                        if (twig_get_attribute($this->env, $this->source, $context["prices"], "enabled", [], "any", false, false, false, 34)) {
                            // line 35
                            echo "                        <option value=\"";
                            echo twig_escape_filter($this->env, $context["code"], "html", null, true);
                            echo "\">";
                            echo twig_escape_filter($this->env, (($__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4 = ($context["periods"] ?? null)) && is_array($__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4) || $__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4 instanceof ArrayAccess ? ($__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4[$context["code"]] ?? null) : null), "html", null, true);
                            echo " ";
                            echo twig_money_convert($this->env, twig_get_attribute($this->env, $this->source, $context["prices"], "price", [], "any", false, false, false, 35));
                            echo " ";
                            if ((twig_get_attribute($this->env, $this->source, $context["prices"], "setup", [], "any", false, false, false, 35) != "0.00")) {
                                echo gettext("Setup:");
                                echo " ";
                                echo twig_money_convert($this->env, twig_get_attribute($this->env, $this->source, $context["prices"], "setup", [], "any", false, false, false, 35));
                            }
                            echo "</option>
                        ";
                        }
                        // line 37
                        echo "                        ";
                    }
                    $_parent = $context['_parent'];
                    unset($context['_seq'], $context['_iterated'], $context['code'], $context['prices'], $context['_parent'], $context['loop']);
                    $context = array_intersect_key($context, $_parent) + $_parent;
                    // line 38
                    echo "                    </select>
                    ";
                }
                // line 40
                echo "
                    ";
                // line 41
                if ((twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["addon"], "pricing", [], "any", false, false, false, 41), "type", [], "any", false, false, false, 41) === "once")) {
                    // line 42
                    echo "                    ";
                    echo twig_money_convert($this->env, (twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["addon"], "pricing", [], "any", false, false, false, 42), "once", [], "any", false, false, false, 42), "price", [], "any", false, false, false, 42) + twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["addon"], "pricing", [], "any", false, false, false, 42), "once", [], "any", false, false, false, 42), "setup", [], "any", false, false, false, 42)));
                    echo "
                    ";
                }
                // line 44
                echo "
                    ";
                // line 45
                if ((twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["addon"], "pricing", [], "any", false, false, false, 45), "type", [], "any", false, false, false, 45) === "free")) {
                    // line 46
                    echo "                    ";
                    echo twig_money_convert($this->env, 0);
                    echo "
                    ";
                }
                // line 48
                echo "                    </label>
                </td>
            </tr>
            ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['addon'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 52
            echo "            </tbody>
        </table>
</div>
";
        }
    }

    public function getTemplateName()
    {
        return "mod_orderbutton_addons.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  184 => 52,  175 => 48,  169 => 46,  167 => 45,  164 => 44,  158 => 42,  156 => 41,  153 => 40,  149 => 38,  143 => 37,  127 => 35,  124 => 34,  120 => 33,  113 => 32,  110 => 31,  108 => 30,  104 => 29,  97 => 25,  91 => 24,  82 => 20,  76 => 19,  68 => 16,  64 => 15,  57 => 12,  53 => 11,  44 => 5,  39 => 2,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% if product.addons|length > 0 %}
<hr/>
<section>
    <header>
        <h2>{% trans 'Addons & extras' %}</h2>
    </header>
</section>
<div class=\"row-fluid\">
        <table class=\"table table-condensed\">
            <tbody>
            {% for addon in product.addons %}
            <label for=\"addon_{{ addon.id }}\">
            <tr>
                <td>
                    <input type=\"hidden\" name=\"addons[{{ addon.id }}][selected]\" value=\"0\">
                    <input type=\"checkbox\" name=\"addons[{{ addon.id }}][selected]\" value=\"1\" id=\"addon_{{ addon.id }}\">
                </td>

                <td {% if not addon.icon_url%}style=\"display: none\"{% endif %}>
                    <label for=\"addon_{{ addon.id }}\"><img src=\"{{ addon.icon_url }}\" alt=\"\" width=\"36\"/></label>
                </td>

                <td>
                    <label for=\"addon_{{ addon.id }}\"><h3>{{ addon.title }}</h3></label>
                    {{ addon.description|bbmd }}
                </td>

                <td class=\"currency\">
                    <label for=\"addon_{{ addon.id }}\">
                    {% if addon.pricing.type is same as('recurrent') %}
                        {% set periods = guest.system_periods %}
                        <select name=\"addons[{{ addon.id }}][period]\" rel=\"addon-periods-{{ addon.id }}\">
                        {% for code,prices in addon.pricing.recurrent %}
                        {% if prices.enabled %}
                        <option value=\"{{code}}\">{{ periods[code] }} {{ prices.price | money_convert }} {% if prices.setup != '0.00' %}{% trans 'Setup:' %} {{prices.setup | money_convert}}{% endif %}</option>
                        {% endif %}
                        {% endfor %}
                    </select>
                    {% endif %}

                    {% if addon.pricing.type is same as('once') %}
                    {{ (addon.pricing.once.price + addon.pricing.once.setup) | money_convert }}
                    {% endif %}

                    {% if addon.pricing.type is same as('free') %}
                    {{ 0 | money_convert }}
                    {% endif %}
                    </label>
                </td>
            </tr>
            {% endfor %}
            </tbody>
        </table>
</div>
{% endif %}", "mod_orderbutton_addons.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-modules/Orderbutton/html_client/mod_orderbutton_addons.phtml");
    }
}
