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

/* mod_page_login.phtml */
class __TwigTemplate_bb2c4cb9a0ab4ee3d576641542472af1c2e3f4ae31b3ca6ab7bceb71bfe5b9c9 extends \Twig\Template
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
        $this->parent = $this->loadTemplate("layout_public.phtml", "mod_page_login.phtml", 1);
        $this->parent->display($context, array_merge($this->blocks, $blocks));
    }

    // line 3
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo gettext("Log in");
    }

    // line 5
    public function block_body($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 6
        echo "
<section class=\"container login\" role=\"main\">

";
        // line 9
        if (twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "login_page_show_logo", [], "any", false, false, false, 9)) {
            // line 10
            echo "    ";
            $context["company"] = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_company", [], "any", false, false, false, 10);
            // line 11
            echo "    <h1 style=\"text-align: center\">
        ";
            // line 12
            if (twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "login_page_show_logo", [], "any", false, false, false, 12)) {
                // line 13
                echo "        <a href=\"";
                echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "login_page_logo_url", [], "any", true, true, false, 13)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "login_page_logo_url", [], "any", false, false, false, 13), "/")) : ("/")), "html", null, true);
                echo "\" target=\"_blank\"><img src=\"";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_company", [], "any", false, false, false, 13), "logo_url", [], "any", false, false, false, 13), "html", null, true);
                echo "\" alt=\"";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_company", [], "any", false, false, false, 13), "name", [], "any", false, false, false, 13), "html", null, true);
                echo "\"/></a>
        ";
            }
            // line 15
            echo "    </h1>
";
        }
        // line 17
        echo "
    <div class=\"data-block\">
        <div class=\"data-container\">

            <form method=\"post\" action=\"\" id=\"client-login\">
                <fieldset>
                    <div class=\"control-group\">
                        <label class=\"control-label\" for=\"email\">";
        // line 24
        echo gettext("Email Address");
        echo "</label>
                        <div class=\"controls\">
                            <input id=\"icon\" type=\"text\" placeholder=\"";
        // line 26
        echo gettext("Your email address");
        echo "\" name=\"email\" value=\"";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "email", [], "any", false, false, false, 26), "html", null, true);
        echo "\" required=\"required\" data-validation-required-message=\"";
        echo gettext("You must fill in your email.");
        echo "\" autofocus>
                            <div class=\"help-block\"></div>
                        </div>
                    </div>
                    <div class=\"control-group\">
                        <label class=\"control-label\" for=\"password\">";
        // line 31
        echo gettext("Password");
        echo "</label>
                        <div class=\"controls\">
                            <input id=\"password\" type=\"password\" placeholder=\"";
        // line 33
        echo gettext("Password");
        echo "\" name=\"password\" required=\"required\" value=\"";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "password", [], "any", false, false, false, 33), "html", null, true);
        echo "\" data-validation-required-message=\"";
        echo gettext("You must fill in your password.");
        echo "\">
                            ";
        // line 34
        if (twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "login_page_show_remember_me", [], "any", false, false, false, 34)) {
            // line 35
            echo "                            <label class=\"checkbox\">
                                <input type=\"checkbox\" name=\"remember\" checked=\"checked\"> ";
            // line 36
            echo gettext("Remember me");
            // line 37
            echo "                            </label>
                            ";
        }
        // line 39
        echo "                            <div class=\"help-block\"></div>
                        </div>
                    </div>
                    <div class=\"form-actions\">
                        <button class=\"btn btn-block btn-large btn-inverse btn-alt\" type=\"submit\">";
        // line 43
        echo gettext("Log in");
        echo "</button>
                    </div>
                </fieldset>
            </form>

        </div>
    </div>

    ";
        // line 51
        if ((twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "show_password_reset_link", [], "any", false, false, false, 51) || twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "show_signup_link", [], "any", false, false, false, 51))) {
            // line 52
            echo "    <ul class=\"login-footer\">
        ";
            // line 53
            if (twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "show_signup_link", [], "any", false, false, false, 53)) {
                // line 54
                echo "        <li><a href=\"";
                echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("signup");
                echo "\"><small>";
                echo gettext("Signup");
                echo "</small></a></li>
        ";
            }
            // line 56
            echo "
        ";
            // line 57
            if (twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "show_password_reset_link", [], "any", false, false, false, 57)) {
                // line 58
                echo "        <li><a href=\"";
                echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("password-reset");
                echo "\"><small>";
                echo gettext("Forgot password?");
                echo "</small></a></li>
        ";
            }
            // line 60
            echo "    </ul>
    ";
        }
        // line 62
        echo "
</section>

