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
class __TwigTemplate_2b94011af41ec3e75e3d9f20770208a8086d16fbf87f8ef30b7fdadca31b2728 extends \Twig\Template
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
        // line 2
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_kb_index.phtml", 2)->unwrap();
        // line 4
        $context["active_menu"] = "kb";
        // line 1
        $this->getParent($context)->display($context, array_merge($this->blocks, $blocks));
    }

    // line 3
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo gettext("Knowledge Base");
    }

    // line 5
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 6
        echo "
<div class=\"widget simpleTabs\">

    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">";
        // line 10
        echo gettext("Articles");
        echo "</a></li>
        <li><a href=\"#tab-new-article\">";
        // line 11
        echo gettext("New article");
        echo "</a></li>
        <li><a href=\"#tab-new-category\">";
        // line 12
        echo gettext("New category");
        echo "</a></li>
        <li><a href=\"#tab-categories\">";
        // line 13
        echo gettext("Manage categories");
        echo "</a></li>
    </ul>

    <div class=\"tabs_container\">

        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-index\">

        ";
        // line 21
        echo twig_call_macro($macros["mf"], "macro_table_search", [], 21, $context, $this->getSourceContext());
        echo "
        <table class=\"tableStatic wide\">
            <thead>
                <tr>
                    <td>";
        // line 25
        echo gettext("Title");
        echo "</td>
                    <td>";
        // line 26
        echo gettext("Category");
        echo "</td>
                    <td>";
        // line 27
        echo gettext("Status");
        echo "</td>
                    <td>";
        // line 28
        echo gettext("Views");
        echo "</td>
                    <td>&nbsp;</td>
                </tr>
            </thead>

            <tbody>
            ";
        // line 34
        $context["posts"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "kb_article_get_list", [0 => twig_array_merge(["per_page" => 30, "page" => twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 34)], ($context["request"] ?? null))], "method", false, false, false, 34);
        // line 35
        echo "            ";
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["posts"] ?? null), "list", [], "any", false, false, false, 35));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["i"] => $context["post"]) {
            // line 36
            echo "            <tr>
                <td>";
            // line 37
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["post"], "title", [], "any", false, false, false, 37), "html", null, true);
            echo "</td>
                <td><a href=\"";
            // line 38
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/kb/category");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["post"], "category", [], "any", false, false, false, 38), "id", [], "any", false, false, false, 38), "html", null, true);
            echo "\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["post"], "category", [], "any", false, false, false, 38), "title", [], "any", false, false, false, 38), "html", null, true);
            echo "</a></td>
                <td>";
            // line 39
            echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, $context["post"], "status", [], "any", false, false, false, 39)], 39, $context, $this->getSourceContext());
            echo "</td>
                <td>";
            // line 40
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["post"], "views", [], "any", false, false, false, 40), "html", null, true);
            echo "</td>
                <td class=\"actions\">
                    <a class=\"bb-button btn14\" href=\"";
            // line 42
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/kb/article");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["post"], "id", [], "any", false, false, false, 42), "html", null, true);
            echo "\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                    <a class=\"bb-button btn14 bb-rm-tr api-link\" data-api-confirm=\"Are you sure?\" data-api-redirect=\"";
            // line 43
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("kb");
            echo "\" href=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/kb/article_delete", ["id" => twig_get_attribute($this->env, $this->source, $context["post"], "id", [], "any", false, false, false, 43)]);
            echo "\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                </td>
            </tr>
            ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 47
            echo "                <tr>
                    <td colspan=\"4\">
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
        // line 54
        echo "            </tbody>
        </table>
        
        ";
        // line 57
        $this->loadTemplate("partial_pagination.phtml", "mod_kb_index.phtml", 57)->display(twig_array_merge($context, ["list" => ($context["posts"] ?? null), "url" => "kb"]));
        // line 58
        echo "        </div>


        <div class=\"tab_content nopadding\" id=\"tab-new-article\">
            <form method=\"post\" action=\"";
        // line 62
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/kb/article_create");
        echo "\" class=\"mainForm api-form\" data-api-reload=\"1\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>";
        // line 65
        echo gettext("Category");
        echo ":</label>
                        <div class=\"formRight\">
                            ";
        // line 67
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["kb_article_category_id", twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "kb_category_get_pairs", [], "any", false, false, false, 67), twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "kb_article_category_id", [], "any", false, false, false, 67), 1], 67, $context, $this->getSourceContext());
        echo "
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 73
        echo gettext("Title");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"title\" value=\"";
        // line 75
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "title", [], "any", false, false, false, 75), "html", null, true);
        echo "\" required=\"required\" placeholder=\"\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 81
        echo gettext("Status");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"radio\" name=\"status\" value=\"draft\" checked=\"checked\"/><label>";
        // line 83
        echo gettext("Draft");
        echo "</label>
                            <input type=\"radio\" name=\"status\" value=\"active\"/><label>";
        // line 84
        echo gettext("Active");
        echo "</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 90
        echo gettext("Content");
        echo "</label>
                        <div class=\"formRight\">
                            <textarea name=\"content\" cols=\"5\" rows=\"10\">";
        // line 92
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "content", [], "any", false, false, false, 92), "html", null, true);
        echo "</textarea>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <input type=\"submit\" value=\"";
        // line 96
        echo gettext("Create");
        echo "\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-new-category\">

            <form method=\"post\" action=\"";
        // line 103
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/kb/category_create");
        echo "\" class=\"mainForm save api-form\" data-api-reload=\"";
        echo gettext("Category created");
        echo "\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>";
        // line 106
        echo gettext("Title");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"title\" value=\"\" required=\"required\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 114
        echo gettext("Description");
        echo ":</label>
                        <div class=\"formRight\">
                            <textarea name=\"description\" cols=\"5\" rows=\"20\"></textarea>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <input type=\"submit\" value=\"";
        // line 121
        echo gettext("Create");
        echo "\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-categories\">
            <table class=\"tableStatic wide\">
                <tbody>
                    ";
        // line 129
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "kb_category_get_pairs", [], "any", false, false, false, 129));
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
        foreach ($context['_seq'] as $context["cat_id"] => $context["cat_title"]) {
            // line 130
            echo "                    <tr ";
            if (twig_get_attribute($this->env, $this->source, $context["loop"], "first", [], "any", false, false, false, 130)) {
                echo "class=\"noborder\"";
            }
            echo ">
                        <td>";
            // line 131
            echo twig_escape_filter($this->env, $context["cat_title"], "html", null, true);
            echo "</td>
                        <td class=\"actions\" style=\"width:13%\">
                            <a class=\"bb-button btn14\" href=\"";
            // line 133
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/kb/category");
            echo "/";
            echo twig_escape_filter($this->env, $context["cat_id"], "html", null, true);
            echo "\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                            <a class=\"bb-button btn14 bb-rm-tr api-link\" data-api-confirm=\"Are you sure?\" href=\"";
            // line 134
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/kb/category_delete", ["id" => $context["cat_id"]]);
            echo "\" data-api-redirect=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("kb/new");
            echo "\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                        </td>
                    </tr>
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
            // line 138
            echo "                    <tr>
                        <td colspan=\"3\">";
            // line 139
            echo gettext("The list is empty");
            echo "</td>
                    </tr>
                    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['cat_id'], $context['cat_title'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 142
        echo "                </tbody>
            </table>
        </div>

    </div>

    <div class=\"fix\"></div>
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
        return array (  377 => 142,  368 => 139,  365 => 138,  346 => 134,  340 => 133,  335 => 131,  328 => 130,  310 => 129,  299 => 121,  289 => 114,  278 => 106,  270 => 103,  260 => 96,  253 => 92,  248 => 90,  239 => 84,  235 => 83,  230 => 81,  221 => 75,  216 => 73,  207 => 67,  202 => 65,  196 => 62,  190 => 58,  188 => 57,  183 => 54,  174 => 50,  172 => 49,  168 => 47,  157 => 43,  151 => 42,  146 => 40,  142 => 39,  134 => 38,  130 => 37,  127 => 36,  121 => 35,  119 => 34,  110 => 28,  106 => 27,  102 => 26,  98 => 25,  91 => 21,  80 => 13,  76 => 12,  72 => 11,  68 => 10,  62 => 6,  58 => 5,  51 => 3,  47 => 1,  45 => 4,  43 => 2,  36 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}
{% import \"macro_functions.phtml\" as mf %}
{% block meta_title %}{% trans 'Knowledge Base' %}{% endblock %}
{% set active_menu = 'kb' %}
{% block content %}

<div class=\"widget simpleTabs\">

    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">{% trans 'Articles' %}</a></li>
        <li><a href=\"#tab-new-article\">{% trans 'New article' %}</a></li>
        <li><a href=\"#tab-new-category\">{% trans 'New category' %}</a></li>
        <li><a href=\"#tab-categories\">{% trans 'Manage categories' %}</a></li>
    </ul>

    <div class=\"tabs_container\">

        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-index\">

        {{ mf.table_search }}
        <table class=\"tableStatic wide\">
            <thead>
                <tr>
                    <td>{% trans 'Title' %}</td>
                    <td>{% trans 'Category' %}</td>
                    <td>{% trans 'Status' %}</td>
                    <td>{% trans 'Views' %}</td>
                    <td>&nbsp;</td>
                </tr>
            </thead>

            <tbody>
            {% set posts = admin.kb_article_get_list({\"per_page\":30, \"page\":request.page}|merge(request)) %}
            {% for i, post in posts.list %}
            <tr>
                <td>{{ post.title }}</td>
                <td><a href=\"{{ '/kb/category'|alink }}/{{post.category.id}}\">{{post.category.title}}</a></td>
                <td>{{ mf.status_name(post.status) }}</td>
                <td>{{ post.views }}</td>
                <td class=\"actions\">
                    <a class=\"bb-button btn14\" href=\"{{ '/kb/article'|alink }}/{{post.id}}\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                    <a class=\"bb-button btn14 bb-rm-tr api-link\" data-api-confirm=\"Are you sure?\" data-api-redirect=\"{{'kb'|alink}}\" href=\"{{ 'api/admin/kb/article_delete'|link({'id' : post.id}) }}\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                </td>
            </tr>
            {% else %}
                <tr>
                    <td colspan=\"4\">
                        {% trans 'The list is empty' %}
                    </td>
                </tr>
            
            {% endfor %}
            </tbody>
        </table>
        
        {% include \"partial_pagination.phtml\" with {'list': posts, 'url':'kb'} %}
        </div>


        <div class=\"tab_content nopadding\" id=\"tab-new-article\">
            <form method=\"post\" action=\"{{ 'api/admin/kb/article_create'|link }}\" class=\"mainForm api-form\" data-api-reload=\"1\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>{% trans 'Category' %}:</label>
                        <div class=\"formRight\">
                            {{ mf.selectbox('kb_article_category_id', admin.kb_category_get_pairs, request.kb_article_category_id, 1) }}
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Title' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"title\" value=\"{{request.title}}\" required=\"required\" placeholder=\"\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Status' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"radio\" name=\"status\" value=\"draft\" checked=\"checked\"/><label>{% trans 'Draft' %}</label>
                            <input type=\"radio\" name=\"status\" value=\"active\"/><label>{% trans 'Active' %}</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Content' %}</label>
                        <div class=\"formRight\">
                            <textarea name=\"content\" cols=\"5\" rows=\"10\">{{ request.content }}</textarea>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <input type=\"submit\" value=\"{% trans 'Create' %}\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-new-category\">

            <form method=\"post\" action=\"{{ 'api/admin/kb/category_create'|link }}\" class=\"mainForm save api-form\" data-api-reload=\"{% trans 'Category created' %}\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>{% trans 'Title' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"title\" value=\"\" required=\"required\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Description' %}:</label>
                        <div class=\"formRight\">
                            <textarea name=\"description\" cols=\"5\" rows=\"20\"></textarea>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <input type=\"submit\" value=\"{% trans 'Create' %}\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-categories\">
            <table class=\"tableStatic wide\">
                <tbody>
                    {% for cat_id, cat_title in admin.kb_category_get_pairs %}
                    <tr {% if loop.first %}class=\"noborder\"{% endif %}>
                        <td>{{cat_title}}</td>
                        <td class=\"actions\" style=\"width:13%\">
                            <a class=\"bb-button btn14\" href=\"{{ '/kb/category'|alink }}/{{cat_id}}\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                            <a class=\"bb-button btn14 bb-rm-tr api-link\" data-api-confirm=\"Are you sure?\" href=\"{{ 'api/admin/kb/category_delete'|link({'id' : cat_id}) }}\" data-api-redirect=\"{{'kb/new'|alink}}\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                        </td>
                    </tr>
                    {% else %}
                    <tr>
                        <td colspan=\"3\">{% trans 'The list is empty' %}</td>
                    </tr>
                    {% endfor %}
                </tbody>
            </table>
        </div>

    </div>

    <div class=\"fix\"></div>
</div>

{% endblock %}
", "mod_kb_index.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-modules/Kb/html_admin/mod_kb_index.phtml");
    }
}
