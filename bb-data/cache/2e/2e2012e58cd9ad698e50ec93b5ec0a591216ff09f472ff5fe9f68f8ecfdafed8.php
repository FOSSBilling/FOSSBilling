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

/* mod_staff_login.phtml */
class __TwigTemplate_0982701e02170f68a02449bc9cad774f70538031e266fbd1b4cb2e4b71c386ce extends \Twig\Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->blocks = [
            'meta_title' => [$this, 'block_meta_title'],
            'content' => [$this, 'block_content'],
        ];
    }

    protected function doGetParent(array $context)
    {
        // line 1
        return "layout_login.phtml";
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        $this->parent = $this->loadTemplate("layout_login.phtml", "mod_staff_login.phtml", 1);
        $this->parent->display($context, array_merge($this->blocks, $blocks));
    }

    // line 3
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo gettext("Login");
    }

    // line 5
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 6
        echo "
<!-- Login form area -->
<div class=\"loginWrapper\">
    <div class=\"loginLogo\"><img src=\"";
        // line 9
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_company", [], "any", false, false, false, 9), "logo_url", [], "any", false, false, false, 9), "html", null, true);
        echo "\" alt=\"\" style=\"max-height: 75px\"/></div>
    <div class=\"loginPanel\">
        ";
        // line 11
        if (($context["create_admin"] ?? null)) {
            // line 12
            echo "        <div class=\"head\"><h5 class=\"iUser\">";
            echo gettext("Create main administrator account");
            echo "</h5></div>
        <form class=\"mainForm api-form\" action=\"";
            // line 13
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/guest/staff/create");
            echo "\" method=\"post\" data-api-redirect=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("index");
            echo "\">
            <fieldset>
                <div class=\"loginRow noborder\">
                    <label for=\"req1\">";
            // line 16
            echo gettext("Email");
            echo ":</label>
                    <div class=\"loginInput\"><input id=\"req1\" type=\"email\" name=\"email\" value=\"";
            // line 17
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "email", [], "any", false, false, false, 17), "html", null, true);
            echo "\" placeholder=\"";
            echo gettext("Enter your email address");
            echo "\"/></div>
                    <div class=\"fix\"></div>
                </div>
                
                <div class=\"loginRow\">
                    <label for=\"req2\">";
            // line 22
            echo gettext("Password");
            echo ":</label>
                    <div class=\"loginInput\"><input id=\"req2\" type=\"password\" name=\"password\" value=\"";
            // line 23
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "password", [], "any", false, false, false, 23), "html", null, true);
            echo "\" placeholder=\"";
            echo gettext("Enter your password");
            echo "\"/></div>
                    <div class=\"fix\"></div>
                </div>
                
                <div class=\"loginRow\">
                    <input type=\"submit\" value=\"";
            // line 28
            echo gettext("Create administrator account");
            echo "\" class=\"greyishBtn submitForm\" />
                    <div class=\"fix\"></div>
                </div>
            </fieldset>
        </form>
        ";
        } else {
            // line 34
            echo "        <div class=\"head\"><h5 class=\"iUser\"><i class=\"dark-sprite-icon sprite-user\" style=\"margin-left: -25px; margin-right: 11px;\"></i>";
            echo gettext("Login");
            echo "</h5></div>
        <form class=\"mainForm api-form\" action=\"";
            // line 35
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/guest/staff/login");
            echo "\" method=\"post\" data-api-redirect=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("index");
            echo "\">
            <fieldset>
                <div class=\"loginRow noborder\">
                    <label for=\"req1\">";
            // line 38
            echo gettext("Email");
            echo ":</label>
                    <div class=\"loginInput\"><input id=\"req1\" type=\"email\" name=\"email\" value=\"";
            // line 39
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "email", [], "any", false, false, false, 39), "html", null, true);
            echo "\" placeholder=\"";
            echo gettext("Enter your email address");
            echo "\" autofocus/></div>
                    <div class=\"fix\"></div>
                </div>
                
                <div class=\"loginRow\">
                    <label for=\"req2\">";
            // line 44
            echo gettext("Password");
            echo ":</label>
                    <div class=\"loginInput\"><input id=\"req2\" type=\"password\" name=\"password\" value=\"";
            // line 45
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "password", [], "any", false, false, false, 45), "html", null, true);
            echo "\" placeholder=\"";
            echo gettext("Enter your password");
            echo "\"/></div>
                    <div class=\"fix\"></div>
                </div>
                
                <div class=\"loginRow\">
                    <div class=\"rememberMe\"><input type=\"checkbox\" id=\"remember\" name=\"remember\" value=\"1\" checked=\"checked\"/><label for=\"remember\">";
            // line 50
            echo gettext("Remember me");
            echo "</label></div>
                    <input type=\"submit\" value=\"";
            // line 51
            echo gettext("Log me in");
            echo "\" class=\"greyishBtn submitForm\" />
                    <div class=\"fix\"></div>
                </div>
            </fieldset>
        </form>
        ";
        }
        // line 57
        echo "    </div>
