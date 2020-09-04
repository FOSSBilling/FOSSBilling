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

/* mod_client_profile.phtml */
class __TwigTemplate_1b749aa7b9abcafcc5fdfec503d5ef9a9ad3f036ff2b5d96ba7d056492f8acdc extends \Twig\Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->blocks = [
            'meta_title' => [$this, 'block_meta_title'],
            'page_header' => [$this, 'block_page_header'],
            'breadcrumb' => [$this, 'block_breadcrumb'],
            'content' => [$this, 'block_content'],
            'js' => [$this, 'block_js'],
        ];
    }

    protected function doGetParent(array $context)
    {
        // line 1
        return $this->loadTemplate(((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ajax", [], "any", false, false, false, 1)) ? ("layout_blank.phtml") : ("layout_default.phtml")), "mod_client_profile.phtml", 1);
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        $this->getParent($context)->display($context, array_merge($this->blocks, $blocks));
    }

    // line 3
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo gettext("Profile details");
    }

    // line 4
    public function block_page_header($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo gettext("User profile settings");
    }

    // line 5
    public function block_breadcrumb($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo " <li class=\"active\">";
        echo gettext("Profile");
        echo "</li>";
    }

    // line 7
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 8
        echo "
<div class=\"row-fluid\">

<article class=\"span12 data-block\">
<div class=\"data-container\">

<section class=\"tab-content\">

<div class=\"tab-pane active\" id=\"two\">

<!-- Second level tabs -->
<div class=\"tabbable tabs-left\">
    <ul class=\"nav nav-tabs\">
        <li class=\"active\"><a href=\"#tab1\" data-toggle=\"tab\">";
        // line 21
        echo gettext("Details");
        echo "</a></li>
        <li class=\"\"><a href=\"#tab2\" data-toggle=\"tab\">";
        // line 22
        echo gettext("Change Password");
        echo "</a></li>
        <li class=\"\"><a href=\"#tab3\" data-toggle=\"tab\">";
        // line 23
        echo gettext("API key");
        echo "</a></li>
        <li class=\"\"><a href=\"#tab4\" data-toggle=\"tab\">";
        // line 24
        echo gettext("Currency");
        echo "</a></li>
    </ul>

    <div class=\"tab-content\">

        <div class=\"tab-pane active\" id=\"tab1\">
            <header>
                <h1>";
        // line 31
        echo gettext("Update details");
        echo "</h1>
                <p>";
        // line 32
        echo gettext("Keep your personal data up to date.");
        echo "</p>
            </header>
            <form method=\"post\" action=\"\" id=\"profile-update\" class=\"form-horizontal\">
                <fieldset>
                    <div class=\"alert alert-block alert-success\">
                        <div class=\"row\">
                        <div class=\"span3\"><img src=\"";
        // line 38
        echo twig_escape_filter($this->env, twig_gravatar_filter(twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "email", [], "any", false, false, false, 38)), "html", null, true);
        echo "\" alt=\"Gravatar\"></div>
                        <div class=\"span6\">";
        // line 39
        echo gettext("Please register with");
        echo " <strong>";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "email", [], "any", false, false, false, 39), "html", null, true);
        echo "</strong> ";
        echo gettext("at ");
        echo "<a target=\"_blank\" href=\"http://gravatar.com\">Gravatar.com</a> ";
        echo gettext("to change your profile image. Gravatar image updates may not appear immediately.");
        echo "</div>
                        </div>
                    </div>
                        <div class=\"control-group\">
                            <label class=\"control-label\" for=\"input\">";
        // line 43
        echo gettext("Email Address");
        echo "</label>
                            <div class=\"controls\">
                                <input type=\"email\" class=\"input-xlarge\" name=\"email\" value=\"";
        // line 45
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "email", [], "any", false, false, false, 45), "html", null, true);
        echo "\" required=\"required\">
                                <p class=\"help-block\"></p>
                            </div>
                        </div>

                       <div class=\"control-group\">
                           <label class=\"control-label\" for=\"input\">";
        // line 51
        echo gettext("First Name");
        echo "</label>
                           <div class=\"controls\">
                                <input type=\"text\" name=\"first_name\" value=\"";
        // line 53
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "first_name", [], "any", false, false, false, 53), "html", null, true);
        echo "\" required=\"required\">
                                <p class=\"help-block\"></p>
                           </div>
                       </div>

                       <div class=\"control-group\">
                           <label class=\"control-label\" for=\"input\">";
        // line 59
        echo gettext("Last Name");
        echo "</label>
                           <div class=\"controls\">
                                <input type=\"text\" name=\"last_name\" value=\"";
        // line 61
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "last_name", [], "any", false, false, false, 61), "html", null, true);
        echo "\" required=\"required\">
                               <p class=\"help-block\"></p>
                           </div>
                       </div>

                       <div class=\"control-group\">
                           <label class=\"control-label\" for=\"input\">";
        // line 67
        echo gettext("Birth date");
        echo "</label>
                           <div class=\"controls\">
                                <input type=\"date\" name=\"birthday\" value=\"";
        // line 69
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "birthday", [], "any", false, false, false, 69), "html", null, true);
        echo "\" >
                               <p class=\"help-block\"></p>
                           </div>
                       </div>

                       <div class=\"control-group\">
                           <label class=\"control-label\" for=\"input\">";
        // line 75
        echo gettext("Company Name");
        echo "</label>
                           <div class=\"controls\">
                                <input type=\"text\" name=\"company\" value=\"";
        // line 77
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "company", [], "any", false, false, false, 77), "html", null, true);
        echo "\">
                               <p class=\"help-block\"></p>
                           </div>
                       </div>

                    <div class=\"control-group\">
                           <label class=\"control-label\" for=\"input\">";
        // line 83
        echo gettext("Company VAT");
        echo "</label>
                           <div class=\"controls\">
                                <input type=\"text\" name=\"company_vat\" value=\"";
        // line 85
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "company_vat", [], "any", false, false, false, 85), "html", null, true);
        echo "\">
                               <p class=\"help-block\"></p>
                           </div>
                       </div>

                    <div class=\"control-group\">
                           <label class=\"control-label\" for=\"input\">";
        // line 91
        echo gettext("Company Number");
        echo "</label>
                           <div class=\"controls\">
                                <input type=\"text\" name=\"company_number\" value=\"";
        // line 93
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "company_number", [], "any", false, false, false, 93), "html", null, true);
        echo "\">
                               <p class=\"help-block\"></p>
                           </div>
                       </div>

                        <div class=\"control-group\">
                            <label class=\"control-label\" for=\"input\">";
        // line 99
        echo gettext("Phone Country Code");
        echo "</label>
                            <div class=\"controls\">
                                <input type=\"text\" name=\"phone_cc\" value=\"";
        // line 101
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "phone_cc", [], "any", false, false, false, 101), "html", null, true);
        echo "\" required=\"required\">
                                <p class=\"help-block\"></p>
                            </div>
                        </div>

                       <div class=\"control-group\">
                           <label class=\"control-label\" for=\"input\">";
        // line 107
        echo gettext("Phone Number");
        echo "</label>
                           <div class=\"controls\">
                                <input type=\"text\" name=\"phone\" value=\"";
        // line 109
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "phone", [], "any", false, false, false, 109), "html", null, true);
        echo "\" required=\"required\">
                               <p class=\"help-block\"></p>
                           </div>
                       </div>

                        <div class=\"control-group\">
                            <label class=\"control-label\" for=\"input\">";
        // line 115
        echo gettext("Address");
        echo "</label>
                            <div class=\"controls\">
                                <input type=\"text\" name=\"address_1\" value=\"";
        // line 117
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "address_1", [], "any", false, false, false, 117), "html", null, true);
        echo "\" required=\"required\">
                                <p class=\"help-block\"></p>
                            </div>
                        </div>

                       <div class=\"control-group\">
                           <label class=\"control-label\" for=\"input\">";
        // line 123
        echo gettext("Address 2");
        echo "</label>
                           <div class=\"controls\">
                                <input type=\"text\" name=\"address_2\" value=\"";
        // line 125
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "address_2", [], "any", false, false, false, 125), "html", null, true);
        echo "\">
                               <p class=\"help-block\"></p>
                           </div>
                       </div>

                        <div class=\"control-group\">
                            <label class=\"control-label\" for=\"input\">";
        // line 131
        echo gettext("City");
        echo "</label>
                            <div class=\"controls\">
                                <input type=\"text\" name=\"city\" value=\"";
        // line 133
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "city", [], "any", false, false, false, 133), "html", null, true);
        echo "\" required=\"required\">
                                <p class=\"help-block\"></p>
                            </div>
                        </div>

                        <div class=\"control-group\">
                            <label class=\"control-label\" for=\"input\">";
        // line 139
        echo gettext("Country");
        echo "</label>
                            <div class=\"controls\">
                                <select name=\"country\" required=\"required\">
                                <option value=\"\">";
        // line 142
        echo gettext("-- Select country --");
        echo "</option>
                                ";
        // line 143
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_countries", [], "any", false, false, false, 143));
        foreach ($context['_seq'] as $context["val"] => $context["label"]) {
            // line 144
            echo "                                <option value=\"";
            echo twig_escape_filter($this->env, $context["val"], "html", null, true);
            echo "\" label=\"";
            echo twig_escape_filter($this->env, $context["label"]);
            echo "\" ";
            if (($context["val"] == twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "country", [], "any", false, false, false, 144))) {
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
        // line 146
        echo "                                </select>
                                <p class=\"help-block\"></p>
                            </div>
                        </div>

                       <div class=\"control-group\">
                           <label class=\"control-label\" for=\"input\">";
        // line 152
        echo gettext("State");
        echo "</label>
                           <div class=\"controls\">
                                ";
        // line 155
        echo "                                <input type=\"text\" name=\"state\" value=\"";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "state", [], "any", false, false, false, 155), "html", null, true);
        echo "\" />
                               <p class=\"help-block\"></p>
                           </div>
                       </div>

                      <div class=\"control-group\">
                          <label class=\"control-label\" for=\"input\">";
        // line 161
        echo gettext("Zip/Postal Code");
        echo "</label>
                          <div class=\"controls\">
                                <input type=\"text\" name=\"postcode\" value=\"";
        // line 163
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "postcode", [], "any", false, false, false, 163), "html", null, true);
        echo "\" required=\"required\">
                              <p class=\"help-block\"></p>
                          </div>
                      </div>

                      <div class=\"control-group\">
                          <label class=\"control-label\" for=\"input\">";
        // line 169
        echo gettext("Passport number");
        echo "</label>
                          <div class=\"controls\">
                                <input type=\"text\" name=\"document_nr\" value=\"";
        // line 171
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "document_nr", [], "any", false, false, false, 171), "html", null, true);
        echo "\" >
                              <p class=\"help-block\"></p>
                          </div>
                      </div>

                    <div class=\"form-actions\">
                        <button class=\"btn btn-alt btn-large btn-primary\" type=\"submit\">";
        // line 177
        echo gettext("Update profile");
        echo "</button>
                    </div>
                </fieldset>
            </form>
        </div>
        <div class=\"tab-pane\" id=\"tab2\">
            <header>
                <h1>";
        // line 184
        echo gettext("New password");
        echo "</h1>
                <p>";
        // line 185
        echo gettext("Please enter new password two times in order avoid mistypes");
        echo "</p>
            </header>
            <form method=\"post\" action=\"\" id=\"change-password\" class=\"form-horizontal\">
                <fieldset>
                        <div class=\"control-group\">
                            <label class=\"control-label\" for=\"input\">";
        // line 190
        echo gettext("Password");
        echo "</label>
                            <div class=\"controls\">
                                <input type=\"password\" name=\"password\" value=\"\" required=\"required\">
                                <p class=\"help-block\"></p>
                            </div>
                        </div>

                        <div class=\"control-group\">
                            <label class=\"control-label\" for=\"input\">";
        // line 198
        echo gettext("Password confirm");
        echo "</label>
                            <div class=\"controls\">
                                <input type=\"password\" name=\"password_confirm\" value=\"\" required=\"required\">
                                <p class=\"help-block\"></p>
                            </div>
                        </div>
                    <div class=\"form-actions\">
                        <button class=\"btn btn-alt btn-large btn-primary\" type=\"submit\">";
        // line 205
        echo gettext("Update password");
        echo "</button>
                    </div>                </fieldset>
            </form>        </div>
        <div class=\"tab-pane\" id=\"tab3\">
            <header>
                <h1>";
        // line 210
        echo gettext("API key");
        echo "</h1>
                <p>";
        // line 211
        echo gettext("API key allows integration with external applications. You will need this key for authentication.");
        echo "</p>
            </header>
            <form method=\"post\" action=\"\" id=\"change-api-key\" class=\"form-horizontal\">
                <fieldset>
                    <div class=\"alert alert-block\">
                     <h4><p>";
        // line 216
        echo gettext("Warning! Resetting the key will break existing applications using it!");
        echo "</p></h4>
                    </div>
                    <div class=\"control-group\">
                        <label class=\"control-label\" for=\"api-key\">";
        // line 219
        echo gettext("Your API key");
        echo ": </label>
                        <div class=\"controls\">
                        <input type=\"text\" value=\"";
        // line 221
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "profile_api_key_get", [], "any", false, false, false, 221), "html", null, true);
        echo "\" class=\"input-xlarge\" id=\"api-key\">
                        </div>
                        <div class=\"form-actions\">
                            <button class=\"btn btn-alt btn-large btn-primary\" type=\"submit\">";
        // line 224
        echo gettext("Reset key");
        echo "</button>
                        </div>                    </div>
                </fieldset>
            </form>        </div>
        <div class=\"tab-pane\" id=\"tab4\">
            <header>
                <h1>";
        // line 230
        echo gettext("Currency");
        echo "</h1>
                <p>";
        // line 231
        echo gettext("Your profile currency is defined after your first order. Once your currency is set, all your profile accounting will be managed in that currency and can not be changed.");
        echo "</p>
            </header>
                ";
        // line 233
        if (twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "currency", [], "any", false, false, false, 233)) {
            // line 234
            echo "                <p>";
            echo gettext("Your profile currency is");
            echo " <strong>";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "currency", [], "any", false, false, false, 234), "html", null, true);
            echo "</strong></p>
                <p>";
            // line 235
            echo gettext("Create new client profile if you want to manage your money in other currency");
            echo "</p>
                ";
        }
        // line 237
        echo "
        </div>
    </div>
