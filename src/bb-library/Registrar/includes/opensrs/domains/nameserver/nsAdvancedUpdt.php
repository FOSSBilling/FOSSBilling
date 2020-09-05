<?php
/*
 *  Required object values:
 *  data - 
 */
 
class nsAdvancedUpdt extends openSRS_base {
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
		
		// Command required values
		if ((!isSet($this->_dataObject->data->cookie) || $this->_dataObject->data->cookie == "") && (!isSet($this->_dataObject->data->bypass) || $this->_dataObject->data->bypass == "")) {
			trigger_error ("oSRS Error - cookie / bypass is not defined.", E_USER_WARNING);
			$allPassed = false;
		}
		if ( $this->_dataObject->data->cookie != "" && $this->_dataObject->data->bypass != "" ) {
			trigger_error ("oSRS Error - Both cookie and bypass cannot be set in one call.", E_USER_WARNING);
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
		$cmd = array(
			'protocol' => 'XCP',
			'action' => 'advanced_update_nameservers',
			'object' => 'domain',
//			'cookie' => $this->_dataObject->data->cookie,
			'attributes' => array (
				'op_type' => $this->_dataObject->data->op_type
			)
		);
		
		// Cookie / bypass
		if (isSet($this->_dataObject->data->cookie) && $this->_dataObject->data->cookie != "") $cmd['cookie'] = $this->_dataObject->data->cookie;
		if (isSet($this->_dataObject->data->bypass) && $this->_dataObject->data->bypass != "") $cmd['domain'] = $this->_dataObject->data->bypass;
		
				// Command optional values
		if (isSet($this->_dataObject->data->add_ns) && $this->_dataObject->data->add_ns != "") {
			$tempAdd = explode (",", $this->_dataObject->data->add_ns);
			$cmd['attributes']['add_ns'] = $tempAdd;
		}
		if (isSet($this->_dataObject->data->assign_ns) && $this->_dataObject->data->assign_ns != "") {
			$tempAdd = explode (",", $this->_dataObject->data->assign_ns);
			$cmd['attributes']['assign_ns'] = $tempAdd;
		}
		if (isSet($this->_dataObject->data->remove_ns) && $this->_dataObject->data->remove_ns != "") {
			$tempAdd = explode (",", $this->_dataObject->data->remove_ns);
			$cmd['attributes']['remove_ns'] = $tempAdd;
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