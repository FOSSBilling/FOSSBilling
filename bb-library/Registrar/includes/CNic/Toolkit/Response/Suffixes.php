<?php
	// CNic_Toolkit_Response_Suffixes - response object for suffixes
	// Copyright (c) 2011 CentralNic Ltd. All rights reserved. This program is
	// free software; you can redistribute it and/or modify it under the terms
	// of the GNU GPL
	// $Id: Suffixes.php,v 1.8 2011/05/13 13:21:26 gavin Exp $

	class CNic_Toolkit_Response_Suffixes extends CNic_Toolkit_Response {

		function suffixes() {
			$suffixes = $this->keys();
            unset($suffixes[0]);
            unset($suffixes[1]);
			sort($suffixes, SORT_STRING);
			return $suffixes;
		}

	}

?>