</div>

</div>

</section>
</div>
</article>

</div>

";
    }

    // line 253
    public function block_js($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 254
        echo "<script type=\"text/javascript\">
\$(function() {

    \$('#change-password').bind('submit',function(event){
        bb.post(
            'client/client/change_password',
            \$(this).serialize(),
            function(result) {
                bb.msg('Password was changed');
            }
        );
        return false;
    });
    
    \$('#change-api-key').submit(function(event){

        bb.post(
            'client/client/api_key_reset',
            \$(this).serialize(),
            function(result) {
                \$('#api-key').val(result);
                bb.msg('API key was changed');
            }
        );

        return false;
    });

    \$('#profile-update').bind('submit',function(event){
        bb.post(
            'client/client/update',
            \$(this).serialize(),
            function(result) {
                bb.msg('Profile updated');
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
        return "mod_client_profile.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  526 => 254,  522 => 253,  505 => 237,  500 => 235,  493 => 234,  491 => 233,  486 => 231,  482 => 230,  473 => 224,  467 => 221,  462 => 219,  456 => 216,  448 => 211,  444 => 210,  436 => 205,  426 => 198,  415 => 190,  407 => 185,  403 => 184,  393 => 177,  384 => 171,  379 => 169,  370 => 163,  365 => 161,  355 => 155,  350 => 152,  342 => 146,  325 => 144,  321 => 143,  317 => 142,  311 => 139,  302 => 133,  297 => 131,  288 => 125,  283 => 123,  274 => 117,  269 => 115,  260 => 109,  255 => 107,  246 => 101,  241 => 99,  232 => 93,  227 => 91,  218 => 85,  213 => 83,  204 => 77,  199 => 75,  190 => 69,  185 => 67,  176 => 61,  171 => 59,  162 => 53,  157 => 51,  148 => 45,  143 => 43,  130 => 39,  126 => 38,  117 => 32,  113 => 31,  103 => 24,  99 => 23,  95 => 22,  91 => 21,  76 => 8,  72 => 7,  63 => 5,  56 => 4,  49 => 3,  39 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}

{% block meta_title %}{% trans 'Profile details' %}{% endblock %}
{% block page_header %}{% trans 'User profile settings' %}{% endblock %}
{% block breadcrumb %} <li class=\"active\">{% trans 'Profile' %}</li>{% endblock %}

{% block content %}

<div class=\"row-fluid\">

<article class=\"span12 data-block\">
<div class=\"data-container\">

<section class=\"tab-content\">

<div class=\"tab-pane active\" id=\"two\">

<!-- Second level tabs -->
<div class=\"tabbable tabs-left\">
    <ul class=\"nav nav-tabs\">
        <li class=\"active\"><a href=\"#tab1\" data-toggle=\"tab\">{% trans 'Details' %}</a></li>
        <li class=\"\"><a href=\"#tab2\" data-toggle=\"tab\">{% trans 'Change Password' %}</a></li>
        <li class=\"\"><a href=\"#tab3\" data-toggle=\"tab\">{% trans 'API key' %}</a></li>
        <li class=\"\"><a href=\"#tab4\" data-toggle=\"tab\">{% trans 'Currency' %}</a></li>
    </ul>

    <div class=\"tab-content\">

        <div class=\"tab-pane active\" id=\"tab1\">
            <header>
                <h1>{% trans 'Update details' %}</h1>
                <p>{% trans 'Keep your personal data up to date.' %}</p>
            </header>
            <form method=\"post\" action=\"\" id=\"profile-update\" class=\"form-horizontal\">
                <fieldset>
                    <div class=\"alert alert-block alert-success\">
                        <div class=\"row\">
                        <div class=\"span3\"><img src=\"{{ profile.email|gravatar }}\" alt=\"Gravatar\"></div>
                        <div class=\"span6\">{% trans 'Please register with'%} <strong>{{ profile.email }}</strong> {% trans 'at ' %}<a target=\"_blank\" href=\"http://gravatar.com\">Gravatar.com</a> {% trans 'to change your profile image. Gravatar image updates may not appear immediately.' %}</div>
                        </div>
                    </div>
                        <div class=\"control-group\">
                            <label class=\"control-label\" for=\"input\">{% trans 'Email Address' %}</label>
                            <div class=\"controls\">
                                <input type=\"email\" class=\"input-xlarge\" name=\"email\" value=\"{{ profile.email }}\" required=\"required\">
                                <p class=\"help-block\"></p>
                            </div>
                        </div>

                       <div class=\"control-group\">
                           <label class=\"control-label\" for=\"input\">{% trans 'First Name' %}</label>
                           <div class=\"controls\">
                                <input type=\"text\" name=\"first_name\" value=\"{{ profile.first_name }}\" required=\"required\">
                                <p class=\"help-block\"></p>
                           </div>
                       </div>

                       <div class=\"control-group\">
                           <label class=\"control-label\" for=\"input\">{% trans 'Last Name' %}</label>
                           <div class=\"controls\">
                                <input type=\"text\" name=\"last_name\" value=\"{{ profile.last_name }}\" required=\"required\">
                               <p class=\"help-block\"></p>
                           </div>
                       </div>

                       <div class=\"control-group\">
                           <label class=\"control-label\" for=\"input\">{% trans 'Birth date' %}</label>
                           <div class=\"controls\">
                                <input type=\"date\" name=\"birthday\" value=\"{{ profile.birthday }}\" >
                               <p class=\"help-block\"></p>
                           </div>
                       </div>

                       <div class=\"control-group\">
                           <label class=\"control-label\" for=\"input\">{% trans 'Company Name' %}</label>
                           <div class=\"controls\">
                                <input type=\"text\" name=\"company\" value=\"{{ profile.company }}\">
                               <p class=\"help-block\"></p>
                           </div>
                       </div>

                    <div class=\"control-group\">
                           <label class=\"control-label\" for=\"input\">{% trans 'Company VAT' %}</label>
                           <div class=\"controls\">
                                <input type=\"text\" name=\"company_vat\" value=\"{{ profile.company_vat }}\">
                               <p class=\"help-block\"></p>
                           </div>
                       </div>

                    <div class=\"control-group\">
                           <label class=\"control-label\" for=\"input\">{% trans 'Company Number' %}</label>
                           <div class=\"controls\">
                                <input type=\"text\" name=\"company_number\" value=\"{{ profile.company_number }}\">
                               <p class=\"help-block\"></p>
                           </div>
                       </div>

                        <div class=\"control-group\">
                            <label class=\"control-label\" for=\"input\">{% trans 'Phone Country Code' %}</label>
                            <div class=\"controls\">
                                <input type=\"text\" name=\"phone_cc\" value=\"{{ profile.phone_cc }}\" required=\"required\">
                                <p class=\"help-block\"></p>
                            </div>
                        </div>

                       <div class=\"control-group\">
                           <label class=\"control-label\" for=\"input\">{% trans 'Phone Number' %}</label>
                           <div class=\"controls\">
                                <input type=\"text\" name=\"phone\" value=\"{{ profile.phone }}\" required=\"required\">
                               <p class=\"help-block\"></p>
                           </div>
                       </div>

                        <div class=\"control-group\">
                            <label class=\"control-label\" for=\"input\">{% trans 'Address' %}</label>
                            <div class=\"controls\">
                                <input type=\"text\" name=\"address_1\" value=\"{{ profile.address_1 }}\" required=\"required\">
                                <p class=\"help-block\"></p>
                            </div>
                        </div>

                       <div class=\"control-group\">
                           <label class=\"control-label\" for=\"input\">{% trans 'Address 2' %}</label>
                           <div class=\"controls\">
                                <input type=\"text\" name=\"address_2\" value=\"{{ profile.address_2 }}\">
                               <p class=\"help-block\"></p>
                           </div>
                       </div>

                        <div class=\"control-group\">
                            <label class=\"control-label\" for=\"input\">{% trans 'City' %}</label>
                            <div class=\"controls\">
                                <input type=\"text\" name=\"city\" value=\"{{ profile.city }}\" required=\"required\">
                                <p class=\"help-block\"></p>
                            </div>
                        </div>

                        <div class=\"control-group\">
                            <label class=\"control-label\" for=\"input\">{% trans 'Country' %}</label>
                            <div class=\"controls\">
                                <select name=\"country\" required=\"required\">
                                <option value=\"\">{% trans '-- Select country --' %}</option>
                                {% for val,label in guest.system_countries %}
                                <option value=\"{{ val }}\" label=\"{{ label|e }}\" {% if val == profile.country %}selected=\"selected\"{% endif %}>{{ label|e }}</option>
                                {% endfor %}
                                </select>
                                <p class=\"help-block\"></p>
                            </div>
                        </div>

                       <div class=\"control-group\">
                           <label class=\"control-label\" for=\"input\">{% trans 'State' %}</label>
                           <div class=\"controls\">
                                {# mf.selectbox('state', guest.system_states, profile.state, 0, 'Select state') #}
                                <input type=\"text\" name=\"state\" value=\"{{ profile.state }}\" />
                               <p class=\"help-block\"></p>
                           </div>
                       </div>

                      <div class=\"control-group\">
                          <label class=\"control-label\" for=\"input\">{% trans 'Zip/Postal Code' %}</label>
                          <div class=\"controls\">
                                <input type=\"text\" name=\"postcode\" value=\"{{ profile.postcode }}\" required=\"required\">
                              <p class=\"help-block\"></p>
                          </div>
                      </div>

                      <div class=\"control-group\">
                          <label class=\"control-label\" for=\"input\">{% trans 'Passport number' %}</label>
                          <div class=\"controls\">
                                <input type=\"text\" name=\"document_nr\" value=\"{{ profile.document_nr}}\" >
                              <p class=\"help-block\"></p>
                          </div>
                      </div>

                    <div class=\"form-actions\">
                        <button class=\"btn btn-alt btn-large btn-primary\" type=\"submit\">{% trans 'Update profile' %}</button>
                    </div>
                </fieldset>
            </form>
        </div>
        <div class=\"tab-pane\" id=\"tab2\">
            <header>
                <h1>{% trans 'New password' %}</h1>
                <p>{% trans 'Please enter new password two times in order avoid mistypes' %}</p>
            </header>
            <form method=\"post\" action=\"\" id=\"change-password\" class=\"form-horizontal\">
                <fieldset>
                        <div class=\"control-group\">
                            <label class=\"control-label\" for=\"input\">{% trans 'Password' %}</label>
                            <div class=\"controls\">
                                <input type=\"password\" name=\"password\" value=\"\" required=\"required\">
                                <p class=\"help-block\"></p>
                            </div>
                        </div>

                        <div class=\"control-group\">
                            <label class=\"control-label\" for=\"input\">{% trans 'Password confirm' %}</label>
                            <div class=\"controls\">
                                <input type=\"password\" name=\"password_confirm\" value=\"\" required=\"required\">
                                <p class=\"help-block\"></p>
                            </div>
                        </div>
                    <div class=\"form-actions\">
                        <button class=\"btn btn-alt btn-large btn-primary\" type=\"submit\">{% trans 'Update password' %}</button>
                    </div>                </fieldset>
            </form>        </div>
        <div class=\"tab-pane\" id=\"tab3\">
            <header>
                <h1>{% trans 'API key' %}</h1>
                <p>{% trans 'API key allows integration with external applications. You will need this key for authentication.' %}</p>
            </header>
            <form method=\"post\" action=\"\" id=\"change-api-key\" class=\"form-horizontal\">
                <fieldset>
                    <div class=\"alert alert-block\">
                     <h4><p>{% trans 'Warning! Resetting the key will break existing applications using it!' %}</p></h4>
                    </div>
                    <div class=\"control-group\">
                        <label class=\"control-label\" for=\"api-key\">{% trans 'Your API key' %}: </label>
                        <div class=\"controls\">
                        <input type=\"text\" value=\"{{ client.profile_api_key_get }}\" class=\"input-xlarge\" id=\"api-key\">
                        </div>
                        <div class=\"form-actions\">
                            <button class=\"btn btn-alt btn-large btn-primary\" type=\"submit\">{% trans 'Reset key' %}</button>
                        </div>                    </div>
                </fieldset>
            </form>        </div>
        <div class=\"tab-pane\" id=\"tab4\">
            <header>
                <h1>{% trans 'Currency' %}</h1>
                <p>{% trans 'Your profile currency is defined after your first order. Once your currency is set, all your profile accounting will be managed in that currency and can not be changed.' %}</p>
            </header>
                {% if profile.currency %}
                <p>{% trans 'Your profile currency is' %} <strong>{{ profile.currency }}</strong></p>
                <p>{% trans 'Create new client profile if you want to manage your money in other currency' %}</p>
                {% endif %}

        </div>
    </div>
</div>

</div>

</section>
</div>
</article>

</div>

{% endblock %}


{% block js %}
<script type=\"text/javascript\">
\$(function() {

    \$('#change-password').bind('submit',function(event){
        bb.post(
            'client/client/change_password',
            \$(this).serialize(),
            function(result) {
                bb.msg('Password was changed');
            }
        );
        return false;
    });
    
    \$('#change-api-key').submit(function(event){

        bb.post(
            'client/client/api_key_reset',
            \$(this).serialize(),
            function(result) {
                \$('#api-key').val(result);
                bb.msg('API key was changed');
            }
        );

        return false;
    });

    \$('#profile-update').bind('submit',function(event){
        bb.post(
            'client/client/update',
            \$(this).serialize(),
            function(result) {
                bb.msg('Profile updated');
            }
        );
        return false;
    });

});
</script>
{% endblock %}", "mod_client_profile.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-modules/Client/html_client/mod_client_profile.phtml");
    }
}
