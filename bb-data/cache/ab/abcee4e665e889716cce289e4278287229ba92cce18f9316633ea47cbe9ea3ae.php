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

/* mod_email_details.phtml */
class __TwigTemplate_902dcc00f3f8cc5c4d910610bb60547a540ca735f2f9061df139c36841e2b811 extends \Twig\Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->blocks = [
            'meta_title' => [$this, 'block_meta_title'],
            'breadcrumbs' => [$this, 'block_breadcrumbs'],
            'content' => [$this, 'block_content'],
        ];
    }

    protected function doGetParent(array $context)
    {
        // line 1
        return $this->loadTemplate(((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ajax", [], "any", false, false, false, 1)) ? ("layout_blank.phtml") : ("layout_default.phtml")), "mod_email_details.phtml", 1);
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 2
        $context["active_menu"] = "activity";
        // line 1
        $this->getParent($context)->display($context, array_merge($this->blocks, $blocks));
    }

    // line 3
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["email"] ?? null), "subject", [], "any", false, false, false, 3), "html", null, true);
    }

    // line 5
    public function block_breadcrumbs($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 6
        echo "<ul>
    <li class=\"firstB\"><a href=\"";
        // line 7
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/");
        echo "\">";
        echo gettext("Home");
        echo "</a></li>
    <li><a href=\"";
        // line 8
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("email/history");
        echo "\">";
        echo gettext("Email history");
        echo "</a></li>
    <li class=\"lastB\">";
        // line 9
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["email"] ?? null), "subject", [], "any", false, false, false, 9), "html", null, true);
        echo "</li>
</ul>
";
    }

    // line 13
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 14
        echo "<div class=\"widget\">
    <div class=\"head\">
        <h5>";
        // line 16
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["email"] ?? null), "subject", [], "any", false, false, false, 16), "html", null, true);
        echo "</h5>
    </div>
    
    <table class=\"tableStatic wide\">
        <tbody>
            <tr class=\"noborder\">
                <td>";
        // line 22
        echo gettext("From");
        echo "</td>
                <td>";
        // line 23
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["email"] ?? null), "sender", [], "any", false, false, false, 23), "html", null, true);
        echo "</td>
            </tr>

            <tr>
                <td>";
        // line 27
        echo gettext("To");
        echo "</td>
                <td>";
        // line 28
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["email"] ?? null), "recipients", [], "any", false, false, false, 28), "html", null, true);
        echo "</td>
            </tr>

            <tr>
                <td>";
        // line 32
        echo gettext("Sent");
        echo "</td>
                <td>";
        // line 33
        echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["email"] ?? null), "created_at", [], "any", false, false, false, 33), "l, d F Y"), "html", null, true);
        echo "</td>
            </tr>
         </tbody>
         <tfoot>
             <tr>
                 <td colspan=\"2\">
                    <div class=\"aligncenter\">
                        <a class=\"btn55 mr10 api-link\" href=\"";
        // line 40
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/email/email_resend", ["id" => twig_get_attribute($this->env, $this->source, ($context["email"] ?? null), "id", [], "any", false, false, false, 40)]);
        echo "\" data-api-msg=\"Email resent\"><img src=\"images/icons/middlenav/refresh2.png\" alt=\"\"><span>";
        echo gettext("Resend");
        echo "</span></a>
                        <a class=\"btn55 mr10 api-link\" href=\"";
        // line 41
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/email/email_delete", ["id" => twig_get_attribute($this->env, $this->source, ($context["email"] ?? null), "id", [], "any", false, false, false, 41)]);
        echo "\" data-api-confirm=\"Are you sure?\" data-api-redirect=\"";
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("email/history");
        echo "\"><img src=\"images/icons/middlenav/trash.png\" alt=\"\"><span>";
        echo gettext("Delete");
        echo "</span></a>
                    </div>
                 </td>
             </tr>
         </tfoot>
    </table>
    
    <div class=\"body\">
        ";
        // line 49
        echo twig_get_attribute($this->env, $this->source, ($context["email"] ?? null), "content_html", [], "any", false, false, false, 49);
        echo "
    </div>
    
</div>

";
    }

    public function getTemplateName()
    {
        return "mod_email_details.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  157 => 49,  142 => 41,  136 => 40,  126 => 33,  122 => 32,  115 => 28,  111 => 27,  104 => 23,  100 => 22,  91 => 16,  87 => 14,  83 => 13,  76 => 9,  70 => 8,  64 => 7,  61 => 6,  57 => 5,  50 => 3,  46 => 1,  44 => 2,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}
{% set active_menu = 'activity' %}
{% block meta_title %}{{email.subject}}{% endblock %}

{% block breadcrumbs %}
<ul>
    <li class=\"firstB\"><a href=\"{{ '/'|alink }}\">{% trans 'Home' %}</a></li>
    <li><a href=\"{{ 'email/history'|alink }}\">{% trans 'Email history' %}</a></li>
    <li class=\"lastB\">{{email.subject}}</li>
</ul>
{% endblock %}

{% block content %}
<div class=\"widget\">
    <div class=\"head\">
        <h5>{{email.subject}}</h5>
    </div>
    
    <table class=\"tableStatic wide\">
        <tbody>
            <tr class=\"noborder\">
                <td>{% trans 'From' %}</td>
                <td>{{email.sender}}</td>
            </tr>

            <tr>
                <td>{% trans 'To' %}</td>
                <td>{{email.recipients}}</td>
            </tr>

            <tr>
                <td>{% trans 'Sent' %}</td>
                <td>{{email.created_at|date('l, d F Y')}}</td>
            </tr>
         </tbody>
         <tfoot>
             <tr>
                 <td colspan=\"2\">
                    <div class=\"aligncenter\">
                        <a class=\"btn55 mr10 api-link\" href=\"{{'api/admin/email/email_resend'|link({'id' : email.id}) }}\" data-api-msg=\"Email resent\"><img src=\"images/icons/middlenav/refresh2.png\" alt=\"\"><span>{% trans 'Resend' %}</span></a>
                        <a class=\"btn55 mr10 api-link\" href=\"{{'api/admin/email/email_delete'|link({'id' : email.id}) }}\" data-api-confirm=\"Are you sure?\" data-api-redirect=\"{{'email/history'|alink}}\"><img src=\"images/icons/middlenav/trash.png\" alt=\"\"><span>{% trans 'Delete' %}</span></a>
                    </div>
                 </td>
             </tr>
         </tfoot>
    </table>
    
    <div class=\"body\">
        {{email.content_html|raw}}
    </div>
    
</div>

{% endblock %}", "mod_email_details.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-modules/Email/html_admin/mod_email_details.phtml");
    }
}
