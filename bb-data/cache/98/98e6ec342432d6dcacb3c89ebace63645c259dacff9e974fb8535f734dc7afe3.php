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
class __TwigTemplate_c3e3a874164fb36eaebfeece714a8aaf1c1504d4a3f367df848c9da53887f429 extends \Twig\Template
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
        // line 2
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_support_tickets.phtml", 2)->unwrap();
        // line 1
        $this->getParent($context)->display($context, array_merge($this->blocks, $blocks));
    }

    // line 3
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo gettext("Support tickets");
    }

    // line 4
    public function block_page_header($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo gettext("Support tickets");
    }

    // line 5
    public function block_breadcrumb($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo gettext("Support tickets");
    }

    // line 6
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 7
        echo "

<div class=\"row\">
    <article class=\"span12 data-block\">
        <div class=\"data-container\">
            <header>
                <h1>";
        // line 13
        echo gettext("Support tickets");
        echo "</h1><br/>
                ";
        // line 14
        echo gettext("Need an answer? We are here to help!");
        // line 15
        echo "                <ul class=\"data-header-actions\">
                    <li>
                        <a class=\"btn btn-alt btn-info\" href=\"#submit-ticket\" id=\"new-ticket-button\" data-toggle=\"modal\">";
        // line 17
        echo gettext("Submit new ticket");
        echo "</a>
                    </li>
                </ul>
            </header>
            <section>
                <table class=\"table table-striped table-bordered table-condensed\">
                    <thead>
                    <tr>
                        <th>";
        // line 25
        echo gettext("Id");
        echo "</th>
                        <th>";
        // line 26
        echo gettext("Subject");
        echo "</th>
                        <th>";
        // line 27
        echo gettext("Help desk");
        echo "</th>
                        <th>";
        // line 28
        echo gettext("Status");
        echo "</th>
                        <th>";
        // line 29
        echo gettext("Submitted");
        echo "</th>
                        <th>";
        // line 30
        echo gettext("Actions");
        echo "</th>
                    </tr>
                    </thead>

                    <tbody>
                    ";
        // line 35
        $context["tickets"] = twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "support_ticket_get_list", [0 => ["per_page" => 10, "page" => twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 35)]], "method", false, false, false, 35);
        // line 36
        echo "                        ";
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["tickets"] ?? null), "list", [], "any", false, false, false, 36));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["i"] => $context["ticket"]) {
            // line 37
            echo "
                    <tr>
                        <td>#";
            // line 39
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "id", [], "any", false, false, false, 39), "html", null, true);
            echo "</td>
                        <td><a href=\"";
            // line 40
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("support/ticket");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "id", [], "any", false, false, false, 40), "html", null, true);
            echo "\">";
            echo twig_escape_filter($this->env, twig_truncate_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "subject", [], "any", false, false, false, 40), 40), "html", null, true);
            echo "</a></td>
                        <td>";
            // line 41
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["ticket"], "helpdesk", [], "any", false, false, false, 41), "name", [], "any", false, false, false, 41), "html", null, true);
            echo "</td>
                        <td><span class=\"label ";
            // line 42
            if ((twig_get_attribute($this->env, $this->source, $context["ticket"], "status", [], "any", false, false, false, 42) == "open")) {
                echo "label-info";
            } elseif ((twig_get_attribute($this->env, $this->source, $context["ticket"], "status", [], "any", false, false, false, 42) == "on_hold")) {
                echo "label-warning";
            }
            echo "\">";
            echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, $context["ticket"], "status", [], "any", false, false, false, 42)], 42, $context, $this->getSourceContext());
            echo "</span></td>
                        <td>";
            // line 43
            echo twig_escape_filter($this->env, twig_timeago_filter(twig_get_attribute($this->env, $this->source, $context["ticket"], "created_at", [], "any", false, false, false, 43)), "html", null, true);
            echo " ";
            echo gettext("ago");
            echo "</td>
                        <td class=\"actions\"><a href=\"";
            // line 44
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("support/ticket");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "id", [], "any", false, false, false, 44), "html", null, true);
            echo "\" class=\"btn btn-small\">";
            if ((twig_get_attribute($this->env, $this->source, $context["ticket"], "status", [], "any", false, false, false, 44) == "closed")) {
                echo gettext("View");
            } else {
                echo gettext("Reply");
            }
            echo "</a></td>
                    </tr>

                    ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 48
            echo "
                    <tr>
                        <td colspan=\"6\">";
            // line 50
            echo gettext("The list is empty");
            echo "</td>
                    </tr>

                    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['i'], $context['ticket'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 54
        echo "
                    </tbody>

                    ";
        // line 57
        if ((twig_get_attribute($this->env, $this->source, ($context["tickets"] ?? null), "pages", [], "any", false, false, false, 57) > 1)) {
            // line 58
            echo "                    <tfoot>
                    <tr>
                        <td colspan=\"6\">
                            ";
            // line 61
            $this->loadTemplate("partial_pagination.phtml", "mod_support_tickets.phtml", 61)->display(twig_array_merge($context, ["list" => ($context["tickets"] ?? null)]));
            // line 62
            echo "                        </td>
                    </tr>
                    </tfoot>
                    ";
        }
        // line 66
        echo "                </table>

            </section>
        </div>
    </article>
</div>



<div id=\"submit-ticket\" class=\"modal hide fade\" tabindex=\"-1\" role=\"dialog\" aria-labelledby=\"myModalLabel\" aria-hidden=\"true\">
    <div class=\"modal-header\">
        <button type=\"button\" class=\"close\" data-dismiss=\"modal\" aria-hidden=\"true\">&times;</button>
        <h3>
            <span class=\"awe-wrench\"></span> ";
        // line 79
        echo gettext("Submit new support ticket");
        // line 80
        echo "        </h3>
    </div>
    <div class=\"modal-body\">
        <form action=\"\" method=\"post\" id=\"ticket-submit\" class=\"form\" style=\"background: none\">
                <div class=\"control-group\">
                    <label>";
        // line 85
        echo gettext("Help desk");
        echo ": </label>
                    <div class=\"controls\">
                        ";
        // line 87
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["support_helpdesk_id", twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "support_helpdesk_get_pairs", [], "any", false, false, false, 87), twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "support_helpdesk_id", [], "any", false, false, false, 87), 1], 87, $context, $this->getSourceContext());
        echo "
                    </div>
                </div>

                <div class=\"control-group\">
                    <label>";
        // line 92
        echo gettext("Subject");
        echo ": </label>
                    <div class=\"controls\">
                        <input type=\"text\" name=\"subject\" value=\"";
        // line 94
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "subject", [], "any", false, false, false, 94));
        echo "\" required=\"required\" class=\"span5\"/>
                    </div>
                </div>

                <div class=\"control-group\">
                    <label>";
        // line 99
        echo gettext("Message");
        echo ": </label>
                    <div class=\"controls\">
                        <textarea name=\"content\" cols=\"10\" rows=\"10\" required=\"required\" class=\"span5\">";
        // line 101
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "content", [], "any", false, false, false, 101));
        echo "</textarea>
                    </div>
                </div>
    </div>
    <div class=\"modal-footer\">
        <button class=\"btn btn-primary btn-large\" type=\"submit\" value=\"";
        // line 106
        echo gettext("Submit");
        echo "\">";
        echo gettext("Submit");
        echo "</button>
    </div>
    </form>
