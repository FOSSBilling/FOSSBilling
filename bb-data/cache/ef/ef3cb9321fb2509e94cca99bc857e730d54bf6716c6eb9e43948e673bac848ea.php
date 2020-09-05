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

/* mod_orderbutton_checkout.phtml */
class __TwigTemplate_8c176e1b311258f6f14e6ce837e3fd42d982a83274b2f727a3e4cc6e003d9bdf extends \Twig\Template
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
        $context["cart"] = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "cart_get", [], "any", false, false, false, 1);
        // line 2
        echo "<div class=\"accordion-group\">
    <div class=\"accordion-heading\">
        <a class=\"accordion-toggle\" href=\"#checkout\" data-parent=\"#accordion1\" data-toggle=\"collapse\"><span class=\"awe-shopping-cart\"></span> ";
        // line 4
        echo gettext("Cart");
        echo " <span class=\"label label-warning pull-right\">";
        echo twig_escape_filter($this->env, twig_length_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["cart"] ?? null), "items", [], "any", false, false, false, 4)), "html", null, true);
        echo "</span></a>
    </div>
    ";
        // line 6
        if (twig_get_attribute($this->env, $this->source, ($context["cart"] ?? null), "items", [], "any", false, false, false, 6)) {
            // line 7
            echo "    <div id=\"checkout\" class=\"accordion-body collapse ";
            if ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "checkout", [], "any", false, false, false, 7) && ($context["client"] ?? null))) {
                echo "in";
            }
            echo "\">
        <div class=\"accordion-inner\" id=\"checkout-inner\">

            <table class=\"table table-striped table-bordered table-condensed\">
                <thead>
                <tr>
                    <th>";
            // line 13
            echo gettext("Product");
            echo "</th>
                    <th>";
            // line 14
            echo gettext("Price");
            echo "</th>
                    <th style=\"width: 3%; text-align: center\"></th>
                </tr>
                </thead>
                <tbody>
                ";
            // line 19
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["cart"] ?? null), "items", [], "any", false, false, false, 19));
            foreach ($context['_seq'] as $context["i"] => $context["item"]) {
                // line 20
                echo "                <tr>
                    <td>
                        ";
                // line 22
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["item"], "title", [], "any", false, false, false, 22), "html", null, true);
                echo "
                        ";
                // line 23
                if ((twig_get_attribute($this->env, $this->source, $context["item"], "quantity", [], "any", false, false, false, 23) > 1)) {
                    // line 24
                    echo "                        x ";
                    echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["item"], "quantity", [], "any", false, false, false, 24), "html", null, true);
                    echo "
                        ";
                }
                // line 26
                echo "
                        ";
                // line 27
                if (twig_get_attribute($this->env, $this->source, $context["item"], "period", [], "any", false, false, false, 27)) {
                    // line 28
                    echo "                            (";
                    echo twig_period_title($this->env, twig_get_attribute($this->env, $this->source, $context["item"], "period", [], "any", false, false, false, 28));
                    echo ")
                        ";
                }
                // line 30
                echo "                    </td>
                    <td>
                        ";
                // line 32
                if (twig_get_attribute($this->env, $this->source, $context["item"], "discount_price", [], "any", false, false, false, 32)) {
                    // line 33
                    echo "                        <del>";
                    echo twig_money_convert($this->env, twig_get_attribute($this->env, $this->source, $context["item"], "total", [], "any", false, false, false, 33));
                    echo "</del>
                        <strong class=\"text-success\">";
                    // line 34
                    echo twig_money_convert($this->env, (twig_get_attribute($this->env, $this->source, $context["item"], "total", [], "any", false, false, false, 34) - twig_get_attribute($this->env, $this->source, $context["item"], "discount_price", [], "any", false, false, false, 34)));
                    echo "</strong>
\t\t\t\t\t\t\t
\t\t\t\t\t\t\t\t
                        ";
                } else {
                    // line 38
                    echo "                            ";
                    echo twig_money_convert($this->env, twig_get_attribute($this->env, $this->source, $context["item"], "total", [], "any", false, false, false, 38));
                    echo "
                        ";
                }
                // line 40
                echo "                    </td>
                    <td><button data-cart-item-id=\"";
                // line 41
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["item"], "id", [], "any", false, false, false, 41), "html", null, true);
                echo "\" class=\"btn btn-inverse btn-mini remove-cart-item\" title=\"";
                echo gettext("Remove item");
                echo "\"><strong><i class=\"awe-remove\"></i></strong></button></td>
                </tr>

                ";
                // line 44
                if (twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "show_custom_form_values", [], "any", false, false, false, 44)) {
                    // line 45
                    echo "                <tr>
                    <td>
                        ";
                    // line 47
                    if ((twig_get_attribute($this->env, $this->source, $context["item"], "form_id", [], "any", false, false, false, 47) && twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "extension_is_on", [0 => ["mod" => "formbuilder"]], "method", false, false, false, 47))) {
                        // line 48
                        echo "                        ";
                        $context["form"] = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "formbuilder_get", [0 => ["id" => twig_get_attribute($this->env, $this->source, $context["item"], "form_id", [], "any", false, false, false, 48)]], "method", false, false, false, 48);
                        // line 49
                        echo "                        ";
                        // line 50
                        echo "                        <div class=\"well\">
                            <dl class=\"dl-horizontal\">
                                ";
                        // line 52
                        $context['_parent'] = $context;
                        $context['_seq'] = twig_ensure_traversable($context["item"]);
                        foreach ($context['_seq'] as $context["field"] => $context["value"]) {
                            // line 53
                            echo "                                    ";
                            $context['_parent'] = $context;
                            $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["form"] ?? null), "fields", [], "any", false, false, false, 53));
                            foreach ($context['_seq'] as $context["_key"] => $context["form_field"]) {
                                // line 54
                                echo "                                        ";
                                if ( !twig_test_empty($context["value"])) {
                                    // line 55
                                    echo "                                            ";
                                    if ((twig_get_attribute($this->env, $this->source, $context["form_field"], "name", [], "any", false, false, false, 55) == $context["field"])) {
                                        // line 56
                                        echo "                                            <dt>
                                                ";
                                        // line 57
                                        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["form_field"], "label", [], "any", false, false, false, 57), "html", null, true);
                                        echo "
                                            </dt>
                                            <dd>
                                                ";
                                        // line 60
                                        if ((twig_get_attribute($this->env, $this->source, $context["form_field"], "type", [], "any", false, false, false, 60) == "checkbox")) {
                                            // line 61
                                            echo "                                                    ";
                                            $context['_parent'] = $context;
                                            $context['_seq'] = twig_ensure_traversable($context["value"]);
                                            foreach ($context['_seq'] as $context["_key"] => $context["selection"]) {
                                                // line 62
                                                echo "                                                        ";
                                                $context['_parent'] = $context;
                                                $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, $context["form_field"], "options", [], "any", false, false, false, 62));
                                                foreach ($context['_seq'] as $context["field_key"] => $context["field_value"]) {
                                                    // line 63
                                                    echo "                                                            ";
                                                    if (($context["field_value"] == $context["selection"])) {
                                                        // line 64
                                                        echo "                                                                ";
                                                        echo twig_escape_filter($this->env, $context["field_key"], "html", null, true);
                                                        echo "
                                                            ";
                                                    }
                                                    // line 66
                                                    echo "                                                        ";
                                                }
                                                $_parent = $context['_parent'];
                                                unset($context['_seq'], $context['_iterated'], $context['field_key'], $context['field_value'], $context['_parent'], $context['loop']);
                                                $context = array_intersect_key($context, $_parent) + $_parent;
                                                // line 67
                                                echo "                                                    ";
                                            }
                                            $_parent = $context['_parent'];
                                            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['selection'], $context['_parent'], $context['loop']);
                                            $context = array_intersect_key($context, $_parent) + $_parent;
                                            // line 68
                                            echo "                                                ";
                                        } elseif (((twig_get_attribute($this->env, $this->source, $context["form_field"], "type", [], "any", false, false, false, 68) == "select") || (twig_get_attribute($this->env, $this->source, $context["form_field"], "type", [], "any", false, false, false, 68) == "radio"))) {
                                            // line 69
                                            echo "                                                    ";
                                            $context['_parent'] = $context;
                                            $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, $context["form_field"], "options", [], "any", false, false, false, 69));
                                            foreach ($context['_seq'] as $context["field_key"] => $context["field_value"]) {
                                                // line 70
                                                echo "                                                        ";
                                                if (($context["field_value"] == $context["value"])) {
                                                    // line 71
                                                    echo "                                                            ";
                                                    echo twig_escape_filter($this->env, $context["field_key"], "html", null, true);
                                                    echo "
                                                        ";
                                                }
                                                // line 73
                                                echo "                                                    ";
                                            }
                                            $_parent = $context['_parent'];
                                            unset($context['_seq'], $context['_iterated'], $context['field_key'], $context['field_value'], $context['_parent'], $context['loop']);
                                            $context = array_intersect_key($context, $_parent) + $_parent;
                                            // line 74
                                            echo "                                                ";
                                        } else {
                                            // line 75
                                            echo "                                                    ";
                                            echo twig_escape_filter($this->env, $context["value"], "html", null, true);
                                            echo "
                                                ";
                                        }
                                        // line 77
                                        echo "                                            </dd>
                                            ";
                                    }
                                    // line 79
                                    echo "                                        ";
                                }
                                // line 80
                                echo "                                    ";
                            }
                            $_parent = $context['_parent'];
                            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['form_field'], $context['_parent'], $context['loop']);
                            $context = array_intersect_key($context, $_parent) + $_parent;
                            // line 81
                            echo "                                ";
                        }
                        $_parent = $context['_parent'];
                        unset($context['_seq'], $context['_iterated'], $context['field'], $context['value'], $context['_parent'], $context['loop']);
                        $context = array_intersect_key($context, $_parent) + $_parent;
                        // line 82
                        echo "                            </dl>
                        </div>
                        ";
                    }
                    // line 85
                    echo "                    </td>
                    <td></td>
                </tr>
                ";
                }
                // line 89
                echo "
                ";
                // line 90
                if ((twig_get_attribute($this->env, $this->source, $context["item"], "setup_price", [], "any", false, false, false, 90) != 0)) {
                    // line 91
                    echo "                <tr>
                    <td>";
                    // line 92
                    echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["item"], "title", [], "any", false, false, false, 92), "html", null, true);
                    echo " ";
                    echo gettext("setup");
                    echo "</td>
                    <td>
                        ";
                    // line 94
                    if (twig_get_attribute($this->env, $this->source, $context["item"], "discount_setup", [], "any", false, false, false, 94)) {
                        // line 95
                        echo "                        <del>";
                        echo twig_money_convert($this->env, twig_get_attribute($this->env, $this->source, $context["item"], "setup_price", [], "any", false, false, false, 95));
                        echo "</del>
                        ";
                        // line 96
                        echo twig_money_convert($this->env, (twig_get_attribute($this->env, $this->source, $context["item"], "setup_price", [], "any", false, false, false, 96) - twig_get_attribute($this->env, $this->source, $context["item"], "discount_setup", [], "any", false, false, false, 96)));
                        echo "
                        ";
                    } else {
                        // line 98
                        echo "                        ";
                        echo twig_money_convert($this->env, twig_get_attribute($this->env, $this->source, $context["item"], "setup_price", [], "any", false, false, false, 98));
                        echo "</td>
                    ";
                    }
                    // line 100
                    echo "                </tr>
                ";
                }
                // line 102
                echo "\t\t\t
                ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['i'], $context['item'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 104
            echo "                </tbody>
            </table>

            <div class=\"row-fluid\">
                <div class=\"span6\">
                    ";
            // line 109
            if ( !twig_get_attribute($this->env, $this->source, ($context["cart"] ?? null), "promocode", [], "any", false, false, false, 109)) {
                // line 110
                echo "                    <a href=\"#\" id=\"show-promo-field\">";
                echo gettext("Have coupon code?");
                echo "</a>
                    ";
            }
            // line 112
            echo "
                    <form action=\"guest/cart/apply_promo\" method=\"post\" class=\"well\" id=\"apply-promo\" data-api-reload=\"1\" ";
            // line 113
            if ( !twig_get_attribute($this->env, $this->source, ($context["cart"] ?? null), "promocode", [], "any", false, false, false, 113)) {
                echo "style=\"display:none\"";
            }
            echo ">
                        <div class=\"control-group\">
                            <div class=\"form-controls\">
                                <div class=\"input-append\">
                                    <input class=\"span8\" type=\"text\" name=\"promocode\" id=\"promocode\" value=\"";
            // line 117
            echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "promocode", [], "any", true, true, false, 117)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "promocode", [], "any", false, false, false, 117), twig_get_attribute($this->env, $this->source, ($context["cart"] ?? null), "promocode", [], "any", false, false, false, 117))) : (twig_get_attribute($this->env, $this->source, ($context["cart"] ?? null), "promocode", [], "any", false, false, false, 117))), "html", null, true);
            echo "\" ";
            if (twig_get_attribute($this->env, $this->source, ($context["promo"] ?? null), "required", [], "any", false, false, false, 117)) {
                echo "required=\"required\"";
            }
            echo " placeholder=\"";
            echo gettext("Enter code");
            echo "\">
                                    ";
            // line 118
            if (twig_get_attribute($this->env, $this->source, ($context["cart"] ?? null), "promocode", [], "any", false, false, false, 118)) {
                // line 119
                echo "                                    <button class=\"btn\" id=\"remove-promo\" href=\"";
                echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/guest/cart/remove_promo");
                echo "\" type=\"button\" data-api-reload=\"1\">";
                echo gettext("Remove");
                echo "</button>
                                    ";
            } else {
                // line 121
                echo "                                    <button class=\"btn\" type=\"submit\">";
                echo gettext("Apply");
                echo "</button>
                                    ";
            }
            // line 123
            echo "                                </div>
                            </div>
                        </div>
                        ";
            // line 134
            echo "                    </form>
                </div>

                <div class=\"span6\">
                    <table class=\"table table-bordered table-striped\">

                        ";
            // line 140
            if ((twig_get_attribute($this->env, $this->source, ($context["cart"] ?? null), "discount", [], "any", false, false, false, 140) > 0)) {
                // line 141
                echo "                        <tr>
                            <td><strong>";
                // line 142
                echo gettext("Subtotal:");
                echo "</strong></td>
                            <td><strong>";
                // line 143
                echo twig_money_convert($this->env, twig_get_attribute($this->env, $this->source, ($context["cart"] ?? null), "subtotal", [], "any", false, false, false, 143));
                echo "</strong></td>
                        </tr>
                        <tr>
                            <td><strong>";
                // line 146
                echo gettext("Discount:");
                echo "</strong></td>
                            <td><strong>- ";
                // line 147
                echo twig_money_convert($this->env, twig_get_attribute($this->env, $this->source, ($context["cart"] ?? null), "discount", [], "any", false, false, false, 147));
                echo "</strong></td>
                        </tr>
                        ";
            }
            // line 150
            echo "
                        ";
            // line 151
            $context["tax_amount"] = 0;
            // line 152
            echo "                        ";
            if (twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "client_is_taxable", [], "any", false, false, false, 152)) {
                // line 153
                echo "                        ";
                $context["tax_rate"] = twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "invoice_get_tax_rate", [], "any", false, false, false, 153);
                // line 154
                echo "                        ";
                $context["tax_amount"] = ((twig_get_attribute($this->env, $this->source, ($context["cart"] ?? null), "total", [], "any", false, false, false, 154) * ($context["tax_rate"] ?? null)) / 100);
                // line 155
                echo "                        <tr>
                            <td><strong>";
                // line 156
                echo gettext("VAT");
                echo " (";
                echo twig_escape_filter($this->env, ($context["tax_rate"] ?? null), "html", null, true);
                echo "%) :</strong></td>
                            <td><strong>";
                // line 157
                echo twig_money_convert($this->env, ($context["tax_amount"] ?? null));
                echo "</strong></td>
                        </tr>
                        ";
            }
            // line 160
            echo "                        <tr>
                            <td><strong>";
            // line 161
            echo gettext("Total:");
            echo "</strong></td>
                            <td><strong>";
            // line 162
            echo twig_money_convert($this->env, (twig_get_attribute($this->env, $this->source, ($context["cart"] ?? null), "total", [], "any", false, false, false, 162) + ($context["tax_amount"] ?? null)));
            echo "</strong></td>
                        </tr>

                    </table>

                    <form method=\"post\" action=\"client/cart/checkout\" class=\"form-horizontal\" id=\"checkout-form\" onsubmit=\"return false;\">
                        <fieldset>
                            ";
            // line 169
            $context["enough_in_balance"] = (twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "client_balance_get_total", [], "any", false, false, false, 169) >= twig_get_attribute($this->env, $this->source, ($context["cart"] ?? null), "total", [], "any", false, false, false, 169));
            // line 170
            echo "                            ";
            if ((twig_get_attribute($this->env, $this->source, ($context["cart"] ?? null), "total", [], "any", false, false, false, 170) &&  !($context["enough_in_balance"] ?? null))) {
                // line 171
                echo "                            <div class=\"control-group\">
                                ";
                // line 172
                $context['_parent'] = $context;
                $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "invoice_gateways", [], "any", false, false, false, 172));
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
                foreach ($context['_seq'] as $context["_key"] => $context["gtw"]) {
                    // line 173
                    echo "                                ";
                    if (twig_in_filter(twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["cart"] ?? null), "currency", [], "any", false, false, false, 173), "code", [], "any", false, false, false, 173), twig_get_attribute($this->env, $this->source, $context["gtw"], "accepted_currencies", [], "any", false, false, false, 173))) {
                        // line 174
                        echo "                                <label class=\"radio\" for=\"";
                        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["gtw"], "id", [], "any", false, false, false, 174), "html", null, true);
                        echo "\">
                                    <input type=\"radio\" name=\"gateway_id\" id=";
                        // line 175
                        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["gtw"], "id", [], "any", false, false, false, 175), "html", null, true);
                        echo " value=\"";
                        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["gtw"], "id", [], "any", false, false, false, 175), "html", null, true);
                        echo "\" ";
                        echo ((twig_get_attribute($this->env, $this->source, $context["loop"], "first", [], "any", false, false, false, 175)) ? ("checked") : (""));
                        echo "/>
                                    ";
                        // line 176
                        echo gettext("Pay by");
                        echo " ";
                        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["gtw"], "title", [], "any", false, false, false, 176), "html", null, true);
                        echo "
                                </label>
                                ";
                    }
                    // line 179
                    echo "                                ";
                    ++$context['loop']['index0'];
                    ++$context['loop']['index'];
                    $context['loop']['first'] = false;
                    if (isset($context['loop']['length'])) {
                        --$context['loop']['revindex0'];
                        --$context['loop']['revindex'];
                        $context['loop']['last'] = 0 === $context['loop']['revindex0'];
                    }
                }
                $_parent = $context['_parent'];
                unset($context['_seq'], $context['_iterated'], $context['_key'], $context['gtw'], $context['_parent'], $context['loop']);
                $context = array_intersect_key($context, $_parent) + $_parent;
                // line 180
                echo "                            </div>
                            ";
            }
            // line 182
            echo "                            <div class=\"control-group\">
                                <div class=\"controls\">
                                    ";
            // line 184
            if (($context["enough_in_balance"] ?? null)) {
                // line 185
                echo "                                        <p>";
                echo gettext("Total amount will be deducted from account balance");
                echo "</p>
                                    ";
            }
            // line 187
            echo "                                    <button class=\"btn btn-primary btn-large\" type=\"submit\">";
            echo gettext("Checkout");
            echo "</button>
                                </div>
                            </div>
                        </fieldset>
                    </form>

                </div>
            </div>
        </div>
    </div>
    ";
        }
        // line 198
        echo "</div>
