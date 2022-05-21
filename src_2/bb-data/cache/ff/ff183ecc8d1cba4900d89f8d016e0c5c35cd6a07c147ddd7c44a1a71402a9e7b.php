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
class __TwigTemplate_3e18b3b1b98224fb83c8d8b7850e4cbb18994d4212409f7cb851d7efeefa4b04 extends Template
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
        if ((($context["bb_editor"] ?? null) == "ckeditor")) {
            // line 4
            echo twig_script_tag(twig_mod_asset_url("ckeditor/ckeditor.js", "wysiwyg"));
            echo "
<script type=\"text/javascript\" >
    \$(document).ready(function() {
        CKEDITOR.replaceAll( '";
            // line 7
            echo twig_escape_filter($this->env, ($context["class"] ?? null), "html", null, true);
            echo "' );    
    })

    CKEDITOR.on('instanceReady', function(){
    \$.each( CKEDITOR.instances, function(instance) {
        CKEDITOR.instances[instance].on(\"change\", function(e) {
            for ( instance in CKEDITOR.instances )
            CKEDITOR.instances[instance].updateElement();
        });
   });
});
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
        return array (  50 => 7,  44 => 4,  42 => 3,  39 => 2,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% set bb_editor = admin.wysiwyg_editor %}

{% if bb_editor == 'ckeditor' %}
{{ 'ckeditor/ckeditor.js' | mod_asset_url('wysiwyg') | script_tag }}
<script type=\"text/javascript\" >
    \$(document).ready(function() {
        CKEDITOR.replaceAll( '{{class}}' );    
    })

    CKEDITOR.on('instanceReady', function(){
    \$.each( CKEDITOR.instances, function(instance) {
        CKEDITOR.instances[instance].on(\"change\", function(e) {
            for ( instance in CKEDITOR.instances )
            CKEDITOR.instances[instance].updateElement();
        });
   });
});
</script>
{% endif %}", "mod_wysiwyg_js.phtml", "/shared/httpd/up-boxbilling/FOSSBilling/src/bb-modules/Wysiwyg/html_admin/mod_wysiwyg_js.phtml");
    }
}
