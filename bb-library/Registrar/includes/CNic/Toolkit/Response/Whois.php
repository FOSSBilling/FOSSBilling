<?php
	// CNic_Toolkit_Response_Whois - response object for whois requests
	// Copyright (c) 2011 CentralNic Ltd. All rights reserved. This program is
	// free software; you can redistribute it and/or modify it under the terms
	// of the GNU GPL
	// $Id: Whois.php,v 1.10 2011/05/13 13:21:26 gavin Exp $

	class CNic_Toolkit_Response_Whois extends CNic_Toolkit_Response {

		function response($key) {
			// hack, cos we want an array of arrays, so don't flatten:
			if (strtolower($key) == 'dns') {
				return $this->_response['dns'];
			} else {
				return $this->_expand($this->_response[$key]);
			}
		}

	}

?>
