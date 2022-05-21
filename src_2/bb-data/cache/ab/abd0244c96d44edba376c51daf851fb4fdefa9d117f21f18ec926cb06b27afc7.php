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
class __TwigTemplate_3c7d7e620adeae9e49add884ef042c3cf93500fa2c127ecb4998540f9f85ad9a extends Template
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
            'meta_keywords' => [$this, 'block_meta_keywords'],
            'opengraph' => [$this, 'block_opengraph'],
            'head' => [$this, 'block_head'],
            'header_buttons' => [$this, 'block_header_buttons'],
            'content_before' => [$this, 'block_content_before'],
            'content' => [$this, 'block_content'],
            'content_after' => [$this, 'block_content_after'],
            'sidebar' => [$this, 'block_sidebar'],
            'sidebar2' => [$this, 'block_sidebar2'],
            'js' => [$this, 'block_js'],
        ];
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 1
        $context["company"] = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_company", [], "any", false, false, false, 1);
        // line 2
        echo "<!DOCTYPE html>
<html>
<head>
    <title>";
        // line 5
        $this->displayBlock('meta_title', $context, $blocks);
        echo " ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "meta_title", [], "any", false, false, false, 5), "html", null, true);
        echo "</title>

    <meta property=\"bb:url\" content=\"";
        // line 7
        echo twig_escape_filter($this->env, twig_constant("BB_URL"), "html", null, true);
        echo "\"/>
    <meta property=\"bb:client_area\" content=\"";
        // line 8
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/");
        echo "\"/>
    
    <meta name=\"description\" content=\"";
        // line 10
        $this->displayBlock('meta_description', $context, $blocks);
        echo "\" />
    <meta name=\"keywords\" content=\"";
        // line 11
        $this->displayBlock('meta_keywords', $context, $blocks);
        echo "\" />
    <meta name=\"robots\" content=\"index, follow\" />
    <meta http-equiv=\"content-type\" content=\"text/html; charset=utf-8\" />

    <link rel=\"shortcut icon\" href=\"";
        // line 15
        echo twig_asset_url($this->env, "favicon.ico");
        echo "\" />
    <link href=\"https://fonts.googleapis.com/css?family=Cuprum\" rel=\"stylesheet\" type=\"text/css\" />
    ";
        // line 17
        echo twig_stylesheet_tag(twig_asset_url($this->env, "css/print.css"), "print");
        echo "
    ";
        // line 18
        echo twig_stylesheet_tag(twig_asset_url($this->env, "css/style.css"));
        echo "

    ";
        // line 20
        echo twig_script_tag(twig_asset_url($this->env, "js/jquery.min.js"));
        echo "
    ";
        // line 21
        echo twig_script_tag(twig_asset_url($this->env, "js/jquery.tipsy.js"));
        echo "
    ";
        // line 22
        echo twig_script_tag(twig_asset_url($this->env, "js/bb-jquery.js"));
        echo "

    ";
        // line 24
        $this->displayBlock('opengraph', $context, $blocks);
        // line 25
        echo "    ";
        $this->displayBlock('head', $context, $blocks);
        // line 26
        echo "</head>

