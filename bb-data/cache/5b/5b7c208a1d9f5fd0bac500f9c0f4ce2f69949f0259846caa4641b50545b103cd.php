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

/* __string_template__0ed430260a3625679a4b89b147c22b7ffeb0954b957951517b9f719bb9dce584 */
class __TwigTemplate_fa5db484aff5a55b65e3e73e66cd513e80d6bb4f423dae43c09eaffa124d7cf9 extends \Twig\Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
            '__internal_2dbcae69fdd243df294bbf748f60bddb8fbffab8e15f38fa8e6321620514cf2d' => [$this, 'block___internal_2dbcae69fdd243df294bbf748f60bddb8fbffab8e15f38fa8e6321620514cf2d'],
        ];
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 1
        echo "
";
        // line 2
        echo twig_markdown_filter($this->env,         $this->renderBlock("__internal_2dbcae69fdd243df294bbf748f60bddb8fbffab8e15f38fa8e6321620514cf2d", $context, $blocks));
    }

    public function block___internal_2dbcae69fdd243df294bbf748f60bddb8fbffab8e15f38fa8e6321620514cf2d($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 3
        echo "Hello ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["c"] ?? null), "first_name", [], "any", false, false, false, 3), "html", null, true);
        echo " ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["c"] ?? null), "last_name", [], "any", false, false, false, 3), "html", null, true);
        echo ",

This is to notify that proforma invoice ";
        // line 5
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "id", [], "any", false, false, false, 5), "html", null, true);
        echo " was generated on ";
        echo twig_escape_filter($this->env, $this->extensions['Box_TwigExtensions']->twig_bb_date(twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "created_at", [], "any", false, false, false, 5)), "html", null, true);
        echo ".
Amount Due: ";
        // line 6
        echo twig_money($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "total", [], "any", false, false, false, 6), twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "currency", [], "any", false, false, false, 6));
        echo "
Due Date: ";
        // line 7
        echo twig_escape_filter($this->env, $this->extensions['Box_TwigExtensions']->twig_bb_date(twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "due_at", [], "any", false, false, false, 7)), "html", null, true);
        echo "

You can view and pay the invoice at: ";
        // line 9
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("invoice");
        echo "/";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "hash", [], "any", false, false, false, 9), "html", null, true);
        echo "

Login to members area: ";
        // line 11
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("login", ["email" => twig_get_attribute($this->env, $this->source, ($context["c"] ?? null), "email", [], "any", false, false, false, 11)]);
        echo "

";
        // line 13
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_company", [], "any", false, false, false, 13), "signature", [], "any", false, false, false, 13), "html", null, true);
        echo "

";
    }

    public function getTemplateName()
    {
        return "__string_template__0ed430260a3625679a4b89b147c22b7ffeb0954b957951517b9f719bb9dce584";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  83 => 13,  78 => 11,  71 => 9,  66 => 7,  62 => 6,  56 => 5,  48 => 3,  41 => 2,  38 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("
{% filter markdown %}
Hello {{ c.first_name }} {{ c.last_name }},

This is to notify that proforma invoice {{ invoice.id }} was generated on {{ invoice.created_at|bb_date }}.
Amount Due: {{ invoice.total | money(invoice.currency) }}
Due Date: {{invoice.due_at|bb_date}}

You can view and pay the invoice at: {{'invoice'|link}}/{{invoice.hash}}

Login to members area: {{'login'|link({'email' : c.email }) }}

{{ guest.system_company.signature }}

{% endfilter %}
", "__string_template__0ed430260a3625679a4b89b147c22b7ffeb0954b957951517b9f719bb9dce584", "");
    }
}
