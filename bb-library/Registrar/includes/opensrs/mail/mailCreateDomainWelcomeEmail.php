<?php
/*
 *  Required object values:
 *  data - 
 */
 
class mailCreateDomainWelcomeEmail extends openSRS_mail {
	private $_dataObject;
	private $_formatHolder = "";
	private $_osrsm;
	
	public $resultRaw;
	public $resultFormated;
	public $resultSuccess;

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
		$compile = "";
		
		// Command required values - authentication
		if (!isSet($this->_dataObject->data->username) || $this->_dataObject->data->username == "") {
			if ($this->osrs_mailuser == ""){
				trigger_error ("oSRS-eMail Error - username is not defined.", E_USER_WARNING);
				$allPassed = false;
			} else {
				$this->_dataObject->data->username = $this->osrs_mailuser;
			}
		}
		if (!isSet($this->_dataObject->data->password) || $this->_dataObject->data->password == "") {
			if ($this->osrs_mailpassword == ""){
				trigger_error ("oSRS-eMail Error - password is not defined.", E_USER_WARNING);
				$allPassed = false;
			} else {
				$this->_dataObject->data->password = $this->osrs_mailpassword;
			}
		}
		if (!isSet($this->_dataObject->data->authdomain) || $this->_dataObject->data->authdomain == "") {
			if ($this->osrs_maildomain == ""){
				trigger_error ("oSRS-eMail Error - authentication domain is not defined.", E_USER_WARNING);
				$allPassed = false;
			} else {
				$this->_dataObject->data->authdomain = $this->osrs_maildomain;
			}
		}
						
		// Command required values
		if (!isSet($this->_dataObject->data->domain) || $this->_dataObject->data->domain == "") {
			trigger_error ("oSRS-eMail Error - domain is not defined.", E_USER_WARNING);
			$allPassed = false;
		} else {
			$compile .= " domain=\"". $this->_dataObject->data->domain ."\"";
		}
		if (!isSet($this->_dataObject->data->welcome_text) || $this->_dataObject->data->welcome_text == "") {
			trigger_error ("oSRS-eMail Error - welcome_text is not defined.", E_USER_WARNING);
			$allPassed = false;
		} else {
			$compile .= " welcome_text=\"". $this->_dataObject->data->welcome_text ."\"";
		}
		if (!isSet($this->_dataObject->data->welcome_subject) || $this->_dataObject->data->welcome_subject == "") {
			trigger_error ("oSRS-eMail Error - welcome_subject is not defined.", E_USER_WARNING);
			$allPassed = false;
		} else {
			$compile .= " welcome_subject=\"". $this->_dataObject->data->welcome_subject ."\"";
		}
		if (!isSet($this->_dataObject->data->from_name) || $this->_dataObject->data->from_name == "") {
			trigger_error ("oSRS-eMail Error - from_name is not defined.", E_USER_WARNING);
			$allPassed = false;
		} else {
			$compile .= " from_name=\"". $this->_dataObject->data->from_name ."\"";
		}
		if (!isSet($this->_dataObject->data->from_address) || $this->_dataObject->data->from_address == "") {
			trigger_error ("oSRS-eMail Error - from_address is not defined.", E_USER_WARNING);
			$allPassed = false;
		} else {
			$compile .= " from_address=\"". $this->_dataObject->data->from_address ."\"";
		}
		if (!isSet($this->_dataObject->data->charset) || $this->_dataObject->data->charset == "") {
			trigger_error ("oSRS-eMail Error - charset is not defined.", E_USER_WARNING);
			$allPassed = false;
		} else {
			$compile .= " charset=\"". $this->_dataObject->data->charset ."\"";
		}
		if (!isSet($this->_dataObject->data->mime_type) || $this->_dataObject->data->mime_type == "") {
			trigger_error ("oSRS-eMail Error - mime_type is not defined.", E_USER_WARNING);
			$allPassed = false;
		} else {
			$compile .= " mime_type=\"". $this->_dataObject->data->mime_type ."\"";
		}
		
		// Run the command
		if ($allPassed) {
			// Execute the command
			$this->_processRequest ($compile);
		} else {
			trigger_error ("oSRS-eMail Error - Missing data.", E_USER_WARNING);
		}
	}
	
	// Post validation functions
	private function _processRequest ($command = ""){
		$sequence = array (
			0 => "ver ver=\"3.4\"",
			1 => "login user=\"". $this->_dataObject->data->username ."\" domain=\"". $this->_dataObject->data->authdomain ."\" password=\"". $this->_dataObject->data->password ."\"",
			2 => "create_domain_welcome_email". $command,
			3 => "quit"
		);		
		$tucRes = $this->makeCall($sequence);
		$arrayResult = $this->parseResults($tucRes);
		
		// Results
		$this->resultFullRaw = $arrayResult;
		$this->resultRaw = $arrayResult;
		$this->resultFullFormated = convertArray2Formated ($this->_formatHolder, $this->resultFullRaw);
		$this->resultFormated = convertArray2Formated ($this->_formatHolder, $this->resultRaw);
	}
}