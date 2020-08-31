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

/* layout_default.phtml */
class __TwigTemplate_0937c31262e91644fbdaf2eb2d09cba8f75b4c67f3079416e8c5f31f2a97da22 extends \Twig\Template
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
            'meta_description' => [$this, 'block_meta_description'],
            'opengraph' => [$this, 'block_opengraph'],
            'head' => [$this, 'block_head'],
            'js' => [$this, 'block_js'],
            'body_class' => [$this, 'block_body_class'],
            'body' => [$this, 'block_body'],
            'breadcrumbs' => [$this, 'block_breadcrumbs'],
            'breadcrumb' => [$this, 'block_breadcrumb'],
            'content_before' => [$this, 'block_content_before'],
            'content' => [$this, 'block_content'],
            'content_after' => [$this, 'block_content_after'],
        ];
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 1
        echo "<!DOCTYPE html>
<!--[if IE 8]>    <html class=\"no-js ie8 ie\" lang=\"en\"> <![endif]-->
<!--[if IE 9]>    <html class=\"no-js ie9 ie\" lang=\"en\"> <![endif]-->
<!--[if gt IE 9]><!--> <html class=\"no-js\" lang=\"en\"> <!--<![endif]-->
<head>
    <meta charset=\"utf-8\">
    <title>";
        // line 7
        $this->displayBlock('meta_title', $context, $blocks);
        echo "</title>

    <meta property=\"bb:url\" content=\"";
        // line 9
        echo twig_escape_filter($this->env, twig_constant("BB_URL"), "html", null, true);
        echo "\">
    <meta property=\"bb:client_area\" content=\"";
        // line 10
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/");
        echo "\">

    <meta name=\"description\" content=\"";
        // line 12
        $this->displayBlock('meta_description', $context, $blocks);
        echo "\">
    <meta name=\"robots\" content=\"";
        // line 13
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "meta_robots", [], "any", false, false, false, 13), "html", null, true);
        echo "\">
    <meta name=\"author\" content=\"";
        // line 14
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "meta_author", [], "any", false, false, false, 14), "html", null, true);
        echo "\">
    <meta name=\"generator\" content=\"BoxBilling ";
        // line 15
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_version", [], "any", false, false, false, 15), "html", null, true);
        echo "\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">

    ";
        // line 18
        $this->displayBlock('opengraph', $context, $blocks);
        // line 19
        echo "
    <link rel='stylesheet' type='text/css' href=\"";
        // line 20
        echo twig_asset_url($this->env, (("css/huraga-" . twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "color_scheme", [], "any", false, false, false, 20)) . ".css"));
        echo "\">
    <link rel='stylesheet' type='text/css' href=\"";
        // line 21
        echo twig_asset_url($this->env, "css/plugins/jquery.jgrowl.css");
        echo "\">
    <link rel='stylesheet' type='text/css' href=\"";
        // line 22
        echo twig_asset_url($this->env, "css/logos.css");
        echo "\">
    <link rel='stylesheet' type='text/css' href=\"";
        // line 23
        echo twig_asset_url($this->env, "css/flags16.css");
        echo "\">

    <link rel=\"shortcut icon\" href=\"";
        // line 25
        echo twig_asset_url($this->env, "favicon.ico");
        echo "\">
    <link rel=\"apple-touch-icon-precomposed\" sizes=\"114x114\" href=\"";
        // line 26
        echo twig_asset_url($this->env, "img/icons/apple-touch-icon-114-precomposed.png");
        echo "\">
    <link rel=\"apple-touch-icon-precomposed\" sizes=\"72x72\" href=\"";
        // line 27
        echo twig_asset_url($this->env, "img/icons/apple-touch-icon-72-precomposed.png");
        echo "\">
    <link rel=\"apple-touch-icon-precomposed\" href=\"";
        // line 28
        echo twig_asset_url($this->env, "img/icons/apple-touch-icon-57-precomposed.png");
        echo "\">

    <script src=\"";
        // line 30
        echo twig_asset_url($this->env, "js/libs/jquery.js");
        echo "\"></script>
    <script src=\"";
        // line 31
        echo twig_asset_url($this->env, "js/bb-jquery.js");
        echo "\" defer=\"defer\"></script>
    <script src=\"";
        // line 32
        echo twig_asset_url($this->env, "js/libs/modernizr.js");
        echo "\" defer=\"defer\"></script>
    <script src=\"";
        // line 33
        echo twig_asset_url($this->env, "js/bootstrap/bootstrap.min.js");
        echo "\" defer=\"defer\"></script>
    <script src=\"";
        // line 34
        echo twig_asset_url($this->env, "js/libs/selectivizr.js");
        echo "\" defer=\"defer\"></script>
    <script src=\"";
        // line 35
        echo twig_asset_url($this->env, "js/plugins/jGrowl/jquery.jgrowl.js");
        echo "\" defer=\"defer\"></script>

    ";
        // line 37
        $this->displayBlock('head', $context, $blocks);
        // line 38
        echo "    ";
        $this->displayBlock('js', $context, $blocks);
        // line 39
        echo "</head>

<body class=\"";
        // line 41
        $this->displayBlock('body_class', $context, $blocks);
        echo "\">
";
        // line 42
        if (twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "extension_is_on", [0 => ["mod" => "cookieconsent"]], "method", false, false, false, 42)) {
            // line 43
            echo "    ";
            $__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4 = null;
            try {
                $__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4 =                 $this->loadTemplate("mod_cookieconsent_index.phtml", "layout_default.phtml", 43);
            } catch (LoaderError $e) {
                // ignore missing template
            }
            if ($__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4) {
                $__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4->display($context);
            }
        }
        // line 45
        echo "
