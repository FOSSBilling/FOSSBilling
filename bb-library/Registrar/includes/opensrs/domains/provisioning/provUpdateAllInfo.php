<?php
/*
 *  Required object values:
 *  data - 
 */
 
class provUpdateAllInfo extends openSRS_base {
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
		if (!isSet($this->_dataObject->data->domain) || $this->_dataObject->data->domain  == "") {
			trigger_error ("oSRS Error - domain is not defined.", E_USER_WARNING);
			$allPassed = false;
		}

		// Check that the full contact set is there
		if (!isSet($this->_dataObject->data->owner_contact) || $this->_dataObject->data->owner_contact  == ""){
			trigger_error ("oSRS Error - owner_contact is not defined.", E_USER_WARNING);
			$allPassed = false;
		}else{
			if(!$this->_allRequired("owner_contact"))
				$allPassed=false;
		
		}
		if (!isSet($this->_dataObject->data->admin_contact) || $this->_dataObject->data->admin_contact  == ""){
			trigger_error ("oSRS Error - admin_contact is not defined.", E_USER_WARNING);
			$allPassed = false;
		}else{
			if(!$this->_allRequired("admin_contact"))
				$allPassed=false;
			
		}
		if (!isSet($this->_dataObject->data->tech_contact) || $this->_dataObject->data->tech_contact  == ""){
			trigger_error ("oSRS Error - tech_contact in is not defined.", E_USER_WARNING);
			$allPassed = false;
		}else{
			if(!$this->_allRequired("tech_contact"))
				$allPassed=false;
		}	
		if (!isSet($this->_dataObject->data->billing_contact) || $this->_dataObject->data->billing_contact  == ""){
				trigger_error ("oSRS Error - billing_contact is not defined.", E_USER_WARNING);
				$allPassed = false;
		}else{
			if(!$this->_allRequired("billing_contact"))
				$allPassed=false;
		}
		
		//Check Nameserver Values
		if (!isSet($this->_dataObject->data->nameserver_names) || $this->_dataObject->data->nameserver_names  == "") {
			trigger_error ("oSRS Error - The function requires at least one nameserver is provided.", E_USER_WARNING);
			$allPassed = false;
		}

