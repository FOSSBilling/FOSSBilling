<?php
/**
 * SRSX REGISTRAR MODULE FOR BOXBILLING
 *
 * LICENSE
 *
 * This source file is subject to the license that is bundled
 * with this package in the file LICENSE.txt
 * It is also available through the world-wide-web at this URL:
 * http://www.boxbilling.com/LICENSE.txt
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@boxbilling.com so we can send you a copy immediately.
 *
 * @copyright Copyright (c) 2010-2020 BoxBilling (http://www.boxbilling.com)
 * @license   http://www.boxbilling.com/LICENSE.txt
 * @version   $Id$
 * Created by bayamsoftware.com & @timothygwebb
 * For free hosting please visit host.bayamsoftware.com
 */
 
/**
 * HTTP API documentation https://kb.srs-x.com/en/
 */
class Registrar_Adapter_srsx extends Registrar_Adapter_Resellerclub
{
 
    public function __construct($options)
    {
 
        if (!extension_loaded('curl')) {
            throw new Registrar_Exception('CURL extension is not enabled');
        }
 
        if(isset($options['resellerId']) && !empty($options['resellerId'])) {
            $this->config['resellerId'] = $options['resellerId'];
            unset($options['resellerId']);
        } else {
            throw new Registrar_Exception('Domain registrar "SRSX" is not configured properly. Please update configuration parameter "SRSX Reseller ID" at "Configuration -> Domain registration".');
        }
 
        if(isset($options['apiUsername']) && !empty($options['apiUsername'])) {
            $this->config['apiUsername'] = $options['apiUsername'];
            unset($options['apiUsername']);
        } else {
            throw new Registrar_Exception('Domain registrar "SRSX" is not configured properly. Please update configuration parameter "SRSX Username" at "Configuration -> Domain registration".');
        }
 
        if(isset($options['apiPassword']) && !empty($options['apiPassword'])) {
            $this->config['apiPassword'] = $options['apiPassword'];
            unset($options['apiPassword']);
        } else {
            throw new Registrar_Exception('Domain registrar "SRSX" is not configured properly. Please update configuration parameter "ResellerClub API Key" at "Configuration -> Domain registration".');
        }
 
 
 
 
    }    
 
        public static function getConfig()
    {
        return array(
            'label'     =>  'Manages domains on SRSX via API. ResellerClub requires your server IP in order to work. Login to the ResellerClub control panel (the url will be in the email you received when you signed up with them) and then go to Settings > API and enter the IP address of the server where BoxBilling is installed to authorize it for API access.',
            'form'  => array(
 
                'resellerId' => array('text', array(
                            'label' => 'RESELLER ID',
                            'description'=> 'You can get this at ResellerClub control panel, go to Settings -> API'
                        ),
                     ),
 
                'apiUsername' => array('text', array(
                        'label' => 'Username Reseller. You can get this at ResellerClub control panel Settings > Personal information > Primary profile > Reseller ID',
                        'description'=> 'SRSX Reseller UserName'
                            ),
                        ),
 
                 'apiPassword' => array('password', array(
                    'label' => 'Password Reseller. You can get this at ResellerClub control panel Settings > Personal information > Primary profile > Reseller ID',
                    'description'=> 'SRSX Password ID'
                ),
             ),
 
            ),
        );
    } 
 
     #still not fixed 
    public function synchInfo() {
		# Get domain information
		$postfields = array(
			"domain" => $this->options["sld"].".".$this->options["tld"]
		);
		$domaininfoResult = $this->_callApi("domain/info",$postfields);
		if (sprintf($domaininfoResult->result->resultCode)==1000) {
			# Get domain ID
			$domainid = $this->getDomainId();
			# Update status
			$status = sprintf($domaininfoResult->resultData->status);
			if (strtolower($status)=="active") {
				if (Capsule::table("hb_domains")->where("id",$domainid)->update(array("status"=>"Active"))) {
					$this->addInfo("Status has been updated to Active");
				}
			} else {
				if (in_array(strtolower($status),array("awaiting document","active*","verifying","validating","document approved"))) {
					if (Capsule::table("hb_domains")->where("id",$domainid)->update(array("status"=>"Pending Registration"))) {
						$this->addInfo("Status has been updated to Pending Registration");
					}
				}
			}
			if (in_array(strtolower($status),array("active","active*"))) {
				# Update start date
				$startdate = sprintf($domaininfoResult->resultData->enddate);
				if ($startdate!="1970-01-01") {
					if (Capsule::table("hb_domains")->where("id",$domainid)->update(array("date_created"=>$startdate))) {
						$this->addInfo("Start date has been updated");
					}
				}
				# Update expiry date
				$expirydate = sprintf($domaininfoResult->resultData->enddate);
				if ($expirydate!="1970-01-01") {
					if (Capsule::table("hb_domains")->where("id",$domainid)->update(array("expires"=>$expirydate))) {
						$this->addInfo("Expiry date has been updated");
					}
				}
				# Update NS
				$ns = array();
				for ($i=1;$i<=8;$i++) { 
					$nsItem = "ns{$i}";
					if (sprintf($domaininfoResult->resultData->$nsItem)) {
						$ns[] = sprintf($domaininfoResult->resultData->$nsItem);
					}
				}
				if (Capsule::table("hb_domains")->where("id",$domainid)->update(array("nameservers"=>implode("|",$ns)))) {
					$this->addInfo("Nameservers has been updated");
				}
			}
			return true;
		} else {
			$this->addError(sprintf($domaininfoResult->result->resultMsg));
			return false;
		}
    }
    
