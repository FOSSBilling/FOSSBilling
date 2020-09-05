<?php
/*
 *  Required object values:
 *  data - 
 */
 
class transCancel extends openSRS_base {
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
		if (!isSet($this->_dataObject->data->reseller) || $this->_dataObject->data->reseller == "") {
			trigger_error ("oSRS Error - reseller is not defined.", E_USER_WARNING);
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
			'action' => 'cancel_transfer',
			'object' => 'transfer',
			'attributes' => array (
				'reseller' => $this->_dataObject->data->reseller
			)
		);
		
		// Command optional values
		if (isSet($this->_dataObject->data->domain) && $this->_dataObject->data->domain != "") $cmd['attributes']['domain'] = $this->_dataObject->data->domain;
		if (isSet($this->_dataObject->data->order_id) && $this->_dataObject->data->order_id != "") $cmd['attributes']['order_id'] = $this->_dataObject->data->order_id;
		
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