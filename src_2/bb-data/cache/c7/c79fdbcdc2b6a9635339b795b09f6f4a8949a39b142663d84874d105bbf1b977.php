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

/* partial_batch_delete.phtml */
class __TwigTemplate_bebe015d3e3039b8c0d2baf7fff39d1c9b2c9acc0ee23e73ed5890c34c5c8f07 extends Template
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
        echo "<a class=\"bb-button btn14\" id=\"batch-delete-selected-btn\" style=\"margin: 10px\">
    <img src=\"images/icons/dark/trash.png\" alt=\"\" > ";
        // line 2
        echo twig_escape_filter($this->env, gettext("Delete selected"), "html", null, true);
        echo "</a>

<script>
    \$(function () {
        \$('#batch-delete-selected-btn').click(function () {
            if (\$('input.batch-delete-checkbox:checked').length) {
                jConfirm('Are you sure?', 'Confirm Batch Delete', function (r) {
                    if (r) {
                        var ids = \$('input.batch-delete-checkbox:checked').map(function () {
                            return \$(this).attr(\"data-item-id\");
                        }).get();
                        bb.post(
                            '";
        // line 14
        echo twig_escape_filter($this->env, ($context["action"] ?? null), "html", null, true);
        echo "',
                            {ids: ids},
                            function (result) {
                                bb.reload();
                            }
                        )
                    }
                });
            } else {
                jAlert('You need to select at least one item to delete');
            }
        });

        \$('input.batch-delete-master-checkbox').click(function () {
            \$('input.batch-delete-checkbox').prop('checked', this.checked);
        });
    });
</script>
";
    }

    public function getTemplateName()
    {
        return "partial_batch_delete.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  55 => 14,  40 => 2,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("<a class=\"bb-button btn14\" id=\"batch-delete-selected-btn\" style=\"margin: 10px\">
    <img src=\"images/icons/dark/trash.png\" alt=\"\" > {{ 'Delete selected'|trans }}</a>

<script>
    \$(function () {
        \$('#batch-delete-selected-btn').click(function () {
            if (\$('input.batch-delete-checkbox:checked').length) {
                jConfirm('Are you sure?', 'Confirm Batch Delete', function (r) {
                    if (r) {
                        var ids = \$('input.batch-delete-checkbox:checked').map(function () {
                            return \$(this).attr(\"data-item-id\");
                        }).get();
                        bb.post(
                            '{{ action }}',
                            {ids: ids},
                            function (result) {
                                bb.reload();
                            }
                        )
                    }
                });
            } else {
                jAlert('You need to select at least one item to delete');
            }
        });

        \$('input.batch-delete-master-checkbox').click(function () {
            \$('input.batch-delete-checkbox').prop('checked', this.checked);
        });
    });
</script>
", "partial_batch_delete.phtml", "/shared/httpd/up-boxbilling/FOSSBilling/src/bb-themes/admin_default/html/partial_batch_delete.phtml");
    }
}
