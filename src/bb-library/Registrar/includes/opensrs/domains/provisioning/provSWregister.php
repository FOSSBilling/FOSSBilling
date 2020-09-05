<?php
/*
 *  Required object values:
 *  data - 
 */
 
class provSWregister extends openSRS_base {
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
		$this->_validate ();
	}

	public function __destruct () {
		parent::__destruct();
	}

	private function _validate () {
		// Command required values
		if (isSet($this->_dataObject->data->domain) && $this->_dataObject->data->domain != "") {
			// find the TLD
			$this->_dataObject->data->domain = str_replace("www.", "", $this->_dataObject->data->domain);
			$tld = explode (".", $this->_dataObject->data->domain);
			
			// Data validation with all the additional options
			$allPassed = true;
			$allPassed = $this->_allTimeRequired ();
			if ($tld[1] == "ca") $allPassed = $this->_ccTLD_ca();
			if ($tld[1] == "asia") $allPassed = $this->_ccTLD_asia();
			if ($tld[1] == "be") $allPassed = $this->_ccTLD_be();
			if ($tld[1] == "de") $allPassed = $this->_ccTLD_de();
			if ($tld[1] == "eu") $allPassed = $this->_ccTLD_eu();
			if ($tld[1] == "name") $allPassed = $this->_ccTLD_name();
			if ($tld[1] == "us") $allPassed = $this->_ccTLD_us();
			
			// Call the process function
			if ($allPassed) {
				$this->_processRequest ($tld[1]);
			} else {
				trigger_error ("oSRS Error - Incorrect call.", E_USER_WARNING);
			}
		} else {
			trigger_error ("oSRS Error - Domain is not defined.", E_USER_ERROR);
			die();
		}
	}
	
	// Personal Information
	private function _allTimeRequired(){
		$subtest = true;
		//
		$reqPers = array ("first_name", "last_name", "org_name", "address1", "city", "state", "country", "postal_code", "phone", "email", "lang_pref");
		for ($i = 0; $i < count($reqPers); $i++){
			if ($this->_dataObject->personal->$reqPers[$i] == "") {
				trigger_error ("oSRS Error - ". $reqPers[$i] ." is not defined.", E_USER_WARNING);
				$subtest = false;
			}
		}
		
		$reqData = array ("reg_type", "reg_username", "reg_password", "domain", "custom_nameservers", "period", "custom_tech_contact", "custom_nameservers");
		for ($i = 0; $i < count($reqData); $i++){
			if ($this->_dataObject->data->$reqData[$i] == "") {
				trigger_error ("oSRS Error - ". $reqData[$i] ." is not defined.", E_USER_WARNING);
				$subtest = false;
			}
		}
		return $subtest;
	}
	
	
	// ccTLD specific validation
	private function _ccTLD_ca () {
		$subtest = true;
		$reqData = array ("isa_trademark", "lang_pref", "legal_type");
		for ($i = 0; $i < count($reqData); $i++){
			if ($this->_dataObject->data->$reqData[$i] == "") {
				trigger_error ("oSRS Error - ". $reqData[$i] ." is not defined.", E_USER_WARNING);
				$subtest = false;
			}
		}
		return $subtest;
	}

	private function _ccTLD_asia () {
		$subtest = true;
		$reqData = array ("contact_type", "id_number", "id_type", "legal_entity_type", "locality_country");
		for ($i = 0; $i < count($reqData); $i++){
			if ($this->_dataObject->cedinfo->$reqData[$i] == "") {
				trigger_error ("oSRS Error - ". $reqData[$i] ." is not defined.", E_USER_WARNING);
				$subtest = false;
			}
		}
		return $subtest;
	}
	
	private function _ccTLD_be () {
		$subtest = true;
		$reqData = array ("lang", "owner_confirm_address");
		for ($i = 0; $i < count($reqData); $i++){
			if ($this->_dataObject->data->$reqData[$i] == "") {
				trigger_error ("oSRS Error - ". $reqData[$i] ." is not defined.", E_USER_WARNING);
				$subtest = false;
			}
		}
		return $subtest;
	}

	private function _ccTLD_de () {
		$subtest = true;
		$reqData = array ("owner_confirm_address");
		for ($i = 0; $i < count($reqData); $i++){
			if ($this->_dataObject->data->$reqData[$i] == "") {
				trigger_error ("oSRS Error - ". $reqData[$i] ." is not defined.", E_USER_WARNING);
				$subtest = false;
			}
		}
		return $subtest;
	}
	
	private function _ccTLD_eu () {
		$subtest = true;
		$reqData = array ("country", "lang", "owner_confirm_address");
		for ($i = 0; $i < count($reqData); $i++){
			if ($this->_dataObject->data->$reqData[$i] == "") {
				trigger_error ("oSRS Error - ". $reqData[$i] ." is not defined.", E_USER_WARNING);
				$subtest = false;
			}
		}
		return $subtest;
	}

	private function _ccTLD_us () {
		$subtest = true;
		$reqData = array ("app_purpose", "category");
		for ($i = 0; $i < count($reqData); $i++){
			if ($this->_dataObject->nexus->$reqData[$i] == "") {
				trigger_error ("oSRS Error - ". $reqData[$i] ." is not defined.", E_USER_WARNING);
				$subtest = false;
			}
		}
		return $subtest;
	}
	
	private function _ccTLD_name () {
		$subtest = true;
		$reqData = array ("forwarding_email");
		for ($i = 0; $i < count($reqData); $i++){
			if ($this->_dataObject->data->$reqData[$i] == "") {
				trigger_error ("oSRS Error - ". $reqData[$i] ." is not defined.", E_USER_WARNING);
				$subtest = false;
			}
		}
		return $subtest;
	}

	
	
	
	// Post validation functions
	private function _processRequest ($ccTLD){
		// Compile the command	
		$cmd = array(
			'protocol' => 'XCP',
			'action' => 'SW_REGISTER',
			'object' => 'DOMAIN',
			'attributes' => array(
				'reg_type' => $this->_dataObject->data->reg_type,
				'reg_username' => $this->_dataObject->data->reg_username,
				'reg_password' => $this->_dataObject->data->reg_password,
				'domain' => $this->_dataObject->data->domain,
				'custom_nameservers' => $this->_dataObject->data->custom_nameservers,
				'period' => $this->_dataObject->data->period,
				'custom_tech_contact' => $this->_dataObject->data->custom_tech_contact,
				'custom_nameservers' => $this->_dataObject->data->custom_nameservers,
				'contact_set' => array(
					'owner' => $this->_createUserData(),
					'admin' => $this->_createUserData(),
					'billing' => $this->_createUserData(),
					'tech' => $this->_createUserData()
				)
			)
		);
		
		// Command optional values
		if (isSet($this->_dataObject->data->affiliate_id) && $this->_dataObject->data->affiliate_id != "") $cmd['attributes']['affiliate_id'] = $this->_dataObject->data->affiliate_id;
		if (isSet($this->_dataObject->data->auto_renew) && $this->_dataObject->data->auto_renew != "") $cmd['attributes']['auto_renew'] = $this->_dataObject->data->auto_renew;
		if (isSet($this->_dataObject->data->change_contact) && $this->_dataObject->data->change_contact != "") $cmd['attributes']['change_contact'] = $this->_dataObject->data->change_contact;
		if (isSet($this->_dataObject->data->custom_transfer_nameservers) && $this->_dataObject->data->custom_transfer_nameservers != "") $cmd['attributes']['custom_transfer_nameservers'] = $this->_dataObject->data->custom_transfer_nameservers;
		if (isSet($this->_dataObject->data->dns_template) && $this->_dataObject->data->dns_template != "") $cmd['attributes']['dns_template'] = $this->_dataObject->data->dns_template;
		if (isSet($this->_dataObject->data->encoding_type) && $this->_dataObject->data->encoding_type != "") $cmd['attributes']['encoding_type'] = $this->_dataObject->data->encoding_type;
		if (isSet($this->_dataObject->data->f_lock_domain) && $this->_dataObject->data->f_lock_domain != "") $cmd['attributes']['f_lock_domain'] = $this->_dataObject->data->f_lock_domain;
		if (isSet($this->_dataObject->data->f_parkp) && $this->_dataObject->data->f_parkp != "") $cmd['attributes']['f_parkp'] = $this->_dataObject->data->f_parkp;
		if (isSet($this->_dataObject->data->f_whois_privacy) && $this->_dataObject->data->f_whois_privacy != "") $cmd['attributes']['f_whois_privacy'] = $this->_dataObject->data->f_whois_privacy;
		if (isSet($this->_dataObject->data->handle) && $this->_dataObject->data->handle != "") $cmd['attributes']['handle'] = $this->_dataObject->data->handle;
		if (isSet($this->_dataObject->data->link_domains) && $this->_dataObject->data->link_domains != "") $cmd['attributes']['link_domains'] = $this->_dataObject->data->link_domains;
		if (isSet($this->_dataObject->data->master_order_id) && $this->_dataObject->data->master_order_id != "") $cmd['attributes']['master_order_id'] = $this->_dataObject->data->master_order_id;
		if (isSet($this->_dataObject->data->nameserver_list) && $this->_dataObject->data->nameserver_list != "") $cmd['attributes']['nameserver_list'] = $this->_dataObject->data->nameserver_list;
		if (isSet($this->_dataObject->data->premium_price_to_verify) && $this->_dataObject->data->premium_price_to_verify != "") $cmd['attributes']['premium_price_to_verify'] = $this->_dataObject->data->premium_price_to_verify;
		if (isSet($this->_dataObject->data->reg_domain) && $this->_dataObject->data->reg_domain != "") $cmd['attributes']['reg_domain'] = $this->_dataObject->data->reg_domain;
		
		// NS records
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

		// ccTLD specific
		if ($ccTLD == "ca") {
			$cmd['attributes']['isa_trademark'] = $this->_dataObject->data->isa_trademark;
			$cmd['attributes']['lang_pref'] = $this->_dataObject->data->lang_pref;
			$cmd['attributes']['legal_type'] = strtoupper($this->_dataObject->data->legal_type);
			if (isSet($this->_dataObject->data->ca_link_domain) && $this->_dataObject->data->ca_link_domain != "") $cmd['attributes']['ca_link_domain'] = $this->_dataObject->data->ca_link_domain;
			if (isSet($this->_dataObject->data->cwa) && $this->_dataObject->data->cwa != "") $cmd['attributes']['cwa'] = $this->_dataObject->data->cwa;
			if (isSet($this->_dataObject->data->domain_description) && $this->_dataObject->data->domain_description != "") $cmd['attributes']['domain_description'] = $this->_dataObject->data->domain_description;
			if (isSet($this->_dataObject->data->rant_agrees) && $this->_dataObject->data->rant_agrees != "") $cmd['attributes']['rant_agrees'] = $this->_dataObject->data->rant_agrees;
			if (isSet($this->_dataObject->data->rant_no) && $this->_dataObject->data->rant_no != "") $cmd['attributes']['rant_no'] = $this->_dataObject->data->rant_no;
		}
		
		if ($ccTLD == "asia") {
			$cmd['attributes']['tld_data']['ced_info']['contact_type'] = $this->_dataObject->cedinfo->contact_type;
			$cmd['attributes']['tld_data']['ced_info']['id_number'] = $this->_dataObject->cedinfo->id_number;
			$cmd['attributes']['tld_data']['ced_info']['id_type'] = $this->_dataObject->cedinfo->id_type;
			$cmd['attributes']['tld_data']['ced_info']['legal_entity_type'] = $this->_dataObject->cedinfo->legal_entity_type;
			$cmd['attributes']['tld_data']['ced_info']['locality_country'] = $this->_dataObject->cedinfo->locality_country;
			if (isSet($this->_dataObject->cedinfo->id_type_info) && $this->_dataObject->cedinfo->id_type_info != "") $cmd['attributes']['tld_data']['ced_info']['id_type_info'] = $this->_dataObject->cedinfo->id_type_info;
			if (isSet($this->_dataObject->cedinfo->legal_entity_type_info) && $this->_dataObject->cedinfo->legal_entity_type_info != "") $cmd['attributes']['tld_data']['ced_info']['legal_entity_type_info'] = $this->_dataObject->cedinfo->legal_entity_type_info;
			if (isSet($this->_dataObject->cedinfo->locality_city) && $this->_dataObject->cedinfo->locality_city != "") $cmd['attributes']['tld_data']['ced_info']['locality_city'] = $this->_dataObject->cedinfo->locality_city;
			if (isSet($this->_dataObject->cedinfo->locality_state_prov) && $this->_dataObject->cedinfo->locality_state_prov != "") $cmd['attributes']['tld_data']['ced_info']['locality_state_prov'] = $this->_dataObject->cedinfo->locality_state_prov;
		}
		
		if ($ccTLD == "eu"){
			$cmd['attributes']['country'] = strtoupper($this->_dataObject->data->country);
			$cmd['attributes']['lang'] = $this->_dataObject->data->lang;
			$cmd['attributes']['owner_confirm_address'] = $this->_dataObject->data->owner_confirm_address;
		}
		
		if ($ccTLD == "be"){
			$cmd['attributes']['lang'] = $this->_dataObject->data->lang;
			$cmd['attributes']['owner_confirm_address'] = $this->_dataObject->data->owner_confirm_address;
		}

		if ($ccTLD == "de"){
			$cmd['attributes']['owner_confirm_address'] = $this->_dataObject->data->owner_confirm_address;
		}

		if ($ccTLD == "name") {
			$cmd['attributes']['tld_data']['forwarding_email'] = $this->_dataObject->data->forwarding_email;
		}

		if ($ccTLD == "us") {
			$cmd['attributes']['tld_data']['nexus']['app_purpose'] = $this->_dataObject->nexus->app_purpose;
			$cmd['attributes']['tld_data']['nexus']['category'] = $this->_dataObject->nexus->category;
			if (isSet($this->_dataObject->nexus->validator) && $this->_dataObject->nexus->validator != "") $cmd['attributes']['tld_data']['nexus']['validator'] = $this->_dataObject->nexus->validator;
		}
		
		
		// Process the call
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
