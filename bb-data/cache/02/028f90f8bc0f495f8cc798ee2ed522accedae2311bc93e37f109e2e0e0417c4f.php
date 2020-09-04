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

/* mod_email_email.phtml */
class __TwigTemplate_6947c8d33d69292d4e893cce63277744b4417db144fefcad09a504cd2d72c3ab extends \Twig\Template
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
        return $this->loadTemplate(((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ajax", [], "any", false, false, false, 1)) ? ("layout_blank.phtml") : ("layout_default.phtml")), "mod_email_email.phtml", 1);
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        $this->getParent($context)->display($context, array_merge($this->blocks, $blocks));
    }

    // line 3
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo gettext("Email");
    }

    // line 4
    public function block_breadcrumb($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 5
        echo "<li><a href=\"";
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("email");
        echo "\">";
        echo gettext("Emails");
        echo "</a><span class=\"divider\">/</span></li>
";
        // line 6
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["email"] ?? null), "subject", [], "any", false, false, false, 6), "html", null, true);
        echo "
";
    }

    // line 8
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 9
        echo "<div class=\"row\">
    <article class=\"span12 data-block\">
        <div class=\"data-container\">
            <header>
                <h1>";
        // line 13
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["email"] ?? null), "subject", [], "any", false, false, false, 13), "html", null, true);
        echo "</h1>
                <div class=\"data-header-actions\">
                    <ul>
                        <li>
                            <a class=\"btn btn-small\" href=\"";
        // line 17
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/email");
        echo "\">";
        echo gettext("Back to emails list");
        echo "</a>
                        </li>
                    </ul>
                </div>
            </header>
            <section>

                        <div class=\"tab-pane ";
        // line 24
        if (twig_get_attribute($this->env, $this->source, ($context["loop"] ?? null), "first", [], "any", false, false, false, 24)) {
            echo "active";
        }
        echo "\" id=\"tab";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["email"] ?? null), "id", [], "any", false, false, false, 24), "html", null, true);
        echo "\"  >
                            <div class=\"well well-small\">
                                <p><strong>";
        // line 26
        echo gettext("From:");
        echo "</strong> ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["email"] ?? null), "sender", [], "any", false, false, false, 26), "html", null, true);
        echo "</p>
                                <p><strong>";
        // line 27
        echo gettext("To:");
        echo "</strong> ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["email"] ?? null), "recipients", [], "any", false, false, false, 27), "html", null, true);
        echo "</p>
                                <p><strong>";
        // line 28
        echo gettext("Created at:");
        echo "</strong> ";
        echo twig_escape_filter($this->env, $this->extensions['Box_TwigExtensions']->twig_bb_date(twig_get_attribute($this->env, $this->source, ($context["email"] ?? null), "created_at", [], "any", false, false, false, 28)), "html", null, true);
        echo "</p>
                            </div>
                            <div class=\"well\">
                                <h3>";
        // line 31
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["email"] ?? null), "subject", [], "any", false, false, false, 31), "html", null, true);
        echo "</h3>
                                <p> ";
        // line 32
        echo twig_get_attribute($this->env, $this->source, ($context["email"] ?? null), "content_html", [], "any", false, false, false, 32);
        echo "</p>
                            </div>
                            <a class=\"btn btn-inverse email-resend\" href=\"#\" mail-id=\"";
        // line 34
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["email"] ?? null), "id", [], "any", false, false, false, 34), "html", null, true);
        echo "\">";
        echo gettext("Resend");
        echo "</a>
                            <a class=\"btn btn-danger email-delete\" href=\"#\"  mail-id=\"";
        // line 35
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["email"] ?? null), "id", [], "any", false, false, false, 35), "html", null, true);
        echo "\">";
        echo gettext("Delete");
        echo "</a>
                        </div>
                </div>
            </section>
    </article>
</div>

