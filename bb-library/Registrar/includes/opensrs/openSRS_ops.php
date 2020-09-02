<?php

// class openSRS_ops extends PEAR {
class openSRS_ops {
	var $_OPS_VERSION	= '0.9';
	var $_OPT			= '';
	var $_SPACER		= ' ';		/* indent character */
	var $_CRLF			= "\n";
	var $_MSGTYPE_STD	= 'standard';
	var $_SESSID;
	var $_MSGCNT;
	var $CRLF			= "\r\n";
	var $_data;
	var $_pointers;
	var $_last_was_data_block;

	/**
	 * Class constructor
	 * Initialize variables, logs, etc.
	 * @param	array	allows for setting various options (right now, just whether
	 *					to use compression or not on the generated XML)
	 */
	function openSRS_ops($args=false) {
		if (is_array($args)) {
			if ($args['option']=='compress') {
				$this->_OPT	= 'compress';
				$this->_SPACER	= '';
				$this->_CRLF	= '';
			}
		}
		
		$this->_SESSID = getmypid();
		$this->_MSGCNT = 0;
	}

	/**
	 * Checks a socket for timeout or EOF
	 * @param	int 		socket handle
	 * @return	boolean 	true if the socket has timed out or is EOF
	 */
	function socketStatus(&$fh) {
		$return = false;
		if (is_resource($fh)) {
			$temp = socket_get_status($fh);
			if ($temp['timed_out']) $return = true;
			if ($temp['eof']) $return = true;
			unset($temp);
		}
		return $return;
	}

	/**
	 * Accepts an OPS protocol message or an file handle
	 * and decodes the data into a PHP array
	 * @param	string 		OPS message
	 * @return	mixed		PHP array, or error
	 */
	function decode($in) {
		$ops_msg = '';
		/* determine if we were passed a string or file handle */
		if (is_resource($in)) {
			# read the file into a string, then process as usual
			while (!feof($in)) {
				$ops_msg .= fgets($in, 400);
			}
		} else {
			$ops_msg = $in;
		}
		return $this->XML2PHP($ops_msg);		/* decode and return */
	}

	/**
	 * XML Parser that converts an OPS protocol message into a PHP array
	 * @param	string 		OPS message
	 * @return	mixed		PHP array, or error
	 */
	function XML2PHP($msg) {
		$this->_data = NULL;
		
		$xp = xml_parser_create();
		xml_parser_set_option($xp, XML_OPTION_CASE_FOLDING, false);
		xml_parser_set_option($xp, XML_OPTION_SKIP_WHITE, true);
		xml_parser_set_option($xp, XML_OPTION_TARGET_ENCODING, 'ISO-8859-1');
		
		if (!xml_parse_into_struct($xp,$msg,$vals,$index)) {
			$error = sprintf('XML error: %s at line %d',
				xml_error_string(xml_get_error_code($xp)),
				xml_get_current_line_number($xp)
			);
			xml_parser_free($xp);
			trigger_error ("oSRS Error - ". $error, E_USER_WARNING);
			die();
		}
		
		xml_parser_free($xp);
		$temp = $depth = array();
		
		foreach($vals as $value) {
			switch ($value['tag']) {
				case 'OPS_envelope':
				case 'header':
				case 'body':
				case 'data_block':
					break;
				case 'version':
				case 'msg_id':
				case 'msg_type':
					$key = '_OPS_' . $value['tag'];
					$temp[$key] = $value['value'];
					break;
				case 'item':
					// Not every Item has attributes
					if (isSet($value['attributes'])) {
						$key = $value['attributes']['key'];
					} else {
						$key = "";
					}
					
					switch ($value['type']) {
						case 'open':
							array_push($depth, $key);
							break;
						case 'complete':
							array_push($depth, $key);
							$p = join('::',$depth);
							
							// enn_change - make sure that   $value['value']   is defined
							if (isSet($value['value'])){
								$temp[$p] = $value['value'];
							} else {
								$temp[$p] = "";
							}
							
							array_pop($depth);
							break;
						case 'close':
							array_pop($depth);
							break;
					}
					break;
				case 'dt_assoc':
				case 'dt_array':
					break;
			}
		}
		
		foreach ($temp as $key=>$value) {
			$levels = explode('::',$key);
			$num_levels = count($levels);
			
			if ($num_levels==1) {
				$this->_data[$levels[0]] = $value;
			} else {
				$pointer = &$this->_data;
				for ($i=0; $i<$num_levels; $i++) {
					if ( !isset( $pointer[$levels[$i]] ) ) {
						$pointer[$levels[$i]] = array();
					}
					$pointer = &$pointer[$levels[$i]];
				}
				$pointer = $value;
			}
		}
		return ($this->_data);
	}

	
	/**
	 * Converts a PHP array into an OPS message
	 * @param	array		PHP array
	 * @return 	string		OPS XML message
	 */
	function encode($array) {
		$this->_MSGCNT++;
		$msg_id = $this->_SESSID + $this->_MSGCNT;			/* addition removes the leading zero */
		$msg_type = $this->_MSGTYPE_STD;
		
		if ($array['protocol']) {
			$array['protocol'] = strtoupper($array['protocol']);
		}
		if ($array['action']) {
			$array['action'] = strtoupper($array['action']);
		}
		if ($array['object']) {
			$array['object'] = strtoupper($array['object']);
		}
		
		$xml_data_block = $this->PHP2XML($array);
		$ops_msg = '<?xml version="1.0" encoding="UTF-8" standalone="no" ?>' . $this->_CRLF .
			'<!DOCTYPE OPS_envelope SYSTEM "ops.dtd">' . $this->_CRLF .
			'<OPS_envelope>' . $this->_CRLF .
			$this->_SPACER . '<header>' . $this->_CRLF .
			$this->_SPACER . $this->_SPACER . '<version>' . $this->_OPS_VERSION . '</version>' . $this->_CRLF .
			$this->_SPACER . $this->_SPACER . '<msg_id>' . $msg_id . '</msg_id>' . $this->_CRLF .
			$this->_SPACER . $this->_SPACER . '<msg_type>' . $msg_type . '</msg_type>' . $this->_CRLF .
			$this->_SPACER . '</header>' . $this->_CRLF .
			$this->_SPACER . '<body>' . $this->_CRLF .
			$xml_data_block . $this->_CRLF .
			$this->_SPACER . '</body>' . $this->_CRLF .
			'</OPS_envelope>';
		
		return $ops_msg;
	}


