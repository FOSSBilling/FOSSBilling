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

/* mod_index_dashboard.phtml */
class __TwigTemplate_1175f892d2db3986dca35874ec9a127627640528d9a0f61957e0e8617aa88578 extends \Twig\Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->blocks = [
            'meta_title' => [$this, 'block_meta_title'],
            'page_header' => [$this, 'block_page_header'],
            'breadcrumbs' => [$this, 'block_breadcrumbs'],
            'content' => [$this, 'block_content'],
        ];
    }

    protected function doGetParent(array $context)
    {
        // line 1
        return $this->loadTemplate(((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ajax", [], "any", false, false, false, 1)) ? ("layout_blank.phtml") : ("layout_default.phtml")), "mod_index_dashboard.phtml", 1);
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 15
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_index_dashboard.phtml", 15)->unwrap();
        // line 1
        $this->getParent($context)->display($context, array_merge($this->blocks, $blocks));
    }

    // line 3
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo gettext("Client Area");
    }

    // line 4
    public function block_page_header($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo gettext("Dashboard");
    }

    // line 6
    public function block_breadcrumbs($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 7
        echo "    ";
        if ( !twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "hide_dashboard_breadcrumb", [], "any", false, false, false, 7)) {
            // line 8
            echo "        <ul class=\"breadcrumb\">
            <li><a href=\"";
            // line 9
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/");
            echo "\">";
            echo gettext("Home");
            echo "</a> <span class=\"divider\">/</span></li>
            <li class=\"active\">";
            // line 10
            echo gettext("Dashboard");
            echo "</li>
        </ul>
    ";
        }
    }

    // line 17
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 18
        echo "
";
        // line 19
        if (twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "showcase_enabled", [], "any", false, false, false, 19)) {
            // line 20
            echo "<div class=\"hero-unit\">
    <h1>";
            // line 21
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "showcase_heading", [], "any", false, false, false, 21), "html", null, true);
            echo "</h1>
    <p>";
            // line 22
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "showcase_text", [], "any", false, false, false, 22), "html", null, true);
            echo "</p>
    <p><a class=\"btn btn-alt btn-primary btn-large\" href=\"";
            // line 23
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "showcase_button_url", [], "any", false, false, false, 23), "html", null, true);
            echo "\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["settings"] ?? null), "showcase_button_title", [], "any", false, false, false, 23), "html", null, true);
            echo "</a></p>
</div>
";
        }
        // line 26
        echo "
