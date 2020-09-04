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
class __TwigTemplate_573ad03413b3f0c5b44c62c0b3866a6ddef1759765a6975ac9d0f8576812359a extends \Twig\Template
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
            'head' => [$this, 'block_head'],
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
        // line 2
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_news_index.phtml", 2)->unwrap();
        // line 3
        $context["active_menu"] = "support";
        // line 1
        $this->getParent($context)->display($context, array_merge($this->blocks, $blocks));
    }

    // line 4
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo gettext("News");
    }

    // line 6
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 7
        echo "<div class=\"widget simpleTabs\">

    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">";
        // line 10
        echo gettext("News and announcements");
        echo "</a></li>
        <li><a href=\"#tab-new\">";
        // line 11
        echo gettext("New announcement");
        echo "</a></li>
    </ul>

    <div class=\"tabs_container\">

        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-index\">

        ";
        // line 19
        echo twig_call_macro($macros["mf"], "macro_table_search", [], 19, $context, $this->getSourceContext());
        echo "
        <table class=\"tableStatic wide\">
            <thead>
                <tr>
                    <td style=\"width: 2%\"><input type=\"checkbox\" class=\"batch-delete-master-checkbox\"/></td>
                    <td colspan=\"2\">";
        // line 24
        echo gettext("Title");
        echo "</td>
                    <td>";
        // line 25
        echo gettext("Active");
        echo "</td>
                    <td>";
        // line 26
        echo gettext("Date");
        echo "</td>
                    <td style=\"width: 13%\">&nbsp;</td>
                </tr>
            </thead>

            <tbody>
            ";
        // line 32
        $context["posts"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "news_get_list", [0 => twig_array_merge(["per_page" => 30, "page" => twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 32)], ($context["request"] ?? null))], "method", false, false, false, 32);
        // line 33
        echo "            ";
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["posts"] ?? null), "list", [], "any", false, false, false, 33));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["i"] => $context["post"]) {
            // line 34
            echo "            <tr>
                <td><input type=\"checkbox\" class=\"batch-delete-checkbox\" data-item-id=\"";
            // line 35
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["post"], "id", [], "any", false, false, false, 35), "html", null, true);
            echo "\"/></td>
                <td>";
            // line 36
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["post"], "id", [], "any", false, false, false, 36), "html", null, true);
            echo "</td>
                <td>";
            // line 37
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["post"], "title", [], "any", false, false, false, 37), "html", null, true);
            echo "</td>
                <td>";
            // line 38
            echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, $context["post"], "status", [], "any", false, false, false, 38)], 38, $context, $this->getSourceContext());
            echo "</td>
                <td>";
            // line 39
            echo twig_escape_filter($this->env, $this->extensions['Box_TwigExtensions']->twig_bb_datetime(twig_get_attribute($this->env, $this->source, $context["post"], "created_at", [], "any", false, false, false, 39)), "html", null, true);
            echo "</td>
                <td class=\"actions\">
                    <a class=\"bb-button btn14\" href=\"";
            // line 41
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/news/post");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["post"], "id", [], "any", false, false, false, 41), "html", null, true);
            echo "\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                    <a class=\"bb-button btn14 bb-rm-tr api-link\" data-api-confirm=\"Are you sure?\" href=\"";
            // line 42
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/news/delete", ["id" => twig_get_attribute($this->env, $this->source, $context["post"], "id", [], "any", false, false, false, 42)]);
            echo "\" data-api-redirect=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("news");
            echo "\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                </td>
            </tr>

            ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 47
            echo "                <tr>
                    <td colspan=\"5\">
                        ";
            // line 49
            echo gettext("The list is empty");
            // line 50
            echo "                    </td>
                </tr>
            ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['i'], $context['post'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 53
        echo "            </tbody>
        </table>
        ";
        // line 55
        $this->loadTemplate("partial_pagination.phtml", "mod_news_index.phtml", 55)->display(twig_array_merge($context, ["list" => ($context["posts"] ?? null), "url" => "news/index"]));
        // line 56
        echo "        ";
        $this->loadTemplate("partial_batch_delete.phtml", "mod_news_index.phtml", 56)->display(twig_array_merge($context, ["action" => "admin/news/batch_delete"]));
        // line 57
        echo "
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-new\">

            <form method=\"post\" action=\"";
        // line 62
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/news/create");
        echo "\" class=\"mainForm api-form\" data-api-redirect=\"";
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("news");
        echo "\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>";
        // line 65
        echo gettext("Title");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"title\" value=\"";
        // line 67
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "title", [], "any", false, false, false, 67), "html", null, true);
        echo "\" required=\"required\" placeholder=\"\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 72
        echo gettext("Status");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"radio\" name=\"status\" value=\"draft\"checked=\"checked\"/><label>";
        // line 74
        echo gettext("Draft");
        echo "</label>
                            <input type=\"radio\" name=\"status\" value=\"active\"/><label>";
        // line 75
        echo gettext("Active");
        echo "</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 80
        echo gettext("Content");
        echo "</label>
                        <div class=\"formRight\">
                            <p>If the text is very long you can use <strong>&lt;!--more--&gt;</strong> tag. Inserting this tag within the post will create and excerpt of text (before the tag) to be displayed in posts list. Users will be able to see whole content when they click on \"Read more\" button.</p>
                            <br/>
                            <textarea name=\"content\" cols=\"5\" rows=\"10\" class=\"bb-textarea\">";
        // line 84
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "content", [], "any", false, false, false, 84), "html", null, true);
        echo "</textarea>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <input type=\"submit\" value=\"";
        // line 88
        echo gettext("Create");
        echo "\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>

    </div>