</div>
";
    }

    // line 113
    public function block_js($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 114
        echo "<script type=\"text/javascript\">
\$(function() {
    \$('#ticket-submit').bind('submit',function(event){
        \$('.wait').show();
        bb.post(
            'client/support/ticket_create',
            \$(this).serialize(),
            function(result) {
                bb.redirect('";
        // line 122
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("support/ticket");
        echo "' + '/' + result);
            }
        );
        return false;
    });
    \$('#submit-ticket').modal('hide').css(
        {
            'margin-top': function () {
                return -(\$(this).height() / 2);
            }
        })

    ";
        // line 134
        if (twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ticket", [], "any", false, false, false, 134)) {
            // line 135
            echo "    \$('#submit-ticket').modal('show');
    ";
        }
        // line 137
        echo "
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
        return array (  338 => 137,  334 => 135,  332 => 134,  317 => 122,  307 => 114,  303 => 113,  292 => 106,  284 => 101,  279 => 99,  271 => 94,  266 => 92,  258 => 87,  253 => 85,  246 => 80,  244 => 79,  229 => 66,  223 => 62,  221 => 61,  216 => 58,  214 => 57,  209 => 54,  199 => 50,  195 => 48,  178 => 44,  172 => 43,  162 => 42,  158 => 41,  150 => 40,  146 => 39,  142 => 37,  136 => 36,  134 => 35,  126 => 30,  122 => 29,  118 => 28,  114 => 27,  110 => 26,  106 => 25,  95 => 17,  91 => 15,  89 => 14,  85 => 13,  77 => 7,  73 => 6,  66 => 5,  59 => 4,  52 => 3,  48 => 1,  46 => 2,  39 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}
{% import \"macro_functions.phtml\" as mf %}
{% block meta_title %}{% trans 'Support tickets' %}{% endblock %}
{% block page_header %}{% trans 'Support tickets' %}{% endblock %}
{% block breadcrumb %}{% trans 'Support tickets' %}{% endblock %}
{% block content %}


<div class=\"row\">
    <article class=\"span12 data-block\">
        <div class=\"data-container\">
            <header>
                <h1>{% trans 'Support tickets'%}</h1><br/>
                {% trans 'Need an answer? We are here to help!' %}
                <ul class=\"data-header-actions\">
                    <li>
                        <a class=\"btn btn-alt btn-info\" href=\"#submit-ticket\" id=\"new-ticket-button\" data-toggle=\"modal\">{% trans 'Submit new ticket' %}</a>
                    </li>
                </ul>
            </header>
            <section>
                <table class=\"table table-striped table-bordered table-condensed\">
                    <thead>
                    <tr>
                        <th>{% trans 'Id' %}</th>
                        <th>{% trans 'Subject' %}</th>
                        <th>{% trans 'Help desk' %}</th>
                        <th>{% trans 'Status' %}</th>
                        <th>{% trans 'Submitted' %}</th>
                        <th>{% trans 'Actions' %}</th>
                    </tr>
                    </thead>

                    <tbody>
                    {% set tickets = client.support_ticket_get_list({\"per_page\":10, \"page\":request.page}) %}
                        {% for i, ticket in tickets.list %}

                    <tr>
                        <td>#{{ticket.id}}</td>
                        <td><a href=\"{{ 'support/ticket'|link }}/{{ticket.id}}\">{{ticket.subject|truncate(40)}}</a></td>
                        <td>{{ticket.helpdesk.name}}</td>
                        <td><span class=\"label {% if ticket.status=='open'%}label-info{% elseif ticket.status == 'on_hold'%}label-warning{%endif%}\">{{mf.status_name(ticket.status)}}</span></td>
                        <td>{{ticket.created_at|timeago}} {% trans 'ago' %}</td>
                        <td class=\"actions\"><a href=\"{{ 'support/ticket'|link }}/{{ticket.id}}\" class=\"btn btn-small\">{% if ticket.status=='closed'%}{% trans 'View' %}{% else %}{% trans 'Reply' %}{%endif%}</a></td>
                    </tr>

                    {% else %}

                    <tr>
                        <td colspan=\"6\">{% trans 'The list is empty' %}</td>
                    </tr>

                    {% endfor %}

                    </tbody>

                    {% if tickets.pages > 1 %}
                    <tfoot>
                    <tr>
                        <td colspan=\"6\">
                            {% include \"partial_pagination.phtml\" with {'list': tickets} %}
                        </td>
                    </tr>
                    </tfoot>
                    {% endif %}
                </table>

            </section>
        </div>
    </article>
</div>



<div id=\"submit-ticket\" class=\"modal hide fade\" tabindex=\"-1\" role=\"dialog\" aria-labelledby=\"myModalLabel\" aria-hidden=\"true\">
    <div class=\"modal-header\">
        <button type=\"button\" class=\"close\" data-dismiss=\"modal\" aria-hidden=\"true\">&times;</button>
        <h3>
            <span class=\"awe-wrench\"></span> {% trans 'Submit new support ticket' %}
        </h3>
    </div>
    <div class=\"modal-body\">
        <form action=\"\" method=\"post\" id=\"ticket-submit\" class=\"form\" style=\"background: none\">
                <div class=\"control-group\">
                    <label>{% trans 'Help desk' %}: </label>
                    <div class=\"controls\">
                        {{ mf.selectbox('support_helpdesk_id', client.support_helpdesk_get_pairs, request.support_helpdesk_id, 1) }}
                    </div>
                </div>

                <div class=\"control-group\">
                    <label>{% trans 'Subject' %}: </label>
                    <div class=\"controls\">
                        <input type=\"text\" name=\"subject\" value=\"{{ request.subject|e }}\" required=\"required\" class=\"span5\"/>
                    </div>
                </div>

                <div class=\"control-group\">
                    <label>{% trans 'Message' %}: </label>
                    <div class=\"controls\">
                        <textarea name=\"content\" cols=\"10\" rows=\"10\" required=\"required\" class=\"span5\">{{ request.content|e }}</textarea>
                    </div>
                </div>
    </div>
    <div class=\"modal-footer\">
        <button class=\"btn btn-primary btn-large\" type=\"submit\" value=\"{% trans 'Submit' %}\">{% trans 'Submit' %}</button>
    </div>
    </form>
</div>
{% endblock %}


{% block js %}
<script type=\"text/javascript\">
\$(function() {
    \$('#ticket-submit').bind('submit',function(event){
        \$('.wait').show();
        bb.post(
            'client/support/ticket_create',
            \$(this).serialize(),
            function(result) {
                bb.redirect('{{ 'support/ticket'|link }}' + '/' + result);
            }
        );
        return false;
    });
    \$('#submit-ticket').modal('hide').css(
        {
            'margin-top': function () {
                return -(\$(this).height() / 2);
            }
        })

    {% if request.ticket %}
    \$('#submit-ticket').modal('show');
    {% endif %}

});
</script>
{% endblock %}", "mod_support_tickets.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/src/bb-modules/Support/html_client/mod_support_tickets.phtml");
    }
}
