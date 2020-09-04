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

/* mod_product_manage.phtml */
class __TwigTemplate_0e608849e22a2e5dffa9377d0fd1ef5a3ce7742d0566064045bbb74cd2899c2a extends \Twig\Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->blocks = [
            'meta_title' => [$this, 'block_meta_title'],
            'breadcrumbs' => [$this, 'block_breadcrumbs'],
            'content' => [$this, 'block_content'],
            'head' => [$this, 'block_head'],
            'js' => [$this, 'block_js'],
        ];
    }

    protected function doGetParent(array $context)
    {
        // line 1
        return "layout_default.phtml";
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 2
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_product_manage.phtml", 2)->unwrap();
        // line 3
        $context["active_menu"] = "products";
        // line 1
        $this->parent = $this->loadTemplate("layout_default.phtml", "mod_product_manage.phtml", 1);
        $this->parent->display($context, array_merge($this->blocks, $blocks));
    }

    // line 4
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo gettext("Product configuration");
    }

    // line 7
    public function block_breadcrumbs($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 8
        echo "<ul>
    <li class=\"firstB\"><a href=\"";
        // line 9
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/");
        echo "\">";
        echo gettext("Home");
        echo "</a></li>
    <li><a href=\"";
        // line 10
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("product");
        echo "\">";
        echo gettext("Products");
        echo "</a></li>
    <li class=\"lastB\">";
        // line 11
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "title", [], "any", false, false, false, 11), "html", null, true);
        echo "</li>
</ul>
";
    }

    // line 15
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 16
        echo "
