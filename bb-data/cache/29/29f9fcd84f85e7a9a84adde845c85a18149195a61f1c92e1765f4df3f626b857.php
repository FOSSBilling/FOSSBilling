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

/* __string_template__a5fa9baca248410a0311f6bf2d5c0dc7ad007aa35f31f4df6cd1cbf2f87e713b */
class __TwigTemplate_359256d97645d709ff1be09031fdc69befb913a4a9d3bbcfaf0cef34d4f5699a extends \Twig\Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
            '__internal_fda75832a1532316ef7f49b0da6a17990f7f9d1dda9c017f712c387dfdf1b218' => [$this, 'block___internal_fda75832a1532316ef7f49b0da6a17990f7f9d1dda9c017f712c387dfdf1b218'],
        ];
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 1
        echo "
";
        // line 2
        echo twig_markdown_filter($this->env,         $this->renderBlock("__internal_fda75832a1532316ef7f49b0da6a17990f7f9d1dda9c017f712c387dfdf1b218", $context, $blocks));
    }

    public function block___internal_fda75832a1532316ef7f49b0da6a17990f7f9d1dda9c017f712c387dfdf1b218($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 3
        echo "
Hello ";
        // line 4
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["c"] ?? null), "first_name", [], "any", false, false, false, 4), "html", null, true);
        echo " ";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["c"] ?? null), "last_name", [], "any", false, false, false, 4), "html", null, true);
        echo ",

Thank you for ordering with us! Your hosting account has now been set up. This email contains all the information you will need in order to begin using your service.

If you have requested a domain name during the signup, please keep in mind that 
your domain name will not be visible  on the internet instantly. 
This process is called propagation and can take up to 48 hours. 
Your website and email will not function until your domain has propagated.

**";
        // line 13
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "title", [], "any", false, false, false, 13), "html", null, true);
        echo "**

Activated: ";
        // line 15
        echo twig_escape_filter($this->env, $this->extensions['Box_TwigExtensions']->twig_bb_date(twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "activated_at", [], "any", false, false, false, 15)), "html", null, true);
        echo "
";
        // line 16
        if (twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "expires_at", [], "any", false, false, false, 16)) {
            echo "Expires: ";
            echo twig_escape_filter($this->env, $this->extensions['Box_TwigExtensions']->twig_bb_date(twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "expires_at", [], "any", false, false, false, 16)), "html", null, true);
            echo " ";
        }
        // line 17
        if (twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "period", [], "any", false, false, false, 17)) {
            // line 18
            echo "Billing period:

";
            // line 20
            echo twig_money($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "total", [], "any", false, false, false, 20), twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "currency", [], "any", false, false, false, 20));
            echo "
";
            // line 21
            echo twig_period_title($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "period", [], "any", false, false, false, 21));
            echo "

";
        }
        // line 23
        echo "       

**New Account Information**


Hosting Package: ";
        // line 28
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "hosting_plan", [], "any", false, false, false, 28), "name", [], "any", false, false, false, 28), "html", null, true);
        echo "    
Domain: ";
        // line 29
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "domain", [], "any", false, false, false, 29), "html", null, true);
        echo "    
IP Address: ";
        // line 30
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "server", [], "any", false, false, false, 30), "ip", [], "any", false, false, false, 30), "html", null, true);
        echo "


**Control Panel Login Details**

Username: ";
        // line 35
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "username", [], "any", false, false, false, 35), "html", null, true);
        echo "    
Password: ";
        // line 36
        echo twig_escape_filter($this->env, ($context["password"] ?? null), "html", null, true);
        echo "     
Control Panel URL: ";
        // line 37
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "server", [], "any", false, false, false, 37), "cpanel_url", [], "any", false, false, false, 37), "html", null, true);
        echo "


**Server Information**

Server Name: ";
        // line 42
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "server", [], "any", false, false, false, 42), "name", [], "any", false, false, false, 42), "html", null, true);
        echo "     
Server IP: ";
        // line 43
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "server", [], "any", false, false, false, 43), "ip", [], "any", false, false, false, 43), "html", null, true);
        echo "

If you are using an existing domain with your new hosting account, you  will 
need to update the domain settings to point it to the nameservers listed below.

Nameserver 1: ";
        // line 48
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "server", [], "any", false, false, false, 48), "ns1", [], "any", false, false, false, 48), "html", null, true);
        echo "   
Nameserver 2: ";
        // line 49
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "server", [], "any", false, false, false, 49), "ns2", [], "any", false, false, false, 49), "html", null, true);
        echo "   
