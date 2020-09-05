<?php
	// CNic_Toolkit_Response_Whois - response object for whois requests
	// Copyright (c) 2011 CentralNic Ltd. All rights reserved. This program is
	// free software; you can redistribute it and/or modify it under the terms
	// of the GNU GPL
	// $Id: IssueRenewals.php,v 1.8 2011/05/13 13:21:26 gavin Exp $

	class CNic_Toolkit_Response_IssueRenewals extends CNic_Toolkit_Response {

		function invoice() {
			return $this->response('invoice');
		}

		function proforma() {
			return $this->response('proforma');
		}

		function amount() {
			return $this->response('amount');
		}

	}

?>