</div>

";
    }

    // line 98
    public function block_head($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo twig_call_macro($macros["mf"], "macro_bb_editor", [".bb-textarea"], 98, $context, $this->getSourceContext());
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
        return array (  253 => 98,  240 => 88,  233 => 84,  226 => 80,  218 => 75,  214 => 74,  209 => 72,  201 => 67,  196 => 65,  188 => 62,  181 => 57,  178 => 56,  176 => 55,  172 => 53,  164 => 50,  162 => 49,  158 => 47,  146 => 42,  140 => 41,  135 => 39,  131 => 38,  127 => 37,  123 => 36,  119 => 35,  116 => 34,  110 => 33,  108 => 32,  99 => 26,  95 => 25,  91 => 24,  83 => 19,  72 => 11,  68 => 10,  63 => 7,  59 => 6,  52 => 4,  48 => 1,  46 => 3,  44 => 2,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}
{% import \"macro_functions.phtml\" as mf %}
{% set active_menu = 'support' %}
{% block meta_title %}{% trans 'News' %}{% endblock %}

{% block content %}
<div class=\"widget simpleTabs\">

    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">{% trans 'News and announcements' %}</a></li>
        <li><a href=\"#tab-new\">{% trans 'New announcement' %}</a></li>
    </ul>

    <div class=\"tabs_container\">

        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-index\">

        {{ mf.table_search }}
        <table class=\"tableStatic wide\">
            <thead>
                <tr>
                    <td style=\"width: 2%\"><input type=\"checkbox\" class=\"batch-delete-master-checkbox\"/></td>
                    <td colspan=\"2\">{% trans 'Title' %}</td>
                    <td>{% trans 'Active' %}</td>
                    <td>{% trans 'Date' %}</td>
                    <td style=\"width: 13%\">&nbsp;</td>
                </tr>
            </thead>

            <tbody>
            {% set posts = admin.news_get_list({\"per_page\":30, \"page\":request.page}|merge(request)) %}
            {% for i, post in posts.list %}
            <tr>
                <td><input type=\"checkbox\" class=\"batch-delete-checkbox\" data-item-id=\"{{ post.id }}\"/></td>
                <td>{{ post.id }}</td>
                <td>{{ post.title }}</td>
                <td>{{ mf.status_name(post.status) }}</td>
                <td>{{ post.created_at|bb_datetime }}</td>
                <td class=\"actions\">
                    <a class=\"bb-button btn14\" href=\"{{ '/news/post'|alink }}/{{post.id}}\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                    <a class=\"bb-button btn14 bb-rm-tr api-link\" data-api-confirm=\"Are you sure?\" href=\"{{ 'api/admin/news/delete'|link({'id' : post.id}) }}\" data-api-redirect=\"{{ 'news'|alink }}\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                </td>
            </tr>

            {% else %}
                <tr>
                    <td colspan=\"5\">
                        {% trans 'The list is empty' %}
                    </td>
                </tr>
            {% endfor %}
            </tbody>
        </table>
        {% include \"partial_pagination.phtml\" with {'list': posts, 'url':'news/index'} %}
        {% include \"partial_batch_delete.phtml\" with {'action' : 'admin/news/batch_delete'} %}

        </div>

        <div class=\"tab_content nopadding\" id=\"tab-new\">

            <form method=\"post\" action=\"{{ 'api/admin/news/create'|link }}\" class=\"mainForm api-form\" data-api-redirect=\"{{ 'news'|alink }}\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>{% trans 'Title' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"title\" value=\"{{request.title}}\" required=\"required\" placeholder=\"\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Status' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"radio\" name=\"status\" value=\"draft\"checked=\"checked\"/><label>{% trans 'Draft' %}</label>
                            <input type=\"radio\" name=\"status\" value=\"active\"/><label>{% trans 'Active' %}</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Content' %}</label>
                        <div class=\"formRight\">
                            <p>If the text is very long you can use <strong>&lt;!--more--&gt;</strong> tag. Inserting this tag within the post will create and excerpt of text (before the tag) to be displayed in posts list. Users will be able to see whole content when they click on \"Read more\" button.</p>
                            <br/>
                            <textarea name=\"content\" cols=\"5\" rows=\"10\" class=\"bb-textarea\">{{ request.content }}</textarea>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <input type=\"submit\" value=\"{% trans 'Create' %}\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>

    </div>
</div>

{% endblock %}

{% block head %}{{ mf.bb_editor('.bb-textarea') }}{% endblock %}", "mod_news_index.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-modules/News/html_admin/mod_news_index.phtml");
    }
}
