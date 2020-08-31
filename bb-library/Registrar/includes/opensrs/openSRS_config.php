<?php
// Paths
// Setting default path to the same directory this file is in
 define ("OPENSRSURI", dirname(__FILE__) . "/");

// Application core configurations.
define ("OPENSRSCONFINGS", "configurations/");
define ("OPENSRSDOMAINS", "domains/");
define ("OPENSRSMAIL", "mail/");
define ("OPENSRSFASTLOOKUP", "fastlookup/");

// Active Config file is file that points to the OpenSRS file holding connection information
define ("ACTIVECONFIG", OPENSRSURI . OPENSRSCONFINGS ."activeConfig.xml");
