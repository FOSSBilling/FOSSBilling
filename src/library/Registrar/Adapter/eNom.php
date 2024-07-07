<?php

class Registrar_Adapter_eNom extends Registrar_AdapterAbstract
{
    public $config = [
        'username' => null,
        'password' => null,
        'api_key' => null,
        'endpoint' => null,
        'debug' => false,
    ];

	private $rrpSuccessCodes = [
		200 => "Command completed successfully",
		210 => "Domain name available",
		211 => "Domain name not available",
		212 => "Nameserver name available",
		213 => "Nameserver name not available",
		220 => "Command completed successfully. Server closing connection.",
		// Add more success codes as needed
	];

	private $rrpFailureCodes = [
		420 => "Command failed due to server error. Server closing connection.",
		421 => "Command failed due to server error. Client should try again.",
		500 => "Invalid command name",
		501 => "Invalid command option",
		502 => "Invalid entity value",
		503 => "Invalid attribute name",
		504 => "Missing required attribute",
		505 => "Invalid attribute value syntax",
		506 => "Invalid option value",
		507 => "Invalid command format",
		508 => "Missing required entity",
		509 => "Missing command option",
		520 => "Server closing connection. Client should try opening new connection",
		521 => "Too many sessions open. Server closing connection.",
		530 => "Authentication failed",
		531 => "Authorization failed",
		532 => "Domain names linked with name server",
		533 => "Domain name has active name servers",
		534 => "Domain name has not been flagged for transfer",
		535 => "Restricted IP address",
		536 => "Domain already flagged for transfer",
		540 => "Attribute value is not unique",
		541 => "Invalid attribute value",
		542 => "Invalid old value for an attribute",
		543 => "Final or implicit attribute cannot be updated",
		544 => "Entity on hold",
		545 => "Entity reference not found",
		546 => "Credit limit exceeded",
		547 => "Invalid command sequence",
		548 => "Domain is not up for renewal",
		549 => "Command failed",
		550 => "Parent domain not registered",
		551 => "Parent domain status does not allow for operation",
		552 => "Domain status does not allow for operation",
		553 => "Operation not allowed. Domain pending transfer",
		554 => "Domain already registered",
		555 => "Domain already renewed",
		556 => "Maximum registration period exceeded",
		880	=> "Failed to send request to registry",
		// Add more failure codes as needed
	];

	public static function getConfig(){
		return [
			'label' => 'Manages domains on eNom via API',
			'form' => [
				'endpoint' => ['text', [
					'label' => 'eNom endpoint URL',
					'description' => 'Your eNom endpoint URL.',
				]],
				'username' => ['text', [
					'label' => 'eNom API Username',
					'description' => 'Your eNom API username used to authenticate requests.',
				]],
				'password' => ['password', [
					'label' => 'eNom API Password',
					'description' => 'Your eNom API password used to authenticate requests.',
					'renderPassword' => true,
				]],
				'api_key' => ['password', [
					'label' => 'eNom API Key',
					'description' => 'Your eNom API key, if different from your password, used for additional security.',
					'renderPassword' => true,
				]],
			],
		];
	}


	public function __construct($options) {
			$this->config['endpoint'] = $options['endpoint'] ?? null;
			$this->config['username'] = $options['username'] ?? null;
			$this->config['password'] = $options['password'] ?? null;
			$this->config['api_key'] = $options['api_key'] ?? null;
			$this->config['debug'] = $options['debug'] ?? true;

			if (empty($this->config['endpoint']) || empty($this->config['username']) || empty($this->config['password']) || empty($this->config['api_key'])) {
				throw new Registrar_Exception('The eNom registrar is not fully configured.');
			}
	}


	protected function _makeRequest($params) {
		// Directly assign the password without encoding
		$params['PW'] = $this->config['password'];

		// Merge additional necessary parameters
		$params = array_merge([
			'UID' => $this->config['username'],
			'Key' => $this->config['api_key'],
			'ResponseType' => 'XML'
		], $params);
	
		// Ensure parameters are sorted correctly
		ksort($params);
		$url = $this->config['endpoint'] . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
	
		if ($this->config['debug']) {
			error_log("eNom API request URL: " . $url);
		}
	
		$contextOptions = [
			'http' => [
				'method' => 'GET', 
				'ignore_errors' => true 
			]
		];
	
		// Create stream context
		$context = stream_context_create($contextOptions);
		$response = @file_get_contents($url, false, $context);
	
		if ($response === false) {
			$error = error_get_last();
			if ($error !== null) {
				error_log("Failed to retrieve API response: " . $error['message']);
			}
			throw new Registrar_Exception("API request to eNom failed: No response received.");
		}
	
		if ($this->config['debug']) {
			error_log("eNom API response: " . $response);
		}
		
		$xml = simplexml_load_string($response);
		//check for ErrCount
		if (isset($xml->ErrCount) && (int) $xml->ErrCount > 0) {
			//show all errors
			$errors = $xml->errors;
			$errorMessages = [];
			for ($i = 1; $i <= (int) $xml->ErrCount; $i++) {
				$errorKey = "Err{$i}";
				if (isset($errors->$errorKey)) {
					$errorMessages[] = (string) $errors->$errorKey;
				}
			}
			$errorMessage = implode("; ", $errorMessages);
			throw new Registrar_Exception("eNom API error: {$errorMessage}");
		}

		return $xml;
	}
	
