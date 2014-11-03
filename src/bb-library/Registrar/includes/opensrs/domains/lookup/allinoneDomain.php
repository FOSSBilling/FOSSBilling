<?php

class allinoneDomain extends openSRS_base {
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
			$tdomain = $this->_dataObject->data->domain;
			$tdom = explode (".", $tdomain);
			$domain = $tdom[0];
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
					"lookup" => array(
						"tlds" => $request
					),
					"premium" => array(
						"tlds" => $request
					),
					"suggestion" => array(
						"tlds" => $request
					)
				),
				"services" => array(
					"lookup","premium","suggestion"
				)
			)
		);

                if(isSet($this->_dataObject->data->maximum) && $this->_dataObject->data->maximum != ""){
                    $cmd['attributes']['service_override']['lookup']['maximum'] = $this->_dataObject->data->maximum;
                    $cmd['attributes']['service_override']['premium']['maximum'] = $this->_dataObject->data->maximum;
                    $cmd['attributes']['service_override']['suggestion']['maximum'] = $this->_dataObject->data->maximum;
                }


		$xmlCMD = $this->_opsHandler->encode($cmd);					// Flip Array to XML
		$XMLresult = $this->send_cmd($xmlCMD);						// Send XML
		$arrayResult = $this->_opsHandler->decode($XMLresult);		// FLip XML to Array

		// Results
		$this->resultFullRaw = $arrayResult;
		
		// empty premium
		$this->resultFullRaw = $arrayResult;

		if (isSet($arrayResult['attributes'])){

                    if (isSet($arrayResult['attributes']['premium']['items'])) {
                            $tempHold = $arrayResult['attributes']['premium']['items'];
                    } else {
                            $tempHold = null;			// Null if there are no premium domains
                    }

                    $this->resultRaw = array (
                            'lookup' => $arrayResult['attributes']['lookup']['items'],
                            'premium' => $tempHold,
                            'suggestion' => $arrayResult['attributes']['suggestion']['items']
                    );

                } else {
			$this->resultRaw = $arrayResult;
		}

		$this->resultFullFormated = convertArray2Formated ($this->_formatHolder, $this->resultFullRaw);
		$this->resultFormated = convertArray2Formated ($this->_formatHolder, $this->resultRaw);
	}
}