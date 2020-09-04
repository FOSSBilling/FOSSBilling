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

/* mod_product_addons.phtml */
class __TwigTemplate_a22c3c12a9a9b3cdff9a37374b16e1bca043f2a4405c408cb874533c22437fe0 extends \Twig\Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->blocks = [
            'meta_title' => [$this, 'block_meta_title'],
            'breadcrumbs' => [$this, 'block_breadcrumbs'],
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
        $context["active_menu"] = "products";
        // line 1
        $this->parent = $this->loadTemplate("layout_default.phtml", "mod_product_addons.phtml", 1);
        $this->parent->display($context, array_merge($this->blocks, $blocks));
    }

    // line 3
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo gettext("Addons");
    }

    // line 5
    public function block_breadcrumbs($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 6
        echo "<ul>
    <li class=\"firstB\"><a href=\"";
        // line 7
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/");
        echo "\">";
        echo gettext("Home");
        echo "</a></li>
    <li><a href=\"";
        // line 8
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("product");
        echo "\">";
        echo gettext("Products");
        echo "</a></li>
    <li class=\"lastB\">";
        // line 9
        echo gettext("Addons");
        echo "</li>
</ul>
";
    }

    // line 13
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 14
        echo "
<div class=\"widget simpleTabs\">

    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">";
        // line 18
        echo gettext("Addons");
        echo "</a></li>
        <li><a href=\"#tab-new\">";
        // line 19
        echo gettext("New Addon");
        echo "</a></li>
    </ul>

    <div class=\"tabs_container\">

        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-index\">

            <table class=\"tableStatic wide\">
                <tbody>
                    ";
        // line 29
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "product_addon_get_pairs", [], "any", false, false, false, 29));
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
        foreach ($context['_seq'] as $context["addon_id"] => $context["addon_title"]) {
            // line 30
            echo "                    <tr ";
            if (twig_get_attribute($this->env, $this->source, $context["loop"], "first", [], "any", false, false, false, 30)) {
                echo "class=\"noborder\"";
            }
            echo ">
                        <td><label for=\"addon_";
            // line 31
            echo twig_escape_filter($this->env, $context["addon_id"], "html", null, true);
            echo "\">";
            echo twig_escape_filter($this->env, $context["addon_title"], "html", null, true);
            echo "</label></td>
                        <td class=\"actions\" style=\"width:13%\">
                            <a class=\"bb-button btn14\" href=\"";
            // line 33
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/product/addon");
            echo "/";
            echo twig_escape_filter($this->env, $context["addon_id"], "html", null, true);
            echo "\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                            <a class=\"bb-button btn14 bb-rm-tr api-link\" data-api-confirm=\"Are you sure?\" data-api-redirect=\"";
            // line 34
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("product/addons");
            echo "\" href=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/product/delete", ["id" => $context["addon_id"]]);
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
            // line 38
            echo "                    <tr>
                        <td colspan=\"2\">";
            // line 39
            echo gettext("The list is empty");
            echo "</td>
                    </tr>
                    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['addon_id'], $context['addon_title'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 42
        echo "                </tbody>
            </table>
            <div class=\"fix\"></div>
        </div>
        
        <div class=\"fix\"></div>

        <div class=\"tab_content nopadding\" id=\"tab-new\">
            
            <form method=\"post\" action=\"";
        // line 51
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/product/addon_create");
        echo "\" class=\"mainForm api-form save\" data-api-jsonp=\"onAfterAddonCreate\">
            <fieldset>
                <div class=\"rowElem\">
                    <label>";
        // line 54
        echo gettext("Status");
        echo ":</label>
                    <div class=\"formRight\">
                        <input type=\"radio\" name=\"status\" value=\"enabled\"/><label>";
        // line 56
        echo gettext("Enabled");
        echo "</label>
                        <input type=\"radio\" name=\"status\" value=\"disabled\" checked=\"checked\"/><label>";
        // line 57
        echo gettext("Disabled");
        echo "</label>
                    </div>
                    <div class=\"fix\"></div>
                </div>
                <div class=\"rowElem\">
                    <label>";
        // line 62
        echo gettext("Activation");
        echo ":</label>
                    <div class=\"formRight\">
                        <input type=\"radio\" name=\"setup\" value=\"after_order\"/><label>";
        // line 64
        echo gettext("After order is placed");
        echo "</label>
                        <input type=\"radio\" name=\"setup\" value=\"after_payment\" checked=\"checked\"/><label>";
        // line 65
        echo gettext("After payment is received");
        echo "</label>
                        <input type=\"radio\" name=\"setup\" value=\"manual\"/><label>";
        // line 66
        echo gettext("Manual activation");
        echo "</label>
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>";
        // line 72
        echo gettext("Icon");
        echo ":</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"icon_url\" value=\"";
        // line 74
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "icon_url", [], "any", false, false, false, 74), "html", null, true);
        echo "\" placeholder=\"\"/>
                    </div>
                    <div class=\"fix\"></div>
                </div>
                <div class=\"rowElem\">
                    <label>";
        // line 79
        echo gettext("Title");
        echo ":</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"title\" value=\"";
        // line 81
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "title", [], "any", false, false, false, 81), "html", null, true);
        echo "\" required=\"required\" placeholder=\"\"/>
                    </div>
                    <div class=\"fix\"></div>
                </div>
                <div class=\"rowElem\">
                    <label>";
        // line 86
        echo gettext("Description");
        echo ":</label>
                    <div class=\"formRight\">
                        <textarea name=\"description\" cols=\"5\" rows=\"5\">";
        // line 88
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "description", [], "any", false, false, false, 88), "html", null, true);
        echo "</textarea>
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <input type=\"submit\" value=\"";
        // line 93
        echo gettext("Create");
        echo "\" class=\"greyishBtn submitForm\" />
            </fieldset>
            </form>

        </div>

    </div>
