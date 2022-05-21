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

/* macro_functions.phtml */
class __TwigTemplate_01b85c04a93ab03da87730ad09ae587a6493f01dfbeda15ccee791f7b0182e52 extends Template
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
        // line 8
        echo "
";
        // line 17
        echo "
";
        // line 25
        echo "
";
        // line 38
        echo "
";
        // line 76
        echo "
";
        // line 93
        echo "
";
        // line 117
        echo "
";
        // line 119
        echo "
";
        // line 123
        echo "
";
        // line 130
        echo "
";
    }

    // line 1
    public function macro_q($__bool__ = null, ...$__varargs__)
    {
        $macros = $this->macros;
        $context = $this->env->mergeGlobals([
            "bool" => $__bool__,
            "varargs" => $__varargs__,
        ]);

        $blocks = [];

        ob_start();
        try {
            // line 2
            if (($context["bool"] ?? null)) {
                // line 3
                echo "    ";
                echo twig_escape_filter($this->env, gettext("Yes"), "html", null, true);
                echo "
";
            } else {
                // line 5
                echo "    ";
                echo twig_escape_filter($this->env, gettext("No"), "html", null, true);
                echo "
";
            }

            return ('' === $tmp = ob_get_contents()) ? '' : new Markup($tmp, $this->env->getCharset());
        } finally {
            ob_end_clean();
        }
    }

    // line 9
    public function macro_selectbox($__name__ = null, $__options__ = null, $__selected__ = null, $__required__ = null, $__nullOption__ = null, ...$__varargs__)
    {
        $macros = $this->macros;
        $context = $this->env->mergeGlobals([
            "name" => $__name__,
            "options" => $__options__,
            "selected" => $__selected__,
            "required" => $__required__,
            "nullOption" => $__nullOption__,
            "varargs" => $__varargs__,
        ]);

        $blocks = [];

        ob_start();
        try {
            // line 10
            echo "    <select name=\"";
            echo twig_escape_filter($this->env, ($context["name"] ?? null), "html", null, true);
            echo "\" id=\"";
            echo twig_escape_filter($this->env, ($context["name"] ?? null), "html", null, true);
            echo "\" ";
            if (($context["required"] ?? null)) {
                echo "required=\"required\"";
            }
            echo ">
        ";
            // line 11
            if (($context["nullOption"] ?? null)) {
                echo "<option value=\"\">-- ";
                echo twig_escape_filter($this->env, ($context["nullOption"] ?? null), "html", null, true);
                echo " --</option>";
            }
            // line 12
            echo "        ";
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(($context["options"] ?? null));
            foreach ($context['_seq'] as $context["val"] => $context["label"]) {
                // line 13
                echo "        <option value=\"";
                echo twig_escape_filter($this->env, $context["val"], "html", null, true);
                echo "\" label=\"";
                echo twig_escape_filter($this->env, $context["label"]);
                echo "\" ";
                if ((($context["selected"] ?? null) == $context["val"])) {
                    echo "selected=\"selected\"";
                }
                echo ">";
                echo twig_escape_filter($this->env, $context["label"]);
                echo "</option>
        ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['val'], $context['label'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 15
            echo "    </select>
";

            return ('' === $tmp = ob_get_contents()) ? '' : new Markup($tmp, $this->env->getCharset());
        } finally {
            ob_end_clean();
        }
    }

    // line 18
    public function macro_selectboxtld($__name__ = null, $__options__ = null, $__selected__ = null, $__required__ = null, ...$__varargs__)
    {
        $macros = $this->macros;
        $context = $this->env->mergeGlobals([
            "name" => $__name__,
            "options" => $__options__,
            "selected" => $__selected__,
            "required" => $__required__,
            "varargs" => $__varargs__,
        ]);

        $blocks = [];

        ob_start();
        try {
            // line 19
            echo "    <select name=\"";
            echo twig_escape_filter($this->env, ($context["name"] ?? null), "html", null, true);
            echo "\" ";
            if (($context["required"] ?? null)) {
                echo "required=\"required\"";
            }
            echo " style=\"width:80px;\">
        ";
            // line 20
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(($context["options"] ?? null));
            foreach ($context['_seq'] as $context["_key"] => $context["data"]) {
                // line 21
                echo "        <option value=\"";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["data"], "tld", [], "any", false, false, false, 21), "html", null, true);
                echo "\" label=\"";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["data"], "tld", [], "any", false, false, false, 21), "html", null, true);
                echo "\" ";
                if ((($context["selected"] ?? null) == twig_get_attribute($this->env, $this->source, $context["data"], "tld", [], "any", false, false, false, 21))) {
                    echo "selected=\"selected\"";
                }
                echo ">";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["data"], "tld", [], "any", false, false, false, 21), "html", null, true);
                echo "</option>
        ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['data'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 23
            echo "    </select>
";

            return ('' === $tmp = ob_get_contents()) ? '' : new Markup($tmp, $this->env->getCharset());
        } finally {
            ob_end_clean();
        }
    }

    // line 26
    public function macro_table_search(...$__varargs__)
    {
        $macros = $this->macros;
        $context = $this->env->mergeGlobals([
            "varargs" => $__varargs__,
        ]);

        $blocks = [];

        ob_start();
        try {
            // line 27
            echo "<div style=\"position: relative;\">
    <div class=\"dataTables_filter\">
        <form method=\"get\" action=\"\">
            <input type=\"hidden\" name=\"_url\" value=\"";
            // line 30
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "_url", [], "any", false, false, false, 30), "html", null, true);
            echo "\"/>
            <label>";
            // line 31
            echo twig_escape_filter($this->env, gettext("Search:"), "html", null, true);
            echo " <input type=\"text\" name=\"search\" placeholder=\"";
            echo twig_escape_filter($this->env, gettext("Enter search text.."), "html", null, true);
            echo "\" value=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "search", [], "any", false, false, false, 31), "html", null, true);
            echo "\">
                <div class=\"srch\"></div>
            </label>
        </form>
    </div>
