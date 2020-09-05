<?php

class fastDomainLookup extends openSRS_fastlookup {
	private $_domain = "";
	private $_tldSelect = array ();
	private $_tldAll = array ();
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
		$domain = "";
		$arraSelected = array ();
		$arraAll = array ();
		$arraCall = array ();

		if (isSet($this->_dataObject->data->domain)) {
			// Grab domain name
			$domain = $this->_dataObject->data->domain;
		} else {
			trigger_error ("oSRS Error - Search domain strinng not defined.", E_USER_WARNING);
			$allPassed = false;
		}

		if (isSet($this->_dataObject->data->selected)) {
			if ($this->_dataObject->data->selected != ""){
				$arraSelected = explode (";", $this->_dataObject->data->selected);
			}
		} else {
			trigger_error ("oSRS Error - Selected domains are not defined.", E_USER_WARNING);
			$allPassed = false;
		}

		if (isSet($this->_dataObject->data->alldomains)) {
			if ($this->_dataObject->data->alldomains != ""){
				$arraAll = explode (";", $this->_dataObject->data->alldomains);
			} else {
				$allPassed = false;
			}
		} else {
			trigger_error ("oSRS Error - All domain strinng not defined.", E_USER_WARNING);
			$allPassed = false;
		}

		// Select non empty one
		if (count($arraSelected) == 0) {
			$arraCall = $arraAll;
		} else {
			$arraCall = $arraSelected;
		}

		// Call function
		if ($allPassed) {
			$resObject = $this->_domainTLD ($domain, $arraCall);
		} else {
			trigger_error ("oSRS Error - Incorrect call.", E_USER_WARNING);
		}
	}

	// Selected / all TLD options
	private function _domainTLD($domain, $request){
		$result = $this->checkDomainBunch($domain, $request);

		// Results
		$this->resultFullRaw = $result;
		$this->resultRaw = $result;
		$this->resultFullFormated = convertArray2Formated ($this->_formatHolder, $this->resultFullRaw);
		$this->resultFormated = convertArray2Formated ($this->_formatHolder, $this->resultRaw);
	}
}
