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
class __TwigTemplate_4cc6d617e76f45e109288739a04ad38eeae53a3f12f868b8a292c3b7cd71a37b extends Template
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
        // line 3
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_support_public_tickets.phtml", 3)->unwrap();
        // line 7
        $context["active_menu"] = "support";
        // line 1
        $this->getParent($context)->display($context, array_merge($this->blocks, $blocks));
    }

    // line 5
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo "Public tickets";
    }

    // line 9
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 10
        $context["statuses"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "support_public_ticket_get_statuses", [], "any", false, false, false, 10);
        // line 11
        echo "<div class=\"stats\">
    <ul>
        <li><a href=\"";
        // line 13
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support/public-tickets", ["status" => "open"]);
        echo "\" class=\"count green\" title=\"\">";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["statuses"] ?? null), "open", [], "any", false, false, false, 13), "html", null, true);
        echo "</a><span>";
        echo twig_escape_filter($this->env, gettext("Tickets waiting for staff reply"), "html", null, true);
        echo "</span></li>
        <li><a href=\"";
        // line 14
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support/public-tickets", ["status" => "on_hold"]);
        echo "\" class=\"count blue\" title=\"\">";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["statuses"] ?? null), "on_hold", [], "any", false, false, false, 14), "html", null, true);
        echo "</a><span>";
        echo twig_escape_filter($this->env, gettext("Tickets waiting for client reply"), "html", null, true);
        echo "</span></li>
        <li><a href=\"";
        // line 15
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support/public-tickets", ["status" => "closed"]);
        echo "\" class=\"count grey\" title=\"\">";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["statuses"] ?? null), "closed", [], "any", false, false, false, 15), "html", null, true);
        echo "</a><span>";
        echo twig_escape_filter($this->env, gettext("Solved tickets"), "html", null, true);
        echo "</span></li>
        <li class=\"last\"><a href=\"";
        // line 16
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support/public-tickets");
        echo "\" class=\"count grey\" title=\"\">";
        echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["statuses"] ?? null), "open", [], "any", false, false, false, 16) + twig_get_attribute($this->env, $this->source, ($context["statuses"] ?? null), "on_hold", [], "any", false, false, false, 16)) + twig_get_attribute($this->env, $this->source, ($context["statuses"] ?? null), "closed", [], "any", false, false, false, 16)), "html", null, true);
        echo "</a><span>";
        echo twig_escape_filter($this->env, gettext("Total"), "html", null, true);
        echo "</span></li>
    </ul>
    <div class=\"fix\"></div>
</div>

<div class=\"widget\">
    <div class=\"head\"><h5 class=\"iFrames\">";
        // line 22
        echo twig_escape_filter($this->env, gettext("Public tickets"), "html", null, true);
        echo "</h5></div>

";
        // line 24
        echo twig_call_macro($macros["mf"], "macro_table_search", [], 24, $context, $this->getSourceContext());
        echo "