</div>
";

            return ('' === $tmp = ob_get_contents()) ? '' : new Markup($tmp, $this->env->getCharset());
        } finally {
            ob_end_clean();
        }
    }

    // line 39
    public function macro_build_form($__elements__ = null, $__values__ = null, ...$__varargs__)
    {
        $macros = $this->macros;
        $context = $this->env->mergeGlobals([
            "elements" => $__elements__,
            "values" => $__values__,
            "varargs" => $__varargs__,
        ]);

        $blocks = [];

        ob_start();
        try {
            // line 40
            echo "    ";
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(($context["elements"] ?? null));
            foreach ($context['_seq'] as $context["name"] => $context["element"]) {
                // line 41
                echo "    <div class=\"rowElem\">
            ";
                // line 42
                if (((($__internal_compile_0 = $context["element"]) && is_array($__internal_compile_0) || $__internal_compile_0 instanceof ArrayAccess ? ($__internal_compile_0[0] ?? null) : null) == "select")) {
                    // line 43
                    echo "            <label class=\"topLabel\">";
                    echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (($__internal_compile_1 = $context["element"]) && is_array($__internal_compile_1) || $__internal_compile_1 instanceof ArrayAccess ? ($__internal_compile_1[1] ?? null) : null), "label", [], "any", false, false, false, 43), "html", null, true);
                    if (twig_get_attribute($this->env, $this->source, (($__internal_compile_2 = $context["element"]) && is_array($__internal_compile_2) || $__internal_compile_2 instanceof ArrayAccess ? ($__internal_compile_2[1] ?? null) : null), "description", [], "any", false, false, false, 43)) {
                        echo " - ";
                        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (($__internal_compile_3 = $context["element"]) && is_array($__internal_compile_3) || $__internal_compile_3 instanceof ArrayAccess ? ($__internal_compile_3[1] ?? null) : null), "description", [], "any", false, false, false, 43), "html", null, true);
                    }
                    echo "</label>
            <div class=\"formBottom\">
                <select name=\"config[";
                    // line 45
                    echo twig_escape_filter($this->env, $context["name"], "html", null, true);
                    echo "]\">
                ";
                    // line 46
                    $context['_parent'] = $context;
                    $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, (($__internal_compile_4 = $context["element"]) && is_array($__internal_compile_4) || $__internal_compile_4 instanceof ArrayAccess ? ($__internal_compile_4[1] ?? null) : null), "multiOptions", [], "any", false, false, false, 46));
                    foreach ($context['_seq'] as $context["k"] => $context["v"]) {
                        // line 47
                        echo "                    <option value=\"";
                        echo twig_escape_filter($this->env, $context["k"], "html", null, true);
                        echo "\" ";
                        if (($context["k"] == (($__internal_compile_5 = ($context["values"] ?? null)) && is_array($__internal_compile_5) || $__internal_compile_5 instanceof ArrayAccess ? ($__internal_compile_5[$context["name"]] ?? null) : null))) {
                            echo "selected=\"selected\"";
                        }
                        echo "/><label>";
                        echo twig_escape_filter($this->env, $context["v"], "html", null, true);
                        echo "</label>
                ";
                    }
                    $_parent = $context['_parent'];
                    unset($context['_seq'], $context['_iterated'], $context['k'], $context['v'], $context['_parent'], $context['loop']);
                    $context = array_intersect_key($context, $_parent) + $_parent;
                    // line 49
                    echo "                </select>
            </div>
            <div class=\"fix\"></div>
            ";
                } elseif (((($__internal_compile_6 =                 // line 52
$context["element"]) && is_array($__internal_compile_6) || $__internal_compile_6 instanceof ArrayAccess ? ($__internal_compile_6[0] ?? null) : null) == "radio")) {
                    // line 53
                    echo "            <label>";
                    echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (($__internal_compile_7 = $context["element"]) && is_array($__internal_compile_7) || $__internal_compile_7 instanceof ArrayAccess ? ($__internal_compile_7[1] ?? null) : null), "label", [], "any", false, false, false, 53), "html", null, true);
                    if (twig_get_attribute($this->env, $this->source, (($__internal_compile_8 = $context["element"]) && is_array($__internal_compile_8) || $__internal_compile_8 instanceof ArrayAccess ? ($__internal_compile_8[1] ?? null) : null), "description", [], "any", false, false, false, 53)) {
                        echo " - ";
                        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (($__internal_compile_9 = $context["element"]) && is_array($__internal_compile_9) || $__internal_compile_9 instanceof ArrayAccess ? ($__internal_compile_9[1] ?? null) : null), "description", [], "any", false, false, false, 53), "html", null, true);
                    }
                    echo "</label>
            <div class=\"formRight\">
                ";
                    // line 55
                    $context['_parent'] = $context;
                    $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, (($__internal_compile_10 = $context["element"]) && is_array($__internal_compile_10) || $__internal_compile_10 instanceof ArrayAccess ? ($__internal_compile_10[1] ?? null) : null), "multiOptions", [], "any", false, false, false, 55));
                    foreach ($context['_seq'] as $context["k"] => $context["v"]) {
                        // line 56
                        echo "                    <input id=\"el-";
                        echo twig_escape_filter($this->env, $context["name"], "html", null, true);
                        echo "\" type=\"radio\" name=\"config[";
                        echo twig_escape_filter($this->env, $context["name"], "html", null, true);
                        echo "]\" value=\"";
                        echo twig_escape_filter($this->env, $context["k"], "html", null, true);
                        echo "\" ";
                        if (($context["k"] == (($__internal_compile_11 = ($context["values"] ?? null)) && is_array($__internal_compile_11) || $__internal_compile_11 instanceof ArrayAccess ? ($__internal_compile_11[$context["name"]] ?? null) : null))) {
                            echo "checked=\"checked\"";
                        }
                        echo "/><label>";
                        echo twig_escape_filter($this->env, $context["v"], "html", null, true);
                        echo "</label>
                ";
                    }
                    $_parent = $context['_parent'];
                    unset($context['_seq'], $context['_iterated'], $context['k'], $context['v'], $context['_parent'], $context['loop']);
                    $context = array_intersect_key($context, $_parent) + $_parent;
                    // line 58
                    echo "            </div>
            <div class=\"fix\"></div>
            ";
                } elseif (((($__internal_compile_12 =                 // line 60
$context["element"]) && is_array($__internal_compile_12) || $__internal_compile_12 instanceof ArrayAccess ? ($__internal_compile_12[0] ?? null) : null) == "textarea")) {
                    // line 61
                    echo "            <label class=\"topLabel\" for=\"el-";
                    echo twig_escape_filter($this->env, $context["name"], "html", null, true);
                    echo "\">";
                    echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (($__internal_compile_13 = $context["element"]) && is_array($__internal_compile_13) || $__internal_compile_13 instanceof ArrayAccess ? ($__internal_compile_13[1] ?? null) : null), "label", [], "any", false, false, false, 61), "html", null, true);
                    if (twig_get_attribute($this->env, $this->source, (($__internal_compile_14 = $context["element"]) && is_array($__internal_compile_14) || $__internal_compile_14 instanceof ArrayAccess ? ($__internal_compile_14[1] ?? null) : null), "description", [], "any", false, false, false, 61)) {
                        echo " - ";
                        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (($__internal_compile_15 = $context["element"]) && is_array($__internal_compile_15) || $__internal_compile_15 instanceof ArrayAccess ? ($__internal_compile_15[1] ?? null) : null), "description", [], "any", false, false, false, 61), "html", null, true);
                    }
                    echo "</label>
            <div class=\"formBottom\">
                <textarea id=\"el-";
                    // line 63
                    echo twig_escape_filter($this->env, $context["name"], "html", null, true);
                    echo "\" name=\"config[";
                    echo twig_escape_filter($this->env, $context["name"], "html", null, true);
                    echo "]\" cols=\"5\" rows=\"20\" required=\"required\">";
                    echo twig_escape_filter($this->env, (($__internal_compile_16 = ($context["values"] ?? null)) && is_array($__internal_compile_16) || $__internal_compile_16 instanceof ArrayAccess ? ($__internal_compile_16[$context["name"]] ?? null) : null), "html", null, true);
                    echo "</textarea>
            </div>
            <div class=\"fix\"></div>
            ";
                } else {
                    // line 67
                    echo "            <label class=\"topLabel\" for=\"el-";
                    echo twig_escape_filter($this->env, $context["name"], "html", null, true);
                    echo "\">";
                    echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (($__internal_compile_17 = $context["element"]) && is_array($__internal_compile_17) || $__internal_compile_17 instanceof ArrayAccess ? ($__internal_compile_17[1] ?? null) : null), "label", [], "any", false, false, false, 67), "html", null, true);
                    if (twig_get_attribute($this->env, $this->source, (($__internal_compile_18 = $context["element"]) && is_array($__internal_compile_18) || $__internal_compile_18 instanceof ArrayAccess ? ($__internal_compile_18[1] ?? null) : null), "description", [], "any", false, false, false, 67)) {
                        echo " - ";
                        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (($__internal_compile_19 = $context["element"]) && is_array($__internal_compile_19) || $__internal_compile_19 instanceof ArrayAccess ? ($__internal_compile_19[1] ?? null) : null), "description", [], "any", false, false, false, 67), "html", null, true);
                    }
                    echo "</label>
            <div class=\"formBottom\">
                <input id=\"el-";
                    // line 69
                    echo twig_escape_filter($this->env, $context["name"], "html", null, true);
                    echo "\" type=\"";
                    echo twig_escape_filter($this->env, (($__internal_compile_20 = $context["element"]) && is_array($__internal_compile_20) || $__internal_compile_20 instanceof ArrayAccess ? ($__internal_compile_20[0] ?? null) : null), "html", null, true);
                    echo "\" name=\"config[";
                    echo twig_escape_filter($this->env, $context["name"], "html", null, true);
                    echo "]\" value=\"";
                    echo twig_escape_filter($this->env, (($__internal_compile_21 = ($context["values"] ?? null)) && is_array($__internal_compile_21) || $__internal_compile_21 instanceof ArrayAccess ? ($__internal_compile_21[$context["name"]] ?? null) : null), "html", null, true);
                    echo "\" ";
                    if (( !twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["element"], 1, [], "array", false, true, false, 69), "required", [], "any", true, true, false, 69) && ( !twig_get_attribute($this->env, $this->source, (($__internal_compile_22 = $context["element"]) && is_array($__internal_compile_22) || $__internal_compile_22 instanceof ArrayAccess ? ($__internal_compile_22[1] ?? null) : null), "required", [], "any", false, false, false, 69) == "false"))) {
                        echo "required=\"required\"";
                    }
                    echo "/>
            </div>
            <div class=\"fix\"></div>
            ";
                }
                // line 73
                echo "    </div>
    ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['name'], $context['element'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;

            return ('' === $tmp = ob_get_contents()) ? '' : new Markup($tmp, $this->env->getCharset());
        } finally {
            ob_end_clean();
        }
    }

    // line 77
    public function macro_currency_format($__price__ = null, $__currency__ = null, ...$__varargs__)
    {
        $macros = $this->macros;
        $context = $this->env->mergeGlobals([
            "price" => $__price__,
            "currency" => $__currency__,
            "varargs" => $__varargs__,
        ]);

        $blocks = [];

        ob_start();
        try {
            // line 78
            echo "    ";
            $context["c"] = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "currency_get", [0 => ["code" => ($context["currency"] ?? null)]], "method", false, false, false, 78);
            // line 79
            echo "    ";
            $context["p"] = twig_number_filter(($context["price"] ?? null));
            // line 80
            echo "    ";
            if ((twig_get_attribute($this->env, $this->source, ($context["c"] ?? null), "price_format", [], "any", false, false, false, 80) == 1)) {
                // line 81
                echo "        ";
                $context["p"] = twig_number_filter(($context["p"] ?? null), "2", ".", "");
                // line 82
                echo "    ";
            } elseif ((twig_get_attribute($this->env, $this->source, ($context["c"] ?? null), "price_format", [], "any", false, false, false, 82) == 2)) {
                // line 83
                echo "        ";
                $context["p"] = twig_number_filter(($context["p"] ?? null), "2", ".", ",");
                // line 84
                echo "    ";
            } elseif ((twig_get_attribute($this->env, $this->source, ($context["c"] ?? null), "price_format", [], "any", false, false, false, 84) == 3)) {
                // line 85
                echo "        ";
                $context["p"] = twig_number_filter(($context["p"] ?? null), "2", ",", ".");
                // line 86
                echo "    ";
            } elseif ((twig_get_attribute($this->env, $this->source, ($context["c"] ?? null), "price_format", [], "any", false, false, false, 86) == 4)) {
                // line 87
                echo "        ";
                $context["p"] = twig_number_filter(($context["p"] ?? null), "0", "", ",");
                // line 88
                echo "    ";
            } elseif ((twig_get_attribute($this->env, $this->source, ($context["c"] ?? null), "price_format", [], "any", false, false, false, 88) == 5)) {
                // line 89
                echo "        ";
                $context["p"] = twig_number_filter(($context["p"] ?? null), 0, "", "");
                // line 90
                echo "    ";
            }
            // line 91
            echo "    ";
            echo twig_escape_filter($this->env, twig_replace_filter(twig_get_attribute($this->env, $this->source, ($context["c"] ?? null), "format", [], "any", false, false, false, 91), ["{{price}}" => ($context["p"] ?? null)]), "html", null, true);
            echo "
";

            return ('' === $tmp = ob_get_contents()) ? '' : new Markup($tmp, $this->env->getCharset());
        } finally {
            ob_end_clean();
        }
    }

    // line 94
    public function macro_currency($__price__ = null, $__currency__ = null, ...$__varargs__)
    {
        $macros = $this->macros;
        $context = $this->env->mergeGlobals([
            "price" => $__price__,
            "currency" => $__currency__,
            "varargs" => $__varargs__,
        ]);

        $blocks = [];

        ob_start();
        try {
            // line 95
            echo "    ";
            if ((($context["currency"] ?? null) == null)) {
                // line 96
                echo "        ";
                $context["c"] = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "cart_get_currency", [], "any", false, false, false, 96);
                // line 97
                echo "    ";
            } else {
                // line 98
                echo "        ";
                $context["c"] = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "currency_get", [0 => ["code" => ($context["currency"] ?? null)]], "method", false, false, false, 98);
                // line 99
                echo "    ";
            }
            // line 100
            echo "
    ";
            // line 101
            $context["p"] = (($context["price"] ?? null) * twig_get_attribute($this->env, $this->source, ($context["c"] ?? null), "conversion_rate", [], "any", false, false, false, 101));
            // line 102
            echo "    
    ";
            // line 103
            if ((twig_get_attribute($this->env, $this->source, ($context["c"] ?? null), "price_format", [], "any", false, false, false, 103) == 1)) {
                // line 104
                echo "        ";
                $context["p"] = twig_number_filter(($context["p"] ?? null), "2", ".", "");
                // line 105
                echo "    ";
            } elseif ((twig_get_attribute($this->env, $this->source, ($context["c"] ?? null), "price_format", [], "any", false, false, false, 105) == 2)) {
                // line 106
                echo "        ";
                $context["p"] = twig_number_filter(($context["p"] ?? null), "2", ".", ",");
                // line 107
                echo "    ";
            } elseif ((twig_get_attribute($this->env, $this->source, ($context["c"] ?? null), "price_format", [], "any", false, false, false, 107) == 3)) {
                // line 108
                echo "        ";
                $context["p"] = twig_number_filter(($context["p"] ?? null), "2", ",", ".");
                // line 109
                echo "    ";
            } elseif ((twig_get_attribute($this->env, $this->source, ($context["c"] ?? null), "price_format", [], "any", false, false, false, 109) == 4)) {
                // line 110
                echo "        ";
                $context["p"] = twig_number_filter(($context["p"] ?? null), "0", "", ",");
                // line 111
                echo "    ";
            } elseif ((twig_get_attribute($this->env, $this->source, ($context["c"] ?? null), "price_format", [], "any", false, false, false, 111) == 5)) {
                // line 112
                echo "        ";
                $context["p"] = twig_number_filter(($context["p"] ?? null), 0, "", "");
                // line 113
                echo "    ";
            }
            // line 114
            echo "    
    ";
            // line 115
            echo twig_escape_filter($this->env, twig_replace_filter(twig_get_attribute($this->env, $this->source, ($context["c"] ?? null), "format", [], "any", false, false, false, 115), ["{{price}}" => ($context["p"] ?? null)]), "html", null, true);
            echo "
