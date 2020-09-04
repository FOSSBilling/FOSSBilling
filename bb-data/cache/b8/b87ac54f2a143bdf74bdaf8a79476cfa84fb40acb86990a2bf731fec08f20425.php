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

/* mod_support_public_tickets.phtml */
class __TwigTemplate_653feb0025278d552404e3b286062d17f44d7dad440ecdc6f592edf98507b2ff extends \Twig\Template
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
        return $this->loadTemplate(((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ajax", [], "any", false, false, false, 1)) ? ("layout_blank.phtml") : ("layout_default.phtml")), "mod_support_public_tickets.phtml", 1);
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 2
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_support_public_tickets.phtml", 2)->unwrap();
        // line 4
        $context["active_menu"] = "support";
        // line 1
        $this->getParent($context)->display($context, array_merge($this->blocks, $blocks));
    }

    // line 3
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo "Public tickets";
    }

    // line 5
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 6
        $context["statuses"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "support_public_ticket_get_statuses", [], "any", false, false, false, 6);
        // line 7
        echo "<div class=\"stats\">
    <ul>
        <li><a href=\"";
        // line 9
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support/public-tickets", ["status" => "open"]);
        echo "\" class=\"count green\" title=\"\">";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["statuses"] ?? null), "open", [], "any", false, false, false, 9), "html", null, true);
        echo "</a><span>";
        echo gettext("Tickets waiting for staff reply");
        echo "</span></li>
        <li><a href=\"";
        // line 10
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support/public-tickets", ["status" => "on_hold"]);
        echo "\" class=\"count blue\" title=\"\">";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["statuses"] ?? null), "on_hold", [], "any", false, false, false, 10), "html", null, true);
        echo "</a><span>";
        echo gettext("Tickets waiting for client reply");
        echo "</span></li>
        <li><a href=\"";
        // line 11
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support/public-tickets", ["status" => "closed"]);
        echo "\" class=\"count grey\" title=\"\">";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["statuses"] ?? null), "closed", [], "any", false, false, false, 11), "html", null, true);
        echo "</a><span>";
        echo gettext("Solved tickets");
        echo "</span></li>
        <li class=\"last\"><a href=\"";
        // line 12
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support/public-tickets");
        echo "\" class=\"count grey\" title=\"\">";
        echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["statuses"] ?? null), "open", [], "any", false, false, false, 12) + twig_get_attribute($this->env, $this->source, ($context["statuses"] ?? null), "on_hold", [], "any", false, false, false, 12)) + twig_get_attribute($this->env, $this->source, ($context["statuses"] ?? null), "closed", [], "any", false, false, false, 12)), "html", null, true);
        echo "</a><span>";
        echo gettext("Total");
        echo "</span></li>
    </ul>
    <div class=\"fix\"></div>
</div>

<div class=\"widget\">
    <div class=\"head\"><h5 class=\"iFrames\">";
        // line 18
        echo gettext("Public tickets");
        echo "</h5></div>

";
        // line 20
        echo twig_call_macro($macros["mf"], "macro_table_search", [], 20, $context, $this->getSourceContext());
        echo "
