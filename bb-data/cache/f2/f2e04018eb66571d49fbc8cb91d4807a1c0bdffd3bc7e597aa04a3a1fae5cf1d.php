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

/* mod_product_promos.phtml */
class __TwigTemplate_c37db84aa37d65b6450c321d721d1476a197eb144d9f43498baf5e69dc2ebb78 extends \Twig\Template
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
        ];
    }

    protected function doGetParent(array $context)
    {
        // line 2
        return "layout_default.phtml";
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 1
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_product_promos.phtml", 1)->unwrap();
        // line 4
        $context["active_menu"] = "products";
        // line 2
        $this->parent = $this->loadTemplate("layout_default.phtml", "mod_product_promos.phtml", 2);
        $this->parent->display($context, array_merge($this->blocks, $blocks));
    }

    // line 3
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo gettext("Product promotions");
    }

    // line 6
    public function block_breadcrumbs($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 7
        echo "<ul>
    <li class=\"firstB\"><a href=\"";
        // line 8
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/");
        echo "\">";
        echo gettext("Home");
        echo "</a></li>
    <li><a href=\"";
        // line 9
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("product");
        echo "\">";
        echo gettext("Products");
        echo "</a></li>
    <li class=\"lastB\">";
        // line 10
        echo gettext("Product promotions");
        echo "</li>
</ul>
";
    }

    // line 14
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 15
        echo "<div class=\"widget simpleTabs\">

    <ul class=\"tabs\">
        <li><a href=\"#tab-promos\">";
        // line 18
        echo gettext("Promo");
        echo "</a></li>
        <li><a href=\"#tab-new\">";
        // line 19
        echo gettext("New promo");
        echo "</a></li>
    </ul>

    <div class=\"tabs_container\">

        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-promos\">

            ";
        // line 27
        echo twig_call_macro($macros["mf"], "macro_table_search", [], 27, $context, $this->getSourceContext());
        echo "
            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td>";
        // line 31
        echo gettext("Code");
        echo "</td>
                        <td>";
        // line 32
        echo gettext("Discount");
        echo "</td>
                        <td>";
        // line 33
        echo gettext("Applies to");
        echo "</td>
                        <td>";
        // line 34
        echo gettext("Client groups");
        echo "</td>
                        <td>";
        // line 35
        echo gettext("Valid period");
        echo "</td>
                        <td>";
        // line 36
        echo gettext("Enabled");
        echo "</td>
                        <td>";
        // line 37
        echo gettext("Usage");
        echo "</td>
                        <td style=\"width: 13%\">&nbsp;</td>
                    </tr>
                </thead>

                <tbody>
                ";
        // line 43
        $context["promos"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "product_promo_get_list", [0 => twig_array_merge(["per_page" => 30, "page" => twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 43)], ($context["request"] ?? null))], "method", false, false, false, 43);
        // line 44
        echo "                ";
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["promos"] ?? null), "list", [], "any", false, false, false, 44));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["_key"] => $context["promo"]) {
            // line 45
            echo "                <tr>
                    <td><strong>";
            // line 46
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["promo"], "code", [], "any", false, false, false, 46), "html", null, true);
            echo "</strong></td>
                    <td>";
            // line 47
            if ((twig_get_attribute($this->env, $this->source, $context["promo"], "type", [], "any", false, false, false, 47) == "percentage")) {
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["promo"], "value", [], "any", false, false, false, 47), "html", null, true);
                echo "%";
            }
            if ((twig_get_attribute($this->env, $this->source, $context["promo"], "type", [], "any", false, false, false, 47) == "absolute")) {
                echo twig_call_macro($macros["mf"], "macro_currency_format", [twig_get_attribute($this->env, $this->source, $context["promo"], "value", [], "any", false, false, false, 47)], 47, $context, $this->getSourceContext());
            }
            echo "</td>
                    <td>";
            // line 48
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, $context["promo"], "applies_to", [], "any", false, false, false, 48));
            $context['_iterated'] = false;
            foreach ($context['_seq'] as $context["pid"] => $context["product"]) {
                // line 49
                echo "                        <a href=\"";
                echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("product/manage");
                echo "/";
                echo twig_escape_filter($this->env, $context["pid"], "html", null, true);
                echo "\">";
                echo twig_escape_filter($this->env, twig_truncate_filter($this->env, $context["product"], 15), "html", null, true);
                echo "</a><br/>
                        ";
                $context['_iterated'] = true;
            }
            if (!$context['_iterated']) {
                // line 51
                echo "                        All products
                        ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['pid'], $context['product'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 53
            echo "                    </td>
                    <td>";
            // line 54
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, $context["promo"], "cgroups", [], "any", false, false, false, 54));
            $context['_iterated'] = false;
            foreach ($context['_seq'] as $context["cid"] => $context["client_group"]) {
                // line 55
                echo "                        <a href=\"";
                echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("client/group");
                echo "/";
                echo twig_escape_filter($this->env, $context["cid"], "html", null, true);
                echo "\">";
                echo twig_escape_filter($this->env, twig_truncate_filter($this->env, $context["client_group"], 15), "html", null, true);
                echo "</a><br/>
                        ";
                $context['_iterated'] = true;
            }
            if (!$context['_iterated']) {
                // line 57
                echo "                        All client groups
                        ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['cid'], $context['client_group'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 59
            echo "                    </td>
                    <td>
                        From ";
            // line 61
            if (twig_get_attribute($this->env, $this->source, $context["promo"], "start_at", [], "any", false, false, false, 61)) {
                echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, $context["promo"], "start_at", [], "any", false, false, false, 61), "Y-m-d"), "html", null, true);
            } else {
                echo "now";
            }
            // line 62
            echo "                        untill ";
            if (twig_get_attribute($this->env, $this->source, $context["promo"], "end_at", [], "any", false, false, false, 62)) {
                echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, $context["promo"], "end_at", [], "any", false, false, false, 62), "Y-m-d"), "html", null, true);
            } else {
                echo "disabled";
            }
            // line 63
            echo "                    </td>
                    <td>";
            // line 64
            echo twig_call_macro($macros["mf"], "macro_q", [twig_get_attribute($this->env, $this->source, $context["promo"], "active", [], "any", false, false, false, 64)], 64, $context, $this->getSourceContext());
            echo "</td>
                    <td>";
            // line 65
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["promo"], "used", [], "any", false, false, false, 65), "html", null, true);
            echo " / ";
            if (twig_get_attribute($this->env, $this->source, $context["promo"], "maxuses", [], "any", false, false, false, 65)) {
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["promo"], "maxuses", [], "any", false, false, false, 65), "html", null, true);
            } else {
                echo "&#8734;";
            }
            echo "</td>
                    <td class=\"actions\">
                        <a class=\"bb-button btn14\" href=\"";
            // line 67
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/product/promo");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["promo"], "id", [], "any", false, false, false, 67), "html", null, true);
            echo "\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                        <a class=\"bb-button btn14 bb-rm-tr api-link\" data-api-confirm=\"Are you sure?\" href=\"";
            // line 68
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/product/promo_delete", ["id" => twig_get_attribute($this->env, $this->source, $context["promo"], "id", [], "any", false, false, false, 68)]);
            echo "\" data-api-redirect=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("product/promos");
            echo "\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                    </td>
                </tr>
                ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 72
            echo "                    <tr>
                        <td colspan=\"7\">
                            ";
            // line 74
            echo gettext("The list is empty");
            // line 75
            echo "                        </td>
                    </tr>
                ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['promo'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 78
        echo "                </tbody>
            </table>
            
            ";
        // line 81
        $this->loadTemplate("partial_pagination.phtml", "mod_product_promos.phtml", 81)->display(twig_array_merge($context, ["list" => ($context["promos"] ?? null), "url" => "product/promos"]));
        // line 82
        echo "        </div>

        <div class=\"tab_content nopadding\" id=\"tab-new\">
            <div class=\"help\">
                <h3>";
        // line 86
        echo gettext("Create new coupon code");
        echo "</h3>
                <p>";
        // line 87
        echo gettext("Create special offers for your clients by creating coupon codes.");
        echo "</p>
            </div>
            
            <form method=\"post\" action=\"";
        // line 90
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/product/promo_create");
        echo "\" class=\"mainForm save api-form\" data-api-redirect=\"";
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("product/promos");
        echo "\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>";
        // line 93
        echo gettext("Code");
        echo "</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"code\" value=\"\" required=\"required\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 100
        echo gettext("Discount type");
        echo "</label>
                        <div class=\"formRight moreFields\">
                            <ul>
                                <li style=\"width: 270px\">
                                    <input type=\"radio\" name=\"type\" value=\"absolute\" checked=\"checked\"/><label>";
        // line 104
        echo gettext("Absolute");
        echo "</label>
                                    <input type=\"radio\" name=\"type\" value=\"percentage\"/><label>";
        // line 105
        echo gettext("Percentage");
        echo "</label>
                                </li>
                                <li style=\"width: 100px\"><input type=\"text\" name=\"value\" value=\"\" required=\"required\" placeholder=\"0\"/></li>
                            </ul>
                        </div> 
                        <div class=\"fix\"></div>
                    </div>
                    
                    <div class=\"rowElem\">
                        <label>";
        // line 114
        echo gettext("Recurring");
        echo "</label>
                        <div class=\"formRight moreFields\">
                            <input type=\"radio\" name=\"recurring\" value=\"1\" checked=\"checked\"/><label>Applies to first order and renewals</label>
                            <input type=\"radio\" name=\"recurring\" value=\"0\"/><label>Applies to first order only</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <div class=\"rowElem\">
                        <label>";
        // line 123
        echo gettext("Active");
        echo "</label>
                        <div class=\"formRight\">
                            <input type=\"radio\" name=\"active\" value=\"1\" checked=\"checked\"/><label>";
        // line 125
        echo gettext("Yes");
        echo "</label>
                            <input type=\"radio\" name=\"active\" value=\"0\"/><label>";
        // line 126
        echo gettext("No");
        echo "</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 131
        echo gettext("Free setup");
        echo "</label>
                        <div class=\"formRight\">
                            <input type=\"radio\" name=\"freesetup\" value=\"1\"/><label>";
        // line 133
        echo gettext("Yes");
        echo "</label>
                            <input type=\"radio\" name=\"freesetup\" value=\"0\" checked=\"checked\"/><label>";
        // line 134
        echo gettext("No");
        echo "</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>";
        // line 141
        echo gettext("Promo code limitations");
        echo "</legend>
                    
                    <div class=\"rowElem\">
                        <label>";
        // line 144
        echo gettext("Once per client");
        echo "</label>
                        <div class=\"formRight\">
                            <input type=\"radio\" name=\"once_per_client\" value=\"1\" checked=\"checked\"/><label>Yes</label>
                            <input type=\"radio\" name=\"once_per_client\" value=\"0\"/><label>No</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <div class=\"rowElem\">
                        <label>";
        // line 153
        echo gettext("Max uses (zero for unlimited)");
        echo "</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"maxuses\" value=\"\" class=\"dirRight\" title=\"Leave blank for unlimited uses\"  placeholder=\"0\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 160
        echo gettext("Valid time (Leave blank for undefined time)");
        echo "</label>
                        <div class=\"formRight moreFields\">
                            <ul>
                                <li style=\"width: 100px\"><input type=\"text\" name=\"start_at\" value=\"\" placeholder=\"";
        // line 163
        echo twig_escape_filter($this->env, twig_date_format_filter($this->env, ($context["now"] ?? null), "Y-m-d"), "html", null, true);
        echo "\" class=\"datepicker\"/></li>
                                <li class=\"sep\">-</li>
                                <li style=\"width: 100px\"><input type=\"text\" name=\"end_at\" value=\"\" placeholder=\"";
        // line 165
        echo twig_escape_filter($this->env, twig_date_format_filter($this->env, ($context["now"] ?? null), "Y-m-d"), "html", null, true);
        echo "\" class=\"datepicker\"/></li>
                            </ul>
                        </div> 
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 171
        echo gettext("Products (Select none to apply to all products)");
        echo "</label>
                        <div class=\"formRight\">
                            ";
        // line 173
        $context["products"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "product_get_pairs", [], "any", false, false, false, 173);
        // line 174
        echo "                            <select name=\"products[]\" multiple=\"multiple\" class=\"multiple\" size=\"";
        echo twig_escape_filter($this->env, twig_length_filter($this->env, ($context["products"] ?? null)), "html", null, true);
        echo "\">
                                ";
        // line 175
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(($context["products"] ?? null));
        foreach ($context['_seq'] as $context["id"] => $context["product"]) {
            // line 176
            echo "                                <option value=\"";
            echo twig_escape_filter($this->env, $context["id"], "html", null, true);
            echo "\">";
            echo twig_escape_filter($this->env, $context["product"], "html", null, true);
            echo "</option>
                                ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['id'], $context['product'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 178
        echo "                            </select>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 183
        echo gettext("Periods (Select none to apply to all periods)");
        echo "</label>
                        <div class=\"formRight\">
                            ";
        // line 185
        $context["periods"] = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_periods", [], "any", false, false, false, 185);
        // line 186
        echo "                            <select name=\"periods[]\" multiple=\"multiple\" class=\"multiple\" size=\"";
        echo twig_escape_filter($this->env, twig_length_filter($this->env, ($context["periods"] ?? null)), "html", null, true);
        echo "\">
                                ";
        // line 187
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(($context["periods"] ?? null));
        foreach ($context['_seq'] as $context["id"] => $context["period"]) {
            // line 188
            echo "                                <option value=\"";
            echo twig_escape_filter($this->env, $context["id"], "html", null, true);
            echo "\">";
            echo twig_escape_filter($this->env, $context["period"], "html", null, true);
            echo "</option>
                                ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['id'], $context['period'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 190
        echo "                            </select>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 195
        echo gettext("Client Groups (Select none to apply to all client groups)");
        echo "</label>
                        <div class=\"formRight\">
                            ";
        // line 197
        $context["client_groups"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "client_group_get_pairs", [], "any", false, false, false, 197);
        // line 198
        echo "                            <select name=\"client_groups[]\" multiple=\"multiple\" class=\"multiple\" size=\"";
        echo twig_escape_filter($this->env, twig_length_filter($this->env, ($context["groups"] ?? null)), "html", null, true);
        echo "\">
                                ";
        // line 199
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(($context["client_groups"] ?? null));
        foreach ($context['_seq'] as $context["id"] => $context["client_group"]) {
            // line 200
            echo "                                <option value=\"";
            echo twig_escape_filter($this->env, $context["id"], "html", null, true);
            echo "\">";
            echo twig_escape_filter($this->env, $context["client_group"], "html", null, true);
            echo "</option>
                                ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['id'], $context['client_group'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 202
        echo "                            </select>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    ";
        // line 220
        echo "                    <input type=\"submit\" value=\"";
        echo gettext("Create");
        echo "\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>
        
    </div>
</div>

";
    }

    public function getTemplateName()
    {
        return "mod_product_promos.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  553 => 220,  547 => 202,  536 => 200,  532 => 199,  527 => 198,  525 => 197,  520 => 195,  513 => 190,  502 => 188,  498 => 187,  493 => 186,  491 => 185,  486 => 183,  479 => 178,  468 => 176,  464 => 175,  459 => 174,  457 => 173,  452 => 171,  443 => 165,  438 => 163,  432 => 160,  422 => 153,  410 => 144,  404 => 141,  394 => 134,  390 => 133,  385 => 131,  377 => 126,  373 => 125,  368 => 123,  356 => 114,  344 => 105,  340 => 104,  333 => 100,  323 => 93,  315 => 90,  309 => 87,  305 => 86,  299 => 82,  297 => 81,  292 => 78,  284 => 75,  282 => 74,  278 => 72,  267 => 68,  261 => 67,  250 => 65,  246 => 64,  243 => 63,  236 => 62,  230 => 61,  226 => 59,  219 => 57,  207 => 55,  202 => 54,  199 => 53,  192 => 51,  180 => 49,  175 => 48,  165 => 47,  161 => 46,  158 => 45,  152 => 44,  150 => 43,  141 => 37,  137 => 36,  133 => 35,  129 => 34,  125 => 33,  121 => 32,  117 => 31,  110 => 27,  99 => 19,  95 => 18,  90 => 15,  86 => 14,  79 => 10,  73 => 9,  67 => 8,  64 => 7,  60 => 6,  53 => 3,  48 => 2,  46 => 4,  44 => 1,  37 => 2,);
    }

    public function getSourceContext()
    {
        return new Source("{% import \"macro_functions.phtml\" as mf %}
{% extends \"layout_default.phtml\" %}
{% block meta_title %}{% trans 'Product promotions' %}{% endblock %}
{% set active_menu = 'products' %}

{% block breadcrumbs %}
<ul>
    <li class=\"firstB\"><a href=\"{{ '/'|alink }}\">{% trans 'Home' %}</a></li>
    <li><a href=\"{{ 'product'|alink }}\">{% trans 'Products' %}</a></li>
    <li class=\"lastB\">{% trans 'Product promotions' %}</li>
</ul>
{% endblock %}

{% block content %}
<div class=\"widget simpleTabs\">

    <ul class=\"tabs\">
        <li><a href=\"#tab-promos\">{% trans 'Promo' %}</a></li>
        <li><a href=\"#tab-new\">{% trans 'New promo' %}</a></li>
    </ul>

    <div class=\"tabs_container\">

        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-promos\">

            {{ mf.table_search }}
            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td>{% trans 'Code' %}</td>
                        <td>{% trans 'Discount' %}</td>
                        <td>{% trans 'Applies to' %}</td>
                        <td>{% trans 'Client groups' %}</td>
                        <td>{% trans 'Valid period' %}</td>
                        <td>{% trans 'Enabled' %}</td>
                        <td>{% trans 'Usage' %}</td>
                        <td style=\"width: 13%\">&nbsp;</td>
                    </tr>
                </thead>

                <tbody>
                {% set promos = admin.product_promo_get_list({\"per_page\":30, \"page\":request.page}|merge(request)) %}
                {% for promo in promos.list %}
                <tr>
                    <td><strong>{{ promo.code }}</strong></td>
                    <td>{% if promo.type == 'percentage' %}{{ promo.value }}%{% endif %}{% if promo.type == 'absolute' %}{{ mf.currency_format(promo.value) }}{% endif %}</td>
                    <td>{% for pid,product in promo.applies_to %}
                        <a href=\"{{ 'product/manage'|alink }}/{{pid}}\">{{ product|truncate(15) }}</a><br/>
                        {% else %}
                        All products
                        {% endfor %}
                    </td>
                    <td>{% for cid,client_group in promo.cgroups %}
                        <a href=\"{{ 'client/group'|alink }}/{{cid}}\">{{ client_group|truncate(15) }}</a><br/>
                        {% else %}
                        All client groups
                        {% endfor %}
                    </td>
                    <td>
                        From {% if promo.start_at %}{{ promo.start_at|date('Y-m-d') }}{% else %}now{% endif %}
                        untill {% if promo.end_at %}{{ promo.end_at|date('Y-m-d') }}{% else %}disabled{% endif %}
                    </td>
                    <td>{{ mf.q(promo.active) }}</td>
                    <td>{{ promo.used }} / {% if promo.maxuses %}{{ promo.maxuses }}{% else %}&#8734;{% endif %}</td>
                    <td class=\"actions\">
                        <a class=\"bb-button btn14\" href=\"{{ '/product/promo'|alink }}/{{promo.id}}\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                        <a class=\"bb-button btn14 bb-rm-tr api-link\" data-api-confirm=\"Are you sure?\" href=\"{{ 'api/admin/product/promo_delete'|link({'id' : promo.id}) }}\" data-api-redirect=\"{{ 'product/promos'|alink }}\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                    </td>
                </tr>
                {% else %}
                    <tr>
                        <td colspan=\"7\">
                            {% trans 'The list is empty' %}
                        </td>
                    </tr>
                {% endfor %}
                </tbody>
            </table>
            
            {% include \"partial_pagination.phtml\" with {'list': promos, 'url':'product/promos'} %}
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-new\">
            <div class=\"help\">
                <h3>{% trans 'Create new coupon code' %}</h3>
                <p>{% trans %}Create special offers for your clients by creating coupon codes.{% endtrans %}</p>
            </div>
            
            <form method=\"post\" action=\"{{ 'api/admin/product/promo_create'|link }}\" class=\"mainForm save api-form\" data-api-redirect=\"{{ 'product/promos'|alink }}\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>{% trans 'Code' %}</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"code\" value=\"\" required=\"required\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Discount type' %}</label>
                        <div class=\"formRight moreFields\">
                            <ul>
                                <li style=\"width: 270px\">
                                    <input type=\"radio\" name=\"type\" value=\"absolute\" checked=\"checked\"/><label>{% trans 'Absolute' %}</label>
                                    <input type=\"radio\" name=\"type\" value=\"percentage\"/><label>{% trans 'Percentage' %}</label>
                                </li>
                                <li style=\"width: 100px\"><input type=\"text\" name=\"value\" value=\"\" required=\"required\" placeholder=\"0\"/></li>
                            </ul>
                        </div> 
                        <div class=\"fix\"></div>
                    </div>
                    
                    <div class=\"rowElem\">
                        <label>{% trans 'Recurring' %}</label>
                        <div class=\"formRight moreFields\">
                            <input type=\"radio\" name=\"recurring\" value=\"1\" checked=\"checked\"/><label>Applies to first order and renewals</label>
                            <input type=\"radio\" name=\"recurring\" value=\"0\"/><label>Applies to first order only</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <div class=\"rowElem\">
                        <label>{% trans 'Active' %}</label>
                        <div class=\"formRight\">
                            <input type=\"radio\" name=\"active\" value=\"1\" checked=\"checked\"/><label>{% trans 'Yes' %}</label>
                            <input type=\"radio\" name=\"active\" value=\"0\"/><label>{% trans 'No' %}</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Free setup' %}</label>
                        <div class=\"formRight\">
                            <input type=\"radio\" name=\"freesetup\" value=\"1\"/><label>{% trans 'Yes' %}</label>
                            <input type=\"radio\" name=\"freesetup\" value=\"0\" checked=\"checked\"/><label>{% trans 'No' %}</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>{% trans 'Promo code limitations' %}</legend>
                    
                    <div class=\"rowElem\">
                        <label>{% trans 'Once per client' %}</label>
                        <div class=\"formRight\">
                            <input type=\"radio\" name=\"once_per_client\" value=\"1\" checked=\"checked\"/><label>Yes</label>
                            <input type=\"radio\" name=\"once_per_client\" value=\"0\"/><label>No</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <div class=\"rowElem\">
                        <label>{% trans 'Max uses (zero for unlimited)' %}</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"maxuses\" value=\"\" class=\"dirRight\" title=\"Leave blank for unlimited uses\"  placeholder=\"0\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Valid time (Leave blank for undefined time)' %}</label>
                        <div class=\"formRight moreFields\">
                            <ul>
                                <li style=\"width: 100px\"><input type=\"text\" name=\"start_at\" value=\"\" placeholder=\"{{ now|date('Y-m-d') }}\" class=\"datepicker\"/></li>
                                <li class=\"sep\">-</li>
                                <li style=\"width: 100px\"><input type=\"text\" name=\"end_at\" value=\"\" placeholder=\"{{ now|date('Y-m-d') }}\" class=\"datepicker\"/></li>
                            </ul>
                        </div> 
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Products (Select none to apply to all products)' %}</label>
                        <div class=\"formRight\">
                            {% set products = admin.product_get_pairs %}
                            <select name=\"products[]\" multiple=\"multiple\" class=\"multiple\" size=\"{{products|length}}\">
                                {% for id,product in products %}
                                <option value=\"{{id}}\">{{ product }}</option>
                                {% endfor %}
                            </select>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Periods (Select none to apply to all periods)' %}</label>
                        <div class=\"formRight\">
                            {% set periods = guest.system_periods %}
                            <select name=\"periods[]\" multiple=\"multiple\" class=\"multiple\" size=\"{{periods|length}}\">
                                {% for id,period in periods %}
                                <option value=\"{{id}}\">{{ period }}</option>
                                {% endfor %}
                            </select>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Client Groups (Select none to apply to all client groups)' %}</label>
                        <div class=\"formRight\">
                            {% set client_groups = admin.client_group_get_pairs %}
                            <select name=\"client_groups[]\" multiple=\"multiple\" class=\"multiple\" size=\"{{groups|length}}\">
                                {% for id, client_group in client_groups %}
                                <option value=\"{{id}}\">{{ client_group }}</option>
                                {% endfor %}
                            </select>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    {#
                    <div class=\"rowElem\">
                        <label>{% trans 'Addons (Select none to apply to all addons)' %}</label>
                        <div class=\"formRight\">
                            {% set products = admin.product_addon_get_pairs %}
                            <select name=\"products[]\" multiple=\"multiple\" class=\"multiple\" size=\"{{products|length}}\">
                                {% for id,product in products %}
                                <option value=\"{{id}}\">{{ product }}</option>
                                {% endfor %}
                            </select>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    #}
                    <input type=\"submit\" value=\"{% trans 'Create' %}\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>
        
    </div>
</div>

{% endblock %}
", "mod_product_promos.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-themes/admin_default/html/mod_product_promos.phtml");
    }
}
