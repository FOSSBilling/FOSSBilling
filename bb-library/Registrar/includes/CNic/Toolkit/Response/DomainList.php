<?php
	// CNic_Toolkit_Response_DomainList - response object for domain lists
	// Copyright (c) 2011 CentralNic Ltd. All rights reserved. This program is
	// free software; you can redistribute it and/or modify it under the terms
	// of the GNU GPL
	// $Id: DomainList.php,v 1.8 2011/05/13 13:21:26 gavin Exp $

	class CNic_Toolkit_Response_DomainList extends CNic_Toolkit_Response {

		function domains() {
			return preg_grep('/\..{3}$/i', $this->keys());
		}

		function regdate($domain) {
			list($status, $regdate, $expirydate) = $this->response($domain);
			return $regdate;
		}

		function expirydate($domain) {
			list($status, $regdate, $expirydate) = $this->response($domain);
			return $expirydate;
		}

		function status($domain) {
			list($status, $regdate, $expirydate) = $this->response($domain);
			return $status;
		}
	}

?>