";
    }

    // line 67
    public function block_js($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 68
        echo "<script type=\"text/javascript\">
\$(function() {
    \$('#client-login').bind('submit',function(event){
        bb.post('guest/client/login',
            \$(this).serialize(),
            function(result) {
                bb.redirect();
            }
        );
        return false;
    });
    ";
        // line 79
        if (twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "al", [], "any", false, false, false, 79)) {
            // line 80
            echo "        \$('#client-login').submit();
    ";
        }
        // line 82
        echo "});
</script>
<script src=\"";
        // line 84
        echo twig_asset_url($this->env, "js/plugins/bootstrapValidation/jqBootstrapValidation.min.js");
        echo "\"></script>
<script>
    \$(document).ready(function() {

        \$(\"input\").jqBootstrapValidation({
            submitSuccess: function(\$form, event) {
                event.preventDefault();
            }
        });

    });
</script>
";
    }

    public function getTemplateName()
    {
        return "mod_page_login.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  221 => 84,  217 => 82,  213 => 80,  211 => 79,  198 => 68,  194 => 67,  187 => 62,  183 => 60,  175 => 58,  173 => 57,  170 => 56,  162 => 54,  160 => 53,  157 => 52,  155 => 51,  144 => 43,  138 => 39,  134 => 37,  132 => 36,  129 => 35,  127 => 34,  119 => 33,  114 => 31,  102 => 26,  97 => 24,  88 => 17,  84 => 15,  74 => 13,  72 => 12,  69 => 11,  66 => 10,  64 => 9,  59 => 6,  55 => 5,  48 => 3,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends \"layout_public.phtml\" %}

{% block meta_title %}{% trans 'Log in' %}{% endblock %}

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

            <form method=\"post\" action=\"\" id=\"client-login\">
                <fieldset>
                    <div class=\"control-group\">
                        <label class=\"control-label\" for=\"email\">{% trans 'Email Address' %}</label>
                        <div class=\"controls\">
                            <input id=\"icon\" type=\"text\" placeholder=\"{% trans 'Your email address' %}\" name=\"email\" value=\"{{ request.email }}\" required=\"required\" data-validation-required-message=\"{% trans 'You must fill in your email.' %}\" autofocus>
                            <div class=\"help-block\"></div>
                        </div>
                    </div>
                    <div class=\"control-group\">
                        <label class=\"control-label\" for=\"password\">{% trans 'Password' %}</label>
                        <div class=\"controls\">
                            <input id=\"password\" type=\"password\" placeholder=\"{% trans 'Password' %}\" name=\"password\" required=\"required\" value=\"{{ request.password }}\" data-validation-required-message=\"{% trans 'You must fill in your password.' %}\">
                            {% if settings.login_page_show_remember_me %}
                            <label class=\"checkbox\">
                                <input type=\"checkbox\" name=\"remember\" checked=\"checked\"> {% trans 'Remember me' %}
                            </label>
                            {% endif %}
                            <div class=\"help-block\"></div>
                        </div>
                    </div>
                    <div class=\"form-actions\">
                        <button class=\"btn btn-block btn-large btn-inverse btn-alt\" type=\"submit\">{% trans 'Log in' %}</button>
                    </div>
                </fieldset>
            </form>

        </div>
    </div>

    {% if settings.show_password_reset_link or settings.show_signup_link %}
    <ul class=\"login-footer\">
        {% if settings.show_signup_link%}
        <li><a href=\"{{ 'signup'|link }}\"><small>{% trans 'Signup' %}</small></a></li>
        {% endif %}

        {% if settings.show_password_reset_link%}
        <li><a href=\"{{ 'password-reset'|link }}\"><small>{% trans 'Forgot password?' %}</small></a></li>
        {% endif %}
    </ul>
    {% endif %}

</section>

{% endblock%}

{% block js %}
<script type=\"text/javascript\">
\$(function() {
    \$('#client-login').bind('submit',function(event){
        bb.post('guest/client/login',
            \$(this).serialize(),
            function(result) {
                bb.redirect();
            }
        );
        return false;
    });
    {% if request.al %}
        \$('#client-login').submit();
    {% endif %}
});
</script>
<script src=\"{{ 'js/plugins/bootstrapValidation/jqBootstrapValidation.min.js' | asset_url}}\"></script>
<script>
    \$(document).ready(function() {

        \$(\"input\").jqBootstrapValidation({
            submitSuccess: function(\$form, event) {
                event.preventDefault();
            }
        });

    });
</script>
{% endblock %}", "mod_page_login.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/src/bb-modules/Page/html_client/mod_page_login.phtml");
    }
}
