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

/* mod_orderbutton_index.phtml */
class __TwigTemplate_d1d9f5ffb1beb2c1f24481d80ebab4141416b33a87f4abbaaaf34ef78f2b21d7 extends \Twig\Template
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
        if (twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "theme_color", [], "any", false, false, false, 1)) {
            $context["theme_color"] = (("css/huraga-" . twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "theme_color", [], "any", false, false, false, 1)) . ".css");
        } else {
            $context["theme_color"] = "css/huraga-green.css";
        }
        // line 2
        $context["loader_nr"] = ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "loader", [], "any", true, true, false, 2)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "loader", [], "any", false, false, false, 2), "8")) : ("8"));
        // line 3
        $context["loader_url"] = (("img/assets/loaders/loader" . ($context["loader_nr"] ?? null)) . ".gif");
        // line 4
        echo "<!DOCTYPE html>
<html>
<head>
    <meta property=\"bb:url\" content=\"";
        // line 7
        echo twig_escape_filter($this->env, twig_constant("BB_URL"), "html", null, true);
        echo "\"/>
    <meta property=\"bb:client_area\" content=\"";
        // line 8
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/");
        echo "\"/>

    <meta charset=\"utf-8\">
    <title>Order</title>
    ";
        // line 12
        echo twig_stylesheet_tag(twig_mod_asset_url("css/bootstrap.css", "orderbutton"));
        echo "
    ";
        // line 13
        echo twig_stylesheet_tag(twig_mod_asset_url(($context["theme_color"] ?? null), "orderbutton"));
        echo "
    ";
        // line 14
        echo twig_stylesheet_tag(twig_mod_asset_url("css/plugins/jquery.jgrowl.css", "orderbutton"));
        echo "
    <script src=\"";
        // line 15
        echo twig_escape_filter($this->env, twig_mod_asset_url("js/libs/jquery.js", "orderbutton"), "html", null, true);
        echo "\"></script>
    <script src=\"";
        // line 16
        echo twig_escape_filter($this->env, twig_mod_asset_url("js/bb-jquery.js", "orderbutton"), "html", null, true);
        echo "\"></script>
    <script src=\"";
        // line 17
        echo twig_escape_filter($this->env, twig_mod_asset_url("js/bootstrap/bootstrap.min.js", "orderbutton"), "html", null, true);
        echo "\"></script>
    <script src=\"";
        // line 18
        echo twig_escape_filter($this->env, twig_mod_asset_url("js/bootstrap/plugins/bootstrap-collapse.js", "orderbutton"), "html", null, true);
        echo "\"></script>
    <script src=\"";
        // line 19
        echo twig_escape_filter($this->env, twig_mod_asset_url("js/bootstrap/plugins/bootstrap-tab.js", "orderbutton"), "html", null, true);
        echo "\"></script>
    <script src=\"";
        // line 20
        echo twig_escape_filter($this->env, twig_mod_asset_url("js/jGrowl/jquery.jgrowl.js", "orderbutton"), "html", null, true);
        echo "\"></script>
    <style type=\"text/css\">
        body{
            background:none transparent;
            background-color:transparent;
            padding-left: 0px;
            padding-right: 0px;
            height: auto;
        }
        .accordion-body form {
            border: 0px;
            margin-bottom: 0;
            border-radius: 0;
            -webkit-box-shadow: none;
            -moz-box-shadow: none;
            box-shadow: none;
        }
    </style>

</head>

<body>
<article class=\"data-block decent\" id=\"orderbutton\" style=\"margin-bottom: 0\">
    <div class=\"data-container\">
        ";
        // line 49
        echo "        <section>
            <div id=\"accordion1\" class=\"accordion\">

                ";
        // line 52
        $this->loadTemplate("mod_orderbutton_choose_product.phtml", "mod_orderbutton_index.phtml", 52)->display($context);
        // line 53
        echo "
                ";
        // line 54
        $this->loadTemplate("mod_orderbutton_product_configuration.phtml", "mod_orderbutton_index.phtml", 54)->display($context);
        // line 55
        echo "
                ";
        // line 56
        if ( !($context["client"] ?? null)) {
            // line 57
            echo "                    ";
            $this->loadTemplate("mod_orderbutton_client.phtml", "mod_orderbutton_index.phtml", 57)->display($context);
            // line 58
            echo "                ";
        }
        // line 59
        echo "
                ";
        // line 60
        $this->loadTemplate("mod_orderbutton_checkout.phtml", "mod_orderbutton_index.phtml", 60)->display($context);
        // line 61
        echo "

                <div class=\"accordion-group\" id=\"payment-html-outer\">
                    <div class=\"accordion-heading\">
                        <a class=\"accordion-toggle\" href=\"#payment-html\" data-parent=\"#accordion1\"><span class=\"awe-list\"></span> ";
        // line 65
        echo gettext("Payment");
        echo "</a>
                    </div>
                    <div id=\"payment-html\" class=\"accordion-body collapse\" >
                        <div class=\"accordion-inner\" id=\"payment-html-inner\"></div>
                    </div>
                </div>

            </div>
        </section>
        ";
        // line 74
        $this->loadTemplate("mod_orderbutton_currency.phtml", "mod_orderbutton_index.phtml", 74)->display($context);
        // line 75
        echo "        ";
        if (twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "extension_is_on", [0 => ["mod" => "branding"]], "method", false, false, false, 75)) {
            // line 76
            echo "        <div style=\"text-align: center\">
            <a href=\"http://www.boxbilling.com\" title=\"Billing Software\" target=\"_blank\">";
            // line 77
            echo gettext("Powered by BoxBilling");
            echo "</a>
        </div>
        ";
        }
        // line 80
        echo "
    </div>
