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

/* mod_news_post.phtml */
class __TwigTemplate_fc5bc75e71e9dff2c197cf827a4e5ddca792c2efd6a5cdab91aa622f29b4eb9e extends \Twig\Template
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
            'opengraph' => [$this, 'block_opengraph'],
            'content' => [$this, 'block_content'],
        ];
    }

    protected function doGetParent(array $context)
    {
        // line 1
        return $this->loadTemplate(((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ajax", [], "any", false, false, false, 1)) ? ("layout_blank.phtml") : ("layout_default.phtml")), "mod_news_post.phtml", 1);
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
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["post"] ?? null), "title", [], "any", false, false, false, 3), "html", null, true);
    }

    // line 4
    public function block_breadcrumb($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 5
        echo "<li><a href=\"";
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("news");
        echo "\">";
        echo gettext("Blog");
        echo "</a><span class=\"divider\">/</span></li>
<li class=\"active\">";
        // line 6
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["post"] ?? null), "title", [], "any", false, false, false, 6), "html", null, true);
        echo "</li>
";
    }

    // line 9
    public function block_opengraph($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 10
        echo "
    <meta property=\"og:title\" content=\"";
        // line 11
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["post"] ?? null), "title", [], "any", false, false, false, 11), "html", null, true);
        echo "\" />
    <meta property=\"og:type\" content=\"article\" />
    <meta property=\"og:url\" content=\"";
        // line 13
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_current_url", [], "any", false, false, false, 13), "html", null, true);
        echo "\" />
    <meta property=\"og:image\" content=\"";
        // line 14
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["post"] ?? null), "image", [], "any", false, false, false, 14), "html", null, true);
        echo "\" />
    <meta property=\"article:author\" content=\"";
        // line 15
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["post"] ?? null), "author", [], "any", false, false, false, 15), "name", [], "any", false, false, false, 15), "html", null, true);
        echo "\" />
    
    ";
        // line 17
        if (twig_get_attribute($this->env, $this->source, ($context["post"] ?? null), "published_at", [], "any", false, false, false, 17)) {
            echo "<meta property=\"article:published_time\" content=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["post"] ?? null), "published_at", [], "any", false, false, false, 17), "html", null, true);
            echo "\" />";
        }
        // line 18
        echo "    ";
        if (twig_get_attribute($this->env, $this->source, ($context["post"] ?? null), "updated_at", [], "any", false, false, false, 18)) {
            echo "<meta property=\"article:modified_time\" content=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["post"] ?? null), "updated_at", [], "any", false, false, false, 18), "html", null, true);
            echo "\" />";
        }
        // line 19
        echo "    ";
        if (twig_get_attribute($this->env, $this->source, ($context["post"] ?? null), "expires_at", [], "any", false, false, false, 19)) {
            echo "<meta property=\"article:expiration_time\" content=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["post"] ?? null), "expires_at", [], "any", false, false, false, 19), "html", null, true);
            echo "\" />";
        }
        // line 20
        echo "    ";
        if (twig_get_attribute($this->env, $this->source, ($context["post"] ?? null), "section", [], "any", false, false, false, 20)) {
            echo "<meta property=\"article:section\" content=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["post"] ?? null), "section", [], "any", false, false, false, 20), "html", null, true);
            echo "\" />";
        }
        // line 21
        echo "    ";
        if (twig_get_attribute($this->env, $this->source, ($context["post"] ?? null), "tags", [], "any", false, false, false, 21)) {
            echo "<meta property=\"article:tag\" content=\"";
            echo twig_escape_filter($this->env, twig_join_filter(twig_get_attribute($this->env, $this->source, ($context["post"] ?? null), "tags", [], "any", false, false, false, 21), ", "), "html", null, true);
            echo "\" />";
        }
        // line 22
        echo "    