    //its fixed for check
    public function isDomainAvailable(Registrar_Domain $domain)
    {
        $params = array(
            'domain'           =>  $domain->getName(),
        );
 
        $domaincheckResult = $this->_callApi("domain/check",$params);
		if (sprintf($domaincheckResult->result->resultCode)==1000) {
			return true;
		} else {
			return false;
		}
    }
 
     //for register domain -- still not fixed
     public function registerDomain(Registrar_Domain $domain)
     {
         
        $c = $domain->getContactRegistrar();
        $company = $c->getCompany();
        if (!isset($company) || strlen(trim($company)) == 0 ){
            $company = 'N/A';
        }
        $phoneNum = $c->getTel();
        $phoneNum = preg_replace( "/[^0-9]/", "", $phoneNum);
        $phoneNum = substr($phoneNum, 0, 12);

         # check if available on srsx
         $postfields = array(
             "user_username" => $c->getEmail()
         );
         $userinfoResult = $this->_callApi("user/info",$postfields);
         if (sprintf($userinfoResult->result->resultCode)!=1000) {
             # create new user
             $postfields = array(
                 "user_username" => $c->getEmail(),
                 "user_password" =>  $c->getPassword(),
                 "fname"         => $c->getFirstName(),
                 "lname"         => $c->getLastName(), 
                 "company"       => $company,
                 "address"       => $c->getAddress1(),
                 "address2"      => $c->getAddress2(),
                 "city"          => $c->getCity(),
                 "province"      =>  $c->getState(),
                 "country"       => $c->getCountry(),
                 "postal_code"   => $c->getZip(),
                 "phone"         => $phoneNum

             );
             $usercreate = $this->_callApi("user/create",$postfields);
             if (sprintf($usercreate->result->resultCode)!=1000) {
                 $this->addError("User create: ".sprintf($usercreate->result->resultMsg));
                 return false;
             }
         }
         # Get domain ID
         $domainid = $this->getDomainId();
         # Register domain  
         #$this->client_data wut by timothy? 
         $postfields = array(
             "domain"           => $this->options["sld"].".".$this->options["tld"],
             "api_id"           => $domainid,
             "periode"          => $this->options["numyears"],
             "fname"            => $this->client_data["firstname"],
             "lname"            => $this->client_data["lastname"],
             "company"          => $this->client_data["companyname"],
             "address1"         => $this->client_data["address1"],
             "address2"         => $this->client_data["address2"],
             "city"             => $this->client_data["city"],
             "state"            => $this->client_data["state"],
             "postcode"         => $this->client_data["postcode"],
             "country"          => $this->client_data["country"],
             "phonenumber"      => $this->client_data["phonenumber"],
             "handphone"        => $this->client_data["phonenumber"],
             "email"            => $this->client_data["email"],
             "user_username"    => $this->client_data["email"],
             "user_fname"       => $this->client_data["firstname"],
             "user_lname"       => $this->client_data["lastname"],
             "user_email"       => $this->client_data["email"],
             "user_company"     => $this->client_data["companyname"],
             "user_address"     => $this->client_data["address1"],
             "user_address2"    => $this->client_data["address2"],
             "user_city"        => $this->client_data["city"],
             "user_province"    => $this->client_data["state"],
             "user_phone"       => $this->client_data["phonenumber"],
             "user_country"     => $this->client_data["country"],
             "user_postal_code" => $this->client_data["postcode"],
             "randomhash"       => $this->randomhash(64)
         );
         # NS
         for ($i=1;$i<=4;$i++) { 
             if ($this->options["ns{$i}"] || $this->details["ns{$i}"]) {
                 $nameserver[] = $this->options["ns{$i}"] ? $this->options["ns{$i}"] : $this->details["ns{$i}"];
             }
         }
         $registerResult = $this->_callApi("domain/register",$postfields);
         if (sprintf($registerResult->result->resultCode)==1000) {
             $this->addInfo("Domain has been registered");
             return true;
         } else {
             if (preg_match("#upload required documents#",sprintf($registerResult->result->resultMsg))) {
                 if (Capsule::table("hb_domains")->where("id",$domainid)->update(array("status"=>"Pending Registration"))) {
                     $this->addInfo("Domain has been registered, but domain name activation is waiting for documents to be uploaded");
                     return true;
                 }
             } elseif (sprintf($registerResult->result->resultMsg)=="Order with the same ID already created") {
                 if (Capsule::table("hb_domains")->where("id",$domainid)->update(array("status"=>"Pending Registration"))) {
                     $this->addInfo("Domain has been registered, please Synchronize this order");
                     return true;
                 }
             } else {
                 $this->addError("Domain create: ".sprintf($registerResult->result->resultMsg));
             }
             return false;
         }
     }
 
