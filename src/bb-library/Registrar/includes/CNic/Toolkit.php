<?php
	// CNic_Toolkit - toolkit classes for PHP
	// Copyright (c) 2011 CentralNic Ltd. All rights reserved. This program is
	// free software; you can redistribute it and/or modify it under the terms
	// of the GNU GPL
	//
	// $Id: Toolkit.php,v 1.60 2011/05/13 13:21:26 gavin Exp $

	if (!extension_loaded('curl')) trigger_error('CNic_Toolkit requires the cURL extension!', E_USER_ERROR);

	class CNic_Toolkit {

		/**
		* The version of this release
		* @var string
		*/
		var $VERSION  = '0.0.34';

		/**
		* The hostname of the Toolkit service
		* @var string
		*/
		var $HOSTNAME = 'toolkit.centralnic.com';

		/**
		* where we store request parameters:
		* @var array
		*/
		private $params;

		/**
		* constructor. the behaviour of this method is dependant on the number of arguments it is given.
		* However, the first argument is always a string containing the command to execute.
		*/
		function __construct() {
			$this->params = array('test' => '0');
			$no_args = func_num_args();
			switch ($no_args) {
				case 1:
					$this->command = func_get_arg(0);
					break;

				case 2:
					list($this->command, $this->params['domain']) = func_get_args();
					break;

				case 3:
					list($this->command, $this->params['domain'], $this->ssl) = func_get_args();
					break;

				case 4:
					list($this->command, $this->ssl, $this->user, $this->password) = func_get_args();
					break;

				case 5:
					list($this->command, $this->params['domain'], $this->ssl, $this->user, $this->password) = func_get_args();
					break;

				default:
					trigger_error('invalid number of arguments!', E_USER_ERROR);

			}

			$this->agent	= curl_init();
			$curl_version	= curl_version();
			curl_setopt($this->agent, CURLOPT_USERAGENT, sprintf(
				'CNic_Toolkit/%s (cURL %s, OpenSSL %s, PHP %s, %s %s)',
				$this->VERSION,
				$curl_version['version'],
				$curl_version['ssl_version'],
				phpversion(),
				php_uname('s'),
				php_uname('r')
			));

			ini_set('include_path', ini_get('include_path').':'.dirname(dirname(__FILE__)));
		}

		/**
		* set a query parameter
		* @param string $name
		* @param mixed $value
		*/
		function set($name, $value) {
			$this->params[$name] = $value;
			return true;
		}

		/**
		* prepare the query, execute it and return the response
		* @return CNic_Toolkit_Response
		*/
		function execute() {
			require_once('CNic/Toolkit/Response.php');
			curl_setopt($this->agent, CURLOPT_TIMEOUT, (isset($this->params['timeout']) && $this->params['timeout'] > 0 ? $this->params['timeout'] : 10));
			switch ($this->command) {
				case 'suffixes':
					return $this->_suffixes();

				case 'search':
					return $this->_search();

				case 'whois':
					return $this->_whois();

				case 'create_handle':
					return $this->_create_handle();

				case 'handle_info':
					return $this->_handle_info();

				case 'register':
					return $this->_register(0);

				case 'register_idn':
					return $this->_register(1);

				case 'modify':
					return $this->_modify();

				case 'modify_handle':
					return $this->_modify_handle();

				case 'renewals':
					return $this->_renewals();

				case 'list_domains':
					return $this->_list_domains();

				case 'issue_renewals':
					return $this->_issue_renewals();

				case 'pricing':
					return $this->_get_pricing();

				case 'delete':
					return $this->_delete_domain();

				case 'decline':
					return $this->_decline_domain();

				case 'undecline':
					return $this->_undecline_domain();

				case 'start_transfer':
					return $this->_start_transfer();

				case 'cancel_transfer':
					return $this->_cancel_transfer();

				case 'check_transfer':
					return $this->_check_transfer();

				case 'reactivate':
					return $this->_reactivate_request();

				case 'push_domain':
					return $this->_push_domain();

				case 'auth_info':
					return $this->_auth_info();

				case 'poll_transfers':
					return $this->_poll_transfers();

				case 'approve_transfer':
					return $this->_approve_transfer();

				case 'reject_transfer':
					return $this->_reject_transfer();

				case 'list_outstanding_domains':
					return $this->_list_outstanding_domains();

				case 'submit_payment_batch':
					return $this->_submit_payment_batch();

				case 'registrant_transfer':
					return $this->_registrant_transfer();

				case 'lock_domain':
					return $this->_lock_domain();

				case 'unlock_domain':
					return $this->_unlock_domain();

				case 'validate_domain':
					return $this->_validate_domain();

				default:
					trigger_error('Invalid command', E_USER_ERROR);
			}
		}

		/**
		* @access private
		*/
		private function _suffixes() {
			require_once('CNic/Toolkit/Response/Suffixes.php');
			$url = $this->_base().'suffixes?test='.$this->params['test'];
			$this->_raw_data = $this->_request($url);
			return new CNic_Toolkit_Response_Suffixes($this->_raw_data);
		}

		/**
		* @access private
		*/
		private function _search() {
			require_once('CNic/Toolkit/Response/Search.php');
			$url = $this->_base().'search';
			$params = array(
				'domain'	=> $this->params['domain'],
				'test'		=> $this->params['test']
			);
			if (isset($this->params['suffixlist'])) $params['suffixlist'] = implode(',', $this->params['suffixlist']);
			$this->_raw_data = $this->_request($url, $params);
			return new CNic_Toolkit_Response_Search($this->_raw_data);
		}

		/**
		* @access private
		*/
		private function _whois() {
			require_once('CNic/Toolkit/Response/Whois.php');
			$url = $this->_base().'wwwhois';
			$params = array(
				'domain'	=> $this->params['domain'],
				'test'		=> $this->params['test']
			);
			$this->_raw_data = $this->_request($url, $params);
			return new CNic_Toolkit_Response_Whois($this->_raw_data);
		}

		/**
		* @access private
		*/
		private function _create_handle() {
			require_once('CNic/Toolkit/Response/CreateHandle.php');
			$url = $this->_base().'create_handle';
			$params = array(
				'user'		=> $this->user,
				'password'	=> $this->_crypt_md5($this->password),
				'test'		=> $this->params['test'],
				'visible'	=> ($this->params['visible'] === 0 ? 0 : 1),
			);
			if (!empty($this->params['handle']['street1'])) {
				$params['name']		= $this->params['handle']['name'];
				$params['company']	= $this->params['handle']['company'];
				$params['street1']	= $this->params['handle']['street1'];
				$params['street2']	= $this->params['handle']['street2'];
				$params['street3']	= $this->params['handle']['street3'];
				$params['city']		= $this->params['handle']['city'];
				$params['sp']		= $this->params['handle']['sp'];
				$params['postcode']	= $this->params['handle']['postcode'];
				$params['country']	= $this->params['handle']['country'];
				$params['phone']	= $this->params['handle']['phone'];
				$params['fax']		= $this->params['handle']['fax'];
				$params['email']	= $this->params['handle']['email'];

			} else {
				$params['handle'] = $this->_format_handle_string($this->params['handle']);

			}

			$this->_raw_data = $this->_request($url, 'POST', $params);
			return new CNic_Toolkit_Response_CreateHandle($this->_raw_data);
		}

		/**
		* @access private
		*/
		private function _handle_info() {
			require_once('CNic/Toolkit/Response/HandleInfo.php');
			$url = $this->_base().'handle_info';
			$params = array(
				'user'		=> $this->user,
				'password'	=> $this->_crypt_md5($this->password),
				'handle'	=> $this->params['handle'],
				'test'		=> $this->params['test'],
				'visible'	=> ($this->params['visible'] === 0 ? 0 : 1),
			);
			$this->_raw_data = $this->_request($url, 'POST', $params);
			return new CNic_Toolkit_Response_HandleInfo($this->_raw_data);
		}

		/**
		* @access private
		*/
		private function _register($idn) {
			require_once('CNic/Toolkit/Response/Register.php');
			$url = $this->_base().'register';
			if (!isset($this->params['suffix'])) list($this->params['domain'], $this->params['suffix']) = explode('.', $this->params['domain'], 2);
			$params = array(
				'domain'	=> $this->params['domain'],
				'suffix'	=> $this->params['suffix'],
				'registrant'	=> $this->params['registrant'],
				'user'		=> $this->user,
				'password'	=> $this->_crypt_md5($this->password),
				'chandle'	=> $this->_format_handle_string($this->params['chandle']),
				'thandle'	=> $this->_format_handle_string($this->params['thandle']),
				'period'	=> $this->params['period'],
				'test'		=> $this->params['test']
			);
			if (isset($this->params['ahandle'])) $params['ahandle'] = $this->params['ahandle'];
			if (isset($this->params['bhandle'])) $params['bhandle'] = $this->params['bhandle'];
			if (isset($this->params['url'])) $params['url'] = $this->params['url'];
			if (isset($this->params['dns'])) $params = array_merge($params, $this->_dns_register_params($this->params['dns']));
			$this->_raw_data = $this->_request($url, 'POST', $params);
			return new CNic_Toolkit_Response_Register($this->_raw_data);
		}

		/**
		* @access private
		*/
		private function _modify() {
			require_once('CNic/Toolkit/Response/Modify.php');
			$url = $this->_base().'modify';
			$params = array(
				'domain'	=> $this->params['domain'],
				'user'		=> $this->user,
				'password'	=> $this->_crypt_md5($this->password),
				'test'		=> $this->params['test']
			);
			if (isset($this->params['thandle'])) $params['thandle'] = $this->_format_handle_string($this->params['thandle']);
			if (isset($this->params['chandle'])) $params['chandle'] = $this->_format_handle_string($this->params['chandle']);
			if (isset($this->params['ahandle'])) $params['url'] = $this->params['ahandle'];
			if (isset($this->params['bhandle'])) $params['url'] = $this->params['bhandle'];
			if (isset($this->params['ttl'])) $params['ttl'] = $this->_params['ttl'];
			if (isset($this->params['dns'])) $params = array_merge($params, $this->_dns_mod_params($this->params['dns']));
			if (isset($this->params['url'])) $params['url'] = $this->params['url'];
			$this->_raw_data = $this->_request($url, 'POST', $params);
			return new CNic_Toolkit_Response_Modify($this->_raw_data);
		}

		/**
		* @access private
		*/
		private function _modify_handle() {
			require_once('CNic/Toolkit/Response/ModifyHandle.php');
			$url = $this->_base().'modify_handle';
			$params = array(
				'user'		=> $this->user,
				'password'	=> $this->_crypt_md5($this->password),
				'test'		=> $this->params['test']
			);
			foreach ($this->params as $name => $value) $params[$name] = $value;
			$this->_raw_data = $this->_request($url, 'POST', $params);
			return new CNic_Toolkit_Response_ModifyHandle($this->_raw_data);
		}

		/**
		* @access private
		*/
		private function _renewals() {
			require_once('CNic/Toolkit/Response/Renewals.php');
			$url = $this->_base().'upcoming_renewals';
			$params = array(
				'user'		=> $this->user,
				'password'	=> $this->_crypt_md5($this->password),
				'months'	=> $this->params['months'],
				'test'		=> $this->params['test']
			);
			$this->_raw_data = $this->_request($url, 'GET', $params);
			return new CNic_Toolkit_Response_Renewals($this->_raw_data);
		}

		/**
		* @access private
		*/
		private function _list_domains() {
			require_once('CNic/Toolkit/Response/DomainList.php');
			$url = $this->_base().'list_domains';
			$params = array(
				'user'		=> $this->user,
				'password'	=> $this->_crypt_md5($this->password),
				'offset'	=> $this->params['offset'],
				'length'	=> $this->params['length'],
				'orderby'	=> $this->params['orderby'],
				'test'		=> $this->params['test']
			);
			$this->_raw_data = $this->_request($url, 'POST', $params);
			return new CNic_Toolkit_Response_DomainList($this->_raw_data);
		}

		/**
		* @access private
		*/
		private function _issue_renewals() {
			require_once('CNic/Toolkit/Response/IssueRenewals.php');
			$url = $this->_base().'issue_renewals';

            $params = array(
                'user' => $this->user,
                'password' => $this->_crypt_md5($this->password)
            );

			$params = array(
				'user'		=> $this->user,
				'password'	=> $this->_crypt_md5($this->password),
				'test'		=> $this->params['test'],
				//'immediate'	=> $this->params['immediate'],
				'period'	=> $this->params['period'],
			);
			for ($i = 0 ; $i < count($this->params['domains']) ; $i++) $params["domains[$i]"] = $this->params['domains'][$i];
			$this->_raw_data = $this->_request($url, 'POST', $params);
			return new CNic_Toolkit_Response_IssueRenewals($this->_raw_data);
		}

		/**
		* @access private
		*/
		private function _get_pricing() {
			require_once('CNic/Toolkit/Response/GetPricing.php');
			$url = $this->_base().'get_pricing';
			$params = array(
				'user'		=> $this->user,
				'password'	=> $this->_crypt_md5($this->password),
				'test'		=> $this->params['test'],
				'type'		=> $this->params['type']
			);
			$this->_raw_data = $this->_request($url, 'POST', $params);
			return new CNic_Toolkit_Response_GetPricing($this->_raw_data);
		}

		/**
		* @access private
		*/
		private function _delete_domain() {
			require_once('CNic/Toolkit/Response/DeleteDomain.php');
			$url = $this->_base().'delete_domain';
			$params = array(
				'domain'	=> $this->params['domain'],
				'user'		=> $this->user,
				'password'	=> $this->_crypt_md5($this->password),
				'test'		=> $this->params['test'],
				'reason'	=> $this->params['reason'],
				'immediate'	=> $this->params['immediate'],
			);
			$this->_raw_data = $this->_request($url, 'POST', $params);
			return new CNic_Toolkit_Response_DeleteDomain($this->_raw_data);
		}

		/**
		* @access private
		*/
		private function _decline_domain() {
			require_once('CNic/Toolkit/Response/DeclineDomain.php');
			$url = $this->_base().'decline_domain';
			$params = array(
				'domain'	=> $this->params['domain'],
				'user'		=> $this->user,
				'password'	=> $this->_crypt_md5($this->password),
				'test'		=> $this->params['test']
			);
			$this->_raw_data = $this->_request($url, 'POST', $params);
			return new CNic_Toolkit_Response_DeclineDomain($this->_raw_data);
		}

		/**
		* @access private
		*/
		private function _undecline_domain() {
			require_once('CNic/Toolkit/Response/UnDeclineDomain.php');
			$url = $this->_base().'undecline_domain';
			$params = array(
				'domain'	=> $this->params['domain'],
				'user'		=> $this->user,
				'password'	=> $this->_crypt_md5($this->password),
				'test'		=> $this->params['test']
			);
			$this->_raw_data = $this->_request($url, 'POST', $params);
			return new CNic_Toolkit_Response_UnDeclineDomain($this->_raw_data);
		}

		/**
		* @access private
		*/
		private function _start_transfer() {
			require_once('CNic/Toolkit/Response/StartTransfer.php');
			$url = $this->_base().'start_transfer';
			$params = array(
				'user'		=> $this->user,
				'password'	=> $this->_crypt_md5($this->password),
				'test'		=> $this->params['test'],
			);
			for ($i = 0 ; $i < count($this->params['domains']) ; $i++) $params["domains[$i]"] = $this->params['domains'][$i];
			//for ($i = 0 ; $i < count($this->params['authinfo']) ; $i++) $params["authinfo[$i]"] = $this->params['authinfo'][$i];
			$this->_raw_data = $this->_request($url, 'POST', $params);
			return new CNic_Toolkit_Response_StartTransfer($this->_raw_data);
		}

		/**
		* @access private
		*/
		private function _cancel_transfer() {
			require_once('CNic/Toolkit/Response/CancelTransfer.php');
			$url = $this->_base().'cancel_transfer';
			$params = array(
				'domain'	=> $this->params['domain'],
				'user'		=> $this->user,
				'password'	=> $this->_crypt_md5($this->password),
				'test'		=> $this->params['test']
			);
			$this->_raw_data = $this->_request($url, 'POST', $params);
			return new CNic_Toolkit_Response_CancelTransfer($this->_raw_data);
		}

		/**
		* @access private
		*/
		private function _check_transfer() {
			require_once('CNic/Toolkit/Response/CheckTransfer.php');
			$url = $this->_base().'check_transfer';
			$params = array(
				'domain'	=> $this->params['domain'],
				'user'		=> $this->user,
				'password'	=> $this->_crypt_md5($this->password),
				'test'		=> $this->params['test']
			);
			$this->_raw_data = $this->_request($url, 'POST', $params);
			return new CNic_Toolkit_Response_CheckTransfer($this->_raw_data);
		}

		/**
		* @access private
		*/
		private function _reactivate_request() {
			require_once('CNic/Toolkit/Response/ReactivateDomain.php');
			$url = $this->_base().'reactivation_request';
			$params = array(
				'domain'	=> $this->params['domain'],
				'user'		=> $this->user,
				'password'	=> $this->_crypt_md5($this->password),
				'test'		=> $this->params['test'],
				'email'		=> $this->params['email'],
			);
			$this->_raw_data = $this->_request($url, 'POST', $params);
			return new CNic_Toolkit_Response_ReactivateDomain($this->_raw_data);
		}

		/**
		* @access private
		*/
		private function _push_domain() {
			require_once('CNic/Toolkit/Response/PushDomain.php');
			$url = $this->_base().'push_domain';
			$params = array(
				'domain'	=> $this->params['domain'],
				'user'		=> $this->user,
				'password'	=> $this->_crypt_md5($this->password),
				'test'		=> $this->params['test'],
				'handle'	=> $this->params['handle'],
			);
			$this->_raw_data = $this->_request($url, 'POST', $params);
			return new CNic_Toolkit_Response_PushDomain($this->_raw_data);
		}

		/**
		* @access private
		*/
		private function _auth_info() {
			require_once('CNic/Toolkit/Response/AuthInfo.php');
			$url = $this->_base().'auth_info';
			$params = array(
				'domain'	=> $this->params['domain'],
				'user'		=> $this->user,
				'password'	=> $this->_crypt_md5($this->password),
				'test'		=> $this->params['test'],
			);
			$this->_raw_data = $this->_request($url, 'POST', $params);
			return new CNic_Toolkit_Response_AuthInfo($this->_raw_data);
		}

		/**
		* @access private
		*/
		private function _poll_transfers() {
			require_once('CNic/Toolkit/Response/PollTransfers.php');
			$url = $this->_base().'poll_transfers';
			$params = array(
				'user'		=> $this->user,
				'password'	=> $this->_crypt_md5($this->password),
				'test'		=> $this->params['test'],
			);
			$this->_raw_data = $this->_request($url, 'POST', $params);
			return new CNic_Toolkit_Response_PollTransfers($this->_raw_data);
		}

		/**
		* @access private
		*/
		private function _approve_transfer() {
			require_once('CNic/Toolkit/Response/ApproveTransfer.php');
			$url = $this->_base().'approve_transfer';
			$params = array(
				'domain'	=> $this->params['domain'],
				'user'		=> $this->user,
				'password'	=> $this->_crypt_md5($this->password),
				'test'		=> $this->params['test']
			);
			$this->_raw_data = $this->_request($url, 'POST', $params);
			return new CNic_Toolkit_Response_ApproveTransfer($this->_raw_data);
		}

		/**
		* @access private
		*/
		private function _reject_transfer() {
			require_once('CNic/Toolkit/Response/RejectTransfer.php');
			$url = $this->_base().'reject_transfer';
			$params = array(
				'domain'	=> $this->params['domain'],
				'user'		=> $this->user,
				'password'	=> $this->_crypt_md5($this->password),
				'test'		=> $this->params['test']
			);
			$this->_raw_data = $this->_request($url, 'POST', $params);
			return new CNic_Toolkit_Response_RejectTransfer($this->_raw_data);
		}

		/**
		* @access private
		*/
		private function _list_outstanding_domains() {
			require_once('CNic/Toolkit/Response/ListOutstandingDomains.php');
			$url = $this->_base().'list_outstanding_domains';
			$params = array(
				'user'		=> $this->user,
				'password'	=> $this->_crypt_md5($this->password),
				'test'		=> $this->params['test'],
			);
			$this->_raw_data = $this->_request($url, 'POST', $params);
			return new CNic_Toolkit_Response_ListOutstandingDomains($this->_raw_data);
		}

		/**
		* @access private
		*/
		private function _submit_payment_batch() {
			require_once('CNic/Toolkit/Response/SubmitPaymentBatch.php');
			$url = $this->_base().'submit_payment_batch';
			$params = array(
				'user'		=> $this->user,
				'password'	=> $this->_crypt_md5($this->password),
				'test'		=> $this->params['test'],
				'method'	=> $this->params['method'],
			);
			$i = 0;
			for ($i = 0 ; $i < count($this->params['domains']) ; $i++) $params["domains[$i]"] = $this->params['domains'][$i];
			$this->_raw_data = $this->_request($url, 'POST', $params);
			return new CNic_Toolkit_Response_SubmitPaymentBatch($this->_raw_data);
		}


		/**
		* @access private
		*/
		private function _registrant_transfer() {
			require_once('CNic/Toolkit/Response/RegistrantTransfer.php');
			$url = $this->_base().'registrant_transfer';
			$params = array(
				'user'		=> $this->user,
				'password'  => $this->_crypt_md5($this->password),
				'test'		=> $this->params['test'],
				'domain'	=> $this->params['domain'],
				'registrant' => $this->params['registrant']
			);
			foreach ($this->params as $name => $value) $params[$name] = $value;
			$this->_raw_data = $this->_request($url, 'POST', $params);
			return new CNic_Toolkit_Response_RegistrantTransfer($this->_raw_data);
		}

		/**
		* @access private
		*/
		private function _lock_domain() {
			require_once('CNic/Toolkit/Response/LockDomain.php');
			$url = $this->_base().'lock_domain';
			$params = array(
				'domain'	=> $this->params['domain'],
				'user'		=> $this->user,
				'password'	=> $this->_crypt_md5($this->password),
				'test'		=> $this->params['test']
			);
			$this->_raw_data = $this->_request($url, 'POST', $params);
			return new CNic_Toolkit_Response_LockDomain($this->_raw_data);
		}

		/**
		* @access private
		*/
		private function _unlock_domain() {
			require_once('CNic/Toolkit/Response/UnlockDomain.php');
			$url = $this->_base().'unlock_domain';
			$params = array(
				'domain'	=> $this->params['domain'],
				'user'		=> $this->user,
				'password'	=> $this->_crypt_md5($this->password),
				'test'		=> $this->params['test']
			);
			$this->_raw_data = $this->_request($url, 'POST', $params);
			return new CNic_Toolkit_Response_UnlockDomain($this->_raw_data);
		}

		/**
		* @access private
		*/
		private function _validate_domain() {
			require_once('CNic/Toolkit/Response/ValidateDomain.php');
			$url = $this->_base().'validate_domain';
			$params = array(
				'domain'	=> $this->params['domain'],
				'user'		=> $this->user,
				'password'	=> $this->_crypt_md5($this->password),
				'test'		=> $this->params['test']
			);
			$this->_raw_data = $this->_request($url, 'POST', $params);
			return new CNic_Toolkit_Response_ValidateDomain($this->_raw_data);
		}


		/***********************************************************************************************************************************/

		/**
		* @access private
		*/
		private function _request() {
			$no_args = func_num_args();
			switch ($no_args) {
				case 1:
					$url = func_get_arg(0);
					$method = 'GET';
					$params = array();
					break;

				case 2:
					list($url, $params) = func_get_args();
					$method = 'GET';
					break;

				case 3:
					list($url, $method, $params) = func_get_args();
					break;

				default:
					trigger_error('invalid number of arguments to CNic_Toolkit::_get()', E_USER_ERROR);
			}

			if ($method == 'POST') curl_setopt($this->agent, CURLOPT_POST, 1);
			// this makes curl return the content when curl_exec() is done:
			curl_setopt($this->agent, CURLOPT_RETURNTRANSFER, 1);

			$post_vars = array();
			if (count($params) > 0) {
				foreach ($params as $name => $value) {
					if (in_array(gettype($value), array('array', 'object', 'resource'))) trigger_error(sprintf("cannot use value of type %s as value for '%s', must be a string", gettype($value), $name), E_USER_ERROR);
					$post_vars[] = sprintf('%s=%s', urlencode(strtolower($name)), urlencode($value));
				}
			}

			if ($method == 'POST' && count($params) > 0) {
				curl_setopt($this->agent, CURLOPT_POSTFIELDS, implode('&', $post_vars));

			} elseif (count($params) > 0) {
				$url .= '?'.implode('&', $post_vars);

			}

			curl_setopt($this->agent, CURLOPT_URL, $url);
			$response = stripslashes(curl_exec($this->agent));
			if (empty($response)) {
				return sprintf("Status: 1\nMessage: %s (CURL error %d)\n",
					curl_error($this->agent),
					curl_errno($this->agent)
				);

			} else {
				return $response;

			}
		}

		/**
		* @access private
		*/
		private function _format_handle_string($handle) {
			if (is_string($handle)) {
				return $handle;

			} elseif (!is_array($handle)) {
				trigger_error('invalid variable type passed to CNic_Toolkit::_format_handle_string()', E_USER_ERROR);

			} else {
				return 'new::' . implode('::', array_values($handle));

			}
		}

		/**
		* @access private
		*/
		private function _dns_register_params($dns) {
            if (!is_array($dns)) {
				trigger_error('invalid variable type passed to CNic_Toolkit::_dns_register_params()', E_USER_ERROR);

			} else {
				$array = array();
				$i = 0;
				foreach ($dns as $key => $value) {
					$array["dns[$i]"] = (is_int($key) ? $value : sprintf('%s::%s', $key, $value));
					$i++;
				}
				return $array;
			}
		}

		/**
		* @access private
		*/
		private function _dns_mod_params($dns) {
			if (!is_array($dns)) {
				trigger_error('invalid variable type passed to CNic_Toolkit::_dns_mod_params()', E_USER_ERROR);

			} else {
				$array = array();
				$i = 0;
				if (@count($dns['add']) > 0) {
					foreach ($dns['add'] as $server) {
						if (@count($server) == 2) {
							$array["dns[$i]"] = sprintf('add:%s:%s', $server[0], $server[1]);
							$i++;

						} elseif (!empty($server)) {
							$array["dns[$i]"] = 'add:'.$server;
							$i++;

						}
					}
				}
				if ($dns['drop'] == 'all') {
					$array["dns[$i]"] = 'drop:all';
					$i++;

				} elseif (@count($dns['drop']) > 0) {
					foreach ($dns['drop'] as $server) {
						$array["dns[$i]"] = 'drop:'.$server;
						$i++;
					}
				}

				return $array;
			}
		}

		/**
		* @access private
		*/
		private function _base()
		{
			if(in_array($this->command, array('whois', 'search', 'suffixes')))
			{
				return 'http://' . $this->HOSTNAME.'/srv/';
			}
			else
			{
				return 'https://' . $this->HOSTNAME.'/srv/';
			}
		}

		/**
		* this function forces the creation of an MD5-crypted hash.
		* This is because MD5 has better support than other
		* ciphers on different platforms, so we know that
		* password hashes will use a cipher the server can understand
		* @access private
		*/
		private function _crypt_md5($str) {
			$salt = '$1$' . substr(md5(uniqid(rand(), 1)), 0, 8) . '$';
			$crypted = crypt($str, $salt);
			return $crypted;
		}

	}

?>
