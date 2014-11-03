<?php
	// CNic_Toolkit_Response_Search - response object for searches
	// Copyright (c) 2011 CentralNic Ltd. All rights reserved. This program is
	// free software; you can redistribute it and/or modify it under the terms
	// of the GNU GPL
	// $Id: Search.php,v 1.9 2011/05/13 13:21:26 gavin Exp $

	class CNic_Toolkit_Response_Search extends CNic_Toolkit_Response {

		function is_registered($suffix) {
			foreach ($this->keys() as $key) {
				if (preg_match("/$suffix$/i", $key)) {
					$data = $this->response($key);
					return ($data[0] == 1 ? true : false);
				}
			}
			return false;
		}

		function registrant($suffix) {
			foreach ($this->keys() as $key) {
				if (preg_match("/$suffix$/i", $key)) {
					$data = $this->response($key);
					return $data[1];
				}
			}
			return false;
		}

		function expiry($suffix) {
			foreach ($this->keys() as $key) {
				if (preg_match("/$suffix$/i", $key)) {
					$data = $this->response($key);
					return $data[2];
				}
			}
			return false;
		}

	}

?>