</article>
<div class=\"loading\" style=\"display: none; background: rgba(0,0,0,.5) no-repeat; width:100%; height:100%; position:fixed; top:0; left:0; z-index:999;\">
    <img src=\"";
        // line 84
        echo twig_escape_filter($this->env, twig_mod_asset_url(($context["loader_url"] ?? null), "orderbutton"), "html", null, true);
        echo "\" style=\"display: block; margin-left: auto; margin-right: auto;position: relative; top : 50%\">
</div>
<script type=\"text/javascript\">
    \$(function(){
        \$('.accordion-body').on('shown', function(){
            \$('#popup-iframe').height(\$('body').height());
        });

        \$('#client-login').bind('submit',function(event){
            bb.post(
                'guest/client/login',
                \$(this).serialize(),
                function(result) {
                    bb.msg(\"";
        // line 97
        echo gettext("You logged in successfully");
        echo "\");
                    \$('#register-or-login').hide(1000, function(){
                        \$('#register-or-login').remove();
                        bb.reload();
                    });

                }
            );
            return false;
        });
        \$('#create-profile').bind('submit',function(event){
            bb.post(
                'guest/client/create',
                \$(this).serialize(),
                function(result) {
                    //login after registration
                    var login_details = {
                        email: \$('#reg-email').val(),
                        password: \$('#reg-password').val()
                    };
                    bb.post(
                        'guest/client/login',
                        login_details,
                        function(result) {
                            bb.msg(\"";
        // line 121
        echo gettext("You logged in successfully");
        echo "\");
                            \$('#register-or-login').hide(1000, function(){
                                \$('#register-or-login').remove();
                                bb.reload();
                            });
                        }
                    );
                }
            );
            return false;
        });
        \$('#add-to-cart').bind('submit',function(event){
            bb.post(
                'guest/cart/add_item',
                \$(this).serialize(),
                function(result) {
                    bb.msg(\"";
        // line 137
        echo gettext("Product was added to shopping cart");
        echo "\");
                    bb.redirect(\"";
        // line 138
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("orderbutton", ["checkout" => 1]);
        echo "\"+\"";
        if (twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "show_custom_form_values", [], "any", false, false, false, 138)) {
            echo "&show_custom_form_values=1";
        }
        echo "\");
                }
            );
            return false;
        });
        \$('#apply-promo').bind('submit',function(event){
            bb.post(
                'guest/cart/apply_promo',
                \$(this).serialize(),
                function(result) {
                    bb.msg(\"";
        // line 148
        echo gettext("Promo code was applied for your order");
        echo "\");
                    location.reload(false);
                }
            );
            return false;
        });

        \$('#checkout-form').bind('submit',function(event){
            bb.post(
                'client/cart/checkout',
                \$(this).serialize(),
                function(result) {
                    if(result.invoice_hash) {
                        bb.post('guest/invoice/payment', {hash:result.invoice_hash, gateway_id:result.gateway_id,auto_redirect:true }, function(r){
                            if(r.iframe) {
                            \$('#payment-html-inner').html(r.result);
                                \$('#checkout').collapse('hide');
                                \$('#checkout').on('hidden', function(){
                                    \$('#checkout').remove();
                                    \$('#payment-html').collapse('show');
                                });
                            } else {
                                var link = '";
        // line 170
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("invoice/banklink");
        echo "' + '/' + result.invoice_hash + '/' + result.gateway_id;
                                \$('#payment-html-inner').html('<a href=\"'+link+'\" target=\"_parent\" id=\"redirect-to-gateway\">Redirect to payment gateway</a>');
                                \$('#checkout').collapse('hide');
                                \$('#checkout-inner').remove();
                                \$('#payment-html').collapse('show');
                                \$('#redirect-to-gateway')[0].click();
                            }
                        });
                    } else {
                        window.top.location.href = ('";
        // line 179
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("order/service/manage");
        echo "' + '/' + result.order_id );
                    }
                }
            );
            return false;
        });

        \$('#show-promo-field').bind('click', function(event){
            \$('#apply-promo').show();
            \$(this).hide();
            \$('#promocode').focus();
        });

        \$('.register-login a').click(function (e) {
            e.preventDefault();
            \$(this).tab('show');
        });

    });
</script>
</body>

</html>";
    }

    public function getTemplateName()
    {
        return "mod_orderbutton_index.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  308 => 179,  296 => 170,  271 => 148,  254 => 138,  250 => 137,  231 => 121,  204 => 97,  188 => 84,  182 => 80,  176 => 77,  173 => 76,  170 => 75,  168 => 74,  156 => 65,  150 => 61,  148 => 60,  145 => 59,  142 => 58,  139 => 57,  137 => 56,  134 => 55,  132 => 54,  129 => 53,  127 => 52,  122 => 49,  95 => 20,  91 => 19,  87 => 18,  83 => 17,  79 => 16,  75 => 15,  71 => 14,  67 => 13,  63 => 12,  56 => 8,  52 => 7,  47 => 4,  45 => 3,  43 => 2,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% if request.theme_color %}{% set theme_color = 'css/huraga-'~request.theme_color~'.css' %}{% else %}{% set theme_color = 'css/huraga-green.css' %}{% endif %}
{% set loader_nr = request.loader | default(\"8\")%}
{% set loader_url = ('img/assets/loaders/loader'~loader_nr~'.gif') %}
<!DOCTYPE html>
<html>
<head>
    <meta property=\"bb:url\" content=\"{{ constant('BB_URL') }}\"/>
    <meta property=\"bb:client_area\" content=\"{{ '/'|link }}\"/>

    <meta charset=\"utf-8\">
    <title>Order</title>
    {{ 'css/bootstrap.css' | mod_asset_url('orderbutton') | stylesheet_tag }}
    {{ theme_color | mod_asset_url('orderbutton') | stylesheet_tag }}
    {{ 'css/plugins/jquery.jgrowl.css' | mod_asset_url('orderbutton') | stylesheet_tag }}
    <script src=\"{{ 'js/libs/jquery.js' | mod_asset_url('orderbutton')}}\"></script>
    <script src=\"{{ 'js/bb-jquery.js' | mod_asset_url('orderbutton')}}\"></script>
    <script src=\"{{ 'js/bootstrap/bootstrap.min.js' | mod_asset_url('orderbutton')}}\"></script>
    <script src=\"{{ 'js/bootstrap/plugins/bootstrap-collapse.js' | mod_asset_url('orderbutton')}}\"></script>
    <script src=\"{{ 'js/bootstrap/plugins/bootstrap-tab.js' | mod_asset_url('orderbutton')}}\"></script>
    <script src=\"{{ 'js/jGrowl/jquery.jgrowl.js' | mod_asset_url('orderbutton')}}\"></script>
    <style type=\"text/css\">
        body{
            background:none transparent;
            background-color:transparent;
            padding-left: 0px;
            padding-right: 0px;
            height: auto;
        }
        .accordion-body form {
            border: 0px;
            margin-bottom: 0;
            border-radius: 0;
            -webkit-box-shadow: none;
            -moz-box-shadow: none;
            box-shadow: none;
        }
    </style>

</head>

<body>
<article class=\"data-block decent\" id=\"orderbutton\" style=\"margin-bottom: 0\">
    <div class=\"data-container\">
        {#
        <header>
            <h2>{% trans 'Order Form' %}</h2>
        </header>
        #}
        <section>
            <div id=\"accordion1\" class=\"accordion\">

                {% include 'mod_orderbutton_choose_product.phtml' %}

                {% include 'mod_orderbutton_product_configuration.phtml' %}

                {% if not client %}
                    {% include 'mod_orderbutton_client.phtml' %}
                {% endif %}

                {% include 'mod_orderbutton_checkout.phtml' %}


                <div class=\"accordion-group\" id=\"payment-html-outer\">
                    <div class=\"accordion-heading\">
                        <a class=\"accordion-toggle\" href=\"#payment-html\" data-parent=\"#accordion1\"><span class=\"awe-list\"></span> {% trans 'Payment' %}</a>
                    </div>
                    <div id=\"payment-html\" class=\"accordion-body collapse\" >
                        <div class=\"accordion-inner\" id=\"payment-html-inner\"></div>
                    </div>
                </div>

            </div>
        </section>
        {% include 'mod_orderbutton_currency.phtml' %}
        {% if guest.extension_is_on({\"mod\":'branding'}) %}
        <div style=\"text-align: center\">
            <a href=\"http://www.boxbilling.com\" title=\"Billing Software\" target=\"_blank\">{% trans 'Powered by BoxBilling' %}</a>
        </div>
        {% endif %}

    </div>
</article>
<div class=\"loading\" style=\"display: none; background: rgba(0,0,0,.5) no-repeat; width:100%; height:100%; position:fixed; top:0; left:0; z-index:999;\">
    <img src=\"{{ loader_url | mod_asset_url('orderbutton')}}\" style=\"display: block; margin-left: auto; margin-right: auto;position: relative; top : 50%\">
</div>
<script type=\"text/javascript\">
    \$(function(){
        \$('.accordion-body').on('shown', function(){
            \$('#popup-iframe').height(\$('body').height());
        });

        \$('#client-login').bind('submit',function(event){
            bb.post(
                'guest/client/login',
                \$(this).serialize(),
                function(result) {
                    bb.msg(\"{% trans 'You logged in successfully' %}\");
                    \$('#register-or-login').hide(1000, function(){
                        \$('#register-or-login').remove();
                        bb.reload();
                    });

                }
            );
            return false;
        });
        \$('#create-profile').bind('submit',function(event){
            bb.post(
                'guest/client/create',
                \$(this).serialize(),
                function(result) {
                    //login after registration
                    var login_details = {
                        email: \$('#reg-email').val(),
                        password: \$('#reg-password').val()
                    };
                    bb.post(
                        'guest/client/login',
                        login_details,
                        function(result) {
                            bb.msg(\"{% trans 'You logged in successfully' %}\");
                            \$('#register-or-login').hide(1000, function(){
                                \$('#register-or-login').remove();
                                bb.reload();
                            });
                        }
                    );
                }
            );
            return false;
        });
        \$('#add-to-cart').bind('submit',function(event){
            bb.post(
                'guest/cart/add_item',
                \$(this).serialize(),
                function(result) {
                    bb.msg(\"{% trans 'Product was added to shopping cart' %}\");
                    bb.redirect(\"{{ 'orderbutton' |link({'checkout' : 1}) }}\"+\"{% if request.show_custom_form_values%}&show_custom_form_values=1{% endif%}\");
                }
            );
            return false;
        });
        \$('#apply-promo').bind('submit',function(event){
            bb.post(
                'guest/cart/apply_promo',
                \$(this).serialize(),
                function(result) {
                    bb.msg(\"{% trans 'Promo code was applied for your order' %}\");
                    location.reload(false);
                }
            );
            return false;
        });

        \$('#checkout-form').bind('submit',function(event){
            bb.post(
                'client/cart/checkout',
                \$(this).serialize(),
                function(result) {
                    if(result.invoice_hash) {
                        bb.post('guest/invoice/payment', {hash:result.invoice_hash, gateway_id:result.gateway_id,auto_redirect:true }, function(r){
                            if(r.iframe) {
                            \$('#payment-html-inner').html(r.result);
                                \$('#checkout').collapse('hide');
                                \$('#checkout').on('hidden', function(){
                                    \$('#checkout').remove();
                                    \$('#payment-html').collapse('show');
                                });
                            } else {
                                var link = '{{\"invoice/banklink\"|link}}' + '/' + result.invoice_hash + '/' + result.gateway_id;
                                \$('#payment-html-inner').html('<a href=\"'+link+'\" target=\"_parent\" id=\"redirect-to-gateway\">Redirect to payment gateway</a>');
                                \$('#checkout').collapse('hide');
                                \$('#checkout-inner').remove();
                                \$('#payment-html').collapse('show');
                                \$('#redirect-to-gateway')[0].click();
                            }
                        });
                    } else {
                        window.top.location.href = ('{{\"order/service/manage\"|link}}' + '/' + result.order_id );
                    }
                }
            );
            return false;
        });

        \$('#show-promo-field').bind('click', function(event){
            \$('#apply-promo').show();
            \$(this).hide();
            \$('#promocode').focus();
        });

        \$('.register-login a').click(function (e) {
            e.preventDefault();
            \$(this).tab('show');
        });

    });
</script>
</body>

</html>", "mod_orderbutton_index.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-modules/Orderbutton/html_client/mod_orderbutton_index.phtml");
    }
}
