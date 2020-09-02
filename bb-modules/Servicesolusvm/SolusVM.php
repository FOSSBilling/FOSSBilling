<?php

namespace Box\Mod\Servicesolusvm;

class SolusVM implements \Box\InjectionAwareInterface{

    protected $api_host = null; // SolusVM Controlpanel URL
    protected $api_ID = ''; // API ID
    protected $api_key = ''; // API KEY

    protected $_parameters = array();

    /**
     * @var \Box_Di
     */
    protected $di;

    /**
     * @param \Box_Di $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return \Box_Di
     */
    public function getDi()
    {
        return $this->di;
    }


    /**
     * SolusVM Controlpanel URL
     * @return string
     */
    public function getApiHost()
    {
        return $this->api_host;
    }

    /**
     * @param string $api_host
     */
    public function setApiHost($api_host)
    {
        $this->api_host = $api_host;
    }

    /**
     * @return string
     */
    public function getApiID()
    {
        return $this->api_ID;
    }

    /**
     * @param string $api_ID
     */
    public function setApiID($api_ID)
    {
        $this->api_ID = $api_ID;
    }

    /**
     * @return string
     */
    public function getApiKey()
    {
        return $this->api_key;
    }

    /**
     * @param string $api_key
     */
    public function setApiKey($api_key)
    {
        $this->api_key = $api_key;
    }

    public function buildUrl(array $config)
    {
        return $config['protocol'] ."://". $config['ipaddress'] . ":" . $config['port'] . "/api/" . $config['usertype'] . "/command.php";
    }

    public function getSecureUrl(array $config)
    {
        $config['port'] = $this->di['array_get']($config, 'port', 5656);
        $config['protocol'] = 'https';
        return $this->buildUrl($config);
    }

    public function getUrl(array $config)
    {
        $config['port'] = $this->di['array_get']($config, 'port', 5353);
        $config['protocol'] = 'http';
        return $this->buildUrl($config);
    }

    public function setConfig(array $c)
    {
        $required = array(
            'id'        => 'API ID is missing',
            'key'       => 'API key is missing',
            'ipaddress' => 'API ip address is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $c);

        $c['usertype'] = $this->di['array_get']($c, 'usertype', 'admin');
        $c['secure']   = $this->di['array_get']($c, 'secure', false);
        $c['port']     = $this->di['array_get']($c, 'port', null);

        $url = $this->getUrl($c);

        if ($c['secure']) {
            $url = $this->getSecureUrl($c);
        }

        $this->setApiHost($url);
        $this->setApiID($c['id']);
        $this->setApiKey($c['key']);
    }

    /**
     * @param string $action
     */
    private function callAPI($action, $raw_response = false){

        $postfields = array_merge(
            array('id' => $this->api_ID),
            array('key' => $this->api_key),
            array('action'=>$action),
            $this->_parameters);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->api_host);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Expect:"));
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
        $return = curl_exec($ch);

        $curl_error = curl_error($ch);
        if($curl_error) {
            throw new \Exception($curl_error, 8755);
        }

        curl_close($ch);

        if(empty($return)){
            throw new \Exception('Empty response from SolusVM server.', 8766);
        }

        //used by client-list action
        if($raw_response) {
            return $return;
        }

        $result = $match = array();
        preg_match_all('/<(.*?)>([^<]+)<\/\\1>/i', $return, $match);
        foreach ($match[1] as $x => $y) {
            $result[$y] = $match[2][$x];
        }

        if($result['status'] != "success" && isset($result['statusmsg'])){
            throw new \Exception($result['statusmsg'], 8777);
        }

        return $result;
    }

    /*
        API FUNCTIONS SOLUSVM
    */

