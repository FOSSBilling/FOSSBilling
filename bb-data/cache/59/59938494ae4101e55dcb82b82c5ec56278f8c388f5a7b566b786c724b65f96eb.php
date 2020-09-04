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
class __TwigTemplate_a8bc913a7cd3fad9060f8f5d45f6367fcbc3dde6aa4cc25a77900ee7dacc687b extends \Twig\Template
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
        // line 4
        echo "
";
        // line 13
        echo "
";
        // line 21
        echo "
";
        // line 32
        echo "
";
        // line 70
        echo "
";
        // line 87
        echo "
";
        // line 111
        echo "
";
        // line 113
        echo "
";
        // line 117
        echo "
";
        // line 126
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
                echo gettext("Yes");
            } else {
                echo gettext("No");
            }

            return ('' === $tmp = ob_get_contents()) ? '' : new Markup($tmp, $this->env->getCharset());
        } finally {
            ob_end_clean();
        }
    }

    // line 5
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
            // line 6
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
            // line 7
            if (($context["nullOption"] ?? null)) {
                echo "<option value=\"\">-- ";
                echo twig_escape_filter($this->env, ($context["nullOption"] ?? null), "html", null, true);
                echo " --</option>";
            }
            // line 8
            echo "        ";
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(($context["options"] ?? null));
            foreach ($context['_seq'] as $context["val"] => $context["label"]) {
                // line 9
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
            // line 11
            echo "    </select>
";

            return ('' === $tmp = ob_get_contents()) ? '' : new Markup($tmp, $this->env->getCharset());
        } finally {
            ob_end_clean();
        }
    }

    // line 14
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
            // line 15
            echo "    <select name=\"";
            echo twig_escape_filter($this->env, ($context["name"] ?? null), "html", null, true);
            echo "\" ";
            if (($context["required"] ?? null)) {
                echo "required=\"required\"";
            }
            echo " style=\"width:80px;\">
        ";
            // line 16
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(($context["options"] ?? null));
            foreach ($context['_seq'] as $context["_key"] => $context["data"]) {
                // line 17
                echo "        <option value=\"";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["data"], "tld", [], "any", false, false, false, 17), "html", null, true);
                echo "\" label=\"";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["data"], "tld", [], "any", false, false, false, 17), "html", null, true);
                echo "\" ";
                if ((($context["selected"] ?? null) == twig_get_attribute($this->env, $this->source, $context["data"], "tld", [], "any", false, false, false, 17))) {
                    echo "selected=\"selected\"";
                }
                echo ">";
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["data"], "tld", [], "any", false, false, false, 17), "html", null, true);
                echo "</option>
        ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['data'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 19
            echo "    </select>
";

            return ('' === $tmp = ob_get_contents()) ? '' : new Markup($tmp, $this->env->getCharset());
        } finally {
            ob_end_clean();
        }
    }

    // line 22
    public function macro_table_search(...$__varargs__)
    {
        $macros = $this->macros;
        $context = $this->env->mergeGlobals([
            "varargs" => $__varargs__,
        ]);

        $blocks = [];

        ob_start();
        try {
            // line 23
            echo "<div style=\"position: relative;\">
    <div class=\"dataTables_filter\">
        <form method=\"get\" action=\"\">
            <input type=\"hidden\" name=\"_url\" value=\"";
            // line 26
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "_url", [], "any", false, false, false, 26), "html", null, true);
            echo "\"/>
            <label>";
            // line 27
            echo gettext("Search:");
            echo " <input type=\"text\" name=\"search\" placeholder=\"";
            echo gettext("Enter search text..");
            echo "\" value=\"";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["request"] ?? null), "search", [], "any", false, false, false, 27), "html", null, true);
            echo "\"><div class=\"srch\"></div></label>
        </form>
    </div>
