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

/* mod_invoice_index.phtml */
class __TwigTemplate_32988c64323791a693fd426478a43bb3a72284f2fe19fac61f22807407c14e7b extends \Twig\Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->blocks = [
            'meta_title' => [$this, 'block_meta_title'],
            'page_header' => [$this, 'block_page_header'],
            'breadcrumb' => [$this, 'block_breadcrumb'],
            'content' => [$this, 'block_content'],
        ];
    }

    protected function doGetParent(array $context)
    {
        // line 1
        return $this->loadTemplate(((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ajax", [], "any", false, false, false, 1)) ? ("layout_blank.phtml") : ("layout_default.phtml")), "mod_invoice_index.phtml", 1);
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        $this->getParent($context)->display($context, array_merge($this->blocks, $blocks));
    }

    // line 2
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo gettext("Invoice management");
    }

    // line 3
    public function block_page_header($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo gettext("Invoice management");
    }

    // line 5
    public function block_breadcrumb($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 6
        echo "    <li class=\"active\">";
        echo gettext("Invoices");
        echo "</li>
";
    }

    // line 9
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 10
        echo "
";
        // line 11
        $context["unpaid_invoices"] = twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "invoice_get_list", [0 => ["status" => "unpaid", "per_page" => 100]], "method", false, false, false, 11);
        // line 12
        $context["paid_invoices"] = twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "invoice_get_list", [0 => ["per_page" => 10, "page" => twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 12), "status" => "paid"]], "method", false, false, false, 12);
        // line 13
        echo "
