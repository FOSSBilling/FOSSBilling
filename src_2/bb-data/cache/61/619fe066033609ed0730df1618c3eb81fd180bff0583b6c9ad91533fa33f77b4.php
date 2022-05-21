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

/* mod_extension_index.phtml */
class __TwigTemplate_c817ea39220d09378ea319814e42511c001a76a05e41db420c0f305502bd1faf extends Template
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
            'js' => [$this, 'block_js'],
        ];
    }

    protected function doGetParent(array $context)
    {
        // line 1
        return $this->loadTemplate(((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ajax", [], "any", false, false, false, 1)) ? ("layout_blank.phtml") : ("layout_default.phtml")), "mod_extension_index.phtml", 1);
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 3
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_extension_index.phtml", 3)->unwrap();
        // line 7
        $context["active_menu"] = "extensions";
        // line 1
        $this->getParent($context)->display($context, array_merge($this->blocks, $blocks));
    }

    // line 5
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo twig_escape_filter($this->env, gettext("Extensions"), "html", null, true);
    }

    // line 9
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 10
        echo "<div class=\"widget simpleTabs\">
    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">";
        // line 12
        echo twig_escape_filter($this->env, gettext("Extensions"), "html", null, true);
        echo "</a></li>
        <li><a href=\"#tab-core\">";
        // line 13
        echo twig_escape_filter($this->env, gettext("Update BoxBilling"), "html", null, true);
        echo "</a></li>
        <li><a href=\"#tab-about\">";
        // line 14
        echo twig_escape_filter($this->env, gettext("Learn more about extensions"), "html", null, true);
        echo "</a></li>
        <li><a href=\"#tab-hooks\">";
        // line 15
        echo twig_escape_filter($this->env, gettext("Hooks"), "html", null, true);
        echo "</a></li>
    </ul>

    <div class=\"tabs_container\">
        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-index\">
            <div class=\"help\">
                <h5>BoxBilling extensions</h5>
                <p>";
        // line 23
        echo twig_escape_filter($this->env, gettext("Activate or deactivate extensions"), "html", null, true);
        echo "</p>
            </div>
            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td width=\"3%\">&nbsp;</td>
                        <td width=\"20%\">";
        // line 29
        echo twig_escape_filter($this->env, gettext("Extension"), "html", null, true);
        echo "</td>
                        <td>";
        // line 30
        echo twig_escape_filter($this->env, gettext("Description"), "html", null, true);
        echo "</td>
                        <td style=\"width: 21%;\">";
        // line 31
        echo twig_escape_filter($this->env, gettext("Actions"), "html", null, true);
        echo "</td>
                    </tr>
                </thead>

                <tbody>
                ";
        // line 36
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "extension_get_list", [], "any", false, false, false, 36));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["_key"] => $context["ext"]) {
            // line 37
            echo "                <tr>
                    <td><img src=\"";
            // line 38
            echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, $context["ext"], "icon_url", [], "any", true, true, false, 38)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, $context["ext"], "icon_url", [], "any", false, false, false, 38), "images/icons/middlenav/cog.png")) : ("images/icons/middlenav/cog.png")), "html", null, true);
            echo "\" alt=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ext"], "name", [], "any", false, false, false, 38), "html", null, true);
            echo "\" style=\"width: 32px; height: 32px;\"/></td>
                    <td><strong>";
            // line 39
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ext"], "name", [], "any", false, false, false, 39), "html", null, true);
            echo "</strong> ";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ext"], "version", [], "any", false, false, false, 39), "html", null, true);
            echo "<br />by <a href=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ext"], "author_url", [], "any", false, false, false, 39), "html", null, true);
            echo "\" target=\"_blank\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ext"], "author", [], "any", false, false, false, 39), "html", null, true);
            echo "</a></td>
                    <td>
                        ";
            // line 41
            echo twig_bbmd_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ext"], "description", [], "any", false, false, false, 41));
            echo "
                        <a href=\"";
            // line 42
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ext"], "project_url", [], "any", false, false, false, 42), "html", null, true);
            echo "\" target=\"_blank\" title=\"Project details\">Learn more</a>
                    </td>
                    <td>
                        ";
            // line 45
            if ((twig_get_attribute($this->env, $this->source, $context["ext"], "type", [], "any", false, false, false, 45) == "mod")) {
                // line 46
                echo "                            ";
                if ((twig_get_attribute($this->env, $this->source, $context["ext"], "status", [], "any", false, false, false, 46) == "installed")) {
                    // line 47
                    echo "                            <a class=\"api-link bb-button btn14\" href=\"";
                    echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/extension/deactivate", ["type" => twig_get_attribute($this->env, $this->source, $context["ext"], "type", [], "any", false, false, false, 47), "id" => twig_get_attribute($this->env, $this->source, $context["ext"], "id", [], "any", false, false, false, 47)]);
                    echo "\" data-api-confirm=\"Are you sure?\" data-api-reload=\"Module was deactivated\" title=\"";
                    echo twig_escape_filter($this->env, gettext("Deactivate"), "html", null, true);
                    echo "\">
                                <img src=\"images/icons/dark/close.png\" alt=\"\" class=\"icon\">
                            </a>
                            ";
                } else {
                    // line 51
                    echo "                            <a class=\"btnIconLeft mr10 api-link\" href=\"";
                    echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/extension/activate", ["type" => twig_get_attribute($this->env, $this->source, $context["ext"], "type", [], "any", false, false, false, 51), "id" => twig_get_attribute($this->env, $this->source, $context["ext"], "id", [], "any", false, false, false, 51)]);
                    echo "\" data-api-confirm=\"Are you sure?\" data-api-jsonp=\"onAfterModuleActivated\">
                                <img src=\"images/icons/dark/cog.png\" alt=\"\" class=\"icon\"><span>Activate</span>
                            </a>
                            ";
                }
                // line 55
                echo "                        ";
            } else {
                // line 56
                echo "                        &nbsp;
                        ";
            }
            // line 58
            echo "
                        ";
            // line 59
            if (twig_get_attribute($this->env, $this->source, $context["ext"], "has_settings", [], "any", false, false, false, 59)) {
                // line 60
                echo "                            <a class=\"bb-button btn14\" href=\"";
                echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("extension/settings");
                echo "/";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ext"], "id", [], "any", false, false, false, 60), "html", null, true);
                echo "\">
                                <img src=\"images/icons/dark/pencil.png\" alt=\"\" class=\"icon\" title=\"";
                // line 61
                echo twig_escape_filter($this->env, gettext("Module settings"), "html", null, true);
                echo "\">
                            </a>
                        ";
            }
            // line 64
            echo "                    </td>
                </tr>
                ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 67
            echo "                    <tr>
                        <td colspan=\"4\">
                            ";
            // line 69
            echo twig_escape_filter($this->env, gettext("The list is empty"), "html", null, true);
            echo "
                        </td>
                    </tr>
                ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['ext'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 73
        echo "                </tbody>
                <tfoot>
                    <tr>
                        <td colspan=\"4\"></td>
                    </tr>
                </tfoot>
            </table>

            <div class=\"help\">
                <h5>BoxBilling modules on extension site</h5>
            </div>
            ";
        // line 84
        $this->loadTemplate("partial_extensions.phtml", "mod_extension_index.phtml", 84)->display($context);
        // line 85
        echo "        </div>

        <div class=\"tab_content nopadding\" id=\"tab-hooks\">
            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td>";
        // line 91
        echo twig_escape_filter($this->env, gettext("Extension"), "html", null, true);
        echo "</td>
                        <td>";
        // line 92
        echo twig_escape_filter($this->env, gettext("Hook"), "html", null, true);
        echo "</td>
                    </tr>
                </thead>

                <tbody>
                ";
        // line 97
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "hook_get_list", [0 => ["per_page" => 90]], "method", false, false, false, 97), "list", [], "any", false, false, false, 97));
        foreach ($context['_seq'] as $context["_key"] => $context["hook"]) {
            // line 98
            echo "                <tr>
                    <td>
                        ";
            // line 100
            echo twig_escape_filter($this->env, twig_capitalize_string_filter($this->env, twig_get_attribute($this->env, $this->source, $context["hook"], "rel_id", [], "any", false, false, false, 100)), "html", null, true);
            echo "
                    </td>
                    <td>
                        ";
            // line 103
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["hook"], "event", [], "any", false, false, false, 103), "html", null, true);
            echo "
                    </td>
                </tr>
                ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['hook'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 107
        echo "                </tbody>
            </table>
        </div>
            
        <div class=\"tab_content nopadding\" id=\"tab-core\">
            <div class=\"help\">
                <h3>";
        // line 113
        echo twig_escape_filter($this->env, gettext("Automatic update"), "html", null, true);
        echo "</h3>
                <p>";
        // line 114
        echo twig_escape_filter($this->env, gettext("Automatic updater is a tool to update BoxBilling to latest version in one click. Works on these hosting environments where PHP has permissions to overwrite files uploaded via FTP."), "html", null, true);
        echo "</p>
            </div>

            <div class=\"body\">
                ";
        // line 118
        echo twig_bbmd_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "system_release_notes", [], "any", false, false, false, 118));
        echo "
                <a href=\"";
        // line 119
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/extension/update_core");
        echo "\" title=\"\" class=\"btnIconLeft mr10 mt5 api-link\" data-api-confirm=\"Make sure that you have made database and files backups before proceeding with automatic update. Click OK when you are ready to continue.\" data-api-msg=\"Update complete\"><img src=\"images/icons/dark/cog.png\" alt=\"\" class=\"icon\"><span>Update BoxBilling</span></a>
            </div>

            <div class=\"help\">
                <h3>";
        // line 123
        echo twig_escape_filter($this->env, gettext("Manual update"), "html", null, true);
        echo "</h3>
                <p>";
        // line 124
        echo twig_escape_filter($this->env, gettext("Manual update is a solution when auto updater can not work on current installation environment"), "html", null, true);
        echo "</p>
            </div>

            <div class=\"body list arrowGreen\">
                <ul>
                    <li>Download the latest release from <a href=\"https://github.com/boxbilling/boxbilling/releases\" target=\"_blank\">GitHub</a></li>
                    <li>Extract the files into your computer</li>
                    <li>Upload (overwrite) extracted files via FTP to <strong>";
        // line 131
        echo twig_escape_filter($this->env, twig_constant("BB_PATH_ROOT"), "html", null, true);
        echo "</strong></li>
                    <li>When the uploading is done, execute <a href=\"";
        // line 132
        echo twig_escape_filter($this->env, twig_constant("BB_URL"), "html", null, true);
        echo "bb-update.php\" target=\"_blank\">";
        echo twig_escape_filter($this->env, twig_constant("BB_URL"), "html", null, true);
        echo "bb-update.php</a> in your browser</li>
                    <li>Your BoxBilling is now updated to latest version.</li>
                </ul>
            </div>
        </div>
        
        <div class=\"tab_content nopadding\" id=\"tab-about\">
            <div class=\"help\">
                <h3>";
        // line 140
        echo twig_escape_filter($this->env, gettext("Extending BoxBilling"), "html", null, true);
        echo "</h3>
                <p>";
        // line 141
        echo twig_escape_filter($this->env, gettext("BoxBilling gives developers all the capabilities to customize, integrate & extend the core system into your own website & applications."), "html", null, true);
        echo "</p>
            </div>
            <div class=\"body\">
                <h2 class=\"pt20\">More extensions</h2>
                <p>If you can not find extensions you are looking for in this admin area, please visit extensions site at <a href=\"http://extensions.boxbilling.org\" target=\"_blank\">http://extensions.boxbilling.org</a></p>

                <h2 class=\"pt20\">How to create new extension</h2>
                <div class=\"pt20 list arrowGrey\">
                    <ul>
                        <li>Create free account at <a href=\"https://github.com/signup/free\" target=\"_blank\">Github</a></li>
                        <li>Create new public repository dedicated for extension only</li>
                        <li>Repository must have plugin json file. <a href=\"http://extensions.boxbilling.org/article/getting-started\" target=\"_blank\">More information</a></li>
                        <li>Login to <a href=\"http://extensions.boxbilling.org/\" target=\"_blank\">BoxBilling extensions site</a> with github account.</li>
                        <li>If your repository contains valid json file, it can be registered in extensions site.</li>
                        <li>Registered extensions can be visible in every BoxBilling admin area.</li>
                    </ul>
                </div>

                <h2 class=\"pt20\">Supported extension types</h2>
                <div class=\"pt20 list arrowGrey\">
                    <ul>
                        <li>Payment gateways</li>
                        <li>Server managers</li>
                        <li>Domain registrars</li>
                        <li>Client area themes</li>
                        <li>Admin area themes</li>
                        <li>Translations for client and admin areas</li>
                        <li>Event hooks - Hooks can be injected into the process, change its behaviour, stop executing actions</li>
                        <li>API module - External program which uses BoxBilling API</li>
                    </ul>
                </div>

                <h2 class=\"pt20\">Extension support</h2>
                <p>Contact extensions developers directly for support. You can find issue tracker on extension site.</p>
            </div>
        </div>
    </div>
