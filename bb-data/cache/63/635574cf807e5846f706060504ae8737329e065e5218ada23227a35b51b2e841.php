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

/* mod_servicehosting_config.phtml */
class __TwigTemplate_93ba1a5baaa4da499a0e9ec808fbeb129ed115e4d56daebb100611264b3e7ebe extends \Twig\Template
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
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_servicehosting_config.phtml", 1)->unwrap();
        // line 2
        echo "<div class=\"help\">
    <h5>";
        // line 3
        echo gettext("Hosting settings");
        echo "</h5>
</div>

<form method=\"post\" action=\"";
        // line 6
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/product/update_config");
        echo "\" class=\"mainForm api-form save\" data-api-msg=\"Hosting settings updated\">
<fieldset>
    <div class=\"rowElem\">
        <label>";
        // line 9
        echo gettext("Server");
        echo ":</label>
        <div class=\"formRight noborder\">
            ";
        // line 11
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["config[server_id]", twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "servicehosting_server_get_pairs", [], "any", false, false, false, 11), twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "config", [], "any", false, false, false, 11), "server_id", [], "any", false, false, false, 11), 0, "Select server"], 11, $context, $this->getSourceContext());
        echo "
        </div>
        <div class=\"fix\"></div>
    </div>
    <div class=\"rowElem\">
        <label>";
        // line 16
        echo gettext("Hosting plan");
        echo ":</label>
        <div class=\"formRight\">
            ";
        // line 18
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["config[hosting_plan_id]", twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "servicehosting_hp_get_pairs", [], "any", false, false, false, 18), twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "config", [], "any", false, false, false, 18), "hosting_plan_id", [], "any", false, false, false, 18), 0, "Select hosting plan"], 18, $context, $this->getSourceContext());
        echo "
        </div>
        <div class=\"fix\"></div>
    </div>
    <div class=\"rowElem\">
        <label>";
        // line 23
        echo gettext("Reseller hosting");
        echo ":</label>
        <div class=\"formRight\">
            <input type=\"radio\" name=\"config[reseller]\" value=\"1\"";
        // line 25
        if (twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "config", [], "any", false, false, false, 25), "reseller", [], "any", false, false, false, 25)) {
            echo " checked=\"checked\"";
        }
        echo "/><label>Yes</label>
            <input type=\"radio\" name=\"config[reseller]\" value=\"0\"";
        // line 26
        if ( !twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "config", [], "any", false, false, false, 26), "reseller", [], "any", false, false, false, 26)) {
            echo " checked=\"checked\"";
        }
        echo " /><label>No</label>
        </div>
        <div class=\"fix\"></div>
    </div>
    <div class=\"rowElem\">
        <label>";
        // line 31
        echo gettext("Free domain registration");
        echo ":</label>
        <div class=\"formRight\">
            <input type=\"radio\" name=\"config[free_domain]\" value=\"1\"";
        // line 33
        if (twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "config", [], "any", false, false, false, 33), "free_domain", [], "any", false, false, false, 33)) {
            echo " checked=\"checked\"";
        }
        echo "/><label>Yes</label>
            <input type=\"radio\" name=\"config[free_domain]\" value=\"0\"";
        // line 34
        if ( !twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "config", [], "any", false, false, false, 34), "free_domain", [], "any", false, false, false, 34)) {
            echo " checked=\"checked\"";
        }
        echo " /><label>No</label>
        </div>
        <div class=\"fix\"></div>
    </div>
    <div class=\"rowElem free-tlds-row\">
        <label>";
        // line 39
        echo gettext("Select free tlds");
        echo "</label>
        <div class=\"formRight\">
            ";
        // line 41
        $context["tlds"] = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "serviceDomain_tlds", [0 => ["allow_register" => 1]], "method", false, false, false, 41);
        // line 42
        echo "            <select name=\"config[free_tlds][]\" multiple=\"multiple\" class=\"multiple\" size=\"";
        echo twig_escape_filter($this->env, twig_length_filter($this->env, ($context["tlds"] ?? null)), "html", null, true);
        echo "\" ";
        if (twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "config", [], "any", false, false, false, 42), "free_domain", [], "any", false, false, false, 42)) {
            echo "required";
        }
        echo ">
                ";
        // line 43
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(($context["tlds"] ?? null));
        foreach ($context['_seq'] as $context["id"] => $context["tld"]) {
            // line 44
            echo "                <option value=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tld"], "tld", [], "any", false, false, false, 44), "html", null, true);
            echo "\" ";
            if (twig_in_filter(twig_get_attribute($this->env, $this->source, $context["tld"], "tld", [], "any", false, false, false, 44), twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "config", [], "any", false, false, false, 44), "free_tlds", [], "any", false, false, false, 44))) {
                echo "selected=\"selected\"";
            }
            echo ">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tld"], "tld", [], "any", false, false, false, 44), "html", null, true);
            echo "</option>
                ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['id'], $context['tld'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 46
        echo "            </select>
        </div>
        <div class=\"fix\"></div>
    </div>
\t\t<!-- Select periods to offer free domains -->
\t   <div class=\"rowElem free-periods-row\">
        <label>";
        // line 52
        echo gettext("Select free periods");
        echo "</label>
        <div class=\"formRight\">
            <select name=\"config[free_domain_periods][]\" multiple=\"multiple\" class=\"multiple\" size=\"8\" ";
        // line 54
        if (twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "config", [], "any", false, false, false, 54), "free_domain", [], "any", false, false, false, 54)) {
            echo "required";
        }
        echo ">
                <option value=\"1M\" ";
        // line 55
        if (twig_in_filter("1M", twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "config", [], "any", false, false, false, 55), "free_domain_periods", [], "any", false, false, false, 55))) {
            echo "selected=\"selected\"";
        }
        echo ">1M</option>
