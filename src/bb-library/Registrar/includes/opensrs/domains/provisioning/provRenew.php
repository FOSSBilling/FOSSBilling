<?php
/*
 *  Required object values:
 *  data - 
 */
 
class provRenew extends openSRS_base {
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
		if (!isSet($this->_dataObject->data->auto_renew) || $this->_dataObject->data->auto_renew == "") {
			trigger_error ("oSRS Error - auto_renew is not defined.", E_USER_WARNING);
			$allPassed = false;
		}
		if (!isSet($this->_dataObject->data->currentexpirationyear) || $this->_dataObject->data->currentexpirationyear == "") {
			trigger_error ("oSRS Error - currentexpirationyear is not defined.", E_USER_WARNING);
			$allPassed = false;
		}
		if (!isSet($this->_dataObject->data->domain) || $this->_dataObject->data->domain == "") {
			trigger_error ("oSRS Error - domain is not defined.", E_USER_WARNING);
			$allPassed = false;
		}
		if (!isSet($this->_dataObject->data->handle) || $this->_dataObject->data->handle == "") {
			trigger_error ("oSRS Error - handle is not defined.", E_USER_WARNING);
			$allPassed = false;
		}
		if (!isSet($this->_dataObject->data->period) || $this->_dataObject->data->period == "") {
			trigger_error ("oSRS Error - period is not defined.", E_USER_WARNING);
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
			'action' => 'renew',
			'object' => 'DOMAIN',
			'attributes' => array ( 
			    'auto_renew' => $this->_dataObject->data->auto_renew,
			    'currentexpirationyear' => $this->_dataObject->data->currentexpirationyear,
			    'domain' => $this->_dataObject->data->domain,
			    'handle' => $this->_dataObject->data->handle,
			    'period' => $this->_dataObject->data->period
			)
		);
		
		// Command optional values
		if (isSet($this->_dataObject->data->f_parkp) && $this->_dataObject->data->f_parkp != "") $cmd['attributes']['f_parkp'] = $this->_dataObject->data->f_parkp;
		if (isSet($this->_dataObject->data->affiliate_id) && $this->_dataObject->data->affiliate_id != "") $cmd['attributes']['affiliate_id'] = $this->_dataObject->data->affiliate_id;
		
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