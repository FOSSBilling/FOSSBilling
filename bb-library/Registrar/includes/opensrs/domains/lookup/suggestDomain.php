<?php

class suggestDomain extends openSRS_base {
	private $_domain = "";
	private $_tldSelect = array ();
	private $_tldAll = array ();
	private $_dataObject;
	private $_formatHolder = "";
	public $resultFullRaw;
	public $resultRaw;
	public $resultFullFormated;
	public $resultFormated;
	public $result;

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
		$domain = "";
		$arraSelected = array ();
		$arraAll = array ();
		$arraCall = array ();

		if (isSet($this->_dataObject->data->domain)) {
			// Grab domain name
			$domain = $this->_dataObject->data->domain;
			// $domain = htmlspecialchars($domain);
		} else {
			trigger_error ("oSRS Error - Search domain strinng not defined.", E_USER_WARNING);
			$allPassed = false;
		}

		// Select non empty one
		if (isSet($this->_dataObject->data->selected) && $this->_dataObject->data->selected != "") $arraSelected = explode (";", $this->_dataObject->data->selected);
		if (isSet($this->_dataObject->data->defaulttld) && $this->_dataObject->data->defaulttld != "") $arraAll = explode (";", $this->_dataObject->data->defaulttld);
		if (count($arraSelected) == 0) {
			if (count($arraAll) == 0){
				$arraCall = array (".com",".net",".org");
			} else {
				$arraCall = $arraAll;
			}
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
		$cmd = array(
			"protocol" => "XCP",
			"action" => "name_suggest",
			"object" => "domain",
			"attributes" => array(
				"searchstring" => $domain,
				"service_override" => array(
					"suggestion" => array(
						"tlds" => $request
					)
				),
				"services" => array(
					"suggestion"
				)
			)
		);

                if(isSet($this->_dataObject->data->maximum) && $this->_dataObject->data->maximum != ""){
                    $cmd['attributes']['service_override']['suggestion']['maximum'] = $this->_dataObject->data->maximum;
                }
		
//		print_r ($cmd);
//		echo ("\n\n\n");
		$xmlCMD = $this->_opsHandler->encode($cmd);					// Flip Array to XML
//		echo ($xmlCMD);
//		echo ("\n\n\n");
		$XMLresult = $this->send_cmd($xmlCMD);						// Send XML
//		echo ($XMLresult);
//		echo ("\n\n\n");
		$arrayResult = $this->_opsHandler->decode($XMLresult);		// FLip XML to Array
//		print_r ($arrayResult);
//		echo ("\n\n\n");

		// Results
		$this->resultFullRaw = $arrayResult;
		if (isSet($arrayResult['attributes']['suggestion']['items'])){
			$this->resultRaw = $arrayResult['attributes']['suggestion']['items'];
		} else {
			$this->resultRaw = $arrayResult;
		}
		
		$this->resultFullFormated = convertArray2Formated ($this->_formatHolder, $this->resultFullRaw);
		$this->resultFormated = convertArray2Formated ($this->_formatHolder, $this->resultRaw);
	}
}