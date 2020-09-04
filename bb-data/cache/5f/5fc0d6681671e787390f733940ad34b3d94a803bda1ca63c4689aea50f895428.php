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

/* mod_forum_index.phtml */
class __TwigTemplate_c911dc8dd18d9b65f4a94194deab6458fe941a9adb0a296f2d45e6457c1bae08 extends \Twig\Template
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
        return $this->loadTemplate(((twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "ajax", [], "any", false, false, false, 1)) ? ("layout_blank.phtml") : ("layout_default.phtml")), "mod_forum_index.phtml", 1);
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 2
        $macros["mf"] = $this->macros["mf"] = $this->loadTemplate("macro_functions.phtml", "mod_forum_index.phtml", 2)->unwrap();
        // line 3
        $context["active_menu"] = "support";
        // line 1
        $this->getParent($context)->display($context, array_merge($this->blocks, $blocks));
    }

    // line 4
    public function block_meta_title($context, array $blocks = [])
    {
        $macros = $this->macros;
        echo "Forum";
    }

    // line 6
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 7
        echo "<div class=\"widget simpleTabs\">

    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">";
        // line 10
        echo gettext("Posts");
        echo "</a></li>
        <li><a href=\"#tab-topics\">";
        // line 11
        echo gettext("Topics");
        echo "</a></li>
        <li><a href=\"#tab-forums\">";
        // line 12
        echo gettext("Forums");
        echo "</a></li>
        <li><a href=\"#tab-new\">";
        // line 13
        echo gettext("New topic");
        echo "</a></li>
        <li><a href=\"#tab-new-forum\">";
        // line 14
        echo gettext("New forum");
        echo "</a></li>
    </ul>

    <div class=\"tabs_container\">

        <div class=\"fix\"></div>
        
        <div class=\"tab_content nopadding\" id=\"tab-index\">
        ";
        // line 22
        echo twig_call_macro($macros["mf"], "macro_table_search", [], 22, $context, $this->getSourceContext());
        echo "
        <table class=\"tableStatic wide\">
            <thead>
                <tr>
                    <td colspan=\"2\">";
        // line 26
        echo gettext("Message");
        echo "</td>
                    <td>";
        // line 27
        echo gettext("Date");
        echo "</td>
                    <td style=\"width: 13%\">Actions</td>
                </tr>
            </thead>

            <tbody>
            ";
        // line 33
        $context["posts"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "forum_message_get_list", [0 => twig_array_merge(["per_page" => 30, "page" => twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 33)], ($context["request"] ?? null))], "method", false, false, false, 33);
        // line 34
        echo "            ";
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["posts"] ?? null), "list", [], "any", false, false, false, 34));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["i"] => $context["post"]) {
            // line 35
            echo "            <tr class=\"msg-id-";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["post"], "id", [], "any", false, false, false, 35), "html", null, true);
            echo "\">
                <td style=\"width: 5%;\"><a href=\"";
            // line 36
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("forum/profile");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["post"], "author", [], "any", false, false, false, 36), "id", [], "any", false, false, false, 36), "html", null, true);
            echo "\"><img src=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["post"], "author", [], "any", false, false, false, 36), "gravatar", [], "any", false, false, false, 36), "html", null, true);
            echo "?size=20\" alt=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["post"], "author", [], "any", false, false, false, 36), "id", [], "any", false, false, false, 36), "html", null, true);
            echo "\" /></a></td>
                <td>
                    <a href=\"";
            // line 38
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("forum/topic");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["post"], "forum_topic_id", [], "any", false, false, false, 38), "html", null, true);
            echo "#msg-";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["post"], "id", [], "any", false, false, false, 38), "html", null, true);
            echo "\" target=\"_blank\">";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["post"], "forum_title", [], "any", false, false, false, 38), "html", null, true);
            echo "</a>
                    <br/>
                    ";
            // line 40
            echo twig_escape_filter($this->env, twig_truncate_filter($this->env, twig_get_attribute($this->env, $this->source, $context["post"], "message", [], "any", false, false, false, 40), 80), "html", null, true);
            echo "</td>
                <td>";
            // line 41
            echo twig_escape_filter($this->env, twig_timeago_filter(twig_get_attribute($this->env, $this->source, $context["post"], "created_at", [], "any", false, false, false, 41)), "html", null, true);
            echo " ago</td>
                <td class=\"actions\">
                    <a class=\"bb-button btn14\" href=\"";
            // line 43
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/forum/topic");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["post"], "forum_topic_id", [], "any", false, false, false, 43), "html", null, true);
            echo "#msg-";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["post"], "id", [], "any", false, false, false, 43), "html", null, true);
            echo "\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                    <a class=\"bb-button btn14 bb-rm-tr api-link\" href=\"";
            // line 44
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/forum/message_delete", ["id" => twig_get_attribute($this->env, $this->source, $context["post"], "id", [], "any", false, false, false, 44)]);
            echo "\" data-api-confirm=\"Are you sure?\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                </td>
            </tr>
            
            ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 49
            echo "                <tr>
                    <td colspan=\"4\">
                        ";
            // line 51
            echo gettext("The list is empty");
            // line 52
            echo "                    </td>
                </tr>
            ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['i'], $context['post'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 55
        echo "            </tbody>
        </table>
        ";
        // line 57
        $this->loadTemplate("partial_pagination.phtml", "mod_forum_index.phtml", 57)->display(twig_array_merge($context, ["list" => ($context["posts"] ?? null), "url" => "forum"]));
        // line 58
        echo "        </div>
            
        <div class=\"tab_content nopadding\" id=\"tab-topics\">
            ";
        // line 61
        echo twig_call_macro($macros["mf"], "macro_table_search", [], 61, $context, $this->getSourceContext());
        echo "
            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td style=\"width: 60%\">";
        // line 65
        echo gettext("Title");
        echo "</td>
                        <td>";
        // line 66
        echo gettext("Replies");
        echo "</td>
                        <td>";
        // line 67
        echo gettext("Views");
        echo "</td>
                        <td>";
        // line 68
        echo gettext("Status");
        echo "</td>
                        <td>";
        // line 69
        echo gettext("Sticky");
        echo "</td>
                        <td style=\"width: 13%\">Actions</td>
                    </tr>
                </thead>

                <tbody>
                ";
        // line 75
        $context["topics"] = twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "forum_topic_get_list", [0 => twig_array_merge(["per_page" => 30, "page" => twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "page", [], "any", false, false, false, 75)], ($context["request"] ?? null))], "method", false, false, false, 75);
        // line 76
        echo "                ";
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["topics"] ?? null), "list", [], "any", false, false, false, 76));
        $context['_iterated'] = false;
        foreach ($context['_seq'] as $context["i"] => $context["topic"]) {
            // line 77
            echo "
                <tr>
                    <td>";
            // line 79
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["topic"], "forum", [], "any", false, false, false, 79), "category", [], "any", false, false, false, 79), "html", null, true);
            echo " > ";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["topic"], "forum", [], "any", false, false, false, 79), "title", [], "any", false, false, false, 79), "html", null, true);
            echo " > ";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["topic"], "title", [], "any", false, false, false, 79), "html", null, true);
            echo "</td>
                    <td>";
            // line 80
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["topic"], "stats", [], "any", false, false, false, 80), "posts_count", [], "any", false, false, false, 80), "html", null, true);
            echo "</td>
                    <td>";
            // line 81
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["topic"], "stats", [], "any", false, false, false, 81), "views_count", [], "any", false, false, false, 81), "html", null, true);
            echo "</td>
                    <td>";
            // line 82
            echo twig_call_macro($macros["mf"], "macro_status_name", [twig_get_attribute($this->env, $this->source, $context["topic"], "status", [], "any", false, false, false, 82)], 82, $context, $this->getSourceContext());
            echo "</td>
                    <td>";
            // line 83
            echo twig_call_macro($macros["mf"], "macro_q", [twig_get_attribute($this->env, $this->source, $context["topic"], "sticky", [], "any", false, false, false, 83)], 83, $context, $this->getSourceContext());
            echo "</td>
                    <td class=\"actions\">
                        <a class=\"bb-button btn14\" href=\"";
            // line 85
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/forum/topic");
            echo "/";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["topic"], "id", [], "any", false, false, false, 85), "html", null, true);
            echo "\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                        <a class=\"bb-button btn14 bb-rm-tr api-link\" href=\"";
            // line 86
            echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/forum/topic_delete", ["id" => twig_get_attribute($this->env, $this->source, $context["topic"], "id", [], "any", false, false, false, 86)]);
            echo "\" data-api-confirm=\"Are you sure?\" data-api-redirect=\"";
            echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("forum");
            echo "\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                    </td>
                </tr>
                ";
            $context['_iterated'] = true;
        }
        if (!$context['_iterated']) {
            // line 90
            echo "                    <tr>
                        <td colspan=\"6\">
                            ";
            // line 92
            echo gettext("The list is empty");
            // line 93
            echo "                        </td>
                    </tr>
                ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['i'], $context['topic'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 96
        echo "                </tbody>
            </table>
            ";
        // line 98
        $this->loadTemplate("partial_pagination.phtml", "mod_forum_index.phtml", 98)->display(twig_array_merge($context, ["list" => ($context["topics"] ?? null), "url" => "forum"]));
        // line 99
        echo "        </div>

        <div class=\"tab_content nopadding\" id=\"tab-new\">

            <form method=\"post\" action=\"";
        // line 103
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/forum/topic_create");
        echo "\" class=\"mainForm save api-form\" data-api-redirect=\"";
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("forum");
        echo "\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>";
        // line 106
        echo gettext("Forum");
        echo ":</label>
                        <div class=\"formRight\">
                            ";
        // line 108
        echo twig_call_macro($macros["mf"], "macro_selectbox", ["forum_id", twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "forum_get_pairs", [], "any", false, false, false, 108), twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "forum_id", [], "any", false, false, false, 108), 1], 108, $context, $this->getSourceContext());
        echo "
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 113
        echo gettext("Status");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"radio\" name=\"status\" value=\"active\" checked=\"checked\"/><label>";
        // line 115
        echo gettext("Active");
        echo "</label>
                            <input type=\"radio\" name=\"status\" value=\"locked\" /><label>";
        // line 116
        echo gettext("Locked");
        echo "</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 121
        echo gettext("Sticky");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"radio\" name=\"sticky\" value=\"1\" /><label>";
        // line 123
        echo gettext("Yes");
        echo "</label>
                            <input type=\"radio\" name=\"sticky\" value=\"0\" checked=\"checked\"/><label>";
        // line 124
        echo gettext("No");
        echo "</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 129
        echo gettext("Title");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"title\" value=\"";
        // line 131
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "title", [], "any", false, false, false, 131), "html", null, true);
        echo "\" required=\"required\" placeholder=\"\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <div class=\"rowElem\">
                        <label>";
        // line 137
        echo gettext("Message");
        echo "</label>
                        <div class=\"formRight\">
                            <textarea name=\"message\" cols=\"5\" rows=\"10\">";
        // line 139
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "message", [], "any", false, false, false, 139), "html", null, true);
        echo "</textarea>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <input type=\"submit\" value=\"";
        // line 143
        echo gettext("Create");
        echo "\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-new-forum\">

            <form method=\"post\" action=\"";
        // line 150
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/forum/create");
        echo "\" class=\"mainForm save api-form\" data-api-redirect=\"";
        echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("forum");
        echo "\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>";
        // line 153
        echo gettext("Category");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"category\" value=\"";
        // line 155
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "category", [], "any", false, false, false, 155), "html", null, true);
        echo "\" placeholder=\"\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 160
        echo gettext("Title");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"title\" value=\"";
        // line 162
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "title", [], "any", false, false, false, 162), "html", null, true);
        echo "\" required=\"required\" placeholder=\"\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 167
        echo gettext("Status");
        echo ":</label>
                        <div class=\"formRight\">
                            <input type=\"radio\" name=\"status\" value=\"active\" checked=\"checked\"/><label>";
        // line 169
        echo gettext("Active");
        echo "</label>
                            <input type=\"radio\" name=\"status\" value=\"locked\" /><label>";
        // line 170
        echo gettext("Locked");
        echo "</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>";
        // line 175
        echo gettext("Description");
        echo "</label>
                        <div class=\"formRight\">
                            <textarea name=\"description\" cols=\"5\" rows=\"4\">";
        // line 177
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "description", [], "any", false, false, false, 177), "html", null, true);
        echo "</textarea>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <input type=\"submit\" value=\"";
        // line 181
        echo gettext("Create");
        echo "\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
            <div class=\"fix\"></div>
        </div>
        
        <div class=\"tab_content nopadding\" id=\"tab-forums\">
            <form method=\"post\" action=\"";
        // line 188
        echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/forum/update_priority");
        echo "\" class=\"mainForm api-form\" data-api-reload=\"1\">
                <fieldset>
                    <table class=\"tableStatic wide\">
                        <thead>
                            <tr>
                                <td style=\"width: 5%\">";
        // line 193
        echo gettext("Sorting");
        echo "</td>
                                <td>";
        // line 194
        echo gettext("Title");
        echo "</td>
                                <td>";
        // line 195
        echo gettext("Actions");
        echo "</td>
                            </tr>
                        </thead>
                            
                        <tbody>
                        ";
        // line 200
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["admin"] ?? null), "forum_get_categories", [], "any", false, false, false, 200));
        foreach ($context['_seq'] as $context["category"] => $context["forums"]) {
            // line 201
            echo "                        <tr class=\"group\">
                            <th colspan=\"3\">";
            // line 202
            echo twig_escape_filter($this->env, $context["category"], "html", null, true);
            echo "</th>
                        </tr>
                        ";
            // line 204
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable($context["forums"]);
            foreach ($context['_seq'] as $context["_key"] => $context["forum"]) {
                // line 205
                echo "                        <tr>
                            <td><input type=\"text\" name=\"priority[";
                // line 206
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["forum"], "id", [], "any", false, false, false, 206), "html", null, true);
                echo "]\" value=\"";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["forum"], "priority", [], "any", false, false, false, 206), "html", null, true);
                echo "\" style=\"width:30px;\"></td>
                            <td>";
                // line 207
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["forum"], "title", [], "any", false, false, false, 207), "html", null, true);
                echo "</td>
                            <td class=\"actions\" style=\"width:13%\">
                                <a class=\"bb-button btn14\" href=\"";
                // line 209
                echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("/forum/");
                echo "/";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["forum"], "id", [], "any", false, false, false, 209), "html", null, true);
                echo "\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                                <a class=\"bb-button btn14 bb-rm-tr api-link\" href=\"";
                // line 210
                echo $this->extensions['Box_TwigExtensions']->twig_bb_client_link_filter("api/admin/forum/delete", ["id" => twig_get_attribute($this->env, $this->source, $context["forum"], "id", [], "any", false, false, false, 210)]);
                echo "\" data-api-redirect=\"";
                echo $this->extensions['Box_TwigExtensions']->twig_bb_admin_link_filter("forum");
                echo "\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                            </td>
                        </tr>
                        ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['forum'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 214
            echo "                        ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['category'], $context['forums'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 215
        echo "                        </tbody>

                        <tfoot>
                            <tr>
                                <td colspan=\"3\"></td>
                            </tr>
                        </tfoot>
                    </table>
                    <input type=\"submit\" value=\"";
        // line 223
        echo gettext("Update");
        echo "\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>
        
        <div class=\"fix\"></div>
    </div>
</div>

";
    }

    public function getTemplateName()
    {
        return "mod_forum_index.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  565 => 223,  555 => 215,  549 => 214,  537 => 210,  531 => 209,  526 => 207,  520 => 206,  517 => 205,  513 => 204,  508 => 202,  505 => 201,  501 => 200,  493 => 195,  489 => 194,  485 => 193,  477 => 188,  467 => 181,  460 => 177,  455 => 175,  447 => 170,  443 => 169,  438 => 167,  430 => 162,  425 => 160,  417 => 155,  412 => 153,  404 => 150,  394 => 143,  387 => 139,  382 => 137,  373 => 131,  368 => 129,  360 => 124,  356 => 123,  351 => 121,  343 => 116,  339 => 115,  334 => 113,  326 => 108,  321 => 106,  313 => 103,  307 => 99,  305 => 98,  301 => 96,  293 => 93,  291 => 92,  287 => 90,  276 => 86,  270 => 85,  265 => 83,  261 => 82,  257 => 81,  253 => 80,  245 => 79,  241 => 77,  235 => 76,  233 => 75,  224 => 69,  220 => 68,  216 => 67,  212 => 66,  208 => 65,  201 => 61,  196 => 58,  194 => 57,  190 => 55,  182 => 52,  180 => 51,  176 => 49,  166 => 44,  158 => 43,  153 => 41,  149 => 40,  138 => 38,  127 => 36,  122 => 35,  116 => 34,  114 => 33,  105 => 27,  101 => 26,  94 => 22,  83 => 14,  79 => 13,  75 => 12,  71 => 11,  67 => 10,  62 => 7,  58 => 6,  51 => 4,  47 => 1,  45 => 3,  43 => 2,  36 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("{% extends request.ajax ? \"layout_blank.phtml\" : \"layout_default.phtml\" %}
{% import \"macro_functions.phtml\" as mf %}
{% set active_menu = 'support' %}
{% block meta_title %}Forum{% endblock %}

{% block content %}
<div class=\"widget simpleTabs\">

    <ul class=\"tabs\">
        <li><a href=\"#tab-index\">{% trans 'Posts' %}</a></li>
        <li><a href=\"#tab-topics\">{% trans 'Topics' %}</a></li>
        <li><a href=\"#tab-forums\">{% trans 'Forums' %}</a></li>
        <li><a href=\"#tab-new\">{% trans 'New topic' %}</a></li>
        <li><a href=\"#tab-new-forum\">{% trans 'New forum' %}</a></li>
    </ul>

    <div class=\"tabs_container\">

        <div class=\"fix\"></div>
        
        <div class=\"tab_content nopadding\" id=\"tab-index\">
        {{ mf.table_search }}
        <table class=\"tableStatic wide\">
            <thead>
                <tr>
                    <td colspan=\"2\">{% trans 'Message' %}</td>
                    <td>{% trans 'Date' %}</td>
                    <td style=\"width: 13%\">Actions</td>
                </tr>
            </thead>

            <tbody>
            {% set posts = admin.forum_message_get_list({\"per_page\":30, \"page\":request.page}|merge(request)) %}
            {% for i, post in posts.list %}
            <tr class=\"msg-id-{{post.id}}\">
                <td style=\"width: 5%;\"><a href=\"{{ 'forum/profile'|alink }}/{{post.author.id}}\"><img src=\"{{  post.author.gravatar }}?size=20\" alt=\"{{post.author.id}}\" /></a></td>
                <td>
                    <a href=\"{{ 'forum/topic'|alink }}/{{post.forum_topic_id}}#msg-{{post.id}}\" target=\"_blank\">{{post.forum_title}}</a>
                    <br/>
                    {{ post.message|truncate(80) }}</td>
                <td>{{ post.created_at|timeago }} ago</td>
                <td class=\"actions\">
                    <a class=\"bb-button btn14\" href=\"{{ '/forum/topic'|alink }}/{{post.forum_topic_id}}#msg-{{post.id}}\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                    <a class=\"bb-button btn14 bb-rm-tr api-link\" href=\"{{ 'api/admin/forum/message_delete'|link({'id' : post.id})}}\" data-api-confirm=\"Are you sure?\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                </td>
            </tr>
            
            {% else %}
                <tr>
                    <td colspan=\"4\">
                        {% trans 'The list is empty' %}
                    </td>
                </tr>
            {% endfor %}
            </tbody>
        </table>
        {% include \"partial_pagination.phtml\" with {'list': posts, 'url':'forum'} %}
        </div>
            
        <div class=\"tab_content nopadding\" id=\"tab-topics\">
            {{ mf.table_search }}
            <table class=\"tableStatic wide\">
                <thead>
                    <tr>
                        <td style=\"width: 60%\">{% trans 'Title' %}</td>
                        <td>{% trans 'Replies' %}</td>
                        <td>{% trans 'Views' %}</td>
                        <td>{% trans 'Status' %}</td>
                        <td>{% trans 'Sticky' %}</td>
                        <td style=\"width: 13%\">Actions</td>
                    </tr>
                </thead>

                <tbody>
                {% set topics = admin.forum_topic_get_list({\"per_page\":30, \"page\":request.page}|merge(request)) %}
                {% for i, topic in topics.list %}

                <tr>
                    <td>{{ topic.forum.category }} > {{ topic.forum.title }} > {{ topic.title }}</td>
                    <td>{{ topic.stats.posts_count }}</td>
                    <td>{{ topic.stats.views_count }}</td>
                    <td>{{ mf.status_name(topic.status) }}</td>
                    <td>{{ mf.q(topic.sticky) }}</td>
                    <td class=\"actions\">
                        <a class=\"bb-button btn14\" href=\"{{ '/forum/topic'|alink }}/{{topic.id}}\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                        <a class=\"bb-button btn14 bb-rm-tr api-link\" href=\"{{'api/admin/forum/topic_delete'|link({'id' : topic.id}) }}\" data-api-confirm=\"Are you sure?\" data-api-redirect=\"{{ 'forum'|alink }}\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                    </td>
                </tr>
                {% else %}
                    <tr>
                        <td colspan=\"6\">
                            {% trans 'The list is empty' %}
                        </td>
                    </tr>
                {% endfor %}
                </tbody>
            </table>
            {% include \"partial_pagination.phtml\" with {'list': topics, 'url':'forum'} %}
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-new\">

            <form method=\"post\" action=\"{{ 'api/admin/forum/topic_create'|link }}\" class=\"mainForm save api-form\" data-api-redirect=\"{{ 'forum'|alink }}\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>{% trans 'Forum' %}:</label>
                        <div class=\"formRight\">
                            {{ mf.selectbox('forum_id', admin.forum_get_pairs, request.forum_id, 1) }}
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Status' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"radio\" name=\"status\" value=\"active\" checked=\"checked\"/><label>{% trans 'Active' %}</label>
                            <input type=\"radio\" name=\"status\" value=\"locked\" /><label>{% trans 'Locked' %}</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Sticky' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"radio\" name=\"sticky\" value=\"1\" /><label>{% trans 'Yes' %}</label>
                            <input type=\"radio\" name=\"sticky\" value=\"0\" checked=\"checked\"/><label>{% trans 'No' %}</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Title' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"title\" value=\"{{request.title}}\" required=\"required\" placeholder=\"\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    
                    <div class=\"rowElem\">
                        <label>{% trans 'Message' %}</label>
                        <div class=\"formRight\">
                            <textarea name=\"message\" cols=\"5\" rows=\"10\">{{ request.message }}</textarea>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <input type=\"submit\" value=\"{% trans 'Create' %}\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>

        <div class=\"tab_content nopadding\" id=\"tab-new-forum\">

            <form method=\"post\" action=\"{{ 'api/admin/forum/create'|link }}\" class=\"mainForm save api-form\" data-api-redirect=\"{{ 'forum'|alink }}\">
                <fieldset>
                    <div class=\"rowElem noborder\">
                        <label>{% trans 'Category' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"category\" value=\"{{request.category}}\" placeholder=\"\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Title' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"text\" name=\"title\" value=\"{{request.title}}\" required=\"required\" placeholder=\"\"/>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Status' %}:</label>
                        <div class=\"formRight\">
                            <input type=\"radio\" name=\"status\" value=\"active\" checked=\"checked\"/><label>{% trans 'Active' %}</label>
                            <input type=\"radio\" name=\"status\" value=\"locked\" /><label>{% trans 'Locked' %}</label>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <div class=\"rowElem\">
                        <label>{% trans 'Description' %}</label>
                        <div class=\"formRight\">
                            <textarea name=\"description\" cols=\"5\" rows=\"4\">{{ request.description }}</textarea>
                        </div>
                        <div class=\"fix\"></div>
                    </div>
                    <input type=\"submit\" value=\"{% trans 'Create' %}\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
            <div class=\"fix\"></div>
        </div>
        
        <div class=\"tab_content nopadding\" id=\"tab-forums\">
            <form method=\"post\" action=\"{{ 'api/admin/forum/update_priority'|link }}\" class=\"mainForm api-form\" data-api-reload=\"1\">
                <fieldset>
                    <table class=\"tableStatic wide\">
                        <thead>
                            <tr>
                                <td style=\"width: 5%\">{% trans 'Sorting' %}</td>
                                <td>{% trans 'Title' %}</td>
                                <td>{% trans 'Actions' %}</td>
                            </tr>
                        </thead>
                            
                        <tbody>
                        {% for category, forums in admin.forum_get_categories %}
                        <tr class=\"group\">
                            <th colspan=\"3\">{{category}}</th>
                        </tr>
                        {% for forum in forums %}
                        <tr>
                            <td><input type=\"text\" name=\"priority[{{forum.id}}]\" value=\"{{ forum.priority }}\" style=\"width:30px;\"></td>
                            <td>{{forum.title}}</td>
                            <td class=\"actions\" style=\"width:13%\">
                                <a class=\"bb-button btn14\" href=\"{{ '/forum/'|alink }}/{{forum.id}}\"><img src=\"images/icons/dark/pencil.png\" alt=\"\"></a>
                                <a class=\"bb-button btn14 bb-rm-tr api-link\" href=\"{{'api/admin/forum/delete'|link({'id' : forum.id}) }}\" data-api-redirect=\"{{ 'forum'|alink }}\"><img src=\"images/icons/dark/trash.png\" alt=\"\"></a>
                            </td>
                        </tr>
                        {% endfor %}
                        {% endfor %}
                        </tbody>

                        <tfoot>
                            <tr>
                                <td colspan=\"3\"></td>
                            </tr>
                        </tfoot>
                    </table>
                    <input type=\"submit\" value=\"{% trans 'Update' %}\" class=\"greyishBtn submitForm\" />
                </fieldset>
            </form>
        </div>
        
        <div class=\"fix\"></div>
    </div>
</div>

{% endblock %}
", "mod_forum_index.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/bb-modules/Forum/html_admin/mod_forum_index.phtml");
    }
}
