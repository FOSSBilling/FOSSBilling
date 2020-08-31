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

    /**
     * BoxBilling extensions API wrapper
     */
    class Box_Extension
    {

        /**
         * @var \Box_Ii
         */
        protected $di = null;

        /**
         * @param \Box_Ii $di
         */
        public function setDi($di)
        {
            $this->di = $di;
        }

        /**
         * @return \Box_Ii
         */
        public function getDi()
        {
            return $this->di;
        }

        const TYPE_MOD      = 'mod';
        const TYPE_THEME    = 'theme';
        const TYPE_PG       = 'payment-gateway';
        const TYPE_SM       = 'server-manager';
        const TYPE_DR       = 'domain-registrar';
        const TYPE_HOOK     = 'hook';
        const TYPE_TRANSLATION    = 'translation';

        private $_url = 'https://extensions.boxbilling.com/api/';

        public function getExtension($id, $type = Box_Extension::TYPE_MOD)
        {
            $params = array();
            $params['return'] = 'manifest';
            $params['type'] = $type;
            $params['id'] = $id;
            return $this->_request('guest/extension/get', $params);
        }

        public function getLatestExtensionVersion($id, $type = Box_Extension::TYPE_MOD)
        {
            $params = array();
            $params['type'] = $type;
            $params['id'] = $id;
            return $this->_request('guest/extension/version', $params);
        }

        public function getLatest($type = null)
        {
            $params = array();
            $params['return'] = 'manifest';
            if(!empty($type)) {
                $params['type'] = $type;
            }
            return $this->_request('guest/extension/search', $params);
        }

        /**
         * @param string $call
         */
        private function _request($call, array $params)
        {
            $params['bb_version'] = Box_Version::VERSION;
            $url = $this->_url.$call.'?'.http_build_query($params);
            $curl = new Box_Curl($url, 5);
            $curl->request();
            $response = $curl->getBody();
            $json = json_decode($response, 1);

            if(is_null($json)) {
                throw new \Box_Exception('Unable to connect to BoxBilling extensions site.', null, 1545);
            }

            if(isset($json['error']) && is_array($json['error'])) {
                throw new Exception($json['error']['message'], 746);
            }
            return $json['result'];
        }
    }