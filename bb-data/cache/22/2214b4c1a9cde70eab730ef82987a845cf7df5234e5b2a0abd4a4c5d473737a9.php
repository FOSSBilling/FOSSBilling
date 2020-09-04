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

/* mod_orderbutton_product_configuration.phtml */
class __TwigTemplate_3c1b5d373d4392f6a2d940fdd046a3e9cabadea0fe4bea2c12030e5be61cf6c1 extends \Twig\Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
        ];
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 1
        $context["product"] = ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "order", [], "any", false, false, false, 1)) ? (twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "product_get", [0 => ["id" => twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "order", [], "any", false, false, false, 1)]], "method", false, false, false, 1)) : (null));
        // line 2
        echo "<div class=\"accordion-group\" id=\"product-configuration\">
    <div class=\"accordion-heading\">
        <a class=\"accordion-toggle\" href=\"#order\" data-parent=\"#accordion1\" data-toggle=\"collapse\"><span class=\"awe-cog\"></span> ";
        // line 4
        echo gettext("Product Configuration");
        echo "</a>
    </div>
    ";
        // line 6
        if (($context["product"] ?? null)) {
            // line 7
            echo "    <div id=\"order\" class=\"accordion-body collapse ";
            if (twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "order", [], "any", false, false, false, 7)) {
                echo "in";
            }
            echo "\">
        <div class=\"accordion-inner\">
            <form method=\"post\" style=\"background:none;\" class=\"form-";
            // line 9
            ((twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "form_id", [], "any", false, false, false, 9)) ? (print (twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "formbuilder_get", [0 => ["id" => twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "form_id", [], "any", false, false, false, 9)]], "method", false, false, false, 9), "style", [], "any", false, false, false, 9), "type", [], "any", false, false, false, 9), "html", null, true))) : (print (0)));
            echo "\" action=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/order");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "slug", [], "any", false, false, false, 9), "html", null, true);
            echo "\" id=\"add-to-cart\" onsubmit=\"return false;\">

            ";
            // line 11
            ob_start();
            // line 12
            echo "            <div class=\"well\">
                <strong>";
            // line 13
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "title", [], "any", false, false, false, 13), "html", null, true);
            echo "</strong>
                ";
            // line 14
            echo twig_bbmd_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "description", [], "any", false, false, false, 14));
            echo "

                ";
            // line 16
            if ((twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "pricing", [], "any", false, false, false, 16), "type", [], "any", false, false, false, 16) == "recurrent")) {
                // line 17
                echo "                ";
                $context["periods"] = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_periods", [], "any", false, false, false, 17);
                // line 18
                echo "                <select name=\"period\" id=\"period-selector\">
                    ";
                // line 19
                $context['_parent'] = $context;
                $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "pricing", [], "any", false, false, false, 19), "recurrent", [], "any", false, false, false, 19));
                foreach ($context['_seq'] as $context["code"] => $context["prices"]) {
                    // line 20
                    echo "                    ";
                    if (twig_get_attribute($this->env, $this->source, $context["prices"], "enabled", [], "any", false, false, false, 20)) {
                        // line 21
                        echo "                    <option value=\"";
                        echo twig_escape_filter($this->env, $context["code"], "html", null, true);
                        echo "\" data-bb-price=\"";
                        echo twig_money_convert($this->env, twig_get_attribute($this->env, $this->source, $context["prices"], "price", [], "any", false, false, false, 21));
                        echo "\" name=\"period\">";
                        echo twig_escape_filter($this->env, (($__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4 = ($context["periods"] ?? null)) && is_array($__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4) || $__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4 instanceof ArrayAccess ? ($__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4[$context["code"]] ?? null) : null), "html", null, true);
                        echo " - ";
                        echo twig_money_convert($this->env, twig_get_attribute($this->env, $this->source, $context["prices"], "price", [], "any", false, false, false, 21));
                        echo "</option>
                    ";
                    }
                    // line 23
                    echo "                    ";
                }
                $_parent = $context['_parent'];
                unset($context['_seq'], $context['_iterated'], $context['code'], $context['prices'], $context['_parent'], $context['loop']);
                $context = array_intersect_key($context, $_parent) + $_parent;
                // line 24
                echo "                </select>
                ";
            } elseif ((twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source,             // line 25
($context["product"] ?? null), "pricing", [], "any", false, false, false, 25), "type", [], "any", false, false, false, 25) == "free")) {
                // line 26
                echo "                <span class=\"label label-info\">";
                echo gettext("FREE");
                echo "</span>
                ";
            } else {
                // line 28
                echo "                <span class=\"label label-info\">";
                echo twig_money_convert($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "pricing", [], "any", false, false, false, 28), "once", [], "any", false, false, false, 28), "price", [], "any", false, false, false, 28));
                echo "</span>
                ";
            }
            // line 30
            echo "            </div>
            ";
            $context["product_details"] = ('' === $tmp = ob_get_clean()) ? '' : new Markup($tmp, $this->env->getCharset());
            // line 32
            echo "
            ";
            // line 33
            $context["tpl"] = (("mod_service" . twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "type", [], "any", false, false, false, 33)) . "_order_form.phtml");
            // line 34
            echo "            ";
            if (twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_template_exists", [0 => ["file" => ($context["tpl"] ?? null)]], "method", false, false, false, 34)) {
                // line 35
                echo "                ";
                $this->loadTemplate(($context["tpl"] ?? null), "mod_orderbutton_product_configuration.phtml", 35)->display(twig_array_merge($context, ($context["product"] ?? null)));
                // line 36
                echo "            ";
            } elseif ((twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "form_id", [], "any", false, false, false, 36) && twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "extension_is_on", [0 => ["mod" => "formbuilder"]], "method", false, false, false, 36))) {
                // line 37
                echo "                ";
                echo twig_escape_filter($this->env, ($context["product_details"] ?? null), "html", null, true);
                echo "
                ";
                // line 38
                $context["form"] = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "formbuilder_get", [0 => ["id" => twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "form_id", [], "any", false, false, false, 38)]], "method", false, false, false, 38);
                // line 39
                echo "                ";
                $this->loadTemplate("mod_formbuilder_build.phtml", "mod_orderbutton_product_configuration.phtml", 39)->display(twig_array_merge($context, ($context["product"] ?? null)));
                // line 40
                echo "            ";
            } else {
                // line 41
                echo "                ";
                echo twig_escape_filter($this->env, ($context["product_details"] ?? null), "html", null, true);
                echo "
            ";
            }
            // line 43
            echo "
            ";
            // line 44
            $this->loadTemplate("mod_orderbutton_addons.phtml", "mod_orderbutton_product_configuration.phtml", 44)->display(twig_array_merge($context, ($context["product"] ?? null)));
            // line 45
            echo "
            <input type=\"hidden\" name=\"multiple\" value=\"1\" />
            <input type=\"hidden\" name=\"id\" value=\"";
            // line 47
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "id", [], "any", false, false, false, 47), "html", null, true);
            echo "\" />
               <button type=\"submit\" class=\"btn btn-primary\">";
            // line 48
            echo gettext("Order");
            echo "</button>
        </div>
        </form>
    </div>
    ";
        }
        // line 53
        echo "</div>";
    }

    public function getTemplateName()
    {
        return "mod_orderbutton_product_configuration.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  189 => 53,  181 => 48,  177 => 47,  173 => 45,  171 => 44,  168 => 43,  162 => 41,  159 => 40,  156 => 39,  154 => 38,  149 => 37,  146 => 36,  143 => 35,  140 => 34,  138 => 33,  135 => 32,  131 => 30,  125 => 28,  119 => 26,  117 => 25,  114 => 24,  108 => 23,  96 => 21,  93 => 20,  89 => 19,  86 => 18,  83 => 17,  81 => 16,  76 => 14,  72 => 13,  69 => 12,  67 => 11,  58 => 9,  50 => 7,  48 => 6,  43 => 4,  39 => 2,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% set product = request.order ? guest.product_get({\"id\":request.order}) : null %}
<div class=\"accordion-group\" id=\"product-configuration\">
    <div class=\"accordion-heading\">
        <a class=\"accordion-toggle\" href=\"#order\" data-parent=\"#accordion1\" data-toggle=\"collapse\"><span class=\"awe-cog\"></span> {% trans 'Product Configuration' %}</a>
    </div>
    {% if product %}
    <div id=\"order\" class=\"accordion-body collapse {% if request.order%}in{%endif%}\">
        <div class=\"accordion-inner\">
            <form method=\"post\" style=\"background:none;\" class=\"form-{{ product.form_id ? guest.formbuilder_get( {\"id\":product.form_id}).style.type : 0 }}\" action=\"{{ '/order'|link }}/{{ product.slug }}\" id=\"add-to-cart\" onsubmit=\"return false;\">

            {% set product_details %}
            <div class=\"well\">
                <strong>{{ product.title }}</strong>
                {{ product.description | bbmd }}

                {% if product.pricing.type == 'recurrent' %}
                {% set periods = guest.system_periods %}
                <select name=\"period\" id=\"period-selector\">
                    {% for code,prices in product.pricing.recurrent %}
                    {% if prices.enabled %}
                    <option value=\"{{code}}\" data-bb-price=\"{{ prices.price | money_convert }}\" name=\"period\">{{ periods[code] }} - {{ prices.price | money_convert }}</option>
                    {% endif %}
                    {% endfor %}
                </select>
                {% elseif product.pricing.type == 'free' %}
                <span class=\"label label-info\">{% trans 'FREE' %}</span>
                {% else %}
                <span class=\"label label-info\">{{ product.pricing.once.price | money_convert }}</span>
                {% endif %}
            </div>
            {% endset %}

            {% set tpl = \"mod_service\"~product.type~\"_order_form.phtml\" %}
            {% if guest.system_template_exists({\"file\":tpl}) %}
                {% include tpl with product %}
            {% elseif product.form_id and guest.extension_is_on({\"mod\":\"formbuilder\"}) %}
                {{ product_details }}
                {% set form = guest.formbuilder_get({\"id\":product.form_id}) %}
                {% include 'mod_formbuilder_build.phtml' with product %}
            {% else %}
                {{ product_details }}
            {% endif %}

            {% include 'mod_orderbutton_addons.phtml' with product %}

            <input type=\"hidden\" name=\"multiple\" value=\"1\" />
            <input type=\"hidden\" name=\"id\" value=\"{{ product.id }}\" />
               <button type=\"submit\" class=\"btn btn-primary\">{% trans 'Order' %}</button>
        </div>
        </form>
    </div>
    {% endif %}
</div>", "mod_orderbutton_product_configuration.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-modules/Orderbutton/html_client/mod_orderbutton_product_configuration.phtml");
    }
}