<div class=\"widget simpleTabs\">

    <ul class=\"tabs\">
        <li><a href=\"#tab-settings\">";
        // line 20
        echo gettext("General settings");
        echo "</a></li>
        <li><a href=\"#tab-config\">";
        // line 21
        echo gettext("Configuration");
        echo "</a></li>
        <li><a href=\"#tab-addons\">";
        // line 22
        echo gettext("Addons");
        echo "</a></li>
        <li><a href=\"#tab-upgrades\">";
        // line 23
        echo gettext("Upgrades");
        echo "</a></li>
        <li><a href=\"#tab-links\">";
        // line 24
        echo gettext("Links");
        echo "</a></li>
    </ul>

    <div class=\"tabs_container\">
        <form method=\"post\" action=\"admin/product/update\" class=\"mainForm api-form save\" data-api-msg=\"Product configuration updated\" name=\"form\">
        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-settings\">

            <div class=\"help\">
                <h5>";
        // line 33
        echo twig_escape_filter($this->env, twig_title_string_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "type", [], "any", false, false, false, 33)), "html", null, true);
        echo " ";
        echo gettext("General settings");
        echo "</h5>
            </div>

            <fieldset>
                <div class=\"rowElem noborder\">
                    <label>";
        // line 38
        echo gettext("Category");
        echo ":</label>
                    <div class=\"formRight \">
                        ";
        // line 40
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["product_category_id", twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "product_category_get_pairs", [], "any", false, false, false, 40), twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "product_category_id", [], "any", false, false, false, 40), 0, "None"], 40, $context, $this->getSourceContext());
        echo "
                    </div>
                    <div class=\"fix\"></div>
                </div>

                ";
        // line 45
        if (twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "extension_is_on", [0 => ["mod" => "formbuilder"]], "method", false, false, false, 45)) {
            // line 46
            echo "                <div class=\"rowElem\">
                    <label>";
            // line 47
            echo gettext("Order Form");
            echo ":</label>
                    <div class=\"formRight\">
                        ";
            // line 49
            $context["tpl"] = (("mod_service" . twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "type", [], "any", false, false, false, 49)) . "_order_form.phtml");
            // line 50
            echo "                        ";
            if (twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_template_exists", [0 => ["file" => ($context["tpl"] ?? null)]], "method", false, false, false, 50)) {
                // line 51
                echo "                        <div class=\"nNote nInformation\"><p>Please edit <strong>";
                echo twig_escape_filter($this->env, ($context["tpl"] ?? null), "html", null, true);
                echo "</strong> file in order to change order form for <strong>";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "type", [], "any", false, false, false, 51), "html", null, true);
                echo "</strong> category products.</p></div>
                       <a href=\"";
                // line 52
                echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("filemanager/ide");
                echo "\" class=\"button blueBtn\">";
                echo gettext("Open Editor");
                echo "</a>
                        ";
            } else {
                // line 54
                echo "                       ";
                echo twig_call_macro($macros["mf"], "macro_selectbox", ["form_id", twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "formbuilder_get_pairs", [], "any", false, false, false, 54), twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "form_id", [], "any", false, false, false, 54), 0, "None"], 54, $context, $this->getSourceContext());
                echo "
                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        <a href=\"";
                // line 56
                echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("extension/settings/formbuilder");
                if (twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "form_id", [], "any", false, false, false, 56)) {
                    echo "?id=";
                    echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "form_id", [], "any", false, false, false, 56), "html", null, true);
                }
                echo "\" class=\"button blueBtn\" id=\"formbuilder_btn\">";
                echo ((twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "form_id", [], "any", false, false, false, 56)) ? ("Edit Form") : ("Add new form"));
                echo "</a>
                        ";
            }
            // line 58
            echo "                    </div>
                    <div class=\"fix\"></div>
                </div>
                ";
        }
        // line 62
        echo "                <div class=\"rowElem\">
                    <label>";
        // line 63
        echo gettext("Status");
        echo ":</label>
                    <div class=\"formRight\">
                        <input type=\"radio\" name=\"status\" value=\"enabled\"";
        // line 65
        if ((twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "status", [], "any", false, false, false, 65) == "enabled")) {
            echo " checked=\"checked\"";
        }
        echo " id=\"status-enabled\"/><label for=\"status-enabled\">";
        echo gettext("Enabled");
        echo "</label>
                        <input type=\"radio\" name=\"status\" value=\"disabled\"";
        // line 66
        if ((twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "status", [], "any", false, false, false, 66) == "disabled")) {
            echo " checked=\"checked\"";
        }
        echo " id=\"status-disabled\"/><label for=\"status-disabled\">";
        echo gettext("Disabled");
        echo "</label>
                    </div>
                    <div class=\"fix\"></div>
                </div>
                <div class=\"rowElem\">
                    <label>";
        // line 71
        echo gettext("Hidden");
        echo ":</label>
                    <div class=\"formRight\">
                        <input type=\"radio\" name=\"hidden\" value=\"1\"";
        // line 73
        if (twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "hidden", [], "any", false, false, false, 73)) {
            echo " checked=\"checked\"";
        }
        echo " id=\"hidden-yes\"/><label for=\"hidden-yes\">";
        echo gettext("Yes");
        echo "</label>
                        <input type=\"radio\" name=\"hidden\" value=\"0\"";
        // line 74
        if ( !twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "hidden", [], "any", false, false, false, 74)) {
            echo " checked=\"checked\"";
        }
        echo " id=\"hidden-no\"/><label for=\"hidden-no\">";
        echo gettext("No");
        echo "</label>
                    </div>
                    <div class=\"fix\"></div>
                </div>
                <div class=\"rowElem\">
                    <label>";
        // line 79
        echo gettext("Activation");
        echo ":</label>
                    <div class=\"formRight\">
                        <input type=\"radio\" name=\"setup\" value=\"after_order\"";
        // line 81
        if ((twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "setup", [], "any", false, false, false, 81) == "after_order")) {
            echo " checked=\"checked\"";
        }
        echo " id=\"activation-after-order\"/><label for=\"activation-after-order\">";
        echo gettext("After order is placed");
        echo "</label>
                        <input type=\"radio\" name=\"setup\" value=\"after_payment\"";
        // line 82
        if ((twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "setup", [], "any", false, false, false, 82) == "after_payment")) {
            echo " checked=\"checked\"";
        }
        echo " id=\"activation-after-payment\"/><label for=\"activation-after-payment\">";
        echo gettext("After payment is received");
        echo "</label>
                        <input type=\"radio\" name=\"setup\" value=\"manual\"";
        // line 83
        if ((twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "setup", [], "any", false, false, false, 83) == "manual")) {
            echo " checked=\"checked\"";
        }
        echo " id=\"activation-manual\"/><label for=\"activation-manual\">";
        echo gettext("Manual activation");
        echo "</label>
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>";
        // line 89
        echo gettext("Icon/Image URL");
        echo ":</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"icon_url\" id=\"bb-icon\" value=\"";
        // line 91
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "icon_url", [], "any", false, false, false, 91), "html", null, true);
        echo "\" placeholder=\"\" style=\"width: 80%\"/>
                        <input type=\"button\" value=\"";
        // line 92
        echo gettext("Browse");
        echo "\" class=\"bHtml blueBtn button\" />
                    </div>
                    <div class=\"fix\"></div>
                </div>
                <div class=\"rowElem\">
                    <label>";
        // line 97
        echo gettext("Title");
        echo ":</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"title\" value=\"";
        // line 99
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "title", [], "any", false, false, false, 99), "html", null, true);
        echo "\" required=\"required\" placeholder=\"\"/>
                    </div>
                    <div class=\"fix\"></div>
                </div>
                <div class=\"rowElem\">
                    <label>";
        // line 104
        echo gettext("Slug");
        echo ":</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"slug\" value=\"";
        // line 106
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "slug", [], "any", false, false, false, 106), "html", null, true);
        echo "\" required=\"required\" placeholder=\"\"/>
                    </div>
                    <div class=\"fix\"></div>
                </div>
                
                ";
        // line 111
        $this->loadTemplate("partial_pricing.phtml", "mod_product_manage.phtml", 111)->display(twig_array_merge($context, ["product" => ($context["product"] ?? null)]));
        // line 112
        echo "
            </fieldset>

           <fieldset>
               <legend>";
        // line 116
        echo gettext("Product / service description");
        echo "</legend>
                <textarea name=\"description\" cols=\"5\" rows=\"5\" class=\"bb-textarea\">";
        // line 117
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "description", [], "any", false, false, false, 117), "html", null, true);
        echo "</textarea>
                
                <input type=\"submit\" value=\"";
        // line 119
        echo gettext("Update");
        echo "\" class=\"greyishBtn submitForm\" />
           </fieldset>
        <div class=\"fix\"></div>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-addons\">
            <div class=\"help\">
                <h5>";
        // line 126
        echo gettext("Choose which addons you would like to offer with");
        echo " ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "title", [], "any", false, false, false, 126), "html", null, true);
        echo "</h5>
            </div>

            <table class=\"tableStatic wide\">
                <tbody>
                    ";
        // line 131
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "product_addon_get_pairs", [], "any", false, false, false, 131));
        $context['_iterated'] = false;
        $context['loop'] = [
          'parent' => $context['_parent'],
          'index0' => 0,
          'index'  => 1,
          'first'  => true,
        ];
        if (is_array($context['_seq']) || (is_object($context['_seq']) && $context['_seq'] instanceof \Countable)) {
            $length = count($context['_seq']);
            $context['loop']['revindex0'] = $length - 1;
            $context['loop']['revindex'] = $length;
            $context['loop']['length'] = $length;
            $context['loop']['last'] = 1 === $length;
        }
        foreach ($context['_seq'] as $context["addon_id"] => $context["addon_title"]) {
            // line 132
            echo "                    <tr ";
            if (twig_get_attribute($this->env, $this->source, $context["loop"], "first", [], "any", false, false, false, 132)) {
                echo "class=\"noborder\"";
            }
            echo ">
                        <td style=\"width:5%\">
                            <input type=\"hidden\" name=\"addons[";
            // line 134
            echo twig_escape_filter($this->env, $context["addon_id"], "html", null, true);
            echo "]\" value=\"0\">
                            <input type=\"checkbox\" name=\"addons[";
            // line 135
            echo twig_escape_filter($this->env, $context["addon_id"], "html", null, true);
            echo "]\" value=\"1\" id=\"addon_";
            echo twig_escape_filter($this->env, $context["addon_id"], "html", null, true);
            echo "\" ";
            if (twig_in_filter($context["addon_id"], ($context["assigned_addons"] ?? null))) {
                echo "checked=\"checked\"";
            }
            echo "/>
                        </td>
                        <td><label for=\"addon_";
            // line 137
            echo twig_escape_filter($this->env, $context["addon_id"], "html", null, true);
            echo "\">";
            echo twig_escape_filter($this->env, $context["addon_title"], "html", null, true);
            echo "</label></td>
                        <td style=\"width:5%\"><a class=\"bb-button btn14\" href=\"";
            // line 138
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/product/addon");
            echo "/";
            echo twig_escape_filter($this->env, $context["addon_id"], "html", null, true);
            echo "\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a></td>
                    </tr>
                    ";
            $context['_iterated'] = true;
            ++$context['loop']['index0'];
            ++$context['loop']['index'];
            $context['loop']['first'] = false;
            if (isset($context['loop']['length'])) {
                --$context['loop']['revindex0'];
                --$context['loop']['revindex'];
                $context['loop']['last'] = 0 === $context['loop']['revindex0'];
            }
        }
        if (!$context['_iterated']) {
            // line 141
            echo "                    <tr>
                        <td colspan=\"3\">";
            // line 142
            echo gettext("The list is empty");
            echo "</td>
                    </tr>
                    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['addon_id'], $context['addon_title'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 145
        echo "                </tbody>
                <tfoot>
                    <tr>
                        <td colspan=\"3\">
                            <a href=\"";
        // line 149
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("product/addons");
        echo "#tab-new\" title=\"\" class=\"btnIconLeft mr10 mt5\"><img src=\"images/icons/dark/settings2.png\" alt=\"\" class=\"icon\"><span>";
        echo gettext("Create new addon");
        echo "</span></a>
                        </td>
                    </tr>
                </tfoot>
            </table>
            <input type=\"submit\" value=\"";
        // line 154
        echo gettext("Update");
        echo "\" class=\"greyishBtn submitForm\" />


            <div class=\"fix\"></div>
        </div>

        <div class=\"tab_content nopadding mainForm\" id=\"tab-upgrades\">
            <div class=\"help\">
                <h5>";
        // line 162
        echo gettext("Choose which products can client upgrade to");
        echo "</h5>
            </div>
            <fieldset>
                <div class=\"rowElem noborder\">
                    <label>";
        // line 166
        echo gettext("Product Upgrades");
        echo "</label>
                    <div class=\"formRight\">
                        ";
        // line 168
        $context["products"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "product_get_pairs", [], "any", false, false, false, 168);
        // line 169
        echo "                        <input type=\"hidden\" name=\"upgrades\" value=\"\">
                        <select name=\"upgrades[]\" multiple=\"multiple\" class=\"multiple\" size=\"";
        // line 170
        echo twig_escape_filter($this->env, twig_length_filter($this->env, ($context["products"] ?? null)), "html", null, true);
        echo "\">
                            ";
        // line 171
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(($context["products"] ?? null));
        foreach ($context['_seq'] as $context["id"] => $context["ptitle"]) {
            // line 172
            echo "                            <option value=\"";
            echo twig_escape_filter($this->env, $context["id"], "html", null, true);
            echo "\" ";
            if ((($__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4 = twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "upgrades", [], "any", false, false, false, 172)) && is_array($__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4) || $__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4 instanceof ArrayAccess ? ($__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4[$context["id"]] ?? null) : null)) {
                echo "selected=\"selected\"";
            }
            echo ">";
            echo twig_escape_filter($this->env, $context["ptitle"], "html", null, true);
            echo "</option>
                            ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['id'], $context['ptitle'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 174
        echo "                        </select>
                    </div>
                    <div class=\"fix\"></div>
                </div>
                <input type=\"submit\" value=\"";
        // line 178
        echo gettext("Update");
        echo "\" class=\"greyishBtn submitForm\" />
                <input type=\"hidden\" name=\"id\" value=\"";
        // line 179
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "id", [], "any", false, false, false, 179), "html", null, true);
        echo "\" />
            </fieldset>
        </div>

        <input type=\"hidden\" name=\"id\" value=\"";
        // line 183
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "id", [], "any", false, false, false, 183), "html", null, true);
        echo "\" />
        </form>

        <div class=\"tab_content nopadding\" id=\"tab-config\">
            ";
        // line 187
        $context["service_partial"] = (("mod_service" . twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "type", [], "any", false, false, false, 187)) . "_config.phtml");
        // line 188
        echo "            ";
        if (twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "system_template_exists", [0 => ["file" => ($context["service_partial"] ?? null)]], "method", false, false, false, 188)) {
            // line 189
            echo "                ";
            $this->loadTemplate(($context["service_partial"] ?? null), "mod_product_manage.phtml", 189)->display(twig_array_merge($context, ["product" => ($context["product"] ?? null)]));
            // line 190
            echo "            ";
        } else {
            // line 191
            echo "                <div class=\"help\">
                    <h5>";
            // line 192
            echo gettext("No additional configuration for this product is required");
            echo "</h5>
                </div>
            ";
        }
        // line 195
        echo "            <div class=\"fix\"></div>
        </div>
        
        <div class=\"tab_content nopadding mainForm\" id=\"tab-links\">
            <fieldset>
            <div class=\"help\">
                <h5>";
        // line 201
        echo gettext("Product links");
        echo "</h5>
            </div>

            <div class=\"rowElem noborder\">
                <label>";
        // line 205
        echo gettext("Product ID");
        echo "</label>
                <div class=\"formRight\">
                    <input type=\"text\" value=\"";
        // line 207
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "id", [], "any", false, false, false, 207), "html", null, true);
        echo "\"/>
                    <div class=\"fix\"></div>
                </div>
            </div>

            <div class=\"rowElem\">
                <label>";
        // line 213
        echo gettext("Order page with ID");
        echo "</label>
                <div class=\"formRight\">
                    <input type=\"text\" value=\"";
        // line 215
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("order");
        echo "/";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "id", [], "any", false, false, false, 215), "html", null, true);
        echo "\"/>
                    <div class=\"fix\"></div>
                </div>
            </div>

            <div class=\"rowElem\">
                <label>";
        // line 221
        echo gettext("Order page with slug");
        echo "</label>
                <div class=\"formRight\">
                    <input type=\"text\" value=\"";
        // line 223
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("order");
        echo "/";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "slug", [], "any", false, false, false, 223), "html", null, true);
        echo "\"/>
                    <div class=\"fix\"></div>
                </div>
            </div>

            </fieldset>
            
            <div class=\"body aligncenter\">
                <a href=\"";
        // line 231
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("order");
        echo "/";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["product"] ?? null), "slug", [], "any", false, false, false, 231), "html", null, true);
        echo "\" title=\"\" class=\"btn55 mr10\" target=\"_blank\"><img src=\"images/icons/middlenav/preview.png\" alt=\"\"><span>";
        echo gettext("View as client<");
        echo "/span></a>
            </div>

            <div class=\"fix\"></div>
        </div>

    </div>

