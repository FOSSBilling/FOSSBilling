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

/* mod_index_dashboard.phtml */
class __TwigTemplate_46233a374024aa8490a74de24a9089b535d22e5cd94a3bf6f4bb3b3e14220830 extends Template
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
        return $this->loadTemplate(((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ajax", [], "any", false, false, false, 1)) ? ("layout_blank.phtml") : ("layout_default.phtml")), "mod_index_dashboard.phtml", 1);
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 5
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_index_dashboard.phtml", 5)->unwrap();
        // line 1
        $this->getParent($context)->display($context, array_merge($this->blocks, $blocks));
    }

    // line 3
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo twig_escape_filter($this->env, gettext("Client Area"), "html", null, true);
    }

    // line 7
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 8
        echo "<div class=\"dashboard\">

    <div class=\"grid_6 alpha\">
        <div class=\"box\">
            <h2 class=\"big-dark-icon i-order\"><a href=\"";
        // line 12
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/order");
        echo "\">";
        echo twig_escape_filter($this->env, gettext("Order"), "html", null, true);
        echo "</a></h2>
            <div class=\"block\">
                <p>";
        // line 14
        echo twig_escape_filter($this->env, gettext("Order products and services"), "html", null, true);
        echo "</p>
            </div>
        </div>
    </div>
    ";
        // line 18
        if (($context["client"] ?? null)) {
            // line 19
            echo "    <div class=\"grid_6 omega\">
        <div class=\"box\">
            <h2 class=\"big-dark-icon i-profile\"><a href=\"";
            // line 21
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("client/me");
            echo "\">";
            echo twig_escape_filter($this->env, gettext("Profile"), "html", null, true);
            echo "</a></h2>
            <div class=\"block\">
                <p>";
            // line 23
            echo twig_escape_filter($this->env, gettext("Manage your profile and keep it up to date"), "html", null, true);
            echo "</p>
            </div>
        </div>
    </div>
    ";
        } else {
            // line 28
            echo "    <div class=\"grid_6 omega\">
        <div class=\"box\">
            <h2 class=\"big-dark-icon i-profile\"><a href=\"";
            // line 30
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/login", ["register" => 1]);
            echo "\">";
            echo twig_escape_filter($this->env, gettext("Register"), "html", null, true);
            echo "</a></h2>
            <div class=\"block\">
                <p>";
            // line 32
            echo twig_escape_filter($this->env, gettext("Become a member and manage services"), "html", null, true);
            echo "</p>
            </div>
        </div>
    </div>
    ";
        }
        // line 37
        echo "    <div class=\"grid_6 alpha\">
        <div class=\"box\">
            <h2 class=\"big-dark-icon i-email\"><a href=\"";
        // line 39
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/support/contact-us");
        echo "\">";
        echo twig_escape_filter($this->env, gettext("Contact Us"), "html", null, true);
        echo "</a></h2>
            <div class=\"block\">
                <p>";
        // line 41
        echo twig_escape_filter($this->env, gettext("Contact us for more information"), "html", null, true);
        echo "</p>
            </div>
        </div>
    </div>

    ";
        // line 46
        if (twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "extension_is_on", [0 => ["mod" => "news"]], "method", false, false, false, 46)) {
            // line 47
            echo "    <div class=\"grid_6 omega\">
        <div class=\"box\">
            <h2 class=\"big-dark-icon i-blog\"><a href=\"";
            // line 49
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/news");
            echo "\">";
            echo twig_escape_filter($this->env, gettext("Announcements"), "html", null, true);
            echo "</a></h2>
            <div class=\"block\">
                <p>";
            // line 51
            echo twig_escape_filter($this->env, gettext("Latest news & announcements"), "html", null, true);
            echo "</p>
            </div>
        </div>
    </div>
    ";
        }
        // line 56
        echo "
    ";
        // line 57
        if (twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "extension_is_on", [0 => ["mod" => "kb"]], "method", false, false, false, 57)) {
            // line 58
            echo "    <div class=\"grid_6 alpha\">
        <div class=\"box\">
            <h2 class=\"big-dark-icon i-kb\"><a href=\"";
            // line 60
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/kb");
            echo "\">";
            echo twig_escape_filter($this->env, gettext("Knowledge Base"), "html", null, true);
            echo "</a></h2>
            <div class=\"block\">
                <p>";
            // line 62
            echo twig_escape_filter($this->env, gettext("Browse our KB for answers and FAQs"), "html", null, true);
            echo "</p>
            </div>
        </div>
    </div>
    ";
        }
        // line 67
        echo "
    ";
        // line 68
        if (twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "extension_is_on", [0 => ["mod" => "branding"]], "method", false, false, false, 68)) {
            // line 69
            echo "    <div class=\"grid_6 omega\">
        <div class=\"box\">
            <h2 class=\"big-dark-icon i-boxbilling\"><a href=\"https://github.com/boxbilling/boxbilling\">";
            // line 71
            echo twig_escape_filter($this->env, gettext("Invoicing Software"), "html", null, true);
            echo "</a></h2>
            <div class=\"block\">
                <p>";
            // line 73
            echo twig_escape_filter($this->env, gettext("Powered by BoxBilling invoicing software"), "html", null, true);
            echo "</p>
            </div>
        </div>
    </div>
    ";
        }
        // line 78
        echo "    <div class=\"clear\"></div>
