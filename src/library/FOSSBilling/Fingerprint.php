<?php
declare(strict_types=1);
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

class Fingerprint
{
    private array $fingerprintItems;

    public function __construct()
    {
        $agentDetails = $this->extractAgentInfo();

        /**
         * Sets up the fingerprint info for the existing request.
         * 'wrongWeight' is used to weigh specific parameters.
         *      Example: The agent string is weight as 3 different properties, so a browser update wouldn't be enough to make the fingerprint fail it's check, but with only one or two more items that differ it will.
         */
        $this->fingerprintItems = [
            'agentString' => [
                'source' => $agentDetails['userAgent'],
                'wrongWeight' => 2,
            ],
            'browser' => [
                'source' => $agentDetails['browser'],
                'wrongWeight' => 100, // Always fail if this doesn't match.
            ],
            'browserVersion' => [
                'source' => $agentDetails['browserVersion'],
                'wrongWeight' => 2, // Always fail if this doesn't match.
            ],
            'os' => [
                'source' => $agentDetails['os'],
                'wrongWeight' => 100, // Always fail if this doesn't match.
            ],
            'ip' => [
                'source' => $_SERVER['REMOTE_ADDR'] ?? '',
                'wrongWeight' => 3,
            ],
            'referrer' => [
                'source' => $_SERVER['HTTP_REFERER'] ?? '',
                'wrongWeight' => 1,
            ],
            'forwardedFor' => [
                'source' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
                'wrongWeight' => 1,
            ],
            'language' => [
                'source' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
                'wrongWeight' => 1,
            ],
            'encoding' => [
                'source' => $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
                'wrongWeight' => 1,
            ],
            'memory' => [
                'source' => $_SERVER['HTTP_DEVICE_MEMORY'] ?? '',
                'wrongWeight' => 3,
            ],
            'upgradeRequests' => [
                'source' => $_SERVER['HTTP_UPGRADE_INSECURE_REQUESTS'] ?? '',
                'wrongWeight' => 1,
            ],
        ];
    }

    public function fingerprint(): array
    {
        $fingerprint = [];

        foreach ($this->fingerprintItems as $name => $properties) {
            if (!empty($properties['source'])) {
                $fingerprint[$name] = hash('md5', $properties['source']);
            }
        }

        return $fingerprint;
    }

    public function checkFingerprint(array $fingerprint): bool
    {
        $itemCount = 0;
        $scoreSubtract = 0;

        foreach ($this->fingerprintItems as $name => $properties) {
            $exitsInFingerprint = array_key_exists($name, $fingerprint);
            $exitsInCurrentFingerprint = !empty($properties['source']);

            if ((!$exitsInFingerprint && $exitsInCurrentFingerprint) || ($exitsInFingerprint && !$exitsInCurrentFingerprint)) {
                //The property exists in one fingerprint and not the other, so we increment the total count and deduct from the score.
                $itemCount++;
                $scoreSubtract += $properties['wrongWeight'];
            } elseif (!$exitsInFingerprint && !$exitsInCurrentFingerprint) {
                // Do nothing in this case, as the property isn't in the existing fingerprint or the current one.
            } elseif ($fingerprint[$name] !==  hash('md5', $properties['source'])) {
                $itemCount++;
                $scoreSubtract += $properties['wrongWeight'];
            } elseif ($fingerprint[$name] ===  hash('md5', $properties['source'])) {
                $itemCount++;
            }
        }

        // Remove the total score from the total number of items. The final score must be at least half in order for the fingerprint to be considered valid still.
        $finalScore = $itemCount - $scoreSubtract;
        return $finalScore >= ($itemCount / 2);
    }

    private function extractAgentInfo()
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Extract the browser name
        if (preg_match('/(?:Chrome|CriOS)\/([0-9\.]+)/', $userAgent, $matches)) {
            $browser = 'Chrome';
            $version = $matches[1];
        } elseif (preg_match('/Firefox\/([0-9\.]+)/', $userAgent, $matches)) {
            $browser = 'Firefox';
            $version = $matches[1];
        } elseif (preg_match('/Safari\/([0-9\.]+)/', $userAgent, $matches)) {
            $browser = 'Safari';
            $version = $matches[1];
        } else {
            $browser = 'Unknown';
            $version = 'Unknown';
        }

        // Extract the operating system
        if (preg_match('/Windows NT ([0-9\.]+)/', $userAgent, $matches)) {
            $os = 'Windows NT ' . $matches[1];
        } elseif (preg_match('/Mac OS X ([0-9_]+)/', $userAgent, $matches)) {
            $os = 'Mac OS X';
        } elseif (preg_match('/Linux/', $userAgent)) {
            $os = 'Linux';
        } else {
            $os = 'Unknown';
        }

        return [
            'browser' => $browser,
            'browserVersion' => $version,
            'os' => $os,
            'userAgent' => $userAgent,
        ];
    }
}
