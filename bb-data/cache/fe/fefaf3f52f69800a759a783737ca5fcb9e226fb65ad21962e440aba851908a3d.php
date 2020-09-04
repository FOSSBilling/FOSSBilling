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

/* mod_order_product.phtml */
class __TwigTemplate_d7972efc6ec6beaae516655e29ff0e6b17c08533c9c9388bd2a1d1124f902b99 extends \Twig\Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->blocks = [
            'meta_title' => [$this, 'block_meta_title'],
            'content_before' => [$this, 'block_content_before'],
            'content' => [$this, 'block_content'],
            'sidebar2' => [$this, 'block_sidebar2'],
            'js' => [$this, 'block_js'],
        ];
    }

    protected function doGetParent(array $context)
    {
        // line 1
        return $this->loadTemplate(((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ajax", [], "any", false, false, false, 1)) ? ("layout_blank.phtml") : ("layout_default.phtml")), "mod_order_product.phtml", 1);
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 2
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_order_product.phtml", 2)->unwrap();
        // line 5
        $context["loader_nr"] = ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "loader", [], "any", true, true, false, 5)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "loader", [], "any", false, false, false, 5), "8")) : ("8"));
        // line 6
        $context["loader_url"] = (("img/assets/loaders/loader" . ($context["loader_nr"] ?? null)) . ".gif");
        // line 1
        $this->getParent($context)->display($context, array_merge($this->blocks, $blocks));
    }

    // line 3
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo gettext("Order");
    }

    // line 8
    public function block_content_before($context, array $blocks = [])
    {
        $macros = $this->macros;
    }

    // line 11
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 12
        echo "
<div class=\"row\">
    <article class=\"span12 data-block\">
        <div class=\"data-container\">
            <header>
                <h2>";
        // line 17
        echo gettext("Select Product");
        echo "</h2>
                <p>";
        // line 18
        echo gettext("Choose products we offer for selling");
        echo "</p>
            </header>
            <section>
                <img id=\"loader-image\" src=\"";
        // line 21
        echo twig_escape_filter($this->env, twig_mod_asset_url(($context["loader_url"] ?? null), "orderbutton"), "html", null, true);
        echo "\" style=\"display: block; margin-left: auto; margin-right: auto; position: relative; top: 50%\" />
                <iframe style=\"width: 100%;\" frameborder=\"0\" allowtransparency=\"true\" onload='javascript:resizeIframe(this);' id=\"popup-iframe\"  src=\"";
        // line 22
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("orderbutton");
        echo "&theme_color=";
        echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "theme_color", [], "any", true, true, false, 22)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "theme_color", [], "any", false, false, false, 22), "green")) : ("green")), "html", null, true);
        if (($context["product"] ?? null)) {
            echo "&order=";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "id", [], "any", false, false, false, 22), "html", null, true);
        }
        if (twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "show_custom_form_values", [], "any", false, false, false, 22)) {
            echo "&show_custom_form_values=1";
        }
        echo "&loader=3\" id=\"popup-iframe\"/>
            </section>
        </div>
    </article>
</div>
";
    }

    // line 29
    public function block_sidebar2($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 30
        $this->loadTemplate("partial_currency.phtml", "mod_order_product.phtml", 30)->display($context);
    }

    // line 33
    public function block_js($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 35
        echo "
<script language=\"javascript\" type=\"text/javascript\">
    function resizeIframe(obj) {
        obj.style.height = obj.contentWindow.document.body.scrollHeight + 'px';
        \$('#loader-image').hide();
    }
</script>
";
    }

    public function getTemplateName()
    {
        return "mod_order_product.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  125 => 35,  121 => 33,  117 => 30,  113 => 29,  94 => 22,  90 => 21,  84 => 18,  80 => 17,  73 => 12,  69 => 11,  63 => 8,  56 => 3,  52 => 1,  50 => 6,  48 => 5,  46 => 2,  39 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}
{% import \"macro_functions.phtml\" as mf %}
{% block meta_title %}{% trans 'Order' %}{% endblock %}

{% set loader_nr = request.loader | default(\"8\")%}
{% set loader_url = ('img/assets/loaders/loader'~loader_nr~'.gif') %}

{% block content_before %}
{% endblock %}

{% block content %}

<div class=\"row\">
    <article class=\"span12 data-block\">
        <div class=\"data-container\">
            <header>
                <h2>{% trans 'Select Product' %}</h2>
                <p>{% trans 'Choose products we offer for selling' %}</p>
            </header>
            <section>
                <img id=\"loader-image\" src=\"{{ loader_url | mod_asset_url('orderbutton')}}\" style=\"display: block; margin-left: auto; margin-right: auto; position: relative; top: 50%\" />
                <iframe style=\"width: 100%;\" frameborder=\"0\" allowtransparency=\"true\" onload='javascript:resizeIframe(this);' id=\"popup-iframe\"  src=\"{{ \"orderbutton\" | link }}&theme_color={{request.theme_color | default('green')}}{% if product %}&order={{product.id}}{% endif %}{% if request.show_custom_form_values %}&show_custom_form_values=1{% endif %}&loader=3\" id=\"popup-iframe\"/>
            </section>
        </div>
    </article>
</div>
{% endblock %}

{% block sidebar2 %}
{% include 'partial_currency.phtml' %}
{% endblock %}

{% block js %}
{% autoescape \"js\" %}

<script language=\"javascript\" type=\"text/javascript\">
    function resizeIframe(obj) {
        obj.style.height = obj.contentWindow.document.body.scrollHeight + 'px';
        \$('#loader-image').hide();
    }
</script>
{% endautoescape %}
{% endblock %}
", "mod_order_product.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-modules/Order/html_client/mod_order_product.phtml");
    }
}
