<?php
/* CentovaCast PHP API Client Example
 * Copyright 2007-2008, Centova Technologies Inc.
 * ===========================================================================
 *
 * This file provides an example interface to the CentovaCast XML API.
 * An example of usage is provided in the example.php script accompanying
 * this class.
 *
 * Note that all of the methods defined in the classes below should be
 * considered private; method overloading is used to dynamically handle
 * calls to what would be the public methods of each class.
 *
 */
namespace Box\Mod\Servicecentovacast;

require_once(dirname(__FILE__) . '/class_HTTPRetriever.php');

// This library was originally designed to support object overloading, but
// PHP's support for this appears to be flaky and prone to segfaulting
// (particularly in 4.x) so it's disabled by default.
define('CCAPI_NO_OVERLOAD',true);

/* CCBaseAPIClient
 *
 * Base class for all CentovaCast API classes
 */
class CCBaseAPIClient {
	
	var $debug = false;
	var $debugconsole = false;
	var $encoding = 'UTF-8';
	
	/**
	 * @param string $payload
	 */
	function build_request_packet($methodname,$payload) {
		return sprintf(
			'<?xml version="1.0" encoding="'.$this->encoding.'"?'.'>' .
			'<centovacast>' .
				'<request class="%s" method="%s"%s>' .
				'%s' .
				'</request>' .
			'</centovacast>',
			htmlentities($this->classname),
			htmlentities($methodname),
			$this->debug ? ' debug="enabled"' : '' .
			$this->debugconsole ? ' debugconsole="'.htmlentities($this->debugconsole).'"' : '',
			$payload
		);
	}
	
	function cc_initialize($ccurl) {
		$this->ccurl = $ccurl;
		$this->http = new HTTPRetriever();
		$this->http->HTTPRetriever();
		$this->http->headers["User-Agent"] = 'CentovaCast PHP API Client';
	}
	
	function build_argument_payload($functionargs) {
		return $this->build_argument_xml($functionargs[0]);
	}
	
	function build_argument_xml($args) {
		$payload = '';
		foreach ($args as $name=>$value) {
			$payload .= sprintf('<%s>%s</%s>',$name,htmlentities($value),$name);
		}
		
		return $payload;
	}
	
	function parse_data($data) {
		if (!preg_match('/<data[^\>]*?>([\s\S]+)<\/data>/i',$data,$matches)) return false;
		list(,$rowxml) = $matches;
		
		$rows = array();
		if (!preg_match_all('/<row[^\>]*?>([\s\S]*?)<\/row>/i',$rowxml,$rowmatches,PREG_SET_ORDER)) return $rows;

		foreach ($rowmatches as $k=>$rowmatch) {
			$fields = array();
			list(,$fieldxml) = $rowmatch;
			
			if (preg_match_all('/<field(?:\s+name\s*=\s*"([^"]*?)")?[^\>]*?>([\s\S]*?)<\/field>/i',$fieldxml,$fieldmatches,PREG_SET_ORDER)) {
				foreach ($fieldmatches as $k=>$fieldmatch) {
					list(,$fieldname,$fieldvalue) = $fieldmatch;
					if (strlen($fieldname)) {
						$fields[ $fieldname ] = $fieldvalue;
					} else {
						$fields[] = $fieldvalue;
					}
				}
			}
			
			$rows[] = $fields;
			
		}		

		return $rows;
	}
	
	function parse_response_packet($packet) {
		$this->raw_response = $packet;
		
		if (!preg_match('/<centovacast([^\>]+)>([\s\S]+)<\/centovacast>/i',$packet,$matches)) {
			return $this->set_error('Invalid response packet received from API server');
		}
		$cctags = $matches[1];
		if (preg_match('/version="([^\"]+)"/i',$cctags,$tagmatches)) {
			$this->remote_version = $tagmatches[1];
		} else {
			$this->remote_version = false;
		}

		list(,,$payload) = $matches;
		if (!preg_match('/<response.*?type\s*=\s*"([^"]+)"[^\>]*>([\s\S]+)<\/response>/i',$payload,$matches)) {
			return $this->set_error('Empty or unrecognized response packet received from API server');
		}
		
		list(,$type,$data) = $matches;
		if (preg_match('/<message[^\>]*>([\s\S]+)<\/message>/i',$data,$matches)) {
			$this->message = $matches[1];
		} else {
			$this->message = '(Message not provided by API server)';
		}
		
		switch(strtolower($type)) {
			case 'error':
				return $this->set_error($this->message);
			case 'success':
				$this->data = $this->parse_data($data);
				$this->bb_data = $this->bb_data($packet);
				$this->success = true;
				
				return;
			default:
				return $this->set_error('Invalid response type received from API server');
		}
	}
	
	/**
	 * @param string $packet
	 */
	function api_request($packet) {
		$url = $this->ccurl;
		$apiscript = 'api.php';
		if (substr($url,-strlen($apiscript)-1)!='/'.$apiscript) {
			if (substr($url,-1)!='/') $url .= '/';
			$url .= $apiscript;
		}
		
		$this->success = false;
		
		$postdata = $packet;
		if (!$this->http->post($url,$postdata)) {
			$this->set_error('Error contacting server: '.$this->http->get_error());
			return;
		}
		
		$this->parse_response_packet($this->http->response);
		
		$this->raw_request = $packet;
		$this->raw_response = $this->http->raw_response;
	}
	