";
    }

    // line 25
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 26
        echo "<div class=\"row\">
    <article class=\"span12 data-block\">
        <div class=\"data-container\">
            <header>
                <h1>";
        // line 30
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["post"] ?? null), "title", [], "any", false, false, false, 30), "html", null, true);
        echo "</h1>
                <div class=\"pull-right\"><h5>";
        // line 31
        echo twig_escape_filter($this->env, $this->extensions['Box_TwigExtensions']->twig_bb_date(twig_get_attribute($this->env, $this->source, ($context["post"] ?? null), "created_at", [], "any", false, false, false, 31)), "html", null, true);
        echo "</h5></div>
                <p>";
        // line 32
        echo gettext("by ");
        echo " ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["post"] ?? null), "author", [], "any", false, false, false, 32), "name", [], "any", false, false, false, 32), "html", null, true);
        echo "</p>
            </header>
            <section>
                ";
        // line 35
        if (twig_get_attribute($this->env, $this->source, ($context["post"] ?? null), "image", [], "any", false, false, false, 35)) {
            echo "<img src=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["post"] ?? null), "image", [], "any", false, false, false, 35), "html", null, true);
            echo "\" alt=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["post"] ?? null), "title", [], "any", false, false, false, 35), "html", null, true);
            echo "\">";
        }
        // line 36
        echo "                ";
        echo twig_bbmd_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["post"] ?? null), "content", [], "any", false, false, false, 36));
        echo "
                ";
        // line 38
        echo "
                ";
        // line 39
        if (twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "extension_is_on", [0 => ["mod" => "comment"]], "method", false, false, false, 39)) {
            $this->loadTemplate("mod_comment_block.phtml", "mod_news_post.phtml", 39)->display($context);
        }
        // line 40
        echo "                <hr/>
                <p><a class=\"btn btn-small\" href=\"";
        // line 41
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("news");
        echo "\">";
        echo gettext("Back to list");
        echo "</a></p>
            </section>
        </div>
    </article>
</div>

";
    }

    public function getTemplateName()
    {
        return "mod_news_post.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  185 => 41,  182 => 40,  178 => 39,  175 => 38,  170 => 36,  162 => 35,  154 => 32,  150 => 31,  146 => 30,  140 => 26,  136 => 25,  131 => 22,  124 => 21,  117 => 20,  110 => 19,  103 => 18,  97 => 17,  92 => 15,  88 => 14,  84 => 13,  79 => 11,  76 => 10,  72 => 9,  66 => 6,  59 => 5,  55 => 4,  48 => 3,  38 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}

{% block meta_title %}{{post.title}}{% endblock %}
{% block breadcrumb %}
<li><a href=\"{{ 'news' | link}}\">{% trans 'Blog' %}</a><span class=\"divider\">/</span></li>
<li class=\"active\">{{post.title}}</li>
{% endblock %}

{% block opengraph %}

    <meta property=\"og:title\" content=\"{{ post.title }}\" />
    <meta property=\"og:type\" content=\"article\" />
    <meta property=\"og:url\" content=\"{{ guest.system_current_url }}\" />
    <meta property=\"og:image\" content=\"{{post.image}}\" />
    <meta property=\"article:author\" content=\"{{ post.author.name }}\" />
    
    {% if post.published_at %}<meta property=\"article:published_time\" content=\"{{post.published_at}}\" />{% endif %}
    {% if post.updated_at %}<meta property=\"article:modified_time\" content=\"{{post.updated_at}}\" />{% endif %}
    {% if post.expires_at %}<meta property=\"article:expiration_time\" content=\"{{post.expires_at}}\" />{% endif %}
    {% if post.section %}<meta property=\"article:section\" content=\"{{ post.section }}\" />{% endif %}
    {% if post.tags %}<meta property=\"article:tag\" content=\"{{ post.tags|join(', ') }}\" />{% endif %}
    
{% endblock %}

{% block content %}
<div class=\"row\">
    <article class=\"span12 data-block\">
        <div class=\"data-container\">
            <header>
                <h1>{{post.title}}</h1>
                <div class=\"pull-right\"><h5>{{post.created_at|bb_date}}</h5></div>
                <p>{% trans 'by '%} {{ post.author.name }}</p>
            </header>
            <section>
                {% if post.image %}<img src=\"{{post.image}}\" alt=\"{{post.title}}\">{% endif %}
                {{ post.content|bbmd }}
                {# if post.tags %}Tags: {{ post.tags|join(', ') }}{% endif #}

                {% if guest.extension_is_on({\"mod\":\"comment\"}) %}{% include \"mod_comment_block.phtml\" %}{% endif %}
                <hr/>
                <p><a class=\"btn btn-small\" href=\"{{ 'news'|link }}\">{% trans 'Back to list' %}</a></p>
            </section>
        </div>
    </article>
</div>

{% endblock %}

{# block sidebar2 %}
<div class=\"row\">
    <article class=\"span6 data-block\">
        <div class=\"data-container\">
            <header>
                <h2>{% trans 'Other recent posts' %}</h2>
            </header>
            <section>
                <ul class=\"menu\">
                    {% set posts = guest.news_get_list({\"per_page\": 5}) %}
                    {% for i, post in posts.list %}
                    <li>
                        <a href=\"{{ '/news'|link }}/{{post.slug}}\">{{post.title|truncate(35)}}</a>
                    </li>
                    {% endfor %}
                </ul>
            </section>
        </div>
    </article>
</div>
{% endblock #}", "mod_news_post.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/src/bb-modules/News/html_client/mod_news_post.phtml");
    }
}