";
    }

    // line 43
    public function block_js($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 45
        echo "<script type=\"text/javascript\">
    \$(function() {
        \$('.email-resend').click(function(e){
            e.preventDefault();
            \$('.wait').show();
            var email_id = \$(this).attr('mail-id');
            bb.post(
                'client/email/resend',
                {id: email_id },
                function(result) {
                    \$('.wait').hide();
                    bb.msg('Email resent');
                    return false;
                }
            );
        });
        \$('.email-delete').click(function(e){
            e.preventDefault();
            if (confirm('Are you sure?')){
                \$('.wait').show();
                var email_id = \$(this).attr('mail-id');
                bb.post(
                    'client/email/delete',
                    {id: email_id },
                    function(result) {
                        bb.msg('Email deleted');
                        bb.redirect('";
        // line 71
        echo twig_escape_filter($this->env, $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("email"), "js", null, true);
        echo "');
                        return false;
                    }
                );
            };
        });

    });
</script>
";
    }

    public function getTemplateName()
    {
        return "mod_email_email.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  191 => 71,  163 => 45,  159 => 43,  145 => 35,  139 => 34,  134 => 32,  130 => 31,  122 => 28,  116 => 27,  110 => 26,  101 => 24,  89 => 17,  82 => 13,  76 => 9,  72 => 8,  66 => 6,  59 => 5,  55 => 4,  48 => 3,  38 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}

{% block meta_title %}{% trans 'Email' %}{% endblock %}
{% block breadcrumb %}
<li><a href=\"{{ 'email' | link}}\">{% trans 'Emails' %}</a><span class=\"divider\">/</span></li>
{{ email.subject }}
{% endblock %}
{% block content %}
<div class=\"row\">
    <article class=\"span12 data-block\">
        <div class=\"data-container\">
            <header>
                <h1>{{ email.subject }}</h1>
                <div class=\"data-header-actions\">
                    <ul>
                        <li>
                            <a class=\"btn btn-small\" href=\"{{ '/email'|link }}\">{% trans 'Back to emails list' %}</a>
                        </li>
                    </ul>
                </div>
            </header>
            <section>

                        <div class=\"tab-pane {% if loop.first%}active{% endif %}\" id=\"tab{{email.id}}\"  >
                            <div class=\"well well-small\">
                                <p><strong>{% trans 'From:' %}</strong> {{email.sender}}</p>
                                <p><strong>{% trans 'To:' %}</strong> {{email.recipients}}</p>
                                <p><strong>{% trans 'Created at:' %}</strong> {{email.created_at|bb_date}}</p>
                            </div>
                            <div class=\"well\">
                                <h3>{{ email.subject }}</h3>
                                <p> {{email.content_html | raw}}</p>
                            </div>
                            <a class=\"btn btn-inverse email-resend\" href=\"#\" mail-id=\"{{email.id}}\">{% trans 'Resend' %}</a>
                            <a class=\"btn btn-danger email-delete\" href=\"#\"  mail-id=\"{{email.id}}\">{% trans 'Delete' %}</a>
                        </div>
                </div>
            </section>
    </article>
</div>

{% endblock %}
{% block js %}
{% autoescape \"js\" %}
<script type=\"text/javascript\">
    \$(function() {
        \$('.email-resend').click(function(e){
            e.preventDefault();
            \$('.wait').show();
            var email_id = \$(this).attr('mail-id');
            bb.post(
                'client/email/resend',
                {id: email_id },
                function(result) {
                    \$('.wait').hide();
                    bb.msg('Email resent');
                    return false;
                }
            );
        });
        \$('.email-delete').click(function(e){
            e.preventDefault();
            if (confirm('Are you sure?')){
                \$('.wait').show();
                var email_id = \$(this).attr('mail-id');
                bb.post(
                    'client/email/delete',
                    {id: email_id },
                    function(result) {
                        bb.msg('Email deleted');
                        bb.redirect('{{ 'email'|link }}');
                        return false;
                    }
                );
            };
        });

    });
</script>
{% endautoescape %}
{% endblock %}", "mod_email_email.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-modules/Email/html_client/mod_email_email.phtml");
    }
}