    /*
    Action ................ : vserver-create
    Method ................ : GET or POST
    Variables ............. : type             [openvz|xen|xen hvm|kvm]
                            node             [name of node]
                            nodegroup        [name of nodegroup]
                            hostname         [hostname of virtual server]
                            password         [root password]
                            username         [client username]
                            plan             [plan name]
                            template         [template or iso name]
                            ips              [amount of ips]
                            hvmt             [0|1] default is 0. This allows to to define templates & isos for Xen HVM
                            custommemory     [overide plan memory with this amount]
                            customdiskspace  [overide plan diskspace with this amount]
                            custombandwidth  [overide plan bandwidth with this amount]
                            customcpu        [overide plan cpu cores with this amount]
                            customextraip    [add this amount of extra ips]
                            issuelicense     [1|2] 1 = cPanel monthly 2= cPanel yearly


    Success Returns ....... : <status>success</status>
                            <statusmsg>Virtual server created</statusmsg>
                            <mainipaddress>123.123.123.123</mainipaddress>
                            <extraipaddress>122.122.122.122,111.111.111.111</extraipaddress>
                            <rootpassword>123456</rootpassword>
                            <vserverid>100</vserverid>
                            <consoleuser>console-123</consoleuser>
                            <consolepassword>123456</consolepassword>
                            <hostname>server.hostname.com</hostname>
                            <virtid>vm101|101</virtid>
                            <nodeid>1</nodeid>

    Failure Returns ....... : <status>error</status>
                            <statusmsg>error message</statusmsg>
    */
    public function vserver_create($type, $node, $nodegroup, $hostname, $password, $username, $plan, $template, $ips, $hvmt = '', $custommemory = '', $customdiskspace = '', $custombandwidth = '', $customcpu = '', $customextraip = '', $issuelicense = ''){

        $this->_parameters = array(
            'type' => $type,
            'node' => $node,
            'nodegroup' => $nodegroup,
            'hostname' => $hostname,
            'password' => $password,
            'username' => $username,
            'template' => $template,
            'plan' => $plan,
            'ips' => $ips,
            'hvmt' => $hvmt,
            'custommemory' => $custommemory,
            'customdiskspace' => $customdiskspace,
            'custombandwidth' => $custombandwidth,
            'customcpu' => $customcpu,
            'customextraip' => $customextraip,
            'issuelicense' => $issuelicense
        );
        return $this->callAPI("vserver-create");
    }

    public function vserver_checkexists($vserverid){

        $this->_parameters = array(
            'vserverid' => $vserverid
        );

        return $this->callAPI("vserver-checkexists");
    }

    public function client_checkexists($username){

        $this->_parameters = array(
            'username' => $username
        );

        return $this->callAPI("client-checkexists");

    }

    public function client_delete($username){

        $this->_parameters = array(
            'username' => $username
        );

        return $this->callAPI("client-delete");

    }

    public function vserver_status($vserverid){

        $this->_parameters = array(
            'vserverid' => $vserverid
        );

        return $this->callAPI("vserver-status");

    }

    public function client_create($username, $password, $email, $firstname, $lastname, $company = ''){

        $this->_parameters = array(
            'username' => $username,
            'password' => $password,
            'email' => $email,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'company' => $company
        );

        return $this->callAPI("client-create");

    }

    public function reseller_create($username, $password, $email, $firstname, $lastname, $company = '', $usernameprefix = '', $maxvps = '', $maxusers = '', $maxmem = '', $maxburst = '', $maxdisk = '', $maxbw = '', $maxipv4 = '', $maxipv6 = '', $nodegroup = '', $mediagroups = '', $openvz = '', $xenpv = '', $xenhvm = '', $kvm = ''){

        $this->_parameters = array(
            'username' => $username,
            'password' => $password,
            'email' => $email,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'company' => $company,
            'usernameprefix' => $usernameprefix,
            'maxvps' => $maxvps,
            'maxusers' => $maxusers,
            'maxmem' => $maxmem,
            'maxburst' => $maxburst,
            'maxdisk' => $maxdisk,
            'maxbw' => $maxbw,
            'maxipv4' => $maxipv4,
            'maxipv6' => $maxipv6,
            'nodegroup' => $nodegroup,
            'mediagroups' => $mediagroups,
            'openvz' => $openvz,
            'xenpv' => $xenpv,
            'xenhvm' => $xenhvm,
            'kvm' => $kvm
        );

        return $this->callAPI("reseller-create");

    }

