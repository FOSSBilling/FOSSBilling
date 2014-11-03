<?php
/*
 *  Required object values:
 *  data - 
 */
 
class persSUregister extends openSRS_base {
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
		if (!isSet($this->_dataObject->data->mailbox_type) || $this->_dataObject->data->mailbox_type == "") {
			trigger_error ("oSRS Error - mailbox_type is not defined.", E_USER_WARNING);
			$allPassed = false;
		}
		if (!isSet($this->_dataObject->data->password) || $this->_dataObject->data->password == "") {
			trigger_error ("oSRS Error - password is not defined.", E_USER_WARNING);
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
			'action' => 'SU_REGISTER',
			'object' => 'SURNAME',
			'attributes' => array (
				'domain' => $this->_dataObject->data->domain,
				'mailbox' => array (
					'mailbox_type' => $this->_dataObject->data->mailbox_type,
					'password' => $this->_dataObject->data->password
				)
			)
		);
		
		// Command optional values
		if (isSet($this->_dataObject->data->forward_email) && $this->_dataObject->data->forward_email != "") $cmd['attributes']['mailbox']['forward_email'] = $this->_dataObject->data->forward_email;
		$content = "";
		$name = "";
		$type = "";
		if (isSet($this->_dataObject->data->content) && $this->_dataObject->data->content != "") $content = $this->_dataObject->data->content;
		if (isSet($this->_dataObject->data->name) && $this->_dataObject->data->name != "") $name = $this->_dataObject->data->name;
		if (isSet($this->_dataObject->data->type) && $this->_dataObject->data->type != "") $type = $this->_dataObject->data->type;
		if ($content != "" && $name != "" && $type != ""){
			$cmd['attributes']['dnsRecords']['content'] = $content;
			$cmd['attributes']['dnsRecords']['name'] = $name;
			$cmd['attributes']['dnsRecords']['type'] = $type;
		}
		
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