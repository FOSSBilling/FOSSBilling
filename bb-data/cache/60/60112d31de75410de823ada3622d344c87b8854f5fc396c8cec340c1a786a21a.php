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

/* mod_order_new.phtml */
class __TwigTemplate_76f2953f7543bad742fba906651d9493799f35e33d7229968764ab5f4ce81dcf extends \Twig\Template
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
            'js' => [$this, 'block_js'],
        ];
    }

    protected function doGetParent(array $context)
    {
        // line 1
        return $this->loadTemplate(((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ajax", [], "any", false, false, false, 1)) ? ("layout_blank.phtml") : ("layout_default.phtml")), "mod_order_new.phtml", 1);
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 2
        $context["active_menu"] = "order";
        // line 3
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_order_new.phtml", 3)->unwrap();
        // line 1
        $this->getParent($context)->display($context, array_merge($this->blocks, $blocks));
    }

    // line 5
    public function block_breadcrumbs($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 6
        echo "    <ul>
        <li class=\"firstB\"><a href=\"";
        // line 7
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/");
        echo "\">";
        echo gettext("Home");
        echo "</a></li>
        <li><a href=\"";
        // line 8
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("order");
        echo "\">";
        echo gettext("Orders");
        echo "</a></li>
        <li class=\"lastB\">";
        // line 9
        echo gettext("Create new order");
        echo "</li>
    </ul>
";
    }

    // line 13
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo gettext("Create new order");
    }

    // line 15
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 16
        echo "<div class=\"widget\">
    <div class=\"head\"><h5 class=\"iFrames\">";
        // line 17
        echo gettext("Create new order");
        echo "</h5></div>
            <div class=\"help\">
                <h2>\"";
        // line 19
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "title", [], "any", false, false, false, 19), "html", null, true);
        echo "\" ";
        echo gettext("for");
        echo " ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "first_name", [], "any", false, false, false, 19), "html", null, true);
        echo " ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "last_name", [], "any", false, false, false, 19), "html", null, true);
        echo "</h2>
            </div>
    <form method=\"get\" action=\"";
        // line 21
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/order/create");
        echo "\" class=\"mainForm api-form\" data-api-jsonp=\"onAfterOrderPlaced\">
        <fieldset>

            <div class=\"rowElem noborder\">
                <label>";
        // line 25
        echo gettext("Invoice option");
        echo "</label>
                <div class=\"formRight noborder\">
                    ";
        // line 27
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["invoice_option", twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "order_get_invoice_options", [], "any", false, false, false, 27), twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "invoice_option", [], "any", false, false, false, 27)], 27, $context, $this->getSourceContext());
        echo "
                </div>
                <div class=\"fix\"></div>
            </div>
            
            <div class=\"rowElem\">
                <label>";
        // line 33
        echo gettext("Activate order");
        echo ":</label>
                <div class=\"formRight\">
                    <input type=\"radio\" name=\"activate\" value=\"1\" checked=\"checked\"/><label>Yes</label>
                    <input type=\"radio\" name=\"activate\" value=\"0\" /><label>No</label>
                </div>
                <div class=\"fix\"></div>
            </div>
            
            ";
        // line 41
        if ((twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "pricing", [], "any", false, false, false, 41), "type", [], "any", false, false, false, 41) == "recurrent")) {
            // line 42
            echo "            <div class=\"rowElem\">
                <label>";
            // line 43
            echo gettext("Period");
            echo "</label>
                <div class=\"formRight\">
                    <select name=\"period\" id=\"period\" required=\"required\">
                        ";
            // line 46
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_periods", [], "any", false, false, false, 46));
            foreach ($context['_seq'] as $context["val"] => $context["label"]) {
                // line 47
                echo "                        <option value=\"";
                echo twig_escape_filter($this->env, $context["val"], "html", null, true);
                echo "\" label=\"";
                echo twig_escape_filter($this->env, $context["label"]);
                echo "\" data-price=\"";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (($__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4 = twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "pricing", [], "any", false, false, false, 47), "recurrent", [], "any", false, false, false, 47)) && is_array($__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4) || $__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4 instanceof ArrayAccess ? ($__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4[$context["val"]] ?? null) : null), "price", [], "any", false, false, false, 47), "html", null, true);
                echo "\" data-status=\"";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (($__internal_62824350bc4502ee19dbc2e99fc6bdd3bd90e7d8dd6e72f42c35efd048542144 = twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "pricing", [], "any", false, false, false, 47), "recurrent", [], "any", false, false, false, 47)) && is_array($__internal_62824350bc4502ee19dbc2e99fc6bdd3bd90e7d8dd6e72f42c35efd048542144) || $__internal_62824350bc4502ee19dbc2e99fc6bdd3bd90e7d8dd6e72f42c35efd048542144 instanceof ArrayAccess ? ($__internal_62824350bc4502ee19dbc2e99fc6bdd3bd90e7d8dd6e72f42c35efd048542144[$context["val"]] ?? null) : null), "enabled", [], "any", false, false, false, 47), "html", null, true);
                echo "\" ";
                if ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "period", [], "any", false, false, false, 47) == $context["val"])) {
                    echo "selected=\"selected\"";
                }
                echo ">";
                echo twig_escape_filter($this->env, $context["label"]);
                echo "</option>
                        ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['val'], $context['label'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 49
            echo "                    </select>
                    <span id=\"period-info\" class=\"help\"></span>
                </div>
                <div class=\"fix\"></div>
            </div>
            ";
        }
        // line 55
        echo "            
            ";
        // line 56
        $context["product_order"] = (("mod_service" . twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "type", [], "any", false, false, false, 56)) . "_order.phtml");
        // line 57
        echo "            ";
        if (twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "system_template_exists", [0 => ["file" => ($context["product_order"] ?? null)]], "method", false, false, false, 57)) {
            // line 58
            echo "                ";
            $this->loadTemplate(($context["product_order"] ?? null), "mod_order_new.phtml", 58)->display($context);
            // line 59
            echo "            ";
        }
        // line 60
        echo "            
            <input type=\"submit\" value=\"";
        // line 61
        echo gettext("Place new order");
        echo "\" class=\"greyishBtn submitForm\" />
            <input type=\"hidden\" name=\"client_id\" value=\"";
        // line 62
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "id", [], "any", false, false, false, 62), "html", null, true);
        echo "\" />
            <input type=\"hidden\" name=\"product_id\" value=\"";
        // line 63
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "id", [], "any", false, false, false, 63), "html", null, true);
        echo "\" />
        </fieldset>
    </form>