";
        // line 46
        $this->displayBlock('body', $context, $blocks);
        // line 272
        echo "
";
        // line 273
        if ((twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "top_menu_order", [], "any", false, false, false, 273) || twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "side_menu_order", [], "any", false, false, false, 273))) {
            // line 274
            echo "<script src=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("orderbutton/js", ["options" => "1", "width" => 600, "theme_color" => "green", "background_color" => "black", "background_opacity" => 50, "background_close" => 1, "bind_selector" => ".order-button", "border_radius" => 0, "loader" => 8]);
            echo "\" ></script>
";
        }
        // line 276
        echo "
";
        // line 277
        if (twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "inject_javascript", [], "any", false, false, false, 277)) {
            // line 278
            echo "    ";
            echo twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "inject_javascript", [], "any", false, false, false, 278);
            echo "
";
        }
        // line 280
        $__internal_62824350bc4502ee19dbc2e99fc6bdd3bd90e7d8dd6e72f42c35efd048542144 = null;
        try {
            $__internal_62824350bc4502ee19dbc2e99fc6bdd3bd90e7d8dd6e72f42c35efd048542144 =             $this->loadTemplate("partial_pending_messages.phtml", "layout_default.phtml", 280);
        } catch (LoaderError $e) {
            // ignore missing template
        }
        if ($__internal_62824350bc4502ee19dbc2e99fc6bdd3bd90e7d8dd6e72f42c35efd048542144) {
            $__internal_62824350bc4502ee19dbc2e99fc6bdd3bd90e7d8dd6e72f42c35efd048542144->display($context);
        }
        // line 281
        echo "</body>
