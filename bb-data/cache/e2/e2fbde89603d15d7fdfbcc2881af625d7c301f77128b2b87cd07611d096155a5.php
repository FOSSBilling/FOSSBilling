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

/* mod_invoice_invoice.phtml */
class __TwigTemplate_0ef862e929e36d66879e29d04a5b2be114dce2bf422dc65763b2d2df5f5a6e21 extends \Twig\Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->blocks = [
            'meta_title' => [$this, 'block_meta_title'],
            'breadcrumb' => [$this, 'block_breadcrumb'],
            'content' => [$this, 'block_content'],
            'js' => [$this, 'block_js'],
        ];
    }

    protected function doGetParent(array $context)
    {
        // line 1
        return $this->loadTemplate(((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ajax", [], "any", false, false, false, 1)) ? ("layout_blank.phtml") : ("layout_default.phtml")), "mod_invoice_invoice.phtml", 1);
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 2
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_invoice_invoice.phtml", 2)->unwrap();
        // line 3
        $context["nr"] = (twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "serie", [], "any", false, false, false, 3) . sprintf("%05s", twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "nr", [], "any", false, false, false, 3)));
        // line 1
        $this->getParent($context)->display($context, array_merge($this->blocks, $blocks));
    }

    // line 5
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo gettext("Proforma invoice");
    }

    // line 8
    public function block_breadcrumb($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 9
        echo "    <li><a href=\"";
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/invoice");
        echo "\">";
        echo gettext("Invoices");
        echo "</a> <span class=\"divider\">/</span></li>
    <li class=\"active\"> ";
        // line 10
        if ((twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "status", [], "any", false, false, false, 10) == "paid")) {
            echo " ";
            echo gettext("Receipt");
            echo " ";
        } else {
            echo "  ";
            echo gettext("Invoice");
            echo " ";
        }
        echo twig_escape_filter($this->env, ($context["nr"] ?? null), "html", null, true);
        echo "</li>
";
    }

    // line 13
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 14
        echo "
";
        // line 15
        $context["seller"] = twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "seller", [], "any", false, false, false, 15);
        // line 16
        $context["buyer"] = twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "buyer", [], "any", false, false, false, 16);
        // line 17
        $context["company"] = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_company", [], "any", false, false, false, 17);
        // line 18
        echo "
";
        // line 19
        if ((twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "status", [], "any", false, false, false, 19) == "unpaid")) {
            // line 20
            echo "<div class=\"row\">
<article class=\"span12 data-block decent\">
<div class=\"data-container\">


<header>
    <h2>";
            // line 26
            echo gettext("Payment methods");
            echo "</h2>
    <p>";
            // line 27
            echo gettext("Please choose payment type and pay for your chosen products.");
            echo "</p>
</header>
<form method=\"post\" action=\"";
            // line 29
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/guest/invoice/payment");
            echo "\" class=\"api-form\" data-api-redirect=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter(("invoice/" . twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "hash", [], "any", false, false, false, 29)), ["auto_redirect" => 1]);
            echo "\">
    <input type=\"hidden\" name=\"hash\" value=\"";
            // line 30
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "hash", [], "any", false, false, false, 30), "html", null, true);
            echo "\"/>
    ";
            // line 31
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "invoice_gateways", [], "any", false, false, false, 31));
            foreach ($context['_seq'] as $context["_key"] => $context["gtw"]) {
                // line 32
                echo "    ";
                if (twig_in_filter(twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "currency", [], "any", false, false, false, 32), twig_get_attribute($this->env, $this->source, $context["gtw"], "accepted_currencies", [], "any", false, false, false, 32))) {
                    // line 33
                    echo "    ";
                    $context["banklink"] = $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("invoice/banklink");
                    // line 34
                    echo "    <button type=\"button\"  class=\"logo-";
                    echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["gtw"], "code", [], "any", false, false, false, 34), "html", null, true);
                    echo " hover-popover\" type=\"radio\" name=\"gateway_id\" gateway_id=\"";
                    echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["gtw"], "id", [], "any", false, false, false, 34), "html", null, true);
                    echo "\" data-toggle=\"tooltip\" title=\"";
                    echo gettext("Pay with");
                    echo " ";
                    echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["gtw"], "title", [], "any", false, false, false, 34), "html", null, true);
                    echo "\" onclick=\"window.location.replace('";
                    echo twig_escape_filter($this->env, ($context["banklink"] ?? null), "html", null, true);
                    echo "/";
                    echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "hash", [], "any", false, false, false, 34), "html", null, true);
                    echo "/";
                    echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["gtw"], "id", [], "any", false, false, false, 34), "html", null, true);
                    echo "')\")></button>
    ";
                }
                // line 36
                echo "    ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['gtw'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 37
            echo "    <input type=\"hidden\" name=\"gateway_id\" id=\"gateway_id\">
