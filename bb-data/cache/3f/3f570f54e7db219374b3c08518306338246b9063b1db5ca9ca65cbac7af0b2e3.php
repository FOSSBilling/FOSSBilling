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

/* mod_support_canned_selector.phtml */
class __TwigTemplate_5fd3f9edc95e7302daa5deabef67741049eef7596794c2ac6677096700b4e99c extends \Twig\Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
            'head' => [$this, 'block_head'],
        ];
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 1
        $this->displayBlock('head', $context, $blocks);
        // line 4
        echo "<div class=\"canned_response\" style=\"position: relative;\">
    <select name=\"canned_response\" class=\"canned\" style=\"position: absolute; top:6px; right: 10px; margin-bottom: 10px; min-width: 50%\">
        <option value=\"\"></option>
        ";
        // line 7
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "support_canned_pairs", [], "any", false, false, false, 7));
        foreach ($context['_seq'] as $context["ctitle"] => $context["cat"]) {
            // line 8
            echo "            <optgroup label=\"";
            echo twig_escape_filter($this->env, $context["ctitle"], "html", null, true);
            echo "\">
                ";
            // line 9
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable($context["cat"]);
            foreach ($context['_seq'] as $context["mid"] => $context["mtitle"]) {
                // line 10
                echo "                    <option value=\"";
                echo twig_escape_filter($this->env, $context["mid"], "html", null, true);
                echo "\">";
                echo twig_escape_filter($this->env, $context["mtitle"], "html", null, true);
                echo "</option>
                ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['mid'], $context['mtitle'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 12
            echo "            </optgroup>
        ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['ctitle'], $context['cat'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 14
        echo "    </select>
</div>

<script src='js/forms/select2.min.js'></script>
<script type=\"text/javascript\">
    \$(function () {
        \$('select.canned').on(\"change\", function () {
            var id = \$(this).val();
            if (id) bb.get('admin/support/canned_get', {id: id}, function (result) {
                bb.insertToTextarea('rt', result.content)
            });
            return false;
        });
        \$(\"select.canned\").select2({
            placeholder: \"Select Canned Response\"
        });
    });
</script>
";
    }

    // line 1
    public function block_head($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 2
        echo "<link rel='stylesheet' href='css/select2.css' />
";
    }

    public function getTemplateName()
    {
        return "mod_support_canned_selector.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  102 => 2,  98 => 1,  76 => 14,  69 => 12,  58 => 10,  54 => 9,  49 => 8,  45 => 7,  40 => 4,  38 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% block head%}
<link rel='stylesheet' href='css/select2.css' />
{% endblock %}
<div class=\"canned_response\" style=\"position: relative;\">
    <select name=\"canned_response\" class=\"canned\" style=\"position: absolute; top:6px; right: 10px; margin-bottom: 10px; min-width: 50%\">
        <option value=\"\"></option>
        {% for ctitle,cat in admin.support_canned_pairs %}
            <optgroup label=\"{{ctitle}}\">
                {% for mid,mtitle in cat %}
                    <option value=\"{{ mid }}\">{{ mtitle }}</option>
                {% endfor %}
            </optgroup>
        {% endfor %}
    </select>
</div>

<script src='js/forms/select2.min.js'></script>
<script type=\"text/javascript\">
    \$(function () {
        \$('select.canned').on(\"change\", function () {
            var id = \$(this).val();
            if (id) bb.get('admin/support/canned_get', {id: id}, function (result) {
                bb.insertToTextarea('rt', result.content)
            });
            return false;
        });
        \$(\"select.canned\").select2({
            placeholder: \"Select Canned Response\"
        });
    });
</script>
", "mod_support_canned_selector.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-modules/Support/html_admin/mod_support_canned_selector.phtml");
    }
}