</div>
";
    }

    // line 181
    public function block_js($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 182
        echo "<script type=\"text/javascript\">
    function onAfterUpdate(result) {
    
    }

    function onAfterModuleActivated(result) {
        if(result.redirect && result.type == 'mod') {
            bb.redirect(\"";
        // line 189
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("");
        echo "/\" + result.id);
        } else if(result.has_settings) {
            bb.redirect(\"";
        // line 191
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("extension/settings");
        echo "/\" + result.id);
        } else {
            bb.reload();
        }
    }
</script>
";
    }

    public function getTemplateName()
    {
        return "mod_extension_index.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  404 => 191,  399 => 189,  390 => 182,  386 => 181,  343 => 141,  339 => 140,  326 => 132,  322 => 131,  312 => 124,  308 => 123,  301 => 119,  297 => 118,  290 => 114,  286 => 113,  278 => 107,  268 => 103,  262 => 100,  258 => 98,  254 => 97,  246 => 92,  242 => 91,  234 => 85,  232 => 84,  219 => 73,  209 => 69,  205 => 67,  198 => 64,  192 => 61,  185 => 60,  183 => 59,  180 => 58,  176 => 56,  173 => 55,  165 => 51,  155 => 47,  152 => 46,  150 => 45,  144 => 42,  140 => 41,  129 => 39,  123 => 38,  120 => 37,  115 => 36,  107 => 31,  103 => 30,  99 => 29,  90 => 23,  79 => 15,  75 => 14,  71 => 13,  67 => 12,  63 => 10,  59 => 9,  52 => 5,  48 => 1,  46 => 7,  44 => 3,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}

{% import \"macro_functions.phtml\" as mf %}

{% block meta_title %}{{ 'Extensions'|trans }}{% endblock %}

{% set active_menu = 'extensions' %}

{% block content %}
<div class=\"widget simpleTabs\">
    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">{{ 'Extensions'|trans }}</a></li>
        <li><a href=\"#tab-core\">{{ 'Update BoxBilling'|trans }}</a></li>
        <li><a href=\"#tab-about\">{{ 'Learn more about extensions'|trans }}</a></li>
        <li><a href=\"#tab-hooks\">{{ 'Hooks'|trans }}</a></li>
    </ul>

    <div class=\"tabs_container\">
        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-index\">
            <div class=\"help\">
                <h5>BoxBilling extensions</h5>
                <p>{{ 'Activate or deactivate extensions'|trans }}</p>
            </div>
            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td width=\"3%\">&nbsp;</td>
                        <td width=\"20%\">{{ 'Extension'|trans }}</td>
                        <td>{{ 'Description'|trans }}</td>
                        <td style=\"width: 21%;\">{{ 'Actions'|trans }}</td>
                    </tr>
                </thead>

                <tbody>
                {% for ext in admin.extension_get_list %}
                <tr>
                    <td><img src=\"{{ ext.icon_url|default('images/icons/middlenav/cog.png') }}\" alt=\"{{ext.name}}\" style=\"width: 32px; height: 32px;\"/></td>
                    <td><strong>{{ ext.name }}</strong> {{ ext.version }}<br />by <a href=\"{{ ext.author_url }}\" target=\"_blank\">{{ ext.author }}</a></td>
                    <td>
                        {{ ext.description|bbmd }}
                        <a href=\"{{ ext.project_url }}\" target=\"_blank\" title=\"Project details\">Learn more</a>
                    </td>
                    <td>
                        {% if ext.type == 'mod' %}
                            {% if ext.status == 'installed' %}
                            <a class=\"api-link bb-button btn14\" href=\"{{ 'api/admin/extension/deactivate'|link({ 'type': ext.type, 'id': ext.id }) }}\" data-api-confirm=\"Are you sure?\" data-api-reload=\"Module was deactivated\" title=\"{{ 'Deactivate'|trans }}\">
                                <img src=\"images/icons/dark/close.png\" alt=\"\" class=\"icon\">
                            </a>
                            {% else %}
                            <a class=\"btnIconLeft mr10 api-link\" href=\"{{ 'api/admin/extension/activate'|link({ 'type': ext.type, 'id': ext.id }) }}\" data-api-confirm=\"Are you sure?\" data-api-jsonp=\"onAfterModuleActivated\">
                                <img src=\"images/icons/dark/cog.png\" alt=\"\" class=\"icon\"><span>Activate</span>
                            </a>
                            {% endif %}
                        {% else %}
                        &nbsp;
                        {% endif %}

                        {% if ext.has_settings %}
                            <a class=\"bb-button btn14\" href=\"{{ 'extension/settings'|alink }}/{{ ext.id }}\">
                                <img src=\"images/icons/dark/pencil.png\" alt=\"\" class=\"icon\" title=\"{{ 'Module settings'|trans }}\">
                            </a>
                        {% endif %}
                    </td>
                </tr>
                {% else %}
                    <tr>
                        <td colspan=\"4\">
                            {{ 'The list is empty'|trans }}
                        </td>
                    </tr>
                {% endfor %}
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan=\"4\"></td>
                    </tr>
                </tfoot>
            </table>

            <div class=\"help\">
                <h5>BoxBilling modules on extension site</h5>
            </div>
            {% include \"partial_extensions.phtml\" %}
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-hooks\">
            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td>{{ 'Extension'|trans }}</td>
                        <td>{{ 'Hook'|trans }}</td>
                    </tr>
                </thead>

                <tbody>
                {% for hook in admin.hook_get_list({ \"per_page\": 90 }).list %}
                <tr>
                    <td>
                        {{ hook.rel_id|capitalize}}
                    </td>
                    <td>
                        {{ hook.event }}
                    </td>
                </tr>
                {% endfor %}
                </tbody>
            </table>
        </div>
            
        <div class=\"tab_content nopadding\" id=\"tab-core\">
            <div class=\"help\">
                <h3>{{ 'Automatic update'|trans }}</h3>
                <p>{{ 'Automatic updater is a tool to update BoxBilling to latest version in one click. Works on these hosting environments where PHP has permissions to overwrite files uploaded via FTP.'|trans }}</p>
            </div>

            <div class=\"body\">
                {{ admin.system_release_notes|bbmd }}
                <a href=\"{{ 'api/admin/extension/update_core'|link }}\" title=\"\" class=\"btnIconLeft mr10 mt5 api-link\" data-api-confirm=\"Make sure that you have made database and files backups before proceeding with automatic update. Click OK when you are ready to continue.\" data-api-msg=\"Update complete\"><img src=\"images/icons/dark/cog.png\" alt=\"\" class=\"icon\"><span>Update BoxBilling</span></a>
            </div>

            <div class=\"help\">
                <h3>{{ 'Manual update'|trans }}</h3>
                <p>{{ 'Manual update is a solution when auto updater can not work on current installation environment'|trans }}</p>
            </div>

            <div class=\"body list arrowGreen\">
                <ul>
                    <li>Download the latest release from <a href=\"https://github.com/boxbilling/boxbilling/releases\" target=\"_blank\">GitHub</a></li>
                    <li>Extract the files into your computer</li>
                    <li>Upload (overwrite) extracted files via FTP to <strong>{{ constant('BB_PATH_ROOT') }}</strong></li>
                    <li>When the uploading is done, execute <a href=\"{{ constant('BB_URL') }}bb-update.php\" target=\"_blank\">{{ constant('BB_URL') }}bb-update.php</a> in your browser</li>
                    <li>Your BoxBilling is now updated to latest version.</li>
                </ul>
            </div>
        </div>
        
        <div class=\"tab_content nopadding\" id=\"tab-about\">
            <div class=\"help\">
                <h3>{{ 'Extending BoxBilling'|trans }}</h3>
                <p>{{ 'BoxBilling gives developers all the capabilities to customize, integrate & extend the core system into your own website & applications.'|trans }}</p>
            </div>
            <div class=\"body\">
                <h2 class=\"pt20\">More extensions</h2>
                <p>If you can not find extensions you are looking for in this admin area, please visit extensions site at <a href=\"http://extensions.boxbilling.org\" target=\"_blank\">http://extensions.boxbilling.org</a></p>

                <h2 class=\"pt20\">How to create new extension</h2>
                <div class=\"pt20 list arrowGrey\">
                    <ul>
                        <li>Create free account at <a href=\"https://github.com/signup/free\" target=\"_blank\">Github</a></li>
                        <li>Create new public repository dedicated for extension only</li>
                        <li>Repository must have plugin json file. <a href=\"http://extensions.boxbilling.org/article/getting-started\" target=\"_blank\">More information</a></li>
                        <li>Login to <a href=\"http://extensions.boxbilling.org/\" target=\"_blank\">BoxBilling extensions site</a> with github account.</li>
                        <li>If your repository contains valid json file, it can be registered in extensions site.</li>
                        <li>Registered extensions can be visible in every BoxBilling admin area.</li>
                    </ul>
                </div>

                <h2 class=\"pt20\">Supported extension types</h2>
                <div class=\"pt20 list arrowGrey\">
                    <ul>
                        <li>Payment gateways</li>
                        <li>Server managers</li>
                        <li>Domain registrars</li>
                        <li>Client area themes</li>
                        <li>Admin area themes</li>
                        <li>Translations for client and admin areas</li>
                        <li>Event hooks - Hooks can be injected into the process, change its behaviour, stop executing actions</li>
                        <li>API module - External program which uses BoxBilling API</li>
                    </ul>
                </div>

                <h2 class=\"pt20\">Extension support</h2>
                <p>Contact extensions developers directly for support. You can find issue tracker on extension site.</p>
            </div>
        </div>
    </div>
</div>
{% endblock %}

{% block js %}
<script type=\"text/javascript\">
    function onAfterUpdate(result) {
    
    }

    function onAfterModuleActivated(result) {
        if(result.redirect && result.type == 'mod') {
            bb.redirect(\"{{ ''|alink}}/\" + result.id);
        } else if(result.has_settings) {
            bb.redirect(\"{{ 'extension/settings'|alink }}/\" + result.id);
        } else {
            bb.reload();
        }
    }
</script>
{% endblock %}
", "mod_extension_index.phtml", "/shared/httpd/up-boxbilling/FOSSBilling/src/bb-themes/admin_default/html/mod_extension_index.phtml");
    }
}
