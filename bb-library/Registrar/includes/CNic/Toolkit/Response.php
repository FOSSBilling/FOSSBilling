<?php
	// CNic_Toolkit_Response - base class for response objects
	// Copyright (c) 2011 CentralNic Ltd. All rights reserved. This program is
	// free software; you can redistribute it and/or modify it under the terms
	// of the GNU GPL
	// $Id: Response.php,v 1.9 2011/05/13 13:21:26 gavin Exp $

	class CNic_Toolkit_Response {

		var $_response = array();

		function CNic_Toolkit_Response($raw_data) {
			$this->_raw = $raw_data;
			$lines = explode("\n", trim($raw_data));

			foreach ($lines as $line) {
				$line = trim($line);
                if (preg_match('/:\s?/', $line))
                    list($name, $value) = preg_split('/:\s?/', $line, 2);
                else
                    $name = $value = $line;
				if (preg_match('/(::|=)/', $value)) {
					if (preg_match('/=/', $value)) {
						$pairs = explode('::', $value);
						foreach ($pairs as $pair) {
							list($n, $v) = explode('=', $pair, 2);
							$v = preg_replace('/^\"?/', '', $v);
							$v = preg_replace('/\"?$/', '', $v);
							$values[$n] = $v;
						}
						$this->_response[strtolower($name)][] = $values;
					} else {
						$this->_response[strtolower($name)][] = explode('::', $value);
					}
				} else {
					$this->_response[strtolower($name)][] = $value;
				}
			}
		}

		function is_success() {
			return ((isset($this->_response['query-status'][0]) ? $this->_response['query-status'][0] : $this->_response['status'][0]) == 0 ? true : false);
		}

		function is_error() {
			return ($this->is_success() ? false : true);
		}

		function error() {
			return $this->response('message');
		}

		function keys() {
			return array_keys($this->_response);
		}

		function response($key) {
			return $this->_expand($this->_response[$key]);						
		}

		function _expand($struct) {
			if (is_string($struct)) {
				return $struct;
			} elseif (is_array($struct)) {
				if (count($struct) == 1 && $this->is_assoc($struct)) {
					return $this->_expand($struct[0]);
				} else {
					return $struct;
				}
			} else {
				return false;
			}
		}

		// sort of a kludge to work out whether an array is associative or not
		// (since "indexed" arrays in PHP are just associative arrays with ordered
		// numerical keys). Walk through the keys in order and see if they're all
		// numerical and sequential. This is what you get for not using XML :p
		function is_assoc($array) {
			$keys = array_keys($array);
			for ($i = 0 ; $i < count($keys) ; $i++) {
				if ($keys[$i] !== $i) return false;
			}
			return true;
		}

	}

?>
