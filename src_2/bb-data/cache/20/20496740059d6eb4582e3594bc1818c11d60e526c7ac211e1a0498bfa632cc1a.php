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
class __TwigTemplate_9c37d14ab4192bf953384b9d7755946e241f7f0c7ab9d37e711351ae9a821cac extends Template
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
            $context["currentPage"] = ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", true, true, false, 2)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 2), 1)) : (1));
            // line 3
            $context["paginator"] = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_paginator", [0 => ["total" => twig_get_attribute($this->env, $this->source, ($context["list"] ?? null), "total", [], "any", false, false, false, 3), "page" => ($context["currentPage"] ?? null), "per_page" => twig_get_attribute($this->env, $this->source, ($context["list"] ?? null), "per_page", [], "any", false, false, false, 3)]], "method", false, false, false, 3);
            // line 4
            echo "
<div class=\"pagination\">
    <ul class=\"pages\">
        ";
            // line 7
            if ((twig_get_attribute($this->env, $this->source, ($context["paginator"] ?? null), "currentpage", [], "any", false, false, false, 7) != 1)) {
                // line 8
                echo "        <li class=\"prev\"><a href=\"";
                echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter(($context["url"] ?? null), twig_array_merge(twig_slice($this->env, twig_array_merge([], ($context["request"] ?? null)), 1, twig_length_filter($this->env, ($context["request"] ?? null))), ["page" => (($context["currentPage"] ?? null) - 1)]));
                echo "\"><</a></li>
        ";
            }
            // line 10
            echo "        ";
            if ((twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["paginator"] ?? null), "range", [], "any", false, false, false, 10), 0, [], "any", false, false, false, 10) != 1)) {
                // line 11
                echo "            <li><a href=\"";
                echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter(($context["url"] ?? null), ["page" => 1]);
                echo "\" >1</a></li>
        ";
            }
            // line 13
            echo "        ";
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(range(twig_get_attribute($this->env, $this->source, ($context["paginator"] ?? null), "start", [], "any", false, false, false, 13), twig_get_attribute($this->env, $this->source, ($context["paginator"] ?? null), "end", [], "any", false, false, false, 13)));
            foreach ($context['_seq'] as $context["_key"] => $context["i"]) {
                // line 14
                echo "
            ";
                // line 15
                if (((twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["paginator"] ?? null), "range", [], "any", false, false, false, 15), 0, [], "any", false, false, false, 15) > 2) && ($context["i"] == twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["paginator"] ?? null), "range", [], "any", false, false, false, 15), 0, [], "any", false, false, false, 15)))) {
                    // line 16
                    echo "                ...
            ";
                }
                // line 18
                echo "
            ";
                // line 19
                if (($context["i"] == twig_get_attribute($this->env, $this->source, ($context["paginator"] ?? null), "currentpage", [], "any", false, false, false, 19))) {
                    // line 20
                    echo "                <li><a class=\"active\" href=\"#\" onclick=\"return false;\">";
                    echo twig_escape_filter($this->env, $context["i"], "html", null, true);
                    echo "</a></li>
            ";
                } else {
                    // line 22
                    echo "                <li><a href=\"";
                    echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter(($context["url"] ?? null), twig_array_merge(twig_slice($this->env, twig_array_merge([], ($context["request"] ?? null)), 1, twig_length_filter($this->env, ($context["request"] ?? null))), ["page" => $context["i"]]));
                    echo "\"> ";
                    echo twig_escape_filter($this->env, $context["i"], "html", null, true);
                    echo "</a></li>
            ";
                }
                // line 24
                echo "        ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['i'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 25
            echo "
        ";
            // line 26
            if ((((($__internal_compile_0 = twig_get_attribute($this->env, $this->source, ($context["paginator"] ?? null), "range", [], "any", false, false, false, 26)) && is_array($__internal_compile_0) || $__internal_compile_0 instanceof ArrayAccess ? ($__internal_compile_0[(twig_get_attribute($this->env, $this->source, ($context["paginator"] ?? null), "midrange", [], "any", false, false, false, 26) - 1)] ?? null) : null) < (twig_get_attribute($this->env, $this->source, ($context["paginator"] ?? null), "numpages", [], "any", false, false, false, 26) - 1)) && (twig_get_attribute($this->env, $this->source, ($context["paginator"] ?? null), "end", [], "any", false, false, false, 26) == (($__internal_compile_1 = twig_get_attribute($this->env, $this->source, ($context["paginator"] ?? null), "range", [], "any", false, false, false, 26)) && is_array($__internal_compile_1) || $__internal_compile_1 instanceof ArrayAccess ? ($__internal_compile_1[(twig_get_attribute($this->env, $this->source, ($context["paginator"] ?? null), "midrange", [], "any", false, false, false, 26) - 1)] ?? null) : null)))) {
                // line 27
                echo "            ...
            <li><a href=\"";
                // line 28
                echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter(($context["url"] ?? null), twig_array_merge(twig_slice($this->env, twig_array_merge([], ($context["request"] ?? null)), 1, twig_length_filter($this->env, ($context["request"] ?? null))), ["page" => twig_get_attribute($this->env, $this->source, ($context["paginator"] ?? null), "numpages", [], "any", false, false, false, 28)]));
                echo "\"> ";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["paginator"] ?? null), "numpages", [], "any", false, false, false, 28), "html", null, true);
                echo "</a></li>
        ";
            }
            // line 30
            echo "
        ";
            // line 31
            if ((twig_get_attribute($this->env, $this->source, ($context["paginator"] ?? null), "currentpage", [], "any", false, false, false, 31) != twig_get_attribute($this->env, $this->source, ($context["paginator"] ?? null), "numpages", [], "any", false, false, false, 31))) {
                // line 32
                echo "        <li class=\"next\"><a href=\"";
                echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter(($context["url"] ?? null), twig_array_merge(twig_slice($this->env, twig_array_merge([], ($context["request"] ?? null)), 1, twig_length_filter($this->env, ($context["request"] ?? null))), ["page" => (($context["currentPage"] ?? null) + 1)]));
                echo "\">></a></li>
        ";
            }
            // line 34
            echo "    </ul>
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
        return array (  130 => 34,  124 => 32,  122 => 31,  119 => 30,  112 => 28,  109 => 27,  107 => 26,  104 => 25,  98 => 24,  90 => 22,  84 => 20,  82 => 19,  79 => 18,  75 => 16,  73 => 15,  70 => 14,  65 => 13,  59 => 11,  56 => 10,  50 => 8,  48 => 7,  43 => 4,  41 => 3,  39 => 2,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% if list.pages > 1 %}
{% set currentPage = request.page|default(1) %}
{% set paginator = guest.system_paginator({\"total\":list.total, \"page\":currentPage, \"per_page\":list.per_page}) %}

<div class=\"pagination\">
    <ul class=\"pages\">
        {% if paginator.currentpage != 1 %}
        <li class=\"prev\"><a href=\"{{ url|alink({}|merge(request)|slice(1,request|length)|merge({'page': currentPage-1})) }}\"><</a></li>
        {% endif %}
        {% if(paginator.range.0 != 1) %}
            <li><a href=\"{{ url|alink({'page' : 1}) }}\" >1</a></li>
        {% endif %}
        {% for i in paginator.start..paginator.end %}

            {% if paginator.range.0 > 2 and i == paginator.range.0 %}
                ...
            {% endif %}

            {% if i==paginator.currentpage %}
                <li><a class=\"active\" href=\"#\" onclick=\"return false;\">{{i}}</a></li>
            {% else %}
                <li><a href=\"{{ url|alink({}|merge(request)|slice(1,request|length)|merge({'page': i})) }}\"> {{i}}</a></li>
            {% endif %}
        {% endfor %}

        {% if paginator.range[paginator.midrange -1] < paginator.numpages -1 and paginator.end == paginator.range[paginator.midrange-1] %}
            ...
            <li><a href=\"{{ url|alink({}|merge(request)|slice(1,request|length)|merge({'page': paginator.numpages})) }}\"> {{ paginator.numpages }}</a></li>
        {% endif %}

        {% if paginator.currentpage != paginator.numpages %}
        <li class=\"next\"><a href=\"{{ url|alink({}|merge(request)|slice(1,request|length)|merge({'page': currentPage+1}))}}\">></a></li>
        {% endif %}
    </ul>
</div>
{% endif %}", "partial_pagination.phtml", "/shared/httpd/up-boxbilling/FOSSBilling/src/bb-themes/admin_default/html/partial_pagination.phtml");
    }
}
