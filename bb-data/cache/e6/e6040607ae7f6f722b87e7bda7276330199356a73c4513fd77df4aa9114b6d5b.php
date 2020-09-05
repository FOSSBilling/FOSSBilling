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

/* mod_kb_index.phtml */
class __TwigTemplate_23a6290840475a43fc17ff988ae7d221d9c603ebaf1efc86df5a73e173b0d6e4 extends \Twig\Template
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
        return $this->loadTemplate(((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ajax", [], "any", false, false, false, 1)) ? ("layout_blank.phtml") : ("layout_default.phtml")), "mod_kb_index.phtml", 1);
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
        echo gettext("Knowledge base");
    }

    // line 4
    public function block_breadcrumb($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo gettext("Knowledge base");
    }

    // line 6
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 7
        echo "
    ";
        // line 8
        if (twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "q", [], "any", false, false, false, 8)) {
            // line 9
            echo "        ";
            $context["kbcategories"] = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "kb_category_get_list", [0 => ["q" => twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "q", [], "any", false, false, false, 9)]], "method", false, false, false, 9);
            // line 10
            echo "    ";
        } else {
            // line 11
            echo "        ";
            $context["kbcategories"] = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "kb_category_get_list", [], "any", false, false, false, 11);
            // line 12
            echo "    ";
        }
        // line 13
        echo "
<div class=\"content-block\" role=\"main\">
    <div class=\"row\">
        <article class=\" span12 data-block\">
            <div class=\"data-container\">
                <header>
                    <h1>";
        // line 19
        echo gettext("Knowledge base");
        echo "</h1><br/>
                    ";
        // line 20
        echo gettext("Please try to read related topics in knowledge base before contacting support.");
        // line 21
        echo "                    <form method=\"get\" action=\"\" class=\"form form-search pull-right\" style=\"background: none; border: 0px\">
                        <p>
                            <input class=\"\" name=\"_url\" type=\"hidden\" value=\"/kb\">
                            <input class=\"search-query text\" name=\"q\" type=\"text\" value=\"";
        // line 24
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "q", [], "any", false, false, false, 24), "html", null, true);
        echo "\" placeholder=\"";
        echo gettext("What are you looking for?");
        echo "\">
                            <input class=\"btn btn-primary\" value=\"";
        // line 25
        echo gettext("Search");
        echo "\" type=\"submit\">
                        </p>
                    </form>
                </header>
                <section>

                    <div class=\"row\" >
                        ";
        // line 32
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["kbcategories"] ?? null), "list", [], "any", false, false, false, 32));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["i"] => $context["category"]) {
            // line 33
            echo "                        <article class=\"data-block\">
                            <div class=\"data-container\">
                                <header>
                                    <h2 id=\"category-";
            // line 36
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["category"], "id", [], "any", false, false, false, 36), "html", null, true);
            echo "\"><a href=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("kb");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["category"], "slug", [], "any", false, false, false, 36), "html", null, true);
            echo "\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["category"], "title", [], "any", false, false, false, 36), "html", null, true);
            echo "</a></h2>
                                </header>
                                <section>
                                    <ul>
                                        ";
            // line 40
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, $context["category"], "articles", [], "any", false, false, false, 40));
            foreach ($context['_seq'] as $context["i"] => $context["article"]) {
                // line 41
                echo "                                        <li><a href=\"";
                echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/kb");
                echo "/";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["article"], "category", [], "any", false, false, false, 41), "slug", [], "any", false, false, false, 41), "html", null, true);
                echo "/";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["article"], "slug", [], "any", false, false, false, 41), "html", null, true);
                echo "\">";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["article"], "title", [], "any", false, false, false, 41), "html", null, true);
                echo "</a></li>
                                        ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['i'], $context['article'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 43
            echo "                                    </ul>
                                </section>
                            </div>
                        </article>
                        ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 48
            echo "                        <p class=\"alert alert-block alert-danger\">
                            ";
            // line 49
            echo gettext("Try using other keyword. No matches found for keyword:");
            // line 50
            echo "                            <strong>";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "q", [], "any", false, false, false, 50), "html", null, true);
            echo "</strong>
                        </p>
                        ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['i'], $context['category'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 53
        echo "                    </div>
                </section>
            </div>
        </article>
    </div>
</div>
";
    }

    public function getTemplateName()
    {
        return "mod_kb_index.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  183 => 53,  173 => 50,  171 => 49,  168 => 48,  159 => 43,  144 => 41,  140 => 40,  127 => 36,  122 => 33,  117 => 32,  107 => 25,  101 => 24,  96 => 21,  94 => 20,  90 => 19,  82 => 13,  79 => 12,  76 => 11,  73 => 10,  70 => 9,  68 => 8,  65 => 7,  61 => 6,  54 => 4,  47 => 3,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}

{% block meta_title %}{%trans 'Knowledge base'%}{% endblock %}
{% block breadcrumb %}{%trans 'Knowledge base'%}{% endblock %}

{% block content %}

    {% if request.q %}
        {% set kbcategories = guest.kb_category_get_list({\"q\": request.q}) %}
    {% else %}
        {% set kbcategories = guest.kb_category_get_list %}
    {% endif %}

<div class=\"content-block\" role=\"main\">
    <div class=\"row\">
        <article class=\" span12 data-block\">
            <div class=\"data-container\">
                <header>
                    <h1>{% trans 'Knowledge base' %}</h1><br/>
                    {% trans 'Please try to read related topics in knowledge base before contacting support.' %}
                    <form method=\"get\" action=\"\" class=\"form form-search pull-right\" style=\"background: none; border: 0px\">
                        <p>
                            <input class=\"\" name=\"_url\" type=\"hidden\" value=\"/kb\">
                            <input class=\"search-query text\" name=\"q\" type=\"text\" value=\"{{ request.q }}\" placeholder=\"{% trans 'What are you looking for?' %}\">
                            <input class=\"btn btn-primary\" value=\"{% trans 'Search'%}\" type=\"submit\">
                        </p>
                    </form>
                </header>
                <section>

                    <div class=\"row\" >
                        {% for i, category in kbcategories.list %}
                        <article class=\"data-block\">
                            <div class=\"data-container\">
                                <header>
                                    <h2 id=\"category-{{category.id}}\"><a href=\"{{ 'kb'|link }}/{{ category.slug }}\">{{category.title}}</a></h2>
                                </header>
                                <section>
                                    <ul>
                                        {% for i, article in category.articles %}
                                        <li><a href=\"{{ '/kb'|link }}/{{article.category.slug}}/{{article.slug}}\">{{article.title}}</a></li>
                                        {% endfor %}
                                    </ul>
                                </section>
                            </div>
                        </article>
                        {% else %}
                        <p class=\"alert alert-block alert-danger\">
                            {% trans 'Try using other keyword. No matches found for keyword:' %}
                            <strong>{{ request.q }}</strong>
                        </p>
                        {% endfor %}
                    </div>
                </section>
            </div>
        </article>
    </div>
</div>
{% endblock %}
", "mod_kb_index.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/src/bb-modules/Kb/html_client/mod_kb_index.phtml");
    }
}
