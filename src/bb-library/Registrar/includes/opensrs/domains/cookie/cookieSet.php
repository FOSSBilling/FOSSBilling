<?php
/*
 *  Required object values:
 *  data - 
 */
 
class cookieSet extends openSRS_base {
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
		
		if (!isSet($this->_dataObject->data->reg_username)) {
			trigger_error ("oSRS Error - Username string not defined.", E_USER_WARNING);
			$allPassed = false;
		}

		if (!isSet($this->_dataObject->data->reg_password)) {
			trigger_error ("oSRS Error - Password string not defined.", E_USER_WARNING);
			$allPassed = false;
		}
		
		if (!isSet($this->_dataObject->data->domain)) {
			trigger_error ("oSRS Error - Search domain strinng not defined.", E_USER_WARNING);
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
			"action" => "set",
			"object" => "cookie",
//			"registrant_ip" => "12.34.56.78",
			"attributes" => array (
				"reg_username" => $this->_dataObject->data->reg_username,
				"reg_password" => $this->_dataObject->data->reg_password,
				"domain" => $this->_dataObject->data->domain
			)
		);

		$xmlCMD = $this->_opsHandler->encode($cmd);					// Flip Array to XML
		$XMLresult = $this->send_cmd($xmlCMD);						// Send XML
		$arrayResult = $this->_opsHandler->decode($XMLresult);		// Flip XML to Array

		// Results
		$this->resultFullRaw = $arrayResult;
		if (isSet($arrayResult['attributes'])){
			$this->resultRaw = $arrayResult['attributes'];
		} else {
			$this->resultRaw = $arrayResult;
		}
		
		$this->resultFullRaw = $arrayResult;
		$this->resultRaw = $arrayResult;
		$this->resultFullFormated = convertArray2Formated ($this->_formatHolder, $this->resultFullRaw);
		$this->resultFormated = convertArray2Formated ($this->_formatHolder, $this->resultRaw);
	}
}
