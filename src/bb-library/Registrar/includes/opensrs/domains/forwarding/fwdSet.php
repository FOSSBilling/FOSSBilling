<?php
/*
 *  Required object values:
 *  data - 
 */
 
class fwdSet extends openSRS_base {
	private $_dataObject;
	private $_formatHolder = "";
	public $resultFullRaw;
	public $resultRaw;
	public $resultFullFormated;
	public $resultFormated;

	public function __construct ($formatString, $dataObject) {
		parent::__construct();
		$this->_dataObject = $dataObject;
		$this->_formatHolder = $formatString;
		$this->_validateObject ();
	}

	public function __destruct () {
		parent::__destruct();
	}

	// Validate the object
	private function _validateObject (){
		$allPassed = true;
		
		// Command required values
		if ((!isSet($this->_dataObject->data->cookie) || $this->_dataObject->data->cookie == "") && (!isSet($this->_dataObject->data->bypass) || $this->_dataObject->data->bypass == "")) {
			trigger_error ("oSRS Error - cookie / bypass is not defined.", E_USER_WARNING);
			$allPassed = false;
		}
		if ( $this->_dataObject->data->cookie != "" && $this->_dataObject->data->bypass != "" ) {
			trigger_error ("oSRS Error - Both cookie and bypass cannot be set in one call.", E_USER_WARNING);
			$allPassed = false;
		}
		
		if (!isSet($this->_dataObject->data->domain) || $this->_dataObject->data->domain == "") {
			trigger_error ("oSRS Error - domain is not defined.", E_USER_WARNING);
			$allPassed = false;
		}
		if (!isSet($this->_dataObject->data->subdomain) || $this->_dataObject->data->subdomain == "") {
			trigger_error ("oSRS Error - subdomain is not defined.", E_USER_WARNING);
			$allPassed = false;
		}
				
		// Run the command
		if ($allPassed) {
			// Execute the command
			$this->_processRequest ();
		} else {
			trigger_error ("oSRS Error - Incorrect call.", E_USER_WARNING);
		}
	}

	// Post validation functions
	private function _processRequest (){
		$cmd = array(
			'protocol' => 'XCP',
			'action' => 'set_domain_forwarding',
			'object' => 'domain',
//			'cookie' => $this->_dataObject->data->cookie,
			'attributes' => array (
				'domain' => $this->_dataObject->data->domain, 
				'forwarding' => array (
					array (
					'subdomain' => $this->_dataObject->data->subdomain
					)
					
				)
			)
		);
		
		// Cookie / bypass
		if (isSet($this->_dataObject->data->cookie) && $this->_dataObject->data->cookie != "") $cmd['cookie'] = $this->_dataObject->data->cookie;
		if (isSet($this->_dataObject->data->bypass) && $this->_dataObject->data->bypass != "") $cmd['domain'] = $this->_dataObject->data->bypass;
		
		// Command optional values
		if (isSet($this->_dataObject->data->description) && $this->_dataObject->data->description != "") $cmd['attributes']['forwarding'][0]['description'] = $this->_dataObject->data->description;
		if (isSet($this->_dataObject->data->destination_url) && $this->_dataObject->data->destination_url != "") $cmd['attributes']['forwarding'][0]['destination_url'] = $this->_dataObject->data->destination_url;
		if (isSet($this->_dataObject->data->enabled) && $this->_dataObject->data->enabled != "") $cmd['attributes']['forwarding'][0]['enabled'] = $this->_dataObject->data->enabled;
		if (isSet($this->_dataObject->data->keywords) && $this->_dataObject->data->keywords != "") $cmd['attributes']['forwarding'][0]['keywords'] = $this->_dataObject->data->keywords;
		if (isSet($this->_dataObject->data->masked) && $this->_dataObject->data->masked != "") $cmd['attributes']['forwarding'][0]['masked'] = $this->_dataObject->data->masked;
		if (isSet($this->_dataObject->data->title) && $this->_dataObject->data->title != "") $cmd['attributes']['forwarding'][0]['title'] = $this->_dataObject->data->title;
		
		$xmlCMD = $this->_opsHandler->encode($cmd);					// Flip Array to XML
		$XMLresult = $this->send_cmd($xmlCMD);						// Send XML
		$arrayResult = $this->_opsHandler->decode($XMLresult);		// Flip XML to Array

		// Results
		$this->resultFullRaw = $arrayResult;
		$this->resultRaw = $arrayResult;
		$this->resultFullFormated = convertArray2Formated ($this->_formatHolder, $this->resultFullRaw);
		$this->resultFormated = convertArray2Formated ($this->_formatHolder, $this->resultRaw);
	}
}
