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

/* mod_client_balance.phtml */
class __TwigTemplate_cc6186593d122c7b39d29f7cfffd1d9daf7ad0a1e67b8e3fddd76fbabb82f5fa extends \Twig\Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->blocks = [
            'meta_title' => [$this, 'block_meta_title'],
            'breadcrumb' => [$this, 'block_breadcrumb'],
            'content' => [$this, 'block_content'],
            'js' => [$this, 'block_js'],
        ];
    }

    protected function doGetParent(array $context)
    {
        // line 1
        return $this->loadTemplate(((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ajax", [], "any", false, false, false, 1)) ? ("layout_blank.phtml") : ("layout_default.phtml")), "mod_client_balance.phtml", 1);
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 2
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_client_balance.phtml", 2)->unwrap();
        // line 5
        $context["profile"] = twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "client_get", [], "any", false, false, false, 5);
        // line 1
        $this->getParent($context)->display($context, array_merge($this->blocks, $blocks));
    }

    // line 3
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo gettext("Payments history");
    }

    // line 4
    public function block_breadcrumb($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo " <li class=\"active\">";
        echo gettext("Payments history");
        echo "</li>";
    }

    // line 7
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 8
        echo "<div class=\"row\">
<article class=\"span12 data-block\">
    <div class=\"data-container\">

        <header>
           <h1>";
        // line 13
        echo gettext("Payments history");
        echo "</h1>
           <p>";
        // line 14
        echo gettext("Here you can track what you have been charged for and add more funds to your account");
        echo "</p>
        </header>

        <section>
            <table class=\"table table-striped table-bordered table-condensed table-hover\">
                <thead>
                <tr>
                    <th>";
        // line 21
        echo gettext("Description");
        echo "</th>
                    <th>";
        // line 22
        echo gettext("Date");
        echo "</th>
                    <th>";
        // line 23
        echo gettext("Amount");
        echo "</th>
                </tr>
                </thead>
                <tbody>
                ";
        // line 27
        $context["transactions"] = twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "client_balance_get_list", [0 => ["per_page" => 10, "page" => twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 27)]], "method", false, false, false, 27);
        // line 28
        echo "                ";
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["transactions"] ?? null), "list", [], "any", false, false, false, 28));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["i"] => $context["tx"]) {
            // line 29
            echo "                <tr class=\"";
            echo twig_escape_filter($this->env, twig_cycle([0 => "odd", 1 => "even"], $context["i"]), "html", null, true);
            echo "\">
                    <td>";
            // line 30
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tx"], "description", [], "any", false, false, false, 30), "html", null, true);
            echo "</td>
                    <td>";
            // line 31
            echo twig_escape_filter($this->env, $this->extensions['Box_TwigExtensions']->twig_bb_date(twig_get_attribute($this->env, $this->source, $context["tx"], "created_at", [], "any", false, false, false, 31)), "html", null, true);
            echo "</td>
                    <td>";
            // line 32
            echo twig_money($this->env, twig_get_attribute($this->env, $this->source, $context["tx"], "amount", [], "any", false, false, false, 32), twig_get_attribute($this->env, $this->source, $context["tx"], "currency", [], "any", false, false, false, 32));
            echo "</td>
                </tr>
                ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 35
            echo "                <tr>
                    <td colspan=\"3\">";
            // line 36
            echo gettext("The list is empty");
            echo "</td>
                </tr>
                ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['i'], $context['tx'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 39
        echo "                </tbody>
                <tfoot>
                    <tr>
                        <td colspan=\"2\">";
        // line 42
        echo gettext("Total:");
        echo "</td>
                        <td><strong>";
        // line 43
        echo twig_money($this->env, twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "balance", [], "any", false, false, false, 43), twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "currency", [], "any", false, false, false, 43));
        echo "</strong></td>
                    </tr>
                </tfoot>
            </table>
            ";
        // line 47
        $this->loadTemplate("partial_pagination.phtml", "mod_client_balance.phtml", 47)->display(twig_array_merge($context, ["list" => ($context["transactions"] ?? null)]));
        // line 48
        echo "
            <div class=\"row-fluid\">

                <div class=\"span3\">
                    <form action=\"\" method=\"post\" class=\"form-inline api-form\" data-api-url=\"";
        // line 52
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/client/invoice/funds_invoice");
        echo "\" data-api-jsonp=\"onAfterInvoiceCreated\">
                        <fieldset>
                            <div class=\"control-group\">
                                <div class=\"form-controls\">
                                    <div class=\"input-append\">
                                        <input id=\"appendedPrependedInput\" class=\"span4\" type=\"text\" name=\"amount\" placeholder=\"0\" required=\"required\"><button class=\"btn\" type=\"submit\">";
        // line 57
        echo gettext("Add funds!");
        echo "</button>
                                    </div>
                                </div>
                            </div>
                        </fieldset>
                    </form>
                </div>

            </div>

        </section>

