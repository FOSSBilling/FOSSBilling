<?php
/*
 *  Required object values:
 *  data - 
 */
 
class lookupGetDomain extends openSRS_base {
	private $_dataObject;
	private $_formatHolder = "";
	public $resultFullRaw;
	public $resultRaw;
	public $resultFullFormated;
	public $resultFormated;

	public function __construct ($formatString, $dataObject) {
		parent::__construct($dataObject);
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
						
		if (!isSet($this->_dataObject->data->type) || $this->_dataObject->data->type == "") {
			trigger_error ("oSRS Error - type is not defined.", E_USER_WARNING);
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
			'action' => 'get',
			'object' => 'domain',
///			'cookie' => $this->_dataObject->data->cookie,
			'attributes' => array (
				'type' => $this->_dataObject->data->type
			)
		);
		
		// Cookie / bypass
		if (isSet($this->_dataObject->data->cookie) && $this->_dataObject->data->cookie != "") $cmd['cookie'] = $this->_dataObject->data->cookie;
		if (isSet($this->_dataObject->data->bypass) && $this->_dataObject->data->bypass != "") $cmd['domain'] = $this->_dataObject->data->bypass;
		if (isSet($this->_dataObject->data->registrant_ip) && $this->_dataObject->data->registrant_ip != "") $cmd['registrant_ip'] = $this->_dataObject->data->registrant_ip;
		
		// Command optional values
		if (isSet($this->_dataObject->data->limit) && $this->_dataObject->data->limit != "") $cmd['attributes']['limit'] = $this->_dataObject->data->limit;
		if (isSet($this->_dataObject->data->page) && $this->_dataObject->data->page != "") $cmd['attributes']['page'] = $this->_dataObject->data->page;
		if (isSet($this->_dataObject->data->max_to_expiry) && $this->_dataObject->data->max_to_expiry != "") $cmd['attributes']['max_to_expiry'] = $this->_dataObject->data->max_to_expiry;
		if (isSet($this->_dataObject->data->min_to_expiry) && $this->_dataObject->data->min_to_expiry != "") $cmd['attributes']['min_to_expiry'] = $this->_dataObject->data->min_to_expiry;
		
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