<script type=\"text/javascript\">
    \$('#remove-promo').click(function(e){
        e.preventDefault();
        bb.post(\"guest/cart/remove_promo\", {}, function(r){
                bb.msg(\"";
        // line 203
        echo gettext("Promo code was removed");
        echo "\");
                location.reload(false);
        });

    });

    \$('.remove-cart-item').click(function(e){
        e.preventDefault();
        var btn = \$(this);
        if (confirm('";
        // line 212
        echo gettext("Are you sure you want to remove this item from cart?");
        echo "')){
            var item_id = \$(btn).attr('data-cart-item-id');
            bb.post(\"guest/cart/remove_item\", {id: item_id}, function(r){
                bb.msg(\"";
        // line 215
        echo gettext("Item was removed from cart");
        echo "\");
                location.reload(false);
            });
        }


    });
</script>";
    }

    public function getTemplateName()
    {
        return "mod_orderbutton_checkout.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  578 => 215,  572 => 212,  560 => 203,  553 => 198,  538 => 187,  532 => 185,  530 => 184,  526 => 182,  522 => 180,  508 => 179,  500 => 176,  492 => 175,  487 => 174,  484 => 173,  467 => 172,  464 => 171,  461 => 170,  459 => 169,  449 => 162,  445 => 161,  442 => 160,  436 => 157,  430 => 156,  427 => 155,  424 => 154,  421 => 153,  418 => 152,  416 => 151,  413 => 150,  407 => 147,  403 => 146,  397 => 143,  393 => 142,  390 => 141,  388 => 140,  380 => 134,  375 => 123,  369 => 121,  361 => 119,  359 => 118,  349 => 117,  340 => 113,  337 => 112,  331 => 110,  329 => 109,  322 => 104,  315 => 102,  311 => 100,  305 => 98,  300 => 96,  295 => 95,  293 => 94,  286 => 92,  283 => 91,  281 => 90,  278 => 89,  272 => 85,  267 => 82,  261 => 81,  255 => 80,  252 => 79,  248 => 77,  242 => 75,  239 => 74,  233 => 73,  227 => 71,  224 => 70,  219 => 69,  216 => 68,  210 => 67,  204 => 66,  198 => 64,  195 => 63,  190 => 62,  185 => 61,  183 => 60,  177 => 57,  174 => 56,  171 => 55,  168 => 54,  163 => 53,  159 => 52,  155 => 50,  153 => 49,  150 => 48,  148 => 47,  144 => 45,  142 => 44,  134 => 41,  131 => 40,  125 => 38,  118 => 34,  113 => 33,  111 => 32,  107 => 30,  101 => 28,  99 => 27,  96 => 26,  90 => 24,  88 => 23,  84 => 22,  80 => 20,  76 => 19,  68 => 14,  64 => 13,  52 => 7,  50 => 6,  43 => 4,  39 => 2,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% set cart = guest.cart_get%}
<div class=\"accordion-group\">
    <div class=\"accordion-heading\">
        <a class=\"accordion-toggle\" href=\"#checkout\" data-parent=\"#accordion1\" data-toggle=\"collapse\"><span class=\"awe-shopping-cart\"></span> {% trans 'Cart' %} <span class=\"label label-warning pull-right\">{{ cart.items | length }}</span></a>
    </div>
    {% if cart.items %}
    <div id=\"checkout\" class=\"accordion-body collapse {% if request.checkout and client %}in{%endif%}\">
        <div class=\"accordion-inner\" id=\"checkout-inner\">

            <table class=\"table table-striped table-bordered table-condensed\">
                <thead>
                <tr>
                    <th>{% trans 'Product' %}</th>
                    <th>{% trans 'Price' %}</th>
                    <th style=\"width: 3%; text-align: center\"></th>
                </tr>
                </thead>
                <tbody>
                {% for i, item in cart.items %}
                <tr>
                    <td>
                        {{ item.title }}
                        {% if item.quantity > 1 %}
                        x {{ item.quantity }}
                        {% endif %}

                        {% if item.period %}
                            ({{ item.period | period_title }})
                        {% endif %}
                    </td>
                    <td>
                        {% if item.discount_price %}
                        <del>{{ item.total | money_convert }}</del>
                        <strong class=\"text-success\">{{ (item.total-item.discount_price) | money_convert }}</strong>
\t\t\t\t\t\t\t
\t\t\t\t\t\t\t\t
                        {% else %}
                            {{ (item.total) | money_convert }}
                        {% endif %}
                    </td>
                    <td><button data-cart-item-id=\"{{ item.id }}\" class=\"btn btn-inverse btn-mini remove-cart-item\" title=\"{% trans 'Remove item' %}\"><strong><i class=\"awe-remove\"></i></strong></button></td>
                </tr>

                {% if request.show_custom_form_values %}
                <tr>
                    <td>
                        {% if item.form_id and guest.extension_is_on({\"mod\":\"formbuilder\"}) %}
                        {% set form = guest.formbuilder_get({\"id\": item.form_id}) %}
                        {# debug form #}
                        <div class=\"well\">
                            <dl class=\"dl-horizontal\">
                                {% for field, value in item %}
                                    {% for form_field in form.fields %}
                                        {% if value is not empty %}
                                            {% if form_field.name == field%}
                                            <dt>
                                                {{form_field.label}}
                                            </dt>
                                            <dd>
                                                {% if form_field.type == \"checkbox\"%}
                                                    {% for selection in value %}
                                                        {% for field_key,field_value in form_field.options%}
                                                            {% if field_value == selection %}
                                                                {{ field_key }}
                                                            {% endif %}
                                                        {% endfor %}
                                                    {% endfor%}
                                                {% elseif form_field.type == \"select\" or form_field.type == \"radio\" %}
                                                    {% for field_key,field_value in form_field.options%}
                                                        {% if field_value == value %}
                                                            {{ field_key }}
                                                        {% endif %}
                                                    {% endfor %}
                                                {% else %}
                                                    {{value}}
                                                {% endif %}
                                            </dd>
                                            {% endif %}
                                        {% endif %}
                                    {% endfor %}
                                {% endfor %}
                            </dl>
                        </div>
                        {% endif %}
                    </td>
                    <td></td>
                </tr>
                {% endif %}

                {% if item.setup_price != 0 %}
                <tr>
                    <td>{{ item.title }} {% trans 'setup' %}</td>
                    <td>
                        {% if item.discount_setup %}
                        <del>{{ item.setup_price | money_convert }}</del>
                        {{ (item.setup_price - item.discount_setup) | money_convert }}
                        {% else %}
                        {{ item.setup_price | money_convert }}</td>
                    {% endif %}
                </tr>
                {% endif %}
\t\t\t
                {% endfor %}
                </tbody>
            </table>

            <div class=\"row-fluid\">
                <div class=\"span6\">
                    {% if not cart.promocode %}
                    <a href=\"#\" id=\"show-promo-field\">{% trans 'Have coupon code?' %}</a>
                    {% endif %}

                    <form action=\"guest/cart/apply_promo\" method=\"post\" class=\"well\" id=\"apply-promo\" data-api-reload=\"1\" {% if not cart.promocode %}style=\"display:none\"{% endif %}>
                        <div class=\"control-group\">
                            <div class=\"form-controls\">
                                <div class=\"input-append\">
                                    <input class=\"span8\" type=\"text\" name=\"promocode\" id=\"promocode\" value=\"{{ request.promocode|default(cart.promocode) }}\" {% if promo.required %}required=\"required\"{% endif %} placeholder=\"{% trans 'Enter code' %}\">
                                    {% if cart.promocode %}
                                    <button class=\"btn\" id=\"remove-promo\" href=\"{{ 'api/guest/cart/remove_promo'|link }}\" type=\"button\" data-api-reload=\"1\">{% trans 'Remove' %}</button>
                                    {% else %}
                                    <button class=\"btn\" type=\"submit\">{% trans 'Apply' %}</button>
                                    {% endif %}
                                </div>
                            </div>
                        </div>
                        {#
                        <input type=\"text\" class=\"search-query\" name=\"promocode\" value=\"{{ request.promocode|default(cart.promocode) }}\" {% if promo.required %}required=\"required\"{% endif %} placeholder=\"{% trans 'Enter code' %}\">
                        {% if cart.promocode %}
                        <a href=\"{{ 'api/guest/cart/remove_promo'|link }}\" class=\"btn btn-info api-link\" data-api-reload=\"1\" >{% trans 'Remove ' %}(<strong>{{cart.promocode}}</strong>)</a>
                        {% else %}
                        <button class=\"btn btn-info\" type=\"submit\"><span class=\"awe-gift\"></span> {% trans 'Apply' %}</button>
                        {% endif %}
                        #}
                    </form>
                </div>

                <div class=\"span6\">
                    <table class=\"table table-bordered table-striped\">

                        {% if cart.discount >0 %}
                        <tr>
                            <td><strong>{% trans 'Subtotal:' %}</strong></td>
                            <td><strong>{{ (cart.subtotal)| money_convert }}</strong></td>
                        </tr>
                        <tr>
                            <td><strong>{% trans 'Discount:' %}</strong></td>
                            <td><strong>- {{ cart.discount | money_convert }}</strong></td>
                        </tr>
                        {% endif %}

                        {% set tax_amount = 0 %}
                        {% if client.client_is_taxable %}
                        {% set tax_rate = client.invoice_get_tax_rate %}
                        {% set tax_amount = cart.total * tax_rate / 100 %}
                        <tr>
                            <td><strong>{% trans 'VAT'%} ({{ tax_rate }}%) :</strong></td>
                            <td><strong>{{ tax_amount | money_convert }}</strong></td>
                        </tr>
                        {% endif %}
                        <tr>
                            <td><strong>{% trans 'Total:' %}</strong></td>
                            <td><strong>{{ (cart.total + tax_amount) | money_convert }}</strong></td>
                        </tr>

                    </table>

                    <form method=\"post\" action=\"client/cart/checkout\" class=\"form-horizontal\" id=\"checkout-form\" onsubmit=\"return false;\">
                        <fieldset>
                            {% set enough_in_balance = client.client_balance_get_total >= cart.total %}
                            {% if cart.total and not enough_in_balance %}
                            <div class=\"control-group\">
                                {% for gtw in guest.invoice_gateways %}
                                {% if cart.currency.code in gtw.accepted_currencies %}
                                <label class=\"radio\" for=\"{{gtw.id}}\">
                                    <input type=\"radio\" name=\"gateway_id\" id={{gtw.id}} value=\"{{gtw.id}}\" {{loop.first ? 'checked' : ''}}/>
                                    {% trans 'Pay by' %} {{gtw.title}}
                                </label>
                                {% endif %}
                                {% endfor %}
                            </div>
                            {% endif %}
                            <div class=\"control-group\">
                                <div class=\"controls\">
                                    {% if enough_in_balance %}
                                        <p>{% trans 'Total amount will be deducted from account balance' %}</p>
                                    {% endif %}
                                    <button class=\"btn btn-primary btn-large\" type=\"submit\">{% trans 'Checkout' %}</button>
                                </div>
                            </div>
                        </fieldset>
                    </form>

                </div>
            </div>
        </div>
    </div>
    {% endif %}
</div>
<script type=\"text/javascript\">
    \$('#remove-promo').click(function(e){
        e.preventDefault();
        bb.post(\"guest/cart/remove_promo\", {}, function(r){
                bb.msg(\"{% trans 'Promo code was removed' %}\");
                location.reload(false);
        });

    });

    \$('.remove-cart-item').click(function(e){
        e.preventDefault();
        var btn = \$(this);
        if (confirm('{% trans 'Are you sure you want to remove this item from cart?' %}')){
            var item_id = \$(btn).attr('data-cart-item-id');
            bb.post(\"guest/cart/remove_item\", {id: item_id}, function(r){
                bb.msg(\"{% trans 'Item was removed from cart' %}\");
                location.reload(false);
            });
        }


    });
</script>", "mod_orderbutton_checkout.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/src/bb-modules/Orderbutton/html_client/mod_orderbutton_checkout.phtml");
    }
}