	public function registerDomain(Registrar_Domain $domain) {
		$params = [
			'Command' => 'Purchase',
			'SLD' => strtolower($domain->getSld()),
			'TLD' => $domain->getTld(false),
			'NumYears' => $domain->getRegistrationPeriod(),
			'ResponseType' => 'XML',
		];

		if ($domain->getNs1()) {
			$nsList = [$domain->getNs1(), $domain->getNs2(), $domain->getNs3(), $domain->getNs4()];
			$params['NS'] = implode(',', array_filter($nsList));
		}

		//update Registrant/Admin/Tech contacts
		$contactTypes = ['Registrar', 'Admin', 'Tech'];
		foreach ($contactTypes as $contactType) {
			$contactMethod = "getContact{$contactType}";
			if (method_exists($domain, $contactMethod)) {
				$contact = $domain->$contactMethod();
				//contactType is first initiated to "Registrar" as The class Registrar_Domain have the method "getContactRegistrar" instead of "getContactRegistrant"
				//change "Registrar" contactType to "Registrant" as the contact is called "Registrant" in enom api
				$contactType = $contactType == "Registrar" ? "Registrant" : $contactType;
				$params["{$contactType}FirstName"] = $contact->getFirstName();
				$params["{$contactType}LastName"] = $contact->getLastName();
				$params["{$contactType}EmailAddress"] = $contact->getEmail();
				$params["{$contactType}Phone"] = '+' . $contact->getTelCc() . '.' . $contact->getTel();
				$params["{$contactType}Address1"] = $contact->getAddress1();
				$params["{$contactType}Address2"] = $contact->getAddress2();
				$params["{$contactType}City"] = $contact->getCity();
				$params["{$contactType}StateProvince"] = $contact->getState();
				$params["{$contactType}PostalCode"] = $contact->getZip();
				$params["{$contactType}Country"] = $contact->getCountry();
			}
		}

		try {
			$result = $this->_makeRequest($params);
			$this->handleApiRRPCode($result);
			return true;
		} catch (Registrar_Exception $e) {
			error_log("Domain registration failed: " . $e->getMessage());
			throw new Registrar_Exception("Domain registration failed: " . $e->getMessage());
		}
	}

	public function isDomainAvailable(Registrar_Domain $domain) {
		$params = [
			'Command' => 'check',
			'SLD' => $domain->getSld(),
			'TLD' => $domain->getTld(false),
			'ResponseType' => 'XML',
		];
		
		try {
			$result = $this->_makeRequest($params);
			return ($result->RRPCode == 210);  // Domain name available
		} catch (Registrar_Exception $e) {
			error_log("Checking domain availability failed: " . $e->getMessage());
			throw new Registrar_Exception("Checking domain availability failed: " . $e->getMessage());
		}
	}

	public function isDomainCanBeTransferred(Registrar_Domain $domain) {
		$params = [
			'Command' => 'check',
			'SLD' => $domain->getSld(),
			'TLD' => $domain->getTld(false),
			'Version' => 2,
			'IncludeProperties' => 1,
			'ResponseType' => 'XML',
		];

		try {
			$result = $this->_makeRequest($params);
			$rrpCode = $result->Domains->Domain->RRPCode;

			//make sure the domain is registered
			if ($rrpCode == 210) throw  new Registrar_Exception("This domain is not registered");
			else if ($rrpCode != 211) throw  new Registrar_Exception("RRPCode: " . $rrpCode);
			
			//make sure the domain can be transfered
			if (!$result->Domains->Domain->Properties->Transferable) throw  new Registrar_Exception("This domain is not transferable");

			//make sure the domain is not registered in the current registrar
			$this->transferCondition_domainNotFound($domain); 
			
			//make sure the domain doesnt have a processing order
			return $this->transferCondition_orderStatus($domain);
			
		} catch (Registrar_Exception $e) {
			error_log("Checking domain transfer availability failed: " . $e->getMessage());
			throw  new Registrar_Exception($e->getMessage());
		}
	}

