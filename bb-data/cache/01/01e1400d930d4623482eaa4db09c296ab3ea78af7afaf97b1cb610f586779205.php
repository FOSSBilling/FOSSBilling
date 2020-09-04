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

/* mod_activity_index.phtml */
class __TwigTemplate_ea1b92bbb9822e91c7858a0b1dd0af1c4ce206f777b23252f0f74b9c2e9594a5 extends \Twig\Template
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
        return $this->loadTemplate(((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ajax", [], "any", false, false, false, 1)) ? ("layout_blank.phtml") : ("layout_default.phtml")), "mod_activity_index.phtml", 1);
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 2
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_activity_index.phtml", 2)->unwrap();
        // line 4
        $context["active_menu"] = "activity";
        // line 1
        $this->getParent($context)->display($context, array_merge($this->blocks, $blocks));
    }

    // line 3
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo "System activity";
    }

    // line 6
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 7
        echo "<div class=\"widget\">
    <div class=\"head\"><h5 class=\"iFrames\">";
        // line 8
        echo gettext("System activity");
        echo "</h5></div>

";
        // line 10
        echo twig_call_macro($macros["mf"], "macro_table_search", [], 10, $context, $this->getSourceContext());
        echo "
<table class=\"tableStatic wide\">
    <thead>
        <tr>
            <td style=\"width: 2%\"><input type=\"checkbox\" class=\"batch-delete-master-checkbox\"/></td>
            <td colspan=\"2\">";
        // line 15
        echo gettext("Message");
        echo "</td>
            <td>";
        // line 16
        echo gettext("Ip");
        echo "</td>
            <td>";
        // line 17
        echo gettext("Country");
        echo "</td>
            <td>";
        // line 18
        echo gettext("Date");
        echo "</td>
            <td style=\"width: 5%\">&nbsp;</td>
        </tr>
    </thead>

    <tbody>
    ";
        // line 24
        $context["events"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "activity_log_get_list", [0 => twig_array_merge(["per_page" => 30, "page" => twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 24)], ($context["request"] ?? null))], "method", false, false, false, 24);
        // line 25
        echo "    ";
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["events"] ?? null), "list", [], "any", false, false, false, 25));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["i"] => $context["event"]) {
            // line 26
            echo "    <tr>
        <td><input type=\"checkbox\" class=\"batch-delete-checkbox\" data-item-id=\"";
            // line 27
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["event"], "id", [], "any", false, false, false, 27), "html", null, true);
            echo "\"/></td>
        <td>
            ";
            // line 29
            if (twig_get_attribute($this->env, $this->source, $context["event"], "client", [], "any", false, false, false, 29)) {
                // line 30
                echo "            <a href=\"";
                echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("client/manage");
                echo "/";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["event"], "client", [], "any", false, false, false, 30), "id", [], "any", false, false, false, 30), "html", null, true);
                echo "\" title=\"";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["event"], "client", [], "any", false, false, false, 30), "name", [], "any", false, false, false, 30), "html", null, true);
                echo "\"><img src=\"";
                echo twig_escape_filter($this->env, twig_gravatar_filter(twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["event"], "client", [], "any", false, false, false, 30), "email", [], "any", false, false, false, 30)), "html", null, true);
                echo "?size=20\" alt=\"";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["event"], "client", [], "any", false, false, false, 30), "name", [], "any", false, false, false, 30), "html", null, true);
                echo "\" /> ";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["event"], "client", [], "any", false, false, false, 30), "name", [], "any", false, false, false, 30), "html", null, true);
                echo " </a>
            ";
            } elseif (twig_get_attribute($this->env, $this->source,             // line 31
$context["event"], "staff", [], "any", false, false, false, 31)) {
                // line 32
                echo "            <a href=\"";
                echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("staff/manage");
                echo "/";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["event"], "staff", [], "any", false, false, false, 32), "id", [], "any", false, false, false, 32), "html", null, true);
                echo "\" title=\"";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["event"], "staff", [], "any", false, false, false, 32), "name", [], "any", false, false, false, 32), "html", null, true);
                echo "\"><img src=\"";
                echo twig_escape_filter($this->env, twig_gravatar_filter(twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["event"], "staff", [], "any", false, false, false, 32), "email", [], "any", false, false, false, 32)), "html", null, true);
                echo "?size=20\" alt=\"";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["event"], "staff", [], "any", false, false, false, 32), "name", [], "any", false, false, false, 32), "html", null, true);
                echo "\" /> ";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["event"], "staff", [], "any", false, false, false, 32), "name", [], "any", false, false, false, 32), "html", null, true);
                echo "</a>
            ";
            } else {
                // line 34
                echo "            Guest
            ";
            }
            // line 36
            echo "        </td>
        <td><div style=\"overflow: auto; width: 250px;\">";
            // line 37
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["event"], "message", [], "any", false, false, false, 37), "html", null, true);
            echo "</div></td>
        <td>";
            // line 38
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["event"], "ip", [], "any", false, false, false, 38), "html", null, true);
            echo "</td>
        <td>";
            // line 39
            echo twig_escape_filter($this->env, _twig_default_filter($this->extensions['Box_TwigExtensions']->twig_ipcountryname_filter(twig_get_attribute($this->env, $this->source, $context["event"], "ip", [], "any", false, false, false, 39)), "Unknown"), "html", null, true);
            echo "</td>
        <td>";
            // line 40
            echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, $context["event"], "created_at", [], "any", false, false, false, 40), "Y-m-d H:i"), "html", null, true);
            echo "</td>
        <td class=\"actions\">
            <a class=\"bb-button btn14 bb-rm-tr api-link\" data-api-confirm=\"";
            // line 42
            echo gettext("Are you sure?");
            echo "\" data-api-redirect=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("activity");
            echo "\" href=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/activity/log_delete", ["id" => twig_get_attribute($this->env, $this->source, $context["event"], "id", [], "any", false, false, false, 42)]);
            echo "\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
        </td>
    </tr>
    ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 46
            echo "    <tr>
        <td colspan=\"6\">
            ";
            // line 48
            echo gettext("The list is empty");
            // line 49
            echo "        </td>
    </tr>
    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['i'], $context['event'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 52
        echo "    </tbody>
</table>

</div>
";
        // line 56
        $this->loadTemplate("partial_batch_delete.phtml", "mod_activity_index.phtml", 56)->display(twig_array_merge($context, ["action" => "admin/activity/batch_delete"]));
        // line 57
        $this->loadTemplate("partial_pagination.phtml", "mod_activity_index.phtml", 57)->display(twig_array_merge($context, ["list" => ($context["events"] ?? null), "url" => "activity"]));
        // line 58
        echo "
";
    }

    public function getTemplateName()
    {
        return "mod_activity_index.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  211 => 58,  209 => 57,  207 => 56,  201 => 52,  193 => 49,  191 => 48,  187 => 46,  174 => 42,  169 => 40,  165 => 39,  161 => 38,  157 => 37,  154 => 36,  150 => 34,  134 => 32,  132 => 31,  117 => 30,  115 => 29,  110 => 27,  107 => 26,  101 => 25,  99 => 24,  90 => 18,  86 => 17,  82 => 16,  78 => 15,  70 => 10,  65 => 8,  62 => 7,  58 => 6,  51 => 3,  47 => 1,  45 => 4,  43 => 2,  36 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}
{% import \"macro_functions.phtml\" as mf %}
{% block meta_title %}System activity{% endblock %}
{% set active_menu = 'activity' %}

{% block content %}
<div class=\"widget\">
    <div class=\"head\"><h5 class=\"iFrames\">{% trans 'System activity' %}</h5></div>

{{ mf.table_search }}
<table class=\"tableStatic wide\">
    <thead>
        <tr>
            <td style=\"width: 2%\"><input type=\"checkbox\" class=\"batch-delete-master-checkbox\"/></td>
            <td colspan=\"2\">{% trans 'Message' %}</td>
            <td>{% trans 'Ip' %}</td>
            <td>{% trans 'Country' %}</td>
            <td>{% trans 'Date' %}</td>
            <td style=\"width: 5%\">&nbsp;</td>
        </tr>
    </thead>

    <tbody>
    {% set events = admin.activity_log_get_list({\"per_page\":30, \"page\":request.page}|merge(request)) %}
    {% for i, event in events.list %}
    <tr>
        <td><input type=\"checkbox\" class=\"batch-delete-checkbox\" data-item-id=\"{{ event.id }}\"/></td>
        <td>
            {% if event.client %}
            <a href=\"{{ 'client/manage'|alink }}/{{ event.client.id }}\" title=\"{{ event.client.name }}\"><img src=\"{{ event.client.email|gravatar }}?size=20\" alt=\"{{ event.client.name }}\" /> {{ event.client.name }} </a>
            {% elseif event.staff %}
            <a href=\"{{ 'staff/manage'|alink }}/{{ event.staff.id }}\" title=\"{{ event.staff.name }}\"><img src=\"{{ event.staff.email|gravatar }}?size=20\" alt=\"{{ event.staff.name }}\" /> {{ event.staff.name }}</a>
            {% else %}
            Guest
            {% endif %}
        </td>
        <td><div style=\"overflow: auto; width: 250px;\">{{ event.message }}</div></td>
        <td>{{ event.ip }}</td>
        <td>{{ event.ip|ipcountryname|default('Unknown') }}</td>
        <td>{{ event.created_at|date('Y-m-d H:i') }}</td>
        <td class=\"actions\">
            <a class=\"bb-button btn14 bb-rm-tr api-link\" data-api-confirm=\"{% trans 'Are you sure?' %}\" data-api-redirect=\"{{ 'activity'|alink }}\" href=\"{{ 'api/admin/activity/log_delete'|link({'id' : event.id}) }}\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
        </td>
    </tr>
    {% else %}
    <tr>
        <td colspan=\"6\">
            {% trans 'The list is empty' %}
        </td>
    </tr>
    {% endfor %}
    </tbody>
</table>

</div>
{% include \"partial_batch_delete.phtml\" with {'action' : 'admin/activity/batch_delete'} %}
{% include \"partial_pagination.phtml\" with {'list': events, 'url':'activity'} %}

{% endblock %}
", "mod_activity_index.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-themes/admin_default/html/mod_activity_index.phtml");
    }
}
