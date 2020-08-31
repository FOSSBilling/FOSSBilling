<?php
/*
 *  Required object values:
 *  data - 
 */
 
class dnsCreate extends openSRS_base {
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
		if (!isSet($this->_dataObject->data->domain) || $this->_dataObject->data->domain == "") {
			trigger_error ("oSRS Error - domain is not defined.", E_USER_WARNING);
			$allPassed = false;
		}
				
		// Run the command
		if ($allPassed) {
			// Execute the command
			$this->_processRequest ();
		} else {
			trigger_error ("oSRS Error - Incorrect call.", E_USER_WARNING);
		}
	}

	// Post validation functions
	private function _processRequest (){
		$cmd = array(
			'protocol' => 'XCP',
			'action' => 'create_dns_zone',
			'object' => 'domain',
			'attributes' => array (
				'domain' => $this->_dataObject->data->domain,
			)
		);

		// Command optional values
		if (isSet($this->_dataObject->data->dns_template) && $this->_dataObject->data->dns_template != "") $cmd['attributes']['dns_template'] = $this->_dataObject->data->dns_template;
		
		// records - A 
		$a_ip_address = "";
		$a_subdomain = "";
		if (isSet($this->_dataObject->data->a_ip_address) && $this->_dataObject->data->a_ip_address != "") $a_ip_address = $this->_dataObject->data->a_ip_address;
		if (isSet($this->_dataObject->data->a_subdomain) && $this->_dataObject->data->a_subdomain != "") $a_subdomain = $this->_dataObject->data->a_subdomain;
		if ($a_ip_address != "" && $a_subdomain != ""){
			$cmd['attributes']['records']['A'][0] = array(
				'ip_address' => $a_ip_address,
				'subdomain' => $a_subdomain
			);
		}
		
		// records - AAAA 
		$aaaa_ipv6_address = "";
		$aaaa_subdomain = "";
		if (isSet($this->_dataObject->data->aaaa_ipv6_address) && $this->_dataObject->data->aaaa_ipv6_address != "") $aaaa_ipv6_address = $this->_dataObject->data->aaaa_ipv6_address;
		if (isSet($this->_dataObject->data->aaaa_subdomain) && $this->_dataObject->data->aaaa_subdomain != "") $aaaa_subdomain = $this->_dataObject->data->aaaa_subdomain;
		if ($aaaa_ipv6_address != "" && $aaaa_subdomain != ""){
			$cmd['attributes']['records']['AAAA'][0] = array(
				'ipv6_address' => $aaaa_ipv6_address,
				'subdomain' =>$aaaa_subdomain
			);
		}
		
		// records - CNAME 
		$cname_hostname = "";
		$cname_subdomain = "";
		if (isSet($this->_dataObject->data->cname_hostname) && $this->_dataObject->data->cname_hostname != "") $cname_hostname = $this->_dataObject->data->cname_hostname;
		if (isSet($this->_dataObject->data->cname_subdomain) && $this->_dataObject->data->cname_subdomain != "") $cname_subdomain = $this->_dataObject->data->cname_subdomain;
		if ($cname_hostname != "" && $cname_subdomain != ""){
			$cmd['attributes']['records']['CNAME'][0] = array(
				'hostname' => $cname_hostname,
				'subdomain' => $cname_subdomain
			);
		}
		
		// records - MX 
		$mx_priority = "";
		$mx_subdomain = "";
		$mx_hostname = "";
		if (isSet($this->_dataObject->data->mx_priority) && $this->_dataObject->data->mx_priority != "") $mx_priority = $this->_dataObject->data->mx_priority;
		if (isSet($this->_dataObject->data->mx_subdomain) && $this->_dataObject->data->mx_subdomain != "") $mx_subdomain = $this->_dataObject->data->mx_subdomain;
		if (isSet($this->_dataObject->data->mx_hostname) && $this->_dataObject->data->mx_hostname != "") $mx_hostname = $this->_dataObject->data->mx_hostname;
		if ($mx_priority != "" && $mx_subdomain != "" && $mx_hostname != ""){
			$cmd['attributes']['records']['MX'][0] = array(
				'priority' => $mx_priority,
				'subdomain' => $mx_subdomain,
				'hostname' => $mx_hostname
			);
		}
		
		// records - SRV 
		$srv_priority = "";
		$srv_weight = "";
		$srv_subdomain = "";
		$srv_hostname = "";
		$srv_port = "";
		if (isSet($this->_dataObject->data->srv_priority) && $this->_dataObject->data->srv_priority != "") $srv_priority = $this->_dataObject->data->srv_priority;
		if (isSet($this->_dataObject->data->srv_weight) && $this->_dataObject->data->srv_weight != "") $srv_weight = $this->_dataObject->data->srv_weight;
		if (isSet($this->_dataObject->data->srv_subdomain) && $this->_dataObject->data->srv_subdomain != "") $srv_subdomain = $this->_dataObject->data->srv_subdomain;
		if (isSet($this->_dataObject->data->srv_hostname) && $this->_dataObject->data->srv_hostname != "") $srv_hostname = $this->_dataObject->data->srv_hostname;
		if (isSet($this->_dataObject->data->srv_port) && $this->_dataObject->data->srv_port != "") $srv_port = $this->_dataObject->data->srv_port;
		if ($srv_priority != "" && $srv_weight != "" && $srv_subdomain != "" && $srv_hostname != "" && $srv_port != ""){
			$cmd['attributes']['records']['SRV'][0] = array(
				'priority' => $srv_priority,
				'weight' => $srv_weight,
				'subdomain' => $srv_subdomain,
				'hostname' => $srv_hostname,
				'port' => $srv_port
			);
		}

		// records - TXT 
		$txt_subdomain = "";
		$txt_text = "";
		if (isSet($this->_dataObject->data->txt_subdomain) && $this->_dataObject->data->txt_subdomain != "") $txt_subdomain = $this->_dataObject->data->txt_subdomain;
		if (isSet($this->_dataObject->data->txt_text) && $this->_dataObject->data->txt_text != "") $txt_text = $this->_dataObject->data->txt_text;
		if ($txt_subdomain != "" && $txt_text != ""){
			$cmd['attributes']['records']['TXT'][0] = array(
				'subdomain' => $txt_subdomain,
				'text' => $txt_text
			);
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
