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
class __TwigTemplate_c41044e419079f2cdd8ba63ea2a6add1ad670c7f208f1efe11ea92c514ff0366 extends \Twig\Template
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
        echo "<a class=\"bb-button btn14\" id=\"batch-delete-selected-btn\" style=\"margin: 10px\"><img src=\"images/icons/dark/trash.png\" alt=\"\" > ";
        echo gettext("Delete selected");
        echo "</a>
<script type=\"text/javascript\">
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
        // line 12
        echo twig_escape_filter($this->env, ($context["action"] ?? null), "html", null, true);
        echo "',
                            {ids: ids},
                            function (result) {
                                bb.reload();
                            })
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
</script>";
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
        return array (  52 => 12,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("<a class=\"bb-button btn14\" id=\"batch-delete-selected-btn\" style=\"margin: 10px\"><img src=\"images/icons/dark/trash.png\" alt=\"\" > {% trans 'Delete selected' %}</a>
<script type=\"text/javascript\">
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
                            })
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
</script>", "partial_batch_delete.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/src/bb-themes/admin_default/html/partial_batch_delete.phtml");
    }
}
