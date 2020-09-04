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

/* mod_servicehosting_order_form.phtml */
class __TwigTemplate_956171185ccc2372145845a86ffc6e05ea74944a1e3a18072252c00f473b842a extends \Twig\Template
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
        $context["periods"] = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_periods", [], "any", false, false, false, 1);
        // line 2
        $context["pricing"] = twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "pricing", [], "any", false, false, false, 2);
        // line 3
        echo "
<section>

";
        // line 6
        echo twig_escape_filter($this->env, ($context["product_details"] ?? null), "html", null, true);
        echo "

<div class=\"well\">

    <strong>";
        // line 10
        echo gettext("Domain configuration");
        echo "</strong>
    <div class=\"control-group\">
        <label class=\"radio\">";
        // line 12
        echo gettext("I will use my existing domain and update nameservers");
        // line 13
        echo "            <input type=\"radio\" name=\"domain[action]\" value=\"owndomain\" onclick=\"selectDomainAction(this);\"/>
        </label>

        <label class=\"radio\">";
        // line 16
        echo gettext("I want to register a new domain");
        // line 17
        echo "            <input type=\"radio\" name=\"domain[action]\" value=\"register\" onclick=\"selectDomainAction(this);\"/>
        </label>


    <div id=\"owndomain\" class=\"domain_action\" style=\"display: none;\">
        <fieldset>
            <div class=\"row-fluid\">
            <div class=\"controls\">
                <input type=\"text\" name=\"domain[owndomain_sld]\" value=\"";
        // line 25
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "owndomain_sld", [], "any", false, false, false, 25), "html", null, true);
        echo "\" placeholder=\"";
        echo gettext("mydomain");
        echo "\" class=\"span4\">
                <input type=\"text\" name=\"domain[owndomain_tld]\" value=\"";
        // line 26
        echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "owndomain_tld", [], "any", true, true, false, 26)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "owndomain_tld", [], "any", false, false, false, 26), ".com")) : (".com")), "html", null, true);
        echo "\">
            </div>
            </div>
        </fieldset>
    </div>

    <div id=\"register\" class=\"domain_action\" style=\"display: none;\">
        <fieldset>
            <div class=\"row-fluid\">
            <div class=\"controls\">
                <input type=\"text\" name=\"domain[register_sld]\" value=\"";
        // line 36
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "register_sld", [], "any", false, false, false, 36), "html", null, true);
        echo "\" placeholder=\"";
        echo gettext("mydomain");
        echo "\" class=\"span4\">
                    ";
        // line 37
        $context["tlds"] = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "serviceDomain_tlds", [0 => ["allow_register" => 1]], "method", false, false, false, 37);
        // line 38
        echo "                <select name=\"domain[register_tld]\">
                    ";
        // line 39
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(($context["tlds"] ?? null));
        foreach ($context['_seq'] as $context["_key"] => $context["tld"]) {
            // line 40
            echo "                        <option value=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tld"], "tld", [], "any", false, false, false, 40), "html", null, true);
            echo "\" label=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tld"], "tld", [], "any", false, false, false, 40), "html", null, true);
            echo "\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tld"], "tld", [], "any", false, false, false, 40), "html", null, true);
            echo "</option>
                    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['tld'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 42
        echo "                </select>
                <button class=\"btn btn-inverse\" type=\"button\" id=\"domain-check\">";
        // line 43
        echo gettext("Check");
        echo "</button>
            </div>
            </div>

            <div id=\"domain-config\" style=\"display:none;\">
                <div class=\"control-group\">
                    <label>";
        // line 49
        echo gettext("Period");
        echo "</label>
                    <div class=\"controls\">
                        <select name=\"domain[register_years]\"></select>
                    </div>
                </div>
            </div>
        </fieldset>
    </div>
</div>
</div>
</section>

";
        // line 61
        $context["currency"] = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "cart_get_currency", [], "any", false, false, false, 61);
        // line 62
        echo "<script type=\"text/javascript\">
    function selectDomainAction(el)
    {
        \$('.domain_action').hide();
        \$('#'+\$(el).val()).show();
    }