<body>
";
        // line 29
        if (twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "extension_is_on", [0 => ["mod" => "cookieconsent"]], "method", false, false, false, 29)) {
            // line 30
            echo "    ";
            $__internal_compile_0 = null;
            try {
                $__internal_compile_0 =                 $this->loadTemplate("mod_cookieconsent_index.phtml", "layout_default.phtml", 30);
            } catch (LoaderError $e) {
                // ignore missing template
            }
            if ($__internal_compile_0) {
                $__internal_compile_0->display($context);
            }
        }
        // line 32
        echo "<div class=\"header\">
    <div class=\"container_16\">
        <div class=\"grid_4\" >
            <a href=\"";
        // line 35
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("");
        echo "\">
                ";
        // line 36
        if (twig_get_attribute($this->env, $this->source, ($context["company"] ?? null), "logo_url", [], "any", false, false, false, 36)) {
            // line 37
            echo "                    <img src=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["company"] ?? null), "logo_url", [], "any", false, false, false, 37), "html", null, true);
            echo "\" alt=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["company"] ?? null), "name", [], "any", false, false, false, 37), "html", null, true);
            echo "\" style=\"max-height: 60px\"/>
                ";
        } else {
            // line 39
            echo "                    <img src=\" ";
            echo twig_asset_url($this->env, "images/logo.png");
            echo "\" alt=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["company"] ?? null), "name", [], "any", false, false, false, 39), "html", null, true);
            echo "\" style=\"max-height: 60px\"/>
                ";
        }
        // line 41
        echo "            </a>
        </div>

        <div class=\"grid_12\">
            ";
        // line 45
        if (($context["client"] ?? null)) {
            // line 46
            echo "            <ul class=\"middleNav\">
                <li>
                    <a href=\"";
            // line 48
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("dashboard");
            echo "\" class=\"show-tip\" title=\"";
            echo twig_escape_filter($this->env, gettext("Dashboard"), "html", null, true);
            echo "\"><span class=\"big-dark-icon i-home\"></span></a>
                </li>
                <li>
                    <a href=\"";
            // line 51
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("cart");
            echo "\" class=\"show-tip\" title=\"";
            echo twig_escape_filter($this->env, gettext("Shopping cart"), "html", null, true);
            echo "\"><span class=\"big-dark-icon i-cart\"></span></a>
                </li>
                <li>
                    <a href=\"";
            // line 54
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("client/me");
            echo "\" class=\"show-tip\" title=\"";
            echo twig_escape_filter($this->env, gettext("Profile"), "html", null, true);
            echo "\"><span class=\"big-dark-icon i-profile\"></span></a>
                </li>
                <li>
                    <a href=\"client/client/logout\" class=\"show-tip api\" title=\"";
            // line 57
            echo twig_escape_filter($this->env, gettext("Sign out"), "html", null, true);
            echo "\"><span class=\"big-dark-icon i-logout\"></span></a>
                </li>
            </ul>

            ";
        } else {
            // line 62
            echo "            ";
            $this->displayBlock('header_buttons', $context, $blocks);
            // line 68
            echo "            ";
        }
        // line 69
        echo "        </div>
        <div class=\"clear\"></div>
    </div>
</div>