		//Check there are the samenumber of Nameserver IP values are there are  Nameserver Name values
		if (isSet($this->_dataObject->data->nameserver_ips)  && $this->_dataObject->data->nameserver_ips  != "") {
			if(count(explode(",",$this->_dataObject->data->nameserver_ips)) != count(explode(",",$this->_dataObject->data->nameserver_names))){
				trigger_error ("oSRS Error - The function requires the same number of Nameserver IP addresses as Nameserver names if you are defining Nameserver IP addresses.", E_USER_WARNING);
				$allPassed = false;
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

			
	private function _allRequired($contact){
		$allInfo = true;
		// Check Contact information
		$reqPers = array ("first_name", "last_name", "org_name", "address1", "city", "state", "country", "postal_code", "phone", "email", "lang_pref");
		for ($i = 0; $i < count($reqPers); $i++){
			if ($this->_dataObject->data->$contact->$reqPers[$i] == "") {
				trigger_error ("oSRS Error -  ". $reqPers[$i] ." is not defined in $contact contact set.", E_USER_WARNING);
				$allInfo = false;
			}
		}

		return $allInfo;
	}

	// Post validation functions
	private function _processRequest (){

	$cmd = array(
			'protocol' => 'XCP',
			'action' => 'update_all_info',
			'object' => 'domain',
			'domain' => $this->_dataObject->data->domain,
			'attributes' => array (
				'domain' =>$this->_dataObject->data->domain,
				'nameserver_list' => $name_servers,
				'contact_set' => array(
						'owner' => array(
							"first_name" => $this->_dataObject->data->owner_contact->first_name,
							"last_name" => $this->_dataObject->data->owner_contact->last_name,
							"org_name" => $this->_dataObject->data->owner_contact->org_name,
							"address1" => $this->_dataObject->data->owner_contact->address1,
							"address2" => $this->_dataObject->data->owner_contact->address2,
							"address3" => $this->_dataObject->data->owner_contact->address3,
							"city" => $this->_dataObject->data->owner_contact->city,
							"state" => $this->_dataObject->data->owner_contact->state,
							"postal_code" => $this->_dataObject->data->owner_contact->postal_code,
							"country" => $this->_dataObject->data->owner_contact->country,
							"phone" => $this->_dataObject->data->owner_contact->phone,
							"fax" => $this->_dataObject->data->owner_contact->fax,
							"email" => $this->_dataObject->data->owner_contact->email,
							"lang_pref" => $this->_dataObject->data->owner_contact->lang_pref
							),
							
						'admin' => array(
							"first_name" => $this->_dataObject->data->admin_contact->first_name,
							"last_name" => $this->_dataObject->data->admin_contact->last_name,
							"org_name" => $this->_dataObject->data->admin_contact->org_name,
							"address1" => $this->_dataObject->data->admin_contact->address1,
							"address2" => $this->_dataObject->data->admin_contact->address2,
							"address3" => $this->_dataObject->data->admin_contact->address3,
							"city" => $this->_dataObject->data->admin_contact->city,
							"state" => $this->_dataObject->data->admin_contact->state,
							"postal_code" => $this->_dataObject->data->admin_contact->postal_code,
							"country" => $this->_dataObject->data->admin_contact->country,
							"phone" => $this->_dataObject->data->admin_contact->phone,
							"fax" => $this->_dataObject->data->admin_contact->fax,
							"email" => $this->_dataObject->data->admin_contact->email,
							"lang_pref" => $this->_dataObject->data->admin_contact->lang_pref
							),
							
						'tech' => array(
							"first_name" => $this->_dataObject->data->tech_contact->first_name,
							"last_name" => $this->_dataObject->data->tech_contact->last_name,
							"org_name" => $this->_dataObject->data->tech_contact->org_name,
							"address1" => $this->_dataObject->data->tech_contact->address1,
							"address2" => $this->_dataObject->data->tech_contact->address2,
							"address3" => $this->_dataObject->data->tech_contact->address3,
							"city" => $this->_dataObject->data->tech_contact->city,
							"state" => $this->_dataObject->data->tech_contact->state,
							"postal_code" => $this->_dataObject->data->tech_contact->postal_code,
							"country" => $this->_dataObject->data->tech_contact->country,
							"phone" => $this->_dataObject->data->tech_contact->phone,
							"fax" => $this->_dataObject->data->tech_contact->fax,
							"email" => $this->_dataObject->data->tech_contact->email,
							"lang_pref" => $this->_dataObject->data->tech_contact->lang_pref
							),
						'billing' => array(
							"first_name" => $this->_dataObject->data->billing_contact->first_name,
							"last_name" => $this->_dataObject->data->billing_contact->last_name,
							"org_name" => $this->_dataObject->data->billing_contact->org_name,
							"address1" => $this->_dataObject->data->billing_contact->address1,
							"address2" => $this->_dataObject->data->billing_contact->address2,
							"address3" => $this->_dataObject->data->billing_contact->address3,
							"city" => $this->_dataObject->data->billing_contact->city,
							"state" => $this->_dataObject->data->billing_contact->state,
							"postal_code" => $this->_dataObject->data->billing_contact->postal_code,
							"country" => $this->_dataObject->data->billing_contact->country,
							"phone" => $this->_dataObject->data->billing_contact->phone,
							"fax" => $this->_dataObject->data->billing_contact->fax,
							"email" => $this->_dataObject->data->billing_contact->email,
							"lang_pref" => $this->_dataObject->data->billing_contact->lang_pref
							)
						)
				)
			
		);
		
		// Command optional values
		if (isSet($this->_dataObject->data->nameserver_names) && $this->_dataObject->data->nameserver_names != ""){

			$nameServers=explode(",",$this->_dataObject->data->nameserver_names);

    			if (isSet($this->_dataObject->data->nameserver_ips) && $this->_dataObject->data->nameserver_ips != "")
				$ipAddresses=explode(",",$this->_dataObject->data->nameserver_ips);

			$i=0;

			foreach ($nameServers as $nameServer){
				$cmd['attributes']['nameserver_list'][$i]['fqdn'] = $nameServer;
				
				if(isSet($ipAddresses[$i]))
					$cmd['attributes']['nameserver_list'][$i]['ipaddress'] = $ipAddresses[$i];
				
				$i++;
			}
			

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


}
