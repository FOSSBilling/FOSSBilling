<?php
/*
 *  Required object values:
 *  data - 
 */
 
class provModify extends openSRS_base {
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
		if (!isSet($this->_dataObject->data->cookie) || $this->_dataObject->data->cookie == "") {
			trigger_error ("oSRS Error - domain is not defined.", E_USER_WARNING);
			$allPassed = false;
		}
		if (!isSet($this->_dataObject->data->affect_domains) || $this->_dataObject->data->affect_domains == "") {
			trigger_error ("oSRS Error - affect_domains is not defined.", E_USER_WARNING);
			$allPassed = false;
		}
		if (!isSet($this->_dataObject->data->data) || $this->_dataObject->data->data == "") {
			trigger_error ("oSRS Error - data variable that defines the modify type is not defined.", E_USER_WARNING);
			$allPassed = false;
				
		}else{

			switch($this->_dataObject->data->data){
				case "ced_info":
					if(!$this->_allCedInfoCheck())
						$allPassed=false;
					break;
				case "rsp_whois_info":
					if(!$this->_allRspWhoisInfoCheck())
						$allPassed=false;
					break;
				case "trademark":
					if(!$this->_allTrademarkCheck())
						$allPassed=false;
					break;
				case "change_ips_tag":
					if(!$this->_allChangeIpsTagCheck())
						$allPassed=false;
					break;
				case "contact_info":
					if(!$this->_allContactInfoCheck())
						$allPassed=false;
					break;
				case "domain_auth_info":
					if(!$this->_allDomainAuthInfoCheck())
						$allPassed=false;
					break;
				case "expire_action":
					if(!$this->_allExpireActionCheck())
						$allPassed=false;
					break;
				case "forwarding_email":
					if(!$this->_allForwardingEmailCheck())
						$allPassed=false;
					break;
				case "nexus_info":
					if(!$this->_allNexusInfoCheck())
						$allPassed=false;
					break;
				case "parkpage_state":
					if(!$this->_allParkpageStateCheck())
						$allPassed=false;
					break;
				case "status":
					if(!$this->_allStatusCheck())
						$allPassed=false;
					break;
				case "uk_whois_opt":
					if(!$this->_allUKWhoisOptCheck())
						$allPassed=false;
					break;
				case "whois_privacy_state":
					if(!$this->_allWhoisPrivacyStateCheck())
						$allPassed=false;
					break;
				default:
					trigger_error ("oSRS Error - Unknown change type.", E_USER_WARNING);
					$allPassed = false;
					break;
			}
		}
	

