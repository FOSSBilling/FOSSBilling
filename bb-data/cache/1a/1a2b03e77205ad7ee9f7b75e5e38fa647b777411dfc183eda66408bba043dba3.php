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
class __TwigTemplate_588632fda869ae5787c9df57b62ad2f4e8a3dfe8119d57a85f105be09b838bad extends \Twig\Template
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
<div class=\"pagination pagination-centered\">
    <ul>
        <li ";
            // line 6
            if (( !twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 6) || (twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 6) == 1))) {
                echo "class=\"disabled\"";
            }
            echo ">
            <a href=\"";
            // line 7
            if ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 7) && (twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 7) != 1))) {
                echo "?";
                $context['_parent'] = $context;
                $context['_seq'] = twig_ensure_traversable(twig_array_merge(twig_array_merge([], ($context["request"] ?? null)), ["page" => 1]));
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
                    if ((twig_get_attribute($this->env, $this->source, $context["loop"], "last", [], "any", false, false, false, 7) == false)) {
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
            } else {
                echo "#";
            }
            echo "\">«</a>
        </li>

        <li ";
            // line 10
            if (( !twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 10) || (twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 10) == 1))) {
                echo "class=\"disabled\"";
            }
            echo ">
        <a href=\"";
            // line 11
            if ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 11) && (twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 11) != "1"))) {
                echo "?";
                $context['_parent'] = $context;
                $context['_seq'] = twig_ensure_traversable(twig_array_merge(twig_array_merge([], ($context["request"] ?? null)), ["page" => (twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 11) - 1)]));
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
            } else {
                echo "#";
            }
            echo "\"> <span class=\"awe-arrow-left\"></span> </a>
        </li>

        ";
            // line 14
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(range(1, twig_get_attribute($this->env, $this->source, ($context["list"] ?? null), "pages", [], "any", false, false, false, 14)));
            foreach ($context['_seq'] as $context["_key"] => $context["i"]) {
                // line 15
                echo "            <li  ";
                if (($context["i"] == ($context["page"] ?? null))) {
                    echo "class=\"active\" ";
                }
                echo ">
            ";
                // line 16
                if ((($context["i"] == twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 16)) || ( !twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 16) && ($context["i"] == 1)))) {
                    // line 17
                    echo "                <a href=\"#\" onclick=\"return false;\">";
                    echo twig_escape_filter($this->env, $context["i"], "html", null, true);
                    echo "</a>
            ";
                } else {
                    // line 19
                    echo "                <a href=\"?";
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
                        if ((twig_get_attribute($this->env, $this->source, $context["loop"], "last", [], "any", false, false, false, 19) == false)) {
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
                // line 21
                echo "            </li>
        ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['i'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 23
            echo "
        <li ";
            // line 24
            if ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 24) == twig_get_attribute($this->env, $this->source, ($context["list"] ?? null), "pages", [], "any", false, false, false, 24))) {
                echo "class=\"disabled\"";
            }
            echo ">
            <a href=\"";
            // line 25
            if ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 25) && (twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 25) != twig_get_attribute($this->env, $this->source, ($context["list"] ?? null), "pages", [], "any", false, false, false, 25)))) {
                echo "?";
                $context['_parent'] = $context;
                $context['_seq'] = twig_ensure_traversable(twig_array_merge(twig_array_merge([], ($context["request"] ?? null)), ["page" => (twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 25) + 1)]));
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
                    if ((twig_get_attribute($this->env, $this->source, $context["loop"], "last", [], "any", false, false, false, 25) == false)) {
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
            } elseif ( !twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 25)) {
                echo "?";
                $context['_parent'] = $context;
                $context['_seq'] = twig_ensure_traversable(twig_array_merge(twig_array_merge([], ($context["request"] ?? null)), ["page" => 2]));
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
                    if ((twig_get_attribute($this->env, $this->source, $context["loop"], "last", [], "any", false, false, false, 25) == false)) {
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
            } else {
                echo "#";
            }
            echo "\"><span class=\"awe-arrow-right\"></span> </a>
        </li>

        <li ";
            // line 28
            if ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 28) == twig_get_attribute($this->env, $this->source, ($context["list"] ?? null), "pages", [], "any", false, false, false, 28))) {
                echo "class=\"disabled\"";
            }
            echo ">
            <a href=\"";
            // line 29
            if (( !twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 29) || (twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 29) != twig_get_attribute($this->env, $this->source, ($context["list"] ?? null), "pages", [], "any", false, false, false, 29)))) {
                echo "?";
                $context['_parent'] = $context;
                $context['_seq'] = twig_ensure_traversable(twig_array_merge(twig_array_merge([], ($context["request"] ?? null)), ["page" => twig_get_attribute($this->env, $this->source, ($context["list"] ?? null), "pages", [], "any", false, false, false, 29)]));
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
                    if ((twig_get_attribute($this->env, $this->source, $context["loop"], "last", [], "any", false, false, false, 29) == false)) {
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
            } else {
                echo "#";
            }
            echo "\">»</a>
        </li>
    </ul>
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
        return array (  308 => 29,  302 => 28,  222 => 25,  216 => 24,  213 => 23,  206 => 21,  165 => 19,  159 => 17,  157 => 16,  150 => 15,  146 => 14,  102 => 11,  96 => 10,  52 => 7,  46 => 6,  41 => 3,  39 => 2,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% if list.pages > 1 %}
{% set page = list.page %}

<div class=\"pagination pagination-centered\">
    <ul>
        <li {% if not request.page or request.page == 1 %}class=\"disabled\"{% endif %}>
            <a href=\"{% if request.page and request.page != 1%}?{% for k,v in {}|merge(request)|merge({'page': 1}) %}{{k}}={{v}}{% if loop.last == FALSE %}&{%endif%}{% endfor %}{% else %}#{% endif %}\">«</a>
        </li>

        <li {% if not request.page or request.page == 1 %}class=\"disabled\"{% endif %}>
        <a href=\"{% if request.page  and request.page != '1' %}?{% for k,v in {}|merge(request)|merge({'page': request.page - 1}) %}{{k}}={{v}}{% if loop.last == FALSE %}&{%endif%}{% endfor %}{% else %}#{% endif %}\"> <span class=\"awe-arrow-left\"></span> </a>
        </li>

        {% for i in 1..list.pages %}
            <li  {% if i == page %}class=\"active\" {% endif%}>
            {% if i == request.page  or (not request.page and i == 1)%}
                <a href=\"#\" onclick=\"return false;\">{{ i }}</a>
            {%else%}
                <a href=\"?{% for k,v in {}|merge(request)|merge({'page': i}) %}{{k}}={{v}}{% if loop.last == FALSE %}&{%endif%}{% endfor %}\">{{ i }}</a>
            {% endif %}
            </li>
        {% endfor %}

        <li {% if request.page == list.pages %}class=\"disabled\"{% endif %}>
            <a href=\"{% if request.page and request.page != list.pages %}?{% for k,v in {}|merge(request)|merge({'page': request.page + 1}) %}{{k}}={{v}}{% if loop.last == FALSE %}&{%endif%}{% endfor %}{% elseif not request.page %}?{% for k,v in {}|merge(request)|merge({'page': 2}) %}{{k}}={{v}}{% if loop.last == FALSE %}&{%endif%}{% endfor %}{% else %}#{% endif %}\"><span class=\"awe-arrow-right\"></span> </a>
        </li>

        <li {% if request.page == list.pages %}class=\"disabled\"{% endif %}>
            <a href=\"{% if not request.page or request.page != list.pages %}?{% for k,v in {}|merge(request)|merge({'page': list.pages}) %}{{k}}={{v}}{% if loop.last == FALSE %}&{%endif%}{% endfor %}{% else %}#{% endif %}\">»</a>
        </li>
    </ul>
</div>
{% endif %}", "partial_pagination.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-themes/huraga/html/partial_pagination.phtml");
    }
}
