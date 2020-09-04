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

/* mod_servicehosting_order.phtml */
class __TwigTemplate_0597ce35621e2907403666c332a7b755d6f533339b6188c5463e5052d4555219 extends \Twig\Template
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
        echo "<div class=\"rowElem\">
    <label>";
        // line 2
        echo gettext("Import existing");
        echo ":</label>
    <div class=\"formRight\">
        <input type=\"radio\" name=\"config[import]\" value=\"1\"/><label>Yes</label>
        <input type=\"radio\" name=\"config[import]\" value=\"0\" checked=\"checked\" /><label>No</label>
    </div>
    <div class=\"fix\"></div>
</div>

<div class=\"rowElem\">
    <label>";
        // line 11
        echo gettext("Domain");
        echo ":</label>
    <div class=\"formRight moreFields\">
        <ul>
            <li style=\"width: 200px\"><input type=\"text\" name=\"config[domain][owndomain_sld]]\" value=\"\" placeholder=\"";
        // line 14
        echo gettext("Domain name");
        echo "\"></li>
            <li style=\"width: 50px\"><input type=\"text\" name=\"config[domain][owndomain_tld]]\" value=\".com\" placeholder=\"";
        // line 15
        echo gettext("Domain TLD");
        echo "\"></li>
        </ul>
    </div>
    <div class=\"fix\"></div>
</div>

<div class=\"rowElem\">
    <label>";
        // line 22
        echo gettext("Username");
        echo ":</label>
    <div class=\"formRight\">
        <input type=\"text\" name=\"config[username]\" value=\"\" placeholder=\"Leave blank to generate\"/>
    </div>
    <div class=\"fix\"></div>
</div>

<div class=\"rowElem\">
    <label>";
        // line 30
        echo gettext("Password");
        echo ":</label>
    <div class=\"formRight\">
        <input type=\"text\" name=\"config[password]\" value=\"\" placeholder=\"Leave blank to generate\"/>
    </div>
    <div class=\"fix\"></div>
</div>

<input type=\"hidden\" name=\"config[domain][action]\" value=\"owndomain\"/>
";
    }

    public function getTemplateName()
    {
        return "mod_servicehosting_order.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  83 => 30,  72 => 22,  62 => 15,  58 => 14,  52 => 11,  40 => 2,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("<div class=\"rowElem\">
    <label>{% trans 'Import existing' %}:</label>
    <div class=\"formRight\">
        <input type=\"radio\" name=\"config[import]\" value=\"1\"/><label>Yes</label>
        <input type=\"radio\" name=\"config[import]\" value=\"0\" checked=\"checked\" /><label>No</label>
    </div>
    <div class=\"fix\"></div>
</div>

<div class=\"rowElem\">
    <label>{% trans 'Domain' %}:</label>
    <div class=\"formRight moreFields\">
        <ul>
            <li style=\"width: 200px\"><input type=\"text\" name=\"config[domain][owndomain_sld]]\" value=\"\" placeholder=\"{% trans 'Domain name' %}\"></li>
            <li style=\"width: 50px\"><input type=\"text\" name=\"config[domain][owndomain_tld]]\" value=\".com\" placeholder=\"{% trans 'Domain TLD' %}\"></li>
        </ul>
    </div>
    <div class=\"fix\"></div>
</div>

<div class=\"rowElem\">
    <label>{% trans 'Username' %}:</label>
    <div class=\"formRight\">
        <input type=\"text\" name=\"config[username]\" value=\"\" placeholder=\"Leave blank to generate\"/>
    </div>
    <div class=\"fix\"></div>
</div>

<div class=\"rowElem\">
    <label>{% trans 'Password' %}:</label>
    <div class=\"formRight\">
        <input type=\"text\" name=\"config[password]\" value=\"\" placeholder=\"Leave blank to generate\"/>
    </div>
    <div class=\"fix\"></div>
</div>

<input type=\"hidden\" name=\"config[domain][action]\" value=\"owndomain\"/>
", "mod_servicehosting_order.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-modules/Servicehosting/html_admin/mod_servicehosting_order.phtml");
    }
}
