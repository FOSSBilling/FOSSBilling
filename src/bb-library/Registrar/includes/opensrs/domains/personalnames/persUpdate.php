<?php
/*
 *  Required object values:
 *  data - 
 */
 
class persUpdate extends openSRS_base {
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
		if (!isSet($this->_dataObject->data->domain) || $this->_dataObject->data->domain == "") {
			trigger_error ("oSRS Error - domain is not defined.", E_USER_WARNING);
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
			'action' => 'UPDATE',
			'object' => 'SURNAME',
			'attributes' => array (
				'domain' => $this->_dataObject->data->domain 
			)
		);
		
		// Command optional values
		if (isSet($this->_dataObject->data->mailbox_type) && $this->_dataObject->data->mailbox_type != "") $cmd['attributes']['mailbox']['mailbox_type'] = $this->_dataObject->data->mailbox_type;
		if (isSet($this->_dataObject->data->password) && $this->_dataObject->data->password != "") $cmd['attributes']['mailbox']['password'] = $this->_dataObject->data->password;
		if (isSet($this->_dataObject->data->disable_forward_email) && $this->_dataObject->data->disable_forward_email != "") $cmd['attributes']['mailbox']['disable_forward_email'] = $this->_dataObject->data->disable_forward_email;
		if (isSet($this->_dataObject->data->forward_email) && $this->_dataObject->data->forward_email != "") $cmd['attributes']['mailbox']['forward_email'] = $this->_dataObject->data->forward_email;
		if (isSet($this->_dataObject->data->type) && $this->_dataObject->data->type != "") $cmd['attributes']['dnsRecords'][0]['type'] = $this->_dataObject->data->type;
		if (isSet($this->_dataObject->data->name) && $this->_dataObject->data->name != "") $cmd['attributes']['dnsRecords'][0]['name'] = $this->_dataObject->data->name;
		if (isSet($this->_dataObject->data->content) && $this->_dataObject->data->content != "") $cmd['attributes']['dnsRecords'][0]['content'] = $this->_dataObject->data->content;
		
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
