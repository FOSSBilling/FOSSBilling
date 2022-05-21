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

/* mod_support_tickets.phtml */
class __TwigTemplate_db57d4170ecaf179feb9afcebec75c0371e41bf4a640f7942a2fce968a02346b extends Template
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
            'sidebar2' => [$this, 'block_sidebar2'],
            'js' => [$this, 'block_js'],
        ];
    }

    protected function doGetParent(array $context)
    {
        // line 1
        return $this->loadTemplate(((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ajax", [], "any", false, false, false, 1)) ? ("layout_blank.phtml") : ("layout_default.phtml")), "mod_support_tickets.phtml", 1);
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 3
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_support_tickets.phtml", 3)->unwrap();
        // line 1
        $this->getParent($context)->display($context, array_merge($this->blocks, $blocks));
    }

    // line 5
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo twig_escape_filter($this->env, gettext("Support tickets"), "html", null, true);
    }

    // line 7
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 8
        echo "    <div class=\"h-block\">
        <div class=\"h-block-header\">
            <div class=\"icon\"><span class=\"big-light-icon i-support\"></span></div>
            <h2>";
        // line 11
        echo twig_escape_filter($this->env, gettext("Support tickets"), "html", null, true);
        echo "</h2>
            <p>";
        // line 12
        echo twig_escape_filter($this->env, gettext("Need an answer? We are here to help"), "html", null, true);
        echo "</p>
        </div>
        <div class=\"block\">
            <table>
                <thead>
                    <tr>
                        <th style=\"width: 1%\">";
        // line 18
        echo twig_escape_filter($this->env, gettext("Id"), "html", null, true);
        echo "</th>
                        <th style=\"width: 40%\">";
        // line 19
        echo twig_escape_filter($this->env, gettext("Subject"), "html", null, true);
        echo "</th>
                        <th>";
        // line 20
        echo twig_escape_filter($this->env, gettext("Help desk"), "html", null, true);
        echo "</th>
                        <th>";
        // line 21
        echo twig_escape_filter($this->env, gettext("Status"), "html", null, true);
        echo "</th>
                        <th>";
        // line 22
        echo twig_escape_filter($this->env, gettext("Submitted"), "html", null, true);
        echo "</th>
                        <th>&nbsp;</th>
                    </tr>
                </thead>
                <tbody>
                    ";
        // line 27
        $context["tickets"] = twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "support_ticket_get_list", [0 => ["per_page" => 10, "page" => twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 27)]], "method", false, false, false, 27);
        // line 28
        echo "                    ";
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["tickets"] ?? null), "list", [], "any", false, false, false, 28));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["i"] => $context["ticket"]) {
            // line 29
            echo "
                    <tr class=\"";
            // line 30
            echo twig_escape_filter($this->env, twig_cycle([0 => "odd", 1 => "even"], $context["i"]), "html", null, true);
            echo "\">
                        <td>#";
            // line 31
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "id", [], "any", false, false, false, 31), "html", null, true);
            echo "</td>
                        <td><a href=\"";
            // line 32
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("support/ticket");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "id", [], "any", false, false, false, 32), "html", null, true);
            echo "\">";
            echo twig_escape_filter($this->env, twig_truncate_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "subject", [], "any", false, false, false, 32), 40), "html", null, true);
            echo "</a></td>
                        <td>";
            // line 33
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["ticket"], "helpdesk", [], "any", false, false, false, 33), "name", [], "any", false, false, false, 33), "html", null, true);
            echo "</td>
                        <td>";
            // line 34
            echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, $context["ticket"], "status", [], "any", false, false, false, 34)], 34, $context, $this->getSourceContext());
            echo "</td>
                        <td>";
            // line 35
            echo twig_escape_filter($this->env, twig_timeago_filter(twig_get_attribute($this->env, $this->source, $context["ticket"], "created_at", [], "any", false, false, false, 35)), "html", null, true);
            echo " ";
            echo twig_escape_filter($this->env, gettext("ago"), "html", null, true);
            echo "</td>
                        <td class=\"actions\">
                            <a class=\"bb-button\" href=\"";
            // line 37
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("support/ticket");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "id", [], "any", false, false, false, 37), "html", null, true);
            echo "\">
                                <span class=\"dark-icon i-drag\"></span>
                            </a>
                        </td>
                    </tr>

                    ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 44
            echo "
                    <tr>
                        <td colspan=\"6\">";
            // line 46
            echo twig_escape_filter($this->env, gettext("The list is empty"), "html", null, true);
            echo "</td>
                    </tr>

                    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['i'], $context['ticket'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 50
        echo "
                </tbody>

                <tfoot>
                    <tr>
                        <td colspan=\"2\" ><a class=\"bb-button\" href=\"#\" id=\"new-ticket-button\">";
        // line 55
        echo twig_escape_filter($this->env, gettext("Submit new ticket"), "html", null, true);
        echo "</a></td>
                        <td colspan=\"4\">
                            ";
        // line 57
        $this->loadTemplate("partial_pagination.phtml", "mod_support_tickets.phtml", 57)->display(twig_array_merge($context, ["list" => ($context["tickets"] ?? null)]));
        // line 58
        echo "                        </td>
                    </tr>
                </tfoot>

            </table>
        </div>
    </div>

<div class=\"widget\" id=\"new-ticket\" ";
        // line 66
        if ( !twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ticket", [], "any", false, false, false, 66)) {
            echo "style=\"display: none;\"";
        }
        echo ">
    <div class=\"head\">
        <h2 class=\"dark-icon i-support\">";
        // line 68
        echo twig_escape_filter($this->env, gettext("Submit new ticket"), "html", null, true);
        echo "</h2>
    </div>
    <div class=\"block\">
        <form action=\"\" method=\"post\" id=\"ticket-submit\">
            <fieldset>
                <legend>";
        // line 73
        echo twig_escape_filter($this->env, gettext("Submit new support ticket"), "html", null, true);
        echo "</legend>
                <p>
                    <label>";
        // line 75
        echo twig_escape_filter($this->env, gettext("Help desk"), "html", null, true);
        echo ": </label>
                    ";
        // line 76
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["support_helpdesk_id", twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "support_helpdesk_get_pairs", [], "any", false, false, false, 76), twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "support_helpdesk_id", [], "any", false, false, false, 76), 1], 76, $context, $this->getSourceContext());
        echo "
                </p>

                <p>
                    <label>";
        // line 80
        echo twig_escape_filter($this->env, gettext("Subject"), "html", null, true);
        echo ": </label>
                    <input type=\"text\" name=\"subject\" value=\"";
        // line 81
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "subject", [], "any", false, false, false, 81));
        echo "\" required=\"required\"/>
                </p>

                <p>
                    <label>";
        // line 85
        echo twig_escape_filter($this->env, gettext("Message"), "html", null, true);
        echo ": </label>
                    <textarea name=\"content\" cols=\"5\" rows=\"5\" required=\"required\">";
        // line 86
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "content", [], "any", false, false, false, 86));
        echo "</textarea>
                </p>

                <input class=\"bb-button bb-button-submit\" type=\"submit\" value=\"";
        // line 89
        echo twig_escape_filter($this->env, gettext("Submit"), "html", null, true);
        echo "\">
            </fieldset>
        </form>
    </div>