<div id=\"main\">
    <div class=\"container_16\">
        ";
        // line 76
        $this->displayBlock('content_before', $context, $blocks);
        // line 77
        echo "
        <div class=\"grid_12\">
            ";
        // line 79
        $this->loadTemplate("partial_message.phtml", "layout_default.phtml", 79)->display($context);
        // line 80
        echo "            ";
        $this->displayBlock('content', $context, $blocks);
        // line 81
        echo "            ";
        $this->displayBlock('content_after', $context, $blocks);
        // line 82
        echo "        </div>

        <div class=\"grid_4\">
            ";
        // line 85
        $this->displayBlock('sidebar', $context, $blocks);
        // line 86
        echo "
            ";
        // line 87
        if ( !($context["client"] ?? null)) {
            // line 88
            echo "            <div class=\"widget\" id=\"login-form\" style=\"display: none;\" >
                <div class=\"head\">
                    <h2 class=\"dark-icon i-profile\">";
            // line 90
            echo twig_escape_filter($this->env, gettext("Login to client area"), "html", null, true);
            echo "</h2>
                </div>
                <div class=\"block\">
                <form action=\"\" method=\"post\" class=\"api_form\" data-api-url=\"";
            // line 93
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/guest/client/login");
            echo "\" data-api-redirect=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("");
            echo "\">
                    <fieldset>
                        <legend>";
            // line 95
            echo twig_escape_filter($this->env, gettext("Login to client area"), "html", null, true);
            echo "</legend>
                        <p>
                            <input type=\"email\" name=\"email\" value=\"";
            // line 97
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "email", [], "any", false, false, false, 97), "html", null, true);
            echo "\" required=\"required\" placeholder=\"";
            echo twig_escape_filter($this->env, gettext("Email address"), "html", null, true);
            echo "\">
                        </p>
                        <p>
                            <input type=\"password\" name=\"password\" value=\"\" required=\"required\" placeholder=\"";
            // line 100
            echo twig_escape_filter($this->env, gettext("Password"), "html", null, true);
            echo "\">
                        </p>
                        <a class=\"bb-button\" href=\"";
            // line 102
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("reset-password");
            echo "\">";
            echo twig_escape_filter($this->env, gettext("Reset password"), "html", null, true);
            echo "</a>
                        <input type=\"hidden\" name=\"remember\" value=\"1\" />
                        <input class=\"bb-button bb-button-submit\" type=\"submit\" value=\"";
            // line 104
            echo twig_escape_filter($this->env, gettext("Sign in"), "html", null, true);
            echo "\" style=\"float: right\">
                    </fieldset>
                </form>
                </div>
            </div>
            ";
        }
        // line 110
        echo "            
            <div class=\"gradient\" style=\"margin-bottom: 10px;\">
                <nav>";
        // line 112
        $this->loadTemplate("partial_menu.phtml", "layout_default.phtml", 112)->display($context);
        echo "</nav>
            </div>

            ";
        // line 115
        $context["languages"] = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "extension_languages", [], "any", false, false, false, 115);
        // line 116
        echo "            ";
        if ((twig_length_filter($this->env, ($context["languages"] ?? null)) > 1)) {
            // line 117
            echo "            <div class=\"widget\">
                <div class=\"head\">
                    <h2 class=\"dark-icon i-drag\">";
            // line 119
            echo twig_escape_filter($this->env, gettext("Select language"), "html", null, true);
            echo "</h2>
                </div>
                <div class=\"block\">
                    <select name=\"lang\" class=\"language_selector\">
                    ";
            // line 123
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(($context["languages"] ?? null));
            foreach ($context['_seq'] as $context["_key"] => $context["lang"]) {
                // line 124
                echo "                        <option value=\"";
                echo twig_escape_filter($this->env, $context["lang"], "html", null, true);
                echo "\" class=\"lang_";
                echo twig_escape_filter($this->env, $context["lang"], "html", null, true);
                echo "\">";
                echo twig_escape_filter($this->env, gettext($context["lang"]), "html", null, true);
                echo "</option>
                    ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['lang'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 126
            echo "                    </select>
                </div>
            </div>
            ";
        }
        // line 130
        echo "            
            ";
        // line 131
        $this->displayBlock('sidebar2', $context, $blocks);
        // line 132
        echo "
        </div>
        <div class=\"clear\"></div>
    </div>
</div>

<div class=\"footer\">
    <div class=\"container_16\">
        ";
        // line 140
        if (twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "extension_is_on", [0 => ["mod" => "kb"]], "method", false, false, false, 140)) {
            // line 141
            echo "        <div class=\"grid_4\">
            <div class=\"box\">
                <h2>";
            // line 143
            echo twig_escape_filter($this->env, gettext("Popular articles"), "html", null, true);
            echo "</h2>
                <div class=\"block\">
                    <ul>
                        ";
            // line 146
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "kb_article_get_list", [0 => ["per_page" => 4, "page" => 1]], "method", false, false, false, 146), "list", [], "any", false, false, false, 146));
            foreach ($context['_seq'] as $context["i"] => $context["article"]) {
                // line 147
                echo "                            <li><a href=\"";
                echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/kb");
                echo "/";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["article"], "category", [], "any", false, false, false, 147), "slug", [], "any", false, false, false, 147), "html", null, true);
                echo "/";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["article"], "slug", [], "any", false, false, false, 147), "html", null, true);
                echo "\">";
                echo twig_escape_filter($this->env, twig_truncate_filter($this->env, twig_get_attribute($this->env, $this->source, $context["article"], "title", [], "any", false, false, false, 147), 30), "html", null, true);
                echo "</a></li>
                        ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['i'], $context['article'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 149
            echo "                    </ul>
                </div>
            </div>
        </div>
        ";
        }
        // line 154
        echo "
        ";
        // line 155
        if (twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "extension_is_on", [0 => ["mod" => "news"]], "method", false, false, false, 155)) {
            // line 156
            echo "        <div class=\"grid_4\">
            <div class=\"box\">
                <h2>";
            // line 158
            echo twig_escape_filter($this->env, gettext("Recent posts"), "html", null, true);
            echo "</h2>
                <div class=\"block\">
                    <ul>
                        ";
            // line 161
            $context["posts"] = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "news_get_list", [0 => ["per_page" => 4, "page" => 1]], "method", false, false, false, 161);
            // line 162
            echo "                        ";
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["posts"] ?? null), "list", [], "any", false, false, false, 162));
            foreach ($context['_seq'] as $context["i"] => $context["post"]) {
                // line 163
                echo "                        <li>
                            <a href=\"";
                // line 164
                echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/news");
                echo "/";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["post"], "slug", [], "any", false, false, false, 164), "html", null, true);
                echo "\">";
                echo twig_escape_filter($this->env, twig_truncate_filter($this->env, twig_get_attribute($this->env, $this->source, $context["post"], "title", [], "any", false, false, false, 164), 30), "html", null, true);
                echo "</a>
                        </li>
                        ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['i'], $context['post'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 167
            echo "                    </ul>
                </div>
            </div>
        </div>
        ";
        }
        // line 172
        echo "        
        <div class=\"grid_4\">
            <div class=\"box\">
                <h2>";
        // line 175
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["company"] ?? null), "name", [], "any", false, false, false, 175), "html", null, true);
        echo "</h2>
                <div class=\"block\">
                    <ul>
                        <li>
                            <a href=\"";
        // line 179
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/about-us");
        echo "\">";
        echo twig_escape_filter($this->env, gettext("About us"), "html", null, true);
        echo "</a>
                        </li>
                        <li>
                            <a href=\"";
        // line 182
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/tos");
        echo "\">";
        echo twig_escape_filter($this->env, gettext("Terms and Conditions"), "html", null, true);
        echo "</a>
                        </li>
                        <li>
                            <a href=\"";
        // line 185
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/privacy-policy");
        echo "\">";
        echo twig_escape_filter($this->env, gettext("Privacy Policy"), "html", null, true);
        echo "</a>
                        </li>
                        ";
        // line 187
        if (twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "extension_is_on", [0 => ["mod" => "branding"]], "method", false, false, false, 187)) {
            // line 188
            echo "                        <li>
                            <a href=\"http://www.boxbilling.org\" title=\"Billing Software\" target=\"_blank\">Billing software</a>
                        </li>
                        ";
        }
        // line 192
        echo "                    </ul>
                </div>
            </div>
        </div>

        <div class=\"grid_4\">
            ";
        // line 198
        if (twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "extension_is_on", [0 => ["mod" => "branding"]], "method", false, false, false, 198)) {
            // line 199
            echo "            <p class=\"logo\">
                <a class=\"boxbilling\" href=\"http://www.boxbilling.org\" title=\"Invoicing software\" target=\"_blank\">";
            // line 200
            echo twig_img_tag(twig_asset_url($this->env, "images/boxbilling-logo.png"), "Business software");
            echo "</a>
            </p>
            ";
        }
        // line 203
        echo "        </div>

        <div class=\"clear\"></div>
    </div>