<div class=\"row\">
        <article class=\"span12 data-block\">
            <div class=\"data-container\">
                <header>
                    <h1>";
        // line 18
        echo gettext("List of invoices");
        echo "</h1><br/>
                    ";
        // line 19
        echo gettext("All of your invoices can be found here. You can choose to see either paid or unpaid invoices by clicking corresponding button.");
        // line 20
        echo "                    <ul class=\"data-header-actions\">
                        <li class=\"demoTabs active\"><a href=\"#unpaid\" class=\"btn btn-alt btn-inverse\" data-toggle=\"tab\">";
        // line 21
        echo gettext("Unpaid");
        echo "</a></li>
                        <li class=\"demoTabs\" ><a href=\"#paid\" class=\"btn btn-alt btn-inverse\" data-toggle=\"tab\">";
        // line 22
        echo gettext("Paid");
        echo "</a></li>
                    </ul>
                </header>
                <section class=\"tab-content\">
                    <div class=\"tab-pane active\" id=\"unpaid\">
                    <h3>";
        // line 27
        echo gettext("Unpaid");
        echo "</h3>

                        <table class=\"table table-hover table-striped\">
                            <thead>
                            <tr>
                                <th>";
        // line 32
        echo gettext("Title");
        echo "</th>
                                <th>";
        // line 33
        echo gettext("Issue Date");
        echo "</th>
                                <th>";
        // line 34
        echo gettext("Due Date");
        echo "</th>
                                <th>";
        // line 35
        echo gettext("Total");
        echo "</th>
                                <th>&nbsp</th>
                            </tr>
                            </thead>
                            <tbody>
                            ";
        // line 40
        if ((twig_get_attribute($this->env, $this->source, ($context["unpaid_invoices"] ?? null), "total", [], "any", false, false, false, 40) > 0)) {
            // line 41
            echo "                            ";
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["unpaid_invoices"] ?? null), "list", [], "any", false, false, false, 41));
            foreach ($context['_seq'] as $context["i"] => $context["invoice"]) {
                // line 42
                echo "                            <tr class=\"";
                echo twig_escape_filter($this->env, twig_cycle([0 => "odd", 1 => "even"], $context["i"]), "html", null, true);
                echo "\">
                                <td>";
                // line 43
                echo twig_escape_filter($this->env, sprintf("Proforma invoice #%05s", twig_get_attribute($this->env, $this->source, $context["invoice"], "id", [], "any", false, false, false, 43)), "html", null, true);
                echo "</td>
                                <td>";
                // line 44
                echo twig_escape_filter($this->env, $this->extensions['Box_TwigExtensions']->twig_bb_date(twig_get_attribute($this->env, $this->source, $context["invoice"], "created_at", [], "any", false, false, false, 44)), "html", null, true);
                echo "</td>
                                <td>";
                // line 45
                echo twig_escape_filter($this->env, $this->extensions['Box_TwigExtensions']->twig_bb_date(twig_get_attribute($this->env, $this->source, $context["invoice"], "due_at", [], "any", false, false, false, 45)), "html", null, true);
                echo "</td>
                                <td>";
                // line 46
                echo twig_money($this->env, twig_get_attribute($this->env, $this->source, $context["invoice"], "total", [], "any", false, false, false, 46), twig_get_attribute($this->env, $this->source, $context["invoice"], "currency", [], "any", false, false, false, 46));
                echo "</td>
                                <td><a class=\"btn btn-small btn-primary\" href=\"";
                // line 47
                echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("invoice");
                echo "/";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["invoice"], "hash", [], "any", false, false, false, 47), "html", null, true);
                echo "\">";
                echo gettext("Pay");
                echo "</a></td>
                            </tr>
                            ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['i'], $context['invoice'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 50
            echo "                            ";
        } else {
            // line 51
            echo "                            <tr>
                                <td colspan=\"5\" >";
            // line 52
            echo gettext("The list is empty");
            echo "</td>
                            </tr>
                            ";
        }
        // line 55
        echo "
                            </tbody>
                        </table>

                    </div>

                    <div class=\"tab-pane\" id=\"paid\">
                    <h3>";
        // line 62
        echo gettext("Paid");
        echo "</h3>

                        <table class=\"table table-hover table-striped\">
                            <thead>
                            <tr>
                                <th>";
        // line 67
        echo gettext("Title");
        echo "</th>
                                <th>";
        // line 68
        echo gettext("Issue Date");
        echo "</th>
                                <th>";
        // line 69
        echo gettext("Paid at");
        echo "</th>
                                <th>";
        // line 70
        echo gettext("Total");
        echo "</th>
                                <th>&nbsp</th>
                            </tr>
                            </thead>

                            <tbody>
                            ";
        // line 76
        if ((twig_get_attribute($this->env, $this->source, ($context["paid_invoices"] ?? null), "total", [], "any", false, false, false, 76) > 0)) {
            // line 77
            echo "                            ";
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["paid_invoices"] ?? null), "list", [], "any", false, false, false, 77));
            foreach ($context['_seq'] as $context["i"] => $context["invoice"]) {
                // line 78
                echo "
                            <tr class=\"";
                // line 79
                echo twig_escape_filter($this->env, twig_cycle([0 => "odd", 1 => "even"], $context["i"]), "html", null, true);
                echo "\">
                                <td>";
                // line 80
                echo twig_escape_filter($this->env, sprintf("Proforma invoice #%05s", twig_get_attribute($this->env, $this->source, $context["invoice"], "id", [], "any", false, false, false, 80)), "html", null, true);
                echo "</td>
                                <td>";
                // line 81
                echo twig_escape_filter($this->env, $this->extensions['Box_TwigExtensions']->twig_bb_date(twig_get_attribute($this->env, $this->source, $context["invoice"], "created_at", [], "any", false, false, false, 81)), "html", null, true);
                echo "</td>
                                <td>";
                // line 82
                echo twig_escape_filter($this->env, $this->extensions['Box_TwigExtensions']->twig_bb_date(twig_get_attribute($this->env, $this->source, $context["invoice"], "paid_at", [], "any", false, false, false, 82)), "html", null, true);
                echo "</td>
                                <td>";
                // line 83
                echo twig_money($this->env, twig_get_attribute($this->env, $this->source, $context["invoice"], "total", [], "any", false, false, false, 83), twig_get_attribute($this->env, $this->source, $context["invoice"], "currency", [], "any", false, false, false, 83));
                echo "</td>
                                <td><a href=\"";
                // line 84
                echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("invoice");
                echo "/";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["invoice"], "hash", [], "any", false, false, false, 84), "html", null, true);
                echo "\" class=\"btn btn-primary btn-small\">";
                echo gettext("View");
                echo "</a></td>
                            </tr>
                            ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['i'], $context['invoice'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 87
            echo "                            ";
        } else {
            // line 88
            echo "                            <tr>
                                <td colspan=\"7\">";
            // line 89
            echo gettext("The list is empty");
            echo "</td>
                            </tr>
                            ";
        }
        // line 92
        echo "                            </tbody>
                        </table>

                    </div>
                </section>
                <footer>
                    <p>";
        // line 98
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["unpaid_invoices"] ?? null), "total", [], "any", false, false, false, 98), "html", null, true);
        echo " unpaid invoices and ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["paid_invoices"] ?? null), "total", [], "any", false, false, false, 98), "html", null, true);
        echo " paid invoices</p>
                </footer>
            </div>
        </article>
        </div>
