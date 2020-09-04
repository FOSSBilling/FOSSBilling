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

/* mod_email_index.phtml */
class __TwigTemplate_3725ebd8307f740f3802329aa19a1545919dcd5fb3fb38a5f3471d532b56c5d0 extends \Twig\Template
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
        return $this->loadTemplate(((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ajax", [], "any", false, false, false, 1)) ? ("layout_blank.phtml") : ("layout_default.phtml")), "mod_email_index.phtml", 1);
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 6
        $context["emails"] = twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "email_get_list", [0 => ["per_page" => 10, "page" => twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 6)]], "method", false, false, false, 6);
        // line 1
        $this->getParent($context)->display($context, array_merge($this->blocks, $blocks));
    }

    // line 3
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo gettext("Emails");
    }

    // line 4
    public function block_breadcrumb($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo " <li class=\"active\">";
        echo gettext("Emails");
        echo "</li>";
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
        echo gettext("Emails");
        echo "</h1>
                <p>";
        // line 14
        echo gettext("Here you can find all the emails we sent you. Please click on email topic in left column and it will be displayed in right side.");
        echo "</p>
            </header>
            <section>
                ";
        // line 17
        if (twig_test_empty(twig_get_attribute($this->env, $this->source, ($context["emails"] ?? null), "list", [], "any", false, false, false, 17))) {
            // line 18
            echo "                <div class=\"alert alert-info\" id=\"information-block\">";
            echo gettext("There are no emails to display");
            echo "</div>
                ";
        } else {
            // line 20
            echo "                <div class=\"tabbable tabs-left\">
                    <ul class=\"nav nav-tabs\" >
                        ";
            // line 22
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["emails"] ?? null), "list", [], "any", false, false, false, 22));
            $context['loop'] = [
              'parent' => $context['_parent'],
              'index0' => 0,
              'index'  => 1,
              'first'  => true,
            ];
            if (is_array($context['_seq']) || (is_object($context['_seq']) && $context['_seq'] instanceof \Countable)) {
                $length = count($context['_seq']);
                $context['loop']['revindex0'] = $length - 1;
                $context['loop']['revindex'] = $length;
                $context['loop']['length'] = $length;
                $context['loop']['last'] = 1 === $length;
            }
            foreach ($context['_seq'] as $context["i"] => $context["email"]) {
                // line 23
                echo "                        <li class=\"email-title ";
                if (twig_get_attribute($this->env, $this->source, $context["loop"], "first", [], "any", false, false, false, 23)) {
                    echo "active";
                }
                echo "\" style=\"line-height: 50%\">
                            <a href=\"#tab";
                // line 24
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["email"], "id", [], "any", false, false, false, 24), "html", null, true);
                echo "\" data-toggle=\"tab\" style=\"padding-left: 0px; padding-bottom: 0px\">
                                    <p><strong>";
                // line 25
                echo twig_escape_filter($this->env, twig_slice($this->env, twig_get_attribute($this->env, $this->source, $context["email"], "subject", [], "any", false, false, false, 25), 0, 50), "html", null, true);
                if ((twig_length_filter($this->env, twig_get_attribute($this->env, $this->source, $context["email"], "subject", [], "any", false, false, false, 25)) > 50)) {
                    echo "...";
                }
                echo "</strong></p>
                                    <p><small>";
                // line 26
                echo twig_escape_filter($this->env, $this->extensions['Box_TwigExtensions']->twig_bb_date(twig_get_attribute($this->env, $this->source, $context["email"], "created_at", [], "any", false, false, false, 26)), "html", null, true);
                echo "</small></p>
                            </a>
                        </li>
                        ";
                ++$context['loop']['index0'];
                ++$context['loop']['index'];
                $context['loop']['first'] = false;
                if (isset($context['loop']['length'])) {
                    --$context['loop']['revindex0'];
                    --$context['loop']['revindex'];
                    $context['loop']['last'] = 0 === $context['loop']['revindex0'];
                }
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['i'], $context['email'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 30
            echo "                    </ul>
                    <div class=\"tab-content\">
                        ";
            // line 32
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["emails"] ?? null), "list", [], "any", false, false, false, 32));
            $context['loop'] = [
              'parent' => $context['_parent'],
              'index0' => 0,
              'index'  => 1,
              'first'  => true,
            ];
            if (is_array($context['_seq']) || (is_object($context['_seq']) && $context['_seq'] instanceof \Countable)) {
                $length = count($context['_seq']);
                $context['loop']['revindex0'] = $length - 1;
                $context['loop']['revindex'] = $length;
                $context['loop']['length'] = $length;
                $context['loop']['last'] = 1 === $length;
            }
            foreach ($context['_seq'] as $context["i"] => $context["email"]) {
                // line 33
                echo "                        <div class=\"tab-pane ";
                if (twig_get_attribute($this->env, $this->source, $context["loop"], "first", [], "any", false, false, false, 33)) {
                    echo "active";
                }
                echo "\" id=\"tab";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["email"], "id", [], "any", false, false, false, 33), "html", null, true);
                echo "\"  >
                            <div class=\"well well-small\">
                                <p><strong>";
                // line 35
                echo gettext("From:");
                echo "</strong> ";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["email"], "sender", [], "any", false, false, false, 35), "html", null, true);
                echo "</p>
                                <p><strong>";
                // line 36
                echo gettext("To:");
                echo "</strong> ";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["email"], "recipients", [], "any", false, false, false, 36), "html", null, true);
                echo "</p>
                                <p><strong>";
                // line 37
                echo gettext("Created at:");
                echo "</strong> ";
                echo twig_escape_filter($this->env, $this->extensions['Box_TwigExtensions']->twig_bb_date(twig_get_attribute($this->env, $this->source, $context["email"], "created_at", [], "any", false, false, false, 37)), "html", null, true);
                echo "</p>
                            </div>
                            <div class=\"well\">
                                <h3>";
                // line 40
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["email"], "subject", [], "any", false, false, false, 40), "html", null, true);
                echo "</h3>
                                <p> ";
                // line 41
                echo twig_get_attribute($this->env, $this->source, $context["email"], "content_html", [], "any", false, false, false, 41);
                echo "</p>
                            </div>
                            <a class=\"btn btn-inverse email-resend\" href=\"#\" mail-id=\"";
                // line 43
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["email"], "id", [], "any", false, false, false, 43), "html", null, true);
                echo "\">";
                echo gettext("Resend");
                echo "</a>
                            <a class=\"btn btn-danger email-delete\" href=\"#\"  mail-id=\"";
                // line 44
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["email"], "id", [], "any", false, false, false, 44), "html", null, true);
                echo "\">";
                echo gettext("Delete");
                echo "</a>
                        </div>
                        ";
                ++$context['loop']['index0'];
                ++$context['loop']['index'];
                $context['loop']['first'] = false;
                if (isset($context['loop']['length'])) {
                    --$context['loop']['revindex0'];
                    --$context['loop']['revindex'];
                    $context['loop']['last'] = 0 === $context['loop']['revindex0'];
                }
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['i'], $context['email'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 47
            echo "                    </div>
                </div>
                ";
            // line 49
            $this->loadTemplate("partial_pagination.phtml", "mod_email_index.phtml", 49)->display(twig_array_merge($context, ["list" => ($context["emails"] ?? null)]));
            // line 50
            echo "                ";
        }
        // line 51
        echo "            </section>
    </article>
