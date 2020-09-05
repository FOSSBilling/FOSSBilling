<?php
/*
 *  Required object values:
 *  - none -
 *
 *  Optional Data:
 *  data - owner_email, admin_email, billing_email, tech_email, del_from, del_to, exp_from, exp_to, page, limit
 */

class lookupGetDeletedDomains extends openSRS_base {
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

// Maybe all attribute array should be compiled here
		if (isSet($this->_dataObject->data->owner_email)) {
			// Verify proper email
		}
		if (isSet($this->_dataObject->data->admin_email)) {
			// Verify proper email
		}
		if (isSet($this->_dataObject->data->billing_email)) {
			// Verify proper email
		}
		if (isSet($this->_dataObject->data->tech_email)) {
			// Verify proper email
		}
		if (isSet($this->_dataObject->data->del_from)) {
			// verify format - "2000-10-10" - YYYY-MM-DD
		}
		if (isSet($this->_dataObject->data->del_to)) {
			// verify format - "2000-10-10" - YYYY-MM-DD
		}
		if (isSet($this->_dataObject->data->exp_from)) {
			// verify format - "2000-10-10" - YYYY-MM-DD
		}
		if (isSet($this->_dataObject->data->exp_to)) {
			// verify format - "2000-10-10" - YYYY-MM-DD
		}
		if (isSet($this->_dataObject->data->page)) {
			// verify format - positive number
		}
		if (isSet($this->_dataObject->data->limit)) {
			// verify format - postive number
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
			"action" => "GET_DELETED_DOMAINS",
			"object" => "domain",
			"attributes" => array ()
		);
		
		// Command optional values
		if (isSet($this->_dataObject->data->owner_email) && $this->_dataObject->data->owner_email != "") $cmd['attributes']['owner_email'] = $this->_dataObject->data->owner_email;
		if (isSet($this->_dataObject->data->admin_email) && $this->_dataObject->data->admin_email != "") $cmd['attributes']['admin_email'] = $this->_dataObject->data->admin_email;
		if (isSet($this->_dataObject->data->billing_email) && $this->_dataObject->data->billing_email != "") $cmd['attributes']['billing_email'] = $this->_dataObject->data->billing_email;
		if (isSet($this->_dataObject->data->tech_email) && $this->_dataObject->data->tech_email != "") $cmd['attributes']['tech_email'] = $this->_dataObject->data->tech_email;
		if (isSet($this->_dataObject->data->del_from) && $this->_dataObject->data->del_from != "") $cmd['attributes']['del_from'] = $this->_dataObject->data->del_from;
		if (isSet($this->_dataObject->data->del_to) && $this->_dataObject->data->del_to != "") $cmd['attributes']['del_to'] = $this->_dataObject->data->del_to;
		if (isSet($this->_dataObject->data->exp_from) && $this->_dataObject->data->exp_from != "") $cmd['attributes']['exp_from'] = $this->_dataObject->data->exp_from;
		if (isSet($this->_dataObject->data->exp_to) && $this->_dataObject->data->exp_to != "") $cmd['attributes']['exp_to'] = $this->_dataObject->data->exp_to;
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
