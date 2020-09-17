<?php

class openSRS_base {
	// This values will be part of separate config file
	protected $osrs_username = "";
	protected $osrs_password = "";
	protected $osrs_key = "";
	protected $osrs_environment = "";		// 'TEST' / 'LIVE' / 'HRS'
	protected $osrs_protocol = "";			// 'XCP' / 'TPP'
	protected $osrs_host = "";
	protected $osrs_port = "";
	protected $osrs_sslPort = "";
	protected $osrs_baseClassVersion = "";
	protected $osrs_version = "";

	// Class internal
	protected $crypt_type = 'DES'; 				// 'DES'/'BLOWFISH'/'SSL'
	private $_socket = false;
	private $_socketErrorNum = false;
	private $_socketErrorMsg = false;
	private $_socketTimeout = 120;				// seconds
	private $_socketReadTimeout = 120;			// seconds
	private $_authenticated = false;
	private $CRLF= "\r\n";

	protected $_opsHandler;
	protected $_CBC = false;


	// Class constructor
	public function __construct ($dataObject) {
		$this->_pullConfigVariables ();
		$this->_verifySystemProperties ();
		$this->_opsHandler = new openSRS_ops;
		$this->osrs_username = $dataObject->user;
		$this->osrs_key = $dataObject->key;
        $this->osrs_host = $dataObject->host;
	}

	// Class destructor
	public function __destruct () {
	}


 	// Private functions
	private function _pullConfigVariables () {
		$xmlHand = new class_xml;

		// Load and read active config file
		$actConfig = $xmlHand->xml2array(ACTIVECONFIG);
		if ($actConfig != false){
			$actFileArray = $xmlHand->getValueByPath ($actConfig, "config/file");
			$actFile = $actFileArray['value'];
		
			// Load active Config
			$fileConfig = $xmlHand->xml2array(OPENSRSURI . OPENSRSCONFINGS . $actFile);
			if ($fileConfig != false){
				$fileFileArray = $xmlHand->getValueByPath ($fileConfig, "config/osrsRegular");
	
				foreach ($fileFileArray as $ffa){
					if ($ffa['name'] == "osrs_username" && isSet($ffa['value']))  $this->osrs_username = $ffa['value'];
					if ($ffa['name'] == "osrs_password" && isSet($ffa['value']))  $this->osrs_password = $ffa['value'];
					if ($ffa['name'] == "osrs_key" && isSet($ffa['value']))  $this->osrs_key = $ffa['value'];
					if ($ffa['name'] == "osrs_environment" && isSet($ffa['value']))  $this->osrs_environment = $ffa['value'];
					if ($ffa['name'] == "osrs_protocol" && isSet($ffa['value']))  $this->osrs_protocol = $ffa['value'];
					if ($ffa['name'] == "osrs_host" && isSet($ffa['value']))  $this->osrs_host = $ffa['value'];
					if ($ffa['name'] == "osrs_port" && isSet($ffa['value']))  $this->osrs_port = $ffa['value'];
					if ($ffa['name'] == "osrs_sslPort" && isSet($ffa['value']))  $this->osrs_sslPort = $ffa['value'];
					if ($ffa['name'] == "osrs_baseClassVersion" && isSet($ffa['value']))  $this->osrs_baseClassVersion = $ffa['value'];
					if ($ffa['name'] == "osrs_version" && isSet($ffa['value']))  $this->osrs_version = $ffa['value'];
				}
		
				// Verify all the variables
				if ($this->osrs_username == "" || $this->osrs_key == "" || $this->osrs_environment == "" || $this->osrs_protocol == ""){
					if ($this->osrs_username == "") trigger_error ("oSRS Error - Incomplete config file - Missing osrs_username", E_USER_WARNING);
					if ($this->osrs_key == "") trigger_error ("oSRS Error - Incomplete config file - Missing osrs_key", E_USER_WARNING);
					if ($this->osrs_environment == "") trigger_error ("oSRS Error - Incomplete config file - Missing osrs_environment", E_USER_WARNING);
					if ($this->osrs_protocol == "") trigger_error ("oSRS Error - Incomplete config file - Missing osrs_protocol", E_USER_WARNING);
					die ();
				}
				if ($this->osrs_host == "" || $this->osrs_port == "" || $this->osrs_sslPort == "" || $this->osrs_baseClassVersion == "" || $this->osrs_version == ""){
					if ($this->osrs_host == "") trigger_error ("oSRS Error - Incomplete config file - Missing osrs_host", E_USER_WARNING);
					if ($this->osrs_port == "") trigger_error ("oSRS Error - Incomplete config file - Missing osrs_port", E_USER_WARNING);
					if ($this->osrs_sslPort == "") trigger_error ("oSRS Error - Incomplete config file - Missing osrs_sslPort", E_USER_WARNING);
					if ($this->osrs_baseClassVersion == "") trigger_error ("oSRS Error - Incomplete config file - Missing osrs_baseClassVersion", E_USER_WARNING);
					if ($this->osrs_version == "") trigger_error ("oSRS Error - Incomplete config file - Missing osrs_version", E_USER_WARNING);
					die ();
				}

				// Some variables should be in upper case
				if ($this->osrs_environment != "") $this->osrs_environment = strtoupper($this->osrs_environment);
				if ($this->osrs_protocol) $this->osrs_protocol = strtoupper($this->osrs_protocol);
			}
		}
	}

