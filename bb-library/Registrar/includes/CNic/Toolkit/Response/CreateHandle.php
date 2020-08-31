<?php
	// CNic_Toolkit_Response_CreateHandle - response object for creating handles
	// Copyright (c) 2011 CentralNic Ltd. All rights reserved. This program is
	// free software; you can redistribute it and/or modify it under the terms
	// of the GNU GPL
	// $Id: CreateHandle.php,v 1.7 2011/05/13 13:21:26 gavin Exp $

	class CNic_Toolkit_Response_CreateHandle extends CNic_Toolkit_Response {

		function handle() {
			return $this->response('handle');
		}

	}

?>
