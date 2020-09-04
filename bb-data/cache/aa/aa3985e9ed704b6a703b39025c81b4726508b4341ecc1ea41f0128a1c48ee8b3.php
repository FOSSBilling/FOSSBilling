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

/* mod_page_password-reset.phtml */
class __TwigTemplate_87e5ac81f504e14f53c556537630e82f9ceeb3adc32a58d7249269bb04a6ca63 extends \Twig\Template
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
        $this->parent = $this->loadTemplate("layout_public.phtml", "mod_page_password-reset.phtml", 1);
        $this->parent->display($context, array_merge($this->blocks, $blocks));
    }

    // line 3
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo gettext("Reset password");
    }

    // line 5
    public function block_body($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 6
        echo "
<section class=\"container login\" role=\"main\">

 <h1>";
        // line 9
        echo gettext("Reset password");
        echo "</h1>

<div class=\"data-block\">
    <div class=\"data-container\">

        <form method=\"post\" action=\"\" id=\"password-reset\">
            <fieldset>
                <div class=\"control-group\">
                    <label class=\"control-label\" for=\"email\">";
        // line 17
        echo gettext("Email Address");
        echo "</label>
                    <div class=\"controls\">
                        <input id=\"icon\" type=\"text\" placeholder=\"";
        // line 19
        echo gettext("Your email address");
        echo "\" name=\"email\" value=\"";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "email", [], "any", false, false, false, 19), "html", null, true);
        echo "\" required=\"required\" data-validation-required-message=\"";
        echo gettext("You must fill in your email.");
        echo "\">
                        <div class=\"help-block\"></div>
                        <span class=\"help-block\">";
        // line 21
        echo gettext("Enter your email to reset password. You will receive new password after reset link is confirmed.");
        echo "</span>
                    </div>
                </div>
                <div class=\"form-actions\">
                    <button class=\"btn btn-block btn-large btn-inverse btn-alt span3\" type=\"submit\">";
        // line 25
        echo gettext("Reset password");
        echo "</button>
                </div>
            </fieldset>
        </form>

    </div>
</div>

<ul class=\"login-footer\">
    <li><a href=\"";
        // line 34
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("signup");
        echo "\"><small>";
        echo gettext("Signup");
        echo "</small></a></li>
    <li><a href=\"";
        // line 35
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("login");
        echo "\"><small>";
        echo gettext("Login");
        echo "</small></a></li>
</ul>

</section>
";
    }

    // line 41
    public function block_js($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 42
        echo "<script type=\"text/javascript\">
\$(function() {
    \$('#password-reset').bind('submit',function(event){
        bb.post(
            'guest/client/reset_password',
            \$(this).serialize(),
            function(result) {
                bb.msg('";
        // line 49
        echo gettext("Password reset confirmation email was sent");
        echo "');
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
        return "mod_page_password-reset.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  138 => 49,  129 => 42,  125 => 41,  114 => 35,  108 => 34,  96 => 25,  89 => 21,  80 => 19,  75 => 17,  64 => 9,  59 => 6,  55 => 5,  48 => 3,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends \"layout_public.phtml\" %}

{% block meta_title %}{% trans 'Reset password' %}{% endblock %}

{% block body %}

<section class=\"container login\" role=\"main\">

 <h1>{% trans 'Reset password' %}</h1>

<div class=\"data-block\">
    <div class=\"data-container\">

        <form method=\"post\" action=\"\" id=\"password-reset\">
            <fieldset>
                <div class=\"control-group\">
                    <label class=\"control-label\" for=\"email\">{% trans 'Email Address' %}</label>
                    <div class=\"controls\">
                        <input id=\"icon\" type=\"text\" placeholder=\"{% trans 'Your email address' %}\" name=\"email\" value=\"{{ request.email }}\" required=\"required\" data-validation-required-message=\"{% trans 'You must fill in your email.' %}\">
                        <div class=\"help-block\"></div>
                        <span class=\"help-block\">{% trans 'Enter your email to reset password. You will receive new password after reset link is confirmed.' %}</span>
                    </div>
                </div>
                <div class=\"form-actions\">
                    <button class=\"btn btn-block btn-large btn-inverse btn-alt span3\" type=\"submit\">{% trans 'Reset password' %}</button>
                </div>
            </fieldset>
        </form>

    </div>
</div>

<ul class=\"login-footer\">
    <li><a href=\"{{ 'signup'|link }}\"><small>{% trans 'Signup' %}</small></a></li>
    <li><a href=\"{{ 'login'|link }}\"><small>{% trans 'Login' %}</small></a></li>
</ul>

</section>
{% endblock %}

{% block js %}
<script type=\"text/javascript\">
\$(function() {
    \$('#password-reset').bind('submit',function(event){
        bb.post(
            'guest/client/reset_password',
            \$(this).serialize(),
            function(result) {
                bb.msg('{% trans \"Password reset confirmation email was sent\" %}');
            }
        );
        return false;
    });
});
</script>
{% endblock %}", "mod_page_password-reset.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-modules/Page/html_client/mod_page_password-reset.phtml");
    }
}