    #still not fixed
    public function modifyNs(Registrar_Domain $domain)
    {
        $ns = array();
        $ns[] = $domain->getNs1();
        $ns[] = $domain->getNs2();
        if($domain->getNs3())  {
            $ns[] = $domain->getNs3();
        }
        if($domain->getNs4())  {
            $ns[] = $domain->getNs4();
        }

        $params = array(
            'order-id'  =>  $this->_getDomainOrderId($domain),
            'ns'        =>  $ns,
        );

        $result = $this->_callApi('domain/updatens', $params, 'POST');
        return ($result['status'] == 'Success');
    }
   
     #still not fixed
    public function modifyContact(Registrar_Domain $domain)
    {
        $customer = $this->_getCustomerDetails($domain);
        $cdetails = $this->_getDefaultContactDetails($domain, $customer['customerid']);
        $contact_id = $cdetails['Contact']['registrant'];

        $c = $domain->getContactRegistrar();
        
        $required_params = array(
            'contact-id'        =>  $contact_id,
            'name'              =>  $c->getName(),
            'company'           =>  $c->getCompany(),
            'email'             =>  $c->getEmail(),
            'address-line-1'    =>  $c->getAddress1(),
            'city'              =>  $c->getCity(),
            'zipcode'           =>  $c->getZip(),
            'phone-cc'          =>  $c->getTelCc(),
            'phone'             =>  $c->getTel(),
            'country'           =>  $c->getCountry(),
        );

        $optional_params = array(
            'address-line-2'    =>  $c->getAddress2(),
            'address-line-3'    =>  $c->getAddress3(),
            'state'             =>  $c->getState(),
        );

        $params = array_merge($optional_params, $required_params);
        $result = $this->_callApi('domain/editcontact', $params, 'POST');
        return ($result['status'] == 'Success');
    }

