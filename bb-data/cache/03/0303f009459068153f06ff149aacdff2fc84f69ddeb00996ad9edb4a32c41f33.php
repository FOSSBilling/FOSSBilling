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

/* mod_servicedomain_index.phtml */
class __TwigTemplate_45785993b8ea9231da2eec4fbb1398d4096959c81036b8d1d86ddb17a1a6b809 extends \Twig\Template
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
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_servicedomain_index.phtml", 2)->unwrap();
        // line 4
        $context["active_menu"] = "system";
        // line 1
        $this->parent = $this->loadTemplate("layout_default.phtml", "mod_servicedomain_index.phtml", 1);
        $this->parent->display($context, array_merge($this->blocks, $blocks));
    }

    // line 3
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo "Domain management";
    }

    // line 6
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 7
        echo "
<div class=\"widget simpleTabs\">

    <ul class=\"tabs\">
        <li><a href=\"#tab-tlds\">";
        // line 11
        echo gettext("Top level domains");
        echo "</a></li>
        <li><a href=\"#tab-new-tld\">";
        // line 12
        echo gettext("New top level domain");
        echo "</a></li>
        <li><a href=\"#tab-registrars\">";
        // line 13
        echo gettext("Registrars");
        echo "</a></li>
        <li><a href=\"#tab-new-registrar\">";
        // line 14
        echo gettext("New domain registrar");
        echo "</a></li>
        <li><a href=\"#tab-nameservers\">";
        // line 15
        echo gettext("Nameservers");
        echo "</a></li>
    </ul>

    <div class=\"tabs_container\">
        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-tlds\">

<div class=\"help\">
    <h5>";
        // line 23
        echo gettext("Manage TLDs");
        echo "</h5>
    <p>";
        // line 24
        echo gettext("Setup domain pricing and allowed operations. Assign specific domain registrars for each Top Level Domain (TLD)");
        echo "</p>
</div>

