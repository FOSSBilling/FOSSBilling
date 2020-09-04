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

/* mod_page_signup.phtml */
class __TwigTemplate_59d77107b2f15dc8fa85eba346f1a7e55614d7908a391f8b88b944d864a560ef extends \Twig\Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->blocks = [
            'meta_title' => [$this, 'block_meta_title'],
            'body' => [$this, 'block_body'],
            'js' => [$this, 'block_js'],
        ];
    }

    protected function doGetParent(array $context)
    {
        // line 1
        return "layout_public.phtml";
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 3
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_page_signup.phtml", 3)->unwrap();
        // line 1
        $this->parent = $this->loadTemplate("layout_public.phtml", "mod_page_signup.phtml", 1);
        $this->parent->display($context, array_merge($this->blocks, $blocks));
    }

    // line 5
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo gettext("Sign up");
    }

    // line 6
    public function block_body($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 7
        echo "
<section class=\"container login\" role=\"main\">

    ";
        // line 10
        if (twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "login_page_show_logo", [], "any", false, false, false, 10)) {
            // line 11
            echo "    ";
            $context["company"] = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_company", [], "any", false, false, false, 11);
            // line 12
            echo "    <h1 style=\"text-align: center\">
        ";
            // line 13
            if (twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "login_page_show_logo", [], "any", false, false, false, 13)) {
                // line 14
                echo "        <a href=\"";
                echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "login_page_logo_url", [], "any", true, true, false, 14)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "login_page_logo_url", [], "any", false, false, false, 14), "/")) : ("/")), "html", null, true);
                echo "\" target=\"_blank\"><img src=\"";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_company", [], "any", false, false, false, 14), "logo_url", [], "any", false, false, false, 14), "html", null, true);
                echo "\" alt=\"";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_company", [], "any", false, false, false, 14), "name", [], "any", false, false, false, 14), "html", null, true);
                echo "\"/></a>
        ";
            }
            // line 16
            echo "    </h1>
    ";
        }
        // line 18
        echo "    <div class=\"data-block\">
        <div class=\"data-container\">

            <form method=\"post\" action=\"\" id=\"client-signup\">
                <div class=\"alert alert-info\" style=\"display: none\" id=\"account-created-info-block\">
                    <button class=\"close\" data-dismiss=\"alert\">×</button>
                    <strong>";
        // line 24
        echo gettext("Account has been created");
        echo ".</strong> ";
        echo gettext("Please check your mailbox and confirm email address");
        echo ".
                </div>
                <fieldset>
                    ";
        // line 27
        $context["r"] = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "client_required", [], "any", false, false, false, 27);
        // line 28
        echo "                    <div class=\"control-group\">
                        <label class=\"control-label\" for=\"reg-email\">";
        // line 29
        echo gettext("Email Address");
        echo "</label>
                        <div class=\"controls\">
                            <input type=\"text\" name=\"email\" value=\"";
        // line 31
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "email", [], "any", false, false, false, 31), "html", null, true);
        echo "\" required=\"required\" id=\"reg-email\">
                            <p class=\"help-block\"></p>
                        </div>
                    </div>

                    <div class=\"control-group\">
                        <label class=\"control-label\" for=\"first-name\">";
        // line 37
        echo gettext("First Name");
        echo "</label>
                        <div class=\"controls\">
                            <input type=\"text\" name=\"first_name\" id=\"first-name\" value=\"";
        // line 39
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "first_name", [], "any", false, false, false, 39), "html", null, true);
        echo "\" required=\"required\">
                            <p class=\"help-block\"></p>
                        </div>
                    </div>


                    ";
        // line 45
        if (twig_in_filter("last_name", ($context["r"] ?? null))) {
            // line 46
            echo "                    <div class=\"control-group\">
                        <label class=\"control-label\" for=\"last_name\">";
            // line 47
            echo gettext("Last Name");
            echo "</label>
                        <div class=\"controls\">
                            <input type=\"text\" name=\"last_name\" id=\"last_name\" value=\"";
            // line 49
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "last_name", [], "any", false, false, false, 49), "html", null, true);
            echo "\" required=\"required\">
                            <p class=\"help-block\"></p>
                        </div>
                    </div>
                    ";
        }
        // line 54
        echo "

                    ";
        // line 56
        if (twig_in_filter("company", ($context["r"] ?? null))) {
            // line 57
            echo "                    <div class=\"control-group\">
                        <label class=\"control-label\" for=\"company\">";
            // line 58
            echo gettext("Company");
            echo "</label>
                        <div class=\"controls\">
                            <input type=\"text\" name=\"company\" id=\"company\" value=\"";
            // line 60
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "company", [], "any", false, false, false, 60), "html", null, true);
            echo "\" required=\"required\">
                            <p class=\"help-block\"></p>
                        </div>
                    </div>
                    ";
        }
        // line 65
        echo "
                    ";
        // line 66
        if (twig_in_filter("birthday", ($context["r"] ?? null))) {
            // line 67
            echo "                    <div class=\"control-group\">
                        <label class=\"control-label\" for=\"birthday\">";
            // line 68
            echo gettext("Birthday");
            echo "</label>
                        <div class=\"controls\">
                            <input type=\"text\" name=\"birthday\" id=\"birthday\" value=\"\">
                            <p class=\"help-block\"></p>
                        </div>
                    </div>
                    ";
        }
        // line 75
        echo "
                    ";
        // line 76
        if (twig_in_filter("gender", ($context["r"] ?? null))) {
            // line 77
            echo "                    <div class=\"control-group\">
                        <label class=\"control-label\" for=\"gender\">";
            // line 78
            echo gettext("You are");
            echo "</label>
                        <div class=\"controls\">
                            <select name=\"gender\" id=\"gender\">
                                <option value=\"male\">Male</option>
                                <option value=\"female\">Female</option>
                            </select>
                            <p class=\"help-block\"></p>
                        </div>
                    </div>
                    ";
        }
        // line 88
        echo "
                    ";
        // line 89
        if (twig_in_filter("address_1", ($context["r"] ?? null))) {
            // line 90
            echo "                    <div class=\"control-group\">
                        <label class=\"control-label\" for=\"address_1\">";
            // line 91
            echo gettext("Address");
            echo "</label>
                        <div class=\"controls\">
                            <input type=\"text\" name=\"address_1\" id=\"address_1\" value=\"";
            // line 93
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "address_1", [], "any", false, false, false, 93), "html", null, true);
            echo "\">
                            <p class=\"help-block\"></p>
                        </div>
                    </div>
                    ";
        }
        // line 98
        echo "
                    ";
        // line 99
        if (twig_in_filter("address_2", ($context["r"] ?? null))) {
            // line 100
            echo "                    <div class=\"control-group\">
                        <label class=\"control-label\" for=\"address_2\">";
            // line 101
            echo gettext("Address 2");
            echo "</label>
                        <div class=\"controls\">
                            <input type=\"text\" name=\"address_2\" id=\"address_2\" value=\"";
            // line 103
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "address_2", [], "any", false, false, false, 103), "html", null, true);
            echo "\">
                            <p class=\"help-block\"></p>
                        </div>
                    </div>
                    ";
        }
        // line 108
        echo "
                    ";
        // line 109
        if (twig_in_filter("city", ($context["r"] ?? null))) {
            // line 110
            echo "                    <div class=\"control-group\">
                        <label class=\"control-label\" for=\"city\">";
            // line 111
            echo gettext("City");
            echo "</label>
                        <div class=\"controls\">
                            <input type=\"text\" name=\"city\" id=\"city\" value=\"";
            // line 113
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "city", [], "any", false, false, false, 113), "html", null, true);
            echo "\">
                            <p class=\"help-block\"></p>
                        </div>
                    </div>
                    ";
        }
        // line 118
        echo "
                    ";
        // line 119
        if (twig_in_filter("country", ($context["r"] ?? null))) {
            // line 120
            echo "                    <div class=\"control-group\">
                        <label class=\"control-label\" for=\"country\">";
            // line 121
            echo gettext("Country");
            echo "</label>
                        <div class=\"controls\">
                            <select name=\"country\" required=\"required\">
                                <option value=\"\">";
            // line 124
            echo gettext("-- Select country --");
            echo "</option>
                                ";
            // line 125
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_countries", [], "any", false, false, false, 125));
            foreach ($context['_seq'] as $context["val"] => $context["label"]) {
                // line 126
                echo "                                <option value=\"";
                echo twig_escape_filter($this->env, $context["val"], "html", null, true);
                echo "\" label=\"";
                echo twig_escape_filter($this->env, $context["label"]);
                echo "\">";
                echo twig_escape_filter($this->env, $context["label"]);
                echo "</option>
                                ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['val'], $context['label'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 128
            echo "                            </select>
                            <p class=\"help-block\"></p>
                        </div>
                    </div>
                    ";
        }
        // line 133
        echo "
                    ";
        // line 134
        if (twig_in_filter("state", ($context["r"] ?? null))) {
            // line 135
            echo "                    <div class=\"control-group\">
                        <label class=\"control-label\" for=\"state\">";
            // line 136
            echo gettext("State");
            echo "</label>
                        <div class=\"controls\">
                            ";
            // line 139
            echo "                            <input type=\"text\" name=\"state\" id=\"state\" value=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "state", [], "any", false, false, false, 139), "html", null, true);
            echo "\" />
                            <p class=\"help-block\"></p>
                        </div>
                    </div>
                    ";
        }
        // line 144
        echo "
                    ";
        // line 145
        if (twig_in_filter("postcode", ($context["r"] ?? null))) {
            // line 146
            echo "                    <div class=\"control-group\">
                        <label class=\"control-label\" for=\"postcode\">";
            // line 147
            echo gettext("Zip/Postal Code");
            echo "</label>
                        <div class=\"controls\">
                            <input type=\"text\" name=\"postcode\" id=\"postcode\" value=\"";
            // line 149
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "postcode", [], "any", false, false, false, 149), "html", null, true);
            echo "\">
                            <p class=\"help-block\"></p>
                        </div>
                    </div>
                    ";
        }
        // line 154
        echo "
                    ";
        // line 155
        if (twig_in_filter("phone", ($context["r"] ?? null))) {
            // line 156
            echo "                    <div class=\"control-group\">
                        <label class=\"control-label\" for=\"phone\">";
            // line 157
            echo gettext("Phone Number");
            echo "</label>
                            <div class=\"input-prepend\">
                                <input type=\"text\" name=\"phone_cc\" value=\"\" style=\"width:20%\">
                                <input id=\"phone\" type=\"text\" name=\"phone\" value=\"";
            // line 160
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "phone", [], "any", false, false, false, 160), "html", null, true);
            echo "\" style=\"width:70%\">
                            </div>
                            <p class=\"help-block\"></p>
                    </div>
                    ";
        }
        // line 165
        echo "
                    ";
        // line 166
        $context["custom_fields"] = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "client_custom_fields", [], "any", false, false, false, 166);
        // line 167
        echo "                    ";
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(($context["custom_fields"] ?? null));
        foreach ($context['_seq'] as $context["field_name"] => $context["field"]) {
            // line 168
            echo "                        ";
            if (twig_get_attribute($this->env, $this->source, $context["field"], "active", [], "any", false, false, false, 168)) {
                // line 169
                echo "                            <div class=\"control-group\">
                                <label class=\"control-label\" for=\"";
                // line 170
                echo twig_escape_filter($this->env, $context["field_name"], "html", null, true);
                echo "\">";
                if ( !twig_test_empty(twig_get_attribute($this->env, $this->source, $context["field"], "title", [], "any", false, false, false, 170))) {
                    echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["field"], "title", [], "any", false, false, false, 170), "html", null, true);
                } else {
                    echo " ";
                    echo twig_escape_filter($this->env, twig_capitalize_string_filter($this->env, $context["field_name"]), "html", null, true);
                    echo " ";
                }
                echo "</label>
                                <div class=\"controls\">
                                    <input type=\"text\" name=\"";
                // line 172
                echo twig_escape_filter($this->env, $context["field_name"], "html", null, true);
                echo "\" id=\"";
                echo twig_escape_filter($this->env, $context["field_name"], "html", null, true);
                echo "\" value=\"";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), $context["field_name"], [], "any", false, false, false, 172), "html", null, true);
                echo "\" ";
                if (twig_get_attribute($this->env, $this->source, $context["field"], "required", [], "any", false, false, false, 172)) {
                    echo "required=\"required\"";
                }
                echo ">
                                    <p class=\"help-block\"></p>
                                </div>
                            </div>
                        ";
            }
            // line 177
            echo "                    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['field_name'], $context['field'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 178
        echo "

                    <div class=\"control-group\">
                        <label class=\"control-label\" for=\"reg-password\">";
        // line 181
        echo gettext("Password");
        echo "</label>
                        <div class=\"controls\">
                            <input type=\"password\" name=\"password\" value=\"\" required=\"required\" id=\"reg-password\">
                            <p class=\"help-block\"></p>
                        </div>
                    </div>

                    <div class=\"control-group\">
                        <label class=\"control-label\" for=\"password-confirm\">";
        // line 189
        echo gettext("Password confirm");
        echo "</label>
                        <div class=\"controls\">
                            <input type=\"password\" name=\"password_confirm\" name=\"password-confirm\" value=\"\" required=\"required\">
                            <p class=\"help-block\"></p>
                        </div>
                    </div>

                    ";
        // line 196
        echo twig_call_macro($macros["mf"], "macro_recaptcha", [], 196, $context, $this->getSourceContext());
        echo "

                    <div class=\"form-actions\">
                        <button class=\"btn btn-block btn-large btn-inverse btn-alt\" type=\"submit\">";
        // line 199
        echo gettext("Sign up");
        echo "</button>
                    </div>

                </fieldset>
                <input type=\"hidden\" name=\"auto_login\" value=\"1\"/>
            </form>

        </div>
    </div>

    <ul class=\"login-footer\">
        <li><a href=\"";
        // line 210
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("login");
        echo "\"><small>";
        echo gettext("Login");
        echo "</small></a></li>
    </ul>