\$(function() {

    \$('#domain-check').bind('click',function(event){
        var sld = \$('input[name=\"domain[register_sld]\"]').val();
        var tld = \$('select[name=\"domain[register_tld]\"]').val();
        var domain = sld + tld;
        bb.post(
            'guest/servicedomain/check',
            { sld: sld, tld: tld },
            function(result) {
                setRegistrationPricing(tld);
                \$('#domain-config').fadeIn('slow');
            }
        );
        return false;
    });

    if(\$(\".addons\").length && \$(\".addons\").is(':hidden')) {
        \$('#order-button').one('click', function(){
            \$(this).slideUp('fast');
            \$('.addons').slideDown('fast');
            return false;
        });
    }

    \$('#period-selector').change(function(){
        \$('.period').hide();
        \$('.period.' + \$(this).val()).show();
    }).trigger('change');

    \$('.addon-period-selector').change(function(){
        var r = \$(this).attr('rel');
        \$('#' + r + ' span').hide();
        \$('#' + r + ' span.' + \$(this).val()).fadeIn();
    }).trigger('change');

    function setRegistrationPricing(tld)
    {
        bb.post(
            'guest/servicedomain/pricing',
            { tld: tld },
            function(result) {
                var s = \$(\"select[name='domain[register_years]']\");
                s.find('option').remove();
                for (i=1;i<6;i++) {
                    var price = bb.currency(result.price_registration, ";
        // line 114
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["currency"] ?? null), "conversion_rate", [], "any", false, false, false, 114), "html", null, true);
        echo ", \"";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["currency"] ?? null), "code", [], "any", false, false, false, 114), "html", null, true);
        echo "\", i);
                    s.append(new Option(i + \"";
        // line 115
        echo gettext(" Year/s @ ");
        echo "\" + price, i));
                }
            }
        );
    }

});
</script>";
    }

    public function getTemplateName()
    {
        return "mod_servicehosting_order_form.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  213 => 115,  207 => 114,  153 => 62,  151 => 61,  136 => 49,  127 => 43,  124 => 42,  111 => 40,  107 => 39,  104 => 38,  102 => 37,  96 => 36,  83 => 26,  77 => 25,  67 => 17,  65 => 16,  60 => 13,  58 => 12,  53 => 10,  46 => 6,  41 => 3,  39 => 2,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% set periods = guest.system_periods %}
{% set pricing = product.pricing %}

<section>

{{ product_details }}

<div class=\"well\">

    <strong>{% trans 'Domain configuration' %}</strong>
    <div class=\"control-group\">
        <label class=\"radio\">{% trans 'I will use my existing domain and update nameservers' %}
            <input type=\"radio\" name=\"domain[action]\" value=\"owndomain\" onclick=\"selectDomainAction(this);\"/>
        </label>

        <label class=\"radio\">{% trans 'I want to register a new domain' %}
            <input type=\"radio\" name=\"domain[action]\" value=\"register\" onclick=\"selectDomainAction(this);\"/>
        </label>


    <div id=\"owndomain\" class=\"domain_action\" style=\"display: none;\">
        <fieldset>
            <div class=\"row-fluid\">
            <div class=\"controls\">
                <input type=\"text\" name=\"domain[owndomain_sld]\" value=\"{{ request.owndomain_sld }}\" placeholder=\"{% trans 'mydomain' %}\" class=\"span4\">
                <input type=\"text\" name=\"domain[owndomain_tld]\" value=\"{{ request.owndomain_tld|default('.com') }}\">
            </div>
            </div>
        </fieldset>
    </div>

    <div id=\"register\" class=\"domain_action\" style=\"display: none;\">
        <fieldset>
            <div class=\"row-fluid\">
            <div class=\"controls\">
                <input type=\"text\" name=\"domain[register_sld]\" value=\"{{ request.register_sld }}\" placeholder=\"{% trans 'mydomain' %}\" class=\"span4\">
                    {% set tlds = guest.serviceDomain_tlds({\"allow_register\":1})%}
                <select name=\"domain[register_tld]\">
                    {% for tld in tlds%}
                        <option value=\"{{ tld.tld}}\" label=\"{{ tld.tld}}\">{{ tld.tld}}</option>
                    {% endfor %}
                </select>
                <button class=\"btn btn-inverse\" type=\"button\" id=\"domain-check\">{% trans 'Check' %}</button>
            </div>
            </div>

            <div id=\"domain-config\" style=\"display:none;\">
                <div class=\"control-group\">
                    <label>{% trans 'Period' %}</label>
                    <div class=\"controls\">
                        <select name=\"domain[register_years]\"></select>
                    </div>
                </div>
            </div>
        </fieldset>
    </div>
</div>
</div>
</section>

{% set currency = guest.cart_get_currency %}
<script type=\"text/javascript\">
    function selectDomainAction(el)
    {
        \$('.domain_action').hide();
        \$('#'+\$(el).val()).show();
    }

\$(function() {

    \$('#domain-check').bind('click',function(event){
        var sld = \$('input[name=\"domain[register_sld]\"]').val();
        var tld = \$('select[name=\"domain[register_tld]\"]').val();
        var domain = sld + tld;
        bb.post(
            'guest/servicedomain/check',
            { sld: sld, tld: tld },
            function(result) {
                setRegistrationPricing(tld);
                \$('#domain-config').fadeIn('slow');
            }
        );
        return false;
    });

    if(\$(\".addons\").length && \$(\".addons\").is(':hidden')) {
        \$('#order-button').one('click', function(){
            \$(this).slideUp('fast');
            \$('.addons').slideDown('fast');
            return false;
        });
    }

    \$('#period-selector').change(function(){
        \$('.period').hide();
        \$('.period.' + \$(this).val()).show();
    }).trigger('change');

    \$('.addon-period-selector').change(function(){
        var r = \$(this).attr('rel');
        \$('#' + r + ' span').hide();
        \$('#' + r + ' span.' + \$(this).val()).fadeIn();
    }).trigger('change');

    function setRegistrationPricing(tld)
    {
        bb.post(
            'guest/servicedomain/pricing',
            { tld: tld },
            function(result) {
                var s = \$(\"select[name='domain[register_years]']\");
                s.find('option').remove();
                for (i=1;i<6;i++) {
                    var price = bb.currency(result.price_registration, {{ currency.conversion_rate }}, \"{{ currency.code }}\", i);
                    s.append(new Option(i + \"{% trans ' Year/s @ ' %}\" + price, i));
                }
            }
        );
    }

});
</script>", "mod_servicehosting_order_form.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-modules/Servicehosting/html_client/mod_servicehosting_order_form.phtml");
    }
}