	/**
	 * Converts a PHP array into an OPS data_block tag
	 * @param	array		PHP array
	 * @return 	string		OPS data_block tag
	 */
	function PHP2XML($data) {
		return str_repeat($this->_SPACER,2) . '<data_block>' . $this->_convertData($data, 3) . $this->_CRLF . str_repeat($this->_SPACER,2) . '</data_block>';
	}


	/**
	 * Recursivly converts PHP data into XML
	 * @param	mixed		PHP array or data
	 * @param	int			ident level
	 * @return 	string		XML string
	 */
	function _convertData(&$array, $indent=0) {
		$string = '';
		$IND = str_repeat($this->_SPACER,$indent);
		
		if (is_array($array)) {
			if ($this->_is_assoc($array)) {		# HASH REFERENCE
				$string .= $this->_CRLF . $IND . '<dt_assoc>';
				$end = '</dt_assoc>';
			} else {				# ARRAY REFERENCE
				$string .= $this->_CRLF . $IND . '<dt_array>';
				$end = '</dt_array>';
			}
			
			foreach ($array as $k=>$v) {
				$indent++;
				/* don't encode some types of stuff */
				if ((gettype($v)=='resource') || (gettype($v)=='user function') || (gettype($v)=='unknown type')) {
					continue;
				}
				
				$string .= $this->_CRLF . $IND . '<item key="' . $k . '"';
				if (gettype($v)=='object' && get_class($v)) {
					$string .= ' class="' . get_class($v) . '"';
				}
				
				$string .= '>';
				if (is_array($v) || is_object($v)) {
					$string .= $this->_convertData($v, $indent+1);
					$string .= $this->_CRLF . $IND . '</item>';
				} else {
					$string .= $this->_quoteXMLChars($v) . '</item>';
				}
				
				$indent--;
			}
			$string .= $this->_CRLF . $IND . $end;
		} else {					# SCALAR
			$string .= $this->_CRLF . $IND . '<dt_scalar>' .
				$this->_quoteXMLChars($array) . '</dt_scalar>';
		}
		return $string;
	}


	/**
	 * Quotes special XML characters
	 * @param	string		string to quote
	 * @return 	string		quoted string
	 */
	function _quoteXMLChars($string) {
		$search  = array ('&', '<', '>', "'", '"');
		$replace = array ('&amp;', '&lt;', '&gt;', '&apos;', '&quot;');
		$string = str_replace($search, $replace, $string);
		$string = utf8_encode($string);
		return $string;
	}


	/**
	 * Determines if an array is associative or not, since PHP
	 * doesn't really distinguish between the two, but Perl/OPS does
	 * @param	array		array to check
	 * @return 	boolean		true if the array is associative
	 */
	function _is_assoc(&$array){
		if (is_array($array)) {
			foreach ($array as $k=>$v) {
				if (!is_int($k)) return true;
			}
		}
		return false;
	}
}
