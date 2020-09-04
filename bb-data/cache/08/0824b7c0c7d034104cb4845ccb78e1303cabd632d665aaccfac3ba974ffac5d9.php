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

/* mod_invoice_banklink.phtml */
class __TwigTemplate_baaf742c0d531e0237bb2f3a01c5cddeb06b0e36a26dc7a90fb0623434752970 extends \Twig\Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->blocks = [
            'meta_title' => [$this, 'block_meta_title'],
            'content' => [$this, 'block_content'],
        ];
    }

    protected function doGetParent(array $context)
    {
        // line 1
        return $this->loadTemplate(((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ajax", [], "any", false, false, false, 1)) ? ("layout_blank.phtml") : ("layout_default.phtml")), "mod_invoice_banklink.phtml", 1);
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        $this->getParent($context)->display($context, array_merge($this->blocks, $blocks));
    }

    // line 3
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo gettext("Processing payment ...");
    }

    // line 5
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 6
        echo "
";
        // line 11
        echo "<div class=\"row\">
    <article class=\"span12 data-block\">
        <div class=\"data-container\">
            <header>
                <h1>";
        // line 15
        echo gettext("Processing payment ...");
        echo "</h1>
                <p>";
        // line 16
        echo gettext("Thank you for your patience.");
        echo "</p>
            </header>
            <section>
                ";
        // line 19
        if ((twig_get_attribute($this->env, $this->source, ($context["payment"] ?? null), "type", [], "any", false, false, false, 19) == "html")) {
            // line 20
            echo "                    ";
            echo twig_get_attribute($this->env, $this->source, ($context["payment"] ?? null), "result", [], "any", false, false, false, 20);
            echo "
                ";
        }
        // line 22
        echo "                ";
        if ((twig_get_attribute($this->env, $this->source, ($context["payment"] ?? null), "type", [], "any", false, false, false, 22) == "form")) {
            // line 23
            echo "                <h2>";
            echo gettext("Redirecting to payment gateway..");
            echo "</h2>
                <form name=\"payment_form\" action=\"";
            // line 24
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["payment"] ?? null), "service_url", [], "any", false, false, false, 24), "html", null, true);
            echo "\" method=\"post\">
                    ";
            // line 25
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["payment"] ?? null), "result", [], "any", false, false, false, 25));
            foreach ($context['_seq'] as $context["key"] => $context["value"]) {
                // line 26
                echo "                    <input type=\"hidden\" name=\"";
                echo twig_escape_filter($this->env, $context["key"], "html", null, true);
                echo "\" value=\"";
                echo twig_escape_filter($this->env, $context["value"], "html", null, true);
                echo "\" />
                    ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['key'], $context['value'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 28
            echo "                    <input class=\"btn btn-primary\" type=\"submit\" value=\"";
            echo gettext("Please click here to continue if this page does not redirect automatically in 5 seconds");
            echo "\" id=\"payment_button\"/>
                </form>

                <script type=\"text/javascript\">
                    \$(document).ready(function(){
                        document.getElementById('payment_button').style.display = 'none';
                        document.forms[\"payment_form\"].submit();
                    });
                </script>
                ";
        }
        // line 38
        echo "            </section>
        </div>
    </article>
</div>




";
    }

    public function getTemplateName()
    {
        return "mod_invoice_banklink.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  125 => 38,  111 => 28,  100 => 26,  96 => 25,  92 => 24,  87 => 23,  84 => 22,  78 => 20,  76 => 19,  70 => 16,  66 => 15,  60 => 11,  57 => 6,  53 => 5,  46 => 3,  36 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}

{% block meta_title %}{% trans 'Processing payment ...' %}{% endblock %}

{% block content %}

{#
<pre>{% debug invoice %}</pre>
<pre>{% debug payment %}</pre>
#}
<div class=\"row\">
    <article class=\"span12 data-block\">
        <div class=\"data-container\">
            <header>
                <h1>{% trans 'Processing payment ...' %}</h1>
                <p>{% trans 'Thank you for your patience.' %}</p>
            </header>
            <section>
                {% if payment.type == 'html' %}
                    {{ payment.result|raw }}
                {% endif %}
                {% if payment.type == 'form' %}
                <h2>{% trans 'Redirecting to payment gateway..' %}</h2>
                <form name=\"payment_form\" action=\"{{payment.service_url}}\" method=\"post\">
                    {% for key, value in payment.result %}
                    <input type=\"hidden\" name=\"{{key}}\" value=\"{{value}}\" />
                    {% endfor %}
                    <input class=\"btn btn-primary\" type=\"submit\" value=\"{% trans 'Please click here to continue if this page does not redirect automatically in 5 seconds' %}\" id=\"payment_button\"/>
                </form>

                <script type=\"text/javascript\">
                    \$(document).ready(function(){
                        document.getElementById('payment_button').style.display = 'none';
                        document.forms[\"payment_form\"].submit();
                    });
                </script>
                {% endif %}
            </section>
        </div>
    </article>
</div>




{% endblock %}", "mod_invoice_banklink.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-modules/Invoice/html_client/mod_invoice_banklink.phtml");
    }
}