</div>

<div class=\"footer lower\">
    <div class=\"container_16\">
        <div class=\"grid_16\">
            <div class=\"box\">
                <div class=\"block\">
                    <p>
                    © ";
        // line 215
        echo twig_escape_filter($this->env, twig_date_format_filter($this->env, ($context["now"] ?? null), "Y"), "html", null, true);
        echo " ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["company"] ?? null), "signature", [], "any", false, false, false, 215), "html", null, true);
        echo "
                    </p>
                </div>
            </div>
        </div>
        <div class=\"clear\"></div>
    </div>
</div>
<div class=\"loading dim\" style=\"display:none\"><div class=\"popup_block\"><h3>";
        // line 223
        echo twig_escape_filter($this->env, gettext("Loading .."), "html", null, true);
        echo "</h3></div></div>
    ";
        // line 224
        $this->displayBlock('js', $context, $blocks);
        // line 225
        echo "    <noscript>NOTE: Many features on BoxBilling require Javascript and cookies. You can enable both via your browser's preference settings.</noscript>
";
        // line 226
        $__internal_compile_1 = null;
        try {
            $__internal_compile_1 =             $this->loadTemplate("partial_pending_messages.phtml", "layout_default.phtml", 226);
        } catch (LoaderError $e) {
            // ignore missing template
        }
        if ($__internal_compile_1) {
            $__internal_compile_1->display($context);
        }
        // line 227
        echo "</body>