</section>

";
    }

    // line 217
    public function block_js($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 218
        echo "<script type=\"text/javascript\">
    \$(function () {
        \$('#client-signup').bind('submit', function (event) {
            \$.ajax({
                type: \"POST\",
                url: bb.restUrl('guest/client/create'),
                data: \$(this).serialize(),
                dataType: 'json',
                success: function (data) {
                    if (data.error) {
                        if (data.error.code == 7777) {
                            \$('#account-created-info-block').show();
                        } else {
                            \$('.wait').hide();
                            bb.msg(data.error.message, 'error');
                        }
                    } else {
                        bb.redirect(\"";
        // line 235
        echo twig_escape_filter($this->env, twig_constant("BB_URL"), "html", null, true);
        echo "\");
                    }
                }
            });
            return false;
        });
    });
</script>
";
    }

    public function getTemplateName()
    {
        return "mod_page_signup.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  521 => 235,  502 => 218,  498 => 217,  486 => 210,  472 => 199,  466 => 196,  456 => 189,  445 => 181,  440 => 178,  434 => 177,  418 => 172,  405 => 170,  402 => 169,  399 => 168,  394 => 167,  392 => 166,  389 => 165,  381 => 160,  375 => 157,  372 => 156,  370 => 155,  367 => 154,  359 => 149,  354 => 147,  351 => 146,  349 => 145,  346 => 144,  337 => 139,  332 => 136,  329 => 135,  327 => 134,  324 => 133,  317 => 128,  304 => 126,  300 => 125,  296 => 124,  290 => 121,  287 => 120,  285 => 119,  282 => 118,  274 => 113,  269 => 111,  266 => 110,  264 => 109,  261 => 108,  253 => 103,  248 => 101,  245 => 100,  243 => 99,  240 => 98,  232 => 93,  227 => 91,  224 => 90,  222 => 89,  219 => 88,  206 => 78,  203 => 77,  201 => 76,  198 => 75,  188 => 68,  185 => 67,  183 => 66,  180 => 65,  172 => 60,  167 => 58,  164 => 57,  162 => 56,  158 => 54,  150 => 49,  145 => 47,  142 => 46,  140 => 45,  131 => 39,  126 => 37,  117 => 31,  112 => 29,  109 => 28,  107 => 27,  99 => 24,  91 => 18,  87 => 16,  77 => 14,  75 => 13,  72 => 12,  69 => 11,  67 => 10,  62 => 7,  58 => 6,  51 => 5,  46 => 1,  44 => 3,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends \"layout_public.phtml\" %}

{% import \"macro_functions.phtml\" as mf %}

{% block meta_title %}{% trans 'Sign up' %}{% endblock %}
{% block body %}

<section class=\"container login\" role=\"main\">

    {% if settings.login_page_show_logo %}
    {% set company = guest.system_company %}
    <h1 style=\"text-align: center\">
        {% if settings.login_page_show_logo %}
        <a href=\"{{ settings.login_page_logo_url | default('/')}}\" target=\"_blank\"><img src=\"{{ guest.system_company.logo_url }}\" alt=\"{{ guest.system_company.name }}\"/></a>
        {% endif %}
    </h1>
    {% endif %}
    <div class=\"data-block\">
        <div class=\"data-container\">

            <form method=\"post\" action=\"\" id=\"client-signup\">
                <div class=\"alert alert-info\" style=\"display: none\" id=\"account-created-info-block\">
                    <button class=\"close\" data-dismiss=\"alert\">×</button>
                    <strong>{% trans 'Account has been created' %}.</strong> {% trans 'Please check your mailbox and confirm email address' %}.
                </div>
                <fieldset>
                    {% set r = guest.client_required %}
                    <div class=\"control-group\">
                        <label class=\"control-label\" for=\"reg-email\">{% trans 'Email Address' %}</label>
                        <div class=\"controls\">
                            <input type=\"text\" name=\"email\" value=\"{{ request.email }}\" required=\"required\" id=\"reg-email\">
                            <p class=\"help-block\"></p>
                        </div>
                    </div>

                    <div class=\"control-group\">
                        <label class=\"control-label\" for=\"first-name\">{% trans 'First Name' %}</label>
                        <div class=\"controls\">
                            <input type=\"text\" name=\"first_name\" id=\"first-name\" value=\"{{ request.first_name }}\" required=\"required\">
                            <p class=\"help-block\"></p>
                        </div>
                    </div>


                    {% if 'last_name' in r %}
                    <div class=\"control-group\">
                        <label class=\"control-label\" for=\"last_name\">{% trans 'Last Name' %}</label>
                        <div class=\"controls\">
                            <input type=\"text\" name=\"last_name\" id=\"last_name\" value=\"{{ request.last_name }}\" required=\"required\">
                            <p class=\"help-block\"></p>
                        </div>
                    </div>
                    {% endif %}


                    {% if 'company' in r %}
                    <div class=\"control-group\">
                        <label class=\"control-label\" for=\"company\">{% trans 'Company' %}</label>
                        <div class=\"controls\">
                            <input type=\"text\" name=\"company\" id=\"company\" value=\"{{ request.company }}\" required=\"required\">
                            <p class=\"help-block\"></p>
                        </div>
                    </div>
                    {% endif %}

                    {% if 'birthday' in r %}
                    <div class=\"control-group\">
                        <label class=\"control-label\" for=\"birthday\">{% trans 'Birthday' %}</label>
                        <div class=\"controls\">
                            <input type=\"text\" name=\"birthday\" id=\"birthday\" value=\"\">
                            <p class=\"help-block\"></p>
                        </div>
                    </div>
                    {% endif %}

                    {% if 'gender' in r %}
                    <div class=\"control-group\">
                        <label class=\"control-label\" for=\"gender\">{% trans 'You are' %}</label>
                        <div class=\"controls\">
                            <select name=\"gender\" id=\"gender\">
                                <option value=\"male\">Male</option>
                                <option value=\"female\">Female</option>
                            </select>
                            <p class=\"help-block\"></p>
                        </div>
                    </div>
                    {% endif %}

                    {% if 'address_1' in r %}
                    <div class=\"control-group\">
                        <label class=\"control-label\" for=\"address_1\">{% trans 'Address' %}</label>
                        <div class=\"controls\">
                            <input type=\"text\" name=\"address_1\" id=\"address_1\" value=\"{{ request.address_1 }}\">
                            <p class=\"help-block\"></p>
                        </div>
                    </div>
                    {% endif %}

                    {% if 'address_2' in r %}
                    <div class=\"control-group\">
                        <label class=\"control-label\" for=\"address_2\">{% trans 'Address 2' %}</label>
                        <div class=\"controls\">
                            <input type=\"text\" name=\"address_2\" id=\"address_2\" value=\"{{ request.address_2 }}\">
                            <p class=\"help-block\"></p>
                        </div>
                    </div>
                    {% endif %}

                    {% if 'city' in r %}
                    <div class=\"control-group\">
                        <label class=\"control-label\" for=\"city\">{% trans 'City' %}</label>
                        <div class=\"controls\">
                            <input type=\"text\" name=\"city\" id=\"city\" value=\"{{ request.city }}\">
                            <p class=\"help-block\"></p>
                        </div>
                    </div>
                    {% endif %}

                    {% if 'country' in r %}
                    <div class=\"control-group\">
                        <label class=\"control-label\" for=\"country\">{% trans 'Country' %}</label>
                        <div class=\"controls\">
                            <select name=\"country\" required=\"required\">
                                <option value=\"\">{% trans '-- Select country --' %}</option>
                                {% for val,label in guest.system_countries %}
                                <option value=\"{{ val }}\" label=\"{{ label|e }}\">{{ label|e }}</option>
                                {% endfor %}
                            </select>
                            <p class=\"help-block\"></p>
                        </div>
                    </div>
                    {% endif %}

                    {% if 'state' in r %}
                    <div class=\"control-group\">
                        <label class=\"control-label\" for=\"state\">{% trans 'State' %}</label>
                        <div class=\"controls\">
                            {# mf.selectbox('state', guest.system_states, request.state, 0, 'Select state') #}
                            <input type=\"text\" name=\"state\" id=\"state\" value=\"{{ request.state }}\" />
                            <p class=\"help-block\"></p>
                        </div>
                    </div>
                    {% endif %}

                    {% if 'postcode' in r %}
                    <div class=\"control-group\">
                        <label class=\"control-label\" for=\"postcode\">{% trans 'Zip/Postal Code' %}</label>
                        <div class=\"controls\">
                            <input type=\"text\" name=\"postcode\" id=\"postcode\" value=\"{{ request.postcode }}\">
                            <p class=\"help-block\"></p>
                        </div>
                    </div>
                    {% endif %}

                    {% if 'phone' in r %}
                    <div class=\"control-group\">
                        <label class=\"control-label\" for=\"phone\">{% trans 'Phone Number' %}</label>
                            <div class=\"input-prepend\">
                                <input type=\"text\" name=\"phone_cc\" value=\"\" style=\"width:20%\">
                                <input id=\"phone\" type=\"text\" name=\"phone\" value=\"{{ request.phone }}\" style=\"width:70%\">
                            </div>
                            <p class=\"help-block\"></p>
                    </div>
                    {% endif %}

                    {% set custom_fields = guest.client_custom_fields %}
                    {% for field_name, field in custom_fields %}
                        {% if field.active %}
                            <div class=\"control-group\">
                                <label class=\"control-label\" for=\"{{ field_name }}\">{% if field.title is not empty %}{{ field.title }}{% else %} {{ field_name | capitalize }} {% endif %}</label>
                                <div class=\"controls\">
                                    <input type=\"text\" name=\"{{ field_name }}\" id=\"{{ field_name }}\" value=\"{{ attribute(request, field_name) }}\" {% if field.required %}required=\"required\"{% endif %}>
                                    <p class=\"help-block\"></p>
                                </div>
                            </div>
                        {% endif %}
                    {% endfor %}


                    <div class=\"control-group\">
                        <label class=\"control-label\" for=\"reg-password\">{% trans 'Password' %}</label>
                        <div class=\"controls\">
                            <input type=\"password\" name=\"password\" value=\"\" required=\"required\" id=\"reg-password\">
                            <p class=\"help-block\"></p>
                        </div>
                    </div>

                    <div class=\"control-group\">
                        <label class=\"control-label\" for=\"password-confirm\">{% trans 'Password confirm' %}</label>
                        <div class=\"controls\">
                            <input type=\"password\" name=\"password_confirm\" name=\"password-confirm\" value=\"\" required=\"required\">
                            <p class=\"help-block\"></p>
                        </div>
                    </div>

                    {{ mf.recaptcha }}

                    <div class=\"form-actions\">
                        <button class=\"btn btn-block btn-large btn-inverse btn-alt\" type=\"submit\">{% trans 'Sign up' %}</button>
                    </div>

                </fieldset>
                <input type=\"hidden\" name=\"auto_login\" value=\"1\"/>
            </form>

        </div>
    </div>

    <ul class=\"login-footer\">
        <li><a href=\"{{ 'login'|link }}\"><small>{% trans 'Login' %}</small></a></li>
    </ul>

</section>

{% endblock %}

{% block js %}
<script type=\"text/javascript\">
    \$(function () {
        \$('#client-signup').bind('submit', function (event) {
            \$.ajax({
                type: \"POST\",
                url: bb.restUrl('guest/client/create'),
                data: \$(this).serialize(),
                dataType: 'json',
                success: function (data) {
                    if (data.error) {
                        if (data.error.code == 7777) {
                            \$('#account-created-info-block').show();
                        } else {
                            \$('.wait').hide();
                            bb.msg(data.error.message, 'error');
                        }
                    } else {
                        bb.redirect(\"{{ constant('BB_URL') }}\");
                    }
                }
            });
            return false;
        });
    });
</script>
{% endblock %}", "mod_page_signup.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-modules/Page/html_client/mod_page_signup.phtml");
    }
}
