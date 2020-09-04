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

/* mod_email_history.phtml */
class __TwigTemplate_644792fd1001c9acc397bbfcfa60762e5eaae9b2118913c4c2d1c083a80a30ef extends \Twig\Template
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
        return $this->loadTemplate(((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ajax", [], "any", false, false, false, 1)) ? ("layout_blank.phtml") : ("layout_default.phtml")), "mod_email_history.phtml", 1);
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 2
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_email_history.phtml", 2)->unwrap();
        // line 4
        $context["active_menu"] = "activity";
        // line 1
        $this->getParent($context)->display($context, array_merge($this->blocks, $blocks));
    }

    // line 3
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo gettext("Email history");
    }

    // line 6
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 7
        echo "
";
        // line 8
        $context["config"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "extension_config_get", [0 => ["ext" => "mod_email"]], "method", false, false, false, 8);
        // line 9
        if (( !twig_get_attribute($this->env, $this->source, ($context["config"] ?? null), "log_enabled", [], "any", true, true, false, 9) || (twig_get_attribute($this->env, $this->source, ($context["config"] ?? null), "log_enabled", [], "any", false, false, false, 9) != 1))) {
            // line 10
            echo "<div class=\"nNote nInformation first\">
    <p><strong>";
            // line 11
            echo gettext("INFORMATION");
            echo ": </strong>";
            echo gettext("Email logging is not enabled. If you want to log sent mails to database, enable it in");
            // line 12
            echo "        <a href=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("extension/settings/email");
            echo "\"> ";
            echo gettext("email settings.");
            echo "</a>
    </p>
</div>
";
        }
        // line 16
        echo "
<div class=\"widget\">
    <div class=\"head\"><h5 class=\"iFrames\">";
        // line 18
        echo gettext("Email history");
        echo "</h5></div>

";
        // line 20
        echo twig_call_macro($macros["mf"], "macro_table_search", [], 20, $context, $this->getSourceContext());
        echo "
<table class=\"tableStatic wide\">
    <thead>
        <tr>
            <td style=\"width: 2%\"><input type=\"checkbox\" class=\"batch-delete-master-checkbox\"/></td>
            <td>";
        // line 25
        echo gettext("To");
        echo "</td>
            <td>";
        // line 26
        echo gettext("From");
        echo "</td>
            <td>";
        // line 27
        echo gettext("Subject");
        echo "</td>
            <td>";
        // line 28
        echo gettext("Date");
        echo "</td>
            <td style=\"width: 13%\">&nbsp;</td>
        </tr>
    </thead>

    <tbody>
    ";
        // line 34
        $context["emails"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "email_email_get_list", [0 => twig_array_merge(["per_page" => 30, "page" => twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 34)], ($context["request"] ?? null))], "method", false, false, false, 34);
        // line 35
        echo "    ";
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["emails"] ?? null), "list", [], "any", false, false, false, 35));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["i"] => $context["email"]) {
            // line 36
            echo "    <tr>
        <td><input type=\"checkbox\" class=\"batch-delete-checkbox\" data-item-id=\"";
            // line 37
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["email"], "id", [], "any", false, false, false, 37), "html", null, true);
            echo "\"/></td>
        <td>";
            // line 38
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["email"], "recipients", [], "any", false, false, false, 38), "html", null, true);
            echo "</td>
        <td>";
            // line 39
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["email"], "sender", [], "any", false, false, false, 39), "html", null, true);
            echo "</td>
        <td>";
            // line 40
            echo twig_escape_filter($this->env, twig_truncate_filter($this->env, twig_get_attribute($this->env, $this->source, $context["email"], "subject", [], "any", false, false, false, 40), 40), "html", null, true);
            echo "</td>
        <td>";
            // line 41
            echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, $context["email"], "created_at", [], "any", false, false, false, 41), "Y-m-d"), "html", null, true);
            echo "</td>
        <td class=\"actions\">
            <a class=\"bb-button btn14\" href=\"";
            // line 43
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/email");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["email"], "id", [], "any", false, false, false, 43), "html", null, true);
            echo "\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
            <a class=\"bb-button btn14 bb-rm-tr api-link\" data-api-redirect=\"";
            // line 44
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("email/history");
            echo "\" data-api-confirm=\"Are you sure?\"  href=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/email/email_delete", ["id" => twig_get_attribute($this->env, $this->source, $context["email"], "id", [], "any", false, false, false, 44)]);
            echo "\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
        </td>
    </tr>
    ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 48
            echo "        <tr>
            <td colspan=\"7\">
                ";
            // line 50
            echo gettext("The list is empty");
            // line 51
            echo "            </td>
        </tr>
    
    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['i'], $context['email'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 55
        echo "    </tbody>
</table>

</div>

";
        // line 60
        $this->loadTemplate("partial_batch_delete.phtml", "mod_email_history.phtml", 60)->display(twig_array_merge($context, ["action" => "admin/email/batch_delete"]));
        // line 61
        $this->loadTemplate("partial_pagination.phtml", "mod_email_history.phtml", 61)->display(twig_array_merge($context, ["list" => ($context["emails"] ?? null), "url" => "email/history"]));
        // line 62
        echo "
";
    }

    public function getTemplateName()
    {
        return "mod_email_history.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  199 => 62,  197 => 61,  195 => 60,  188 => 55,  179 => 51,  177 => 50,  173 => 48,  162 => 44,  156 => 43,  151 => 41,  147 => 40,  143 => 39,  139 => 38,  135 => 37,  132 => 36,  126 => 35,  124 => 34,  115 => 28,  111 => 27,  107 => 26,  103 => 25,  95 => 20,  90 => 18,  86 => 16,  76 => 12,  72 => 11,  69 => 10,  67 => 9,  65 => 8,  62 => 7,  58 => 6,  51 => 3,  47 => 1,  45 => 4,  43 => 2,  36 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}
{% import \"macro_functions.phtml\" as mf %}
{% block meta_title %}{% trans 'Email history' %}{% endblock %}
{% set active_menu = 'activity' %}

{% block content %}

{% set config = admin.extension_config_get({\"ext\":\"mod_email\"}) %}
{% if config.log_enabled is not defined or config.log_enabled != 1 %}
<div class=\"nNote nInformation first\">
    <p><strong>{% trans 'INFORMATION' %}: </strong>{% trans 'Email logging is not enabled. If you want to log sent mails to database, enable it in' %}
        <a href=\"{{'extension/settings/email'|alink}}\"> {% trans 'email settings.' %}</a>
    </p>
</div>
{% endif %}

<div class=\"widget\">
    <div class=\"head\"><h5 class=\"iFrames\">{% trans 'Email history' %}</h5></div>

{{ mf.table_search }}
<table class=\"tableStatic wide\">
    <thead>
        <tr>
            <td style=\"width: 2%\"><input type=\"checkbox\" class=\"batch-delete-master-checkbox\"/></td>
            <td>{% trans 'To' %}</td>
            <td>{% trans 'From' %}</td>
            <td>{% trans 'Subject' %}</td>
            <td>{% trans 'Date' %}</td>
            <td style=\"width: 13%\">&nbsp;</td>
        </tr>
    </thead>

    <tbody>
    {% set emails = admin.email_email_get_list({\"per_page\":30, \"page\":request.page}|merge(request)) %}
    {% for i, email in emails.list %}
    <tr>
        <td><input type=\"checkbox\" class=\"batch-delete-checkbox\" data-item-id=\"{{ email.id }}\"/></td>
        <td>{{ email.recipients }}</td>
        <td>{{ email.sender }}</td>
        <td>{{ email.subject|truncate(40) }}</td>
        <td>{{ email.created_at|date('Y-m-d') }}</td>
        <td class=\"actions\">
            <a class=\"bb-button btn14\" href=\"{{ '/email'|alink }}/{{email.id}}\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
            <a class=\"bb-button btn14 bb-rm-tr api-link\" data-api-redirect=\"{{'email/history'|alink}}\" data-api-confirm=\"Are you sure?\"  href=\"{{'api/admin/email/email_delete'|link({'id' : email.id}) }}\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
        </td>
    </tr>
    {% else %}
        <tr>
            <td colspan=\"7\">
                {% trans 'The list is empty' %}
            </td>
        </tr>
    
    {% endfor %}
    </tbody>
</table>

</div>

{% include \"partial_batch_delete.phtml\" with {'action' : 'admin/email/batch_delete'} %}
{% include \"partial_pagination.phtml\" with {'list': emails, 'url':'email/history'} %}

{% endblock %}
", "mod_email_history.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-modules/Email/html_admin/mod_email_history.phtml");
    }
}
