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

/* partial_footer.phtml */
class __TwigTemplate_001ef0af41d42510f5e4ad282774c95df45fd09a366419ec2b823160b73bd3b4 extends Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
            'js' => [$this, 'block_js'],
        ];
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 1
        echo "    \t<span>&copy; Copyright ";
        echo twig_escape_filter($this->env, twig_date_format_filter($this->env, ($context["now"] ?? null), "Y"), "html", null, true);
        echo ". All rights reserved. Powered by <a href=\"http://www.boxbilling.org\" title=\"Billing system\" target=\"_blank\">BoxBilling ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_version", [], "any", false, false, false, 1), "html", null, true);
        echo "</a>
                    <a href=\"#\" title=\"\" id=\"help_popup_button\" style=\"float:right;\">Help</a>
        </span>

        <div id=\"help_popup\" style=\"color: #424242; position: fixed; z-index: 99999; padding: 5px; margin: 0px; min-width: 310px; max-width: 310px; top: 30%; left: 45%; display: none;\">
            <h5 id=\"help_popup_title\">";
        // line 6
        echo twig_escape_filter($this->env, gettext("Help?"), "html", null, true);
        echo "</h5>
            <div id=\"help_popup_content\" class=\"confirm\">
                <div id=\"help_popup_message\">
                    <table class=\"tableStatic wide\">
                        <tbody>
                        <tr class=\"noborder\">
                            <td><i class=\"dark-sprite-icon sprite-help\" /></td>
                            <td class=\"noborder\"><a href=\"http://docs.boxbilling.org/\" title=\"\" target=\"_blank\">";
        // line 13
        echo twig_escape_filter($this->env, gettext("Documentation"), "html", null, true);
        echo "</a></td>
                        </tr>
                        <tr>
                            <td><i class=\"dark-sprite-icon sprite-ichat\" /></td>
                            <td class=\"noborder\"><a href=\"https://boxbilling.org/slack\" title=\"\" target=\"_blank\">";
        // line 17
        echo twig_escape_filter($this->env, gettext("Slack community"), "html", null, true);
        echo "</a></td>
                        </tr>
                        <tr>
                            <td><i class=\"dark-sprite-icon sprite-ichat\" /></td>
                            <td class=\"noborder\"><a href=\"https://boxbilling.org/discord\" title=\"\" target=\"_blank\">";
        // line 21
        echo twig_escape_filter($this->env, gettext("Discord community"), "html", null, true);
        echo "</a></td>
                        </tr>
                        <tr>
                            <td><i class=\"dark-sprite-icon sprite-pacman\" /></td>
                            <td class=\"noborder\"><a href=\"https://github.com/boxbilling/boxbilling/issues\" title=\"\" target=\"_blank\">";
        // line 25
        echo twig_escape_filter($this->env, gettext("Report bug"), "html", null, true);
        echo "</a></td>
                        </tr>
                        </tbody>

                    </table>
                </div>
                <div id=\"help_popup_panel\">
                    <input type=\"button\" class=\"blueBtn\" value=\"&nbsp;Close&nbsp;\" id=\"help_popup_close\" onclick=\"return susp.suspenderHide();\"/>
                </div>
            </div>
        </div>

        ";
        // line 37
        $this->displayBlock('js', $context, $blocks);
    }

    public function block_js($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 38
        echo "        <script type=\"text/javascript\">
            document.getElementById('help_popup_button').addEventListener('click',function(e){
                e.preventDefault();
                document.getElementById('help_popup').style.display = 'block';
            });
            document.getElementById('help_popup_close').addEventListener('click',function(e){
                e.preventDefault();
                document.getElementById('help_popup').style.display = 'none';
            });
        </script>
        ";
    }

    public function getTemplateName()
    {
        return "partial_footer.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  102 => 38,  95 => 37,  80 => 25,  73 => 21,  66 => 17,  59 => 13,  49 => 6,  38 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("    \t<span>&copy; Copyright {{ now|date('Y') }}. All rights reserved. Powered by <a href=\"http://www.boxbilling.org\" title=\"Billing system\" target=\"_blank\">BoxBilling {{ guest.system_version }}</a>
                    <a href=\"#\" title=\"\" id=\"help_popup_button\" style=\"float:right;\">Help</a>
        </span>

        <div id=\"help_popup\" style=\"color: #424242; position: fixed; z-index: 99999; padding: 5px; margin: 0px; min-width: 310px; max-width: 310px; top: 30%; left: 45%; display: none;\">
            <h5 id=\"help_popup_title\">{{ 'Help?'|trans }}</h5>
            <div id=\"help_popup_content\" class=\"confirm\">
                <div id=\"help_popup_message\">
                    <table class=\"tableStatic wide\">
                        <tbody>
                        <tr class=\"noborder\">
                            <td><i class=\"dark-sprite-icon sprite-help\" /></td>
                            <td class=\"noborder\"><a href=\"http://docs.boxbilling.org/\" title=\"\" target=\"_blank\">{{ 'Documentation'|trans }}</a></td>
                        </tr>
                        <tr>
                            <td><i class=\"dark-sprite-icon sprite-ichat\" /></td>
                            <td class=\"noborder\"><a href=\"https://boxbilling.org/slack\" title=\"\" target=\"_blank\">{{ 'Slack community'|trans }}</a></td>
                        </tr>
                        <tr>
                            <td><i class=\"dark-sprite-icon sprite-ichat\" /></td>
                            <td class=\"noborder\"><a href=\"https://boxbilling.org/discord\" title=\"\" target=\"_blank\">{{ 'Discord community'|trans }}</a></td>
                        </tr>
                        <tr>
                            <td><i class=\"dark-sprite-icon sprite-pacman\" /></td>
                            <td class=\"noborder\"><a href=\"https://github.com/boxbilling/boxbilling/issues\" title=\"\" target=\"_blank\">{{ 'Report bug'|trans }}</a></td>
                        </tr>
                        </tbody>

                    </table>
                </div>
                <div id=\"help_popup_panel\">
                    <input type=\"button\" class=\"blueBtn\" value=\"&nbsp;Close&nbsp;\" id=\"help_popup_close\" onclick=\"return susp.suspenderHide();\"/>
                </div>
            </div>
        </div>

        {% block js %}
        <script type=\"text/javascript\">
            document.getElementById('help_popup_button').addEventListener('click',function(e){
                e.preventDefault();
                document.getElementById('help_popup').style.display = 'block';
            });
            document.getElementById('help_popup_close').addEventListener('click',function(e){
                e.preventDefault();
                document.getElementById('help_popup').style.display = 'none';
            });
        </script>
        {% endblock %}
", "partial_footer.phtml", "/shared/httpd/up-boxbilling/FOSSBilling/src/bb-themes/admin_default/html/partial_footer.phtml");
    }
}