	/**
	 * @param string $msg
	 */
	function set_error($msg) {
		$this->success = false;
		$this->error = $msg;
		
		return false;
	}
	
	/* Overloaded method handler; simply passes the request to
	 * the _call() method.
	 *
	 */
	function __call($name,$args) {
		return $this->_call($name,$args);
	}
	
	/* For use when object overloading is not available.
	 *
	 * Usage: $obj->call('methodname',$arg1,$arg2,...)
	 */
	function call() {
		$args = func_get_args();
		$name = array_shift($args);
		$this->_call($name,$args);
		
		return true;
	}
	
	
	/* Private dispatch method for API calls.  Used by __call() (for
	 * overloaded method calls) and call() (for direct calls).
	 *
	 */
	function _call($name,$args) {
		$this->methodname = $name;
		
		$payload = $this->build_argument_payload($args);
		$packet = $this->build_request_packet($name,$payload);

		$this->api_request($packet);		
	}
    
    function bb_data($packet)
    {
        $array = $this->xmlstr_to_array($packet);
        
        if(isset($array['response']['data'])) {
            return $array['response']['data'];
        }
        
        if(isset($array['response']['@attributes']['type']) && $array['response']['@attributes']['type'] == 'success') {
            return true;
        }
        
        return $array;
    }
    
    /**
    * convert xml string to php array - useful to get a serializable value
    *
    * @param string $xmlstr
    * @return array
    * @author Adrien aka Gaarf
    */
    function xmlstr_to_array($xmlstr) {
        $doc = new \DOMDocument();
        $doc->loadXML($xmlstr);
        return $this->domnode_to_array($doc->documentElement);
    }

    /**
     * @param DOMElement $node
     */
    function domnode_to_array($node) {
        $output = array();
        switch ($node->nodeType) {
        case XML_CDATA_SECTION_NODE:
        case XML_TEXT_NODE:
            $output = trim($node->textContent);
        break;
        case XML_ELEMENT_NODE:
            for ($i=0, $m=$node->childNodes->length; $i<$m; $i++) {
            $child = $node->childNodes->item($i);
            $v = $this->domnode_to_array($child);
            if(isset($child->tagName)) {
            $t = $child->tagName;
            if(!isset($output[$t])) {
                $output[$t] = array();
            }
            $output[$t][] = $v;
            }
            elseif($v) {
            $output = (string) $v;
            }
            }
            if(is_array($output)) {
            if($node->attributes->length) {
            $a = array();
            foreach($node->attributes as $attrName => $attrNode) {
            $a[$attrName] = (string) $attrNode->value;
            }
            $output['@attributes'] = $a;
            }
            foreach ($output as $t => $v) {
            if(is_array($v) && count($v)==1 && $t!='@attributes') {
            $output[$t] = $v[0];
            }
            }
            }
        break;
        }
        if(empty($output)) {
            $output = NULL;
        }
        return $output;
    }
    
}

/* CCServerAPIClient
 *
 * Provides an interface to the Server class of the CentovaCast XML API.
 */
class CCServerAPIClient extends CCBaseAPIClient {
	var $classname = 'server';
	
	function CCServerAPIClient($ccurl) { 
		$this->cc_initialize($ccurl);
	}

	function build_argument_payload($functionargs) {
		if (count($functionargs)<3) trigger_error(sprintf('Function %s requires a minimum of 3 arguments, %d given',$this->methodname,count($functionargs)),E_USER_WARNING);
		
		$username = $functionargs[0];
		$password = $functionargs[1];
		$arguments = $functionargs[2];
		if (!is_array($arguments)) $arguments = array();
		
		$arguments = array_merge(
			array(
				'username'=>$username,
				'password'=>$password
			),
			$arguments
		);
		
		return $this->build_argument_xml($arguments);
	}
}

/* CCSystemAPIClient
 *
 * Provides an interface to the System class of the CentovaCast XML API.
 */

class CCSystemAPIClient extends CCBaseAPIClient {
	var $classname = 'system';

	function CCSystemAPIClient($ccurl) {
		$this->cc_initialize($ccurl);
	}

	function build_argument_payload($functionargs) {
		if (count($functionargs)<2) trigger_error(sprintf('Function %s requires a minimum of 2 arguments, %d given',$this->methodname,count($functionargs)),E_USER_WARNING);
		
		$adminpassword = $functionargs[0];
		$arguments = $functionargs[1];
		if (!is_array($arguments)) $arguments = array();
		
		$arguments = array_merge(
			array('password'=>$adminpassword),
			$arguments
		);
		
		return $this->build_argument_xml($arguments);
	}
}

if (!defined('CCAPI_NO_OVERLOAD')) {
	if ( function_exists('overload') ) {
		overload('CCSystemAPIClient');
		overload('CCServerAPIClient');
	} elseif (version_compare(PHP_VERSION,'5.0.0','<')) {
		die('The CentovaCast PHP API client requires that object overloading support is built into PHP.');
	}
}
?>