</div>
";

            return ('' === $tmp = ob_get_contents()) ? '' : new Markup($tmp, $this->env->getCharset());
        } finally {
            ob_end_clean();
        }
    }

    // line 33
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
            // line 34
            echo "    ";
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(($context["elements"] ?? null));
            foreach ($context['_seq'] as $context["name"] => $context["element"]) {
                // line 35
                echo "    <div class=\"rowElem\">
            ";
                // line 36
                if (((($__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4 = $context["element"]) && is_array($__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4) || $__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4 instanceof ArrayAccess ? ($__internal_f607aeef2c31a95a7bf963452dff024ffaeb6aafbe4603f9ca3bec57be8633f4[0] ?? null) : null) == "select")) {
                    // line 37
                    echo "            <label class=\"topLabel\">";
                    echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (($__internal_62824350bc4502ee19dbc2e99fc6bdd3bd90e7d8dd6e72f42c35efd048542144 = $context["element"]) && is_array($__internal_62824350bc4502ee19dbc2e99fc6bdd3bd90e7d8dd6e72f42c35efd048542144) || $__internal_62824350bc4502ee19dbc2e99fc6bdd3bd90e7d8dd6e72f42c35efd048542144 instanceof ArrayAccess ? ($__internal_62824350bc4502ee19dbc2e99fc6bdd3bd90e7d8dd6e72f42c35efd048542144[1] ?? null) : null), "label", [], "any", false, false, false, 37), "html", null, true);
                    if (twig_get_attribute($this->env, $this->source, (($__internal_1cfccaec8dd2e8578ccb026fbe7f2e7e29ac2ed5deb976639c5fc99a6ea8583b = $context["element"]) && is_array($__internal_1cfccaec8dd2e8578ccb026fbe7f2e7e29ac2ed5deb976639c5fc99a6ea8583b) || $__internal_1cfccaec8dd2e8578ccb026fbe7f2e7e29ac2ed5deb976639c5fc99a6ea8583b instanceof ArrayAccess ? ($__internal_1cfccaec8dd2e8578ccb026fbe7f2e7e29ac2ed5deb976639c5fc99a6ea8583b[1] ?? null) : null), "description", [], "any", false, false, false, 37)) {
                        echo " - ";
                        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (($__internal_68aa442c1d43d3410ea8f958ba9090f3eaa9a76f8de8fc9be4d6c7389ba28002 = $context["element"]) && is_array($__internal_68aa442c1d43d3410ea8f958ba9090f3eaa9a76f8de8fc9be4d6c7389ba28002) || $__internal_68aa442c1d43d3410ea8f958ba9090f3eaa9a76f8de8fc9be4d6c7389ba28002 instanceof ArrayAccess ? ($__internal_68aa442c1d43d3410ea8f958ba9090f3eaa9a76f8de8fc9be4d6c7389ba28002[1] ?? null) : null), "description", [], "any", false, false, false, 37), "html", null, true);
                    }
                    echo "</label>
            <div class=\"formBottom\">
                <select name=\"config[";
                    // line 39
                    echo twig_escape_filter($this->env, $context["name"], "html", null, true);
                    echo "]\">
                ";
                    // line 40
                    $context['_parent'] = $context;
                    $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, (($__internal_d7fc55f1a54b629533d60b43063289db62e68921ee7a5f8de562bd9d4a2b7ad4 = $context["element"]) && is_array($__internal_d7fc55f1a54b629533d60b43063289db62e68921ee7a5f8de562bd9d4a2b7ad4) || $__internal_d7fc55f1a54b629533d60b43063289db62e68921ee7a5f8de562bd9d4a2b7ad4 instanceof ArrayAccess ? ($__internal_d7fc55f1a54b629533d60b43063289db62e68921ee7a5f8de562bd9d4a2b7ad4[1] ?? null) : null), "multiOptions", [], "any", false, false, false, 40));
                    foreach ($context['_seq'] as $context["k"] => $context["v"]) {
                        // line 41
                        echo "                    <option value=\"";
                        echo twig_escape_filter($this->env, $context["k"], "html", null, true);
                        echo "\" ";
                        if (($context["k"] == (($__internal_01476f8db28655ee4ee02ea2d17dd5a92599be76304f08cd8bc0e05aced30666 = ($context["values"] ?? null)) && is_array($__internal_01476f8db28655ee4ee02ea2d17dd5a92599be76304f08cd8bc0e05aced30666) || $__internal_01476f8db28655ee4ee02ea2d17dd5a92599be76304f08cd8bc0e05aced30666 instanceof ArrayAccess ? ($__internal_01476f8db28655ee4ee02ea2d17dd5a92599be76304f08cd8bc0e05aced30666[$context["name"]] ?? null) : null))) {
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
                    // line 43
                    echo "                </select>
            </div>
            <div class=\"fix\"></div>
            ";
                } elseif (((($__internal_01c35b74bd85735098add188b3f8372ba465b232ab8298cb582c60f493d3c22e =                 // line 46
$context["element"]) && is_array($__internal_01c35b74bd85735098add188b3f8372ba465b232ab8298cb582c60f493d3c22e) || $__internal_01c35b74bd85735098add188b3f8372ba465b232ab8298cb582c60f493d3c22e instanceof ArrayAccess ? ($__internal_01c35b74bd85735098add188b3f8372ba465b232ab8298cb582c60f493d3c22e[0] ?? null) : null) == "radio")) {
                    // line 47
                    echo "            <label>";
                    echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (($__internal_63ad1f9a2bf4db4af64b010785e9665558fdcac0e8db8b5b413ed986c62dbb52 = $context["element"]) && is_array($__internal_63ad1f9a2bf4db4af64b010785e9665558fdcac0e8db8b5b413ed986c62dbb52) || $__internal_63ad1f9a2bf4db4af64b010785e9665558fdcac0e8db8b5b413ed986c62dbb52 instanceof ArrayAccess ? ($__internal_63ad1f9a2bf4db4af64b010785e9665558fdcac0e8db8b5b413ed986c62dbb52[1] ?? null) : null), "label", [], "any", false, false, false, 47), "html", null, true);
                    if (twig_get_attribute($this->env, $this->source, (($__internal_f10a4cc339617934220127f034125576ed229e948660ebac906a15846d52f136 = $context["element"]) && is_array($__internal_f10a4cc339617934220127f034125576ed229e948660ebac906a15846d52f136) || $__internal_f10a4cc339617934220127f034125576ed229e948660ebac906a15846d52f136 instanceof ArrayAccess ? ($__internal_f10a4cc339617934220127f034125576ed229e948660ebac906a15846d52f136[1] ?? null) : null), "description", [], "any", false, false, false, 47)) {
                        echo " - ";
                        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (($__internal_887a873a4dc3cf8bd4f99c487b4c7727999c350cc3a772414714e49a195e4386 = $context["element"]) && is_array($__internal_887a873a4dc3cf8bd4f99c487b4c7727999c350cc3a772414714e49a195e4386) || $__internal_887a873a4dc3cf8bd4f99c487b4c7727999c350cc3a772414714e49a195e4386 instanceof ArrayAccess ? ($__internal_887a873a4dc3cf8bd4f99c487b4c7727999c350cc3a772414714e49a195e4386[1] ?? null) : null), "description", [], "any", false, false, false, 47), "html", null, true);
                    }
                    echo "</label>
            <div class=\"formRight\">
                ";
                    // line 49
                    $context['_parent'] = $context;
                    $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, (($__internal_d527c24a729d38501d770b40a0d25e1ce8a7f0bff897cc4f8f449ba71fcff3d9 = $context["element"]) && is_array($__internal_d527c24a729d38501d770b40a0d25e1ce8a7f0bff897cc4f8f449ba71fcff3d9) || $__internal_d527c24a729d38501d770b40a0d25e1ce8a7f0bff897cc4f8f449ba71fcff3d9 instanceof ArrayAccess ? ($__internal_d527c24a729d38501d770b40a0d25e1ce8a7f0bff897cc4f8f449ba71fcff3d9[1] ?? null) : null), "multiOptions", [], "any", false, false, false, 49));
                    foreach ($context['_seq'] as $context["k"] => $context["v"]) {
                        // line 50
                        echo "                    <input id=\"el-";
                        echo twig_escape_filter($this->env, $context["name"], "html", null, true);
                        echo "\" type=\"radio\" name=\"config[";
                        echo twig_escape_filter($this->env, $context["name"], "html", null, true);
                        echo "]\" value=\"";
                        echo twig_escape_filter($this->env, $context["k"], "html", null, true);
                        echo "\" ";
                        if (($context["k"] == (($__internal_f6dde3a1020453fdf35e718e94f93ce8eb8803b28cc77a665308e14bbe8572ae = ($context["values"] ?? null)) && is_array($__internal_f6dde3a1020453fdf35e718e94f93ce8eb8803b28cc77a665308e14bbe8572ae) || $__internal_f6dde3a1020453fdf35e718e94f93ce8eb8803b28cc77a665308e14bbe8572ae instanceof ArrayAccess ? ($__internal_f6dde3a1020453fdf35e718e94f93ce8eb8803b28cc77a665308e14bbe8572ae[$context["name"]] ?? null) : null))) {
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
                    // line 52
                    echo "            </div>
            <div class=\"fix\"></div>
            ";
                } elseif (((($__internal_25c0fab8152b8dd6b90603159c0f2e8a936a09ab76edb5e4d7bc95d9a8d2dc8f =                 // line 54
$context["element"]) && is_array($__internal_25c0fab8152b8dd6b90603159c0f2e8a936a09ab76edb5e4d7bc95d9a8d2dc8f) || $__internal_25c0fab8152b8dd6b90603159c0f2e8a936a09ab76edb5e4d7bc95d9a8d2dc8f instanceof ArrayAccess ? ($__internal_25c0fab8152b8dd6b90603159c0f2e8a936a09ab76edb5e4d7bc95d9a8d2dc8f[0] ?? null) : null) == "textarea")) {
                    // line 55
                    echo "            <label class=\"topLabel\" for=\"el-";
                    echo twig_escape_filter($this->env, $context["name"], "html", null, true);
                    echo "\">";
                    echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (($__internal_f769f712f3484f00110c86425acea59f5af2752239e2e8596bcb6effeb425b40 = $context["element"]) && is_array($__internal_f769f712f3484f00110c86425acea59f5af2752239e2e8596bcb6effeb425b40) || $__internal_f769f712f3484f00110c86425acea59f5af2752239e2e8596bcb6effeb425b40 instanceof ArrayAccess ? ($__internal_f769f712f3484f00110c86425acea59f5af2752239e2e8596bcb6effeb425b40[1] ?? null) : null), "label", [], "any", false, false, false, 55), "html", null, true);
                    if (twig_get_attribute($this->env, $this->source, (($__internal_98e944456c0f58b2585e4aa36e3a7e43f4b7c9038088f0f056004af41f4a007f = $context["element"]) && is_array($__internal_98e944456c0f58b2585e4aa36e3a7e43f4b7c9038088f0f056004af41f4a007f) || $__internal_98e944456c0f58b2585e4aa36e3a7e43f4b7c9038088f0f056004af41f4a007f instanceof ArrayAccess ? ($__internal_98e944456c0f58b2585e4aa36e3a7e43f4b7c9038088f0f056004af41f4a007f[1] ?? null) : null), "description", [], "any", false, false, false, 55)) {
                        echo " - ";
                        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (($__internal_a06a70691a7ca361709a372174fa669f5ee1c1e4ed302b3a5b61c10c80c02760 = $context["element"]) && is_array($__internal_a06a70691a7ca361709a372174fa669f5ee1c1e4ed302b3a5b61c10c80c02760) || $__internal_a06a70691a7ca361709a372174fa669f5ee1c1e4ed302b3a5b61c10c80c02760 instanceof ArrayAccess ? ($__internal_a06a70691a7ca361709a372174fa669f5ee1c1e4ed302b3a5b61c10c80c02760[1] ?? null) : null), "description", [], "any", false, false, false, 55), "html", null, true);
                    }
                    echo "</label>
            <div class=\"formBottom\">
                <textarea id=\"el-";
                    // line 57
                    echo twig_escape_filter($this->env, $context["name"], "html", null, true);
                    echo "\" name=\"config[";
                    echo twig_escape_filter($this->env, $context["name"], "html", null, true);
                    echo "]\" cols=\"5\" rows=\"20\" required=\"required\">";
                    echo twig_escape_filter($this->env, (($__internal_653499042eb14fd8415489ba6fa87c1e85cff03392e9f57b26d0da09b9be82ce = ($context["values"] ?? null)) && is_array($__internal_653499042eb14fd8415489ba6fa87c1e85cff03392e9f57b26d0da09b9be82ce) || $__internal_653499042eb14fd8415489ba6fa87c1e85cff03392e9f57b26d0da09b9be82ce instanceof ArrayAccess ? ($__internal_653499042eb14fd8415489ba6fa87c1e85cff03392e9f57b26d0da09b9be82ce[$context["name"]] ?? null) : null), "html", null, true);
                    echo "</textarea>
            </div>
            <div class=\"fix\"></div>
            ";
                } else {
                    // line 61
                    echo "            <label class=\"topLabel\" for=\"el-";
                    echo twig_escape_filter($this->env, $context["name"], "html", null, true);
                    echo "\">";
                    echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (($__internal_ba9f0a3bb95c082f61c9fbf892a05514d732703d52edc77b51f2e6284135900b = $context["element"]) && is_array($__internal_ba9f0a3bb95c082f61c9fbf892a05514d732703d52edc77b51f2e6284135900b) || $__internal_ba9f0a3bb95c082f61c9fbf892a05514d732703d52edc77b51f2e6284135900b instanceof ArrayAccess ? ($__internal_ba9f0a3bb95c082f61c9fbf892a05514d732703d52edc77b51f2e6284135900b[1] ?? null) : null), "label", [], "any", false, false, false, 61), "html", null, true);
                    if (twig_get_attribute($this->env, $this->source, (($__internal_73db8eef4d2582468dab79a6b09c77ce3b48675a610afd65a1f325b68804a60c = $context["element"]) && is_array($__internal_73db8eef4d2582468dab79a6b09c77ce3b48675a610afd65a1f325b68804a60c) || $__internal_73db8eef4d2582468dab79a6b09c77ce3b48675a610afd65a1f325b68804a60c instanceof ArrayAccess ? ($__internal_73db8eef4d2582468dab79a6b09c77ce3b48675a610afd65a1f325b68804a60c[1] ?? null) : null), "description", [], "any", false, false, false, 61)) {
                        echo " - ";
                        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, (($__internal_d8ad5934f1874c52fa2ac9a4dfae52038b39b8b03cfc82eeb53de6151d883972 = $context["element"]) && is_array($__internal_d8ad5934f1874c52fa2ac9a4dfae52038b39b8b03cfc82eeb53de6151d883972) || $__internal_d8ad5934f1874c52fa2ac9a4dfae52038b39b8b03cfc82eeb53de6151d883972 instanceof ArrayAccess ? ($__internal_d8ad5934f1874c52fa2ac9a4dfae52038b39b8b03cfc82eeb53de6151d883972[1] ?? null) : null), "description", [], "any", false, false, false, 61), "html", null, true);
                    }
                    echo "</label>
            <div class=\"formBottom\">
                <input id=\"el-";
                    // line 63
                    echo twig_escape_filter($this->env, $context["name"], "html", null, true);
                    echo "\" type=\"";
                    echo twig_escape_filter($this->env, (($__internal_df39c71428eaf37baa1ea2198679e0077f3699bdd31bb5ba10d084710b9da216 = $context["element"]) && is_array($__internal_df39c71428eaf37baa1ea2198679e0077f3699bdd31bb5ba10d084710b9da216) || $__internal_df39c71428eaf37baa1ea2198679e0077f3699bdd31bb5ba10d084710b9da216 instanceof ArrayAccess ? ($__internal_df39c71428eaf37baa1ea2198679e0077f3699bdd31bb5ba10d084710b9da216[0] ?? null) : null), "html", null, true);
                    echo "\" name=\"config[";
                    echo twig_escape_filter($this->env, $context["name"], "html", null, true);
                    echo "]\" value=\"";
                    echo twig_escape_filter($this->env, (($__internal_bf0e189d688dc2ad611b50a437a32d3692fb6b8be90d2228617cfa6db44e75c0 = ($context["values"] ?? null)) && is_array($__internal_bf0e189d688dc2ad611b50a437a32d3692fb6b8be90d2228617cfa6db44e75c0) || $__internal_bf0e189d688dc2ad611b50a437a32d3692fb6b8be90d2228617cfa6db44e75c0 instanceof ArrayAccess ? ($__internal_bf0e189d688dc2ad611b50a437a32d3692fb6b8be90d2228617cfa6db44e75c0[$context["name"]] ?? null) : null), "html", null, true);
                    echo "\" ";
                    if (( !twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["element"], 1, [], "array", false, true, false, 63), "required", [], "any", true, true, false, 63) && ( !twig_get_attribute($this->env, $this->source, (($__internal_674c0abf302105af78b0a38907d86c5dd0028bdc3ee5f24bf52771a16487760c = $context["element"]) && is_array($__internal_674c0abf302105af78b0a38907d86c5dd0028bdc3ee5f24bf52771a16487760c) || $__internal_674c0abf302105af78b0a38907d86c5dd0028bdc3ee5f24bf52771a16487760c instanceof ArrayAccess ? ($__internal_674c0abf302105af78b0a38907d86c5dd0028bdc3ee5f24bf52771a16487760c[1] ?? null) : null), "required", [], "any", false, false, false, 63) == "false"))) {
                        echo "required=\"required\"";
                    }
                    echo "/>
            </div>
            <div class=\"fix\"></div>
            ";
                }
                // line 67
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

    // line 71
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
            // line 72
            echo "    ";
            $context["c"] = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "currency_get", [0 => ["code" => ($context["currency"] ?? null)]], "method", false, false, false, 72);
            // line 73
            echo "    ";
            $context["p"] = twig_number_filter(($context["price"] ?? null));
            // line 74
            echo "    ";
            if ((twig_get_attribute($this->env, $this->source, ($context["c"] ?? null), "price_format", [], "any", false, false, false, 74) == 1)) {
                // line 75
                echo "        ";
                $context["p"] = twig_number_filter(($context["p"] ?? null), "2", ".", "");
                // line 76
                echo "    ";
            } elseif ((twig_get_attribute($this->env, $this->source, ($context["c"] ?? null), "price_format", [], "any", false, false, false, 76) == 2)) {
                // line 77
                echo "        ";
                $context["p"] = twig_number_filter(($context["p"] ?? null), "2", ".", ",");
                // line 78
                echo "    ";
            } elseif ((twig_get_attribute($this->env, $this->source, ($context["c"] ?? null), "price_format", [], "any", false, false, false, 78) == 3)) {
                // line 79
                echo "        ";
                $context["p"] = twig_number_filter(($context["p"] ?? null), "2", ",", ".");
                // line 80
                echo "    ";
            } elseif ((twig_get_attribute($this->env, $this->source, ($context["c"] ?? null), "price_format", [], "any", false, false, false, 80) == 4)) {
                // line 81
                echo "        ";
                $context["p"] = twig_number_filter(($context["p"] ?? null), "0", "", ",");
                // line 82
                echo "    ";
            } elseif ((twig_get_attribute($this->env, $this->source, ($context["c"] ?? null), "price_format", [], "any", false, false, false, 82) == 5)) {
                // line 83
                echo "        ";
                $context["p"] = twig_number_filter(($context["p"] ?? null), 0, "", "");
                // line 84
                echo "    ";
            }
            // line 85
            echo "    ";
            echo twig_escape_filter($this->env, twig_replace_filter(twig_get_attribute($this->env, $this->source, ($context["c"] ?? null), "format", [], "any", false, false, false, 85), ["{{price}}" => ($context["p"] ?? null)]), "html", null, true);
            echo "