</html>";
    }

    // line 7
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "meta_title", [], "any", false, false, false, 7), "html", null, true);
    }

    // line 12
    public function block_meta_description($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "meta_description", [], "any", false, false, false, 12), "html", null, true);
    }

    // line 18
    public function block_opengraph($context, array $blocks = [])
    {
        $macros = $this->macros;
    }

    // line 37
    public function block_head($context, array $blocks = [])
    {
        $macros = $this->macros;
    }

    // line 38
    public function block_js($context, array $blocks = [])
    {
        $macros = $this->macros;
    }

    // line 41
    public function block_body_class($context, array $blocks = [])
    {
        $macros = $this->macros;
    }

    // line 46
    public function block_body($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 47
        if ( !($context["client"] ?? null)) {
            // line 48
            echo "<script type=\"text/javascript\">\$(function(){bb.redirect('";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("login");
            echo "');});</script>
";
        } else {
            // line 50
            $context["profile"] = twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "client_get", [], "any", false, false, false, 50);
            // line 51
            $context["company"] = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_company", [], "any", false, false, false, 51);
            // line 52
            echo "
<div id=\"wrapper\">
    <header class=\"container\" id=\"header\">
            ";
            // line 55
            if (twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "show_page_header", [], "any", false, false, false, 55)) {
                // line 56
                echo "                <nav>
                <ul class=\"f16\">
                    ";
                // line 58
                $context["languages"] = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "extension_languages", [], "any", false, false, false, 58);
                // line 59
                echo "                    ";
                if ((twig_length_filter($this->env, ($context["languages"] ?? null)) > 1)) {
                    // line 60
                    echo "                    ";
                    $context["currentLang"] = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_locale", [], "any", false, false, false, 60);
                    // line 61
                    echo "                    ";
                    $context["countryCode"] = twig_slice($this->env, ($context["currentLang"] ?? null), 3, 2);
                    // line 62
                    echo "                        <li>
                            <div class=\"btn-group\">
                                <a class=\"btn dropdown-toggle\" data-toggle=\"dropdown\" href=\"#\">
                                    <span class=\"flag  ";
                    // line 65
                    echo twig_escape_filter($this->env, twig_lower_filter($this->env, ($context["countryCode"] ?? null)), "html", null, true);
                    echo "\"></span>
                                    ";
                    // line 66
                    echo twig_escape_filter($this->env, ($context["countryCode"] ?? null), "html", null, true);
                    echo "
                                    <span class=\"caret\"></span>
                                </a>
                                <ul class=\"dropdown-menu\">
                                    ";
                    // line 70
                    $context['_parent'] = $context;
                    $context['_seq'] = twig_ensure_traversable(($context["languages"] ?? null));
                    foreach ($context['_seq'] as $context["_key"] => $context["lang"]) {
                        // line 71
                        echo "                                    ";
                        $context["countryCode"] = twig_slice($this->env, $context["lang"], 3, 2);
                        // line 72
                        echo "                                    ";
                        if (($context["lang"] != ($context["currentLang"] ?? null))) {
                            // line 73
                            echo "                                            <li class=\"language_selector\" data-language-code=\"";
                            echo twig_escape_filter($this->env, $context["lang"], "html", null, true);
                            echo "\"><a href=\"javascript:;\"> <span class=\"flag ";
                            echo twig_escape_filter($this->env, twig_lower_filter($this->env, ($context["countryCode"] ?? null)), "html", null, true);
                            echo "\"></span> ";
                            echo twig_escape_filter($this->env, gettext($context["lang"]), "html", null, true);
                            echo "</a></li>
                                        ";
                        }
                        // line 75
                        echo "                                    ";
                    }
                    $_parent = $context['_parent'];
                    unset($context['_seq'], $context['_iterated'], $context['_key'], $context['lang'], $context['_parent'], $context['loop']);
                    $context = array_intersect_key($context, $_parent) + $_parent;
                    // line 76
                    echo "                                </ul>
                            </div>
                        </li>

                    ";
                }
                // line 81
                echo "
                    ";
                // line 82
                if (twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "top_menu_dashboard", [], "any", false, false, false, 82)) {
                    // line 83
                    echo "                    <li>
                        <a href=\"";
                    // line 84
                    echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("");
                    echo "\" class=\"show-tip\" title=\"";
                    echo gettext("Dashboard");
                    echo "\">";
                    echo gettext("Dashboard");
                    echo "</a>
                    </li>
                    ";
                }
                // line 87
                echo "                    ";
                if (twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "top_menu_order", [], "any", false, false, false, 87)) {
                    // line 88
                    echo "                    <li class=\"order-button\">
                        <a href=\"#\" class=\"show-tip\" title=\"";
                    // line 89
                    echo gettext("Order");
                    echo "\">";
                    echo gettext("Order services");
                    echo "</a>
                    </li>
                    ";
                }
                // line 92
                echo "                    ";
                if (twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "top_menu_profile", [], "any", false, false, false, 92)) {
                    // line 93
                    echo "                    <li>
                        <a href=\"";
                    // line 94
                    echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("client/me");
                    echo "\" class=\"show-tip\" title=\"";
                    echo gettext("Profile");
                    echo "\">";
                    echo gettext("Profile");
                    echo "</a>
                    </li>
                    ";
                }
                // line 97
                echo "
                    ";
                // line 98
                if (twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "top_menu_signout", [], "any", false, false, false, 98)) {
                    // line 99
                    echo "                    <li>
                        <a href=\"";
                    // line 100
                    echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("client/logout");
                    echo "\" class=\"show-tip\" title=\"";
                    echo gettext("Sign out");
                    echo "\">";
                    echo gettext("Sign out");
                    echo "</a>
                    </li>
                    ";
                }
                // line 103
                echo "                </ul>
            </nav>

            ";
                // line 106
                if (twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "show_company_logo", [], "any", false, false, false, 106)) {
                    // line 107
                    echo "                ";
                    if (twig_get_attribute($this->env, $this->source, ($context["company"] ?? null), "logo_url", [], "any", false, false, false, 107)) {
                        // line 108
                        echo "                    <h1>
                        <a href=\"";
                        // line 109
                        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/");
                        echo "\">
                            <img src=\"";
                        // line 110
                        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["company"] ?? null), "logo_url", [], "any", false, false, false, 110), "html", null, true);
                        echo "\" alt=\"";
                        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["company"] ?? null), "name", [], "any", false, false, false, 110), "html", null, true);
                        echo "\" title=\"";
                        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["company"] ?? null), "name", [], "any", false, false, false, 110), "html", null, true);
                        echo "\" style=\"max-height: 75px\"/>
                        </a>
                        <p></p>
                    </h1>
                ";
                    }
                    // line 115
                    echo "            ";
                }
                // line 116
                echo "
            ";
                // line 117
                if (twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "show_company_name", [], "any", false, false, false, 117)) {
                    // line 118
                    echo "            <p>";
                    echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["company"] ?? null), "name", [], "any", false, false, false, 118), "html", null, true);
                    echo "</p>
            ";
                }
                // line 120
                echo "        ";
            }
            // line 121
            echo "    </header>
    <section class=\"container\" role=\"main\">
        <div class=\"navigation-block\">

            <div class=\"navbar\">
                <a class=\"btn btn-navbar btn-block btn-large\" data-toggle=\"collapse\" data-target=\".nav-collapse\"><span class=\"awe-user\"></span> ";
            // line 126
            echo gettext("User profile");
            echo "</a>
            </div>

            <nav class=\"main-navigation nav-collapse collapse\" role=\"navigation\">
                ";
            // line 130
            $this->loadTemplate("partial_menu.phtml", "layout_default.phtml", 130)->display($context);
            // line 131
            echo "            </nav>

            ";
            // line 133
            if (twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "show_client_details", [], "any", false, false, false, 133)) {
                // line 134
                echo "            <section class=\"user-profile\">
                <figure>
                    <img alt=\"";
                // line 136
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "first_name", [], "any", false, false, false, 136), "html", null, true);
                echo " ";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "last_name", [], "any", false, false, false, 136), "html", null, true);
                echo " gravatar\" src=\"";
                echo twig_escape_filter($this->env, twig_gravatar_filter(twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "email", [], "any", false, false, false, 136), 60), "html", null, true);
                echo "\">
                    <figcaption>
                        <strong><a href=\"";
                // line 138
                echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("client/profile");
                echo "\" class=\"\">";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "first_name", [], "any", false, false, false, 138), "html", null, true);
                echo " ";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "last_name", [], "any", false, false, false, 138), "html", null, true);
                echo "</a></strong>
                        <em>";
                // line 139
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "company", [], "any", false, false, false, 139), "html", null, true);
                echo "</em>
                        <ul>
                            <li><a class=\"btn btn-primary btn-flat\" href=\"";
                // line 141
                echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("client/profile");
                echo "\">";
                echo gettext("profile");
                echo "</a></li>
                            <li><a class=\"btn btn-primary btn-flat\" href=\"";
                // line 142
                echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("client/logout");
                echo "\">";
                echo gettext("sign out");
                echo "</a></li>
                        </ul>
                    </figcaption>
                </figure>
            </section>
            ";
            }
            // line 148
            echo "
            ";
            // line 149
            if (twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "sidebar_balance_enabled", [], "any", false, false, false, 149)) {
                // line 150
                echo "            <section class=\"balance\">
                <h2>";
                // line 151
                echo gettext("Account balance");
                echo "</h2>
                <strong>";
                // line 152
                echo twig_money($this->env, twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "balance", [], "any", false, false, false, 152), twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "currency", [], "any", false, false, false, 152));
                echo "</strong>
            </section>
            ";
            }
            // line 155
            echo "
            ";
            // line 156
            if (twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "sidebar_note_enabled", [], "any", false, false, false, 156)) {
                // line 157
                echo "            <section class=\"side-note\">
                <div class=\"side-note-container\">
                    <h2>";
                // line 159
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "sidebar_note_title", [], "any", false, false, false, 159), "html", null, true);
                echo "</h2>
                    <p>";
                // line 160
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "sidebar_note_content", [], "any", false, false, false, 160), "html", null, true);
                echo "</p>
                </div>
                <div class=\"side-note-bottom\"></div>
            </section>
            ";
            }
            // line 165
            echo "        </div>

        <div class=\"content-block\" role=\"main\">

            ";
            // line 169
            if (twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "show_breadcrumb", [], "any", false, false, false, 169)) {
                // line 170
                echo "            ";
                $this->displayBlock('breadcrumbs', $context, $blocks);
                // line 178
                echo "            ";
            }
            // line 179
            echo "
            ";
            // line 180
            if (twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "show_page_header", [], "any", false, false, false, 180)) {
                // line 181
                echo "            ";
                // line 187
                echo "            ";
            }
            // line 188
            echo "
            ";
            // line 189
            $this->loadTemplate("partial_message.phtml", "layout_default.phtml", 189)->display($context);
            // line 190
            echo "
            ";
            // line 191
            $this->displayBlock('content_before', $context, $blocks);
            // line 192
            echo "            ";
            $this->displayBlock('content', $context, $blocks);
            // line 193
            echo "            ";
            $this->displayBlock('content_after', $context, $blocks);
            // line 194
            echo "        </div>
    </section>
    <div id=\"push\"></div>
