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

/* mod_support_ticket.phtml */
class __TwigTemplate_f222c508b5b937e79d5ed5936b5dbd379ac5a393e590170c48f0372b4d19dece extends Template
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
            'js' => [$this, 'block_js'],
        ];
    }

    protected function doGetParent(array $context)
    {
        // line 1
        return $this->loadTemplate(((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ajax", [], "any", false, false, false, 1)) ? ("layout_blank.phtml") : ("layout_default.phtml")), "mod_support_ticket.phtml", 1);
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 3
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_support_ticket.phtml", 3)->unwrap();
        // line 1
        $this->getParent($context)->display($context, array_merge($this->blocks, $blocks));
    }

    // line 5
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "subject", [], "any", false, false, false, 5), "html", null, true);
    }

    // line 7
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 8
        echo "<div class=\"h-block forum\">
    <div class=\"h-block-header\">
        <div class=\"icon\"><span class=\"big-light-icon i-support\"></span></div>
        <h2>";
        // line 11
        echo twig_escape_filter($this->env, twig_truncate_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "subject", [], "any", false, false, false, 11), 60), "html", null, true);
        echo "</h2>
        <p>#";
        // line 12
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "id", [], "any", false, false, false, 12), "html", null, true);
        echo " - ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "helpdesk", [], "any", false, false, false, 12), "name", [], "any", false, false, false, 12), "html", null, true);
        echo "</p>
    </div>
    <div class=\"block conversation\">
    ";
        // line 15
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "messages", [], "any", false, false, false, 15));
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
        foreach ($context['_seq'] as $context["i"] => $context["message"]) {
            // line 16
            echo "    <div class=\"widget simpleTabs tabsRight";
            if (twig_get_attribute($this->env, $this->source, $context["loop"], "first", [], "any", false, false, false, 16)) {
                echo " first";
            }
            echo "\">
        <div class=\"head\">
            <h2 class=\"dark-icon i-forum\">";
            // line 18
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["message"], "author", [], "any", false, false, false, 18), "name", [], "any", false, false, false, 18), "html", null, true);
            echo "</h2>
            <h3>";
            // line 19
            echo twig_escape_filter($this->env, $this->extensions['Box_TwigExtensions']->twig_bb_date(twig_get_attribute($this->env, $this->source, $context["message"], "created_at", [], "any", false, false, false, 19)), "html", null, true);
            echo "</h3>
        </div>
        <ul class=\"tabs\">
            <li><a href=\"#m-";
            // line 22
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["message"], "id", [], "any", false, false, false, 22), "html", null, true);
            echo "\">#";
            echo twig_escape_filter($this->env, ($context["i"] + 1), "html", null, true);
            echo "</a></li>
            <li><a href=\"#reply-";
            // line 23
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["message"], "id", [], "any", false, false, false, 23), "html", null, true);
            echo "\"><span class=\"dark-icon i-support\"></span>";
            echo twig_escape_filter($this->env, gettext("Reply"), "html", null, true);
            echo "</a></li>
            <li><a href=\"#quote-";
            // line 24
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["message"], "id", [], "any", false, false, false, 24), "html", null, true);
            echo "\"><span class=\"dark-icon i-quote\"></span>";
            echo twig_escape_filter($this->env, gettext("Quote"), "html", null, true);
            echo "</a></li>
        </ul>
        <div class=\"tabs_container\">
            <div class=\"tab_content\" id=\"m-";
            // line 27
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["message"], "id", [], "any", false, false, false, 27), "html", null, true);
            echo "\">
                <div class=\"grid_2 alpha\">
                    <img src=\"";
            // line 29
            echo twig_escape_filter($this->env, twig_gravatar_filter(twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["message"], "author", [], "any", false, false, false, 29), "email", [], "any", false, false, false, 29)), "html", null, true);
            echo "?size=60\" alt=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["message"], "author", [], "any", false, false, false, 29), "name", [], "any", false, false, false, 29), "html", null, true);
            echo "\" class=\"gravatar\"/>
                </div>
                <div class=\"grid_10 omega\">
                    <div class=\"block message\">
                        <div class=\"body\">
                            ";
            // line 34
            echo twig_bbmd_filter($this->env, twig_get_attribute($this->env, $this->source, $context["message"], "content", [], "any", false, false, false, 34));
            echo "
                            <div class=\"clear\"></div>
                        </div>
                    </div>
                </div>
                <div class=\"clear\"></div>
            </div>
            <div class=\"tab_content\" id=\"reply-";
            // line 41
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["message"], "id", [], "any", false, false, false, 41), "html", null, true);
            echo "\">
                <form method=\"post\" action=\"\" class=\"api_form\" data-api-url=\"";
            // line 42
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/client/support/ticket_reply");
            echo "\" data-api-reload=\"1\">
                    <fieldset>
                        <p>
                            <textarea name=\"content\" cols=\"5\" rows=\"10\" required=\"required\"></textarea>
                        </p>
                        <input type=\"hidden\" name=\"id\" value=\"";
            // line 47
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "id", [], "any", false, false, false, 47), "html", null, true);
            echo "\">
                        <input class=\"bb-button bb-button-submit\" type=\"submit\" value=\"";
            // line 48
            echo twig_escape_filter($this->env, gettext("Post"), "html", null, true);
            echo "\">
                    </fieldset>
                </form>
            </div>

            <div class=\"tab_content\" id=\"quote-";
            // line 53
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["message"], "id", [], "any", false, false, false, 53), "html", null, true);
            echo "\">
                <form method=\"post\" action=\"\" class=\"api_form\" data-api-url=\"";
            // line 54
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/client/support/ticket_reply");
            echo "\" data-api-reload=\"1\">
                    <fieldset>
                        <p>
                            <textarea name=\"content\" cols=\"5\" rows=\"10\" required=\"required\">";
            // line 57
            echo twig_call_macro($macros["mf"], "macro_markdown_quote", [twig_get_attribute($this->env, $this->source, $context["message"], "content", [], "any", false, false, false, 57)], 57, $context, $this->getSourceContext());
            echo "</textarea>
                        </p>
                        <input type=\"hidden\" name=\"id\" value=\"";
            // line 59
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "id", [], "any", false, false, false, 59), "html", null, true);
            echo "\">
                        <input class=\"bb-button bb-button-submit\" type=\"submit\" value=\"";
            // line 60
            echo twig_escape_filter($this->env, gettext("Post"), "html", null, true);
            echo "\">
                    </fieldset>
                </form>
            </div>
        </div>
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
        unset($context['_seq'], $context['_iterated'], $context['i'], $context['message'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 67
        echo "    </div>
</div>

<div class=\"grid_12 alpha omega\">
    <div class=\"widget\">
        <div class=\"head\">
            <h2 class=\"dark-icon i-support\">";
        // line 73
        echo twig_escape_filter($this->env, twig_truncate_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "subject", [], "any", false, false, false, 73), 95), "html", null, true);
        echo "</h2>
        </div>
        <table>
            <tbody>
                <tr>
                    <td>";
        // line 78
        echo twig_escape_filter($this->env, gettext("Ticket ID"), "html", null, true);
        echo "</td>
                    <td><b>#";
        // line 79
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "id", [], "any", false, false, false, 79), "html", null, true);
        echo "</b></td>
                </tr>

                <tr>
                    <td>";
        // line 83
        echo twig_escape_filter($this->env, gettext("Help desk"), "html", null, true);
        echo "</td>
                    <td>";
        // line 84
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "helpdesk", [], "any", false, false, false, 84), "name", [], "any", false, false, false, 84), "html", null, true);
        echo "</td>
                </tr>

                <tr>
                    <td>";
        // line 88
        echo twig_escape_filter($this->env, gettext("Status"), "html", null, true);
        echo "</td>
                    <td>";
        // line 89
        echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "status", [], "any", false, false, false, 89)], 89, $context, $this->getSourceContext());
        echo "</td>
                </tr>

                <tr>
                    <td>";
        // line 93
        echo twig_escape_filter($this->env, gettext("Time opened"), "html", null, true);
        echo "</td>
                    <td>";
        // line 94
        echo twig_escape_filter($this->env, $this->extensions['Box_TwigExtensions']->twig_bb_date(twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "created_at", [], "any", false, false, false, 94)), "html", null, true);
        echo "</td>
                </tr>

                ";
        // line 97
        if (((twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "rel_type", [], "any", false, false, false, 97) == "order") && twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "rel_id", [], "any", false, false, false, 97))) {
            // line 98
            echo "                <tr>
                    <td>";
            // line 99
            echo twig_escape_filter($this->env, gettext("Related to"), "html", null, true);
            echo "</td>
                    <td><a href=\"";
            // line 100
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("order/service/manage");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "rel_id", [], "any", false, false, false, 100), "html", null, true);
            echo "\">";
            echo twig_escape_filter($this->env, gettext("Service #"), "html", null, true);
            echo " ";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "rel_id", [], "any", false, false, false, 100), "html", null, true);
            echo "</a></td>
                </tr>
                ";
        }
        // line 103
        echo "             </tbody>

             <tfoot>
                 <tr>
                     <td colspan=\"2\">

                     <a class=\"bb-button\" href=\"";
        // line 109
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/support");
        echo "\"><span class=\"dark-icon i-arrow\"></span>";
        echo twig_escape_filter($this->env, gettext("Tickets list"), "html", null, true);
        echo "</a>

                     ";
        // line 111
        if ((twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "status", [], "any", false, false, false, 111) != "closed")) {
            // line 112
            echo "                        <button class=\"bb-button\" type=\"button\" id=\"ticket-close\">";
            echo twig_escape_filter($this->env, gettext("Close ticket"), "html", null, true);
            echo "</button>
                        ";
        }
        // line 114
        echo "                     </td>
                 </tr>
             </tfoot>
        </table>
    </div>
