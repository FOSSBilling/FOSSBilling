<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Imageproxy\Api;

/**
 * Image Proxy Admin API.
 *
 * Handles administrative API endpoints for the Image Proxy module.
 * Note: Image serving is handled directly in Controller, not API layer,
 * to avoid session issues with binary content.
 */
class Admin extends \Api_Abstract
{
    /**
     * Update module configuration settings.
     *
     * @param array<string, mixed> $data Configuration data containing:
     *                                    - max_size_mb: Maximum image size in MB (1-50)
     *                                    - timeout_seconds: Request timeout in seconds (1-30)
     *                                    - max_duration_seconds: Maximum request duration in seconds (1-60)
     *
     * @return bool True on success
     *
     * @throws \FOSSBilling\InformationException If parameters are invalid or out of range
     */
    public function update_config($data): bool
    {
        $max_size_mb = $data['max_size_mb'] ?? 5;
        $timeout_seconds = $data['timeout_seconds'] ?? 5;
        $max_duration_seconds = $data['max_duration_seconds'] ?? 10;

        // Validate inputs
        if ($max_size_mb < 1 || $max_size_mb > 50) {
            throw new \FOSSBilling\InformationException('Max size must be between 1 and 50 MB');
        }

        if ($timeout_seconds < 1 || $timeout_seconds > 30) {
            throw new \FOSSBilling\InformationException('Timeout must be between 1 and 30 seconds');
        }

        if ($max_duration_seconds < 1 || $max_duration_seconds > 60) {
            throw new \FOSSBilling\InformationException('Max duration must be between 1 and 60 seconds');
        }

        if ($max_duration_seconds < $timeout_seconds) {
            throw new \FOSSBilling\InformationException('Max duration must be greater than or equal to timeout');
        }

        $config = [
            'ext' => 'mod_imageproxy',
            'max_size_mb' => (int) $max_size_mb,
            'timeout_seconds' => (int) $timeout_seconds,
            'max_duration_seconds' => (int) $max_duration_seconds,
        ];

        $this->di['mod_service']('extension')->setConfig($config);

        $this->di['logger']->info('Updated Imageproxy module settings');

        return true;
    }
}