</div>

";
            // line 199
            if (twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "footer_enabled", [], "any", false, false, false, 199)) {
                // line 200
                echo "<footer id=\"footer\" class=\"container\">
    <p>&copy; ";
                // line 201
                echo twig_escape_filter($this->env, twig_date_format_filter($this->env, ($context["now"] ?? null), "Y"), "html", null, true);
                echo " ";
                echo ((twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "footer_signature", [], "any", true, true, false, 201)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "footer_signature", [], "any", false, false, false, 201), twig_get_attribute($this->env, $this->source, ($context["company"] ?? null), "signature", [], "any", false, false, false, 201))) : (twig_get_attribute($this->env, $this->source, ($context["company"] ?? null), "signature", [], "any", false, false, false, 201)));
                echo "</p>
    <ul>
        ";
                // line 203
                if (twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "footer_link_1_enabled", [], "any", false, false, false, 203)) {
                    // line 204
                    echo "        <li>
            ";
                    // line 205
                    if ((twig_in_filter("http://", twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "footer_link_1_page", [], "any", false, false, false, 205)) || twig_in_filter("https://", twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "footer_link_1_page", [], "any", false, false, false, 205)))) {
                        // line 206
                        echo "            <a href=\"";
                        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "footer_link_1_page", [], "any", false, false, false, 206), "html", null, true);
                        echo "\">";
                        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "footer_link_1_title", [], "any", false, false, false, 206), "html", null, true);
                        echo "</a>
            ";
                    } else {
                        // line 208
                        echo "            <a href=\"";
                        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter(twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "footer_link_1_page", [], "any", false, false, false, 208));
                        echo "\">";
                        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "footer_link_1_title", [], "any", false, false, false, 208), "html", null, true);
                        echo "</a>
            ";
                    }
                    // line 210
                    echo "        </li>
        ";
                }
                // line 212
                echo "        ";
                if (twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "footer_link_2_enabled", [], "any", false, false, false, 212)) {
                    // line 213
                    echo "        <li>
            ";
                    // line 214
                    if ((twig_in_filter("http://", twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "footer_link_2_page", [], "any", false, false, false, 214)) || twig_in_filter("https://", twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "footer_link_2_page", [], "any", false, false, false, 214)))) {
                        // line 215
                        echo "            <a href=\"";
                        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "footer_link_2_page", [], "any", false, false, false, 215), "html", null, true);
                        echo "\">";
                        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "footer_link_2_title", [], "any", false, false, false, 215), "html", null, true);
                        echo "</a>
            ";
                    } else {
                        // line 217
                        echo "            <a href=\"";
                        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter(twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "footer_link_2_page", [], "any", false, false, false, 217));
                        echo "\">";
                        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "footer_link_2_title", [], "any", false, false, false, 217), "html", null, true);
                        echo "</a>
            ";
                    }
                    // line 219
                    echo "        </li>
        ";
                }
                // line 221
                echo "        ";
                if (twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "footer_link_3_enabled", [], "any", false, false, false, 221)) {
                    // line 222
                    echo "        <li>
            ";
                    // line 223
                    if ((twig_in_filter("http://", twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "footer_link_3_page", [], "any", false, false, false, 223)) || twig_in_filter("https://", twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "footer_link_3_page", [], "any", false, false, false, 223)))) {
                        // line 224
                        echo "            <a href=\"";
                        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "footer_link_3_page", [], "any", false, false, false, 224), "html", null, true);
                        echo "\">";
                        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "footer_link_3_title", [], "any", false, false, false, 224), "html", null, true);
                        echo "</a>
            ";
                    } else {
                        // line 226
                        echo "            <a href=\"";
                        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter(twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "footer_link_3_page", [], "any", false, false, false, 226));
                        echo "\">";
                        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "footer_link_3_title", [], "any", false, false, false, 226), "html", null, true);
                        echo "</a>

            ";
                    }
                    // line 229
                    echo "        </li>
        ";
                }
                // line 231
                echo "        ";
                if (twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "footer_link_4_enabled", [], "any", false, false, false, 231)) {
                    // line 232
                    echo "        <li>
            ";
                    // line 233
                    if ((twig_in_filter("http://", twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "footer_link_4_page", [], "any", false, false, false, 233)) || twig_in_filter("https://", twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "footer_link_4_page", [], "any", false, false, false, 233)))) {
                        // line 234
                        echo "            <a href=\"";
                        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "footer_link_4_page", [], "any", false, false, false, 234), "html", null, true);
                        echo "\">";
                        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "footer_link_4_title", [], "any", false, false, false, 234), "html", null, true);
                        echo "</a>
            ";
                    } else {
                        // line 236
                        echo "            <a href=\"";
                        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter(twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "footer_link_4_page", [], "any", false, false, false, 236));
                        echo "\">";
                        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "footer_link_4_title", [], "any", false, false, false, 236), "html", null, true);
                        echo "</a>
            ";
                    }
                    // line 238
                    echo "        </li>
        ";
                }
                // line 240
                echo "        ";
                if (twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "footer_link_5_enabled", [], "any", false, false, false, 240)) {
                    // line 241
                    echo "        <li>
            ";
                    // line 242
                    if ((twig_in_filter("http://", twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "footer_link_5_page", [], "any", false, false, false, 242)) || twig_in_filter("https://", twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "footer_link_5_page", [], "any", false, false, false, 242)))) {
                        // line 243
                        echo "            <a href=\"";
                        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "footer_link_5_page", [], "any", false, false, false, 243), "html", null, true);
                        echo "\">";
                        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "footer_link_5_title", [], "any", false, false, false, 243), "html", null, true);
                        echo "</a>
            ";
                    } else {
                        // line 245
                        echo "            <a href=\"";
                        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter(twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "footer_link_5_page", [], "any", false, false, false, 245));
                        echo "\">";
                        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "footer_link_5_title", [], "any", false, false, false, 245), "html", null, true);
                        echo "</a>
            ";
                    }
                    // line 247
                    echo "        </li>
        ";
                }
                // line 249
                echo "
        ";
                // line 251
                echo "        ";
                if (twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "extension_is_on", [0 => ["mod" => "branding"]], "method", false, false, false, 251)) {
                    // line 252
                    echo "        <li>
            <a href=\"http://www.boxbilling.com\" title=\"Billing Software\" target=\"_blank\">";
                    // line 253
                    echo gettext("Powered by BoxBilling");
                    echo "</a>
        </li>
        ";
                }
                // line 256
                echo "    </ul>
    ";
                // line 257
                if (twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "footer_to_top_enabled", [], "any", false, false, false, 257)) {
                    // line 258
                    echo "    <a href=\"#top\" class=\"btn btn-primary btn-flat pull-right\"><span class=\"awe-arrow-up\"></span> ";
                    echo gettext("Top");
                    echo "</a>
    ";
                }
                // line 260
                echo "</footer>