";

            return ('' === $tmp = ob_get_contents()) ? '' : new Markup($tmp, $this->env->getCharset());
        } finally {
            ob_end_clean();
        }
    }

    // line 88
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
            // line 89
            echo "    ";
            if ((($context["currency"] ?? null) == null)) {
                // line 90
                echo "        ";
                $context["c"] = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "cart_get_currency", [], "any", false, false, false, 90);
                // line 91
                echo "    ";
            } else {
                // line 92
                echo "        ";
                $context["c"] = twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "currency_get", [0 => ["code" => ($context["currency"] ?? null)]], "method", false, false, false, 92);
                // line 93
                echo "    ";
            }
            // line 94
            echo "
    ";
            // line 95
            $context["p"] = (($context["price"] ?? null) * twig_get_attribute($this->env, $this->source, ($context["c"] ?? null), "conversion_rate", [], "any", false, false, false, 95));
            // line 96
            echo "    
    ";
            // line 97
            if ((twig_get_attribute($this->env, $this->source, ($context["c"] ?? null), "price_format", [], "any", false, false, false, 97) == 1)) {
                // line 98
                echo "        ";
                $context["p"] = twig_number_filter(($context["p"] ?? null), "2", ".", "");
                // line 99
                echo "    ";
            } elseif ((twig_get_attribute($this->env, $this->source, ($context["c"] ?? null), "price_format", [], "any", false, false, false, 99) == 2)) {
                // line 100
                echo "        ";
                $context["p"] = twig_number_filter(($context["p"] ?? null), "2", ".", ",");
                // line 101
                echo "    ";
            } elseif ((twig_get_attribute($this->env, $this->source, ($context["c"] ?? null), "price_format", [], "any", false, false, false, 101) == 3)) {
                // line 102
                echo "        ";
                $context["p"] = twig_number_filter(($context["p"] ?? null), "2", ",", ".");
                // line 103
                echo "    ";
            } elseif ((twig_get_attribute($this->env, $this->source, ($context["c"] ?? null), "price_format", [], "any", false, false, false, 103) == 4)) {
                // line 104
                echo "        ";
                $context["p"] = twig_number_filter(($context["p"] ?? null), "0", "", ",");
                // line 105
                echo "    ";
            } elseif ((twig_get_attribute($this->env, $this->source, ($context["c"] ?? null), "price_format", [], "any", false, false, false, 105) == 5)) {
                // line 106
                echo "        ";
                $context["p"] = twig_number_filter(($context["p"] ?? null), 0, "", "");
                // line 107
                echo "    ";
            }
            // line 108
            echo "    
    ";
            // line 109
            echo twig_escape_filter($this->env, twig_replace_filter(twig_get_attribute($this->env, $this->source, ($context["c"] ?? null), "format", [], "any", false, false, false, 109), ["{{price}}" => ($context["p"] ?? null)]), "html", null, true);
            echo "
