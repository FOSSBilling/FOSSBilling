<?php

class openSRS_fastlookup {
	// This values will be part of separate config file
	protected $osrs_host = "";
	protected $osrs_port = "";
	
	private $_socket = false;
	private $_socketErrorNum = false;
	private $_socketErrorMsg = false;
	private $_socketTimeout = 120;				// seconds

	// Class constructor
	public function __construct () {
		$this->_pullConfigVariables ();
	}

	// Class destructor
	public function __destruct () {
	}


 	// Private functions
	private function _pullConfigVariables () {
		$xmlHand = new class_xml;

		// Load and read active config file
		$actConfig = $xmlHand->xml2array(ACTIVECONFIG);
		$actFileArray = $xmlHand->getValueByPath ($actConfig, "config/file");
		$actFile = $actFileArray['value'];
		
		// Load active Config
		$fileConfig = $xmlHand->xml2array(OPENSRSURI . OPENSRSCONFINGS . $actFile);
		$fileFileArray = $xmlHand->getValueByPath ($fileConfig, "config/osrsFastLookup");
		
		foreach ($fileFileArray as $ffa){
			if ($ffa['name'] == "osrs_host" && isSet($ffa['value']))  $this->osrs_host = $ffa['value'];
			if ($ffa['name'] == "osrs_port" && isSet($ffa['value']))  $this->osrs_port = $ffa['value'];
		}

		if ($this->osrs_host == "" || $this->osrs_port == ""){
			if ($this->osrs_host == "") trigger_error ("oSRS Error - Incomplete config file - Missing osrs_host", E_USER_WARNING);
			if ($this->osrs_port == "") trigger_error ("oSRS Error - Incomplete config file - Missing osrs_port", E_USER_WARNING);
			die ();
		}
	}


//  Send a command to the server
	public function checkDomain($domain) {
		// make or get the socket filehandle
		if (!$this->init_socket() ) {
			trigger_error ("oSRS Error - Unable to establish socket: (". $this->_socketErrorNum .") ". $this->_socketErrorMsg, E_USER_WARNING);
			die();
		}

		// Send a check call
		$callCheck = "check_domain ". $domain;
		$callLength = strlen($callCheck);
		fputs($this->_socket, $callCheck, $callLength );

//		// wait 0.25 sec - Immediate socket read will result for wait loops and longer response
//		usleep(250000);

		// Read result hand for parsing
		$result = fread($this->_socket, 1024);
		$data = $this->parseResult ($result);
		
		// Close the socket
		$this->close_socket ();

		return $data;
	}
	
	public function checkDomainBunch ($domain, $tlds){
		if (!$this->init_socket() ) {
			trigger_error ("oSRS Error - Unable to establish socket: (". $this->_socketErrorNum .") ". $this->_socketErrorMsg, E_USER_WARNING);
			die();
		}

		// check to see if the domain has a "." in it, if it does then take everything before the dot as the domain
		// This is if someone puts in a domain name instead of just a name
		
		if(preg_match("/(^.+)\.(.+)\.(.+)/",$domain,$matches) > 0)
			$domain=$matches[1];
		else if (preg_match("/(^.+)\.(.+)/",$domain,$matches) > 0)
			$domain=$matches[1];

		// Send a check call
		$resultArray = array ();
		foreach ($tlds as $tld) {
			// check_domain / contact_info / update_all_info

			$callCheck = "check_domain ". $domain . $tld;
			$callLength = strlen($callCheck);
			fputs($this->_socket, $callCheck, $callLength );

//			// wait 0.25 sec - Immediate socket read will result for wait loops and longer response
//			usleep(250000);

			// Read result hand for parsing
			$result = fread($this->_socket, 1024);
			$checkRes = $this->parseResult ($result);
			
			// Sort out the results
			$loopAray = array ();
			$loopAray['domain'] = $domain;
			$loopAray['tld'] = $tld;
			$loopAray['result'] = $checkRes;
			
			// Final push
			array_push ($resultArray, $loopAray);
		}
		
		// Close the socket
		$this->close_socket ();
		
		// And there you go!!  That's it
		return $resultArray;
	}

	
	private function parseResult ($resString) {
		$resultReturn = "";
		
		if ($resString != "") {
			$temArra = explode (" ", $resString);
			if ($temArra[0] == 210) $resultReturn = "Available";
			else if ($temArra[0] == 211) $resultReturn = "Taken";
			else if ($temArra[0] == 701) $resultReturn = "Unknown TLD";
			else $resultReturn = "Syntax error - ". $temArra[0];
		} else {
			$resultReturn = "Read error";
		}
	
		return $resultReturn;
	}

	private function init_socket() {
		$this->_socket = pfsockopen($this->osrs_host, $this->osrs_port, $this->_socketErrorNum, $this->_socketErrorMsg, $this->_socketTimeout);
		if (!$this->_socket) {
			return false;
		} else {
			return true;
		}
	}
	
	private function close_socket() {
		fclose($this->_socket);
		$this->_socket = false;
	}
}
