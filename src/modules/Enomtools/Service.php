<?php

namespace Box\Mod\Enomtools;

use FOSSBilling\InformationException;

class Service{
    protected $di;


    public function setDi(\Pimple\Container|null $di): void{
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container{
        return $this->di;
    }
    // public function getModulePermissions(): array{

    // }

    public function install(): bool{
        return true;
    }

    public function uninstall(): bool{
        return true;
    }

    public function update(array $manifest): bool{
        return true;
    }
    

    public function makeEnomRequest($params){
        $config = $this->getEnomConfig();
        $debug = false;


		$params['PW'] = $config['password'];
		$params = array_merge([
			'UID' => $config['username'],
			'Key' => $config['api_key'],
			'ResponseType' => 'XML'
		], $params);
		ksort($params);
		$url = $config['endpoint'] . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
	
		if ($debug) {
			error_log("eNom API request URL: " . $url);
		}

		$contextOptions = [
			'http' => [
				'method' => 'GET', 
				'ignore_errors' => true 
			]
		];
	
		//create stream
		$context = stream_context_create($contextOptions);
		$response = @file_get_contents($url, false, $context);
		if ($response === false) {
			$error = error_get_last();
			if ($error !== null) {
				error_log("Failed to retrieve API response: " . $error['message']);
			}
			throw new InformationException("API request to eNom failed: No response received.");
		}

		if ($debug) {
			error_log("eNom API response: " . $response);
		}
		
		$xml = simplexml_load_string($response);
		//check for ErrCount
		if (isset($xml->ErrCount) && (int) $xml->ErrCount > 0) {
			//show all errors
			$errors = $xml->errors;
			$errorMessages = [];
			for ($i = 1; $i <= (int) $xml->ErrCount; $i++) {
				$errorKey = "Err{$i}";
				if (isset($errors->$errorKey)) {
					$errorMessages[] = (string) $errors->$errorKey;
				}
			}
			$errorMessage = implode("; ", $errorMessages);
			throw new InformationException("eNom API error: {$errorMessage}");
		}
		return $xml;
    }
	

	public function getExistingTLDs(){
		$TLDList = $this->di['db']->find('Tld');
        $TLDData = [];
        foreach ($TLDList as $TLD) {
            $TLDData[] = [
                'tld' => $TLD->tld,
				'price_registration' => $TLD-> price_registration,
				'price_renew' => $TLD-> price_renew,
				'price_transfer' => $TLD-> price_transfer,
            ];
        }

        $logDataString = json_encode($TLDData, JSON_PRETTY_PRINT);

		return $TLDData;
	}

	public function saveTlds($AllTLDList){
		$success = true;
		$TLDNameArr = array_keys($AllTLDList);
		$existingTlds = $this->getExistingTLDsFromList($TLDNameArr);
		$existTldsNameArr = [];
		//update the existing TLDs
		foreach ($existingTlds as $tld) {
			$tld_id = $tld['id'];
			$tld_name = $tld['tld'];
			$existTldsNameArr[$tld_name] = 1;
			
			$newPrices = $AllTLDList[$tld_name];
			$query = "UPDATE tld SET 
						price_registration = :registerprice, 
						price_renew = :renewprice, 
						price_transfer = :transferprice, 
						updated_at = NOW() 
					WHERE id = :id";
			
			$params = [
				':registerprice' => $newPrices['registerprice'],
				':renewprice' => $newPrices['renewprice'],
				':transferprice' => $newPrices['transferprice'],
				':id' => $tld_id
			];

			try {
				$this->di['db']->exec($query, $params);
			} catch (\Exception $e) {
				error_log($e->getMessage());
				return false;
			}
		}

		//insert the new TLDs
		$registrarId = $this->getEnomRegistrarId();
		$newTldsArr = $this->getNewTldsFromArr($TLDNameArr, $existTldsNameArr);

		foreach ($newTldsArr as $tldName) {
			$tld = $AllTLDList[$tldName];
			
			//insert into the database
			$model = $this->di['db']->dispense('Tld');
			$model->tld = $tldName;
			$model->tld_registrar_id = $registrarId;
			$model->price_registration = $tld["registerprice"];
			$model->price_renew = $tld["renewprice"];
			$model->price_transfer = $tld["transferprice"];
			$model->min_years = $tld["minperiod"];
			$model->allow_register = $tld["registerenabled"] ? 1 : 0;
			$model->allow_transfer = $tld["transferenabled"] ? 1 : 0;
			$model->updated_at = date('Y-m-d H:i:s');
			$model->created_at = date('Y-m-d H:i:s');
			$model->active = $tld["activation"] ? 1 : 0;

			try {
				$this->di['db']->store($model);
			} catch (\Exception $e) {
				error_log($e->getMessage());
				return false;
			}
		}

		return $success;
	}

	public function getExistingTLDsFromList($TLDNameArr){
		try {
			$quotedNames = array_map(function($name) {
				return "'" . addslashes($name) . "'";
			}, $TLDNameArr);
			$placeholders = implode(',', $quotedNames);
			$query = "SELECT * FROM tld WHERE tld IN ($placeholders)";
			return $this->di['db']->getAll($query);
        } catch (\Exception $e) {
            error_log($e->getMessage());
		}
	}
	public function getNewTldsFromArr($AllTldsArr, $existTldsNameArr){
		$result = [];
		foreach ($AllTldsArr as $Tld) {
			if (!array_key_exists($Tld, $existTldsNameArr)){
				$result[] = $Tld;
			}
		}
		return $result;
	}

    protected function getEnomConfig(){
        $bindings = [':registrar' => 'eNom'];
        $tldRegistrar = $this->di['db']->findOne('tld_registrar', 'registrar = :registrar AND config IS NOT NULL ', $bindings);
        if ($tldRegistrar == NULL){
            throw new InformationException("Enom is not configured");
        }

        $config = $tldRegistrar->config;
        if (is_string($config) && json_validate($config)) {
            return json_decode($config, true);
        }

        return [];
    }

    protected function getEnomRegistrarId(){
        $bindings = [':registrar' => 'eNom'];
        $tldRegistrar = $this->di['db']->findOne('tld_registrar', 'registrar = :registrar AND config IS NOT NULL ', $bindings);
        if ($tldRegistrar == NULL){
            throw new InformationException("Enom is not configured");
        }

        return $tldRegistrar->id;
    }
}