";

            return ('' === $tmp = ob_get_contents()) ? '' : new Markup($tmp, $this->env->getCharset());
        } finally {
            ob_end_clean();
        }
    }

    // line 112
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

    // line 114
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
            // line 115
            echo "    ";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "system_period_title", [0 => ["code" => ($context["period"] ?? null)]], "method", false, false, false, 115), "html", null, true);
            echo "
";

            return ('' === $tmp = ob_get_contents()) ? '' : new Markup($tmp, $this->env->getCharset());
        } finally {
            ob_end_clean();
        }
    }

    // line 118
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
            // line 119
            echo "


";
            // line 122
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(twig_split_filter($this->env, ($context["text"] ?? null), "
"));
            foreach ($context['_seq'] as $context["_key"] => $context["line"]) {
                // line 123
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

    // line 127
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
            // line 128
            if (twig_get_attribute($this->env, $this->source, ($context["guest"] ?? null), "extension_is_on", [0 => ["mod" => "wysiwyg"]], "method", false, false, false, 128)) {
                // line 129
                $this->loadTemplate("mod_wysiwyg_js.phtml", "macro_functions.phtml", 129)->display(twig_array_merge($context, ["class" => twig_trim_filter(($context["selector"] ?? null), ".#")]));
            } else {
                // line 131
                echo "<link rel=\"stylesheet\" type=\"text/css\" href=\"js/markitup/skins/boxbilling/style.css\" />
<link rel=\"stylesheet\" type=\"text/css\" href=\"js/markitup/sets/markdown/style.css\" />
<script src=\"js/markitup/jquery.markitup.js\" type=\"text/javascript\"></script>
<script src=\"js/markitup/sets/markdown/set.js\" type=\"text/javascript\"></script>
<script type=\"text/javascript\" >
    \$(document).ready(function() {
        \$(\"";
                // line 137
                echo twig_escape_filter($this->env, ($context["selector"] ?? null), "html", null, true);
                echo "\").markItUp(mySettings);
    });
</script>
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
        return array (  688 => 137,  680 => 131,  677 => 129,  675 => 128,  662 => 127,  646 => 123,  641 => 122,  636 => 119,  623 => 118,  611 => 115,  598 => 114,  573 => 112,  562 => 109,  559 => 108,  556 => 107,  553 => 106,  550 => 105,  547 => 104,  544 => 103,  541 => 102,  538 => 101,  535 => 100,  532 => 99,  529 => 98,  527 => 97,  524 => 96,  522 => 95,  519 => 94,  516 => 93,  513 => 92,  510 => 91,  507 => 90,  504 => 89,  490 => 88,  478 => 85,  475 => 84,  472 => 83,  469 => 82,  466 => 81,  463 => 80,  460 => 79,  457 => 78,  454 => 77,  451 => 76,  448 => 75,  445 => 74,  442 => 73,  439 => 72,  425 => 71,  411 => 67,  394 => 63,  382 => 61,  371 => 57,  359 => 55,  357 => 54,  353 => 52,  334 => 50,  330 => 49,  320 => 47,  318 => 46,  313 => 43,  298 => 41,  294 => 40,  290 => 39,  280 => 37,  278 => 36,  275 => 35,  270 => 34,  256 => 33,  238 => 27,  234 => 26,  229 => 23,  217 => 22,  207 => 19,  190 => 17,  186 => 16,  177 => 15,  161 => 14,  151 => 11,  134 => 9,  129 => 8,  123 => 7,  112 => 6,  95 => 5,  82 => 2,  69 => 1,  64 => 126,  61 => 117,  58 => 113,  55 => 111,  52 => 87,  49 => 70,  46 => 32,  43 => 21,  40 => 13,  37 => 4,);
    }

    public function getSourceContext()
    {
        return new Source("{% macro q(bool) %}
{% if bool %}{% trans 'Yes' %}{% else %}{% trans 'No' %}{% endif %}
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
    <select name=\"{{ name }}\" {% if required %}required=\"required\"{% endif%} style=\"width:80px;\">
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
            <label>{% trans %}Search:{% endtrans %} <input type=\"text\" name=\"search\" placeholder=\"{% trans %}Enter search text..{% endtrans %}\" value=\"{{request.search}}\"><div class=\"srch\"></div></label>
        </form>
    </div>
</div>
{% endmacro %}

{% macro build_form(elements, values) %}
    {% for name, element in elements %}
    <div class=\"rowElem\">
            {% if element[0] == 'select'%}
            <label class=\"topLabel\">{{ element[1].label }}{% if element[1].description %} - {{ element[1].description }}{% endif %}</label>
            <div class=\"formBottom\">
                <select name=\"config[{{ name }}]\">
                {% for k,v in element[1].multiOptions %}
                    <option value=\"{{ k }}\" {% if k == values[name] %}selected=\"selected\"{% endif %}/><label>{{ v }}</label>
                {% endfor %}
                </select>
            </div>
            <div class=\"fix\"></div>
            {% elseif element[0] == 'radio'%}
            <label>{{ element[1].label }}{% if element[1].description %} - {{ element[1].description }}{% endif %}</label>
            <div class=\"formRight\">
                {% for k,v in element[1].multiOptions %}
                    <input id=\"el-{{ name }}\" type=\"radio\" name=\"config[{{ name }}]\" value=\"{{ k }}\" {% if k == values[name] %}checked=\"checked\"{% endif %}/><label>{{ v }}</label>
                {% endfor %}
            </div>
            <div class=\"fix\"></div>
            {% elseif element[0] == 'textarea'%}
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
<link rel=\"stylesheet\" type=\"text/css\" href=\"js/markitup/skins/boxbilling/style.css\" />
<link rel=\"stylesheet\" type=\"text/css\" href=\"js/markitup/sets/markdown/style.css\" />
<script src=\"js/markitup/jquery.markitup.js\" type=\"text/javascript\"></script>
<script src=\"js/markitup/sets/markdown/set.js\" type=\"text/javascript\"></script>
<script type=\"text/javascript\" >
    \$(document).ready(function() {
        \$(\"{{selector}}\").markItUp(mySettings);
    });
</script>
{% endif %}
{% endmacro %}", "macro_functions.phtml", "/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/src/bb-themes/admin_default/html/macro_functions.phtml");
    }
}
