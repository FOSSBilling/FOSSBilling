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

/* macro_functions.phtml */
class __TwigTemplate_dece5404914c8b8eda8c79b5e7ce734c949c4b6c54eb3bcbf23496279ae84dd9 extends Template
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
        // line 9
        echo "
";
        // line 17
        echo "
";
        // line 22
        echo "
";
        // line 27
        echo "
";
        // line 31
        echo "
";
        // line 36
        echo "
";
        // line 43
        echo "
";
    }

    // line 1
    public function macro_selectbox($__name__ = null, $__options__ = null, $__selected__ = null, $__required__ = null, $__nullOption__ = null, ...$__varargs__)
    {
        $macros = $this->macros;
        $context = $this->env->mergeGlobals([
            "name" => $__name__,
            "options" => $__options__,
            "selected" => $__selected__,
            "required" => $__required__,
            "nullOption" => $__nullOption__,
            "varargs" => $__varargs__,
        ]);

        $blocks = [];

        ob_start();
        try {
            // line 2
            echo "    <select name=\"";
            echo twig_escape_filter($this->env, ($context["name"] ?? null), "html", null, true);
            echo "\" ";
            if (($context["required"] ?? null)) {
                echo "required=\"required\"";
            }
            echo ">
        ";
            // line 3
            if (($context["nullOption"] ?? null)) {
                echo "<option value=\"\">-- ";
                echo twig_escape_filter($this->env, ($context["nullOption"] ?? null), "html", null, true);
                echo " --</option>";
            }
            // line 4
            echo "        ";
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(($context["options"] ?? null));
            foreach ($context['_seq'] as $context["val"] => $context["label"]) {
                // line 5
                echo "        <option value=\"";
                echo twig_escape_filter($this->env, $context["val"], "html", null, true);
                echo "\" label=\"";
                echo twig_escape_filter($this->env, $context["label"]);
                echo "\" ";
                if ((($context["selected"] ?? null) == $context["val"])) {
                    echo "selected=\"selected\"";
                }
                echo ">";
                echo twig_escape_filter($this->env, $context["label"]);
                echo "</option>
        ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['val'], $context['label'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 7
            echo "    </select>
";

            return ('' === $tmp = ob_get_contents()) ? '' : new Markup($tmp, $this->env->getCharset());
        } finally {
            ob_end_clean();
        }
    }

    // line 10
    public function macro_selectboxtld($__name__ = null, $__options__ = null, $__selected__ = null, $__required__ = null, ...$__varargs__)
    {
        $macros = $this->macros;
        $context = $this->env->mergeGlobals([
            "name" => $__name__,
            "options" => $__options__,
            "selected" => $__selected__,
            "required" => $__required__,
            "varargs" => $__varargs__,
        ]);

        $blocks = [];

        ob_start();
        try {
            // line 11
            echo "    <select name=\"";
            echo twig_escape_filter($this->env, ($context["name"] ?? null), "html", null, true);
            echo "\" ";
            if (($context["required"] ?? null)) {
                echo "required=\"required\"";
            }
            echo " style=\"width:80px;\">
        ";
            // line 12
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(($context["options"] ?? null));
            foreach ($context['_seq'] as $context["_key"] => $context["data"]) {
                // line 13
                echo "        <option value=\"";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["data"], "tld", [], "any", false, false, false, 13), "html", null, true);
                echo "\" label=\"";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["data"], "tld", [], "any", false, false, false, 13), "html", null, true);
                echo "\" ";
                if ((($context["selected"] ?? null) == twig_get_attribute($this->env, $this->source, $context["data"], "tld", [], "any", false, false, false, 13))) {
                    echo "selected=\"selected\"";
                }
                echo ">";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["data"], "tld", [], "any", false, false, false, 13), "html", null, true);
                echo "</option>
        ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['data'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 15
            echo "    </select>
";

            return ('' === $tmp = ob_get_contents()) ? '' : new Markup($tmp, $this->env->getCharset());
        } finally {
            ob_end_clean();
        }
    }

    // line 19
    public function macro_currency_format($__price__ = null, $__currency__ = null, ...$__varargs__)
    {
        $macros = $this->macros;
        $context = $this->env->mergeGlobals([
            "price" => $__price__,
            "currency" => $__currency__,
            "varargs" => $__varargs__,
        ]);

        $blocks = [];

        ob_start();
        try {
            // line 20
            echo "    ";
            echo twig_money($this->env, ($context["price"] ?? null), ($context["currency"] ?? null));
            echo "
";

            return ('' === $tmp = ob_get_contents()) ? '' : new Markup($tmp, $this->env->getCharset());
        } finally {
            ob_end_clean();
        }
    }

    // line 24
    public function macro_currency($__price__ = null, $__currency__ = null, ...$__varargs__)
    {
        $macros = $this->macros;
        $context = $this->env->mergeGlobals([
            "price" => $__price__,
            "currency" => $__currency__,
            "varargs" => $__varargs__,
        ]);

        $blocks = [];

        ob_start();
        try {
            // line 25
            echo "    ";
            echo twig_money_convert($this->env, ($context["price"] ?? null), ($context["currency"] ?? null));
            echo "
";

            return ('' === $tmp = ob_get_contents()) ? '' : new Markup($tmp, $this->env->getCharset());
        } finally {
            ob_end_clean();
        }
    }

    // line 28
    public function macro_status_name($__status__ = null, ...$__varargs__)
    {
        $macros = $this->macros;
        $context = $this->env->mergeGlobals([
            "status" => $__status__,
            "varargs" => $__varargs__,
        ]);

        $blocks = [];

        ob_start();
        try {
            // line 29
            echo "    ";
            echo twig_escape_filter($this->env, gettext(twig_title_string_filter($this->env, twig_replace_filter(($context["status"] ?? null), ["_" => " "]))), "html", null, true);
            echo "
";

            return ('' === $tmp = ob_get_contents()) ? '' : new Markup($tmp, $this->env->getCharset());
        } finally {
            ob_end_clean();
        }
    }

    // line 33
    public function macro_period_name($__period__ = null, ...$__varargs__)
    {
        $macros = $this->macros;
        $context = $this->env->mergeGlobals([
            "period" => $__period__,
            "varargs" => $__varargs__,
        ]);

        $blocks = [];

        ob_start();
        try {
            // line 34
            echo "    ";
            echo twig_period_title($this->env, ($context["period"] ?? null));
            echo "
";

            return ('' === $tmp = ob_get_contents()) ? '' : new Markup($tmp, $this->env->getCharset());
        } finally {
            ob_end_clean();
        }
    }

    // line 37
    public function macro_markdown_quote($__text__ = null, ...$__varargs__)
    {
        $macros = $this->macros;
        $context = $this->env->mergeGlobals([
            "text" => $__text__,
            "varargs" => $__varargs__,
        ]);

        $blocks = [];

        ob_start();
        try {
            // line 38
            echo "
";
            // line 39
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(twig_split_filter($this->env, ($context["text"] ?? null), "
"));
            foreach ($context['_seq'] as $context["_key"] => $context["line"]) {
                // line 40
                echo "> ";
                echo twig_escape_filter($this->env, $context["line"], "html", null, true);
                echo "
";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['line'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;

            return ('' === $tmp = ob_get_contents()) ? '' : new Markup($tmp, $this->env->getCharset());
        } finally {
            ob_end_clean();
        }
    }

    // line 44
    public function macro_recaptcha(...$__varargs__)
    {
        $macros = $this->macros;
        $context = $this->env->mergeGlobals([
            "varargs" => $__varargs__,
        ]);

        $blocks = [];

        ob_start();
        try {
            // line 45
            echo "
";
            // line 46
            if (twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "extension_is_on", [0 => ["mod" => "spamchecker"]], "method", false, false, false, 46)) {
                // line 47
                echo "    ";
                $context["rc"] = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "spamchecker_recaptcha", [], "any", false, false, false, 47);
                // line 48
                echo "        ";
                if (twig_get_attribute($this->env, $this->source, ($context["rc"] ?? null), "enabled", [], "any", false, false, false, 48)) {
                    // line 49
                    echo "            ";
                    if ((twig_get_attribute($this->env, $this->source, ($context["rc"] ?? null), "version", [], "any", false, false, false, 49) == 2)) {
                        // line 50
                        echo "                <script src='https://www.google.com/recaptcha/api.js' async defer></script>
                <div class=\"g-recaptcha\" data-sitekey=\"";
                        // line 51
                        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["rc"] ?? null), "publickey", [], "any", false, false, false, 51), "html", null, true);
                        echo "\"></div>
            ";
                    }
                    // line 53
                    echo "        ";
                }
                // line 54
                echo "    ";
            }

            return ('' === $tmp = ob_get_contents()) ? '' : new Markup($tmp, $this->env->getCharset());
        } finally {
            ob_end_clean();
        }
    }

    public function getTemplateName()
    {
        return "macro_functions.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  356 => 54,  353 => 53,  348 => 51,  345 => 50,  342 => 49,  339 => 48,  336 => 47,  334 => 46,  331 => 45,  319 => 44,  303 => 40,  298 => 39,  295 => 38,  282 => 37,  270 => 34,  257 => 33,  245 => 29,  232 => 28,  220 => 25,  206 => 24,  194 => 20,  180 => 19,  170 => 15,  153 => 13,  149 => 12,  140 => 11,  124 => 10,  114 => 7,  97 => 5,  92 => 4,  86 => 3,  77 => 2,  60 => 1,  55 => 43,  52 => 36,  49 => 31,  46 => 27,  43 => 22,  40 => 17,  37 => 9,);
    }

    public function getSourceContext()
    {
        return new Source("{% macro selectbox(name, options, selected, required, nullOption) %}
    <select name=\"{{ name }}\" {% if required %}required=\"required\"{% endif%}>
        {% if nullOption %}<option value=\"\">-- {{ nullOption }} --</option>{% endif %}
        {% for val,label in options %}
        <option value=\"{{ val }}\" label=\"{{ label|e }}\" {% if selected == val %}selected=\"selected\"{% endif %}>{{ label|e }}</option>
        {% endfor %}
    </select>
{% endmacro %}

{% macro selectboxtld(name, options, selected, required) %}
    <select name=\"{{ name }}\" {% if required %}required=\"required\"{% endif%} style=\"width:80px;\">
        {% for data in options %}
        <option value=\"{{ data.tld }}\" label=\"{{ data.tld }}\" {% if selected == data.tld %}selected=\"selected\"{% endif %}>{{ data.tld }}</option>
        {% endfor %}
    </select>
{% endmacro %}

{# deprecated - use money filter #}
{% macro currency_format(price, currency) %}
    {{ price | money(currency) }}
{% endmacro %}

{# deprecated - use money_convert filter #}
{% macro currency(price, currency) %}
    {{ price | money_convert(currency) }}
{% endmacro %}

{% macro status_name(status) %}
    {{ status|replace({ '_': \" \" })|title|trans }}
{% endmacro %}

{# deprecated - use period_title filter #}
{% macro period_name(period) %}
    {{ period | period_title }}
{% endmacro %}

{% macro markdown_quote(text) %}

{% for line in text|split('\\n') %}
> {{ line }}
{% endfor %}
{% endmacro %}

{% macro recaptcha() %}

{% if guest.extension_is_on({\"mod\":\"spamchecker\"}) %}
    {% set rc = guest.spamchecker_recaptcha %}
        {% if rc.enabled %}
            {% if rc.version == 2 %}
                <script src='https://www.google.com/recaptcha/api.js' async defer></script>
                <div class=\"g-recaptcha\" data-sitekey=\"{{ rc.publickey }}\"></div>
            {% endif %}
        {% endif %}
    {% endif %}
{% endmacro %}
", "macro_functions.phtml", "/shared/httpd/up-boxbilling/FOSSBilling/src/bb-themes/boxbilling/html/macro_functions.phtml");
    }
}