    public function transferDomain(Registrar_Domain $domain)
    {
        
		# Get EPP Code
		$postfields = array(
			"domain" => $this->options["sld"].".".$this->options["tld"]
		);
		$geteppResult = $this->_callApi("domain/getepp",$postfields);
		if (sprintf($geteppResult->result->resultCode)==1000) {
			# Transfer domain
			$postfields = array(
				"domain"           => $this->options["sld"].".".$this->options["tld"],
				"transfersecret"   => base64_encode(sprintf($geteppResult->resultData->epp)),
				"periode"          => $this->options["numyears"],
				"ns1"              => $this->options["ns1"] ? $this->options["ns1"] : $this->details["ns1"],
				"ns2"              => $this->options["ns2"] ? $this->options["ns2"] : $this->details["ns2"],
				"ns3"              => $this->options["ns3"] ? $this->options["ns3"] : $this->details["ns3"],
				"ns4"              => $this->options["ns4"] ? $this->options["ns4"] : $this->details["ns4"],
				"fname"            => $this->client_data["firstname"],
				"lname"            => $this->client_data["lastname"],
				"companyname"      => $this->client_data["companyname"],
				"address1"         => $this->client_data["address1"],
				"address2"         => $this->client_data["address2"],
				"city"             => $this->client_data["city"],
				"state"            => $this->client_data["state"],
				"postcode"         => $this->client_data["postcode"],
				"country"          => $this->client_data["country"],
				"phonenumber"      => $this->client_data["phonenumber"],
				"handphone"        => $this->client_data["phonenumber"],
				"email"            => $this->client_data["email"],
				"user_username"    => $this->client_data["email"],
				"user_fname"       => $this->client_data["firstname"],
				"user_lname"       => $this->client_data["lastname"],
				"user_email"       => $this->client_data["email"],
				"user_company"     => $this->client_data["companyname"],
				"user_address"     => $this->client_data["address1"],
				"user_address2"    => $this->client_data["address2"],
				"user_city"        => $this->client_data["city"],
				"user_province"    => $this->client_data["state"],
				"user_phone"       => $this->client_data["phonenumber"],
				"user_country"     => $this->client_data["country"],
				"user_postal_code" => $this->client_data["postcode"],
			);
			$domaintransferResult = $this->_callApi("domain/transfer",$postfields);
			if (sprintf($domaintransferResult->result->resultCode)==1000) {
				$this->addInfo("Domain has been transferred");
				return true;
			} else {
				$this->addError(sprintf($domaintransferResult->result->resultMsg));
			}
		} else {
			$this->addError(sprintf($geteppResult->result->resultMsg));
		}
		return false;
    }
 
   
    public function renewDomain(Registrar_Domain $domain)
    {
        # Get EPP Code
		$postfields = array(
			"domain"  => $this->options["sld"].".".$this->options["tld"],
			"periode" => $this->options['numyears']
		);
		$renewdomainResult = $this->_callApi("domain/getepp",$postfields);
		if (sprintf($renewdomainResult->result->resultCode)==1000) {
			$this->addInfo("Domain has been renewed");
			return true;
		} else {
			$this->addError(sprintf($renewdomainResult->result->resultMsg));
		}
		return false;
    }
    
    public function getEpp(Registrar_Domain $domain)
    {
       # Get EPP Code
		$postfields = array(
			"domain" => $this->options["sld"].".".$this->options["tld"]
		);
		$domaingeteppResult = $this->_callApi("domain/getepp",$postfields);
		if (sprintf($domaingeteppResult->result->resultCode)==1000) {
			$domainid = $this->getDomainId();
			Capsule::table("hb_domains")->where("id",$domainid)->update(array("epp_code"=>sprintf($domaingeteppResult->resultData->epp)));
			$this->addInfo("EPP Code: ".sprintf($domaingeteppResult->resultData->epp));
			return true;
		} else {
			$this->addError(sprintf($domaingeteppResult->result->resultMsg));
			return false;
		}
    }

    public function lock(Registrar_Domain $domain)
    {
        $params = array(
            'order-id'        =>  $this->_getDomainOrderId($domain),
        );

        $domaincheckResult = $this->_callApi("domain/lock",$params);
		if (sprintf($domaincheckResult->result->resultCode)==1000) {
			return true;
		} else {
			return false;
		}
    }

    public function unlock(Registrar_Domain $domain)
    {
        $params = array(
            'order-id'        =>  $this->_getDomainOrderId($domain),
        );

        $domaincheckResult = $this->_callApi("domain/unlock",$params);
		if (sprintf($domaincheckResult->result->resultCode)==1000) {
			return true;
		} else {
			return false;
		}
    }

    public function Cancel(Registrar_Domain $domain) {
		# Cancel domain
		$postfields = array(
			"domain" => $this->options["sld"].".".$this->options["tld"]
		);
		$domaincancelResult = $this->_callApi("domain/cancel",$postfields);
		if (sprintf($domaincancelResult->result->resultCode)==1000) {
			$this->addInfo("Domain has been cancelled");
			return true;
		} else {
			$this->addError(sprintf($domaincancelResult->result->resultMsg));
			return false;
		}
    }
    