";
        // line 27
        if (($context["client"] ?? null)) {
            // line 28
            echo "
";
            // line 29
            $context["tickets"] = twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "support_ticket_get_list", [0 => ["status" => "on_hold"]], "method", false, false, false, 29);
            // line 30
            if ((twig_get_attribute($this->env, $this->source, ($context["tickets"] ?? null), "total", [], "any", false, false, false, 30) > 0)) {
                // line 31
                echo "<div class=\"row\">
<article class=\"span12 data-block\">
<div class=\"data-container\">
<header>
    <h2>";
                // line 35
                echo gettext("Tickets waiting your reply");
                echo "</h2>
</header>
<section id=\"slimScroll1\">
    <table class=\"table table-striped table-bordered table-condensed table-hover\">
        <thead>
        <tr>
            <th>";
                // line 41
                echo gettext("Id");
                echo "</th>
            <th>";
                // line 42
                echo gettext("Subject");
                echo "</th>
            <th>";
                // line 43
                echo gettext("Help desk");
                echo "</th>
            <th>";
                // line 44
                echo gettext("Status");
                echo "</th>
            <th>";
                // line 45
                echo gettext("Submitted");
                echo "</th>
            <th>";
                // line 46
                echo gettext("Actions");
                echo "</th>
        </tr>
        </thead>
        <tbody>
        ";
                // line 50
                $context['_parent'] = $context;
                $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["tickets"] ?? null), "list", [], "any", false, false, false, 50));
                $context['_iterated'] = false;
                foreach ($context['_seq'] as $context["i"] => $context["ticket"]) {
                    // line 51
                    echo "
        <tr class=\"";
                    // line 52
                    echo twig_escape_filter($this->env, twig_cycle([0 => "odd", 1 => "even"], $context["i"]), "html", null, true);
                    echo "\">
            <td>";
                    // line 53
                    echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "id", [], "any", false, false, false, 53), "html", null, true);
                    echo "</td>
            <td><a href=\"";
                    // line 54
                    echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("support/ticket");
                    echo "/";
                    echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "id", [], "any", false, false, false, 54), "html", null, true);
                    echo "\">";
                    echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "subject", [], "any", false, false, false, 54), "html", null, true);
                    echo "</a></td>
            <td>";
                    // line 55
                    echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["ticket"], "helpdesk", [], "any", false, false, false, 55), "name", [], "any", false, false, false, 55), "html", null, true);
                    echo "</td>
            <td><span class=\"label\">";
                    // line 56
                    echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, $context["ticket"], "status", [], "any", false, false, false, 56)], 56, $context, $this->getSourceContext());
                    echo "</span></td>
            <td>";
                    // line 57
                    echo twig_escape_filter($this->env, $this->extensions['Box_TwigExtensions']->twig_bb_date(twig_get_attribute($this->env, $this->source, $context["ticket"], "created_at", [], "any", false, false, false, 57)), "html", null, true);
                    echo "</td>
            <td><a href=\"";
                    // line 58
                    echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("support/ticket");
                    echo "/";
                    echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["ticket"], "id", [], "any", false, false, false, 58), "html", null, true);
                    echo "\" class=\"btn btn-small btn-inverse\">";
                    echo gettext("Reply");
                    echo "</a></td>
        </tr>

        ";
                    $context['_iterated'] = true;
                }
                if (!$context['_iterated']) {
                    // line 62
                    echo "
        <tr>
            <td colspan=\"5\">";
                    // line 64
                    echo gettext("The list is empty");
                    echo "</td>
        </tr>

        ";
                }
                $_parent = $context['_parent'];
                unset($context['_seq'], $context['_iterated'], $context['i'], $context['ticket'], $context['_parent'], $context['loop']);
                $context = array_intersect_key($context, $_parent) + $_parent;
                // line 68
                echo "
        </tbody>
    </table>
</section>
</div>
</article>
</div>
";
            }
            // line 76
            echo "
<div class=\"row\">
";
            // line 78
            $context["profile"] = twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "client_get", [], "any", false, false, false, 78);
            // line 79
            echo "<article class=\"span6 data-block decent\">
    <div class=\"data-container\">
        <header>
            <h2>";
            // line 82
            echo gettext("Profile");
            echo "</h2>
            <ul class=\"data-header-actions\">
                <li>
                    <a class=\"btn btn-alt btn-inverse\" href=\"";
            // line 85
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("client/me");
            echo "\">";
            echo gettext("Update");
            echo "</a>
                </li>
            </ul>
        </header>
        <section>
            <dl class=\"dl-horizontal\">
                <dt>";
            // line 91
            echo gettext("ID");
            echo "</dt>
                <dd>#";
            // line 92
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "id", [], "any", false, false, false, 92), "html", null, true);
            echo "</dd>
                <dt>";
            // line 93
            echo gettext("Email");
            echo "</dt>
                <dd>";
            // line 94
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "email", [], "any", false, false, false, 94), "html", null, true);
            echo "</dd>
                <dt>";
            // line 95
            echo gettext("Balance");
            echo "</dt>
                <dd>";
            // line 96
            echo twig_money($this->env, twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "balance", [], "any", false, false, false, 96), twig_get_attribute($this->env, $this->source, ($context["profile"] ?? null), "currency", [], "any", false, false, false, 96));
            echo "</dd>
            </dl>
        </section>
    </div>
</article>