";

            return ('' === $tmp = ob_get_contents()) ? '' : new Markup($tmp, $this->env->getCharset());
        } finally {
            ob_end_clean();
        }
    }

    // line 118
    public function macro_status_name($__status__ = null, ...$__varargs__)
    {
        $macros = $this->macros;
        $context = $this->env->mergeGlobals([
            "status" => $__status__,
            "varargs" => $__varargs__,
        ]);

        $blocks = [];

        ob_start();
        try {
            if (($context["status"] ?? null)) {
                echo twig_escape_filter($this->env, gettext(twig_title_string_filter($this->env, twig_replace_filter(($context["status"] ?? null), ["_" => " "]))), "html", null, true);
            } else {
                echo "-";
            }

            return ('' === $tmp = ob_get_contents()) ? '' : new Markup($tmp, $this->env->getCharset());
        } finally {
            ob_end_clean();
        }
    }

    // line 120
    public function macro_period_name($__period__ = null, ...$__varargs__)
    {
        $macros = $this->macros;
        $context = $this->env->mergeGlobals([
            "period" => $__period__,
            "varargs" => $__varargs__,
        ]);

        $blocks = [];

        ob_start();
        try {
            // line 121
            echo "    ";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_period_title", [0 => ["code" => ($context["period"] ?? null)]], "method", false, false, false, 121), "html", null, true);
            echo "
";

            return ('' === $tmp = ob_get_contents()) ? '' : new Markup($tmp, $this->env->getCharset());
        } finally {
            ob_end_clean();
        }
    }

    // line 124
    public function macro_markdown_quote($__text__ = null, ...$__varargs__)
    {
        $macros = $this->macros;
        $context = $this->env->mergeGlobals([
            "text" => $__text__,
            "varargs" => $__varargs__,
        ]);

        $blocks = [];

        ob_start();
        try {
            // line 125
            echo "
";
            // line 126
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(twig_split_filter($this->env, ($context["text"] ?? null), "
"));
            foreach ($context['_seq'] as $context["_key"] => $context["line"]) {
                // line 127
                echo "> ";
                echo twig_escape_filter($this->env, $context["line"], "html", null, true);
                echo "
";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['line'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;

            return ('' === $tmp = ob_get_contents()) ? '' : new Markup($tmp, $this->env->getCharset());
        } finally {
            ob_end_clean();
        }
    }

    // line 131
    public function macro_bb_editor($__selector__ = null, ...$__varargs__)
    {
        $macros = $this->macros;
        $context = $this->env->mergeGlobals([
            "selector" => $__selector__,
            "varargs" => $__varargs__,
        ]);

        $blocks = [];

        ob_start();
        try {
            // line 132
            if (twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "extension_is_on", [0 => ["mod" => "wysiwyg"]], "method", false, false, false, 132)) {
                // line 133
                $this->loadTemplate("mod_wysiwyg_js.phtml", "macro_functions.phtml", 133)->display(twig_array_merge($context, ["class" => twig_trim_filter(($context["selector"] ?? null), ".#")]));
            } else {
                // line 135
                echo "<!-- No WYSIWYG, no fancy stuff. Enable the WYSIWYG extension for a better management experience. -->
";
            }

            return ('' === $tmp = ob_get_contents()) ? '' : new Markup($tmp, $this->env->getCharset());
        } finally {
            ob_end_clean();
        }
    }

    public function getTemplateName()
    {
        return "macro_functions.phtml";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  688 => 135,  685 => 133,  683 => 132,  670 => 131,  654 => 127,  649 => 126,  646 => 125,  633 => 124,  621 => 121,  608 => 120,  583 => 118,  572 => 115,  569 => 114,  566 => 113,  563 => 112,  560 => 111,  557 => 110,  554 => 109,  551 => 108,  548 => 107,  545 => 106,  542 => 105,  539 => 104,  537 => 103,  534 => 102,  532 => 101,  529 => 100,  526 => 99,  523 => 98,  520 => 97,  517 => 96,  514 => 95,  500 => 94,  488 => 91,  485 => 90,  482 => 89,  479 => 88,  476 => 87,  473 => 86,  470 => 85,  467 => 84,  464 => 83,  461 => 82,  458 => 81,  455 => 80,  452 => 79,  449 => 78,  435 => 77,  421 => 73,  404 => 69,  392 => 67,  381 => 63,  369 => 61,  367 => 60,  363 => 58,  344 => 56,  340 => 55,  330 => 53,  328 => 52,  323 => 49,  308 => 47,  304 => 46,  300 => 45,  290 => 43,  288 => 42,  285 => 41,  280 => 40,  266 => 39,  246 => 31,  242 => 30,  237 => 27,  225 => 26,  215 => 23,  198 => 21,  194 => 20,  185 => 19,  169 => 18,  159 => 15,  142 => 13,  137 => 12,  131 => 11,  120 => 10,  103 => 9,  90 => 5,  84 => 3,  82 => 2,  69 => 1,  64 => 130,  61 => 123,  58 => 119,  55 => 117,  52 => 93,  49 => 76,  46 => 38,  43 => 25,  40 => 17,  37 => 8,);
    }

    public function getSourceContext()
    {
        return new Source("{% macro q(bool) %}
{% if bool %}
    {{ 'Yes'|trans }}
{% else %}
    {{ 'No'|trans }}
{% endif %}
{% endmacro %}

{% macro selectbox(name, options, selected, required, nullOption) %}
    <select name=\"{{ name }}\" id=\"{{ name }}\" {% if required %}required=\"required\"{% endif%}>
        {% if nullOption %}<option value=\"\">-- {{ nullOption }} --</option>{% endif %}
        {% for val,label in options %}
        <option value=\"{{ val }}\" label=\"{{ label|e }}\" {% if selected == val %}selected=\"selected\"{% endif %}>{{ label|e }}</option>
        {% endfor %}
    </select>
{% endmacro %}

{% macro selectboxtld(name, options, selected, required) %}
    <select name=\"{{ name }}\" {% if required %}required=\"required\"{% endif %} style=\"width:80px;\">
        {% for data in options %}
        <option value=\"{{ data.tld }}\" label=\"{{ data.tld }}\" {% if selected == data.tld %}selected=\"selected\"{% endif %}>{{ data.tld }}</option>
        {% endfor %}
    </select>
{% endmacro %}

{% macro table_search() %}
<div style=\"position: relative;\">
    <div class=\"dataTables_filter\">
        <form method=\"get\" action=\"\">
            <input type=\"hidden\" name=\"_url\" value=\"{{request._url}}\"/>
            <label>{{ 'Search:'|trans }} <input type=\"text\" name=\"search\" placeholder=\"{{ 'Enter search text..'|trans }}\" value=\"{{ request.search }}\">
                <div class=\"srch\"></div>
            </label>
        </form>
    </div>
</div>
{% endmacro %}

{% macro build_form(elements, values) %}
    {% for name, element in elements %}
    <div class=\"rowElem\">
            {% if element[0] == 'select' %}
            <label class=\"topLabel\">{{ element[1].label }}{% if element[1].description %} - {{ element[1].description }}{% endif %}</label>
            <div class=\"formBottom\">
                <select name=\"config[{{ name }}]\">
                {% for k,v in element[1].multiOptions %}
                    <option value=\"{{ k }}\" {% if k == values[name] %}selected=\"selected\"{% endif %}/><label>{{ v }}</label>
                {% endfor %}
                </select>
            </div>
            <div class=\"fix\"></div>
            {% elseif element[0] == 'radio' %}
            <label>{{ element[1].label }}{% if element[1].description %} - {{ element[1].description }}{% endif %}</label>
            <div class=\"formRight\">
                {% for k,v in element[1].multiOptions %}
                    <input id=\"el-{{ name }}\" type=\"radio\" name=\"config[{{ name }}]\" value=\"{{ k }}\" {% if k == values[name] %}checked=\"checked\"{% endif %}/><label>{{ v }}</label>
                {% endfor %}
            </div>
            <div class=\"fix\"></div>
            {% elseif element[0] == 'textarea' %}
            <label class=\"topLabel\" for=\"el-{{ name }}\">{{ element[1].label }}{% if element[1].description %} - {{ element[1].description }}{% endif %}</label>
            <div class=\"formBottom\">
                <textarea id=\"el-{{ name }}\" name=\"config[{{ name }}]\" cols=\"5\" rows=\"20\" required=\"required\">{{ values[name] }}</textarea>
            </div>
            <div class=\"fix\"></div>
            {% else %}
            <label class=\"topLabel\" for=\"el-{{ name }}\">{{ element[1].label }}{% if element[1].description %} - {{ element[1].description }}{% endif %}</label>
            <div class=\"formBottom\">
                <input id=\"el-{{ name }}\" type=\"{{ element[0] }}\" name=\"config[{{ name }}]\" value=\"{{ values[name] }}\" {% if not element[1].required is defined and not element[1].required == 'false' %}required=\"required\"{% endif %}/>
            </div>
            <div class=\"fix\"></div>
            {% endif %}
    </div>
    {% endfor %}
{% endmacro %}

{% macro currency_format(price, currency) %}
    {% set c = guest.currency_get({\"code\":currency}) %}
    {% set p = (price)|number %}
    {% if c.price_format == 1 %}
        {% set p = p|number('2', '.', '') %}
    {% elseif c.price_format == 2 %}
        {% set p = p|number('2', '.', ',') %}
    {% elseif c.price_format == 3 %}
        {% set p = p|number('2', ',', '.') %}
    {% elseif c.price_format == 4 %}
        {% set p = p|number('0', '', ',') %}
    {% elseif c.price_format == 5 %}
        {% set p = p|number(0, '', '') %}
    {% endif %}
    {{ c.format|replace({'{{price}}': p }) }}
{% endmacro %}

{% macro currency(price, currency) %}
    {% if currency == NULL %}
        {% set c = guest.cart_get_currency %}
    {% else %}
        {% set c = guest.currency_get({\"code\":currency}) %}
    {% endif %}

    {% set p = (price * c.conversion_rate) %}
    
    {% if c.price_format == 1 %}
        {% set p = p|number('2', '.', '') %}
    {% elseif c.price_format == 2 %}
        {% set p = p|number('2', '.', ',') %}
    {% elseif c.price_format == 3 %}
        {% set p = p|number('2', ',', '.') %}
    {% elseif c.price_format == 4 %}
        {% set p = p|number('0', '', ',') %}
    {% elseif c.price_format == 5 %}
        {% set p = p|number(0, '', '') %}
    {% endif %}
    
    {{ c.format|replace({'{{price}}': p }) }}
{% endmacro %}

{% macro status_name(status) %}{% if status %}{{ status|replace({'_': \" \"})|title|trans }}{% else %}-{% endif %}{% endmacro %}

{% macro period_name(period) %}
    {{ guest.system_period_title({\"code\":period}) }}
{% endmacro %}

{% macro markdown_quote(text) %}

{% for line in text|split('\\n') %}
> {{ line }}
{% endfor %}
{% endmacro %}

{% macro bb_editor(selector) %}
{% if guest.extension_is_on({\"mod\":\"wysiwyg\"}) %}
{% include \"mod_wysiwyg_js.phtml\" with {\"class\":selector|trim('.#')} %}
{% else %}
<!-- No WYSIWYG, no fancy stuff. Enable the WYSIWYG extension for a better management experience. -->
{% endif %}
{% endmacro %}
", "macro_functions.phtml", "/shared/httpd/up-boxbilling/FOSSBilling/src/bb-themes/admin_default/html/macro_functions.phtml");
    }
}