</form>
</div>
</article>
</div>

";
        }
        // line 44
        echo "
<div class=\"row\">
    <article class=\"span12 data-block\">
        <div class=\"data-container\">

        <header>
            <h1> ";
        // line 50
        if ((twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "status", [], "any", false, false, false, 50) == "paid")) {
            echo " ";
            echo gettext("Receipt");
            echo " ";
        } else {
            echo "  ";
            echo gettext("Invoice");
            echo " ";
        }
        echo " - ";
        echo twig_escape_filter($this->env, ($context["nr"] ?? null), "html", null, true);
        echo "</h1><br/>
            ";
        // line 51
        echo gettext("You can print this invoice or export it to PDF file by clicking on corresponding button.");
        // line 52
        echo "            <ul class=\"data-header-actions\">
                <li><a href=\"";
        // line 53
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("invoice/pdf");
        echo "/";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "hash", [], "any", false, false, false, 53), "html", null, true);
        echo "\" class=\"btn btn-alt btn-inverse\">";
        echo gettext("PDF");
        echo "</a></li>
                <li><a href=\"";
        // line 54
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("invoice/print");
        echo "/";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "hash", [], "any", false, false, false, 54), "html", null, true);
        echo "\" target=\"_blank\" class=\"btn btn-alt btn-inverse\">";
        echo gettext("Print");
        echo "</a></li>
            </ul>
        </header>

            <section>
                <div class=\"row-fluid\">
                    <div class=\"span4\">
                        ";
        // line 61
        if (twig_get_attribute($this->env, $this->source, ($context["company"] ?? null), "logo_url", [], "any", false, false, false, 61)) {
            // line 62
            echo "                        <img src=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["company"] ?? null), "logo_url", [], "any", false, false, false, 62), "html", null, true);
            echo "\" alt=\"Logo\">
                        ";
        }
        // line 64
        echo "                        <dl class=\"dl-horizontal\">
                            <dt>";
        // line 65
        echo gettext("Invoice number");
        echo ":</dt>
                            <dd>";
        // line 66
        echo twig_escape_filter($this->env, ($context["nr"] ?? null), "html", null, true);
        echo "</dd>
                            <dt>";
        // line 67
        echo gettext("Invoice date");
        echo ":</dt>
                            <dd>";
        // line 68
        if (twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "paid_at", [], "any", false, false, false, 68)) {
            // line 69
            echo "                                ";
            echo twig_escape_filter($this->env, $this->extensions['Box_TwigExtensions']->twig_bb_date(twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "paid_at", [], "any", false, false, false, 69)), "html", null, true);
            echo "
                                ";
        } else {
            // line 71
            echo "                                ";
            echo twig_escape_filter($this->env, $this->extensions['Box_TwigExtensions']->twig_bb_date(twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "created_at", [], "any", false, false, false, 71)), "html", null, true);
            echo "
                                ";
        }
        // line 73
        echo "                            </dd>
                            <dt>";
        // line 74
        echo gettext("Due date");
        echo ":</dt>
                            <dd>";
        // line 75
        if (twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "due_at", [], "any", false, false, false, 75)) {
            // line 76
            echo "                                ";
            echo twig_escape_filter($this->env, $this->extensions['Box_TwigExtensions']->twig_bb_date(twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "due_at", [], "any", false, false, false, 76)), "html", null, true);
            echo "
                                ";
        } else {
            // line 78
            echo "                                -----
                                ";
        }
        // line 80
        echo "                            </dd>
                            <dt>";
        // line 81
        echo gettext("Invoice status");
        echo ":</dt>
                            <dd>
                                <span class=\"label ";
        // line 83
        if ((twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "status", [], "any", false, false, false, 83) == "paid")) {
            echo " label-success";
        } elseif ((twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "status", [], "any", false, false, false, 83) == "unpaid")) {
            echo "label-warning";
        }
        echo "\">
                                      ";
        // line 84
        echo twig_escape_filter($this->env, twig_capitalize_string_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "status", [], "any", false, false, false, 84)), "html", null, true);
        echo "
                                </span>
                            </dd>
                        </dl>
                    </div>
                    <div class=\"span4\">
                        <div class=\"well small\">
                            <h4>";
        // line 91
        echo gettext("Company");
        echo "</h4>
                            <dl class=\"dl-horizontal\">
                                ";
        // line 93
        if (twig_get_attribute($this->env, $this->source, ($context["seller"] ?? null), "company", [], "any", false, false, false, 93)) {
            // line 94
            echo "                                <dt>";
            echo gettext("Name");
            echo ":</dt>
                                <dd>";
            // line 95
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["seller"] ?? null), "company", [], "any", false, false, false, 95), "html", null, true);
            echo "</dd>
                                ";
        }
        // line 97
        echo "
                                ";
        // line 98
        if (twig_get_attribute($this->env, $this->source, ($context["seller"] ?? null), "company_vat", [], "any", false, false, false, 98)) {
            // line 99
            echo "                                <dt>";
            echo gettext("VAT");
            echo ":</dt>
                                <dd>";
            // line 100
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["seller"] ?? null), "company_vat", [], "any", false, false, false, 100), "html", null, true);
            echo "</dd>
                                ";
        }
        // line 102
        echo "
                                ";
        // line 103
        if (twig_get_attribute($this->env, $this->source, ($context["seller"] ?? null), "address", [], "any", false, false, false, 103)) {
            // line 104
            echo "                                <dt>";
            echo gettext("Address");
            echo ":</dt>
                                <dd>";
            // line 105
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["seller"] ?? null), "address", [], "any", false, false, false, 105), "html", null, true);
            echo "</dd>
                                ";
        }
        // line 107
        echo "
                                ";
        // line 108
        if (twig_get_attribute($this->env, $this->source, ($context["seller"] ?? null), "phone", [], "any", false, false, false, 108)) {
            // line 109
            echo "                                <dt>";
            echo gettext("Phone");
            echo ":</dt>
                                <dd>";
            // line 110
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["seller"] ?? null), "phone", [], "any", false, false, false, 110), "html", null, true);
            echo "</dd>
                                ";
        }
        // line 112
        echo "
                                ";
        // line 113
        if (twig_get_attribute($this->env, $this->source, ($context["seller"] ?? null), "email", [], "any", false, false, false, 113)) {
            // line 114
            echo "                                <dt>";
            echo gettext("Email");
            echo ":</dt>
                                <dd>";
            // line 115
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["seller"] ?? null), "email", [], "any", false, false, false, 115), "html", null, true);
            echo "</dd>
                                ";
        }
        // line 117
        echo "
                                ";
        // line 118
        if (twig_get_attribute($this->env, $this->source, ($context["seller"] ?? null), "account_number", [], "any", false, false, false, 118)) {
            // line 119
            echo "                                <dt>";
            echo gettext("Account");
            echo ":</dt>
                                <dd>";
            // line 120
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["seller"] ?? null), "account_number", [], "any", false, false, false, 120), "html", null, true);
            echo "</dd>
                                ";
        }
        // line 122
        echo "
                                ";
        // line 123
        if (twig_get_attribute($this->env, $this->source, ($context["seller"] ?? null), "note", [], "any", false, false, false, 123)) {
            // line 124
            echo "                                <dt>";
            echo gettext("Note");
            echo ":</dt>
                                <dd>";
            // line 125
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["seller"] ?? null), "note", [], "any", false, false, false, 125), "html", null, true);
            echo "</dd>
                                ";
        }
        // line 127
        echo "                            </dl>

                        </div>
                    </div>


                    <div class=\"span4\">
                        <div class=\"well small\">
                            <h4>";
        // line 135
        echo gettext("Billing & Delivery address");
        echo "</h4>
                            <dl class=\"dl-horizontal\">
                                ";
        // line 137
        if ((twig_get_attribute($this->env, $this->source, ($context["buyer"] ?? null), "first_name", [], "any", false, false, false, 137) || twig_get_attribute($this->env, $this->source, ($context["buyer"] ?? null), "last_name", [], "any", false, false, false, 137))) {
            // line 138
            echo "                                <dt>";
            echo gettext("Name");
            echo ":</dt>
                                <dd>";
            // line 139
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["buyer"] ?? null), "first_name", [], "any", false, false, false, 139), "html", null, true);
            echo " ";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["buyer"] ?? null), "last_name", [], "any", false, false, false, 139), "html", null, true);
            echo "</dd>
                                ";
        }
        // line 141
        echo "
                                ";
        // line 142
        if (twig_get_attribute($this->env, $this->source, ($context["buyer"] ?? null), "company", [], "any", false, false, false, 142)) {
            // line 143
            echo "                                <dt>";
            echo gettext("Company");
            echo ":</dt>
                                <dd>";
            // line 144
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["buyer"] ?? null), "company", [], "any", false, false, false, 144), "html", null, true);
            echo "</dd>
                                ";
        }
        // line 146
        echo "
                                ";
        // line 147
        if (twig_get_attribute($this->env, $this->source, ($context["buyer"] ?? null), "company_number", [], "any", false, false, false, 147)) {
            // line 148
            echo "                                <dt>";
            echo gettext("Company number");
            echo ":</dt>
                                <dd>";
            // line 149
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["buyer"] ?? null), "company_number", [], "any", false, false, false, 149), "html", null, true);
            echo "</dd>
                                ";
        }
        // line 151
        echo "
                                ";
        // line 152
        if (twig_get_attribute($this->env, $this->source, ($context["buyer"] ?? null), "company_vat", [], "any", false, false, false, 152)) {
            // line 153
            echo "                                <dt>";
            echo gettext("Company VAT");
            echo ":</dt>
                                <dd>";
            // line 154
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["buyer"] ?? null), "company_vat", [], "any", false, false, false, 154), "html", null, true);
            echo "</dd>
                                ";
        }
        // line 156
        echo "
                                ";
        // line 157
        if (twig_get_attribute($this->env, $this->source, ($context["buyer"] ?? null), "address", [], "any", false, false, false, 157)) {
            // line 158
            echo "                                <dt>";
            echo gettext("Address");
            echo ":</dt>
                                <dd>";
            // line 159
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["buyer"] ?? null), "address", [], "any", false, false, false, 159), "html", null, true);
            echo "</dd>
                                <dd>";
            // line 160
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["buyer"] ?? null), "city", [], "any", false, false, false, 160), "html", null, true);
            echo ", ";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["buyer"] ?? null), "state", [], "any", false, false, false, 160), "html", null, true);
            echo "</dd>
                                <dd>";
            // line 161
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["buyer"] ?? null), "zip", [], "any", false, false, false, 161), "html", null, true);
            echo ", ";
            echo twig_escape_filter($this->env, (($__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4 = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_countries", [], "any", false, false, false, 161)) && is_array($__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4) || $__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4 instanceof ArrayAccess ? ($__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4[twig_get_attribute($this->env, $this->source, ($context["buyer"] ?? null), "country", [], "any", false, false, false, 161)] ?? null) : null), "html", null, true);
            echo "</dd>
                                ";
        }
        // line 163
        echo "
                                ";
        // line 164
        if (twig_get_attribute($this->env, $this->source, ($context["buyer"] ?? null), "phone", [], "any", false, false, false, 164)) {
            // line 165
            echo "                                <dt>";
            echo gettext("Phone");
            echo ":</dt>
                                <dd>";
            // line 166
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["buyer"] ?? null), "phone", [], "any", false, false, false, 166), "html", null, true);
            echo "</dd>
                                ";
        }
        // line 168
        echo "                            </dl>
                        </div>
                    </div>
                </div>

                ";
        // line 173
        if (twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "text_1", [], "any", false, false, false, 173)) {
            // line 174
            echo "                    <div class=\"well\">
                        ";
            // line 175
            echo twig_markdown_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "text_1", [], "any", false, false, false, 175));
            echo "
                    </div>
                ";
        }
        // line 178
        echo "
                <table class=\"table table-striped table-bordered table-condensed table-hover\">
                    <thead>
                        <tr>
                            <th>";
        // line 182
        echo gettext("#");
        echo "</th>
                            <th>";
        // line 183
        echo gettext("Title");
        echo "</th>
                            <th>";
        // line 184
        echo gettext("Price");
        echo "</th>
                            <th>";
        // line 185
        echo gettext("Total");
        echo "</th>
                        </tr>
                    </thead>
                    <tbody>
                        ";
        // line 189
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "lines", [], "any", false, false, false, 189));
        foreach ($context['_seq'] as $context["i"] => $context["item"]) {
            // line 190
            echo "                        <tr>
                            <td>";
            // line 191
            echo twig_escape_filter($this->env, ($context["i"] + 1), "html", null, true);
            echo ".</td>
                            <td>
                                ";
            // line 193
            if (twig_get_attribute($this->env, $this->source, $context["item"], "order_id", [], "any", false, false, false, 193)) {
                // line 194
                echo "                                <a href=\"";
                echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/order/service");
                echo "/manage/";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["item"], "order_id", [], "any", false, false, false, 194), "html", null, true);
                echo "\">";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["item"], "title", [], "any", false, false, false, 194), "html", null, true);
                echo "</a>
                                ";
            } else {
                // line 196
                echo "                                ";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["item"], "title", [], "any", false, false, false, 196), "html", null, true);
                echo " 
                                ";
            }
            // line 198
            echo "                                ";
            if ((twig_get_attribute($this->env, $this->source, $context["item"], "quantity", [], "any", false, false, false, 198) > 1)) {
                // line 199
                echo "                                x ";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["item"], "quantity", [], "any", false, false, false, 199), "html", null, true);
                echo " ";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["item"], "unit", [], "any", false, false, false, 199), "html", null, true);
                echo "
                                ";
            }
            // line 201
            echo "                            </td>
                            <td>
                                ";
            // line 203
            echo twig_money($this->env, twig_get_attribute($this->env, $this->source, $context["item"], "price", [], "any", false, false, false, 203), twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "currency", [], "any", false, false, false, 203));
            echo "
                               
                            </td>
                            <td >";
            // line 206
            echo twig_money($this->env, twig_get_attribute($this->env, $this->source, $context["item"], "total", [], "any", false, false, false, 206), twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "currency", [], "any", false, false, false, 206));
            echo "</td>
                        </tr>

                        ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['i'], $context['item'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 210
        echo "                    </tbody>

                </table>

                <div class=\"row-fluid\">
                    <div class=\"span4 offset8\">
                        <table class=\"table table-bordered table-striped\">
                            ";
        // line 217
        if ((twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "tax", [], "any", false, false, false, 217) > 0)) {
            // line 218
            echo "                            <tr>
                                <td>";
            // line 219
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "taxname", [], "any", false, false, false, 219), "html", null, true);
            echo " ";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "taxrate", [], "any", false, false, false, 219), "html", null, true);
            echo "%</td>
                                <td>";
            // line 220
            echo twig_money($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "tax", [], "any", false, false, false, 220), twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "currency", [], "any", false, false, false, 220));
            echo "</td>
                            </tr>
                            ";
        }
        // line 223
        echo "                            ";
        if ((twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "discount", [], "any", false, false, false, 223) > 0)) {
            // line 224
            echo "                            <tr>
                                <td>";
            // line 225
            echo gettext("Discount");
            echo "</td>
                                <td>";
            // line 226
            echo twig_money($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "discount", [], "any", false, false, false, 226), twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "currency", [], "any", false, false, false, 226));
            echo "</td>
                            </tr>
                            ";
        }
        // line 229
        echo "
                            <tr>
                                <td><strong>";
        // line 231
        echo gettext("Total");
        echo "</strong></td>
                                <td><strong>";
        // line 232
        echo twig_money($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "total", [], "any", false, false, false, 232), twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "currency", [], "any", false, false, false, 232));
        echo "</strong></td>
                            </tr>
                        </table>
                    </div>
                </div>

                ";
        // line 238
        if (twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "text_2", [], "any", false, false, false, 238)) {
            // line 239
            echo "                    <div class=\"well\">
                        ";
            // line 240
            echo twig_markdown_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["invoice"] ?? null), "text_2", [], "any", false, false, false, 240));
            echo "
                    </div>
                ";
        }
        // line 243
        echo "            </section>
        </div>
    </article>
