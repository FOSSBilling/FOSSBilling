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
class __TwigTemplate_1bfc0fa7529a65c08d7027f97de3a7b11e0b3305b116ae04d821e654db1ed189 extends \Twig\Template
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
        // line 45
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
            // line 41
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(twig_split_filter($this->env, ($context["text"] ?? null), "
"));
            foreach ($context['_seq'] as $context["_key"] => $context["line"]) {
                // line 42
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

    // line 47
    public function macro_recaptcha(...$__varargs__)
    {
        $macros = $this->macros;
        $context = $this->env->mergeGlobals([
            "varargs" => $__varargs__,
        ]);

        $blocks = [];

        ob_start();
        try {
            // line 48
            echo "
";
            // line 49
            if (twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "extension_is_on", [0 => ["mod" => "spamchecker"]], "method", false, false, false, 49)) {
                // line 50
                $context["rc"] = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "spamchecker_recaptcha", [], "any", false, false, false, 50);
                // line 51
                echo "    ";
                if (twig_get_attribute($this->env, $this->source, ($context["rc"] ?? null), "enabled", [], "any", false, false, false, 51)) {
                    // line 52
                    echo "        ";
                    if ((twig_get_attribute($this->env, $this->source, ($context["rc"] ?? null), "version", [], "any", false, false, false, 52) == 2)) {
                        // line 53
                        echo "            <script src='https://www.google.com/recaptcha/api.js' async defer></script>
            <div class=\"g-recaptcha\" data-sitekey=\"";
                        // line 54
                        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["rc"] ?? null), "publickey", [], "any", false, false, false, 54), "html", null, true);
                        echo "\"></div>
        ";
                    } else {
                        // line 56
                        echo "
        ";
                        // line 57
                        $context["server"] = "https://www.google.com/recaptcha/api";
                        // line 58
                        echo "           <script type=\"text/javascript\">
                var RecaptchaOptions = {
                theme : 'custom',
                custom_theme_widget: 'recaptcha_widget'
            };
            </script>

            <div id=\"recaptcha_widget\" style=\"display:none\">

            <div id=\"recaptcha_image\"></div>
            <div class=\"recaptcha_only_if_incorrect_sol\" style=\"color:red\">";
                        // line 68
                        echo gettext("Incorrect please try again");
                        echo "</div>

            <p>
                <label>";
                        // line 71
                        echo gettext("Enter the words above");
                        echo "</label>
                <br/>
                <input type=\"text\" id=\"recaptcha_response_field\" name=\"recaptcha_response_field\" style=\"width: 245px\"/>
                    <a class=\"bb-button\" href=\"javascript:Recaptcha.reload()\" style=\"float: right\"><span class=\"dark-icon i-reload\"></span></a>
            </p>
            </div>

            <script type=\"text/javascript\" src=\"";
                        // line 78
                        echo twig_escape_filter($this->env, ($context["server"] ?? null), "html", null, true);
                        echo "/challenge?k=";
                        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["rc"] ?? null), "publickey", [], "any", false, false, false, 78), "html", null, true);
                        echo "\"></script>
            <noscript>
                <iframe src=\"";
                        // line 80
                        echo twig_escape_filter($this->env, ($context["server"] ?? null), "html", null, true);
                        echo "/noscript?k=";
                        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["rc"] ?? null), "publickey", [], "any", false, false, false, 80), "html", null, true);
                        echo "\" height=\"300\" width=\"500\" frameborder=\"0\"></iframe><br/>
                <textarea name=\"recaptcha_challenge_field\" rows=\"3\" cols=\"40\"></textarea>
                <input type=\"hidden\" name=\"recaptcha_response_field\" value=\"manual_challenge\"/>
            </noscript>
        ";
                    }
                    // line 85
                    echo "    ";
                }
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
        return array (  405 => 85,  395 => 80,  388 => 78,  378 => 71,  372 => 68,  360 => 58,  358 => 57,  355 => 56,  350 => 54,  347 => 53,  344 => 52,  341 => 51,  339 => 50,  337 => 49,  334 => 48,  322 => 47,  306 => 42,  301 => 41,  296 => 38,  283 => 37,  271 => 34,  258 => 33,  246 => 29,  233 => 28,  221 => 25,  207 => 24,  195 => 20,  181 => 19,  171 => 15,  154 => 13,  150 => 12,  141 => 11,  125 => 10,  115 => 7,  98 => 5,  93 => 4,  87 => 3,  78 => 2,  61 => 1,  55 => 45,  52 => 36,  49 => 31,  46 => 27,  43 => 22,  40 => 17,  37 => 9,);
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
    {{ status|replace({'_': \" \"})|title|trans }}
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
        {% else %}

        {% set server = \"https://www.google.com/recaptcha/api\" %}
           <script type=\"text/javascript\">
                var RecaptchaOptions = {
                theme : 'custom',
                custom_theme_widget: 'recaptcha_widget'
            };
            </script>

            <div id=\"recaptcha_widget\" style=\"display:none\">

            <div id=\"recaptcha_image\"></div>
            <div class=\"recaptcha_only_if_incorrect_sol\" style=\"color:red\">{% trans 'Incorrect please try again' %}</div>

            <p>
                <label>{% trans 'Enter the words above' %}</label>
                <br/>
                <input type=\"text\" id=\"recaptcha_response_field\" name=\"recaptcha_response_field\" style=\"width: 245px\"/>
                    <a class=\"bb-button\" href=\"javascript:Recaptcha.reload()\" style=\"float: right\"><span class=\"dark-icon i-reload\"></span></a>
            </p>
            </div>

            <script type=\"text/javascript\" src=\"{{ server }}/challenge?k={{ rc.publickey }}\"></script>
            <noscript>
                <iframe src=\"{{ server }}/noscript?k={{ rc.publickey }}\" height=\"300\" width=\"500\" frameborder=\"0\"></iframe><br/>
                <textarea name=\"recaptcha_challenge_field\" rows=\"3\" cols=\"40\"></textarea>
                <input type=\"hidden\" name=\"recaptcha_response_field\" value=\"manual_challenge\"/>
            </noscript>
        {% endif %}
    {% endif %}
{% endif %}
{% endmacro %}", "macro_functions.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/src/bb-themes/huraga/html/macro_functions.phtml");
    }
}