<table class=\"tableStatic wide\">
    <thead>
        <tr>
            <td style=\"width: 2%\"><input type=\"checkbox\" class=\"batch-delete-master-checkbox\"/></td>
            <td style=\"width: 50%\">";
        // line 25
        echo gettext("Subject");
        echo "</td>
            <td>";
        // line 26
        echo gettext("Email");
        echo "</td>
            <td>";
        // line 27
        echo gettext("Status");
        echo "</td>
            <td>";
        // line 28
        echo gettext("Date");
        echo "</td>
            <td style=\"width: 5%\">&nbsp;</td>
        </tr>
    </thead>

    <tbody>
    ";
        // line 34
        $context["tickets"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "support_public_ticket_get_list", [0 => twig_array_merge(["per_page" => 30, "page" => twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 34)], ($context["request"] ?? null))], "method", false, false, false, 34);
        // line 35
        echo "    ";
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["tickets"] ?? null), "list", [], "any", false, false, false, 35));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["i"] => $context["ticket"]) {
            // line 36
            echo "    <tr>
        <td><input type=\"checkbox\" class=\"batch-delete-checkbox\" data-item-id=\"";
            // line 37
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "id", [], "any", false, false, false, 37), "html", null, true);
            echo "\"/></td>
        <td><a href=\"";
            // line 38
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/support/public-ticket");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "id", [], "any", false, false, false, 38), "html", null, true);
            echo "\">#";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "id", [], "any", false, false, false, 38), "html", null, true);
            echo " - ";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "subject", [], "any", false, false, false, 38), "html", null, true);
            echo " (";
            echo twig_escape_filter($this->env, twig_length_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "messages", [], "any", false, false, false, 38)), "html", null, true);
            echo ")</a></td>
        <td>";
            // line 39
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "author_email", [], "any", false, false, false, 39), "html", null, true);
            echo "</td>
        <td>";
            // line 40
            echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, $context["ticket"], "status", [], "any", false, false, false, 40)], 40, $context, $this->getSourceContext());
            echo "</td>
        <td>";
            // line 41
            echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "updated_at", [], "any", false, false, false, 41), "Y-m-d"), "html", null, true);
            echo "</td>
        <td class=\"actions\">
            <a class=\"bb-button btn14\" href=\"";
            // line 43
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/support/public-ticket");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "id", [], "any", false, false, false, 43), "html", null, true);
            echo "\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
        </td>
    </tr>
    ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 47
            echo "        <tr>
            <td colspan=\"5\">
                ";
            // line 49
            echo gettext("The list is empty");
            // line 50
            echo "            </td>
        </tr>
    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['i'], $context['ticket'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 53
        echo "    </tbody>
</table>
</div>
";
        // line 56
        $this->loadTemplate("partial_batch_delete.phtml", "mod_support_public_tickets.phtml", 56)->display(twig_array_merge($context, ["action" => "admin/support/batch_delete_public"]));
        // line 57
        $this->loadTemplate("partial_pagination.phtml", "mod_support_public_tickets.phtml", 57)->display(twig_array_merge($context, ["list" => ($context["tickets"] ?? null), "url" => "support/public-tickets"]));
        // line 58
        echo "
";
    }

    public function getTemplateName()
    {
        return "mod_support_public_tickets.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  213 => 58,  211 => 57,  209 => 56,  204 => 53,  196 => 50,  194 => 49,  190 => 47,  179 => 43,  174 => 41,  170 => 40,  166 => 39,  154 => 38,  150 => 37,  147 => 36,  141 => 35,  139 => 34,  130 => 28,  126 => 27,  122 => 26,  118 => 25,  110 => 20,  105 => 18,  92 => 12,  84 => 11,  76 => 10,  68 => 9,  64 => 7,  62 => 6,  58 => 5,  51 => 3,  47 => 1,  45 => 4,  43 => 2,  36 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}
{% import \"macro_functions.phtml\" as mf %}
{% block meta_title %}Public tickets{% endblock %}
{% set active_menu = 'support' %}
{% block content %}
{% set statuses = admin.support_public_ticket_get_statuses %}
<div class=\"stats\">
    <ul>
        <li><a href=\"{{ 'support/public-tickets'|alink({'status' : 'open'}) }}\" class=\"count green\" title=\"\">{{ statuses.open }}</a><span>{% trans 'Tickets waiting for staff reply' %}</span></li>
        <li><a href=\"{{ 'support/public-tickets'|alink({'status' : 'on_hold'}) }}\" class=\"count blue\" title=\"\">{{ statuses.on_hold }}</a><span>{% trans 'Tickets waiting for client reply' %}</span></li>
        <li><a href=\"{{ 'support/public-tickets'|alink({'status' : 'closed'}) }}\" class=\"count grey\" title=\"\">{{ statuses.closed }}</a><span>{% trans 'Solved tickets' %}</span></li>
        <li class=\"last\"><a href=\"{{ 'support/public-tickets'|alink }}\" class=\"count grey\" title=\"\">{{ statuses.open + statuses.on_hold + statuses.closed }}</a><span>{% trans 'Total' %}</span></li>
    </ul>
    <div class=\"fix\"></div>
</div>

<div class=\"widget\">
    <div class=\"head\"><h5 class=\"iFrames\">{% trans 'Public tickets' %}</h5></div>

{{ mf.table_search }}
<table class=\"tableStatic wide\">
    <thead>
        <tr>
            <td style=\"width: 2%\"><input type=\"checkbox\" class=\"batch-delete-master-checkbox\"/></td>
            <td style=\"width: 50%\">{% trans 'Subject' %}</td>
            <td>{% trans 'Email' %}</td>
            <td>{% trans 'Status' %}</td>
            <td>{% trans 'Date' %}</td>
            <td style=\"width: 5%\">&nbsp;</td>
        </tr>
    </thead>

    <tbody>
    {% set tickets = admin.support_public_ticket_get_list({\"per_page\":30, \"page\":request.page}|merge(request)) %}
    {% for i, ticket in tickets.list %}
    <tr>
        <td><input type=\"checkbox\" class=\"batch-delete-checkbox\" data-item-id=\"{{ ticket.id }}\"/></td>
        <td><a href=\"{{ '/support/public-ticket'|alink }}/{{ticket.id}}\">#{{ ticket.id }} - {{ ticket.subject }} ({{ ticket.messages|length}})</a></td>
        <td>{{ ticket.author_email }}</td>
        <td>{{ mf.status_name(ticket.status) }}</td>
        <td>{{ ticket.updated_at|date('Y-m-d') }}</td>
        <td class=\"actions\">
            <a class=\"bb-button btn14\" href=\"{{ '/support/public-ticket'|alink }}/{{ticket.id}}\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
        </td>
    </tr>
    {% else %}
        <tr>
            <td colspan=\"5\">
                {% trans 'The list is empty' %}
            </td>
        </tr>
    {% endfor %}
    </tbody>
</table>
</div>
{% include \"partial_batch_delete.phtml\" with {'action':'admin/support/batch_delete_public'} %}
{% include \"partial_pagination.phtml\" with {'list': tickets, 'url':'support/public-tickets'} %}

{% endblock %}
", "mod_support_public_tickets.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-themes/admin_default/html/mod_support_public_tickets.phtml");
    }
}