</div>

";
    }

    // line 56
    public function block_js($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 58
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
        // line 84
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
        return "mod_email_index.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  286 => 84,  258 => 58,  254 => 56,  246 => 51,  243 => 50,  241 => 49,  237 => 47,  218 => 44,  212 => 43,  207 => 41,  203 => 40,  195 => 37,  189 => 36,  183 => 35,  173 => 33,  156 => 32,  152 => 30,  134 => 26,  127 => 25,  123 => 24,  116 => 23,  99 => 22,  95 => 20,  89 => 18,  87 => 17,  81 => 14,  77 => 13,  71 => 9,  67 => 8,  58 => 4,  51 => 3,  47 => 1,  45 => 6,  38 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}

{% block meta_title %}{% trans 'Emails' %}{% endblock %}
{% block breadcrumb %} <li class=\"active\">{% trans 'Emails' %}</li>{% endblock %}

{% set emails = client.email_get_list({\"per_page\":10, \"page\":request.page}) %}

{% block content %}
<div class=\"row\">
    <article class=\"span12 data-block\">
        <div class=\"data-container\">
            <header>
                <h1>{% trans 'Emails' %}</h1>
                <p>{% trans 'Here you can find all the emails we sent you. Please click on email topic in left column and it will be displayed in right side.' %}</p>
            </header>
            <section>
                {% if emails.list is empty  %}
                <div class=\"alert alert-info\" id=\"information-block\">{% trans 'There are no emails to display' %}</div>
                {% else %}
                <div class=\"tabbable tabs-left\">
                    <ul class=\"nav nav-tabs\" >
                        {% for i, email in emails.list %}
                        <li class=\"email-title {% if loop.first%}active{% endif %}\" style=\"line-height: 50%\">
                            <a href=\"#tab{{email.id}}\" data-toggle=\"tab\" style=\"padding-left: 0px; padding-bottom: 0px\">
                                    <p><strong>{{email.subject |slice(0,50) }}{% if email.subject | length > 50%}...{% endif%}</strong></p>
                                    <p><small>{{email.created_at|bb_date}}</small></p>
                            </a>
                        </li>
                        {% endfor %}
                    </ul>
                    <div class=\"tab-content\">
                        {% for i, email in emails.list %}
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
                        {% endfor %}
                    </div>
                </div>
                {% include \"partial_pagination.phtml\" with {'list': emails} %}
                {% endif %}
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
{% endblock %}", "mod_email_index.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-modules/Email/html_client/mod_email_index.phtml");
    }
}
