<?php
	// CNic_Toolkit_Response_Renewals - response object for renewals list
	// Copyright (c) 2011 CentralNic Ltd. All rights reserved. This program is
	// free software; you can redistribute it and/or modify it under the terms
	// of the GNU GPL
	// $Id: Renewals.php,v 1.8 2011/05/13 13:21:26 gavin Exp $

	class CNic_Toolkit_Response_Renewals extends CNic_Toolkit_Response {

		function domains() {
			return $this->keys();
		}

		function amount($domain) {
			list($amount, $expiry) = $this->response($domain);
			return $amount;
		}

		function expiry($domain) {
			list($amount, $expiry) = $this->response($domain);
			return $expiry;
		}
	}

?>