		// Run the command
		if ($allPassed) {
			// Execute the command
			$this->_processRequest ();
		} else {
			trigger_error ("oSRS Error - Incorrect call.", E_USER_WARNING);
		}
	}

	private function _allCedInfoCheck(){
		$allCedInfo=true;

		if (!isSet($this->_dataObject->data->ced_info) || $this->_dataObject->data->ced_info == "") {
			trigger_error ("oSRS Error - change type is ced_info but ced_info is not defined.", E_USER_WARNING);
			$allCedInfo = false;
		}

		if (!isSet($this->_dataObject->data->ced_info->contact_type) || $this->_dataObject->data->ced_info->contact_type == "") {
			trigger_error ("oSRS Error - change type is ced_info but contact_type is not defined.", E_USER_WARNING);
			$allCedInfo = false;
		}
		if (!isSet($this->_dataObject->data->ced_info->id_number) || $this->_dataObject->data->ced_info->id_number == "") {
			trigger_error ("oSRS Error - change type is ced_info but id_number is not defined.", E_USER_WARNING);
			$allCedInfo = false;
		}
		if (!isSet($this->_dataObject->data->ced_info->legal_entity_type) || $this->_dataObject->data->ced_info->legal_entity_type == "") {
			trigger_error ("oSRS Error - change type is ced_info but legal_entity_type is not defined.", E_USER_WARNING);
			$allCedInfo= false;
		}
		if (!isSet($this->_dataObject->data->ced_info->locality_country) || $this->_dataObject->data->ced_info->locality_country == "") {
			trigger_error ("oSRS Error - change type is ced_info but locality_country is not defined.", E_USER_WARNING);
			$allCedInfo = false;
		}
		if (!isSet($this->_dataObject->data->ced_info->locality_state_prov) || $this->_dataObject->data->ced_info->locality_state_prov == "") {
			trigger_error ("oSRS Error - change type is ced_info but locality_state_prov is not defined.", E_USER_WARNING);
			$allCedInfo = false;
		}
	
		return $allCedInfo;
	}


	private function _allRspWhoisInfoCheck(){
		$allRspWhoisInfo=true;

		if (!isSet($this->_dataObject->data->all) || $this->_dataObject->data->all == "") {
			trigger_error ("oSRS Error - change type is change_rsp_whois_info but all is not defined.", E_USER_WARNING);
			$allRspWhoisInfo = false;
		}
		if (!isSet($this->_dataObject->data->flag) || $this->_dataObject->data->flag == "") {
			trigger_error ("oSRS Error - change type is rsp_whois_info but flag is not defined.", E_USER_WARNING);
			$allRspWhoisInfo = false;
		}
	
		return $allRspWhoisInfo;
	}

	private function _allTrademarkCheck(){
		$allTrademarkInfo=true;
		if (!isSet($this->_dataObject->data->trademark) || $this->_dataObject->data->trademark == "") {
			trigger_error ("oSRS Error - change type is trademark but trademark is not defined.", E_USER_WARNING);
			$allTrademarkInfo = false;
		}
		return $allTrademarkInfo;
	}

	private function _allChangeIpsTagCheck(){
		$allChangeIpsTagInfo=true;
		if (!isSet($this->_dataObject->data->domain) || $this->_dataObject->data->domain == "") {
			trigger_error ("oSRS Error - change type is change_ips_tag but domain is not defined.", E_USER_WARNING);
			$allChangeIpsTagInfo = false;
		}
		if (!isSet($this->_dataObject->data->gaining_registrar_tag) || $this->_dataObject->data->gaining_registrar_tag == "") {
			trigger_error ("oSRS Error - change type is change_ips_tag but gaining_registrar_tag is not defined.", E_USER_WARNING);
			$allChangeIpsTagInfo = false;
		}
		return $allChangeIpsTagInfo;
	}

	private function _allContactInfoCheck(){
		$allContactInfo=true;
		if (!isSet($this->_dataObject->personal) || $this->_dataObject->personal == "") {
			trigger_error ("oSRS Error - change type is contact_info but personal is not defined.", E_USER_WARNING);
			$allContactInfo = false;
		}
		if (!isSet($this->_dataObject->data->contact_type) || $this->_dataObject->data->contact_type == "") {
			trigger_error ("oSRS Error - change type is contact_info but contact_type is not defined.", E_USER_WARNING);
			$allContactInfo = false;
		}

		// Personal information
		$reqPers = array ("first_name", "last_name", "org_name", "address1", "city", "country", "postal_code", "phone", "email", "lang_pref");
		for ($i = 0; $i < count($reqPers); $i++){
			if ($this->_dataObject->personal->$reqPers[$i] == "") {
				trigger_error ("oSRS Error - change type is contact_info but  ". $reqPers[$i] ." is not defined in personal.", E_USER_WARNING);
				$allContactInfo = false;
			}
		}
		return $allContactInfo;
	}

	private function _allDomainAuthInfoCheck(){
		$allDomainAuthInfo=true;
		if (!isSet($this->_dataObject->data->domain_auth_info) || $this->_dataObject->data->domain_auth_info == "") {
			trigger_error ("oSRS Error - data type is domain_auth_info but a domain_auth_info value is not defined.", E_USER_WARNING);
			$allDomainAuthInfo = false;
		}
		return $allDomainAuthInfo;
	}

	private function _allExpireActionCheck(){
		$allExpireActionInfo=true;
		if (!isSet($this->_dataObject->data->auto_renew) || $this->_dataObject->data->auto_renew == "") {
			trigger_error ("oSRS Error - data type is expire_action but auto_renew is not defined.", E_USER_WARNING);
			$allExpireActionInfo = false;
		}
		if (!isSet($this->_dataObject->data->let_expire) || $this->_dataObject->data->let_expire == "") {
			trigger_error ("oSRS Error - data type is expire_action but let_expire is not defined.", E_USER_WARNING);
			$allExpireActionInfo = false;
		}
		return $allExpireActionInfo;
	}

	private function _allForwardingEmailCheck(){
		$allForwardingEmailInfo=true;
		if (!isSet($this->_dataObject->data->forwarding_email) || $this->_dataObject->data->forwarding_email == "") {
			trigger_error ("oSRS Error - data type is forwarding_email but a forwarding_email is not defined.", E_USER_WARNING);
			$allForwardingEmailInfo = false;
		}
		return $allForwardingEmailInfo;
	}

	private function _allNexusInfoCheck(){
		$allNexusInfo=true;	
		if (!isSet($this->_dataObject->data->nexus) || $this->_dataObject->data->nexus == "") {
			trigger_error ("oSRS Error - data type is nexus_info but nexus is not defined.", E_USER_WARNING);
			$allNexusInfo = false;
		}
		if (!isSet($this->_dataObject->data->nexus->app_purpose) || $this->_dataObject->data->nexus->app_purpose == "") {
			trigger_error ("oSRS Error - data type is nexus_info but app_purpose is not defined.", E_USER_WARNING);
			$allNexusInfo = false;
		}
		if (!isSet($this->_dataObject->data->nexus->category) || $this->_dataObject->data->nexus->category == "") {
			trigger_error ("oSRS Error - data type is nexus_info but category is not defined.", E_USER_WARNING);
			$allNexusInfo = false;
		}
		return $allNexusInfo;
	}

	private function _allParkpageStateCheck(){
		$allParkpageStateInfo=true;
		if (!isSet($this->_dataObject->data->domain) || $this->_dataObject->data->domain == "") {
			trigger_error ("oSRS Error - data type is parkpage_state but domain is not defined.", E_USER_WARNING);
			$allParkpageStateInfo = false;
		}
		if (!isSet($this->_dataObject->data->state) || $this->_dataObject->data->state == "") {
			trigger_error ("oSRS Error - data type is parkpage_state but state is not defined.", E_USER_WARNING);
			$allParkPageStateInfo = false;
		}
		return $allParkpageStateInfo;
	}


	private function _allUKWhoisOptCheck(){
		$allUKWhoisOptInfo=true;
		if (!isSet($this->_dataObject->data->reg_type) || $this->_dataObject->data->reg_type == "") {
			trigger_error ("oSRS Error - change type is uk_whois_opt but reg_type is not defined.", E_USER_WARNING);
			$allUKWhoisOptInfo = false;
		}
		if (!isSet($this->_dataObject->data->uk_whois_opt) || $this->_dataObject->data->uk_whois_opt == "") {
			trigger_error ("oSRS Error - change type is uk_whois_opt but uk_whois_opt is not defined.", E_USER_WARNING);
			$allUKWhoisOptInfo = false;
		}
		return $allUKWhoisOptInfo;
	}

	private function _allStatusCheck(){
		$allStatusInfo=true;
		if (!isSet($this->_dataObject->data->lock_state) || $this->_dataObject->data->lock_state == "") {
			trigger_error ("oSRS Error - data type is status but lock_state is not defined.", E_USER_WARNING);
			$allStatusInfo = false;
		}
		if (!isSet($this->_dataObject->data->domain_name) || $this->_dataObject->data->domain_name == "") {
			trigger_error ("oSRS Error - data type is status but domain_name is not defined.", E_USER_WARNING);
			$allStatusInfo = false;
		}
		return $allStatusInfo;
	}

	private function _allWhoisPrivacyStateCheck(){
		$allWhoisPrivacyStateInfo=true;
		if (!isSet($this->_dataObject->data->state) || $this->_dataObject->data->state == "") {
			trigger_error ("oSRS Error - change type is whois_privacy_state but state is not defined.", E_USER_WARNING);
			$allWhoisPrivacyStateInfo = false;
		}
		return $allWhoisPrivacyStateInfo;
	}

	// Post validation functions
	private function _processRequest (){
                  $cmd = array(
                         'protocol' => 'XCP',
                         'action' => 'modify',
                         'object' => 'DOMAIN',
			 'cookie' => $this->_dataObject->data->cookie,
                         'attributes' => array (
                                     'affect_domains' => $this->_dataObject->data->affect_domains,
                                     'data' => $this->_dataObject->data->data
                            )
                  );
		
		// Command optional values
		
		switch($this->_dataObject->data->data){
			case "ced_info":
				// ced_info data
				if (isSet($this->_dataObject->data->ced_info->contact_type) && $this->_dataObject->data->ced_info->contact_type != "") 
					$cmd['attributes']['ced_info']['contact_type'] = $this->_dataObject->data->ced_info->contact_type;
				if (isSet($this->_dataObject->data->ced_info->id_number) && $this->_dataObject->data->ced_info->id_number != "") 
					$cmd['attributes']['ced_info']['id_number'] = $this->_dataObject->data->ced_info->id_number;
				if (isSet($this->_dataObject->data->ced_info->id_type) && $this->_dataObject->data->ced_info->id_type != "") 
					$cmd['attributes']['ced_info']['id_type'] = $this->_dataObject->data->ced_info->id_type;
				if (isSet($this->_dataObject->data->ced_info->id_type_info) && $this->_dataObject->data->ced_info->id_type_info != "") 
					$cmd['attributes']['ced_info']['id_type_info'] = $this->_dataObject->data->ced_info->id_type_info;
				if (isSet($this->_dataObject->data->ced_info->legal_entity_type) && $this->_dataObject->data->ced_info->legal_entity_type != "") 
					$cmd['attributes']['ced_info']['legal_entity_type'] = $this->_dataObject->data->ced_info->legal_entity_type;
				if (isSet($this->_dataObject->data->ced_info->legal_entity_type_info) && $this->_dataObject->data->ced_info->legal_entity_type_info != "") 
					$cmd['attributes']['ced_info']['legal_entity_type_info'] = $this->_dataObject->data->ced_info->legal_entity_type_info;
				if (isSet($this->_dataObject->data->ced_info->locality_city) && $this->_dataObject->data->ced_info->locality_city != "") 
					$cmd['attributes']['ced_info']['locality_city'] = $this->_dataObject->data->ced_info->locality_city;
				if (isSet($this->_dataObject->data->ced_info->locality_country) && $this->_dataObject->data->ced_info->locality_country != "") 
					$cmd['attributes']['ced_info']['locality_country'] = $this->_dataObject->data->ced_info->locality_country;
				if (isSet($this->_dataObject->data->ced_info->locality_state_prov) && $this->_dataObject->data->ced_info->locality_state_prov != "") 
					$cmd['attributes']['ced_info']['localicty_state_prov'] = $this->_dataObject->data->ced_info->locality_state_prov;
					
				break;
			case "rsp_whois_info":
				//rsp_whois_info data
				if (isSet($this->_dataObject->data->all) && $this->_dataObject->data->all != "") 
					$cmd['attributes']['all'] = $this->_dataObject->data->all;
				if (isSet($this->_dataObject->data->affect_domains) && $this->_dataObject->data->affect_domains != "") 
					$cmd['attributes']['affect_domains'] = $this->_dataObject->data->affect_domains;
				if (isSet($this->_dataObject->data->flag) && $this->_dataObject->data->flag != "") 
					$cmd['attributes']['flag'] = $this->_dataObject->data->flag;
				break;
			case "trademark":
				//trademark data
				if (isSet($this->_dataObject->data->trademark) && $this->_dataObject->data->trademark != "") 
					$cmd['attributes']['trademark'] = $this->_dataObject->data->trademark;
				break;
			case "change_ips_tag":
				//change_ips_tag data
				if (isSet($this->_dataObject->data->domain) && $this->_dataObject->data->domain != "") 
					$cmd['attributes']['domain'] = $this->_dataObject->data->domain;
				if (isSet($this->_dataObject->data->change_tag_all) && $this->_dataObject->data->change_tag_all != "") 
					$cmd['attributes']['change_tag_all'] = $this->_dataObject->data->change_tag_all;
				if (isSet($this->_dataObject->data->gaining_registrar_tag) && $this->_dataObject->data->gaining_registrar_tag != "") 
					$cmd['attributes']['gaining_registrar_tag'] = $this->_dataObject->data->gaining_registrar_tag;
				if (isSet($this->_dataObject->data->rsp_override) && $this->_dataObject->data->rsp_override != "") 
					$cmd['attributes']['rsp_override'] = $this->_dataObject->data->rsp_override;
				break;
			case "contact_info":
				//contact_info data
				if (isSet($this->_dataObject->data->report_email) && $this->_dataObject->data->report_email != "") 
					$cmd['attributes']['report_email'] = $this->_dataObject->data->report_email;
				if (isSet($this->_dataObject->data->contact_type) && $this->_dataObject->data->contact_type != ""){
					$contact_types=explode(",",$this->_dataObject->data->contact_type);
					foreach($contact_types as $contact_type)
						$cmd['attributes']['contact_set'][$contact_type] = $this->_createUserData();
				}
				break;
			case "domain_auth_info":
				//domain_auth_info data
				if (isSet($this->_dataObject->data->domain_auth_info) && $this->_dataObject->data->domain_auth_info != "") 
					$cmd['attributes']['domain_auth_info'] = $this->_dataObject->data->domain_auth_info;
				break;
			case "expire_action":
				//expire_action data
				if (isSet($this->_dataObject->data->auto_renew) && $this->_dataObject->data->auto_renew != "") 
					$cmd['attributes']['auto_renew'] = $this->_dataObject->data->auto_renew;
				if (isSet($this->_dataObject->data->let_expire) && $this->_dataObject->data->let_expire != "") 
					$cmd['attributes']['let_expire'] = $this->_dataObject->data->let_expire;
				break;
			case "forwarding_email":
				if (isSet($this->_dataObject->data->forwarding_email) && $this->_dataObject->data->forwarding_email != "") 
					$cmd['attributes']['forwarding_email'] = $this->_dataObject->data->forwarding_email;
				break;
			case "nexus_info":
				//nexus_info data
				if (isSet($this->_dataObject->data->nexus->app_purpose) && $this->_dataObject->data->nexus->app_purpose != "") 
					$cmd['attributes']['nexus']['app_purpose'] = $this->_dataObject->data->nexus->app_purpose;
				if (isSet($this->_dataObject->data->nexus->category) && $this->_dataObject->data->nexus->category != "") 
					$cmd['attributes']['nexus']['category'] = $this->_dataObject->data->nexus->category;
				if (isSet($this->_dataObject->data->nexus->validator) && $this->_dataObject->data->nexus->validator != "") 
					$cmd['attributes']['nexus']['validator'] = $this->_dataObject->data->nexus->validator;
				break;
			case "parkpage_state":
				//parkpage_state
				if (isSet($this->_dataObject->data->domain) && $this->_dataObject->data->domain != "") 
					$cmd['attributes']['domain'] = $this->_dataObject->data->domain;
				if (isSet($this->_dataObject->data->state) && $this->_dataObject->data->state != "") 
					$cmd['attributes']['state'] = $this->_dataObject->data->state;
				break;
			case "status":
				//status data
				if (isSet($this->_dataObject->data->lock_state) && $this->_dataObject->data->lock_state != "") 
					$cmd['attributes']['lock_state'] = $this->_dataObject->data->lock_state;
				if (isSet($this->_dataObject->data->domain_name) && $this->_dataObject->data->domain_name != "") 
					$cmd['attributes']['domain_name'] = $this->_dataObject->data->domain_name;
				break;
			case "uk_whois_opt":
				//uk_whois_opt
				if (isSet($this->_dataObject->data->reg_type) && $this->_dataObject->data->reg_type != "")
					$cmd['attributes']['reg_type'] = $this->_dataObject->data->reg_type;
				if (isSet($this->_dataObject->data->uk_affect_domains) && $this->_dataObject->data->uk_affect_domains != "") 
					$cmd['attributes']['uk_affect_domains'] = $this->_dataObject->data->uk_affect_domains;
				if (isSet($this->_dataObject->data->uk_whois_opt) && $this->_dataObject->data->uk_whois_opt != "") 
					$cmd['attributes']['uk_whois_opt'] = $this->_dataObject->data->uk_whois_opt;
				break;
			case "whois_privacy_state":
				//whois_privacy_state
				if (isSet($this->_dataObject->data->state) && $this->_dataObject->data->state != "") 
					$cmd['attributes']['state'] = $this->_dataObject->data->state;
				if (isSet($this->_dataObject->data->affect_domains) && $this->_dataObject->data->affect_domains != "") 
					$cmd['attributes']['affect_domains'] = $this->_dataObject->data->affect_domains;
				break;
			default:
				break;
		}
		
		// Run the command
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
