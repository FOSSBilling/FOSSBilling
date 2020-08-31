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

/* mod_news_index.phtml */
class __TwigTemplate_918cee07d31add9a27a541af038de6bfb7322287aa9869e8f398c4fb6c710398 extends \Twig\Template
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
        ];
    }

    protected function doGetParent(array $context)
    {
        // line 1
        return $this->loadTemplate(((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ajax", [], "any", false, false, false, 1)) ? ("layout_blank.phtml") : ("layout_default.phtml")), "mod_news_index.phtml", 1);
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
        echo gettext("Blog");
    }

    // line 4
    public function block_breadcrumb($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo gettext("Blog");
    }

    // line 6
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 7
        echo "<div class=\"row\">
    <article class=\"span12 data-block\">
        <div class=\"data-container\">
            <header>
                <div class=\"row-fluid\"></div>
                <h1>";
        // line 12
        echo gettext("News & Announcements");
        echo "</h1>
                <p>";
        // line 13
        echo gettext("Track our latest information.");
        echo "</p>
            </header>
            <section>
                ";
        // line 16
        $context["posts"] = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "news_get_list", [0 => ["page" => twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 16), "per_page" => 5]], "method", false, false, false, 16);
        // line 17
        echo "                ";
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["posts"] ?? null), "list", [], "any", false, false, false, 17));
        $context['_iterated'] = false;
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
        foreach ($context['_seq'] as $context["i"] => $context["post"]) {
            // line 18
            echo "                <div class=\"article";
            if (twig_get_attribute($this->env, $this->source, $context["loop"], "last", [], "any", false, false, false, 18)) {
                echo " last";
            }
            echo " data-container\">
                    <header>
                        <h2><a href=\"";
            // line 20
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/news");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["post"], "slug", [], "any", false, false, false, 20), "html", null, true);
            echo "\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["post"], "title", [], "any", false, false, false, 20), "html", null, true);
            echo "</a></h2>
                        <div class=\"pull-right\"><h5>";
            // line 21
            echo twig_escape_filter($this->env, $this->extensions['Box_TwigExtensions']->twig_bb_date(twig_get_attribute($this->env, $this->source, $context["post"], "updated_at", [], "any", false, false, false, 21)), "html", null, true);
            echo "</h5></div>
                    </header>
                   <section>
                       ";
            // line 24
            if (twig_get_attribute($this->env, $this->source, $context["post"], "excerpt", [], "any", false, false, false, 24)) {
                // line 25
                echo "                            ";
                echo twig_bbmd_filter($this->env, twig_get_attribute($this->env, $this->source, $context["post"], "excerpt", [], "any", false, false, false, 25));
                echo "
                       ";
            } else {
                // line 27
                echo "                            ";
                echo twig_bbmd_filter($this->env, twig_get_attribute($this->env, $this->source, $context["post"], "content", [], "any", false, false, false, 27));
                echo "
                       ";
            }
            // line 29
            echo "                   </section>
                    <a class=\"btn btn-primary btn-small read_more\" href=\"";
            // line 30
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/news");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["post"], "slug", [], "any", false, false, false, 30), "html", null, true);
            echo "\">";
            echo gettext("Read more ...");
            echo "</a>
                </div>
                ";
            $context['_iterated'] = true;
            ++$context['loop']['index0'];
            ++$context['loop']['index'];
            $context['loop']['first'] = false;
            if (isset($context['loop']['length'])) {
                --$context['loop']['revindex0'];
                --$context['loop']['revindex'];
                $context['loop']['last'] = 0 === $context['loop']['revindex0'];
            }
        }
        if (!$context['_iterated']) {
            // line 33
            echo "                <p>The list is empty</p>
                ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['i'], $context['post'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 35
        echo "            </section>
        </div>
        ";
        // line 37
        $this->loadTemplate("partial_pagination.phtml", "mod_news_index.phtml", 37)->display(twig_array_merge($context, ["list" => ($context["posts"] ?? null)]));
        // line 38
        echo "    </article>
</div>

";
    }

    public function getTemplateName()
    {
        return "mod_news_index.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  175 => 38,  173 => 37,  169 => 35,  162 => 33,  142 => 30,  139 => 29,  133 => 27,  127 => 25,  125 => 24,  119 => 21,  111 => 20,  103 => 18,  84 => 17,  82 => 16,  76 => 13,  72 => 12,  65 => 7,  61 => 6,  54 => 4,  47 => 3,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}

{% block meta_title %}{% trans 'Blog'%}{% endblock %}
{% block breadcrumb %}{% trans 'Blog'%}{% endblock %}

{% block content %}
<div class=\"row\">
    <article class=\"span12 data-block\">
        <div class=\"data-container\">
            <header>
                <div class=\"row-fluid\"></div>
                <h1>{% trans 'News & Announcements' %}</h1>
                <p>{% trans 'Track our latest information.' %}</p>
            </header>
            <section>
                {% set posts = guest.news_get_list({\"page\":request.page,\"per_page\": 5}) %}
                {% for i, post in posts.list %}
                <div class=\"article{% if loop.last %} last{% endif%} data-container\">
                    <header>
                        <h2><a href=\"{{ '/news'|link }}/{{post.slug}}\">{{post.title}}</a></h2>
                        <div class=\"pull-right\"><h5>{{post.updated_at|bb_date}}</h5></div>
                    </header>
                   <section>
                       {% if post.excerpt %}
                            {{ post.excerpt|bbmd }}
                       {% else %}
                            {{ post.content|bbmd }}
                       {% endif %}
                   </section>
                    <a class=\"btn btn-primary btn-small read_more\" href=\"{{ '/news'|link }}/{{post.slug}}\">{% trans 'Read more ...' %}</a>
                </div>
                {% else %}
                <p>The list is empty</p>
                {% endfor %}
            </section>
        </div>
        {% include \"partial_pagination.phtml\" with {'list': posts} %}
    </article>
</div>

{% endblock %}", "mod_news_index.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-modules/News/html_client/mod_news_index.phtml");
    }
}
