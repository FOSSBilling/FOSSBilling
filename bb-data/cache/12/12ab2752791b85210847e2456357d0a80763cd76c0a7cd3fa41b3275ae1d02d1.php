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

/* mod_orderbutton_choose_product.phtml */
class __TwigTemplate_f5b6d55ff421d35214f834d93798c2f592d32f932fd95de655808b8642c10613 extends \Twig\Template
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
        echo "<div class=\"accordion-group\" id=\"choose-product\">
    ";
        // line 2
        $context["products"] = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "product_get_list", [], "any", false, false, false, 2);
        // line 3
        echo "    <div class=\"accordion-heading\">
        <a class=\"accordion-toggle\" href=\"#products\" data-parent=\"#accordion1\" data-toggle=\"collapse\"><span class=\"awe-list\"></span> ";
        // line 4
        echo gettext("Select Product");
        echo "  <span class=\"label label-info pull-right\">";
        echo twig_escape_filter($this->env, twig_length_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["products"] ?? null), "list", [], "any", false, false, false, 4)), "html", null, true);
        echo " ";
        echo gettext("Items");
        echo "</span></a>
    </div>
    <div id=\"products\" class=\"accordion-body collapse ";
        // line 6
        if ((( !twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "order", [], "any", false, false, false, 6) &&  !twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "cart", [], "any", false, false, false, 6)) &&  !twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "checkout", [], "any", false, false, false, 6))) {
            echo "in";
        }
        echo "\" >
        <div class=\"accordion-inner\">
            <ul class=\"nav nav-list\">
                ";
        // line 9
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["products"] ?? null), "list", [], "any", false, false, false, 9));
        foreach ($context['_seq'] as $context["i"] => $context["product"]) {
            // line 10
            echo "                <li>
                    <a href=\"";
            // line 11
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("orderbutton", ["order" => twig_get_attribute($this->env, $this->source, $context["product"], "id", [], "any", false, false, false, 11), "show_custom_form_values" => twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "show_custom_form_values", [], "any", false, false, false, 11)]);
            echo "\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["product"], "title", [], "any", false, false, false, 11), "html", null, true);
            echo " <span class=\"awe-arrow-right pull-right\"><span></a>
                </li>
                ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['i'], $context['product'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 14
        echo "            </ul>
        </div>
    </div>
</div>";
    }

    public function getTemplateName()
    {
        return "mod_orderbutton_choose_product.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  80 => 14,  69 => 11,  66 => 10,  62 => 9,  54 => 6,  45 => 4,  42 => 3,  40 => 2,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("<div class=\"accordion-group\" id=\"choose-product\">
    {% set products = guest.product_get_list %}
    <div class=\"accordion-heading\">
        <a class=\"accordion-toggle\" href=\"#products\" data-parent=\"#accordion1\" data-toggle=\"collapse\"><span class=\"awe-list\"></span> {% trans 'Select Product' %}  <span class=\"label label-info pull-right\">{{ products.list | length }} {% trans 'Items' %}</span></a>
    </div>
    <div id=\"products\" class=\"accordion-body collapse {% if not request.order and not request.cart and not request.checkout %}in{%endif%}\" >
        <div class=\"accordion-inner\">
            <ul class=\"nav nav-list\">
                {% for i, product in products.list %}
                <li>
                    <a href=\"{{ 'orderbutton' | link({'order':product.id, 'show_custom_form_values':request.show_custom_form_values}) }}\">{{ product.title }} <span class=\"awe-arrow-right pull-right\"><span></a>
                </li>
                {% endfor %}
            </ul>
        </div>
    </div>
</div>", "mod_orderbutton_choose_product.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/src/bb-modules/Orderbutton/html_client/mod_orderbutton_choose_product.phtml");
    }
}