	public function modifyNs(Registrar_Domain $domain) {
		$params = [
			'Command' => 'modifyns',
			'SLD' => $domain->getSld(),
			'TLD' => $domain->getTld(false),
			'NS1' => $domain->getNs1(),
			'NS2' => $domain->getNs2(),
			'NS3' => $domain->getNs3(),
			'NS4' => $domain->getNs4(),
			'ResponseType' => 'XML',
		];

		try {
			$result = $this->_makeRequest($params);
			return ($result->RRPCode == 200);  // Nameserver modification successful
		} catch (Registrar_Exception $e) {
			error_log("Modifying nameservers failed: " . $e->getMessage());
			throw  new Registrar_Exception("Modifying nameservers failed: " . $e->getMessage());
		}
	}

	public function getDomainDetails(Registrar_Domain $domain) {
		$params = [
			'Command' => 'GetDomainInfo',
			'SLD' => $domain->getSld(),
			'TLD' => $domain->getTld(false),
			'ResponseType' => 'XML',
		];

		try {
			$result = $this->_makeRequest($params);
			// Ensure the domain information is present in the response
			if (!isset($result->GetDomainInfo)) {
				throw new Registrar_Exception("Domain details are missing in the API response.");
			}

			// Extract domain details and set them on the domain object
			$domainInfo = $result->GetDomainInfo;
			$expiration = $domainInfo->status->expiration;
			$expirationTimestamp = strtotime($expiration);
			$privacyEnabled = $this->fetchPrivacyStatus($domain);

			$domain->setExpirationTime($expirationTimestamp);
			$domain->setPrivacyEnabled($privacyEnabled);


			return $domain;
		} catch (Registrar_Exception $e) {
			error_log("Error retrieving domain details: " . $e->getMessage());
			throw  new Registrar_Exception("Error retrieving domain details: " . $e->getMessage());
		}
	}

	public function modifyContact(Registrar_Domain $domain) {
		$contact = $domain->getContactRegistrar();
		if (!$contact) {
			error_log("No contact information available.");
			throw  new Registrar_Exception("No contact information available.");
		}

		$params = [
			'Command' => 'contacts',
			'SLD' => $domain->getSld(),
			'TLD' => $domain->getTld(false),
		];

		//update Registrant/Admin/Tech contacts
		$contactTypes = ['Registrar', 'Admin', 'Tech'];
		foreach ($contactTypes as $contactType) {
			$contactMethod = "getContact{$contactType}";
			if (method_exists($domain, $contactMethod)) {
				$contact = $domain->$contactMethod();
				//contactType is first initiated to "Registrar" as The class Registrar_Domain have the method "getContactRegistrar" instead of "getContactRegistrant"
				//change "Registrar" contactType to "Registrant" as the contact is called "Registrant" in enom api
				$contactType = $contactType == "Registrar" ? "Registrant" : $contactType;
				$params["{$contactType}FirstName"] = $contact->getFirstName();
				$params["{$contactType}LastName"] = $contact->getLastName();
				$params["{$contactType}EmailAddress"] = $contact->getEmail();
				$params["{$contactType}Phone"] = '+' . $contact->getTelCc() . '.' . $contact->getTel();
				$params["{$contactType}Address1"] = $contact->getAddress1();
				$params["{$contactType}Address2"] = $contact->getAddress2();
				$params["{$contactType}City"] = $contact->getCity();
				$params["{$contactType}StateProvince"] = $contact->getState();
				$params["{$contactType}PostalCode"] = $contact->getZip();
				$params["{$contactType}Country"] = $contact->getCountry();
			}
		}


		try {
			$result = $this->_makeRequest($params);
			return true;
		} catch (Registrar_Exception $e) {
			throw new Registrar_Exception("Error modifying contact details: " . $e->getMessage());
		}
	}

	public function transferDomain(Registrar_Domain $domain) {
		$params = [
			'Command' => 'TP_CreateOrder',
			'SLD1' => $domain->getSld(),
			'TLD1' => $domain->getTld(false),
			'AuthInfo1' => $domain->getEpp(),
			'DomainCount' => 1,
			'OrderType' => 'Autoverification',
		];

		try {
			$result = $this->_makeRequest($params);
			return true;
		} catch (Registrar_Exception $e) {
			error_log("Transfer Error" . $e->getMessage());
			throw  new Registrar_Exception("Transfer Error");
		}
	}

