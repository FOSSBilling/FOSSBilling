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

/* mod_notification_index.phtml */
class __TwigTemplate_09dc994fc803d07b262a47917b0737d70c62e93aaea6348e971c5c1ae1c36e88 extends \Twig\Template
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
        return $this->loadTemplate(((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ajax", [], "any", false, false, false, 1)) ? ("layout_blank.phtml") : ("layout_default.phtml")), "mod_notification_index.phtml", 1);
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 2
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_notification_index.phtml", 2)->unwrap();
        // line 4
        $context["active_menu"] = "activity";
        // line 1
        $this->getParent($context)->display($context, array_merge($this->blocks, $blocks));
    }

    // line 3
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo "Notifications center";
    }

    // line 6
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 7
        echo "<div class=\"widget simpleTabs\">
    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">";
        // line 9
        echo gettext("Notifications");
        echo "</a></li>
        <li><a href=\"#tab-new\">";
        // line 10
        echo gettext("New note");
        echo "</a></li>
    </ul>

    <div class=\"tabs_container\">
        
        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-index\">

            ";
        // line 18
        echo twig_call_macro($macros["mf"], "macro_table_search", [], 18, $context, $this->getSourceContext());
        echo "
            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td>";
        // line 22
        echo gettext("Message");
        echo "</td>
                        <td>";
        // line 23
        echo gettext("Date");
        echo "</td>
                        <td style=\"width: 5%\">&nbsp;</td>
                    </tr>
                </thead>

                <tbody>
                ";
        // line 29
        $context["events"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "notification_get_list", [0 => twig_array_merge(["per_page" => 10, "page" => twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 29)], ($context["request"] ?? null))], "method", false, false, false, 29);
        // line 30
        echo "                ";
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["events"] ?? null), "list", [], "any", false, false, false, 30));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["i"] => $context["event"]) {
            // line 31
            echo "                <tr>
                    <td>";
            // line 32
            echo twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "system_string_render", [0 => ["_tpl" => twig_get_attribute($this->env, $this->source, $context["event"], "meta_value", [], "any", false, false, false, 32), "_try" => true]], "method", false, false, false, 32);
            echo "</td>
                    <td>";
            // line 33
            echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, $context["event"], "created_at", [], "any", false, false, false, 33), "Y-m-d H:i"), "html", null, true);
            echo "</td>
                    <td class=\"actions\">
                        <a class=\"bb-button btn14 bb-rm-tr api-link\" data-api-confirm=\"";
            // line 35
            echo gettext("Are you sure?");
            echo "\" data-api-redirect=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("activity");
            echo "\" href=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/notification/delete", ["id" => twig_get_attribute($this->env, $this->source, $context["event"], "id", [], "any", false, false, false, 35)]);
            echo "\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                    </td>
                </tr>
                ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 39
            echo "                    <tr>
                        <td colspan=\"6\">
                            ";
            // line 41
            echo gettext("The list is empty");
            // line 42
            echo "                        </td>
                    </tr>
                ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['i'], $context['event'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 45
        echo "                </tbody>
            </table>
            
            ";
        // line 48
        $this->loadTemplate("partial_pagination.phtml", "mod_notification_index.phtml", 48)->display(twig_array_merge($context, ["list" => ($context["events"] ?? null), "url" => "notification"]));
        // line 49
        echo "            
            <div class=\"body\">
                <a href=\"";
        // line 51
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/notification/delete_all");
        echo "\"  title=\"\" class=\"btnIconLeft mr10 api-link\" data-api-confirm=\"";
        echo gettext("Are you sure?");
        echo "\" data-api-reload=\"1\"><img src=\"images/icons/dark/trash.png\" alt=\"\" class=\"icon\"><span>";
        echo gettext("Delete all messages");
        echo "</span></a>
            </div>

        </div>
        
        <div class=\"tab_content nopadding\" id=\"tab-new\">
            <form method=\"post\" action=\"";
        // line 57
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/notification/add");
        echo "\" class=\"mainForm api-form\" data-api-reload=\"1\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <div class=\"formBottom\">
                            <textarea cols=\"5\" rows=\"10\" name=\"message\" placeholder=\"";
        // line 61
        echo gettext("Add note or todo task");
        echo "\" ></textarea>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                     <input type=\"submit\" value=\"";
        // line 66
        echo gettext("Add note");
        echo "\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>
    </div>
</div>

";
    }

    public function getTemplateName()
    {
        return "mod_notification_index.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  187 => 66,  179 => 61,  172 => 57,  159 => 51,  155 => 49,  153 => 48,  148 => 45,  140 => 42,  138 => 41,  134 => 39,  121 => 35,  116 => 33,  112 => 32,  109 => 31,  103 => 30,  101 => 29,  92 => 23,  88 => 22,  81 => 18,  70 => 10,  66 => 9,  62 => 7,  58 => 6,  51 => 3,  47 => 1,  45 => 4,  43 => 2,  36 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}
{% import \"macro_functions.phtml\" as mf %}
{% block meta_title %}Notifications center{% endblock %}
{% set active_menu = 'activity' %}

{% block content %}
<div class=\"widget simpleTabs\">
    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">{% trans 'Notifications' %}</a></li>
        <li><a href=\"#tab-new\">{% trans 'New note' %}</a></li>
    </ul>

    <div class=\"tabs_container\">
        
        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-index\">

            {{ mf.table_search }}
            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td>{% trans 'Message' %}</td>
                        <td>{% trans 'Date' %}</td>
                        <td style=\"width: 5%\">&nbsp;</td>
                    </tr>
                </thead>

                <tbody>
                {% set events = admin.notification_get_list({\"per_page\":10, \"page\":request.page}|merge(request)) %}
                {% for i, event in events.list %}
                <tr>
                    <td>{{ admin.system_string_render({\"_tpl\": event.meta_value, \"_try\":true })|raw }}</td>
                    <td>{{ event.created_at|date('Y-m-d H:i') }}</td>
                    <td class=\"actions\">
                        <a class=\"bb-button btn14 bb-rm-tr api-link\" data-api-confirm=\"{% trans 'Are you sure?' %}\" data-api-redirect=\"{{ 'activity'|alink }}\" href=\"{{ 'api/admin/notification/delete'|link({'id' : event.id}) }}\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
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
            
            {% include \"partial_pagination.phtml\" with {'list': events, 'url':'notification'} %}
            
            <div class=\"body\">
                <a href=\"{{ 'api/admin/notification/delete_all'|link }}\"  title=\"\" class=\"btnIconLeft mr10 api-link\" data-api-confirm=\"{% trans 'Are you sure?' %}\" data-api-reload=\"1\"><img src=\"images/icons/dark/trash.png\" alt=\"\" class=\"icon\"><span>{% trans 'Delete all messages' %}</span></a>
            </div>

        </div>
        
        <div class=\"tab_content nopadding\" id=\"tab-new\">
            <form method=\"post\" action=\"{{ 'api/admin/notification/add'|link }}\" class=\"mainForm api-form\" data-api-reload=\"1\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <div class=\"formBottom\">
                            <textarea cols=\"5\" rows=\"10\" name=\"message\" placeholder=\"{% trans 'Add note or todo task' %}\" ></textarea>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                     <input type=\"submit\" value=\"{% trans 'Add note' %}\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>
    </div>
</div>

{% endblock %}
", "mod_notification_index.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-modules/Notification/html_admin/mod_notification_index.phtml");
    }
}