</article>
</div>

";
    }

    // line 74
    public function block_js($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 75
        echo "<script type=\"text/javascript\">
    function onAfterInvoiceCreated(hash) {
        var link = '";
        // line 77
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("invoice");
        echo "/' + hash;
        bb.redirect(link);
    }
</script>
";
    }

    public function getTemplateName()
    {
        return "mod_client_balance.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  210 => 77,  206 => 75,  202 => 74,  182 => 57,  174 => 52,  168 => 48,  166 => 47,  159 => 43,  155 => 42,  150 => 39,  141 => 36,  138 => 35,  130 => 32,  126 => 31,  122 => 30,  117 => 29,  111 => 28,  109 => 27,  102 => 23,  98 => 22,  94 => 21,  84 => 14,  80 => 13,  73 => 8,  69 => 7,  60 => 4,  53 => 3,  49 => 1,  47 => 5,  45 => 2,  38 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}
{% import \"macro_functions.phtml\" as mf %}
{% block meta_title %}{% trans 'Payments history' %}{% endblock %}
{% block breadcrumb %} <li class=\"active\">{% trans 'Payments history' %}</li>{% endblock %}
{% set profile = client.client_get %}

{% block content %}
<div class=\"row\">
<article class=\"span12 data-block\">
    <div class=\"data-container\">

        <header>
           <h1>{% trans 'Payments history'%}</h1>
           <p>{% trans 'Here you can track what you have been charged for and add more funds to your account' %}</p>
        </header>

        <section>
            <table class=\"table table-striped table-bordered table-condensed table-hover\">
                <thead>
                <tr>
                    <th>{% trans 'Description' %}</th>
                    <th>{% trans 'Date' %}</th>
                    <th>{% trans 'Amount' %}</th>
                </tr>
                </thead>
                <tbody>
                {% set transactions = client.client_balance_get_list({\"per_page\":10, \"page\":request.page}) %}
                {% for i, tx in transactions.list %}
                <tr class=\"{{ cycle(['odd', 'even'], i) }}\">
                    <td>{{tx.description}}</td>
                    <td>{{tx.created_at|bb_date}}</td>
                    <td>{{ tx.amount | money(tx.currency) }}</td>
                </tr>
                {% else %}
                <tr>
                    <td colspan=\"3\">{% trans 'The list is empty' %}</td>
                </tr>
                {% endfor %}
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan=\"2\">{% trans 'Total:' %}</td>
                        <td><strong>{{ profile.balance | money(profile.currency) }}</strong></td>
                    </tr>
                </tfoot>
            </table>
            {% include \"partial_pagination.phtml\" with {'list': transactions} %}

            <div class=\"row-fluid\">

                <div class=\"span3\">
                    <form action=\"\" method=\"post\" class=\"form-inline api-form\" data-api-url=\"{{ 'api/client/invoice/funds_invoice'|link }}\" data-api-jsonp=\"onAfterInvoiceCreated\">
                        <fieldset>
                            <div class=\"control-group\">
                                <div class=\"form-controls\">
                                    <div class=\"input-append\">
                                        <input id=\"appendedPrependedInput\" class=\"span4\" type=\"text\" name=\"amount\" placeholder=\"0\" required=\"required\"><button class=\"btn\" type=\"submit\">{% trans 'Add funds!' %}</button>
                                    </div>
                                </div>
                            </div>
                        </fieldset>
                    </form>
                </div>

            </div>

        </section>

</article>
</div>

{% endblock %}

{% block js %}
<script type=\"text/javascript\">
    function onAfterInvoiceCreated(hash) {
        var link = '{{ \"invoice\"|link }}/' + hash;
        bb.redirect(link);
    }
</script>
{% endblock %}

", "mod_client_balance.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-modules/Client/html_client/mod_client_balance.phtml");
    }
}