</div>
";
    }

    // line 82
    public function block_js($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 83
        echo "<script type=\"text/javascript\">
    \$(function() {
        \$('#client-login').bind('submit', function(event) {
            bb.post(
                'guest/client/login',
                \$(this).serialize(),
                function(result) {
                    bb.redirect();
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
        return "mod_index_dashboard.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  217 => 83,  213 => 82,  207 => 78,  199 => 73,  194 => 71,  190 => 69,  188 => 68,  185 => 67,  177 => 62,  170 => 60,  166 => 58,  164 => 57,  161 => 56,  153 => 51,  146 => 49,  142 => 47,  140 => 46,  132 => 41,  125 => 39,  121 => 37,  113 => 32,  106 => 30,  102 => 28,  94 => 23,  87 => 21,  83 => 19,  81 => 18,  74 => 14,  67 => 12,  61 => 8,  57 => 7,  50 => 3,  46 => 1,  44 => 5,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}

{% block meta_title %}{{ 'Client Area'|trans }}{% endblock %}

{% import \"macro_functions.phtml\" as mf %}

{% block content %}
<div class=\"dashboard\">

    <div class=\"grid_6 alpha\">
        <div class=\"box\">
            <h2 class=\"big-dark-icon i-order\"><a href=\"{{ '/order'|link }}\">{{ 'Order'|trans }}</a></h2>
            <div class=\"block\">
                <p>{{ 'Order products and services'|trans }}</p>
            </div>
        </div>
    </div>
    {% if client %}
    <div class=\"grid_6 omega\">
        <div class=\"box\">
            <h2 class=\"big-dark-icon i-profile\"><a href=\"{{ 'client/me'|link }}\">{{ 'Profile'|trans }}</a></h2>
            <div class=\"block\">
                <p>{{ 'Manage your profile and keep it up to date'|trans }}</p>
            </div>
        </div>
    </div>
    {% else %}
    <div class=\"grid_6 omega\">
        <div class=\"box\">
            <h2 class=\"big-dark-icon i-profile\"><a href=\"{{ '/login'|link({'register' : 1}) }}\">{{ 'Register'|trans }}</a></h2>
            <div class=\"block\">
                <p>{{ 'Become a member and manage services'|trans }}</p>
            </div>
        </div>
    </div>
    {% endif %}
    <div class=\"grid_6 alpha\">
        <div class=\"box\">
            <h2 class=\"big-dark-icon i-email\"><a href=\"{{ '/support/contact-us'|link }}\">{{ 'Contact Us'|trans }}</a></h2>
            <div class=\"block\">
                <p>{{ 'Contact us for more information'|trans }}</p>
            </div>
        </div>
    </div>

    {% if guest.extension_is_on({\"mod\":\"news\"}) %}
    <div class=\"grid_6 omega\">
        <div class=\"box\">
            <h2 class=\"big-dark-icon i-blog\"><a href=\"{{ '/news'|link }}\">{{ 'Announcements'|trans }}</a></h2>
            <div class=\"block\">
                <p>{{ 'Latest news & announcements'|trans }}</p>
            </div>
        </div>
    </div>
    {% endif %}

    {% if guest.extension_is_on({\"mod\":\"kb\"}) %}
    <div class=\"grid_6 alpha\">
        <div class=\"box\">
            <h2 class=\"big-dark-icon i-kb\"><a href=\"{{ '/kb'|link }}\">{{ 'Knowledge Base'|trans }}</a></h2>
            <div class=\"block\">
                <p>{{ 'Browse our KB for answers and FAQs'|trans }}</p>
            </div>
        </div>
    </div>
    {% endif %}

    {% if guest.extension_is_on({ \"mod\": \"branding\" }) %}
    <div class=\"grid_6 omega\">
        <div class=\"box\">
            <h2 class=\"big-dark-icon i-boxbilling\"><a href=\"https://github.com/boxbilling/boxbilling\">{{ 'Invoicing Software'|trans }}</a></h2>
            <div class=\"block\">
                <p>{{ 'Powered by BoxBilling invoicing software'|trans }}</p>
            </div>
        </div>
    </div>
    {% endif %}
    <div class=\"clear\"></div>
</div>
{% endblock %}

{% block js %}
<script type=\"text/javascript\">
    \$(function() {
        \$('#client-login').bind('submit', function(event) {
            bb.post(
                'guest/client/login',
                \$(this).serialize(),
                function(result) {
                    bb.redirect();
                }
            );

            return false;
        });
    });
</script>
{% endblock %}", "mod_index_dashboard.phtml", "/shared/httpd/up-boxbilling/FOSSBilling/src/bb-themes/boxbilling/html/mod_index_dashboard.phtml");
    }
}