<article class=\"span6 data-block decent\">
    <div class=\"data-container\">
        <header>
            <h2>";
            // line 105
            echo gettext("Invoices");
            echo "</h2>
            <ul class=\"data-header-actions\">
                <li>
                    <a class=\"btn btn-alt btn-inverse\" href=\"";
            // line 108
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("invoice");
            echo "\">";
            echo gettext("All Invoices");
            echo "</a>
                </li>
            </ul>
        </header>
        <section>
            <dl class=\"dl-horizontal\">
                <dt>";
            // line 114
            echo gettext("Total");
            echo "</dt>
                    <dd>";
            // line 115
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "invoice_get_list", [], "method", false, false, false, 115), "total", [], "any", false, false, false, 115), "html", null, true);
            echo "</dd>
                <dt>";
            // line 116
            echo gettext("Paid");
            echo "</dt>
                    <dd>";
            // line 117
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "invoice_get_list", [0 => ["status" => "paid"]], "method", false, false, false, 117), "total", [], "any", false, false, false, 117), "html", null, true);
            echo "</dd>
                <dt>";
            // line 118
            echo gettext("Unpaid");
            echo "</dt>
                    <dd>";
            // line 119
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "invoice_get_list", [0 => ["status" => "unpaid"]], "method", false, false, false, 119), "total", [], "any", false, false, false, 119), "html", null, true);
            echo "</dd>
            </dl>
        </section>
    </div>
</article>
</div>

<div class=\"row\">

<article class=\"span6 data-block decent\">
    <div class=\"data-container\">
        <header>
            <h2>";
            // line 131
            echo gettext("Orders");
            echo "</h2>
            <ul class=\"data-header-actions\">
                <li>
                    <a class=\"btn btn-alt btn-info order-button\" href=\"";
            // line 134
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("order");
            echo "\">";
            echo gettext("New order");
            echo "</a>
                    <a class=\"btn btn-alt btn-inverse\" href=\"";
            // line 135
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("order/service");
            echo "\">";
            echo gettext("All orders");
            echo "</a>
                </li>
            </ul>
        </header>
        <section>
            <dl class=\"dl-horizontal\">
                <dt>";
            // line 141
            echo gettext("Total");
            echo "</dt>
                <dd>";
            // line 142
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "order_get_list", [0 => ["hide_addons" => 1]], "method", false, false, false, 142), "total", [], "any", false, false, false, 142), "html", null, true);
            echo "</dd>
                <dt>";
            // line 143
            echo gettext("Active");
            echo "</dt>
                <dd>";
            // line 144
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "order_get_list", [0 => ["hide_addons" => 1, "status" => "active"]], "method", false, false, false, 144), "total", [], "any", false, false, false, 144), "html", null, true);
            echo "</dd>
                <dt>";
            // line 145
            echo gettext("Expiring");
            echo "</dt>
                <dd>";
            // line 146
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "order_get_list", [0 => ["expiring" => 1]], "method", false, false, false, 146), "total", [], "any", false, false, false, 146), "html", null, true);
            echo "</dd>
            </dl>
        </section>
    </div>
</article>

<article class=\"span6 data-block decent\">
    <div class=\"data-container\">
        <header>
            <h2>";
            // line 155
            echo gettext("Tickets");
            echo "</h2>
            <ul class=\"data-header-actions\">
                <li>
                    <a class=\"btn btn-alt btn-info\" href=\"";
            // line 158
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("support", ["ticket" => 1]);
            echo "\">";
            echo gettext("New ticket");
            echo "</a>
                    <a class=\"btn btn-alt btn-inverse\" href=\"";
            // line 159
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("support");
            echo "\">";
            echo gettext("All tickets");
            echo "</a>
                </li>
            </ul>
        </header>
        <section>
            <dl class=\"dl-horizontal\">
                <dt>";
            // line 165
            echo gettext("Total");
            echo "</dt>
                <dd>";
            // line 166
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "support_ticket_get_list", [], "method", false, false, false, 166), "total", [], "any", false, false, false, 166), "html", null, true);
            echo "</dd>
                <dt>";
            // line 167
            echo gettext("Open");
            echo "</dt>
                <dd>";
            // line 168
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "support_ticket_get_list", [0 => ["status" => "open"]], "method", false, false, false, 168), "total", [], "any", false, false, false, 168), "html", null, true);
            echo "</dd>
                <dt>";
            // line 169
            echo gettext("On Hold");
            echo "</dt>
                <dd>";
            // line 170
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "support_ticket_get_list", [0 => ["status" => "on_hold"]], "method", false, false, false, 170), "total", [], "any", false, false, false, 170), "html", null, true);
            echo "</dd>
            </dl>
        </section>
    </div>