	public function getEpp(Registrar_Domain $domain) {
		$params = [
			'Command' => 'SynchAuthInfo',
			'SLD' => $domain->getSld(),
			'TLD' => $domain->getTld(false),
			'EmailEPP' => 'True',
			'RunSynchAutoInfo' => 'True',
		];

		$result = $this->_makeRequest($params);
		return "EPP code email has been sent.";
	}

	public function renewDomain(Registrar_Domain $domain) {
		$params = [
			'Command' => 'Extend',
			'SLD' => $domain->getSld(),
			'TLD' => $domain->getTld(false),
			'NumYears' => $domain->getRegistrationPeriod(),
		];

		$result = $this->_makeRequest($params);
		$this->handleApiRRPCode($result);
		return true;
	}

	public function deleteDomain(Registrar_Domain $domain) {
		$params = [
			'Command' => 'DeleteRegistration',
			'SLD' => $domain->getSld(),
			'TLD' => $domain->getTld(false),
		];

		$result = $this->_makeRequest($params);
		$this->handleApiRRPCode($result);
		return true;
	}

	public function enablePrivacyProtection(Registrar_Domain $domain) {
		//check if the user have bought the subscription
		$wppsInfoParams = [
			'Command' => 'GetWPPSInfo',
			'SLD' => $domain->getSld(),
			'TLD' => $domain->getTld(false),
		];
		$wppsInfoResult = $this->_makeRequest($wppsInfoParams);
		if (!isset($wppsInfoResult->GetWPPSInfo->WPPSExists)){
			throw new Registrar_Exception("Unable to retrieve WPPS information for the domain");
		}

		$wppsSubscribed = $wppsInfoResult->GetWPPSInfo->WPPSExists;
		if ($wppsSubscribed == "0"){
			//the user isnt subscribed so make the user purchase it first
			$params = [
				'Command' => 'PurchaseServices',
				'Service' => 'WPPS',
				'SLD' => $domain->getSld(),
				'TLD' => $domain->getTld(false),
			];
			$this->_makeRequest($params);
			
			//redo the request to check new wpps info
			$wppsInfoResult = $this->_makeRequest($wppsInfoParams);
			if (!isset($wppsInfoResult->GetWPPSInfo->WPPSExists)){
				throw new Registrar_Exception("Unable to retrieve WPPS information for the domain");
			}
		}
		//now enable the wpps service if its disabled
		if (!isset($wppsInfoResult->GetWPPSInfo->WPPSEnabled)){
			throw new Registrar_Exception("Unable to retrieve WPPS information for the domain");
		}
		if ($wppsInfoResult->GetWPPSInfo->WPPSEnabled == "0"){
			$enableParams = [
				'Command' => 'EnableServices',
				'Service' => 'WPPS',
				'SLD' => $domain->getSld(),
				'TLD' => $domain->getTld(false),
			];
			$enableResult = $this->_makeRequest($enableParams);
			
			$serviceStatus = $enableResult->ServiceStatus;
			if (isset($serviceStatus) && $serviceStatus == 'Enabled') {
				return true;
			} else {
				throw new Exception('Failed to enable privacy protection.');
			}
		}
		//wpps already enabled
		return true;
	}

	public function disablePrivacyProtection(Registrar_Domain $domain) {
		$wppsInfoParams = [
			'Command' => 'GetWPPSInfo',
			'SLD' => $domain->getSld(),
			'TLD' => $domain->getTld(false),
		];

		$wppsInfoResult = $this->_makeRequest($wppsInfoParams);

		//check if wpps subscription exists
		if (isset($wppsInfoResult->GetWPPSInfo->WPPSExists) && $wppsInfoResult->GetWPPSInfo->WPPSExists == '1') {
			//check if wpps is enabled
			if (isset($wppsInfoResult->GetWPPSInfo->WPPSEnabled) && $wppsInfoResult->GetWPPSInfo->WPPSEnabled == '1') {
				//disable wpps
				$disableParams = [
					'Command' => 'DisableServices',
					'Service' => 'WPPS',
					'SLD' => $domain->getSld(),
					'TLD' => $domain->getTld(false),
				];
	
				$disableResult = $this->_makeRequest($disableParams);
	
				$serviceStatus = $disableResult->ServiceStatus;
				if (isset($serviceStatus) && $serviceStatus == 'Disabled') {
					return true;
				} else {
					throw new Exception('Failed to disable privacy protection.');
				}
			} else {
				//WPPS exists but its already disabled
				return true;
			}
		} else {
			//WPPS doesnt exist so nothing to disable
			return true;
		}
	}