    public function reseller_modifyresources($username, $maxvps = '', $maxusers = '', $maxmem = '', $maxburst = '', $maxdisk = '', $maxbw = '', $maxipv4 = '', $maxipv6 = '', $nodegroup = '', $mediagroups = '', $openvz = '', $xenpv = '', $xenhvm = '', $kvm = ''){

        $this->_parameters = array(
            'username' => $username,
            'maxvps' => $maxvps,
            'maxusers' => $maxusers,
            'maxmem' => $maxmem,
            'maxburst' => $maxburst,
            'maxdisk' => $maxdisk,
            'maxbw' => $maxbw,
            'maxipv4' => $maxipv4,
            'maxipv6' => $maxipv6,
            'nodegroup' => $nodegroup,
            'mediagroups' => $mediagroups,
            'openvz' => $openvz,
            'xenpv' => $xenpv,
            'xenhvm' => $xenhvm,
            'kvm' => $kvm
        );

        return $this->callAPI("reseller-modifyresources");

    }

    public function reseller_info($username){

        $this->_parameters = array(
            'username' => $username
        );

        return $this->callAPI("reseller-info-delete");

    }

    public function reseller_list(){
        return $this->callAPI("reseller-list");
    }

    public function reseller_delete($username){

        $this->_parameters = array(
            'username' => $username
        );

        return $this->callAPI("reseller-delete");

    }

    public function client_updatepassword($username, $password){
        $this->_parameters = array(
            'username' => $username,
            'password' => $password
        );
        return $this->callAPI("client-updatepassword");
    }

    public function vserver_addip($vserverid){
        $this->_parameters = array(
            'vserverid' => $vserverid
        );
        return $this->callAPI("vserver-addip");
    }

    public function vserver_changeowner($vserverid, $clientid){
        $this->_parameters = array(
            'vserverid' => $vserverid,
            'clientid' => $clientid
        );
        return $this->callAPI("vserver-changeowner");
    }

    public function vserver_reboot($vserverid){
        $this->_parameters = array(
            'vserverid' => $vserverid
        );
        return $this->callAPI("vserver-reboot");
    }

    public function vserver_shutdown($vserverid){
        $this->_parameters = array(
            'vserverid' => $vserverid
        );
        return $this->callAPI("vserver-shutdown");
    }

    public function vserver_boot($vserverid){
        $this->_parameters = array(
            'vserverid' => $vserverid
        );
        return $this->callAPI("vserver-boot");
    }

    public function vserver_suspend($vserverid){

        $this->_parameters = array(
            'vserverid' => $vserverid
        );
        return $this->callAPI("vserver-suspend");
    }

    public function vserver_unsuspend($vserverid){

        $this->_parameters = array(
            'vserverid' => $vserverid
        );
        return $this->callAPI("vserver-unsuspend");
    }

    public function vserver_tun_enable($vserverid){
        $this->_parameters = array(
            'vserverid' => $vserverid
        );
        return $this->callAPI("vserver-tun-enable");
    }

    public function vserver_network_enable($vserverid){

        $this->_parameters = array(
            'vserverid' => $vserverid
        );
        return $this->callAPI("vserver-network-enable");
    }

    public function vserver_network_disable($vserverid){

        $this->_parameters = array(
            'vserverid' => $vserverid
        );
        return $this->callAPI("vserver-network-disable");
    }

    public function vserver_consolepass($vserverid, $consolepassword){
        $this->_parameters = array(
            'vserverid' => $vserverid,
            'consolepassword' => $consolepassword
        );
        return $this->callAPI("vserver-consolepass");
    }

    public function vserver_vncpass($vserverid, $vncpassword){

        $this->_parameters = array(
            'vserverid' => $vserverid,
            'vncpassword' => $vncpassword
        );
        return $this->callAPI("vserver-vncpass");
    }

    public function vserver_vnc($vserverid){

        $this->_parameters = array(
            'vserverid' => $vserverid
        );

        return $this->callAPI("vserver-vnc");

    }

    public function vserver_console($vserverid){

        $this->_parameters = array(
            'vserverid' => $vserverid
        );

        return $this->callAPI("vserver-console");

    }

    public function vserver_tun_disable($vserverid){
        $this->_parameters = array(
            'vserverid' => $vserverid
        );
        return $this->callAPI("vserver-tun-disable");
    }

