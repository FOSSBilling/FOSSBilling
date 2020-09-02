<?php
	// CNic_Toolkit_Response_PollTransfers - response object for polling domain transfers
	// Copyright (c) 2011 CentralNic Ltd. All rights reserved. This program is
	// free software; you can redistribute it and/or modify it under the terms
	// of the GNU GPL
	// $Id: PollTransfers.php,v 1.4 2011/05/13 13:21:26 gavin Exp $

	class CNic_Toolkit_Response_PollTransfers extends CNic_Toolkit_Response {

		function transfers() {
			$transfers = array();
			foreach (preg_grep('/^domain=/', $this->keys()) as $key) {
				$info  = $this->response($key);
				$info['domain'] = preg_replace('/^domain=/', '', $key);
				$info['type'] = $info[':type'];
				unset($info[':type']);
				$transfers[] = $info;
			}
			return $transfers;
		}
	}

?>
