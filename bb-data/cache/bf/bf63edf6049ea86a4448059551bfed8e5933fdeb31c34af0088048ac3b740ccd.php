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

/* mod_order_manage.phtml */
class __TwigTemplate_2647f1f999e95a80a6b4ec61745f89a063f93f30be0591d2b96d5973e12bb4bf extends \Twig\Template
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
            'js' => [$this, 'block_js'],
        ];
    }

    protected function doGetParent(array $context)
    {
        // line 1
        return $this->loadTemplate(((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ajax", [], "any", false, false, false, 1)) ? ("layout_blank.phtml") : ("layout_default.phtml")), "mod_order_manage.phtml", 1);
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 2
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_order_manage.phtml", 2)->unwrap();
        // line 13
        $context["active_menu"] = "order";
        // line 15
        $context["service_partial"] = (("mod_service" . twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "service_type", [], "any", false, false, false, 15)) . "_manage.phtml");
        // line 16
        if ((twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "group_master", [], "any", false, false, false, 16) == 1)) {
            // line 17
            $context["addons"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "order_addons", [0 => ["id" => twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "id", [], "any", false, false, false, 17)]], "method", false, false, false, 17);
        }
        // line 1
        $this->getParent($context)->display($context, array_merge($this->blocks, $blocks));
    }

    // line 3
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "title", [], "any", false, false, false, 3), "html", null, true);
    }

    // line 5
    public function block_breadcrumbs($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 6
        echo "<ul>
    <li class=\"firstB\"><a href=\"";
        // line 7
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/");
        echo "\">";
        echo gettext("Home");
        echo "</a></li>
    <li><a href=\"";
        // line 8
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("order");
        echo "\">";
        echo gettext("Orders");
        echo "</a></li>
    <li class=\"lastB\">";
        // line 9
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "title", [], "any", false, false, false, 9), "html", null, true);
        echo "</li>
</ul>
";
    }

    // line 20
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 21
        echo "