\t\t\t\t<option value=\"3M\" ";
        // line 56
        if (twig_in_filter("3M", twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "config", [], "any", false, false, false, 56), "free_domain_periods", [], "any", false, false, false, 56))) {
            echo "selected=\"selected\"";
        }
        echo ">3M</option>
\t\t\t\t<option value=\"6M\" ";
        // line 57
        if (twig_in_filter("6M", twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "config", [], "any", false, false, false, 57), "free_domain_periods", [], "any", false, false, false, 57))) {
            echo "selected=\"selected\"";
        }
        echo ">6M</option>
\t\t\t\t<option value=\"1Y\" ";
        // line 58
        if (twig_in_filter("1Y", twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "config", [], "any", false, false, false, 58), "free_domain_periods", [], "any", false, false, false, 58))) {
            echo "selected=\"selected\"";
        }
        echo ">1Y</option>
\t\t\t\t<option value=\"2Y\" ";
        // line 59
        if (twig_in_filter("2Y", twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "config", [], "any", false, false, false, 59), "free_domain_periods", [], "any", false, false, false, 59))) {
            echo "selected=\"selected\"";
        }
        echo ">2Y</option>
\t\t\t\t<option value=\"3Y\" ";
        // line 60
        if (twig_in_filter("3Y", twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "config", [], "any", false, false, false, 60), "free_domain_periods", [], "any", false, false, false, 60))) {
            echo "selected=\"selected\"";
        }
        echo ">3Y</option>
              
            </select>
        </div>
        <div class=\"fix\"></div>
    </div>
\t
    <div class=\"rowElem\">
        <label>";
        // line 68
        echo gettext("Free domain transfer");
        echo ":</label>
        <div class=\"formRight\">
            <input type=\"radio\" name=\"config[free_transfer]\" value=\"1\"";
        // line 70
        if (twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "config", [], "any", false, false, false, 70), "free_transfer", [], "any", false, false, false, 70)) {
            echo " checked=\"checked\"";
        }
        echo "/><label>Yes</label>
            <input type=\"radio\" name=\"config[free_transfer]\" value=\"0\"";
        // line 71
        if ( !twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "config", [], "any", false, false, false, 71), "free_transfer", [], "any", false, false, false, 71)) {
            echo " checked=\"checked\"";
        }
        echo " /><label>No</label>
        </div>
        <div class=\"fix\"></div>
    </div>

    <input type=\"submit\" value=\"";
        // line 76
        echo gettext("Update");
        echo "\" class=\"greyishBtn submitForm\" />
</fieldset>
        
<input type=\"hidden\" name=\"id\" value=\"";
        // line 79
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "id", [], "any", false, false, false, 79), "html", null, true);
        echo "\" />
</form>

<div class=\"help\">
    <h5>";
        // line 83
        echo gettext("Hosting plans");
        echo "</h5>
</div>

