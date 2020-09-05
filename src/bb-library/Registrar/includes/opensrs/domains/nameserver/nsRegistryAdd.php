<?php
/*
 *  Required object values:
 *  data - 
 */
 
class nsRegistryAdd extends openSRS_base {
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
		if (!isSet($this->_dataObject->data->fqdn) || $this->_dataObject->data->fqdn == "") {
			trigger_error ("oSRS Error - fqdn is not defined.", E_USER_WARNING);
			$allPassed = false;
		}
		if (!isSet($this->_dataObject->data->tld) || $this->_dataObject->data->tld == "") {
			trigger_error ("oSRS Error - tld is not defined.", E_USER_WARNING);
			$allPassed = false;
		}
		if (!isSet($this->_dataObject->data->all) || $this->_dataObject->data->all == "") {
			trigger_error ("oSRS Error - all is not defined.", E_USER_WARNING);
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
			'action' => 'registry_add_ns',
			'object' => 'nameserver',
			'attributes' => array (
				'fqdn' => $this->_dataObject->data->fqdn,
				'tld' => $this->_dataObject->data->tld,
				'all' => $this->_dataObject->data->all
			)
		);
		
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