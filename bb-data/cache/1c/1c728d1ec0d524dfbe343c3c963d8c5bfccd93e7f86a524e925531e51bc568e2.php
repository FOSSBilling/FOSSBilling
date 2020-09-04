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

/* mod_forum_index.phtml */
class __TwigTemplate_b8216f232e59843919a7fd0feb50f6d4e4db94e3ee53c9db920d2ffcbaf23e2f extends \Twig\Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->blocks = [
            'meta_title' => [$this, 'block_meta_title'],
            'meta_description' => [$this, 'block_meta_description'],
            'breadcrumb' => [$this, 'block_breadcrumb'],
            'content' => [$this, 'block_content'],
        ];
    }

    protected function doGetParent(array $context)
    {
        // line 1
        return $this->loadTemplate(((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ajax", [], "any", false, false, false, 1)) ? ("layout_blank.phtml") : ("layout_default.phtml")), "mod_forum_index.phtml", 1);
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
        echo gettext("Forum");
    }

    // line 4
    public function block_meta_description($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo gettext("Welcome to our forums.");
    }

    // line 5
    public function block_breadcrumb($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo " <li class=\"active\">";
        echo gettext("Forum");
        echo "</li>";
    }

    // line 7
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 8
        echo "
";
        // line 9
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "forum_get_categories", [], "any", false, false, false, 9));
        foreach ($context['_seq'] as $context["cat_name"] => $context["forums"]) {
            // line 10
            echo "    <div class=\"row\">
        <article class=\"span12 data-block\" role=\"main\">
            <div class=\"data-container\">
                <header>
                    <h1>";
            // line 14
            echo twig_escape_filter($this->env, $context["cat_name"], "html", null, true);
            echo "</h1>
                    <p>";
            // line 15
            echo gettext("Welcome to our forums. Feel free to join the discussions.");
            echo "</p>
                </header>
                <section>
                <table class=\"table table-striped table-bordered table-condensed\">
                    <thead>
                    <tr>
                        <th>&nbsp;</th>
                        <th style=\"width:80%;\">";
            // line 22
            echo gettext("Forum");
            echo "</th>
                        <th>";
            // line 23
            echo gettext("Threads");
            echo "</th>
                        <th>";
            // line 24
            echo gettext("Posts");
            echo "</th>
                    </tr>
                    </thead>

                    <tbody>
                    ";
            // line 29
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable($context["forums"]);
            foreach ($context['_seq'] as $context["_key"] => $context["forum"]) {
                // line 30
                echo "                    <tr>
                        <td>
                            <span class=\"awe-comments-alt awe-3x ";
                // line 32
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["forum"], "status", [], "any", false, false, false, 32), "html", null, true);
                echo "\" style=\"padding-right: 0px; padding-left: 5px\"></span>
                        </td>
                        <td>
                            <a href=\"";
                // line 35
                echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/forum");
                echo "/";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["forum"], "slug", [], "any", false, false, false, 35), "html", null, true);
                echo "\">";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["forum"], "title", [], "any", false, false, false, 35), "html", null, true);
                echo "</a>
                            <p>";
                // line 36
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["forum"], "description", [], "any", false, false, false, 36), "html", null, true);
                echo "</p>
                        </td>
                        <td>
                            ";
                // line 39
                echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["forum"], "stats", [], "any", false, true, false, 39), "topics_count", [], "any", true, true, false, 39)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["forum"], "stats", [], "any", false, true, false, 39), "topics_count", [], "any", false, false, false, 39), 0)) : (0)), "html", null, true);
                echo "
                        </td>
                        <td>";
                // line 41
                echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["forum"], "stats", [], "any", false, true, false, 41), "posts_count", [], "any", true, true, false, 41)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["forum"], "stats", [], "any", false, true, false, 41), "posts_count", [], "any", false, false, false, 41), 0)) : (0)), "html", null, true);
                echo "</td>
                    </tr>
                    ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['forum'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 44
            echo "                    </tbody>
                </table>
                ";
            // line 46
            if ( !($context["client"] ?? null)) {
                // line 47
                echo "                <p><button class=\"btn btn-primary\" type=\"button\" onclick=\"parent.location='";
                echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/login");
                echo "'\">";
                echo gettext("Register");
                echo "</button></p>
                ";
            }
            // line 49
            echo "            </section>
            </div>
        </article>
    </div>
";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['cat_name'], $context['forums'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 54
        echo "
";
        // line 55
        if ((($context["client"] ?? null) && twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "forum_favorites", [], "any", false, false, false, 55))) {
            // line 56
            echo "<div class=\"row\">
    <article class=\"span12 data-block\">
        <div class=\"data-container\">
            <header>
                <h2>";
            // line 60
            echo gettext("Favorite topics");
            echo "</h2>
                <p>";
            // line 61
            echo gettext("You set this topic as favorite. It can be done by opening any topic and clicking on \"Add to favorites\" button in \"Topic information\" block.");
            echo "</p>
            </header>
            <section>
                <table class=\"table table-striped table-bordered table-condensed\">
                    <thead>
                    <tr>
                        <th style=\"width:5%\">&nbsp;</th>
                        <th style=\"width:5%\">&nbsp;</th>
                        <th style=\"width:40%\">";
            // line 69
            echo gettext("Thread / Thread Starter");
            echo "</th>
                        <th>";
            // line 70
            echo gettext("Last post");
            echo "</th>
                        <th>";
            // line 71
            echo gettext("Replies");
            echo "</th>
                        <th>";
            // line 72
            echo gettext("Views");
            echo "</th>
                    </tr>
                    </thead>

                    <tbody>
                    ";
            // line 77
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "forum_favorites", [], "any", false, false, false, 77));
            $context['_iterated'] = false;
            foreach ($context['_seq'] as $context["i"] => $context["topic"]) {
                // line 78
                echo "                    <tr class=\"";
                echo twig_escape_filter($this->env, twig_cycle([0 => "odd", 1 => "even"], $context["i"]), "html", null, true);
                echo "\">
                        <td>
                            <span class=\"awe-star awe-2x ";
                // line 80
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["topic"], "status", [], "any", false, false, false, 80), "html", null, true);
                echo "\" style=\"padding-right: 0px; padding-left: 5px\"></span>
                        </td>
                        <td>
                            <img src=\"";
                // line 83
                echo twig_escape_filter($this->env, twig_gravatar_filter(twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["topic"], "first", [], "any", false, false, false, 83), "author", [], "any", false, false, false, 83), "email", [], "any", false, false, false, 83)), "html", null, true);
                echo "?size=30\" alt=\"";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["topic"], "first", [], "any", false, false, false, 83), "author", [], "any", false, false, false, 83), "name", [], "any", false, false, false, 83), "html", null, true);
                echo "\" />
                        </td>
                        <td>
                            <a href=\"";
                // line 86
                echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/forum");
                echo "/";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["topic"], "forum", [], "any", false, false, false, 86), "slug", [], "any", false, false, false, 86), "html", null, true);
                echo "/";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["topic"], "slug", [], "any", false, false, false, 86), "html", null, true);
                echo "\">";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["topic"], "title", [], "any", false, false, false, 86), "html", null, true);
                echo "</a>
                            <p style=\"font-size: .9em\">";
                // line 87
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["topic"], "first", [], "any", false, false, false, 87), "author", [], "any", false, false, false, 87), "name", [], "any", false, false, false, 87), "html", null, true);
                echo "</p>
                        </td>
                        <td>
                            ";
                // line 90
                echo twig_escape_filter($this->env, $this->extensions['Box_TwigExtensions']->twig_bb_date(twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["topic"], "last", [], "any", false, false, false, 90), "created_at", [], "any", false, false, false, 90)), "html", null, true);
                echo "
                            <p style=\"font-size: .9em\">";
                // line 91
                echo gettext("by");
                echo " <a href=\"";
                echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/forum");
                echo "/";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["topic"], "forum", [], "any", false, false, false, 91), "slug", [], "any", false, false, false, 91), "html", null, true);
                echo "/";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["topic"], "slug", [], "any", false, false, false, 91), "html", null, true);
                echo "#m-";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["topic"], "last", [], "any", false, false, false, 91), "id", [], "any", false, false, false, 91), "html", null, true);
                echo "\">";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["topic"], "last", [], "any", false, false, false, 91), "author", [], "any", false, false, false, 91), "name", [], "any", false, false, false, 91), "html", null, true);
                echo " ";
                echo "</p>
                        </td>
                        <td>";
                // line 93
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["topic"], "stats", [], "any", false, false, false, 93), "posts_count", [], "any", false, false, false, 93), "html", null, true);
                echo "</td>
                        <td>";
                // line 94
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["topic"], "stats", [], "any", false, false, false, 94), "views_count", [], "any", false, false, false, 94), "html", null, true);
                echo "</td>
                    </tr>

                    ";
                $context['_iterated'] = true;
            }
            if (!$context['_iterated']) {
                // line 98
                echo "
                    <tr>
                        <td colspan=\"6\">";
                // line 100
                echo gettext("The list is empty");
                echo "</td>
                    </tr>

                    ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['i'], $context['topic'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 104
            echo "
                    </tbody>

                </table>

            </section>
        </div>
    </article>
</div>
";
        }
        // line 114
        echo "


    
";
    }

    public function getTemplateName()
    {
        return "mod_forum_index.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  330 => 114,  318 => 104,  308 => 100,  304 => 98,  295 => 94,  291 => 93,  275 => 91,  271 => 90,  265 => 87,  255 => 86,  247 => 83,  241 => 80,  235 => 78,  230 => 77,  222 => 72,  218 => 71,  214 => 70,  210 => 69,  199 => 61,  195 => 60,  189 => 56,  187 => 55,  184 => 54,  174 => 49,  166 => 47,  164 => 46,  160 => 44,  151 => 41,  146 => 39,  140 => 36,  132 => 35,  126 => 32,  122 => 30,  118 => 29,  110 => 24,  106 => 23,  102 => 22,  92 => 15,  88 => 14,  82 => 10,  78 => 9,  75 => 8,  71 => 7,  62 => 5,  55 => 4,  48 => 3,  38 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}

{% block meta_title %}{% trans 'Forum' %}{% endblock %}
{% block meta_description %}{% trans 'Welcome to our forums.' %}{% endblock %}
{% block breadcrumb %} <li class=\"active\">{% trans 'Forum' %}</li>{% endblock %}

{% block content %}

{% for cat_name, forums in guest.forum_get_categories %}
    <div class=\"row\">
        <article class=\"span12 data-block\" role=\"main\">
            <div class=\"data-container\">
                <header>
                    <h1>{{cat_name}}</h1>
                    <p>{% trans 'Welcome to our forums. Feel free to join the discussions.' %}</p>
                </header>
                <section>
                <table class=\"table table-striped table-bordered table-condensed\">
                    <thead>
                    <tr>
                        <th>&nbsp;</th>
                        <th style=\"width:80%;\">{% trans 'Forum' %}</th>
                        <th>{% trans 'Threads' %}</th>
                        <th>{% trans 'Posts' %}</th>
                    </tr>
                    </thead>

                    <tbody>
                    {%  for forum in forums %}
                    <tr>
                        <td>
                            <span class=\"awe-comments-alt awe-3x {{forum.status}}\" style=\"padding-right: 0px; padding-left: 5px\"></span>
                        </td>
                        <td>
                            <a href=\"{{ '/forum'|link }}/{{forum.slug}}\">{{forum.title}}</a>
                            <p>{{forum.description}}</p>
                        </td>
                        <td>
                            {{forum.stats.topics_count|default(0)}}
                        </td>
                        <td>{{forum.stats.posts_count|default(0)}}</td>
                    </tr>
                    {% endfor %}
                    </tbody>
                </table>
                {% if not client %}
                <p><button class=\"btn btn-primary\" type=\"button\" onclick=\"parent.location='{{ '/login'|link }}'\">{% trans 'Register' %}</button></p>
                {% endif %}
            </section>
            </div>
        </article>
    </div>
{% endfor %}

{% if client and client.forum_favorites %}
<div class=\"row\">
    <article class=\"span12 data-block\">
        <div class=\"data-container\">
            <header>
                <h2>{% trans 'Favorite topics' %}</h2>
                <p>{% trans 'You set this topic as favorite. It can be done by opening any topic and clicking on \"Add to favorites\" button in \"Topic information\" block.' %}</p>
            </header>
            <section>
                <table class=\"table table-striped table-bordered table-condensed\">
                    <thead>
                    <tr>
                        <th style=\"width:5%\">&nbsp;</th>
                        <th style=\"width:5%\">&nbsp;</th>
                        <th style=\"width:40%\">{% trans 'Thread / Thread Starter' %}</th>
                        <th>{% trans 'Last post' %}</th>
                        <th>{% trans 'Replies' %}</th>
                        <th>{% trans 'Views' %}</th>
                    </tr>
                    </thead>

                    <tbody>
                    {% for i, topic in client.forum_favorites %}
                    <tr class=\"{{ cycle(['odd', 'even'], i) }}\">
                        <td>
                            <span class=\"awe-star awe-2x {{topic.status}}\" style=\"padding-right: 0px; padding-left: 5px\"></span>
                        </td>
                        <td>
                            <img src=\"{{ topic.first.author.email|gravatar }}?size=30\" alt=\"{{ topic.first.author.name }}\" />
                        </td>
                        <td>
                            <a href=\"{{ '/forum'|link }}/{{topic.forum.slug}}/{{topic.slug}}\">{{topic.title}}</a>
                            <p style=\"font-size: .9em\">{{ topic.first.author.name }}</p>
                        </td>
                        <td>
                            {{ topic.last.created_at|bb_date }}
                            <p style=\"font-size: .9em\">{% trans 'by' %} <a href=\"{{ '/forum'|link }}/{{topic.forum.slug}}/{{topic.slug}}#m-{{ topic.last.id }}\">{{ topic.last.author.name }} {#<img src=\"{{ topic.last.author.email|gravatar }}?size=15\" alt=\"{{ topic.last.author.name }}\"></a>#}</p>
                        </td>
                        <td>{{ topic.stats.posts_count }}</td>
                        <td>{{ topic.stats.views_count }}</td>
                    </tr>

                    {% else %}

                    <tr>
                        <td colspan=\"6\">{% trans 'The list is empty' %}</td>
                    </tr>

                    {% endfor %}

                    </tbody>

                </table>

            </section>
        </div>
    </article>
</div>
{% endif %}



    
{% endblock %}", "mod_forum_index.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-modules/Forum/html_client/mod_forum_index.phtml");
    }
}
