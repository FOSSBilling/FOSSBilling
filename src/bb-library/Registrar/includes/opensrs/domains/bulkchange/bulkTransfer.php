<?php
/*
 *  Required object values:
 *  data - 
 */
 
class bulkTransfer extends openSRS_base {
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
		if (!isSet($this->_dataObject->data->custom_tech_contact) || $this->_dataObject->data->custom_tech_contact == "") {
			trigger_error ("oSRS Error - custom_tech_contact is not defined.", E_USER_WARNING);
			$allPassed = false;
		}
		if (!isSet($this->_dataObject->data->domain_list) || $this->_dataObject->data->domain_list == "") {
			trigger_error ("oSRS Error - domain_list is not defined.", E_USER_WARNING);
			$allPassed = false;
		}
		if (!isSet($this->_dataObject->data->reg_username) || $this->_dataObject->data->reg_username == "") {
			trigger_error ("oSRS Error - reg_username is not defined.", E_USER_WARNING);
			$allPassed = false;
		}
		if (!isSet($this->_dataObject->data->reg_password) || $this->_dataObject->data->reg_password == "") {
			trigger_error ("oSRS Error - reg_password is not defined.", E_USER_WARNING);
			$allPassed = false;
		}
				
		// Run the command
		if ($allPassed)
	 		$allPassed=$this->_allTimeRequired();
	
		if ($allPassed){
			// Execute the command
			$this->_processRequest ();
		} else {
			trigger_error ("oSRS Error - Incorrect call.", E_USER_WARNING);
		}
}

private function _allTimeRequired(){
              $subtest = true;
              $reqPers = array ("first_name", "last_name", "org_name", "address1", "city", "state", "country", "postal_code", "phone", "email", "lang_pref");
              for ($i = 0; $i < count($reqPers); $i++){
                        if ($this->_dataObject->personal->$reqPers[$i] == "") {
                             trigger_error ("oSRS Error - ". $reqPers[$i] ." is not defined.", E_USER_WARNING);
                             $subtest = false;
                         }
              }

	      return $subtest;
}

	// Post validation functions
	private function _processRequest (){
		$this->_dataObject->data->domain_list = explode (",", $this->_dataObject->data->domain_list);
	
		$cmd = array(
			'protocol' => 'XCP',
			'action' => 'bulk_transfer',
			'object' => 'domain',
			'attributes' => array (
				'contact_set' => array(
					'owner' => $this->_createUserData(),
					'admin' => $this->_createUserData(),
					'billing' => $this->_createUserData(),
					'tech' => $this->_createUserData()
				),
				'reg_username' => $this->_dataObject->data->reg_username,
				'reg_domain' => $this->_dataObject->data->reg_domain,
				'reg_password' => $this->_dataObject->data->reg_password,
				'custom_tech_contact' => $this->_dataObject->data->custom_tech_contact,
				'domain_list' => $this->_dataObject->data->domain_list
			)
		);
		
		// Command optional values

		if (isSet($this->_dataObject->data->reg_domain) || $this->_dataObject->data->reg_domain != "") $cmd['attributes']['reg_domain'] = $this->_dataObject->data->reg_domain;
		
		if (isSet($this->_dataObject->data->affiliate_id) && $this->_dataObject->data->affiliate_id != "") $cmd['attributes']['affiliate_id'] = $this->_dataObject->data->affiliate_id;
		if (isSet($this->_dataObject->data->handle) && $this->_dataObject->data->handle != "") $cmd['attributes']['handle'] = $this->_dataObject->data->handle;
		if (isSet($this->_dataObject->data->registrant_ip) && $this->_dataObject->data->registrant_ip != "") $cmd['attributes']['registrant_ip'] = $this->_dataObject->data->registrant_ip;
		
		$xmlCMD = $this->_opsHandler->encode($cmd);					// Flip Array to XML
		$XMLresult = $this->send_cmd($xmlCMD);						// Send XML
		$arrayResult = $this->_opsHandler->decode($XMLresult);		// Flip XML to Array

		// Results
		$this->resultFullRaw = $arrayResult;
		$this->resultRaw = $arrayResult;
		$this->resultFullFormated = convertArray2Formated ($this->_formatHolder, $this->resultFullRaw);
		$this->resultFormated = convertArray2Formated ($this->_formatHolder, $this->resultRaw);
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
	
}
