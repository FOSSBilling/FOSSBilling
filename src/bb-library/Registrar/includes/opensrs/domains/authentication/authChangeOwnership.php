<?php
/*
 *  Required object values:
 *  data - 
 */
 
class authChangeOwnership extends openSRS_base {
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

		if (!isSet($this->_dataObject->data->cookie)) {
			trigger_error ("oSRS Error - Cookie string not defined.", E_USER_WARNING);
			$allPassed = false;
		}

		if (!isSet($this->_dataObject->data->username)) {
			trigger_error ("oSRS Error - Username string not defined.", E_USER_WARNING);
			$allPassed = false;
		}

		if (!isSet($this->_dataObject->data->password)) {
			trigger_error ("oSRS Error - Password string not defined.", E_USER_WARNING);
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
			"protocol" => "XCP",
			"action" => "CHANGE",
			"object" => "OWNERSHIP",
			"cookie" => $this->_dataObject->data->cookie,
//			"registrant_ip" => "12.34.56.78",
			"attributes" => array (
				"username" => $this->_dataObject->data->username,
				"password" => $this->_dataObject->data->password
			)
		);

		// Command optional values
		if (isSet($this->_dataObject->data->move_all) && $this->_dataObject->data->move_all != "") $cmd['attributes']['move_all'] = $this->_dataObject->data->move_all;
		if (isSet($this->_dataObject->data->reg_domain) && $this->_dataObject->data->reg_domain != "") $cmd['attributes']['reg_domain'] = $this->_dataObject->data->reg_domain;
		
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