";
        // line 50
        if (twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "server", [], "any", false, false, false, 50), "ns3", [], "any", false, false, false, 50)) {
            echo "Nameserver 3: ";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "server", [], "any", false, false, false, 50), "ns3", [], "any", false, false, false, 50), "html", null, true);
            echo "   ";
        }
        // line 51
        if (twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "server", [], "any", false, false, false, 51), "ns4", [], "any", false, false, false, 51)) {
            echo "Nameserver 4: ";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "server", [], "any", false, false, false, 51), "ns4", [], "any", false, false, false, 51), "html", null, true);
            echo "   ";
        }
        // line 52
        echo "
**Uploading Your Website**


You may use one of the addresses given below to manage your web site:


Temporary FTP Hostname: ";
        // line 59
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "server", [], "any", false, false, false, 59), "ip", [], "any", false, false, false, 59), "html", null, true);
        echo "    
Full FTP Hostname: ";
        // line 60
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "domain", [], "any", false, false, false, 60), "html", null, true);
        echo "    
FTP Username: ";
        // line 61
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["service"] ?? null), "username", [], "any", false, false, false, 61), "html", null, true);
        echo "    
FTP Password: ";
        // line 62
        echo twig_escape_filter($this->env, ($context["password"] ?? null), "html", null, true);
        echo "    

You must upload files to the **public_html** folder!
Thank you for choosing us.


Login to members area: ";
        // line 68
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("login", ["email" => twig_get_attribute($this->env, $this->source, ($context["c"] ?? null), "email", [], "any", false, false, false, 68)]);
        echo "
Manage order: ";
        // line 69
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("order/service/manage");
        echo "/";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["order"] ?? null), "id", [], "any", false, false, false, 69), "html", null, true);
        echo "

";
        // line 71
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_company", [], "any", false, false, false, 71), "signature", [], "any", false, false, false, 71), "html", null, true);
        echo "

";
    }

    public function getTemplateName()
    {
        return "__string_template__a5fa9baca248410a0311f6bf2d5c0dc7ad007aa35f31f4df6cd1cbf2f87e713b";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  208 => 71,  201 => 69,  197 => 68,  188 => 62,  184 => 61,  180 => 60,  176 => 59,  167 => 52,  161 => 51,  155 => 50,  151 => 49,  147 => 48,  139 => 43,  135 => 42,  127 => 37,  123 => 36,  119 => 35,  111 => 30,  107 => 29,  103 => 28,  96 => 23,  90 => 21,  86 => 20,  82 => 18,  80 => 17,  74 => 16,  70 => 15,  65 => 13,  51 => 4,  48 => 3,  41 => 2,  38 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("
{% filter markdown %}

Hello {{ c.first_name }} {{ c.last_name }},

Thank you for ordering with us! Your hosting account has now been set up. This email contains all the information you will need in order to begin using your service.

If you have requested a domain name during the signup, please keep in mind that 
your domain name will not be visible  on the internet instantly. 
This process is called propagation and can take up to 48 hours. 
Your website and email will not function until your domain has propagated.

**{{ order.title }}**

Activated: {{ order.activated_at|bb_date }}
{% if order.expires_at %}Expires: {{ order.expires_at|bb_date }} {% endif %}
{% if order.period %}
Billing period:

{{ order.total | money(order.currency) }}
{{ order.period | period_title }}

{% endif %}       

**New Account Information**


Hosting Package: {{ service.hosting_plan.name }}    
Domain: {{ service.domain }}    
IP Address: {{ service.server.ip }}


**Control Panel Login Details**

Username: {{ service.username }}    
Password: {{ password }}     
Control Panel URL: {{ service.server.cpanel_url }}


**Server Information**

Server Name: {{ service.server.name }}     
Server IP: {{ service.server.ip }}

If you are using an existing domain with your new hosting account, you  will 
need to update the domain settings to point it to the nameservers listed below.

Nameserver 1: {{ service.server.ns1 }}   
Nameserver 2: {{ service.server.ns2 }}   
{% if  service.server.ns3 %}Nameserver 3: {{ service.server.ns3 }}   {% endif %}
{% if  service.server.ns4 %}Nameserver 4: {{ service.server.ns4 }}   {% endif %}

**Uploading Your Website**


You may use one of the addresses given below to manage your web site:


Temporary FTP Hostname: {{ service.server.ip }}    
Full FTP Hostname: {{ service.domain }}    
FTP Username: {{ service.username }}    
FTP Password: {{ password }}    

You must upload files to the **public_html** folder!
Thank you for choosing us.


Login to members area: {{'login'|link({'email' : c.email }) }}
Manage order: {{ 'order/service/manage'|link }}/{{ order.id }}

{{ guest.system_company.signature }}

{% endfilter %}
", "__string_template__a5fa9baca248410a0311f6bf2d5c0dc7ad007aa35f31f4df6cd1cbf2f87e713b", "");
    }
}
