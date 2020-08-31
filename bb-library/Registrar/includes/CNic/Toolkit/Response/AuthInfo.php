<?php
	// CNic_Toolkit_Response_AuthInfo - response object for transfer codes
	// Copyright (c) 2011 CentralNic Ltd. All rights reserved. This program is
	// free software; you can redistribute it and/or modify it under the terms
	// of the GNU GPL
	// $Id: AuthInfo.php,v 1.3 2011/05/13 13:21:26 gavin Exp $

	class CNic_Toolkit_Response_AuthInfo extends CNic_Toolkit_Response {

		function auth_code() {
			return $this->response('authcode');
		}

	}

?>
