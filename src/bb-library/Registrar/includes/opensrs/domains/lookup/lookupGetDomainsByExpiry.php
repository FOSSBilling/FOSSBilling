<?php
/*
 *  Required object values:
 *  - none -
 *
 *  Optional Data:
 *  data - owner_email, admin_email, billing_email, tech_email, del_from, del_to, exp_from, exp_to, page, limit
 */

class lookupGetDomainsByExpiry extends openSRS_base {
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
		
		if (!isSet($this->_dataObject->data->exp_from)) {
			trigger_error ("oSRS Error - exp_from is not defined.", E_USER_WARNING);
			$allPassed = false;
		}

		if (!isSet($this->_dataObject->data->exp_to)) {
			trigger_error ("oSRS Error - exp_to is not defined.", E_USER_WARNING);
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
			"action" => "GET_DOMAINS_BY_EXPIREDATE",
			"object" => "DOMAIN",
			"attributes" => array (
				'exp_from' => $this->_dataObject->data->exp_from,
				'exp_to' => $this->_dataObject->data->exp_to
			)
		);

		// Command optional values
		if (isSet($this->_dataObject->data->page) && $this->_dataObject->data->page != "") $cmd['attributes']['page'] = $this->_dataObject->data->page;
		if (isSet($this->_dataObject->data->limit) && $this->_dataObject->data->limit != "") $cmd['attributes']['limit'] = $this->_dataObject->data->limit;

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
		$this->resultFullFormated = convertArray2Formated ($this->_formatHolder, $this->resultFullRaw);
		$this->resultFormated = convertArray2Formated ($this->_formatHolder, $this->resultRaw);
	}
}
