<?php
/*
 *  Required object values:
 *  data - 
 */
 
class bulkSubmit extends openSRS_base {
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
		if (!isSet($this->_dataObject->data->change_items) || $this->_dataObject->data->change_items == "") {
			trigger_error ("oSRS Error - change_items is not defined.", E_USER_WARNING);
			$allPassed = false;
		}
				
		if (!isSet($this->_dataObject->data->change_type) || $this->_dataObject->data->change_type == "") {
			trigger_error ("oSRS Error - change_type is not defined.", E_USER_WARNING);
			$allPassed = false;
		}

		if (!isSet($this->_dataObject->data->op_type) || $this->_dataObject->data->op_type == "") {
			trigger_error ("oSRS Error - op_type is not defined.", E_USER_WARNING);
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

        $this->_dataObject->data->change_items = explode(",", $this->_dataObject->data->change_items);

		$cmd = array(
			'protocol' => 'XCP',
			'action' => 'submit',
			'object' => 'bulk_change',
			'attributes' => array (
				'change_items' => $this->_dataObject->data->change_items,
				'change_type' => $this->_dataObject->data->change_type, 
				'op_type' => $this->_dataObject->data->op_type,
			)
		);
		
		// Command optional values
		if (isSet($this->_dataObject->data->contact_email) && $this->_dataObject->data->contact_email != "") $cmd['attributes']['contact_email'] = $this->_dataObject->data->contact_email;
		if (isSet($this->_dataObject->data->apply_to_locked_domains) && $this->_dataObject->data->apply_to_locked_domains != "") $cmd['attributes']['apply_to_locked_domains'] = $this->_dataObject->data->apply_to_locked_domains;

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
