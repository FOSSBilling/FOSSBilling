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

/* mod_servicedomain_manage.phtml */
class __TwigTemplate_b40ff7c45ffbc135b2f8119294dfadf18651d7ae00a2cfc6ac8b774312a567d6 extends \Twig\Template
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
        if ((twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "status", [], "any", false, false, false, 1) == "active")) {
            // line 2
            echo "
<div class=\"row\">
    <article class=\"span12 data-block\">
        <div class=\"data-container\">
            <header>
                <h2>";
            // line 7
            echo gettext("Domain management");
            echo "</h2>
                <ul class=\"data-header-actions\">
                    <li class=\"domain-tabs active\"><a href=\"#details\" class=\"btn btn-inverse btn-alt\">";
            // line 9
            echo gettext("Details");
            echo "</a></li>
                    <li class=\"domain-tabs\"><a href=\"#protection\" class=\"btn btn-inverse btn-alt\">";
            // line 10
            echo gettext("Protection");
            echo "</a></li>
                    <li class=\"domain-tabs\"><a href=\"#privacy\" class=\"btn btn-inverse btn-alt\">";
            // line 11
            echo gettext("Privacy");
            echo "</a></li>
                    <li class=\"domain-tabs\"><a href=\"#nameservers\" class=\"btn btn-inverse btn-alt\">";
            // line 12
            echo gettext("Nameservers");
            echo "</a></li>
                    <li class=\"domain-tabs\"><a href=\"#whois\" class=\"btn btn-inverse btn-alt\">";
            // line 13
            echo gettext("Whois");
            echo "</a></li>
                    <li class=\"domain-tabs\"><a href=\"#epp\" class=\"btn btn-inverse btn-alt\">";
            // line 14
            echo gettext("Transfer");
            echo "</a></li>
                </ul>
            </header>
            <section class=\"tab-content\">
                <div class=\"tab-pane active\" id=\"details\">
                    <h2>";
            // line 19
            echo gettext("Domain details");
            echo "</h2>
                    <table class=\"table table-striped table-bordered table-condensed\">
                        <tbody>
                        <tr>
                            <td>";
            // line 23
            echo gettext("Domain");
            echo "</td>
                            <td><a target=\"_blank\" href=\"http://";
            // line 24
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "domain", [], "any", false, false, false, 24), "html", null, true);
            echo "\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "domain", [], "any", false, false, false, 24), "html", null, true);
            echo "</a></td>
                        </tr>
                        <tr>
                            <td>";
            // line 27
            echo gettext("Registration date");
            echo "</td>
                            <td>";
            // line 28
            echo twig_escape_filter($this->env, $this->extensions['Box_TwigExtensions']->twig_bb_date(twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "registered_at", [], "any", false, false, false, 28)), "html", null, true);
            echo "</td>
                        </tr>

                        <tr>
                            <td>";
            // line 32
            echo gettext("Expires at");
            echo "</td>
                            <td>";
            // line 33
            echo twig_escape_filter($this->env, $this->extensions['Box_TwigExtensions']->twig_bb_date(twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "expires_at", [], "any", false, false, false, 33)), "html", null, true);
            echo "</td>
                        </tr>

                        </tbody>
                    </table>
                </div>
                <div class=\"tab-pane\" id=\"protection\">
                    <h2>";
            // line 40
            echo gettext("Domain Protection");
            echo "</h2>
                    <p class=\"alert alert-info\">";
            // line 41
            echo gettext("Domain locking is a security enhancement to prevent unauthorized transfers of your domain to another registrar or web host by \"locking\" your domain name servers.");
            echo "</p>
                    <div class=\"control-group\">
                        <div class=\"controls\">
                            <button class=\"btn btn-primary\" type=\"button\" id=\"domain-unlock\" ";
            // line 44
            if ((twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "locked", [], "any", false, false, false, 44) == 0)) {
                echo "style=\"display:none;\"";
            }
            echo ">";
            echo gettext("Unlock");
            echo "</button>
                            <button class=\"btn btn-primary\" type=\"button\" id=\"domain-lock\" ";
            // line 45
            if ((twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "locked", [], "any", false, false, false, 45) == 1)) {
                echo "style=\"display:none;\"";
            }
            echo ">";
            echo gettext("Lock");
            echo "</button>
                        </div>
                    </div>

                </div>
                <div class=\"tab-pane\" id=\"privacy\">
                    <h2>";
            // line 51
            echo gettext("Domain Privacy Settings");
            echo "</h2>
                    <div class=\"block\">
                        <p class=\"alert alert-info\">";
            // line 53
            echo gettext("If you would like to hide domain contact information which is shown on WHOIS you can enable privacy protection. Once domain privacy protection is enabled no one will know who registered this service. And once you decide to disable privacy protection, information that is listed above on \"Update Domain Contact Details\" section will be seen on domain WHOIS again.");
            echo "</p>
                        <div class=\"control-group\">
                            <div class=\"controls\">
                                <button class=\"btn btn-primary\" type=\"button\" id=\"domain-disable-pp\" ";
            // line 56
            if ((twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "privacy", [], "any", false, false, false, 56) == 0)) {
                echo "style=\"display:none;\"";
            }
            echo ">";
            echo gettext("Disable Privacy protection");
            echo "</button>
                                <button class=\"btn btn-primary\" type=\"button\" id=\"domain-enable-pp\" ";
            // line 57
            if ((twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "privacy", [], "any", false, false, false, 57) == 1)) {
                echo "style=\"display:none;\"";
            }
            echo ">";
            echo gettext("Enable Privacy protection");
            echo "</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class=\"tab-pane\" id=\"nameservers\">
                    <h2>";
            // line 64
            echo gettext("Update Nameservers");
            echo "</h2>
                    <div class=\"block\">
                        <p class=\"alert alert-info\">";
            // line 66
            echo gettext("If you would like to host this domain with another company you can update nameservers.");
            echo "</p>
                        <form action=\"\" method=\"post\" id=\"update-nameservers\" class=\"form-horizontal\">
                            <fieldset>
                            <div class=\"control-group\">
                                <label class=\"control-label\" >";
            // line 70
            echo gettext("Nameserver 1");
            echo ": </label>
                                <div class=\"controls\">
                                    <input type=\"text\" name=\"ns1\" value=\"";
            // line 72
            echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ns1", [], "any", true, true, false, 72)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ns1", [], "any", false, false, false, 72), twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "ns1", [], "any", false, false, false, 72))) : (twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "ns1", [], "any", false, false, false, 72))), "html", null, true);
            echo "\" required=\"required\">
                                </div>
                            </div>
                            <div class=\"control-group\">
                                <label class=\"control-label\" >";
            // line 76
            echo gettext("Nameserver 2");
            echo ": </label>
                                <div class=\"controls\">
                                    <input type=\"text\" name=\"ns2\" value=\"";
            // line 78
            echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ns2", [], "any", true, true, false, 78)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ns2", [], "any", false, false, false, 78), twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "ns2", [], "any", false, false, false, 78))) : (twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "ns2", [], "any", false, false, false, 78))), "html", null, true);
            echo "\" required=\"required\">
                                </div>
                            </div>
                            <div class=\"control-group\">
                                <label class=\"control-label\" >";
            // line 82
            echo gettext("Nameserver 3");
            echo ": </label>
                                <div class=\"controls\">
                                    <input type=\"text\" name=\"ns3\" value=\"";
            // line 84
            echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ns3", [], "any", true, true, false, 84)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ns3", [], "any", false, false, false, 84), twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "ns3", [], "any", false, false, false, 84))) : (twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "ns3", [], "any", false, false, false, 84))), "html", null, true);
            echo "\">
                                </div>
                            </div>
                            <div class=\"control-group\">
                                <label class=\"control-label\" >";
            // line 88
            echo gettext("Nameserver 4");
            echo ": </label>
                                <div class=\"controls\">
                                    <input type=\"text\" name=\"ns4\" value=\"";
            // line 90
            echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ns4", [], "any", true, true, false, 90)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ns4", [], "any", false, false, false, 90), twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "ns4", [], "any", false, false, false, 90))) : (twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "ns4", [], "any", false, false, false, 90))), "html", null, true);
            echo "\">
                                </div>
                            </div>
                                <input type=\"hidden\" name=\"order_id\" value=\"";
            // line 93
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "id", [], "any", false, false, false, 93), "html", null, true);
            echo "\">
                            <div class=\"control-group\">
                                <div class=\"controls\">
                                    <button class=\"btn btn-primary\" type=\"submit\" value=\"";
            // line 96
            echo gettext("Update");
            echo "\">";
            echo gettext("Update");
            echo "</button>
                                </div>
                            </div>
                            </fieldset>
                        </form>

                    </div>
                </div>
                <div class=\"tab-pane\" id=\"whois\">
                    <h2>";
            // line 105
            echo gettext("Update domain contact details");
            echo "</h2>
                    <div class=\"block\">
                        <p class=\"alert alert-info\">";
            // line 107
            echo gettext("Domain contact details will be displayed once someone will check WHOIS output (which is public) of your service. This will update Technical, Billing and Admin contacts for this service. You can enable domain privacy protection if you want to hide your public WHOIS information.");
            echo "</p>
                        <form method=\"post\" action=\"\" id=\"update-contact\" class=\"form-horizontal\">
                            <fieldset>
                                <div class=\"control-group\">
                                    <label class=\"control-label\" >";
            // line 111
            echo gettext("First Name");
            echo ": </label>
                                    <div class=\"controls\">
                                        <input type=\"text\" name=\"contact[first_name]\" value=\"";
            // line 113
            echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "first_name", [], "any", true, true, false, 113)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "first_name", [], "any", false, false, false, 113), twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "contact", [], "any", false, false, false, 113), "first_name", [], "any", false, false, false, 113))) : (twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "contact", [], "any", false, false, false, 113), "first_name", [], "any", false, false, false, 113))), "html", null, true);
            echo "\" required=\"required\">
                                    </div>
                                </div>

                                <div class=\"control-group\">
                                    <label class=\"control-label\" >";
            // line 118
            echo gettext("Last Name");
            echo ": </label>
                                    <div class=\"controls\">
                                        <input type=\"text\" name=\"contact[last_name]\" value=\"";
            // line 120
            echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "last_name", [], "any", true, true, false, 120)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "last_name", [], "any", false, false, false, 120), twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "contact", [], "any", false, false, false, 120), "last_name", [], "any", false, false, false, 120))) : (twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "contact", [], "any", false, false, false, 120), "last_name", [], "any", false, false, false, 120))), "html", null, true);
            echo "\" required=\"required\">
                                    </div>
                                </div>

                                <div class=\"control-group\">
                                    <label class=\"control-label\" >";
            // line 125
            echo gettext("Email");
            echo ": </label>
                                    <div class=\"controls\">
                                        <input type=\"text\" name=\"contact[email]\" value=\"";
            // line 127
            echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "email", [], "any", true, true, false, 127)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "email", [], "any", false, false, false, 127), twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "contact", [], "any", false, false, false, 127), "email", [], "any", false, false, false, 127))) : (twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "contact", [], "any", false, false, false, 127), "email", [], "any", false, false, false, 127))), "html", null, true);
            echo "\" required=\"required\">
                                    </div>
                                </div>

                                <div class=\"control-group\">
                                    <label class=\"control-label\" >";
            // line 132
            echo gettext("Company");
            echo ": </label>
                                    <div class=\"controls\">
                                        <input type=\"text\" name=\"contact[company]\" value=\"";
            // line 134
            echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "company", [], "any", true, true, false, 134)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "company", [], "any", false, false, false, 134), twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "contact", [], "any", false, false, false, 134), "company", [], "any", false, false, false, 134))) : (twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "contact", [], "any", false, false, false, 134), "company", [], "any", false, false, false, 134))), "html", null, true);
            echo "\" required=\"required\">
                                    </div>
                                </div>

                                <div class=\"control-group\">
                                    <label class=\"control-label\" >";
            // line 139
            echo gettext("Address Line 1");
            echo ": </label>
                                    <div class=\"controls\">
                                        <input type=\"text\" name=\"contact[address1]\" value=\"";
            // line 141
            echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "address1", [], "any", true, true, false, 141)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "address1", [], "any", false, false, false, 141), twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "contact", [], "any", false, false, false, 141), "address1", [], "any", false, false, false, 141))) : (twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "contact", [], "any", false, false, false, 141), "address1", [], "any", false, false, false, 141))), "html", null, true);
            echo "\" required=\"required\">
                                    </div>
                                </div>

                                <div class=\"control-group\">
                                    <label class=\"control-label\" >";
            // line 146
            echo gettext("Address Line 2");
            echo ": </label>
                                    <div class=\"controls\">
                                        <input type=\"text\" name=\"contact[address2]\" value=\"";
            // line 148
            echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "address2", [], "any", true, true, false, 148)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "address2", [], "any", false, false, false, 148), twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "contact", [], "any", false, false, false, 148), "address2", [], "any", false, false, false, 148))) : (twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "contact", [], "any", false, false, false, 148), "address2", [], "any", false, false, false, 148))), "html", null, true);
            echo "\" required=\"required\">
                                    </div>
                                </div>

                                <div class=\"control-group\">
                                    <label class=\"control-label\" >";
            // line 153
            echo gettext("Country");
            echo ": </label>
                                    <div class=\"controls\">
                                        <input type=\"text\" name=\"contact[country]\" value=\"";
            // line 155
            echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "country", [], "any", true, true, false, 155)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "country", [], "any", false, false, false, 155), twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "contact", [], "any", false, false, false, 155), "country", [], "any", false, false, false, 155))) : (twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "contact", [], "any", false, false, false, 155), "country", [], "any", false, false, false, 155))), "html", null, true);
            echo "\" required=\"required\">
                                    </div>
                                </div>

                                <div class=\"control-group\">
                                    <label class=\"control-label\" >";
            // line 160
            echo gettext("City");
            echo ": </label>
                                    <div class=\"controls\">
                                        <input type=\"text\" name=\"contact[city]\" value=\"";
            // line 162
            echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "city", [], "any", true, true, false, 162)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "city", [], "any", false, false, false, 162), twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "contact", [], "any", false, false, false, 162), "city", [], "any", false, false, false, 162))) : (twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "contact", [], "any", false, false, false, 162), "city", [], "any", false, false, false, 162))), "html", null, true);
            echo "\" required=\"required\">
                                    </div>
                                </div>

                                <div class=\"control-group\">
                                    <label class=\"control-label\" >";
            // line 167
            echo gettext("State");
            echo ": </label>
                                    <div class=\"controls\">
                                        <input type=\"text\" name=\"contact[state]\" value=\"";
            // line 169
            echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "state", [], "any", true, true, false, 169)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "state", [], "any", false, false, false, 169), twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "contact", [], "any", false, false, false, 169), "state", [], "any", false, false, false, 169))) : (twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "contact", [], "any", false, false, false, 169), "state", [], "any", false, false, false, 169))), "html", null, true);
            echo "\" required=\"required\">
                                    </div>
                                </div>

                                <div class=\"control-group\">
                                    <label class=\"control-label\" >";
            // line 174
            echo gettext("Zip");
            echo ": </label>
                                    <div class=\"controls\">
                                        <input type=\"text\" name=\"contact[postcode]\" value=\"";
            // line 176
            echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "postcode", [], "any", true, true, false, 176)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "postcode", [], "any", false, false, false, 176), twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "contact", [], "any", false, false, false, 176), "postcode", [], "any", false, false, false, 176))) : (twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "contact", [], "any", false, false, false, 176), "postcode", [], "any", false, false, false, 176))), "html", null, true);
            echo "\" required=\"required\">
                                    </div>
                                </div>

                                <div class=\"control-group\">
                                    <label class=\"control-label\" >";
            // line 181
            echo gettext("Phone Country Code");
            echo ": </label>
                                    <div class=\"controls\">
                                        <input type=\"text\" name=\"contact[phone_cc]\" value=\"";
            // line 183
            echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "phone_cc", [], "any", true, true, false, 183)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "phone_cc", [], "any", false, false, false, 183), twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "contact", [], "any", false, false, false, 183), "phone_cc", [], "any", false, false, false, 183))) : (twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "contact", [], "any", false, false, false, 183), "phone_cc", [], "any", false, false, false, 183))), "html", null, true);
            echo "\" required=\"required\">
                                    </div>
                                </div>

                                <div class=\"control-group\">
                                    <label class=\"control-label\" >";
            // line 188
            echo gettext("Phone number");
            echo ": </label>
                                    <div class=\"controls\">
                                        <input type=\"text\" name=\"contact[phone]\" value=\"";
            // line 190
            echo twig_escape_filter($this->env, ((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "phone", [], "any", true, true, false, 190)) ? (_twig_default_filter(twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "phone", [], "any", false, false, false, 190), twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "contact", [], "any", false, false, false, 190), "phone", [], "any", false, false, false, 190))) : (twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "contact", [], "any", false, false, false, 190), "phone", [], "any", false, false, false, 190))), "html", null, true);
            echo "\" required=\"required\">
                                    </div>
                                </div>

                                <input type=\"hidden\" name=\"order_id\" value=\"";
            // line 194
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "id", [], "any", false, false, false, 194), "html", null, true);
            echo "\">
                                <div class=\"control-group\">
                                    <div class=\"controls\">
                                        <button class=\"btn btn-primary\" type=\"submit\" value=\"";
            // line 197
            echo gettext("Update");
            echo "\">";
            echo gettext("Update");
            echo "</button>
                                    </div>
                                </div>

                            </fieldset>
                        </form>
                    </div>
                </div>
                <div class=\"tab-pane\" id=\"epp\">
                    <div class=\"block\">
                        <h2>";
            // line 207
            echo gettext("Domain Secret");
            echo "</h2>
                        <p class=\"alert alert-info\">";
            // line 208
            echo gettext("All domain names (except a .EU, .UK domain name) have a Domain (Transfer) Secret Key/Authorization Code (EPP Code) associated with them. This would be required if you want to transfer service.");
            echo "</p>
                        <div class=\"control-group\">
                            <div class=\"controls\">
                                <button class=\"btn btn-primary\" type=\"button\" id=\"get-epp\">";
            // line 211
            echo gettext("Get EPP code");
            echo "</button>
                            </div>
                        </div>
                </div>

            </section>
        </div>
    </article>