<table class=\"tableStatic wide\">
    <thead>
        <tr>
            <td>Title</td>
            <td style=\"width:5%\">&nbsp;</td>
        </tr>
    </thead>
    <tbody>
        ";
        // line 94
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "servicehosting_hp_get_pairs", [], "any", false, false, false, 94));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["id"] => $context["plan"]) {
            // line 95
            echo "        <tr>
            <td>";
            // line 96
            echo twig_escape_filter($this->env, $context["plan"], "html", null, true);
            echo "</td>
            <td class=\"actions\"><a class=\"bb-button btn14\" href=\"";
            // line 97
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/servicehosting/plan");
            echo "/";
            echo twig_escape_filter($this->env, $context["id"], "html", null, true);
            echo "\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a></td>
        </tr>
        ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 100
            echo "        <tr>
            <td colspan=\"2\">";
            // line 101
            echo gettext("The list is empty");
            echo "</td>
        </tr>
        ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['id'], $context['plan'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 104
        echo "    </tbody>
    <tfoot>
        <tr>
            <td colspan=\"2\">
                <a href=\"";
        // line 108
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("servicehosting#tab-new-plan");
        echo "\" title=\"\" class=\"btnIconLeft mr10 mt5\"><img src=\"images/icons/dark/settings2.png\" alt=\"\" class=\"icon\"><span>New hosting plan</span></a>
            </td>
        </tr>
    </tfoot>
</table>

<div class=\"help\">
    <h5>";
        // line 115
        echo gettext("Servers");
        echo "</h5>
</div>

<table class=\"tableStatic wide\">
    <thead>
        <tr>
            <td>Title</td>
            <td style=\"width:5%\">&nbsp;</td>
        </tr>
    </thead>
    <tbody>
        ";
        // line 126
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "servicehosting_server_get_pairs", [], "any", false, false, false, 126));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["id"] => $context["server"]) {
            // line 127
            echo "        <tr>
            <td>";
            // line 128
            echo twig_escape_filter($this->env, $context["server"], "html", null, true);
            echo "</td>
            <td class=\"actions\"><a class=\"bb-button btn14\" href=\"";
            // line 129
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/servicehosting/server");
            echo "/";
            echo twig_escape_filter($this->env, $context["id"], "html", null, true);
            echo "\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a></td>
        </tr>
        ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 132
            echo "        <tr>
            <td colspan=\"7\">";
            // line 133
            echo gettext("The list is empty");
            echo "</td>
        </tr>
        ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['id'], $context['server'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 136
        echo "    </tbody>
    <tfoot>
        <tr>
            <td colspan=\"2\">
                <a href=\"";
        // line 140
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("servicehosting#tab-new-server");
        echo "\" title=\"\" class=\"btnIconLeft mr10 mt5\"><img src=\"images/icons/dark/computer.png\" alt=\"\" class=\"icon\"><span>New server</span></a>
            </td>
        </tr>
    </tfoot>
</table>

<script>
    var free_domain_radios = \$('input:radio[name=\"config[free_domain]\"]');
    var freeTldsRow = \$('.free-tlds-row');
\tvar freePerdsRow = \$('.free-periods-row');

    free_domain_radios.on('click', function(){
        if (\$(this).val() == 1){
            freeTldsRow.fadeIn('slow');
\t\t\tfreePerdsRow.fadeIn('slow');
            \$('select[name=\"config[free_tlds][]\"]').prop('required', true);
            \$('select[name=\"config[free_domain_periods][]\"]').prop('required', true);
        }
        if (\$(this).val() == 0){
            \$('select[name=\"config[free_domain_periods][]\"]').prop('required', false);
            \$('select[name=\"config[free_tlds][]\"]').prop('required', false);
            \$('select[name=\"config[free_tlds][]\"] option:selected').prop('selected', false);
            \$('select[name=\"config[free_domain_periods][]\"] option:selected').prop('selected', false);
            freeTldsRow.fadeOut('slow');
\t\t\tfreePerdsRow.fadeOut('slow');
        }
    });

    if (free_domain_radios.filter('[value=0]:checked').length > 0){
        freeTldsRow.hide();
\t\tfreePerdsRow.hide();
    }


</script>
";
    }

    public function getTemplateName()
    {
        return "mod_servicehosting_config.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  371 => 140,  365 => 136,  356 => 133,  353 => 132,  343 => 129,  339 => 128,  336 => 127,  331 => 126,  317 => 115,  307 => 108,  301 => 104,  292 => 101,  289 => 100,  279 => 97,  275 => 96,  272 => 95,  267 => 94,  253 => 83,  246 => 79,  240 => 76,  230 => 71,  224 => 70,  219 => 68,  206 => 60,  200 => 59,  194 => 58,  188 => 57,  182 => 56,  176 => 55,  170 => 54,  165 => 52,  157 => 46,  142 => 44,  138 => 43,  129 => 42,  127 => 41,  122 => 39,  112 => 34,  106 => 33,  101 => 31,  91 => 26,  85 => 25,  80 => 23,  72 => 18,  67 => 16,  59 => 11,  54 => 9,  48 => 6,  42 => 3,  39 => 2,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% import \"macro_functions.phtml\" as mf %}
<div class=\"help\">
    <h5>{% trans 'Hosting settings' %}</h5>
</div>

<form method=\"post\" action=\"{{ 'api/admin/product/update_config'|link }}\" class=\"mainForm api-form save\" data-api-msg=\"Hosting settings updated\">
<fieldset>
    <div class=\"rowElem\">
        <label>{% trans 'Server' %}:</label>
        <div class=\"formRight noborder\">
            {{ mf.selectbox('config[server_id]', admin.servicehosting_server_get_pairs, product.config.server_id, 0, 'Select server') }}
        </div>
        <div class=\"fix\"></div>
    </div>
    <div class=\"rowElem\">
        <label>{% trans 'Hosting plan' %}:</label>
        <div class=\"formRight\">
            {{ mf.selectbox('config[hosting_plan_id]', admin.servicehosting_hp_get_pairs, product.config.hosting_plan_id, 0, 'Select hosting plan') }}
        </div>
        <div class=\"fix\"></div>
    </div>
    <div class=\"rowElem\">
        <label>{% trans 'Reseller hosting' %}:</label>
        <div class=\"formRight\">
            <input type=\"radio\" name=\"config[reseller]\" value=\"1\"{% if product.config.reseller %} checked=\"checked\"{% endif %}/><label>Yes</label>
            <input type=\"radio\" name=\"config[reseller]\" value=\"0\"{% if not product.config.reseller %} checked=\"checked\"{% endif %} /><label>No</label>
        </div>
        <div class=\"fix\"></div>
    </div>
    <div class=\"rowElem\">
        <label>{% trans 'Free domain registration' %}:</label>
        <div class=\"formRight\">
            <input type=\"radio\" name=\"config[free_domain]\" value=\"1\"{% if product.config.free_domain %} checked=\"checked\"{% endif %}/><label>Yes</label>
            <input type=\"radio\" name=\"config[free_domain]\" value=\"0\"{% if not product.config.free_domain %} checked=\"checked\"{% endif %} /><label>No</label>
        </div>
        <div class=\"fix\"></div>
    </div>
    <div class=\"rowElem free-tlds-row\">
        <label>{% trans 'Select free tlds' %}</label>
        <div class=\"formRight\">
            {% set tlds = guest.serviceDomain_tlds({\"allow_register\":1}) %}
            <select name=\"config[free_tlds][]\" multiple=\"multiple\" class=\"multiple\" size=\"{{tlds|length}}\" {% if product.config.free_domain %}required{% endif %}>
                {% for id,tld in tlds %}
                <option value=\"{{tld.tld}}\" {% if tld.tld in product.config.free_tlds %}selected=\"selected\"{% endif %}>{{tld.tld }}</option>
                {% endfor %}
            </select>
        </div>
        <div class=\"fix\"></div>
    </div>
\t\t<!-- Select periods to offer free domains -->
\t   <div class=\"rowElem free-periods-row\">
        <label>{% trans 'Select free periods' %}</label>
        <div class=\"formRight\">
            <select name=\"config[free_domain_periods][]\" multiple=\"multiple\" class=\"multiple\" size=\"8\" {% if product.config.free_domain %}required{% endif %}>
                <option value=\"1M\" {% if \"1M\" in product.config.free_domain_periods %}selected=\"selected\"{% endif %}>1M</option>
\t\t\t\t<option value=\"3M\" {% if \"3M\" in product.config.free_domain_periods %}selected=\"selected\"{% endif %}>3M</option>
\t\t\t\t<option value=\"6M\" {% if \"6M\" in product.config.free_domain_periods %}selected=\"selected\"{% endif %}>6M</option>
\t\t\t\t<option value=\"1Y\" {% if \"1Y\" in product.config.free_domain_periods %}selected=\"selected\"{% endif %}>1Y</option>
\t\t\t\t<option value=\"2Y\" {% if \"2Y\" in product.config.free_domain_periods %}selected=\"selected\"{% endif %}>2Y</option>
\t\t\t\t<option value=\"3Y\" {% if \"3Y\" in product.config.free_domain_periods %}selected=\"selected\"{% endif %}>3Y</option>
              
            </select>
        </div>
        <div class=\"fix\"></div>
    </div>
\t
    <div class=\"rowElem\">
        <label>{% trans 'Free domain transfer' %}:</label>
        <div class=\"formRight\">
            <input type=\"radio\" name=\"config[free_transfer]\" value=\"1\"{% if product.config.free_transfer %} checked=\"checked\"{% endif %}/><label>Yes</label>
            <input type=\"radio\" name=\"config[free_transfer]\" value=\"0\"{% if not product.config.free_transfer %} checked=\"checked\"{% endif %} /><label>No</label>
        </div>
        <div class=\"fix\"></div>
    </div>

    <input type=\"submit\" value=\"{% trans 'Update' %}\" class=\"greyishBtn submitForm\" />
</fieldset>
        
<input type=\"hidden\" name=\"id\" value=\"{{ product.id }}\" />
</form>

<div class=\"help\">
    <h5>{% trans 'Hosting plans' %}</h5>
</div>

<table class=\"tableStatic wide\">
    <thead>
        <tr>
            <td>Title</td>
            <td style=\"width:5%\">&nbsp;</td>
        </tr>
    </thead>
    <tbody>
        {% for id, plan in admin.servicehosting_hp_get_pairs %}
        <tr>
            <td>{{plan}}</td>
            <td class=\"actions\"><a class=\"bb-button btn14\" href=\"{{ '/servicehosting/plan'|alink }}/{{id}}\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a></td>
        </tr>
        {% else %}
        <tr>
            <td colspan=\"2\">{% trans 'The list is empty' %}</td>
        </tr>
        {% endfor %}
    </tbody>
    <tfoot>
        <tr>
            <td colspan=\"2\">
                <a href=\"{{ 'servicehosting#tab-new-plan'|alink }}\" title=\"\" class=\"btnIconLeft mr10 mt5\"><img src=\"images/icons/dark/settings2.png\" alt=\"\" class=\"icon\"><span>New hosting plan</span></a>
            </td>
        </tr>
    </tfoot>
</table>

<div class=\"help\">
    <h5>{% trans 'Servers' %}</h5>
</div>

<table class=\"tableStatic wide\">
    <thead>
        <tr>
            <td>Title</td>
            <td style=\"width:5%\">&nbsp;</td>
        </tr>
    </thead>
    <tbody>
        {% for id, server in admin.servicehosting_server_get_pairs %}
        <tr>
            <td>{{server}}</td>
            <td class=\"actions\"><a class=\"bb-button btn14\" href=\"{{ '/servicehosting/server'|alink }}/{{id}}\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a></td>
        </tr>
        {% else %}
        <tr>
            <td colspan=\"7\">{% trans 'The list is empty' %}</td>
        </tr>
        {% endfor %}
    </tbody>
    <tfoot>
        <tr>
            <td colspan=\"2\">
                <a href=\"{{ 'servicehosting#tab-new-server'|alink }}\" title=\"\" class=\"btnIconLeft mr10 mt5\"><img src=\"images/icons/dark/computer.png\" alt=\"\" class=\"icon\"><span>New server</span></a>
            </td>
        </tr>
    </tfoot>
</table>

<script>
    var free_domain_radios = \$('input:radio[name=\"config[free_domain]\"]');
    var freeTldsRow = \$('.free-tlds-row');
\tvar freePerdsRow = \$('.free-periods-row');

    free_domain_radios.on('click', function(){
        if (\$(this).val() == 1){
            freeTldsRow.fadeIn('slow');
\t\t\tfreePerdsRow.fadeIn('slow');
            \$('select[name=\"config[free_tlds][]\"]').prop('required', true);
            \$('select[name=\"config[free_domain_periods][]\"]').prop('required', true);
        }
        if (\$(this).val() == 0){
            \$('select[name=\"config[free_domain_periods][]\"]').prop('required', false);
            \$('select[name=\"config[free_tlds][]\"]').prop('required', false);
            \$('select[name=\"config[free_tlds][]\"] option:selected').prop('selected', false);
            \$('select[name=\"config[free_domain_periods][]\"] option:selected').prop('selected', false);
            freeTldsRow.fadeOut('slow');
\t\t\tfreePerdsRow.fadeOut('slow');
        }
    });

    if (free_domain_radios.filter('[value=0]:checked').length > 0){
        freeTldsRow.hide();
\t\tfreePerdsRow.hide();
    }


</script>
", "mod_servicehosting_config.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-themes/admin_default/html/mod_servicehosting_config.phtml");
    }
}
