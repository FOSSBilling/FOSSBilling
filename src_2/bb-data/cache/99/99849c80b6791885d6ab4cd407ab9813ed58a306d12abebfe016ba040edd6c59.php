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

/* partial_bb_meta.phtml */
class __TwigTemplate_396bd4eb866cbb77a9da6298ad78e5a0731e6fa151226f3bac3ae9bdbae4134a extends Template
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
        echo "    <base href=\"";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["theme"] ?? null), "url", [], "any", false, false, false, 1), "html", null, true);
        echo "\"/>
    <meta property=\"bb:url\" content=\"";
        // line 2
        echo twig_escape_filter($this->env, twig_constant("BB_URL"), "html", null, true);
        echo "\"/>
    <meta property=\"bb:client_area\" content=\"";
        // line 3
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/");
        echo "\"/>
    ";
    }

    public function getTemplateName()
    {
        return "partial_bb_meta.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  46 => 3,  42 => 2,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("    <base href=\"{{ theme.url }}\"/>
    <meta property=\"bb:url\" content=\"{{ constant('BB_URL') }}\"/>
    <meta property=\"bb:client_area\" content=\"{{ '/'|link }}\"/>
    ", "partial_bb_meta.phtml", "/shared/httpd/up-boxbilling/FOSSBilling/src/bb-themes/admin_default/html/partial_bb_meta.phtml");
    }
}
