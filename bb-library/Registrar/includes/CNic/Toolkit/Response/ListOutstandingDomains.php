<?php
	// CNic_Toolkit_Response_ListOutstandingDomains - response object listing outstanding domains
	// Copyright (c) 2011 CentralNic Ltd. All rights reserved. This program is
	// free software; you can redistribute it and/or modify it under the terms
	// of the GNU GPL
	// $Id: ListOutstandingDomains.php,v 1.4 2011/05/13 13:21:26 gavin Exp $

	class CNic_Toolkit_Response_ListOutstandingDomains extends CNic_Toolkit_Response {

		function domains() {
			$domains = array();
			foreach ($this->keys() as $domain) {
				$info = $this->response($domain);
				if (is_array($info)) {
					$info['domain'] = $domain;
					$domains[] = $info;
				}
			}
			return $domains;
		}

	}

?>
