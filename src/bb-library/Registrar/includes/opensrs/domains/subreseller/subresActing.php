<?php

class subresActing extends openSRS_base {
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

		$reqPers = array ("first_name", "last_name", "org_name", "address1", "city", "state", "country", "postal_code", "phone", "email", "lang_pref");
		for ($i = 0; $i < count($reqPers); $i++){
			if ($this->_dataObject->personal->$reqPers[$i] == "") {
				trigger_error ("oSRS Error - ". $reqPers[$i] ." is not defined.", E_USER_WARNING);
				$allPassed = false;
			}
		}

		$reqData = array ("reg_username", "reg_password", "domain", "custom_nameservers", "as_subreseller", "bulk_order");
		for ($i = 0; $i < count($reqData); $i++){
			if ($this->_dataObject->data->$reqData[$i] == "") {
				trigger_error ("oSRS Error - ". $reqData[$i] ." is not defined.", E_USER_WARNING);
				$allPassed = false;
			}
		}

		// Run the command
		if ($allPassed) {

			$cmd = array(
				'protocol' => 'XCP',
				'action' => 'SW_REGISTER',
				'object' => 'DOMAIN',
				'attributes' => array(
					'reg_type' => 'new',
					'reg_username' => $this->_dataObject->data->reg_username,
					'reg_password' => $this->_dataObject->data->reg_password,
					'reg_domain' => $this->_dataObject->data->reg_domain,
					'affiliate_id' => $this->_dataObject->data->affiliate_id,
					'auto_renew' => $this->_dataObject->data->auto_renew,
					'domain' => $this->_dataObject->data->domain,
					'contact_set' => array(
						'owner' => $this->_createUserData(),
						'admin' => $this->_createUserData(),
						'billing' => $this->_createUserData(),
						'tech' => $this->_createUserData()
					),
					'f_parkp' => $this->_dataObject->data->f_parkp,
					'f_whois_privacy' => $this->_dataObject->data->f_whois_privacy,
					'f_lock_domain' => $this->_dataObject->data->f_lock_domain,
					'period' => $this->_dataObject->data->period,
					'link_domains' => $this->_dataObject->data->link_domains,
					'custom_nameservers' => $this->_dataObject->data->custom_nameservers,
					'encoding_type' => $this->_dataObject->data->encoding_type,
					'custom_tech_contact' => $this->_dataObject->data->custom_tech_contact,
					'as_subreseller' => $this->_dataObject->data->as_subreseller,
					'bulk_order' => $this->_dataObject->data->bulk_order
					),
			);
			
			if ($this->_dataObject->data->custom_nameservers == 1){
				for ($j=1; $j<=10; $j++){
					$tns = "name". $j;
					$tso = "sortorder". $j;
				
					$passArray = array ();
					$temHolder = array ();
					if (isSet($this->_dataObject->data->$tns)){
						if ($this->_dataObject->data->$tns != ""){
							$temHolder['name'] = $this->_dataObject->data->$tns;
							$temHolder['sortorder'] = $this->_dataObject->data->$tso;
							array_push ($passArray, $temHolder);
						}
					}
				}
				
				$cmd['attributes']['nameserver_list'] = $passArray;
			}

			
			$xmlCMD = $this->_opsHandler->encode($cmd);					// Flip Array to XML
			$XMLresult = $this->send_cmd($xmlCMD);						// Send XML
			$arrayResult = $this->_opsHandler->decode($XMLresult);		// FLip XML to Array

			// Results
			$this->resultFullRaw = $arrayResult;
			$this->resultRaw = $arrayResult;
			$this->resultFullFormated = convertArray2Formated ($this->_formatHolder, $this->resultFullRaw);
			$this->resultFormated = convertArray2Formated ($this->_formatHolder, $this->resultRaw);
		} else {
			echo ("Incorrect call data.<br>\n");
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
}