<table class=\"tableStatic wide\">
    <thead>
        <tr class=\"noborder\">
            <td>";
        // line 30
        echo gettext("TLD");
        echo "</td>
            <td>";
        // line 31
        echo gettext("Registration");
        echo "</td>
            <td>";
        // line 32
        echo gettext("Renew");
        echo "</td>
            <td>";
        // line 33
        echo gettext("Transfer");
        echo " </td>
            <td>";
        // line 34
        echo gettext("Operations");
        echo "</td>
            <td>";
        // line 35
        echo gettext("Registrar");
        echo "</td>
            <td style=\"width:13%\">&nbsp;</td>
        </tr>
    </thead>

    <tbody>
        ";
        // line 41
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "servicedomain_tld_get_list", [0 => ["per_page" => 99]], "method", false, false, false, 41), "list", [], "any", false, false, false, 41));
        foreach ($context['_seq'] as $context["_key"] => $context["tld"]) {
            // line 42
            echo "        <tr>
            <td>";
            // line 43
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["tld"], "tld", [], "any", false, false, false, 43), "html", null, true);
            echo "</td>
            <td>";
            // line 44
            echo twig_call_macro($macros["mf"], "macro_currency_format", [twig_get_attribute($this->env, $this->source, $context["tld"], "price_registration", [], "any", false, false, false, 44)], 44, $context, $this->getSourceContext());
            echo "</td>
            <td>";
            // line 45
            echo twig_call_macro($macros["mf"], "macro_currency_format", [twig_get_attribute($this->env, $this->source, $context["tld"], "price_renew", [], "any", false, false, false, 45)], 45, $context, $this->getSourceContext());
            echo "</td>
            <td>";
            // line 46
            echo twig_call_macro($macros["mf"], "macro_currency_format", [twig_get_attribute($this->env, $this->source, $context["tld"], "price_transfer", [], "any", false, false, false, 46)], 46, $context, $this->getSourceContext());
            echo "</td>
            <td>
             ";
            // line 48
            echo gettext("Allow register:");
            echo " ";
            if (twig_get_attribute($this->env, $this->source, $context["tld"], "allow_register", [], "any", false, false, false, 48)) {
                echo gettext("Yes");
            } else {
                echo gettext("No");
            }
            echo "<br/>
             ";
            // line 49
            echo gettext("Allow transfer:");
            echo " ";
            if (twig_get_attribute($this->env, $this->source, $context["tld"], "allow_transfer", [], "any", false, false, false, 49)) {
                echo gettext("Yes");
            } else {
                echo gettext("No");
            }
            echo "<br/>
             ";
            // line 50
            echo gettext("Active:");
            echo " ";
            if (twig_get_attribute($this->env, $this->source, $context["tld"], "active", [], "any", false, false, false, 50)) {
                echo gettext("Yes");
            } else {
                echo gettext("No");
            }
            // line 51
            echo "            </td>
            <td><a class=\"\" href=\"";
            // line 52
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("servicedomain/registrar");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["tld"], "registrar", [], "any", false, false, false, 52), "id", [], "any", false, false, false, 52), "html", null, true);
            echo "\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["tld"], "registrar", [], "any", false, false, false, 52), "title", [], "any", false, false, false, 52), "html", null, true);
            echo "</a></td>
            <td class=\"actions\">
                <a class=\"btn14 mr5\" href=\"";
            // line 54
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("servicedomain/tld");
            echo "/";
            echo twig_escape_filter($this->env, twig_slice($this->env, twig_get_attribute($this->env, $this->source, $context["tld"], "tld", [], "any", false, false, false, 54), 1), "html", null, true);
            echo "\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                <a class=\"bb-button btn14 bb-rm-tr api-link\" data-api-confirm=\"Are you sure?\" data-api-reload=\"1\" href=\"";
            // line 55
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/servicedomain/tld_delete", ["tld" => twig_get_attribute($this->env, $this->source, $context["tld"], "tld", [], "any", false, false, false, 55)]);
            echo "\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
            </td>
        </tr>
        ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['tld'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 59
        echo "     </tbody>

</table>
</div>

        <div class=\"tab_content nopadding\" id=\"tab-new-tld\">

            <div class=\"help\">
                <h5>";
        // line 67
        echo gettext("Add new top level domain");
        echo "</h5>
                <p>";
        // line 68
        echo gettext("Setup new TLD prices and properties");
        echo "</p>
            </div>

            <form method=\"post\" action=\"";
        // line 71
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/servicedomain/tld_create");
        echo "\" class=\"mainForm save api-form\" data-api-reload=\"1\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>";
        // line 74
        echo gettext("Tld");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"tld\" value=\".\" required=\"required\" class=\"dirTop\" title=\"Must start with a dot\">
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 81
        echo gettext("Registrar");
        echo ":</label>
                        <div class=\"formRight\">
                            <select name=\"tld_registrar_id\" required=\"required\">
                                ";
        // line 84
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "servicedomain_registrar_get_pairs", [], "any", false, false, false, 84));
        foreach ($context['_seq'] as $context["id"] => $context["title"]) {
            // line 85
            echo "                                <option value=\"";
            echo twig_escape_filter($this->env, $context["id"], "html", null, true);
            echo "\">";
            echo twig_escape_filter($this->env, $context["title"], "html", null, true);
            echo "</option>
                                ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['id'], $context['title'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 87
        echo "                            </select>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 93
        echo gettext("Registration price");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"price_registration\" value=\"\" required=\"required\">
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 101
        echo gettext("Renewal price");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"price_renew\" value=\"\" required=\"required\">
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 109
        echo gettext("Transfer price");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"price_transfer\" value=\"\" required=\"required\">
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 117
        echo gettext("Minimum years of registration");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"min_years\" value=\"1\" required=\"required\">
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 125
        echo gettext("Allow registration");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"radio\" name=\"allow_register\" value=\"1\"checked=\"checked\"/><label>";
        // line 127
        echo gettext("Yes");
        echo "</label>
                            <input type=\"radio\" name=\"allow_register\" value=\"0\"/><label>";
        // line 128
        echo gettext("No");
        echo "</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 134
        echo gettext("Allow transfer");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"radio\" name=\"allow_transfer\" value=\"1\" checked=\"checked\"/><label>";
        // line 136
        echo gettext("Yes");
        echo "</label>
                            <input type=\"radio\" name=\"allow_transfer\" value=\"0\"/><label>";
        // line 137
        echo gettext("No");
        echo "</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 143
        echo gettext("Active");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"radio\" name=\"active\" value=\"1\" checked=\"checked\"/><label>";
        // line 145
        echo gettext("Yes");
        echo "</label>
                            <input type=\"radio\" name=\"active\" value=\"0\"/><label>";
        // line 146
        echo gettext("No");
        echo "</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <input type=\"submit\" value=\"";
        // line 151
        echo gettext("Add");
        echo "\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>

        </div>

        <div class=\"tab_content nopadding\" id=\"tab-registrars\">

        <div class=\"help\">
            <h5>";
        // line 160
        echo gettext("Domain registrars");
        echo "</h5>
            <p>";
        // line 161
        echo gettext("Manage domain registrars");
        echo "</p>
        </div>

        <table class=\"tableStatic wide\">
            <thead>
                <tr class=\"noborder\">
                    <th>";
        // line 167
        echo gettext("Title");
        echo "</th>
                    <th style=\"width:18%\">&nbsp;</th>
                </tr>
            </thead>
            <tbody>
                ";
        // line 172
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "servicedomain_registrar_get_list", [], "any", false, false, false, 172), "list", [], "any", false, false, false, 172));
        foreach ($context['_seq'] as $context["_key"] => $context["registrar"]) {
            // line 173
            echo "                <tr>
                    <td>";
            // line 174
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["registrar"], "title", [], "any", false, false, false, 174), "html", null, true);
            echo "</td>
                    <td>
                        <a class=\"btn14 mr5\" href=\"";
            // line 176
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("servicedomain/registrar/");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["registrar"], "id", [], "any", false, false, false, 176), "html", null, true);
            echo "\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                        <a class=\"bb-button btn14 api-link\" href=\"";
            // line 177
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/servicedomain/registrar_copy", ["id" => twig_get_attribute($this->env, $this->source, $context["registrar"], "id", [], "any", false, false, false, 177)]);
            echo "\" data-api-reload=\"1\" title=\"Install\"><img src=\"images/icons/dark/baloons.png\" alt=\"\"></a>
                        <a class=\"bb-button btn14 api-link\" href=\"";
            // line 178
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/servicedomain/registrar_delete", ["id" => twig_get_attribute($this->env, $this->source, $context["registrar"], "id", [], "any", false, false, false, 178)]);
            echo "\" data-api-confirm=\"Are you sure?\" data-api-reload=\"1\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                    </td>
                </tr>
                ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['registrar'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 182
        echo "            </tbody>
        </table>

            </div>

        <div class=\"tab_content nopadding\" id=\"tab-nameservers\">

        <div class=\"help\">
            <h5>";
        // line 190
        echo gettext("Nameservers");
        echo "</h5>
            <p>";
        // line 191
        echo gettext("Setup default nameservers that will be used for new domain registrations if client have not provided his own nameservers in order form");
        echo "</p>
        </div>

        <form method=\"post\" action=\"";
        // line 194
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/system/update_params");
        echo "\" class=\"mainForm api-form\" data-api-msg=\"Nameservers updated\">
        <fieldset>
            <div class=\"rowElem noborder\">
                <label>";
        // line 197
        echo gettext("Nameserver 1");
        echo ":</label>
                <div class=\"formRight noborder\">
                    <input type=\"text\" name=\"nameserver_1\" value=\"";
        // line 199
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "system_param", [0 => ["key" => "nameserver_1"]], "method", false, false, false, 199), "html", null, true);
        echo "\">
                </div>
                <div class=\"fix\"></div>
            </div>

            <div class=\"rowElem\">
                <label>";
        // line 205
        echo gettext("Nameserver 2");
        echo ":</label>
                <div class=\"formRight noborder\">
                    <input type=\"text\" name=\"nameserver_2\" value=\"";
        // line 207
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "system_param", [0 => ["key" => "nameserver_2"]], "method", false, false, false, 207), "html", null, true);
        echo "\">
                </div>
                <div class=\"fix\"></div>
            </div>
            <div class=\"rowElem\">
                <label>";
        // line 212
        echo gettext("Nameserver 3");
        echo ":</label>
                <div class=\"formRight noborder\">
                    <input type=\"text\" name=\"nameserver_3\" value=\"";
        // line 214
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "system_param", [0 => ["key" => "nameserver_3"]], "method", false, false, false, 214), "html", null, true);
        echo "\">
                </div>
                <div class=\"fix\"></div>
            </div>

            <div class=\"rowElem\">
                <label>";
        // line 220
        echo gettext("Nameserver 4");
        echo ":</label>
                <div class=\"formRight noborder\">
                    <input type=\"text\" name=\"nameserver_4\" value=\"";
        // line 222
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "system_param", [0 => ["key" => "nameserver_4"]], "method", false, false, false, 222), "html", null, true);
        echo "\">
                </div>
                <div class=\"fix\"></div>
            </div>
            <input type=\"submit\" value=\"";
        // line 226
        echo gettext("Update nameservers");
        echo "\" class=\"greyishBtn submitForm\" />
        </fieldset>
        </form>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-new-registrar\">
            ";
        // line 232
        $this->loadTemplate("partial_extensions.phtml", "mod_servicedomain_index.phtml", 232)->display(twig_to_array(["type" => "domain-registrar", "header" => "List of domain registrars on extensions site"]));
        // line 233
        echo "            <div class=\"body\">
                <h1 class=\"pt10\">";
        // line 234
        echo gettext("Adding new domain registrar");
        echo "</h1>
                <p>";
        // line 235
        echo gettext("Follow instructions below to install new domain registrar.");
        echo "</p>

                <div class=\"pt20 list arrowGrey\">
                    <ul>
                        <li>";
        // line 239
        echo gettext("Check domain registrar you are looking for is available at");
        echo " <a href=\"http://extensions.boxbilling.com/\" target=\"_blank\">BoxBilling extensions site</a></li>
                        <li>";
        // line 240
        echo gettext("Download domain registrar file and place to");
        echo " <strong>";
        echo twig_escape_filter($this->env, twig_constant("BB_PATH_LIBRARY"), "html", null, true);
        echo "/Registrar/Adapter</strong></li>
                        <li>";
        // line 241
        echo gettext("Reload this page to see newly detected domain registrar");
        echo "</li>
                        <li>";
        // line 242
        echo gettext("Click on install button. Now you will be able to create top level domains with new domain registrar");
        echo "</li>
                        <li>";
        // line 243
        echo gettext("For developers. Read");
        echo " <a href=\"http://docs.boxbilling.com/en/latest/reference/extension.html#domain-registrar\" target=\"_blank\">";
        echo gettext("BoxBilling documentation");
        echo "</a> ";
        echo gettext("to learn how to create your own domain registrar.");
        echo "</li>
                    </ul>
                </div>

            </div>

            ";
        // line 249
        if ((twig_length_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "servicedomain_registrar_get_available", [], "any", false, false, false, 249)) > 0)) {
            // line 250
            echo "            <table class=\"tableStatic wide\">
            <thead>
                <tr>
                    <td>";
            // line 253
            echo gettext("Code");
            echo "</td>
                    <td style=\"width: 5%\">";
            // line 254
            echo gettext("Install");
            echo "</td>
                </tr>
            </thead>

            <tbody>
            ";
            // line 259
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "servicedomain_registrar_get_available", [], "any", false, false, false, 259));
            $context['_iterated'] = false;
            foreach ($context['_seq'] as $context["_key"] => $context["code"]) {
                // line 260
                echo "            <tr>
                <td>";
                // line 261
                echo twig_escape_filter($this->env, $context["code"], "html", null, true);
                echo "</td>
                <td class=\"actions\">
                    <a class=\"bb-button btn14 api-link\" href=\"";
                // line 263
                echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/servicedomain/registrar_install", ["code" => $context["code"]]);
                echo "\" data-api-msg=\"Domain registrar installed\" title=\"Install\"><img src=\"images/icons/dark/cog.png\" alt=\"\"></a>
                </td>
            </tr>
            </tbody>

            ";
                $context['_iterated'] = true;
            }
            if (!$context['_iterated']) {
                // line 269
                echo "            <tbody>
                <tr>
                    <td colspan=\"5\">
                        ";
                // line 272
                echo gettext("All payment gateways installed");
                // line 273
                echo "                    </td>
                </tr>
            </tbody>
            ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['code'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 277
            echo "        </table>
        ";
        }
        // line 279
        echo "        </div>
    </div>
</div>
";
    }

    public function getTemplateName()
    {
        return "mod_servicedomain_index.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  643 => 279,  639 => 277,  630 => 273,  628 => 272,  623 => 269,  612 => 263,  607 => 261,  604 => 260,  599 => 259,  591 => 254,  587 => 253,  582 => 250,  580 => 249,  567 => 243,  563 => 242,  559 => 241,  553 => 240,  549 => 239,  542 => 235,  538 => 234,  535 => 233,  533 => 232,  524 => 226,  517 => 222,  512 => 220,  503 => 214,  498 => 212,  490 => 207,  485 => 205,  476 => 199,  471 => 197,  465 => 194,  459 => 191,  455 => 190,  445 => 182,  435 => 178,  431 => 177,  425 => 176,  420 => 174,  417 => 173,  413 => 172,  405 => 167,  396 => 161,  392 => 160,  380 => 151,  372 => 146,  368 => 145,  363 => 143,  354 => 137,  350 => 136,  345 => 134,  336 => 128,  332 => 127,  327 => 125,  316 => 117,  305 => 109,  294 => 101,  283 => 93,  275 => 87,  264 => 85,  260 => 84,  254 => 81,  244 => 74,  238 => 71,  232 => 68,  228 => 67,  218 => 59,  208 => 55,  202 => 54,  193 => 52,  190 => 51,  182 => 50,  172 => 49,  162 => 48,  157 => 46,  153 => 45,  149 => 44,  145 => 43,  142 => 42,  138 => 41,  129 => 35,  125 => 34,  121 => 33,  117 => 32,  113 => 31,  109 => 30,  100 => 24,  96 => 23,  85 => 15,  81 => 14,  77 => 13,  73 => 12,  69 => 11,  63 => 7,  59 => 6,  52 => 3,  47 => 1,  45 => 4,  43 => 2,  36 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends \"layout_default.phtml\" %}
{% import \"macro_functions.phtml\" as mf %}
{% block meta_title %}Domain management{% endblock %}
{% set active_menu = 'system' %}

{% block content %}

<div class=\"widget simpleTabs\">

    <ul class=\"tabs\">
        <li><a href=\"#tab-tlds\">{% trans 'Top level domains' %}</a></li>
        <li><a href=\"#tab-new-tld\">{% trans 'New top level domain' %}</a></li>
        <li><a href=\"#tab-registrars\">{% trans 'Registrars' %}</a></li>
        <li><a href=\"#tab-new-registrar\">{% trans 'New domain registrar' %}</a></li>
        <li><a href=\"#tab-nameservers\">{% trans 'Nameservers' %}</a></li>
    </ul>

    <div class=\"tabs_container\">
        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-tlds\">

<div class=\"help\">
    <h5>{% trans 'Manage TLDs' %}</h5>
    <p>{% trans 'Setup domain pricing and allowed operations. Assign specific domain registrars for each Top Level Domain (TLD)' %}</p>
</div>

<table class=\"tableStatic wide\">
    <thead>
        <tr class=\"noborder\">
            <td>{% trans 'TLD' %}</td>
            <td>{% trans 'Registration' %}</td>
            <td>{% trans 'Renew' %}</td>
            <td>{% trans 'Transfer' %} </td>
            <td>{% trans 'Operations' %}</td>
            <td>{% trans 'Registrar' %}</td>
            <td style=\"width:13%\">&nbsp;</td>
        </tr>
    </thead>

    <tbody>
        {% for tld in admin.servicedomain_tld_get_list({\"per_page\":99}).list %}
        <tr>
            <td>{{ tld.tld }}</td>
            <td>{{ mf.currency_format(tld.price_registration) }}</td>
            <td>{{ mf.currency_format(tld.price_renew) }}</td>
            <td>{{ mf.currency_format(tld.price_transfer) }}</td>
            <td>
             {% trans 'Allow register:' %} {% if tld.allow_register %}{% trans 'Yes' %}{% else %}{% trans 'No' %}{% endif %}<br/>
             {% trans 'Allow transfer:' %} {% if tld.allow_transfer %}{% trans 'Yes' %}{% else %}{% trans 'No' %}{% endif %}<br/>
             {% trans 'Active:' %} {% if tld.active %}{% trans 'Yes' %}{% else %}{% trans 'No' %}{% endif %}
            </td>
            <td><a class=\"\" href=\"{{ 'servicedomain/registrar'|alink }}/{{ tld.registrar.id }}\">{{ tld.registrar.title }}</a></td>
            <td class=\"actions\">
                <a class=\"btn14 mr5\" href=\"{{ 'servicedomain/tld'|alink }}/{{ tld.tld|slice(1) }}\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                <a class=\"bb-button btn14 bb-rm-tr api-link\" data-api-confirm=\"Are you sure?\" data-api-reload=\"1\" href=\"{{ 'api/admin/servicedomain/tld_delete'|link({'tld' : tld.tld}) }}\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
            </td>
        </tr>
        {% endfor %}
     </tbody>

</table>
</div>

        <div class=\"tab_content nopadding\" id=\"tab-new-tld\">

            <div class=\"help\">
                <h5>{% trans 'Add new top level domain' %}</h5>
                <p>{% trans 'Setup new TLD prices and properties' %}</p>
            </div>

            <form method=\"post\" action=\"{{ 'api/admin/servicedomain/tld_create'|link }}\" class=\"mainForm save api-form\" data-api-reload=\"1\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>{% trans 'Tld' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"tld\" value=\".\" required=\"required\" class=\"dirTop\" title=\"Must start with a dot\">
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Registrar' %}:</label>
                        <div class=\"formRight\">
                            <select name=\"tld_registrar_id\" required=\"required\">
                                {% for id,title in admin.servicedomain_registrar_get_pairs %}
                                <option value=\"{{id}}\">{{ title }}</option>
                                {% endfor %}
                            </select>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Registration price' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"price_registration\" value=\"\" required=\"required\">
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Renewal price' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"price_renew\" value=\"\" required=\"required\">
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Transfer price' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"price_transfer\" value=\"\" required=\"required\">
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Minimum years of registration' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"min_years\" value=\"1\" required=\"required\">
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Allow registration' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"radio\" name=\"allow_register\" value=\"1\"checked=\"checked\"/><label>{% trans 'Yes' %}</label>
                            <input type=\"radio\" name=\"allow_register\" value=\"0\"/><label>{% trans 'No' %}</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Allow transfer' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"radio\" name=\"allow_transfer\" value=\"1\" checked=\"checked\"/><label>{% trans 'Yes' %}</label>
                            <input type=\"radio\" name=\"allow_transfer\" value=\"0\"/><label>{% trans 'No' %}</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Active' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"radio\" name=\"active\" value=\"1\" checked=\"checked\"/><label>{% trans 'Yes' %}</label>
                            <input type=\"radio\" name=\"active\" value=\"0\"/><label>{% trans 'No' %}</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <input type=\"submit\" value=\"{% trans 'Add' %}\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>

        </div>

        <div class=\"tab_content nopadding\" id=\"tab-registrars\">

        <div class=\"help\">
            <h5>{% trans 'Domain registrars' %}</h5>
            <p>{% trans 'Manage domain registrars' %}</p>
        </div>

        <table class=\"tableStatic wide\">
            <thead>
                <tr class=\"noborder\">
                    <th>{% trans 'Title' %}</th>
                    <th style=\"width:18%\">&nbsp;</th>
                </tr>
            </thead>
            <tbody>
                {% for registrar in admin.servicedomain_registrar_get_list.list %}
                <tr>
                    <td>{{ registrar.title }}</td>
                    <td>
                        <a class=\"btn14 mr5\" href=\"{{ 'servicedomain/registrar/'|alink }}/{{ registrar.id }}\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                        <a class=\"bb-button btn14 api-link\" href=\"{{ 'api/admin/servicedomain/registrar_copy'|link({'id' : registrar.id}) }}\" data-api-reload=\"1\" title=\"Install\"><img src=\"images/icons/dark/baloons.png\" alt=\"\"></a>
                        <a class=\"bb-button btn14 api-link\" href=\"{{ 'api/admin/servicedomain/registrar_delete'|link({'id' : registrar.id}) }}\" data-api-confirm=\"Are you sure?\" data-api-reload=\"1\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                    </td>
                </tr>
                {% endfor %}
            </tbody>
        </table>

            </div>

        <div class=\"tab_content nopadding\" id=\"tab-nameservers\">

        <div class=\"help\">
            <h5>{% trans 'Nameservers' %}</h5>
            <p>{% trans 'Setup default nameservers that will be used for new domain registrations if client have not provided his own nameservers in order form' %}</p>
        </div>

        <form method=\"post\" action=\"{{ 'api/admin/system/update_params'|link }}\" class=\"mainForm api-form\" data-api-msg=\"Nameservers updated\">
        <fieldset>
            <div class=\"rowElem noborder\">
                <label>{% trans 'Nameserver 1' %}:</label>
                <div class=\"formRight noborder\">
                    <input type=\"text\" name=\"nameserver_1\" value=\"{{ admin.system_param({'key':'nameserver_1'}) }}\">
                </div>
                <div class=\"fix\"></div>
            </div>

            <div class=\"rowElem\">
                <label>{% trans 'Nameserver 2' %}:</label>
                <div class=\"formRight noborder\">
                    <input type=\"text\" name=\"nameserver_2\" value=\"{{ admin.system_param({'key':'nameserver_2'}) }}\">
                </div>
                <div class=\"fix\"></div>
            </div>
            <div class=\"rowElem\">
                <label>{% trans 'Nameserver 3' %}:</label>
                <div class=\"formRight noborder\">
                    <input type=\"text\" name=\"nameserver_3\" value=\"{{ admin.system_param({'key':'nameserver_3'}) }}\">
                </div>
                <div class=\"fix\"></div>
            </div>

            <div class=\"rowElem\">
                <label>{% trans 'Nameserver 4' %}:</label>
                <div class=\"formRight noborder\">
                    <input type=\"text\" name=\"nameserver_4\" value=\"{{ admin.system_param({'key':'nameserver_4'}) }}\">
                </div>
                <div class=\"fix\"></div>
            </div>
            <input type=\"submit\" value=\"{% trans 'Update nameservers' %}\" class=\"greyishBtn submitForm\" />
        </fieldset>
        </form>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-new-registrar\">
            {% include \"partial_extensions.phtml\" with {'type': 'domain-registrar', 'header':\"List of domain registrars on extensions site\"} only %}
            <div class=\"body\">
                <h1 class=\"pt10\">{% trans 'Adding new domain registrar' %}</h1>
                <p>{% trans 'Follow instructions below to install new domain registrar.' %}</p>

                <div class=\"pt20 list arrowGrey\">
                    <ul>
                        <li>{% trans 'Check domain registrar you are looking for is available at' %} <a href=\"http://extensions.boxbilling.com/\" target=\"_blank\">BoxBilling extensions site</a></li>
                        <li>{% trans 'Download domain registrar file and place to' %} <strong>{{ constant('BB_PATH_LIBRARY') }}/Registrar/Adapter</strong></li>
                        <li>{% trans 'Reload this page to see newly detected domain registrar' %}</li>
                        <li>{% trans 'Click on install button. Now you will be able to create top level domains with new domain registrar' %}</li>
                        <li>{% trans 'For developers. Read' %} <a href=\"http://docs.boxbilling.com/en/latest/reference/extension.html#domain-registrar\" target=\"_blank\">{% trans 'BoxBilling documentation' %}</a> {% trans 'to learn how to create your own domain registrar.' %}</li>
                    </ul>
                </div>

            </div>

            {% if admin.servicedomain_registrar_get_available|length > 0 %}
            <table class=\"tableStatic wide\">
            <thead>
                <tr>
                    <td>{% trans 'Code' %}</td>
                    <td style=\"width: 5%\">{% trans 'Install' %}</td>
                </tr>
            </thead>

            <tbody>
            {% for code in admin.servicedomain_registrar_get_available %}
            <tr>
                <td>{{ code }}</td>
                <td class=\"actions\">
                    <a class=\"bb-button btn14 api-link\" href=\"{{ 'api/admin/servicedomain/registrar_install'|link({'code' : code}) }}\" data-api-msg=\"Domain registrar installed\" title=\"Install\"><img src=\"images/icons/dark/cog.png\" alt=\"\"></a>
                </td>
            </tr>
            </tbody>

            {% else %}
            <tbody>
                <tr>
                    <td colspan=\"5\">
                        {% trans 'All payment gateways installed' %}
                    </td>
                </tr>
            </tbody>
            {% endfor %}
        </table>
        {% endif %}
        </div>
    </div>
</div>
{% endblock %}
", "mod_servicedomain_index.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-themes/admin_default/html/mod_servicedomain_index.phtml");
    }
}
