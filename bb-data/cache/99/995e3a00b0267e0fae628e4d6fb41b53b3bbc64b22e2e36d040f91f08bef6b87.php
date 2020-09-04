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

/* mod_support_canned_responses.phtml */
class __TwigTemplate_8342085811a6142e83075c40c09918e10cc460bca2940fac23d646976678c473 extends \Twig\Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->blocks = [
            'breadcrumbs' => [$this, 'block_breadcrumbs'],
            'meta_title' => [$this, 'block_meta_title'],
            'content' => [$this, 'block_content'],
        ];
    }

    protected function doGetParent(array $context)
    {
        // line 1
        return $this->loadTemplate(((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ajax", [], "any", false, false, false, 1)) ? ("layout_blank.phtml") : ("layout_default.phtml")), "mod_support_canned_responses.phtml", 1);
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 2
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_support_canned_responses.phtml", 2)->unwrap();
        // line 12
        $context["active_menu"] = "support";
        // line 1
        $this->getParent($context)->display($context, array_merge($this->blocks, $blocks));
    }

    // line 4
    public function block_breadcrumbs($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 5
        echo "<ul>
    <li class=\"firstB\"><a href=\"";
        // line 6
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/");
        echo "\">";
        echo gettext("Home");
        echo "</a></li>
    <li><a href=\"";
        // line 7
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support");
        echo "\">";
        echo gettext("Support");
        echo "</a></li>
    <li class=\"lastB\">";
        // line 8
        echo gettext("Canned responses");
        echo "</li>
</ul>
";
    }

    // line 13
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo "Canned responses";
    }

    // line 15
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 16
        echo "<div class=\"widget simpleTabs\">

    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">";
        // line 19
        echo gettext("Canned responses");
        echo "</a></li>
        <li><a href=\"#tab-new\">";
        // line 20
        echo gettext("New response");
        echo "</a></li>
        <li><a href=\"#tab-new-category\">";
        // line 21
        echo gettext("New category");
        echo "</a></li>
        <li><a href=\"#tab-categories\">";
        // line 22
        echo gettext("Categories");
        echo "</a></li>
    </ul>

    <div class=\"tabs_container\">

        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-index\">

        ";
        // line 30
        echo twig_call_macro($macros["mf"], "macro_table_search", [], 30, $context, $this->getSourceContext());
        echo "
        <table class=\"tableStatic wide\">
            <thead>
                <tr>
                    <td>";
        // line 34
        echo gettext("Title");
        echo "</td>
                    <td>";
        // line 35
        echo gettext("Category");
        echo "</td>
                    <td style=\"width: 13%\">&nbsp;</td>
                </tr>
            </thead>

            <tbody>
            ";
        // line 41
        $context["responses"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "support_canned_get_list", [0 => twig_array_merge(["per_page" => 90, "page" => twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 41)], ($context["request"] ?? null))], "method", false, false, false, 41);
        // line 42
        echo "            ";
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["responses"] ?? null), "list", [], "any", false, false, false, 42));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["i"] => $context["response"]) {
            // line 43
            echo "            <tr>
                <td>";
            // line 44
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["response"], "title", [], "any", false, false, false, 44), "html", null, true);
            echo "</td>
                <td><a href=\"";
            // line 45
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/support/canned-category");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["response"], "category", [], "any", false, false, false, 45), "id", [], "any", false, false, false, 45), "html", null, true);
            echo "\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["response"], "category", [], "any", false, false, false, 45), "title", [], "any", false, false, false, 45), "html", null, true);
            echo "</a></td>
                <td class=\"actions\">
                    <a class=\"bb-button btn14\" href=\"";
            // line 47
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/support/canned");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["response"], "id", [], "any", false, false, false, 47), "html", null, true);
            echo "\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                    <a class=\"bb-button btn14 bb-rm-tr api-link\" data-api-confirm=\"Are you sure?\" href=\"";
            // line 48
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/support/canned_delete", ["id" => twig_get_attribute($this->env, $this->source, $context["response"], "id", [], "any", false, false, false, 48)]);
            echo "\" data-api-redirect=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support/canned-responses");
            echo "\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                </td>
            </tr>
            ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 52
            echo "            <tr>
                <td colspan=\"3\">
                    ";
            // line 54
            echo gettext("The list is empty");
            // line 55
            echo "                </td>
            </tr>
            ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['i'], $context['response'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 58
        echo "            </tbody>
        </table>
        ";
        // line 60
        $this->loadTemplate("partial_pagination.phtml", "mod_support_canned_responses.phtml", 60)->display(twig_array_merge($context, ["list" => ($context["responses"] ?? null), "url" => "support/canned-responses"]));
        // line 61
        echo "        </div>

        <div class=\"tab_content nopadding\" id=\"tab-new\">

            <form method=\"post\" action=\"";
        // line 65
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/support/canned_create");
        echo "\" class=\"mainForm save api-form\" data-api-redirect=\"";
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support/canned-responses");
        echo "\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>";
        // line 68
        echo gettext("Category");
        echo ":</label>
                        <div class=\"formRight\">
                            ";
        // line 70
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["category_id", twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "support_canned_category_pairs", [], "any", false, false, false, 70), "", 1], 70, $context, $this->getSourceContext());
        echo "
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 75
        echo gettext("Title");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"title\" value=\"";
        // line 77
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "title", [], "any", false, false, false, 77), "html", null, true);
        echo "\" required=\"required\" placeholder=\"\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <div class=\"rowElem\">
                        <label>";
        // line 83
        echo gettext("Content");
        echo "</label>
                        <div class=\"formRight\">
                            <textarea name=\"content\" cols=\"5\" rows=\"10\">";
        // line 85
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "content", [], "any", false, false, false, 85), "html", null, true);
        echo "</textarea>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <input type=\"submit\" value=\"";
        // line 89
        echo gettext("Create");
        echo "\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-new-category\">

            <form method=\"post\" action=\"";
        // line 96
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/support/canned_category_create");
        echo "\" class=\"mainForm save api-form\" data-api-redirect=\"";
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support/canned-responses");
        echo "\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>";
        // line 99
        echo gettext("Title");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"title\" value=\"";
        // line 101
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "title", [], "any", false, false, false, 101), "html", null, true);
        echo "\" required=\"required\" placeholder=\"\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <input type=\"submit\" value=\"";
        // line 105
        echo gettext("Create");
        echo "\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>
        
        <div class=\"tab_content nopadding\" id=\"tab-categories\">
            <table class=\"tableStatic wide\">
                <tbody>
                    ";
        // line 113
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "support_canned_category_pairs", [], "any", false, false, false, 113));
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
            // line 114
            echo "                    <tr ";
            if (twig_get_attribute($this->env, $this->source, $context["loop"], "first", [], "any", false, false, false, 114)) {
                echo "class=\"noborder\"";
            }
            echo ">
                        <td>";
            // line 115
            echo twig_escape_filter($this->env, $context["cat_title"], "html", null, true);
            echo "</td>
                        <td style=\"width:13%\">
                            <a class=\"bb-button btn14\" href=\"";
            // line 117
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/support/canned-category");
            echo "/";
            echo twig_escape_filter($this->env, $context["cat_id"], "html", null, true);
            echo "\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                            <a class=\"bb-button btn14 api-link\" data-api-confirm=\"Are you sure?\" href=\"";
            // line 118
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/support/canned_category_delete", ["id" => $context["cat_id"]]);
            echo "\" data-api-redirect=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support/canned-responses");
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
            // line 122
            echo "                    <tr>
                        <td colspan=\"3\">";
            // line 123
            echo gettext("The list is empty");
            echo "</td>
                    </tr>
                    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['cat_id'], $context['cat_title'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 126
        echo "                </tbody>
            </table>
        </div>

    </div>
</div>

";
    }

    public function getTemplateName()
    {
        return "mod_support_canned_responses.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  359 => 126,  350 => 123,  347 => 122,  328 => 118,  322 => 117,  317 => 115,  310 => 114,  292 => 113,  281 => 105,  274 => 101,  269 => 99,  261 => 96,  251 => 89,  244 => 85,  239 => 83,  230 => 77,  225 => 75,  217 => 70,  212 => 68,  204 => 65,  198 => 61,  196 => 60,  192 => 58,  184 => 55,  182 => 54,  178 => 52,  167 => 48,  161 => 47,  152 => 45,  148 => 44,  145 => 43,  139 => 42,  137 => 41,  128 => 35,  124 => 34,  117 => 30,  106 => 22,  102 => 21,  98 => 20,  94 => 19,  89 => 16,  85 => 15,  78 => 13,  71 => 8,  65 => 7,  59 => 6,  56 => 5,  52 => 4,  48 => 1,  46 => 12,  44 => 2,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}
{% import \"macro_functions.phtml\" as mf %}

{% block breadcrumbs %}
<ul>
    <li class=\"firstB\"><a href=\"{{ '/'|alink }}\">{% trans 'Home' %}</a></li>
    <li><a href=\"{{ 'support'|alink }}\">{% trans 'Support' %}</a></li>
    <li class=\"lastB\">{% trans 'Canned responses' %}</li>
</ul>
{% endblock %}

{% set active_menu = 'support' %}
{% block meta_title %}Canned responses{% endblock %}

{% block content %}
<div class=\"widget simpleTabs\">

    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">{% trans 'Canned responses' %}</a></li>
        <li><a href=\"#tab-new\">{% trans 'New response' %}</a></li>
        <li><a href=\"#tab-new-category\">{% trans 'New category' %}</a></li>
        <li><a href=\"#tab-categories\">{% trans 'Categories' %}</a></li>
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
                    <td style=\"width: 13%\">&nbsp;</td>
                </tr>
            </thead>

            <tbody>
            {% set responses = admin.support_canned_get_list({\"per_page\":90, \"page\":request.page}|merge(request)) %}
            {% for i, response in responses.list %}
            <tr>
                <td>{{ response.title }}</td>
                <td><a href=\"{{ '/support/canned-category'|alink }}/{{response.category.id}}\">{{response.category.title}}</a></td>
                <td class=\"actions\">
                    <a class=\"bb-button btn14\" href=\"{{ '/support/canned'|alink }}/{{response.id}}\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                    <a class=\"bb-button btn14 bb-rm-tr api-link\" data-api-confirm=\"Are you sure?\" href=\"{{ 'api/admin/support/canned_delete'|link({'id' : response.id}) }}\" data-api-redirect=\"{{ 'support/canned-responses'|alink }}\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                </td>
            </tr>
            {% else %}
            <tr>
                <td colspan=\"3\">
                    {% trans 'The list is empty' %}
                </td>
            </tr>
            {% endfor %}
            </tbody>
        </table>
        {% include \"partial_pagination.phtml\" with {'list': responses, 'url':'support/canned-responses'} %}
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-new\">

            <form method=\"post\" action=\"{{ 'api/admin/support/canned_create'|link }}\" class=\"mainForm save api-form\" data-api-redirect=\"{{ 'support/canned-responses'|alink }}\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>{% trans 'Category' %}:</label>
                        <div class=\"formRight\">
                            {{ mf.selectbox('category_id', admin.support_canned_category_pairs, '', 1) }}
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

            <form method=\"post\" action=\"{{ 'api/admin/support/canned_category_create'|link }}\" class=\"mainForm save api-form\" data-api-redirect=\"{{ 'support/canned-responses'|alink }}\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>{% trans 'Title' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"title\" value=\"{{request.title}}\" required=\"required\" placeholder=\"\"/>
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
                    {% for cat_id, cat_title in admin.support_canned_category_pairs %}
                    <tr {% if loop.first %}class=\"noborder\"{% endif %}>
                        <td>{{cat_title}}</td>
                        <td style=\"width:13%\">
                            <a class=\"bb-button btn14\" href=\"{{ '/support/canned-category'|alink }}/{{cat_id}}\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                            <a class=\"bb-button btn14 api-link\" data-api-confirm=\"Are you sure?\" href=\"{{ 'api/admin/support/canned_category_delete'|link({'id' : cat_id}) }}\" data-api-redirect=\"{{ 'support/canned-responses'|alink }}\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
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
</div>

{% endblock %}
", "mod_support_canned_responses.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-themes/admin_default/html/mod_support_canned_responses.phtml");
    }
}