";
            }
            // line 262
            echo "
<div class=\"wait\" style=\"display:none\" onclick=\"\$(this).hide();\">
    <div class=\"popup_block\" style=\"position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: black; opacity: 0.5; -webkit-opacity: 0.5; -moz-opacity: 0.5; filter :  alpha(opacity=50); z-index: 2000\">
        <img src=\"";
            // line 265
            echo twig_asset_url($this->env, "img/loader.gif");
            echo "\" style=\"position: absolute; display: block; margin-left: auto; margin-right: auto; position: relative; top: 50%; opacity: 1; filter: alpha(opacity=100); z-index: 1003\">
    </div>
</div>
<noscript>NOTE: Many features on BoxBilling require Javascript and cookies. You can enable both via your browser's preference settings.</noscript>

";
        }
    }

    // line 170
    public function block_breadcrumbs($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 171
        echo "            <ul class=\"breadcrumb\">
                <li><a href=\"";
        // line 172
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/");
        echo "\">";
        echo gettext("Home");
        echo "</a> <span class=\"divider\">/</span></li>
                ";
        // line 173
        $this->displayBlock('breadcrumb', $context, $blocks);
        // line 176
        echo "            </ul>
            ";
    }

    // line 173
    public function block_breadcrumb($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 174
        echo "                <li class=\"active\">";
        echo gettext("Dashboard");
        echo "</li>
                ";
    }

    // line 191
    public function block_content_before($context, array $blocks = [])
    {
        $macros = $this->macros;
    }

    // line 192
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
    }

    // line 193
    public function block_content_after($context, array $blocks = [])
    {
        $macros = $this->macros;
    }

    public function getTemplateName()
    {
        return "layout_default.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  851 => 193,  845 => 192,  839 => 191,  832 => 174,  828 => 173,  823 => 176,  821 => 173,  815 => 172,  812 => 171,  808 => 170,  797 => 265,  792 => 262,  788 => 260,  782 => 258,  780 => 257,  777 => 256,  771 => 253,  768 => 252,  765 => 251,  762 => 249,  758 => 247,  750 => 245,  742 => 243,  740 => 242,  737 => 241,  734 => 240,  730 => 238,  722 => 236,  714 => 234,  712 => 233,  709 => 232,  706 => 231,  702 => 229,  693 => 226,  685 => 224,  683 => 223,  680 => 222,  677 => 221,  673 => 219,  665 => 217,  657 => 215,  655 => 214,  652 => 213,  649 => 212,  645 => 210,  637 => 208,  629 => 206,  627 => 205,  624 => 204,  622 => 203,  615 => 201,  612 => 200,  610 => 199,  603 => 194,  600 => 193,  597 => 192,  595 => 191,  592 => 190,  590 => 189,  587 => 188,  584 => 187,  582 => 181,  580 => 180,  577 => 179,  574 => 178,  571 => 170,  569 => 169,  563 => 165,  555 => 160,  551 => 159,  547 => 157,  545 => 156,  542 => 155,  536 => 152,  532 => 151,  529 => 150,  527 => 149,  524 => 148,  513 => 142,  507 => 141,  502 => 139,  494 => 138,  485 => 136,  481 => 134,  479 => 133,  475 => 131,  473 => 130,  466 => 126,  459 => 121,  456 => 120,  450 => 118,  448 => 117,  445 => 116,  442 => 115,  430 => 110,  426 => 109,  423 => 108,  420 => 107,  418 => 106,  413 => 103,  403 => 100,  400 => 99,  398 => 98,  395 => 97,  385 => 94,  382 => 93,  379 => 92,  371 => 89,  368 => 88,  365 => 87,  355 => 84,  352 => 83,  350 => 82,  347 => 81,  340 => 76,  334 => 75,  324 => 73,  321 => 72,  318 => 71,  314 => 70,  307 => 66,  303 => 65,  298 => 62,  295 => 61,  292 => 60,  289 => 59,  287 => 58,  283 => 56,  281 => 55,  276 => 52,  274 => 51,  272 => 50,  266 => 48,  264 => 47,  260 => 46,  254 => 41,  248 => 38,  242 => 37,  236 => 18,  229 => 12,  222 => 7,  217 => 281,  207 => 280,  201 => 278,  199 => 277,  196 => 276,  190 => 274,  188 => 273,  185 => 272,  183 => 46,  180 => 45,  168 => 43,  166 => 42,  162 => 41,  158 => 39,  155 => 38,  153 => 37,  148 => 35,  144 => 34,  140 => 33,  136 => 32,  132 => 31,  128 => 30,  123 => 28,  119 => 27,  115 => 26,  111 => 25,  106 => 23,  102 => 22,  98 => 21,  94 => 20,  91 => 19,  89 => 18,  83 => 15,  79 => 14,  75 => 13,  71 => 12,  66 => 10,  62 => 9,  57 => 7,  49 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("<!DOCTYPE html>
<!--[if IE 8]>    <html class=\"no-js ie8 ie\" lang=\"en\"> <![endif]-->
<!--[if IE 9]>    <html class=\"no-js ie9 ie\" lang=\"en\"> <![endif]-->
<!--[if gt IE 9]><!--> <html class=\"no-js\" lang=\"en\"> <!--<![endif]-->
<head>
    <meta charset=\"utf-8\">
    <title>{% block meta_title %}{{ settings.meta_title }}{% endblock %}</title>

    <meta property=\"bb:url\" content=\"{{ constant('BB_URL') }}\">
    <meta property=\"bb:client_area\" content=\"{{ '/'|link }}\">

    <meta name=\"description\" content=\"{% block meta_description %}{{ settings.meta_description }}{% endblock %}\">
    <meta name=\"robots\" content=\"{{ settings.meta_robots }}\">
    <meta name=\"author\" content=\"{{ settings.meta_author }}\">
    <meta name=\"generator\" content=\"BoxBilling {{ guest.system_version }}\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">

    {% block opengraph %}{% endblock %}

    <link rel='stylesheet' type='text/css' href=\"{{ ('css/huraga-' ~ settings.color_scheme ~ '.css') | asset_url }}\">
    <link rel='stylesheet' type='text/css' href=\"{{ 'css/plugins/jquery.jgrowl.css' | asset_url }}\">
    <link rel='stylesheet' type='text/css' href=\"{{ 'css/logos.css' | asset_url }}\">
    <link rel='stylesheet' type='text/css' href=\"{{ 'css/flags16.css' | asset_url }}\">

    <link rel=\"shortcut icon\" href=\"{{ 'favicon.ico' | asset_url }}\">
    <link rel=\"apple-touch-icon-precomposed\" sizes=\"114x114\" href=\"{{ 'img/icons/apple-touch-icon-114-precomposed.png' | asset_url }}\">
    <link rel=\"apple-touch-icon-precomposed\" sizes=\"72x72\" href=\"{{ 'img/icons/apple-touch-icon-72-precomposed.png' | asset_url }}\">
    <link rel=\"apple-touch-icon-precomposed\" href=\"{{ 'img/icons/apple-touch-icon-57-precomposed.png' | asset_url }}\">

    <script src=\"{{ 'js/libs/jquery.js' | asset_url }}\"></script>
    <script src=\"{{ 'js/bb-jquery.js' | asset_url }}\" defer=\"defer\"></script>
    <script src=\"{{ 'js/libs/modernizr.js' | asset_url }}\" defer=\"defer\"></script>
    <script src=\"{{ 'js/bootstrap/bootstrap.min.js' | asset_url}}\" defer=\"defer\"></script>
    <script src=\"{{ 'js/libs/selectivizr.js' | asset_url }}\" defer=\"defer\"></script>
    <script src=\"{{ 'js/plugins/jGrowl/jquery.jgrowl.js' | asset_url }}\" defer=\"defer\"></script>

    {% block head %}{% endblock %}
    {% block js %}{% endblock %}
</head>

<body class=\"{% block body_class %}{% endblock %}\">
{% if guest.extension_is_on({\"mod\":\"cookieconsent\"}) %}
    {% include 'mod_cookieconsent_index.phtml' ignore missing%}
{% endif %}

{% block body %}
{% if not client %}
<script type=\"text/javascript\">\$(function(){bb.redirect('{{ \"login\"|link }}');});</script>
{% else %}
{% set profile = client.client_get %}
{% set company = guest.system_company %}

<div id=\"wrapper\">
    <header class=\"container\" id=\"header\">
            {% if settings.show_page_header %}
                <nav>
                <ul class=\"f16\">
                    {% set languages = guest.extension_languages %}
                    {% if languages|length > 1 %}
                    {% set currentLang = guest.system_locale %}
                    {% set countryCode = currentLang | slice(3, 2) %}
                        <li>
                            <div class=\"btn-group\">
                                <a class=\"btn dropdown-toggle\" data-toggle=\"dropdown\" href=\"#\">
                                    <span class=\"flag  {{ countryCode | lower }}\"></span>
                                    {{ countryCode }}
                                    <span class=\"caret\"></span>
                                </a>
                                <ul class=\"dropdown-menu\">
                                    {% for lang in languages %}
                                    {% set countryCode = lang | slice(3, 2) %}
                                    {% if lang != currentLang %}
                                            <li class=\"language_selector\" data-language-code=\"{{ lang }}\"><a href=\"javascript:;\"> <span class=\"flag {{ countryCode | lower }}\"></span> {{ lang | trans }}</a></li>
                                        {% endif %}
                                    {% endfor %}
                                </ul>
                            </div>
                        </li>

                    {% endif %}

                    {% if settings.top_menu_dashboard %}
                    <li>
                        <a href=\"{{ ''|link }}\" class=\"show-tip\" title=\"{% trans 'Dashboard' %}\">{% trans 'Dashboard' %}</a>
                    </li>
                    {% endif %}
                    {% if settings.top_menu_order %}
                    <li class=\"order-button\">
                        <a href=\"#\" class=\"show-tip\" title=\"{% trans 'Order' %}\">{% trans 'Order services' %}</a>
                    </li>
                    {% endif %}
                    {% if settings.top_menu_profile %}
                    <li>
                        <a href=\"{{ 'client/me'|link }}\" class=\"show-tip\" title=\"{% trans 'Profile' %}\">{% trans 'Profile' %}</a>
                    </li>
                    {% endif %}

                    {% if settings.top_menu_signout %}
                    <li>
                        <a href=\"{{ 'client/logout' | link }}\" class=\"show-tip\" title=\"{% trans 'Sign out' %}\">{% trans 'Sign out' %}</a>
                    </li>
                    {% endif %}
                </ul>
            </nav>

            {% if settings.show_company_logo %}
                {% if company.logo_url %}
                    <h1>
                        <a href=\"{{'/'|link }}\">
                            <img src=\"{{company.logo_url}}\" alt=\"{{company.name}}\" title=\"{{company.name}}\" style=\"max-height: 75px\"/>
                        </a>
                        <p></p>
                    </h1>
                {% endif %}
            {% endif %}

            {% if settings.show_company_name %}
            <p>{{company.name}}</p>
            {% endif %}
        {% endif %}
    </header>
    <section class=\"container\" role=\"main\">
        <div class=\"navigation-block\">

            <div class=\"navbar\">
                <a class=\"btn btn-navbar btn-block btn-large\" data-toggle=\"collapse\" data-target=\".nav-collapse\"><span class=\"awe-user\"></span> {% trans 'User profile' %}</a>
            </div>

            <nav class=\"main-navigation nav-collapse collapse\" role=\"navigation\">
                {% include 'partial_menu.phtml' %}
            </nav>

            {% if settings.show_client_details %}
            <section class=\"user-profile\">
                <figure>
                    <img alt=\"{{profile.first_name}} {{profile.last_name}} gravatar\" src=\"{{ profile.email|gravatar(60) }}\">
                    <figcaption>
                        <strong><a href=\"{{ 'client/profile' | link}}\" class=\"\">{{profile.first_name}} {{profile.last_name}}</a></strong>
                        <em>{{ profile.company }}</em>
                        <ul>
                            <li><a class=\"btn btn-primary btn-flat\" href=\"{{ 'client/profile' | link}}\">{% trans 'profile' %}</a></li>
                            <li><a class=\"btn btn-primary btn-flat\" href=\"{{ 'client/logout' | link}}\">{% trans 'sign out' %}</a></li>
                        </ul>
                    </figcaption>
                </figure>
            </section>
            {% endif %}

            {% if settings.sidebar_balance_enabled %}
            <section class=\"balance\">
                <h2>{% trans 'Account balance' %}</h2>
                <strong>{{ profile.balance | money(profile.currency) }}</strong>
            </section>
            {% endif %}

            {% if settings.sidebar_note_enabled %}
            <section class=\"side-note\">
                <div class=\"side-note-container\">
                    <h2>{{ settings.sidebar_note_title }}</h2>
                    <p>{{ settings.sidebar_note_content }}</p>
                </div>
                <div class=\"side-note-bottom\"></div>
            </section>
            {% endif %}
        </div>

        <div class=\"content-block\" role=\"main\">

            {% if settings.show_breadcrumb %}
            {% block breadcrumbs %}
            <ul class=\"breadcrumb\">
                <li><a href=\"{{ '/'|link }}\">{% trans 'Home' %}</a> <span class=\"divider\">/</span></li>
                {% block breadcrumb %}
                <li class=\"active\">{% trans 'Dashboard' %}</li>
                {% endblock %}
            </ul>
            {% endblock %}
            {% endif %}

            {% if settings.show_page_header %}
            {# block page_header %}
                <article class=\"page-header\">
                    <h1>{{ block('meta_title') }}</h1>
                    <p>{{ block('meta_description') }}</p>
                </article>
            {% endblock #}
            {% endif %}

            {% include \"partial_message.phtml\" %}

            {% block content_before %}{% endblock %}
            {% block content %}{% endblock %}
            {% block content_after %}{% endblock %}
        </div>
    </section>
    <div id=\"push\"></div>
</div>

{% if settings.footer_enabled %}
<footer id=\"footer\" class=\"container\">
    <p>&copy; {{ now|date('Y') }} {{ settings.footer_signature | default(company.signature) | raw }}</p>
    <ul>
        {% if settings.footer_link_1_enabled %}
        <li>
            {% if 'http://' in settings.footer_link_1_page or  'https://' in settings.footer_link_1_page%}
            <a href=\"{{ settings.footer_link_1_page }}\">{{ settings.footer_link_1_title }}</a>
            {% else %}
            <a href=\"{{ settings.footer_link_1_page | link }}\">{{ settings.footer_link_1_title }}</a>
            {% endif %}
        </li>
        {% endif %}
        {% if settings.footer_link_2_enabled %}
        <li>
            {% if 'http://' in settings.footer_link_2_page or  'https://' in settings.footer_link_2_page%}
            <a href=\"{{ settings.footer_link_2_page }}\">{{ settings.footer_link_2_title }}</a>
            {% else %}
            <a href=\"{{ settings.footer_link_2_page | link}}\">{{ settings.footer_link_2_title }}</a>
            {% endif %}
        </li>
        {% endif %}
        {% if settings.footer_link_3_enabled %}
        <li>
            {% if 'http://' in settings.footer_link_3_page or  'https://' in settings.footer_link_3_page%}
            <a href=\"{{ settings.footer_link_3_page }}\">{{ settings.footer_link_3_title }}</a>
            {% else %}
            <a href=\"{{ settings.footer_link_3_page | link }}\">{{ settings.footer_link_3_title }}</a>

            {%endif %}
        </li>
        {% endif %}
        {% if settings.footer_link_4_enabled %}
        <li>
            {% if 'http://' in settings.footer_link_4_page or  'https://' in settings.footer_link_4_page%}
            <a href=\"{{ settings.footer_link_4_page }}\">{{ settings.footer_link_4_title }}</a>
            {% else %}
            <a href=\"{{ settings.footer_link_4_page | link }}\">{{ settings.footer_link_4_title }}</a>
            {% endif %}
        </li>
        {% endif %}
        {% if settings.footer_link_5_enabled %}
        <li>
            {% if 'http://' in settings.footer_link_5_page or  'https://' in settings.footer_link_5_page%}
            <a href=\"{{ settings.footer_link_5_page }}\">{{ settings.footer_link_5_title }}</a>
            {% else %}
            <a href=\"{{ settings.footer_link_5_page | link }}\">{{ settings.footer_link_5_title }}</a>
            {% endif %}
        </li>
        {% endif %}

        {# Removing this link is allowed only for BoxBilling PRO license owners. #}
        {% if guest.extension_is_on({\"mod\":'branding'}) %}
        <li>
            <a href=\"http://www.boxbilling.com\" title=\"Billing Software\" target=\"_blank\">{% trans 'Powered by BoxBilling' %}</a>
        </li>
        {% endif %}
    </ul>
    {% if settings.footer_to_top_enabled %}
    <a href=\"#top\" class=\"btn btn-primary btn-flat pull-right\"><span class=\"awe-arrow-up\"></span> {% trans 'Top' %}</a>
    {% endif %}
</footer>
{% endif %}

<div class=\"wait\" style=\"display:none\" onclick=\"\$(this).hide();\">
    <div class=\"popup_block\" style=\"position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: black; opacity: 0.5; -webkit-opacity: 0.5; -moz-opacity: 0.5; filter :  alpha(opacity=50); z-index: 2000\">
        <img src=\"{{ 'img/loader.gif' | asset_url}}\" style=\"position: absolute; display: block; margin-left: auto; margin-right: auto; position: relative; top: 50%; opacity: 1; filter: alpha(opacity=100); z-index: 1003\">
    </div>
</div>
<noscript>NOTE: Many features on BoxBilling require Javascript and cookies. You can enable both via your browser's preference settings.</noscript>

{% endif %}
{% endblock %}

{% if settings.top_menu_order or settings.side_menu_order %}
<script src=\"{{'orderbutton/js'| link({'options' : '1', 'width' : 600, 'theme_color' : 'green', 'background_color' : 'black', 'background_opacity' : 50, 'background_close' : 1, 'bind_selector' : '.order-button', 'border_radius' : 0, 'loader' : 8}) }}\" ></script>
{% endif %}

{% if settings.inject_javascript %}
    {{ settings.inject_javascript | raw}}
{% endif %}
{% include 'partial_pending_messages.phtml' ignore missing %}
</body>
</html>", "layout_default.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/src/bb-themes/huraga/html/layout_default.phtml");
    }
}
