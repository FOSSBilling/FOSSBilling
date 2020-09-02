<?php
/*
 *  Required object values:
 *  data - 
 */
 
class bulkChange extends openSRS_base {
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
		if (!isSet($this->_dataObject->data->change_items) || $this->_dataObject->data->change_items == "") {
			trigger_error ("oSRS Error - change_items is not defined.", E_USER_WARNING);
			$allPassed = false;
		}
		if (!isSet($this->_dataObject->data->change_type) || $this->_dataObject->data->change_type == "") {
			trigger_error ("oSRS Error - change_type is not defined.", E_USER_WARNING);
			$allPassed = false;
		}else{

			switch($this->_dataObject->data->change_type){
				case "availability_check":
					break;
				case "domain_renew":
					if(!$this->_allDomainRenewCheck())
						$allPassed=false;
					break;
				case "push_domains":
					if(!$this->_allPushDomainsCheck())
						$allPassed=false;
					break;
				case "dns_zone":
					if(!$this->_allDnsZoneCheck())
						$allPassed=false;
					break;
				case "dns_zone_record":
					if(!$this->_allDnsZoneRecordCheck())
						$allPassed=false;
					break;
				case "domain_contacts":
					if(!$this->_allDomainContactsCheck())
						$allPassed=false;
					break;
				case "domain_forwarding":
					if(!$this->_allDomainForwardingCheck())
						$allPassed=false;
					break;
				case "domain_lock":
					if(!$this->_allDomainLockCheck())
						$allPassed=false;
					break;
				case "domain_parked_pages":
					if(!$this->_allDomainParkedPagesCheck())
						$allPassed=false;
					break;
				case "domain_nameservers":
					if(!$this->_allDomainNameserversCheck())
						$allPassed=false;
					break;
				case "whois_privacy":
					if(!$this->_allWhoisPrivacyCheck())
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

	private function _allDomainRenewCheck(){
		$allDomainRenewPassed=true;

		if(!isSet($this->_dataObject->data->period) && $this->_dataObject->data->period == "" && 
		!isSet($this->_dataObject->data->let_expire) && $this->_dataObject->data->let_expire == "" && 
		!isSet($this->_dataObject->data->auto_renew) && $this->_dataObject->data->auto_renew == "" ) {

			trigger_error ("oSRS Error - change type is domain_renew but at least one of period, let_expire or auto_renew has to be defined.", E_USER_WARNING);
			$allDomainRenewPassed = false;
		}

		return $allDomainRenewPassed;
	}

	private function _allPushDomainsCheck(){
		$allPushDomainsPassed=true;

		if (!isSet($this->_dataObject->data->gaining_reseller_username) || $this->_dataObject->data->gaining_reseller_username == "") {
			trigger_error ("oSRS Error - change type is dns_zone but gaining_reseller_username is not defined.", E_USER_WARNING);
			$allPushDomainsPassed = false;
		}
		return $allPushDomainsPassed;
	}

	private function _allDnsZoneCheck(){
		$allDnsZonePassed=true;
		if (!isSet($this->_dataObject->data->apply_to_domains) || $this->_dataObject->data->apply_to_domains == "") {
			trigger_error ("oSRS Error - change type is dns_zone but apply_to_domains is not defined.", E_USER_WARNING);
			$allDnsZonePassed = false;
		}
		if (!isSet($this->_dataObject->data->dns_action) || $this->_dataObject->data->dns_action == "") {
			trigger_error ("oSRS Error - change type is dns_zone but dns_action is not defined.", E_USER_WARNING);
			$allDnsZonePassed = false;
		}
		return $allDnsZonePassed;
	}

	private function _allDnsZoneRecordCheck(){
		$allDnsZoneRecordPassed=true;
		if (!isSet($this->_dataObject->data->dns_action) || $this->_dataObject->data->dns_action == "") {
			trigger_error ("oSRS Error - change type is dns_zone_record but dns_action is not defined.", E_USER_WARNING);
			$allDnsZoneRecordPassed = false;
		}
		if (!isSet($this->_dataObject->data->dns_record_type) || $this->_dataObject->data->dns_record_type == "") {
			trigger_error ("oSRS Error - change type is dns_zone_record but dns_record_type is not defined.", E_USER_WARNING);
			$allDnsZoneRecordPassed = false;
		}
		if (!isSet($this->_dataObject->data->dns_record_data) || $this->_dataObject->data->dns_record_data == "") {
			trigger_error ("oSRS Error - change type is dns_zone_record but dns_record_data is not defined.", E_USER_WARNING);
			$allDnsZoneRecordPassed = false;
		}
		return $allDnsZoneRecordCheck;
	}

	private function _allDomainContactsCheck(){
		$allDomainContactsPassed=true;
		if (!isSet($this->_dataObject->data->type) || $this->_dataObject->data->type == "") {
			trigger_error ("oSRS Error - change type is domain_contacts but type is not defined.", E_USER_WARNING);
			$alllDomainContactsPassed = false;
		}
		if (!isSet($this->_dataObject->personal) || $this->_dataObject->personal == "") {
			trigger_error ("oSRS Error - change type is domain_contacts but personal is not defined.", E_USER_WARNING);
			$alllDomainContactsPassed = false;
		}
		return $allDomainContactsPassed;
	}

	private function _allDomainForwardingCheck(){
		$allDomainForwardingPassed=true;
		if (!isSet($this->_dataObject->data->op_type) || $this->_dataObject->data->op_type == "") {
			trigger_error ("oSRS Error - change type is domain_forwarding but op_type is not defined.", E_USER_WARNING);
			$allDomainForwardingPassed = false;
		}
		return $allDomainForwardingPassed;
	}
	
	private function _allDomainLockCheck(){
		$allDomainLockPassed=true;
		if (!isSet($this->_dataObject->data->op_type) || $this->_dataObject->data->op_type == "") {
			trigger_error ("oSRS Error - change type is domain_lock but op_type is not defined.", E_USER_WARNING);
			$allDomainLockPassed = false;
		}
		return $allDomainLockPassed;
	}

	private function _allDomainNameserversCheck(){
		$allDomainNameserversPassed=true;
		if (!isSet($this->_dataObject->data->op_type) || $this->_dataObject->data->op_type == "") {
			trigger_error ("oSRS Error - change type is domain_nameservers but op_type is not defined.", E_USER_WARNING);
			$allDomainNameserversPassed = false;
		}
		if(!isSet($this->_dataObject->data->add_ns) && $this->_dataObject->data->add_ns == "" && 
		!isSet($this->_dataObject->data->remove_ns) && $this->_dataObject->data->remove_ns == "" && 
		!isSet($this->_dataObject->data->assign_ns) && $this->_dataObject->data->assign_ns == "" ) {

			trigger_error ("oSRS Error - change type is domain_nameservers but at least one of add_ns, remove_ns or assign_ns has to be defined.", E_USER_WARNING);
			$allDomainNameserversPassed = false;
		}
		return $allDomainNameserversPassed;
	}

	private function _allDomainParkedPagesCheck(){
		$allDomainParkedPagesPassed=true;
		if (!isSet($this->_dataObject->data->op_type) || $this->_dataObject->data->op_type == "") {
			trigger_error ("oSRS Error - change type is domain_parked_pages but op_type is not defined.", E_USER_WARNING);
			$allDomainParkedPagesPassed = false;
		}
		return $allDomainParkedPagesPassed;
	}

	private function _allWhoisPrivacyCheck(){
		$allWhoisPrivacyPassed=true;
		if (!isSet($this->_dataObject->data->op_type) || $this->_dataObject->data->op_type == "") {
			trigger_error ("oSRS Error - change type is whois_privacy but op_type is not defined.", E_USER_WARNING);
			$allWhoisPrivacyPassed = false;
		}
		return $allWhoisPrivacyPassed;
	}

	// Post validation functions
	private function _processRequest (){
		$this->_dataObject->data->change_items = explode (",", $this->_dataObject->data->change_items);
	
		$cmd = array(
			'protocol' => 'XCP',
			'action' => 'submit_bulk_change',
			'object' => 'bulk_change',
			'attributes' => array (
				'change_type' => $this->_dataObject->data->change_type,
				'change_items' => $this->_dataObject->data->change_items
			)
		);
		
		// Command optional values
		if (isSet($this->_dataObject->data->apply_to_locked_domains) && $this->_dataObject->data->apply_to_locked_domains != "") 
			$cmd['attributes']['apply_to_locked_domains'] = $this->_dataObject->data->apply_to_locked_domains;
		if (isSet($this->_dataObject->data->contact_email) && $this->_dataObject->data->contact_email != "") 
			$cmd['attributes']['contact_email'] = $this->_dataObject->data->contact_email;
		if (isSet($this->_dataObject->data->apply_to_all_reseller_items) && $this->_dataObject->data->apply_to_all_reseller_items!= "") 
			$cmd['attributes']['apply_to_all_reseller_items'] = $this->_dataObject->data->apply_to_all_reseller_items;

		switch($this->_dataObject->data->change_type){
			case "availability_check":
				break;
			case "domain_renew":
				if (isSet($this->_dataObject->data->period) && $this->_dataObject->data->period!= "") 
					$cmd['attributes']['period'] = $this->_dataObject->data->period;
				if (isSet($this->_dataObject->data->let_expire) && $this->_dataObject->data->let_expire!= "") 
					$cmd['attributes']['let_expire'] = $this->_dataObject->data->let_expire;
				if (isSet($this->_dataObject->data->auto_renew) && $this->_dataObject->data->auto_renew!= "") 
					$cmd['attributes']['auto_renew'] = $this->_dataObject->data->auto_renew;
				if (isSet($this->_dataObject->data->affiliate_id) && $this->_dataObject->data->affiliate_id!= "") 
					$cmd['attributes']['affiliate_id'] = $this->_dataObject->data->affiliate_id;
				break;
			case "push_domains":
				if (isSet($this->_dataObject->data->gaining_reseller_username) && $this->_dataObject->data->gaining_reseller_username!= "") 
					$cmd['attributes']['gaining_reseller_username'] = $this->_dataObject->data->gaining_reseller_username;
				break;
			case "dns_zone":
				if (isSet($this->_dataObject->data->apply_to_domains) && $this->_dataObject->data->apply_to_domains!= "") 
					$cmd['attributes']['apply_to_domains'] = $this->_dataObject->data->apply_to_domains;
				if (isSet($this->_dataObject->data->dns_action) && $this->_dataObject->data->dns_action!= "") 
					$cmd['attributes']['dns_action'] = $this->_dataObject->data->dns_action;
				if (isSet($this->_dataObject->data->dns_template) && $this->_dataObject->data->dns_template!= "") 
					$cmd['attributes']['dns_template'] = $this->_dataObject->data->dns_template;
				if (isSet($this->_dataObject->data->only_if) && $this->_dataObject->data->only_if!= "") 
					$cmd['attributes']['only_if'] = $this->_dataObject->data->only_if;
				if (isSet($this->_dataObject->data->force_dns_nameservers) && $this->_dataObject->data->force_dns_nameservers!= "") 
					$cmd['attributes']['force_dns_nameservers'] = $this->_dataObject->data->force_dns_nameservers;
				break;
			case "dns_zone_record":
				if (isSet($this->_dataObject->data->dns_action) && $this->_dataObject->data->dns_action!= "") 
					$cmd['attributes']['dns_action'] = $this->_dataObject->data->dns_action;
				if (isSet($this->_dataObject->data->dns_record_type) && $this->_dataObject->data->dns_record_type!= "") 
					$cmd['attributes']['dns_record_type'] = $this->_dataObject->data->dns_record_type;
				if (isSet($this->_dataObject->data->dns_record_data->ip_address) && $this->_dataObject->data->dns_record_data->ip_address!= "") 
					$cmd['attributes']['dns_record_data']['ip_address'] = $this->_dataObject->data->dns_record_data->ip_address;
				if (isSet($this->_dataObject->data->dns_record_data->subdomain) && $this->_dataObject->data->dns_record_data->subdomain!= "") 
					$cmd['attributes']['dns_record_data']['subdomain'] = $this->_dataObject->data->dns_record_data->subdomain;
				if (isSet($this->_dataObject->data->dns_record_data->ipv6_address) && $this->_dataObject->data->dns_record_data->ipv6_address!= "") 
					$cmd['attributes']['dns_record_data']['ipv6_address'] = $this->_dataObject->data->dns_record_data->ipv6_address;
				if (isSet($this->_dataObject->data->dns_record_data->hostname) && $this->_dataObject->data->dns_record_data->hostname!= "") $
					$cmd['attributes']['dns_record_data']['hostname'] = $this->_dataObject->data->dns_record_data->hostname;
				if (isSet($this->_dataObject->data->dns_record_data->priority) && $this->_dataObject->data->dns_record_data->priority!= "") 
					$cmd['attributes']['dns_record_data']['priority'] = $this->_dataObject->data->dns_record_data->priority;
				if (isSet($this->_dataObject->data->dns_record_data->weight) && $this->_dataObject->data->dns_record_data->weight!= "") 
					$cmd['attributes']['dns_record_data']['weight'] = $this->_dataObject->data->dns_record_data->weight;
				if (isSet($this->_dataObject->data->dns_record_data->port) && $this->_dataObject->data->dns_record_data->port!= "") 
					$cmd['attributes']['dns_record_data']['port'] = $this->_dataObject->data->dns_record_data->port;
				if (isSet($this->_dataObject->data->dns_record_data->text) && $this->_dataObject->data->dns_record_data->text!= "") 
					$cmd['attributes']['dns_record_data']['text'] = $this->_dataObject->data->dns_record_data->text;
				if (isSet($this->_dataObject->data->only_if) && $this->_dataObject->data->only_if!= "") 
					$cmd['attributes']['only_if'] = $this->_dataObject->data->only_if;
				break;
			case "domain_contacts":
				// Allows for multiple contact changes with the same data
				if (isSet($this->_dataObject->data->type) && $this->_dataObject->data->type!= "" && 
				    isSet($this->_dataObject->personal) && $this->_dataObject->personal!= ""){

					$contact_types=explode (",", $this->_dataObject->data->type);

					$i=0;
					foreach($contact_types as $contact_type){
						$cmd['attributes']['contacts'][$i]['type'] = $contact_type;
						$cmd['attributes']['contacts'][$i]['set'] = $this->_createUserData();
						$i++;
					}
				}
				break;
			case "domain_forwarding":
				if (isSet($this->_dataObject->data->op_type) && $this->_dataObject->data->op_type!= "") 
					$cmd['attributes']['op_type'] = $this->_dataObject->data->op_type;
				break;
			case "domain_lock":
				if (isSet($this->_dataObject->data->op_type) && $this->_dataObject->data->op_type!= "") 
					$cmd['attributes']['op_type'] = $this->_dataObject->data->op_type;
				break;
			case "domain_parked_pages":
				if (isSet($this->_dataObject->data->op_type) && $this->_dataObject->data->op_type!= "") 
					$cmd['attributes']['op_type'] = $this->_dataObject->data->op_type;
				break;
			case "domain_nameservers":
				if (isSet($this->_dataObject->data->op_type) && $this->_dataObject->data->op_type!= "") 
					$cmd['attributes']['op_type'] = $this->_dataObject->data->op_type;
				if (isSet($this->_dataObject->data->add_ns) && $this->_dataObject->data->add_ns!= "") 
					$cmd['attributes']['add_ns'] = explode(",",$this->_dataObject->data->add_ns);
				if (isSet($this->_dataObject->data->remove_ns) && $this->_dataObject->data->remove_ns!= "") 
					$cmd['attributes']['remove_ns'] = explode(",",$this->_dataObject->data->remove_ns);
				if (isSet($this->_dataObject->data->assign_ns) && $this->_dataObject->data->assign_ns!= "") 
					$cmd['attributes']['assign_ns'] = explode(",",$this->_dataObject->data->assign_ns);
				break;
			case "whois_privacy":
				if (isSet($this->_dataObject->data->op_type) && $this->_dataObject->data->op_type!= "") 
					$cmd['attributes']['op_type'] = $this->_dataObject->data->op_type;
				break;
			default:
				break;
		}

		

		
	
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
