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

/* mod_wysiwyg_js.phtml */
class __TwigTemplate_4b60cc0d6d24c35ba69c70d5dd4c10874f2dbc414f1af52d54493083cc98bc1a extends \Twig\Template
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
        $context["bb_editor"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "wysiwyg_editor", [], "any", false, false, false, 1);
        // line 2
        echo "
";
        // line 3
        if ((($context["bb_editor"] ?? null) == "markitup")) {
            // line 4
            echo twig_stylesheet_tag(twig_mod_asset_url("markitup/skins/boxbilling/style.css", "wysiwyg"));
            echo "
";
            // line 5
            echo twig_stylesheet_tag(twig_mod_asset_url("markitup/sets/markdown/style.css", "wysiwyg"));
            echo "
";
            // line 6
            echo twig_script_tag(twig_mod_asset_url("markitup/jquery.markitup.js", "wysiwyg"));
            echo "
";
            // line 7
            echo twig_script_tag(twig_mod_asset_url("markitup/sets/markdown/set.js", "wysiwyg"));
            echo "
<script type=\"text/javascript\" >
    \$(document).ready(function() {
        \$(\".";
            // line 10
            echo twig_escape_filter($this->env, ($context["class"] ?? null), "html", null, true);
            echo "\").markItUp(mySettings);
    });
</script>
";
        }
        // line 14
        echo "
";
        // line 15
        if ((($context["bb_editor"] ?? null) == "ckeditor")) {
            // line 16
            echo twig_script_tag(twig_mod_asset_url("ckeditor/ckeditor.js", "wysiwyg"));
            echo "
<script type=\"text/javascript\" >
    CKEDITOR.replaceClass = '";
            // line 18
            echo twig_escape_filter($this->env, ($context["class"] ?? null), "html", null, true);
            echo "';
</script>
";
        }
    }

    public function getTemplateName()
    {
        return "mod_wysiwyg_js.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  79 => 18,  74 => 16,  72 => 15,  69 => 14,  62 => 10,  56 => 7,  52 => 6,  48 => 5,  44 => 4,  42 => 3,  39 => 2,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% set bb_editor = admin.wysiwyg_editor %}

{% if bb_editor == 'markitup' %}
{{ 'markitup/skins/boxbilling/style.css' | mod_asset_url('wysiwyg') | stylesheet_tag }}
{{ 'markitup/sets/markdown/style.css' | mod_asset_url('wysiwyg') | stylesheet_tag }}
{{ 'markitup/jquery.markitup.js' | mod_asset_url('wysiwyg') | script_tag }}
{{ 'markitup/sets/markdown/set.js' | mod_asset_url('wysiwyg') | script_tag }}
<script type=\"text/javascript\" >
    \$(document).ready(function() {
        \$(\".{{class}}\").markItUp(mySettings);
    });
</script>
{% endif %}

{% if bb_editor == 'ckeditor' %}
{{ 'ckeditor/ckeditor.js' | mod_asset_url('wysiwyg') | script_tag }}
<script type=\"text/javascript\" >
    CKEDITOR.replaceClass = '{{class}}';
</script>
{% endif %}", "mod_wysiwyg_js.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-modules/Wysiwyg/html_admin/mod_wysiwyg_js.phtml");
    }
}