</html>
";
    }

    // line 5
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
    }

    // line 10
    public function block_meta_description($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "meta_description", [], "any", false, false, false, 10), "html", null, true);
    }

    // line 11
    public function block_meta_keywords($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "meta_keywords", [], "any", false, false, false, 11), "html", null, true);
    }

    // line 24
    public function block_opengraph($context, array $blocks = [])
    {
        $macros = $this->macros;
    }

    // line 25
    public function block_head($context, array $blocks = [])
    {
        $macros = $this->macros;
    }

    // line 62
    public function block_header_buttons($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 63
        echo "            <div class=\"top-buttons\">
                <a id=\"login-form-link\" class=\"bb-button bb-button-submit\" href=\"";
        // line 64
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("login");
        echo "\">";
        echo twig_escape_filter($this->env, gettext("Sign in"), "html", null, true);
        echo "</a>
                <a class=\"bb-button bb-button-red\" href=\"";
        // line 65
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("login", ["register" => 1]);
        echo "\">";
        echo twig_escape_filter($this->env, gettext("Register"), "html", null, true);
        echo "</a>
            </div>
            ";
    }

    // line 76
    public function block_content_before($context, array $blocks = [])
    {
        $macros = $this->macros;
    }

    // line 80
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
    }

    // line 81
    public function block_content_after($context, array $blocks = [])
    {
        $macros = $this->macros;
    }

    // line 85
    public function block_sidebar($context, array $blocks = [])
    {
        $macros = $this->macros;
    }

    // line 131
    public function block_sidebar2($context, array $blocks = [])
    {
        $macros = $this->macros;
    }

    // line 224
    public function block_js($context, array $blocks = [])
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
        return array (  642 => 224,  636 => 131,  630 => 85,  624 => 81,  618 => 80,  612 => 76,  603 => 65,  597 => 64,  594 => 63,  590 => 62,  584 => 25,  578 => 24,  571 => 11,  564 => 10,  558 => 5,  552 => 227,  542 => 226,  539 => 225,  537 => 224,  533 => 223,  520 => 215,  506 => 203,  500 => 200,  497 => 199,  495 => 198,  487 => 192,  481 => 188,  479 => 187,  472 => 185,  464 => 182,  456 => 179,  449 => 175,  444 => 172,  437 => 167,  424 => 164,  421 => 163,  416 => 162,  414 => 161,  408 => 158,  404 => 156,  402 => 155,  399 => 154,  392 => 149,  377 => 147,  373 => 146,  367 => 143,  363 => 141,  361 => 140,  351 => 132,  349 => 131,  346 => 130,  340 => 126,  327 => 124,  323 => 123,  316 => 119,  312 => 117,  309 => 116,  307 => 115,  301 => 112,  297 => 110,  288 => 104,  281 => 102,  276 => 100,  268 => 97,  263 => 95,  256 => 93,  250 => 90,  246 => 88,  244 => 87,  241 => 86,  239 => 85,  234 => 82,  231 => 81,  228 => 80,  226 => 79,  222 => 77,  220 => 76,  211 => 69,  208 => 68,  205 => 62,  197 => 57,  189 => 54,  181 => 51,  173 => 48,  169 => 46,  167 => 45,  161 => 41,  153 => 39,  145 => 37,  143 => 36,  139 => 35,  134 => 32,  122 => 30,  120 => 29,  115 => 26,  112 => 25,  110 => 24,  105 => 22,  101 => 21,  97 => 20,  92 => 18,  88 => 17,  83 => 15,  76 => 11,  72 => 10,  67 => 8,  63 => 7,  56 => 5,  51 => 2,  49 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% set company = guest.system_company %}
<!DOCTYPE html>
<html>
<head>
    <title>{% block meta_title %}{% endblock %} {{ settings.meta_title }}</title>

    <meta property=\"bb:url\" content=\"{{ constant('BB_URL') }}\"/>
    <meta property=\"bb:client_area\" content=\"{{ '/'|link }}\"/>
    
    <meta name=\"description\" content=\"{% block meta_description %}{{ settings.meta_description }}{% endblock %}\" />
    <meta name=\"keywords\" content=\"{% block meta_keywords %}{{ settings.meta_keywords }}{% endblock %}\" />
    <meta name=\"robots\" content=\"index, follow\" />
    <meta http-equiv=\"content-type\" content=\"text/html; charset=utf-8\" />

    <link rel=\"shortcut icon\" href=\"{{ 'favicon.ico' | asset_url }}\" />
    <link href=\"https://fonts.googleapis.com/css?family=Cuprum\" rel=\"stylesheet\" type=\"text/css\" />
    {{ 'css/print.css' | asset_url | stylesheet_tag('print') }}
    {{ 'css/style.css' | asset_url | stylesheet_tag }}

    {{ 'js/jquery.min.js' | asset_url | script_tag }}
    {{ 'js/jquery.tipsy.js' | asset_url | script_tag }}
    {{ 'js/bb-jquery.js' | asset_url | script_tag }}

    {% block opengraph %}{% endblock %}
    {% block head %}{% endblock %}