</div>

";
    }

    // line 104
    public function block_js($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 105
        echo "<script type=\"text/javascript\">
    
    function onAfterAddonCreate(result)
    {
        bb.redirect(\"";
        // line 109
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("product/addon/");
        echo "/\" + result);
    }
    
</script>
";
    }

    public function getTemplateName()
    {
        return "mod_product_addons.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  300 => 109,  294 => 105,  290 => 104,  276 => 93,  268 => 88,  263 => 86,  255 => 81,  250 => 79,  242 => 74,  237 => 72,  228 => 66,  224 => 65,  220 => 64,  215 => 62,  207 => 57,  203 => 56,  198 => 54,  192 => 51,  181 => 42,  172 => 39,  169 => 38,  150 => 34,  144 => 33,  137 => 31,  130 => 30,  112 => 29,  99 => 19,  95 => 18,  89 => 14,  85 => 13,  78 => 9,  72 => 8,  66 => 7,  63 => 6,  59 => 5,  52 => 3,  47 => 1,  45 => 2,  38 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends \"layout_default.phtml\" %}
{% set active_menu = 'products' %}
{% block meta_title %}{% trans 'Addons' %}{% endblock %}

{% block breadcrumbs %}
<ul>
    <li class=\"firstB\"><a href=\"{{ '/'|alink }}\">{% trans 'Home' %}</a></li>
    <li><a href=\"{{ 'product'|alink }}\">{% trans 'Products' %}</a></li>
    <li class=\"lastB\">{% trans 'Addons' %}</li>
</ul>
{% endblock %}

{% block content %}

<div class=\"widget simpleTabs\">

    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">{% trans 'Addons' %}</a></li>
        <li><a href=\"#tab-new\">{% trans 'New Addon' %}</a></li>
    </ul>

    <div class=\"tabs_container\">

        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-index\">

            <table class=\"tableStatic wide\">
                <tbody>
                    {% for addon_id, addon_title in admin.product_addon_get_pairs %}
                    <tr {% if loop.first %}class=\"noborder\"{% endif %}>
                        <td><label for=\"addon_{{ addon_id }}\">{{addon_title}}</label></td>
                        <td class=\"actions\" style=\"width:13%\">
                            <a class=\"bb-button btn14\" href=\"{{ '/product/addon'|alink }}/{{addon_id}}\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                            <a class=\"bb-button btn14 bb-rm-tr api-link\" data-api-confirm=\"Are you sure?\" data-api-redirect=\"{{ 'product/addons'|alink }}\" href=\"{{ 'api/admin/product/delete'|link({'id' : addon_id}) }}\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                        </td>
                    </tr>
                    {% else %}
                    <tr>
                        <td colspan=\"2\">{% trans 'The list is empty' %}</td>
                    </tr>
                    {% endfor %}
                </tbody>
            </table>
            <div class=\"fix\"></div>
        </div>
        
        <div class=\"fix\"></div>

        <div class=\"tab_content nopadding\" id=\"tab-new\">
            
            <form method=\"post\" action=\"{{ 'api/admin/product/addon_create'|link }}\" class=\"mainForm api-form save\" data-api-jsonp=\"onAfterAddonCreate\">
            <fieldset>
                <div class=\"rowElem\">
                    <label>{% trans 'Status' %}:</label>
                    <div class=\"formRight\">
                        <input type=\"radio\" name=\"status\" value=\"enabled\"/><label>{% trans 'Enabled' %}</label>
                        <input type=\"radio\" name=\"status\" value=\"disabled\" checked=\"checked\"/><label>{% trans 'Disabled' %}</label>
                    </div>
                    <div class=\"fix\"></div>
                </div>
                <div class=\"rowElem\">
                    <label>{% trans 'Activation' %}:</label>
                    <div class=\"formRight\">
                        <input type=\"radio\" name=\"setup\" value=\"after_order\"/><label>{% trans 'After order is placed' %}</label>
                        <input type=\"radio\" name=\"setup\" value=\"after_payment\" checked=\"checked\"/><label>{% trans 'After payment is received' %}</label>
                        <input type=\"radio\" name=\"setup\" value=\"manual\"/><label>{% trans 'Manual activation' %}</label>
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>{% trans 'Icon' %}:</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"icon_url\" value=\"{{product.icon_url}}\" placeholder=\"\"/>
                    </div>
                    <div class=\"fix\"></div>
                </div>
                <div class=\"rowElem\">
                    <label>{% trans 'Title' %}:</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"title\" value=\"{{product.title}}\" required=\"required\" placeholder=\"\"/>
                    </div>
                    <div class=\"fix\"></div>
                </div>
                <div class=\"rowElem\">
                    <label>{% trans 'Description' %}:</label>
                    <div class=\"formRight\">
                        <textarea name=\"description\" cols=\"5\" rows=\"5\">{{product.description}}</textarea>
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

{% block js %}
<script type=\"text/javascript\">
    
    function onAfterAddonCreate(result)
    {
        bb.redirect(\"{{ 'product/addon/'|alink }}/\" + result);
    }
    
</script>
{% endblock %}
", "mod_product_addons.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-themes/admin_default/html/mod_product_addons.phtml");
    }
}