<table class=\"tableStatic wide\">
    <thead>
        <tr>
            <td style=\"width: 2%\"><input type=\"checkbox\" class=\"batch-delete-master-checkbox\"/></td>
            <td style=\"width: 50%\">";
        // line 29
        echo twig_escape_filter($this->env, gettext("Subject"), "html", null, true);
        echo "</td>
            <td>";
        // line 30
        echo twig_escape_filter($this->env, gettext("Email"), "html", null, true);
        echo "</td>
            <td>";
        // line 31
        echo twig_escape_filter($this->env, gettext("Status"), "html", null, true);
        echo "</td>
            <td>";
        // line 32
        echo twig_escape_filter($this->env, gettext("Date"), "html", null, true);
        echo "</td>
            <td style=\"width: 5%\">&nbsp;</td>
        </tr>
    </thead>

    <tbody>
    ";
        // line 38
        $context["tickets"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "support_public_ticket_get_list", [0 => twig_array_merge(["per_page" => 30, "page" => twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 38)], ($context["request"] ?? null))], "method", false, false, false, 38);
        // line 39
        echo "    ";
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["tickets"] ?? null), "list", [], "any", false, false, false, 39));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["i"] => $context["ticket"]) {
            // line 40
            echo "    <tr>
        <td><input type=\"checkbox\" class=\"batch-delete-checkbox\" data-item-id=\"";
            // line 41
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "id", [], "any", false, false, false, 41), "html", null, true);
            echo "\"/></td>
        <td><a href=\"";
            // line 42
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/support/public-ticket");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "id", [], "any", false, false, false, 42), "html", null, true);
            echo "\">#";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "id", [], "any", false, false, false, 42), "html", null, true);
            echo " - ";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "subject", [], "any", false, false, false, 42), "html", null, true);
            echo " (";
            echo twig_escape_filter($this->env, twig_length_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "messages", [], "any", false, false, false, 42)), "html", null, true);
            echo ")</a></td>
        <td>";
            // line 43
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "author_email", [], "any", false, false, false, 43), "html", null, true);
            echo "</td>
        <td>";
            // line 44
            echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, $context["ticket"], "status", [], "any", false, false, false, 44)], 44, $context, $this->getSourceContext());
            echo "</td>
        <td>";
            // line 45
            echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "updated_at", [], "any", false, false, false, 45), "Y-m-d"), "html", null, true);
            echo "</td>
        <td class=\"actions\">
            <a class=\"bb-button btn14\" href=\"";
            // line 47
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/support/public-ticket");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "id", [], "any", false, false, false, 47), "html", null, true);
            echo "\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
        </td>
    </tr>
    ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 51
            echo "        <tr>
            <td colspan=\"5\">
                ";
            // line 53
            echo twig_escape_filter($this->env, gettext("The list is empty"), "html", null, true);
            echo "
            </td>
        </tr>
    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['i'], $context['ticket'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 57
        echo "    </tbody>
</table>
</div>
";
        // line 60
        $this->loadTemplate("partial_batch_delete.phtml", "mod_support_public_tickets.phtml", 60)->display(twig_array_merge($context, ["action" => "admin/support/batch_delete_public"]));
        // line 61
        $this->loadTemplate("partial_pagination.phtml", "mod_support_public_tickets.phtml", 61)->display(twig_array_merge($context, ["list" => ($context["tickets"] ?? null), "url" => "support/public-tickets"]));
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
        return array (  211 => 61,  209 => 60,  204 => 57,  194 => 53,  190 => 51,  179 => 47,  174 => 45,  170 => 44,  166 => 43,  154 => 42,  150 => 41,  147 => 40,  141 => 39,  139 => 38,  130 => 32,  126 => 31,  122 => 30,  118 => 29,  110 => 24,  105 => 22,  92 => 16,  84 => 15,  76 => 14,  68 => 13,  64 => 11,  62 => 10,  58 => 9,  51 => 5,  47 => 1,  45 => 7,  43 => 3,  36 => 1,);
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
        <li><a href=\"{{ 'support/public-tickets'|alink({ 'status': 'open' }) }}\" class=\"count green\" title=\"\">{{ statuses.open }}</a><span>{{ 'Tickets waiting for staff reply'|trans }}</span></li>
        <li><a href=\"{{ 'support/public-tickets'|alink({ 'status': 'on_hold' }) }}\" class=\"count blue\" title=\"\">{{ statuses.on_hold }}</a><span>{{ 'Tickets waiting for client reply'|trans }}</span></li>
        <li><a href=\"{{ 'support/public-tickets'|alink({ 'status': 'closed' }) }}\" class=\"count grey\" title=\"\">{{ statuses.closed }}</a><span>{{ 'Solved tickets'|trans }}</span></li>
        <li class=\"last\"><a href=\"{{ 'support/public-tickets'|alink }}\" class=\"count grey\" title=\"\">{{ statuses.open + statuses.on_hold + statuses.closed }}</a><span>{{ 'Total'|trans }}</span></li>
    </ul>
    <div class=\"fix\"></div>
</div>

<div class=\"widget\">
    <div class=\"head\"><h5 class=\"iFrames\">{{ 'Public tickets'|trans }}</h5></div>

{{ mf.table_search }}
<table class=\"tableStatic wide\">
    <thead>
        <tr>
            <td style=\"width: 2%\"><input type=\"checkbox\" class=\"batch-delete-master-checkbox\"/></td>
            <td style=\"width: 50%\">{{ 'Subject'|trans }}</td>
            <td>{{ 'Email'|trans }}</td>
            <td>{{ 'Status'|trans }}</td>
            <td>{{ 'Date'|trans }}</td>
            <td style=\"width: 5%\">&nbsp;</td>
        </tr>
    </thead>

    <tbody>
    {% set tickets = admin.support_public_ticket_get_list({ \"per_page\": 30, \"page\": request.page }|merge(request)) %}
    {% for i, ticket in tickets.list %}
    <tr>
        <td><input type=\"checkbox\" class=\"batch-delete-checkbox\" data-item-id=\"{{ ticket.id }}\"/></td>
        <td><a href=\"{{ '/support/public-ticket'|alink }}/{{ ticket.id }}\">#{{ ticket.id }} - {{ ticket.subject }} ({{ ticket.messages|length }})</a></td>
        <td>{{ ticket.author_email }}</td>
        <td>{{ mf.status_name(ticket.status) }}</td>
        <td>{{ ticket.updated_at|date('Y-m-d') }}</td>
        <td class=\"actions\">
            <a class=\"bb-button btn14\" href=\"{{ '/support/public-ticket'|alink }}/{{ ticket.id }}\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
        </td>
    </tr>
    {% else %}
        <tr>
            <td colspan=\"5\">
                {{ 'The list is empty'|trans }}
            </td>
        </tr>
    {% endfor %}
    </tbody>
</table>
</div>
{% include \"partial_batch_delete.phtml\" with { 'action': 'admin/support/batch_delete_public' } %}
{% include \"partial_pagination.phtml\" with { 'list': tickets, 'url': 'support/public-tickets' } %}
{% endblock %}
", "mod_support_public_tickets.phtml", "/shared/httpd/up-boxbilling/FOSSBilling/src/bb-themes/admin_default/html/mod_support_public_tickets.phtml");
    }
}
