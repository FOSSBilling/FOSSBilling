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

/* partial_menu.phtml */
class __TwigTemplate_75ff88e865bdceae26edaa64802147b8d6d844353f3d21413189bd07a911cdc1 extends Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
        ];
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 1
        echo "<ul class=\"nav main\">
    ";
        // line 2
        if ( !($context["client"] ?? null)) {
            // line 3
            echo "    <li>
        <a class=\"dark-icon i-home\" href=\"";
            // line 4
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("");
            echo "\">";
            echo twig_escape_filter($this->env, gettext("Home"), "html", null, true);
            echo "</a>
    </li>
    ";
        }
        // line 7
        echo "    <li>
        <a class=\"dark-icon i-order\" href=\"";
        // line 8
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/order");
        echo "\">";
        echo twig_escape_filter($this->env, gettext("Order"), "html", null, true);
        echo "</a>
    </li>

    ";
        // line 11
        if (twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "extension_is_on", [0 => ["mod" => "news"]], "method", false, false, false, 11)) {
            // line 12
            echo "    <li>
        <a class=\"dark-icon i-blog\" href=\"";
            // line 13
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/news");
            echo "\">";
            echo twig_escape_filter($this->env, gettext("Blog"), "html", null, true);
            echo "</a>
    </li>
    ";
        }
        // line 16
        echo "    
    ";
        // line 17
        if (twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "extension_is_on", [0 => ["mod" => "kb"]], "method", false, false, false, 17)) {
            // line 18
            echo "    <li>
        <a class=\"dark-icon i-kb\" href=\"";
            // line 19
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/kb");
            echo "\">";
            echo twig_escape_filter($this->env, gettext("Knowledge Base"), "html", null, true);
            echo "</a>
    </li>
    ";
        }
        // line 22
        echo "
    ";
        // line 23
        if (twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "extension_is_on", [0 => ["mod" => "forum"]], "method", false, false, false, 23)) {
            // line 24
            echo "    <li>
        <a class=\"dark-icon i-forum\" href=\"";
            // line 25
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/forum");
            echo "\">";
            echo twig_escape_filter($this->env, gettext("Forum"), "html", null, true);
            echo "</a>
    </li>
    ";
        }
        // line 28
        echo "    
    <li>
        <a class=\"dark-icon i-contacts\" href=\"";
        // line 30
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/support/contact-us");
        echo "\">";
        echo twig_escape_filter($this->env, gettext("Contact us"), "html", null, true);
        echo "</a>
    </li>

    ";
        // line 33
        if (($context["client"] ?? null)) {
            // line 34
            echo "    <li>
        <a class=\"dark-icon i-support\" href=\"";
            // line 35
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/support");
            echo "\">";
            echo twig_escape_filter($this->env, gettext("Support"), "html", null, true);
            echo "</a>
    </li>
    <li>
        <a class=\"dark-icon i-services\" href=\"";
            // line 38
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/order/service");
            echo "\">";
            echo twig_escape_filter($this->env, gettext("Services"), "html", null, true);
            echo "</a>
    </li>
    
    <li>
        <a class=\"dark-icon i-invoice\" href=\"";
            // line 42
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/invoice");
            echo "\">";
            echo twig_escape_filter($this->env, gettext("Invoices"), "html", null, true);
            echo "</a>
    </li>
    <li>
        <a class=\"dark-icon i-email\" href=\"";
            // line 45
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/email");
            echo "\">";
            echo twig_escape_filter($this->env, gettext("Emails"), "html", null, true);
            echo "</a>
    </li>

    <li>
        <a class=\"dark-icon i-payment\" href=\"";
            // line 49
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("/client/balance");
            echo "\">";
            echo twig_escape_filter($this->env, gettext("Payment history"), "html", null, true);
            echo "</a>
    </li>
    
    ";
            // line 61
            echo "    ";
        } else {
            // line 62
            echo "    ";
            // line 67
            echo "    ";
        }
        // line 68
        echo "</ul>";
    }

    public function getTemplateName()
    {
        return "partial_menu.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  176 => 68,  173 => 67,  171 => 62,  168 => 61,  160 => 49,  151 => 45,  143 => 42,  134 => 38,  126 => 35,  123 => 34,  121 => 33,  113 => 30,  109 => 28,  101 => 25,  98 => 24,  96 => 23,  93 => 22,  85 => 19,  82 => 18,  80 => 17,  77 => 16,  69 => 13,  66 => 12,  64 => 11,  56 => 8,  53 => 7,  45 => 4,  42 => 3,  40 => 2,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("<ul class=\"nav main\">
    {% if not client %}
    <li>
        <a class=\"dark-icon i-home\" href=\"{{ ''|link }}\">{{ 'Home'|trans }}</a>
    </li>
    {% endif %}
    <li>
        <a class=\"dark-icon i-order\" href=\"{{ '/order'|link }}\">{{ 'Order'|trans }}</a>
    </li>

    {% if guest.extension_is_on({\"mod\":\"news\"}) %}
    <li>
        <a class=\"dark-icon i-blog\" href=\"{{ '/news'|link }}\">{{ 'Blog'|trans }}</a>
    </li>
    {% endif %}
    
    {% if guest.extension_is_on({\"mod\":\"kb\"}) %}
    <li>
        <a class=\"dark-icon i-kb\" href=\"{{ '/kb'|link }}\">{{ 'Knowledge Base'|trans }}</a>
    </li>
    {% endif %}

    {% if guest.extension_is_on({\"mod\":\"forum\"}) %}
    <li>
        <a class=\"dark-icon i-forum\" href=\"{{ '/forum'|link }}\">{{ 'Forum'|trans }}</a>
    </li>
    {% endif %}
    
    <li>
        <a class=\"dark-icon i-contacts\" href=\"{{ '/support/contact-us'|link }}\">{{ 'Contact us'|trans }}</a>
    </li>

    {% if client %}
    <li>
        <a class=\"dark-icon i-support\" href=\"{{ '/support'|link }}\">{{ 'Support'|trans }}</a>
    </li>
    <li>
        <a class=\"dark-icon i-services\" href=\"{{ '/order/service'|link }}\">{{ 'Services'|trans }}</a>
    </li>
    
    <li>
        <a class=\"dark-icon i-invoice\" href=\"{{ '/invoice'|link }}\">{{ 'Invoices'|trans }}</a>
    </li>
    <li>
        <a class=\"dark-icon i-email\" href=\"{{ '/email'|link }}\">{{ 'Emails'|trans }}</a>
    </li>

    <li>
        <a class=\"dark-icon i-payment\" href=\"{{ '/client/balance'|link }}\">{{ 'Payment history'|trans }}</a>
    </li>
    
    {#
    <li>
        <a class=\"dark-icon i-profile\" href=\"{{ '/client/me'|link }}\">{{ 'Profile'|trans }}</a>
    </li>

    <li>
        <a class=\"dark-icon i-logout api\" href=\"client/client/logout\">{{ 'Logout'|trans }}</a>
    </li>
    #}
    {% else %}
    {#
    <li class=\"secondary\">
        <a class=\"dark-icon i-profile\" href=\"{{ '/login'|link }}\">{{ 'Sign in / Register'|trans }}</a>
    </li>
    #}
    {% endif %}
</ul>", "partial_menu.phtml", "/shared/httpd/up-boxbilling/FOSSBilling/src/bb-themes/boxbilling/html/partial_menu.phtml");
    }
}