<div class=\"widget simpleTabs tabsRight\">

    <div class=\"head\">
        <h5 class=\"iFrames\">";
        // line 25
        echo gettext("Order management");
        echo "</h5>
    </div>

    <ul class=\"tabs\">
        <li><a href=\"#tab-info\">";
        // line 29
        echo gettext("Details");
        echo "</a></li>
        <li><a href=\"#tab-manage\">";
        // line 30
        echo gettext("Edit order");
        echo "</a></li>
        <li><a href=\"#tab-config\">";
        // line 31
        echo gettext("Edit order config");
        echo "</a></li>
        ";
        // line 32
        if (twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "system_template_exists", [0 => ["file" => ($context["service_partial"] ?? null)]], "method", false, false, false, 32)) {
            echo "<li><a href=\"#tab-service\">";
            echo gettext("Service management");
            echo "</a></li>";
        }
        // line 33
        echo "        <li><a href=\"#tab-invoices\">";
        echo gettext("Invoices");
        echo "</a></li>
        ";
        // line 34
        if ((twig_length_filter($this->env, ($context["addons"] ?? null)) > 0)) {
            echo "<li><a href=\"#tab-addons\">";
            echo gettext("Addons");
            echo "</a></li>";
        }
        // line 35
        echo "        <li><a href=\"#tab-status\">";
        echo gettext("History");
        echo "</a></li>
        <li><a href=\"#tab-support\">";
        // line 36
        echo gettext("Support");
        echo "</a></li>
    </ul>

    <div class=\"tabs_container\">
        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-info\">
            <div class=\"help\">
                <h2>";
        // line 43
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "title", [], "any", false, false, false, 43), "html", null, true);
        echo "</h2>
            </div>

            <div class=\"block\">
                <table class=\"tableStatic wide\">
                    <tbody>
                    <tr class=\"noborder\">
                        <td><label>";
        // line 50
        echo gettext("Order");
        echo "</label></td>
                        <td>#";
        // line 51
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "id", [], "any", false, false, false, 51), "html", null, true);
        echo " (";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "service_type", [], "any", false, false, false, 51), "html", null, true);
        echo ")</td>
                    </tr>

                    <tr>
                        <td><label>";
        // line 55
        echo gettext("Client");
        echo "</label></td>
                        <td><a href=\"";
        // line 56
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("client/manage");
        echo "/";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "client", [], "any", false, false, false, 56), "id", [], "any", false, false, false, 56), "html", null, true);
        echo "\">";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "client", [], "any", false, false, false, 56), "first_name", [], "any", false, false, false, 56), "html", null, true);
        echo " ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "client", [], "any", false, false, false, 56), "last_name", [], "any", false, false, false, 56), "html", null, true);
        echo "</a></td>
                    </tr>

                    <tr>
                        <td><label>";
        // line 60
        echo gettext("Title");
        echo "</label></td>
                        <td><a href=\"";
        // line 61
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("product/manage");
        echo "/";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "product_id", [], "any", false, false, false, 61), "html", null, true);
        echo "\"><strong>";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "title", [], "any", false, false, false, 61), "html", null, true);
        echo "</strong></a></td>
                    </tr>

                    <tr>
                        <td><label>";
        // line 65
        echo gettext("Payment amount");
        echo "</label></td>
                        <td>";
        // line 66
        echo twig_call_macro($macros["mf"], "macro_currency_format", [twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "total", [], "any", false, false, false, 66), twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "currency", [], "any", false, false, false, 66)], 66, $context, $this->getSourceContext());
        echo " ";
        if (twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "period", [], "any", false, false, false, 66)) {
            echo twig_call_macro($macros["mf"], "macro_period_name", [twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "period", [], "any", false, false, false, 66)], 66, $context, $this->getSourceContext());
        }
        echo " ";
        if ((twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "quantity", [], "any", false, false, false, 66) > 1)) {
            echo "(";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "quantity", [], "any", false, false, false, 66), "html", null, true);
            echo " x ";
            echo twig_call_macro($macros["mf"], "macro_currency_format", [twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "price", [], "any", false, false, false, 66), twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "currency", [], "any", false, false, false, 66)], 66, $context, $this->getSourceContext());
            echo ")";
        }
        echo "</td>
                    </tr>

                    ";
        // line 69
        if ((twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "discount", [], "any", false, false, false, 69) && (twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "discount", [], "any", false, false, false, 69) > 0))) {
            // line 70
            echo "                    <tr>
                        <td><label>";
            // line 71
            echo gettext("Order discount");
            echo "</label></td>
                        <td>-";
            // line 72
            echo twig_call_macro($macros["mf"], "macro_currency_format", [twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "discount", [], "any", false, false, false, 72), twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "currency", [], "any", false, false, false, 72)], 72, $context, $this->getSourceContext());
            echo " </td>
                    </tr>

                    <tr>
                        <td><label>";
            // line 76
            echo gettext("Payment amount after discount");
            echo "</label></td>
                        <td>";
            // line 77
            echo twig_call_macro($macros["mf"], "macro_currency_format", [(twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "total", [], "any", false, false, false, 77) - twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "discount", [], "any", false, false, false, 77)), twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "currency", [], "any", false, false, false, 77)], 77, $context, $this->getSourceContext());
            echo " </td>
                    </tr>
                    ";
        }
        // line 80
        echo "
                    <tr>
                        <td><label>";
        // line 82
        echo gettext("Order status");
        echo "</label></td>
                        <td>";
        // line 83
        echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "status", [], "any", false, false, false, 83)], 83, $context, $this->getSourceContext());
        echo "</td>
                    </tr>

                    <tr>
                        <td><label>";
        // line 87
        echo gettext("Order created");
        echo "</label></td>
                        <td>";
        // line 88
        echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "created_at", [], "any", false, false, false, 88), "l, d F Y"), "html", null, true);
        echo "</td>
                    </tr>

                    <tr>
                        <td><label>";
        // line 92
        echo gettext("Activated");
        if (twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "activated_at", [], "any", false, false, false, 92)) {
            echo " ";
            echo twig_escape_filter($this->env, twig_timeago_filter(twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "activated_at", [], "any", false, false, false, 92)), "html", null, true);
            echo " ago";
        }
        echo "</label></td>
                        <td>";
        // line 93
        if (twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "activated_at", [], "any", false, false, false, 93)) {
            echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "activated_at", [], "any", false, false, false, 93), "l, d F Y"), "html", null, true);
            echo " ";
        } else {
            echo "-";
        }
        echo "</td>
                    </tr>

                    <tr>
                        <td><label>";
        // line 97
        echo gettext("Renewal date");
        echo " ";
        if (twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "expires_at", [], "any", false, false, false, 97)) {
            echo " in ";
            echo twig_escape_filter($this->env, twig_daysleft_filter(twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "expires_at", [], "any", false, false, false, 97)), "html", null, true);
            echo " day(s) ";
        }
        echo "</label></td>
                        <td>";
        // line 98
        if (twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "expires_at", [], "any", false, false, false, 98)) {
            echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "expires_at", [], "any", false, false, false, 98), "l, d F Y"), "html", null, true);
        } else {
            echo "-";
        }
        echo "</td>
                    </tr>

                    <tr>
                        <td><label>";
        // line 102
        echo gettext("Order notes");
        echo "</label></td>
                        <td>";
        // line 103
        echo twig_bbmd_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "notes", [], "any", false, false, false, 103));
        echo "</td>
                    </tr>

                    <tr>
                        <td><label>";
        // line 107
        echo gettext("Order group ID");
        echo "</label></td>
                        <td>";
        // line 108
        echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "group_id", [], "any", true, true, false, 108)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "group_id", [], "any", false, false, false, 108), "-")) : ("-")), "html", null, true);
        echo "</td>
                    </tr>

                    ";
        // line 111
        if (twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "promo_id", [], "any", false, false, false, 111)) {
            // line 112
            echo "                    <tr>
                        <td><label>";
            // line 113
            echo gettext("Order promo code");
            echo "</label></td>
                        <td>
                            ";
            // line 115
            $context["promo"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "product_promo_get", [0 => ["id" => twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "promo_id", [], "any", false, false, false, 115)]], "method", false, false, false, 115);
            // line 116
            echo "                            ";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["promo"] ?? null), "code", [], "any", false, false, false, 116), "html", null, true);
            echo "
                        </td>
                    </tr>
                    ";
        }
        // line 120
        echo "                    
                    ";
        // line 121
        if ((twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "active_tickets", [], "any", false, false, false, 121) > 0)) {
            // line 122
            echo "                    <tr>
                        <td><label>";
            // line 123
            echo gettext("Active support tickets");
            echo "</label></td>
                        <td>
                            <div class=\"num\"><a href=\"";
            // line 125
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support", ["status" => "open", "order_id" => twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "id", [], "any", false, false, false, 125)]);
            echo "\" class=\"redNum\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "active_tickets", [], "any", false, false, false, 125), "html", null, true);
            echo "</a></div>
                        </td>
                    </tr>
                    ";
        }
        // line 129
        echo "                    </tbody>
                    
                    <tfoot>
                        <tr>
                            <td colspan=\"2\">
                                
                                <div class=\"aligncenter\">
                                    ";
        // line 136
        ob_start();
        // line 137
        echo "                                    
                                    ";
        // line 138
        if (((twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "status", [], "any", false, false, false, 138) == "pending_setup") || (twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "status", [], "any", false, false, false, 138) == "failed_setup"))) {
            // line 139
            echo "                                    <a href=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/order/activate", ["id" => twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "id", [], "any", false, false, false, 139)]);
            echo "\" title=\"\" data-api-confirm=\"Are you sure?\" class=\"btn55 mr10 api-link\" data-api-reload=\"Order activated\"><img src=\"images/icons/middlenav/play2.png\" alt=\"\"><span>Activate</span></a>
                                    ";
        }
        // line 141
        echo "                                    
                                    ";
        // line 142
        if ((twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "status", [], "any", false, false, false, 142) == "active")) {
            // line 143
            echo "                                    ";
            $context["params"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "extension_config_get", [0 => ["ext" => "mod_order"]], "method", false, false, false, 143);
            // line 144
            echo "                                    <a href=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/order/renew", ["id" => twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "id", [], "any", false, false, false, 144)]);
            echo "\" title=\"\" data-api-confirm=\"Are you sure?\" class=\"btn55 mr10 api-link\" data-api-reload=\"Order renewed\"><img src=\"images/icons/middlenav/play2.png\" alt=\"\"><span>Renew</span></a>

                                    ";
            // line 146
            if ((twig_trim_filter(twig_get_attribute($this->env, $this->source, ($context["params"] ?? null), "suspend_reason_list", [], "any", false, false, false, 146)) == null)) {
                // line 147
                echo "                                    <a href=\"";
                echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/order/suspend", ["id" => twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "id", [], "any", false, false, false, 147)]);
                echo "\" title=\"\" class=\"btn55 mr10 api-link\" data-api-prompt-key=\"reason\" data-api-prompt=\"1\" data-api-prompt-text=\"";
                echo gettext("Reason of suspension");
                echo "\" data-api-prompt-title=\"";
                echo gettext("Suspension reason");
                echo "\" data-api-reload=\"Order suspended\"><img src=\"images/icons/middlenav/stop.png\" alt=\"\"><span>Suspend</span></a>

                                    ";
            } else {
                // line 150
                echo "                                    <div id=\"suspend_popup\" style=\"position: fixed; z-index: 99999; padding: 5px; margin: 0px; min-width: 310px; max-width: 310px; top: 30%; left: 45%; display: none;\">
                                        <h5 id=\"suspend_popup_title\">";
                // line 151
                echo gettext("Suspension reason");
                echo "</h5>
                                        <div id=\"suspend_popup_content\" class=\"confirm\">
                                            <div id=\"suspend_popup_message\">
                                                <div>";
                // line 154
                echo gettext("Reason of suspension");
                // line 155
                echo "                                                    ";
                $context['_parent'] = $context;
                $context['_seq'] = twig_ensure_traversable(twig_split_filter($this->env, twig_trim_filter(twig_get_attribute($this->env, $this->source, ($context["params"] ?? null), "suspend_reason_list", [], "any", false, false, false, 155)), "
"));
                foreach ($context['_seq'] as $context["_key"] => $context["reason"]) {
                    // line 156
                    echo "                                                    <div class=\"item\">
                                                        <input type=\"radio\" value=\"";
                    // line 157
                    echo twig_escape_filter($this->env, $context["reason"], "html", null, true);
                    echo "\" name=\"reason\"/> <label>";
                    echo twig_escape_filter($this->env, $context["reason"], "html", null, true);
                    echo "</label>
                                                    </div>
                                                    ";
                }
                $_parent = $context['_parent'];
                unset($context['_seq'], $context['_iterated'], $context['_key'], $context['reason'], $context['_parent'], $context['loop']);
                $context = array_intersect_key($context, $_parent) + $_parent;
                // line 160
                echo "                                                </div>
                                            </div>
                                            <div id=\"suspend_popup_panel\">
                                                <input type=\"button\" class=\"blueBtn\" value=\"&nbsp;";
                // line 163
                echo gettext("Suspend");
                echo "&nbsp;\" id=\"popup_ok\" onclick=\"return susp.suspendOrder('/api/admin/order/suspend?id=";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "id", [], "any", false, false, false, 163), "html", null, true);
                echo "', 'reason');\"/>
                                                <input type=\"button\" class=\"basicBtn\" value=\"&nbsp;Cancel&nbsp;\" id=\"popup_cancel\" onclick=\"return susp.suspenderHide();\"/>
                                            </div>
                                        </div>
                                    </div>
                                    <a href=\"#\" title=\"\" id=\"suspend_button\" onclick=\"return susp.showSuspendPopup()\" data-api-reload=\"Order suspended\" class=\"btn55 mr10\"><img src=\"images/icons/middlenav/stop.png\" alt=\"\"><span>Suspend</span></a>
                                    ";
            }
            // line 170
            echo "                                    <a href=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/order/cancel", ["id" => twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "id", [], "any", false, false, false, 170)]);
            echo "\" title=\"\" class=\"btn55 mr10 api-link\" data-api-prompt-key=\"reason\" data-api-prompt=\"1\" data-api-prompt-text=\"";
            echo gettext("Reason of cancelation");
            echo "\" data-api-prompt-title=\"";
            echo gettext("Cancelation reason");
            echo "\" data-api-reload=\"Order canceled\"><img src=\"images/icons/middlenav/close.png\" alt=\"\"><span>Cancel</span></a>
                                    ";
        }
        // line 172
        echo "                                    
                                    ";
        // line 173
        if ((twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "status", [], "any", false, false, false, 173) == "suspended")) {
            // line 174
            echo "                                    <a href=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/order/unsuspend", ["id" => twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "id", [], "any", false, false, false, 174)]);
            echo "\" title=\"\" data-api-confirm=\"Are you sure?\" class=\"btn55 mr10 api-link\" data-api-reload=\"Order activated\"><img src=\"images/icons/middlenav/play2.png\" alt=\"\"><span>Unsuspend</span></a>
                                    ";
        }
        // line 176
        echo "                                    
                                    ";
        // line 177
        if ((twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "status", [], "any", false, false, false, 177) == "canceled")) {
            // line 178
            echo "                                    <a href=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/order/uncancel", ["id" => twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "id", [], "any", false, false, false, 178)]);
            echo "\" title=\"\" data-api-confirm=\"Are you sure?\" class=\"btn55 mr10 api-link\" data-api-reload=\"Order activated\"><img src=\"images/icons/middlenav/play2.png\" alt=\"\"><span>Activate</span></a>
                                    ";
        }
        // line 180
        echo "                                    
                                    <a href=\"";
        // line 181
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/order/delete", ["id" => twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "id", [], "any", false, false, false, 181)]);
        echo "\" title=\"\" data-api-confirm=\"Are you sure?\" data-api-redirect=\"";
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("order");
        echo "\" class=\"btn55 mr10 api-link\"><img src=\"images/icons/middlenav/trash.png\" alt=\"\"><span>Delete</span></a>

                                    ";
        // line 183
        if ( !twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "unpaid_invoice_id", [], "any", false, false, false, 183)) {
            // line 184
            echo "                                    <a href=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/invoice/renewal_invoice", ["id" => twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "id", [], "any", false, false, false, 184)]);
            echo "\" title=\"\" data-api-confirm=\"Are you sure?\" class=\"btn55 mr10 api-link\" data-api-reload=\"1\"><img src=\"images/icons/middlenav/create.png\" alt=\"\"><span>Issue invoice</span></a>
                                    ";
        }
        // line 186
        echo "                                    ";
        $context["order_actions"] = ('' === $tmp = ob_get_clean()) ? '' : new Markup($tmp, $this->env->getCharset());
        // line 187
        echo "                                    
                                    ";
        // line 188
        echo twig_escape_filter($this->env, ($context["order_actions"] ?? null), "html", null, true);
        echo "
                                </div>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

        </div>

        <div class=\"tab_content nopadding\" id=\"tab-manage\">
            <div class=\"help\">
                <h2>Order management</h2>
            </div>
            <form method=\"post\" action=\"";
        // line 202
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/order/update");
        echo "\" class=\"mainForm api-form\" data-api-reload=\"1\">
                <fieldset>

                    <div class=\"rowElem noborder\">
                        <label>";
        // line 206
        echo gettext("Title");
        echo "</label>
                        <div class=\"formRight noborder\">
                            <input type=\"text\" name=\"title\" value=\"";
        // line 208
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "title", [], "any", false, false, false, 208), "html", null, true);
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 214
        echo gettext("Changes status without performing action");
        echo "</label>
                        <div class=\"formRight noborder\">
                            ";
        // line 216
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["status", twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "order_get_status_pairs", [], "any", false, false, false, 216), twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "status", [], "any", false, false, false, 216), 0, "Select status"], 216, $context, $this->getSourceContext());
        echo "
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <div class=\"rowElem\">
                        <label>";
        // line 222
        echo gettext("Invoice option");
        echo "</label>
                        <div class=\"formRight noborder\">
                            ";
        // line 224
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["invoice_option", twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "order_get_invoice_options", [], "any", false, false, false, 224), twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "invoice_option", [], "any", false, false, false, 224)], 224, $context, $this->getSourceContext());
        echo "
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 230
        echo gettext("Price");
        echo "</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"price\" value=\"";
        // line 232
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "price", [], "any", false, false, false, 232), "html", null, true);
        echo "\" required=\"required\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 238
        echo gettext("Reason");
        echo "</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"reason\" value=\"";
        // line 240
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "reason", [], "any", false, false, false, 240), "html", null, true);
        echo "\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 246
        echo gettext("Period");
        echo "</label>
                        <div class=\"formRight\">
                            ";
        // line 248
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["period", twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_periods", [], "any", false, false, false, 248), twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "period", [], "any", false, false, false, 248), 0, "Not recurrent"], 248, $context, $this->getSourceContext());
        echo "
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 254
        echo gettext("Expires at");
        echo "</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"expires_at\" value=\"";
        // line 256
        if (twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "expires_at", [], "any", false, false, false, 256)) {
            echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "expires_at", [], "any", false, false, false, 256), "Y-m-d"), "html", null, true);
        }
        echo "\" class=\"datepicker\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <div class=\"rowElem\">
                        <label>";
        // line 262
        echo gettext("Created at");
        echo "</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"created_at\" value=\"";
        // line 264
        echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "created_at", [], "any", false, false, false, 264), "Y-m-d"), "html", null, true);
        echo "\" required=\"required\" class=\"datepicker\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <div class=\"rowElem\">
                        <label>";
        // line 270
        echo gettext("Activated at");
        echo "</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"activated_at\" value=\"";
        // line 272
        echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "activated_at", [], "any", false, false, false, 272), "Y-m-d"), "html", null, true);
        echo "\" required=\"required\" class=\"datepicker\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>";
        // line 278
        echo gettext("Notes");
        echo "</label>
                        <div class=\"formRight\">
                            <textarea name=\"notes\" cols=\"5\" rows=\"5\">";
        // line 280
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "notes", [], "any", false, false, false, 280), "html", null, true);
        echo "</textarea>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                     <input type=\"submit\" value=\"";
        // line 285
        echo gettext("Update");
        echo "\" class=\"greyishBtn submitForm\" />
                </fieldset>
                <input type=\"hidden\" name=\"id\" value=\"";
        // line 287
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "id", [], "any", false, false, false, 287), "html", null, true);
        echo "\">
            </form>
            
            ";
        // line 308
        echo "            ";
        // line 309
        echo "            
            ";
        // line 333
        echo "        </div>

        <div class=\"tab_content nopadding\" id=\"tab-config\">
            <div class=\"help\">
                <h2>";
        // line 337
        echo gettext("Order config management");
        echo "</h2>
                <h6>";
        // line 338
        echo gettext("Please be cautious and make sure you know what you are doing when editing order config! BoxBilling relies on these parameters and you might get unexpected results if you change some of them");
        echo "</h6>
            </div>
            <form method=\"post\" action=\"";
        // line 340
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/order/update_config");
        echo "\" class=\"mainForm api-form\" data-api-reload=\"1\">
                <fieldset>
                    ";
        // line 342
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "config", [], "any", false, false, false, 342));
        foreach ($context['_seq'] as $context["key"] => $context["config"]) {
            // line 343
            echo "                    <div class=\"rowElem noborder\">
                        <label>";
            // line 344
            echo twig_escape_filter($this->env, $context["key"], "html", null, true);
            echo "</label>
                        <div class=\"formRight noborder\">
                            ";
            // line 346
            if (twig_test_iterable($context["config"])) {
                // line 347
                echo "                                ";
                $context['_parent'] = $context;
                $context['_seq'] = twig_ensure_traversable($context["config"]);
                foreach ($context['_seq'] as $context["key2"] => $context["config2"]) {
                    // line 348
                    echo "                                    <input type=\"text\" name=\"config[";
                    echo twig_escape_filter($this->env, $context["key"], "html", null, true);
                    echo "][";
                    echo twig_escape_filter($this->env, $context["key2"], "html", null, true);
                    echo "]\" value=\"";
                    echo twig_escape_filter($this->env, $context["config2"], "html", null, true);
                    echo "\" ";
                    if ((null === $context["config2"])) {
                        echo " placeholder=\"null\" ";
                    }
                    echo "/>
                                ";
                }
                $_parent = $context['_parent'];
                unset($context['_seq'], $context['_iterated'], $context['key2'], $context['config2'], $context['_parent'], $context['loop']);
                $context = array_intersect_key($context, $_parent) + $_parent;
                // line 350
                echo "                            ";
            } else {
                // line 351
                echo "                                <input type=\"text\" name=\"config[";
                echo twig_escape_filter($this->env, $context["key"], "html", null, true);
                echo "]\" value=\"";
                echo twig_escape_filter($this->env, $context["config"], "html", null, true);
                echo "\" ";
                if ((null === $context["config"])) {
                    echo " placeholder=\"null\" ";
                }
                echo "/>
                            ";
            }
            // line 353
            echo "                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['key'], $context['config'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 357
        echo "
                     <input type=\"submit\" value=\"";
        // line 358
        echo gettext("Update");
        echo "\" class=\"greyishBtn submitForm\" />
                </fieldset>
                <input type=\"hidden\" name=\"id\" value=\"";
        // line 360
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "id", [], "any", false, false, false, 360), "html", null, true);
        echo "\">
            </form>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-addons\">
            <div class=\"help\">
                <h2>";
        // line 366
        echo gettext("Addons you have ordered with this service");
        echo "</h2>
            </div>
            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td>";
        // line 371
        echo gettext("Product/Service");
        echo "</td>
                        <td>";
        // line 372
        echo gettext("Price");
        echo "</td>
                        <td>";
        // line 373
        echo gettext("Billing Cycle");
        echo "</td>
                        <td>";
        // line 374
        echo gettext("Next due date");
        echo "</td>
                        <td>";
        // line 375
        echo gettext("Status");
        echo "</td>
                        <td>&nbsp</td>
                    </tr>
                </thead>

                <tbody>
                ";
        // line 381
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(($context["addons"] ?? null));
        foreach ($context['_seq'] as $context["i"] => $context["addon"]) {
            // line 382
            echo "                <tr>
                    <td>";
            // line 383
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["addon"], "title", [], "any", false, false, false, 383), "html", null, true);
            echo "</td>
                    <td>";
            // line 384
            echo twig_call_macro($macros["mf"], "macro_currency_format", [twig_get_attribute($this->env, $this->source, $context["addon"], "total", [], "any", false, false, false, 384), twig_get_attribute($this->env, $this->source, $context["addon"], "currency", [], "any", false, false, false, 384)], 384, $context, $this->getSourceContext());
            echo "</td>
                    <td>";
            // line 385
            echo twig_call_macro($macros["mf"], "macro_period_name", [twig_get_attribute($this->env, $this->source, $context["addon"], "period", [], "any", false, false, false, 385)], 385, $context, $this->getSourceContext());
            echo "</td>
                    <td>";
            // line 386
            if (twig_get_attribute($this->env, $this->source, $context["addon"], "expires_at", [], "any", false, false, false, 386)) {
                echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, $context["addon"], "expires_at", [], "any", false, false, false, 386), "l, d F Y"), "html", null, true);
            } else {
                echo "-";
            }
            echo "</td>
                    <td>";
            // line 387
            echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, $context["addon"], "status", [], "any", false, false, false, 387)], 387, $context, $this->getSourceContext());
            echo "</td>
                    <td class=\"actions\"><a class=\"bb-button btn14\" href=\"";
            // line 388
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/order/manage");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["addon"], "id", [], "any", false, false, false, 388), "html", null, true);
            echo "\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a></td>
                </tr>
                ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['i'], $context['addon'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 391
        echo "                </tbody>
            </table>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-service\">
            ";
        // line 396
        if (twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_template_exists", [0 => ["file" => ($context["service_partial"] ?? null)]], "method", false, false, false, 396)) {
            // line 397
            echo "                ";
            $context["service"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "order_service", [0 => ["id" => twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "id", [], "any", false, false, false, 397)]], "method", false, false, false, 397);
            // line 398
            echo "                ";
            $this->loadTemplate(($context["service_partial"] ?? null), "mod_order_manage.phtml", 398)->display(twig_array_merge($context, ["order" => ($context["order"] ?? null), "service" => ($context["service"] ?? null)]));
            // line 399
            echo "            ";
        } elseif ((twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "form_id", [], "any", false, false, false, 399) && twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "extension_is_on", [0 => ["mod" => "formbuilder"]], "method", false, false, false, 399))) {
            // line 400
            echo "                ";
            $this->loadTemplate("mod_formbuilder_manage.phtml", "mod_order_manage.phtml", 400)->display(twig_array_merge($context, ($context["order"] ?? null)));
            // line 401
            echo "            ";
        } else {
            // line 402
            echo "                ";
            // line 403
            echo "            ";
        }
        // line 404
        echo "        </div>

        <div class=\"tab_content nopadding\" id=\"tab-invoices\">
            <div class=\"help\">
                <h2>";
        // line 408
        echo gettext("Order invoices");
        echo "</h2>
            </div>

            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td>ID</td>
                        <td width=\"15%\">";
        // line 415
        echo gettext("Amount");
        echo "</td>
                        <td width=\"30%\">";
        // line 416
        echo gettext("Issued at");
        echo "</td>
                        <td width=\"30%\">";
        // line 417
        echo gettext("Paid at");
        echo "</td>
                        <td width=\"15%\">";
        // line 418
        echo gettext("Status");
        echo "</td>
                        <td>&nbsp;</td>
                    </tr>
                </thead>
                
                <tbody>
                    ";
        // line 424
        $context["invoices"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "invoice_get_list", [0 => ["per_page" => 50, "order_id" => twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "id", [], "any", false, false, false, 424)]], "method", false, false, false, 424);
        // line 425
        echo "                    ";
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["invoices"] ?? null), "list", [], "any", false, false, false, 425));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["_key"] => $context["invoice"]) {
            // line 426
            echo "                    <tr>
                        <td>";
            // line 427
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["invoice"], "id", [], "any", false, false, false, 427), "html", null, true);
            echo "</td>
                        <td>";
            // line 428
            echo twig_call_macro($macros["mf"], "macro_currency_format", [twig_get_attribute($this->env, $this->source, $context["invoice"], "total", [], "any", false, false, false, 428), twig_get_attribute($this->env, $this->source, $context["invoice"], "currency", [], "any", false, false, false, 428), 1], 428, $context, $this->getSourceContext());
            echo "</td>
                        <td>";
            // line 429
            echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, $context["invoice"], "created_at", [], "any", false, false, false, 429), "Y-m-d"), "html", null, true);
            echo "</td>
                        <td>";
            // line 430
            if (twig_get_attribute($this->env, $this->source, $context["invoice"], "paid_at", [], "any", false, false, false, 430)) {
                echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, $context["invoice"], "paid_at", [], "any", false, false, false, 430), "Y-m-d"), "html", null, true);
            } else {
                echo "-";
            }
            echo "</td>
                        <td>";
            // line 431
            echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, $context["invoice"], "status", [], "any", false, false, false, 431)], 431, $context, $this->getSourceContext());
            echo "</td>
                        <td class=\"actions\"><a class=\"bb-button btn14\" href=\"";
            // line 432
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/invoice/manage");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["invoice"], "id", [], "any", false, false, false, 432), "html", null, true);
            echo "\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a></td>
                    </tr>
                    ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 435
            echo "                    <tr>
                        <td colspan=\"5\">";
            // line 436
            echo gettext("The list is empty");
            echo "</td>
                    </tr>
                    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['invoice'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 439
        echo "                </tbody>
            </table>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-status\">
            <div class=\"help\">
                <h2>";
        // line 445
        echo gettext("Order status change history");
        echo "</h2>
            </div>
            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td>";
        // line 450
        echo gettext("Status");
        echo "</td>
                        <td>";
        // line 451
        echo gettext("Note");
        echo "</td>
                        <td>";
        // line 452
        echo gettext("Date");
        echo "</td>
                        <td>";
        // line 453
        echo gettext("Actions");
        echo "</td>
                    </tr>
                </thead>
                <tbody>
                    ";
        // line 457
        $context["statuses"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "order_status_history_get_list", [0 => ["per_page" => 50, "id" => twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "id", [], "any", false, false, false, 457)]], "method", false, false, false, 457);
        // line 458
        echo "                    ";
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["statuses"] ?? null), "list", [], "any", false, false, false, 458));
        foreach ($context['_seq'] as $context["i"] => $context["sh"]) {
            // line 459
            echo "                    <tr>
                        <td>";
            // line 460
            echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, $context["sh"], "status", [], "any", false, false, false, 460)], 460, $context, $this->getSourceContext());
            echo "</td>
                        <td><div style=\"overflow: auto; width: 470px; max-height: 50px;\">";
            // line 461
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["sh"], "notes", [], "any", false, false, false, 461), "html", null, true);
            echo "</div></td>
                        <td>";
            // line 462
            echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, $context["sh"], "created_at", [], "any", false, false, false, 462), "Y-m-d H:i"), "html", null, true);
            echo "</td>
                        <td>
                            <a class=\"bb-button btn14 bb-rm-tr api-link\" data-api-confirm=\"Are you sure?\" data-api-redirect=\"";
            // line 464
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("support");
            echo "\" href=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/order/status_history_delete", ["id" => twig_get_attribute($this->env, $this->source, $context["sh"], "id", [], "any", false, false, false, 464)]);
            echo "\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                        </td>
                    </tr>
                    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['i'], $context['sh'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 468
        echo "                </tbody>
            </table>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-support\">
            <div class=\"help\">
                <h2>";
        // line 474
        echo gettext("Support tickets regarding this order");
        echo "</h2>
            </div>
            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td>ID</td>
                        <td width=\"60%\">";
        // line 480
        echo gettext("Subject");
        echo "</td>
                        <td width=\"15%\">";
        // line 481
        echo gettext("Help desk");
        echo "</td>
                        <td width=\"15%\">";
        // line 482
        echo gettext("Status");
        echo "</td>
                        <td>&nbsp;</td>
                    </tr>
                </thead>

                <tbody>
                    ";
        // line 488
        $context["tickets"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "support_ticket_get_list", [0 => ["per_page" => "20", "order_id" => twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "id", [], "any", false, false, false, 488)]], "method", false, false, false, 488);
        // line 489
        echo "                    ";
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["tickets"] ?? null), "list", [], "any", false, false, false, 489));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["_key"] => $context["ticket"]) {
            // line 490
            echo "                    <tr>
                        <td>";
            // line 491
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "id", [], "any", false, false, false, 491), "html", null, true);
            echo "</td>
                        <td>";
            // line 492
            echo twig_escape_filter($this->env, twig_truncate_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "subject", [], "any", false, false, false, 492), 30), "html", null, true);
            echo "</td>
                        <td>";
            // line 493
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["ticket"], "helpdesk", [], "any", false, false, false, 493), "name", [], "any", false, false, false, 493), "html", null, true);
            echo "</td>
                        <td>";
            // line 494
            echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, $context["ticket"], "status", [], "any", false, false, false, 494)], 494, $context, $this->getSourceContext());
            echo "</td>
                        <td class=\"actions\"><a class=\"bb-button btn14\" href=\"";
            // line 495
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/support/ticket");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "id", [], "any", false, false, false, 495), "html", null, true);
            echo "\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a></td>
                    </tr>
                    ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 498
            echo "                    <tr>
                        <td colspan=\"5\">";
            // line 499
            echo gettext("The list is empty");
            echo "</td>
                    </tr>
                    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['ticket'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 502
        echo "                </tbody>
            </table>
        </div>
    </div>

</div>
";
    }

    // line 509
    public function block_js($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 510
        echo "<script type=\"text/javascript\">
    var susp = {
        showSuspendPopup: function() {
                    \$('#suspend_popup').show();
                    return false;

        },
    suspendOrder: function(url, name) {
        var p = {};
        var inps = document.getElementsByName(name);
        var value = '';
        \$.each(inps, function(index, input){

            if (input.checked) {
                value = input.value;

            }
        });

        p[name] = value;
        bb.get(url, p, function(result) {return bb._afterComplete(\$('#suspend_button'), result);});

        \$('#suspend_popup').hide();
        return false;
    },
    suspenderHide: function() {
        \$('#suspend_popup').hide();
    }
    };
</script>
";
    }

    public function getTemplateName()
    {
        return "mod_order_manage.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  1184 => 510,  1180 => 509,  1170 => 502,  1161 => 499,  1158 => 498,  1148 => 495,  1144 => 494,  1140 => 493,  1136 => 492,  1132 => 491,  1129 => 490,  1123 => 489,  1121 => 488,  1112 => 482,  1108 => 481,  1104 => 480,  1095 => 474,  1087 => 468,  1075 => 464,  1070 => 462,  1066 => 461,  1062 => 460,  1059 => 459,  1054 => 458,  1052 => 457,  1045 => 453,  1041 => 452,  1037 => 451,  1033 => 450,  1025 => 445,  1017 => 439,  1008 => 436,  1005 => 435,  995 => 432,  991 => 431,  983 => 430,  979 => 429,  975 => 428,  971 => 427,  968 => 426,  962 => 425,  960 => 424,  951 => 418,  947 => 417,  943 => 416,  939 => 415,  929 => 408,  923 => 404,  920 => 403,  918 => 402,  915 => 401,  912 => 400,  909 => 399,  906 => 398,  903 => 397,  901 => 396,  894 => 391,  883 => 388,  879 => 387,  871 => 386,  867 => 385,  863 => 384,  859 => 383,  856 => 382,  852 => 381,  843 => 375,  839 => 374,  835 => 373,  831 => 372,  827 => 371,  819 => 366,  810 => 360,  805 => 358,  802 => 357,  793 => 353,  781 => 351,  778 => 350,  761 => 348,  756 => 347,  754 => 346,  749 => 344,  746 => 343,  742 => 342,  737 => 340,  732 => 338,  728 => 337,  722 => 333,  719 => 309,  717 => 308,  711 => 287,  706 => 285,  698 => 280,  693 => 278,  684 => 272,  679 => 270,  670 => 264,  665 => 262,  654 => 256,  649 => 254,  640 => 248,  635 => 246,  626 => 240,  621 => 238,  612 => 232,  607 => 230,  598 => 224,  593 => 222,  584 => 216,  579 => 214,  570 => 208,  565 => 206,  558 => 202,  541 => 188,  538 => 187,  535 => 186,  529 => 184,  527 => 183,  520 => 181,  517 => 180,  511 => 178,  509 => 177,  506 => 176,  500 => 174,  498 => 173,  495 => 172,  485 => 170,  473 => 163,  468 => 160,  457 => 157,  454 => 156,  448 => 155,  446 => 154,  440 => 151,  437 => 150,  426 => 147,  424 => 146,  418 => 144,  415 => 143,  413 => 142,  410 => 141,  404 => 139,  402 => 138,  399 => 137,  397 => 136,  388 => 129,  379 => 125,  374 => 123,  371 => 122,  369 => 121,  366 => 120,  358 => 116,  356 => 115,  351 => 113,  348 => 112,  346 => 111,  340 => 108,  336 => 107,  329 => 103,  325 => 102,  314 => 98,  304 => 97,  292 => 93,  283 => 92,  276 => 88,  272 => 87,  265 => 83,  261 => 82,  257 => 80,  251 => 77,  247 => 76,  240 => 72,  236 => 71,  233 => 70,  231 => 69,  213 => 66,  209 => 65,  198 => 61,  194 => 60,  181 => 56,  177 => 55,  168 => 51,  164 => 50,  154 => 43,  144 => 36,  139 => 35,  133 => 34,  128 => 33,  122 => 32,  118 => 31,  114 => 30,  110 => 29,  103 => 25,  97 => 21,  93 => 20,  86 => 9,  80 => 8,  74 => 7,  71 => 6,  67 => 5,  60 => 3,  56 => 1,  53 => 17,  51 => 16,  49 => 15,  47 => 13,  45 => 2,  38 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}
{% import \"macro_functions.phtml\" as mf %}
{% block meta_title %}{{ order.title }}{% endblock %}

{% block breadcrumbs %}
<ul>
    <li class=\"firstB\"><a href=\"{{ '/'|alink }}\">{% trans 'Home' %}</a></li>
    <li><a href=\"{{ 'order'|alink }}\">{% trans 'Orders' %}</a></li>
    <li class=\"lastB\">{{ order.title }}</li>
</ul>
{% endblock %}

{% set active_menu = 'order' %}

{% set service_partial = \"mod_service\" ~ order.service_type ~ \"_manage.phtml\" %}
{% if order.group_master == 1 %}
    {% set addons = admin.order_addons({\"id\":order.id}) %}
{% endif %}

{% block content %}

<div class=\"widget simpleTabs tabsRight\">

    <div class=\"head\">
        <h5 class=\"iFrames\">{% trans 'Order management' %}</h5>
    </div>

    <ul class=\"tabs\">
        <li><a href=\"#tab-info\">{% trans 'Details' %}</a></li>
        <li><a href=\"#tab-manage\">{% trans 'Edit order' %}</a></li>
        <li><a href=\"#tab-config\">{% trans 'Edit order config' %}</a></li>
        {% if admin.system_template_exists({\"file\":service_partial}) %}<li><a href=\"#tab-service\">{% trans 'Service management' %}</a></li>{% endif %}
        <li><a href=\"#tab-invoices\">{% trans 'Invoices' %}</a></li>
        {% if addons|length > 0 %}<li><a href=\"#tab-addons\">{% trans 'Addons' %}</a></li>{% endif %}
        <li><a href=\"#tab-status\">{% trans 'History' %}</a></li>
        <li><a href=\"#tab-support\">{% trans 'Support' %}</a></li>
    </ul>

    <div class=\"tabs_container\">
        <div class=\"fix\"></div>
        <div class=\"tab_content nopadding\" id=\"tab-info\">
            <div class=\"help\">
                <h2>{{ order.title }}</h2>
            </div>

            <div class=\"block\">
                <table class=\"tableStatic wide\">
                    <tbody>
                    <tr class=\"noborder\">
                        <td><label>{% trans 'Order' %}</label></td>
                        <td>#{{ order.id }} ({{ order.service_type }})</td>
                    </tr>

                    <tr>
                        <td><label>{% trans 'Client' %}</label></td>
                        <td><a href=\"{{ 'client/manage'|alink }}/{{order.client.id}}\">{{ order.client.first_name }} {{ order.client.last_name }}</a></td>
                    </tr>

                    <tr>
                        <td><label>{% trans 'Title' %}</label></td>
                        <td><a href=\"{{ 'product/manage'|alink }}/{{ order.product_id }}\"><strong>{{ order.title }}</strong></a></td>
                    </tr>

                    <tr>
                        <td><label>{% trans 'Payment amount' %}</label></td>
                        <td>{{ mf.currency_format( order.total, order.currency) }} {% if order.period %}{{mf.period_name(order.period)}}{% endif %} {% if order.quantity > 1 %}({{ order.quantity }} x {{ mf.currency_format( order.price, order.currency) }}){% endif %}</td>
                    </tr>

                    {% if order.discount and order.discount > 0%}
                    <tr>
                        <td><label>{% trans 'Order discount' %}</label></td>
                        <td>-{{ mf.currency_format( order.discount, order.currency) }} </td>
                    </tr>

                    <tr>
                        <td><label>{% trans 'Payment amount after discount' %}</label></td>
                        <td>{{ mf.currency_format( order.total - order.discount, order.currency) }} </td>
                    </tr>
                    {% endif %}

                    <tr>
                        <td><label>{% trans 'Order status' %}</label></td>
                        <td>{{mf.status_name(order.status)}}</td>
                    </tr>

                    <tr>
                        <td><label>{% trans 'Order created' %}</label></td>
                        <td>{{ order.created_at|date('l, d F Y') }}</td>
                    </tr>

                    <tr>
                        <td><label>{% trans 'Activated' %}{% if order.activated_at %} {{ order.activated_at|timeago }} ago{% endif %}</label></td>
                        <td>{% if order.activated_at %}{{ order.activated_at|date('l, d F Y')}} {% else %}-{% endif %}</td>
                    </tr>

                    <tr>
                        <td><label>{% trans 'Renewal date' %} {% if order.expires_at %} in {{ order.expires_at|daysleft }} day(s) {% endif %}</label></td>
                        <td>{% if order.expires_at %}{{ order.expires_at|date('l, d F Y')}}{% else %}-{% endif %}</td>
                    </tr>

                    <tr>
                        <td><label>{% trans 'Order notes' %}</label></td>
                        <td>{{ order.notes|bbmd }}</td>
                    </tr>

                    <tr>
                        <td><label>{% trans 'Order group ID' %}</label></td>
                        <td>{{ order.group_id|default('-') }}</td>
                    </tr>

                    {% if order.promo_id %}
                    <tr>
                        <td><label>{% trans 'Order promo code' %}</label></td>
                        <td>
                            {% set promo = admin.product_promo_get({\"id\":order.promo_id}) %}
                            {{ promo.code }}
                        </td>
                    </tr>
                    {% endif %}
                    
                    {% if order.active_tickets > 0 %}
                    <tr>
                        <td><label>{% trans 'Active support tickets' %}</label></td>
                        <td>
                            <div class=\"num\"><a href=\"{{ 'support'|alink({'status' : 'open', 'order_id' : order.id}) }}\" class=\"redNum\">{{ order.active_tickets }}</a></div>
                        </td>
                    </tr>
                    {% endif %}
                    </tbody>
                    
                    <tfoot>
                        <tr>
                            <td colspan=\"2\">
                                
                                <div class=\"aligncenter\">
                                    {% set order_actions %}
                                    
                                    {% if order.status == 'pending_setup' or order.status == 'failed_setup' %}
                                    <a href=\"{{ 'api/admin/order/activate'|link({'id' : order.id}) }}\" title=\"\" data-api-confirm=\"Are you sure?\" class=\"btn55 mr10 api-link\" data-api-reload=\"Order activated\"><img src=\"images/icons/middlenav/play2.png\" alt=\"\"><span>Activate</span></a>
                                    {% endif %}
                                    
                                    {% if order.status == 'active' %}
                                    {% set params = admin.extension_config_get({\"ext\":\"mod_order\"}) %}
                                    <a href=\"{{ 'api/admin/order/renew'|link({'id' : order.id}) }}\" title=\"\" data-api-confirm=\"Are you sure?\" class=\"btn55 mr10 api-link\" data-api-reload=\"Order renewed\"><img src=\"images/icons/middlenav/play2.png\" alt=\"\"><span>Renew</span></a>

                                    {% if params.suspend_reason_list|trim == null %}
                                    <a href=\"{{ 'api/admin/order/suspend'|link({'id' : order.id}) }}\" title=\"\" class=\"btn55 mr10 api-link\" data-api-prompt-key=\"reason\" data-api-prompt=\"1\" data-api-prompt-text=\"{% trans 'Reason of suspension' %}\" data-api-prompt-title=\"{% trans 'Suspension reason' %}\" data-api-reload=\"Order suspended\"><img src=\"images/icons/middlenav/stop.png\" alt=\"\"><span>Suspend</span></a>

                                    {% else %}
                                    <div id=\"suspend_popup\" style=\"position: fixed; z-index: 99999; padding: 5px; margin: 0px; min-width: 310px; max-width: 310px; top: 30%; left: 45%; display: none;\">
                                        <h5 id=\"suspend_popup_title\">{% trans 'Suspension reason' %}</h5>
                                        <div id=\"suspend_popup_content\" class=\"confirm\">
                                            <div id=\"suspend_popup_message\">
                                                <div>{% trans 'Reason of suspension' %}
                                                    {% for reason in params.suspend_reason_list|trim|split(\"\\r\\n\") %}
                                                    <div class=\"item\">
                                                        <input type=\"radio\" value=\"{{reason}}\" name=\"reason\"/> <label>{{reason}}</label>
                                                    </div>
                                                    {% endfor %}
                                                </div>
                                            </div>
                                            <div id=\"suspend_popup_panel\">
                                                <input type=\"button\" class=\"blueBtn\" value=\"&nbsp;{% trans 'Suspend' %}&nbsp;\" id=\"popup_ok\" onclick=\"return susp.suspendOrder('/api/admin/order/suspend?id={{order.id}}', 'reason');\"/>
                                                <input type=\"button\" class=\"basicBtn\" value=\"&nbsp;Cancel&nbsp;\" id=\"popup_cancel\" onclick=\"return susp.suspenderHide();\"/>
                                            </div>
                                        </div>
                                    </div>
                                    <a href=\"#\" title=\"\" id=\"suspend_button\" onclick=\"return susp.showSuspendPopup()\" data-api-reload=\"Order suspended\" class=\"btn55 mr10\"><img src=\"images/icons/middlenav/stop.png\" alt=\"\"><span>Suspend</span></a>
                                    {% endif %}
                                    <a href=\"{{ 'api/admin/order/cancel'|link({'id' : order.id}) }}\" title=\"\" class=\"btn55 mr10 api-link\" data-api-prompt-key=\"reason\" data-api-prompt=\"1\" data-api-prompt-text=\"{% trans 'Reason of cancelation' %}\" data-api-prompt-title=\"{% trans 'Cancelation reason' %}\" data-api-reload=\"Order canceled\"><img src=\"images/icons/middlenav/close.png\" alt=\"\"><span>Cancel</span></a>
                                    {% endif %}
                                    
                                    {% if order.status == 'suspended' %}
                                    <a href=\"{{ 'api/admin/order/unsuspend'|link({'id' : order.id}) }}\" title=\"\" data-api-confirm=\"Are you sure?\" class=\"btn55 mr10 api-link\" data-api-reload=\"Order activated\"><img src=\"images/icons/middlenav/play2.png\" alt=\"\"><span>Unsuspend</span></a>
                                    {% endif %}
                                    
                                    {% if order.status == 'canceled' %}
                                    <a href=\"{{ 'api/admin/order/uncancel'|link({'id' : order.id}) }}\" title=\"\" data-api-confirm=\"Are you sure?\" class=\"btn55 mr10 api-link\" data-api-reload=\"Order activated\"><img src=\"images/icons/middlenav/play2.png\" alt=\"\"><span>Activate</span></a>
                                    {% endif %}
                                    
                                    <a href=\"{{ 'api/admin/order/delete'|link({'id' : order.id}) }}\" title=\"\" data-api-confirm=\"Are you sure?\" data-api-redirect=\"{{ 'order'|alink }}\" class=\"btn55 mr10 api-link\"><img src=\"images/icons/middlenav/trash.png\" alt=\"\"><span>Delete</span></a>

                                    {% if not order.unpaid_invoice_id %}
                                    <a href=\"{{ 'api/admin/invoice/renewal_invoice'|link({'id' : order.id}) }}\" title=\"\" data-api-confirm=\"Are you sure?\" class=\"btn55 mr10 api-link\" data-api-reload=\"1\"><img src=\"images/icons/middlenav/create.png\" alt=\"\"><span>Issue invoice</span></a>
                                    {% endif %}
                                    {% endset %}
                                    
                                    {{ order_actions }}
                                </div>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

        </div>

        <div class=\"tab_content nopadding\" id=\"tab-manage\">
            <div class=\"help\">
                <h2>Order management</h2>
            </div>
            <form method=\"post\" action=\"{{ 'api/admin/order/update'|link }}\" class=\"mainForm api-form\" data-api-reload=\"1\">
                <fieldset>

                    <div class=\"rowElem noborder\">
                        <label>{% trans 'Title' %}</label>
                        <div class=\"formRight noborder\">
                            <input type=\"text\" name=\"title\" value=\"{{order.title}}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Changes status without performing action' %}</label>
                        <div class=\"formRight noborder\">
                            {{ mf.selectbox('status', admin.order_get_status_pairs, order.status, 0, 'Select status') }}
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <div class=\"rowElem\">
                        <label>{% trans 'Invoice option' %}</label>
                        <div class=\"formRight noborder\">
                            {{ mf.selectbox('invoice_option', admin.order_get_invoice_options, order.invoice_option) }}
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Price' %}</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"price\" value=\"{{order.price}}\" required=\"required\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Reason' %}</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"reason\" value=\"{{order.reason}}\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Period' %}</label>
                        <div class=\"formRight\">
                            {{ mf.selectbox('period', guest.system_periods, order.period, 0, 'Not recurrent') }}
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Expires at' %}</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"expires_at\" value=\"{% if order.expires_at %}{{order.expires_at|date('Y-m-d')}}{% endif %}\" class=\"datepicker\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <div class=\"rowElem\">
                        <label>{% trans 'Created at' %}</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"created_at\" value=\"{{order.created_at|date('Y-m-d')}}\" required=\"required\" class=\"datepicker\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <div class=\"rowElem\">
                        <label>{% trans 'Activated at' %}</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"activated_at\" value=\"{{order.activated_at|date('Y-m-d')}}\" required=\"required\" class=\"datepicker\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <div class=\"rowElem\">
                        <label>{% trans 'Notes' %}</label>
                        <div class=\"formRight\">
                            <textarea name=\"notes\" cols=\"5\" rows=\"5\">{{ order.notes }}</textarea>
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                     <input type=\"submit\" value=\"{% trans 'Update' %}\" class=\"greyishBtn submitForm\" />
                </fieldset>
                <input type=\"hidden\" name=\"id\" value=\"{{ order.id }}\">
            </form>
            
            {#
            <form method=\"post\" action=\"{{ 'api/admin/order/update'|link }}\" class=\"mainForm api-form\" data-api-reload=\"1\">
                <fieldset>
                    <legend>Order promotion code</legend>
                    
                    <div class=\"rowElem noborder\">
                        <label>{% trans 'Promo code' %}</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"promo_id\" value=\"{{order.promo_id}}\" required=\"required\" />
                        </div>
                        <div class=\"fix\"></div>
                    </div>

                    <input type=\"submit\" value=\"{% trans 'Update promo' %}\" class=\"greyishBtn submitForm\" />
                </fieldset>
                <input type=\"hidden\" name=\"id\" value=\"{{ order.id }}\">
            </form>
            #}
            {# order_actions #}
            
            {# if order.status == 'pending_setup' or order.status == 'failed_setup' %}
            <div class=\"help\">
                <h2>Order parameters</h2>
            </div>

            <form method=\"post\" action=\"{{ 'api/admin/order/update_config'|link }}\" class=\"mainForm save api-form\" data-api-msg=\"Order config updated\">
                <fieldset>

                    {% for name, value in order.config %}
                    <div class=\"rowElem\">
                        <label class=\"topLabel\">{{ name }}:</label>
                            <div class=\"formBottom\">
                                <textarea rows=\"2\" cols=\"\" name=\"config[{{ name }}]\">{{ value }}</textarea>
                            </div>
                        <div class=\"fix\"></div>
                    </div>
                    {% endfor %}

                    <input type=\"submit\" value=\"{% trans 'Update' %}\" class=\"greyishBtn submitForm\" />
                    <input type=\"hidden\" name=\"id\" value=\"{{ order.id }}\"/>
                </fieldset>
            </form>
            {% endif #}
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-config\">
            <div class=\"help\">
                <h2>{% trans 'Order config management' %}</h2>
                <h6>{% trans 'Please be cautious and make sure you know what you are doing when editing order config! BoxBilling relies on these parameters and you might get unexpected results if you change some of them' %}</h6>
            </div>
            <form method=\"post\" action=\"{{ 'api/admin/order/update_config'|link }}\" class=\"mainForm api-form\" data-api-reload=\"1\">
                <fieldset>
                    {% for key, config in order.config %}
                    <div class=\"rowElem noborder\">
                        <label>{{ key }}</label>
                        <div class=\"formRight noborder\">
                            {% if config is iterable %}
                                {% for key2, config2 in config %}
                                    <input type=\"text\" name=\"config[{{key}}][{{key2}}]\" value=\"{{ config2 }}\" {% if config2 is null %} placeholder=\"null\" {% endif %}/>
                                {% endfor %}
                            {% else %}
                                <input type=\"text\" name=\"config[{{key}}]\" value=\"{{ config }}\" {% if config is null %} placeholder=\"null\" {% endif %}/>
                            {% endif %}
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    {% endfor %}

                     <input type=\"submit\" value=\"{% trans 'Update' %}\" class=\"greyishBtn submitForm\" />
                </fieldset>
                <input type=\"hidden\" name=\"id\" value=\"{{ order.id }}\">
            </form>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-addons\">
            <div class=\"help\">
                <h2>{% trans 'Addons you have ordered with this service' %}</h2>
            </div>
            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td>{% trans 'Product/Service' %}</td>
                        <td>{% trans 'Price' %}</td>
                        <td>{% trans 'Billing Cycle' %}</td>
                        <td>{% trans 'Next due date' %}</td>
                        <td>{% trans 'Status' %}</td>
                        <td>&nbsp</td>
                    </tr>
                </thead>

                <tbody>
                {% for i, addon in addons %}
                <tr>
                    <td>{{addon.title}}</td>
                    <td>{{ mf.currency_format( addon.total, addon.currency) }}</td>
                    <td>{{ mf.period_name(addon.period) }}</td>
                    <td>{% if addon.expires_at %}{{addon.expires_at|date('l, d F Y')}}{% else %}-{% endif %}</td>
                    <td>{{ mf.status_name(addon.status) }}</td>
                    <td class=\"actions\"><a class=\"bb-button btn14\" href=\"{{ '/order/manage'|alink }}/{{addon.id}}\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a></td>
                </tr>
                {% endfor %}
                </tbody>
            </table>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-service\">
            {% if guest.system_template_exists({\"file\":service_partial}) %}
                {% set service = admin.order_service({\"id\":order.id}) %}
                {% include service_partial with {'order' : order, 'service':service} %}
            {% elseif order.form_id and guest.extension_is_on({\"mod\":\"formbuilder\"}) %}
                {% include 'mod_formbuilder_manage.phtml' with order %}
            {% else %}
                {# trans 'Order form was not found' #}
            {% endif %}
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-invoices\">
            <div class=\"help\">
                <h2>{% trans 'Order invoices' %}</h2>
            </div>

            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td>ID</td>
                        <td width=\"15%\">{% trans 'Amount' %}</td>
                        <td width=\"30%\">{% trans 'Issued at' %}</td>
                        <td width=\"30%\">{% trans 'Paid at' %}</td>
                        <td width=\"15%\">{% trans 'Status' %}</td>
                        <td>&nbsp;</td>
                    </tr>
                </thead>
                
                <tbody>
                    {% set invoices = admin.invoice_get_list({\"per_page\":50, \"order_id\":order.id}) %}
                    {% for invoice in invoices.list %}
                    <tr>
                        <td>{{invoice.id}}</td>
                        <td>{{ mf.currency_format( invoice.total, invoice.currency, 1) }}</td>
                        <td>{{ invoice.created_at|date('Y-m-d') }}</td>
                        <td>{% if invoice.paid_at %}{{ invoice.paid_at|date('Y-m-d') }}{% else %}-{% endif %}</td>
                        <td>{{ mf.status_name(invoice.status) }}</td>
                        <td class=\"actions\"><a class=\"bb-button btn14\" href=\"{{ '/invoice/manage'|alink }}/{{invoice.id}}\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a></td>
                    </tr>
                    {% else %}
                    <tr>
                        <td colspan=\"5\">{% trans 'The list is empty' %}</td>
                    </tr>
                    {% endfor %}
                </tbody>
            </table>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-status\">
            <div class=\"help\">
                <h2>{% trans 'Order status change history' %}</h2>
            </div>
            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td>{% trans 'Status' %}</td>
                        <td>{% trans 'Note' %}</td>
                        <td>{% trans 'Date' %}</td>
                        <td>{% trans 'Actions' %}</td>
                    </tr>
                </thead>
                <tbody>
                    {% set statuses = admin.order_status_history_get_list({\"per_page\":50, \"id\":order.id}) %}
                    {% for i,sh in statuses.list %}
                    <tr>
                        <td>{{ mf.status_name(sh.status) }}</td>
                        <td><div style=\"overflow: auto; width: 470px; max-height: 50px;\">{{ sh.notes }}</div></td>
                        <td>{{ sh.created_at|date('Y-m-d H:i') }}</td>
                        <td>
                            <a class=\"bb-button btn14 bb-rm-tr api-link\" data-api-confirm=\"Are you sure?\" data-api-redirect=\"{{ 'support'|alink }}\" href=\"{{ 'api/admin/order/status_history_delete'|link({'id' : sh.id}) }}\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                        </td>
                    </tr>
                    {% endfor %}
                </tbody>
            </table>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-support\">
            <div class=\"help\">
                <h2>{% trans 'Support tickets regarding this order' %}</h2>
            </div>
            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td>ID</td>
                        <td width=\"60%\">{% trans 'Subject' %}</td>
                        <td width=\"15%\">{% trans 'Help desk' %}</td>
                        <td width=\"15%\">{% trans 'Status' %}</td>
                        <td>&nbsp;</td>
                    </tr>
                </thead>

                <tbody>
                    {% set tickets = admin.support_ticket_get_list({\"per_page\":\"20\", \"order_id\":order.id}) %}
                    {% for ticket in tickets.list %}
                    <tr>
                        <td>{{ ticket.id }}</td>
                        <td>{{ ticket.subject|truncate(30) }}</td>
                        <td>{{ ticket.helpdesk.name }}</td>
                        <td>{{ mf.status_name(ticket.status) }}</td>
                        <td class=\"actions\"><a class=\"bb-button btn14\" href=\"{{ '/support/ticket'|alink }}/{{ticket.id}}\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a></td>
                    </tr>
                    {% else %}
                    <tr>
                        <td colspan=\"5\">{% trans 'The list is empty' %}</td>
                    </tr>
                    {% endfor %}
                </tbody>
            </table>
        </div>
    </div>

</div>
{% endblock %}
{% block js %}
<script type=\"text/javascript\">
    var susp = {
        showSuspendPopup: function() {
                    \$('#suspend_popup').show();
                    return false;

        },
    suspendOrder: function(url, name) {
        var p = {};
        var inps = document.getElementsByName(name);
        var value = '';
        \$.each(inps, function(index, input){

            if (input.checked) {
                value = input.value;

            }
        });

        p[name] = value;
        bb.get(url, p, function(result) {return bb._afterComplete(\$('#suspend_button'), result);});

        \$('#suspend_popup').hide();
        return false;
    },
    suspenderHide: function() {
        \$('#suspend_popup').hide();
    }
    };
</script>
{% endblock %}
", "mod_order_manage.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-themes/admin_default/html/mod_order_manage.phtml");
    }
}
