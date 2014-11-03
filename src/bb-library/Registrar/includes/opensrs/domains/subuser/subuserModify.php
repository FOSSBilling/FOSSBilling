<?php
/*
 *  Required object values:
 *  data - 
 */
 
class subuserModify extends openSRS_base {
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

		if (!isSet($this->_dataObject->data->username) || $this->_dataObject->data->username == "") {
			trigger_error ("oSRS Error - username is not defined.", E_USER_WARNING);
			$allPassed = false;
		}
		if (!isSet($this->_dataObject->data->sub_username) || $this->_dataObject->data->sub_username == "") {
			trigger_error ("oSRS Error - sub_username is not defined.", E_USER_WARNING);
			$allPassed = false;
		}
		if (!isSet($this->_dataObject->data->sub_permission) || $this->_dataObject->data->sub_permission == "") {
			trigger_error ("oSRS Error - sub_permission is not defined.", E_USER_WARNING);
			$allPassed = false;
		}
		if (!isSet($this->_dataObject->data->sub_password) || $this->_dataObject->data->sub_password == "") {
			trigger_error ("oSRS Error - sub_password is not defined.", E_USER_WARNING);
			$allPassed = false;
		}
		if (!isSet($this->_dataObject->data->sub_id) || $this->_dataObject->data->sub_id == "") {
			trigger_error ("oSRS Error - sub_id is not defined.", E_USER_WARNING);
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
			'action' => 'modify',
			'object' => 'subuser',
//			'cookie' => $this->_dataObject->data->cookie,
			'username' => $this->_dataObject->data->username,
//			'registrant_ip' => '12.34.56.78',
			'attributes' => array (
				'sub_username' => $this->_dataObject->data->sub_username,
				'sub_permission' => $this->_dataObject->data->sub_permission,
				'sub_password' => $this->_dataObject->data->sub_password,
				'sub_id' => $this->_dataObject->data->sub_id
			)
		);
		
		// Cookie / bypass
		if (isSet($this->_dataObject->data->cookie) && $this->_dataObject->data->cookie != "") $cmd['cookie'] = $this->_dataObject->data->cookie;
		if (isSet($this->_dataObject->data->bypass) && $this->_dataObject->data->bypass != "") $cmd['domain'] = $this->_dataObject->data->bypass;
		
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