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

/* mod_orderbutton_currency.phtml */
class __TwigTemplate_6403e440208a7f533aa12cf24e0e5633bd49ea2e8bc933e6739d2cbc9fd0448a extends \Twig\Template
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
        $context["currencies"] = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "currency_get_pairs", [], "any", false, false, false, 1);
        // line 2
        if ((twig_length_filter($this->env, ($context["currencies"] ?? null)) > 1)) {
            // line 3
            echo "        <select name=\"currency\" class=\"currency_selector input-mini pull-right\">
            ";
            // line 4
            $context["selected"] = twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "cart_get_currency", [], "any", false, false, false, 4), "code", [], "any", false, false, false, 4);
            // line 5
            echo "            ";
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(($context["currencies"] ?? null));
            foreach ($context['_seq'] as $context["code"] => $context["title"]) {
                // line 6
                echo "            <option value=\"";
                echo twig_escape_filter($this->env, $context["code"], "html", null, true);
                echo "\" class=\"currency_";
                echo twig_escape_filter($this->env, $context["code"], "html", null, true);
                echo "\"";
                if (($context["code"] == ($context["selected"] ?? null))) {
                    echo " selected=\"selected\"";
                }
                echo ">";
                echo twig_escape_filter($this->env, $context["code"], "html", null, true);
                echo " - ";
                echo twig_escape_filter($this->env, $context["title"], "html", null, true);
                echo "</option>
            ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['code'], $context['title'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 8
            echo "        </select>
";
        }
    }

    public function getTemplateName()
    {
        return "mod_orderbutton_currency.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  70 => 8,  51 => 6,  46 => 5,  44 => 4,  41 => 3,  39 => 2,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% set currencies = guest.currency_get_pairs %}
{% if currencies|length > 1 %}
        <select name=\"currency\" class=\"currency_selector input-mini pull-right\">
            {% set selected = guest.cart_get_currency.code %}
            {% for code,title in currencies %}
            <option value=\"{{ code }}\" class=\"currency_{{ code }}\"{% if code == selected %} selected=\"selected\"{% endif %}>{{ code }} - {{ title }}</option>
            {% endfor %}
        </select>
{% endif %}", "mod_orderbutton_currency.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/src/bb-modules/Orderbutton/html_client/mod_orderbutton_currency.phtml");
    }
}
