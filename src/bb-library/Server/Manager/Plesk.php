<?php
#!/usr/bin/env php
// Copyright 1999-2016. Parallels IP Holdings GmbH. All Rights Reserved.
require_once('PleskApiClient.php');
$host = getenv('REMOTE_HOST');
$login = getenv('REMOTE_LOGIN') ?: 'admin';
$password = getenv('REMOTE_PASSWORD');
$client = new Server_Manager_Plesk($host);
$client->setCredentials($login, $password);
$request = <<<EOF
<packet>
  <server>
    <get_protos/>
  </server>
</packet>
EOF;
$response = $client->request($request);
echo $response;
?>