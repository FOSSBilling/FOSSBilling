<?php 

class provUpdateContacts extends openSRS_base {
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

		// Personal information
		$reqPers = array ("first_name", "last_name", "org_name", "address1", "city", "state", "country", "postal_code", "phone", "email", "lang_pref");
		for ($i = 0; $i < count($reqPers); $i++){
			if ($this->_dataObject->personal->$reqPers[$i] == "") {
				trigger_error ("oSRS Error - ". $reqPers[$i] ." is not defined.", E_USER_WARNING);
				$allPassed = false;
			}
		}
		
		$reqData = array ("domain", "types");
		for ($i = 0; $i < count($reqData); $i++){
			if ($this->_dataObject->data->$reqData[$i] == "") {
				trigger_error ("oSRS Error - ". $reqData[$i] ." is not defined.", E_USER_WARNING);
				$allPassed = false;
			}
		}

		// Run the command
		if ($allPassed) {
			// Execute the command
			$this->processRequest ();
		} else {
			trigger_error ("oSRS Error - Incorrect call.", E_USER_WARNING);
		}		
	}

	private function _createUserData(){
		$userArray = array(
			"first_name" => $this->_dataObject->personal->first_name,
			"last_name" => $this->_dataObject->personal->last_name,
			"org_name" => $this->_dataObject->personal->org_name,
			"address1" => $this->_dataObject->personal->address1,
			"address2" => $this->_dataObject->personal->address2,
			"address3" => $this->_dataObject->personal->address3,
			"city" => $this->_dataObject->personal->city,
			"state" => $this->_dataObject->personal->state,
			"postal_code" => $this->_dataObject->personal->postal_code,
			"country" => $this->_dataObject->personal->country,
			"phone" => $this->_dataObject->personal->phone,
			"fax" => $this->_dataObject->personal->fax,
			"email" => $this->_dataObject->personal->email,
			"url" => $this->_dataObject->personal->url,
			"lang_pref" => $this->_dataObject->personal->lang_pref
		);
		return $userArray;
	}	
	
	// Post validation functions
	private function processRequest (){
		$this->_dataObject->data->types = explode (",", $this->_dataObject->data->types);
	
		// Compile the command
		$cmd = array(
			'protocol' => 'XCP',
			'action' => 'update_contacts',
			'object' => 'domain',
            'cookie' => $this->_dataObject->data->cookie,
			'attributes' => array(
				'domain' => $this->_dataObject->data->domain,
				'types' => $this->_dataObject->data->types,
				'contact_set' => array(
					'owner' => $this->_createUserData(),
					'admin' => $this->_createUserData(),
					'billing' => $this->_createUserData(),
					'tech' => $this->_createUserData()
				)
			)
		);

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