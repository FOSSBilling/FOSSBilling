<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Email\Api;

class Guest extends \Api_Abstract
{
    public function google_oauth2_callback()
    {
        try {
            $code = $_GET['code'] ?? null;
            if (!$code) {
                throw new \FOSSBilling\Exception('Authorization code is missing');
            }

            $mod = $this->di['mod']('email');
            $config = $mod->getConfig();

            if (empty($config['google_oauth2_client_id']) || empty($config['google_oauth2_client_secret'])) {
                throw new \FOSSBilling\Exception('Google OAuth2 credentials are not configured');
            }

            $siteUrl = rtrim($this->di['config']['url'], '/');
            $redirectUri = $siteUrl . '/api/guest/email/google_oauth2_callback';

            $transport = new \FOSSBilling\Mail\Transport\GoogleOAuth2Transport(
                $config['google_oauth2_client_id'],
                $config['google_oauth2_client_secret']
            );
            
            $tokens = $transport->fetchAccessToken($code, $redirectUri);

            if (!isset($tokens['access_token'])) {
                throw new \FOSSBilling\Exception('No access token received from Google');
            }

            $config['google_oauth2_access_token'] = $tokens['access_token'];
            if (isset($tokens['refresh_token'])) {
                $config['google_oauth2_refresh_token'] = $tokens['refresh_token'];
            }

            $mod->setConfig($config);

            return [
                'status' => 'success',
                'message' => 'Successfully authorized with Google OAuth2',
                'redirect' => $this->di['url']->link('extension/settings/email')
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Authentication failed: ' . $e->getMessage(),
                'redirect' => $this->di['url']->link('extension/settings/email')
            ];
        }
    }
} 