";
    }

    public function getTemplateName()
    {
        return "mod_invoice_index.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  294 => 98,  286 => 92,  280 => 89,  277 => 88,  274 => 87,  261 => 84,  257 => 83,  253 => 82,  249 => 81,  245 => 80,  241 => 79,  238 => 78,  233 => 77,  231 => 76,  222 => 70,  218 => 69,  214 => 68,  210 => 67,  202 => 62,  193 => 55,  187 => 52,  184 => 51,  181 => 50,  168 => 47,  164 => 46,  160 => 45,  156 => 44,  152 => 43,  147 => 42,  142 => 41,  140 => 40,  132 => 35,  128 => 34,  124 => 33,  120 => 32,  112 => 27,  104 => 22,  100 => 21,  97 => 20,  95 => 19,  91 => 18,  84 => 13,  82 => 12,  80 => 11,  77 => 10,  73 => 9,  66 => 6,  62 => 5,  55 => 3,  48 => 2,  38 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}
{% block meta_title %}{% trans 'Invoice management' %}{% endblock %}
{% block page_header %}{% trans 'Invoice management' %}{% endblock %}

{% block breadcrumb %}
    <li class=\"active\">{% trans 'Invoices' %}</li>
{% endblock %}

{% block content %}

{% set unpaid_invoices = client.invoice_get_list({\"status\":\"unpaid\", \"per_page\":100}) %}
{% set paid_invoices = client.invoice_get_list({\"per_page\":10, \"page\":request.page, \"status\":\"paid\"}) %}

<div class=\"row\">
        <article class=\"span12 data-block\">
            <div class=\"data-container\">
                <header>
                    <h1>{% trans 'List of invoices' %}</h1><br/>
                    {% trans 'All of your invoices can be found here. You can choose to see either paid or unpaid invoices by clicking corresponding button.' %}
                    <ul class=\"data-header-actions\">
                        <li class=\"demoTabs active\"><a href=\"#unpaid\" class=\"btn btn-alt btn-inverse\" data-toggle=\"tab\">{% trans 'Unpaid' %}</a></li>
                        <li class=\"demoTabs\" ><a href=\"#paid\" class=\"btn btn-alt btn-inverse\" data-toggle=\"tab\">{% trans 'Paid' %}</a></li>
                    </ul>
                </header>
                <section class=\"tab-content\">
                    <div class=\"tab-pane active\" id=\"unpaid\">
                    <h3>{% trans 'Unpaid' %}</h3>

                        <table class=\"table table-hover table-striped\">
                            <thead>
                            <tr>
                                <th>{% trans 'Title' %}</th>
                                <th>{% trans 'Issue Date' %}</th>
                                <th>{% trans 'Due Date' %}</th>
                                <th>{% trans 'Total' %}</th>
                                <th>&nbsp</th>
                            </tr>
                            </thead>
                            <tbody>
                            {% if unpaid_invoices.total > 0 %}
                            {% for i, invoice in unpaid_invoices.list %}
                            <tr class=\"{{ cycle(['odd', 'even'], i) }}\">
                                <td>{{ \"Proforma invoice #%05s\"|format(invoice.id) }}</td>
                                <td>{{ invoice.created_at|bb_date }}</td>
                                <td>{{ invoice.due_at|bb_date }}</td>
                                <td>{{ invoice.total | money(invoice.currency) }}</td>
                                <td><a class=\"btn btn-small btn-primary\" href=\"{{ 'invoice'|link }}/{{ invoice.hash }}\">{% trans 'Pay' %}</a></td>
                            </tr>
                            {% endfor %}
                            {% else %}
                            <tr>
                                <td colspan=\"5\" >{% trans 'The list is empty' %}</td>
                            </tr>
                            {% endif %}

                            </tbody>
                        </table>

                    </div>

                    <div class=\"tab-pane\" id=\"paid\">
                    <h3>{% trans 'Paid' %}</h3>

                        <table class=\"table table-hover table-striped\">
                            <thead>
                            <tr>
                                <th>{% trans 'Title' %}</th>
                                <th>{% trans 'Issue Date' %}</th>
                                <th>{% trans 'Paid at' %}</th>
                                <th>{% trans 'Total' %}</th>
                                <th>&nbsp</th>
                            </tr>
                            </thead>

                            <tbody>
                            {% if paid_invoices.total > 0 %}
                            {% for i, invoice in paid_invoices.list %}

                            <tr class=\"{{ cycle(['odd', 'even'], i) }}\">
                                <td>{{ \"Proforma invoice #%05s\"|format(invoice.id) }}</td>
                                <td>{{ invoice.created_at|bb_date }}</td>
                                <td>{{ invoice.paid_at|bb_date }}</td>
                                <td>{{ invoice.total | money(invoice.currency) }}</td>
                                <td><a href=\"{{ 'invoice'|link }}/{{ invoice.hash }}\" class=\"btn btn-primary btn-small\">{% trans 'View' %}</a></td>
                            </tr>
                            {% endfor %}
                            {% else %}
                            <tr>
                                <td colspan=\"7\">{% trans 'The list is empty' %}</td>
                            </tr>
                            {% endif %}
                            </tbody>
                        </table>

                    </div>
                </section>
                <footer>
                    <p>{{ unpaid_invoices.total }} unpaid invoices and {{ paid_invoices.total }} paid invoices</p>
                </footer>
            </div>
        </article>
        </div>
{% endblock %}

", "mod_invoice_index.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/src/bb-modules/Invoice/html_client/mod_invoice_index.phtml");
    }
}