</div>

";
    }

    // line 70
    public function block_js($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 71
        echo "<script type=\"text/javascript\">

    function onAfterOrderPlaced(id) {
        bb.redirect(\"";
        // line 74
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("order/manage/");
        echo "/\"+id);
    }

    \$('#period').on('change', function(){
        var optionSelected = \$(\"option:selected\", this);
        var periodNotification = \$('#period-info');
        var spanElem = \$('<span />').css({'padding-left' : '20px', 'line-height' : '28px', 'float' : 'left'});

        periodNotification.text('');
        if (optionSelected.data('price') == 0){
            spanElem.clone().text(\"";
        // line 84
        echo gettext("Selected price is 0.00");
        echo "\").appendTo(periodNotification);
        }
        if (optionSelected.data('status') == 0){
            spanElem.clone().text(\"";
        // line 87
        echo gettext("Product is disabled in configuration");
        echo "\").appendTo(periodNotification);
        }

    });
    
</script>
";
    }

    public function getTemplateName()
    {
        return "mod_order_new.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  248 => 87,  242 => 84,  229 => 74,  224 => 71,  220 => 70,  210 => 63,  206 => 62,  202 => 61,  199 => 60,  196 => 59,  193 => 58,  190 => 57,  188 => 56,  185 => 55,  177 => 49,  156 => 47,  152 => 46,  146 => 43,  143 => 42,  141 => 41,  130 => 33,  121 => 27,  116 => 25,  109 => 21,  98 => 19,  93 => 17,  90 => 16,  86 => 15,  79 => 13,  72 => 9,  66 => 8,  60 => 7,  57 => 6,  53 => 5,  49 => 1,  47 => 3,  45 => 2,  38 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}
{% set active_menu = 'order' %}
{% import \"macro_functions.phtml\" as mf %}

{% block breadcrumbs %}
    <ul>
        <li class=\"firstB\"><a href=\"{{ '/'|alink }}\">{% trans 'Home' %}</a></li>
        <li><a href=\"{{ 'order'|alink }}\">{% trans 'Orders' %}</a></li>
        <li class=\"lastB\">{% trans 'Create new order' %}</li>
    </ul>
{% endblock %}

{% block meta_title %}{% trans 'Create new order' %}{% endblock %}

{% block content %}
<div class=\"widget\">
    <div class=\"head\"><h5 class=\"iFrames\">{% trans 'Create new order' %}</h5></div>
            <div class=\"help\">
                <h2>\"{{ product.title }}\" {% trans 'for' %} {{ client.first_name }} {{ client.last_name }}</h2>
            </div>
    <form method=\"get\" action=\"{{ 'api/admin/order/create'|link }}\" class=\"mainForm api-form\" data-api-jsonp=\"onAfterOrderPlaced\">
        <fieldset>

            <div class=\"rowElem noborder\">
                <label>{% trans 'Invoice option' %}</label>
                <div class=\"formRight noborder\">
                    {{ mf.selectbox('invoice_option', admin.order_get_invoice_options, order.invoice_option) }}
                </div>
                <div class=\"fix\"></div>
            </div>
            
            <div class=\"rowElem\">
                <label>{% trans 'Activate order' %}:</label>
                <div class=\"formRight\">
                    <input type=\"radio\" name=\"activate\" value=\"1\" checked=\"checked\"/><label>Yes</label>
                    <input type=\"radio\" name=\"activate\" value=\"0\" /><label>No</label>
                </div>
                <div class=\"fix\"></div>
            </div>
            
            {% if product.pricing.type == 'recurrent' %}
            <div class=\"rowElem\">
                <label>{% trans 'Period' %}</label>
                <div class=\"formRight\">
                    <select name=\"period\" id=\"period\" required=\"required\">
                        {% for val,label in guest.system_periods %}
                        <option value=\"{{ val }}\" label=\"{{ label|e }}\" data-price=\"{{product.pricing.recurrent[val].price}}\" data-status=\"{{product.pricing.recurrent[val].enabled}}\" {% if request.period == val %}selected=\"selected\"{% endif %}>{{ label|e }}</option>
                        {% endfor %}
                    </select>
                    <span id=\"period-info\" class=\"help\"></span>
                </div>
                <div class=\"fix\"></div>
            </div>
            {% endif %}
            
            {% set product_order = 'mod_service'~product.type~'_order.phtml' %}
            {% if admin.system_template_exists({\"file\":product_order}) %}
                {% include product_order %}
            {% endif %}
            
            <input type=\"submit\" value=\"{% trans 'Place new order' %}\" class=\"greyishBtn submitForm\" />
            <input type=\"hidden\" name=\"client_id\" value=\"{{ client.id }}\" />
            <input type=\"hidden\" name=\"product_id\" value=\"{{ product.id }}\" />
        </fieldset>
    </form>
</div>

{% endblock %}

{% block js%}
<script type=\"text/javascript\">

    function onAfterOrderPlaced(id) {
        bb.redirect(\"{{'order/manage/'|alink}}/\"+id);
    }

    \$('#period').on('change', function(){
        var optionSelected = \$(\"option:selected\", this);
        var periodNotification = \$('#period-info');
        var spanElem = \$('<span />').css({'padding-left' : '20px', 'line-height' : '28px', 'float' : 'left'});

        periodNotification.text('');
        if (optionSelected.data('price') == 0){
            spanElem.clone().text(\"{% trans 'Selected price is 0.00'%}\").appendTo(periodNotification);
        }
        if (optionSelected.data('status') == 0){
            spanElem.clone().text(\"{% trans 'Product is disabled in configuration'%}\").appendTo(periodNotification);
        }

    });
    
</script>
{% endblock %}", "mod_order_new.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-themes/admin_default/html/mod_order_new.phtml");
    }
}