</div>

";
    }

    // line 123
    public function block_js($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 124
        echo "<script type=\"text/javascript\">
    \$(function() {
        \$('#ticket-close').bind('click', function(event) {
            bb.post(
                \"";
        // line 128
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/client/support/ticket_close");
        echo "\",
                { id: ";
        // line 129
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["ticket"] ?? null), "id", [], "any", false, false, false, 129), "html", null, true);
        echo " },
                function(result) {
                    bb.redirect(\"";
        // line 131
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("support");
        echo "\");
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
        return "mod_support_ticket.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  355 => 131,  350 => 129,  346 => 128,  340 => 124,  336 => 123,  325 => 114,  319 => 112,  317 => 111,  310 => 109,  302 => 103,  290 => 100,  286 => 99,  283 => 98,  281 => 97,  275 => 94,  271 => 93,  264 => 89,  260 => 88,  253 => 84,  249 => 83,  242 => 79,  238 => 78,  230 => 73,  222 => 67,  201 => 60,  197 => 59,  192 => 57,  186 => 54,  182 => 53,  174 => 48,  170 => 47,  162 => 42,  158 => 41,  148 => 34,  138 => 29,  133 => 27,  125 => 24,  119 => 23,  113 => 22,  107 => 19,  103 => 18,  95 => 16,  78 => 15,  70 => 12,  66 => 11,  61 => 8,  57 => 7,  50 => 5,  46 => 1,  44 => 3,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}

{% import \"macro_functions.phtml\" as mf %}

{% block meta_title %}{{ ticket.subject }}{% endblock %}

{% block content %}
<div class=\"h-block forum\">
    <div class=\"h-block-header\">
        <div class=\"icon\"><span class=\"big-light-icon i-support\"></span></div>
        <h2>{{ ticket.subject|truncate(60) }}</h2>
        <p>#{{ ticket.id }} - {{ticket.helpdesk.name}}</p>
    </div>
    <div class=\"block conversation\">
    {% for i, message in ticket.messages %}
    <div class=\"widget simpleTabs tabsRight{% if loop.first %} first{% endif %}\">
        <div class=\"head\">
            <h2 class=\"dark-icon i-forum\">{{ message.author.name }}</h2>
            <h3>{{ message.created_at|bb_date }}</h3>
        </div>
        <ul class=\"tabs\">
            <li><a href=\"#m-{{message.id}}\">#{{ i+1 }}</a></li>
            <li><a href=\"#reply-{{message.id}}\"><span class=\"dark-icon i-support\"></span>{{ 'Reply'|trans }}</a></li>
            <li><a href=\"#quote-{{message.id}}\"><span class=\"dark-icon i-quote\"></span>{{ 'Quote'|trans }}</a></li>
        </ul>
        <div class=\"tabs_container\">
            <div class=\"tab_content\" id=\"m-{{message.id}}\">
                <div class=\"grid_2 alpha\">
                    <img src=\"{{ message.author.email|gravatar }}?size=60\" alt=\"{{ message.author.name }}\" class=\"gravatar\"/>
                </div>
                <div class=\"grid_10 omega\">
                    <div class=\"block message\">
                        <div class=\"body\">
                            {{ message.content|bbmd }}
                            <div class=\"clear\"></div>
                        </div>
                    </div>
                </div>
                <div class=\"clear\"></div>
            </div>
            <div class=\"tab_content\" id=\"reply-{{message.id}}\">
                <form method=\"post\" action=\"\" class=\"api_form\" data-api-url=\"{{ 'api/client/support/ticket_reply'|link }}\" data-api-reload=\"1\">
                    <fieldset>
                        <p>
                            <textarea name=\"content\" cols=\"5\" rows=\"10\" required=\"required\"></textarea>
                        </p>
                        <input type=\"hidden\" name=\"id\" value=\"{{ ticket.id }}\">
                        <input class=\"bb-button bb-button-submit\" type=\"submit\" value=\"{{ 'Post'|trans }}\">
                    </fieldset>
                </form>
            </div>

            <div class=\"tab_content\" id=\"quote-{{message.id}}\">
                <form method=\"post\" action=\"\" class=\"api_form\" data-api-url=\"{{ 'api/client/support/ticket_reply'|link }}\" data-api-reload=\"1\">
                    <fieldset>
                        <p>
                            <textarea name=\"content\" cols=\"5\" rows=\"10\" required=\"required\">{{ mf.markdown_quote(message.content) }}</textarea>
                        </p>
                        <input type=\"hidden\" name=\"id\" value=\"{{ ticket.id }}\">
                        <input class=\"bb-button bb-button-submit\" type=\"submit\" value=\"{{ 'Post'|trans }}\">
                    </fieldset>
                </form>
            </div>
        </div>
    </div>
    {% endfor %}
    </div>
</div>

<div class=\"grid_12 alpha omega\">
    <div class=\"widget\">
        <div class=\"head\">
            <h2 class=\"dark-icon i-support\">{{ticket.subject|truncate(95)}}</h2>
        </div>
        <table>
            <tbody>
                <tr>
                    <td>{{ 'Ticket ID'|trans }}</td>
                    <td><b>#{{ ticket.id }}</b></td>
                </tr>

                <tr>
                    <td>{{ 'Help desk'|trans }}</td>
                    <td>{{ ticket.helpdesk.name }}</td>
                </tr>

                <tr>
                    <td>{{ 'Status'|trans }}</td>
                    <td>{{ mf.status_name(ticket.status) }}</td>
                </tr>

                <tr>
                    <td>{{ 'Time opened'|trans }}</td>
                    <td>{{ ticket.created_at|bb_date }}</td>
                </tr>

                {% if ticket.rel_type == 'order' and ticket.rel_id %}
                <tr>
                    <td>{{ 'Related to'|trans }}</td>
                    <td><a href=\"{{ 'order/service/manage'|link }}/{{ ticket.rel_id }}\">{{ 'Service #'|trans }} {{ ticket.rel_id }}</a></td>
                </tr>
                {% endif %}
             </tbody>

             <tfoot>
                 <tr>
                     <td colspan=\"2\">

                     <a class=\"bb-button\" href=\"{{ '/support'|link }}\"><span class=\"dark-icon i-arrow\"></span>{{ 'Tickets list'|trans }}</a>

                     {% if ticket.status != 'closed' %}
                        <button class=\"bb-button\" type=\"button\" id=\"ticket-close\">{{ 'Close ticket'|trans }}</button>
                        {% endif %}
                     </td>
                 </tr>
             </tfoot>
        </table>
    </div>
</div>

{% endblock %}

{% block js %}
<script type=\"text/javascript\">
    \$(function() {
        \$('#ticket-close').bind('click', function(event) {
            bb.post(
                \"{{ 'api/client/support/ticket_close'|link }}\",
                { id: {{ ticket.id }} },
                function(result) {
                    bb.redirect(\"{{ 'support'|link }}\");
                }
            );

            return false;
        });
    });
</script>
{% endblock %}
", "mod_support_ticket.phtml", "/shared/httpd/up-boxbilling/FOSSBilling/src/bb-themes/boxbilling/html/mod_support_ticket.phtml");
    }
}
