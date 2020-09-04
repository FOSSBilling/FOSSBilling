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

/* mod_cookieconsent_index.phtml */
class __TwigTemplate_b2ef43fc2082a43ec1432770fb4a708092fee95906bebccf8a759869d23a2dc2 extends \Twig\Template
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
        echo "<div id=\"close-cookie-consent-block\" style=\"display:none; z-index: 101; opacity: 0.8; top: 0; left: 0; right: 0; margin-bottom: 5px; background: #fde073; text-align: center; line-height: 2.5; overflow: hidden; -webkit-box-shadow: 0 0 5px black; -moz-box-shadow: 0 0 5px black; box-shadow: 0 0 5px black;\">
        ";
        // line 2
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "cookieconsent_message", [], "any", false, false, false, 2), "html", null, true);
        echo "
    <button id=\"close-cookie-consent-btn\" style=\"padding: 5px; cursor: pointer; background: rgba(0, 0, 0, 0); float:right; border: 0; -webkit-appearance: none; font-size: 20px; font-weight: bold; line-height: 0.8em; color: #000; text-shadow: 0 1px 0 #FFF; opacity: .4; filter: alpha(opacity=20);\">Close &times;</button>
</div>

<script type=\"text/javascript\">
    \$(function () {
        if (localStorage.getItem(\"cookie-consent\") != 1) {
            \$('#close-cookie-consent-block').show();
        }
        \$('#close-cookie-consent-btn').click(function () {
            localStorage.setItem('cookie-consent', 1);
            \$('#close-cookie-consent-block').hide();
        });
    });
</script>";
    }

    public function getTemplateName()
    {
        return "mod_cookieconsent_index.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  40 => 2,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("<div id=\"close-cookie-consent-block\" style=\"display:none; z-index: 101; opacity: 0.8; top: 0; left: 0; right: 0; margin-bottom: 5px; background: #fde073; text-align: center; line-height: 2.5; overflow: hidden; -webkit-box-shadow: 0 0 5px black; -moz-box-shadow: 0 0 5px black; box-shadow: 0 0 5px black;\">
        {{ guest.cookieconsent_message }}
    <button id=\"close-cookie-consent-btn\" style=\"padding: 5px; cursor: pointer; background: rgba(0, 0, 0, 0); float:right; border: 0; -webkit-appearance: none; font-size: 20px; font-weight: bold; line-height: 0.8em; color: #000; text-shadow: 0 1px 0 #FFF; opacity: .4; filter: alpha(opacity=20);\">Close &times;</button>
</div>

<script type=\"text/javascript\">
    \$(function () {
        if (localStorage.getItem(\"cookie-consent\") != 1) {
            \$('#close-cookie-consent-block').show();
        }
        \$('#close-cookie-consent-btn').click(function () {
            localStorage.setItem('cookie-consent', 1);
            \$('#close-cookie-consent-block').hide();
        });
    });
</script>", "mod_cookieconsent_index.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/src/bb-modules/Cookieconsent/html_client/mod_cookieconsent_index.phtml");
    }
}
