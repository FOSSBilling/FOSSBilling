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

/* partial_pagination.phtml */
class __TwigTemplate_055c5011180c4a7d29c67981fd93859603a34c07628c5709055565cf82a5e526 extends Template
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
        if ((twig_get_attribute($this->env, $this->source, ($context["list"] ?? null), "pages", [], "any", false, false, false, 1) > 1)) {
            // line 2
            $context["page"] = twig_get_attribute($this->env, $this->source, ($context["list"] ?? null), "page", [], "any", false, false, false, 2);
            // line 3
            echo "
<div class=\"paginator\">
<ul>
  ";
            // line 6
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(range(1, twig_get_attribute($this->env, $this->source, ($context["list"] ?? null), "pages", [], "any", false, false, false, 6)));
            foreach ($context['_seq'] as $context["_key"] => $context["i"]) {
                // line 7
                echo "    <li>
    ";
                // line 8
                if (($context["i"] == ($context["page"] ?? null))) {
                    // line 9
                    echo "        <a class=\"bb-button bb-button-submit\" href=\"#\" onclick=\"return false;\">";
                    echo twig_escape_filter($this->env, $context["i"], "html", null, true);
                    echo "</a>
    ";
                } else {
                    // line 11
                    echo "        <a class=\"bb-button\" href=\"?";
                    $context['_parent'] = $context;
                    $context['_seq'] = twig_ensure_traversable(twig_array_merge(twig_array_merge([], ($context["request"] ?? null)), ["page" => $context["i"]]));
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
                    foreach ($context['_seq'] as $context["k"] => $context["v"]) {
                        echo twig_escape_filter($this->env, $context["k"], "html", null, true);
                        echo "=";
                        echo twig_escape_filter($this->env, $context["v"], "html", null, true);
                        if ((twig_get_attribute($this->env, $this->source, $context["loop"], "last", [], "any", false, false, false, 11) == false)) {
                            echo "&";
                        }
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
                    unset($context['_seq'], $context['_iterated'], $context['k'], $context['v'], $context['_parent'], $context['loop']);
                    $context = array_intersect_key($context, $_parent) + $_parent;
                    echo "\">";
                    echo twig_escape_filter($this->env, $context["i"], "html", null, true);
                    echo "</a>
    ";
                }
                // line 13
                echo "    </li>
  ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['i'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 15
            echo "</ul>
    <div class=\"clear\"></div>
</div>
";
        }
    }

    public function getTemplateName()
    {
        return "partial_pagination.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  109 => 15,  102 => 13,  61 => 11,  55 => 9,  53 => 8,  50 => 7,  46 => 6,  41 => 3,  39 => 2,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% if list.pages > 1 %}
{% set page = list.page %}

<div class=\"paginator\">
<ul>
  {% for i in 1..list.pages %}
    <li>
    {% if i == page %}
        <a class=\"bb-button bb-button-submit\" href=\"#\" onclick=\"return false;\">{{ i }}</a>
    {%else%}
        <a class=\"bb-button\" href=\"?{% for k,v in {}|merge(request)|merge({ 'page': i }) %}{{ k }}={{ v }}{% if loop.last == FALSE %}&{% endif %}{% endfor %}\">{{ i }}</a>
    {% endif %}
    </li>
  {% endfor %}
</ul>
    <div class=\"clear\"></div>
</div>
{% endif %}", "partial_pagination.phtml", "/shared/httpd/up-boxbilling/FOSSBilling/src/bb-themes/boxbilling/html/partial_pagination.phtml");
    }
}