    private function _getCustomerDetails()
    {
        # Get contact information
		$postfields = array(
			"domain" => $this->options["sld"].".".$this->options["tld"]
		);
		$contactinfoResult = $this->_callApi("contact/info",$postfields);
		if (sprintf($contactinfoResult->result->resultCode)==1000) {
			$contact = array(
				"contactid"   => sprintf($contactinfoResult->resultData->contactid),
				"userid"      => sprintf($contactinfoResult->resultData->userid),
				"nickhandle"  => sprintf($contactinfoResult->resultData->nickhandle),
				"firstname"   => sprintf($contactinfoResult->resultData->fname),
				"lastname"    => sprintf($contactinfoResult->resultData->lname),
				"email"       => sprintf($contactinfoResult->resultData->email),
				"companyname" => sprintf($contactinfoResult->resultData->company),
				"address1"    => sprintf($contactinfoResult->resultData->address1),
				"address2"    => sprintf($contactinfoResult->resultData->address2),
				"address3"    => sprintf($contactinfoResult->resultData->address3),
				"city"        => sprintf($contactinfoResult->resultData->city),
				"state"       => sprintf($contactinfoResult->resultData->state),
				"phonenumber" => sprintf($contactinfoResult->resultData->phonenumber),
				"fax"         => sprintf($contactinfoResult->resultData->fax),
				"country"     => sprintf($contactinfoResult->resultData->country),
				"postcode"    => sprintf($contactinfoResult->resultData->postcode),
			);
			return $contact;
		} else {
			$this->addError(sprintf($contactinfoResult->result->resultMsg));
			return false;
		}
    }

    private function _createCustomer()
    {
        # Get contact information
		$postfields = array(
			"domain" => $this->options["sld"].".".$this->options["tld"]
		);
		$contactinfoResult = $this->_callApi("contact/info",$postfields);
		if (sprintf($contactinfoResult->result->resultCode)==1000) {
			# Update contact information
			$postfields = array(
				"contactid"   => sprintf($contactinfoResult->resultData->contactid),
				"fname"       => $this->options['firstname'],
				"lname"       => $this->options['lastname'],
				"email"       => $this->options["email"],
				"company"     => $this->options['companyname'],
				"address"     => $this->options['address1'],
				"address2"    => $this->options["address2"],
				"city"        => $this->options["city"],
				"province"    => $this->options["state"],
				"country"     => $this->options["country"],
				"postal_code" => $this->options["postcode"],
				"phone"       => $this->options["phonenumber"],
			);
			$contactupdateResult = $this->_callApi("contact/create",$postfields);
			if ($contactupdateResult) {
				$this->addInfo("Contact has been updated");
				return true;
			} else {
				$this->addError(sprintf($contactupdateResult->result->resultMsg));
				return false;
			}
		} else {
			$this->addError(sprintf($contactinfoResult->result->resultMsg));
			return false;
		}
    }
    
    private function _getDefaultContactDetails(Registrar_Domain $domain, $customerid)
    {
        $params = array(
            'customer-id'   =>  $customerid,
            'type'          =>  'Contact',
        );

        return $this->_callApi('contact/info', $params, 'POST');
    }

    private function removeCustomer($params)
    {
        $required_params = array(
            'customer-id'   =>  '',
        );
        $params = $this->_checkRequiredParams($required_params, $params);
        $result = $this->_callApi('contact/delete', $params, 'POST');
        return ($result == 'true');
    }
    
    private function _hasCompletedOrder(Registrar_Domain $domain)
    {
        try {
            $orderid = $this->_getDomainOrderId($domain);
            $params = array(
                'order-id'      =>  $orderid,
                'options'       =>  'All',
            );
            $data = $this->_callApi('domain/info', $params);
        } catch(Exception $e) {
            return false;
        }
        
        return ($data['currentstatus'] == 'Active');
    }

    protected function _callApi($query=true,$postfields=true) {
 

		if ($query && is_array($postfields)) {
			# Get URL
			$apiUrl = "https://srb{$this->config["resellerId"]}.srs-x.com";
			# Basic authentication
			$postfields["username"] = $this->config["apiUsername"];
            $postfields["password"] = hash('sha256',$this->config["apiPassword"]);
 
			# CURL
            $ch = curl_init();
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_URL, "{$apiUrl}/api/{$query}");
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postfields));
			$apiXml = curl_exec($ch);
			curl_close($ch);
            $apiResult = simplexml_load_string($apiXml);
 
			//$this->logmessage("error","API_URL: {$apiUrl}/api/{$query}\nAPI_Postfields:\n".print_r($this->protectField($postfields),true)."API_Response:\n".print_r($apiXml,true));
            return $apiResult;
            echo json_encode($apiResult);
 
		}
		return false;
    }
 

    protected function randomhash($length=6) {
		$base = 'ABCDEFGHKLMNOPQRSTWXYZ123456789';
		$max = strlen($base)-1;
		$randomResult = "";
		mt_srand((double)microtime()*1000000);
		while (strlen($randomResult)<$length) {
			$randomResult .= $base[mt_rand(0,$max)];
		}
		return $randomResult;
	}
 
}