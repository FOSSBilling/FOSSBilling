<?php
/*
 *  Required object values:
 *  data - 
 */
 
class mailCreateMailbox extends openSRS_mail {
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
		
		if (!isSet($this->_dataObject->data->workgroup) || $this->_dataObject->data->workgroup == "") {
			trigger_error ("oSRS-eMail Error - workgroup is not defined.", E_USER_WARNING);
			$allPassed = false;
		} else {
			$compile .= " workgroup=\"". $this->_dataObject->data->workgroup ."\"";
		}
		
		if (!isSet($this->_dataObject->data->mailbox) || $this->_dataObject->data->mailbox == "") {
			trigger_error ("oSRS-eMail Error - mailbox is not defined.", E_USER_WARNING);
			$allPassed = false;
		} else {
			$compile .= " mailbox=\"". $this->_dataObject->data->mailbox ."\"";
		}
		
		if (!isSet($this->_dataObject->data->password) || $this->_dataObject->data->password == "") {
			trigger_error ("oSRS-eMail Error - password is not defined.", E_USER_WARNING);
			$allPassed = false;
		} else {
			$compile .= " password=\"". $this->_dataObject->data->password ."\"";
		}
		
		// Command optional values
		if (isSet($this->_dataObject->data->first_name) && $this->_dataObject->data->first_name != "")
                        $compile .= " first_name=\"". $this->_dataObject->data->first_name ."\"";

		if (isSet($this->_dataObject->data->last_name) && $this->_dataObject->data->last_name != "")
                        $compile .= " last_name=\"". $this->_dataObject->data->last_name ."\"";

		if (isSet($this->_dataObject->data->title) && $this->_dataObject->data->title != "")
                        $compile .= " title=\"". $this->_dataObject->data->title ."\"";

		if (isSet($this->_dataObject->data->phone) && $this->_dataObject->data->phone != "")
                        $compile .= " phone=\"". $this->_dataObject->data->phone ."\"";
                
		if (isSet($this->_dataObject->data->fax) && $this->_dataObject->data->fax != "")
                        $compile .= " fax=\"". $this->_dataObject->data->fax ."\"";

		if (isSet($this->_dataObject->data->timezone) && $this->_dataObject->data->timezone != "")
                        $compile .= " timezone=\"". $this->_dataObject->data->timezone ."\"";

		if (isSet($this->_dataObject->data->language) && $this->_dataObject->data->language != "")
                        $compile .= " language=\"". $this->_dataObject->data->language ."\"";

		if (isSet($this->_dataObject->data->filteronly) && $this->_dataObject->data->filteronly != "")
                        $compile .= " filteronly=\"". $this->_dataObject->data->filteronly ."\"";

		if (isSet($this->_dataObject->data->spam_tag) && $this->_dataObject->data->spam_tag != "")
                        $compile .= " spam_tag=\"". $this->_dataObject->data->spam_tag ."\"";

		if (isSet($this->_dataObject->data->spam_folder) && $this->_dataObject->data->spam_folder != "")
                        $compile .= " spam_folder=\"". $this->_dataObject->data->spam_folder ."\"";

		if (isSet($this->_dataObject->data->spam_level) && $this->_dataObject->data->spam_level != "")
                        $compile .= " spam_level=\"". $this->_dataObject->data->spam_level ."\"";
		
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
			1 => "login user=\"". $this->_dataObject->data->authusername ."\" domain=\"". $this->_dataObject->data->authdomain ."\" password=\"". $this->_dataObject->data->authpassword ."\"",
			2 => "create_mailbox". $command,
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