</div>
";
            // line 220
            $this->displayBlock('js', $context, $blocks);
        }
    }

    public function block_js($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 221
        echo "<script type=\"text/javascript\">
\$(function() {
    \$('.domain-tabs a').bind('click',function(e){
        e.preventDefault();
        \$(this).tab('show');
    });

    \$('#domain-lock').bind('click',function(event){
        event.preventDefault();
        bb.post(
            'client/servicedomain/lock',
            { order_id: ";
        // line 232
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "id", [], "any", false, false, false, 232), "html", null, true);
        echo " },
            function(result) {
                bb.msg('Domain locked');
                \$('#domain-lock').toggle();
                \$('#domain-unlock').toggle();
            }
        );
    });

    \$('#domain-unlock').bind('click',function(event){
        event.preventDefault();
        bb.post(
            'client/servicedomain/unlock',
            { order_id: ";
        // line 245
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "id", [], "any", false, false, false, 245), "html", null, true);
        echo " },
            function(result) {
                bb.msg('Domain unlocked');
                \$('#domain-lock').toggle();
                \$('#domain-unlock').toggle();
            }
        );
    });

    \$('#domain-enable-pp').bind('click',function(event){
        event.preventDefault();
        bb.post(
            'client/servicedomain/enable_privacy_protection',
            { order_id: ";
        // line 258
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "id", [], "any", false, false, false, 258), "html", null, true);
        echo " },
            function(result) {
                bb.msg('Privacy Protection enabled');
                \$('#domain-enable-pp').toggle();
                \$('#domain-disable-pp').toggle();
            }
        );
    });

    \$('#domain-disable-pp').bind('click',function(event){
        event.preventDefault();
        bb.post(
            'client/servicedomain/disable_privacy_protection',
            { order_id: ";
        // line 271
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "id", [], "any", false, false, false, 271), "html", null, true);
        echo " },
            function(result) {
                bb.msg('Privacy Protection disabled');
                \$('#domain-enable-pp').toggle();
                \$('#domain-disable-pp').toggle();
            }
        );
    });

    \$('#get-epp').bind('click',function(event){
        event.preventDefault();
        bb.post(
            'client/servicedomain/get_transfer_code',
            { order_id: ";
        // line 284
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "id", [], "any", false, false, false, 284), "html", null, true);
        echo " },
            function(result) {
                bb.msg('Domain transfer code is: ' + result);
            }
        );
    });

    \$('#update-nameservers').bind('submit',function(event){
        bb.post(
            'client/servicedomain/update_nameservers',
            \$(this).serialize(),
            function(result) {
                bb.msg('Nameservers updated');
            }
        );
        return false;
    });

    \$('#update-contact').bind('submit',function(event){
        bb.post(
            'client/servicedomain/update_contacts',
            \$(this).serialize(),
            function(result) {
                bb.msg('Contact details updated');
            }
        );
        return false;
    });

});
</script>
";
    }

    public function getTemplateName()
    {
        return "mod_servicedomain_manage.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  562 => 284,  546 => 271,  530 => 258,  514 => 245,  498 => 232,  485 => 221,  477 => 220,  465 => 211,  459 => 208,  455 => 207,  440 => 197,  434 => 194,  427 => 190,  422 => 188,  414 => 183,  409 => 181,  401 => 176,  396 => 174,  388 => 169,  383 => 167,  375 => 162,  370 => 160,  362 => 155,  357 => 153,  349 => 148,  344 => 146,  336 => 141,  331 => 139,  323 => 134,  318 => 132,  310 => 127,  305 => 125,  297 => 120,  292 => 118,  284 => 113,  279 => 111,  272 => 107,  267 => 105,  253 => 96,  247 => 93,  241 => 90,  236 => 88,  229 => 84,  224 => 82,  217 => 78,  212 => 76,  205 => 72,  200 => 70,  193 => 66,  188 => 64,  174 => 57,  166 => 56,  160 => 53,  155 => 51,  142 => 45,  134 => 44,  128 => 41,  124 => 40,  114 => 33,  110 => 32,  103 => 28,  99 => 27,  91 => 24,  87 => 23,  80 => 19,  72 => 14,  68 => 13,  64 => 12,  60 => 11,  56 => 10,  52 => 9,  47 => 7,  40 => 2,  38 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% if order.status == 'active' %}

<div class=\"row\">
    <article class=\"span12 data-block\">
        <div class=\"data-container\">
            <header>
                <h2>{% trans 'Domain management' %}</h2>
                <ul class=\"data-header-actions\">
                    <li class=\"domain-tabs active\"><a href=\"#details\" class=\"btn btn-inverse btn-alt\">{% trans 'Details' %}</a></li>
                    <li class=\"domain-tabs\"><a href=\"#protection\" class=\"btn btn-inverse btn-alt\">{% trans 'Protection' %}</a></li>
                    <li class=\"domain-tabs\"><a href=\"#privacy\" class=\"btn btn-inverse btn-alt\">{% trans 'Privacy' %}</a></li>
                    <li class=\"domain-tabs\"><a href=\"#nameservers\" class=\"btn btn-inverse btn-alt\">{% trans 'Nameservers' %}</a></li>
                    <li class=\"domain-tabs\"><a href=\"#whois\" class=\"btn btn-inverse btn-alt\">{% trans 'Whois' %}</a></li>
                    <li class=\"domain-tabs\"><a href=\"#epp\" class=\"btn btn-inverse btn-alt\">{% trans 'Transfer' %}</a></li>
                </ul>
            </header>
            <section class=\"tab-content\">
                <div class=\"tab-pane active\" id=\"details\">
                    <h2>{% trans 'Domain details' %}</h2>
                    <table class=\"table table-striped table-bordered table-condensed\">
                        <tbody>
                        <tr>
                            <td>{% trans 'Domain' %}</td>
                            <td><a target=\"_blank\" href=\"http://{{ service.domain }}\">{{ service.domain }}</a></td>
                        </tr>
                        <tr>
                            <td>{% trans 'Registration date' %}</td>
                            <td>{{ service.registered_at|bb_date }}</td>
                        </tr>

                        <tr>
                            <td>{% trans 'Expires at' %}</td>
                            <td>{{ service.expires_at|bb_date }}</td>
                        </tr>

                        </tbody>
                    </table>
                </div>
                <div class=\"tab-pane\" id=\"protection\">
                    <h2>{% trans 'Domain Protection' %}</h2>
                    <p class=\"alert alert-info\">{% trans 'Domain locking is a security enhancement to prevent unauthorized transfers of your domain to another registrar or web host by \"locking\" your domain name servers.' %}</p>
                    <div class=\"control-group\">
                        <div class=\"controls\">
                            <button class=\"btn btn-primary\" type=\"button\" id=\"domain-unlock\" {% if service.locked == 0 %}style=\"display:none;\"{% endif %}>{% trans 'Unlock' %}</button>
                            <button class=\"btn btn-primary\" type=\"button\" id=\"domain-lock\" {% if service.locked == 1 %}style=\"display:none;\"{% endif %}>{% trans 'Lock' %}</button>
                        </div>
                    </div>

                </div>
                <div class=\"tab-pane\" id=\"privacy\">
                    <h2>{% trans 'Domain Privacy Settings' %}</h2>
                    <div class=\"block\">
                        <p class=\"alert alert-info\">{% trans 'If you would like to hide domain contact information which is shown on WHOIS you can enable privacy protection. Once domain privacy protection is enabled no one will know who registered this service. And once you decide to disable privacy protection, information that is listed above on \"Update Domain Contact Details\" section will be seen on domain WHOIS again.' %}</p>
                        <div class=\"control-group\">
                            <div class=\"controls\">
                                <button class=\"btn btn-primary\" type=\"button\" id=\"domain-disable-pp\" {% if service.privacy == 0 %}style=\"display:none;\"{% endif %}>{% trans 'Disable Privacy protection' %}</button>
                                <button class=\"btn btn-primary\" type=\"button\" id=\"domain-enable-pp\" {% if service.privacy == 1 %}style=\"display:none;\"{% endif %}>{% trans 'Enable Privacy protection' %}</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class=\"tab-pane\" id=\"nameservers\">
                    <h2>{% trans 'Update Nameservers' %}</h2>
                    <div class=\"block\">
                        <p class=\"alert alert-info\">{% trans 'If you would like to host this domain with another company you can update nameservers.' %}</p>
                        <form action=\"\" method=\"post\" id=\"update-nameservers\" class=\"form-horizontal\">
                            <fieldset>
                            <div class=\"control-group\">
                                <label class=\"control-label\" >{% trans 'Nameserver 1' %}: </label>
                                <div class=\"controls\">
                                    <input type=\"text\" name=\"ns1\" value=\"{{ request.ns1|default(service.ns1) }}\" required=\"required\">
                                </div>
                            </div>
                            <div class=\"control-group\">
                                <label class=\"control-label\" >{% trans 'Nameserver 2' %}: </label>
                                <div class=\"controls\">
                                    <input type=\"text\" name=\"ns2\" value=\"{{ request.ns2|default(service.ns2) }}\" required=\"required\">
                                </div>
                            </div>
                            <div class=\"control-group\">
                                <label class=\"control-label\" >{% trans 'Nameserver 3' %}: </label>
                                <div class=\"controls\">
                                    <input type=\"text\" name=\"ns3\" value=\"{{ request.ns3|default(service.ns3) }}\">
                                </div>
                            </div>
                            <div class=\"control-group\">
                                <label class=\"control-label\" >{% trans 'Nameserver 4' %}: </label>
                                <div class=\"controls\">
                                    <input type=\"text\" name=\"ns4\" value=\"{{ request.ns4|default(service.ns4) }}\">
                                </div>
                            </div>
                                <input type=\"hidden\" name=\"order_id\" value=\"{{ order.id }}\">
                            <div class=\"control-group\">
                                <div class=\"controls\">
                                    <button class=\"btn btn-primary\" type=\"submit\" value=\"{% trans 'Update' %}\">{% trans 'Update' %}</button>
                                </div>
                            </div>
                            </fieldset>
                        </form>

                    </div>
                </div>
                <div class=\"tab-pane\" id=\"whois\">
                    <h2>{% trans 'Update domain contact details' %}</h2>
                    <div class=\"block\">
                        <p class=\"alert alert-info\">{% trans 'Domain contact details will be displayed once someone will check WHOIS output (which is public) of your service. This will update Technical, Billing and Admin contacts for this service. You can enable domain privacy protection if you want to hide your public WHOIS information.' %}</p>
                        <form method=\"post\" action=\"\" id=\"update-contact\" class=\"form-horizontal\">
                            <fieldset>
                                <div class=\"control-group\">
                                    <label class=\"control-label\" >{% trans 'First Name' %}: </label>
                                    <div class=\"controls\">
                                        <input type=\"text\" name=\"contact[first_name]\" value=\"{{ request.first_name|default(service.contact.first_name) }}\" required=\"required\">
                                    </div>
                                </div>

                                <div class=\"control-group\">
                                    <label class=\"control-label\" >{% trans 'Last Name' %}: </label>
                                    <div class=\"controls\">
                                        <input type=\"text\" name=\"contact[last_name]\" value=\"{{ request.last_name|default(service.contact.last_name) }}\" required=\"required\">
                                    </div>
                                </div>

                                <div class=\"control-group\">
                                    <label class=\"control-label\" >{% trans 'Email' %}: </label>
                                    <div class=\"controls\">
                                        <input type=\"text\" name=\"contact[email]\" value=\"{{ request.email|default(service.contact.email) }}\" required=\"required\">
                                    </div>
                                </div>

                                <div class=\"control-group\">
                                    <label class=\"control-label\" >{% trans 'Company' %}: </label>
                                    <div class=\"controls\">
                                        <input type=\"text\" name=\"contact[company]\" value=\"{{ request.company|default(service.contact.company) }}\" required=\"required\">
                                    </div>
                                </div>

                                <div class=\"control-group\">
                                    <label class=\"control-label\" >{% trans 'Address Line 1' %}: </label>
                                    <div class=\"controls\">
                                        <input type=\"text\" name=\"contact[address1]\" value=\"{{ request.address1|default(service.contact.address1) }}\" required=\"required\">
                                    </div>
                                </div>

                                <div class=\"control-group\">
                                    <label class=\"control-label\" >{% trans 'Address Line 2' %}: </label>
                                    <div class=\"controls\">
                                        <input type=\"text\" name=\"contact[address2]\" value=\"{{ request.address2|default(service.contact.address2) }}\" required=\"required\">
                                    </div>
                                </div>

                                <div class=\"control-group\">
                                    <label class=\"control-label\" >{% trans 'Country' %}: </label>
                                    <div class=\"controls\">
                                        <input type=\"text\" name=\"contact[country]\" value=\"{{ request.country|default(service.contact.country) }}\" required=\"required\">
                                    </div>
                                </div>

                                <div class=\"control-group\">
                                    <label class=\"control-label\" >{% trans 'City' %}: </label>
                                    <div class=\"controls\">
                                        <input type=\"text\" name=\"contact[city]\" value=\"{{ request.city|default(service.contact.city) }}\" required=\"required\">
                                    </div>
                                </div>

                                <div class=\"control-group\">
                                    <label class=\"control-label\" >{% trans 'State' %}: </label>
                                    <div class=\"controls\">
                                        <input type=\"text\" name=\"contact[state]\" value=\"{{ request.state|default(service.contact.state) }}\" required=\"required\">
                                    </div>
                                </div>

                                <div class=\"control-group\">
                                    <label class=\"control-label\" >{% trans 'Zip' %}: </label>
                                    <div class=\"controls\">
                                        <input type=\"text\" name=\"contact[postcode]\" value=\"{{ request.postcode|default(service.contact.postcode) }}\" required=\"required\">
                                    </div>
                                </div>

                                <div class=\"control-group\">
                                    <label class=\"control-label\" >{% trans 'Phone Country Code' %}: </label>
                                    <div class=\"controls\">
                                        <input type=\"text\" name=\"contact[phone_cc]\" value=\"{{ request.phone_cc|default(service.contact.phone_cc) }}\" required=\"required\">
                                    </div>
                                </div>

                                <div class=\"control-group\">
                                    <label class=\"control-label\" >{% trans 'Phone number' %}: </label>
                                    <div class=\"controls\">
                                        <input type=\"text\" name=\"contact[phone]\" value=\"{{ request.phone|default(service.contact.phone) }}\" required=\"required\">
                                    </div>
                                </div>

                                <input type=\"hidden\" name=\"order_id\" value=\"{{ order.id }}\">
                                <div class=\"control-group\">
                                    <div class=\"controls\">
                                        <button class=\"btn btn-primary\" type=\"submit\" value=\"{% trans 'Update' %}\">{% trans 'Update' %}</button>
                                    </div>
                                </div>

                            </fieldset>
                        </form>
                    </div>
                </div>
                <div class=\"tab-pane\" id=\"epp\">
                    <div class=\"block\">
                        <h2>{% trans 'Domain Secret' %}</h2>
                        <p class=\"alert alert-info\">{% trans 'All domain names (except a .EU, .UK domain name) have a Domain (Transfer) Secret Key/Authorization Code (EPP Code) associated with them. This would be required if you want to transfer service.' %}</p>
                        <div class=\"control-group\">
                            <div class=\"controls\">
                                <button class=\"btn btn-primary\" type=\"button\" id=\"get-epp\">{% trans 'Get EPP code' %}</button>
                            </div>
                        </div>
                </div>

            </section>
        </div>
    </article>
</div>
{% block js %}
<script type=\"text/javascript\">
\$(function() {
    \$('.domain-tabs a').bind('click',function(e){
        e.preventDefault();
        \$(this).tab('show');
    });

    \$('#domain-lock').bind('click',function(event){
        event.preventDefault();
        bb.post(
            'client/servicedomain/lock',
            { order_id: {{ order.id }} },
            function(result) {
                bb.msg('Domain locked');
                \$('#domain-lock').toggle();
                \$('#domain-unlock').toggle();
            }
        );
    });

    \$('#domain-unlock').bind('click',function(event){
        event.preventDefault();
        bb.post(
            'client/servicedomain/unlock',
            { order_id: {{ order.id }} },
            function(result) {
                bb.msg('Domain unlocked');
                \$('#domain-lock').toggle();
                \$('#domain-unlock').toggle();
            }
        );
    });

    \$('#domain-enable-pp').bind('click',function(event){
        event.preventDefault();
        bb.post(
            'client/servicedomain/enable_privacy_protection',
            { order_id: {{ order.id }} },
            function(result) {
                bb.msg('Privacy Protection enabled');
                \$('#domain-enable-pp').toggle();
                \$('#domain-disable-pp').toggle();
            }
        );
    });

    \$('#domain-disable-pp').bind('click',function(event){
        event.preventDefault();
        bb.post(
            'client/servicedomain/disable_privacy_protection',
            { order_id: {{ order.id }} },
            function(result) {
                bb.msg('Privacy Protection disabled');
                \$('#domain-enable-pp').toggle();
                \$('#domain-disable-pp').toggle();
            }
        );
    });

    \$('#get-epp').bind('click',function(event){
        event.preventDefault();
        bb.post(
            'client/servicedomain/get_transfer_code',
            { order_id: {{ order.id }} },
            function(result) {
                bb.msg('Domain transfer code is: ' + result);
            }
        );
    });

    \$('#update-nameservers').bind('submit',function(event){
        bb.post(
            'client/servicedomain/update_nameservers',
            \$(this).serialize(),
            function(result) {
                bb.msg('Nameservers updated');
            }
        );
        return false;
    });

    \$('#update-contact').bind('submit',function(event){
        bb.post(
            'client/servicedomain/update_contacts',
            \$(this).serialize(),
            function(result) {
                bb.msg('Contact details updated');
            }
        );
        return false;
    });

});
</script>
{% endblock %}
{% endif %}", "mod_servicedomain_manage.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-modules/Servicedomain/html_client/mod_servicedomain_manage.phtml");
    }
}
