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

/* 404.phtml */
class __TwigTemplate_0fded5c805321fbc2771ed750d29525bd1a73f83226e6b8619378196138513ea extends \Twig\Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->blocks = [
            'meta_title' => [$this, 'block_meta_title'],
            'body_class' => [$this, 'block_body_class'],
            'body' => [$this, 'block_body'],
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
        $this->parent = $this->loadTemplate("layout_default.phtml", "404.phtml", 1);
        $this->parent->display($context, array_merge($this->blocks, $blocks));
    }

    // line 3
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo gettext("Error");
    }

    // line 5
    public function block_body_class($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo "error-page";
    }

    // line 6
    public function block_body($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 7
        echo "<section class=\"error-container\">
    ";
        // line 8
        if (twig_get_attribute($this->env, $this->source, ($context["exception"] ?? null), "getCode", [], "any", false, false, false, 8)) {
            // line 9
            echo "        <h1>";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["exception"] ?? null), "getCode", [], "any", false, false, false, 9), "html", null, true);
            echo "</h1>
    ";
        } else {
            // line 11
            echo "        <h1>404</h1>
    ";
        }
        // line 13
        echo "    <p class=\"description\">";
        echo gettext("Whoops! This is not the web page you are looking for.");
        echo "</p>
    <p class=\"alert alert-danger\">";
        // line 14
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["exception"] ?? null), "getMessage", [], "any", false, false, false, 14), "html", null, true);
        echo ".</p>
    <a href=\"";
        // line 15
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/");
        echo "\" class=\"btn btn-alt btn-primary btn-large\" title=\"Back to Homepage\">Back to Homepage</a>
</section>
";
    }

    public function getTemplateName()
    {
        return "404.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  90 => 15,  86 => 14,  81 => 13,  77 => 11,  71 => 9,  69 => 8,  66 => 7,  62 => 6,  55 => 5,  48 => 3,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends \"layout_default.phtml\" %}

{% block meta_title %}{% trans 'Error' %}{% endblock %}

{% block body_class %}error-page{% endblock %}
{% block body %}
<section class=\"error-container\">
    {% if exception.getCode %}
        <h1>{{ exception.getCode }}</h1>
    {% else %}
        <h1>404</h1>
    {% endif %}
    <p class=\"description\">{% trans 'Whoops! This is not the web page you are looking for.' %}</p>
    <p class=\"alert alert-danger\">{{ exception.getMessage }}.</p>
    <a href=\"{{ '/' | link}}\" class=\"btn btn-alt btn-primary btn-large\" title=\"Back to Homepage\">Back to Homepage</a>
</section>
{% endblock %}", "404.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-themes/huraga/html/404.phtml");
    }
}
