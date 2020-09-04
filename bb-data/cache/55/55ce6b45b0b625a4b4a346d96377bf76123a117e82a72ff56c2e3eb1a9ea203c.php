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

/* mod_product_index.phtml */
class __TwigTemplate_7502148945cd1712c48b6336c4ee6b0f8f87c13ddafe0160c72295d688562a49 extends \Twig\Template
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
        return "layout_default.phtml";
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 2
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_product_index.phtml", 2)->unwrap();
        // line 3
        $context["active_menu"] = "products";
        // line 1
        $this->parent = $this->loadTemplate("layout_default.phtml", "mod_product_index.phtml", 1);
        $this->parent->display($context, array_merge($this->blocks, $blocks));
    }

    // line 4
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo gettext("Products");
    }

    // line 6
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 7
        echo "
<div class=\"widget simpleTabs\">

    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">";
        // line 11
        echo gettext("Products");
        echo "</a></li>
        <li><a href=\"#tab-new\">";
        // line 12
        echo gettext("New product");
        echo "</a></li>
        <li><a href=\"#tab-new-category\">";
        // line 13
        echo gettext("New category");
        echo "</a></li>
        <li><a href=\"#tab-categories\">";
        // line 14
        echo gettext("Manage categories");
        echo "</a></li>
    </ul>

    <div class=\"tabs_container\">
        <div class=\"fix\"></div>

        <div class=\"tab_content nopadding\" id=\"tab-index\">

            ";
        // line 22
        echo twig_call_macro($macros["mf"], "macro_table_search", [], 22, $context, $this->getSourceContext());
        echo "
            <form method=\"post\" action=\"";
        // line 23
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/product/update_priority");
        echo "\" class=\"mainForm api-form\" data-api-reload=\"1\">
            <fieldset>
            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td width=\"50%\">Title</td>
                        <td>Status</td>
                        <td>Category</td>
                        <td width=\"10%\">Type</td>
                        <td style=\"width: 5%\">";
        // line 32
        echo gettext("Priority");
        echo "</td>
                        <td width=\"13%\">&nbsp;</td>
                    </tr>
                </thead>
                <tbody>
                    ";
        // line 37
        $context["products"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "product_get_list", [0 => twig_array_merge(["per_page" => 30, "page" => twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 37)], ($context["request"] ?? null))], "method", false, false, false, 37);
        // line 38
        echo "                    ";
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["products"] ?? null), "list", [], "any", false, false, false, 38));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["_key"] => $context["product"]) {
            // line 39
            echo "                    <tr>
                        <td><a href=\"";
            // line 40
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/product/manage");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["product"], "id", [], "any", false, false, false, 40), "html", null, true);
            echo "\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["product"], "title", [], "any", false, false, false, 40), "html", null, true);
            echo "</a></td>
                        <td>";
            // line 41
            echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, $context["product"], "status", [], "any", false, false, false, 41)], 41, $context, $this->getSourceContext());
            echo "</td>
                        <td><a href=\"";
            // line 42
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/product/category");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["product"], "category", [], "any", false, false, false, 42), "id", [], "any", false, false, false, 42), "html", null, true);
            echo "\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["product"], "category", [], "any", false, false, false, 42), "title", [], "any", false, false, false, 42), "html", null, true);
            echo "</a></td>
                        <td>";
            // line 43
            echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, $context["product"], "type", [], "any", false, false, false, 43)], 43, $context, $this->getSourceContext());
            echo "</td>
                        <td><input type=\"text\" name=\"priority[";
            // line 44
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["product"], "id", [], "any", false, false, false, 44), "html", null, true);
            echo "]\" value=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["product"], "priority", [], "any", false, false, false, 44), "html", null, true);
            echo "\" style=\"width:30px;\"></td>
                        <td class=\"actions\">
                            <a class=\"bb-button btn14\" href=\"";
            // line 46
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/product/manage");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["product"], "id", [], "any", false, false, false, 46), "html", null, true);
            echo "\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                            <a class=\"bb-button btn14 bb-rm-tr api-link\" data-api-confirm=\"Are you sure?\" href=\"";
            // line 47
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/product/delete", ["id" => twig_get_attribute($this->env, $this->source, $context["product"], "id", [], "any", false, false, false, 47)]);
            echo "\" data-api-redirect=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("product");
            echo "\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                        </td>
                    </tr>
                    ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 51
            echo "                    <tr>
                        <td colspan=\"6\">";
            // line 52
            echo gettext("The list is empty");
            echo "</td>
                    </tr>
                    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['product'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 55
        echo "                </tbody>
            </table>
                
            ";
        // line 58
        $this->loadTemplate("partial_pagination.phtml", "mod_product_index.phtml", 58)->display(twig_array_merge($context, ["list" => ($context["products"] ?? null), "url" => "product"]));
        // line 59
        echo "            
                    <input type=\"submit\" value=\"";
        // line 60
        echo gettext("Update priority");
        echo "\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-new\">
            <form method=\"post\" action=\"";
        // line 66
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/product/prepare");
        echo "\" class=\"mainForm\" id=\"prepare-product\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>";
        // line 69
        echo gettext("Type");
        echo ":</label>
                        <div class=\"formRight\">
                            ";
        // line 71
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["type", twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "product_get_types", [], "any", false, false, false, 71), twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "type", [], "any", false, false, false, 71), 1], 71, $context, $this->getSourceContext());
        echo "
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 76
        echo gettext("Category");
        echo ":</label>
                        <div class=\"formRight\">
                            ";
        // line 78
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["product_category_id", twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "product_category_get_pairs", [], "any", false, false, false, 78), twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "product_category_id", [], "any", false, false, false, 78), 1], 78, $context, $this->getSourceContext());
        echo "
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 83
        echo gettext("Title");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"title\" value=\"";
        // line 85
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "title", [], "any", false, false, false, 85), "html", null, true);
        echo "\" required=\"required\" placeholder=\"\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>


                    <input type=\"submit\" value=\"";
        // line 91
        echo gettext("Create");
        echo "\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>

            <div class=\"fix\"></div>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-new-category\">

            <form method=\"post\" action=\"";
        // line 100
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/product/category_create");
        echo "\" class=\"mainForm save api-form\" data-api-redirect=\"";
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("product");
        echo "\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>";
        // line 103
        echo gettext("Title");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"title\" value=\"";
        // line 105
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "cat_title", [], "any", false, false, false, 105), "html", null, true);
        echo "\" required=\"required\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 111
        echo gettext("Icon URL");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"icon_url\" value=\"\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 119
        echo gettext("Description");
        echo ":</label>
                        <div class=\"formRight\">
                            <textarea name=\"description\" cols=\"5\" rows=\"4\">";
        // line 121
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "cat_description", [], "any", false, false, false, 121), "html", null, true);
        echo "</textarea>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <input type=\"submit\" value=\"";
        // line 126
        echo gettext("Create");
        echo "\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-categories\">
            <table class=\"tableStatic wide\">
                <tbody>
                    ";
        // line 134
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "product_category_get_pairs", [], "any", false, false, false, 134));
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
            // line 135
            echo "                    <tr ";
            if (twig_get_attribute($this->env, $this->source, $context["loop"], "first", [], "any", false, false, false, 135)) {
                echo "class=\"noborder\"";
            }
            echo ">
                        <td>";
            // line 136
            echo twig_escape_filter($this->env, $context["cat_title"], "html", null, true);
            echo "</td>
                        <td class=\"actions\" style=\"width:13%\">
                            <a class=\"bb-button btn14\" href=\"";
            // line 138
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/product/category");
            echo "/";
            echo twig_escape_filter($this->env, $context["cat_id"], "html", null, true);
            echo "\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                            <a class=\"bb-button btn14 bb-rm-tr api-link\" data-api-confirm=\"Are you sure?\" href=\"";
            // line 139
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/product/category_delete", ["id" => $context["cat_id"]]);
            echo "\" data-api-redirect=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("product");
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
            // line 143
            echo "                    <tr>
                        <td colspan=\"3\">";
            // line 144
            echo gettext("The list is empty");
            echo "</td>
                    </tr>
                    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['cat_id'], $context['cat_title'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 147
        echo "                </tbody>
            </table>
        </div>

    </div>

</div>

";
    }

    // line 157
    public function block_js($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 158
        echo "<script type=\"text/javascript\">
\$(function() {

    \$('#prepare-product').bind('submit', function(){
        bb.post(
            \$(this).attr('action'),
            \$(this).serialize(),
            function(result) {
                bb.redirect('";
        // line 166
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("product/manage/");
        echo "/' + result);
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
        return "mod_product_index.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  415 => 166,  405 => 158,  401 => 157,  389 => 147,  380 => 144,  377 => 143,  358 => 139,  352 => 138,  347 => 136,  340 => 135,  322 => 134,  311 => 126,  303 => 121,  298 => 119,  287 => 111,  278 => 105,  273 => 103,  265 => 100,  253 => 91,  244 => 85,  239 => 83,  231 => 78,  226 => 76,  218 => 71,  213 => 69,  207 => 66,  198 => 60,  195 => 59,  193 => 58,  188 => 55,  179 => 52,  176 => 51,  165 => 47,  159 => 46,  152 => 44,  148 => 43,  140 => 42,  136 => 41,  128 => 40,  125 => 39,  119 => 38,  117 => 37,  109 => 32,  97 => 23,  93 => 22,  82 => 14,  78 => 13,  74 => 12,  70 => 11,  64 => 7,  60 => 6,  53 => 4,  48 => 1,  46 => 3,  44 => 2,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends \"layout_default.phtml\" %}
{% import \"macro_functions.phtml\" as mf %}
{% set active_menu = 'products' %}
{% block meta_title %}{% trans 'Products' %}{% endblock %}

{% block content %}

<div class=\"widget simpleTabs\">

    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">{% trans 'Products' %}</a></li>
        <li><a href=\"#tab-new\">{% trans 'New product' %}</a></li>
        <li><a href=\"#tab-new-category\">{% trans 'New category' %}</a></li>
        <li><a href=\"#tab-categories\">{% trans 'Manage categories' %}</a></li>
    </ul>

    <div class=\"tabs_container\">
        <div class=\"fix\"></div>

        <div class=\"tab_content nopadding\" id=\"tab-index\">

            {{ mf.table_search }}
            <form method=\"post\" action=\"{{ 'api/admin/product/update_priority'|link }}\" class=\"mainForm api-form\" data-api-reload=\"1\">
            <fieldset>
            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td width=\"50%\">Title</td>
                        <td>Status</td>
                        <td>Category</td>
                        <td width=\"10%\">Type</td>
                        <td style=\"width: 5%\">{% trans 'Priority' %}</td>
                        <td width=\"13%\">&nbsp;</td>
                    </tr>
                </thead>
                <tbody>
                    {% set products = admin.product_get_list({\"per_page\":30, \"page\":request.page}|merge(request)) %}
                    {% for product in products.list %}
                    <tr>
                        <td><a href=\"{{ '/product/manage'|alink }}/{{product.id}}\">{{ product.title }}</a></td>
                        <td>{{ mf.status_name(product.status) }}</td>
                        <td><a href=\"{{ '/product/category'|alink }}/{{product.category.id}}\">{{product.category.title}}</a></td>
                        <td>{{ mf.status_name(product.type) }}</td>
                        <td><input type=\"text\" name=\"priority[{{product.id}}]\" value=\"{{ product.priority }}\" style=\"width:30px;\"></td>
                        <td class=\"actions\">
                            <a class=\"bb-button btn14\" href=\"{{ '/product/manage'|alink }}/{{product.id}}\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                            <a class=\"bb-button btn14 bb-rm-tr api-link\" data-api-confirm=\"Are you sure?\" href=\"{{ 'api/admin/product/delete'|link({'id' : product.id}) }}\" data-api-redirect=\"{{ 'product'|alink }}\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                        </td>
                    </tr>
                    {% else %}
                    <tr>
                        <td colspan=\"6\">{% trans 'The list is empty' %}</td>
                    </tr>
                    {% endfor %}
                </tbody>
            </table>
                
            {% include \"partial_pagination.phtml\" with {'list': products, 'url':'product'} %}
            
                    <input type=\"submit\" value=\"{% trans 'Update priority' %}\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-new\">
            <form method=\"post\" action=\"{{ 'api/admin/product/prepare'|link }}\" class=\"mainForm\" id=\"prepare-product\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>{% trans 'Type' %}:</label>
                        <div class=\"formRight\">
                            {{ mf.selectbox('type', admin.product_get_types, request.type, 1) }}
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Category' %}:</label>
                        <div class=\"formRight\">
                            {{ mf.selectbox('product_category_id', admin.product_category_get_pairs, request.product_category_id, 1) }}
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


                    <input type=\"submit\" value=\"{% trans 'Create' %}\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>

            <div class=\"fix\"></div>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-new-category\">

            <form method=\"post\" action=\"{{ 'api/admin/product/category_create'|link }}\" class=\"mainForm save api-form\" data-api-redirect=\"{{ 'product'|alink }}\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>{% trans 'Title' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"title\" value=\"{{request.cat_title}}\" required=\"required\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Icon URL' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"icon_url\" value=\"\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Description' %}:</label>
                        <div class=\"formRight\">
                            <textarea name=\"description\" cols=\"5\" rows=\"4\">{{request.cat_description}}</textarea>
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
                    {% for cat_id, cat_title in admin.product_category_get_pairs %}
                    <tr {% if loop.first %}class=\"noborder\"{% endif %}>
                        <td>{{cat_title}}</td>
                        <td class=\"actions\" style=\"width:13%\">
                            <a class=\"bb-button btn14\" href=\"{{ '/product/category'|alink }}/{{cat_id}}\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                            <a class=\"bb-button btn14 bb-rm-tr api-link\" data-api-confirm=\"Are you sure?\" href=\"{{ 'api/admin/product/category_delete'|link({'id' : cat_id}) }}\" data-api-redirect=\"{{ 'product'|alink }}\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
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

{% block js %}
<script type=\"text/javascript\">
\$(function() {

    \$('#prepare-product').bind('submit', function(){
        bb.post(
            \$(this).attr('action'),
            \$(this).serialize(),
            function(result) {
                bb.redirect('{{\"product/manage/\"|alink}}/' + result);
            }
        );
        return false;
    });
});
</script>
{% endblock %}", "mod_product_index.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-themes/admin_default/html/mod_product_index.phtml");
    }
}