</div>
";
    }

    // line 249
    public function block_js($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 250
        echo "<script type=\"text/javascript\">
    \$(function() {
        \$(\".hover-popover\").tooltip({
            placement: 'top'
        });
    });
</script>
";
    }

    public function getTemplateName()
    {
        return "mod_invoice_invoice.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  714 => 250,  710 => 249,  702 => 243,  696 => 240,  693 => 239,  691 => 238,  682 => 232,  678 => 231,  674 => 229,  668 => 226,  664 => 225,  661 => 224,  658 => 223,  652 => 220,  646 => 219,  643 => 218,  641 => 217,  632 => 210,  622 => 206,  616 => 203,  612 => 201,  604 => 199,  601 => 198,  595 => 196,  585 => 194,  583 => 193,  578 => 191,  575 => 190,  571 => 189,  564 => 185,  560 => 184,  556 => 183,  552 => 182,  546 => 178,  540 => 175,  537 => 174,  535 => 173,  528 => 168,  523 => 166,  518 => 165,  516 => 164,  513 => 163,  506 => 161,  500 => 160,  496 => 159,  491 => 158,  489 => 157,  486 => 156,  481 => 154,  476 => 153,  474 => 152,  471 => 151,  466 => 149,  461 => 148,  459 => 147,  456 => 146,  451 => 144,  446 => 143,  444 => 142,  441 => 141,  434 => 139,  429 => 138,  427 => 137,  422 => 135,  412 => 127,  407 => 125,  402 => 124,  400 => 123,  397 => 122,  392 => 120,  387 => 119,  385 => 118,  382 => 117,  377 => 115,  372 => 114,  370 => 113,  367 => 112,  362 => 110,  357 => 109,  355 => 108,  352 => 107,  347 => 105,  342 => 104,  340 => 103,  337 => 102,  332 => 100,  327 => 99,  325 => 98,  322 => 97,  317 => 95,  312 => 94,  310 => 93,  305 => 91,  295 => 84,  287 => 83,  282 => 81,  279 => 80,  275 => 78,  269 => 76,  267 => 75,  263 => 74,  260 => 73,  254 => 71,  248 => 69,  246 => 68,  242 => 67,  238 => 66,  234 => 65,  231 => 64,  225 => 62,  223 => 61,  209 => 54,  201 => 53,  198 => 52,  196 => 51,  182 => 50,  174 => 44,  165 => 37,  159 => 36,  141 => 34,  138 => 33,  135 => 32,  131 => 31,  127 => 30,  121 => 29,  116 => 27,  112 => 26,  104 => 20,  102 => 19,  99 => 18,  97 => 17,  95 => 16,  93 => 15,  90 => 14,  86 => 13,  71 => 10,  64 => 9,  60 => 8,  53 => 5,  49 => 1,  47 => 3,  45 => 2,  38 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}
{% import \"macro_functions.phtml\" as mf %}
{% set nr = invoice.serie ~ \"%05s\"|format(invoice.nr) %}

{% block meta_title %}{% trans 'Proforma invoice' %}{% endblock %}


{% block breadcrumb %}
    <li><a href=\"{{ '/invoice'|link }}\">{% trans 'Invoices' %}</a> <span class=\"divider\">/</span></li>
    <li class=\"active\"> {% if invoice.status == 'paid' %} {% trans 'Receipt' %} {% else %}  {% trans 'Invoice' %} {% endif %}{{nr}}</li>
{% endblock %}

{% block content %}

{% set seller = invoice.seller %}
{% set buyer = invoice.buyer %}
{% set company  = guest.system_company %}

{% if invoice.status == 'unpaid' %}
<div class=\"row\">
<article class=\"span12 data-block decent\">
<div class=\"data-container\">


<header>
    <h2>{% trans 'Payment methods' %}</h2>
    <p>{% trans 'Please choose payment type and pay for your chosen products.' %}</p>
</header>
<form method=\"post\" action=\"{{ 'api/guest/invoice/payment'|link }}\" class=\"api-form\" data-api-redirect=\"{{ ('invoice/'~invoice.hash)|link({'auto_redirect' : 1}) }}\">
    <input type=\"hidden\" name=\"hash\" value=\"{{invoice.hash}}\"/>
    {% for gtw in guest.invoice_gateways %}
    {% if invoice.currency in gtw.accepted_currencies %}
    {% set banklink =  'invoice/banklink' | link %}
    <button type=\"button\"  class=\"logo-{{gtw.code}} hover-popover\" type=\"radio\" name=\"gateway_id\" gateway_id=\"{{gtw.id}}\" data-toggle=\"tooltip\" title=\"{% trans 'Pay with' %} {{gtw.title}}\" onclick=\"window.location.replace('{{banklink}}/{{invoice.hash}}/{{gtw.id}}')\")></button>
    {% endif %}
    {% endfor %}
    <input type=\"hidden\" name=\"gateway_id\" id=\"gateway_id\">
</form>
</div>
</article>
</div>

{% endif %}

<div class=\"row\">
    <article class=\"span12 data-block\">
        <div class=\"data-container\">

        <header>
            <h1> {% if invoice.status == 'paid' %} {% trans 'Receipt' %} {% else %}  {% trans 'Invoice' %} {% endif %} - {{nr}}</h1><br/>
            {% trans 'You can print this invoice or export it to PDF file by clicking on corresponding button.' %}
            <ul class=\"data-header-actions\">
                <li><a href=\"{{ 'invoice/pdf' | link }}/{{invoice.hash}}\" class=\"btn btn-alt btn-inverse\">{% trans 'PDF' %}</a></li>
                <li><a href=\"{{ 'invoice/print' | link }}/{{invoice.hash}}\" target=\"_blank\" class=\"btn btn-alt btn-inverse\">{% trans 'Print' %}</a></li>
            </ul>
        </header>

            <section>
                <div class=\"row-fluid\">
                    <div class=\"span4\">
                        {% if company.logo_url %}
                        <img src=\"{{ company.logo_url }}\" alt=\"Logo\">
                        {% endif %}
                        <dl class=\"dl-horizontal\">
                            <dt>{% trans 'Invoice number' %}:</dt>
                            <dd>{{ nr }}</dd>
                            <dt>{% trans 'Invoice date' %}:</dt>
                            <dd>{% if invoice.paid_at %}
                                {{ invoice.paid_at | bb_date }}
                                {% else %}
                                {{ invoice.created_at | bb_date }}
                                {% endif %}
                            </dd>
                            <dt>{% trans 'Due date' %}:</dt>
                            <dd>{% if invoice.due_at %}
                                {{ invoice.due_at | bb_date }}
                                {% else %}
                                -----
                                {% endif %}
                            </dd>
                            <dt>{% trans 'Invoice status' %}:</dt>
                            <dd>
                                <span class=\"label {% if invoice.status == 'paid' %} label-success{% elseif invoice.status == 'unpaid'%}label-warning{% endif %}\">
                                      {{ invoice.status | capitalize }}
                                </span>
                            </dd>
                        </dl>
                    </div>
                    <div class=\"span4\">
                        <div class=\"well small\">
                            <h4>{% trans 'Company' %}</h4>
                            <dl class=\"dl-horizontal\">
                                {% if seller.company %}
                                <dt>{% trans 'Name' %}:</dt>
                                <dd>{{seller.company}}</dd>
                                {% endif %}

                                {% if seller.company_vat%}
                                <dt>{% trans 'VAT' %}:</dt>
                                <dd>{{seller.company_vat}}</dd>
                                {% endif %}

                                {% if seller.address%}
                                <dt>{% trans 'Address' %}:</dt>
                                <dd>{{seller.address}}</dd>
                                {% endif %}

                                {% if seller.phone %}
                                <dt>{% trans 'Phone' %}:</dt>
                                <dd>{{seller.phone}}</dd>
                                {% endif %}

                                {% if seller.email %}
                                <dt>{% trans 'Email' %}:</dt>
                                <dd>{{seller.email}}</dd>
                                {% endif %}

                                {% if seller.account_number %}
                                <dt>{% trans 'Account' %}:</dt>
                                <dd>{{seller.account_number}}</dd>
                                {% endif %}

                                {% if seller.note %}
                                <dt>{% trans 'Note' %}:</dt>
                                <dd>{{seller.note}}</dd>
                                {% endif %}
                            </dl>

                        </div>
                    </div>


                    <div class=\"span4\">
                        <div class=\"well small\">
                            <h4>{% trans 'Billing & Delivery address' %}</h4>
                            <dl class=\"dl-horizontal\">
                                {% if buyer.first_name or buyer.last_name %}
                                <dt>{% trans 'Name' %}:</dt>
                                <dd>{{buyer.first_name}} {{buyer.last_name}}</dd>
                                {% endif %}

                                {% if buyer.company %}
                                <dt>{% trans 'Company' %}:</dt>
                                <dd>{{buyer.company}}</dd>
                                {% endif %}

                                {% if buyer.company_number %}
                                <dt>{% trans 'Company number' %}:</dt>
                                <dd>{{buyer.company_number}}</dd>
                                {% endif %}

                                {% if buyer.company_vat %}
                                <dt>{% trans 'Company VAT' %}:</dt>
                                <dd>{{buyer.company_vat}}</dd>
                                {% endif %}

                                {% if buyer.address %}
                                <dt>{% trans 'Address' %}:</dt>
                                <dd>{{buyer.address}}</dd>
                                <dd>{{buyer.city}}, {{buyer.state}}</dd>
                                <dd>{{buyer.zip}}, {{guest.system_countries[buyer.country]}}</dd>
                                {% endif %}

                                {% if buyer.phone %}
                                <dt>{% trans 'Phone' %}:</dt>
                                <dd>{{buyer.phone}}</dd>
                                {% endif %}
                            </dl>
                        </div>
                    </div>
                </div>

                {% if invoice.text_1 %}
                    <div class=\"well\">
                        {{ invoice.text_1|markdown }}
                    </div>
                {% endif %}

                <table class=\"table table-striped table-bordered table-condensed table-hover\">
                    <thead>
                        <tr>
                            <th>{% trans '#' %}</th>
                            <th>{% trans 'Title' %}</th>
                            <th>{% trans 'Price' %}</th>
                            <th>{% trans 'Total' %}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {% for i, item in invoice.lines %}
                        <tr>
                            <td>{{ i+1 }}.</td>
                            <td>
                                {% if item.order_id%}
                                <a href=\"{{ '/order/service'|link }}/manage/{{item.order_id}}\">{{ item.title }}</a>
                                {% else %}
                                {{ item.title }} 
                                {% endif %}
                                {% if item.quantity > 1 %}
                                x {{ item.quantity }} {{ item.unit }}
                                {% endif %}
                            </td>
                            <td>
                                {{ item.price | money(invoice.currency) }}
                               
                            </td>
                            <td >{{ item.total | money(invoice.currency) }}</td>
                        </tr>

                        {% endfor %}
                    </tbody>

                </table>

                <div class=\"row-fluid\">
                    <div class=\"span4 offset8\">
                        <table class=\"table table-bordered table-striped\">
                            {% if invoice.tax > 0 %}
                            <tr>
                                <td>{{ invoice.taxname }} {{ invoice.taxrate }}%</td>
                                <td>{{ invoice.tax | money(invoice.currency) }}</td>
                            </tr>
                            {% endif %}
                            {% if invoice.discount > 0 %}
                            <tr>
                                <td>{% trans 'Discount' %}</td>
                                <td>{{ invoice.discount | money(invoice.currency) }}</td>
                            </tr>
                            {% endif %}

                            <tr>
                                <td><strong>{% trans 'Total' %}</strong></td>
                                <td><strong>{{ invoice.total | money(invoice.currency) }}</strong></td>
                            </tr>
                        </table>
                    </div>
                </div>

                {% if invoice.text_2 %}
                    <div class=\"well\">
                        {{ invoice.text_2|markdown }}
                    </div>
                {% endif %}
            </section>
        </div>
    </article>
</div>
{% endblock %}

{% block js%}
<script type=\"text/javascript\">
    \$(function() {
        \$(\".hover-popover\").tooltip({
            placement: 'top'
        });
    });
</script>
{% endblock%}", "mod_invoice_invoice.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-modules/Invoice/html_client/mod_invoice_invoice.phtml");
    }
}