</article>
</div>

<div class=\"row\">

<article class=\"span6 data-block decent\">
    <div class=\"data-container\">
        <header>
            <h2>";
            // line 182
            echo gettext("Recent orders");
            echo "</h2>
        </header>
        <section>
            <table class=\"table table-striped table-bordered table-condensed table-hover\">
                <tbody>
                ";
            // line 187
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "order_get_list", [0 => ["per_page" => 5, "page" => twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 187), "hide_addons" => 1]], "method", false, false, false, 187), "list", [], "any", false, false, false, 187));
            $context['_iterated'] = false;
            foreach ($context['_seq'] as $context["i"] => $context["order"]) {
                // line 188
                echo "                <tr class=\"";
                echo twig_escape_filter($this->env, twig_cycle([0 => "odd", 1 => "even"], $context["i"]), "html", null, true);
                echo "\">
                    <td><a href=\"";
                // line 189
                echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("order/service/manage");
                echo "/";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["order"], "id", [], "any", false, false, false, 189), "html", null, true);
                echo "\">";
                echo twig_escape_filter($this->env, twig_truncate_filter($this->env, twig_get_attribute($this->env, $this->source, $context["order"], "title", [], "any", false, false, false, 189), 30), "html", null, true);
                echo "</a></td>
                    <td><span class=\"label ";
                // line 190
                if ((twig_get_attribute($this->env, $this->source, $context["order"], "status", [], "any", false, false, false, 190) == "active")) {
                    echo "label-success";
                } elseif ((twig_get_attribute($this->env, $this->source, $context["order"], "status", [], "any", false, false, false, 190) == "pending_setup")) {
                    echo "label-warning";
                }
                echo "\">";
                echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, $context["order"], "status", [], "any", false, false, false, 190)], 190, $context, $this->getSourceContext());
                echo "</span></td>
                </tr>
                ";
                $context['_iterated'] = true;
            }
            if (!$context['_iterated']) {
                // line 193
                echo "                <tr>
                    <td colspan=\"3\">";
                // line 194
                echo gettext("The list is empty");
                echo "</td>
                </tr>
                ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['i'], $context['order'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 197
            echo "                </tbody>
            </table>
        </section>
    </div>
</article>

<article class=\"span6 data-block decent\">
    <div class=\"data-container\">
        <header>
            <h2>";
            // line 206
            echo gettext("Recent emails");
            echo "</h2>
        </header>
        <section>
            <table class=\"table table-striped table-bordered table-condensed table-hover\">
                <tbody>
                ";
            // line 211
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["client"] ?? null), "email_get_list", [0 => ["per_page" => 5]], "method", false, false, false, 211), "list", [], "any", false, false, false, 211));
            $context['_iterated'] = false;
            foreach ($context['_seq'] as $context["i"] => $context["email"]) {
                // line 212
                echo "                <tr class=\"";
                echo twig_escape_filter($this->env, twig_cycle([0 => "odd", 1 => "even"], $context["i"]), "html", null, true);
                echo "\">
                    <td><a href=\"";
                // line 213
                echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("email");
                echo "/";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["email"], "id", [], "any", false, false, false, 213), "html", null, true);
                echo "\">";
                echo twig_escape_filter($this->env, twig_truncate_filter($this->env, twig_get_attribute($this->env, $this->source, $context["email"], "subject", [], "any", false, false, false, 213), 30), "html", null, true);
                echo "</a></td>
                    <td title=\"";
                // line 214
                echo twig_escape_filter($this->env, $this->extensions['Box_TwigExtensions']->twig_bb_date(twig_get_attribute($this->env, $this->source, $context["email"], "created_at", [], "any", false, false, false, 214)), "html", null, true);
                echo "\">";
                echo twig_escape_filter($this->env, twig_timeago_filter(twig_get_attribute($this->env, $this->source, $context["email"], "created_at", [], "any", false, false, false, 214)), "html", null, true);
                echo " ";
                echo gettext("ago");
                echo "</td>
                </tr>
                ";
                $context['_iterated'] = true;
            }
            if (!$context['_iterated']) {
                // line 217
                echo "                <tr>
                    <td colspan=\"2\">";
                // line 218
                echo gettext("The list is empty");
                echo "</td>
                </tr>
                ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['i'], $context['email'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 221
            echo "                </tbody>
            </table>
        </section>
    </div>
</article>

</div>

";
        }
    }

    public function getTemplateName()
    {
        return "mod_index_dashboard.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  582 => 221,  573 => 218,  570 => 217,  558 => 214,  550 => 213,  545 => 212,  540 => 211,  532 => 206,  521 => 197,  512 => 194,  509 => 193,  495 => 190,  487 => 189,  482 => 188,  477 => 187,  469 => 182,  454 => 170,  450 => 169,  446 => 168,  442 => 167,  438 => 166,  434 => 165,  423 => 159,  417 => 158,  411 => 155,  399 => 146,  395 => 145,  391 => 144,  387 => 143,  383 => 142,  379 => 141,  368 => 135,  362 => 134,  356 => 131,  341 => 119,  337 => 118,  333 => 117,  329 => 116,  325 => 115,  321 => 114,  310 => 108,  304 => 105,  292 => 96,  288 => 95,  284 => 94,  280 => 93,  276 => 92,  272 => 91,  261 => 85,  255 => 82,  250 => 79,  248 => 78,  244 => 76,  234 => 68,  224 => 64,  220 => 62,  207 => 58,  203 => 57,  199 => 56,  195 => 55,  187 => 54,  183 => 53,  179 => 52,  176 => 51,  171 => 50,  164 => 46,  160 => 45,  156 => 44,  152 => 43,  148 => 42,  144 => 41,  135 => 35,  129 => 31,  127 => 30,  125 => 29,  122 => 28,  120 => 27,  117 => 26,  109 => 23,  105 => 22,  101 => 21,  98 => 20,  96 => 19,  93 => 18,  89 => 17,  81 => 10,  75 => 9,  72 => 8,  69 => 7,  65 => 6,  58 => 4,  51 => 3,  47 => 1,  45 => 15,  38 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}

{% block meta_title %}{% trans 'Client Area' %}{% endblock %}
{% block page_header %}{% trans 'Dashboard' %}{% endblock %}

{% block breadcrumbs %}
    {% if not settings.hide_dashboard_breadcrumb %}
        <ul class=\"breadcrumb\">
            <li><a href=\"{{ '/'|link }}\">{% trans 'Home' %}</a> <span class=\"divider\">/</span></li>
            <li class=\"active\">{% trans 'Dashboard' %}</li>
        </ul>
    {% endif %}
{% endblock %}

{% import \"macro_functions.phtml\" as mf %}

{% block content %}

{% if settings.showcase_enabled %}
<div class=\"hero-unit\">
    <h1>{{ settings.showcase_heading }}</h1>
    <p>{{ settings.showcase_text }}</p>
    <p><a class=\"btn btn-alt btn-primary btn-large\" href=\"{{ settings.showcase_button_url }}\">{{ settings.showcase_button_title }}</a></p>
</div>
{% endif %}

{% if client %}

{% set tickets = client.support_ticket_get_list({\"status\":'on_hold'}) %}
{% if tickets.total > 0 %}
<div class=\"row\">
<article class=\"span12 data-block\">
<div class=\"data-container\">
<header>
    <h2>{% trans 'Tickets waiting your reply' %}</h2>
</header>
<section id=\"slimScroll1\">
    <table class=\"table table-striped table-bordered table-condensed table-hover\">
        <thead>
        <tr>
            <th>{% trans 'Id' %}</th>
            <th>{% trans 'Subject' %}</th>
            <th>{% trans 'Help desk' %}</th>
            <th>{% trans 'Status' %}</th>
            <th>{% trans 'Submitted' %}</th>
            <th>{% trans 'Actions' %}</th>
        </tr>
        </thead>
        <tbody>
        {% for i, ticket in tickets.list %}

        <tr class=\"{{ cycle(['odd', 'even'], i) }}\">
            <td>{{ticket.id}}</td>
            <td><a href=\"{{ 'support/ticket'|link }}/{{ticket.id}}\">{{ticket.subject}}</a></td>
            <td>{{ticket.helpdesk.name}}</td>
            <td><span class=\"label\">{{mf.status_name(ticket.status)}}</span></td>
            <td>{{ticket.created_at|bb_date}}</td>
            <td><a href=\"{{ 'support/ticket'|link }}/{{ticket.id}}\" class=\"btn btn-small btn-inverse\">{% trans 'Reply' %}</a></td>
        </tr>

        {% else %}

        <tr>
            <td colspan=\"5\">{% trans 'The list is empty' %}</td>
        </tr>

        {% endfor %}

        </tbody>
    </table>
</section>
</div>
</article>
</div>
{% endif %}

<div class=\"row\">
{% set profile = client.client_get %}
<article class=\"span6 data-block decent\">
    <div class=\"data-container\">
        <header>
            <h2>{% trans 'Profile' %}</h2>
            <ul class=\"data-header-actions\">
                <li>
                    <a class=\"btn btn-alt btn-inverse\" href=\"{{ 'client/me'|link }}\">{% trans 'Update' %}</a>
                </li>
            </ul>
        </header>
        <section>
            <dl class=\"dl-horizontal\">
                <dt>{% trans 'ID' %}</dt>
                <dd>#{{ profile.id }}</dd>
                <dt>{% trans 'Email' %}</dt>
                <dd>{{ profile.email }}</dd>
                <dt>{% trans 'Balance' %}</dt>
                <dd>{{ profile.balance | money(profile.currency) }}</dd>
            </dl>
        </section>
    </div>
</article>

<article class=\"span6 data-block decent\">
    <div class=\"data-container\">
        <header>
            <h2>{% trans 'Invoices' %}</h2>
            <ul class=\"data-header-actions\">
                <li>
                    <a class=\"btn btn-alt btn-inverse\" href=\"{{ 'invoice'|link }}\">{% trans 'All Invoices' %}</a>
                </li>
            </ul>
        </header>
        <section>
            <dl class=\"dl-horizontal\">
                <dt>{% trans 'Total' %}</dt>
                    <dd>{{ client.invoice_get_list().total }}</dd>
                <dt>{% trans 'Paid' %}</dt>
                    <dd>{{ client.invoice_get_list({\"status\":\"paid\"}).total }}</dd>
                <dt>{% trans 'Unpaid' %}</dt>
                    <dd>{{ client.invoice_get_list({\"status\":\"unpaid\"}).total }}</dd>
            </dl>
        </section>
    </div>
</article>
</div>

<div class=\"row\">

<article class=\"span6 data-block decent\">
    <div class=\"data-container\">
        <header>
            <h2>{% trans 'Orders' %}</h2>
            <ul class=\"data-header-actions\">
                <li>
                    <a class=\"btn btn-alt btn-info order-button\" href=\"{{ 'order'|link }}\">{% trans 'New order' %}</a>
                    <a class=\"btn btn-alt btn-inverse\" href=\"{{ 'order/service'|link }}\">{% trans 'All orders' %}</a>
                </li>
            </ul>
        </header>
        <section>
            <dl class=\"dl-horizontal\">
                <dt>{% trans 'Total' %}</dt>
                <dd>{{ client.order_get_list({\"hide_addons\":1}).total }}</dd>
                <dt>{% trans 'Active' %}</dt>
                <dd>{{ client.order_get_list({\"hide_addons\":1, \"status\":\"active\"}).total }}</dd>
                <dt>{% trans 'Expiring' %}</dt>
                <dd>{{ client.order_get_list({\"expiring\":1}).total }}</dd>
            </dl>
        </section>
    </div>
</article>

<article class=\"span6 data-block decent\">
    <div class=\"data-container\">
        <header>
            <h2>{% trans 'Tickets' %}</h2>
            <ul class=\"data-header-actions\">
                <li>
                    <a class=\"btn btn-alt btn-info\" href=\"{{ 'support'|link({'ticket' : 1}) }}\">{% trans 'New ticket' %}</a>
                    <a class=\"btn btn-alt btn-inverse\" href=\"{{ 'support'|link }}\">{% trans 'All tickets' %}</a>
                </li>
            </ul>
        </header>
        <section>
            <dl class=\"dl-horizontal\">
                <dt>{% trans 'Total' %}</dt>
                <dd>{{ client.support_ticket_get_list().total }}</dd>
                <dt>{% trans 'Open' %}</dt>
                <dd>{{ client.support_ticket_get_list({\"status\":'open'}).total }}</dd>
                <dt>{% trans 'On Hold' %}</dt>
                <dd>{{ client.support_ticket_get_list({\"status\":'on_hold'}).total }}</dd>
            </dl>
        </section>
    </div>
</article>
</div>

<div class=\"row\">

<article class=\"span6 data-block decent\">
    <div class=\"data-container\">
        <header>
            <h2>{% trans 'Recent orders' %}</h2>
        </header>
        <section>
            <table class=\"table table-striped table-bordered table-condensed table-hover\">
                <tbody>
                {% for i, order in client.order_get_list({\"per_page\":5, \"page\":request.page, \"hide_addons\":1}).list %}
                <tr class=\"{{ cycle(['odd', 'even'], i) }}\">
                    <td><a href=\"{{ 'order/service/manage'|link }}/{{order.id}}\">{{ order.title|truncate(30) }}</a></td>
                    <td><span class=\"label {% if order.status == 'active'%}label-success{% elseif order.status == 'pending_setup' %}label-warning{% endif %}\">{{ mf.status_name(order.status) }}</span></td>
                </tr>
                {% else %}
                <tr>
                    <td colspan=\"3\">{% trans 'The list is empty' %}</td>
                </tr>
                {% endfor %}
                </tbody>
            </table>
        </section>
    </div>
</article>

<article class=\"span6 data-block decent\">
    <div class=\"data-container\">
        <header>
            <h2>{% trans 'Recent emails' %}</h2>
        </header>
        <section>
            <table class=\"table table-striped table-bordered table-condensed table-hover\">
                <tbody>
                {% for i, email in client.email_get_list({\"per_page\":5}).list %}
                <tr class=\"{{ cycle(['odd', 'even'], i) }}\">
                    <td><a href=\"{{ 'email'|link }}/{{email.id}}\">{{email.subject|truncate(30)}}</a></td>
                    <td title=\"{{ email.created_at|bb_date }}\">{{ email.created_at|timeago }} {% trans 'ago' %}</td>
                </tr>
                {% else %}
                <tr>
                    <td colspan=\"2\">{% trans 'The list is empty' %}</td>
                </tr>
                {% endfor %}
                </tbody>
            </table>
        </section>
    </div>
</article>

</div>

{% endif %}
{% endblock %}", "mod_index_dashboard.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/src/bb-modules/Index/html_client/mod_index_dashboard.phtml");
    }
}
