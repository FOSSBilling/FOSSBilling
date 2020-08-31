<?php
/*
 *  Required object values:
 *  data - 
 */
 
class transGetAway extends openSRS_base {
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
			'action' => 'GET_TRANSFERS_AWAY',
			'object' => 'domain',
			'attributes' => array ()
		);
		
		// Command optional values
		if (isSet($this->_dataObject->data->domain) && $this->_dataObject->data->domain != "") $cmd['attributes']['domain'] = $this->_dataObject->data->domain;
		if (isSet($this->_dataObject->data->gaining_registrar) && $this->_dataObject->data->gaining_registrar != "") $cmd['attributes']['gaining_registrar'] = $this->_dataObject->data->gaining_registrar;
		if (isSet($this->_dataObject->data->limit) && $this->_dataObject->data->limit != "") $cmd['attributes']['limit'] = $this->_dataObject->data->limit;
		if (isSet($this->_dataObject->data->owner_confirm_from) && $this->_dataObject->data->owner_confirm_from != "") $cmd['attributes']['owner_confirm_from'] = $this->_dataObject->data->owner_confirm_from;
		if (isSet($this->_dataObject->data->owner_confirm_ip) && $this->_dataObject->data->owner_confirm_ip != "") $cmd['attributes']['owner_confirm_ip'] = $this->_dataObject->data->owner_confirm_ip;
		if (isSet($this->_dataObject->data->owner_confirm_to) && $this->_dataObject->data->owner_confirm_to != "") $cmd['attributes']['owner_confirm_to'] = $this->_dataObject->data->owner_confirm_to;
		if (isSet($this->_dataObject->data->owner_request_from) && $this->_dataObject->data->owner_request_from != "") $cmd['attributes']['owner_request_from'] = $this->_dataObject->data->owner_request_from;
		if (isSet($this->_dataObject->data->owner_request_to) && $this->_dataObject->data->owner_request_to != "") $cmd['attributes']['owner_request_to'] = $this->_dataObject->data->owner_request_to;
		if (isSet($this->_dataObject->data->page) && $this->_dataObject->data->page != "") $cmd['attributes']['page'] = $this->_dataObject->data->page;
		if (isSet($this->_dataObject->data->req_from) && $this->_dataObject->data->req_from != "") $cmd['attributes']['req_from'] = $this->_dataObject->data->req_from;
		if (isSet($this->_dataObject->data->req_to) && $this->_dataObject->data->req_to != "") $cmd['attributes']['req_to'] = $this->_dataObject->data->req_to;
		if (isSet($this->_dataObject->data->status) && $this->_dataObject->data->status != "") $cmd['attributes']['status'] = $this->_dataObject->data->status;
		if (isSet($this->_dataObject->data->registry_request_date) && $this->_dataObject->data->registry_request_date != "") $cmd['attributes']['registry_request_date'] = $this->_dataObject->data->registry_request_date;
		if (isSet($this->_dataObject->data->request_address) && $this->_dataObject->data->request_address != "") $cmd['attributes']['request_address'] = $this->_dataObject->data->request_address;
		
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