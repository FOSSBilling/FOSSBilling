<?php
	// CNic_Toolkit_Response_CheckTransfer - response object for checking the status of domain transfers
	// Copyright (c) 2011 CentralNic Ltd. All rights reserved. This program is
	// free software; you can redistribute it and/or modify it under the terms
	// of the GNU GPL
	// $Id: CheckTransfer.php,v 1.5 2011/05/13 13:21:26 gavin Exp $

	class CNic_Toolkit_Response_CheckTransfer extends CNic_Toolkit_Response {

		function status() {
			return $this->response('transfer-status');
		}

	}

?>