</div>

";
    }

    public function getTemplateName()
    {
        return "mod_staff_login.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  174 => 57,  165 => 51,  161 => 50,  151 => 45,  147 => 44,  137 => 39,  133 => 38,  125 => 35,  120 => 34,  111 => 28,  101 => 23,  97 => 22,  87 => 17,  83 => 16,  75 => 13,  70 => 12,  68 => 11,  63 => 9,  58 => 6,  54 => 5,  47 => 3,  36 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends \"layout_login.phtml\" %}

{% block meta_title %}{% trans 'Login' %}{% endblock %}

{% block content %}

<!-- Login form area -->
<div class=\"loginWrapper\">
    <div class=\"loginLogo\"><img src=\"{{ guest.system_company.logo_url }}\" alt=\"\" style=\"max-height: 75px\"/></div>
    <div class=\"loginPanel\">
        {% if create_admin %}
        <div class=\"head\"><h5 class=\"iUser\">{% trans 'Create main administrator account' %}</h5></div>
        <form class=\"mainForm api-form\" action=\"{{ 'api/guest/staff/create'|link }}\" method=\"post\" data-api-redirect=\"{{ 'index'|alink }}\">
            <fieldset>
                <div class=\"loginRow noborder\">
                    <label for=\"req1\">{% trans 'Email' %}:</label>
                    <div class=\"loginInput\"><input id=\"req1\" type=\"email\" name=\"email\" value=\"{{ request.email }}\" placeholder=\"{% trans 'Enter your email address' %}\"/></div>
                    <div class=\"fix\"></div>
                </div>
                
                <div class=\"loginRow\">
                    <label for=\"req2\">{% trans 'Password' %}:</label>
                    <div class=\"loginInput\"><input id=\"req2\" type=\"password\" name=\"password\" value=\"{{ request.password }}\" placeholder=\"{% trans 'Enter your password' %}\"/></div>
                    <div class=\"fix\"></div>
                </div>
                
                <div class=\"loginRow\">
                    <input type=\"submit\" value=\"{% trans 'Create administrator account' %}\" class=\"greyishBtn submitForm\" />
                    <div class=\"fix\"></div>
                </div>
            </fieldset>
        </form>
        {% else %}
        <div class=\"head\"><h5 class=\"iUser\"><i class=\"dark-sprite-icon sprite-user\" style=\"margin-left: -25px; margin-right: 11px;\"></i>{% trans 'Login' %}</h5></div>
        <form class=\"mainForm api-form\" action=\"{{ 'api/guest/staff/login'|link }}\" method=\"post\" data-api-redirect=\"{{ 'index'|alink }}\">
            <fieldset>
                <div class=\"loginRow noborder\">
                    <label for=\"req1\">{% trans 'Email' %}:</label>
                    <div class=\"loginInput\"><input id=\"req1\" type=\"email\" name=\"email\" value=\"{{ request.email }}\" placeholder=\"{% trans 'Enter your email address' %}\" autofocus/></div>
                    <div class=\"fix\"></div>
                </div>
                
                <div class=\"loginRow\">
                    <label for=\"req2\">{% trans 'Password' %}:</label>
                    <div class=\"loginInput\"><input id=\"req2\" type=\"password\" name=\"password\" value=\"{{ request.password }}\" placeholder=\"{% trans 'Enter your password' %}\"/></div>
                    <div class=\"fix\"></div>
                </div>
                
                <div class=\"loginRow\">
                    <div class=\"rememberMe\"><input type=\"checkbox\" id=\"remember\" name=\"remember\" value=\"1\" checked=\"checked\"/><label for=\"remember\">{% trans 'Remember me' %}</label></div>
                    <input type=\"submit\" value=\"{% trans 'Log me in' %}\" class=\"greyishBtn submitForm\" />
                    <div class=\"fix\"></div>
                </div>
            </fieldset>
        </form>
        {% endif %}
    </div>
</div>

{% endblock %}
", "mod_staff_login.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/src/bb-themes/admin_default/html/mod_staff_login.phtml");
    }
}