</head>

<body>
{% if guest.extension_is_on({ \"mod\": \"cookieconsent\" }) %}
    {% include 'mod_cookieconsent_index.phtml' ignore missing%}
{% endif %}
<div class=\"header\">
    <div class=\"container_16\">
        <div class=\"grid_4\" >
            <a href=\"{{''|link }}\">
                {% if company.logo_url %}
                    <img src=\"{{company.logo_url}}\" alt=\"{{company.name}}\" style=\"max-height: 60px\"/>
                {% else %}
                    <img src=\" {{ 'images/logo.png' | asset_url }}\" alt=\"{{company.name}}\" style=\"max-height: 60px\"/>
                {% endif %}
            </a>
        </div>

        <div class=\"grid_12\">
            {% if client %}
            <ul class=\"middleNav\">
                <li>
                    <a href=\"{{ 'dashboard'|link }}\" class=\"show-tip\" title=\"{{ 'Dashboard'|trans }}\"><span class=\"big-dark-icon i-home\"></span></a>
                </li>
                <li>
                    <a href=\"{{ 'cart'|link }}\" class=\"show-tip\" title=\"{{ 'Shopping cart'|trans }}\"><span class=\"big-dark-icon i-cart\"></span></a>
                </li>
                <li>
                    <a href=\"{{ 'client/me'|link }}\" class=\"show-tip\" title=\"{{ 'Profile'|trans }}\"><span class=\"big-dark-icon i-profile\"></span></a>
                </li>
                <li>
                    <a href=\"client/client/logout\" class=\"show-tip api\" title=\"{{ 'Sign out'|trans }}\"><span class=\"big-dark-icon i-logout\"></span></a>
                </li>
            </ul>

            {% else %}
            {% block header_buttons %}
            <div class=\"top-buttons\">
                <a id=\"login-form-link\" class=\"bb-button bb-button-submit\" href=\"{{ 'login'|link }}\">{{ 'Sign in'|trans }}</a>
                <a class=\"bb-button bb-button-red\" href=\"{{ 'login'|link({'register' : 1}) }}\">{{ 'Register'|trans }}</a>
            </div>
            {% endblock %}
            {% endif %}
        </div>
        <div class=\"clear\"></div>
    </div>
</div>

<div id=\"main\">
    <div class=\"container_16\">
        {% block content_before %}{% endblock %}

        <div class=\"grid_12\">
            {% include \"partial_message.phtml\" %}
            {% block content %}{% endblock %}
            {% block content_after %}{% endblock %}
        </div>

        <div class=\"grid_4\">
            {% block sidebar %}{% endblock %}

            {% if not client %}
            <div class=\"widget\" id=\"login-form\" style=\"display: none;\" >
                <div class=\"head\">
                    <h2 class=\"dark-icon i-profile\">{{ 'Login to client area'|trans }}</h2>
                </div>
                <div class=\"block\">
                <form action=\"\" method=\"post\" class=\"api_form\" data-api-url=\"{{ 'api/guest/client/login'|link }}\" data-api-redirect=\"{{ ''|link }}\">
                    <fieldset>
                        <legend>{{ 'Login to client area'|trans }}</legend>
                        <p>
                            <input type=\"email\" name=\"email\" value=\"{{ request.email }}\" required=\"required\" placeholder=\"{{ 'Email address'|trans }}\">
                        </p>
                        <p>
                            <input type=\"password\" name=\"password\" value=\"\" required=\"required\" placeholder=\"{{ 'Password'|trans }}\">
                        </p>
                        <a class=\"bb-button\" href=\"{{ 'reset-password'|link }}\">{{ 'Reset password'|trans }}</a>
                        <input type=\"hidden\" name=\"remember\" value=\"1\" />
                        <input class=\"bb-button bb-button-submit\" type=\"submit\" value=\"{{ 'Sign in'|trans }}\" style=\"float: right\">
                    </fieldset>
                </form>
                </div>
            </div>
            {% endif %}
            
            <div class=\"gradient\" style=\"margin-bottom: 10px;\">
                <nav>{% include \"partial_menu.phtml\" %}</nav>
            </div>

            {% set languages = guest.extension_languages %}
            {% if languages|length > 1 %}
            <div class=\"widget\">
                <div class=\"head\">
                    <h2 class=\"dark-icon i-drag\">{{ 'Select language'|trans }}</h2>
                </div>
                <div class=\"block\">
                    <select name=\"lang\" class=\"language_selector\">
                    {% for lang in languages %}
                        <option value=\"{{ lang }}\" class=\"lang_{{ lang }}\">{{ lang|trans }}</option>
                    {% endfor %}
                    </select>
                </div>
            </div>
            {% endif %}
            
            {% block sidebar2 %}{% endblock %}

        </div>
        <div class=\"clear\"></div>
    </div>
</div>

<div class=\"footer\">
    <div class=\"container_16\">
        {% if guest.extension_is_on({\"mod\":'kb'}) %}
        <div class=\"grid_4\">
            <div class=\"box\">
                <h2>{{ 'Popular articles'|trans }}</h2>
                <div class=\"block\">
                    <ul>
                        {% for i, article in guest.kb_article_get_list({\"per_page\":4, \"page\" : 1}).list %}
                            <li><a href=\"{{ '/kb'|link }}/{{article.category.slug}}/{{article.slug}}\">{{article.title|truncate(30)}}</a></li>
                        {% endfor %}
                    </ul>
                </div>
            </div>
        </div>
        {% endif %}

        {% if guest.extension_is_on({\"mod\":'news'}) %}
        <div class=\"grid_4\">
            <div class=\"box\">
                <h2>{{ 'Recent posts'|trans }}</h2>
                <div class=\"block\">
                    <ul>
                        {% set posts = guest.news_get_list({\"per_page\": 4, \"page\" : 1}) %}
                        {% for i, post in posts.list %}
                        <li>
                            <a href=\"{{ '/news'|link }}/{{post.slug}}\">{{post.title|truncate(30)}}</a>
                        </li>
                        {% endfor %}
                    </ul>
                </div>
            </div>
        </div>
        {% endif %}
        
        <div class=\"grid_4\">
            <div class=\"box\">
                <h2>{{ company.name }}</h2>
                <div class=\"block\">
                    <ul>
                        <li>
                            <a href=\"{{ '/about-us'|link }}\">{{ 'About us'|trans }}</a>
                        </li>
                        <li>
                            <a href=\"{{ '/tos'|link }}\">{{ 'Terms and Conditions'|trans }}</a>
                        </li>
                        <li>
                            <a href=\"{{ '/privacy-policy'|link }}\">{{ 'Privacy Policy'|trans }}</a>
                        </li>
                        {% if guest.extension_is_on({\"mod\":'branding'}) %}
                        <li>
                            <a href=\"http://www.boxbilling.org\" title=\"Billing Software\" target=\"_blank\">Billing software</a>
                        </li>
                        {% endif %}
                    </ul>
                </div>
            </div>
        </div>

        <div class=\"grid_4\">
            {% if guest.extension_is_on({\"mod\":'branding'}) %}
            <p class=\"logo\">
                <a class=\"boxbilling\" href=\"http://www.boxbilling.org\" title=\"Invoicing software\" target=\"_blank\">{{ 'images/boxbilling-logo.png' | asset_url | img_tag('Business software') }}</a>
            </p>
            {% endif %}
        </div>

        <div class=\"clear\"></div>
    </div>
</div>

<div class=\"footer lower\">
    <div class=\"container_16\">
        <div class=\"grid_16\">
            <div class=\"box\">
                <div class=\"block\">
                    <p>
                    © {{ now|date('Y') }} {{ company.signature }}
                    </p>
                </div>
            </div>
        </div>
        <div class=\"clear\"></div>
    </div>
</div>
<div class=\"loading dim\" style=\"display:none\"><div class=\"popup_block\"><h3>{{ 'Loading ..'|trans }}</h3></div></div>
    {% block js %}{% endblock %}
    <noscript>NOTE: Many features on BoxBilling require Javascript and cookies. You can enable both via your browser's preference settings.</noscript>
{% include 'partial_pending_messages.phtml' ignore missing %}
</body>
</html>
", "layout_default.phtml", "/shared/httpd/up-boxbilling/FOSSBilling/src/bb-themes/boxbilling/html/layout_default.phtml");
    }
}