	private function _verifySystemProperties () {
		// Encryption verification
        if ('SSL' == $this->crypt_type) {
            if (!function_exists('version_compare') || version_compare('4.3', phpversion(), '>=')) {
				$error_message = "PHP version must be v4.3+ (current version is ". phpversion() .") to use \"SSL\" encryption";
				trigger_error ($error_message, E_USER_WARNING);
				die();
			} elseif (!function_exists('openssl_open')) {
				$error_message = "PHP must be compiled using --with-openssl to use \"SSL\" encryption";
				trigger_error ($error_message, E_USER_WARNING);
				die();
			}
        }
	}

//  Send a command to the server
	public function send_cmd($request) {
		// make or get the socket filehandle
		if (!$this->init_socket() ) {
			trigger_error ("oSRS Error - Unable to establish socket: (". $this->_socketErrorNum .") ". $this->_socketErrorMsg, E_USER_WARNING);
			die();
		}

		// Authenticate user
		$auth = $this->authenticate();
		
		if (!$auth) {
			if ($this->_socket) $this->close_socket();
			trigger_error ("oSRS Error - Authentication Error: ". $auth['error'], E_USER_WARNING);
			die();
		}

		$this->send_data($request);
		$data = $this->read_data();
                
                $num_matches = preg_match('/<item key="response_code">401<\/item>/', $data, $matches);

                if ($num_matches > 0)
                    trigger_error("oSRS Error - Reseller username or osrs_key is incorrect, please check your config file.");
                
		return $data;
	}


//  Initialize a socket connection to the OpenSRS server
	private function init_socket() {
		if ($this->_socket) return true;
		if (!$this->osrs_environment) return false;

		if ($this->crypt_type == 'SSL') {
			$tempPortHand = $this->osrs_sslPort;
        	$conType = 'ssl://';
		} else {
			$tempPortHand = $this->osrs_port;
			$conType = '';
		}
        
		$this->_socket = fsockopen($conType . $this->osrs_host, $tempPortHand, $this->_socketErrorNum, $this->_socketErrorMsg, $this->_socketTimeout);
		if (!$this->_socket) {
			return false;
		} else {
			return true;
		}
	}


//  Authenticate the connection with the username/private key
	private function authenticate() {
		if ($this->_authenticated || 'SSL' == $this->crypt_type) {
			return array('is_success' => true);
		}

		$promptXML = $this->read_data();
		$prompt = $this->_opsHandler->decode($promptXML);
		if (isSet($prompt['response_code'])) {
			if ($prompt['response_code'] == 555 ) {
				// the ip address from which we are connecting is not accepted
				return array(
					'is_success'	=> false,
					'error'			=> $prompt['response_text']
				);
			}
		} else if ( !preg_match('/OpenSRS\sSERVER/', $prompt['attributes']['sender']) ||
			substr($prompt['attributes']['version'],0,3) != 'XML' ) {
			return array(
				'is_success'	=> false,
				'error'			=> 'Unrecognized Peer'
			);
		}

		// first response is server version
		$cmd = array(
			'protocol' => $this->osrs_protocol,
			'action' => 'check',
			'object' => 'version',
			'attributes' => array(
				'sender' => 'OpenSRS CLIENT',
				'version' => $this->osrs_version,
				'state' => 'ready'
			)
		);
		$xmlCMD = $this->_opsHandler->encode($cmd);
		$this->send_data($xmlCMD);

		$cmd = array(
			'protocol' => $this->osrs_protocol,
			'action' => 'authenticate',
			'object' => 'user',
			'attributes' => array(
				'crypt_type' => strtolower($this->crypt_type),
				'username' => $this->osrs_username,
				'password' => $this->osrs_username
			)
		);
		$xmlCMD = $this->_opsHandler->encode($cmd);
		$this->send_data( $xmlCMD );

		$challenge = $this->read_data();

		 // Sanity check to make sure that the osrs_key is all hex values
                $hex_check = ctype_xdigit($this->osrs_key);

                // Respond to the challenge with the MD5 checksum of the challenge.
                // ... and PHP's md5() doesn't return binary data, so
                // we need to pack that too

                if ($hex_check){
                        $this->_CBC = new openSRS_crypt(pack('H*', $this->osrs_key), $this->crypt_type);
                        $response = pack('H*',md5($challenge));
                        $this->send_data($response);

                        // Read the server's response to our login attempt (XML)
                        $answerXML = $this->read_data();
                        $answer = $this->_opsHandler->decode($answerXML);

                        if (substr($answer['response_code'],0,1)== '2') {
                                $this->_authenticated = true;
                                return true;
                        } else {
                                return false;
                        }
                } else {
                        trigger_error("oSRS Error - Please check the osrs_key value in the config file, it contains a non hexidecimal character.");
                }
	}

//		Close the socket connection
	private function close_socket() {
		fclose($this->_socket);
		if ($this->_CBC) $this->_CBC->_openSRS_crypt();			/* destructor */
		$this->_CBC				= false;
		$this->_authenticated	= false;
		$this->_socket			= false;
	}


