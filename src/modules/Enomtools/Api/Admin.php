<?php


namespace Box\Mod\Enomtools\Api;

class Admin extends \Api_Abstract{

    public function get_all_tlds($data){
        $duration = 1;
		$params = [
			'Command' => 'PE_GetRetailPricing',
            'TLDOnly' => 1,
            'Years' => $duration
		];
        $EnomtoolsService = $this->di['mod_service']('Enomtools');
        $xml = $EnomtoolsService->makeEnomRequest($params);
        $minperiod = isset($xml->MinPeriod) ? (int) $xml->MinPeriod : 1;
        
        $tldList = [];
        $tlds = $xml->xpath('//pricestructure/tld');
		foreach ($tlds as $tld) {
            $tldList[] = [
                'tld' => '.' . (string) $tld->tld,
                'registerprice' => (string) $tld->registerprice,
                'renewprice' => (string) $tld->renewprice,
                'transferprice' => (string) $tld->transferprice,
                'duration' => $duration,
                'registerenabled' => (bool) $tld->registerenabled,
                'transferenabled' => (bool) $tld->transferenabled,
                'minperiod' => $minperiod
            ];
		}
        return $tldList;
    }

    public function get_existing_tlds(){
        $EnomtoolsService = $this->di['mod_service']('Enomtools');
        $ExistingTLDs = $EnomtoolsService->getExistingTLDs();
        return $ExistingTLDs;
    }

    public function post_submit_TLDs($data){
        $TLDList = $data;
        $EnomtoolsService = $this->di['mod_service']('Enomtools');
        $success = $EnomtoolsService->saveTlds($TLDList);

        return $success;
    }

}