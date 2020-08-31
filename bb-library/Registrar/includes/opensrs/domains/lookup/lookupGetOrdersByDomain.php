<?php
/*
 *  Required object values:
 *  - none -
 *
 *  Optional Data:
 *  data - owner_email, admin_email, billing_email, tech_email, del_from, del_to, exp_from, exp_to, page, limit
 */

class lookupGetOrdersByDomain extends openSRS_base {
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
		
		if (!isSet($this->_dataObject->data->domain)) {
			trigger_error ("oSRS Error - Domain is not defined.", E_USER_WARNING);
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
			"action" => "GET_ORDERS_BY_DOMAIN",
			"object" => "DOMAIN",
			"attributes" => array (
				'domain' => $this->_dataObject->data->domain
			)
		);
		
		// Command optional values
		if (isSet($this->_dataObject->data->page) && $this->_dataObject->data->page != "") $cmd['attributes']['page'] = $this->_dataObject->data->page;
		if (isSet($this->_dataObject->data->order_from) && $this->_dataObject->data->order_from != "") $cmd['attributes']['order_from'] = $this->_dataObject->data->order_from;
		if (isSet($this->_dataObject->data->status) && $this->_dataObject->data->status != "") $cmd['attributes']['status'] = $this->_dataObject->data->status;
		if (isSet($this->_dataObject->data->type) && $this->_dataObject->data->type != "") $cmd['attributes']['type'] = $this->_dataObject->data->type;
		if (isSet($this->_dataObject->data->limit) && $this->_dataObject->data->limit != "") $cmd['attributes']['limit'] = $this->_dataObject->data->limit;
		if (isSet($this->_dataObject->data->order_to) && $this->_dataObject->data->order_to != "") $cmd['attributes']['order_to'] = $this->_dataObject->data->order_to;

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
