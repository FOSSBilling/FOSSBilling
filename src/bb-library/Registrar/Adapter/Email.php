<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (http://www.boxbilling.com)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

class Registrar_Adapter_Email extends Registrar_AdapterAbstract
{
    protected $config;
    
    public function __construct($options)
    {
        if(isset($options['email']) && !empty($options['email'])) {
            $this->config['email'] = $options['email'];
            unset($options['email']);
        } else {
            throw new Registrar_Exception('Email Registrar config requires param "email"');
        }

        if(isset($options['use_whois'])) {
            $this->config['use_whois'] = (bool)$options['use_whois'];
        } else {
            $this->config['use_whois'] = false;
        }
        
        $this->config['from'] = $this->config['email'];
    }
    
    public static function getConfig()
    {
        return array(
            'label'     =>  'This registrar type sends notifications to the given email about domain management events. For example, when client registers a new domain an email with domain details will be sent to you. It is then your responsibility to register domain on real registrar.',
            'form'  => array(
                'email' => array('text', array(
                            'label' => 'Email address', 
                            'description'=>'Email to send domain change notifications'
                    ),
                 ),
                'use_whois' => array('radio', array(
                            'multiOptions' => array('1'=>'Yes', '0'=>'No'),
                            'label' => 'Use WHOIS to check for domain availability',
                    ),
                 ),
            ),
        );
    }
    
    public function getTlds()
    {
        return array();
    }

    public function isDomainAvailable(Registrar_Domain $domain)
    {
        $this->getLog()->debug('Checking domain availability: ' . $domain->getName());

        if($this->config['use_whois']) {
            $w = new Whois2($domain->getName());
            return $w->isAvailable();
        }
        throw new Registrar_Exception('Email registrar can not determine whether domain is available');
    }

    public function isDomainCanBeTransfered(Registrar_Domain $domain)
    {
        throw new Registrar_Exception('Email registrar can not determine whether domain can be transferred');
    }

    public function modifyNs(Registrar_Domain $domain)
    {
        $params =array();
        $params['subject'] = 'Modify Name Servers';
        $params['content'] = 'A request to change domain nameservers has been received.';
        
        return $this->sendEmail($domain, $params);
    }

    public function transferDomain(Registrar_Domain $domain)
    {
        $params =array();
        $params['subject'] = 'Transfer domain';
        $params['content'] = 'A request to transfer domain has been received.';
        
        return $this->sendEmail($domain, $params);
    }

    public function getDomainDetails(Registrar_Domain $domain)
    {
        return $domain;
    }

    public function deleteDomain(Registrar_Domain $domain)
    {
        $params =array();
        $params['subject'] = 'Delete domain';
        $params['content'] = 'A request to delete domain has been received.';
        
        return $this->sendEmail($domain, $params);
    }

    public function registerDomain(Registrar_Domain $domain)
    {
        $params =array();
        $params['subject'] = 'Register domain';
        $params['content'] = 'A request to register domain has been received.';
        
        return $this->sendEmail($domain, $params);
    }

    public function renewDomain(Registrar_Domain $domain)
    {
        $params =array();
        $params['subject'] = 'Renew domain';
        $params['content'] = 'A request to renew domain has been received.';
        
        return $this->sendEmail($domain, $params);
    }

    public function modifyContact(Registrar_Domain $domain)
    {
        $params =array();
        $params['subject'] = 'Modify Domain Contact';
        $params['content'] = 'A request to update domain contacts details has been received.';
        
        return $this->sendEmail($domain, $params);
    }
    
    public function enablePrivacyProtection(Registrar_Domain $domain)
    {
        $params =array();
        $params['subject'] = 'Turn On Domain privacy protection';
        $params['content'] = 'A request to change domain privacy protection has been received.';
        
        return $this->sendEmail($domain, $params);
    }

    public function disablePrivacyProtection(Registrar_Domain $domain)
    {
        $params =array();
        $params['subject'] = 'Turn Off Domain privacy protection';
        $params['content'] = 'A request to change domain privacy protection has been received.';

        return $this->sendEmail($domain, $params);
    }

    public function getEpp(Registrar_Domain $domain)
    {
        $params =array();
        $params['subject'] = 'Request for Epp code was received';
        $params['content'] = 'A request for Domain Transfer code was received.';

        return $this->sendEmail($domain, $params);
    }

    public function lock(Registrar_Domain $domain)
    {
        $params =array();
        $params['subject'] = 'Request to lock domain received';
        $params['content'] = 'A request to lock domain was received.';

        return $this->sendEmail($domain, $params);
    }

    public function unlock(Registrar_Domain $domain)
    {
        $params =array();
        $params['subject'] = 'Request to unlock domain received';
        $params['content'] = 'A request to unlock domain was received.';

        return $this->sendEmail($domain, $params);
    }

    private function sendEmail(Registrar_Domain $domain, array $params)
    {
        $c = $params['content'];
        $c .= PHP_EOL;
        $c .= PHP_EOL;
        $c .= 'Domain should be configured as follows:';
        $c .= PHP_EOL;
        $c .= PHP_EOL;
        $c .= $domain->__toString();
        
        $log = $this->getLog();
        if($this->_testMode) {
            $log->alert($params['subject'].PHP_EOL.PHP_EOL.$c);
            return true;
        }

        mail($this->config['email'], $params['subject'], $c);
        $log->info("Email sent: ".$params['subject']);
        return true;
    }
}

class Whois2 {

    public $domain="";
    protected $idn=array(224,225,226,227,228,229,230,231,232,233,234,235,240,236,237,238,239,241,242,243,244,245,246,248,254,249,250,251,252,253,255);

    public static function getServers()
    {
        /*******************************
        * Initializing server variables
        * array(top level domain,whois_Server,not_found_string or MAX number of CHARS: MAXCHARS:n)
        **/
        $servers=array(
                  array("ac","whois.nic.ac","Available"),
                  array("ac.cn","whois.cnnic.net.cn","no matching record"),
                  array("ac.jp","whois.nic.ad.jp","No match"),
                  array("ac.uk","whois.ja.net","No such domain"),
                  array("ad.jp","whois.nic.ad.jp","No match"),
                  array("adm.br","whois.nic.br","No match"),
                  array("adv.br","whois.nic.br","No match"),
                  array("aero","whois.information.aero","is available"),
                  array("ag","whois.nic.ag","Not found"),
                  array("agr.br","whois.nic.br","No match"),
                  array("ah.cn","whois.cnnic.net.cn","No entries found"),
                  array("al","whois.ripe.net","No entries found"),
                  array("am","whois.amnic.net","No match"),
                  array("am.br","whois.nic.br","No match"),
                  array("arq.br","whois.nic.br","No match"),
                  array("at","whois.nic.at","nothing found"),
                  array("au","whois.aunic.net","No Data Found"),
                  array("art.br","whois.nic.br","No match"),
                  array("as","whois.nic.as","Domain Not Found"),
                array("asia","whois.nic.asia","NOT FOUND"),
                  array("asn.au","whois.aunic.net","No Data Found"),
                  array("ato.br","whois.nic.br","No match"),
                  array("av.tr","whois.nic.tr","Not found in database"),
                  array("az","whois.ripe.net","no entries found"),
                  array("ba","whois.ripe.net","No match for"),
                  array("be","whois.geektools.com","FREE"),
                  array("bg","whois.digsys.bg","does not exist"),
                  array("bio.br","whois.nic.br","No match"),
                array("biz","whois.biz","Not found"),
                  array("biz.tr","whois.nic.tr","Not found in database"),
                  array("bj.cn","whois.cnnic.net.cn","No entries found"),
                  array("bel.tr","whois.nic.tr","Not found in database"),
                  array("bmd.br","whois.nic.br","No match"),
                  array("br","whois.registro.br","No match"),
                  array("by","whois.ripe.net","no entries found"),
                  array("ca","whois.cira.ca","Status: AVAIL"),
                array("cc","whois.nic.cc","No match"),
                  array("cd","whois.cd","No match"),
                  array("ch","whois.nic.ch","We do not have an entry"),
                  array("cim.br","whois.nic.br","No match"),
                  array("ck","whois.ck-nic.org.ck","No entries found"),
                  array("cl","whois.nic.cl","no existe"),
                  array("cn","whois.cnnic.net.cn","No entries found"),
                  array("cng.br","whois.nic.br","No match"),
                  array("cnt.br","whois.nic.br","No match"),
                array("com","whois.crsnic.net","No match"),
                  array("com.au","whois.aunic.net","No Data Found"),
                  array("com.br","whois.nic.br","No match"),
                  array("com.cn","whois.cnnic.net.cn","No entries found"),
                  array("com.eg","whois.ripe.net","No entries found"),
                  array("com.hk","whois.hknic.net.hk","No Match for"),
                  array("com.mx","whois.nic.mx","Nombre del Dominio"),
                  array("com.tr","whois.nic.tr","Not found in database"),
                  array("com.ru","whois.ripn.ru","No entries found"),
                  array("com.tw","whois.twnic.net","NO MATCH TIP"),
                  array("conf.au","whois.aunic.net","No entries found"),
                  array("co.at","whois.nic.at","nothing found"),
                  array("co.jp","whois.nic.ad.jp","No match"),
                array("co.uk","whois.nic.uk","No match for"),
                  array("co.in","whois.iisc.ernet.in","No match for"),
                  array("co.za","whois.coza.net.za","Available"),
                  array("cq.cn","whois.cnnic.net.cn","No entries found"),
                  array("csiro.au","whois.aunic.net","No Data Found"),
                  array("cx","whois.nic.cx","No match"),
                  array("cy","whois.ripe.net","no entries found"),
                  array("cz","whois.nic.cz","No data found"),
                  array("de","whois.denic.de","not found"),
                  array("dr.tr","whois.nic.tr","Not found in database"),
                  array("dk","whois.dk-hostmaster.dk","No entries found"),
                  array("dz","whois.ripe.net","no entries found"),
                  array("ecn.br","whois.nic.br","No match"),
                  array("ee","whois.eenet.ee","NOT FOUND"),
                  array("edu","whois.crsnic.net","No match"),
                  array("edu.au","whois.aunic.net","No Data Found"),
                  array("edu.br","whois.nic.br","No match"),
                  array("edu.tr","whois.nic.tr","Not found in database"),
                  array("eg","whois.ripe.net","No entries found"),
                  array("es","whois.ripe.net","No entries found"),
                  array("esp.br","whois.nic.br","No match"),
                  array("etc.br","whois.nic.br","No match"),
                  array("eti.br","whois.nic.br","No match"),
                  array("eun.eg","whois.ripe.net","No entries found"),
                  array("emu.id.au","whois.aunic.net","No Data Found"),
                  array("eng.br","whois.nic.br","No match"),
                array("eu","whois.eu","Status: AVAILABLE"),
                  array("far.br","whois.nic.br","No match"),
                  array("fi","whois.ripe.net","No entries found"),
                  array("fj","whois.usp.ac.fj",""),
                  array("fj.cn","whois.cnnic.net.cn","No entries found"),
                  array("fm.br","whois.nic.br","No match"),
                  array("fnd.br","whois.nic.br","No match"),
                  array("fo","whois.ripe.net","no entries found"),
                  array("fot.br","whois.nic.br","No match"),
                  array("fst.br","whois.nic.br","No match"),
                  array("fr","whois.nic.fr","No entries found"),
                  array("gb","whois.ripe.net","No match for"),
                  array("gb.com","whois.nomination.net","No match for"),
                  array("gb.net","whois.nomination.net","No match for"),
                  array("g12.br","whois.nic.br","No match"),
                  array("gd.cn","whois.cnnic.net.cn","No entries found"),
                  array("ge","whois.ripe.net","no entries found"),
                  array("gen.tr","whois.nic.tr","Not found in database"),
                  array("ggf.br","whois.nic.br","No match"),
                  array("gl","whois.ripe.net","no entries found"),
                  array("gr","whois.ripe.net","no entries found"),
                  array("gr.jp","whois.nic.ad.jp","No match"),
                  array("gs","whois.adamsnames.tc","is not registered"),
                  array("gs.cn","whois.cnnic.net.cn","No entries found"),
                  array("gov.au","whois.aunic.net","No Data Found"),
                  array("gov.br","whois.nic.br","No match"),
                  array("gov.cn","whois.cnnic.net.cn","No entries found"),
                  array("gov.hk","whois.hknic.net.hk","No Match for"),
                  array("gov.tr","whois.nic.tr","Not found in database"),
                  array("gob.mx","whois.nic.mx","Nombre del Dominio"),
                  array("gs","whois.adamsnames.tc","is not registered"),
                  array("gz.cn","whois.cnnic.net.cn","No entries found"),
                  array("gx.cn","whois.cnnic.net.cn","No entries found"),
                  array("he.cn","whois.cnnic.net.cn","No entries found"),
                  array("ha.cn","whois.cnnic.net.cn","No entries found"),
                  array("hb.cn","whois.cnnic.net.cn","No entries found"),
                  array("hi.cn","whois.cnnic.net.cn","No entries found"),
                  array("hl.cn","whois.cnnic.net.cn","No entries found"),
                  array("hn.cn","whois.cnnic.net.cn","No entries found"),
                  array("hm","whois.registry.hm","(null)"),
                  array("hk","whois.hknic.net.hk","No Match for"),
                  array("hk.cn","whois.cnnic.net.cn","No entries found"),
                  array("hu","whois.ripe.net","MAXCHARS:500"),
                  array("id.au","whois.aunic.net","No Data Found"),
                  array("ac.id", "whois.magnet-id.com", "No match for domain"),
                  array("co.id", "whois.magnet-id.com", "No match for domain"),
                  array("net.id", "whois.magnet-id.com", "No match for domain"),
                  array("or.id", "whois.magnet-id.com", "No match for domain"),
                  array("web.id", "whois.magnet-id.com", "No match for domain"),
                  array("sch.id", "whois.magnet-id.com", "No match for domain"),
                  array("mil.id", "whois.magnet-id.com", "No match for domain"),
                  array("go.id", "whois.magnet-id.com", "No match for domain"),
                  array("ie","whois.domainregistry.ie","no match"),
                  array("ind.br","whois.nic.br","No match"),
                  array("imb.br","whois.nic.br","No match"),
                  array("inf.br","whois.nic.br","No match"),
                array("info","whois.afilias.info","Not found"),
                  array("info.au","whois.aunic.net","No Data Found"),
                  array("info.tr","whois.nic.tr","Not found in database"),
                  array("it","whois.nic.it","No entries found"),
                  array("idv.tw","whois.twnic.net","NO MATCH TIP"),
                array("in","whois.inregistry.net","NOT FOUND"),
                  array("int","whois.iana.org","not found"),
                  array("is","whois.isnic.is","No entries found"),
                  array("il","whois.isoc.org.il","No data was found"),
                  array("jl.cn","whois.cnnic.net.cn","No entries found"),
                  array("jor.br","whois.nic.br","No match"),
                  array("jp","whois.nic.ad.jp","No match"),
                  array("js.cn","whois.cnnic.net.cn","No entries found"),
                  array("jx.cn","whois.cnnic.net.cn","No entries found"),
                  array("k12.tr","whois.nic.tr","Not found in database"),
                  array("ke","whois.rg.net","No match for"),
                  array("kr","whois.krnic.net","is not registered"),
                  array("la","whois.nic.la","NO MATCH"),
                  array("lel.br","whois.nic.br","No match"),
                  array("li","whois.nic.ch","We do not have an entry"),
                  array("lk","whois.nic.lk","No domain registered"),
                  array("ln.cn","whois.cnnic.net.cn","No entries found"),
                  array("lt","whois.domreg.lt","Status: available"),
                  array("lu","whois.dns.lu","No entries found"),
                  array("lv","whois.ripe.net","no entries found"),
                  array("ltd.uk","whois.nic.uk","No match for"),
                  array("ma","whois.ripe.net","No entries found"),
                  array("mat.br","whois.nic.br","No match"),
                  array("mc","whois.ripe.net","No entries found"),
                  array("md","whois.ripe.net","No match for"),
                array("me.uk","whois.nic.uk","No match for"),
                  array("med.br","whois.nic.br","No match"),
                  array("mil","whois.nic.mil","No match"),
                  array("mil.br","whois.nic.br","No match"),
                  array("mil.tr","whois.nic.tr","Not found in database"),
                  array("mk","whois.ripe.net","No match for"),
                  array("mn","whois.nic.mn","Domain not found"),
                array("mobi","whois.dotmobiregistry.net","NOT FOUND"),
                  array("mo.cn","whois.cnnic.net.cn","No entries found"),
                  array("ms","whois.adamsnames.tc","is not registered"),
                  array("mt","whois.ripe.net","No Entries found"),
                  array("mus.br","whois.nic.br","No match"),
                  array("mx","whois.nic.mx","Nombre del Dominio"),
                array("name","whois.nic.name","No match"),
                  array("name.tr","whois.nic.tr","Not found in database"),
                  array("ne.jp","whois.nic.ad.jp","No match"),
                array("net","whois.crsnic.net","No match"),
                  array("net.au","whois.aunic.net","No Data Found"),
                  array("net.br","whois.nic.br","No match"),
                  array("net.cn","whois.cnnic.net.cn","No entries found"),
                  array("net.eg","whois.ripe.net","No entries found"),
                  array("net.hk","whois.hknic.net.hk","No Match for"),
                  array("net.lu","whois.dns.lu","No entries found"),
                  array("net.mx","whois.nic.mx","Nombre del Dominio"),
                  array("net.uk","whois.nic.uk","No match for "),
                  array("net.ru","whois.ripn.ru","No entries found"),
                  array("net.tr","whois.nic.tr","Not found in database"),
                  array("net.tw","whois.twnic.net","NO MATCH TIP"),
                  array("nl","whois.domain-registry.nl","is free"),
                  array("nm.cn","whois.cnnic.net.cn","No entries found"),
                  array("no","whois.norid.no","no matches"),
                  array("no.com","whois.nomination.net","No match for"),
                  array("nom.br","whois.nic.br","No match"),
                  array("not.br","whois.nic.br","No match"),
                  array("ntr.br","whois.nic.br","No match"),
                  array("nu","whois.nic.nu","NO MATCH for"),
                  array("nx.cn","whois.cnnic.net.cn","No entries found"),
                  array("nz","whois.domainz.net.nz","Not Listed"),
                  array("plc.uk","whois.nic.uk","No match for"),
                  array("odo.br","whois.nic.br","No match"),
                  array("oop.br","whois.nic.br","No match"),
                  array("or.jp","whois.nic.ad.jp","No match"),
                  array("or.at","whois.nic.at","nothing found"),
                array("org","whois.pir.org","NOT FOUND"),
                  array("org.au","whois.aunic.net","No Data Found"),
                  array("org.br","whois.nic.br","No match"),
                  array("org.cn","whois.cnnic.net.cn","No entries found"),
                  array("org.hk","whois.hknic.net.hk","No Match for"),
                  array("org.lu","whois.dns.lu","No entries found"),
                  array("org.ru","whois.ripn.ru","No entries found"),
                  array("org.tr","whois.nic.tr","Not found in database"),
                  array("org.tw","whois.twnic.net","NO MATCH TIP"),
                array("org.uk","whois.nic.uk","No match for"),
                  array("pk","whois.pknic.net","is not registered"),
                  array("pl","whois.ripe.net","No information about"),
                  array("pol.tr","whois.nic.tr","Not found in database"),
                  array("pp.ru","whois.ripn.ru","No entries found"),
                  array("ppg.br","whois.nic.br","No match"),
                  array("pro.br","whois.nic.br","No match"),
                  array("psi.br","whois.nic.br","No match"),
                  array("psc.br","whois.nic.br","No match"),
                  array("pt","whois.ripe.net","No match for"),
                  array("qh.cn","whois.cnnic.net.cn","No entries found"),
                  array("qsl.br","whois.nic.br","No match"),
                  array("rec.br","whois.nic.br","No match"),
                  array("ro","whois.ripe.net","No entries found"),
                  array("ru","whois.ripn.ru","No entries found"),
                  array("sc.cn","whois.cnnic.net.cn","No entries found"),
                  array("sd.cn","whois.cnnic.net.cn","No entries found"),
                  array("se","whois.nic-se.se","No data found"),
                  array("se.com","whois.nomination.net","No match for"),
                  array("se.net","whois.nomination.net","No match for"),
                  array("sg","whois.nic.net.sg","NO entry found"),
                  array("sh","whois.nic.sh","No match for"),
                  array("sh.cn","whois.cnnic.net.cn","No entries found"),
                  array("si","whois.arnes.si","No entries found"),
                  array("sk","whois.ripe.net","no entries found"),
                  array("slg.br","whois.nic.br","No match"),
                  array("sm","whois.ripe.net","no entries found"),
                  array("sn.cn","whois.cnnic.net.cn","No entries found"),
                  array("srv.br","whois.nic.br","No match"),
                  array("st","whois.nic.st","No entries found"),
                  array("su","whois.ripe.net","No entries found"),
                  array("sx.cn","whois.cnnic.net.cn","No entries found"),
                  array("tc","whois.adamsnames.tc","is not registered"),
                array("tel","whois.nic.tel","Not found:"),
                  array("tel.tr","whois.nic.tr","Not found in database"),
                  array("th","whois.nic.uk","No entries found"),
                  array("tj.cn","whois.cnnic.net.cn","No entries found"),
                  array("tm","whois.nic.tm","No match for"),
                  array("tn","whois.ripe.net","No entries found"),
                  array("tmp.br","whois.nic.br","No match"),
                  array("to","whois.tonic.to","No match"),
                  array("trd.br","whois.nic.br","No match"),
                  array("tur.br","whois.nic.br","No match"),
                array("tv","whois.nic.tv","No match for "),
                  array("tv.br","whois.nic.br","No match"),
                  array("tw","whois.twnic.net","NO MATCH TIP"),
                  array("tw.cn","whois.cnnic.net.cn","No entries found"),
                  array("ua","whois.ripe.net","No entries found"),
                  array("uk","whois.thnic.net","No match for"),
                  array("uk.com","whois.nomination.net","No match for"),
                  array("uk.net","whois.nomination.net","No match for"),
                array("us","whois.nic.us","Not found"),
                  array("va","whois.ripe.net","No entries found"),
                  array("vet.br","whois.nic.br","No match"),
                  array("vg","whois.adamsnames.tc","is not registered"),
                  array("wattle.id.au","whois.aunic.net","No Data Found"),
                  array("web.tr","whois.nic.tr","Not found in database"),
                array("ws","whois.worldsite.ws","No match for"),
                  array("xj.cn","whois.cnnic.net.cn","No entries found"),
                  array("xz.cn","whois.cnnic.net.cn","No entries found"),
                  array("yn.cn","whois.cnnic.net.cn","No entries found"),
                  array("yu","whois.ripe.net","No entries found"),
                  array("za","whois.frd.ac.za","No match for"),
                  array("zlg.br","whois.nic.br","No match"),
                  array("zj.cn","whois.cnnic.net.cn","No entries found"),
        );

        return $servers;
    }

    /**
     * Constructor of class domain
     * @param string	$str_domainname    the full name of the domain
     * @desc Constructor of class domain
     */
    public function __construct($str_domainname)
    {
        $this->domain=$str_domainname;
    }

    /**
     * Returns the whois data of the domain
     * @return string $whoisdata Whois data as string
     * @desc Returns the whois data of the domain
     */
    public function info()
    {
        $tldname=$this->get_tld();
        $domainname=$this->get_domain();
        $whois_server=$this->get_whois_server();

        // If tldname have not been found
        if($whois_server == "") {
            throw new Box_Exception('No whois server for this tld in list!');
        }

        // Getting whois information
        $fp = @fsockopen($whois_server,43);

        if (!$fp) {
            throw new Box_Exception('Whois server '. $whois_server .' is not available');
        }

        $dom=$domainname.".".$tldname;

        // New IDN
        if($tldname=="de") {
            fputs($fp, "-C ISO-8859-1 -T dn $dom\r\n");
        } else {
            fputs($fp, "$dom\r\n");
        }

        // Getting string
        $string="";

        // Checking whois server for .com and .net
        if($tldname=="com" || $tldname=="net" || $tldname=="edu") {
            while(!feof($fp)) {
                $line=trim(fgets($fp,128));

                $string.=$line;

                $lineArr=explode(":",$line);

                if(strtolower($lineArr[0])=="whois server") {
                    $whois_server=trim($lineArr[1]);
                }
            }
            // Getting whois information
            $fp = fsockopen($whois_server,43);
            if(!is_resource($fp)) {
                throw new \Box_Exception('Could not connect to whois :server server', array(':server'=>$whois_server));
            }

            $dom=$domainname.".".$tldname;
            fputs($fp, "$dom\r\n");

            // Getting string
            $string="";

            while(!feof($fp)) {
                $string.=fgets($fp,128);
            }

            // Checking for other tld's
        } else {
            while(!feof($fp)) {
                $string.=fgets($fp,128);
            }
        }
        fclose($fp);
        return $string;
    }

    /**
     * Returns name of the whois server of the tld
     * @return string $server the whois servers hostname
     * @desc Returns name of the whois server of the tld
     */
    private function get_whois_server()
    {
        $found=false;
        $tldname=$this->get_tld();
        $servers = self::getServers();
        $counted = count($servers);
        for($i=0;$i<$counted;$i++) {
            if($servers[$i][0]==$tldname) {
                $server=$servers[$i][1];
                $found=true;
            }
        }

        if(!$found) {
            throw new Exception(sprintf('Whois server for TLD %s not found', $tldname));
        }
        return $server;
    }

    /**
     * Returns the tld of the domain without domain name
     * @return string $tldname the TLDs name without domain name
     * @desc Returns the tld of the domain without domain name
     */
    private function get_tld()
    {
        // Splitting domainname
        $domain=explode(".",$this->domain);

        if(count($domain)>2) {
            $domainname=$domain[0];
            $counted = count($domain);
            for($i=1;$i<$counted;$i++) {
                if($i==1) {
                    $tldname=$domain[$i];
                }else {
                    $tldname.=".".$domain[$i];
                }
            }
        } else {
            $domainname=$domain[0];
            $tldname=$domain[1];
        }
        return $tldname;
    }

    /**
     * Returns all TLDs which are supported by the class
     * @return string $tlds all TLDs as array
     * @desc Returns all TLDs which are supported by the class
     */
    public static function getTlds()
    {
        $tlds="";
        $servers = self::getServers();
        $counted = count($servers);
        for($i=0;$i<$counted;$i++) {
            $tlds[$i]=$servers[$i][0];
        }
        return $tlds;
    }

    /**
     * Returns the name of the domain without tld
     * @return string $domain the domains name without tld name
     * @desc Returns the name of the domain without tld
     */
    private function get_domain()
    {
        // Splitting domainname
        $domain=explode(".",$this->domain);
        return $domain[0];
    }

    /**
     * Returns the string which will be returned by the whois server of the tld if a domain is avalable
     * @return string $notfound  the string which will be returned by the whois server of the tld if a domain is avalable
     * @desc Returns the string which will be returned by the whois server of the tld if a domain is avalable
     */
    private function get_notfound_string()
    {
        $found=false;
        $tldname=$this->get_tld();
        $servers = self::getServers();
        $counted = count($servers);
        for($i=0;$i<$counted;$i++) {
            if($servers[$i][0]==$tldname) {
                $notfound=$servers[$i][2];
            }
        }
        return $notfound;
    }

    /**
     * Returns if the domain is available for registering
     * @return boolean $is_available Returns 1 if domain is available and 0 if domain isn't available
     * @desc Returns if the domain is available for registering
     */
    public function isAvailable()
    {
        $whois_string=$this->info(); // Gets the entire WHOIS query from registrar
        $not_found_string=$this->get_notfound_string(); // Gets 3rd item from array
        $domain=$this->domain; // Gets current domain being queried

        $whois_string2=@preg_replace("/$domain/","",$whois_string);

        $whois_string =@preg_replace("/\s+/"," ",$whois_string); //Replace whitespace with single space

        $array=explode(":",$not_found_string);

        if($array[0]=="MAXCHARS") {
            if(strlen($whois_string2)<=$array[1]) {
                return true;
            }else {
                return false;
            }
        }else {
            if(preg_match("/".$not_found_string."/i",$whois_string)) {
                return true;
            }else {
                return false;
            }
        }
    }
}
