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

/* layout_login.phtml */
class __TwigTemplate_49d0bd5784d8d2577722996deb8ce010a488c65f3209b06eaa58ad4863ec0209 extends \Twig\Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
            'meta_title' => [$this, 'block_meta_title'],
            'content' => [$this, 'block_content'],
            'js' => [$this, 'block_js'],
        ];
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 1
        echo "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">
<html xmlns=\"http://www.w3.org/1999/xhtml\">
<head>
    <title>";
        // line 4
        $this->displayBlock('meta_title', $context, $blocks);
        echo "</title>
    <meta http-equiv=\"content-type\" content=\"text/html; charset=utf-8\" />
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0\" />
    
    ";
        // line 8
        $this->loadTemplate("partial_bb_meta.phtml", "layout_login.phtml", 8)->display($context);
        // line 9
        echo "
    <link rel=\"stylesheet\" href=\"css/min.css\" type=\"text/css\" media=\"screen\" />
    <link href=\"https://fonts.googleapis.com/css?family=Cuprum\" rel=\"stylesheet\" type=\"text/css\" />

    <script type=\"text/javascript\" src=\"js/boxbilling.min.js\"></script>
    <script type=\"text/javascript\" src=\"js/bb-admin.js?v=";
        // line 14
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_version", [], "any", false, false, false, 14), "html", null, true);
        echo "\"></script>
</head>

<body>
<!-- Top navigation bar -->
<div id=\"topNav\">
    <div class=\"fixed\">
        <div class=\"wrapper\">
            <div class=\"backTo\"><a href=\"";
        // line 22
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/");
        echo "\" title=\"\"><img src=\"images/icons/topnav/mainWebsite.png\" alt=\"\" /><span>";
        echo gettext("Main website");
        echo "</span></a></div>
            <div class=\"userNav\">
                <ul>
                    <li class=\"loading\" style=\"display:none;\"><img src=\"images/loader.gif\" alt=\"\" /><span>";
        // line 25
        echo gettext("Loading ...");
        echo "</span></li>
                    <li><a href=\"http://docs.boxbilling.com/";
        // line 26
        if (($context["help_query"] ?? null)) {
            echo "en/latest/search.html?q=";
            echo twig_escape_filter($this->env, ($context["help_query"] ?? null), "html", null, true);
            echo "&check_keywords=yes&area=default";
        }
        echo "\" title=\"\" target=\"_blank\"><img src=\"images/icons/topnav/help.png\" alt=\"\" /><span>";
        echo gettext("Help");
        echo "</span></a></li>
                </ul>
            </div>
            <div class=\"fix\"></div>
        </div>
    </div>
</div>

";
        // line 34
        $this->displayBlock('content', $context, $blocks);
        // line 35
        echo "
<!-- Footer -->
<div id=\"footer\">
\t<div class=\"wrapper\">
    \t";
        // line 39
        $this->loadTemplate("partial_footer.phtml", "layout_login.phtml", 39)->display(twig_array_merge($context, ["product" => ($context["product"] ?? null)]));
        // line 40
        echo "    </div>
</div>
";
        // line 42
        $this->displayBlock('js', $context, $blocks);
        // line 43
        echo "</body>
</html>
";
    }

    // line 4
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
    }

    // line 34
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
    }

    // line 42
    public function block_js($context, array $blocks = [])
    {
        $macros = $this->macros;
    }

    public function getTemplateName()
    {
        return "layout_login.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  135 => 42,  129 => 34,  123 => 4,  117 => 43,  115 => 42,  111 => 40,  109 => 39,  103 => 35,  101 => 34,  84 => 26,  80 => 25,  72 => 22,  61 => 14,  54 => 9,  52 => 8,  45 => 4,  40 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">
<html xmlns=\"http://www.w3.org/1999/xhtml\">
<head>
    <title>{% block meta_title %}{% endblock %}</title>
    <meta http-equiv=\"content-type\" content=\"text/html; charset=utf-8\" />
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0\" />
    
    {% include \"partial_bb_meta.phtml\" %}

    <link rel=\"stylesheet\" href=\"css/min.css\" type=\"text/css\" media=\"screen\" />
    <link href=\"https://fonts.googleapis.com/css?family=Cuprum\" rel=\"stylesheet\" type=\"text/css\" />

    <script type=\"text/javascript\" src=\"js/boxbilling.min.js\"></script>
    <script type=\"text/javascript\" src=\"js/bb-admin.js?v={{guest.system_version}}\"></script>
</head>

<body>
<!-- Top navigation bar -->
<div id=\"topNav\">
    <div class=\"fixed\">
        <div class=\"wrapper\">
            <div class=\"backTo\"><a href=\"{{ '/'|link }}\" title=\"\"><img src=\"images/icons/topnav/mainWebsite.png\" alt=\"\" /><span>{% trans 'Main website' %}</span></a></div>
            <div class=\"userNav\">
                <ul>
                    <li class=\"loading\" style=\"display:none;\"><img src=\"images/loader.gif\" alt=\"\" /><span>{% trans 'Loading ...' %}</span></li>
                    <li><a href=\"http://docs.boxbilling.com/{% if help_query %}en/latest/search.html?q={{help_query}}&check_keywords=yes&area=default{% endif %}\" title=\"\" target=\"_blank\"><img src=\"images/icons/topnav/help.png\" alt=\"\" /><span>{% trans 'Help' %}</span></a></li>
                </ul>
            </div>
            <div class=\"fix\"></div>
        </div>
    </div>
</div>

{% block content %}{% endblock %}

<!-- Footer -->
<div id=\"footer\">
\t<div class=\"wrapper\">
    \t{% include \"partial_footer.phtml\" with {'product': product} %}
    </div>
</div>
{% block js %}{% endblock %}
</body>
</html>
", "layout_login.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/src/bb-themes/admin_default/html/layout_login.phtml");
    }
}