	public function lock(Registrar_Domain $domain) {
		$params = [
			'Command' => 'SetRegLock',
			'SLD' => $domain->getSld(),
			'TLD' => $domain->getTld(false),
			'unlockregistrar' => 0,  // 0 to lock the domain
		];

		$result = $this->_makeRequest($params);
		$this->handleApiRRPCode($result);
		return true;
	}

	public function unlock(Registrar_Domain $domain) {
		$params = [
			'Command' => 'SetRegLock',
			'SLD' => $domain->getSld(),
			'TLD' => $domain->getTld(false),
			'unlockregistrar' => 1,  // 1 to unlock the domain
		];

		$result = $this->_makeRequest($params);
		$this->handleApiRRPCode($result);
		return true;
	}


	private function handleApiRRPCode($xml) {
		$rrpCode = (int) $xml->RRPCode;
		if (in_array($rrpCode, array_keys($this->rrpFailureCodes))) {
			throw new Registrar_Exception("Failed RRPCode code: {$rrpCode}");
		}
	}

	private function fetchPrivacyStatus(Registrar_Domain $domain) {
		$params = [
			'Command' => 'GetWPPSInfo',
			'SLD' => $domain->getSld(),
			'TLD' => $domain->getTld(false),
			'ResponseType' => 'XML',
		];



		$result = $this->_makeRequest($params);
		if (isset($result->GetWPPSInfo->WPPSEnabled)) {
			return $result->GetWPPSInfo->WPPSEnabled == '1';
		}
		throw new Registrar_Exception("Could not fetch the privacy status");
	}

	private function transferCondition_domainNotFound(Registrar_Domain $domain){
		//make sure GetWhoisContact shows an error "domain not found"
		$params = [
			'command' => 'GetWhoisContact',
			'SLD' => $domain->getSld(),
			'TLD' => $domain->getTld(false),
			'PW' => $this->config['password'],
			'UID' => $this->config['username'],
			'Key' => $this->config['api_key'],
			'ResponseType' => 'XML'
		];
		ksort($params);
		$url = $this->config['endpoint'] . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
		if ($this->config['debug']) {
			error_log("eNom API request URL: " . $url);
		}
		$contextOptions = [
			'http' => [
				'method' => 'GET', 
				'ignore_errors' => true 
			]
		];
	
		// Create stream context
		$context = stream_context_create($contextOptions);
		$response = @file_get_contents($url, false, $context);
		if ($response === false) {
			$error = error_get_last();
			if ($error !== null) {
				error_log("Failed to retrieve API response: " . $error['message']);
			}
			throw new Registrar_Exception("API request to eNom failed: No response received.");
		}
		if ($this->config['debug']) {
			error_log("eNom API response: " . $response);
		}
		$xml = simplexml_load_string($response);

		if (isset($xml->ErrCount) && (int) $xml->ErrCount == 0) {
			//domain found in the current registrar
			throw  new Registrar_Exception("Domain exist in the current Registrar");
		}else if (isset($xml->ErrCount) && (int) $xml->ErrCount == 1) {
			$errors = $xml->errors;
			$errorKey = "Err1";
			$errMessage = (string) $errors->$errorKey;
			//domain not found
			if ($errMessage == "The remote server returned an error: (404) Not Found.") {
				return;
			}
			//unknown error
			throw  new Registrar_Exception("eNom API error: " . $errMessage);
		}else{
			//unknown errors
			$errors = $xml->errors;
			$errorMessages = [];
			for ($i = 1; $i <= (int) $xml->ErrCount; $i++) {
				$errorKey = "Err{$i}";
				if (isset($errors->$errorKey)) {
					$errorMessages[] = (string) $errors->$errorKey;
				}
			}
			$errorMessage = implode("; ", $errorMessages);
			throw new Registrar_Exception("eNom API error: {$errorMessage}");
		}
	}

	private function transferCondition_orderStatus(Registrar_Domain $domain){
		$params = [
			'Command' => 'TP_GetOrdersByDomain',
			'SLD' => $domain->getSld(),
			'TLD' => $domain->getTld(false),
			'ResponseType' => 'XML',
		];
		$result = $this->_makeRequest($params);

		$transferOrders = $result->xpath('//TransferOrder/statusid');
		foreach ($transferOrders as $statusid) {
			if ((int)$statusid < 6) {
				throw new Registrar_Exception("Domain transfer order is already in process.");
			}
		}
		return true;
	}
}