    public function vserver_mountiso($vserverid, $iso){
        $this->_parameters = array(
            'vserverid' => $vserverid,
            'iso' => $iso
        );
        return $this->callAPI("vserver-mountiso");
    }

    public function vserver_unmountiso($vserverid){
        $this->_parameters = array(
            'vserverid' => $vserverid
        );
        return $this->callAPI("vserver-unmountiso");
    }

    /**
     * @param string $pae
     */
    public function vserver_pae($vserverid, $pae){
        $this->_parameters = array(
            'vserverid' => $vserverid,
            'pae' => $pae
        );
        return $this->callAPI("vserver-pae");
    }

    public function vserver_bootorder($vserverid, $bootorder){
        $this->_parameters = array(
            'vserverid' => $vserverid,
            'bootorder' => $bootorder
        );
        return $this->callAPI("vserver-bootorder");
    }

    public function vserver_terminate($vserverid, $deleteclient = false){
        $this->_parameters = array(
            'vserverid' => $vserverid,
            'deleteclient' => $deleteclient
        );
        return $this->callAPI("vserver-terminate");
    }

    public function vserver_rebuild($vserverid, $template){
        $this->_parameters = array(
            'vserverid' => $vserverid,
            'template' => $template
        );
        return $this->callAPI("vserver-rebuild");
    }

    public function vserver_change($vserverid, $plan){
        $this->_parameters = array(
            'vserverid' => $vserverid,
            'plan' => $plan
        );
        return $this->callAPI("vserver-change");
    }

    public function vserver_rootpassword($vserverid, $rootpassword){
        $this->_parameters = array(
            'vserverid' => $vserverid,
            'rootpassword' => $rootpassword
        );
        return $this->callAPI("vserver-rootpassword");
    }

    public function vserver_hostname($vserverid, $hostname){
        $this->_parameters = array(
            'vserverid' => $vserverid,
            'hostname' => $hostname
        );
        return $this->callAPI("vserver-hostname");
    }

    public function listnodes($type){
        $this->_parameters = array(
            'type' => $type
        );
        return $this->callAPI("listnodes");
    }

    public function node_idlist($type){
        $this->_parameters = array(
            'type' => $type
        );
        return $this->callAPI("node-idlist");
    }

    public function listnodegroups(){
        return $this->callAPI("listnodegroups");
    }

    public function client_list(){
        $xml = $this->callAPI("client-list", true);
        $a = json_decode(json_encode((array) simplexml_load_string($xml)),1);
        return $a['client'];
    }

    /**
     * @param string $type
     */
    public function listplans($type){
        $this->_parameters = array(
            'type' => $type
        );
        return $this->callAPI("listplans");
    }

    public function listtemplates($type){
        $this->_parameters = array(
            'type' => $type
        );
        return $this->callAPI("listtemplates");
    }

    public function listiso($type){
        $this->_parameters = array(
            'type' => $type
        );
        return $this->callAPI("listiso");
    }

    public function node_iplist($nodeid){
        $this->_parameters = array(
            'nodeid' => $nodeid
        );
        return $this->callAPI("node-iplist");
    }

    public function node_virtualservers($nodeid){
        $this->_parameters = array(
            'nodeid' => $nodeid
        );
        $xml = $this->callAPI("node-virtualservers", true);
        $a = json_decode(json_encode((array) simplexml_load_string($xml)),1);

        //one server
        if(isset($a['virtualserver']['vserverid'])) {
            return array($a['virtualserver']);
        } else {
            return $a['virtualserver'];
        }
    }

    public function vserver_info($vserverid){
        $this->_parameters = array(
            'vserver-info' => $vserverid
        );
        return $this->callAPI("vserver-info");
    }

    public function vserver_infoall($vserverid){
        $this->_parameters = array(
            'vserverid' => $vserverid
        );
        return $this->callAPI("vserver-infoall");
    }

    public function node_xenresources($nodeid){
        $this->_parameters = array(
            'nodeid' => $nodeid
        );
        return $this->callAPI("node-xenresources");
    }

    public function node_statistics($nodeid){
        $this->_parameters = array(
            'nodeid' => $nodeid
        );
        return $this->callAPI("node-statistics");
    }
}