</div>
";
    }

    // line 96
    public function block_sidebar2($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 97
        if (twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "extension_is_on", [0 => ["mod" => "kb"]], "method", false, false, false, 97)) {
            // line 98
            echo "<div class=\"widget\">
    <div class=\"head\">
        <h2 class=\"dark-icon i-kb\">";
            // line 100
            echo twig_escape_filter($this->env, gettext("Knowledge base"), "html", null, true);
            echo "</h2>
    </div>
    <ul class=\"menu\">
    ";
            // line 103
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "kb_category_get_list", [], "any", false, false, false, 103), "list", [], "any", false, false, false, 103));
            foreach ($context['_seq'] as $context["i"] => $context["category"]) {
                // line 104
                echo "    <li><a href=\"";
                echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/kb");
                echo "#category-";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["category"], "id", [], "any", false, false, false, 104), "html", null, true);
                echo "\">";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["category"], "title", [], "any", false, false, false, 104), "html", null, true);
                echo "</a></li>
    ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['i'], $context['category'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 106
            echo "    </ul>
</div>
";
        }
    }

    // line 111
    public function block_js($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 112
        echo "<script type=\"text/javascript\">
    \$(function() {
        \$('#new-ticket-button').bind('click', function(event) {
            \$('#new-ticket').slideToggle();
            
            return false;
        });

        \$('#ticket-submit').bind('submit', function(event) {
            bb.post(
                'client/support/ticket_create',
                \$(this).serialize(),
                function(result) {
                    bb.redirect(\"";
        // line 125
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("support/ticket");
        echo "\" + '/' + result);
                }
            );

            return false;
        });
    });
</script>
";
    }

    public function getTemplateName()
    {
        return "mod_support_tickets.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  319 => 125,  304 => 112,  300 => 111,  293 => 106,  280 => 104,  276 => 103,  270 => 100,  266 => 98,  264 => 97,  260 => 96,  250 => 89,  244 => 86,  240 => 85,  233 => 81,  229 => 80,  222 => 76,  218 => 75,  213 => 73,  205 => 68,  198 => 66,  188 => 58,  186 => 57,  181 => 55,  174 => 50,  164 => 46,  160 => 44,  146 => 37,  139 => 35,  135 => 34,  131 => 33,  123 => 32,  119 => 31,  115 => 30,  112 => 29,  106 => 28,  104 => 27,  96 => 22,  92 => 21,  88 => 20,  84 => 19,  80 => 18,  71 => 12,  67 => 11,  62 => 8,  58 => 7,  51 => 5,  47 => 1,  45 => 3,  38 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}

{% import \"macro_functions.phtml\" as mf %}

{% block meta_title %}{{ 'Support tickets'|trans }}{% endblock %}

{% block content %}
    <div class=\"h-block\">
        <div class=\"h-block-header\">
            <div class=\"icon\"><span class=\"big-light-icon i-support\"></span></div>
            <h2>{{ 'Support tickets'|trans }}</h2>
            <p>{{ 'Need an answer? We are here to help'|trans }}</p>
        </div>
        <div class=\"block\">
            <table>
                <thead>
                    <tr>
                        <th style=\"width: 1%\">{{ 'Id'|trans }}</th>
                        <th style=\"width: 40%\">{{ 'Subject'|trans }}</th>
                        <th>{{ 'Help desk'|trans }}</th>
                        <th>{{ 'Status'|trans }}</th>
                        <th>{{ 'Submitted'|trans }}</th>
                        <th>&nbsp;</th>
                    </tr>
                </thead>
                <tbody>
                    {% set tickets = client.support_ticket_get_list({\"per_page\":10, \"page\":request.page}) %}
                    {% for i, ticket in tickets.list %}

                    <tr class=\"{{ cycle(['odd', 'even'], i) }}\">
                        <td>#{{ ticket.id }}</td>
                        <td><a href=\"{{ 'support/ticket'|link }}/{{ticket.id}}\">{{ ticket.subject|truncate(40) }}</a></td>
                        <td>{{ ticket.helpdesk.name }}</td>
                        <td>{{ mf.status_name(ticket.status) }}</td>
                        <td>{{ ticket.created_at|timeago }} {{ 'ago'|trans }}</td>
                        <td class=\"actions\">
                            <a class=\"bb-button\" href=\"{{ 'support/ticket'|link }}/{{ticket.id}}\">
                                <span class=\"dark-icon i-drag\"></span>
                            </a>
                        </td>
                    </tr>

                    {% else %}

                    <tr>
                        <td colspan=\"6\">{{ 'The list is empty'|trans }}</td>
                    </tr>

                    {% endfor %}

                </tbody>

                <tfoot>
                    <tr>
                        <td colspan=\"2\" ><a class=\"bb-button\" href=\"#\" id=\"new-ticket-button\">{{ 'Submit new ticket'|trans }}</a></td>
                        <td colspan=\"4\">
                            {% include \"partial_pagination.phtml\" with {'list': tickets} %}
                        </td>
                    </tr>
                </tfoot>

            </table>
        </div>
    </div>

<div class=\"widget\" id=\"new-ticket\" {% if not request.ticket %}style=\"display: none;\"{% endif %}>
    <div class=\"head\">
        <h2 class=\"dark-icon i-support\">{{ 'Submit new ticket'|trans }}</h2>
    </div>
    <div class=\"block\">
        <form action=\"\" method=\"post\" id=\"ticket-submit\">
            <fieldset>
                <legend>{{ 'Submit new support ticket'|trans }}</legend>
                <p>
                    <label>{{ 'Help desk'|trans }}: </label>
                    {{ mf.selectbox('support_helpdesk_id', client.support_helpdesk_get_pairs, request.support_helpdesk_id, 1) }}
                </p>

                <p>
                    <label>{{ 'Subject'|trans }}: </label>
                    <input type=\"text\" name=\"subject\" value=\"{{ request.subject|e }}\" required=\"required\"/>
                </p>

                <p>
                    <label>{{ 'Message'|trans }}: </label>
                    <textarea name=\"content\" cols=\"5\" rows=\"5\" required=\"required\">{{ request.content|e }}</textarea>
                </p>

                <input class=\"bb-button bb-button-submit\" type=\"submit\" value=\"{{ 'Submit'|trans }}\">
            </fieldset>
        </form>
    </div>
</div>
{% endblock %}

{% block sidebar2 %}
{% if guest.extension_is_on({\"mod\":'kb'}) %}
<div class=\"widget\">
    <div class=\"head\">
        <h2 class=\"dark-icon i-kb\">{{ 'Knowledge base'|trans }}</h2>
    </div>
    <ul class=\"menu\">
    {% for i, category in guest.kb_category_get_list.list %}
    <li><a href=\"{{ '/kb'|link }}#category-{{category.id}}\">{{ category.title }}</a></li>
    {% endfor %}
    </ul>
</div>
{% endif %}
{% endblock %}

{% block js %}
<script type=\"text/javascript\">
    \$(function() {
        \$('#new-ticket-button').bind('click', function(event) {
            \$('#new-ticket').slideToggle();
            
            return false;
        });

        \$('#ticket-submit').bind('submit', function(event) {
            bb.post(
                'client/support/ticket_create',
                \$(this).serialize(),
                function(result) {
                    bb.redirect(\"{{ 'support/ticket'|link }}\" + '/' + result);
                }
            );

            return false;
        });
    });
</script>
{% endblock %}", "mod_support_tickets.phtml", "/shared/httpd/up-boxbilling/FOSSBilling/src/bb-themes/boxbilling/html/mod_support_tickets.phtml");
    }
}