	private function read_data() {
		$buf = $this->readData($this->_socket, $this->_socketReadTimeout);
		if (!$buf) {
			trigger_error ("oSRS Error - Read buffer is empty.  Please make sure IP is whitelisted in RWI. Check the osrs_key and osrs_username in the config file as well.", E_USER_WARNING);
			$data = "";
		} else {
			$data = $this->_CBC ? $this->_CBC->decrypt($buf) : $buf;
		}
		return $data;
	}

	private function send_data($message) {
		if ($this->_CBC) $message = $this->_CBC->encrypt($message);
		return $this->writeData( $this->_socket, $message );
	}

//	Regex check for valid email
	private function check_email_syntax($email) {
		if (preg_match('/(@.*@)|(\.\.)|(@\.)|(\.@)|(^\.)/', $email) || !preg_match('/^\S+\@(\[?)[a-zA-Z0-9\-\.]+\.([a-zA-Z]{2,3}|[0-9]{1,3})(\]?)$/', $email)) {
			return false;
		} else {
			return true;
		}
	}


/**
* Writes a message to a socket (buffered IO)
* @param	int 	socket handle
* @param	string 	message to write
*/
	private function writeData(&$fh,$msg) {
		$header = "";
		$len = strlen($msg);
		switch ($this->crypt_type) {
			case 'SSL':
				$signature = md5(md5($msg.$this->osrs_key).$this->osrs_key);
				$header .= "POST / HTTP/1.0". $this->CRLF;
				$header .= "Content-Type: text/xml" . $this->CRLF;
				$header .= "X-Username: " . $this->osrs_username . $this->CRLF;
				$header .= "X-Signature: " . $signature . $this->CRLF;
				$header .= "Content-Length: " . $len . $this->CRLF . $this->CRLF;
				break;
			case 'BLOWFISH':
			case 'DES':
			default:
				$header .= "Content-Length: " . $len . $this->CRLF . $this->CRLF;
				break;
		}
		fputs($fh, $header);
		fputs($fh, $msg, $len );
	}


/**
* Reads header data
* @param	int 	socket handle
* @param	int 	timeout for read
* @return	hash	hash containing header key/value pairs
*/
	private function readHeader($fh, $timeout=5) {
		$header = array();
		switch ($this->crypt_type) {
			case 'SSL':
				/* HTTP/SSL connection method */
				$http_log ='';
				$line = fgets($fh, 4000);
				$http_log .= $line;
				if (!preg_match('/^HTTP\/1.1 ([0-9]{0,3}) (.*)\r\n$/',$line, $matches)) {
					trigger_error ("oSRS Error - UNEXPECTED READ: Unable to parse HTTP response code. Please make sure IP is whitelisted in RWI.", E_USER_WARNING);
					return false;
				}
				$header['http_response_code'] = $matches[1];
				$header['http_response_text'] = $matches[2];

				while ($line != $this->CRLF) {
					$line = fgets($fh, 4000);
					$http_log .= $line;
					if (feof($fh)) {
						trigger_error ("oSRS Error - UNEXPECTED READ: Error reading HTTP header.", E_USER_WARNING);
						return false;
					}
					$matches = explode(': ', $line, 2);
					if (sizeof($matches) == 2) {
						$header[trim(strtolower($matches[0]))] = $matches[1];
					}
				}
				$header['full_header'] = $http_log;
				break;
			case 'BLOWFISH':
			case 'DES':
			default:
				/* socket (old-style) connection */
				$line = fgets($fh, 4000);
				if ($this->_opsHandler->socketStatus($fh)) {
					return false;
				}

				if (preg_match('/^\s*Content-Length:\s+(\d+)\s*\r\n/i', $line, $matches ) ) {
					$header['content-length'] = (int)$matches[1];
				} else {
					trigger_error ("oSRS Error - UNEXPECTED READ: No Content-Length.", E_USER_WARNING);
					return false;
				}

				/* read the empty line */
				$line = fread($fh, 2);
				if ($this->_opsHandler->socketStatus($fh)) {
					return false;
				}
				if ($line!=$this->CRLF) {
					trigger_error ("oSRS Error - UNEXPECTED READ: No CRLF.", E_USER_WARNING);
					return false;
				}
				break;
		}
		return $header;
	}


/**
* Reads data from a socket
* @param	int 	socket handle
* @param	int 	timeout for read
* @return	mixed	buffer with data, or an error for a short read
*/
	private function readData(&$fh, $timeout=5) {
		$len = 0;
		/* PHP doesn't have timeout for fread ... we just set the timeout for the socket */
		socket_set_timeout($fh, $timeout);
		$header = $this->readHeader($fh, $timeout);
		if (!$header || !isset($header['content-length']) || (empty($header['content-length']))) {
                        if ($this->crypt_type == "SSL")
                            trigger_error ("oSRS Error - UNEXPECTED ERROR: No Content-Length header provided! Please make sure IP is whitelisted in RWI.", E_USER_WARNING);
                        else
                            trigger_error ("oSRS Error - No Content-Length header returned. Please make sure IP is whitelisted in RWI. Check the osrs_key and osrs_username in the config file as well.", E_USER_WARNING);
		}

		$len = (int)$header['content-length'];
		$line = '';
		while (strlen($line) < $len) {
			$line .= fread($fh, $len);
			if ($this->_opsHandler->socketStatus($fh)) {
				return false;
			}
		}

		if ($line) {
			$buf = $line;
		} else {
			$buf = false;
		}

		if ('SSL' == $this->crypt_type) $this->close_socket();
		return $buf;
	}

	// Helper functions
	public function convertXML2array ($xml) {
		$array = $this->_opsHandler->decode($xml);
		return $array;
	}

}