</div>
";
    }

    // line 243
    public function block_head($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo twig_call_macro($macros["mf"], "macro_bb_editor", [".bb-textarea"], 243, $context, $this->getSourceContext());
    }

    // line 245
    public function block_js($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 246
        echo "<script type=\"text/javascript\">
\$(function() {
\t\$(\".bHtml\").click( function() {
\t\tjAlert(bb.load('";
        // line 249
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("filemanager/icons");
        echo "', {rel:\"bb-icon\"}), '";
        echo gettext("Select icon and click OK");
        echo "');
\t});
    \$(\"[name='form_id']\").change( function(){
        var form_id = \$(this).val();
        var btn = \$(\"#formbuilder_btn\");
            if (form_id !=\"\"){
            btn.html(\"Edit form\");
                var href = \"";
        // line 256
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("extension/settings/formbuilder", ["id" => ""]);
        echo "\" + form_id;
                btn.attr('href', href);
        }
        else{
                btn.html(\"Add new form\");
                btn.attr(\"href\", \"";
        // line 261
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("extension/settings/formbuilder");
        echo "\");
            }

    });

});
</script>
";
    }

    public function getTemplateName()
    {
        return "mod_product_manage.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  673 => 261,  665 => 256,  653 => 249,  648 => 246,  644 => 245,  637 => 243,  619 => 231,  606 => 223,  601 => 221,  590 => 215,  585 => 213,  576 => 207,  571 => 205,  564 => 201,  556 => 195,  550 => 192,  547 => 191,  544 => 190,  541 => 189,  538 => 188,  536 => 187,  529 => 183,  522 => 179,  518 => 178,  512 => 174,  497 => 172,  493 => 171,  489 => 170,  486 => 169,  484 => 168,  479 => 166,  472 => 162,  461 => 154,  451 => 149,  445 => 145,  436 => 142,  433 => 141,  415 => 138,  409 => 137,  398 => 135,  394 => 134,  386 => 132,  368 => 131,  358 => 126,  348 => 119,  343 => 117,  339 => 116,  333 => 112,  331 => 111,  323 => 106,  318 => 104,  310 => 99,  305 => 97,  297 => 92,  293 => 91,  288 => 89,  275 => 83,  267 => 82,  259 => 81,  254 => 79,  242 => 74,  234 => 73,  229 => 71,  217 => 66,  209 => 65,  204 => 63,  201 => 62,  195 => 58,  184 => 56,  178 => 54,  171 => 52,  164 => 51,  161 => 50,  159 => 49,  154 => 47,  151 => 46,  149 => 45,  141 => 40,  136 => 38,  126 => 33,  114 => 24,  110 => 23,  106 => 22,  102 => 21,  98 => 20,  92 => 16,  88 => 15,  81 => 11,  75 => 10,  69 => 9,  66 => 8,  62 => 7,  55 => 4,  50 => 1,  48 => 3,  46 => 2,  39 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends \"layout_default.phtml\" %}
{% import \"macro_functions.phtml\" as mf %}
{% set active_menu = 'products' %}
{% block meta_title %}{% trans 'Product configuration' %}{% endblock %}


{% block breadcrumbs %}
<ul>
    <li class=\"firstB\"><a href=\"{{ '/'|alink }}\">{% trans 'Home' %}</a></li>
    <li><a href=\"{{ 'product'|alink }}\">{% trans 'Products' %}</a></li>
    <li class=\"lastB\">{{product.title}}</li>
</ul>
{% endblock %}

{% block content %}

<div class=\"widget simpleTabs\">

    <ul class=\"tabs\">
        <li><a href=\"#tab-settings\">{% trans 'General settings' %}</a></li>
        <li><a href=\"#tab-config\">{% trans 'Configuration' %}</a></li>
        <li><a href=\"#tab-addons\">{% trans 'Addons' %}</a></li>
        <li><a href=\"#tab-upgrades\">{% trans 'Upgrades' %}</a></li>
        <li><a href=\"#tab-links\">{% trans 'Links' %}</a></li>
    </ul>

    <div class=\"tabs_container\">
        <form method=\"post\" action=\"admin/product/update\" class=\"mainForm api-form save\" data-api-msg=\"Product configuration updated\" name=\"form\">
        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-settings\">

            <div class=\"help\">
                <h5>{{ product.type|title }} {% trans 'General settings' %}</h5>
            </div>

            <fieldset>
                <div class=\"rowElem noborder\">
                    <label>{% trans 'Category' %}:</label>
                    <div class=\"formRight \">
                        {{ mf.selectbox('product_category_id', guest.product_category_get_pairs, product.product_category_id, 0, 'None') }}
                    </div>
                    <div class=\"fix\"></div>
                </div>

                {% if guest.extension_is_on({\"mod\":\"formbuilder\"}) %}
                <div class=\"rowElem\">
                    <label>{% trans 'Order Form' %}:</label>
                    <div class=\"formRight\">
                        {% set tpl = \"mod_service\"~product.type~\"_order_form.phtml\" %}
                        {% if guest.system_template_exists({\"file\":tpl}) %}
                        <div class=\"nNote nInformation\"><p>Please edit <strong>{{tpl}}</strong> file in order to change order form for <strong>{{product.type}}</strong> category products.</p></div>
                       <a href=\"{{ 'filemanager/ide' | alink }}\" class=\"button blueBtn\">{% trans 'Open Editor' %}</a>
                        {% else %}
                       {{ mf.selectbox('form_id', admin.formbuilder_get_pairs, product.form_id, 0, 'None') }}
                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        <a href=\"{{'extension/settings/formbuilder' | alink}}{% if product.form_id %}?id={{product.form_id}}{% endif %}\" class=\"button blueBtn\" id=\"formbuilder_btn\">{{ (product.form_id) ? 'Edit Form' : 'Add new form'}}</a>
                        {% endif %}
                    </div>
                    <div class=\"fix\"></div>
                </div>
                {% endif %}
                <div class=\"rowElem\">
                    <label>{% trans 'Status' %}:</label>
                    <div class=\"formRight\">
                        <input type=\"radio\" name=\"status\" value=\"enabled\"{% if product.status == 'enabled' %} checked=\"checked\"{% endif %} id=\"status-enabled\"/><label for=\"status-enabled\">{% trans 'Enabled' %}</label>
                        <input type=\"radio\" name=\"status\" value=\"disabled\"{% if product.status == 'disabled' %} checked=\"checked\"{% endif %} id=\"status-disabled\"/><label for=\"status-disabled\">{% trans 'Disabled' %}</label>
                    </div>
                    <div class=\"fix\"></div>
                </div>
                <div class=\"rowElem\">
                    <label>{% trans 'Hidden' %}:</label>
                    <div class=\"formRight\">
                        <input type=\"radio\" name=\"hidden\" value=\"1\"{% if product.hidden %} checked=\"checked\"{% endif %} id=\"hidden-yes\"/><label for=\"hidden-yes\">{% trans 'Yes' %}</label>
                        <input type=\"radio\" name=\"hidden\" value=\"0\"{% if not product.hidden %} checked=\"checked\"{% endif %} id=\"hidden-no\"/><label for=\"hidden-no\">{% trans 'No' %}</label>
                    </div>
                    <div class=\"fix\"></div>
                </div>
                <div class=\"rowElem\">
                    <label>{% trans 'Activation' %}:</label>
                    <div class=\"formRight\">
                        <input type=\"radio\" name=\"setup\" value=\"after_order\"{% if product.setup == 'after_order' %} checked=\"checked\"{% endif %} id=\"activation-after-order\"/><label for=\"activation-after-order\">{% trans 'After order is placed' %}</label>
                        <input type=\"radio\" name=\"setup\" value=\"after_payment\"{% if product.setup == 'after_payment' %} checked=\"checked\"{% endif %} id=\"activation-after-payment\"/><label for=\"activation-after-payment\">{% trans 'After payment is received' %}</label>
                        <input type=\"radio\" name=\"setup\" value=\"manual\"{% if product.setup == 'manual' %} checked=\"checked\"{% endif %} id=\"activation-manual\"/><label for=\"activation-manual\">{% trans 'Manual activation' %}</label>
                    </div>
                    <div class=\"fix\"></div>
                </div>

                <div class=\"rowElem\">
                    <label>{% trans 'Icon/Image URL' %}:</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"icon_url\" id=\"bb-icon\" value=\"{{product.icon_url}}\" placeholder=\"\" style=\"width: 80%\"/>
                        <input type=\"button\" value=\"{% trans 'Browse' %}\" class=\"bHtml blueBtn button\" />
                    </div>
                    <div class=\"fix\"></div>
                </div>
                <div class=\"rowElem\">
                    <label>{% trans 'Title' %}:</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"title\" value=\"{{product.title}}\" required=\"required\" placeholder=\"\"/>
                    </div>
                    <div class=\"fix\"></div>
                </div>
                <div class=\"rowElem\">
                    <label>{% trans 'Slug' %}:</label>
                    <div class=\"formRight\">
                        <input type=\"text\" name=\"slug\" value=\"{{product.slug}}\" required=\"required\" placeholder=\"\"/>
                    </div>
                    <div class=\"fix\"></div>
                </div>
                
                {% include \"partial_pricing.phtml\" with {'product': product} %}

            </fieldset>

           <fieldset>
               <legend>{% trans 'Product / service description' %}</legend>
                <textarea name=\"description\" cols=\"5\" rows=\"5\" class=\"bb-textarea\">{{product.description}}</textarea>
                
                <input type=\"submit\" value=\"{% trans 'Update' %}\" class=\"greyishBtn submitForm\" />
           </fieldset>
        <div class=\"fix\"></div>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-addons\">
            <div class=\"help\">
                <h5>{% trans 'Choose which addons you would like to offer with' %} {{ product.title }}</h5>
            </div>

            <table class=\"tableStatic wide\">
                <tbody>
                    {% for addon_id, addon_title in admin.product_addon_get_pairs %}
                    <tr {% if loop.first %}class=\"noborder\"{% endif %}>
                        <td style=\"width:5%\">
                            <input type=\"hidden\" name=\"addons[{{addon_id}}]\" value=\"0\">
                            <input type=\"checkbox\" name=\"addons[{{addon_id}}]\" value=\"1\" id=\"addon_{{ addon_id }}\" {% if addon_id in assigned_addons %}checked=\"checked\"{% endif %}/>
                        </td>
                        <td><label for=\"addon_{{ addon_id }}\">{{addon_title}}</label></td>
                        <td style=\"width:5%\"><a class=\"bb-button btn14\" href=\"{{ '/product/addon'|alink }}/{{addon_id}}\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a></td>
                    </tr>
                    {% else %}
                    <tr>
                        <td colspan=\"3\">{% trans 'The list is empty' %}</td>
                    </tr>
                    {% endfor %}
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan=\"3\">
                            <a href=\"{{ 'product/addons'|alink }}#tab-new\" title=\"\" class=\"btnIconLeft mr10 mt5\"><img src=\"images/icons/dark/settings2.png\" alt=\"\" class=\"icon\"><span>{% trans 'Create new addon' %}</span></a>
                        </td>
                    </tr>
                </tfoot>
            </table>
            <input type=\"submit\" value=\"{% trans 'Update' %}\" class=\"greyishBtn submitForm\" />


            <div class=\"fix\"></div>
        </div>

        <div class=\"tab_content nopadding mainForm\" id=\"tab-upgrades\">
            <div class=\"help\">
                <h5>{% trans 'Choose which products can client upgrade to' %}</h5>
            </div>
            <fieldset>
                <div class=\"rowElem noborder\">
                    <label>{% trans 'Product Upgrades' %}</label>
                    <div class=\"formRight\">
                        {% set products = admin.product_get_pairs %}
                        <input type=\"hidden\" name=\"upgrades\" value=\"\">
                        <select name=\"upgrades[]\" multiple=\"multiple\" class=\"multiple\" size=\"{{products|length}}\">
                            {% for id,ptitle in products %}
                            <option value=\"{{id}}\" {% if product.upgrades[id] %}selected=\"selected\"{% endif %}>{{ptitle }}</option>
                            {% endfor %}
                        </select>
                    </div>
                    <div class=\"fix\"></div>
                </div>
                <input type=\"submit\" value=\"{% trans 'Update' %}\" class=\"greyishBtn submitForm\" />
                <input type=\"hidden\" name=\"id\" value=\"{{ product.id }}\" />
            </fieldset>
        </div>

        <input type=\"hidden\" name=\"id\" value=\"{{ product.id }}\" />
        </form>

        <div class=\"tab_content nopadding\" id=\"tab-config\">
            {% set service_partial = \"mod_service\" ~ product.type ~ \"_config.phtml\" %}
            {% if admin.system_template_exists({\"file\":service_partial}) %}
                {% include service_partial with {'product': product} %}
            {% else %}
                <div class=\"help\">
                    <h5>{% trans 'No additional configuration for this product is required' %}</h5>
                </div>
            {% endif %}
            <div class=\"fix\"></div>
        </div>
        
        <div class=\"tab_content nopadding mainForm\" id=\"tab-links\">
            <fieldset>
            <div class=\"help\">
                <h5>{% trans 'Product links' %}</h5>
            </div>

            <div class=\"rowElem noborder\">
                <label>{% trans 'Product ID' %}</label>
                <div class=\"formRight\">
                    <input type=\"text\" value=\"{{ product.id }}\"/>
                    <div class=\"fix\"></div>
                </div>
            </div>

            <div class=\"rowElem\">
                <label>{% trans 'Order page with ID' %}</label>
                <div class=\"formRight\">
                    <input type=\"text\" value=\"{{ 'order'|link }}/{{product.id}}\"/>
                    <div class=\"fix\"></div>
                </div>
            </div>

            <div class=\"rowElem\">
                <label>{% trans 'Order page with slug' %}</label>
                <div class=\"formRight\">
                    <input type=\"text\" value=\"{{ 'order'|link }}/{{product.slug}}\"/>
                    <div class=\"fix\"></div>
                </div>
            </div>

            </fieldset>
            
            <div class=\"body aligncenter\">
                <a href=\"{{ 'order'|link }}/{{product.slug}}\" title=\"\" class=\"btn55 mr10\" target=\"_blank\"><img src=\"images/icons/middlenav/preview.png\" alt=\"\"><span>{% trans 'View as client<' %}/span></a>
            </div>

            <div class=\"fix\"></div>
        </div>

    </div>

</div>
{% endblock %}


{% block head %}{{ mf.bb_editor('.bb-textarea') }}{% endblock %}

{% block js %}
<script type=\"text/javascript\">
\$(function() {
\t\$(\".bHtml\").click( function() {
\t\tjAlert(bb.load('{{\"filemanager/icons\"|alink }}', {rel:\"bb-icon\"}), '{% trans \"Select icon and click OK\" %}');
\t});
    \$(\"[name='form_id']\").change( function(){
        var form_id = \$(this).val();
        var btn = \$(\"#formbuilder_btn\");
            if (form_id !=\"\"){
            btn.html(\"Edit form\");
                var href = \"{{'extension/settings/formbuilder' | alink({'id' : ''})}}\" + form_id;
                btn.attr('href', href);
        }
        else{
                btn.html(\"Add new form\");
                btn.attr(\"href\", \"{{'extension/settings/formbuilder' | alink}}\");
            }

    });

});
</script>
{% endblock %}
", "mod_product_manage.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-themes/admin_default/html/mod_product_manage.phtml");
    }
}
