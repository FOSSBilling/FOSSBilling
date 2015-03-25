<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (http://www.boxbilling.com)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */


class Box_UrlHelper {
    private $method;
    private $conditions;

    public $params = array();
    public $match = false;

    /**
     * @param string $requestUri
     */
    public function __construct($httpMethod, $url, $conditions=array(), $requestUri) {

        $requestMethod = $_SERVER['REQUEST_METHOD'];
        $this->method = $httpMethod;
        $this->conditions = $conditions;

        if (strtoupper($httpMethod) == $requestMethod) {

            $paramNames = array();
            $paramValues = array();

            preg_match_all('@:([a-zA-Z]+)@', $url, $paramNames, PREG_PATTERN_ORDER);                    // get param names
            $paramNames = $paramNames[1];                                                               // we want the set of matches
            $regexedUrl = preg_replace_callback('@:[a-zA-Z_\-]+@', array($this, 'regexValue'), $url);   // replace param with regex capture
            if (preg_match('@^' . $regexedUrl . '$@', $requestUri, $paramValues)) {                            // determine match and get param values
                array_shift($paramValues);                                                              // remove the complete text match
                $counted = count($paramNames);
                for ($i=0; $i < $counted; $i++) {
                    $this->params[$paramNames[$i]] = $paramValues[$i];
                }
                $this->match = true;
            }
        }
    }

    private function regexValue($matches) {
        $key = str_replace(':', '', $matches[0]);
        if (array_key_exists($key, $this->conditions)) {
            return '(' . $this->conditions[$key] . ')';
        } else {
            return '([a-zA-Z0-9_\-]+)';
        }
    }

}